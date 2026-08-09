-- ============================================================
-- V11: FRETE E RASTREIO — Campos adicionais para integração
-- com transportadoras e rastreamento de pedidos.
-- ============================================================

ALTER TABLE pedidos
    ADD COLUMN codigo_rastreio VARCHAR(100),
    ADD COLUMN transportadora VARCHAR(100),
    ADD COLUMN prazo_entrega_dias INT;

CREATE INDEX idx_pedidos_rastreio ON pedidos(codigo_rastreio);
