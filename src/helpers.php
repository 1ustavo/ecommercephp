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

function asset(string $path): string
{
    return baseUrl() . "/assets/" . ltrim($path, "/");
}

function pageUrl(string $path): string
{
    return baseUrl() . "/pages/" . ltrim($path, "/");
}

function apiUrl(string $path): string
{
    return baseUrl() . "/api/" . ltrim($path, "/");
}

function isAdmin(): bool
{
    $cargo = $_SESSION["cargo"] ?? "";

    return in_array($cargo, ["ADMIN", "GERENTE", "FUNCIONARIO"], true);
}

/**
 * Guard para endpoints de API admin: retorna JSON 401/403 e encerra
 * a execucao em vez de redirecionar (diferente de Auth::verificarAdmin,
 * que faz header redirect e serve para paginas, nao para APIs).
 *
 * Uso tipico no topo de api/admin/*.php:
 *   requererAdminApi(); // encerra aqui se nao for admin
 */
function requererAdminApi(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    header("Content-Type: application/json; charset=utf-8");

    // 1) Autenticacao: precisa estar logado
    if (!isset($_SESSION["usuario_id"])) {
        http_response_code(401);
        echo json_encode([
            "sucesso" => false,
            "erro" => "Sessao expirada. Faca login novamente.",
        ]);
        exit;
    }

    // 2) Autorizacao: precisa ser admin/gerente/funcionario
    if (!isAdmin()) {
        http_response_code(403);
        echo json_encode([
            "sucesso" => false,
            "erro" => "Acesso negado. Apenas administradores podem acessar este recurso.",
        ]);
        exit;
    }
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
            $descricao,
            $_SERVER["REMOTE_ADDR"] ?? null,
            substr($_SERVER["HTTP_USER_AGENT"] ?? "", 0, 500),
        ]);
    } catch (PDOException $e) {
        error_log("Erro ao registrar auditoria: " . $e->getMessage());
    }
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
