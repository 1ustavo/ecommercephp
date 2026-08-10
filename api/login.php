<?php

require_once __DIR__ . "/../config/bootstrap.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../index.php");
    exit;
}

$email = trim($_POST["email"] ?? "");
$password = $_POST["password"] ?? "";

if ($email === "" || $password === "") {
    header("Location: ../index.php?erro=campos");
    exit;
}

$auth = new Auth();

// CSRF & rate-limit protections
verify_csrf_from_request(true);
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
if (!rate_limit_check("login:{$ip}", 5, 60)) {
    header("Location: ../index.php?erro=rate_limit");
    exit;
}

if ($auth->login($email, $password)) {
    // Redirect based on role (admin -> admin dashboard, cliente -> loja)
    $auth->redirectPosLogin();
}

header("Location: ../index.php?erro=credenciais");
exit;
