<?php

function slugify(string $text): string
{
    $text = mb_strtolower($text, "UTF-8");
    $text = preg_replace("/[^\p{L}\p{N}\s-]/u", "", $text);
    $text = preg_replace("/[\s-]+/", "-", trim($text));

    return $text ?: "item";
}

function formatarMoeda(float $valor): string
{
    return "R$ " . number_format($valor, 2, ",", ".");
}

function e(?string $text): string
{
    return htmlspecialchars($text ?? "", ENT_QUOTES, "UTF-8");
}

function baseUrl(): string
{
    $script = $_SERVER["SCRIPT_NAME"] ?? "";
    $dir = str_replace("\\", "/", dirname($script));

    // Always treat anything under /pages or /api as application root.
    $dir = preg_replace("#/(pages|api)(/.*)?$#", "", $dir);

    return rtrim($dir, "/") ?: "";
}

/**
 * CSRF helpers
 */
function csrf_token(): string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_input(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Verifica CSRF a partir de POST body ou do header X-CSRF-Token (para JSON APIs).
 * Se $exitOnFail=true, responde 403 e encerra a execução.
 */
function verify_csrf_from_request(bool $exitOnFail = true): bool
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $expected = $_SESSION['csrf_token'] ?? '';
    $provided = '';

    // Prefer header (for JSON requests)
    $headers = [];
    foreach (getallheaders() as $k => $v) {
        $headers[strtolower($k)] = $v;
    }
    if (!empty($headers['x-csrf-token'])) {
        $provided = $headers['x-csrf-token'];
    } elseif (isset($_POST['csrf_token'])) {
        $provided = $_POST['csrf_token'];
    } else {
        // Try JSON body
        $raw = file_get_contents('php://input');
        $data = @json_decode($raw, true);
        if (is_array($data) && isset($data['csrf_token'])) {
            $provided = $data['csrf_token'];
        }
    }

    $ok = hash_equals((string) $expected, (string) $provided);

    if (!$ok && $exitOnFail) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['sucesso' => false, 'erro' => 'Token CSRF inválido']);
        exit;
    }

    return $ok;
}

/**
 * Rate limiting using DB table rate_limits
 * key: arbitrary string (e.g., "login:1.2.3.4" or "reset:email@example.com")
 * limit: allowed requests per windowSeconds
 */
function rate_limit_check(string $key, int $limit, int $windowSeconds): bool
{
    global $pdo;

    $now = new DateTime();
    $nowStr = $now->format('Y-m-d H:i:s');

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('SELECT id, count, window_start FROM rate_limits WHERE key_name = ? FOR UPDATE');
        $stmt->execute([$key]);
        $row = $stmt->fetch();

        if (!$row) {
            $insert = $pdo->prepare('INSERT INTO rate_limits (key_name, count, window_start) VALUES (?, ?, ?)');
            $insert->execute([$key, 1, $nowStr]);
            $pdo->commit();
            return true;
        }

        $windowStart = new DateTime($row['window_start']);
        $elapsed = $now->getTimestamp() - $windowStart->getTimestamp();

        if ($elapsed > $windowSeconds) {
            // reset window
            $update = $pdo->prepare('UPDATE rate_limits SET count = 1, window_start = ? WHERE id = ?');
            $update->execute([$nowStr, $row['id']]);
            $pdo->commit();
            return true;
        }

        if ($row['count'] + 1 > $limit) {
            $pdo->commit();
            return false;
        }

        $update = $pdo->prepare('UPDATE rate_limits SET count = count + 1 WHERE id = ?');
        $update->execute([$row['id']]);
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        try { $pdo->rollBack(); } catch (Exception $ee) {}
        error_log('Rate limit error: ' . $e->getMessage());
        // Fail-closed: se o controle não pôde ser verificado (ex.: banco fora),
        // nega a ação. Em endpoints sensíveis (login, cadastro, reset de senha)
        // segurança vale mais do que disponibilidade momentânea.
        return false;
    }
}

