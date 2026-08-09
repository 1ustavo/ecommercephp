-- ============================================================
-- V6: PEDIDO APROVACAO — Fluxo de aprovacao manual de pedidos
-- pelo admin (estilo Amazon seller). Adiciona campos para
-- registrar a decisao do admin, o motivo da recusa e quem/quando
-- aprovou. Nao cria tabela nova: a decisao e unica por pedido.
-- ============================================================

-- ------------------------------------------------------------
-- 1. NOVOS CAMPOS NA TABELA pedidos
-- ------------------------------------------------------------
ALTER TABLE pedidos
    ADD COLUMN IF NOT EXISTS motivo_recusa   TEXT,
    ADD COLUMN IF NOT EXISTS aprovado_por    BIGINT REFERENCES usuarios(id) ON DELETE SET NULL,
    ADD COLUMN IF NOT EXISTS aprovado_em     TIMESTAMP,
    ADD COLUMN IF NOT EXISTS decisao_admin   VARCHAR(20);
-- decisao_admin: 'APROVADO' | 'RECUSADO' | NULL (antes da decisao)

-- Indice para listar pedidos aguardando aprovacao rapidamente
CREATE INDEX IF NOT EXISTS idx_pedidos_aprovacao
    ON pedidos(status, created_at)
    WHERE status = 'AGUARDANDO_APROVACAO';

-- ------------------------------------------------------------
-- 2. MIGRACAO DE DADOS EXISTENTES
-- ------------------------------------------------------------
-- Pedidos ja existentes com status PENDENTE sao considerados
-- "ja aprovados" (fluxo antigo), entao marcamos decisao_admin
-- para nao confundir com o novo fluxo. Nao alteramos o status
-- deles para nao quebrar pedidos em andamento.
UPDATE pedidos
SET decisao_admin = 'APROVADO'
WHERE status IN ('PENDENTE', 'PAGO', 'ENVIADO', 'ENTREGUE')
  AND decisao_admin IS NULL;