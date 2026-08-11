<?php
require_once __DIR__ . "/../../config/bootstrap.php";
require_once __DIR__ . "/../../src/helpers.php";

// API para alterar status de pedidos por admin
requererAdminApi();

// Verify CSRF token passed via X-CSRF-Token header or JSON body
verify_csrf_from_request(true);

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$pedidoId = isset($input['pedido_id']) ? (int) $input['pedido_id'] : 0;
$action = trim($input['action'] ?? '');

if (!$pedidoId || $action === '') {
    http_response_code(400);
    echo json_encode(['sucesso' => false, 'erro' => 'Parâmetros inválidos']);
    exit;
}

switch ($action) {
    case 'aprovar':
        $adminId = $_SESSION['usuario_id'] ?? null;
        if (!$adminId) {
            http_response_code(401);
            echo json_encode(['sucesso' => false, 'erro' => 'Usuário não autenticado']);
            exit;
        }
        $ok = Pedido::aprovar($pedidoId, $adminId);
        if ($ok) {
            registrarAuditoria('APROVAR_PEDIDO', 'PEDIDO', $pedidoId, "Aprovado via API por admin #{$adminId}");
            echo json_encode(['sucesso' => true]);
            exit;
        }
        http_response_code(400);
        echo json_encode(['sucesso' => false, 'erro' => 'Não foi possível aprovar o pedido. Verifique estoque ou estado atual.']);
        exit;

    case 'recusar':
        $motivo = trim($input['motivo'] ?? '');
        $adminId = $_SESSION['usuario_id'] ?? null;
        if (!$adminId) { http_response_code(401); echo json_encode(['sucesso' => false, 'erro' => 'Usuário não autenticado']); exit; }
        if ($motivo === '') { http_response_code(400); echo json_encode(['sucesso' => false, 'erro' => 'Motivo obrigatório']); exit; }
        $ok = Pedido::recusar($pedidoId, $adminId, $motivo);
        if ($ok) {
            registrarAuditoria('RECUSAR_PEDIDO', 'PEDIDO', $pedidoId, "Recusado via API por admin #{$adminId}: {$motivo}");
            echo json_encode(['sucesso' => true]);
            exit;
        }
        http_response_code(400);
        echo json_encode(['sucesso' => false, 'erro' => 'Não foi possível recusar o pedido.']);
        exit;

    case 'atualizar':
        $status = strtoupper(trim($input['status'] ?? ''));
        $permitidos = ['APROVADO','PENDENTE','PAGO','ENVIADO','ENTREGUE','CANCELADO'];
        if (!in_array($status, $permitidos, true)) { http_response_code(400); echo json_encode(['sucesso' => false, 'erro' => 'Status inválido']); exit; }
        Pedido::atualizarStatus($pedidoId, $status);
        registrarAuditoria('ATUALIZAR_STATUS_PEDIDO', 'PEDIDO', $pedidoId, "Status alterado para {$status}");
        echo json_encode(['sucesso' => true]);
        exit;

    default:
        http_response_code(400);
        echo json_encode(['sucesso' => false, 'erro' => 'Ação desconhecida']);
        exit;
}
