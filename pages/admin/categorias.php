<?php

require_once __DIR__ . "/../../config/bootstrap.php";

$pageTitle = "Categorias";
$currentAdminPage = "categorias";
require __DIR__ . "/../../includes/admin-header.php";

$categorias = Categoria::todasAdmin();
?>

<h1 class="page-title">Categorias</h1>

<div style="margin-bottom:16px">
    <button type="button" class="btn btn-primary" id="btn-nova-categoria">Nova Categoria</button>
</div>

<div class="card-grid" style="grid-template-columns: 1fr; gap:16px;">
    <?php if (empty($categorias)): ?>
        <div class="empty-state">
            <h3>Nenhuma categoria cadastrada.</h3>
        </div>
    <?php else: ?>
        <?php foreach ($categorias as $cat): ?>
            <div class="card" style="padding:16px;">
                <div class="flex justify-between items-center flex-wrap gap-12">
                    <div>
                        <h2 style="margin:0 0 4px"><?= e($cat["nome"]) ?></h2>
                        <p style="margin:0;color:var(--cinza-600);font-size:0.85rem">
                            Slug: <?= e($cat["slug"]) ?> • <?= $cat["ativo"] ? "Ativo" : "Inativo" ?>
                        </p>
                        <?php if ($cat["descricao"]): ?>
                            <p style="margin:4px 0 0;color:var(--cinza-500);font-size:0.85rem"><?= e($cat["descricao"]) ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="flex gap-8">
                        <button type="button" class="btn btn-sm btn-outline btn-editar" data-id="<?= (int) $cat["id"] ?>">Editar</button>
                        <button type="button" class="btn btn-sm btn-outline btn-excluir" data-id="<?= (int) $cat["id"] ?>" style="border-color:var(--vermelho);color:var(--vermelho)">Excluir</button>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Modal Categoria -->
<div id="modal-categoria" class="modal-overlay" style="display:none">
    <div class="modal card" style="max-width:520px;width:90%">
        <h2 style="margin-top:0" id="modal-titulo">Nova Categoria</h2>
        <form id="form-categoria">
            <input type="hidden" id="cat-id">
            <div class="form-group">
                <label class="form-label" for="cat-nome">Nome *</label>
                <input type="text" id="cat-nome" class="form-control" required>
            </div>
            <div class="form-group">
                <label class="form-label" for="cat-slug">Slug *</label>
                <input type="text" id="cat-slug" class="form-control" required>
            </div>
            <div class="form-group">
                <label class="form-label" for="cat-descricao">Descrição</label>
                <textarea id="cat-descricao" class="form-control" rows="3"></textarea>
            </div>
            <div class="form-group">
                <label class="form-label" for="cat-pai">Categoria pai</label>
                <select id="cat-pai" class="form-control">
                    <option value="">Nenhuma</option>
                    <?php foreach ($categorias as $c): ?>
                        <option value="<?= (int) $c["id"] ?>"><?= e($c["nome"]) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label" for="cat-ativo">Ativo</label>
                <select id="cat-ativo" class="form-control">
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

<script src="<?= asset("js/admin-categorias.js") ?>"></script>
<?php require __DIR__ . "/../../includes/admin-footer.php"; ?>
