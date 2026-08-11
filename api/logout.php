<?php

require_once __DIR__ . "/../config/bootstrap.php";

// Logout exige POST + CSRF para evitar logout forçado via CSRF
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode(["sucesso" => false, "erro" => "Método não permitido. Use POST."]);
    exit;
}

verify_csrf_from_request(true);

$auth = new Auth();
$auth->logout();