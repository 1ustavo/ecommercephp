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

<input type="hidden" id="csrf-token-checkout" value="<?= e(csrf_token()) ?>">

<?php $checkoutError = flash("checkout_error"); ?>
<?php if ($checkoutError): ?>
    <div class="alert alert-error"><?= e($checkoutError) ?></div>
<?php endif; ?>

<div class="checkout-steps">
    <div class="step-wrapper">
        <div class="step active" data-step="1">1. Endereço</div>
        <div class="step-line"></div>
    </div>
    <div class="step-wrapper">
        <div class="step" data-step="2">2. Frete</div>
        <div class="step-line"></div>
    </div>
    <div class="step-wrapper">
        <div class="step" data-step="3">3. Pagamento</div>
        <div class="step-line"></div>
    </div>
    <div class="step-wrapper">
        <div class="step" data-step="4">4. Confirmação</div>
    </div>
</div>

<form id="checkout-form" action="<?= apiUrl("checkout.php") ?>" method="post">
        <?= csrf_input() ?>
    <div class="checkout-panel active card" data-panel="1">
        <h3 style="margin-bottom:16px;color:var(--verde-mato)">Endereço de entrega</h3>

        <?php if (empty($enderecos)): ?>
            <div class="alert alert-info">Cadastre um endereço no seu <a href="<?= pageUrl("perfil.php") ?>">perfil</a> ou preencha abaixo:</div>
        <?php else: ?>
            <?php foreach ($enderecos as $end): ?>
                <label class="payment-option" style="margin-bottom:8px">
                    <input type="radio" name="endereco_id" value="<?= (int) $end["id"] ?>" data-cep="<?= e($end["cep"]) ?>" <?= $end["principal"] ? "checked" : "" ?>>
                    <div>
                        <strong><?= e($end["titulo"]) ?></strong><br>
                        <span style="color:var(--cinza-600);font-size:0.9rem">
                            <?= e($end["rua"]) ?>, <?= e($end["numero"]) ?> – <?= e($end["bairro"]) ?>, <?= e($end["cidade"]) ?>/<?= e($end["estado"]) ?> — CEP <?= e($end["cep"]) ?>
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
        <div id="frete-loading" style="display:none" class="mock-notice">
            <span class="spinner"></span> Calculando frete...
        </div>
        <div class="payment-options" id="frete-opcoes">
            <label class="payment-option">
                <input type="radio" name="metodo_frete" value="retirada">
                <div><strong>Retirada na loja</strong><div style="color:var(--cinza-600);font-size:0.85rem">Grátis</div></div>
                <span>Grátis</span>
            </label>
        </div>
        <p class="suggestion-hint" id="frete-hint" style="margin-top:8px">
            Selecione ou informe o CEP de entrega no passo anterior para calcular o frete.
        </p>
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
        <!--
            CARTÃO DE CRÉDITO — SIMULAÇÃO DE CHECKOUT
            ==========================================
            ESTE CARTÃO É 100% VISUAL/DECORATIVO.
            Nenhum dado de cartão é enviado ao servidor ou
            processado por gateway real (sem PCI compliance).

            Todo o processamento é de validação de FORMATO
            no navegador (Luhn, validade, CVV, bandeira).
            Modo de simulação de checkout apenas.
        -->
        <div id="cartao-fields" class="credit-card-form" style="display:none;margin-top:16px">
            <!-- Cartão 3D -->
            <div class="credit-card-wrapper">
                <div class="credit-card-plane" id="card-plane" aria-hidden="true">
                    <!-- FRENTE -->
                    <div class="credit-card-face">
                        <div class="card-front-top">
                            <div class="card-chip"></div>
                            <div class="card-contactless" title="Contactless"></div>
                        </div>
                        <div class="card-number placeholder" id="card-number">•••• •••• •••• ••••</div>
                        <div class="card-front-bottom">
                            <div class="card-holder">
                                <div class="card-holder-label">Titular</div>
                                <div class="card-holder-name placeholder" id="card-holder">SEU NOME</div>
                            </div>
                            <div class="card-exp">
                                <div class="card-exp-label">Validade</div>
                                <div class="card-exp-value placeholder" id="card-exp">MM/AA</div>
                            </div>
                        </div>
                        <div class="card-brand default" id="card-brand"><span class="brand-text">•••</span></div>
                    </div>
                    <!-- VERSO -->
                    <div class="credit-card-face back">
                        <div class="card-back-stripe"></div>
                        <div class="card-back-body">
                            <div class="card-back-label">CVV</div>
                            <div class="card-cvv-row">
                                <div class="card-signature">Assinatura do titular</div>
                                <div class="card-cvv-box" id="card-cvv"><span class="card-back-cvv-placeholder">•••</span></div>
                            </div>
                            <div class="card-back-info">Validado apenas para fins de simulação de checkout.</div>
                        </div>
                        <div class="card-back-terms">ECO SHOP • SIMULAÇÃO DE PAGAMENTO • SEM PROCESSAMENTO REAL</div>
                    </div>
                </div>
            </div>

            <!-- Campos do cartão -->
            <div class="form-group">
                <label class="form-label" for="cc-number">Número do cartão</label>
                <input type="tel" inputmode="numeric" id="cc-number" name="cartao_numero" class="form-control" placeholder="0000 0000 0000 0000" maxlength="19" autocomplete="cc-number">
                <div class="feedback" id="number-feedback"></div>
            </div>
            <div class="form-group">
                <label class="form-label" for="cc-name">Nome impresso no cartão</label>
                <input type="text" id="cc-name" name="cartao_nome" class="form-control" placeholder="NOME COMO ESTÁ NO CARTÃO" maxlength="26" autocomplete="cc-name">
                <div class="feedback feedback-hidden" id="name-feedback"></div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="cc-exp">Validade (MM/AA)</label>
                    <input type="tel" inputmode="numeric" id="cc-exp" name="cartao_validade" class="form-control" placeholder="MM/AA" maxlength="5" autocomplete="cc-exp">
                    <div class="feedback" id="exp-feedback"></div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="cc-cvv">CVV</label>
                    <input type="tel" inputmode="numeric" id="cc-cvv" name="cartao_cvv" class="form-control" placeholder="•••" maxlength="4" autocomplete="cc-csc">
                    <div class="feedback" id="cvv-feedback"></div>
                </div>
            </div>
        </div>
        <div class="mock-notice">
            ⚠️ <strong>Simulação de checkout</strong>: nenhum dado de cartão é enviado a servidor nem processado por gateway real (sem PCI). Apenas validação de formato no navegador.
        </div>
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
            <div class="cart-summary-row"><span>Frete</span><span id="resumo-frete">—</span></div>
            <div class="cart-summary-row" id="resumo-desconto-row" style="<?= $cupomSessao ? "display:flex" : "display:none" ?>">
                <span>Desconto</span>
                <span id="resumo-desconto" style="color:var(--verde-700);font-weight:700">- <?= formatarMoeda($descontoSessao) ?></span>
            </div>
            <div class="cart-summary-row cart-summary-total"><span>Total</span><span id="resumo-total"><?= formatarMoeda($totalCheckout) ?></span></div>
        </div>
        <input type="hidden" name="subtotal" value="<?= $subtotal ?>">
        <input type="hidden" name="desconto" id="desconto-hidden" value="<?= $descontoSessao ?>">
        <input type="hidden" name="frete" id="frete-hidden" value="">
        <div class="flex gap-12 mt-20">
            <button type="button" class="btn btn-gray btn-prev">Voltar</button>
            <button type="submit" class="btn btn-primary" id="btn-confirmar">Confirmar e Pagar</button>
        </div>
    </div>
</form>

<?php
$extraJs = "checkout.js";
require __DIR__ . "/../includes/footer.php";