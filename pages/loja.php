<?php

require_once __DIR__ . "/../config/bootstrap.php";

// Reset de filtros
if (isset($_GET["reset"])) {
    session_destroy();
    header("Location: " . pageUrl("loja.php"));
    exit;
}

$filtros = [
    "categoria" => $_GET["categoria"] ?? "",
    "marca" => $_GET["marca"] ?? "",
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
$marcas = Produto::marcas();
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
        <label class="form-label">Marca</label>
        <select name="marca" class="form-control">
            <option value="">Todas</option>
            <?php foreach ($marcas as $marca): ?>
                <option value="<?= e($marca["slug"]) ?>" <?= $filtros["marca"] === $marca["slug"] ? "selected" : "" ?>>
                    <?= e($marca["nome"]) ?>
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
        <h3>Nenhum produto encontrado para estes filtros</h3>
        <p>Tente ajustar os filtros de busca ou <a href="<?= pageUrl("loja.php") ?>?reset=1" class="btn btn-gray btn-sm">limpar filtros</a>.</p>
    </div>
<?php else: ?>
    <div class="card-grid">
        <?php foreach ($produtos as $p): ?>
            <a href="<?= pageUrl("produto.php?slug=" . urlencode($p["slug"])) ?>" class="product-card">
                <div class="product-img">
                    <?php if (!empty($p["imagem"]) && !str_starts_with($p["imagem"], 'placeholder:')): ?>
                        <img src="<?= baseUrl() . '/' . $p["imagem"] ?>" alt="<?= e($p["nome"]) ?>">
                    <?php else: ?>
                        <div style="background:<?= corPlaceholder($p["slug"]) ?>;width:100%;height:100%"></div>
                    <?php endif; ?>
                </div>
                <div class="product-body">
                    <div class="product-category">
                        <?= e($p["categoria_nome"]) ?>
                        <?php if (!empty($p["marca_nome"])): ?>
                            • <?= e($p["marca_nome"]) ?>
                        <?php endif; ?>
                    </div>
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

<script>
function updateFilterBadge(input, filterType) {
    const value = input.value.trim();
    const containerId = 'filtros-' + filterType + '-ativos';
    const container = document.getElementById(containerId);
    
    if (!container) return;
    
    // Remove existing badges for this filter type
    container.innerHTML = '';
    
    if (!value) return;
    
    // Create badge
    const badge = document.createElement('span');
    badge.className = 'badge-filter';
    badge.innerHTML = value + ' <span class="badge-filter-close" onclick="removerFiltro(\'' + filterType + '\')">×</span>';
    container.appendChild(badge);
}

function removerFiltro(filterType) {
    // Remove the visual badge and reset the input
    const container = document.getElementById('filtros-' + filterType + '-ativos');
    if (container) {
        container.innerHTML = '';
    }
    // Note: Remover filtro de verdade requer submit do formulário com valor vazio
    // ou atualização via AJAX. Aqui apenas removemos o visual.
}

document.addEventListener('DOMContentLoaded', function() {
    // Inicializar badges a partir dos valores atuais
    const filtros = {
        'busca': '<?= e($filtros["busca"]) ?>',
        'categoria': '<?= e($filtros["categoria"]) ?>',
        'preco_min': '<?= e($filtros["preco_min"]) ?>',
        'preco_max': '<?= e($filtros["preco_max"]) ?>'
    };
    
    for (const [tipo, valor] of Object.entries(filtros)) {
        if (valor) {
            const containerId = 'filtros-' + tipo + '-ativos';
            const container = document.getElementById(containerId);
            if (container) {
                const badge = document.createElement('span');
                badge.className = 'badge-filter';
                badge.innerHTML = valor + ' <span class="badge-filter-close" onclick="removerFiltro(\'' + tipo + '\')">×</span>';
                container.appendChild(badge);
            }
        }
    }
});
</script>
</parameter>
</replace_in_file>
