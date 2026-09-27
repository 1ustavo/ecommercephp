<?php

require_once __DIR__ . "/../config/bootstrap.php";

// Produtos com variações
$stmt = $pdo->query("
    SELECT DISTINCT p.id, p.nome, p.slug, p.preco, p.estoque
    FROM produtos p
    INNER JOIN produto_variacoes pv ON pv.produto_id = p.id
    WHERE p.ativo = TRUE
    LIMIT 5
");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Produtos COM variações:\n";
echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;

if (!empty($rows)) {
    $produtoId = (int) $rows[0]["id"];
    $vars = Produto::variacoes($produtoId);
    echo "Variações do produto {$produtoId}:\n";
    echo json_encode($vars, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;

    $imgs = Produto::imagens($produtoId);
    echo "Imagens do produto {$produtoId}:\n";
    echo json_encode($imgs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
}
