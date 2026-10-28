<?php

require_once __DIR__ . "/../../config/bootstrap.php";

// Guard: apenas ADMIN/GERENTE gerenciam produtos
requererPapel(["ADMIN", "GERENTE"]);

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
$pesoGramas = (int) ($dados["peso_gramas"] ?? 0);
$comprimentoCm = (int) ($dados["comprimento_cm"] ?? 0);
$larguraCm = (int) ($dados["largura_cm"] ?? 0);
$alturaCm = (int) ($dados["altura_cm"] ?? 0);

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

if ($pesoGramas <= 0) {
    $erros[] = "O peso deve ser maior que zero.";
}

if ($comprimentoCm <= 0) {
    $erros[] = "O comprimento deve ser maior que zero.";
}

if ($larguraCm <= 0) {
    $erros[] = "A largura deve ser maior que zero.";
}

if ($alturaCm <= 0) {
    $erros[] = "A altura deve ser maior que zero.";
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

// Whitelist de campos permitidos (mass assignment fix)
// "id" é incluído para permitir EDITAR produto existente (senão cai em criar()).
$camposPermitidos = [
    "id",
    "categoria_id", "marca_id", "nome", "slug", "descricao", "descricao_curta",
    "preco", "estoque", "ativo", "ean", "sku", "ncm",
    "peso_gramas", "comprimento_cm", "largura_cm", "altura_cm",
    "meta_titulo", "meta_descricao",
    "palavras_chave_principais", "palavras_chave_secundarias",
];

// Remove qualquer campo extra que não esteja na whitelist
$dados = array_intersect_key($dados, array_flip($camposPermitidos));

// Valida campo ativo explicitamente (não confia em null)
$ativo = isset($dados["ativo"]) ? (bool) $dados["ativo"] : true;

$dadosProduto = [
    "categoria_id" => isset($dados["categoria_id"]) ? (int) $dados["categoria_id"] : null,
    "marca_id" => isset($dados["marca_id"]) && $dados["marca_id"] !== "" ? (int) $dados["marca_id"] : null,
    "nome" => $nome,
    "slug" => $slug,
    "descricao" => trim($dados["descricao"] ?? ""),
    "descricao_curta" => trim($dados["descricao_curta"] ?? ""),
    "preco" => $preco,
    "estoque" => $estoque,
    "ativo" => $ativo,
    "ean" => $eanLimpo,
    "sku" => $sku,
    "ncm" => $ncm !== "" ? preg_replace("/[\s.-]/", "", $ncm) : null,
    "peso_gramas" => $pesoGramas,
    "comprimento_cm" => $comprimentoCm,
    "largura_cm" => $larguraCm,
    "altura_cm" => $alturaCm,
    "meta_titulo" => trim($dados["meta_titulo"] ?? ""),
    "meta_descricao" => trim($dados["meta_descricao"] ?? ""),
    "palavras_chave_principais" => trim($dados["palavras_chave_principais"] ?? ""),
    "palavras_chave_secundarias" => trim($dados["palavras_chave_secundarias"] ?? ""),
];

// ============================================================
// Determina o modo (criar/atualizar) ANTES de $produtoId ser
// sobrescrito pelo retorno de criar()/atualizar() — necessário
// para a auditoria logar "CRIAR" vs "ATUALIZAR" corretamente.
// ============================================================
$modo = isset($dados["id"]) && (int) $dados["id"] > 0 ? "ATUALIZAR" : "CRIAR";

// Lista de arquivos movidos para o destino final — usada para
// limpar órfãos se a transação falhar (rollback).
$arquivosMovidos = [];

try {
    $pdo->beginTransaction();

    $produtoId = isset($dados["id"]) ? (int) $dados["id"] : null;

    if ($produtoId) {
        // Valida que o produto existe antes de atualizar (evita UPDATE de 0 linhas)
        $produtoExistente = Produto::buscarPorId($produtoId);
        if (!$produtoExistente) {
            throw new RuntimeException("Produto não encontrado para edição.");
        }
        // Atualiza produto existente
        Produto::atualizar($produtoId, $dadosProduto);
        $mensagem = "Produto atualizado com sucesso!";
    } else {
        // Cria novo produto
        $produtoId = Produto::criar($dadosProduto);
        $mensagem = "Produto criado com sucesso!";
    }

    // ============================================================
    // Processa imagens do produto (upload + sincronização)
    //
    // O formulário envia:
    //   - FILES["imagens"]            → arquivos novos a enviar
    //   - POST["imagem_existente"]    → URLs das imagens que ainda
    //                                   permanecem no formulário
    //   - POST["imagem_principal"]    → URL que deve ser a principal
    //   - POST["imagem_ordem"]        → ordem explícita dos slots (PASSO 5)
    // ============================================================
    $urlsManter = [];

    // 1) Coleta as imagens que o admin manteve no formulário (não removeu)
    if (isset($_POST["imagem_existente"]) && is_array($_POST["imagem_existente"])) {
        foreach ($_POST["imagem_existente"] as $urlExistente) {
            $urlExistente = trim((string) $urlExistente);
            if ($urlExistente !== "") {
                $urlsManter[] = $urlExistente;
            }
        }
    }

    // 2) Adiciona as novas imagens enviadas à lista "a manter"
    $novasUrls = [];
    if (!empty($_FILES["imagens"])) {
        $uploadDir = __DIR__ . "/../../uploads/produtos/";
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        // Garante que o diretório não executável (segurança)
        $htaccess = $uploadDir . ".htaccess";
        if (!file_exists($htaccess)) {
            @file_put_contents($htaccess, "php_flag engine off\nRemoveHandler .php .phtml .php3 .php4 .php5 .php7\n");
        }

        $arquivos = $_FILES["imagens"];
        // Normaliza para array quando multiple (PHP 5+)
        if (!is_array($arquivos["name"])) {
            $arquivos = [
                "name" => [$arquivos["name"]],
                "type" => [$arquivos["type"]],
                "tmp_name" => [$arquivos["tmp_name"]],
                "error" => [$arquivos["error"]],
                "size" => [$arquivos["size"]],
            ];
        }

        foreach ($arquivos["name"] as $i => $nomeOriginal) {
            if ($arquivos["error"][$i] !== UPLOAD_ERR_OK) {
                continue;
            }

            $tmp = $arquivos["tmp_name"][$i];
            if ($tmp === "" || !is_uploaded_file($tmp)) {
                continue;
            }

            // --- VALIDAÇÕES DE SEGURANÇA DE UPLOAD ---

            // a) MIME real via mime_content_type
            $mime = mime_content_type($tmp);
            // SVG NÃO é permitido: pode conter JavaScript inline e virar XSS armazenado
            $permitidosMime = ["image/jpeg", "image/png", "image/webp", "image/gif"];
            if (!in_array($mime, $permitidosMime, true)) {
                continue;
            }

            // b) Extensão (nunca confiar apenas no MIME)
            $extensao = strtolower(pathinfo($nomeOriginal, PATHINFO_EXTENSION));
            $permitidosExt = ["jpg", "jpeg", "png", "webp", "gif"];
            if (!in_array($extensao, $permitidosExt, true)) {
                continue;
            }

            // c) É imagem real?
            $imgInfo = @getimagesize($tmp);
            if ($imgInfo === false || $imgInfo[0] <= 0 || $imgInfo[1] <= 0) {
                continue;
            }

            // d) Tamanho máx 5MB
            if ((int) $arquivos["size"][$i] > 5 * 1024 * 1024) {
                continue;
            }

            // e) Nome seguro
            $nomeArquivo = "produto_" . $produtoId . "_" . $i . "_" . bin2hex(random_bytes(16)) . ".{$extensao}";
            $caminho = $uploadDir . $nomeArquivo;

            if (move_uploaded_file($tmp, $caminho)) {
                // Usa $i (posição DOM real no array "imagens[]") como chave,
                // para que o marcador __NOVO__:<indiceDom> do frontend mapeie
                // corretamente, mesmo quando slots do meio estão vazios ou
                // foram rejeitados na validação.
                $novasUrls[$i] = "uploads/produtos/" . $nomeArquivo;
                // Registra o arquivo movido para limpeza em caso de rollback
                $arquivosMovidos[] = $caminho;
            }
        }
    }

    // 3) Lista final de URLs na ordem visual dos slots (PASSO 5)
    //    O frontend envia POST["imagem_ordem"] como array de URLs na
    //    ordem em que os slots aparecem. Para imagens novas (ainda sem
    //    URL no servidor), envia o marcador "__NOVO__:<índice>", onde
    //    <índice> é a posição visual do slot (que corresponde à posição
    //    no array $_FILES["imagens"]). Mapeamos o marcador para a URL
    //    real gerada no upload. Se não enviar, usa o fallback antigo.
    $urlsFinais = [];
    if (isset($_POST["imagem_ordem"]) && is_array($_POST["imagem_ordem"])) {
        foreach ($_POST["imagem_ordem"] as $urlOrdem) {
            $urlOrdem = trim((string) $urlOrdem);
            if ($urlOrdem === "") {
                continue;
            }
            // Mapeia marcador __NOVO__:<índice> para a URL real do upload
            if (str_starts_with($urlOrdem, "__NOVO__:")) {
                $indiceNovo = (int) substr($urlOrdem, strlen("__NOVO__:"));
                if (isset($novasUrls[$indiceNovo])) {
                    $urlsFinais[] = $novasUrls[$indiceNovo];
                }
                // Se o índice não existir (upload falhou), ignora
                continue;
            }
            $urlsFinais[] = $urlOrdem;
        }
    }
    if (empty($urlsFinais)) {
        $urlsFinais = array_merge($urlsManter, $novasUrls);
    }

    // 4) Sincroniza a tabela produto_imagens com as URLs finais
    $stmtExistentes = $pdo->prepare("SELECT id, url FROM produto_imagens WHERE produto_id = ?");
    $stmtExistentes->execute([$produtoId]);
    $imagensAtuais = $stmtExistentes->fetchAll(PDO::FETCH_ASSOC);
    $urlsAtuais = array_column($imagensAtuais, "url");

    // 4.1) Remove do banco (e disco) imagens que não estão mais na lista final
    foreach ($imagensAtuais as $imagem) {
        if (!in_array($imagem["url"], $urlsFinais, true)) {
            // Remove registro
            $pdo->prepare("DELETE FROM produto_imagens WHERE id = ?")->execute([$imagem["id"]]);
            // Remove arquivo físico se for local (ignora placeholder e http)
            if (!str_contains($imagem["url"], "placeholder:") && !preg_match('#^https?://#', $imagem["url"])) {
                $caminhoFs = __DIR__ . "/../../" . $imagem["url"];
                if (is_file($caminhoFs)) {
                    @unlink($caminhoFs);
                }
            }
        }
    }

    // 4.2) Determina a URL principal: escolhida pelo admin OU a primeira da lista
    $principalUrl = trim((string) ($_POST["imagem_principal"] ?? ""));
    // Se a principal for um marcador de imagem nova (__NOVO__:<indiceDom>),
    // mapeia para a URL real gerada no upload (mesma lógica do imagem_ordem).
    if (str_starts_with($principalUrl, "__NOVO__:")) {
        $indiceNovo = (int) substr($principalUrl, strlen("__NOVO__:"));
        $principalUrl = $novasUrls[$indiceNovo] ?? $principalUrl;
    }
    if ($principalUrl === "" && !empty($urlsFinais)) {
        $principalUrl = $urlsFinais[0];
    }

    // 4.3) Reinsere/atualiza as URLs finais com ordem e principal corretas
    //      (faz "delete + insert" para simplificar a ordenação)
    if (!empty($urlsFinais)) {
        // Deleta todas (as removidas já foram; as mantidas serão recriadas)
        // Obs: recriar muda o id, mas é aceitável porque nada referencia produto_imagens.id
        $pdo->prepare("DELETE FROM produto_imagens WHERE produto_id = ?")->execute([$produtoId]);

        $stmtInsert = $pdo->prepare("
            INSERT INTO produto_imagens (produto_id, url, principal, ordem)
            VALUES (?, ?, ?, ?)
        ");

        // Garante exatamente UMA principal: usa o índice da URL principal
        // na lista, não comparação de URL (que falha se a principal foi
        // removida ou se há URLs duplicadas). (PASSO 4.2)
        $indicePrincipal = array_search($principalUrl, $urlsFinais, true);
        if ($indicePrincipal === false) {
            $indicePrincipal = 0;
        }

        foreach ($urlsFinais as $indice => $url) {
            $principal = ($indice === $indicePrincipal);
            // PostgreSQL/PDO não aceita booleano PHP direto em coluna boolean;
            // usa 't'/'f' (aceito pelo driver pgsql).
            $stmtInsert->execute([$produtoId, $url, $principal ? 't' : 'f', $indice]);
        }
    }

    // Registra auditoria com o modo correto (CRIAR vs ATUALIZAR)
    registrarAuditoria(
        $modo,
        "PRODUTO",
        $produtoId,
        "Produto: " . $nome . " | SKU: " . $sku . " | EAN: " . $eanLimpo
    );

    $pdo->commit();

    echo json_encode([
        "sucesso" => true,
        "mensagem" => $mensagem,
        "id" => $produtoId,
    ]);
} catch (Throwable $e) {
    // Rollback da transação
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // Limpa arquivos movidos para o destino final (evita órfãos no disco)
    foreach ($arquivosMovidos as $caminhoArquivo) {
        if (is_file($caminhoArquivo)) {
            @unlink($caminhoArquivo);
        }
    }

    error_log("Erro ao salvar produto: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "sucesso" => false,
        "erro" => "Erro interno ao salvar produto. Tente novamente.",
    ]);
}