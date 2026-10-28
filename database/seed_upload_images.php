<?php

// Somente CLI: script de seed de imagens de demonstração.
// Nunca deve ser acessível via web (fica dentro de htdocs no XAMPP).
if (PHP_SAPI !== "cli") {
    http_response_code(403);
    exit("Acesso permitido apenas via linha de comando.\n");
}

require_once __DIR__ . "/../config/bootstrap.php";

// ATENÇÃO: SVG NÃO é aceito — pode conter JavaScript inline e virar XSS armazenado.
// Este seed agora reverte URLs .svg para placeholder (o frontend renderiza cor sólida).
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
        // Reverte para placeholder (frontend renderiza cor sólida)
        $placeholder = 'placeholder:' . $slug;
        $stmt = $pdo->prepare('UPDATE produto_imagens SET url = ? WHERE url = ?');
        $stmt->execute([$placeholder, $path]);
        echo "Reverted $path -> placeholder (SVG removido por segurança)\n";

        // Remove o arquivo físico .svg se existir
        $caminhoFs = __DIR__ . "/../" . $path;
        if (is_file($caminhoFs)) {
            @unlink($caminhoFs);
            echo "  Removido arquivo físico: $path\n";
        }
    }

    echo "Done\n";
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
}
