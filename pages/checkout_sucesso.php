<?php
require_once __DIR__ . "/../config/bootstrap.php";

$pageTitle = 'Pagamento concluído';
require __DIR__ . "/../includes/header.php";

$sessionId = $_GET['session_id'] ?? null;
?>

<h1 class="page-title">Pagamento concluído</h1>

<div class="card">
    <p>Obrigado! Seu pagamento foi concluído com sucesso. Em alguns instantes confirmaremos o recebimento e o pedido será atualizado.</p>
    <?php if ($sessionId): ?>
        <p><strong>ID da sessão:</strong> <?= e($sessionId) ?></p>
    <?php endif; ?>
    <p>Você pode acompanhar o status do pedido em <a href="<?= pageUrl('pedidos.php') ?>">Meus Pedidos</a>.</p>
</div>

<?php require __DIR__ . "/../includes/footer.php"; ?>