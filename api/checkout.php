<?php

require_once __DIR__ . "/../config/bootstrap.php";

// Garante que a sessão está ativa antes de acessar $_SESSION
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$auth = new Auth();

// Verifica login de forma segura — retorna JSON em vez de redirect
// validarSessao() aplica timeout de inatividade, fingerprint, IP e
// session_version — mesmas regras de verificarLogin, sem redirect.
if (!isset($_SESSION["usuario_id"]) || !$auth->validarSessao()) {
    $_SESSION = []; // sessão inválida/expirada: limpa dados autenticados
    http_response_code(401);
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode(["sucesso" => false, "erro" => "Sessão expirada. Faça login novamente."]);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: " . pageUrl("checkout.php"));
    exit;
}

// CSRF protection
verify_csrf_from_request(true);


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

// ============================================================
// FRETE REAL — usa o resultado calculado em api/frete.php
// (persistido na sessão). Se não houver, usa o fallback fixo.
// ============================================================
$frete = 0.0;
$freteCalculado = $_SESSION["frete_calculo"] ?? null;

if (is_array($freteCalculado) && !empty($freteCalculado["opcoes"])) {
    foreach ($freteCalculado["opcoes"] as $opcao) {
        if (strtolower((string) $opcao["servico"]) === strtolower($metodoFrete)) {
            $frete = (float) ($opcao["valor"] ?? 0);
            break;
        }
    }
}

// Fallback: se não encontrou o serviço na sessão, usa o valor fixo
if ($frete <= 0 && $metodoFrete !== "retirada") {
    $frete = calcularFrete($metodoFrete, $subtotal);
}

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

// ============================================================
// SIMULAÇÃO DE CHECKOUT — SEM GATEWAY REAL
// ============================================================
// O cartão de crédito na etapa de pagamento é 100% visual e de
// validação de formato no navegador (Luhn, validade, CVV, bandeira).
// Nenhum dado de cartão é enviado aqui nem processado por gateway
// real (sem PCI compliance). O pedido é criado como simulado.
// ============================================================

// Confirma o pagamento como simulado/aprovado (sem gateway externo)
Pedido::confirmarPagamento($pedidoId);
try { Notificacao::pedidoPago($pedidoId); } catch (Throwable $e) { error_log('Erro ao notificar pagamento simulado: ' . $e->getMessage()); }

flash("checkout_success", "Pedido criado, pagamento simulado com sucesso.");
header("Location: " . pageUrl("pedidos.php"));
exit;
