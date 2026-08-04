<?php

require_once __DIR__ . "/../../config/bootstrap.php";

$pageTitle = "Pedidos";
$currentAdminPage = "pedidos";
require __DIR__ . "/../../includes/admin-header.php";

$pedidos = Pedido::todosAdmin();
?>

<h1 class="page-title">Pedidos</h1>

<?php if (empty($pedidos)): ?>
    <div class="empty-state">
        <h3>Sem pedidos registrados.</h3>
    </div>
<?php else: ?>
    <div class="card-grid" style="grid-template-columns: 1fr; gap:20px;">
        <?php foreach ($pedidos as $pedido): ?>
            <div class="card">
                <h2>Pedido #<?= (int) $pedido["id"] ?> <span class="badge <?= e(statusPedidoClass($pedido["status"])) ?>"><?= e(statusPedidoLabel($pedido["status"])) ?></span></h2>
                <p><strong>Cliente:</strong> <?= e($pedido["cliente_nome"]) ?></p>
                <p><strong>Total:</strong> <?= formatarMoeda((float) $pedido["total"]) ?></p>
                <p><strong>Data:</strong> <?= e($pedido["created_at"]) ?></p>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . "/../../includes/admin-footer.php"; ?>
