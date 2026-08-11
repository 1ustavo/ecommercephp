<?php
/**
 * POST /api/admin/recusar_pedido.php
 * Recusa um pedido que estava aguardando aprovacao.
 *
 * Body (JSON ou form-encoded):
 *   - pedido_id (int, obrigatorio)
 *   - motivo   (string, obrigatorio, nao vazio)
 *
 * Regras:
 *   - So admin/gerente/funcionario
 *   - So pedidos com status AGUARDANDO_APROVACAO podem ser recusados
 *   - O campo "motivo" e obrigatorio
 *   - Registra auditoria com o motivo
 */

require_once __DIR__ . "/../../config/bootstrap.php";

// Guard de admin para API: retorna JSON 401/403 (nao redirect)
requererAdminApi();
verify_csrf_from_request(true);

// Carrega classe de notificacao (nao esta no bootstrap)
require_once __DIR__ . "/../../src/Notificacao.php";

$ContentType = $_SERVER["CONTENT_TYPE"] ?? "";
$pedidoId = 0;
$motivo = "";

if (stripos($ContentType, "application/json") !== false) {
    $raw = file_get_contents("php://input");
    $data = json_decode($raw, true) ?? [];
    $pedidoId = isset($data["pedido_id"]) ? (int) $data["pedido_id"] : 0;
    $motivo = trim((string) ($data["motivo"] ?? ""));
} else {
    $pedidoId = isset($_POST["pedido_id"]) ? (int) $_POST["pedido_id"] : 0;
    $motivo = trim((string) ($_POST["motivo"] ?? ""));
}

header("Content-Type: application/json; charset=utf-8");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["sucesso" => false, "erro" => "Metodo nao permitido. Use POST."]);
    exit;
}

if ($pedidoId <= 0) {
    http_response_code(400);
    echo json_encode(["sucesso" => false, "erro" => "pedido_id e obrigatorio."]);
    exit;
}

if ($motivo === "") {
    http_response_code(400);
    echo json_encode(["sucesso" => false, "erro" => "motivo e obrigatorio para recusar o pedido."]);
    exit;
}

$adminId = (int) ($_SESSION["usuario_id"] ?? 0);
$adminNome = $_SESSION["usuario_nome"] ?? "Admin";

$pedido = Pedido::buscar($pedidoId);
if (!$pedido) {
    http_response_code(404);
    echo json_encode(["sucesso" => false, "erro" => "Pedido nao encontrado."]);
    exit;
}

if ($pedido["status"] !== "AGUARDANDO_APROVACAO") {
    http_response_code(409);
    echo json_encode([
        "sucesso" => false,
        "erro" => "So pedidos aguardando aprovacao podem ser recusados. Status atual: " . $pedido["status"],
    ]);
    exit;
}

$ok = Pedido::recusar($pedidoId, $adminId, $motivo);

if (!$ok) {
    http_response_code(409);
    echo json_encode(["sucesso" => false, "erro" => "Nao foi possivel recusar o pedido."]);
    exit;
}

// Auditoria com motivo
registrarAuditoria(
    "RECUSAR_PEDIDO",
    "PEDIDO",
    $pedidoId,
    "Pedido #{$pedidoId} recusado por {$adminNome} (admin #{$adminId}). Motivo: {$motivo}"
);

// Notificacao ao cliente (email ou log)
Notificacao::pedidoRecusado($pedidoId);

echo json_encode([
    "sucesso" => true,
    "mensagem" => "Pedido #{$pedidoId} recusado.",
    "pedido_id" => $pedidoId,
    "novo_status" => "RECUSADO",
    "motivo" => $motivo,
]);