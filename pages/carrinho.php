<?php

require_once __DIR__ . "/../config/bootstrap.php";

$itens = Carrinho::itens();
$subtotal = Carrinho::subtotal();
$pageTitle = "Carrinho";

// Cupom persistido na sessão (aplicado no carrinho)
$cupomSessao = $_SESSION["cupom_aplicado"] ?? null;
$descontoSessao = $cupomSessao ? (float) $cupomSessao["desconto"] : 0;
$totalEstimado = max(0, $subtotal - $descontoSessao);

require __DIR__ . "/../includes/header.php";
?>

<h1 class="page-title">Carrinho</h1>

<input type="hidden" id="csrf-token-cart" value="<?= e(csrf_token()) ?>">

<?php if (isset($_GET["sucesso"])): ?>
    <div class="alert alert-success">Item atualizado no carrinho.</div>
<?php elseif (isset($_GET["erro"])): ?>
    <div class="alert alert-error">Ocorreu um problema ao atualizar o carrinho.</div>
<?php endif; ?>

<?php if (empty($itens)): ?>
    <div class="empty-state">
        <h3>Seu carrinho está vazio</h3>
        <p>Explore nossa loja e adicione produtos.</p>
        <a href="<?= pageUrl("loja.php") ?>" class="btn btn-primary" style="margin-top:16px">Ir para a Loja</a>
    </div>
<?php else: ?>
    <div id="cart-items">
        <?php foreach ($itens as $item): ?>
            <div class="cart-item" data-item-id="<?= (int) $item["id"] ?>" data-estoque="<?= (int) $item["estoque_disponivel"] ?>">
                <div class="cart-item-img" style="background:<?= corPlaceholder($item["slug"]) ?>"></div>
                <div class="flex-1">
                    <strong><?= e($item["nome"]) ?></strong>
                    <?php if ($item["tamanho"] || $item["cor"]): ?>
                        <div style="color:var(--cinza-600);font-size:0.85rem">
                            <?= e(trim(($item["tamanho"] ?? "") . " " . ($item["cor"] ?? ""))) ?>
                        </div>
                    <?php endif; ?>
                    <div class="product-price" style="font-size:1rem;margin-top:4px">
                        <?= formatarMoeda((float) $item["preco_final"]) ?>
                    </div>
                </div>
                <div class="qty-control">
                    <button class="qty-btn qty-minus" type="button">−</button>
                    <span class="qty-value"><?= (int) $item["quantidade"] ?></span>
                    <button class="qty-btn qty-plus" type="button">+</button>
                </div>
                <div class="flex-none" style="font-weight:700;color:var(--verde-mato);min-width:100px;text-align:right">
                    <?= formatarMoeda((float) $item["subtotal"]) ?>
                </div>
                <button class="btn btn-gray btn-sm btn-remove" type="button">Remover</button>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="cart-summary">
        <div class="cart-summary-row">
            <span>Subtotal</span>
            <span id="subtotal-value"><?= formatarMoeda($subtotal) ?></span>
        </div>
        <div class="cart-summary-row">
            <span>Frete</span>
            <span>Calculado no checkout</span>
        </div>

        <!-- === CUPOM DE DESCONTO === -->
        <div class="cart-summary-cupom">
            <label class="form-label" for="cupom-input-cart" style="font-size:0.85rem;color:var(--cinza-600)">
                Cupom de desconto
            </label>
            <div class="flex gap-8">
                <input type="text" id="cupom-input-cart" class="form-control" placeholder="Digite seu cupom" style="text-transform:uppercase;font-size:0.85rem"
                       value="<?= e($cupomSessao["codigo"] ?? "") ?>" <?= $cupomSessao ? "disabled" : "" ?>>
                <button type="button" class="btn <?= $cupomSessao ? "btn-gray" : "btn-outline" ?> btn-sm" id="btn-aplicar-cupom-cart" style="white-space:nowrap">
                    <?= $cupomSessao ? "Aplicado ✓" : "Aplicar" ?>
                </button>
            </div>
            <div id="cupom-feedback-cart" style="margin-top:6px;font-size:0.8rem;min-height:1.2em">
                <?php if ($cupomSessao): ?>
                    <span style="color:var(--sucesso-text);font-weight:600">Cupom aplicado! Desconto: <?= formatarMoeda($descontoSessao) ?></span>
                <?php endif; ?>
            </div>
        </div>

        <div class="cart-summary-row" id="desconto-row-cart" style="<?= $cupomSessao ? "display:flex" : "display:none" ?>">
            <span>Desconto</span>
            <span id="desconto-value-cart" style="color:var(--verde-700);font-weight:700">- <?= formatarMoeda($descontoSessao) ?></span>
        </div>

        <div class="cart-summary-row cart-summary-total">
            <span>Total estimado</span>
            <span id="total-value"><?= formatarMoeda($totalEstimado) ?></span>
        </div>
        <input type="hidden" id="subtotal-hidden-cart" value="<?= $subtotal ?>">
        <input type="hidden" id="desconto-hidden-cart" value="<?= $descontoSessao ?>">
        <a href="<?= pageUrl("checkout.php") ?>" class="btn btn-primary btn-block" style="margin-top:16px">
            Finalizar Compra
        </a>
    </div>
<?php endif; ?>

<?php
$extraJs = "carrinho.js";
require __DIR__ . "/../includes/footer.php";