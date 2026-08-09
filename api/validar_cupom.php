<?php

require_once __DIR__ . "/../config/bootstrap.php";

// Garante que a sessão está ativa antes de acessar $_SESSION
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header("Content-Type: application/json; charset=utf-8");

// Verifica login de forma segura — retorna JSON em vez de redirect
if (!isset($_SESSION["usuario_id"])) {
    http_response_code(401);
    echo json_encode(["sucesso" => false, "erro" => "Sessão expirada. Faça login novamente."]);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["sucesso" => false, "erro" => "Método não permitido."]);
    exit;
}

$codigo = strtoupper(trim($_POST["cupom"] ?? ""));
$subtotal = (float) ($_POST["subtotal"] ?? 0);
$frete = (float) ($_POST["frete"] ?? 0);

if ($codigo === "") {
    echo json_encode(["sucesso" => false, "erro" => "Informe um código de cupom."]);
    exit;
}

$cupom = Cupom::buscarPorCodigo($codigo);

if (!$cupom) {
    echo json_encode(["sucesso" => false, "erro" => "Cupom inválido, expirado ou já utilizado."]);
    exit;
}

$desconto = Cupom::calcularDesconto($cupom, $subtotal, $frete);

if ($desconto <= 0) {
    echo json_encode([
        "sucesso" => false,
        "erro" => "Cupom não aplicável. Valor mínimo do pedido: R$ " . number_format((float) $cupom["valor_minimo_pedido"], 2, ",", "."),
    ]);
    exit;
}

echo json_encode([
    "sucesso" => true,
    "cupom_id" => (int) $cupom["id"],
    "codigo" => $cupom["codigo"],
    "desconto" => $desconto,
    "desconto_formatado" => "R$ " . number_format($desconto, 2, ",", "."),
    "mensagem" => "Cupom aplicado com sucesso!",
]);