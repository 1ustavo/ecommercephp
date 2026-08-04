<?php

require_once __DIR__ . "/../config/bootstrap.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: " . pageUrl("cadastro.php"));
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
