<?php

require_once __DIR__ . "/../../config/bootstrap.php";

$pageTitle = "Pedidos";
$currentAdminPage = "pedidos";
require __DIR__ . "/../../includes/admin-header.php";

// Paginação — máximo de 10 pedidos por página
$limitePorPagina = 10;
$paginaAtual = isset($_GET["pagina"]) ? max(1, (int) $_GET["pagina"]) : 1;
$totalPedidos = Pedido::contarTodos();
$totalPaginas = max(1, (int) ceil($totalPedidos / $limitePorPagina));
$paginaAtual = min($paginaAtual, $totalPaginas);

$pedidos = Pedido::todosAdmin($paginaAtual, $limitePorPagina);
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
                <div style="margin-top:12px">
                    <a class="btn btn-outline" href="<?= pageUrl("admin/pedido_detalhe.php") ?>?id=<?= (int) $pedido["id"] ?>">Ver detalhes</a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Paginação -->
    <?php if ($totalPaginas > 1): ?>
        <?php
        // Gera a lista de páginas com janela deslizante
        $paginas = paginacaoJanela($paginaAtual, $totalPaginas, 2);
        ?>
        <div class="pagination-container">
            <?php if ($paginaAtual > 1): ?>
                <a href="<?= pageUrl("admin/pedidos.php") ?>?pagina=<?= $paginaAtual - 1 ?>" class="btn btn-gray btn-sm">← Anterior</a>
            <?php endif; ?>

            <?php foreach ($paginas as $pagina): ?>
                <?php if ($pagina === ".."): ?>
                    <span class="btn btn-sm" style="pointer-events:none;color:var(--cinza-500)">..</span>
                <?php elseif ($pagina === $paginaAtual): ?>
                    <span class="btn btn-primary btn-sm" style="pointer-events:none"><?= $pagina ?></span>
                <?php else: ?>
                    <a href="<?= pageUrl("admin/pedidos.php") ?>?pagina=<?= $pagina ?>" class="btn btn-gray btn-sm"><?= $pagina ?></a>
                <?php endif; ?>
            <?php endforeach; ?>

            <?php if ($paginaAtual < $totalPaginas): ?>
                <a href="<?= pageUrl("admin/pedidos.php") ?>?pagina=<?= $paginaAtual + 1 ?>" class="btn btn-gray btn-sm">Próxima →</a>
            <?php endif; ?>
        </div>
        <p class="pagination-info">
            Página <?= $paginaAtual ?> de <?= $totalPaginas ?> • <?= $totalPedidos ?> pedidos no total
        </p>
    <?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . "/../../includes/admin-footer.php"; ?>
