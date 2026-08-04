<?php

header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . "/../config/bootstrap.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);

    echo json_encode([
        "erro" => "Metodo nao permitido. Utilize POST."
    ]);

    exit;
}

$dados = json_decode(file_get_contents("php://input"), true);

if (!is_array($dados)) {
    http_response_code(400);

    echo json_encode([
        "erro" => "JSON invalido."
    ]);

    exit;
}

$nome = trim($dados["nome"] ?? "");
$email = trim($dados["email"] ?? "");
$password = $dados["password"] ?? "";
$cargo = strtoupper(trim($dados["cargo"] ?? ""));

if ($nome === "" || $email === "" || $password === "" || $cargo === "") {
    http_response_code(400);

    echo json_encode([
        "erro" => "Todos os campos sao obrigatorios."
    ]);

    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);

    echo json_encode([
        "erro" => "Email invalido."
    ]);

    exit;
}

if (strlen($password) < 8) {
    http_response_code(400);

    echo json_encode([
        "erro" => "A senha deve possuir pelo menos 8 caracteres."
    ]);

    exit;
}

$cargosPermitidos = ["FUNCIONARIO", "GERENTE", "ADMIN"];

if (!in_array($cargo, $cargosPermitidos, true)) {
    http_response_code(400);

    echo json_encode([
        "erro" => "Cargo invalido."
    ]);

    exit;
}

$passwordHash = password_hash($password, PASSWORD_DEFAULT);

try {
    $sql = "
        INSERT INTO usuarios (nome, email, password, cargo)
        VALUES (?, ?, ?, ?)
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$nome, $email, $passwordHash, $cargo]);

    http_response_code(201);

    echo json_encode([
        "mensagem" => "Funcionario cadastrado com sucesso."
    ]);
} catch (PDOException $e) {
    if ($e->getCode() === "23505") {
        http_response_code(409);

        echo json_encode([
            "erro" => "Este email ja esta cadastrado."
        ]);

        exit;
    }

    error_log($e->getMessage());

    http_response_code(500);

    echo json_encode([
        "erro" => "Erro interno ao cadastrar o funcionario."
    ]);
}
