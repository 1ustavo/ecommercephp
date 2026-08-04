<?php

require_once __DIR__ . "/../../config/bootstrap.php";

$pageTitle = "Auditoria";
$currentAdminPage = "auditoria";
require __DIR__ . "/../../includes/admin-header.php";

// Filtros
$filtros = [
    "usuario_id" => isset($_GET["usuario_id"]) ? (int) $_GET["usuario_id"] : null,
    "acao" => trim($_GET["acao"] ?? ""),
    "entidade" => trim($_GET["entidade"] ?? ""),
    "data_inicio" => trim($_GET["data_inicio"] ?? ""),
    "data_fim" => trim($_GET["data_fim"] ?? ""),
];

$logs = buscarAuditoria($filtros);

// Lista de usuários para o filtro
$usuarios = $pdo->query("
    SELECT id, nome, cargo FROM usuarios
    WHERE cargo IN ('ADMIN', 'GERENTE', 'FUNCIONARIO')
    ORDER BY nome
")->fetchAll();
?>

<h1 class="page-title">📋 Log de Auditoria</h1>
<p class="page-subtitle">Registro de ações de funcionários e administradores</p>

<!-- Filtros -->
<form class="filters-bar" method="get">
    <div class="form-group">
        <label class="form-label">Funcionário</label>
        <select name="usuario_id" class="form-control">
            <option value="">Todos</option>
            <?php foreach ($usuarios as $u): ?>
                <option value="<?= (int) $u["id"] ?>" <?= $filtros["usuario_id"] === (int) $u["id"] ? "selected" : "" ?>>
                    <?= e($u["nome"]) ?> (<?= e($u["cargo"]) ?>)
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label class="form-label">Ação</label>
        <input type="text" name="acao" class="form-control" placeholder="Ex: CRIAR, ATUALIZAR..." value="<?= e($filtros["acao"]) ?>">
    </div>
    <div class="form-group">
        <label class="form-label">Entidade</label>
        <select name="entidade" class="form-control">
            <option value="">Todas</option>
            <option value="PRODUTO" <?= $filtros["entidade"] === "PRODUTO" ? "selected" : "" ?>>Produto</option>
            <option value="TICKET" <?= $filtros["entidade"] === "TICKET" ? "selected" : "" ?>>Ticket</option>
            <option value="PEDIDO" <?= $filtros["entidade"] === "PEDIDO" ? "selected" : "" ?>>Pedido</option>
        </select>
    </div>
    <div class="form-group">
        <label class="form-label">Data início</label>
        <input type="date" name="data_inicio" class="form-control" value="<?= e($filtros["data_inicio"]) ?>">
    </div>
    <div class="form-group">
        <label class="form-label">Data fim</label>
        <input type="date" name="data_fim" class="form-control" value="<?= e($filtros["data_fim"]) ?>">
    </div>
    <button type="submit" class="btn btn-primary">Filtrar</button>
    <a href="<?= pageUrl("admin/auditoria.php") ?>" class="btn btn-gray">Limpar</a>
</form>

<?php if (empty($logs)): ?>
    <div class="empty-state">
        <h3>Nenhum registro de auditoria encontrado</h3>
        <p>As ações de funcionários aparecerão aqui.</p>
    </div>
<?php else: ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Data/Hora</th>
                    <th>Funcionário</th>
                    <th>Ação</th>
                    <th>Entidade</th>
                    <th>ID</th>
                    <th>Descrição</th>
                    <th>IP</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                    <?php
                    $data = new DateTime($log["created_at"]);
                    $acaoClass = match ($log["acao"]) {
                        "CRIAR" => "badge-pago",
                        "ATUALIZAR" => "badge-enviado",
                        "RESPONDER_TICKET" => "badge-entregue",
                        default => "badge-pendente",
                    };
                    ?>
                    <tr>
                        <td><?= $data->format("d/m/Y H:i:s") ?></td>
                        <td><strong><?= e($log["usuario_nome"] ?? "Sistema") ?></strong></td>
                        <td><span class="badge <?= $acaoClass ?>"><?= e($log["acao"]) ?></span></td>
                        <td><?= e($log["entidade"]) ?></td>
                        <td><?= $log["entidade_id"] ? (int) $log["entidade_id"] : "—" ?></td>
                        <td style="max-width: 300px;"><?= e($log["descricao"]) ?></td>
                        <td><?= e($log["ip"] ?? "—") ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php require __DIR__ . "/../../includes/admin-footer.php"; ?>