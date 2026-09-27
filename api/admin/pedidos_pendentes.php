<?php
/**
 * GET /api/admin/pedidos_pendentes.php
 * Lista pedidos aguardando aprovacao (status = AGUARDANDO_APROVACAO).
 *
 * Query params (opcionais):
 *   - format = json (default) | html  (html retorna fragmento para embed; ETAPA 5 usara a page)
 *
 * Regras:
 *   - So admin/gerente/funcionario
 *   - Retorna JSON com lista de pedidos + seus itens
 */

require_once __DIR__ . "/../../config/bootstrap.php";

// Guard de API com papel exigido (JSON 401/403, não redirect)
requererPapel(["ADMIN", "GERENTE", "FUNCIONARIO"]);

header("Content-Type: application/json; charset=utf-8");

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    http_response_code(405);
    echo json_encode(["sucesso" => false, "erro" => "Metodo nao permitido. Use GET."]);
    exit;
}

$pendentes = Pedido::pendentesAprovacao();

// Anexa os itens de cada pedido para o front montar a lista
$lista = [];
foreach ($pendentes as $p) {
    $p["itens"] = Pedido::itens((int) $p["id"]);
    $lista[] = $p;
}

echo json_encode([
    "sucesso" => true,
    "total" => count($lista),
    "pedidos" => $lista,
]);