function asset(string $path): string
{
    $appUrl = rtrim($_ENV['APP_URL'] ?? '', '/');

    // Cache busting: evita servir JS/CSS antigos do cache do navegador
    $filePath = __DIR__ . "/../assets/" . ltrim($path, '/');
    $version = (file_exists($filePath)) ? "?v=" . filemtime($filePath) : "";

    if ($appUrl !== '') {
        return $appUrl . '/assets/' . ltrim($path, '/') . $version;
    }

    return baseUrl() . "/assets/" . ltrim($path, "/") . $version;
}

function pageUrl(string $path): string
{
    $appUrl = rtrim($_ENV['APP_URL'] ?? '', '/');
    if ($appUrl !== '') {
        return $appUrl . '/pages/' . ltrim($path, '/');
    }

    return baseUrl() . "/pages/" . ltrim($path, "/");
}

function apiUrl(string $path): string
{
    $appUrl = rtrim($_ENV['APP_URL'] ?? '', '/');
    if ($appUrl !== '') {
        return $appUrl . '/api/' . ltrim($path, '/');
    }

    return baseUrl() . "/api/" . ltrim($path, "/");
}

function isAdmin(): bool
{
    $cargo = $_SESSION["cargo"] ?? "";

    return in_array($cargo, ["ADMIN", "GERENTE", "FUNCIONARIO"], true);
}

/**
 * Hierarquia de papéis (matriz de autorização):
 *   ADMIN       = acesso total
 *   GERENTE     = aprova/recusa pedido, produtos, cupons, relatórios
 *   FUNCIONARIO = leitura de pedidos (fila) + resposta a tickets
 */
function papelNivel(string $cargo): int
{
    return match ($cargo) {
        "ADMIN" => 3,
        "GERENTE" => 2,
        "FUNCIONARIO" => 1,
        default => 0,
    };
}

/**
 * Verifica se o usuário logado possui um dos papéis informados.
 * Usa a mesma matriz de requererPapel() — para ajuste de EXIBIÇÃO
 * (esconder links/botões no front). A autorização real continua
 * sendo aplicada pelos guards nos endpoints de API.
 */
function temPapel(string ...$papeis): bool
{
    $cargo = (string) ($_SESSION["cargo"] ?? "");
    return in_array($cargo, $papeis, true);
}

/**
 * Guard para endpoints de API com controle fino de papel.
 * Valida: autenticação + sessão rigorosa (timeout de inatividade,
 * fingerprint do navegador, IP e session_version — sessões anteriores
 * a uma troca de senha são invalidadas) e o papel exigido.
 *
 * Uso tipico no topo de api/admin/*.php:
 *   requererPapel(["ADMIN", "GERENTE"]); // encerra aqui se nao atender
 */
function requererPapel(array $papeis): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    header("Content-Type: application/json; charset=utf-8");

    // 1) Autenticação + sessão rigorosa (timeout, UA, IP, session_version)
    if (!(new Auth())->validarSessao()) {
        http_response_code(401);
        echo json_encode([
            "sucesso" => false,
            "erro" => "Sessão expirada ou inválida. Faça login novamente.",
        ]);
        exit;
    }

    // 2) Autorização por papel
    $cargo = (string) ($_SESSION["cargo"] ?? "");
    if (!in_array($cargo, $papeis, true)) {
        http_response_code(403);
        echo json_encode([
            "sucesso" => false,
            "erro" => "Acesso negado. Este recurso exige papel: " . implode(", ", $papeis) . ".",
        ]);
        exit;
    }
}

/**
 * @deprecated Use requererPapel() com a lista explícita de papéis.
 * Mantido para compatibilidade: agora delega ao guard rigoroso
 * (valida timeout, fingerprint, IP e session_version) mantendo o
 * comportamento antigo de aceitar ADMIN/GERENTE/FUNCIONARIO.
 */
function requererAdminApi(): void
{
    requererPapel(["ADMIN", "GERENTE", "FUNCIONARIO"]);
}

function isCliente(): bool
{
    return ($_SESSION["cargo"] ?? "") === "CLIENTE";
}

function flash(string $key, ?string $message = null): ?string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if ($message !== null) {
        $_SESSION["flash"][$key] = $message;
        return null;
    }

    $value = $_SESSION["flash"][$key] ?? null;
    unset($_SESSION["flash"][$key]);

    return $value;
}

