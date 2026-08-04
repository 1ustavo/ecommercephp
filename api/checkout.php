<?php

require_once __DIR__ . "/../config/bootstrap.php";

// Garante que a sessão está ativa antes de acessar $_SESSION
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$auth = new Auth();

// Verifica login de forma segura — retorna JSON em vez de redirect
if (!isset($_SESSION["usuario_id"])) {
    http_response_code(401);
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode(["sucesso" => false, "erro" => "Sessão expirada. Faça login novamente."]);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: " . pageUrl("checkout.php"));
    exit;
}

$usuarioId = (int) $_SESSION["usuario_id"];
$enderecoId = isset($_POST["endereco_id"]) && $_POST["endereco_id"] !== "" ? (int) $_POST["endereco_id"] : null;
$metodoFrete = trim($_POST["metodo_frete"] ?? "pac");
$metodoPagamento = trim($_POST["metodo_pagamento"] ?? "pix");

if ($enderecoId) {
    $endereco = Endereco::buscar($enderecoId, $usuarioId);
    if (!$endereco) {
        flash("checkout_error", "Endereço selecionado inválido.");
        header("Location: " . pageUrl("checkout.php"));
        exit;
    }
} else {
    $novoEndereco = [
        "titulo" => trim($_POST["novo_titulo"] ?? "Casa"),
        "cep" => trim($_POST["novo_cep"] ?? ""),
        "rua" => trim($_POST["novo_rua"] ?? ""),
        "numero" => trim($_POST["novo_numero"] ?? ""),
        "complemento" => trim($_POST["novo_complemento"] ?? ""),
        "bairro" => trim($_POST["novo_bairro"] ?? ""),
        "cidade" => trim($_POST["novo_cidade"] ?? ""),
        "estado" => trim($_POST["novo_estado"] ?? ""),
        "principal" => 0,
    ];

    if ($novoEndereco["cep"] === "" || $novoEndereco["rua"] === "" || $novoEndereco["numero"] === "" || $novoEndereco["bairro"] === "" || $novoEndereco["cidade"] === "" || $novoEndereco["estado"] === "") {
        flash("checkout_error", "Preencha os dados do endereço de entrega ou selecione um endereço existente.");
        header("Location: " . pageUrl("checkout.php"));
        exit;
    }

    $enderecoId = Endereco::salvar($usuarioId, $novoEndereco);
}

$pedidoId = Pedido::criar($usuarioId, $enderecoId, $metodoFrete, $metodoPagamento);

if (!$pedidoId) {
    flash("checkout_error", "Não foi possível processar o pedido. Verifique o carrinho e o estoque dos produtos.");
    header("Location: " . pageUrl("checkout.php"));
    exit;
}

// Registra auditoria — quem criou o pedido, valor, método
registrarAuditoria(
    "CRIAR_PEDIDO",
    "PEDIDO",
    $pedidoId,
    "Pedido #" . $pedidoId . " | Frete: " . $metodoFrete . " | Pagamento: " . $metodoPagamento
);

flash("checkout_success", "Pedido criado com sucesso.");
header("Location: " . pageUrl("pedidos.php"));
exit;
