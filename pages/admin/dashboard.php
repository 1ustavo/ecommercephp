<?php

require_once __DIR__ . "/../../config/bootstrap.php";

$pageTitle = "Dashboard";
$currentAdminPage = "dashboard";
require __DIR__ . "/../../includes/admin-header.php";

$stats = Pedido::estatisticas();
?>

<h1 class="page-title">Painel Administrativo</h1>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-value"><?= (int) $stats["total_pedidos"] ?></div>
        <div class="stat-label">Total de pedidos</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= formatarMoeda((float) $stats["faturamento"]) ?></div>
        <div class="stat-label">Faturamento</div>
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
