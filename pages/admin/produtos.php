<?php

require_once __DIR__ . "/../../config/bootstrap.php";

// Página restrita a ADMIN/GERENTE — FUNCIONARIO é redirecionado
// (a autorização real continua no requererPapel() das APIs)
if (!temPapel("ADMIN", "GERENTE")) {
    header("Location: " . pageUrl("admin/pedidos_pendentes.php"));
    exit;
}

$pageTitle = "Gestão de Produtos";
$currentAdminPage = "produtos";
$extraJs = "admin-produtos.js";
require __DIR__ . "/../../includes/admin-header.php";

// Categorias e marcas para os selects (do banco)
$categorias = Produto::categorias();
$marcas = Produto::marcas();

// Modo edição: se houver ?id=, carrega o produto do banco
$produtoEditando = null;
$produtoFaqs = [];
$produtoTickets = [];
$produtoImagens = [];

$produtoId = isset($_GET["id"]) ? (int) $_GET["id"] : 0;
if ($produtoId > 0) {
    $produtoEditando = Produto::buscarPorId($produtoId);

    if ($produtoEditando) {
        // Busca FAQs, tickets e imagens vinculados ao produto
        $produtoFaqs = Produto::faqs($produtoId);
        $produtoTickets = Produto::tickets($produtoId);
        $produtoImagens = Produto::imagens($produtoId);

        // Busca mensagens de cada ticket
        foreach ($produtoTickets as &$ticket) {
            $ticket["mensagens"] = Produto::ticketMensagens((int) $ticket["id"]);
        }
        unset($ticket);
    }
}

// Paginação — 10 produtos por página
$limitePorPagina = 10;
$paginaAtual = isset($_GET["pagina"]) ? max(1, (int) $_GET["pagina"]) : 1;
$totalProdutos = Produto::contarTodosAdmin();
$totalPaginas = max(1, (int) ceil($totalProdutos / $limitePorPagina));
$paginaAtual = min($paginaAtual, $totalPaginas);

$produtos = Produto::todosAdmin($paginaAtual, $limitePorPagina);

// Helper para preencher valores nos inputs em modo edição
function valorCampo(?array $produto, string $campo): string
{
    return e($produto[$campo] ?? "");
}
?>

<link rel="stylesheet" href="<?= asset("css/admin-produtos.css") ?>">

<input type="hidden" id="csrf-token" value="<?= e(csrf_token()) ?>">

