-- Migration: fluxo de reembolso de pedidos
-- Ciclo de vida: SOLICITADO (cliente) -> APROVADO | RECUSADO (ADMIN/GERENTE)
CREATE TABLE IF NOT EXISTS reembolsos (
    id BIGSERIAL PRIMARY KEY,
    pedido_id BIGINT NOT NULL REFERENCES pedidos(id) ON DELETE CASCADE,
    usuario_id BIGINT NOT NULL REFERENCES usuarios(id),
    motivo TEXT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'SOLICITADO',
    motivo_recusa TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMP NOT NULL DEFAULT NOW(),
    CONSTRAINT chk_reembolsos_status CHECK (status IN ('SOLICITADO', 'APROVADO', 'RECUSADO'))
);

CREATE INDEX IF NOT EXISTS idx_reembolsos_status ON reembolsos(status);
CREATE INDEX IF NOT EXISTS idx_reembolsos_usuario ON reembolsos(usuario_id);

-- Apenas UMA solicitação em aberto por pedido (permite re-solicitar
-- depois de recusado; a checagem de "já APROVADO" fica no modelo).
CREATE UNIQUE INDEX IF NOT EXISTS uq_reembolsos_pedido_aberto
    ON reembolsos(pedido_id) WHERE status = 'SOLICITADO';
