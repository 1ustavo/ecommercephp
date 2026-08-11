<?php

require_once __DIR__ . "/../../config/bootstrap.php";

$pageTitle = "Cupons";
$currentAdminPage = "cupons";
require __DIR__ . "/../../includes/admin-header.php";

$cupons = Cupom::todosAdmin();
?>

<h1 class="page-title">Cupons de Desconto</h1>

<input type="hidden" id="csrf-token" value="<?= e(csrf_token()) ?>">

<div style="margin-bottom:16px">
    <button type="button" class="btn btn-primary" id="btn-novo-cupom">Novo Cupom</button>
</div>

<div class="card-grid" style="grid-template-columns: 1fr; gap:16px;">
    <?php if (empty($cupons)): ?>
        <div class="empty-state">
            <h3>Nenhum cupom cadastrado.</h3>
        </div>
    <?php else: ?>
        <?php foreach ($cupons as $cupom): ?>
            <div class="card" style="padding:16px;">
                <div class="flex justify-between items-center flex-wrap gap-12">
                    <div>
                        <h2 style="margin:0 0 4px"><?= e($cupom["codigo"]) ?></h2>
                        <p style="margin:0;color:var(--cinza-600);font-size:0.85rem">
                            <?= $cupom["tipo"] === "PERCENTUAL" ? $cupom["valor"] . "%" : formatarMoeda((float) $cupom["valor"]) ?>
                            de desconto • Usado <?= (int) $cupom["quantidade_usada"] ?>/<?= (int) $cupom["quantidade_maxima"] ?> vezes
                        </p>
                        <p style="margin:4px 0 0;color:var(--cinza-500);font-size:0.85rem">
                            Válido de <?= date("d/m/Y", strtotime($cupom["data_inicio"])) ?> até <?= date("d/m/Y", strtotime($cupom["data_fim"])) ?>
                            • Mínimo: <?= formatarMoeda((float) $cupom["valor_minimo_pedido"]) ?>
                        </p>
                    </div>
                    <div class="flex gap-8">
                        <button type="button" class="btn btn-sm btn-outline btn-editar" data-id="<?= (int) $cupom["id"] ?>">Editar</button>
                        <button type="button" class="btn btn-sm btn-outline btn-excluir" data-id="<?= (int) $cupom["id"] ?>" style="border-color:var(--vermelho);color:var(--vermelho)">Excluir</button>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Modal Cupom -->
<div id="modal-cupom" class="modal-overlay" style="display:none">
    <div class="modal card" style="max-width:520px;width:90%">
        <h2 style="margin-top:0" id="modal-titulo">Novo Cupom</h2>
        <form id="form-cupom">
            <input type="hidden" id="cupom-id">
            <div class="form-group">
                <label class="form-label" for="cupom-codigo">Código *</label>
                <input type="text" id="cupom-codigo" class="form-control" required>
            </div>
            <div class="form-group">
                <label class="form-label" for="cupom-tipo">Tipo *</label>
                <select id="cupom-tipo" class="form-control">
                    <option value="PERCENTUAL">Percentual (%)</option>
                    <option value="FIXO">Valor fixo (R$)</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label" for="cupom-valor">Valor *</label>
                <input type="number" id="cupom-valor" class="form-control" step="0.01" required>
            </div>
            <div class="form-group">
                <label class="form-label" for="cupom-valor-minimo">Valor mínimo do pedido</label>
                <input type="number" id="cupom-valor-minimo" class="form-control" step="0.01" value="0">
            </div>
            <div class="form-group">
                <label class="form-label" for="cupom-quantidade">Quantidade máxima de usos</label>
                <input type="number" id="cupom-quantidade" class="form-control" value="1">
            </div>
            <div class="form-group">
                <label class="form-label" for="cupom-inicio">Data início</label>
                <input type="datetime-local" id="cupom-inicio" class="form-control">
            </div>
            <div class="form-group">
                <label class="form-label" for="cupom-fim">Data fim</label>
                <input type="datetime-local" id="cupom-fim" class="form-control">
            </div>
            <div class="form-group">
                <label class="form-label" for="cupom-ativo">Ativo</label>
                <select id="cupom-ativo" class="form-control">
                    <option value="1">Sim</option>
                    <option value="0">Não</option>
                </select>
            </div>
            <div class="flex gap-10 justify-end mt-16">
                <button type="button" class="btn btn-gray" id="btn-cancelar">Cancelar</button>
                <button type="submit" class="btn btn-primary">Salvar</button>
            </div>
        </form>
    </div>
</div>

<script src="<?= asset("js/admin-cupons.js") ?>"></script>
<?php require __DIR__ . "/../../includes/admin-footer.php"; ?>