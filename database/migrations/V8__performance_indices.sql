-- ============================================================
-- V8: PERFORMANCE INDICES — Índices adicionais para melhorar
-- performance de consultas frequentes no admin e no catálogo.
-- ============================================================

-- Pedidos: busca por usuário + status (comum em listagens)
CREATE INDEX IF NOT EXISTS idx_pedidos_usuario_status
    ON pedidos(usuario_id, status);

-- Pedidos: filtros por data + status (relatórios, fila)
CREATE INDEX IF NOT EXISTS idx_pedidos_created_status
    ON pedidos(created_at, status);

-- Pedido itens: busca por pedido (join frequente)
CREATE INDEX IF NOT EXISTS idx_pedido_itens_pedido
    ON pedido_itens(pedido_id);

-- Produtos: filtros de catálogo (ativo + categoria)
CREATE INDEX IF NOT EXISTS idx_produtos_ativo_categoria
    ON produtos(ativo, categoria_id);

-- Produtos: busca por slug (URL amigável)
CREATE INDEX IF NOT EXISTS idx_produtos_slug
    ON produtos(slug);

-- Carrinho itens: busca por carrinho (join frequente)
CREATE INDEX IF NOT EXISTS idx_carrinho_itens_carrinho
    ON carrinho_itens(carrinho_id);