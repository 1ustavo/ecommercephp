<?php

require_once __DIR__ . "/../../config/bootstrap.php";

// Página restrita a ADMIN/GERENTE — FUNCIONARIO é redirecionado
// (a autorização real continua no requererPapel() das APIs)
if (!temPapel("ADMIN", "GERENTE")) {
    header("Location: " . pageUrl("admin/pedidos_pendentes.php"));
    exit;
}

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

<?php if (!temPapel("ADMIN", "GERENTE")): ?>
    <div class="alert alert-error">Acesso restrito a ADMIN/GERENTE. Relatórios financeiros não estão disponíveis para o seu papel.</div>
<?php else: ?>
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

<script src="<?= asset("js/admin-relatorios.js") ?>"></script>
<?php endif; ?>
<?php require __DIR__ . "/../../includes/admin-footer.php"; ?>