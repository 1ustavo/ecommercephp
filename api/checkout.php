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
$codigoCupom = strtoupper(trim($_POST["cupom"] ?? ""));

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

// Revalida o estoque de TODOS os itens do carrinho antes de prosseguir.
// O estoque pode ter mudado entre adicionar ao carrinho e finalizar a compra.
$itensCarrinho = Carrinho::itens();
$itensSemEstoque = [];

foreach ($itensCarrinho as $item) {
    $variacaoId = $item["variacao_id"] ? (int) $item["variacao_id"] : null;
    if (!Produto::estoqueDisponivel((int) $item["produto_id"], $variacaoId, (int) $item["quantidade"])) {
        $itensSemEstoque[] = $item["nome"] . ($item["tamanho"] || $item["cor"] ? " (" . trim(($item["tamanho"] ?? "") . " " . ($item["cor"] ?? "")) . ")" : "");
    }
}

if (!empty($itensSemEstoque)) {
    $mensagem = "Estoque insuficiente para: " . implode(", ", $itensSemEstoque) . ". Remova ou reduza a quantidade desses itens.";
    flash("checkout_error", $mensagem);
    header("Location: " . pageUrl("checkout.php"));
    exit;
}

// Calcula imposto simples sobre o subtotal (ex: 10% de ICMS)
$subtotal = Carrinho::subtotal();
$frete = calcularFrete($metodoFrete, $subtotal);
$imposto = round($subtotal * 0.10, 2);

// Aplica cupom se fornecido manualmente OU persistido na sessão (aplicado no carrinho)
$cupomId = null;
$desconto = 0;

if ($codigoCupom === "" && isset($_SESSION["cupom_aplicado"])) {
    $codigoCupom = $_SESSION["cupom_aplicado"]["codigo"] ?? "";
}

if ($codigoCupom !== "") {
    $cupom = Cupom::buscarPorCodigo($codigoCupom);
    if ($cupom) {
        $desconto = Cupom::calcularDesconto($cupom, $subtotal, $frete);
        $cupomId = (int) $cupom["id"];
    }
}

$pedidoId = Pedido::criar($usuarioId, $enderecoId, $metodoFrete, $metodoPagamento, $desconto, $imposto, $cupomId);

if (!$pedidoId) {
    flash("checkout_error", "Não foi possível processar o pedido. Verifique o carrinho e o estoque dos produtos.");
    header("Location: " . pageUrl("checkout.php"));
    exit;
}

// Registra uso do cupom se houver
if ($cupomId) {
    Cupom::registrarUso($cupomId);
}

// Limpa o cupom persistido na sessão após o pedido ser criado
unset($_SESSION["cupom_aplicado"]);

// Registra auditoria — quem criou o pedido, valor, método
registrarAuditoria(
    "CRIAR_PEDIDO",
    "PEDIDO",
    $pedidoId,
    "Pedido #" . $pedidoId . " | Frete: " . $metodoFrete . " | Pagamento: " . $metodoPagamento
);

// Se o método de pagamento for cartão, iniciar Stripe Checkout
if ($metodoPagamento === 'cartao') {
    $checkoutUrl = GatewayPagamento::criarCheckout($pedidoId, 'stripe');
    if ($checkoutUrl) {
        // Redireciona o cliente para a URL segura do Stripe Checkout
        header("Location: " . $checkoutUrl);
        exit;
    }

    // Falha ao criar sessão de pagamento => sinalizar erro e voltar ao checkout
    flash("checkout_error", "Não foi possível iniciar o pagamento. Tente novamente.");
    header("Location: " . pageUrl("checkout.php"));
    exit;
}

// Fluxo antigo: finalizar sem redirecionamento externo
flash("checkout_success", "Pedido criado com sucesso.");
header("Location: " . pageUrl("pedidos.php"));
exit;
