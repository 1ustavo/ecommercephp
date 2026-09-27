<?php

require_once __DIR__ . "/../config/bootstrap.php";

$slug = $_GET["slug"] ?? "";

if (!$slug) {
    header("Location: " . pageUrl("loja.php"));
    exit;
}

$produto = Produto::buscarPorSlug($slug);

if (!$produto) {
    header("Location: " . pageUrl("loja.php"));
    exit;
}

$variacoes = Produto::variacoes((int) $produto["id"]);
$pageTitle = $produto["nome"];

require __DIR__ . "/../includes/header.php";
?>

<div class="product-detail">
    <div class="product-detail-gallery">
        <?php $imgs = Produto::imagens((int) $produto["id"]); ?>
        <?php
        // Helper para montar a URL completa da imagem:
        // - URLs externas (http/https) são usadas como estão
        // - URLs locais (uploads/...) recebem o prefixo baseUrl()
        function urlImagemProduto(string $url): string
        {
            if (preg_match('#^https?://#', $url)) {
                return $url;
            }
            return baseUrl() . '/' . $url;
        }
        ?>
        <div class="product-detail-img" id="main-image-container">
            <?php $imgPrincipal = $imgs[0]["url"] ?? null; ?>
            <?php if ($imgPrincipal && !str_starts_with($imgPrincipal, 'placeholder:')): ?>
                <img src="<?= e(urlImagemProduto($imgPrincipal)) ?>" 
                     alt="<?= e($produto['nome']) ?>" 
                     id="main-product-image">
            <?php else: ?>
                <div style="background:<?= corPlaceholder($produto["slug"]) ?>;width:100%;height:100%"></div>
            <?php endif; ?>
            
            <?php if (count($imgs) > 1): ?>
                <button type="button" class="gallery-nav gallery-prev" id="gallery-prev" aria-label="Imagem anterior">‹</button>
                <button type="button" class="gallery-nav gallery-next" id="gallery-next" aria-label="Próxima imagem">›</button>
            <?php endif; ?>
        </div>
        
        <?php if (count($imgs) > 1): ?>
            <div class="product-gallery-thumbs">
                <?php foreach ($imgs as $index => $img): ?>
                    <?php if (!empty($img["url"]) && !str_starts_with($img["url"], 'placeholder:')): ?>
                        <div class="product-gallery-thumb <?= $index === 0 ? "active" : "" ?>" 
                             data-src="<?= e(urlImagemProduto($img["url"])) ?>"
                             data-index="<?= $index ?>">
                            <img src="<?= e(urlImagemProduto($img["url"])) ?>" 
                                 alt="Miniatura <?= $index + 1 ?>">
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <div>
        <div class="product-category">
            <?= e($produto["categoria_nome"]) ?>
            <?php if (!empty($produto["marca_nome"])): ?>
                • <?= e($produto["marca_nome"]) ?>
            <?php endif; ?>
        </div>
        <h1 class="page-title"><?= e($produto["nome"]) ?></h1>
        <p style="color:var(--cinza-600);margin-bottom:20px"><?= e($produto["descricao"]) ?></p>
        <div class="product-price" id="preco-display"><?= formatarMoeda((float) $produto["preco"]) ?></div>
        <p style="color:var(--cinza-600);font-size:0.9rem;margin:8px 0 20px">
            Estoque: <span id="estoque-display"><?= (int) $produto["estoque"] ?></span> un.
        </p>

        <form id="form-add-cart" action="<?= apiUrl("carrinho.php") ?>?action=adicionar" method="post" data-produto-id="<?= (int) $produto["id"] ?>" data-estoque="<?= (int) $produto["estoque"] ?>" data-preco="<?= (float) $produto["preco"] ?>">
            <?= csrf_input() ?>
            <input type="hidden" name="produto_id" value="<?= (int) $produto["id"] ?>">
            <?php if (!empty($variacoes)): ?>
                <div class="variation-group">
                    <label class="form-label">Variação</label>
                    <div class="variation-options" id="variacoes">
                        <?php foreach ($variacoes as $i => $v): ?>
                            <button type="button"
                                class="variation-btn <?= $i === 0 ? "selected" : "" ?>"
                                data-id="<?= (int) $v["id"] ?>"
                                data-preco-ad="<?= (float) $v["preco_adicional"] ?>"
                                data-estoque="<?= (int) $v["estoque"] ?>">
                                <?= e(trim(($v["tamanho"] ?? "") . " " . ($v["cor"] ?? ""))) ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                    <input type="hidden" name="variacao_id" id="variacao_id" value="<?= (int) ($variacoes[0]["id"] ?? 0) ?>">
                </div>
            <?php else: ?>
                <input type="hidden" name="variacao_id" id="variacao_id" value="">
            <?php endif; ?>

            <div class="form-group" style="max-width:120px">
                <label class="form-label">Quantidade</label>
                <input type="number" name="quantidade" class="form-control" value="1" min="1" max="<?= (int) $produto["estoque"] ?>" data-estoque="<?= (int) $produto["estoque"] ?>">
            </div>

            <button type="submit" class="btn btn-primary" style="margin-top:16px">Adicionar ao Carrinho</button>
        </form>

        <?php if (isset($_GET["sucesso"])): ?>
            <div class="alert alert-success" style="margin-top:12px">Produto adicionado ao carrinho com sucesso.</div>
        <?php elseif (isset($_GET["erro"])): ?>
            <div class="alert alert-error" style="margin-top:12px">Ocorreu um erro ao adicionar o produto. Tente novamente.</div>
        <?php endif; ?>

        <div id="add-msg" style="margin-top:12px"></div>
    </div>
</div>

<?php
$extraJs = "produto.js";
require __DIR__ . "/../includes/footer.php";
