<?php

require_once __DIR__ . "/../../config/bootstrap.php";

$pageTitle = "Relatórios";
$currentAdminPage = "relatorios";
require __DIR__ . "/../../includes/admin-header.php";

$stats = Pedido::estatisticas();
?>

<h1 class="page-title">Relatórios</h1>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-value"><?= (int) $stats["total_pedidos"] ?></div>
        <div class="stat-label">Total de pedidos</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= formatarMoeda((float) $stats["faturamento"]) ?></div>
        <div class="stat-label">Faturamento líquido</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= (int) $stats["pendentes"] ?></div>
        <div class="stat-label">Pedidos pendentes</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= (int) $stats["produtos_ativos"] ?></div>
        <div class="stat-label">Produtos ativos</div>
    </div>
</div>

<?php require __DIR__ . "/../../includes/admin-footer.php"; ?>
