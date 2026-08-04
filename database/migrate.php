<?php

require_once __DIR__ . "/../config/bootstrap.php";

$migrationsDir = __DIR__ . "/migrations";
$files = glob($migrationsDir . "/V*.sql");
sort($files);

foreach ($files as $file) {
    $sql = file_get_contents($file);
    echo "Executando: " . basename($file) . "\n";

    try {
        $pdo->exec($sql);
        echo "  OK\n";
    } catch (PDOException $e) {
        echo "  AVISO: " . $e->getMessage() . "\n";
    }
}

$hash = password_hash("cliente123", PASSWORD_DEFAULT);
$pdo->prepare("UPDATE usuarios SET password = ? WHERE email = 'cliente@demo.com'")->execute([$hash]);

echo "\nMigracoes concluidas.\n";
echo "Cliente demo: cliente@demo.com / cliente123\n";
