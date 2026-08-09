<?php

require_once __DIR__ . "/../config/bootstrap.php";

$filtros = [
    "categoria" => $_GET["categoria"] ?? "",
    "busca" => trim($_GET["busca"] ?? ""),
    "preco_min" => $_GET["preco_min"] ?? "",
    "preco_max" => $_GET["preco_max"] ?? "",
    "ordenar" => $_GET["ordenar"] ?? "recentes",
    "pagina" => isset($_GET["pagina"]) ? max(1, (int) $_GET["pagina"]) : 1,
];

// Paginação — 20 produtos por página
$limitePorPagina = 20;
$totalProdutos = Produto::contarComFiltros($filtros);
$totalPaginas = max(1, (int) ceil($totalProdutos / $limitePorPagina));
$filtros["pagina"] = min($filtros["pagina"], $totalPaginas);

$produtos = Produto::listar($filtros);
$categorias = Produto::categorias();
$pageTitle = "Loja";

require __DIR__ . "/../includes/header.php";
?>

<h1 class="page-title">Catálogo de Produtos</h1>
<p class="page-subtitle">Encontre o que você precisa com busca e filtros</p>

<form class="filters-bar" method="get">
    <div class="form-group">
        <label class="form-label">Buscar</label>
        <input type="text" name="busca" class="form-control" placeholder="Nome do produto..." value="<?= e($filtros["busca"]) ?>">
    </div>
    <div class="form-group">
        <label class="form-label">Categoria</label>
        <select name="categoria" class="form-control">
            <option value="">Todas</option>
            <?php foreach ($categorias as $cat): ?>
                <option value="<?= e($cat["slug"]) ?>" <?= $filtros["categoria"] === $cat["slug"] ? "selected" : "" ?>>
                    <?= e($cat["nome"]) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label class="form-label">Preço mín.</label>
        <input type="number" name="preco_min" class="form-control" step="0.01" value="<?= e($filtros["preco_min"]) ?>">
    </div>
    <div class="form-group">
        <label class="form-label">Preço máx.</label>
        <input type="number" name="preco_max" class="form-control" step="0.01" value="<?= e($filtros["preco_max"]) ?>">
    </div>
    <div class="form-group">
        <label class="form-label">Ordenar</label>
        <select name="ordenar" class="form-control">
            <option value="recentes" <?= $filtros["ordenar"] === "recentes" ? "selected" : "" ?>>Mais recentes</option>
            <option value="preco_asc" <?= $filtros["ordenar"] === "preco_asc" ? "selected" : "" ?>>Menor preço</option>
            <option value="preco_desc" <?= $filtros["ordenar"] === "preco_desc" ? "selected" : "" ?>>Maior preço</option>
            <option value="nome" <?= $filtros["ordenar"] === "nome" ? "selected" : "" ?>>Nome A-Z</option>
        </select>
    </div>
    <button type="submit" class="btn btn-primary">Filtrar</button>
</form>

<?php if (empty($produtos)): ?>
    <div class="empty-state">
        <h3>Nenhum produto encontrado</h3>
        <p>Tente ajustar os filtros de busca.</p>
    </div>
<?php else: ?>
    <div class="card-grid">
        <?php foreach ($produtos as $p): ?>
            <a href="<?= pageUrl("produto.php?slug=" . urlencode($p["slug"])) ?>" class="product-card">
                <div class="product-img" style="background:<?= corPlaceholder($p["slug"]) ?>">
                
                </div>
                <div class="product-body">
                    <div class="product-category"><?= e($p["categoria_nome"]) ?></div>
                    <div class="product-name"><?= e($p["nome"]) ?></div>
                    <div class="product-price"><?= formatarMoeda((float) $p["preco"]) ?></div>
                </div>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Paginação -->
    <?php if ($totalPaginas > 1): ?>
        <?php
        // Monta a query string preservando os filtros atuais
        $queryParams = $_GET;
        unset($queryParams["pagina"]);
        $queryString = http_build_query($queryParams);

        // Gera a lista de páginas com janela deslizante
        $paginas = paginacaoJanela($filtros["pagina"], $totalPaginas, 2);
        ?>
        <div class="pagination-container">
            <?php if ($filtros["pagina"] > 1): ?>
                <a href="<?= pageUrl("loja.php") ?>?<?= $queryString ?>&pagina=<?= $filtros["pagina"] - 1 ?>" class="btn btn-gray btn-sm">← Anterior</a>
            <?php endif; ?>

            <?php foreach ($paginas as $pagina): ?>
                <?php if ($pagina === ".."): ?>
                    <span class="btn btn-sm" style="pointer-events:none;color:var(--cinza-500)">..</span>
                <?php elseif ($pagina === $filtros["pagina"]): ?>
                    <span class="btn btn-primary btn-sm" style="pointer-events:none"><?= $pagina ?></span>
                <?php else: ?>
                    <a href="<?= pageUrl("loja.php") ?>?<?= $queryString ?>&pagina=<?= $pagina ?>" class="btn btn-gray btn-sm"><?= $pagina ?></a>
                <?php endif; ?>
            <?php endforeach; ?>

            <?php if ($filtros["pagina"] < $totalPaginas): ?>
                <a href="<?= pageUrl("loja.php") ?>?<?= $queryString ?>&pagina=<?= $filtros["pagina"] + 1 ?>" class="btn btn-gray btn-sm">Próxima →</a>
            <?php endif; ?>
        </div>
        <p class="pagination-info">
            Página <?= $filtros["pagina"] ?> de <?= $totalPaginas ?> • <?= $totalProdutos ?> produtos no total
        </p>
    <?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . "/../includes/footer.php"; ?>
