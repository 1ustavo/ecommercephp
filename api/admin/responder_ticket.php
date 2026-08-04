<?php

require_once __DIR__ . "/../../config/bootstrap.php";

// Proteção: apenas admin pode acessar
$auth = new Auth();
$auth->verificarAdmin();

// Aceita apenas POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["sucesso" => false, "erro" => "Método não permitido."]);
    exit;
}

$dados = json_decode(file_get_contents("php://input"), true);

if (!is_array($dados)) {
    $dados = $_POST;
}

$ticketId = isset($dados["ticket_id"]) ? (int) $dados["ticket_id"] : 0;
$mensagem = trim($dados["mensagem"] ?? "");

// Validações
if ($ticketId <= 0) {
    http_response_code(400);
    echo json_encode(["sucesso" => false, "erro" => "ID do ticket inválido."]);
    exit;
}

if ($mensagem === "") {
    http_response_code(422);
    echo json_encode(["sucesso" => false, "erro" => "A mensagem não pode estar vazia."]);
    exit;
}

$usuarioId = (int) ($_SESSION["usuario_id"] ?? 0);

try {
    // Adiciona mensagem do admin e atualiza status para EM_ANDAMENTO
    Produto::adicionarMensagemTicket($ticketId, $usuarioId, $mensagem, "ADMIN");

    // Registra auditoria
    registrarAuditoria(
        "RESPONDER_TICKET",
        "TICKET",
        $ticketId,
        "Resposta enviada ao cliente: " . mb_substr($mensagem, 0, 100)
    );

    echo json_encode([
        "sucesso" => true,
        "mensagem" => "Mensagem enviada ao cliente com sucesso!",
    ]);
} catch (PDOException $e) {
    error_log("Erro ao enviar mensagem: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "sucesso" => false,
        "erro" => "Erro interno ao enviar mensagem. Tente novamente.",
    ]);
}
