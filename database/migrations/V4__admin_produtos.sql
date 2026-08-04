-- ============================================================
-- V4: ADMIN PRODUTOS — Tabelas e colunas para o painel
-- de gestão de produtos (SEO, identificação, FAQ, tickets)
-- ============================================================

-- ------------------------------------------------------------
-- 1. NOVAS COLUNAS NA TABELA produtos
-- ------------------------------------------------------------
ALTER TABLE produtos
    ADD COLUMN IF NOT EXISTS descricao_curta VARCHAR(200),
    ADD COLUMN IF NOT EXISTS ean VARCHAR(14),
    ADD COLUMN IF NOT EXISTS sku VARCHAR(50),
    ADD COLUMN IF NOT EXISTS ncm VARCHAR(10),
    ADD COLUMN IF NOT EXISTS meta_titulo VARCHAR(70),
    ADD COLUMN IF NOT EXISTS meta_descricao VARCHAR(200),
    ADD COLUMN IF NOT EXISTS palavras_chave_principais TEXT,
    ADD COLUMN IF NOT EXISTS palavras_chave_secundarias TEXT;

-- Índices para busca por SKU e EAN
CREATE INDEX IF NOT EXISTS idx_produtos_sku ON produtos(sku);
CREATE INDEX IF NOT EXISTS idx_produtos_ean ON produtos(ean);

