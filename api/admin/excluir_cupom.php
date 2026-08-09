<?php

require_once __DIR__ . "/../../config/bootstrap.php";

requererAdminApi();

header("Content-Type: application/json; charset=utf-8");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["sucesso" => false, "erro" => "Método não permitido."]);
    exit;
}

$id = isset($_POST["id"]) ? (int) $_POST["id"] : 0;

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(["sucesso" => false, "erro" => "ID inválido."]);
    exit;
}

$cupom = Cupom::buscarPorCodigo("");
// Busca diretamente para pegar o código
$stmt = $pdo->prepare("SELECT * FROM cupons WHERE id = ?");
$stmt->execute([$id]);
$cupom = $stmt->fetch();

if (!$cupom) {
    http_response_code(404);
    echo json_encode(["sucesso" => false, "erro" => "Cupom não encontrado."]);
    exit;
}

$ok = Cupom::excluir($id);

if (!$ok) {
    http_response_code(500);
    echo json_encode(["sucesso" => false, "erro" => "Erro ao excluir cupom."]);
    exit;
}

registrarAuditoria(
    "EXCLUIR_CUPOM",
    "CUPOM",
    $id,
    "Cupom #{$id} ({$cupom['codigo']}) excluído."
);

echo json_encode([
    "sucesso" => true,
    "mensagem" => "Cupom excluído com sucesso.",
]);