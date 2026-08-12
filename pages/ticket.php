<?php

require_once __DIR__ . "/../config/bootstrap.php";

$auth = new Auth();
$auth->verificarCliente();

$usuarioId = (int) ($_SESSION["usuario_id"] ?? 0);
$isAdmin = isAdmin();

$ticketId = isset($_GET["id"]) ? (int) $_GET["id"] : 0;

if ($ticketId <= 0) {
    header("Location: " . pageUrl("meus_tickets.php"));
    exit;
}

// Busca o ticket
$ticket = Ticket::buscarPorId($ticketId);

if (!$ticket) {
    header("Location: " . pageUrl("meus_tickets.php"));
    exit;
}

// Verifica permissão de acesso
if (!Ticket::usuarioPodeAcessar($ticketId, $usuarioId, $isAdmin)) {
    header("Location: " . pageUrl("meus_tickets.php"));
    exit;
}

$pageTitle = "Chamado #" . $ticketId . " - " . $ticket["assunto"];

require __DIR__ . "/../includes/header.php";

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
?>

<div class="container" style="max-width: 1000px; margin: 0 auto; padding: 24px;">
    <!-- Cabeçalho do ticket -->
    <div class="ticket-header" style="background: white; border-radius: 8px; padding: 20px; margin-bottom: 24px; border: 1px solid #e5e7eb;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px;">
            <div>
                <h1 style="font-size: 24px; font-weight: 600; margin-bottom: 8px;">
                    <?= e($ticket["assunto"]) ?>
                </h1>
                <div style="display: flex; gap: 12px; flex-wrap: wrap; align-items: center;">
                    <span class="badge <?= $statusClass ?>"><?= $statusLabel ?></span>
                    <span style="color: #6b7280; font-size: 14px;">
                        Pedido #<?= e($ticket["numero_pedido"]) ?> • 
                        <?= formatarMoeda((float) $ticket["valor_pedido"]) ?>
                    </span>
                </div>
            </div>
            <a href="<?= pageUrl("meus_tickets.php") ?>" class="btn btn-gray btn-sm">
                ← Voltar
            </a>
        </div>

        <!-- Informações do ticket -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; padding-top: 16px; border-top: 1px solid #e5e7eb;">
            <div>
                <small style="color: #6b7280;">Cliente</small>
                <div style="font-weight: 500;"><?= e($ticket["cliente_nome"]) ?></div>
            </div>
            <div>
                <small style="color: #6b7280;">Email</small>
                <div style="font-weight: 500;"><?= e($ticket["cliente_email"]) ?></div>
            </div>
            <div>
                <small style="color: #6b7280;">Aberto em</small>
                <div style="font-weight: 500;"><?= (new DateTime($ticket["created_at"]))->format("d/m/Y H:i") ?></div>
            </div>
            <div>
                <small style="color: #6b7280;">Última atualização</small>
                <div style="font-weight: 500;"><?= (new DateTime($ticket["updated_at"]))->format("d/m/Y H:i") ?></div>
            </div>
        </div>
    </div>

    <!-- Chat de mensagens -->
    <div class="chat-container" style="background: white; border-radius: 8px; padding: 20px; margin-bottom: 24px; border: 1px solid #e5e7eb;">
        <h2 style="font-size: 18px; font-weight: 600; margin-bottom: 20px;">Conversa</h2>
        
        <div class="messages" id="messages-container" style="max-height: 500px; overflow-y: auto; padding: 16px; background: #f9fafb; border-radius: 8px;">
            <?php if (empty($mensagens)): ?>
                <div class="empty-state" style="padding: 40px 20px;">
                    <p>Nenhuma mensagem ainda.</p>
                </div>
            <?php else: ?>
                <?php foreach ($mensagens as $msg): ?>
                    <?php
                    $isAdminMsg = $msg["origem"] === "ADMIN";
                    $dataMsg = new DateTime($msg["created_at"]);
                    ?>
                    <div class="message <?= $isAdminMsg ? "message-admin" : "message-client" ?>" 
                         style="margin-bottom: 16px; display: flex; flex-direction: column; <?= $isAdminMsg ? "align-items: flex-end;" : "align-items: flex-start;" ?>">
                        <div style="max-width: 70%; padding: 12px 16px; border-radius: 12px; <?= $isAdminMsg ? "background: #2563eb; color: white;" : "background: white; border: 1px solid #e5e7eb;" ?>">
                            <div style="font-size: 14px; line-height: 1.5;"><?= e($msg["mensagem"]) ?></div>
                        </div>
                        <div style="font-size: 12px; color: #6b7280; margin-top: 4px; padding: 0 8px;">
                            <?= e($msg["autor_nome"]) ?> • <?= $dataMsg->format("d/m/Y H:i") ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Formulário de resposta -->
    <?php if (!in_array($ticket["status"], ["FECHADO"])): ?>
        <div class="reply-form" style="background: white; border-radius: 8px; padding: 20px; border: 1px solid #e5e7eb;">
            <h3 style="font-size: 16px; font-weight: 600; margin-bottom: 12px;">
                <?= $isAdmin ? "Responder" : "Enviar Mensagem" ?>
            </h3>
            <form id="form-mensagem">
                <div class="form-group" style="margin-bottom: 12px;">
                    <textarea id="mensagem" 
                              class="form-control" 
                              rows="3" 
                              placeholder="Digite sua mensagem..." 
                              required
                              style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px;"></textarea>
                </div>
                <button type="submit" class="btn btn-primary">
                    <?= $isAdmin ? "Enviar Resposta" : "Enviar Mensagem" ?>
                </button>
            </form>
        </div>
    <?php else: ?>
        <div class="alert" style="background: #f3f4f6; padding: 16px; border-radius: 8px; text-align: center; color: #6b7280;">
            Este chamado está fechado. Não é possível enviar novas mensagens.
        </div>
    <?php endif; ?>
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

            const origem = <?= json_encode($isAdmin ? 'ADMIN' : 'CLIENTE') ?>;
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
                    // Recarrega a página para mostrar a nova mensagem
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

    // Scroll para o final das mensagens
    if (messagesContainer) {
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }
});
</script>

<style>
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

.form-control:focus {
    outline: none;
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.badge {
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
    display: inline-block;
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

.btn-primary:disabled {
    opacity: 0.6;
    cursor: not-allowed;
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