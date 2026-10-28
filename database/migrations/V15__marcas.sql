-- ============================================================
-- V15: MARCAS — Tabela de marcas e vínculo com produtos
-- ============================================================

-- ------------------------------------------------------------
-- 1. TABELA: marcas
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS marcas (
    id BIGSERIAL PRIMARY KEY,
    nome VARCHAR(100) NOT NULL UNIQUE,
    slug VARCHAR(100) NOT NULL UNIQUE,
    ativo BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NOT NULL DEFAULT NOW()
);

-- ------------------------------------------------------------
-- 2. COLUNA marca_id NA TABELA produtos
-- ------------------------------------------------------------
ALTER TABLE produtos
    ADD COLUMN IF NOT EXISTS marca_id BIGINT REFERENCES marcas(id);

CREATE INDEX IF NOT EXISTS idx_produtos_marca ON produtos(marca_id);

-- ------------------------------------------------------------
-- 3. SEED: Marcas de eletrônicos e roupas
-- ------------------------------------------------------------
INSERT INTO marcas (nome, slug) VALUES
    ('Apple', 'apple'),
    ('Samsung', 'samsung'),
    ('Motorola', 'motorola'),
    ('Xiaomi', 'xiaomi'),
    ('LG', 'lg'),
    ('Sony', 'sony'),
    ('Nike', 'nike'),
    ('Adidas', 'adidas'),
    ('Reebok', 'reebok'),
    ('Puma', 'puma'),
    ('Zara', 'zara'),
    ('H&M', 'hm'),
    ('Levis', 'levis'),
    ('Calvin Klein', 'calvin-klein'),
    ('Tommy Hilfiger', 'tommy-hilfiger'),
    ('Ralph Lauren', 'ralph-lauren'),
    ('Gucci', 'gucci'),
    ('Prada', 'prada'),
    ('Louis Vuitton', 'louis-vuitton'),
    ('Gap', 'gap'),
    ('Renner', 'renner'),
    ('C&A', 'ca'),
    ('Riachuelo', 'riachuelo'),
    ('Hering', 'hering')
ON CONFLICT (slug) DO NOTHING;