<?php

class Auth
{
    /** Erro do último login: 'credenciais' | 'bloqueado' | '' */
    protected string $lastError = "";

    // Lockout por conta: após 5 falhas, bloqueio com duração crescente
    private const LOCKOUT_THRESHOLD = 5;
    private const LOCKOUT_DURATIONS = [300, 900, 3600]; // 5min, 15min, 1h

    public function getLastError(): string
    {
        return $this->lastError;
    }

    /**
     * Registra evento de auditoria de login (falha/bloqueio)
     */
    private function registrarAuditoria(?int $usuarioId, string $nome, string $acao, string $descricao): void
    {
        try {
            global $pdo;
            $pdo->prepare("
                INSERT INTO audit_logs (usuario_id, usuario_nome, acao, entidade, descricao, ip, user_agent)
                VALUES (?, ?, ?, 'SESSAO', ?, ?, ?)
            ")->execute([
                $usuarioId,
                $nome,
                $acao,
                $descricao,
                $_SERVER["REMOTE_ADDR"] ?? null,
                substr($_SERVER["HTTP_USER_AGENT"] ?? "", 0, 500),
            ]);
        } catch (PDOException $e) {
            error_log("Erro ao registrar auditoria de login: " . $e->getMessage());
        }
    }

    /**
     * Incrementa contador de falhas (atomicamente) e aplica bloqueio crescente
     */
    private function registrarFalhaLogin(array $usuario): void
    {
        global $pdo;

        $stmt = $pdo->prepare(
            "UPDATE usuarios SET failed_attempts = failed_attempts + 1
             WHERE id = ? RETURNING failed_attempts"
        );
        $stmt->execute([$usuario["id"]]);
        $tentativas = (int) $stmt->fetchColumn();

        if ($tentativas >= self::LOCKOUT_THRESHOLD) {
            // Escala: 5ª falha -> 5min, 6ª -> 15min, 7ª+ -> 1h (teto)
            $idx = min($tentativas - self::LOCKOUT_THRESHOLD, count(self::LOCKOUT_DURATIONS) - 1);
            $duracao = self::LOCKOUT_DURATIONS[$idx];
            $pdo->prepare(
                "UPDATE usuarios SET locked_until = NOW() + (? * INTERVAL '1 second') WHERE id = ?"
            )->execute([$duracao, $usuario["id"]]);
            $this->registrarAuditoria(
                (int) $usuario["id"],
                $usuario["nome"],
                "LOGIN_FALHA",
                "Senha incorreta ({$tentativas}ª tentativa). Conta bloqueada por " . ((int) ($duracao / 60)) . " min."
            );
        } else {
            $this->registrarAuditoria(
                (int) $usuario["id"],
                $usuario["nome"],
                "LOGIN_FALHA",
                "Senha incorreta ({$tentativas}ª tentativa)."
            );
        }
    }

    /**
     * Realiza login do usuário e registra auditoria
     */
    public function login(string $email, string $password): bool
    {
        global $pdo;

        $stmt = $pdo->prepare(
            "SELECT id, nome, email, password, cargo, ativo, failed_attempts, locked_until, session_version
             FROM usuarios
             WHERE email = ?"
        );
        $stmt->execute([$email]);
        $usuario = $stmt->fetch();

        // Hash dummy: iguala o tempo de resposta para não revelar por timing
        // se o email existe ou não.
        static $dummyHash = null;
        if ($dummyHash === null) {
            $dummyHash = password_hash("dummy-password-timing-equalizer", PASSWORD_DEFAULT);
        }

        if (!$usuario) {
            password_verify($password, $dummyHash);
            $this->lastError = "credenciais";
            $this->registrarAuditoria(null, $email, "LOGIN_FALHA", "Tentativa de login com email inexistente: {$email}");
            return false;
        }

        if (!$usuario["ativo"]) {
            password_verify($password, $dummyHash);
            $this->lastError = "credenciais";
            $this->registrarAuditoria((int) $usuario["id"], $usuario["nome"], "LOGIN_FALHA", "Tentativa de login em conta desativada");
            return false;
        }

        // Lockout: bloqueia ANTES de validar a senha se a conta estiver travada
        if (!empty($usuario["locked_until"])) {
            $lockedUntil = new DateTime($usuario["locked_until"]);
            if ($lockedUntil > new DateTime()) {
                $this->lastError = "bloqueado";
                $this->registrarAuditoria(
                    (int) $usuario["id"],
                    $usuario["nome"],
                    "LOGIN_BLOQUEADO",
                    "Tentativa de login em conta bloqueada (até {$usuario['locked_until']})"
                );
                return false;
            }
        }

        if (!password_verify($password, $usuario["password"])) {
            $this->registrarFalhaLogin($usuario);
            $this->lastError = "credenciais";
            return false;
        }

        // Sucesso: reseta contador e bloqueio
        $pdo->prepare("UPDATE usuarios SET failed_attempts = 0, locked_until = NULL WHERE id = ?")
            ->execute([$usuario["id"]]);

        $this->lastError = "";
        $this->iniciarSessao();

        // Sessão rigorosa: regenera ID e registra fingerprint do navegador
        session_regenerate_id(true);
        $_SESSION["usuario_id"] = $usuario["id"];
        $_SESSION["usuario_nome"] = $usuario["nome"];
        $_SESSION["email"] = $usuario["email"];
        $_SESSION["cargo"] = $usuario["cargo"];
        $_SESSION["login_time"] = time();
        $_SESSION["last_activity"] = time();
        $_SESSION["session_version"] = (int) $usuario["session_version"];
        $_SESSION["user_agent"] = $_SERVER["HTTP_USER_AGENT"] ?? "";
        $_SESSION["ip_address"] = $_SERVER["REMOTE_ADDR"] ?? "";

        // Registra auditoria de login
        try {
            $pdo->prepare("
                INSERT INTO audit_logs (usuario_id, usuario_nome, acao, entidade, descricao, ip, user_agent)
                VALUES (?, ?, 'LOGIN', 'SESSAO', 'Usuário entrou no sistema', ?, ?)
            ")->execute([
                $usuario["id"],
                $usuario["nome"],
                $_SERVER["REMOTE_ADDR"] ?? null,
                substr($_SERVER["HTTP_USER_AGENT"] ?? "", 0, 500),
            ]);
        } catch (PDOException $e) {
            error_log("Erro ao registrar login: " . $e->getMessage());
        }

        if ($usuario["cargo"] === "CLIENTE") {
            Carrinho::mesclarSessaoComUsuario((int) $usuario["id"]);
        }

        return true;
    }

    public function verificarLogin(): void
    {
        $this->iniciarSessao();

        if (!isset($_SESSION["usuario_id"])) {
            header("Location: " . pageUrl("login.php"));
            exit;
        }

        if (!$this->validarSessao()) {
            $this->logout();
            return;
        }
    }

    /**
     * Valida rigorosamente a sessão logada (timeout de inatividade,
     * fingerprint do navegador, IP e session_version) SEM redirecionar.
     * Retorna false se a sessão deve ser encerrada — assim endpoints de
     * API podem responder com JSON 401 em vez de redirect HTML.
     * Atualiza last_activity quando a sessão é válida.
     */
    public function validarSessao(): bool
    {
        if (!isset($_SESSION["usuario_id"])) {
            return false;
        }

        // --- SESSÃO RIGOROSA ---

        // 1) Timeout de inatividade (30 min)
        $timeout = 1800; // 30 minutos
        if (isset($_SESSION["last_activity"]) && (time() - $_SESSION["last_activity"] > $timeout)) {
            return false;
        }
        $_SESSION["last_activity"] = time();

        // 2) Valida fingerprint do navegador (User-Agent)
        $userAgent = $_SERVER["HTTP_USER_AGENT"] ?? "";
        if (isset($_SESSION["user_agent"]) && $_SESSION["user_agent"] !== $userAgent) {
            return false;
        }

        // 3) Valida IP. A exceção para 127.0.0.1/::1 só vale em
        // APP_ENV=development (ex.: proxy local do dev); em produção a
        // validação é sempre rigorosa.
        $ip = $_SERVER["REMOTE_ADDR"] ?? "";
        $appEnv = strtolower(trim($_ENV["APP_ENV"] ?? "production"));
        $localhostPermitido = $appEnv === "development" && in_array($ip, ["127.0.0.1", "::1"], true);
        if (isset($_SESSION["ip_address"]) && $_SESSION["ip_address"] !== $ip && !$localhostPermitido) {
            return false;
        }

        // 4) Invalida sessões abertas antes de uma troca de senha (ex.: reset).
        // Se session_version no banco for maior que a da sessão, esta sessão
        // é anterior à troca de senha e deve ser encerrada.
        global $pdo;
        $stmt = $pdo->prepare("SELECT session_version FROM usuarios WHERE id = ?");
        $stmt->execute([(int) $_SESSION["usuario_id"]]);
        $dbVersion = $stmt->fetchColumn();
        if ($dbVersion === false || (int) $dbVersion > (int) ($_SESSION["session_version"] ?? 0)) {
            return false;
        }

        return true;
    }

    public function verificarCliente(): void
    {
        $this->verificarLogin();

        if (!isCliente() && !isAdmin()) {
            header("Location: " . pageUrl("loja.php"));
            exit;
        }
    }

    public function verificarAdmin(): void
    {
        $this->verificarLogin();

        if (!isAdmin()) {
            header("Location: " . pageUrl("loja.php"));
            exit;
        }
    }

    public function redirectPosLogin(): void
    {
        if (isAdmin()) {
            header("Location: " . pageUrl("admin/dashboard.php"));
        } else {
            header("Location: " . pageUrl("loja.php"));
        }
        exit;
    }

    /**
     * Encerra sessão e registra auditoria de logout
     */
    public function logout(): void
    {
        $this->iniciarSessao();
        $usuarioId = $_SESSION["usuario_id"] ?? null;
        $usuarioNome = $_SESSION["usuario_nome"] ?? null;

        // Registra auditoria de logout
        try {
            global $pdo;
            $pdo->prepare("
                INSERT INTO audit_logs (usuario_id, usuario_nome, acao, entidade, descricao, ip, user_agent)
                VALUES (?, ?, 'LOGOUT', 'SESSAO', 'Usuário saiu do sistema', ?, ?)
            ")->execute([
                $usuarioId,
                $usuarioNome,
                $_SERVER["REMOTE_ADDR"] ?? null,
                substr($_SERVER["HTTP_USER_AGENT"] ?? "", 0, 500),
            ]);
        } catch (PDOException $e) {
            error_log("Erro ao registrar logout: " . $e->getMessage());
        }

        $_SESSION = [];
        session_destroy();

        // Expira o cookie de sessão no navegador — o session_destroy() só
        // remove os dados no servidor; o cookie ficaria órfão no cliente.
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), "", [
                "expires"  => time() - 42000,
                "path"     => $params["path"],
                "domain"   => $params["domain"],
                "secure"   => $params["secure"],
                "httponly" => $params["httponly"],
                "samesite" => $params["samesite"],
            ]);
        }

        header("Location: " . baseUrl() . "/index.php");
        exit;
    }

    private function iniciarSessao(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
}