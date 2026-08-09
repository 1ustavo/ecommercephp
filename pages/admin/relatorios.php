<?php

require_once __DIR__ . "/../../config/bootstrap.php";

$pageTitle = "Relatórios";
$currentAdminPage = "relatorios";
require __DIR__ . "/../../includes/admin-header.php";

$mesAtual = date("Y-m");

// Supondo que você tenha as variáveis $paginaAtual e $totalPaginas definidas
$paginaAtual = $_GET['page'] ?? 1; // Página atual
$totalPaginas = 15; // Total de páginas, ajuste conforme necessário

// Gera a lista de páginas usando a nova função
$paginas = paginacaoJanela($paginaAtual, $totalPaginas, 2);
?>

<h1 class="page-title">Relatórios</h1>
<p class="page-subtitle">Acompanhe o desempenho de vendas por mês ou no total geral.</p>

<!-- Seletor de mês -->
<div class="relatorio-selector" id="relatorio-container" data-mes="<?= e($mesAtual) ?>">
    <div class="relatorio-selector-nav">
        <button type="button" class="btn btn-gray btn-sm" id="btn-mes-anterior" aria-label="Mês anterior">←</button>
        <span class="relatorio-mes-label" id="mes-label"><?= e($mesAtual) ?></span>
        <button type="button" class="btn btn-gray btn-sm" id="btn-mes-proximo" aria-label="Próximo mês">→</button>
    </div>
    <button type="button" class="btn btn-outline btn-sm" id="btn-mes-total">Total Geral</button>
</div>

<!-- Loading -->
<div id="relatorio-loading" style="display:none;text-align:center;padding:40px;color:var(--cinza-500)">
    Carregando relatório...
</div>

<!-- Corpo do relatório (preenchido via AJAX) -->
<div id="relatorio-body"></div>

<!-- Renderiza a nova paginação -->
<!-- <div class="pagination">
    <?php // foreach ($paginas as $pagina): ?>
        <?php// if ($pagina === ".."): ?>
            <span>..</span> <!-- Reticências não clicáveis -->
        <!-- <?php //else: ?> -->
            <!-- <?php $classe // = ($pagina == $paginaAtual) ? "active" : ""; ?> -->
            <!-- <a class="<?= $classe ?>" href="?page=<?= $pagina ?>"><?= $pagina ?></a> -->
        <!-- <?php //endif; ?> -->
    <!-- <?php //endforeach; ?> -->
<!-- </div>  -->

<script src="<?= asset("js/admin-relatorios.js") ?>"></script>
<?php require __DIR__ . "/../../includes/admin-footer.php"; ?>