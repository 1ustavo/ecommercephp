<?php

require_once __DIR__ . "/../config/bootstrap.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: " . pageUrl("cadastro.php"));
    exit;
}

// CSRF protection
verify_csrf_from_request(true);

// Rate limit por IP
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
if (!rate_limit_check("cadastro:{$ip}", 5, 3600)) {
    header("Location: " . pageUrl("cadastro.php") . "?erro=rate_limit");
    exit;
}

$nome = trim($_POST["nome"] ?? "");
$email = trim($_POST["email"] ?? "");
$password = $_POST["password"] ?? "";
$passwordConfirm = $_POST["password_confirm"] ?? "";

if ($nome === "" || $email === "" || $password === "" || $passwordConfirm === "") {
    header("Location: " . pageUrl("cadastro.php") . "?erro=campos");
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header("Location: " . pageUrl("cadastro.php") . "?erro=email_invalido");
    exit;
}

if (strlen($password) < 8) {
    header("Location: " . pageUrl("cadastro.php") . "?erro=senha_curta");
    exit;
}

if ($password !== $passwordConfirm) {
    header("Location: " . pageUrl("cadastro.php") . "?erro=senha");
    exit;
}

$stmt = $pdo->prepare("SELECT 1 FROM usuarios WHERE email = ?");
$stmt->execute([$email]);
if ($stmt->fetchColumn()) {
    header("Location: " . pageUrl("cadastro.php") . "?erro=email");
    exit;
}

$hash = password_hash($password, PASSWORD_DEFAULT);

try {
    $stmt = $pdo->prepare(
        "INSERT INTO usuarios (nome, email, password, cargo) VALUES (?, ?, ?, 'CLIENTE')"
    );
    $stmt->execute([$nome, $email, $hash]);
} catch (Exception $e) {
    header("Location: " . pageUrl("cadastro.php") . "?erro=email");
    exit;
}

header("Location: " . pageUrl("login.php") . "?sucesso=1");
exit;