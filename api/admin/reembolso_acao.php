<?php
/**
 * POST /api/admin/reembolso_acao.php
 * ADMIN/GERENTE decide sobre solicitações de reembolso.
 *
 * Body (JSON ou form):
 *   - reembolso_id (int, obrigatório)
 *   - action       (string: 'aprovar' | 'recusar')
 *   - motivo       (string, obrigatório quando action = recusar)
 *
 * Contrato JSON: {"sucesso": bool, "erro"|"mensagem"|...}
 */

require_once __DIR__ . "/../../config/bootstrap.php";

// Guard: apenas ADMIN/GERENTE decidem reembolsos (FUNCIONARIO nunca)
requererPapel(["ADMIN", "GERENTE"]);

// CSRF
verify_csrf_from_request(true);

header("Content-Type: application/json; charset=utf-8");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["sucesso" => false, "erro" => "Metodo nao permitido. Use POST."]);
    exit;
}

// Aceita JSON ou form-encoded
$dados = json_decode(file_get_contents("php://input"), true);
if (!is_array($dados)) {
    $dados = $_POST;
}

$reembolsoId = isset($dados["reembolso_id"]) ? (int) $dados["reembolso_id"] : 0;
$action = trim((string) ($dados["action"] ?? ""));
$motivo = trim((string) ($dados["motivo"] ?? ""));

if ($reembolsoId <= 0 || $action === "") {
    http_response_code(400);
    echo json_encode(["sucesso" => false, "erro" => "Parâmetros inválidos"]);
    exit;
}

// O reembolso precisa existir (404 explícito, evita auditoria sobre ID fantasma)
$reembolso = Reembolso::buscarPorId($reembolsoId);
if (!$reembolso) {
    http_response_code(404);
    echo json_encode(["sucesso" => false, "erro" => "Reembolso não encontrado"]);
    exit;
}

$adminId = (int) ($_SESSION["usuario_id"] ?? 0);
$adminNome = $_SESSION["usuario_nome"] ?? "Admin";

switch ($action) {
    case 'aprovar':
        // Estado validado antes (mensagem 409 clara) e de novo atomicamente no modelo
        if ($reembolso["status"] !== "SOLICITADO") {
            http_response_code(409);
            echo json_encode([
                "sucesso" => false,
                "erro" => "Este reembolso já foi resolvido. Status atual: " . $reembolso["status"],
            ]);
            exit;
        }

        $ok = Reembolso::aprovar($reembolsoId, $adminId);
        if ($ok) {
            registrarAuditoria(
                "APROVAR_REEMBOLSO",
                "REEMBOLSO",
                $reembolsoId,
                "Reembolso #{$reembolsoId} (pedido #{$reembolso['pedido_id']}) aprovado por {$adminNome} (admin #{$adminId})"
            );
            echo json_encode([
                "sucesso" => true,
                "mensagem" => "Reembolso aprovado com sucesso.",
                "novo_status" => "APROVADO",
            ]);
            exit;
        }
        http_response_code(409);
        echo json_encode(["sucesso" => false, "erro" => "Não foi possível aprovar. O reembolso pode ter sido resolvido por outro usuário ou o estorno falhou."]);
        exit;

    case 'recusar':
        if ($motivo === "") {
            http_response_code(400);
            echo json_encode(["sucesso" => false, "erro" => "Motivo obrigatório para recusar o reembolso."]);
            exit;
        }
        if ($reembolso["status"] !== "SOLICITADO") {
            http_response_code(409);
            echo json_encode([
                "sucesso" => false,
                "erro" => "Este reembolso já foi resolvido. Status atual: " . $reembolso["status"],
            ]);
            exit;
        }

        $ok = Reembolso::recusar($reembolsoId, $adminId, $motivo);
        if ($ok) {
            registrarAuditoria(
                "RECUSAR_REEMBOLSO",
                "REEMBOLSO",
                $reembolsoId,
                "Reembolso #{$reembolsoId} (pedido #{$reembolso['pedido_id']}) recusado por {$adminNome} (admin #{$adminId}). Motivo: {$motivo}"
            );
            echo json_encode([
                "sucesso" => true,
                "mensagem" => "Reembolso recusado.",
                "novo_status" => "RECUSADO",
            ]);
            exit;
        }
        http_response_code(409);
        echo json_encode(["sucesso" => false, "erro" => "Não foi possível recusar. O reembolso pode ter sido resolvido por outro usuário."]);
        exit;

    default:
        http_response_code(400);
        echo json_encode(["sucesso" => false, "erro" => "Ação desconhecida"]);
        exit;
}
