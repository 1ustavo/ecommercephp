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
$nome = trim($_POST["nome"] ?? "");
$slug = trim($_POST["slug"] ?? "");
$descricao = trim($_POST["descricao"] ?? "");
$paiId = isset($_POST["pai_id"]) && $_POST["pai_id"] !== "" ? (int) $_POST["pai_id"] : null;
$ativo = isset($_POST["ativo"]) ? (bool) $_POST["ativo"] : true;

if ($nome === "" || $slug === "") {
    http_response_code(400);
    echo json_encode(["sucesso" => false, "erro" => "Nome e slug são obrigatórios."]);
    exit;
}

if ($id) {
    $ok = Categoria::atualizar($id, $nome, $slug, $descricao ?: null, $paiId, $ativo);
    $acao = "ATUALIZAR_CATEGORIA";
} else {
    $ok = Categoria::criar($nome, $slug, $descricao ?: null, $paiId);
    $acao = "CRIAR_CATEGORIA";
}

if (!$ok) {
    http_response_code(500);
    echo json_encode(["sucesso" => false, "erro" => "Erro ao salvar categoria."]);
    exit;
}

$categoria = $id ? Categoria::buscarPorId($id) : Categoria::buscarPorSlug($slug);

registrarAuditoria(
    $acao,
    "CATEGORIA",
    $categoria["id"] ?? null,
    ($id ? "Categoria #{$id}" : "Categoria {$nome}") . " atualizada/salva."
);

echo json_encode([
    "sucesso" => true,
    "categoria" => $categoria,
]);