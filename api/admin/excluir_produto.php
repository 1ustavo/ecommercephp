<?php

require_once __DIR__ . "/../../config/bootstrap.php";

// Guard: apenas ADMIN/GERENTE gerenciam produtos
requererPapel(["ADMIN", "GERENTE"]);

// CSRF protection
verify_csrf_from_request(true);

header("Content-Type: application/json; charset=utf-8");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["sucesso" => false, "erro" => "Método não permitido."]);
    exit;
}

$id = isset($_POST["id"]) ? (int) $_POST["id"] : 0;

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(["sucesso" => false, "erro" => "ID inválido."]);
    exit;
}

// Busca o produto para auditoria e validação de existência
$produto = Produto::buscarPorId($id);

if (!$produto) {
    http_response_code(404);
    echo json_encode(["sucesso" => false, "erro" => "Produto não encontrado."]);
    exit;
}

try {
    $pdo->beginTransaction();

    // 0) Verifica dependências que bloqueariam o DELETE (FK sem CASCADE)
    //    - carrinho_itens.produto_id: FK sem ON DELETE CASCADE
    //    - pedido_itens.produto_id: não tem FK, mas vale validar por segurança
    foreach (["carrinho_itens", "pedido_itens"] as $tabela) {
        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM {$tabela} WHERE produto_id = ?");
        $stmtCheck->execute([$id]);
        $qtd = (int) $stmtCheck->fetchColumn();
        if ($qtd > 0) {
            $pdo->rollBack();
            http_response_code(409);
            echo json_encode([
                "sucesso" => false,
                "erro" => "Não é possível excluir: o produto está referenciado em " . ($tabela === "carrinho_itens" ? "carrinhos de clientes" : "pedidos") . " (" . $qtd . " registro(s)).",
            ]);
            exit;
        }
    }

    // 1) Busca as imagens do produto (para remover os arquivos físicos)
    $stmtImagens = $pdo->prepare("SELECT url FROM produto_imagens WHERE produto_id = ?");
    $stmtImagens->execute([$id]);
    $imagens = $stmtImagens->fetchAll(PDO::FETCH_ASSOC);

    // 2) Remove os registros de produto_imagens (ON DELETE CASCADE também
    //    removeria, mas fazemos explícito para ter as URLs antes)
    $pdo->prepare("DELETE FROM produto_imagens WHERE produto_id = ?")->execute([$id]);

    // 3) Remove o produto (CASCADE remove produto_variacoes, produto_faqs,
    //    e registros de produto_imagens restantes)
    $pdo->prepare("DELETE FROM produtos WHERE id = ?")->execute([$id]);

    $pdo->commit();

    // 4) Remove os arquivos físicos do disco (após commit bem-sucedido)
    //    Ignora placeholders e URLs externas (http/https)
    foreach ($imagens as $imagem) {
        $url = $imagem["url"] ?? "";
        if ($url === "" || str_contains($url, "placeholder:") || preg_match('#^https?://#', $url)) {
            continue;
        }
        $caminhoFs = __DIR__ . "/../../" . $url;
        if (is_file($caminhoFs)) {
            @unlink($caminhoFs);
        }
    }

    registrarAuditoria(
        "EXCLUIR",
        "PRODUTO",
        $id,
        "Produto: " . $produto["nome"] . " | SKU: " . ($produto["sku"] ?? "—")
    );

    echo json_encode([
        "sucesso" => true,
        "mensagem" => "Produto excluído com sucesso.",
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log("Erro ao excluir produto: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "sucesso" => false,
        "erro" => "Erro interno ao excluir produto. Tente novamente.",
    ]);
}