<div class="produto-form">
    <!-- ============================================================
         CABEÇALHO DO FORMULÁRIO
         ============================================================ -->
    <div class="produto-form-header">
        <h1><?= $produtoEditando ? "Editar: " . e($produtoEditando["nome"]) : "Cadastrar Novo Produto" ?></h1>
        <div class="produto-form-actions">
            <a href="<?= pageUrl("admin/produtos.php") ?>" class="btn btn-gray">Voltar à Lista</a>
            <?php if ($podeGerenciar): ?>
                <button type="submit" form="form-produto" class="btn btn-primary" id="btn-salvar">Salvar Produto</button>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($produtos): ?>
        <!-- Listagem rápida de produtos existentes -->
        <div class="collapsible-section" style="margin-bottom: 24px;">
            <button type="button" class="collapsible-header">
                <span>Produtos Cadastrados (<?= $totalProdutos ?>)</span>
                <span class="collapse-icon">▼</span>
            </button>
            <div class="collapsible-body">
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>Categoria</th>
                                <th>SKU</th>
                                <th>Preço</th>
                                <th>Estoque</th>
                                <th>Status</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($produtos as $produto): ?>
                                <tr>
                                    <td><?= e($produto["nome"]) ?></td>
                                    <td><?= e($produto["categoria_nome"] ?? "—") ?></td>
                                    <td><?= e($produto["sku"] ?? "—") ?></td>
                                    <td><?= formatarMoeda((float) $produto["preco"]) ?></td>
                                    <td><?= (int) $produto["estoque"] ?></td>
                                    <td>
                                        <span class="badge <?= $produto["ativo"] ? "badge-pago" : "badge-cancelado" ?>">
                                            <?= $produto["ativo"] ? "Ativo" : "Inativo" ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($podeGerenciar): ?>
                                            <a href="<?= pageUrl("admin/produtos.php?id=" . $produto["id"]) ?>" class="btn btn-sm btn-outline">Editar</a>
                                        <?php else: ?>
                                            <span style="color:var(--cinza-500);font-size:0.85rem">Somente leitura</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Paginação -->
                <?php if ($totalPaginas > 1): ?>
                    <?php
                    // Gera a lista de páginas com janela deslizante
                    $paginas = paginacaoJanela($paginaAtual, $totalPaginas, 2);
                    ?>
                    <div class="pagination-container">
                        <?php if ($paginaAtual > 1): ?>
                            <a href="<?= pageUrl("admin/produtos.php") ?>?pagina=<?= $paginaAtual - 1 ?>" class="btn btn-gray btn-sm">← Anterior</a>
                        <?php endif; ?>

                        <?php foreach ($paginas as $pagina): ?>
                            <?php if ($pagina === ".."): ?>
                                <span class="btn btn-sm" style="pointer-events:none;color:var(--cinza-500)">..</span>
                            <?php elseif ($pagina === $paginaAtual): ?>
                                <span class="btn btn-primary btn-sm" style="pointer-events:none"><?= $pagina ?></span>
                            <?php else: ?>
                                <a href="<?= pageUrl("admin/produtos.php") ?>?pagina=<?= $pagina ?>" class="btn btn-gray btn-sm"><?= $pagina ?></a>
                            <?php endif; ?>
                        <?php endforeach; ?>

                        <?php if ($paginaAtual < $totalPaginas): ?>
                            <a href="<?= pageUrl("admin/produtos.php") ?>?pagina=<?= $paginaAtual + 1 ?>" class="btn btn-gray btn-sm">Próxima →</a>
                        <?php endif; ?>
                    </div>
                    <p class="pagination-info">
                        Página <?= $paginaAtual ?> de <?= $totalPaginas ?> • <?= $totalProdutos ?> produtos no total
                    </p>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- ============================================================
         BARRA DE PROGRESSO DO FORMULÁRIO
         ============================================================ -->
    <div class="form-progress">
        <div class="form-progress-header">
            <span>Progresso do cadastro</span>
            <span class="progress-percent">0%</span>
        </div>
        <div class="progress-bar">
            <div class="progress-bar-fill"></div>
        </div>
    </div>

    <!-- ============================================================
         NAVEGAÇÃO POR ABAS (TABS)
         ============================================================ -->
    <nav class="tabs-nav" aria-label="Seções do produto">
        <button type="button" class="tab-btn active" data-tab="basicos">
            <span class="tab-icon"></span>
            <span class="tab-valid"></span>
            Dados Básicos
        </button>
        <button type="button" class="tab-btn" data-tab="seo">
            <span class="tab-icon"></span>
            <span class="tab-valid"></span>
            SEO / Pesquisa
        </button>
        <button type="button" class="tab-btn" data-tab="identificacao">
            <span class="tab-icon"></span>
            <span class="tab-valid"></span>
            Identificação
        </button>
        <button type="button" class="tab-btn" data-tab="suporte">
            <span class="tab-icon"></span>
            <span class="tab-valid"></span>
            SAC / Suporte
        </button>
    </nav>

    <!-- ============================================================
         FORMULÁRIO PRINCIPAL
         ============================================================ -->
    <form id="form-produto" enctype="multipart/form-data" novalidate>

        <!-- ID do produto em modo edição -->
        <?php if ($produtoEditando): ?>
            <input type="hidden" id="produto-id" name="id" value="<?= (int) $produtoEditando["id"] ?>">
        <?php endif; ?>

        <!-- ========================================================
             ABA 1: CADASTRO/EDIÇÃO DE PRODUTO
             ======================================================== -->
        <section class="tab-panel active" id="tab-basicos">

            <!-- Seção: Informações Básicas -->
            <div class="collapsible-section">
                <button type="button" class="collapsible-header">
                    <span>Informações Básicas</span>
                    <span class="collapse-icon">▼</span>
                </button>
                <div class="collapsible-body">
                    <div class="form-grid">
                        <!-- Nome do produto (obrigatório) -->
                        <div class="form-group col-12">
                            <label class="form-label" for="produto-nome">Nome do Produto *</label>
                            <input type="text"
                                   class="form-control"
                                   id="produto-nome"
                                   name="nome"
                                   value="<?= valorCampo($produtoEditando, "nome") ?>"
                                   placeholder="Ex: Camiseta Orgânica Algodão Premium"
                                   data-required="true"
                                   required>
                            <div class="invalid-feedback">O nome do produto deve ter pelo menos 3 caracteres.</div>
                        </div>
                        <!-- Marca (select do banco) -->
                        <div class="form-group col-12">
                            <label class="form-label" for="produto-marca">Marca</label>
                            <select class="form-control" id="produto-marca" name="marca_id">
                                <option value="">— Produto sem marca —</option>
                                <?php foreach ($marcas as $marca): ?>
                                    <option value="<?= (int) $marca["id"] ?>"
                                        <?= $produtoEditando && (int) ($produtoEditando["marca_id"] ?? 0) === (int) $marca["id"] ? "selected" : "" ?>>
                                        <?= e($marca["nome"]) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="suggestion-hint">
                                <span class="hint-icon"></span>
                                Selecione a marca do produto ou deixe "Produto sem marca".
                            </p>
                        </div>

                        <!-- Descrição curta -->
                        <div class="form-group col-12">
                            <label class="form-label" for="descricao-curta">Descrição Curta</label>
                            <input type="text"
                                   class="form-control"
                                   id="descricao-curta"
                                   name="descricao_curta"
                                   value="<?= valorCampo($produtoEditando, "descricao_curta") ?>"
                                   placeholder="Resumo atrativo exibido na listagem de produtos"
                                   maxlength="200">
                            <div class="char-counter">
                                <span>Usada no preview do Google</span>
                                <span class="counter-value" id="contador-desc-curta">
                                    <?= $produtoEditando ? mb_strlen($produtoEditando["descricao_curta"] ?? "") : 0 ?> / 200
                                </span>
                            </div>
                        </div>

                        <!-- Descrição completa -->
                        <div class="form-group col-12">
                            <label class="form-label" for="descricao-completa">Descrição Completa</label>
                            <textarea class="form-control"
                                      id="descricao-completa"
                                      name="descricao"
                                      rows="5"
                                      placeholder="Descrição detalhada do produto, características, materiais, etc."><?= valorCampo($produtoEditando, "descricao") ?></textarea>
                        </div>

                        <!-- Categoria (do banco) -->
                        <div class="form-group col-6">
                            <label class="form-label" for="categoria">Categoria *</label>
                            <select class="form-control" id="categoria" name="categoria_id" required>
                                <option value="">Selecione a categoria</option>
                                <?php foreach ($categorias as $categoria): ?>
                                    <option value="<?= (int) $categoria["id"] ?>"
                                        <?= $produtoEditando && (int) $produtoEditando["categoria_id"] === (int) $categoria["id"] ? "selected" : "" ?>>
                                        <?= e($categoria["nome"]) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Subcategoria -->
                        <div class="form-group col-6">
                            <label class="form-label" for="subcategoria">Subcategoria</label>
                            <select class="form-control" id="subcategoria" name="subcategoria">
                                <option value="">Selecione a subcategoria</option>
                                <option value="Camisetas">Camisetas</option>
                                <option value="Calças">Calças</option>
                                <option value="Vestidos">Vestidos</option>
                                <option value="Jaquetas">Jaquetas</option>
                            </select>
                        </div>

                        <!-- Preço e estoque -->
                        <div class="form-group col-4">
                            <label class="form-label" for="produto-preco">Preço (R$) *</label>
                            <input type="number"
                                   class="form-control"
                                   id="produto-preco"
                                   name="preco"
                                   value="<?= valorCampo($produtoEditando, "preco") ?>"
                                   step="0.01"
                                   min="0"
                                   placeholder="0,00"
                                   required>
                            <div class="invalid-feedback">O preço deve ser maior que zero.</div>
                        </div>

                        <div class="form-group col-4">
                            <label class="form-label" for="produto-estoque">Estoque *</label>
                            <input type="number"
                                   class="form-control"
                                   id="produto-estoque"
                                   name="estoque"
                                   value="<?= valorCampo($produtoEditando, "estoque") ?>"
                                   min="0"
                                   placeholder="0"
                                   required>
                            <div class="invalid-feedback">O estoque não pode ser negativo.</div>
                        </div>

                        <!-- Status ativo/inativo -->
                        <div class="form-group col-4">
                            <label class="form-label" for="produto-status">Status</label>
                            <select class="form-control" id="produto-status" name="ativo">
                                <option value="1" <?= $produtoEditando && $produtoEditando["ativo"] ? "selected" : "" ?>>Ativo</option>
                                <option value="0" <?= $produtoEditando && !$produtoEditando["ativo"] ? "selected" : "" ?>>Inativo</option>
                            </select>
                        </div>

                        <!-- Peso e dimensões (para frete e ficha técnica) -->
                        <div class="form-group col-4">
                            <label class="form-label" for="produto-peso">Peso (g) *</label>
                            <input type="number"
                                   class="form-control"
                                   id="produto-peso"
                                   name="peso_gramas"
                                   value="<?= valorCampo($produtoEditando, "peso_gramas") ?>"
                                   min="1"
                                   step="1"
                                   placeholder="300"
                                   required>
                            <div class="invalid-feedback">O peso deve ser um número positivo.</div>
                        </div>

                        <div class="form-group col-4">
                            <label class="form-label" for="produto-comprimento">Comprimento (cm) *</label>
                            <input type="number"
                                   class="form-control"
                                   id="produto-comprimento"
                                   name="comprimento_cm"
                                   value="<?= valorCampo($produtoEditando, "comprimento_cm") ?>"
                                   min="1"
                                   step="1"
                                   placeholder="16"
                                   required>
                            <div class="invalid-feedback">O comprimento deve ser um número positivo.</div>
                        </div>

                        <div class="form-group col-4">
                            <label class="form-label" for="produto-largura">Largura (cm) *</label>
                            <input type="number"
                                   class="form-control"
                                   id="produto-largura"
                                   name="largura_cm"
                                   value="<?= valorCampo($produtoEditando, "largura_cm") ?>"
                                   min="1"
                                   step="1"
                                   placeholder="11"
                                   required>
                            <div class="invalid-feedback">A largura deve ser um número positivo.</div>
                        </div>

                        <div class="form-group col-4">
                            <label class="form-label" for="produto-altura">Altura (cm) *</label>
                            <input type="number"
                                   class="form-control"
                                   id="produto-altura"
                                   name="altura_cm"
                                   value="<?= valorCampo($produtoEditando, "altura_cm") ?>"
                                   min="1"
                                   step="1"
                                   placeholder="2"
                                   required>
                            <div class="invalid-feedback">A altura deve ser um número positivo.</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Seção: Imagens do Produto -->
            <div class="collapsible-section">
                <button type="button" class="collapsible-header">
                    <span>Imagens do Produto</span>
                    <span class="collapse-icon">▼</span>
                </button>
                <div class="collapsible-body">
                    <p class="suggestion-hint">
                        <span class="hint-icon"></span>
                        Arraste e solte imagens ou clique para selecionar. Máximo 8 imagens, 5MB cada. Formatos: JPG, PNG, WebP, GIF.
                    </p>
                    <div class="image-uploader" id="image-uploader">
                        <?php for ($i = 1; $i <= 8; $i++): ?>
                            <?php $imagemAtual = $produtoImagens[$i - 1] ?? null; ?>
                            <div class="image-upload-item <?= $imagemAtual ? "has-image" : "" ?>" 
                                 id="imagem-slot-<?= $i ?>" 
                                 data-index="<?= $i - 1 ?>"
                                 draggable="<?= $imagemAtual ? "true" : "false" ?>">
                                <input type="file" 
                                       accept="image/jpeg,image/png,image/webp,image/gif" 
                                       name="imagens[]"
                                       data-index="<?= $i - 1 ?>">
                                <?php if ($imagemAtual && !empty($imagemAtual["url"])): ?>
                                    <input type="hidden" name="imagem_existente[]" value="<?= e($imagemAtual["url"]) ?>">
                                    <img src="<?= e($imagemAtual["url"]) ?>" 
                                         alt="Imagem <?= $i ?>" 
                                         data-url="<?= e($imagemAtual["url"]) ?>"
                                         draggable="true">
                                <?php endif; ?>
                                <div class="upload-placeholder">
                                    <span class="icon">+</span>
                                    <span><?= $i === 1 ? "Principal" : "Imagem $i" ?></span>
                                </div>
                                <button type="button" class="remove-image" aria-label="Remover imagem">×</button>
                                <div class="upload-progress" style="display:none">
                                    <div class="upload-progress-bar"></div>
                                </div>
                            </div>
                        <?php endfor; ?>
                    </div>
                    <div class="upload-zone" id="upload-zone" style="display:none">
                        <div class="upload-zone-content">
                            <span class="upload-zone-icon">📁</span>
                            <p>Solte as imagens aqui</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ========================================================
             ABA 2: SEO / PESQUISA WEB
             ======================================================== -->
        <section class="tab-panel" id="tab-seo">

            <!-- Seção: Palavras-chave -->
            <div class="collapsible-section">
                <button type="button" class="collapsible-header">
                    <span>Palavras-chave</span>
                    <span class="collapse-icon">▼</span>
                </button>
                <div class="collapsible-body">
                    <div class="form-grid">
                        <!-- Palavras-chave principais (do banco) -->
                        <div class="form-group col-12">
                            <label class="form-label">Palavras-chave Principais</label>
                            <div class="tags-input" id="tags-principais"
                                 data-tags="<?= e($produtoEditando["palavras_chave_principais"] ?? "") ?>">
                                <input type="text" placeholder="Digite e pressione Enter para adicionar...">
                                <input type="hidden" name="palavras_chave_principais">
                            </div>
                            <p class="suggestion-hint">
                                <span class="hint-icon"></span>
                                Ex: camiseta orgânica, camiseta algodão, camiseta sustentável
                            </p>
                        </div>

                        <!-- Palavras-chave secundárias (do banco) -->
                        <div class="form-group col-12">
                            <label class="form-label">Palavras-chave Secundárias</label>
                            <div class="tags-input" id="tags-secundarias"
                                 data-tags="<?= e($produtoEditando["palavras_chave_secundarias"] ?? "") ?>">
                                <input type="text" placeholder="Digite e pressione Enter para adicionar...">
                                <input type="hidden" name="palavras_chave_secundarias">
                            </div>
                            <p class="suggestion-hint">
                                <span class="hint-icon"></span>
                                Ex: roupa ecológica, moda sustentável, tecido natural
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Seção: Meta dados -->
            <div class="collapsible-section">
                <button type="button" class="collapsible-header">
                    <span>Meta Título e Descrição</span>
                    <span class="collapse-icon">▼</span>
                </button>
                <div class="collapsible-body">
                    <div class="form-grid">
                        <!-- Meta título com contador -->
                        <div class="form-group col-12">
                            <label class="form-label" for="meta-titulo">Meta Título</label>
                            <input type="text"
                                   class="form-control"
                                   id="meta-titulo"
                                   name="meta_titulo"
                                   value="<?= valorCampo($produtoEditando, "meta_titulo") ?>"
                                   placeholder="Título exibido nos resultados do Google"
                                   maxlength="70">
                            <div class="char-counter">
                                <span>Recomendado: até 60 caracteres</span>
                                <span class="counter-value" id="contador-titulo">
                                    <?= $produtoEditando ? mb_strlen($produtoEditando["meta_titulo"] ?? "") : 0 ?> / 60
                                </span>
                            </div>
                        </div>

                        <!-- Meta descrição com contador -->
                        <div class="form-group col-12">
                            <label class="form-label" for="meta-descricao">Meta Descrição</label>
                            <textarea class="form-control"
                                      id="meta-descricao"
                                      name="meta_descricao"
                                      rows="3"
                                      placeholder="Descrição exibida nos resultados do Google"
                                      maxlength="200"><?= valorCampo($produtoEditando, "meta_descricao") ?></textarea>
                            <div class="char-counter">
                                <span>Recomendado: até 160 caracteres</span>
                                <span class="counter-value" id="contador-descricao">
                                    <?= $produtoEditando ? mb_strlen($produtoEditando["meta_descricao"] ?? "") : 0 ?> / 160
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Seção: Slug e Preview SERP -->
            <div class="collapsible-section">
                <button type="button" class="collapsible-header">
                    <span>Slug / URL Amigável</span>
                    <span class="collapse-icon">▼</span>
                </button>
                <div class="collapsible-body">
                    <div class="form-grid">
                        <!-- Slug com geração automática -->
                        <div class="form-group col-12">
                            <label class="form-label" for="produto-slug">Slug / URL Amigável *</label>
                            <div class="input-group">
                                <input type="text"
                                       class="form-control"
                                       id="produto-slug"
                                       name="slug"
                                       value="<?= valorCampo($produtoEditando, "slug") ?>"
                                       placeholder="camiseta-organica-algodao"
                                       required>
                                <button type="button"
                                        class="input-action"
                                        id="slug-auto"
                                        title="Gerar slug automaticamente"
                                        aria-label="Gerar slug automaticamente">Regenerar</button>
                            </div>
                            <div class="invalid-feedback">Slug inválido. Use apenas letras minúsculas, números e hífens.</div>
                            <p class="suggestion-hint">
                                <span class="hint-icon"></span>
                                Gerado automaticamente a partir do nome. Clique em "Regenerar" para regenerar.
                            </p>
                        </div>

                        <!-- Preview de SERP (Google) -->
                        <div class="form-group col-12">
                            <div class="serp-preview">
                                <div class="serp-preview-label">
                                    <span></span> Preview no Google
                                </div>
                                <div class="serp-url">
                                    <span class="favicon">E</span>
                                    <span id="serp-url">
                                        www.ecoshop.com.br/produto/<?= e($produtoEditando["slug"] ?? "nome-do-produto") ?>
                                    </span>
                                </div>
                                <div class="serp-title" id="serp-titulo">
                                    <?= e($produtoEditando["meta_titulo"] ?? $produtoEditando["nome"] ?? "Título do produto aparecerá aqui") ?>
                                </div>
                                <div class="serp-desc" id="serp-descricao">
                                    <?= e($produtoEditando["meta_descricao"] ?? $produtoEditando["descricao_curta"] ?? "A descrição do produto aparecerá aqui no Google.") ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ========================================================
             ABA 3: IDENTIFICAÇÃO DO PRODUTO
             ======================================================== -->
        <section class="tab-panel" id="tab-identificacao">

            <!-- Seção: Códigos de identificação -->
            <div class="collapsible-section">
                <button type="button" class="collapsible-header">
                    <span>Códigos de Identificação</span>
                    <span class="collapse-icon">▼</span>
                </button>
                <div class="collapsible-body">
                    <div class="form-grid">
                        <!-- EAN com validação -->
                        <div class="form-group col-6">
                            <label class="form-label" for="produto-ean">EAN (Código de Barras) *</label>
                            <input type="text"
                                   class="form-control"
                                   id="produto-ean"
                                   name="ean"
                                   value="<?= valorCampo($produtoEditando, "ean") ?>"
                                   placeholder="7891234567890"
                                   maxlength="14"
                                   required>
                            <div class="invalid-feedback">EAN inválido. Use 8 ou 13 dígitos válidos.</div>
                            <p class="suggestion-hint">
                                <span class="hint-icon"></span>
                                Código de barras internacional (EAN-8 ou EAN-13)
                            </p>
                        </div>

                        <!-- SKU com sugestão automática -->
                        <div class="form-group col-6">
                            <label class="form-label" for="produto-sku">SKU (Código Interno) *</label>
                            <div class="input-group">
                                <input type="text"
                                       class="form-control"
                                       id="produto-sku"
                                       name="sku"
                                       value="<?= valorCampo($produtoEditando, "sku") ?>"
                                       placeholder="CAM-ORG-1234"
                                       required>
                                <button type="button"
                                        class="input-action"
                                        id="sku-sugerir"
                                        title="Sugerir SKU automaticamente"
                                        aria-label="Sugerir SKU automaticamente">Sugerir</button>
                            </div>
                            <div class="invalid-feedback">O SKU deve ter pelo menos 5 caracteres.</div>
                            <p class="suggestion-hint">
                                <span class="hint-icon"></span>
                                Sugerido automaticamente com base na categoria e nome. Clique em "Sugerir" para gerar.
                            </p>
                        </div>

                        <!-- NCM (opcional) -->
                        <div class="form-group col-6">
                            <label class="form-label" for="produto-ncm">NCM (Opcional)</label>
                            <input type="text"
                                   class="form-control"
                                   id="produto-ncm"
                                   name="ncm"
                                   value="<?= valorCampo($produtoEditando, "ncm") ?>"
                                   placeholder="6109.10.00"
                                   maxlength="10">
                            <div class="invalid-feedback">NCM deve ter 8 dígitos numéricos.</div>
                            <p class="suggestion-hint">
                                <span class="hint-icon"></span>
                                Nomenclatura Comum do Mercosul — usado para impostos no Brasil
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ========================================================
             ABA 4: SAC / SUPORTE AO CLIENTE
             ======================================================== -->
        <section class="tab-panel" id="tab-suporte">

            <!-- Seção: Tickets de atendimento (do banco) -->
            <div class="collapsible-section">
                <button type="button" class="collapsible-header">
                    <span>Tickets de Atendimento (<?= count($produtoTickets) ?>)</span>
                    <span class="collapse-icon">▼</span>
                </button>
                <div class="collapsible-body">
                    <?php if (empty($produtoTickets)): ?>
                        <div class="empty-state">
                            <h3>Nenhum ticket de atendimento</h3>
                            <p>Este produto ainda não possui tickets vinculados.</p>
                        </div>
                    <?php else: ?>
                        <div class="ticket-list">
                            <?php foreach ($produtoTickets as $ticket): ?>

                                <?php
                                // Mapeia status para classes CSS
                                $statusClass = match ($ticket["status"]) {
                                    "ABERTO" => "status-aberto",
                                    "EM_ANDAMENTO" => "status-andamento",
                                    "RESOLVIDO" => "status-resolvido",
                                    default => "status-aberto",
                                };
                                $statusLabel = match ($ticket["status"]) {
                                    "ABERTO" => "Aberto",
                                    "EM_ANDAMENTO" => "Em andamento",
                                    "RESOLVIDO" => "Resolvido",
                                    default => $ticket["status"],
                                };
                                $dataTicket = new DateTime($ticket["created_at"]);
                                ?>

                                <div class="ticket-item">
                                    <div class="ticket-header">
                                        <span class="ticket-subject"><?= e($ticket["assunto"]) ?></span>
                                        <span class="status-badge <?= $statusClass ?>"><?= $statusLabel ?></span>
                                    </div>
                                    <div class="ticket-meta">
                                        <span>Cliente: <?= e($ticket["cliente_nome"] ?? "—") ?></span>
                                        <span><?= $dataTicket->format("d/m/Y") ?> às <?= $dataTicket->format("H:i") ?></span>
                                    </div>

                                    <?php if (!empty($ticket["mensagens"])): ?>
                                        <div class="message-history" style="margin-top: 12px; max-height: 200px;">
                                            <?php foreach ($ticket["mensagens"] as $msg): ?>
                                                <?php
                                                $dataMsg = new DateTime($msg["created_at"]);
                                                $tipoMsg = $msg["origem"] === "ADMIN" ? "admin" : "cliente";
                                                ?>
                                                <div class="message-bubble <?= $tipoMsg ?>">
                                                    <?= e($msg["mensagem"]) ?>
                                                    <div class="message-meta">
                                                        <span><?= e($msg["autor_nome"] ?? ($msg["origem"] === "ADMIN" ? "Equipe EcoShop" : "Cliente")) ?></span>
                                                        <span><?= $dataMsg->format("d/m/Y H:i") ?></span>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Seção: FAQ do Produto (do banco) -->
            <div class="collapsible-section">
                <button type="button" class="collapsible-header">
                    <span>FAQ do Produto (<?= count($produtoFaqs) ?>)</span>
                    <span class="collapse-icon">▼</span>
                </button>
                <div class="collapsible-body">
                    <?php if (empty($produtoFaqs)): ?>
                        <div class="empty-state">
                            <h3>Nenhuma FAQ cadastrada</h3>
                            <p>Adicione perguntas frequentes para auxiliar os clientes.</p>
                        </div>
                    <?php else: ?>
                        <div class="faq-list">
                            <?php foreach ($produtoFaqs as $index => $faq): ?>
                                <div class="faq-item <?= $index === 0 ? "open" : "" ?>">
                                    <div class="faq-item-header">
                                        <span class="faq-question"><?= e($faq["pergunta"]) ?></span>
                                        <span class="faq-toggle">▼</span>
                                    </div>
                                    <div class="faq-item-body">
                                        <?= e($faq["resposta"]) ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Seção: Histórico de Mensagens -->
            <div class="collapsible-section">
                <button type="button" class="collapsible-header">
                    <span>Responder Cliente</span>
                    <span class="collapse-icon">▼</span>
                </button>
                <div class="collapsible-body">
                    <div class="form-group">
                        <label class="form-label" for="nova-mensagem">Nova Mensagem</label>
                        <textarea class="form-control"
                                  id="nova-mensagem"
                                  rows="3"
                                  placeholder="Digite sua resposta para o cliente..."></textarea>
                    </div>
                    <button type="button" class="btn btn-primary" id="btn-enviar-mensagem">
                        Enviar Mensagem
                    </button>
                </div>
            </div>
        </section>

    </form>
</div>

<?php require __DIR__ . "/../../includes/admin-footer.php"; ?>