function getSessionId(): string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION["guest_id"])) {
        $_SESSION["guest_id"] = bin2hex(random_bytes(16));
    }

    return $_SESSION["guest_id"];
}

function corPlaceholder(string $slug): string
{
    // Paleta esmeralda sofisticada (identidade do ecommerce)
    $cores = ["#1B4332", "#2D6A4F", "#40916C", "#52B788", "#2F6B4E", "#3B7A5E"];
    $index = crc32($slug) % count($cores);

    return $cores[$index];
}

function statusPedidoLabel(string $status): string
{
    return match ($status) {
        "AGUARDANDO_APROVACAO" => "Aguardando Aprovação",
        "APROVADO" => "Aprovado",
        "PENDENTE" => "Pendente",
        "PAGO" => "Pago",
        "ENVIADO" => "Enviado",
        "ENTREGUE" => "Entregue",
        "CANCELADO" => "Cancelado",
        "RECUSADO" => "Recusado",
        default => $status,
    };
}

function statusPedidoClass(string $status): string
{
    return match ($status) {
        "AGUARDANDO_APROVACAO" => "badge-pendente",
        "APROVADO" => "badge-pago",
        "PENDENTE" => "badge-pendente",
        "PAGO" => "badge-pago",
        "ENVIADO" => "badge-enviado",
        "ENTREGUE" => "badge-entregue",
        "CANCELADO" => "badge-cancelado",
        "RECUSADO" => "badge-cancelado",
        default => "badge-default",
    };
}

/**
 * Gera a lista de páginas para paginação com "janela deslizante".
 * Sempre mostra a primeira e a última página, um intervalo ao redor
 * da página atual, e "..." quando há salto.
 *
 * Exemplos:
 *   paginacaoJanela(7, 15, 2) → [1, "..", 5, 6, 7, 8, 9, "..", 15]
 *   paginacaoJanela(2, 15, 2) → [1, 2, 3, 4, "..", 15]
 *   paginacaoJanela(1, 3, 2)  → [1, 2, 3]
 *
 * @param int $paginaAtual   Página atual (1-based)
 * @param int $totalPaginas  Total de páginas
 * @param int $vizinhos      Quantidade de páginas antes/depois da atual
 * @return array<int|string> Lista de páginas, com ".." representando reticências
 */
function paginacaoJanela(int $paginaAtual, int $totalPaginas, int $vizinhos = 2): array
{
    $paginaAtual = max(1, min($paginaAtual, $totalPaginas));
    $vizinhos = max(1, $vizinhos);

    if ($totalPaginas <= 1) {
        return [1];
    }

    $paginas = [];

    // Intervalo ao redor da página atual
    $inicio = max(2, $paginaAtual - $vizinhos);
    $fim = min($totalPaginas - 1, $paginaAtual + $vizinhos);

    // Sempre inclui a primeira página
    $paginas[] = 1;

    // Gap entre a primeira e o início do intervalo
    if ($inicio > 2) {
        $paginas[] = "..";
    } elseif ($inicio === 2) {
        $paginas[] = 2;
        $inicio = 3;
    }

    // Intervalo ao redor da página atual
    for ($i = $inicio; $i <= $fim; $i++) {
        $paginas[] = $i;
    }

    // Gap entre o fim do intervalo e a última página
    if ($fim < $totalPaginas - 1) {
        $paginas[] = "..";
    } elseif ($fim === $totalPaginas - 1) {
        $paginas[] = $totalPaginas - 1;
    }

    // Sempre inclui a última página
    if ($totalPaginas > 1) {
        $paginas[] = $totalPaginas;
    }

    // Remove duplicatas mantendo a ordem
    return array_values(array_unique($paginas, SORT_REGULAR));
}

function calcularFrete(string $metodo, float $subtotal): float
{
    return match ($metodo) {
        "sedex" => 35.90,
        "pac" => 22.50,
        "retirada" => 0,
        default => $subtotal >= 199 ? 0 : 15.00,
    };
}

/**
 * Registra ação de auditoria (log de funcionários/admin)
 */
