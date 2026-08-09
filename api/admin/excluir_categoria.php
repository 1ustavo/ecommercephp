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

$categoria = Categoria::buscarPorId($id);
if (!$categoria) {
    http_response_code(404);
    echo json_encode(["sucesso" => false, "erro" => "Categoria não encontrada."]);
    exit;
}

$ok = Categoria::excluir($id);

if (!$ok) {
    http_response_code(500);
    echo json_encode(["sucesso" => false, "erro" => "Erro ao excluir categoria."]);
    exit;
}

registrarAuditoria(
    "EXCLUIR_CATEGORIA",
    "CATEGORIA",
    $id,
    "Categoria #{$id} ({$categoria['nome']}) excluída."
);

echo json_encode([
    "sucesso" => true,
    "mensagem" => "Categoria excluída com sucesso.",
]);