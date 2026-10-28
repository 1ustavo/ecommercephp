# AI HANDOFF — Contexto do sistema (EcoShop / ecommercephp)

> Cole este documento como contexto ao pedir tarefas para outra IA. Ele descreve
> arquitetura, convenções, modelo de segurança ATUAL e armadilhas do projeto.

## 1. Visão geral
- **E-commerce em PHP puro (sem framework)**: procedural nos endpoints + classes estáticas de domínio em `src/`.
- **Banco: PostgreSQL** (não MySQL!). `SERIAL/BIGSERIAL`, `RETURNING`, `ON CONFLICT`, `INTERVAL`, `TRUE/FALSE`.
- **Sessão nativa PHP** (sem bibliotecas), configurada em `config/bootstrap.php`.
- Roda em **XAMPP/Windows** (`C:\xampp\htdocs\php`); Docker opcional (`docker-compose.yml`).
- Composer: **stripe/stripe-php** e **phpmailer/phpmailer** (autoloader carregado no bootstrap).

## 2. Estrutura
```
index.php                 # front controller simples
config/bootstrap.php      # OBRIGATÓRIO ser o 1º require de TODO endpoint/página
config/env.php            # loadEnv() lê .env
config/database.php       # cria $pdo (global)
src/                      # classes estáticas de domínio (uma por entidade)
api/                      # endpoints que respondem JSON (ou redirect p/ forms)
api/admin/                # endpoints de gestão (papel ADMIN/GERENTE na maioria)
pages/                    # páginas HTML (loja, conta) e pages/admin/ (painel)
includes/                 # header.php, footer.php, admin-header.php, admin-footer.php
assets/js/                # JS por página do painel (admin-*.js) via fetch nas APIs
database/migrations/      # V*.sql — ordenados por glob() sort; migrate.php roda todos
vendor/                   # composer
```

## 3. Padrão de request (NUNCA quebrar)
1. **Todo** arquivo em `api/` e `pages/` começa com `require_once __DIR__ . "/../config/bootstrap.php";` (caminho relativo à profundidade).
2. O bootstrap configura: erros por APP_ENV, headers de segurança/CSP, cookie de sessão (HttpOnly, SameSite=Strict, Secure via `isHttpsRequest()` que aceita `X-Forwarded-Proto`), inicia a sessão no fim, e faz `require` de todas as classes de `src/`.
3. Endpoints JSON: `header("Content-Type: application/json; charset=utf-8")` + contrato `{"sucesso": bool, "erro"|"mensagem"|...}` + `http_response_code()` apropriado (400/401/403/404/409/422).
4. URLs geradas por helpers: `pageUrl("login.php")`, `apiUrl("login.php")`, `asset("css/theme.css")`, `baseUrl()`. `APP_URL` vem do `.env`.
5. Escape de HTML **sempre** com `e()`; mensagens entre páginas com `flash('chave', 'msg')` / `flash('chave')`.

## 4. Modelos de domínio (src/*.php)
Classes **estáticas** usando `global $pdo`. Principais: `Auth`, `Usuario`, `Produto`, `Categoria`, `Cupom`, `Carrinho`, `Pedido`, `Endereco`, `Notificacao` (e-mails), `Ticket` (SAC), `Cache`, `Queue`, `GatewayPagamento` (Stripe), `CorreiosToken`/`CorreiosFrete`.
- **Carrinho**: convidado usa carrinho por `session_id`; no login `Carrinho::mesclarSessaoComUsuario()` faz merge (convidado vira do usuário). Itens sempre escopados por `carrinho_id` do solicitante.
- **Pedido**: máquina de estados. `AGUARDANDO_APROVACAO` → aprovar (revalida estoque) / recusar (motivo obrigatório). Transições validadas em `api/admin/pedido_status.php` (`$transicoes`). `Pedido::aprovar()` debita estoque transacionalmente.
- **Ticket**: cliente só abre ticket de **pedido próprio** (validado no modelo, `Ticket::abrir`); acesso via `Ticket::usuarioPodeAcessar()`.
- **Endereco**: `Endereco::buscar($id, $usuarioId)` sempre filtra por dono.

