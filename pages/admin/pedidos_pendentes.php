<?php

require_once __DIR__ . "/../../config/bootstrap.php";

$pageTitle = "Pedidos Pendentes";
$currentAdminPage = "pedidos_pendentes";
$extraJs = "admin-pedidos-pendentes.js";
require __DIR__ . "/../../includes/admin-header.php";

// Paginação — 10 pedidos por página
$limitePorPagina = 10;
$paginaAtual = isset($_GET["pagina"]) ? max(1, (int) $_GET["pagina"]) : 1;
$totalPendentes = Pedido::contarPendentesAprovacao();
$totalPaginas = max(1, (int) ceil($totalPendentes / $limitePorPagina));
$paginaAtual = min($paginaAtual, $totalPaginas);

$pendentes = Pedido::pendentesAprovacao($paginaAtual, $limitePorPagina);
foreach ($pendentes as &$p) {
    $p["itens"] = Pedido::itens((int) $p["id"]);
}
unset($p);

$stats = Pedido::estatisticas();
?>

<h1 class="page-title">Pedidos Pendentes de Aprovação</h1>
<p class="page-subtitle">Aprove ou recuse os pedidos antes de eles seguirem para pagamento e separação.</p>

<div class="stats-grid" style="margin-bottom:24px">
    <div class="stat-card">
        <div class="stat-value"><?= (int) $stats["aguardando_aprovacao"] ?></div>
        <div class="stat-label">Aguardando aprovação</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= count($pendentes) ?></div>
        <div class="stat-label">Exibidos nesta página</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= $totalPendentes ?></div>
        <div class="stat-label">Total aguardando</div>
    </div>
</div>

