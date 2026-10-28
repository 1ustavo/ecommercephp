<?php
/**
 * POST /api/solicitar_reembolso.php
 * Cliente solicita reembolso de um pedido PRÓPRIO (status PAGO/ENVIADO/ENTREGUE).
 *
 * Body (JSON ou form): pedido_id (int, obrigatório), motivo (string, min 10 chars)
 * Contrato JSON: {"sucesso": bool, "erro"|"mensagem"|...}
 */

require_once __DIR__ . "/../config/bootstrap.php";

header("Content-Type: application/json; charset=utf-8");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["sucesso" => false, "erro" => "Método não permitido. Use POST."]);
    exit;
}

// Autenticação + sessão rigorosa (timeout, fingerprint, IP, session_version)
if (!(new Auth())->validarSessao()) {
    $_SESSION = [];
    http_response_code(401);
    echo json_encode(["sucesso" => false, "erro" => "Sessão expirada. Faça login novamente."]);
    exit;
}

// CSRF
verify_csrf_from_request(true);

// Aceita JSON ou form-encoded
$dados = json_decode(file_get_contents("php://input"), true);
if (!is_array($dados)) {
    $dados = $_POST;
}

$pedidoId = isset($dados["pedido_id"]) ? (int) $dados["pedido_id"] : 0;
$motivo = trim((string) ($dados["motivo"] ?? ""));
$usuarioId = (int) ($_SESSION["usuario_id"] ?? 0);

if ($pedidoId <= 0) {
    http_response_code(400);
    echo json_encode(["sucesso" => false, "erro" => "pedido_id é obrigatório."]);
    exit;
}

if (mb_strlen($motivo) < 10) {
    http_response_code(422);
    echo json_encode(["sucesso" => false, "erro" => "Descreva o motivo do reembolso em pelo menos 10 caracteres."]);
    exit;
}

try {
    // Posse do pedido e status elegível validados dentro do modelo
    $reembolsoId = Reembolso::solicitar($pedidoId, $usuarioId, $motivo);

    registrarAuditoria(
        "SOLICITAR_REEMBOLSO",
        "REEMBOLSO",
        $reembolsoId,
        "Reembolso solicitado para o pedido #{$pedidoId}"
    );

    echo json_encode([
        "sucesso" => true,
        "mensagem" => "Solicitação de reembolso enviada. Aguarde a análise da nossa equipe.",
        "reembolso_id" => $reembolsoId,
    ]);
} catch (Exception $e) {
    // Exceções do modelo são regras de negócio (posse, estado, duplicidade) → 422
    error_log("Erro ao solicitar reembolso (pedido #{$pedidoId}): " . $e->getMessage());
    http_response_code(422);
    echo json_encode(["sucesso" => false, "erro" => $e->getMessage()]);
}
