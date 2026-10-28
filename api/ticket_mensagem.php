<?php

require_once __DIR__ . "/../config/bootstrap.php";

// Verifica se usuário está logado
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION["usuario_id"]) || !(new Auth())->validarSessao()) {
    $_SESSION = []; // sessão inválida/expirada: limpa dados autenticados
    http_response_code(401);
    echo json_encode(["sucesso" => false, "erro" => "Faça login para enviar mensagens."]);
    exit;
}

$usuarioId = (int) $_SESSION["usuario_id"];
$isAdmin = isAdmin();

// CSRF protection
verify_csrf_from_request(true);

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["sucesso" => false, "erro" => "Método não permitido."]);
    exit;
}

$dados = json_decode(file_get_contents("php://input"), true);

if (!is_array($dados)) {
    $dados = $_POST;
}

// Validações
$ticketId = isset($dados["ticket_id"]) ? (int) $dados["ticket_id"] : 0;
$mensagem = trim($dados["mensagem"] ?? "");
$origem = strtoupper(trim($dados["origem"] ?? "CLIENTE"));

if ($ticketId <= 0) {
    http_response_code(422);
    echo json_encode(["sucesso" => false, "erro" => "Ticket inválido."]);
    exit;
}

if (strlen($mensagem) < 2) {
    http_response_code(422);
    echo json_encode(["sucesso" => false, "erro" => "Mensagem deve ter pelo menos 2 caracteres."]);
    exit;
}

// Valida origem
if (!in_array($origem, ["CLIENTE", "ADMIN"])) {
    $origem = "CLIENTE";
}

// Se for ADMIN, valida que é realmente admin
if ($origem === "ADMIN" && !$isAdmin) {
    http_response_code(403);
    echo json_encode(["sucesso" => false, "erro" => "Acesso negado."]);
    exit;
}

try {
    // Verifica se ticket existe e usuário tem permissão
    $ticket = Ticket::buscarPorId($ticketId);
    
    if (!$ticket) {
        http_response_code(404);
        echo json_encode(["sucesso" => false, "erro" => "Ticket não encontrado."]);
        exit;
    }

    // Verifica permissão
    if (!Ticket::usuarioPodeAcessar($ticketId, $usuarioId, $isAdmin)) {
        http_response_code(403);
        echo json_encode(["sucesso" => false, "erro" => "Acesso negado a este ticket."]);
        exit;
    }

    // Verifica se ticket está fechado
    if ($ticket["status"] === "FECHADO") {
        http_response_code(422);
        echo json_encode(["sucesso" => false, "erro" => "Este chamado está fechado."]);
        exit;
    }

    // Adiciona mensagem
    Ticket::adicionarMensagem($ticketId, $usuarioId, $mensagem, $origem);

    // Registra auditoria
    registrarAuditoria("CRIAR", "TICKET_MENSAGEM", $ticketId, "Mensagem adicionada ao ticket #$ticketId");

    echo json_encode([
        "sucesso" => true,
        "mensagem" => "Mensagem enviada com sucesso!",
    ]);
} catch (Exception $e) {
    error_log("Erro ao enviar mensagem do ticket #{$ticketId}: " . $e->getMessage());
    http_response_code(422);
    echo json_encode([
        "sucesso" => false,
        "erro" => "Erro ao processar solicitação. Tente novamente.",
    ]);
}
