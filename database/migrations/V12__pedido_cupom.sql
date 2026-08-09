-- ============================================================
-- V12: PEDIDO CUPOM — Adiciona campo para registrar qual cupom
-- foi usado no pedido.
-- ============================================================

ALTER TABLE pedidos
    ADD COLUMN cupom_id BIGINT;

CREATE INDEX idx_pedidos_cupom ON pedidos(cupom_id);
