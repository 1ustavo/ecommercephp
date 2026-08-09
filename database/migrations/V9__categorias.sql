-- ============================================================
-- V9: CATEGORIAS — Tabela de categorias e subcategorias para
-- organizar o catálogo de produtos.
-- ============================================================

CREATE TABLE IF NOT EXISTS categorias (
    id BIGSERIAL PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    descricao TEXT,
    pai_id BIGINT,
    ativo BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_categorias_slug ON categorias(slug);
CREATE INDEX IF NOT EXISTS idx_categorias_pai ON categorias(pai_id);
CREATE INDEX IF NOT EXISTS idx_categorias_ativo ON categorias(ativo);

-- FK autorreferenciada removida temporariamente para evitar erro
-- de coluna inexistente no PostgreSQL. Pode ser adicionada depois
-- com: ALTER TABLE categorias ADD CONSTRAINT fk_categorias_pai FOREIGN KEY (pai_id) REFERENCES categorias(id) ON DELETE SET NULL;

-- Backfill: cria categorias padrão se não houver nenhuma
INSERT INTO categorias (nome, slug, descricao, ativo)
SELECT 'Camisetas', 'camisetas', 'Camisetas em geral', TRUE
WHERE NOT EXISTS (SELECT 1 FROM categorias);

INSERT INTO categorias (nome, slug, descricao, ativo)
SELECT 'Calças', 'calcas', 'Calças em geral', TRUE
WHERE NOT EXISTS (SELECT 1 FROM categorias WHERE slug = 'calcas');

INSERT INTO categorias (nome, slug, descricao, ativo)
SELECT 'Vestidos', 'vestidos', 'Vestidos em geral', TRUE
WHERE NOT EXISTS (SELECT 1 FROM categorias WHERE slug = 'vestidos');

INSERT INTO categorias (nome, slug, descricao, ativo)
SELECT 'Jaquetas', 'jaquetas', 'Jaquetas em geral', TRUE
WHERE NOT EXISTS (SELECT 1 FROM categorias WHERE slug = 'jaquetas');