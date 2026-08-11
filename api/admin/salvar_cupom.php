<?php

require_once __DIR__ . "/../../config/bootstrap.php";

requererAdminApi();

// CSRF protection
verify_csrf_from_request(true);

header("Content-Type: application/json; charset=utf-8");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["sucesso" => false, "erro" => "Método não permitido."]);
    exit;
}

$id = isset($_POST["id"]) && $_POST["id"] !== "" ? (int) $_POST["id"] : null;
$codigo = strtoupper(trim($_POST["codigo"] ?? ""));
$tipo = strtoupper(trim($_POST["tipo"] ?? ""));
$valor = isset($_POST["valor"]) ? (float) $_POST["valor"] : 0;
$valorMinimo = isset($_POST["valor_minimo_pedido"]) ? (float) $_POST["valor_minimo_pedido"] : 0;
$quantidadeMaxima = isset($_POST["quantidade_maxima"]) ? (int) $_POST["quantidade_maxima"] : 1;
$dataInicio = trim($_POST["data_inicio"] ?? "");
$dataFim = trim($_POST["data_fim"] ?? "");
$ativo = isset($_POST["ativo"]) ? (bool) $_POST["ativo"] : true;

if ($codigo === "" || $tipo === "" || $valor <= 0) {
    http_response_code(400);
    echo json_encode(["sucesso" => false, "erro" => "Código, tipo e valor são obrigatórios."]);
    exit;
}

if ($id) {
    $ok = Cupom::atualizar($id, [
        "codigo" => $codigo,
        "tipo" => $tipo,
        "valor" => $valor,
        "valor_minimo_pedido" => $valorMinimo,
        "quantidade_maxima" => $quantidadeMaxima,
        "data_inicio" => $dataInicio ?: date("Y-m-d H:i:s"),
        "data_fim" => $dataFim ?: date("Y-m-d H:i:s", strtotime("+1 year")),
        "ativo" => $ativo,
    ]);
    $acao = "ATUALIZAR_CUPOM";
} else {
    $ok = Cupom::criar([
        "codigo" => $codigo,
        "tipo" => $tipo,
        "valor" => $valor,
        "valor_minimo_pedido" => $valorMinimo,
        "quantidade_maxima" => $quantidadeMaxima,
        "data_inicio" => $dataInicio ?: date("Y-m-d H:i:s"),
        "data_fim" => $dataFim ?: date("Y-m-d H:i:s", strtotime("+1 year")),
        "ativo" => $ativo,
    ]);
    $acao = "CRIAR_CUPOM";
}

if (!$ok) {
    http_response_code(500);
    echo json_encode(["sucesso" => false, "erro" => "Erro ao salvar cupom."]);
    exit;
}

$cupom = $id ? Cupom::buscarPorCodigo($codigo) : null;

registrarAuditoria(
    $acao,
    "CUPOM",
    $id,
    ($id ? "Cupom #{$id}" : "Cupom {$codigo}") . " atualizado/salvo."
);

echo json_encode([
    "sucesso" => true,
    "cupom" => $cupom,
]);