## 5. MODELO DE SEGURANÇA ATUAL (não reverter!)
Estas proteções foram implementadas/auditadas — qualquer refatoração deve mantê-las:

### 5.1 Sessão rigorosa
- `Auth::login()`: `session_regenerate_id(true)`; grava `usuario_id, cargo, login_time, last_activity, user_agent, ip_address, session_version`.
- `Auth::validarSessao(): bool` — valida **sem redirect**: timeout 1800s, User-Agent fingerprint, IP (exceção 127.0.0.1/::1 **só com APP_ENV=development**), e **session_version** (sessões anteriores a troca de senha são invalidadas).
- `Auth::verificarLogin/verificarCliente/verificarAdmin()` — para páginas (redirect); usam `validarSessao()`.
- `Auth::logout()` — destrói sessão e **expira o cookie** com `setcookie(...)` replicando params.

### 5.2 Matriz de papéis (autorização)
- `papelNivel()`: FUNCIONARIO=1 < GERENTE=2 < ADMIN=3.
- **`requererPapel(["ADMIN","GERENTE"])`** — guard de API: chama `validarSessao()` (401) e checa papel (403). É o guard padrão de `api/admin/*`.
- `requererAdminApi()` está **deprecated** e delega para `requererPapel(["ADMIN","GERENTE","FUNCIONARIO"])` — não usar em código novo.
- `temPapel(...)` — **só para exibição/redirect de páginas** (esconder botões/links). Páginas restritas (produtos, cupons, categorias, relatorios) redirecionam FUNCIONARIO para `admin/pedidos_pendentes.php` no topo.
- Mapa aplicado:
  - `["ADMIN"]`: `api/cadastrar_funcionario.php` (só ADMIN cria contas; hierarquia checada com `papelNivel`, nunca criar papel > do criador).
  - `["ADMIN","GERENTE"]`: aprovar/recusar/status/relatórios/buscar_produto, CRUD produto/categoria/cupom.
  - `["ADMIN","GERENTE","FUNCIONARIO"]`: `pedidos_pendentes.php` (leitura), `responder_ticket.php`.
  - FUNCIONARIO: leitura de pedidos + responder tickets. **Não** aprova pedido, não cria conta, não vê relatório financeiro.
- Front do painel acompanha a matriz (menu esconde itens; botões de aprovar/recusar/editar/excluir escondidos quando sem papel).
- `isAdmin()` retorna true p/ ADMIN+GERENTE+FUNCIONARIO — usado só por páginas legadas (`admin-header.php`), não por APIs.

### 5.3 Login
- **Lockout por conta**: colunas `usuarios.failed_attempts` / `locked_until`. Após 5 falhas: bloqueio crescente 5min → 15min → 1h (incremento atômico via `UPDATE ... RETURNING`). Cada falha audita `LOGIN_FALHA`; tentativa em conta bloqueada audita `LOGIN_BLOQUEADO`. Erros via `$auth->getLastError()` → `?erro=bloqueado|credenciais`.
- Rate-limit por IP (tabela `rate_limits`, `rate_limit_check()`) — **fail-closed** (erro de DB nega a ação).
- Hash dummy anti-timing para emails inexistentes.

### 5.4 Reset de senha
- `solicitar_reset.php`: token 256 bits (`random_bytes(32)`), **só o hash SHA-256 vai ao banco** (`password_resets.token`); token cru só por e-mail; expira em 1h; **deleta tokens anteriores do mesmo email** antes de inserir.
- `resetar_senha.php`: lookup pelo hash; política de senha no servidor (`validarPoliticaSenha()`: 8+ chars, letras+números); ao trocar senha incrementa `usuarios.session_version` (derruba todas as sessões ativas) e deleta o token.

