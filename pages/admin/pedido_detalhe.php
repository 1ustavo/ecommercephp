<?php
require_once __DIR__ . "/../../config/bootstrap.php";

$auth = new Auth();
$auth->verificarAdmin();

$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
if (!$id) {
    header("Location: " . pageUrl("admin/pedidos.php"));
    exit;
}

$pedido = Pedido::buscar($id);
if (!$pedido) {
    header("Location: " . pageUrl("admin/pedidos.php"));
    exit;
}

$itens = Pedido::itens($id);

$pageTitle = "Pedido #" . $id;
$currentAdminPage = "pedidos";
require __DIR__ . "/../../includes/admin-header.php";
?>

<h1 class="page-title">Pedido #<?= (int) $pedido['id'] ?> <span class="badge <?= e(statusPedidoClass($pedido['status'])) ?>"><?= e(statusPedidoLabel($pedido['status'])) ?></span></h1>

<div class="card">
    <p><strong>Cliente:</strong> <?= e($pedido['cliente_nome']) ?> (<?= e($pedido['cliente_email']) ?>)</p>
    <p><strong>Endereço:</strong> <?= e($pedido['rua']) ?>, <?= e($pedido['numero']) ?><?= $pedido['complemento'] ? ', ' . e($pedido['complemento']) : '' ?> - <?= e($pedido['bairro']) ?>, <?= e($pedido['cidade']) ?>/<?= e($pedido['estado']) ?> (CEP <?= e($pedido['cep']) ?>)</p>
    <p><strong>Frete:</strong> <?= formatarMoeda((float) $pedido['frete']) ?></p>
    <p><strong>Total:</strong> <?= formatarMoeda((float) $pedido['total']) ?></p>

    <h3 style="margin-top:16px">Itens</h3>
    <table style="width:100%;border-collapse:collapse;">
        <thead>
            <tr>
                <th style="text-align:left;padding:8px;border-bottom:1px solid #ddd">Produto</th>
                <th style="text-align:right;padding:8px;border-bottom:1px solid #ddd">Quantidade</th>
                <th style="text-align:right;padding:8px;border-bottom:1px solid #ddd">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($itens as $it): ?>
                <tr>
                    <td style="padding:8px;border-bottom:1px solid #f0f0f0"><?= e($it['nome_produto']) ?><?= $it['variacao_descricao'] ? '<br><small style="color:#6b7280;">' . e($it['variacao_descricao']) . '</small>' : '' ?></td>
                    <td style="padding:8px;border-bottom:1px solid #f0f0f0;text-align:right"><?= (int) $it['quantidade'] ?></td>
                    <td style="padding:8px;border-bottom:1px solid #f0f0f0;text-align:right"><?= formatarMoeda((float) $it['subtotal']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div style="margin-top:16px;display:flex;gap:8px;flex-wrap:wrap">
        <?php if ($podeAprovarPedido): ?>
            <?php if ($pedido['status'] === 'AGUARDANDO_APROVACAO' || $pedido['status'] === 'PENDENTE' || $pedido['status'] === 'PAGO'): ?>
                <button class="btn btn-primary" id="btn-aprovar">Aprovar pedido</button>
                <button class="btn btn-danger" id="btn-recusar">Recusar pedido</button>
            <?php endif; ?>

            <?php if ($pedido['status'] !== 'ENVIADO' && $pedido['status'] !== 'ENTREGUE' && $pedido['status'] !== 'CANCELADO' && $pedido['status'] !== 'RECUSADO'): ?>
                <button class="btn btn-outline" id="btn-marcar-enviado">Marcar como ENVIADO</button>
            <?php endif; ?>

            <?php if ($pedido['status'] === 'ENVIADO'): ?>
                <button class="btn btn-outline" id="btn-marcar-entregue">Marcar como ENTREGUE</button>
            <?php endif; ?>
        <?php endif; ?>

        <a class="btn btn-gray" href="<?= pageUrl('admin/pedidos.php') ?>">Voltar</a>
    </div>

    <?php if ($podeAprovarPedido): ?>
        <div id="recusa-form" style="display:none;margin-top:12px">
            <label class="form-label">Motivo da recusa</label>
            <textarea id="motivo-recusa" class="form-control" rows="3"></textarea>
            <div style="margin-top:8px">
                <button class="btn btn-danger" id="btn-confirmar-recusar">Confirmar recusa</button>
                <button class="btn btn-gray" id="btn-cancelar-recusa">Cancelar</button>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
const pedidoId = <?= (int) $pedido['id'] ?>;

async function postAction(action, body = {}){
    const csrfToken = '<?= e(csrf_token()) ?>';
    const res = await fetch('<?= apiUrl("admin/pedido_status.php") ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify(Object.assign({ pedido_id: pedidoId, action, csrf_token: csrfToken }, body))
    });
    return res.json();
}

document.getElementById('btn-aprovar')?.addEventListener('click', async () => {
    if (!confirm('Confirma aprovar este pedido?')) return;
    const r = await postAction('aprovar');
    if (r.sucesso) location.reload(); else alert(r.erro || 'Erro');
});

document.getElementById('btn-recusar')?.addEventListener('click', () => {
    document.getElementById('recusa-form').style.display = 'block';
});

document.getElementById('btn-cancelar-recusa')?.addEventListener('click', () => {
    document.getElementById('recusa-form').style.display = 'none';
});

document.getElementById('btn-confirmar-recusar')?.addEventListener('click', async () => {
    const motivo = document.getElementById('motivo-recusa').value.trim();
    if (!motivo) { alert('Informe o motivo da recusa'); return; }
    const r = await postAction('recusar', { motivo });
    if (r.sucesso) location.href = '<?= pageUrl('admin/pedidos.php') ?>'; else alert(r.erro || 'Erro');
});

document.getElementById('btn-marcar-enviado')?.addEventListener('click', async () => {
    if (!confirm('Marcar como ENVIADO?')) return;
    const r = await postAction('atualizar', { status: 'ENVIADO' });
    if (r.sucesso) location.reload(); else alert(r.erro || 'Erro');
});

document.getElementById('btn-marcar-entregue')?.addEventListener('click', async () => {
    if (!confirm('Marcar como ENTREGUE?')) return;
    const r = await postAction('atualizar', { status: 'ENTREGUE' });
    if (r.sucesso) location.reload(); else alert(r.erro || 'Erro');
});
</script>

<?php require __DIR__ . "/../../includes/admin-footer.php"; ?>