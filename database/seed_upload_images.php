<?php
require_once __DIR__ . "/../config/bootstrap.php";

try {
    $updates = [
        'camiseta-basica-verde' => 'uploads/produtos/camiseta-basica-verde.svg',
        'calca-jeans-slim' => 'uploads/produtos/calca-jeans-slim.svg',
        'tenis-casual' => 'uploads/produtos/tenis-casual.svg',
        'bone-trucker' => 'uploads/produtos/bone-trucker.svg',
        'fone-bluetooth' => 'uploads/produtos/fone-bluetooth.svg',
        'moletom-capuz' => 'uploads/produtos/moletom-capuz.svg',
    ];

    foreach ($updates as $slug => $path) {
        $placeholder = 'placeholder:' . $slug;
        $stmt = $pdo->prepare('UPDATE produto_imagens SET url = ? WHERE url = ?');
        $stmt->execute([$path, $placeholder]);
        echo "Updated $slug -> $path\n";
    }

    echo "Done\n";
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
}