-- ------------------------------------------------------------
-- 2. TABELA: produto_faqs — Perguntas frequentes do produto
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS produto_faqs (
    id BIGSERIAL PRIMARY KEY,
    produto_id BIGINT NOT NULL REFERENCES produtos(id) ON DELETE CASCADE,
    pergunta VARCHAR(300) NOT NULL,
    resposta TEXT NOT NULL,
    ordem INT NOT NULL DEFAULT 0,
    ativo BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_produto_faqs_produto ON produto_faqs(produto_id);

-- ------------------------------------------------------------
-- 3. TABELA: tickets — Atendimentos vinculados ao produto
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tickets (
    id BIGSERIAL PRIMARY KEY,
    produto_id BIGINT REFERENCES produtos(id) ON DELETE SET NULL,
    usuario_id BIGINT REFERENCES usuarios(id) ON DELETE SET NULL,
    assunto VARCHAR(200) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'ABERTO',
    prioridade VARCHAR(20) NOT NULL DEFAULT 'NORMAL',
    created_at TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMP NOT NULL DEFAULT NOW()
);

-- Status: ABERTO, EM_ANDAMENTO, RESOLVIDO, FECHADO
-- Prioridade: BAIXA, NORMAL, ALTA, URGENTE

CREATE INDEX IF NOT EXISTS idx_tickets_produto ON tickets(produto_id);
CREATE INDEX IF NOT EXISTS idx_tickets_usuario ON tickets(usuario_id);
CREATE INDEX IF NOT EXISTS idx_tickets_status ON tickets(status);

-- ------------------------------------------------------------
-- 4. TABELA: ticket_mensagens — Histórico de mensagens
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS ticket_mensagens (
    id BIGSERIAL PRIMARY KEY,
    ticket_id BIGINT NOT NULL REFERENCES tickets(id) ON DELETE CASCADE,
    usuario_id BIGINT REFERENCES usuarios(id) ON DELETE SET NULL,
    mensagem TEXT NOT NULL,
    origem VARCHAR(20) NOT NULL DEFAULT 'CLIENTE',
    created_at TIMESTAMP NOT NULL DEFAULT NOW()
);

-- Origem: CLIENTE, ADMIN, SISTEMA

CREATE INDEX IF NOT EXISTS idx_ticket_mensagens_ticket ON ticket_mensagens(ticket_id);

-- ------------------------------------------------------------
-- 5. SEED: Dados de exemplo para demonstração
-- ------------------------------------------------------------

-- FAQ de exemplo para o primeiro produto (se existir)
INSERT INTO produto_faqs (produto_id, pergunta, resposta, ordem)
SELECT id, 'Qual o prazo de entrega?',
       'O prazo de entrega varia de 3 a 7 dias úteis, dependendo da região. Para capitais, o prazo médio é de 3 dias úteis.',
       1
FROM produtos
WHERE id = (SELECT MIN(id) FROM produtos)
  AND NOT EXISTS (SELECT 1 FROM produto_faqs WHERE produto_id = produtos.id);

INSERT INTO produto_faqs (produto_id, pergunta, resposta, ordem)
SELECT id, 'Este produto é sustentável?',
       'Sim! Utilizamos algodão orgânico certificado e corantes naturais em toda a nossa linha de produtos.',
       2
FROM produtos
WHERE id = (SELECT MIN(id) FROM produtos)
  AND NOT EXISTS (SELECT 1 FROM produto_faqs WHERE produto_id = produtos.id AND pergunta = 'Este produto é sustentável?');

INSERT INTO produto_faqs (produto_id, pergunta, resposta, ordem)
SELECT id, 'Como faço para trocar?',
       'Você tem até 30 dias após o recebimento para solicitar a troca. Basta entrar em contato com nosso suporte.',
       3
FROM produtos
WHERE id = (SELECT MIN(id) FROM produtos)
  AND NOT EXISTS (SELECT 1 FROM produto_faqs WHERE produto_id = produtos.id AND pergunta = 'Como faço para trocar?');

-- Tickets de exemplo para o primeiro produto
INSERT INTO tickets (produto_id, usuario_id, assunto, status, prioridade)
SELECT p.id, u.id, 'Produto chegou com defeito', 'ABERTO', 'ALTA'
FROM produtos p
CROSS JOIN usuarios u
WHERE p.id = (SELECT MIN(id) FROM produtos)
  AND u.email = 'cliente@demo.com'
  AND NOT EXISTS (SELECT 1 FROM tickets WHERE produto_id = p.id AND assunto = 'Produto chegou com defeito');

INSERT INTO tickets (produto_id, usuario_id, assunto, status, prioridade)
SELECT p.id, u.id, 'Dúvida sobre tamanho', 'EM_ANDAMENTO', 'NORMAL'
FROM produtos p
CROSS JOIN usuarios u
WHERE p.id = (SELECT MIN(id) FROM produtos)
  AND u.email = 'cliente@demo.com'
  AND NOT EXISTS (SELECT 1 FROM tickets WHERE produto_id = p.id AND assunto = 'Dúvida sobre tamanho');

INSERT INTO tickets (produto_id, usuario_id, assunto, status, prioridade)
SELECT p.id, u.id, 'Troca de produto', 'RESOLVIDO', 'NORMAL'
FROM produtos p
CROSS JOIN usuarios u
WHERE p.id = (SELECT MIN(id) FROM produtos)
  AND u.email = 'cliente@demo.com'
  AND NOT EXISTS (SELECT 1 FROM tickets WHERE produto_id = p.id AND assunto = 'Troca de produto');

-- Mensagens de exemplo para o primeiro ticket
INSERT INTO ticket_mensagens (ticket_id, usuario_id, mensagem, origem)
SELECT t.id, u.id,
       'Olá! Recebi o produto hoje, mas ele veio com um pequeno defeito na costura. Gostaria de saber como proceder.',
       'CLIENTE'
FROM tickets t
CROSS JOIN usuarios u
WHERE t.assunto = 'Produto chegou com defeito'
  AND u.email = 'cliente@demo.com'
  AND NOT EXISTS (SELECT 1 FROM ticket_mensagens WHERE ticket_id = t.id);

INSERT INTO ticket_mensagens (ticket_id, usuario_id, mensagem, origem)
SELECT t.id, NULL,
       'Olá! Sentimos muito pelo ocorrido. Vamos abrir um processo de troca imediatamente. Você receberá um código de postagem reversa por e-mail.',
       'ADMIN'
FROM tickets t
WHERE t.assunto = 'Produto chegou com defeito'
  AND NOT EXISTS (SELECT 1 FROM ticket_mensagens WHERE ticket_id = t.id AND origem = 'ADMIN');

INSERT INTO ticket_mensagens (ticket_id, usuario_id, mensagem, origem)
SELECT t.id, u.id,
       'Perfeito! Obrigada pela agilidade. Vou aguardar o e-mail com o código.',
       'CLIENTE'
FROM tickets t
CROSS JOIN usuarios u
WHERE t.assunto = 'Produto chegou com defeito'
  AND u.email = 'cliente@demo.com'
  AND NOT EXISTS (SELECT 1 FROM ticket_mensagens WHERE ticket_id = t.id AND mensagem LIKE 'Perfeito!%');