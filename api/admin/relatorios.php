<?php
require_once __DIR__ . "/../../config/bootstrap.php";

// Guard: apenas ADMIN/GERENTE acessam relatórios financeiros
requererPapel(["ADMIN", "GERENTE"]);

header("Content-Type: application/json; charset=utf-8");

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    http_response_code(405);
    echo json_encode(["sucesso" => false, "erro" => "Metodo nao permitido. Use GET."]);
    exit;
}

$mes = trim($_GET["mes"] ?? "");

// Se "total" for passado, retorna o agregado geral
if ($mes === "total") {
    $dados = Pedido::relatorioTotal();
    echo json_encode([
        "sucesso" => true,
        "tipo" => "total",
        "dados" => $dados,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Se nenhum mês for passado, usa o mês atual
if ($mes === "") {
    $mes = date("Y-m");
}

// Valida formato YYYY-MM
if (!preg_match('/^\d{4}-\d{2}$/', $mes)) {
    http_response_code(400);
    echo json_encode([
        "sucesso" => false,
        "erro" => "Formato de mês inválido. Use YYYY-MM ou 'total'.",
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$dados = Pedido::relatorioMensal($mes);

echo json_encode([
    "sucesso" => true,
    "tipo" => "mensal",
    "dados" => $dados,
], JSON_UNESCAPED_UNICODE);