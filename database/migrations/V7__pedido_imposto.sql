-- ============================================================
-- V7: PEDIDO IMPOSTO — Adiciona campo de imposto na tabela
-- pedidos para separar faturamento bruto, imposto e líquido.
-- ============================================================

ALTER TABLE pedidos
    ADD COLUMN IF NOT EXISTS imposto DECIMAL(10,2) NOT NULL DEFAULT 0;

-- Backfill: para pedidos existentes, assume imposto = 0
-- (em produção, você pode recalcular se tiver histórico de NCM/ICMS)
UPDATE pedidos
SET imposto = 0
WHERE imposto IS NULL OR imposto = 0;