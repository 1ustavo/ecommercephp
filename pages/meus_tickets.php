<?php

require_once __DIR__ . "/../config/bootstrap.php";

$auth = new Auth();
$auth->verificarCliente();

$usuarioId = (int) ($_SESSION["usuario_id"] ?? 0);
$pageTitle = "Meus Chamados";

require __DIR__ . "/../includes/header.php";

// Filtro de status
$filtroStatus = $_GET["status"] ?? "todos";

// Busca tickets do usuário
$tickets = Ticket::listarPorUsuario($usuarioId, $filtroStatus !== "todos" ? $filtroStatus : null);
?>

<div class="container" style="max-width: 1200px; margin: 0 auto; padding: 24px;">
    <h1 style="margin-bottom: 24px;">Meus Chamados</h1>

    <!-- Filtros -->
    <div class="filters" style="margin-bottom: 24px; display: flex; gap: 12px; flex-wrap: wrap;">
        <a href="<?= pageUrl("meus_tickets.php") ?>" 
           class="btn btn-sm <?= $filtroStatus === "todos" ? "btn-primary" : "btn-gray" ?>">
            Todos
        </a>
        <a href="<?= pageUrl("meus_tickets.php?status=ABERTO") ?>" 
           class="btn btn-sm <?= $filtroStatus === "ABERTO" ? "btn-primary" : "btn-gray" ?>">
            Abertos
        </a>
        <a href="<?= pageUrl("meus_tickets.php?status=EM_ANDAMENTO") ?>" 
           class="btn btn-sm <?= $filtroStatus === "EM_ANDAMENTO" ? "btn-primary" : "btn-gray" ?>">
            Em Andamento
        </a>
        <a href="<?= pageUrl("meus_tickets.php?status=RESOLVIDO") ?>" 
           class="btn btn-sm <?= $filtroStatus === "RESOLVIDO" ? "btn-primary" : "btn-gray" ?>">
            Resolvidos
        </a>
        <a href="<?= pageUrl("meus_tickets.php?status=FECHADO") ?>" 
           class="btn btn-sm <?= $filtroStatus === "FECHADO" ? "btn-primary" : "btn-gray" ?>">
            Fechados
        </a>
    </div>

    <!-- Lista de tickets -->
    <?php if (empty($tickets)): ?>
        <div class="empty-state">
            <h3>Nenhum chamado encontrado</h3>
            <p>Você ainda não abriu nenhum chamado de atendimento.</p>
        </div>
    <?php else: ?>
        <div class="tickets-list">
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
                $categoriaLabel = match ($ticket["categoria"]) {
                    "PRODUTO" => "Produto",
                    "ENTREGA" => "Entrega",
                    "PAGAMENTO" => "Pagamento",
                    "TROCA" => "Troca",
                    "DEVOLUCAO" => "Devolução",
                    "OUTRO" => "Outro",
                    default => "Geral",
                };
                $dataAbertura = new DateTime($ticket["created_at"]);
                $ultimaMensagem = $ticket["ultima_mensagem"] ? new DateTime($ticket["ultima_mensagem"]) : null;
                ?>

                <div class="ticket-card">
                    <div class="ticket-header">
                        <div class="ticket-info">
                            <h3 class="ticket-subject">
                                <a href="<?= pageUrl("ticket.php?id=" . $ticket["id"]) ?>">
                                    <?= e($ticket["assunto"]) ?>
                                </a>
                            </h3>
                            <div class="ticket-meta">
                                <span>Pedido #<?= e($ticket["numero_pedido"]) ?></span>
                                <span>•</span>
                                <span><?= formatarMoeda((float) $ticket["valor_pedido"]) ?></span>
                                <span>•</span>
                                <span>Aberto em <?= $dataAbertura->format("d/m/Y H:i") ?></span>
                            </div>
                        </div>
                        <div class="ticket-badges">
                            <span class="badge <?= $statusClass ?>"><?= $statusLabel ?></span>
                            <span class="badge <?= $prioridadeClass ?>"><?= $ticket["prioridade"] ?></span>
                        </div>
                    </div>

                    <div class="ticket-body">
                        <div class="ticket-category">
                            <span class="category-icon">📂</span>
                            <?= $categoriaLabel ?>
                        </div>

                        <?php if ($ultimaMensagem): ?>
                            <div class="ticket-last-message">
                                <small>Última mensagem: <?= $ultimaMensagem->format("d/m/Y H:i") ?></small>
                            </div>
                        <?php endif; ?>

                        <div class="ticket-stats">
                            <span class="stat">
                                💬 <?= (int) $ticket["total_mensagens"] ?> mensagens
                            </span>
                        </div>
                    </div>

                    <div class="ticket-actions">
                        <a href="<?= pageUrl("ticket.php?id=" . $ticket["id"]) ?>" class="btn btn-primary btn-sm">
                            Ver Chat
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<style>
.ticket-card {
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 16px;
    transition: all 0.2s;
}

.ticket-card:hover {
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    border-color: #2563eb;
}

.ticket-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 12px;
}

.ticket-subject {
    font-size: 18px;
    font-weight: 600;
    margin-bottom: 8px;
}

.ticket-subject a {
    color: #111827;
    text-decoration: none;
}

.ticket-subject a:hover {
    color: #2563eb;
}

.ticket-meta {
    display: flex;
    gap: 8px;
    font-size: 14px;
    color: #6b7280;
    flex-wrap: wrap;
}

.ticket-badges {
    display: flex;
    gap: 8px;
    flex-shrink: 0;
}

.badge {
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
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

.ticket-body {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 12px;
    border-top: 1px solid #e5e7eb;
}

.ticket-category {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 14px;
    color: #6b7280;
}

.ticket-stats {
    font-size: 14px;
    color: #6b7280;
}

.ticket-actions {
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px solid #e5e7eb;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    background: white;
    border-radius: 8px;
    border: 1px solid #e5e7eb;
}

.empty-state h3 {
    color: #111827;
    margin-bottom: 8px;
}

.empty-state p {
    color: #6b7280;
}

.filters {
    background: white;
    padding: 16px;
    border-radius: 8px;
    border: 1px solid #e5e7eb;
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
</style>

<?php require __DIR__ . "/../includes/footer.php"; ?>