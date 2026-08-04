<?php

require_once __DIR__ . "/env.php";

loadEnv(__DIR__ . "/../.env");

$host = $_ENV["DB_HOST"] ?? "localhost";
$dbname = $_ENV["DB_NAME"] ?? "ecommercephp";
$user = $_ENV["DB_USER"] ?? "postgres";
$password = $_ENV["DB_PASS"] ?? "1040";

try {
    $pdo = new PDO(
        "pgsql:host=$host;dbname=$dbname",
        $user,
        $password
    );

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    http_response_code(500);

    if (php_sapi_name() === "cli") {
        fwrite(STDERR, "Erro na conexao com o banco: " . $e->getMessage() . PHP_EOL);
        exit(1);
    }

    echo json_encode([
        "erro" => "Erro na conexao com o banco."
    ]);

    exit;
}
