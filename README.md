# EcoShop - E-commerce PHP

Sistema de e-commerce completo desenvolvido em PHP puro com PostgreSQL, contendo loja virtual, carrinho de compras, checkout, painel administrativo, gestão de pedidos com fluxo de aprovação, cupons de desconto, auditoria, cache e fila de processamento.

## Sumário

- [Visão Geral](#visão-geral)
- [Funcionalidades](#funcionalidades)
- [Tecnologias Utilizadas](#tecnologias-utilizadas)
- [Estrutura do Projeto](#estrutura-do-projeto)
- [Requisitos](#requisitos)
- [Instalação](#instalação)
- [Configuração](#configuração)
- [Banco de Dados](#banco-de-dados)
- [Migrações](#migrações)
- [Usuários e Perfis de Acesso](#usuários-e-perfis-de-acesso)
- [Fluxo de Pedidos](#fluxo-de-pedidos)
- [Painel Administrativo](#painel-administrativo)
- [API](#api)
- [Sistema de Cache](#sistema-de-cache)
- [Fila de Processamento](#fila-de-processamento)
- [Notificações por E-mail](#notificações-por-e-mail)
- [Gateways de Pagamento](#gateways-de-pagamento)
- [Auditoria](#auditoria)
- [Segurança](#segurança)
- [Desenvolvimento](#desenvolvimento)
- [Licença](#licença)

---

## Visão Geral

O EcoShop é uma plataforma de e-commerce construída com PHP puro (sem frameworks) e PostgreSQL. O sistema oferece uma experiência completa de compra para clientes e um painel administrativo robusto para gestão de produtos, pedidos, categorias, cupons e auditoria.

O projeto segue uma arquitetura simples e organizada, com separação clara entre páginas públicas, páginas administrativas, endpoints de API, classes de modelo (src/) e configuração centralizada.

---

## Funcionalidades

### Para Clientes

- Cadastro e login de usuários
- Catálogo de produtos com busca, filtros por categoria, faixa de preço e ordenação
- Página de detalhes do produto com variações (tamanho, cor) e controle de estoque
- Carrinho de compras persistente (funciona para visitantes e usuários logados)
- Checkout em 4 etapas: endereço, frete, pagamento e confirmação
- Opções de frete: PAC, SEDEX e retirada na loja
- Cupons de desconto (percentual ou valor fixo)
- Cálculo de imposto sobre o subtotal
- Gestão de endereços de entrega no perfil
- Acompanhamento de pedidos com status em tempo real
- Notificações por e-mail sobre aprovação ou recusa de pedidos

### Para Administradores

- Dashboard com estatísticas de pedidos, faturamento e produtos
- Gestão completa de produtos com abas para dados básicos, SEO, identificação (EAN, SKU, NCM) e suporte (tickets e FAQ)
- Upload de imagens de produtos
- Gestão de categorias com suporte a subcategorias
- Gestão de cupons de desconto
- Aprovação ou recusa manual de pedidos (fluxo estilo marketplace)
- Fila de pedidos em formato kanban por status
- Relatórios de faturamento bruto, líquido e impostos
- Log de auditoria com filtros por usuário, ação, entidade e período
- Painel de fila de processamento de jobs

---

## Tecnologias Utilizadas

- **PHP 8.x** - Linguagem principal
- **PostgreSQL** - Banco de dados relacional
- **PDO** - Camada de acesso a dados
- **HTML5, CSS3, JavaScript** - Frontend
- **Stripe API** - Gateway de pagamento (opcional)
- **Mercado Pago API** - Gateway de pagamento (opcional)
- **Composer** - Gerenciador de dependências (para SDKs de pagamento)

---

## Estrutura do Projeto

```
.
├── api/                        # Endpoints de API
│   ├── admin/                  # Endpoints administrativos
│   │   ├── aprovar_pedido.php
│   │   ├── buscar_produto.php
│   │   ├── excluir_categoria.php
│   │   ├── excluir_cupom.php
│   │   ├── pedidos_pendentes.php
│   │   ├── recusar_pedido.php
│   │   ├── responder_ticket.php
│   │   ├── salvar_categoria.php
│   │   ├── salvar_cupom.php
│   │   └── salvar_produto.php
│   ├── cadastrar_cliente.php
│   ├── cadastrar_funcionario.php
│   ├── carrinho.php
│   ├── checkout.php
│   ├── login.php
│   └── logout.php
├── assets/                     # Recursos estáticos
│   ├── css/                    # Folhas de estilo
│   └── js/                     # Scripts JavaScript
├── config/                     # Configuração do sistema
│   ├── bootstrap.php           # Carregamento inicial
│   ├── database.php            # Conexão com o banco
│   └── env.php                 # Carregador de variáveis de ambiente
├── database/                   # Scripts de banco de dados
│   ├── migrate.php             # Executor de migrações
│   ├── verificar_tabelas.php   # Verificador de tabelas
│   └── migrations/             # Arquivos SQL de migração
├── includes/                   # Componentes reutilizáveis
│   ├── admin-footer.php
│   ├── admin-header.php
│   ├── footer.php
│   └── header.php
├── pages/                      # Páginas do sistema
│   ├── admin/                  # Páginas administrativas
│   │   ├── auditoria.php
│   │   ├── categorias.php
│   │   ├── cupons.php
│   │   ├── dashboard.php
│   │   ├── fila.php
│   │   ├── pedidos.php
│   │   ├── pedidos_pendentes.php
│   │   ├── produtos.php
│   │   └── relatorios.php
│   ├── cadastro.php
│   ├── carrinho.php
│   ├── checkout.php
│   ├── login.php
│   ├── loja.php
│   ├── menu.php
│   ├── pedidos.php
│   ├── perfil.php
│   └── produto.php
├── src/                        # Classes de modelo e serviços
│   ├── Auth.php                # Autenticação e sessão
│   ├── Cache.php               # Sistema de cache em arquivo
│   ├── Carrinho.php            # Gestão do carrinho
│   ├── Categoria.php           # Gestão de categorias
│   ├── Cupom.php               # Gestão de cupons
│   ├── Endereco.php            # Gestão de endereços
│   ├── GatewayPagamento.php    # Integração com gateways
│   ├── Notificacao.php         # Envio de e-mails
│   ├── Pedido.php              # Gestão de pedidos
│   ├── Produto.php             # Gestão de produtos
│   ├── Queue.php               # Fila de processamento
│   ├── Usuario.php             # Modelo de usuário
│   └── helpers.php             # Funções utilitárias
├── storage/                    # Armazenamento de arquivos
│   ├── cache/                  # Arquivos de cache
│   ├── emails.log              # Log de e-mails
│   └── queue/                  # Jobs da fila
├── uploads/                    # Arquivos enviados
│   └── produtos/               # Imagens de produtos
├── .env                        # Variáveis de ambiente (não versionado)
├── .gitignore
└── index.php                   # Ponto de entrada
```

---

## Requisitos

- PHP 8.0 ou superior
- Extensões PHP: PDO, PDO_PGSQL, mbstring, curl, fileinfo
- PostgreSQL 12 ou superior
- Composer (para instalar SDKs de pagamento, se necessário)
- Servidor web (Apache, Nginx ou servidor embutido do PHP)

---

## Instalação

### 1. Clonar o repositório

```bash
git clone https://github.com/1ustavo/ecommercephp.git
cd ecommercephp
```

### 2. Instalar dependências (opcional)

Se for utilizar os gateways de pagamento Stripe ou Mercado Pago, instale as dependências via Composer:

```bash
composer require stripe/stripe-php
```

### 3. Configurar o banco de dados

Crie um banco de dados PostgreSQL:

```sql
CREATE DATABASE ecommercephp;
```

### 4. Configurar o arquivo .env

Crie um arquivo `.env` na raiz do projeto com as seguintes variáveis:

```env
DB_HOST=localhost
DB_NAME=ecommercephp
DB_USER=postgres
DB_PASS=sua_senha

# Configuração de e-mail
MAIL_ENABLED=false
MAIL_FROM=no-reply@ecoshop.com.br

# Gateways de pagamento (opcional)
STRIPE_SECRET_KEY=
STRIPE_WEBHOOK_SECRET=
MERCADO_PAGO_ACCESS_TOKEN=
```

### 5. Executar as migrações

Execute o script de migração para criar todas as tabelas e popular dados de exemplo:

```bash
php database/migrate.php
```

### 6. Configurar o servidor web

Configure o servidor web para apontar para a raiz do projeto. Se estiver usando o servidor embutido do PHP:

```bash
php -S localhost:8000
```

Acesse `http://localhost:8000` no navegador.

---

## Configuração

### Variáveis de Ambiente

O sistema carrega configurações do arquivo `.env` na raiz do projeto. As variáveis disponíveis são:

| Variável | Descrição | Padrão |
|----------|-----------|--------|
| `DB_HOST` | Host do banco de dados | `localhost` |
| `DB_NAME` | Nome do banco de dados | `ecommercephp` |
| `DB_USER` | Usuário do banco | `postgres` |
| `DB_PASS` | Senha do banco | _(obrigatória, definida no .env)_ |
| `MAIL_ENABLED` | Habilita envio real de e-mails | `false` |
| `MAIL_FROM` | Remetente dos e-mails | `no-reply@ecoshop.com.br` |
| `STRIPE_SECRET_KEY` | Chave secreta do Stripe | vazio |
| `STRIPE_WEBHOOK_SECRET` | Segredo do webhook do Stripe | vazio |
| `MERCADO_PAGO_ACCESS_TOKEN` | Token de acesso do Mercado Pago | vazio |

### Configuração do Banco

A conexão com o banco é feita em `config/database.php` usando PDO. O sistema utiliza o padrão de variáveis globais `$pdo` para acesso ao banco em todas as classes.

---

## Banco de Dados

O sistema utiliza PostgreSQL e possui as seguintes tabelas principais:

### Tabelas de Usuários

- `usuarios` - Usuários do sistema (clientes, funcionários, gerentes, administradores)

### Tabelas de Catálogo

- `categorias` - Categorias e subcategorias de produtos
- `produtos` - Produtos com informações de preço, estoque, SEO e identificação
- `produto_variacoes` - Variações de produtos (tamanho, cor, estoque, preço adicional)
- `produto_imagens` - Imagens dos produtos
- `produto_faqs` - Perguntas frequentes por produto

### Tabelas de Compras

- `carrinhos` - Carrinhos de compra (por usuário ou sessão)
- `carrinho_itens` - Itens do carrinho
- `enderecos` - Endereços de entrega dos clientes
- `pedidos` - Pedidos com status, valores, frete, imposto e cupom
- `pedido_itens` - Itens de cada pedido

### Tabelas de Suporte

- `tickets` - Tickets de atendimento vinculados a produtos
- `ticket_mensagens` - Mensagens de cada ticket

### Tabelas de Gestão

- `cupons` - Cupons de desconto
- `audit_logs` - Registro de auditoria de ações administrativas

---

## Migrações

As migrações estão organizadas em arquivos SQL numerados na pasta `database/migrations/`:

| Arquivo | Descrição |
|---------|-----------|
| `V1__create_tables.sql` | Tabela inicial de usuários |
| `V2__ecommerce_tables.sql` | Tabelas de catálogo, carrinho e pedidos |
| `V3__seed_data.sql` | Dados de exemplo (categorias, produtos, cliente demo) |
| `V4__admin_produtos.sql` | Colunas de SEO, identificação, FAQs e tickets |
| `V5__audit_logs.sql` | Tabela de logs de auditoria |
| `V6__pedido_aprovacao.sql` | Campos de aprovação de pedidos |
| `V7__pedido_imposto.sql` | Campo de imposto em pedidos |
| `V8__performance_indices.sql` | Índices de performance |
| `V9__categorias.sql` | Tabela de categorias com subcategorias |
| `V10__cupons.sql` | Tabela de cupons de desconto |
| `V11__frete_rastreio.sql` | Campos de rastreio e transportadora |
| `V12__pedido_cupom.sql` | Campo de cupom em pedidos |

Para executar as migrações:

```bash
php database/migrate.php
```

Para verificar se as tabelas foram criadas corretamente:

```bash
php database/verificar_tabelas.php
```

---

## Usuários e Perfis de Acesso

O sistema possui quatro perfis de acesso:

| Perfil | Descrição | Acesso |
|--------|-----------|--------|
| `CLIENTE` | Comprador na loja | Loja, carrinho, checkout, pedidos, perfil |
| `FUNCIONARIO` | Funcionário da loja | Painel administrativo |
| `GERENTE` | Gerente da loja | Painel administrativo |
| `ADMIN` | Administrador do sistema | Painel administrativo completo |

### Usuário de demonstração

Após executar as migrações, um usuário de demonstração é criado:

- **E-mail:** `cliente@demo.com`
- **Senha:** `cliente123`

### Cadastro de funcionários

Funcionários podem ser cadastrados via API:

```bash
curl -X POST http://localhost/api/cadastrar_funcionario.php \
  -H "Content-Type: application/json" \
  -d '{
    "nome": "João Silva",
    "email": "joao@loja.com",
    "password": "senha123",
    "cargo": "FUNCIONARIO"
  }'
```

Cargos permitidos: `FUNCIONARIO`, `GERENTE`, `ADMIN`.

---

## Fluxo de Pedidos

O sistema implementa um fluxo de aprovação manual de pedidos, similar a marketplaces:

### Etapas do Pedido

1. **AGUARDANDO_APROVACAO** - Pedido criado pelo cliente, aguardando revisão do administrador
2. **APROVADO** - Pedido aprovado pelo administrador, estoque é decrementado
3. **PENDENTE** - Pedido aprovado aguardando pagamento
4. **PAGO** - Pagamento confirmado
5. **ENVIADO** - Pedido enviado ao cliente
6. **ENTREGUE** - Pedido entregue
7. **RECUSADO** - Pedido recusado pelo administrador (com motivo)
8. **CANCELADO** - Pedido cancelado

### Aprovação de Pedidos

O administrador pode aprovar ou recusar pedidos na página `pages/admin/pedidos_pendentes.php`:

- **Aprovar:** O estoque é revalidado e decrementado. O cliente recebe notificação por e-mail.
- **Recusar:** O motivo é obrigatório. O cliente recebe notificação por e-mail com a justificativa.

### Cálculo de Valores

- **Subtotal:** Soma dos itens do carrinho
- **Frete:** Calculado pelo método de entrega (PAC: R$ 22,50, SEDEX: R$ 35,90, Retirada: grátis)
- **Imposto:** 10% sobre o subtotal
- **Desconto:** Aplicado via cupom (percentual ou valor fixo)
- **Total:** Subtotal + frete + imposto - desconto

---

## Painel Administrativo

O painel administrativo está disponível em `pages/admin/` e inclui:

### Dashboard

Visão geral com estatísticas de pedidos, faturamento, pedidos pendentes e produtos ativos.

### Gestão de Produtos

Formulário completo com abas:

- **Dados Básicos:** Nome, descrição, categoria, preço, estoque, status e imagens
- **SEO / Pesquisa:** Palavras-chave, meta título, meta descrição, slug e preview do Google
- **Identificação:** EAN, SKU e NCM com validação de unicidade
- **SAC / Suporte:** Tickets de atendimento e FAQ do produto

### Gestão de Categorias

CRUD completo de categorias com suporte a subcategorias (categoria pai).

### Gestão de Cupons

CRUD completo de cupons com tipo (percentual ou fixo), valor mínimo, quantidade máxima de usos e período de validade.

### Pedidos Pendentes

Lista de pedidos aguardando aprovação com detalhes do cliente, itens, endereço de entrega e botões para aprovar ou recusar.

### Fila de Pedidos

Visualização em formato kanban com colunas por status de pedido.

### Relatórios

Estatísticas de faturamento bruto, líquido, impostos, pedidos pendentes e produtos ativos.

### Auditoria

Log de ações de funcionários e administradores com filtros por usuário, ação, entidade e período.

---

## API

O sistema expõe endpoints de API para operações específicas:

### Endpoints Públicos

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| POST | `/api/login.php` | Autenticação de usuário |
| POST | `/api/logout.php` | Encerramento de sessão |
| POST | `/api/cadastrar_cliente.php` | Cadastro de cliente |
| POST | `/api/cadastrar_funcionario.php` | Cadastro de funcionário (JSON) |
| POST | `/api/carrinho.php?action=adicionar` | Adicionar item ao carrinho |
| POST | `/api/carrinho.php?action=atualizar` | Atualizar quantidade (JSON) |
| POST | `/api/carrinho.php?action=remover` | Remover item (JSON) |
| POST | `/api/checkout.php` | Processar checkout e criar pedido |

### Endpoints Administrativos

Todos os endpoints administrativos exigem autenticação e perfil de administrador.

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| GET | `/api/admin/pedidos_pendentes.php` | Lista pedidos aguardando aprovação |
| POST | `/api/admin/aprovar_pedido.php` | Aprova um pedido |
| POST | `/api/admin/recusar_pedido.php` | Recusa um pedido com motivo |
| GET | `/api/admin/buscar_produto.php?id=X` | Busca produto com FAQs, tickets e imagens |
| POST | `/api/admin/salvar_produto.php` | Cria ou atualiza produto |
| POST | `/api/admin/salvar_categoria.php` | Cria ou atualiza categoria |
| POST | `/api/admin/excluir_categoria.php` | Exclui categoria |
| POST | `/api/admin/salvar_cupom.php` | Cria ou atualiza cupom |
| POST | `/api/admin/excluir_cupom.php` | Exclui cupom |
| POST | `/api/admin/responder_ticket.php` | Envia resposta a ticket |

---

## Sistema de Cache

O sistema possui um mecanismo de cache em arquivo implementado na classe `src/Cache.php`.

### Funcionalidades

- Armazenamento em arquivos na pasta `storage/cache/`
- TTL (tempo de vida) configurável, padrão de 300 segundos
- Chaves baseadas em hash MD5
- Expiração automática de arquivos vencidos

### Métodos

| Método | Descrição |
|--------|-----------|
| `Cache::init($dir, $ttl)` | Inicializa o cache com diretório e TTL |
| `Cache::get($key)` | Recupera valor do cache |
| `Cache::set($key, $value, $ttl)` | Armazena valor no cache |
| `Cache::delete($key)` | Remove valor do cache |
| `Cache::clear()` | Limpa todo o cache |

---

## Fila de Processamento

O sistema possui uma fila de jobs em arquivo implementada na classe `src/Queue.php`.

### Funcionalidades

- Armazenamento de jobs em arquivos JSON na pasta `storage/queue/`
- Processamento sequencial com limite configurável
- Movimentação de arquivos para pasta `processing/` durante execução
- Tratamento de erros com retorno do job à fila

### Métodos

| Método | Descrição |
|--------|-----------|
| `Queue::init($dir)` | Inicializa a fila |
| `Queue::push($class, $data)` | Adiciona um job à fila |
| `Queue::process($limit)` | Processa jobs pendentes |
| `Queue::count()` | Retorna quantidade de jobs pendentes |

### Exemplo de uso

```php
// Adicionar job à fila
Queue::push("NotificacaoJob", ["pedido_id" => 123]);

// Processar jobs (chamar via cron ou worker)
Queue::process(10);
```

---

## Notificações por E-mail

O sistema envia notificações por e-mail para eventos importantes:

- **Pedido aprovado:** Notifica o cliente que o pedido foi aprovado
- **Pedido recusado:** Notifica o cliente com o motivo da recusa

### Configuração

- Se `MAIL_ENABLED=false` (padrão), os e-mails são registrados no arquivo `storage/emails.log` para debug
- Se `MAIL_ENABLED=true`, os e-mails são enviados via função `mail()` do PHP

### Templates

Os e-mails utilizam um template HTML responsivo com a identidade visual da loja (EcoShop).

---

## Gateways de Pagamento

O sistema possui integração com dois gateways de pagamento:

### Stripe

- Criação de sessões de checkout
- Processamento de webhooks para confirmação de pagamento
- Requer `STRIPE_SECRET_KEY` e `STRIPE_WEBHOOK_SECRET` no `.env`

### Mercado Pago

- Criação de preferências de pagamento
- Processamento de webhooks para confirmação de pagamento
- Requer `MERCADO_PAGO_ACCESS_TOKEN` no `.env`

### Uso

```php
// Criar checkout
$url = GatewayPagamento::criarCheckout($pedidoId, "stripe");
$url = GatewayPagamento::criarCheckout($pedidoId, "mercadopago");

// Processar webhook
GatewayPagamento::processarWebhook("stripe", $payload);
GatewayPagamento::processarWebhook("mercadopago", $payload);
```

---

## Auditoria

O sistema registra todas as ações administrativas na tabela `audit_logs`:

### Ações Registradas

- `LOGIN` e `LOGOUT` - Entrada e saída do sistema
- `CRIAR` e `ATUALIZAR` - Criação e atualização de produtos
- `CRIAR_PEDIDO` - Criação de pedidos
- `APROVAR_PEDIDO` e `RECUSAR_PEDIDO` - Decisões sobre pedidos
- `CRIAR_CATEGORIA`, `ATUALIZAR_CATEGORIA`, `EXCLUIR_CATEGORIA` - Gestão de categorias
- `CRIAR_CUPOM`, `ATUALIZAR_CUPOM`, `EXCLUIR_CUPOM` - Gestão de cupons
- `RESPONDER_TICKET` - Respostas a tickets

### Informações Registradas

- Usuário que executou a ação
- Tipo de ação
- Entidade afetada (produto, pedido, categoria, etc.)
- Descrição detalhada
- Endereço IP
- User agent
- Data e hora

---

## Segurança

O sistema implementa diversas práticas de segurança:

### Autenticação

- Senhas armazenadas com `password_hash()` (bcrypt)
- Verificação com `password_verify()`
- Sessões PHP com proteção contra session fixation

### Autorização

- Verificação de perfil de acesso em páginas administrativas
- Guard de API para endpoints administrativos (retorna JSON 401/403)
- Redirecionamento de usuários não autorizados

### Proteção contra Injeção SQL

- Uso de prepared statements com PDO em todas as consultas
- Parâmetros vinculados em vez de concatenação de strings

### Proteção contra XSS

- Função `e()` para escape de HTML em todas as saídas
- `htmlspecialchars()` com ENT_QUOTES e UTF-8

### Validação de Dados

- Validação de e-mail com `filter_var()`
- Validação de EAN (8 ou 13 dígitos)
- Validação de SKU (mínimo 5 caracteres, único)
- Validação de NCM (8 dígitos)
- Validação de slug (letras minúsculas, números e hífens)
- Validação de senha (mínimo 8 caracteres para funcionários)

### Upload de Arquivos

- Validação de tipo MIME (JPEG, PNG, WebP, GIF)
- Limite de tamanho (5MB)
- Nomes de arquivo únicos gerados com `random_bytes()`

---

## Desenvolvimento

### Adicionar uma nova migração

1. Crie um arquivo na pasta `database/migrations/` com o padrão `V{n}__descricao.sql`
2. Execute `php database/migrate.php` para aplicar

### Adicionar uma nova página

1. Crie o arquivo em `pages/` ou `pages/admin/`
2. Inclua `require_once __DIR__ . "/../config/bootstrap.php";` no início
3. Use os includes `header.php` e `footer.php` para páginas públicas
4. Use `admin-header.php` e `admin-footer.php` para páginas administrativas

### Adicionar uma nova classe de modelo

1. Crie o arquivo em `src/`
2. Adicione o `require_once` no `config/bootstrap.php`
3. Use `global $pdo;` para acessar o banco de dados

### Adicionar um novo endpoint de API

1. Crie o arquivo em `api/` ou `api/admin/`
2. Para endpoints administrativos, use `requererAdminApi()` no início
3. Retorne respostas JSON com códigos HTTP apropriados

---

## Licença

Este projeto é de código aberto e está disponível para uso e modificação.