<?php

require_once __DIR__ . "/../../config/bootstrap.php";

$pageTitle = "Reembolsos";
$currentAdminPage = "reembolsos";
require __DIR__ . "/../../includes/admin-header.php";

// Matriz de papéis: FUNCIONARIO só lê a lista (botões escondidos);
// a decisão real é aplicada na API (requererPapel(["ADMIN","GERENTE"])).
$podeDecidir = temPapel("ADMIN", "GERENTE");

// Filtro por status (?status=SOLICITADO|APROVADO|RECUSADO) — default: SOLICITADO
$limitePorPagina = 10;
$filtroStatus = strtoupper(trim($_GET["status"] ?? "SOLICITADO"));
if (!in_array($filtroStatus, ["SOLICITADO", "APROVADO", "RECUSADO", ""], true)) {
    $filtroStatus = "SOLICITADO";
}

$totalReembolsos = Reembolso::contarAdmin($filtroStatus ?: null);
$totalPaginas = max(1, (int) ceil($totalReembolsos / $limitePorPagina));
$paginaAtual = isset($_GET["pagina"]) ? min(max(1, (int) $_GET["pagina"]), $totalPaginas) : 1;

$reembolsos = Reembolso::listarAdmin($filtroStatus ?: null, $paginaAtual, $limitePorPagina);
$pendentes = Reembolso::contarAdmin("SOLICITADO");
?>

<h1 class="page-title">Reembolsos</h1>
<p class="page-subtitle">Aprove ou recuse solicitações de reembolso dos clientes.</p>

<!-- Filtros por status -->
<div class="flex gap-8" style="margin-bottom:16px">
    <a class="btn btn-sm <?= $filtroStatus === "SOLICITADO" ? "btn-primary" : "btn-gray" ?>"
       href="<?= pageUrl("admin/reembolsos.php") ?>?status=SOLICITADO">Solicitados (<?= $pendentes ?>)</a>
    <a class="btn btn-sm <?= $filtroStatus === "APROVADO" ? "btn-primary" : "btn-gray" ?>"
       href="<?= pageUrl("admin/reembolsos.php") ?>?status=APROVADO">Aprovados</a>
    <a class="btn btn-sm <?= $filtroStatus === "RECUSADO" ? "btn-primary" : "btn-gray" ?>"
       href="<?= pageUrl("admin/reembolsos.php") ?>?status=RECUSADO">Recusados</a>
    <a class="btn btn-sm <?= $filtroStatus === "" ? "btn-primary" : "btn-gray" ?>"
       href="<?= pageUrl("admin/reembolsos.php") ?>?status=">Todos</a>
</div>

<input type="hidden" id="csrf-token" value="<?= e(csrf_token()) ?>">

