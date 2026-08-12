-- ============================================================
-- V14: FIX SCHEMA - Altera tickets de produto_id para pedido_id
-- ============================================================

-- Adiciona coluna pedido_id
ALTER TABLE tickets ADD COLUMN pedido_id INT;

-- Remove coluna produto_id (os dados antigos serão perdidos)
ALTER TABLE tickets DROP COLUMN produto_id;

-- Adiciona colunas que faltam do schema V13
ALTER TABLE tickets ADD COLUMN categoria VARCHAR(50) NOT NULL DEFAULT 'GERAL' 
    CHECK (categoria IN ('GERAL', 'PRODUTO', 'ENTREGA', 'PAGAMENTO', 'TROCA', 'DEVOLUCAO', 'OUTRO'));
ALTER TABLE tickets ADD COLUMN avaliacao INT CHECK (avaliacao BETWEEN 1 AND 5);
ALTER TABLE tickets ADD COLUMN comentario_avaliacao TEXT;
ALTER TABLE tickets ADD COLUMN data_avaliacao TIMESTAMP;
ALTER TABLE tickets ADD COLUMN closed_at TIMESTAMP;

-- Atualiza valores de prioridade antigos (NORMAL -> MEDIA)
UPDATE tickets SET prioridade = 'MEDIA' WHERE prioridade = 'NORMAL';

-- Adiciona foreign key para pedidos
ALTER TABLE tickets ADD CONSTRAINT fk_tickets_pedido 
    FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE;

-- Recria índices
DROP INDEX IF EXISTS idx_tickets_produto;
CREATE INDEX idx_tickets_pedido ON tickets(pedido_id);
CREATE INDEX idx_tickets_usuario ON tickets(usuario_id);
CREATE INDEX idx_tickets_status ON tickets(status);
CREATE INDEX idx_tickets_created ON tickets(created_at DESC);