### 5.5 CSRF e auditoria
- **Todo POST** passa por `verify_csrf_from_request(true)`; formulários usam `csrf_input()`. APIs JSON podem mandar `X-CSRF-Token` ou `csrf_token` no body.
- `registrarAuditoria(acao, entidade, entidadeId, descricao)` → tabela `audit_logs` (mascara CPF/telefone/email na descrição). Login/logout também auditam.
- Webhook Stripe valida assinatura (`\Stripe\Webhook::constructEvent` em `GatewayPagamento::processarWebhook`).

### 5.6 Sessão/cookie no bootstrap
- Cookie: `HttpOnly`, `SameSite=Strict`, `Secure` quando HTTPS real **ou** `X-Forwarded-Proto: https` (`isHttpsRequest()`).
- CSP em `bootstrap.php` (inclui js.stripe.com); `gc_maxlifetime` 1800.

## 6. Convenções de código
- Identificadores/mensagens em **português**.
- SQL sempre **prepared statements** (`?` placeholders).
- Postgres: `RETURNING id` p/ inserts que precisam do id; `ON CONFLICT ... DO UPDATE`; booleans como `'t'/'f'` quando via string.
- IDs externos sempre `(int)` cast. Comparação de dono com cast: `(int) $row["usuario_id"] !== $usuarioId`.
- Paginação: helper `paginacaoJanela($atual, $total, $janela)`.
- JS do painel: um arquivo por página (`assets/js/admin-*.js`), `fetch` nas APIs com header `X-CSRF-Token`; ao esconder botões por papel, **proteger listeners com `?.`** ou `if (el)`.

## 7. Comandos
- Lint: `php -l <arquivo>` (obrigatório após cada mudança).
- Migrações: `php database/migrate.php` (idempotentes — `ADD COLUMN IF NOT EXISTS`; cuidado: o migrate também reseta a senha do demo `cliente@demo.com`).
- Demos/seed: `database/seed_upload_images.php`, `database/verificar_tabelas.php`.

## 8. Histórico recente (3 ciclos de correção — NÃO reverter)
1. Sessão/login: regeneração de ID, timeout 30min, lockout por conta, cookie Secure via X-Forwarded-Proto, exceção localhost só em dev, expirar cookie no logout, fail-closed no rate-limit.
2. Reset de senha: hash SHA-256 do token, invalidação de tokens anteriores, política de senha no servidor, `session_version` invalidando sessões ativas.
3. Autorização: matriz de papéis + `requererPapel()` em todos os `api/admin/*`, máquina de estados no status de pedido, `validarSessao()` em todos os endpoints de cliente, cast int na checagem de dono do pedido, guard JSON em `buscar_produto.php`, redirect de páginas restritas no topo.

## 9. Pendências conhecidas (boas tarefas para prompts)
- Reembolso: **não existe endpoint** — quando criar, usar `requererPapel(["ADMIN","GERENTE"])` + auditoria.
- Flash de sucesso pós-reset não é exibido em `pages/login.php` (só no `reset_senha.php`).
- `pages/admin/ticket_acao.php` e outros fluxos de página podem merecer o mesmo redirect de papel (hoje só produtos/cupons/categorias/relatorios têm).
- Email de reset via GET (`?token=`) — histórico do navegador; alternativa: POST com token em sessão.
- Rate-limit `login` é por IP (5/min) — o lockout por conta cobre brute-force, mas 2FA/reCAPTCHA não existem.

## 10. Checklist ao pedir tarefa para a IA
- Informar arquivo(s)-alvo exato(s) + linha(s), se souber.
- Se toca em auth: lembrar da matriz de papéis (seção 5.2) e de rodar `php -l`.
- Se adiciona coluna: nova migration `V<data>__nome.sql` com `ADD COLUMN IF NOT EXISTS`.
- Exigir: manter contrato JSON `{"sucesso":..., "erro":...}`, prepared statements, `e()` em output, CSRF em todo POST.
