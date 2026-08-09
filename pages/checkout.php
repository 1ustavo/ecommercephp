<?php

require_once __DIR__ . "/../config/bootstrap.php";

$auth = new Auth();
$auth->verificarLogin();

if (!isCliente()) {
    header("Location: " . pageUrl("loja.php"));
    exit;
}

$itens = Carrinho::itens();

if (empty($itens)) {
    header("Location: " . pageUrl("carrinho.php"));
    exit;
}

$enderecos = Endereco::porUsuario((int) $_SESSION["usuario_id"]);
$subtotal = Carrinho::subtotal();
$pageTitle = "Checkout";

// Cupom persistido na sessão (aplicado no carrinho)
$cupomSessao = $_SESSION["cupom_aplicado"] ?? null;
$descontoSessao = $cupomSessao ? (float) $cupomSessao["desconto"] : 0;
$totalCheckout = max(0, $subtotal + 22.50 - $descontoSessao);

require __DIR__ . "/../includes/header.php";
?>

<h1 class="page-title">Finalizar Compra</h1>

<?php $checkoutError = flash("checkout_error"); ?>
<?php if ($checkoutError): ?>
    <div class="alert alert-error"><?= e($checkoutError) ?></div>
<?php endif; ?>

<div class="checkout-steps">
    <div class="step active" data-step="1">1. Endereço</div>
    <div class="step" data-step="2">2. Frete</div>
    <div class="step" data-step="3">3. Pagamento</div>
    <div class="step" data-step="4">4. Confirmação</div>
</div>

