-- ============================================================
-- V10: CUPONS — Tabela de cupons de desconto para o checkout.
-- ============================================================

CREATE TABLE cupons (
    id BIGSERIAL PRIMARY KEY,
    codigo VARCHAR(50) NOT NULL UNIQUE,
    tipo VARCHAR(20) NOT NULL,
    valor DECIMAL(10,2) NOT NULL,
    valor_minimo_pedido DECIMAL(10,2) DEFAULT 0,
    quantidade_maxima INT DEFAULT 1,
    quantidade_usada INT DEFAULT 0,
    data_inicio TIMESTAMP NOT NULL DEFAULT NOW(),
    data_fim TIMESTAMP NOT NULL,
    ativo BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_cupons_codigo ON cupons(codigo);
CREATE INDEX idx_cupons_ativo ON cupons(ativo);
