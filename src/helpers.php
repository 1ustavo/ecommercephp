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
        "PENDENTE" => "Pendente",
        "PAGO" => "Pago",
        "ENVIADO" => "Enviado",
        "ENTREGUE" => "Entregue",
        "CANCELADO" => "Cancelado",
        default => $status,
    };
}

function statusPedidoClass(string $status): string
{
    return match ($status) {
        "PENDENTE" => "badge-pendente",
        "PAGO" => "badge-pago",
        "ENVIADO" => "badge-enviado",
        "ENTREGUE" => "badge-entregue",
        "CANCELADO" => "badge-cancelado",
        default => "badge-default",
    };
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
 * Busca logs de auditoria com filtros
 */
function buscarAuditoria(array $filtros = []): array
{
    global $pdo;

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

    $sql .= " ORDER BY created_at DESC LIMIT 500";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}
