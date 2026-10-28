<?php

// Somente CLI: script de diagnóstico do banco de dados.
// Nunca deve ser acessível via web (fica dentro de htdocs no XAMPP).
if (PHP_SAPI !== "cli") {
    http_response_code(403);
    exit("Acesso permitido apenas via linha de comando.\n");
}

require_once __DIR__ . "/../config/bootstrap.php";

echo "=== TABELAS CRIADAS ===\n";
$tabelas = $pdo->query("
    SELECT tablename FROM pg_tables
    WHERE schemaname = 'public'
    AND tablename IN ('produto_faqs', 'tickets', 'ticket_mensagens')
    ORDER BY tablename
")->fetchAll(PDO::FETCH_COLUMN);

foreach ($tabelas as $tabela) {
    echo "  [OK] $tabela\n";
}

echo "\n=== COLUNAS NOVAS EM produtos ===\n";
$colunas = $pdo->query("
    SELECT column_name FROM information_schema.columns
    WHERE table_name = 'produtos'
    AND column_name IN ('ean', 'sku', 'ncm', 'meta_titulo', 'meta_descricao', 'descricao_curta', 'palavras_chave_principais', 'palavras_chave_secundarias')
    ORDER BY column_name
")->fetchAll(PDO::FETCH_COLUMN);

foreach ($colunas as $coluna) {
    echo "  [OK] $coluna\n";
}

echo "\n=== DADOS DE EXEMPLO ===\n";
$faqs = $pdo->query("SELECT COUNT(*) FROM produto_faqs")->fetchColumn();
$tickets = $pdo->query("SELECT COUNT(*) FROM tickets")->fetchColumn();
$mensagens = $pdo->query("SELECT COUNT(*) FROM ticket_mensagens")->fetchColumn();

echo "  FAQs: $faqs\n";
echo "  Tickets: $tickets\n";
echo "  Mensagens: $mensagens\n";

echo "\n=== PRODUTOS ===\n";
$produtos = $pdo->query("SELECT id, nome, sku, ean FROM produtos LIMIT 5")->fetchAll();
foreach ($produtos as $p) {
    echo "  ID {$p['id']}: {$p['nome']} | SKU: " . ($p['sku'] ?? '—') . " | EAN: " . ($p['ean'] ?? '—') . "\n";
}