<div id="lista-reembolsos">
    <?php if (empty($reembolsos)): ?>
        <div class="empty-state">
            <h3>Nenhuma solicitação de reembolso neste filtro.</h3>
        </div>
    <?php else: ?>
        <div class="card-grid" style="grid-template-columns: 1fr; gap:20px;">
            <?php foreach ($reembolsos as $r): ?>
                <?php
                $data = new DateTime($r["created_at"]);
                $badge = match ($r["status"]) {
                    "APROVADO" => "badge-pago",
                    "RECUSADO" => "badge-cancelado",
                    default => "badge-pendente",
                };
                ?>
                <div class="card" data-reembolso-id="<?= (int) $r["id"] ?>">
                    <div class="flex justify-between items-start gap-16 flex-wrap">
                        <div>
                            <h2 style="margin:0 0 4px">Reembolso #<?= (int) $r["id"] ?>
                                <span class="badge <?= $badge ?>"><?= e($r["status"]) ?></span>
                            </h2>
                            <p style="margin:0;color:var(--cinza-600);font-size:0.9rem">
                                Pedido <a href="<?= pageUrl("admin/pedido_detalhe.php") ?>?id=<?= (int) $r["pedido_id"] ?>">#<?= (int) $r["pedido_id"] ?></a>
                                — <strong><?= e($r["cliente_nome"]) ?></strong> (<?= e($r["cliente_email"]) ?>)
                            </p>
                            <p style="margin:4px 0 0;color:var(--cinza-600);font-size:0.85rem">
                                <?= $data->format("d/m/Y H:i") ?> • Valor do pedido:
                                <?= formatarMoeda((float) $r["pedido_total"]) ?> • Status do pedido:
                                <?= e(statusPedidoLabel($r["pedido_status"])) ?>
                            </p>
                            <p style="margin:8px 0 0;font-size:0.9rem">
                                <strong>Motivo do cliente:</strong> <?= e($r["motivo"]) ?>
                            </p>
                            <?php if ($r["status"] === "RECUSADO" && !empty($r["motivo_recusa"])): ?>
                                <p style="margin:4px 0 0;color:var(--vermelho);font-size:0.85rem">
                                    <strong>Motivo da recusa:</strong> <?= e($r["motivo_recusa"]) ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($podeDecidir && $r["status"] === "SOLICITADO"): ?>
                        <div class="flex gap-10 mt-18">
                            <button type="button" class="btn btn-primary btn-aprovar-reembolso"
                                    data-reembolso-id="<?= (int) $r["id"] ?>">Aprovar reembolso</button>
                            <button type="button" class="btn btn-outline btn-recusar-reembolso"
                                    data-reembolso-id="<?= (int) $r["id"] ?>"
                                    style="border-color:var(--vermelho);color:var(--vermelho)">Recusar reembolso</button>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Paginação (janela deslizante, padrão das demais páginas) -->
        <?php if ($totalPaginas > 1): $paginas = paginacaoJanela($paginaAtual, $totalPaginas, 2); ?>
            <div class="pagination-container">
                <?php foreach ($paginas as $pagina): ?>
                    <?php if ($pagina === ".."): ?>
                        <span class="btn btn-sm" style="pointer-events:none;color:var(--cinza-500)">..</span>
                    <?php elseif ($pagina === $paginaAtual): ?>
                        <span class="btn btn-primary btn-sm" style="pointer-events:none"><?= $pagina ?></span>
                    <?php else: ?>
                        <a href="<?= pageUrl("admin/reembolsos.php") ?>?status=<?= e($filtroStatus) ?>&pagina=<?= $pagina ?>"
                           class="btn btn-gray btn-sm"><?= $pagina ?></a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            <p class="pagination-info">Página <?= $paginaAtual ?> de <?= $totalPaginas ?> • <?= $totalReembolsos ?> reembolsos</p>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Modal de recusa (motivo obrigatório) -->
<div id="modal-recusar-reembolso" class="modal-overlay" style="display:none">
    <div class="modal card" style="max-width:480px;width:90%">
        <h2 style="margin-top:0">Recusar reembolso #<span id="modal-reembolso-id"></span></h2>
        <p style="color:var(--cinza-600);font-size:0.9rem">Informe o motivo. O cliente receberá essa justificativa por e-mail.</p>
        <div class="form-group" style="margin-top:16px">
            <label class="form-label" for="motivo-recusa-reembolso">Motivo da recusa *</label>
            <textarea id="motivo-recusa-reembolso" class="form-control" rows="4" required></textarea>
        </div>
        <div class="flex gap-10 justify-end mt-16">
            <button type="button" class="btn btn-gray" id="btn-cancelar-recusa-reembolso">Cancelar</button>
            <button type="button" class="btn btn-outline" id="btn-confirmar-recusa-reembolso"
                    style="border-color:var(--vermelho);color:var(--vermelho)">Confirmar recusa</button>
        </div>
    </div>
</div>

<script src="<?= asset("js/admin-reembolsos.js") ?>"></script>
<?php require __DIR__ . "/../../includes/admin-footer.php"; ?>
