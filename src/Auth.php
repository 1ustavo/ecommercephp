<?php

class Auth
{
    /**
     * Realiza login do usuário e registra auditoria
     */
    public function login(string $email, string $password): bool
    {
        global $pdo;

        $stmt = $pdo->prepare(
            "SELECT id, nome, email, password, cargo, ativo
             FROM usuarios
             WHERE email = ?"
        );
        $stmt->execute([$email]);
        $usuario = $stmt->fetch();

        if (!$usuario || !$usuario["ativo"]) {
            return false;
        }

        if (!password_verify($password, $usuario["password"])) {
            return false;
        }

        $this->iniciarSessao();
        $_SESSION["usuario_id"] = $usuario["id"];
        $_SESSION["usuario_nome"] = $usuario["nome"];
        $_SESSION["email"] = $usuario["email"];
        $_SESSION["cargo"] = $usuario["cargo"];

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