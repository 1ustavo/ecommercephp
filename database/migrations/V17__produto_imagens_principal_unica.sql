-- ============================================================
-- V17: PRODUTO IMAGENS — Garante exatamente UMA imagem principal
-- por produto (partial unique index)
-- ============================================================

-- Garante que cada produto tenha no máximo UMA imagem marcada como principal.
-- Usa partial index (WHERE principal = TRUE) para permitir múltiplas linhas
-- com principal = FALSE, mas apenas uma com principal = TRUE por produto.
CREATE UNIQUE INDEX IF NOT EXISTS uq_produto_imagens_principal
    ON produto_imagens(produto_id)
    WHERE principal = TRUE;