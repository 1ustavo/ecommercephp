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

if ($auth->login($email, $password)) {
    header("Location: " . pageUrl("loja.php"));
    exit;
}

header("Location: ../index.php?erro=credenciais");
exit;
