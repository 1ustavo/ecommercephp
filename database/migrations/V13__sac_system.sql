-- ============================================================
-- V13: SISTEMA DE SAC (SERVIÇO DE ATENDIMENTO AO CLIENTE)
-- ============================================================

-- Tabela principal de tickets/chamados
CREATE TABLE tickets (
    id SERIAL PRIMARY KEY,
    pedido_id INT NOT NULL,
    usuario_id INT NOT NULL,
    assunto VARCHAR(200) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'ABERTO' 
        CHECK (status IN ('ABERTO', 'EM_ANDAMENTO', 'RESOLVIDO', 'FECHADO')),
    prioridade VARCHAR(20) NOT NULL DEFAULT 'MEDIA' 
        CHECK (prioridade IN ('BAIXA', 'MEDIA', 'ALTA', 'URGENTE')),
    categoria VARCHAR(50) NOT NULL DEFAULT 'GERAL' 
        CHECK (categoria IN ('GERAL', 'PRODUTO', 'ENTREGA', 'PAGAMENTO', 'TROCA', 'DEVOLUCAO', 'OUTRO')),
    avaliacao INT CHECK (avaliacao BETWEEN 1 AND 5),
    comentario_avaliacao TEXT,
    data_avaliacao TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    closed_at TIMESTAMP
);

-- Tabela de mensagens dos tickets
CREATE TABLE ticket_mensagens (
    id SERIAL PRIMARY KEY,
    ticket_id INT NOT NULL,
    usuario_id INT NOT NULL,
    origem VARCHAR(10) NOT NULL CHECK (origem IN ('CLIENTE', 'ADMIN')),
    mensagem TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Índices para performance
CREATE INDEX idx_tickets_usuario ON tickets(usuario_id);
CREATE INDEX idx_tickets_pedido ON tickets(pedido_id);
CREATE INDEX idx_tickets_status ON tickets(status);
CREATE INDEX idx_tickets_created ON tickets(created_at DESC);
CREATE INDEX idx_ticket_mensagens_ticket ON ticket_mensagens(ticket_id);
CREATE INDEX idx_ticket_mensagens_created ON ticket_mensagens(created_at);

-- Foreign Keys
ALTER TABLE tickets ADD CONSTRAINT fk_tickets_pedido 
    FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE;
    
ALTER TABLE tickets ADD CONSTRAINT fk_tickets_usuario 
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE;
    
ALTER TABLE ticket_mensagens ADD CONSTRAINT fk_ticket_mensagens_ticket 
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE;
    
ALTER TABLE ticket_mensagens ADD CONSTRAINT fk_ticket_mensagens_usuario 
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE;

