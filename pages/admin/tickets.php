<?php

require_once __DIR__ . "/../../config/bootstrap.php";

$auth = new Auth();
$auth->verificarAdmin();

$pageTitle = "Gestão de Tickets - SAC";
$currentAdminPage = "tickets";

require __DIR__ . "/../../includes/admin-header.php";

// Filtros
$filtroStatus = $_GET["status"] ?? "todos";
$filtroCategoria = $_GET["categoria"] ?? "todos";
$pagina = isset($_GET["pagina"]) ? max(1, (int) $_GET["pagina"]) : 1;
$limite = 5;

// Busca tickets
$tickets = Ticket::listarTodos(
    $filtroStatus !== "todos" ? $filtroStatus : null,
    $filtroCategoria !== "todos" ? $filtroCategoria : null,
    $pagina,
    $limite
);

$totalTickets = Ticket::contarTodos(
    $filtroStatus !== "todos" ? $filtroStatus : null,
    $filtroCategoria !== "todos" ? $filtroCategoria : null
);

$totalPaginas = max(1, (int) ceil($totalTickets / $limite));
$pagina = min($pagina, $totalPaginas);

// Estatísticas
$stats = Ticket::estatisticas();
?>

<div class="container" style="max-width: 1400px; margin: 0 auto; padding: 24px;">
    <!-- Cabeçalho -->
    <div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
        <h1 style="font-size: 28px; font-weight: 700;">Gestão de Tickets</h1>
    </div>

    <!-- Estatísticas -->
    <div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px;">
        <div class="stat-card" style="background: white; padding: 20px; border-radius: 8px; border: 1px solid #e5e7eb;">
            <div style="font-size: 14px; color: #6b7280; margin-bottom: 8px;">Total de Tickets</div>
            <div style="font-size: 32px; font-weight: 700; color: #111827;">
                <?= array_sum($stats) - (isset($stats['categorias']) ? count($stats['categorias']) : 0) ?>
            </div>
        </div>
        <div class="stat-card" style="background: white; padding: 20px; border-radius: 8px; border: 1px solid #e5e7eb;">
            <div style="font-size: 14px; color: #6b7280; margin-bottom: 8px;">Abertos</div>
            <div style="font-size: 32px; font-weight: 700; color: #f59e0b;">
                <?= $stats['ABERTO'] ?? 0 ?>
            </div>
        </div>
        <div class="stat-card" style="background: white; padding: 20px; border-radius: 8px; border: 1px solid #e5e7eb;">
            <div style="font-size: 14px; color: #6b7280; margin-bottom: 8px;">Em Andamento</div>
            <div style="font-size: 32px; font-weight: 700; color: #2563eb;">
                <?= $stats['EM_ANDAMENTO'] ?? 0 ?>
            </div>
        </div>
        <div class="stat-card" style="background: white; padding: 20px; border-radius: 8px; border: 1px solid #e5e7eb;">
            <div style="font-size: 14px; color: #6b7280; margin-bottom: 8px;">Resolvidos</div>
            <div style="font-size: 32px; font-weight: 700; color: #10b981;">
                <?= $stats['RESOLVIDO'] ?? 0 ?>
            </div>
        </div>
        <div class="stat-card" style="background: white; padding: 20px; border-radius: 8px; border: 1px solid #e5e7eb;">
            <div style="font-size: 14px; color: #6b7280; margin-bottom: 8px;">Pendentes Hoje</div>
            <div style="font-size: 32px; font-weight: 700; color: #ef4444;">
                <?= $stats['pendentes'] ?? 0 ?>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="filters" style="background: white; padding: 16px; border-radius: 8px; border: 1px solid #e5e7eb; margin-bottom: 24px;">
        <form method="GET" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end;">
            <div class="form-group">
                <label style="font-size: 14px; font-weight: 500; margin-bottom: 6px; display: block;">Status</label>
                <select name="status" style="padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; min-width: 150px;">
                    <option value="todos" <?= $filtroStatus === "todos" ? "selected" : "" ?>>Todos</option>
                    <option value="ABERTO" <?= $filtroStatus === "ABERTO" ? "selected" : "" ?>>Abertos</option>
                    <option value="EM_ANDAMENTO" <?= $filtroStatus === "EM_ANDAMENTO" ? "selected" : "" ?>>Em Andamento</option>
                    <option value="RESOLVIDO" <?= $filtroStatus === "RESOLVIDO" ? "selected" : "" ?>>Resolvidos</option>
                    <option value="FECHADO" <?= $filtroStatus === "FECHADO" ? "selected" : "" ?>>Fechados</option>
                </select>
            </div>
            <div class="form-group">
                <label style="font-size: 14px; font-weight: 500; margin-bottom: 6px; display: block;">Categoria</label>
                <select name="categoria" style="padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; min-width: 150px;">
                    <option value="todos">Todas</option>
                    <option value="GERAL" <?= $filtroCategoria === "GERAL" ? "selected" : "" ?>>Geral</option>
                    <option value="PRODUTO" <?= $filtroCategoria === "PRODUTO" ? "selected" : "" ?>>Produto</option>
                    <option value="ENTREGA" <?= $filtroCategoria === "ENTREGA" ? "selected" : "" ?>>Entrega</option>
                    <option value="PAGAMENTO" <?= $filtroCategoria === "PAGAMENTO" ? "selected" : "" ?>>Pagamento</option>
                    <option value="TROCA" <?= $filtroCategoria === "TROCA" ? "selected" : "" ?>>Troca</option>
                    <option value="DEVOLUCAO" <?= $filtroCategoria === "DEVOLUCAO" ? "selected" : "" ?>>Devolução</option>
                    <option value="OUTRO" <?= $filtroCategoria === "OUTRO" ? "selected" : "" ?>>Outro</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Filtrar</button>
            <a href="<?= pageUrl("admin/tickets.php") ?>" class="btn btn-gray">Limpar</a>
        </form>
    </div>

    <!-- Lista de tickets -->
    <?php if (empty($tickets)): ?>
        <div class="empty-state" style="background: white; padding: 60px 20px; border-radius: 8px; border: 1px solid #e5e7eb; text-align: center;">
            <h3 style="color: #111827; margin-bottom: 8px;">Nenhum ticket encontrado</h3>
            <p style="color: #6b7280;">Não há tickets com os filtros selecionados.</p>
        </div>
    <?php else: ?>
        <div class="tickets-table" style="background: white; border-radius: 8px; border: 1px solid #e5e7eb; overflow: hidden;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead style="background: #f9fafb; border-bottom: 1px solid #e5e7eb;">
                    <tr>
                        <th style="padding: 12px 16px; text-align: left; font-size: 13px; font-weight: 600; color: #6b7280; text-transform: uppercase;">ID</th>
                        <th style="padding: 12px 16px; text-align: left; font-size: 13px; font-weight: 600; color: #6b7280; text-transform: uppercase;">Assunto</th>
                        <th style="padding: 12px 16px; text-align: left; font-size: 13px; font-weight: 600; color: #6b7280; text-transform: uppercase;">Cliente</th>
                        <th style="padding: 12px 16px; text-align: left; font-size: 13px; font-weight: 600; color: #6b7280; text-transform: uppercase;">Pedido</th>
                        <th style="padding: 12px 16px; text-align: left; font-size: 13px; font-weight: 600; color: #6b7280; text-transform: uppercase;">Status</th>
                        <th style="padding: 12px 16px; text-align: left; font-size: 13px; font-weight: 600; color: #6b7280; text-transform: uppercase;">Prioridade</th>
                        <th style="padding: 12px 16px; text-align: left; font-size: 13px; font-weight: 600; color: #6b7280; text-transform: uppercase;">Mensagens</th>
                        <th style="padding: 12px 16px; text-align: left; font-size: 13px; font-weight: 600; color: #6b7280; text-transform: uppercase;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tickets as $ticket): ?>
                        <?php
                        $statusClass = match ($ticket["status"]) {
                            "ABERTO" => "status-aberto",
                            "EM_ANDAMENTO" => "status-andamento",
                            "RESOLVIDO" => "status-resolvido",
                            "FECHADO" => "status-fechado",
                            default => "status-default",
                        };
                        $statusLabel = match ($ticket["status"]) {
                            "ABERTO" => "Aberto",
                            "EM_ANDAMENTO" => "Em Andamento",
                            "RESOLVIDO" => "Resolvido",
                            "FECHADO" => "Fechado",
                            default => $ticket["status"],
                        };
                        $prioridadeClass = match ($ticket["prioridade"]) {
                            "URGENTE" => "priority-urgente",
                            "ALTA" => "priority-alta",
                            "MEDIA" => "priority-media",
                            "BAIXA" => "priority-baixa",
                            default => "priority-default",
                        };
                        $dataAbertura = new DateTime($ticket["created_at"]);
                        ?>
                        <tr style="border-bottom: 1px solid #e5e7eb; transition: background 0.2s;" onmouseover="this.style.background='#f9fafb'" onmouseout="this.style.background='white'">
                            <td style="padding: 16px; font-size: 14px; color: #6b7280;">#<?= $ticket["id"] ?></td>
                            <td style="padding: 16px;">
                                <div style="font-weight: 500; color: #111827; margin-bottom: 4px;">
                                    <a href="<?= pageUrl("admin/ticket_detalhe.php?id=" . $ticket["id"]) ?>" style="color: #2563eb; text-decoration: none;">
                                        <?= e($ticket["assunto"]) ?>
                                    </a>
                                </div>
                                <small style="color: #6b7280; font-size: 12px;">
                                    <?= $dataAbertura->format("d/m/Y H:i") ?>
                                </small>
                            </td>
                            <td style="padding: 16px; font-size: 14px; color: #111827;">
                                <?= e($ticket["cliente_nome"]) ?>
                            </td>
                            <td style="padding: 16px; font-size: 14px; color: #6b7280;">
                                #<?= e($ticket["numero_pedido"]) ?>
                            </td>
                            <td style="padding: 16px;">
                                <span class="badge <?= $statusClass ?>"><?= $statusLabel ?></span>
                            </td>
                            <td style="padding: 16px;">
                                <span class="badge <?= $prioridadeClass ?>"><?= $ticket["prioridade"] ?></span>
                            </td>
                            <td style="padding: 16px; text-align: center; font-size: 14px; color: #6b7280;">
                                <?= (int) $ticket["total_mensagens"] ?>
                            </td>
                            <td style="padding: 16px;">
                                <a href="<?= pageUrl("admin/ticket_detalhe.php?id=" . $ticket["id"]) ?>" class="btn btn-sm btn-primary">
                                    Gerenciar
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Paginação -->
        <?php if ($totalPaginas > 1): ?>
            <?php
            $paginas = paginacaoJanela($pagina, $totalPaginas, 2);
            ?>
            <div class="pagination" style="display: flex; justify-content: center; align-items: center; gap: 8px; margin-top: 24px;">
                <?php if ($pagina > 1): ?>
                    <a href="?status=<?= $filtroStatus ?>&categoria=<?= $filtroCategoria ?>&pagina=<?= $pagina - 1 ?>" class="btn btn-sm btn-gray">
                        ← Anterior
                    </a>
                <?php endif; ?>

                <?php foreach ($paginas as $p): ?>
                    <?php if ($p === ".."): ?>
                        <span class="btn btn-sm" style="pointer-events: none; color: #9ca3af;">..</span>
                    <?php elseif ($p === $pagina): ?>
                        <span class="btn btn-sm btn-primary" style="pointer-events: none;"><?= $p ?></span>
                    <?php else: ?>
                        <a href="?status=<?= $filtroStatus ?>&categoria=<?= $filtroCategoria ?>&pagina=<?= $p ?>" class="btn btn-sm btn-gray">
                            <?= $p ?>
                        </a>
                    <?php endif; ?>
                <?php endforeach; ?>

                <?php if ($pagina < $totalPaginas): ?>
                    <a href="?status=<?= $filtroStatus ?>&categoria=<?= $filtroCategoria ?>&pagina=<?= $pagina + 1 ?>" class="btn btn-sm btn-gray">
                        Próxima →
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<style>
.badge {
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
    display: inline-block;
    white-space: nowrap;
}

.status-aberto {
    background: #fef3c7;
    color: #92400e;
}

.status-andamento {
    background: #dbeafe;
    color: #1e40af;
}

.status-resolvido {
    background: #d1fae5;
    color: #065f46;
}

.status-fechado {
    background: #f3f4f6;
    color: #374151;
}

.priority-urgente {
    background: #fee2e2;
    color: #991b1b;
}

.priority-alta {
    background: #fed7aa;
    color: #9a3412;
}

.priority-media {
    background: #fef3c7;
    color: #92400e;
}

.priority-baixa {
    background: #d1fae5;
    color: #065f46;
}

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 10px 20px;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.2s;
}

.btn-primary {
    background: #2563eb;
    color: white;
}

.btn-primary:hover {
    background: #1d4ed8;
}

.btn-gray {
    background: #e5e7eb;
    color: #374151;
}

.btn-gray:hover {
    background: #d1d5db;
}

.btn-sm {
    padding: 6px 12px;
    font-size: 13px;
}

.stat-card {
    transition: all 0.2s;
}

.stat-card:hover {
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    transform: translateY(-2px);
}
</style>

<?php require __DIR__ . "/../../includes/admin-footer.php"; ?>