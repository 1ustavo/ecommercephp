<?php

require_once __DIR__ . "/../../config/bootstrap.php";

$auth = new Auth();
$auth->verificarAdmin();

// Verifica CSRF
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$csrfToken = $_POST["csrf_token"] ?? "";
if (!hash_equals($_SESSION["csrf_token"] ?? "", $csrfToken)) {
    $_SESSION["flash"] = ["erro" => "Token CSRF inválido."];
    header("Location: " . pageUrl("admin/tickets.php"));
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: " . pageUrl("admin/tickets.php"));
    exit;
}

$ticketId = isset($_POST["ticket_id"]) ? (int) $_POST["ticket_id"] : 0;
$acao = $_POST["acao"] ?? "";

if ($ticketId <= 0 || empty($acao)) {
    $_SESSION["flash"] = ["erro" => "Dados inválidos."];
    header("Location: " . pageUrl("admin/tickets.php"));
    exit;
}

try {
    // Verifica se o ticket existe antes de qualquer ação (IDOR fix)
    $ticket = Ticket::buscarPorId($ticketId);
    if (!$ticket) {
        $_SESSION["flash"] = ["erro" => "Ticket não encontrado."];
        header("Location: " . pageUrl("admin/tickets.php"));
        exit;
    }

    switch ($acao) {
        case "status":
            $status = strtoupper(trim($_POST["status"] ?? ""));
            if (!empty($status)) {
                Ticket::atualizarStatus($ticketId, $status);
                registrarAuditoria("ATUALIZAR", "TICKET", $ticketId, "Status alterado para $status");
                $_SESSION["flash"] = ["sucesso" => "Status atualizado com sucesso!"];
            }
            break;

        case "prioridade":
            $prioridade = strtoupper(trim($_POST["prioridade"] ?? ""));
            if (!empty($prioridade)) {
                Ticket::atualizarPrioridade($ticketId, $prioridade);
                registrarAuditoria("ATUALIZAR", "TICKET", $ticketId, "Prioridade alterada para $prioridade");
                $_SESSION["flash"] = ["sucesso" => "Prioridade atualizada com sucesso!"];
            }
            break;

        case "categoria":
            $categoria = strtoupper(trim($_POST["categoria"] ?? ""));
            if (!empty($categoria)) {
                Ticket::atualizarCategoria($ticketId, $categoria);
                registrarAuditoria("ATUALIZAR", "TICKET", $ticketId, "Categoria alterada para $categoria");
                $_SESSION["flash"] = ["sucesso" => "Categoria atualizada com sucesso!"];
            }
            break;

        default:
            $_SESSION["flash"] = ["erro" => "Ação inválida."];
    }
} catch (Exception $e) {
    $_SESSION["flash"] = ["erro" => $e->getMessage()];
}

header("Location: " . pageUrl("admin/ticket_detalhe.php?id=" . $ticketId));
exit;