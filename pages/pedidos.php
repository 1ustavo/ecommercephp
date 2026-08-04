<?php

require_once __DIR__ . "/../config/bootstrap.php";

$auth = new Auth();
$auth->verificarLogin();
$usuarioId = (int) $_SESSION["usuario_id"];
$orders = Pedido::porUsuario($usuarioId);
$orderId = isset($_GET["id"]) ? (int) $_GET["id"] : null;
$order = null;

if ($orderId) {
    $order = Pedido::buscar($orderId);
    if (!$order || $order["usuario_id"] !== $usuarioId) {
        $order = null;
    }
}

$pageTitle = "Meus Pedidos";
require __DIR__ . "/../includes/header.php";

$sucesso = flash("checkout_success");
$error = flash("checkout_error");
?>

<h1 class="page-title">Meus Pedidos</h1>

<?php if ($sucesso): ?>
    <div class="alert alert-success"><?= e($sucesso) ?></div>
<?php elseif ($error): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
<?php endif; ?>

<?php if ($order): ?>
    <div class="card">
        <h2>Pedido #<?= (int) $order["id"] ?> <span class="badge <?= e(statusPedidoClass($order["status"])) ?>"><?= e(statusPedidoLabel($order["status"])) ?></span></h2>
        <p><strong>Data:</strong> <?= e($order["created_at"]) ?></p>
        <p><strong>Entrega:</strong> <?= e($order["rua"]) ?>, <?= e($order["numero"]) ?><?= $order["complemento"] ? ", " . e($order["complemento"]) : "" ?> - <?= e($order["bairro"]) ?>, <?= e($order["cidade"]) ?>/<?= e($order["estado"]) ?> (CEP <?= e($order["cep"]) ?>)</p>
        <p><strong>Frete:</strong> <?= formatarMoeda((float) $order["frete"]) ?></p>
        <p><strong>Total:</strong> <?= formatarMoeda((float) $order["total"]) ?></p>
        <h3 style="margin-top:24px">Itens</h3>
        <?php $items = Pedido::itens($order["id"]); ?>
        <table style="width:100%;border-collapse:collapse;">
            <thead>
                <tr>
                    <th style="text-align:left;padding:8px;border-bottom:1px solid #ddd">Produto</th>
                    <th style="text-align:right;padding:8px;border-bottom:1px solid #ddd">Quantidade</th>
                    <th style="text-align:right;padding:8px;border-bottom:1px solid #ddd">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td style="padding:8px;border-bottom:1px solid #f0f0f0"><?= e($item["nome_produto"]) ?><?= $item["variacao_descricao"] ? "<br><small style=\"color:#6b7280;\">" . e($item["variacao_descricao"]) . "</small>" : "" ?></td>
                        <td style="padding:8px;border-bottom:1px solid #f0f0f0;text-align:right"><?= (int) $item["quantidade"] ?></td>
                        <td style="padding:8px;border-bottom:1px solid #f0f0f0;text-align:right"><?= formatarMoeda((float) $item["subtotal"]) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <a href="<?= pageUrl("pedidos.php") ?>" class="btn btn-gray" style="margin-top:16px;display:inline-block">Voltar para pedidos</a>
    </div>
<?php else: ?>
    <?php if (empty($orders)): ?>
        <div class="empty-state">
            <h3>Você ainda não realizou pedidos.</h3>
            <p>Explore a loja e finalize sua primeira compra.</p>
            <a href="<?= pageUrl("loja.php") ?>" class="btn btn-primary" style="margin-top:16px">Ir para a loja</a>
        </div>
    <?php else: ?>
        <div class="card-grid" style="grid-template-columns: 1fr; gap:24px;">
            <?php foreach ($orders as $orderItem): ?>
                <div class="card" style="padding:20px;">
                    <h2>Pedido #<?= (int) $orderItem["id"] ?> <span class="badge <?= e(statusPedidoClass($orderItem["status"])) ?>"><?= e(statusPedidoLabel($orderItem["status"])) ?></span></h2>
                    <p><strong>Data:</strong> <?= e($orderItem["created_at"]) ?></p>
                    <p><strong>Total:</strong> <?= formatarMoeda((float) $orderItem["total"]) ?></p>
                    <a href="<?= pageUrl("pedidos.php") ?>?id=<?= (int) $orderItem["id"] ?>" class="btn btn-primary" style="margin-top:12px;display:inline-block">Ver detalhes</a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . "/../includes/footer.php"; ?>
