-- Categorias
INSERT INTO categorias (nome, slug) VALUES
    ('Roupas', 'roupas'),
    ('Calçados', 'calcados'),
    ('Acessórios', 'acessorios'),
    ('Eletrônicos', 'eletronicos')
ON CONFLICT (slug) DO NOTHING;

-- Produtos
INSERT INTO produtos (categoria_id, nome, slug, descricao, preco, estoque) VALUES
    ((SELECT id FROM categorias WHERE slug = 'roupas'), 'Camiseta Básica Verde', 'camiseta-basica-verde', 'Camiseta 100% algodão, confortável e durável.', 59.90, 50),
    ((SELECT id FROM categorias WHERE slug = 'roupas'), 'Calça Jeans Slim', 'calca-jeans-slim', 'Jeans slim fit com elastano para maior conforto.', 149.90, 30),
    ((SELECT id FROM categorias WHERE slug = 'calcados'), 'Tênis Casual', 'tenis-casual', 'Tênis casual para uso diário, solado antiderrapante.', 199.90, 25),
    ((SELECT id FROM categorias WHERE slug = 'acessorios'), 'Boné Trucker', 'bone-trucker', 'Boné trucker ajustável, estilo urbano.', 49.90, 40),
    ((SELECT id FROM categorias WHERE slug = 'eletronicos'), 'Fone Bluetooth', 'fone-bluetooth', 'Fone sem fio com cancelamento de ruído.', 299.90, 15),
    ((SELECT id FROM categorias WHERE slug = 'roupas'), 'Moletom Capuz', 'moletom-capuz', 'Moletom com capuz, ideal para dias frios.', 129.90, 20)
ON CONFLICT (slug) DO NOTHING;

-- Variacoes
INSERT INTO produto_variacoes (produto_id, tamanho, cor, estoque, preco_adicional)
SELECT p.id, v.tamanho, v.cor, v.estoque, v.preco
FROM produtos p
JOIN (VALUES
    ('camiseta-basica-verde', 'P', 'Verde', 10, 0),
    ('camiseta-basica-verde', 'M', 'Verde', 15, 0),
    ('camiseta-basica-verde', 'G', 'Verde', 12, 0),
    ('camiseta-basica-verde', 'M', 'Branco', 13, 0),
    ('calca-jeans-slim', '38', 'Azul', 8, 0),
    ('calca-jeans-slim', '40', 'Azul', 10, 0),
    ('calca-jeans-slim', '42', 'Azul', 12, 0),
    ('tenis-casual', '39', 'Preto', 5, 0),
    ('tenis-casual', '40', 'Preto', 8, 0),
    ('tenis-casual', '41', 'Branco', 7, 0),
    ('moletom-capuz', 'M', 'Cinza', 8, 0),
    ('moletom-capuz', 'G', 'Verde', 6, 0)
) AS v(slug, tamanho, cor, estoque, preco) ON p.slug = v.slug
ON CONFLICT DO NOTHING;

-- Imagens placeholder (cores via CSS)
INSERT INTO produto_imagens (produto_id, url, principal)
SELECT p.id, 'placeholder:' || p.slug, TRUE
FROM produtos p
WHERE NOT EXISTS (SELECT 1 FROM produto_imagens pi WHERE pi.produto_id = p.id);

-- Cliente demo (senha: cliente123)
INSERT INTO usuarios (nome, email, password, cargo)
VALUES (
    'Cliente Demo',
    'cliente@demo.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at.2.uheWG/igi',
    'CLIENTE'
)
ON CONFLICT (email) DO NOTHING;
