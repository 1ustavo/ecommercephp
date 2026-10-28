<?php

require_once __DIR__ . "/../config/bootstrap.php";

// Apenas POST (evita cache de GET e exige corpo)
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode(["sucesso" => false, "erro" => "Método não permitido."]);
    exit;
}

// Garante sessão ativa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Precisa estar logado + sessão rigorosa (timeout, fingerprint, IP, session_version)
if (!(new Auth())->validarSessao()) {
    $_SESSION = []; // sessão inválida/expirada: limpa dados autenticados
    http_response_code(401);
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode(["sucesso" => false, "erro" => "Sessão expirada. Faça login novamente."]);
    exit;
}

// CSRF
verify_csrf_from_request(true);

// Lê o CEP (POST form ou JSON)
$cep = "";
$body = @json_decode(file_get_contents("php://input"), true);
if (is_array($body)) {
    $cep = trim((string) ($body["cep"] ?? ""));
} else {
    $cep = trim((string) ($_POST["cep"] ?? ""));
}

$cepLimpo = preg_replace("/\D/", "", $cep);

if (strlen($cepLimpo) !== 8) {
    http_response_code(400);
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode(["sucesso" => false, "erro" => "CEP inválido."]);
    exit;
}

$itensCarrinho = Carrinho::itens();

if (empty($itensCarrinho)) {
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode(["sucesso" => true, "frete" => []]);
    exit;
}

// Chama o cálculo real (com cache interno por CEP + carrinho)
$opcoes = CorreiosFrete::calcularFrete($cepLimpo, $itensCarrinho);

// Persiste na sessão o resultado para o checkout (evita recalcular no POST)
$_SESSION["frete_calculo"] = [
    "cep" => $cepLimpo,
    "opcoes" => $opcoes,
];

header("Content-Type: application/json; charset=utf-8");
echo json_encode([
    "sucesso" => true,
    "frete" => $opcoes,
    "mensagem" => "Frete calculado. Se os Correios estiverem fora do ar usamos valores estimados.",
]);