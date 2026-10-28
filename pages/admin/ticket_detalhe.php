<?php

require_once __DIR__ . "/../../config/bootstrap.php";

$auth = new Auth();
$auth->verificarAdmin();

$ticketId = isset($_GET["id"]) ? (int) $_GET["id"] : 0;

if ($ticketId <= 0) {
    header("Location: " . pageUrl("admin/tickets.php"));
    exit;
}

// Busca o ticket
$ticket = Ticket::buscarPorId($ticketId);

if (!$ticket) {
    header("Location: " . pageUrl("admin/tickets.php"));
    exit;
}

$pageTitle = "Ticket #" . $ticketId . " - " . $ticket["assunto"];

require __DIR__ . "/../../includes/admin-header.php";

// Busca mensagens
$mensagens = Ticket::mensagens($ticketId);

// Status e badges
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
?>

<div class="container" style="max-width: 1200px; margin: 0 auto; padding: 24px;">
    <!-- Cabeçalho -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
        <div>
            <a href="<?= pageUrl("admin/tickets.php") ?>" style="color: #6b7280; text-decoration: none; font-size: 14px; margin-bottom: 8px; display: inline-block;">
                ← Voltar para lista
            </a>
            <h1 style="font-size: 24px; font-weight: 600; margin-top: 8px;">
                <?= e($ticket["assunto"]) ?>
            </h1>
        </div>
        <div style="display: flex; gap: 8px;">
            <a href="<?= pageUrl("admin/tickets.php") ?>" class="btn btn-gray btn-sm">Lista de Tickets</a>
        </div>
    </div>

    <!-- Informações do Ticket -->
    <div style="background: white; border-radius: 8px; padding: 20px; margin-bottom: 24px; border: 1px solid #e5e7eb;">
        <h2 style="font-size: 18px; font-weight: 600; margin-bottom: 16px;">Informações do Chamado</h2>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 16px; margin-bottom: 20px;">
            <div>
                <label style="font-size: 12px; color: #6b7280; display: block; margin-bottom: 4px;">Cliente</label>
                <div style="font-weight: 500;"><?= e($ticket["cliente_nome"]) ?></div>
                <div style="font-size: 14px; color: #6b7280;"><?= e($ticket["cliente_email"]) ?></div>
            </div>
            <div>
                <label style="font-size: 12px; color: #6b7280; display: block; margin-bottom: 4px;">Pedido</label>
                <div style="font-weight: 500;">#<?= e($ticket["numero_pedido"]) ?></div>
                <div style="font-size: 14px; color: #6b7280;"><?= formatarMoeda((float) $ticket["valor_pedido"]) ?></div>
            </div>
            <div>
                <label style="font-size: 12px; color: #6b7280; display: block; margin-bottom: 4px;">Status</label>
                <span class="badge <?= $statusClass ?>"><?= $statusLabel ?></span>
            </div>
            <div>
                <label style="font-size: 12px; color: #6b7280; display: block; margin-bottom: 4px;">Prioridade</label>
                <span class="badge <?= $prioridadeClass ?>"><?= $ticket["prioridade"] ?></span>
            </div>
            <div>
                <label style="font-size: 12px; color: #6b7280; display: block; margin-bottom: 4px;">Categoria</label>
                <div style="font-weight: 500;"><?= e($ticket["categoria"]) ?></div>
            </div>
            <div>
                <label style="font-size: 12px; color: #6b7280; display: block; margin-bottom: 4px;">Data de Abertura</label>
                <div style="font-weight: 500;"><?= (new DateTime($ticket["created_at"]))->format("d/m/Y H:i") ?></div>
            </div>
        </div>

        <!-- Ações Rápidas -->
        <div style="border-top: 1px solid #e5e7eb; padding-top: 16px; display: flex; gap: 8px; flex-wrap: wrap;">
            <form method="POST" action="<?= pageUrl("admin/ticket_acao.php") ?>" style="display: inline;">
                <?= csrf_input() ?>
                <input type="hidden" name="ticket_id" value="<?= $ticketId ?>">
                <input type="hidden" name="acao" value="status">
                <select name="status" style="padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px;">
                    <option value="">Alterar Status</option>
                    <option value="ABERTO">Aberto</option>
                    <option value="EM_ANDAMENTO">Em Andamento</option>
                    <option value="RESOLVIDO">Resolvido</option>
                    <option value="FECHADO">Fechado</option>
                </select>
                <button type="submit" class="btn btn-sm btn-primary">Atualizar</button>
            </form>

            <form method="POST" action="<?= pageUrl("admin/ticket_acao.php") ?>" style="display: inline;">
                <?= csrf_input() ?>
                <input type="hidden" name="ticket_id" value="<?= $ticketId ?>">
                <input type="hidden" name="acao" value="prioridade">
                <select name="prioridade" style="padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px;">
                    <option value="">Alterar Prioridade</option>
                    <option value="BAIXA">Baixa</option>
                    <option value="MEDIA">Média</option>
                    <option value="ALTA">Alta</option>
                    <option value="URGENTE">Urgente</option>
                </select>
                <button type="submit" class="btn btn-sm btn-primary">Atualizar</button>
            </form>

            <form method="POST" action="<?= pageUrl("admin/ticket_acao.php") ?>" style="display: inline;">
                <?= csrf_input() ?>
                <input type="hidden" name="ticket_id" value="<?= $ticketId ?>">
                <input type="hidden" name="acao" value="categoria">
                <select name="categoria" style="padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px;">
                    <option value="">Alterar Categoria</option>
                    <option value="GERAL">Geral</option>
                    <option value="PRODUTO">Produto</option>
                    <option value="ENTREGA">Entrega</option>
                    <option value="PAGAMENTO">Pagamento</option>
                    <option value="TROCA">Troca</option>
                    <option value="DEVOLUCAO">Devolução</option>
                    <option value="OUTRO">Outro</option>
                </select>
                <button type="submit" class="btn btn-sm btn-primary">Atualizar</button>
            </form>
        </div>
    </div>

    <!-- Chat de Mensagens -->
    <div style="background: white; border-radius: 8px; padding: 20px; margin-bottom: 24px; border: 1px solid #e5e7eb;">
        <h2 style="font-size: 18px; font-weight: 600; margin-bottom: 20px;">Histórico de Mensagens</h2>
        
        <div class="messages" id="messages-container" style="max-height: 500px; overflow-y: auto; padding: 16px; background: #f9fafb; border-radius: 8px; margin-bottom: 20px;">
            <?php if (empty($mensagens)): ?>
                <div class="empty-state" style="padding: 40px 20px; text-align: center; color: #6b7280;">
                    <p>Nenhuma mensagem ainda.</p>
                </div>
            <?php else: ?>
                <?php foreach ($mensagens as $msg): ?>
                    <?php
                    $isAdminMsg = $msg["origem"] === "ADMIN";
                    $dataMsg = new DateTime($msg["created_at"]);
                    ?>
                    <div class="message" style="margin-bottom: 16px; display: flex; flex-direction: column; <?= $isAdminMsg ? "align-items: flex-end;" : "align-items: flex-start;" ?>">
                        <div style="max-width: 70%; padding: 12px 16px; border-radius: 12px; <?= $isAdminMsg ? "background: #2563eb; color: white;" : "background: white; border: 1px solid #e5e7eb;" ?>">
                            <div style="font-size: 14px; line-height: 1.5; margin-bottom: 4px;"><?= e($msg["mensagem"]) ?></div>
                            <div style="font-size: 12px; opacity: 0.8; padding-top: 4px; border-top: 1px solid <?= $isAdminMsg ? 'rgba(255,255,255,0.2)' : '#e5e7eb' ?>;">
                                <?= e($msg["autor_nome"]) ?> • <?= $dataMsg->format("d/m/Y H:i") ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Formulário de Resposta -->
        <?php if ($ticket["status"] !== "FECHADO"): ?>
            <form id="form-mensagem" style="border-top: 1px solid #e5e7eb; padding-top: 20px;">
                <div style="margin-bottom: 12px;">
                    <label style="font-size: 14px; font-weight: 500; margin-bottom: 6px; display: block;">Responder ao Cliente</label>
                    <textarea id="mensagem" 
                              class="form-control" 
                              rows="3" 
                              placeholder="Digite sua resposta..." 
                              required
                              style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; font-family: inherit;"></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Enviar Resposta</button>
            </form>
        <?php else: ?>
            <div style="background: #f3f4f6; padding: 16px; border-radius: 8px; text-align: center; color: #6b7280;">
                Este chamado está fechado.
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const form = document.getElementById("form-mensagem");
    const messagesContainer = document.getElementById("messages-container");

    if (form) {
        form.addEventListener("submit", function (e) {
            e.preventDefault();

            const mensagem = document.getElementById("mensagem").value.trim();
            if (!mensagem) return;

            const btn = form.querySelector('button[type="submit"]');
            const originalText = btn.textContent;
            btn.disabled = true;
            btn.textContent = "Enviando...";

            const origem = "ADMIN";
            const csrfInput = document.querySelector('input[name=csrf_token]');
            const csrfToken = csrfInput !== null ? csrfInput.value : "";
            const apiUrl = <?= json_encode(apiUrl("ticket_mensagem.php")) ?>;

            fetch(apiUrl, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-Token": csrfToken,
                },
                body: JSON.stringify({
                    ticket_id: <?= $ticketId ?>,
                    mensagem: mensagem,
                    origem: origem,
                }),
            })
            .then((response) => response.json())
            .then((data) => {
                if (data.sucesso) {
                    location.reload();
                } else {
                    alert(data.erro || "Erro ao enviar mensagem");
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

    if (messagesContainer) {
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }
});
</script>

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

.form-control:focus {
    outline: none;
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.message {
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>

<?php require __DIR__ . "/../../includes/admin-footer.php"; ?>