-- ============================================================
-- V16: PRODUTO IMAGENS — Coluna "ordem" para suportar
-- múltiplas imagens com ordenação determinística (carrossel)
-- ============================================================

-- Adiciona coluna ordem (inteiro, por produto)
ALTER TABLE produto_imagens
    ADD COLUMN IF NOT EXISTS ordem INT NOT NULL DEFAULT 0;

-- Índice composto para buscar imagens de um produto em ordem
CREATE INDEX IF NOT EXISTS idx_produto_imagens_produto_ordem
    ON produto_imagens(produto_id, ordem);

-- Popula a ordem para registros existentes, preservando a
-- "principal" primeiro e depois o id de criação
UPDATE produto_imagens pi
SET ordem = sub.novo_ordem
FROM (
    SELECT id,
           ROW_NUMBER() OVER (
               PARTITION BY produto_id
               ORDER BY principal DESC, id ASC
           ) - 1 AS novo_ordem
    FROM produto_imagens
) sub
WHERE pi.id = sub.id;