<form id="checkout-form" action="<?= apiUrl("checkout.php") ?>" method="post">
    <div class="checkout-panel active card" data-panel="1">
        <h3 style="margin-bottom:16px;color:var(--verde-mato)">Endereço de entrega</h3>

        <?php if (empty($enderecos)): ?>
            <div class="alert alert-info">Cadastre um endereço no seu <a href="<?= pageUrl("perfil.php") ?>">perfil</a> ou preencha abaixo:</div>
        <?php else: ?>
            <?php foreach ($enderecos as $end): ?>
                <label class="payment-option" style="margin-bottom:8px">
                    <input type="radio" name="endereco_id" value="<?= (int) $end["id"] ?>" <?= $end["principal"] ? "checked" : "" ?>>
                    <div>
                        <strong><?= e($end["titulo"]) ?></strong><br>
                        <span style="color:var(--cinza-600);font-size:0.9rem">
                            <?= e($end["rua"]) ?>, <?= e($end["numero"]) ?> – <?= e($end["bairro"]) ?>, <?= e($end["cidade"]) ?>/<?= e($end["estado"]) ?>
                        </span>
                    </div>
                </label>
            <?php endforeach; ?>
        <?php endif; ?>

        <details style="margin-top:16px">
            <summary style="cursor:pointer;color:var(--verde-mato);font-weight:600">+ Novo endereço</summary>
            <div class="form-row" style="margin-top:12px">
                <div class="form-group"><label class="form-label">CEP</label><input type="text" name="novo_cep" id="novo_cep" class="form-control" placeholder="00000-000"></div>
                <div class="form-group"><label class="form-label">Título</label><input type="text" name="novo_titulo" id="novo_titulo" class="form-control" value="Casa"></div>
            </div>
            <div class="form-group"><label class="form-label">Rua</label><input type="text" name="novo_rua" id="novo_rua" class="form-control"></div>
            <div class="form-row">
                <div class="form-group"><label class="form-label">Número</label><input type="text" name="novo_numero" id="novo_numero" class="form-control"></div>
                <div class="form-group"><label class="form-label">Complemento</label><input type="text" name="novo_complemento" id="novo_complemento" class="form-control"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label class="form-label">Bairro</label><input type="text" name="novo_bairro" id="novo_bairro" class="form-control"></div>
                <div class="form-group"><label class="form-label">Cidade</label><input type="text" name="novo_cidade" id="novo_cidade" class="form-control"></div>
            </div>
            <div class="form-group" style="max-width:100px"><label class="form-label">UF</label><input type="text" name="novo_estado" id="novo_estado" class="form-control" maxlength="2"></div>
        </details>

        <button type="button" class="btn btn-primary btn-next" style="margin-top:20px">Continuar</button>
    </div>

    <div class="checkout-panel card" data-panel="2">
        <h3 style="margin-bottom:16px;color:var(--verde-mato)">Opções de frete</h3>
        <div class="payment-options">
            <label class="payment-option selected">
                <input type="radio" name="metodo_frete" value="pac" checked>
                <div><strong>PAC – Correios</strong><div style="color:var(--cinza-600);font-size:0.85rem">8-12 dias úteis</div></div>
                <span>R$ 22,50</span>
            </label>
            <label class="payment-option">
                <input type="radio" name="metodo_frete" value="sedex">
                <div><strong>SEDEX – Correios</strong><div style="color:var(--cinza-600);font-size:0.85rem">3-5 dias úteis</div></div>
                <span>R$ 35,90</span>
            </label>
            <label class="payment-option">
                <input type="radio" name="metodo_frete" value="retirada">
                <div><strong>Retirada na loja</strong><div style="color:var(--cinza-600);font-size:0.85rem">Grátis</div></div>
                <span>Grátis</span>
            </label>
        </div>
        <div class="mock-notice">Esboço: frete simulado. Em produção, integrar Correios API ou Melhor Envio.</div>
        <div class="flex gap-12 mt-20">
            <button type="button" class="btn btn-gray btn-prev">Voltar</button>
            <button type="button" class="btn btn-primary btn-next">Continuar</button>
        </div>
    </div>

    <div class="checkout-panel card" data-panel="3">
        <h3 style="margin-bottom:16px;color:var(--verde-mato)">Pagamento</h3>
        <div class="payment-options">
            <label class="payment-option selected">
                <input type="radio" name="metodo_pagamento" value="pix" checked>
                <div><strong>PIX</strong></div>
            </label>
            <label class="payment-option">
                <input type="radio" name="metodo_pagamento" value="cartao">
                <div><strong>Cartão de crédito</strong></div>
            </label>
            <label class="payment-option">
                <input type="radio" name="metodo_pagamento" value="boleto">
                <div><strong>Boleto</strong></div>
            </label>
        </div>
        <div id="cartao-fields" style="display:none;margin-top:16px">
            <div class="form-group"><label class="form-label">Número do cartão</label><input type="text" class="form-control" placeholder="0000 0000 0000 0000" disabled></div>
        </div>
        <div class="mock-notice">Esboço: pagamento simulado via gateway (Mercado Pago/PagSeguro). Sem armazenamento de cartão (PCI).</div>
        <div class="flex gap-12 mt-20">
            <button type="button" class="btn btn-gray btn-prev">Voltar</button>
            <button type="button" class="btn btn-primary btn-next">Revisar</button>
        </div>
    </div>

    <div class="checkout-panel card" data-panel="4">
        <h3 style="margin-bottom:16px;color:var(--verde-mato)">Confirmar pedido</h3>

        <!-- Campo de cupom de desconto -->
        <div class="form-group" style="margin-bottom:16px">
            <label class="form-label" for="cupom-input">Cupom de desconto</label>
            <div class="flex gap-8">
                <input type="text" name="cupom" id="cupom-input" class="form-control" placeholder="Digite seu cupom (ex: BEMVINDO10)" style="text-transform:uppercase"
                       value="<?= e($cupomSessao["codigo"] ?? "") ?>" <?= $cupomSessao ? "disabled" : "" ?>>
                <button type="button" class="btn <?= $cupomSessao ? "btn-gray" : "btn-outline" ?>" id="btn-aplicar-cupom" style="white-space:nowrap">
                    <?= $cupomSessao ? "Aplicado ✓" : "Aplicar" ?>
                </button>
            </div>
            <div id="cupom-feedback" style="margin-top:8px;font-size:0.85rem">
                <?php if ($cupomSessao): ?>
                    <span style="color:var(--sucesso-text);font-weight:600">Cupom aplicado! Desconto: <?= formatarMoeda($descontoSessao) ?></span>
                <?php endif; ?>
            </div>
        </div>

        <div class="cart-summary" style="margin-top:0">
            <div class="cart-summary-row"><span>Subtotal</span><span><?= formatarMoeda($subtotal) ?></span></div>
            <div class="cart-summary-row"><span>Frete</span><span id="resumo-frete">R$ 22,50</span></div>
            <div class="cart-summary-row" id="resumo-desconto-row" style="<?= $cupomSessao ? "display:flex" : "display:none" ?>">
                <span>Desconto</span>
                <span id="resumo-desconto" style="color:var(--verde-700);font-weight:700">- <?= formatarMoeda($descontoSessao) ?></span>
            </div>
            <div class="cart-summary-row cart-summary-total"><span>Total</span><span id="resumo-total"><?= formatarMoeda($totalCheckout) ?></span></div>
        </div>
        <input type="hidden" name="subtotal" value="<?= $subtotal ?>">
        <input type="hidden" name="desconto" id="desconto-hidden" value="<?= $descontoSessao ?>">
        <div class="flex gap-12 mt-20">
            <button type="button" class="btn btn-gray btn-prev">Voltar</button>
            <button type="submit" class="btn btn-primary" id="btn-confirmar">Confirmar e Pagar</button>
        </div>
    </div>
</form>

<?php
$extraJs = "checkout.js";
require __DIR__ . "/../includes/footer.php";
