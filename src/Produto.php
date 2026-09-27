<?php

class Produto
{
    public static function listar(array $filtros = []): array
    {
        global $pdo;

        $sql = "
            SELECT p.*, c.nome AS categoria_nome, c.slug AS categoria_slug,
                   m.nome AS marca_nome, m.slug AS marca_slug,
                   (SELECT url FROM produto_imagens WHERE produto_id = p.id AND principal = TRUE LIMIT 1) AS imagem
            FROM produtos p
            LEFT JOIN categorias c ON c.id = p.categoria_id
            LEFT JOIN marcas m ON m.id = p.marca_id
            WHERE p.ativo = TRUE
        ";
        $params = [];

        if (!empty($filtros["categoria"])) {
            $sql .= " AND c.slug = ?";
            $params[] = $filtros["categoria"];
        }

        if (!empty($filtros["marca"])) {
            $sql .= " AND m.slug = ?";
            $params[] = $filtros["marca"];
        }

        if (!empty($filtros["busca"])) {
            $termo = "%" . $filtros["busca"] . "%";
            $sql .= " AND (p.nome ILIKE ? OR p.descricao ILIKE ? OR p.palavras_chave_principais ILIKE ? OR p.palavras_chave_secundarias ILIKE ?)";
            $params[] = $termo;
            $params[] = $termo;
            $params[] = $termo;
            $params[] = $termo;
        }

        if (!empty($filtros["preco_min"])) {
            $sql .= " AND p.preco >= ?";
            $params[] = $filtros["preco_min"];
        }

        if (!empty($filtros["preco_max"])) {
            $sql .= " AND p.preco <= ?";
            $params[] = $filtros["preco_max"];
        }

        $ordenacao = match ($filtros["ordenar"] ?? "recentes") {
            "preco_asc" => "p.preco ASC",
            "preco_desc" => "p.preco DESC",
            "nome" => "p.nome ASC",
            default => "p.created_at DESC",
        };

        $sql .= " ORDER BY $ordenacao";

        // Paginação — 20 produtos por página
        $pagina = isset($filtros["pagina"]) ? max(1, (int) $filtros["pagina"]) : 1;
        $limite = 20;
        $offset = ($pagina - 1) * $limite;

        $sql .= " LIMIT ? OFFSET ?";
        $params[] = $limite;
        $params[] = $offset;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * Conta o total de produtos ativos com os mesmos filtros da listagem.
     * Usado para calcular o número de páginas.
     */
    public static function contarComFiltros(array $filtros = []): int
    {
        global $pdo;

        $sql = "
            SELECT COUNT(*)
            FROM produtos p
            LEFT JOIN categorias c ON c.id = p.categoria_id
            LEFT JOIN marcas m ON m.id = p.marca_id
            WHERE p.ativo = TRUE
        ";
        $params = [];

        if (!empty($filtros["categoria"])) {
            $sql .= " AND c.slug = ?";
            $params[] = $filtros["categoria"];
        }

        if (!empty($filtros["marca"])) {
            $sql .= " AND m.slug = ?";
            $params[] = $filtros["marca"];
        }

        if (!empty($filtros["busca"])) {
            $termo = "%" . $filtros["busca"] . "%";
            $sql .= " AND (p.nome ILIKE ? OR p.descricao ILIKE ? OR p.palavras_chave_principais ILIKE ? OR p.palavras_chave_secundarias ILIKE ?)";
            $params[] = $termo;
            $params[] = $termo;
            $params[] = $termo;
            $params[] = $termo;
        }

        if (!empty($filtros["preco_min"])) {
            $sql .= " AND p.preco >= ?";
            $params[] = $filtros["preco_min"];
        }

        if (!empty($filtros["preco_max"])) {
            $sql .= " AND p.preco <= ?";
            $params[] = $filtros["preco_max"];
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    public static function buscarPorSlug(string $slug): ?array
    {
        global $pdo;

        $stmt = $pdo->prepare("
            SELECT p.*, c.nome AS categoria_nome, c.slug AS categoria_slug,
                   m.nome AS marca_nome, m.slug AS marca_slug
            FROM produtos p
            LEFT JOIN categorias c ON c.id = p.categoria_id
            LEFT JOIN marcas m ON m.id = p.marca_id
            WHERE p.slug = ? AND p.ativo = TRUE
        ");
        $stmt->execute([$slug]);

        return $stmt->fetch() ?: null;
    }

    public static function buscarPorId(int $id): ?array
    {
        global $pdo;

        $stmt = $pdo->prepare("
            SELECT p.*, c.nome AS categoria_nome, c.slug AS categoria_slug,
                   m.nome AS marca_nome, m.slug AS marca_slug
            FROM produtos p
            LEFT JOIN categorias c ON c.id = p.categoria_id
            LEFT JOIN marcas m ON m.id = p.marca_id
            WHERE p.id = ?
        ");
        $stmt->execute([$id]);

        $produto = $stmt->fetch() ?: null;

        // Garante que peso e dimensões estejam sempre presentes no retorno
        // (preenche o formulário de edição mesmo se a coluna ainda não existir)
        if ($produto) {
            $produto["peso_gramas"] = (int) ($produto["peso_gramas"] ?? 300);
            $produto["comprimento_cm"] = (int) ($produto["comprimento_cm"] ?? 16);
            $produto["largura_cm"] = (int) ($produto["largura_cm"] ?? 11);
            $produto["altura_cm"] = (int) ($produto["altura_cm"] ?? 2);
        }

        return $produto;
    }

    public static function variacoes(int $produtoId): array
    {
        global $pdo;

        $stmt = $pdo->prepare("
            SELECT * FROM produto_variacoes WHERE produto_id = ? ORDER BY tamanho, cor
        ");
        $stmt->execute([$produtoId]);

        return $stmt->fetchAll();
    }

    public static function imagens(int $produtoId): array
    {
        global $pdo;

        $stmt = $pdo->prepare("SELECT * FROM produto_imagens WHERE produto_id = ? ORDER BY ordem ASC, principal DESC, id ASC");
        $stmt->execute([$produtoId]);

        return $stmt->fetchAll();
    }

    public static function estoqueDisponivel(int $produtoId, ?int $variacaoId, int $quantidade): bool
    {
        global $pdo;

        if ($variacaoId) {
            $stmt = $pdo->prepare("SELECT estoque FROM produto_variacoes WHERE id = ? AND produto_id = ?");
            $stmt->execute([$variacaoId, $produtoId]);
            $estoque = (int) ($stmt->fetchColumn() ?: 0);
        } else {
            $stmt = $pdo->prepare("SELECT estoque FROM produtos WHERE id = ?");
            $stmt->execute([$produtoId]);
            $estoque = (int) ($stmt->fetchColumn() ?: 0);
        }

        return $estoque >= $quantidade;
    }

    /**
     * Decrementa o estoque de forma atômica com condição de estoque suficiente.
     * Retorna true se o estoque foi decrementado, false se não havia estoque suficiente.
     * Usado para tratar concorrência (dois usuários comprando o mesmo produto ao mesmo tempo).
     */
    public static function decrementarEstoque(int $produtoId, ?int $variacaoId, int $quantidade): bool
    {
        global $pdo;

        if ($variacaoId) {
            $stmt = $pdo->prepare("
                UPDATE produto_variacoes
                SET estoque = estoque - ?
                WHERE id = ? AND produto_id = ? AND estoque >= ?
            ");
            $stmt->execute([$quantidade, $variacaoId, $produtoId, $quantidade]);
            $variacaoOk = $stmt->rowCount() > 0;

            if (!$variacaoOk) {
                return false;
            }

            // Também decrementa o estoque do produto pai
            $stmt = $pdo->prepare("
                UPDATE produtos
                SET estoque = estoque - ?, updated_at = NOW()
                WHERE id = ? AND estoque >= ?
            ");
            $stmt->execute([$quantidade, $produtoId, $quantidade]);

            return $stmt->rowCount() > 0;
        }

        $stmt = $pdo->prepare("
            UPDATE produtos
            SET estoque = estoque - ?, updated_at = NOW()
            WHERE id = ? AND estoque >= ?
        ");
        $stmt->execute([$quantidade, $produtoId, $quantidade]);

        return $stmt->rowCount() > 0;
    }

    public static function categorias(): array
    {
        global $pdo;

        return $pdo->query("SELECT * FROM categorias WHERE ativo = TRUE ORDER BY nome")->fetchAll();
    }

    public static function marcas(): array
    {
        global $pdo;

        return $pdo->query("SELECT * FROM marcas WHERE ativo = TRUE ORDER BY nome")->fetchAll();
    }

    public static function criar(array $dados): int
    {
        global $pdo;

        $stmt = $pdo->prepare("
            INSERT INTO produtos (
                categoria_id, marca_id, nome, slug, descricao, descricao_curta,
                preco, estoque, ativo, ean, sku, ncm,
                peso_gramas, comprimento_cm, largura_cm, altura_cm,
                meta_titulo, meta_descricao,
                palavras_chave_principais, palavras_chave_secundarias
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            RETURNING id
        ");
        $stmt->execute([
            $dados["categoria_id"] ?? null,
            $dados["marca_id"] ?? null,
            $dados["nome"],
            $dados["slug"] ?? slugify($dados["nome"]),
            $dados["descricao"] ?? null,
            $dados["descricao_curta"] ?? null,
            $dados["preco"],
            $dados["estoque"] ?? 0,
            $dados["ativo"] ? 1 : 0,
            $dados["ean"] ?? null,
            $dados["sku"] ?? null,
            $dados["ncm"] ?? null,
            $dados["peso_gramas"] ?? 300,
            $dados["comprimento_cm"] ?? 16,
            $dados["largura_cm"] ?? 11,
            $dados["altura_cm"] ?? 2,
            $dados["meta_titulo"] ?? null,
            $dados["meta_descricao"] ?? null,
            $dados["palavras_chave_principais"] ?? null,
            $dados["palavras_chave_secundarias"] ?? null,
        ]);

        return (int) $stmt->fetchColumn();
    }

    public static function atualizar(int $id, array $dados): void
    {
        global $pdo;

        $stmt = $pdo->prepare("
            UPDATE produtos
            SET categoria_id = ?,
                marca_id = ?,
                nome = ?,
                slug = ?,
                descricao = ?,
                descricao_curta = ?,
                preco = ?,
                estoque = ?,
                ativo = ?,
                ean = ?,
                sku = ?,
                ncm = ?,
                peso_gramas = ?,
                comprimento_cm = ?,
                largura_cm = ?,
                altura_cm = ?,
                meta_titulo = ?,
                meta_descricao = ?,
                palavras_chave_principais = ?,
                palavras_chave_secundarias = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            $dados["categoria_id"] ?? null,
            $dados["marca_id"] ?? null,
            $dados["nome"],
            $dados["slug"] ?? slugify($dados["nome"]),
            $dados["descricao"] ?? null,
            $dados["descricao_curta"] ?? null,
            $dados["preco"],
            $dados["estoque"] ?? 0,
            $dados["ativo"] ? 1 : 0,
            $dados["ean"] ?? null,
            $dados["sku"] ?? null,
            $dados["ncm"] ?? null,
            $dados["peso_gramas"] ?? 300,
            $dados["comprimento_cm"] ?? 16,
            $dados["largura_cm"] ?? 11,
            $dados["altura_cm"] ?? 2,
            $dados["meta_titulo"] ?? null,
            $dados["meta_descricao"] ?? null,
            $dados["palavras_chave_principais"] ?? null,
            $dados["palavras_chave_secundarias"] ?? null,
            $id,
        ]);
    }

    public static function todosAdmin(int $page = 1, int $limit = 20): array
    {
        global $pdo;

        $page = max(1, $page);
        $limit = max(1, min(100, $limit));
        $offset = ($page - 1) * $limit;

        $stmt = $pdo->prepare("
            SELECT p.*, c.nome AS categoria_nome, m.nome AS marca_nome
            FROM produtos p
            LEFT JOIN categorias c ON c.id = p.categoria_id
            LEFT JOIN marcas m ON m.id = p.marca_id
            ORDER BY p.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$limit, $offset]);

        return $stmt->fetchAll();
    }

    public static function contarTodosAdmin(): int
    {
        global $pdo;

        return (int) $pdo->query("SELECT COUNT(*) FROM produtos")->fetchColumn();
    }

    /* ============================================================
       MÉTODOS DO PAINEL ADMIN — SEO, FAQ, Tickets
       ============================================================ */

    /**
     * Busca FAQs de um produto
     */
    public static function faqs(int $produtoId): array
    {
        global $pdo;

        $stmt = $pdo->prepare("
            SELECT * FROM produto_faqs
            WHERE produto_id = ? AND ativo = TRUE
            ORDER BY ordem ASC, id ASC
        ");
        $stmt->execute([$produtoId]);

        return $stmt->fetchAll();
    }

    /**
     * Adiciona uma FAQ ao produto
     */
    public static function adicionarFaq(int $produtoId, string $pergunta, string $resposta): int
    {
        global $pdo;

        $stmt = $pdo->prepare("
            INSERT INTO produto_faqs (produto_id, pergunta, resposta, ordem)
            VALUES (?, ?, ?, COALESCE((SELECT MAX(ordem) + 1 FROM produto_faqs WHERE produto_id = ?), 1))
            RETURNING id
        ");
        $stmt->execute([$produtoId, $pergunta, $resposta, $produtoId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Remove uma FAQ
     */
    public static function removerFaq(int $faqId): void
    {
        global $pdo;

        $stmt = $pdo->prepare("DELETE FROM produto_faqs WHERE id = ?");
        $stmt->execute([$faqId]);
    }

    /**
     * Busca tickets de atendimento de um produto
     */
    public static function tickets(int $produtoId): array
    {
        global $pdo;

        $stmt = $pdo->prepare("
            SELECT DISTINCT t.*, u.nome AS cliente_nome, u.email AS cliente_email
            FROM tickets t
            INNER JOIN pedido_itens pi ON pi.pedido_id = t.pedido_id
            LEFT JOIN usuarios u ON u.id = t.usuario_id
            WHERE pi.produto_id = ?
            ORDER BY t.created_at DESC
        ");
        $stmt->execute([$produtoId]);

        return $stmt->fetchAll();
    }

    /**
     * Busca mensagens de um ticket
     */
    public static function ticketMensagens(int $ticketId): array
    {
        global $pdo;

        $stmt = $pdo->prepare("
            SELECT tm.*, u.nome AS autor_nome
            FROM ticket_mensagens tm
            LEFT JOIN usuarios u ON u.id = tm.usuario_id
            WHERE tm.ticket_id = ?
            ORDER BY tm.created_at ASC
        ");
        $stmt->execute([$ticketId]);

        return $stmt->fetchAll();
    }
    public static function adicionarMensagemTicket(int $ticketId, ?int $usuarioId, string $mensagem, string $origem = "ADMIN"): void
    {
        global $pdo;

        $stmt = $pdo->prepare("
            INSERT INTO ticket_mensagens (ticket_id, usuario_id, mensagem, origem)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$ticketId, $usuarioId, $mensagem, $origem]);

        // Atualiza status do ticket para EM_ANDAMENTO se estava ABERTO
        $pdo->prepare("
            UPDATE tickets
            SET status = CASE WHEN status = 'ABERTO' THEN 'EM_ANDAMENTO' ELSE status END,
                updated_at = NOW()
            WHERE id = ?
        ")->execute([$ticketId]);
    }

    /**
     * Atualiza status de um ticket
     */
    public static function atualizarStatusTicket(int $ticketId, string $status): void
    {
        global $pdo;

        $stmt = $pdo->prepare("
            UPDATE tickets SET status = ?, updated_at = NOW() WHERE id = ?
        ");
        $stmt->execute([$status, $ticketId]);
    }

    /**
     * Verifica se SKU já existe (para validação de unicidade)
     */
    public static function skuExiste(string $sku, ?int $ignorarId = null): bool
    {
        global $pdo;

        $sql = "SELECT 1 FROM produtos WHERE sku = ?";
        $params = [$sku];

        if ($ignorarId) {
            $sql .= " AND id != ?";
            $params[] = $ignorarId;
        }

        $sql .= " LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * Verifica se EAN já existe (para validação de unicidade)
     */
    public static function eanExiste(string $ean, ?int $ignorarId = null): bool
    {
        global $pdo;

        $sql = "SELECT 1 FROM produtos WHERE ean = ?";
        $params = [$ean];

        if ($ignorarId) {
            $sql .= " AND id != ?";
            $params[] = $ignorarId;
        }

        $sql .= " LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * Verifica se slug já existe (para validação de unicidade)
     */
    public static function slugExiste(string $slug, ?int $ignorarId = null): bool
    {
        global $pdo;

        $sql = "SELECT 1 FROM produtos WHERE slug = ?";
        $params = [$slug];

        if ($ignorarId) {
            $sql .= " AND id != ?";
            $params[] = $ignorarId;
        }

        $sql .= " LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return (bool) $stmt->fetchColumn();
    }
}