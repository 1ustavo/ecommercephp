<?php

require_once __DIR__ . "/../../config/bootstrap.php";

// Proteção: apenas admin pode acessar
$auth = new Auth();
$auth->verificarAdmin();

// Aceita apenas GET
if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    http_response_code(405);
    echo json_encode(["sucesso" => false, "erro" => "Método não permitido."]);
    exit;
}

$id = isset($_GET["id"]) ? (int) $_GET["id"] : 0;

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(["sucesso" => false, "erro" => "ID de produto inválido."]);
    exit;
}

// Busca produto
$produto = Produto::buscarPorId($id);

if (!$produto) {
    http_response_code(404);
    echo json_encode(["sucesso" => false, "erro" => "Produto não encontrado."]);
    exit;
}

// Busca dados complementares
$faqs = Produto::faqs($id);
$tickets = Produto::tickets($id);

// Busca mensagens de cada ticket
foreach ($tickets as &$ticket) {
    $ticket["mensagens"] = Produto::ticketMensagens((int) $ticket["id"]);
}
unset($ticket);

// Busca imagens
$imagens = Produto::imagens($id);

echo json_encode([
    "sucesso" => true,
    "produto" => $produto,
    "faqs" => $faqs,
    "tickets" => $tickets,
    "imagens" => $imagens,
]);