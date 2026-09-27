<?php

require_once __DIR__ . "/../config/bootstrap.php";

// Autenticação + sessão rigorosa (timeout de inatividade, fingerprint,
// IP e session_version) — sem redirecionar, responde JSON 401
if (!(new Auth())->validarSessao()) {
    $_SESSION = []; // sessão inválida/expirada: limpa dados autenticados
    http_response_code(401);
    echo json_encode(["sucesso" => false, "erro" => "Faça login para abrir um chamado."]);
    exit;
}

$usuarioId = (int) $_SESSION["usuario_id"];

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
$pedidoId = isset($dados["pedido_id"]) ? (int) $dados["pedido_id"] : 0;
$assunto = trim($dados["assunto"] ?? "");
$categoria = strtoupper(trim($dados["categoria"] ?? "GERAL"));
$prioridade = strtoupper(trim($dados["prioridade"] ?? "MEDIA"));

if ($pedidoId <= 0) {
    http_response_code(422);
    echo json_encode(["sucesso" => false, "erro" => "Pedido inválido."]);
    exit;
}

if (strlen($assunto) < 5) {
    http_response_code(422);
    echo json_encode(["sucesso" => false, "erro" => "Assunto deve ter pelo menos 5 caracteres."]);
    exit;
}

// Valida categoria
$categoriasPermitidas = ['GERAL', 'PRODUTO', 'ENTREGA', 'PAGAMENTO', 'TROCA', 'DEVOLUCAO', 'OUTRO'];
if (!in_array($categoria, $categoriasPermitidas)) {
    $categoria = 'GERAL';
}

// Valida prioridade
$prioridadesPermitidas = ['BAIXA', 'MEDIA', 'ALTA', 'URGENTE'];
if (!in_array($prioridade, $prioridadesPermitidas)) {
    $prioridade = 'MEDIA';
}

try {
    // Abre o ticket (validação de propriedade do pedido acontece no modelo)
    $ticketId = Ticket::abrir($pedidoId, $usuarioId, $assunto, $categoria, $prioridade);

    // Adiciona mensagem inicial
    $mensagemInicial = trim($dados["mensagem"] ?? "Olá, gostaria de ajuda com este pedido.");
    if (!empty($mensagemInicial)) {
        Ticket::adicionarMensagem($ticketId, $usuarioId, $mensagemInicial, "CLIENTE");
    }

    // Registra auditoria
    registrarAuditoria("CRIAR", "TICKET", $ticketId, "Ticket #$ticketId aberto para pedido #$pedidoId");

    echo json_encode([
        "sucesso" => true,
        "mensagem" => "Chamado aberto com sucesso!",
        "ticket_id" => $ticketId,
    ]);
} catch (Exception $e) {
    error_log("Erro ao abrir ticket para pedido #{$pedidoId}: " . $e->getMessage());
    http_response_code(422);
    echo json_encode([
        "sucesso" => false,
        "erro" => "Erro ao processar solicitação. Tente novamente.",
    ]);
}
