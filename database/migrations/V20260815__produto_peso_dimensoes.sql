-- ============================================================
-- V20260815: PRODUTO PESO E DIMENSÕES — Colunas de peso e
-- dimensões (cm) para cálculo de frete e ficha técnica
-- ============================================================

-- Peso em gramas (default 300g) e dimensões em centímetros.
-- Para produtos já cadastrados, o DEFAULT cobre a migração.
ALTER TABLE produtos
    ADD COLUMN IF NOT EXISTS peso_gramas INTEGER NOT NULL DEFAULT 300,
    ADD COLUMN IF NOT EXISTS comprimento_cm INTEGER NOT NULL DEFAULT 16,
    ADD COLUMN IF NOT EXISTS largura_cm INTEGER NOT NULL DEFAULT 11,
    ADD COLUMN IF NOT EXISTS altura_cm INTEGER NOT NULL DEFAULT 2;