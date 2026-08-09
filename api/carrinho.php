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
        // Busca o estoque disponível para dar uma mensagem mais precisa
        $produto = Produto::buscarPorId($produtoId);
        $estoqueDisponivel = 0;

        if ($produto) {
            if ($variacaoId) {
                $stmt = $pdo->prepare("SELECT estoque FROM produto_variacoes WHERE id = ? AND produto_id = ?");
                $stmt->execute([$variacaoId, $produtoId]);
                $estoqueDisponivel = (int) ($stmt->fetchColumn() ?: 0);
            } else {
                $estoqueDisponivel = (int) $produto["estoque"];
            }
        }

        $mensagem = $estoqueDisponivel > 0
            ? "Estoque insuficiente. Disponível: {$estoqueDisponivel} un."
            : "Produto sem estoque disponível.";

        if ($isJson) {
            respondJson(["success" => false, "message" => $mensagem], 400);
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
        // Busca o item para dar mensagem específica de estoque
        $stmt = $pdo->prepare("
            SELECT ci.*, p.nome, p.estoque AS estoque_produto,
                   pv.estoque AS estoque_variacao
            FROM carrinho_itens ci
            JOIN produtos p ON p.id = ci.produto_id
            LEFT JOIN produto_variacoes pv ON pv.id = ci.variacao_id
            WHERE ci.id = ?
        ");
        $stmt->execute([$itemId]);
        $item = $stmt->fetch();

        if ($item) {
            $estoqueDisponivel = $item["variacao_id"] ? (int) ($item["estoque_variacao"] ?? 0) : (int) ($item["estoque_produto"] ?? 0);
            $mensagem = $estoqueDisponivel > 0
                ? "Estoque insuficiente para \"{$item['nome']}\". Disponível: {$estoqueDisponivel} un."
                : "Produto \"{$item['nome']}\" sem estoque disponível.";
        } else {
            $mensagem = "Não foi possível atualizar a quantidade.";
        }

        respondJson(["success" => false, "message" => $mensagem], 400);
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

if ($action === "aplicar_cupom") {
    $input = json_decode(file_get_contents("php://input"), true) ?: $_POST;
    $codigo = strtoupper(trim($input["cupom"] ?? ""));

    if ($codigo === "") {
        respondJson(["success" => false, "message" => "Informe um código de cupom."], 400);
    }

    $subtotal = Carrinho::subtotal();

    // Carrinho vazio — aviso claro antes de validar o cupom
    if ($subtotal <= 0) {
        respondJson(["success" => false, "message" => "Adicione produtos ao carrinho antes de aplicar o cupom."], 400);
    }

    $cupom = Cupom::buscarPorCodigo($codigo);

    if (!$cupom) {
        // Busca o cupom sem filtros para dar mensagem mais precisa
        $cupomBruto = Cupom::buscarPorCodigoBruto($codigo);

        if (!$cupomBruto) {
            respondJson(["success" => false, "message" => "Cupom não encontrado. Verifique o código digitado."], 400);
        }

        if (!$cupomBruto["ativo"]) {
            respondJson(["success" => false, "message" => "Este cupom está inativo."], 400);
        }

        $agora = time();
        $dataInicio = strtotime($cupomBruto["data_inicio"]);
        $dataFim = strtotime($cupomBruto["data_fim"]);

        if ($agora < $dataInicio) {
            respondJson(["success" => false, "message" => "Este cupom ainda não está disponível."], 400);
        }

        if ($agora > $dataFim) {
            respondJson(["success" => false, "message" => "Este cupom expirou."], 400);
        }

        if ((int) $cupomBruto["quantidade_usada"] >= (int) $cupomBruto["quantidade_maxima"]) {
            respondJson(["success" => false, "message" => "Este cupom já atingiu o limite de usos."], 400);
        }

        respondJson(["success" => false, "message" => "Cupom inválido ou não encontrado."], 400);
    }

    $desconto = Cupom::calcularDesconto($cupom, $subtotal, 0);

    if ($desconto <= 0) {
        $mensagem = "Cupom não aplicável. Valor mínimo do pedido: R$ " . number_format((float) $cupom["valor_minimo_pedido"], 2, ",", ".");
        if ((float) $cupom["valor_minimo_pedido"] > 0) {
            $mensagem = "Cupom não aplicável. Valor mínimo de compra: R$ " . number_format((float) $cupom["valor_minimo_pedido"], 2, ",", ".");
        }
        respondJson(["success" => false, "message" => $mensagem], 400);
    }

    // Persiste o cupom na sessão para refletir no checkout
    $_SESSION["cupom_aplicado"] = [
        "codigo" => $cupom["codigo"],
        "cupom_id" => (int) $cupom["id"],
        "desconto" => $desconto,
    ];

    respondJson([
        "success" => true,
        "cupom_id" => (int) $cupom["id"],
        "codigo" => $cupom["codigo"],
        "desconto" => $desconto,
        "desconto_formatado" => "R$ " . number_format($desconto, 2, ",", "."),
        "mensagem" => "Cupom aplicado com sucesso!",
    ]);
}

if ($action === "remover_cupom") {
    unset($_SESSION["cupom_aplicado"]);
    respondJson(["success" => true]);
}

if ($isJson) {
    respondJson(["success" => false, "message" => "Ação inválida."], 400);
}

redirectToCart("?erro=acao");
