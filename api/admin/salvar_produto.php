<?php

require_once __DIR__ . "/../../config/bootstrap.php";

$auth = new Auth();
$auth->verificarAdmin();

// CSRF protection
verify_csrf_from_request(true);

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["sucesso" => false, "erro" => "Método não permitido."]);
    exit;
}

$dados = json_decode(file_get_contents("php://input"), true);

if (!is_array($dados)) {
    $dados = $_POST;
}

$erros = [];

$nome = trim($dados["nome"] ?? "");
$sku = trim($dados["sku"] ?? "");
$ean = trim($dados["ean"] ?? "");
$preco = (float) ($dados["preco"] ?? 0);
$estoque = (int) ($dados["estoque"] ?? 0);

if (strlen($nome) < 3) {
    $erros[] = "O nome do produto deve ter pelo menos 3 caracteres.";
}

if (strlen($sku) < 5) {
    $erros[] = "O SKU deve ter pelo menos 5 caracteres.";
} else {
    $produtoId = isset($dados["id"]) ? (int) $dados["id"] : null;
    if (Produto::skuExiste($sku, $produtoId)) {
        $erros[] = "Já existe um produto com este SKU.";
    }
}

$eanLimpo = preg_replace("/[\s-]/", "", $ean);
if (!preg_match("/^\d{8}$|^\d{13}$/", $eanLimpo)) {
    $erros[] = "EAN inválido. Use 8 ou 13 dígitos numéricos.";
} else {
    $produtoId = isset($dados["id"]) ? (int) $dados["id"] : null;
    if (Produto::eanExiste($eanLimpo, $produtoId)) {
        $erros[] = "Já existe um produto com este EAN.";
    }
}

if ($preco <= 0) {
    $erros[] = "O preço deve ser maior que zero.";
}

if ($estoque < 0) {
    $erros[] = "O estoque não pode ser negativo.";
}

$slug = trim($dados["slug"] ?? "");
if (!preg_match("/^[a-z0-9-]+$/", $slug) || $slug === "") {
    $erros[] = "Slug inválido. Use apenas letras minúsculas, números e hífens.";
} else {
    $produtoId = isset($dados["id"]) ? (int) $dados["id"] : null;
    if (Produto::slugExiste($slug, $produtoId)) {
        $erros[] = "Já existe um produto com este slug.";
    }
}

$ncm = trim($dados["ncm"] ?? "");
if ($ncm !== "" && !preg_match("/^\d{8}$/", preg_replace("/[\s.-]/", "", $ncm))) {
    $erros[] = "NCM deve ter 8 dígitos numéricos.";
}

// Se houver erros, retorna 422 com lista de erros
if (!empty($erros)) {
    http_response_code(422);
    echo json_encode(["sucesso" => false, "erros" => $erros]);
    exit;
}

$dadosProduto = [
    "categoria_id" => isset($dados["categoria_id"]) ? (int) $dados["categoria_id"] : null,
    "nome" => $nome,
    "slug" => $slug,
    "descricao" => trim($dados["descricao"] ?? ""),
    "descricao_curta" => trim($dados["descricao_curta"] ?? ""),
    "preco" => $preco,
    "estoque" => $estoque,
    "ativo" => (bool) ($dados["ativo"] ?? true),
    "ean" => $eanLimpo,
    "sku" => $sku,
    "ncm" => $ncm !== "" ? preg_replace("/[\s.-]/", "", $ncm) : null,
    "meta_titulo" => trim($dados["meta_titulo"] ?? ""),
    "meta_descricao" => trim($dados["meta_descricao"] ?? ""),
    "palavras_chave_principais" => trim($dados["palavras_chave_principais"] ?? ""),
    "palavras_chave_secundarias" => trim($dados["palavras_chave_secundarias"] ?? ""),
];

try {
    $produtoId = isset($dados["id"]) ? (int) $dados["id"] : null;

    if ($produtoId) {
        // Atualiza produto existente
        Produto::atualizar($produtoId, $dadosProduto);
        $mensagem = "Produto atualizado com sucesso!";
    } else {
        // Cria novo produto
        $produtoId = Produto::criar($dadosProduto);
        $mensagem = "Produto criado com sucesso!";
    }

    // Registra auditoria
    registrarAuditoria(
        $produtoId ? "ATUALIZAR" : "CRIAR",
        "PRODUTO",
        $produtoId,
        "Produto: " . $nome . " | SKU: " . $sku . " | EAN: " . $eanLimpo
    );

    // Processa upload de imagens (se enviadas via FormData)
    if (!empty($_FILES)) {
        $uploadDir = __DIR__ . "/../../uploads/produtos/";
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $imagemIndex = 0;
        foreach ($_FILES as $campo => $arquivo) {
            if ($arquivo["error"] !== UPLOAD_ERR_OK) {
                continue;
            }

            // --- VALIDAÇÕES DE SEGURANÇA DE UPLOAD ---

            // 1) Valida MIME real via mime_content_type
            $mime = mime_content_type($arquivo["tmp_name"]);
            $permitidosMime = ["image/jpeg", "image/png", "image/webp", "image/gif"];
            if (!in_array($mime, $permitidosMime, true)) {
                continue;
            }

            // 2) Valida extensão do arquivo (nunca confiar apenas no MIME)
            $extensao = strtolower(pathinfo($arquivo["name"], PATHINFO_EXTENSION));
            $permitidosExt = ["jpg", "jpeg", "png", "webp", "gif"];
            if (!in_array($extensao, $permitidosExt, true)) {
                continue;
            }

            // 3) Valida que é uma imagem real (abre e verifica dimensões)
            $imgInfo = @getimagesize($arquivo["tmp_name"]);
            if ($imgInfo === false || $imgInfo[0] <= 0 || $imgInfo[1] <= 0) {
                continue;
            }

            // 4) Valida tamanho (máx 5MB)
            if ($arquivo["size"] > 5 * 1024 * 1024) {
                continue;
            }

            // 5) Gera nome 100% seguro (sem usar nome do usuário)
            $nomeArquivo = "produto_" . $produtoId . "_" . $imagemIndex . "_" . bin2hex(random_bytes(16)) . ".{$extensao}";
            $caminho = $uploadDir . $nomeArquivo;

            // 6) Garante que o diretório de destino não permite execução PHP via .htaccess
            $htaccess = $uploadDir . ".htaccess";
            if (!file_exists($htaccess)) {
                @file_put_contents($htaccess, "php_flag engine off\nRemoveHandler .php .phtml .php3 .php4 .php5 .php7\n");
            }

            if (move_uploaded_file($arquivo["tmp_name"], $caminho)) {
                $url = "uploads/produtos/" . $nomeArquivo;
                $principal = $imagemIndex === 0;

                $pdo->prepare("
                    INSERT INTO produto_imagens (produto_id, url, principal)
                    VALUES (?, ?, ?)
                ")->execute([$produtoId, $url, $principal]);

                $imagemIndex++;
            }
        }
    }

    echo json_encode([
        "sucesso" => true,
        "mensagem" => $mensagem,
        "id" => $produtoId,
    ]);
} catch (PDOException $e) {
    error_log("Erro ao salvar produto: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "sucesso" => false,
        "erro" => "Erro interno ao salvar produto. Tente novamente.",
    ]);
}
