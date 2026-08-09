<?php
/**
 * POST /api/admin/aprovar_pedido.php
 * Aprova um pedido que estava aguardando aprovacao.
 *
 * Body (JSON ou form-encoded):
 *   - pedido_id (int, obrigatorio)
 *
 * Regras:
 *   - So admin/gerente/funcionario (verificado em admin-header.php via Auth::verificarAdmin)
 *   - So pedidos com status AGUARDANDO_APROVACAO podem ser aprovados
 *   - Revalida estoque antes de aprovar
 *   - Registra auditoria (ETAPA 4 adicionara log detalhado; aqui ja chamamos registrarAuditoria)
 */

require_once __DIR__ . "/../../config/bootstrap.php";

// Guard de admin para API: retorna JSON 401/403 (nao redirect)
requererAdminApi();

// Carrega classe de notificacao (nao esta no bootstrap)
require_once __DIR__ . "/../../src/Notificacao.php";

// Aceita JSON ou form-encoded
$ContentType = $_SERVER["CONTENT_TYPE"] ?? "";
$pedidoId = null;

if (stripos($ContentType, "application/json") !== false) {
    $raw = file_get_contents("php://input");
    $data = json_decode($raw, true) ?? [];
    $pedidoId = isset($data["pedido_id"]) ? (int) $data["pedido_id"] : 0;
} else {
    $pedidoId = isset($_POST["pedido_id"]) ? (int) $_POST["pedido_id"] : 0;
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

$adminId = (int) ($_SESSION["usuario_id"] ?? 0);
$adminNome = $_SESSION["usuario_nome"] ?? "Admin";

// Busca o pedido antes para validar estado
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
        "erro" => "So pedidos aguardando aprovacao podem ser aprovados. Status atual: " . $pedido["status"],
    ]);
    exit;
}

// Revalida estoque antes de aprovar para dar mensagem específica
$itensPedido = Pedido::itens($pedidoId);
$itensSemEstoque = [];

foreach ($itensPedido as $item) {
    $variacaoId = $item["variacao_id"] ? (int) $item["variacao_id"] : null;
    if (!Produto::estoqueDisponivel((int) $item["produto_id"], $variacaoId, (int) $item["quantidade"])) {
        $itensSemEstoque[] = $item["nome_produto"] . ($item["variacao_descricao"] ? " (" . $item["variacao_descricao"] . ")" : "");
    }
}

if (!empty($itensSemEstoque)) {
    http_response_code(409);
    echo json_encode([
        "sucesso" => false,
        "erro" => "Estoque insuficiente para: " . implode(", ", $itensSemEstoque) . ". Recuse o pedido ou aguarde reposição.",
    ]);
    exit;
}

$ok = Pedido::aprovar($pedidoId, $adminId);

if (!$ok) {
    // Pode falhar por falta de estoque na revalidacao (concorrência)
    http_response_code(409);
    echo json_encode([
        "sucesso" => false,
        "erro" => "Nao foi possivel aprovar o pedido. O estoque mudou desde a última verificação. Tente novamente.",
    ]);
    exit;
}

// Auditoria
registrarAuditoria(
    "APROVAR_PEDIDO",
    "PEDIDO",
    $pedidoId,
    "Pedido #{$pedidoId} aprovado por {$adminNome} (admin #{$adminId})"
);

// Notificacao ao cliente (email ou log)
Notificacao::pedidoAprovado($pedidoId);

echo json_encode([
    "sucesso" => true,
    "mensagem" => "Pedido #{$pedidoId} aprovado com sucesso.",
    "pedido_id" => $pedidoId,
    "novo_status" => "APROVADO",
]);