function registrarAuditoria(
    string $acao,
    string $entidade,
    ?int $entidadeId = null,
    string $descricao = ""
): void {
    global $pdo;

    try {
        $usuarioId = $_SESSION["usuario_id"] ?? null;
        $usuarioNome = $_SESSION["usuario_nome"] ?? null;

        // Mascara dados sensíveis na descrição antes de gravar
        $descricaoSegura = mascararDadosSensiveis($descricao);

        $stmt = $pdo->prepare("
            INSERT INTO audit_logs (usuario_id, usuario_nome, acao, entidade, entidade_id, descricao, ip, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $usuarioId,
            $usuarioNome,
            $acao,
            $entidade,
            $entidadeId,
            $descricaoSegura,
            $_SERVER["REMOTE_ADDR"] ?? null,
            substr($_SERVER["HTTP_USER_AGENT"] ?? "", 0, 500),
        ]);
    } catch (PDOException $e) {
        error_log("Erro ao registrar auditoria: " . $e->getMessage());
    }
}

/**
 * Mascara dados sensíveis em strings de log/auditoria.
 * Mascara CPF (11 dígitos), telefone (10-11 dígitos) e e-mails.
 */
function mascararDadosSensiveis(string $texto): string
{
    // Mascara CPF: 123.456.789-01 -> ***.***.***-**
    $texto = preg_replace('/\b\d{3}\.?\d{3}\.?\d{3}-?\d{2}\b/', '***.***.***-**', $texto);

    // Mascara telefone: (11) 98765-4321 -> (**) *****-****
    $texto = preg_replace('/\b(\+?\d{2})?\s?\(?\d{2}\)?\s?\d{4,5}-?\d{4}\b/', '(**) *****-****', $texto ?? "");

    // Mascara e-mail: fulano@exemplo.com -> ***@***.***
    $texto = preg_replace('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', '***@***.***', $texto ?? "");

    return $texto ?? "";
}

/**
 * Busca logs de auditoria com filtros e paginação
 */
function buscarAuditoria(array $filtros = [], int $page = 1, int $limit = 10): array
{
    global $pdo;

    $page = max(1, $page);
    $limit = max(1, min(100, $limit));
    $offset = ($page - 1) * $limit;

    $sql = "SELECT * FROM audit_logs WHERE 1=1";
    $params = [];

    if (!empty($filtros["usuario_id"])) {
        $sql .= " AND usuario_id = ?";
        $params[] = (int) $filtros["usuario_id"];
    }

    if (!empty($filtros["acao"])) {
        $sql .= " AND acao ILIKE ?";
        $params[] = "%" . $filtros["acao"] . "%";
    }

    if (!empty($filtros["entidade"])) {
        $sql .= " AND entidade = ?";
        $params[] = $filtros["entidade"];
    }

    if (!empty($filtros["data_inicio"])) {
        $sql .= " AND created_at >= ?";
        $params[] = $filtros["data_inicio"];
    }

    if (!empty($filtros["data_fim"])) {
        $sql .= " AND created_at <= ?";
        $params[] = $filtros["data_fim"];
    }

    $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

/**
 * Conta o total de logs de auditoria com os mesmos filtros.
 * Usado para calcular o número de páginas.
 */
function contarAuditoria(array $filtros = []): int
{
    global $pdo;

    $sql = "SELECT COUNT(*) FROM audit_logs WHERE 1=1";
    $params = [];

    if (!empty($filtros["usuario_id"])) {
        $sql .= " AND usuario_id = ?";
        $params[] = (int) $filtros["usuario_id"];
    }

    if (!empty($filtros["acao"])) {
        $sql .= " AND acao ILIKE ?";
        $params[] = "%" . $filtros["acao"] . "%";
    }

    if (!empty($filtros["entidade"])) {
        $sql .= " AND entidade = ?";
        $params[] = $filtros["entidade"];
    }

    if (!empty($filtros["data_inicio"])) {
        $sql .= " AND created_at >= ?";
        $params[] = $filtros["data_inicio"];
    }

    if (!empty($filtros["data_fim"])) {
        $sql .= " AND created_at <= ?";
        $params[] = $filtros["data_fim"];
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return (int) $stmt->fetchColumn();
}
