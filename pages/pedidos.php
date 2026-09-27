<?php

require_once __DIR__ . "/../config/bootstrap.php";

$auth = new Auth();
$auth->verificarLogin();
$usuarioId = (int) $_SESSION["usuario_id"];

// Paginação — 20 pedidos por página
$limitePorPagina = 20;
$paginaAtual = isset($_GET["pagina"]) ? max(1, (int) $_GET["pagina"]) : 1;
$totalPedidosUsuario = Pedido::contarPorUsuario($usuarioId);
$totalPaginas = max(1, (int) ceil($totalPedidosUsuario / $limitePorPagina));
$paginaAtual = min($paginaAtual, $totalPaginas);

$orders = Pedido::porUsuario($usuarioId, $paginaAtual, $limitePorPagina);
$orderId = isset($_GET["id"]) ? (int) $_GET["id"] : null;
$order = null;

if ($orderId) {
    $order = Pedido::buscar($orderId);
    // Cast explícito: comparação int vs int (valor do banco pode vir como string)
    if (!$order || (int) $order["usuario_id"] !== $usuarioId) {
        $order = null;
    }
}

// Reembolso: solicitação existente deste pedido e elegibilidade
// (mesma regra do modelo: PAGO/ENVIADO/ENTREGUE; pode re-solicitar após RECUSADO)
$reembolsoPedido = $order ? Reembolso::buscarPorPedido((int) $order["id"], $usuarioId) : null;
$reembolsoElegivel = $order && in_array($order["status"], Reembolso::STATUS_ELEGIVEIS, true)
    && (!$reembolsoPedido || $reembolsoPedido["status"] === "RECUSADO");

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
        <!-- Botão para abrir ticket de atendimento -->
        <?php
        // Verifica se já existe ticket aberto para este pedido
        $ticketExiste = false;
        try {
            $stmt = $pdo->prepare("
                SELECT id FROM tickets 
                WHERE pedido_id = ? AND usuario_id = ? AND status IN ('ABERTO', 'EM_ANDAMENTO')
            ");
            $stmt->execute([$order["id"], $usuarioId]);
            $ticketExiste = (bool) $stmt->fetch();
        } catch (Exception $e) {
            $ticketExiste = false;
        }
        ?>
        
        <?php if (!$ticketExiste): ?>
            <button type="button" 
                    class="btn btn-primary" 
                    id="btn-abrir-ticket" 
                    data-pedido-id="<?= (int) $order["id"] ?>"
                    style="margin-top: 16px;">
                💬 Falar com Atendente
            </button>
        <?php else: ?>
            <a href="<?= pageUrl("ticket.php") ?>?id=<?= $ticketExiste ? $ticketExiste : '' ?>" 
               class="btn btn-primary" 
               style="margin-top: 16px; display: inline-block;">
                💬 Ver Meu Atendimento
            </a>
        <?php endif; ?>

        <?php if ($reembolsoPedido): ?>
            <p style="margin-top:16px">
                <strong>Reembolso:</strong>
                <span class="badge <?= $reembolsoPedido["status"] === "APROVADO" ? "badge-pago" : ($reembolsoPedido["status"] === "RECUSADO" ? "badge-cancelado" : "badge-pendente") ?>">
                    <?= e($reembolsoPedido["status"]) ?>
                </span>
                <?php if ($reembolsoPedido["status"] === "RECUSADO" && !empty($reembolsoPedido["motivo_recusa"])): ?>
                    <small style="color:var(--cinza-600)"> — Motivo: <?= e($reembolsoPedido["motivo_recusa"]) ?></small>
                <?php endif; ?>
            </p>
        <?php endif; ?>

        <?php if ($reembolsoElegivel): ?>
            <button type="button" class="btn btn-outline" id="btn-solicitar-reembolso"
                    data-pedido-id="<?= (int) $order["id"] ?>" style="margin-top:16px">
                Solicitar reembolso
            </button>
        <?php endif; ?>
        
        <a href="<?= pageUrl("pedidos.php") ?>" class="btn btn-gray" style="margin-top:16px;display:inline-block;margin-left: 8px;">Voltar para pedidos</a>
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

        <!-- Paginação -->
        <?php if ($totalPaginas > 1): ?>
            <?php
            // Gera a lista de páginas com janela deslizante
            $paginas = paginacaoJanela($paginaAtual, $totalPaginas, 2);
            ?>
            <div class="pagination-container">
                <?php if ($paginaAtual > 1): ?>
                    <a href="<?= pageUrl("pedidos.php") ?>?pagina=<?= $paginaAtual - 1 ?>" class="btn btn-gray btn-sm">← Anterior</a>
                <?php endif; ?>

                <?php foreach ($paginas as $pagina): ?>
                    <?php if ($pagina === ".."): ?>
                        <span class="btn btn-sm" style="pointer-events:none;color:var(--cinza-500)">..</span>
                    <?php elseif ($pagina === $paginaAtual): ?>
                        <span class="btn btn-primary btn-sm" style="pointer-events:none"><?= $pagina ?></span>
                    <?php else: ?>
                        <a href="<?= pageUrl("pedidos.php") ?>?pagina=<?= $pagina ?>" class="btn btn-gray btn-sm"><?= $pagina ?></a>
                    <?php endif; ?>
                <?php endforeach; ?>

                <?php if ($paginaAtual < $totalPaginas): ?>
                    <a href="<?= pageUrl("pedidos.php") ?>?pagina=<?= $paginaAtual + 1 ?>" class="btn btn-gray btn-sm">Próxima →</a>
                <?php endif; ?>
            </div>
            <p class="pagination-info">
                Página <?= $paginaAtual ?> de <?= $totalPaginas ?> • <?= $totalPedidosUsuario ?> pedidos no total
            </p>
        <?php endif; ?>
    <?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . "/../includes/footer.php"; ?>

<!-- Modal para abertura de ticket -->
<div id="modal-ticket" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center;">
    <div style="background: white; border-radius: 8px; padding: 24px; max-width: 500px; width: 90%; max-height: 90vh; overflow-y: auto;">
        <h2 style="font-size: 20px; font-weight: 600; margin-bottom: 16px;">Abrir Chamado de Atendimento</h2>
        
        <form id="form-abrir-ticket">
            <input type="hidden" id="ticket-pedido-id">
            
            <div style="margin-bottom: 16px;">
                <label style="font-size: 14px; font-weight: 500; margin-bottom: 6px; display: block;">Assunto *</label>
                <input type="text" 
                       id="ticket-assunto" 
                       required
                       minlength="5"
                       style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px;"
                       placeholder="Descreva brevemente o problema">
            </div>

            <div style="margin-bottom: 16px;">
                <label style="font-size: 14px; font-weight: 500; margin-bottom: 6px; display: block;">Categoria</label>
                <select id="ticket-categoria" style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px;">
                    <option value="GERAL">Geral</option>
                    <option value="PRODUTO">Produto</option>
                    <option value="ENTREGA">Entrega</option>
                    <option value="PAGAMENTO">Pagamento</option>
                    <option value="TROCA">Troca</option>
                    <option value="DEVOLUCAO">Devolução</option>
                    <option value="OUTRO">Outro</option>
                </select>
            </div>

            <div style="margin-bottom: 16px;">
                <label style="font-size: 14px; font-weight: 500; margin-bottom: 6px; display: block;">Mensagem</label>
                <textarea id="ticket-mensagem" 
                          rows="4"
                          style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; font-family: inherit;"
                          placeholder="Descreva detalhes do seu problema..."></textarea>
            </div>

            <div style="display: flex; gap: 8px; justify-content: flex-end;">
                <button type="button" class="btn btn-gray" id="btn-fechar-modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">Abrir Chamado</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const btnAbrirTicket = document.getElementById("btn-abrir-ticket");
    const modal = document.getElementById("modal-ticket");
    const form = document.getElementById("form-abrir-ticket");
    const btnFecharModal = document.getElementById("btn-fechar-modal");

    if (btnAbrirTicket && modal) {
        btnAbrirTicket.addEventListener("click", function () {
            const pedidoId = this.dataset.pedidoId;
            document.getElementById("ticket-pedido-id").value = pedidoId;
            modal.style.display = "flex";
        });
    }

    if (btnFecharModal && modal) {
        btnFecharModal.addEventListener("click", function () {
            modal.style.display = "none";
        });
    }

    if (modal) {
        modal.addEventListener("click", function (e) {
            if (e.target === modal) {
                modal.style.display = "none";
            }
        });
    }

    if (form) {
        form.addEventListener("submit", function (e) {
            e.preventDefault();

            const pedidoId = document.getElementById("ticket-pedido-id").value;
            const assunto = document.getElementById("ticket-assunto").value.trim();
            const categoria = document.getElementById("ticket-categoria").value;
            const mensagem = document.getElementById("ticket-mensagem").value.trim();

            if (!assunto || assunto.length < 5) {
                alert("Assunto deve ter pelo menos 5 caracteres.");
                return;
            }

            const btn = form.querySelector('button[type="submit"]');
            const originalText = btn.textContent;
            btn.disabled = true;
            btn.textContent = "Abrindo...";

            const csrfInput = document.querySelector('input[name=csrf_token]');
            const csrfToken = csrfInput !== null ? csrfInput.value : "";

            fetch("<?= apiUrl("ticket_abrir.php") ?>", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
<!-- Modal: solicitar reembolso (mesmo padrão do modal de ticket acima) -->
<div id="modal-reembolso" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
    <div style="background:white; border-radius:8px; padding:24px; max-width:480px; width:90%;">
        <h2 style="font-size:20px; font-weight:600; margin:0 0 8px;">Solicitar reembolso — Pedido #<span id="reembolso-pedido-id"></span></h2>
        <div class="form-group">
            <label class="form-label" for="motivo-reembolso">Motivo do reembolso *</label>
            <textarea id="motivo-reembolso" class="form-control" rows="4"
                      placeholder="Descreva o motivo (mínimo 10 caracteres)..."></textarea>
        </div>
        <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:16px;">
            <button type="button" class="btn btn-gray" id="btn-cancelar-reembolso">Cancelar</button>
            <button type="button" class="btn btn-primary" id="btn-confirmar-reembolso">Enviar solicitação</button>
        </div>
    </div>
</div>
<input type="hidden" id="csrf-token" value="<?= e(csrf_token()) ?>">

<script>
(function () {
    const csrfToken = document.getElementById("csrf-token")?.value || "";
    const modal = document.getElementById("modal-reembolso");
    const btn = document.getElementById("btn-solicitar-reembolso");
    const btnConfirmar = document.getElementById("btn-confirmar-reembolso");
    const btnCancelar = document.getElementById("btn-cancelar-reembolso");

    const abrirModal = () => {
        const span = document.getElementById("reembolso-pedido-id");
        if (span) span.textContent = btn?.dataset.pedidoId || "";
        if (modal) modal.style.display = "flex";
    };
    const fecharModal = () => { if (modal) modal.style.display = "none"; };

    btn?.addEventListener("click", abrirModal);
    btnCancelar?.addEventListener("click", fecharModal);
    modal?.addEventListener("click", (e) => { if (e.target === modal) fecharModal(); });

    btnConfirmar?.addEventListener("click", async () => {
        const motivo = document.getElementById("motivo-reembolso")?.value.trim() || "";
        if (motivo.length < 10) { alert("Descreva o motivo em pelo menos 10 caracteres."); return; }
        btnConfirmar.disabled = true;
        try {
            const res = await fetch("<?= apiUrl("solicitar_reembolso.php") ?>", {
                method: "POST",
                headers: { "Content-Type": "application/json", "X-CSRF-Token": csrfToken },
                body: JSON.stringify({ pedido_id: btn?.dataset.pedidoId, motivo })
            });
            const r = await res.json();
            if (r.sucesso) { location.reload(); }
            else { alert(r.erro || "Erro ao solicitar reembolso."); btnConfirmar.disabled = false; }
        } catch (e) {
            alert("Erro de conexão. Tente novamente.");
            btnConfirmar.disabled = false;
        }
    });
})();
</script>
                    "X-CSRF-Token": csrfToken,
                },
                body: JSON.stringify({
                    pedido_id: pedidoId,
                    assunto: assunto,
                    categoria: categoria,
                    mensagem: mensagem,
                }),
            })
            .then((response) => response.json())
            .then((data) => {
                if (data.sucesso) {
                    alert("Chamado aberto com sucesso! Redirecionando...");
                    setTimeout(() => {
                        window.location.href = "<?= pageUrl("ticket.php") ?>?id=" + data.ticket_id;
                    }, 1000);
                } else {
                    alert(data.erro || "Erro ao abrir chamado.");
                    btn.disabled = false;
                    btn.textContent = originalText;
                }
            })
            .catch(() => {
                alert("Erro de conexão. Tente novamente.");
                btn.disabled = false;
                btn.textContent = originalText;
            });
        });
    }
});
</script>
