<?php

require_once __DIR__ . "/../config/bootstrap.php";

$filtros = [
    "categoria" => $_GET["categoria"] ?? "",
    "busca" => trim($_GET["busca"] ?? ""),
    "preco_min" => $_GET["preco_min"] ?? "",
    "preco_max" => $_GET["preco_max"] ?? "",
    "ordenar" => $_GET["ordenar"] ?? "recentes",
];

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
<?php endif; ?>

<?php require __DIR__ . "/../includes/footer.php"; ?>
