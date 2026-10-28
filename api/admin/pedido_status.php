<?php
require_once __DIR__ . "/../../config/bootstrap.php";
require_once __DIR__ . "/../../src/helpers.php";

// API para alterar status de pedidos por admin
// Guard: apenas ADMIN/GERENTE alteram estado de pedidos
requererPapel(["ADMIN", "GERENTE"]);

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

// O pedido precisa existir para qualquer ação (404 explícito, evita
// auditoria de "sucesso" sobre pedido inexistente)
$pedido = Pedido::buscar($pedidoId);
if (!$pedido) {
    http_response_code(404);
    echo json_encode(['sucesso' => false, 'erro' => 'Pedido não encontrado']);
    exit;
}

// Máquina de estados: transições permitidas.
// APROVADO só via case 'aprovar' (revalida estoque) e RECUSADO só via
// case 'recusar' (exige motivo) — mesma lógica de aprovar_pedido.php.
$transicoes = [
    'PENDENTE'             => ['AGUARDANDO_APROVACAO', 'CANCELADO'],
    'AGUARDANDO_APROVACAO' => [], // só via aprovar/recusar
    'APROVADO'             => ['PAGO', 'ENVIADO', 'CANCELADO'],
    'PAGO'                 => ['ENVIADO', 'CANCELADO'],
    'ENVIADO'              => ['ENTREGUE'],
    'ENTREGUE'             => [],
    'CANCELADO'            => [],
    'RECUSADO'             => [],
];

switch ($action) {
    case 'aprovar':
        $adminId = $_SESSION['usuario_id'] ?? null;
        if (!$adminId) {
            http_response_code(401);
            echo json_encode(['sucesso' => false, 'erro' => 'Usuário não autenticado']);
            exit;
        }
        if ((string) $pedido['status'] !== 'AGUARDANDO_APROVACAO') {
            http_response_code(409);
            echo json_encode(['sucesso' => false, 'erro' => 'Somente pedidos AGUARDANDO_APROVACAO podem ser aprovados. Status atual: ' . $pedido['status']]);
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
        if ((string) $pedido['status'] !== 'AGUARDANDO_APROVACAO') {
            http_response_code(409);
            echo json_encode(['sucesso' => false, 'erro' => 'Somente pedidos AGUARDANDO_APROVACAO podem ser recusados. Status atual: ' . $pedido['status']]);
            exit;
        }
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
        // APROVADO e RECUSADO NÃO são setáveis aqui: têm caminhos próprios
        // com validação de estoque (aprovar) e motivo obrigatório (recusar).
        $permitidos = ['PENDENTE', 'PAGO', 'ENVIADO', 'ENTREGUE', 'CANCELADO'];
        if (!in_array($status, $permitidos, true)) { http_response_code(400); echo json_encode(['sucesso' => false, 'erro' => 'Status inválido']); exit; }

        $statusAtual = (string) $pedido['status'];
        if (!in_array($status, $transicoes[$statusAtual] ?? [], true)) {
            http_response_code(409);
            echo json_encode([
                'sucesso' => false,
                'erro' => "Transição de status {$statusAtual} -> {$status} não permitida. Pedidos AGUARDANDO_APROVACAO devem passar por aprovar/recusar.",
            ]);
            exit;
        }

        Pedido::atualizarStatus($pedidoId, $status);
        registrarAuditoria('ATUALIZAR_STATUS_PEDIDO', 'PEDIDO', $pedidoId, "Status alterado de {$statusAtual} para {$status}");
        echo json_encode(['sucesso' => true]);
        exit;

    default:
        http_response_code(400);
        echo json_encode(['sucesso' => false, 'erro' => 'Ação desconhecida']);
        exit;
}
