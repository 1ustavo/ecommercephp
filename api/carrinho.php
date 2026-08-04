<?php

require_once __DIR__ . "/../config/bootstrap.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: " . pageUrl("loja.php"));
    exit;
}

$action = $_GET["action"] ?? $_POST["action"] ?? null;
$isJson = str_contains($_SERVER["HTTP_ACCEPT"] ?? "", "application/json") ||
    strtolower($_SERVER["HTTP_X_REQUESTED_WITH"] ?? "") === "xmlhttprequest";

function respondJson(array $data, int $status = 200): void
{
    header("Content-Type: application/json; charset=utf-8");
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function redirectToCart(string $query = ""): void
{
    header("Location: " . pageUrl("carrinho.php") . $query);
    exit;
}

if ($action === "adicionar") {
    $produtoId = (int) ($_POST["produto_id"] ?? 0);
    $variacaoId = isset($_POST["variacao_id"]) && $_POST["variacao_id"] !== "" ? (int) $_POST["variacao_id"] : null;
    $quantidade = max(1, (int) ($_POST["quantidade"] ?? 1));

    if ($produtoId <= 0) {
        if ($isJson) {
            respondJson(["success" => false, "message" => "Produto inválido."], 400);
        }
        redirectToCart("?erro=produto");
    }

    $sucesso = Carrinho::adicionar($produtoId, $variacaoId, $quantidade);

    if (!$sucesso) {
        if ($isJson) {
            respondJson(["success" => false, "message" => "Não foi possível adicionar o produto ao carrinho."], 400);
        }
        redirectToCart("?erro=estoque");
    }

    if ($isJson) {
        respondJson(["success" => true]);
    }

    $redirect = pageUrl("carrinho.php");
    $referer = $_SERVER["HTTP_REFERER"] ?? "";
    if ($referer !== "" && str_contains($referer, $_SERVER["HTTP_HOST"])) {
        $redirect = $referer;
    }

    $redirect .= str_contains($redirect, "?") ? "&sucesso=1" : "?sucesso=1";
    header("Location: " . $redirect);
    exit;
}

if ($action === "atualizar") {
    $input = json_decode(file_get_contents("php://input"), true) ?: $_POST;
    $itemId = (int) ($input["item_id"] ?? 0);
    $quantidade = max(0, (int) ($input["quantidade"] ?? 0));

    if ($itemId <= 0) {
        respondJson(["success" => false, "message" => "Item inválido."], 400);
    }

    $sucesso = Carrinho::atualizar($itemId, $quantidade);
    if (!$sucesso) {
        respondJson(["success" => false, "message" => "Não foi possível atualizar a quantidade."], 400);
    }

    respondJson(["success" => true]);
}

if ($action === "remover") {
    $input = json_decode(file_get_contents("php://input"), true) ?: $_POST;
    $itemId = (int) ($input["item_id"] ?? 0);

    if ($itemId <= 0) {
        respondJson(["success" => false, "message" => "Item inválido."], 400);
    }

    $sucesso = Carrinho::remover($itemId);
    if (!$sucesso) {
        respondJson(["success" => false, "message" => "Não foi possível remover o item."], 400);
    }

    respondJson(["success" => true]);
}

if ($isJson) {
    respondJson(["success" => false, "message" => "Ação inválida."], 400);
}

redirectToCart("?erro=acao");