<div id="lista-pendentes">
    <?php if (empty($pendentes)): ?>
        <div class="empty-state">
            <h3>Nenhum pedido aguardando aprovação</h3>
            <p>Quando clientes finalizarem compras, os pedidos aparecerão aqui para revisão.</p>
        </div>
    <?php else: ?>
        <div class="card-grid" style="grid-template-columns: 1fr; gap:20px;">
            <?php foreach ($pendentes as $pedido): ?>
                <?php
                $data = new DateTime($pedido["created_at"]);
                $totalItens = array_sum(array_map(fn($i) => (int) $i["quantidade"], $pedido["itens"]));
                ?>
                <div class="card pedido-card" data-pedido-id="<?= (int) $pedido["id"] ?>">
                    <div class="flex justify-between items-start gap-16 flex-wrap">
                        <div>
                            <h2 style="margin:0 0 4px">Pedido #<?= (int) $pedido["id"] ?>
                                <span class="badge <?= statusPedidoClass($pedido["status"]) ?>"><?= e(statusPedidoLabel($pedido["status"])) ?></span>
                            </h2>
                            <p style="margin:0;color:var(--cinza-600);font-size:0.9rem">
                                Cliente: <strong><?= e($pedido["cliente_nome"] ?? "—") ?></strong>
                                (<?= e($pedido["cliente_email"] ?? "—") ?>)
                            </p>
                            <p style="margin:4px 0 0;color:var(--cinza-600);font-size:0.85rem">
                                <?= $data->format("d/m/Y H:i") ?> • <?= $totalItens ?> item(ns)
                            </p>
                        </div>
                        <div style="text-align:right">
                            <div style="font-size:1.3rem;font-weight:700;color:var(--verde-mato)">
                                <?= formatarMoeda((float) $pedido["total"]) ?>
                            </div>
                            <div style="font-size:0.8rem;color:var(--cinza-600)">
                                Frete: <?= formatarMoeda((float) $pedido["frete"]) ?> •
                                Pagto: <?= e($pedido["metodo_pagamento"] ?? "—") ?>
                            </div>
                        </div>
                    </div>

                    <div style="margin-top:16px">
                        <strong style="font-size:0.85rem;color:var(--cinza-600)">Itens:</strong>
                        <ul style="margin:8px 0 0;padding-left:20px;font-size:0.9rem">
                            <?php foreach ($pedido["itens"] as $item): ?>
                                <li>
                                    <?= (int) $item["quantidade"] ?>x
                                    <strong><?= e($item["nome_produto"]) ?></strong>
                                    <?php if (!empty($item["variacao_descricao"])): ?>
                                        <span style="color:var(--cinza-600)"> (<?= e($item["variacao_descricao"]) ?>)</span>
                                    <?php endif; ?>
                                    — <?= formatarMoeda((float) $item["subtotal"]) ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>

                    <?php
                    $endereco = trim(
                        ($pedido["rua"] ?? "") . ", " . ($pedido["numero"] ?? "") .
                        " " . ($pedido["complemento"] ?? "") .
                        " — " . ($pedido["bairro"] ?? "") . ", " .
                        ($pedido["cidade"] ?? "") . "/" . ($pedido["estado"] ?? "")
                    );
                    ?>
                    <?php if ($endereco): ?>
                        <p style="margin:12px 0 0;color:var(--cinza-600);font-size:0.85rem">
                            <strong>Entrega:</strong> <?= e($endereco) ?> • CEP <?= e($pedido["cep"] ?? "—") ?>
                        </p>
                    <?php endif; ?>

                    <div class="flex gap-10 mt-18 flex-wrap">
                        <button type="button"
                                class="btn btn-primary btn-aprovar"
                                data-pedido-id="<?= (int) $pedido["id"] ?>">
                            Aceitar pedido
                        </button>
                        <button type="button"
                                class="btn btn-outline btn-recusar"
                                data-pedido-id="<?= (int) $pedido["id"] ?>">
                            Recusar pedido
                        </button>
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
                    <a href="<?= pageUrl("admin/pedidos_pendentes.php") ?>?pagina=<?= $paginaAtual - 1 ?>" class="btn btn-gray btn-sm">← Anterior</a>
                <?php endif; ?>

                <?php foreach ($paginas as $pagina): ?>
                    <?php if ($pagina === ".."): ?>
                        <span class="btn btn-sm" style="pointer-events:none;color:var(--cinza-500)">..</span>
                    <?php elseif ($pagina === $paginaAtual): ?>
                        <span class="btn btn-primary btn-sm" style="pointer-events:none"><?= $pagina ?></span>
                    <?php else: ?>
                        <a href="<?= pageUrl("admin/pedidos_pendentes.php") ?>?pagina=<?= $pagina ?>" class="btn btn-gray btn-sm"><?= $pagina ?></a>
                    <?php endif; ?>
                <?php endforeach; ?>

                <?php if ($paginaAtual < $totalPaginas): ?>
                    <a href="<?= pageUrl("admin/pedidos_pendentes.php") ?>?pagina=<?= $paginaAtual + 1 ?>" class="btn btn-gray btn-sm">Próxima →</a>
                <?php endif; ?>
            </div>
            <p class="pagination-info">
                Página <?= $paginaAtual ?> de <?= $totalPaginas ?> • <?= $totalPendentes ?> pedidos aguardando aprovação
            </p>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Modal de recusa (motivo obrigatório) -->
<div id="modal-recusar" class="modal-overlay" style="display:none">
    <div class="modal card" style="max-width:480px;width:90%">
        <h2 style="margin-top:0">Recusar pedido #<span id="modal-pedido-id"></span></h2>
        <p style="color:var(--cinza-600);font-size:0.9rem">
            Informe o motivo da recusa. O cliente receberá essa justificativa.
        </p>
        <div class="form-group" style="margin-top:16px">
            <label class="form-label" for="motivo-recusa">Motivo da recusa *</label>
            <textarea id="motivo-recusa"
                      class="form-control"
                      rows="4"
                      placeholder="Ex: Estoque insuficiente, dados de entrega inconsistentes, suspeita de fraude..."
                      required></textarea>
            <div class="invalid-feedback" id="motivo-erro" style="display:none">
                O motivo é obrigatório para recusar o pedido.
            </div>
        </div>
        <div class="flex gap-10 justify-end mt-16">
            <button type="button" class="btn btn-gray" id="btn-cancelar-recusa">Cancelar</button>
            <button type="button" class="btn btn-outline" id="btn-confirmar-recusa" style="border-color:var(--vermelho);color:var(--vermelho)">
                Confirmar recusa
            </button>
        </div>
    </div>
</div>

<?php require __DIR__ . "/../../includes/admin-footer.php"; ?>