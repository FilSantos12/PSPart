# PSPart-Website — Contexto do Projeto

## Regras invioláveis

1. **Timestamps:** sempre `date('Y-m-d H:i:s')` em `America/Sao_Paulo`. Nunca `CURRENT_TIMESTAMP` do SQLite (é UTC).
2. **Visibilidade de elementos:** sempre `element.style.display` no JS + `style="display:none;"` inline no HTML. Nunca `#id { display:none }` no stylesheet — o JS não sobrescreve regra de CSS.
3. **SQL:** sempre PDO com prepared statements. Nunca concatenação de string.
4. **Valores monetários:** sempre lidos do banco. Nunca do request do browser.
5. **Débito de carteira:** nunca automatizar. `meCheckout()` exige confirmação manual do admin + `LOJA_DADOS_REAIS === true`.
6. **Token do Melhor Envio:** nunca vai ao frontend. Sempre via proxy PHP (`backend/api/frete.php`).
7. **Credenciais:** `backend/config/*.php` é gitignored. Nunca commitar segredo, nunca documentar senha em texto puro.
8. **Commits:** nenhum commit sem solicitação explícita.
9. **CSS:** mudanças centralizadas em `style.css` com as variáveis existentes. Sem inline styles, sem paletas novas.

## Visão geral
E-commerce da **PSPart - Partes e Peças Automação** (filipe@pentasis.com.br) — PHP + SQLite, sem build process/bundler (JS puro, CDNs). Loja pública com catálogo, checkout (Mercado Pago Checkout Pro + Bricks) e acompanhamento de pedido; área admin completa (produtos, pedidos, categorias, rastreamento, emissão de etiquetas Melhor Envio com OAuth2); agente conversacional de compras. Em fase final antes do deploy em produção (ver "Placeholders pendentes").

## Arquivos principais
| Arquivo | Função |
|---|---|
| `index.html` | Página única |
| `acompanhar.html` | Página de acompanhamento de pedido (standalone, acesso só por token) |
| `style.css` | Estilos com CSS variables |
| `script.js` | Classe `App` com toda a lógica |
| `manifest.json` | PWA básico |
| `robots.txt` / `sitemap.xml` | SEO/indexação |

## Stack
- Bootstrap 5.3, Font Awesome 6.4, AOS 2.3 (CDN)
- Google Fonts: Poppins + Montserrat
- **marked.js** (CDN) — renderiza Markdown da Especificação Técnica no frontend
- FormSubmit.co (formulário sem backend) → filipe@pentasis.com.br
- Google Analytics GA4: `G-XQBJ002YQC` com Consent Mode v2 (LGPD)

## Estrutura da página
1. Navbar — Início | Sobre | Produtos | Contato + toggle dark mode
2. `#inicio` — Hero (gradiente sólido `var(--primary)` → navy escuro, sem foto/parallax) + contadores animados
3. Diferenciais — 4 cards (sem animação AOS — só o cabeçalho da seção anima)
4. `#sobre` — Sobre Nós (texto placeholder, aguarda conteúdo real)
5. `#produtos` — cards dinâmicos (renderizados via API, sem animação AOS) + filtro de categorias dinâmico + modais de detalhe
6. `#contato` — Formulário + info de contato
7. Footer — marca + link "Acompanhar Pedido" (`acompanhar.html`) + back-to-top; **sem ícones de rede social** (removidos por não terem URL real); botão WhatsApp flutuante **comentado no HTML**, pendente de número real
8. **Modal de Checkout** (`#checkoutModal`, `modal-lg`) — dados do comprador + endereço com ViaCEP + controle de quantidade
9. **Modal de Redirecionamento MP** (`#paymentRedirectModal`) — spinner enquanto redireciona
10. **Lightbox de imagem** (`#lightboxOverlay`) — overlay customizado para ampliar imagens

## Página de Acompanhamento (`acompanhar.html`)
- Standalone — não depende de `index.html`
- **Acesso via token**: `acompanhar.html?token=abc123` ou `acompanhar.html?pedido=X&token=abc123`
- **Formulário de busca manual**: quando não há token na URL, exibe form com `pedido_id` + e-mail; ao submeter chama `buscarManual()` que chama `buscar({ pedido_id, email })`
- **Timeline unificada de 6 etapas** (`#unifiedTimeline`) — visível apenas para pedidos em andamento (`aprovado`, `em_processamento`, `pendente`, `em_analise`):
  1. Pedido Recebido — sempre ativo
  2. Pagamento Confirmado — ativo se status `aprovado/em_processamento`
  3. Em Preparação — controlado por `order_tracking.status >= 0`
  4. Embalado — `order_tracking.status >= 1`
  5. Enviado — `order_tracking.status >= 2`
  6. Código de Rastreio — `order_tracking.status >= 3` (exibe código e link dos Correios)
- **Bloco de falha** (`#falhaBlock`) — exibido em vez da timeline para `recusado`, `cancelado`, `reembolsado`, `contestado`; mostra ícone colorido, título e descrição específicos por status; botão "Falar com a loja" (padrão Mercado Livre — sem etapas de entrega para pedidos não aprovados)
- Exibe: status badge, timeline ou bloco de falha, itens do pedido, endereço, dados do comprador
- **CSS bug crítico**: nunca esconder seções via stylesheet (`#id { display:none }`) — JS usa `element.style.display = ''` que não sobrescreve regras CSS. Usar `style="display:none;"` inline no HTML para que o JS consiga mostrar/esconder.
- API: `GET /backend/api/acompanhar.php?token=...` ou `?pedido_id=X&email=Y`

## Categorias
- Gerenciadas na tabela `categorias` do banco — **não são mais hardcoded**
- Categorias iniciais: `motorizacao`, `robotica`, `acesso`, `bluetooth`
- Admin: `backend/admin/categorias.php` — criar, listar, excluir (com proteção: não exclui se há produtos vinculados)
- API pública: `GET /backend/api/categorias.php` — retorna `[{slug, nome}]` usada pelo frontend
- Os selects de categoria em `produto-novo.php` e `produto-editar.php` carregam do banco automaticamente
- Novos filtros do catálogo e badges de produto refletem novas categorias sem alterar código

## E-mails transacionais (`backend/helpers/email.php`)
- `emailPedidoCriado($pedido, $itens, $token)` — disparado em `pedidos.php` ao criar o pedido; inclui botão "Acompanhar Pedido" com link `{MP_BASE_URL}/acompanhar.html?pedido={id}&token={token}`
- `emailPagamentoAprovado($pedido, $itens, $token)` — disparado em `webhook.php` e `processar-pagamento.php` na primeira transição para `aprovado`; inclui mesmo link de acompanhamento
- `emailPedidoEnviado()` — existe no código mas não é mais chamado (status 'enviado' removido do fluxo)
- `emailFichaSeparacao($pedido, $itens): bool` — e-mail **interno** disparado pelo admin em `pedido-detalhe.php`; monta tabela Código Interno | Produto | Quantidade para o setor de separação/estoque; destinatário via constante `EMAIL_SEPARACAO_INTERNA` (fallback: `filipe@pentasis.com.br`) — **nunca** enviado ao comprador
- Envio via `mail()` nativo do PHP — **não funciona em localhost** (DEV-SKIP no log), funciona em hospedagem compartilhada
- Logs de envio em `logs/emails.log`
- `MP_BASE_URL` (de `mercadopago.php`) é usado como base dos links nos e-mails

## Classe App (script.js)
- `setupScrollHandlers()` — scroll spy + back-to-top unificados com `requestAnimationFrame`
- `setupThemeToggle()` — dark/light, persiste em `localStorage('psp_theme')`
- `setupProductFilter()` — registra listener com **event delegation** no `#product-filters`; consulta `.product-col` no momento do clique (sem NodeList stale); chamado uma vez em `init()`
- `setupLGPD()` — banner de cookies, persiste em `localStorage('psp_lgpd_consent')`
- `setupContactForm()` → `submitForm()` — fetch para FormSubmit.co
- `setupCounters()` — Intersection Observer nos `.stat-number`
- `setupModalButtons()` — botão "Solicitar Orçamento" preenche formulário de contato
- `setupCheckout()` — registra listeners dos dois botões de pagamento (redirect e Bricks), link de fallback redirect dentro do Brick, e limpeza do Brick ao fechar o modal
- `setupImageLightbox()` — lightbox customizado para `img.card-img-top`, `img.carousel-img`, `img.modal-product-image`
- `renderProducts()` — busca categorias e produtos em paralelo (`Promise.all`); gera botões de filtro dinamicamente em `#product-filters`; gera cards e modais em `#products-grid` / `#product-modals`; card mostra `codigo_interno` (se preenchido) e badge de disponibilidade (`.badge-disponivel`/`.badge-esgotado`, reaproveitando `.product-badge`); desabilita botão se `estoque = 0`; sem `data-aos` (removido do template); em caso de falha na API, mantém o markup estático de fallback em `#products-grid` (6 produtos hardcoded, com os mesmos campos de código/estoque para consistência visual)
- `handleRetornoMP()` — detecta `?pagamento=recusado|pendente` na URL após retorno do MP; limpa o param com `history.replaceState`; exibe modal apropriado
- `_validateCheckoutForm()` — valida campos obrigatórios com `is-invalid`, foca no primeiro inválido
- `_doCheckoutSubmit(mode)` — fluxo unificado: valida, grava pedido, cria preference; bifurca por `mode`: `'redirect'` abre `paymentRedirectModal` e redireciona; `'bricks'` esconde form/footer e renderiza o Brick
- `_renderBrick(preferenceId, email, amount)` — busca Public Key em `public-config.php`, inicializa `MercadoPago` SDK com locale pt-BR e tema dark/light; cria Payment Brick no `#bricks-container`; no `onSubmit` chama `processar-pagamento.php` e exibe `_showBricksResult()`
- `_showBricksResult(result)` — fecha o modal de checkout e abre `#bricksResultModal` com três estados: `approved` (✅ ícone verde, botão "Acompanhar pedido"), `rejected` (❌ ícone vermelho, mensagem traduzida via `_traduzirStatusDetail()`), outros (⏳ ícone amarelo, "em análise")
- `_traduzirStatusDetail(detail)` — converte `status_detail` do MP (`cc_rejected_insufficient_amount` etc.) em mensagem legível em PT-BR para o modal de recusa
- `_unmountBrick()` — desmonta instância do Brick, reseta estado (`_bricksInstance`, `_currentPedidoId`, `_currentToken`, `_currentInitPoint`), restaura visibilidade do form e footer
- `_openCheckoutModal()` — chama `_unmountBrick()` antes de abrir para garantir estado limpo
- `_buscarCep()` — consulta ViaCEP e preenche campos de endereço automaticamente

## Fluxo de compra
1. Clicar em **botão verde de carrinho** (card) ou **"Comprar Agora"** (modal de produto)
2. Modal de checkout (`modal-lg`) abre com resumo do produto + controle de quantidade
3. Preencher: nome, e-mail, telefone, CEP (auto-preenche via ViaCEP), número, complemento (opcional)
4. Validação inline (campos obrigatórios: nome, e-mail, telefone, CEP, número)
5. Escolher forma de pagamento:

**Opção A — Pagar no Mercado Pago (redirect)**
- Spinner de redirecionamento → `window.location.href = mp.init_point`
- Pagamento processado no site do MP → webhook atualiza status no banco
- `aprovado` → redireciona para `acompanhar.html?pedido=X&token=Y`
- `recusado` → homepage com modal de erro
- `pendente` → homepage com modal informando análise

**Opção B — Pagar aqui mesmo (Checkout Bricks)**
- Form e footer somem; Payment Brick renderiza inline (cartão, débito, PIX, boleto)
- Usuário preenche dados de pagamento no próprio site
- `onSubmit` do Brick chama `processar-pagamento.php` → MP Payments API
- Resultado exibido em `#bricksResultModal` com nome e e-mail do comprador:
  - `approved` → ✅ "Pagamento aprovado!" + botão "Acompanhar pedido"
  - `rejected` → ❌ "Pagamento não aprovado" + mensagem baseada no `status_detail` (ex: saldo insuficiente, CVV inválido)
  - `pending`/`in_process` → ⏳ "Pagamento em análise" + instrução de aguardar e-mail
- Link "Pagar pelo site do MP" dentro do Brick permite trocar para Opção A sem reiniciar

## Banner de consentimento LGPD (`#lgpdBanner`)
- `position: fixed; bottom: 0; z-index: 1040` (abaixo de modais Bootstrap ~1050-1055, acima do conteúdo normal)
- Visibilidade via `style.display` inline (JS `setupLGPD()` + `style="display:none;"` no HTML) — segue a regra do projeto; antes usava `classList.add('visible')` com transição de `bottom`, corrigido
- Persistência em `localStorage('psp_lgpd_consent')`

## Lightbox de imagem
- Overlay customizado (`z-index: 9999`) — não usa Bootstrap Modal, evita conflito com modais abertos
- Abre ao clicar em `img.card-img-top`, `img.carousel-img` ou `img.modal-product-image`
- Fecha: clique no fundo, botão ✕ ou tecla `Escape`
- Cursor `zoom-in` nas imagens, `zoom-out` no fundo

## Validação de checkout
- Inline via classe `is-invalid` do Bootstrap + `<div class="invalid-feedback">` em cada campo
- **Nunca usar `showErrorModal()` dentro do checkout** — modal de erro abre atrás do modal de checkout
- Foco automático no primeiro campo inválido
- Erros da API exibidos em `#checkout-error-msg` (alerta no topo do modal, some após 6s)
- Produto com `estoque = 0` tem botão desabilitado ("Fora de estoque") — não abre checkout

## Dark mode
- Classe `body.dark-mode` (não media query)
- Anti-FOUC: script inline logo após `<body>` aplica a classe antes do primeiro render
- Fallback: `prefers-color-scheme` se não houver preferência salva
- **Cobertura de variáveis: 6 de 10** (`--text-dark`, `--text-muted`, `--bg-light`, `--bg-white`, `--shadow-sm`, `--shadow-md`). `--primary` e `--accent` **não** têm override global — têm papel duplo (fundo de elementos "chrome" como navbar/footer/botão de filtro ativo, que devem continuar navy/azul escuro em qualquer tema, **e** cor de texto em alguns badges/ícones sobre fundo claro). Um override de variável global quebra o primeiro uso; correção exigiria overrides pontuais por seletor (padrão já usado em `body.dark-mode .product-price`, `.product-price-modal`) — não feito ainda, ~8 seletores identificados com contraste subótimo em dark mode mas não regredidos por essa decisão (comportamento pré-existente). `--radius` não precisa de versão dark (dimensional, não é cor)

## CSS variables principais
```css
--primary: #274185
--accent: #3457a6        /* derivado do --primary, contraste 6.86:1 com texto branco */
--accent-rgb: 52, 87, 166 /* para usos em rgba(), ex. anéis de foco, hero-badge */
--accent-hover: #2d4a8f
--cta: #157a52            /* botão de compra — verde harmonizado com a marca, não é o verde padrão do Bootstrap */
--cta-hover: #0f5f3f
--text-dark / --text-muted / --bg-light / --bg-white
--shadow-sm / --shadow-md / --radius: 12px
```
- Verde (`--cta`) reservado para ação/status: botão de compra (`.btn-buy`), badge de disponibilidade (`.badge-disponivel`, cor própria não ligada a `--cta`), botão WhatsApp. **Preço do produto usa `--primary`, não `--cta`** (em ambos os contextos, card e modal) — evita poluição visual com verde demais na mesma tela
- `body.dark-mode .product-price` e `body.dark-mode .product-price-modal` compartilham override para `#90b4f5` (mesmo tom usado em outros textos-sobre-fundo-escuro do projeto)

## Schema do banco (SQLite — `database.db`)

### Tabela `produtos`
| Coluna | Tipo | Observação |
|---|---|---|
| id | INTEGER PK | |
| nome | TEXT NOT NULL | |
| descricao | TEXT | |
| preco | REAL | |
| categoria | TEXT | slug da tabela `categorias` |
| imagem | TEXT | campo legado — fallback quando `produto_imagens` estiver vazio |
| estoque | INTEGER | |
| ativo | INTEGER | 0 ou 1 |
| codigo_interno | TEXT | formato `SE.02.00002` — único, opcional |
| datasheet | TEXT | caminho relativo ex: `docs/ds_1_xxx.pdf` |
| especificacao_tecnica | TEXT | conteúdo em Markdown |
| peso | REAL | kg — padrão 0.5; usado no cálculo de frete |
| largura | REAL | cm — padrão 15 |
| altura | REAL | cm — padrão 10 |
| comprimento | REAL | cm — padrão 20 |
| criado_em | TEXT | |

### Tabela `produto_imagens`
| Coluna | Tipo | Observação |
|---|---|---|
| id | INTEGER PK | |
| produto_id | INTEGER FK | ON DELETE CASCADE |
| caminho | TEXT | ex: `img/prod_1_xxx.png` |
| ordem | INTEGER | usado para ordenação (drag-and-drop no admin) |
| principal | INTEGER | 1 = imagem principal (thumbnail) |
| criado_em | TEXT | |

### Tabela `categorias`
| Coluna | Tipo | Observação |
|---|---|---|
| id | INTEGER PK | |
| slug | TEXT UNIQUE | chave usada em `produtos.categoria` e `data-category` no frontend |
| nome | TEXT | label exibido nos filtros e badges |
| ordem | INTEGER | ordem de exibição dos botões de filtro |
| criado_em | TEXT | |

> Migration inicial: `migrations/migrate-categorias.php` — já executada, arquivo mantido em `migrations/` para histórico.

### Tabela `pedidos`
- id, nome/email/telefone_comprador, endereço completo, total, status, mp_preferencia_id, token_acompanhamento, criado_em
- Status válidos: `pendente`, `aprovado`, `em_analise`, `recusado`, `cancelado`, `reembolsado`, `contestado`, `em_processamento` — **`enviado` foi removido** (rastreamento físico é gerenciado exclusivamente em `tracking-admin.php`)

### Tabela `order_tracking`
| Coluna | Tipo | Observação |
|---|---|---|
| order_id | TEXT UNIQUE | FK para `pedidos.id` (como string) |
| status | INTEGER | 0 = Em Preparação, 1 = Embalado, 2 = Enviado, 3 = Código de Rastreio |
| tracking_code | TEXT | Código dos Correios (formato `AA000000000BR`) |
| carrier | TEXT | Transportadora |
| notes | TEXT | Observações internas |
| updated_at | TEXT | Timestamp no fuso de Brasília |

- Criada automaticamente via `INSERT OR IGNORE` com `status = 0` quando o pagamento é aprovado (em `webhook.php` e `processar-pagamento.php`)
- Gerenciada exclusivamente em `backend/admin/tracking-admin.php`
- API pública: `GET /backend/api/tracking.php?order_id=X`

### Tabela `melhorenvio_auth`
| Coluna | Tipo | Observação |
|---|---|---|
| id | INTEGER PK | CHECK (id = 1) — sempre 1 linha (lojista único) |
| access_token | TEXT | Token OAuth2 ativo |
| refresh_token | TEXT | Para renovação automática |
| expires_at | DATETIME | `date('Y-m-d H:i:s', time() + expires_in)` |
| requer_reautorizacao | INTEGER | 1 quando o refresh falha — sinaliza admin |
| updated_at | DATETIME | Atualizado a cada refresh |

- Criada por `migrations/migrate_melhorenvio_auth.php` — já executada, arquivo mantido em `migrations/` para histórico
- Gerenciada exclusivamente via `MelhorEnvio::_salvarTokenNoBanco()` e `_marcarRequerReautorizacao()`

### Tabela `cache_cotacoes`
| Coluna | Tipo | Observação |
|---|---|---|
| cache_key | TEXT PK | `md5("v1:{produto_id}:{cep}")` |
| payload | TEXT | JSON da resposta normalizada (`{ok, cep, servicos[]}`) |
| criado_em | TEXT | Timestamp; TTL de 12 horas verificado em runtime |

### Tabela `itens_pedido`
- id, pedido_id (CASCADE), produto_id (RESTRICT), quantidade, preco_unitario

### Tabela `usuarios`
- id, nome, email (UNIQUE), senha_hash, ativo

## Módulo Admin de Produtos

### Arquivos admin relevantes
| Arquivo | Função |
|---|---|
| `backend/admin/produtos.php` | Listagem — usa subquery para pegar imagem principal de `produto_imagens` com fallback para `imagem` legado; cache busting via `?v=filemtime()` |
| `backend/admin/produto-novo.php` | Criação em duas fases na mesma página — fase 1: formulário; fase 2 (após salvar): seção de imagens múltiplas com AJAX + SortableJS |
| `backend/admin/produto-editar.php` | Edição — inclui EasyMDE + gerenciamento de imagens múltiplas via AJAX |
| `backend/admin/ajax-imagens.php` | AJAX para imagens: `upload`, `set-principal`, `reorder`, `delete` |
| `backend/admin/categorias.php` | CRUD de categorias — listar com contagem de produtos, criar (auto-slug), excluir (bloqueado se há produtos vinculados) |
| `backend/admin/_layout.php` | Layout base — aceita `$extra_head` como segundo parâmetro para injetar CSS no `<head>` |

### Fluxo de produto-novo.php
Página em duas fases sem redirect entre elas:
- **Fase 1 (GET ou POST com erro):** formulário completo com todos os campos, incluindo EasyMDE para especificação técnica
- **Fase 2 (POST com sucesso):** `$produtoCriado` recebe o `lastInsertId()`; o formulário é substituído por banner de confirmação + seção de imagens idêntica à de editar; links "Criar outro produto" e "Ver todos os produtos"
- EasyMDE só é carregado na fase 1 (não desperdiça CDN na fase 2)
- Imagem enviada pelo formulário é automaticamente inserida em `produto_imagens` com `principal = 1`

### Campo Código Interno
- Formato fixo: `SE.02.00002` (2 letras maiúsculas + ponto + 2 dígitos + ponto + 5 dígitos)
- Regex backend: `/^[A-Z]{2}\.\d{2}\.\d{5}$/`
- Validação de unicidade via SELECT antes de INSERT/UPDATE
- Auto-uppercase no input; validação visual `is-invalid` no blur

### Upload de Data Sheet (PDF)
- Armazenado em `docs/ds_{produto_id}_{uniqid}.pdf`
- Diretório `docs/` criado automaticamente na primeira vez
- Validação via **magic bytes** (`%PDF` nos primeiros 4 bytes) — mais confiável que `finfo` no Windows
- Erros de upload reportados com mensagem descritiva por código (`UPLOAD_ERR_INI_SIZE`, etc.)
- Admin: visualizar PDF atual, remover (checkbox) ou substituir
- Frontend: botão "Data Sheet" no footer do modal de produto

### Múltiplas Imagens (`produto_imagens`)
- AJAX via `backend/admin/ajax-imagens.php`
- Drag-and-drop para reordenar usando **SortableJS** (CDN)
- Primeira imagem enviada é automaticamente a principal (estrela dourada)
- Ao deletar a principal, a próxima na ordem assume automaticamente
- Validação MIME real (`finfo`) + limite 5 MB por imagem
- Imagens armazenadas em `img/prod_{produto_id}_{uniqid}.{ext}`
- **Importante**: ao deletar um produto via `produto-excluir.php`, os registros em `produto_imagens` são removidos por CASCADE, mas os arquivos físicos em `img/` ficam órfãos (não são deletados automaticamente)

### Especificação Técnica
- Campo Markdown editado com **EasyMDE** (CDN) no admin
- Toolbar: negrito, itálico, listas, preview
- Renderizado com **marked.js** no frontend dentro do modal de detalhes do produto
- Campo opcional (NULL permitido no banco)

### API de Produtos (`backend/api/produtos.php`)
Retorna por produto: `id`, `nome`, `descricao`, `preco`, `categoria`, `imagem`, `estoque`, `codigo_interno`, `datasheet`, `especificacao_tecnica`, `imagens[]`

O array `imagens` contém objetos `{caminho, ordem, principal}` ordenados por `ordem ASC`.

### renderProducts() — lógica de imagem e filtros no frontend
```javascript
// Busca categorias e produtos em paralelo
const [prodRes, catRes] = await Promise.all([
    fetch('backend/api/produtos.php'),
    fetch('backend/api/categorias.php'),
]);

// Prioridade de imagem: imagem principal de produto_imagens → qualquer imagem do array → campo legado
const imgPrincipal = imagens.find(img => img.principal) || imagens[0];
const imgSrc = imgPrincipal ? imgPrincipal.caminho : (p.imagem || '');

// Modal: carousel Bootstrap se imagens.length > 1, imagem simples se = 1

// Filtros: botões gerados dinamicamente em #product-filters a partir de categorias[]
// setupProductFilter() usa event delegation — um listener no container, não por botão
```

> Histórico de fases: ver CHANGELOG.md

## Módulo Frete — Melhor Envio (Fase 11)

### Arquivos
| Arquivo | Função |
|---|---|
| `backend/config/melhorenvio.php` | Constantes de credenciais e CEP de origem — **gitignored**, nunca commitar |
| `backend/helpers/MelhorEnvio.php` | Classe que encapsula chamadas à API, OAuth2 e refresh automático |
| `backend/api/frete.php` | `POST /backend/api/frete.php` — proxy público; recebe `produto_id` + `cep_destino` |
| `backend/admin/melhorenvio.php` | Painel de status da integração (badge + tabela de config + instruções) |
| `backend/admin/melhorenvio-conectar.php` | Inicia o fluxo OAuth2 — gera state CSRF e redireciona para `/oauth/authorize` |
| `backend/admin/melhorenvio-callback.php` | Recebe o `code`, troca por token, persiste na tabela `melhorenvio_auth` |
| `migrations/migrate_melhorenvio_auth.php` | Cria a tabela `melhorenvio_auth` — já executada, mantida para histórico |

### Fluxo do cálculo
1. Frontend envia `{ produto_id, cep_destino }` — nunca o token
2. `frete.php` checa `MelhorEnvio::getStatus()`; se não estiver `ok`/`expira_em_breve`, retorna HTTP 503 sem chamar a API
3. Valida CEP (8 dígitos) e busca produto no banco (com `peso`, `largura`, `altura`, `comprimento`)
4. Consulta `cache_cotacoes`; se hit válido (< 12h), retorna sem chamar a API
5. `MelhorEnvio::calcularFrete()` chama `POST /api/v2/me/shipment/calculate` com headers obrigatórios
6. Em 401, renova token via `POST /oauth/token` com `refresh_token`, persiste no banco e repete uma vez
7. Serviços com campo `error` são filtrados; resultado ordenado por preço (empate → menor prazo)
8. Resposta normalizada gravada no cache e retornada:
```json
{ "ok": true, "cep": "01310-100", "servicos": [
  { "id": 1, "nome": "PAC", "transportadora": "Correios",
    "logo": "https://...", "preco": 23.90, "prazo_min": 5, "prazo_max": 8 }
], "cache": false }
```

### Campos de configuração (`backend/config/melhorenvio.php`)
- `MELHORENVIO_BASE_URL` — `https://sandbox.melhorenvio.com.br` (dev) / `https://www.melhorenvio.com.br` (prod)
- `MELHORENVIO_CLIENT_ID`, `MELHORENVIO_CLIENT_SECRET` — do app no painel Melhor Envio
- `MELHORENVIO_REDIRECT_URI` — URL de callback OAuth2 (deve ser idêntica à cadastrada no app)
- `MELHORENVIO_SCOPES` — `'shipping-calculate'` (adicionar outros scopes requer nova autorização)
- `MELHORENVIO_USER_AGENT` — obrigatório pela API: `"PSPart - Partes e Peças Automação (filipe@pentasis.com.br)"`
- `LOJA_CEP_ORIGEM` — `'18556322'` (CEP de expedição da loja)
- `MELHORENVIO_TOKEN`, `MELHORENVIO_REFRESH_TOKEN` — **legado, não utilizados**; token gerenciado via OAuth na tabela `melhorenvio_auth`

### OAuth2 e renovação de token
- Fluxo `authorization_code`: admin acessa `melhorenvio-conectar.php` → autoriza no painel ME → callback persiste tokens no banco
- **Reconexão em dev exige acesso via ngrok, nunca `localhost`:** o app cadastrado no painel ME (sandbox) tem `redirect_uri` = URL pública do ngrok; se o admin estiver logado via `localhost:8000` ao clicar em "Reconectar", o cookie de sessão PHP (domínio `localhost`) não é enviado quando a ME redireciona de volta pro domínio ngrok — a sessão chega vazia no callback, o state CSRF falha (ou `_auth.php` força novo login) e o token **não é salvo**, mesmo a tela do callback aparentando sucesso (o token antigo, ainda não expirado, mascara a falha). Sintoma: navegação passa por `index.php` (login) no meio do fluxo. Sempre acessar `https://<subdominio>.ngrok-free.dev/backend/admin/...` (não `localhost`) antes de clicar em Reconectar, e validar com `sqlite3 database.db "SELECT expires_at, updated_at FROM melhorenvio_auth;"` que `updated_at` bateu com o horário real da reconexão
- `access_token`: validade 30 dias · `refresh_token`: validade 45 dias
- **Fonte única:** tabela `melhorenvio_auth` (linha única, `id = 1`) — sem fallback para JSON ou constantes
- `getValidToken()`: lê do banco; renova proativamente se expira em < 1 dia; lança `RuntimeException('integracao_nao_conectada')` se banco estiver vazio ou `requer_reautorizacao = 1`
- Em 401 na chamada à API, `_renovarToken()` tenta refresh e persiste novo par com `expires_at`; em falha marca `requer_reautorizacao = 1`
- `getStatus()` (static): retorna `ok | expira_em_breve | expirado | requer_reautorizacao | nao_configurado | sem_tabela` — usado pelo painel admin e por `frete.php` antes de chamar a API
- **`me_tokens.json` aposentado** — não existe mais no projeto

### Frontend (script.js — métodos na classe `App`)
| Método | Função |
|---|---|
| `setupFrete()` | Event delegation: máscara CEP, Enter, botão Calcular, pré-fill de CEP salvo |
| `_calcularFrete(prodId, cep, resultEl, btnEl)` | Fetch + loading state — para modais de produto |
| `_calcularFreteCheckout(cep)` | Idem para `#frete-resultado-checkout` — disparado após ViaCEP |
| `_renderFrete(servicos, cep, containerEl)` | Monta HTML com logo, nome, preço e "Chegará entre X e Y" |
| `_calcularEaster(year)` | Algoritmo gregoriano anônimo para data da Páscoa |
| `_feriadosBR(year)` | Feriados nacionais fixos + Sexta-Feira Santa + Corpus Christi |
| `_adicionarDiasUteis(dataInicio, dias)` | Dias úteis → data real, pulando fins de semana e feriados BR |

- CEP digitado em modal de produto salvo em `localStorage('psp_cep_entrega')` e pré-preenchido ao abrir outros modais
- Checkout: `#frete-resultado-checkout` aparece automaticamente após ViaCEP preencher o endereço; limpo ao reabrir o modal
- Componente `.frete-calc` gerado dinamicamente em `renderProducts()` no `col-md-6` de cada modal de produto
- Dark mode coberto em `style.css` nas classes `.frete-*`

### Admin — campos de dimensão
- `produto-novo.php` e `produto-editar.php` exibem bloco "Dimensões para frete" (peso kg, largura/altura/comprimento cm)
- Validação: todos devem ser numéricos e > 0
- Produtos existentes receberam defaults via `ALTER TABLE` (0.5 kg, 15×10×20 cm)

## Módulo Dashboard (`backend/admin/dashboard.php`)

- **Filtro por período** via GET: padrão = mês atual; `?mes=YYYY-MM` (mês), `?de=YYYY-MM-DD&ate=YYYY-MM-DD` (intervalo — sobrepõe o mês), `?tudo` (sem recorte). Datas inválidas caem no mês atual sem erro.
- **6 métricas** server-side sobre o período filtrado: Total de Pedidos, Receita Aprovada, Ticket Médio, Pedidos Pendentes, Pedidos Aprovados, Recusados/Cancelados
- **Seleção de métricas**: botão "Métricas" (`fa-sliders`) abre dropdown com checkboxes; toggle via `wrap.style.display` (sem `display:none` no stylesheet); preferência em `localStorage('psp_dashboard_cards')`; IDs dos wrappers: `cw-{nome}` (ex: `cw-receita`, `cw-ticket-medio`)
- **Tabela de pedidos do período**: LIMIT 20, badges e labels em português, estado vazio com mensagem amigável
- **Estoque baixo**: seção independente, não filtrada por período
- Helper PHP `periodoWhere(?string $inicio, ?string $fim, string $extra = ''): array` — retorna `[$whereSql, $params]` para compor queries com prepared statements

## Módulo Admin de Pedidos

- `backend/admin/pedidos.php` — listagem com filtro de status, busca por nome/e-mail e coluna "Envio" com badge `Transportadora · Serviço` + ícone de relógio quando rastreio ainda não foi inserido; JOIN com `order_tracking` via `CAST(p.id AS TEXT)`
- `backend/admin/pedido-detalhe.php` — exibe dados do comprador, itens (com `codigo_interno`, fallback `—`), status e card "Entrega" (bloco escolha do cliente + bloco rastreamento atual); botões "Imprimir Ficha" e "Enviar Ficha por E-mail"; link para `tracking-admin.php`
- `backend/admin/pedido-ficha.php` — ficha de separação para impressão; acesso via `?id={pedido_id}`; tabela Código Interno | Produto | Quantidade | ✓ Sep.; `@media print` oculta chrome do admin; `window.print()` dispara no load
- `backend/admin/tracking-admin.php` — única interface para `order_tracking`; inclui `p.status AS pedido_status` no JOIN; pedidos com `pedido_status` em `recusado/cancelado/reembolsado/contestado` exibem badge vermelho "Recusado/Cancelado" (não o status de envio) e linha destacada com `table-danger`; modal exibe bloco informativo da escolha do cliente (read-only) + itens do pedido; campo "Transportadora" pré-preenchido com `chosen_carrier` (editável pelo admin)

## Módulo Transportadora Escolhida pelo Cliente (Fase 12)

### Schema — novas colunas em `order_tracking`
| Coluna | Tipo | Observação |
|---|---|---|
| `chosen_carrier` | TEXT | Transportadora escolhida no checkout (ex.: `Correios`) — imutável |
| `chosen_service` | TEXT | Serviço escolhido (ex.: `PAC`, `SEDEX`) — imutável |
| `chosen_service_id` | INTEGER | ID do serviço no Melhor Envio — imutável |
| `shipping_price` | REAL | Valor do frete pago — imutável |
| `shipping_deadline` | INTEGER | Prazo em dias úteis (`prazo_max`) — imutável |
| `destination_cep` | TEXT | CEP de destino (8 dígitos) — imutável |

- A coluna `carrier` existente permanece como **transportadora efetiva do despacho** (editável pelo admin)
- Migração: `migrations/migrate_shipping_choice.php` (já executada)

### Fluxo de captura
1. `_calcularFreteCheckout()` chama `_renderFreteCheckout()` — exibe opções como itens clicáveis com ícone ✓; primeira opção pré-selecionada; seleção gravada em `this._selectedFrete`
2. `_doCheckoutSubmit()` envia `frete_escolhido` junto ao body de `pedidos.php`
3. `backend/api/pedidos.php` valida o preço contra `cache_cotacoes` (tolerância R$0,10); se válido, faz `INSERT INTO order_tracking ... ON CONFLICT DO UPDATE` apenas nas colunas `chosen_*` e `carrier` (pré-preenche com `chosen_carrier`)
4. `webhook.php` e `processar-pagamento.php` continuam usando `INSERT OR IGNORE` — como o registro já existe, o ignore preserva os dados de escolha

### Distinção importante
- **`chosen_*`** = escolha informativa do cliente, gravada no checkout, nunca sobrescrita
- **`carrier`** = transportadora confirmada pelo admin no despacho, inicializa com `chosen_carrier` mas pode ser ajustada

### Validação anti-adulteração
- Backend verifica o serviço escolhido contra o cache de cotações (`cache_cotacoes`) pelo mesmo `produto_id + cep`
- Aceita diferença de até R$0,10 (arredondamento); sem cache válido, aceita a escolha se os campos obrigatórios (`id`, `transportadora`, `nome`) estiverem presentes

### API `tracking.php` (GET)
Retorna adicionalmente: `chosen_carrier`, `chosen_service`, `shipping_price`, `shipping_deadline`, `destination_cep`

### Frontend — `_renderFreteCheckout()` vs `_renderFrete()`
- `_renderFrete()` — modais de produto, exibição informativa (inalterado)
- `_renderFreteCheckout()` — modal de checkout, opções clicáveis com seleção visual; classe `.frete-opcao--selec` + `.frete-opcao--ativo`; ícone `.frete-selec-check` visível apenas na opção ativa

## Módulo Frete no Total + CEP Persistente (Fase 14)

> Histórico das mudanças em relação à Fase 12: ver CHANGELOG.md

### Fluxo de checkout atualizado

1. Modal abre → CEP pré-preenchido do `localStorage` (se houver) → `_buscarCep()` dispara automaticamente
2. ViaCEP preenche endereço → `_calcularFreteCheckout()` busca cotações → exibe opções + botão "Alterar CEP"
3. Primeira opção selecionada → `_updateCheckoutTotal()` → `#checkout-resumo` aparece → botões habilitados
4. Usuário pode trocar opção (atualiza total) ou clicar "Alterar CEP" (limpa frete, desabilita botões)
5. Ao submeter: `pedidos.php` grava `total = subtotal + frete`; `pagamento.php` envia produto + frete como dois itens ao MP

## Timezone

- **Fuso horário:** `America/Sao_Paulo` em todas as camadas PHP
- `backend/api/_core.php` — `date_default_timezone_set('America/Sao_Paulo')` para todos os endpoints da API
- `backend/admin/_auth.php` — `date_default_timezone_set('America/Sao_Paulo')` para todas as páginas admin
- **`CURRENT_TIMESTAMP` do SQLite é sempre UTC** — todos os inserts/updates que gravam timestamps usam PHP `date('Y-m-d H:i:s')` como parâmetro PDO (não `CURRENT_TIMESTAMP`) para garantir o fuso correto
- `criado_em` de pedidos: passado explicitamente no INSERT de `pedidos.php`
- `updated_at` de `order_tracking`: passado como `:now` em `tracking.php`, `webhook.php` e `processar-pagamento.php`

## Emissão de Etiquetas Melhor Envio (Fase 16)

### Arquivos
| Arquivo | Função |
|---|---|
| `backend/config/loja.php` | Dados do remetente + flag `LOJA_DADOS_REAIS` (gitignored) |
| `backend/melhorenvio/shipment.php` | Serviço: `meCartAdd`, `meCheckout`, `meGenerate`, `mePrint`, `meTracking` |
| `backend/admin/etiqueta-action.php` | Endpoint AJAX admin — recebe `{action, pedido_id}`, chama o serviço |
| `backend/admin/pedido-detalhe.php` | Card "Etiqueta de Envio ME" com estado, botões e modal de confirmação de checkout |

### Fluxo das 4 etapas
1. **Carrinho** (`meCartAdd`) — `POST /api/v2/me/cart` — salva `order_tracking.melhorenvio_order_id`
2. **Checkout** (`meCheckout`) — `POST /api/v2/me/shipment/checkout` — **debita saldo**; bloqueado se `LOJA_DADOS_REAIS !== true`; modal de confirmação explícita no admin
3. **Geração** (`meGenerate`) — `POST /api/v2/me/shipment/generate` — assíncrono
4. **Impressão** (`mePrint`) — `POST /api/v2/me/shipment/print` — salva `order_tracking.label_url`; inclui delay de 5s; tenta capturar `tracking_code` via `meTracking()`

### Colunas adicionadas em `order_tracking`
- `melhorenvio_order_id TEXT DEFAULT NULL` — ID do envio no ME (retornado pelo cart)
- `label_url TEXT DEFAULT NULL` — link/PDF da etiqueta (retornado pelo print)

### Regras críticas
- `meCheckout()` aborta com mensagem clara se `LOJA_DADOS_REAIS !== true`
- `meCartAdd()` é idempotente: se `melhorenvio_order_id` já existe, retorna estado atual sem nova chamada
- `meTracking()` só promove `order_tracking.status = 3` se `statusPermiteRastreamento($pedidoStatus) === true` — nunca sobrescreve pedido cancelado/recusado
- Campo `document` (CPF/CNPJ do comprador) omitido no `to` quando ausente (aceito no sandbox); comentário no código indica ALTER TABLE pontual para produção
- Token: sempre via `MelhorEnvio::request()` (novo método público) com retry automático em 401
- Scopes válidos (aceitos pelo OAuth ME sandbox): `shipping-calculate shipping-checkout shipping-generate shipping-print shipping-tracking` — `shipping-cart` é um nome inválido na API ME; operações de carrinho requerem habilitação adicional do app junto ao suporte ME
- Log em `logs/melhorenvio-etiqueta.log`

## Helper de Status (`backend/helpers/status.php`)

- `mpStatusParaInterno(string $mpStatus): string` — fonte única do mapa MP→interno (`approved→aprovado`, `rejected→recusado`, etc.); usado em `webhook.php` e `processar-pagamento.php`
- `statusPermiteRastreamento(string $pedidoStatus): bool` — retorna `true` apenas para `aprovado` e `em_processamento`; usado em `pedido-detalhe.php` para não exibir "Em Preparação" quando pagamento está recusado/cancelado
- `derivarStatusPedido(array $pedido, ?array $tracking): array` — deriva, somente leitura, o status normalizado de um pedido (status de pagamento + envio + rastreio); espelha a precedência já usada em `tracking.php`; usada pelo agente conversacional (`backend/agente/ferramentas.php`) para montar a resposta de `consultar_pedido`

## Módulo de Pagamento — Arquivos Backend

| Arquivo | Função |
|---|---|
| `backend/api/pagamento.php` | Cria preference no MP (usada por ambos os modos); retorna `preference_id` e `init_point` |
| `backend/api/processar-pagamento.php` | Processa pagamento via MP Payments API (modo Bricks); recebe `{ pedido_id, form_data }` do frontend; `transaction_amount` sempre vem do banco (não do cliente) |
| `backend/api/public-config.php` | Retorna `{ mp_public_key }` para o frontend inicializar o SDK do MP com segurança |

### SDK do MP no frontend
- Carregado via CDN: `https://sdk.mercadopago.com/js/v2`
- Inicializado em `_renderBrick()` com a Public Key de teste (`backend/api/public-config.php`)
- Tema automático: `dark` se `body.dark-mode` estiver ativo, `default` caso contrário
- Locale: `pt-BR`

### Credenciais do MP (desenvolvimento vs produção)
- **Teste:** Public Key e Access Token do app de teste do MP (`APP_USR-` ou `TEST-`)
- **Produção:** trocar ambas as chaves em `backend/config/mercadopago.php` antes do deploy
- **Importante:** Public Key e Access Token devem ser do **mesmo ambiente** (ambas teste ou ambas produção) — misturar resulta em erro 401 `"Unauthorized use of live credentials"`
- **ngrok em Windows:** antivírus com inspeção SSL (Kaspersky, Avast, Bitdefender etc.) bloqueia a autenticação do ngrok com erro `x509: certificate is not valid for any names` — desativar inspeção SSL ou o antivírus temporariamente durante desenvolvimento

## Decisões técnicas
- **Backend:** PHP (familiaridade do desenvolvedor)
- **Banco de dados:** SQLite (arquivo `.db` junto ao projeto, PDO nativo, sem driver extra, compatível com hospedagem compartilhada)
- **Pagamentos:** Mercado Pago **Checkout Pro** (redirect) + **Checkout Bricks** (inline), SDK `mercadopago/dx-php` via Composer
- **TypeScript:** descartado — JS puro suficiente para o escopo do projeto
- **SQL Server:** descartado — requer driver `pdo_sqlsrv` e servidor dedicado, inviável em hospedagem compartilhada
- **`init_point` em vez de `sandbox_init_point`:** evita `ERR_TOO_MANY_REDIRECTS` no subdomínio `sandbox.mercadopago.com.br`; com credenciais de teste o pagamento ainda é processado como teste
- **`auto_return` removido:** causava loop de redirect no sandbox do MP
- **`back_url.success` aponta para `acompanhar.html`:** token incluído na URL para o usuário ver o pedido sem login imediatamente após pagar
- **Webhook:** suporta formato v1 (`type/data.id`) e formato IPN antigo (`topic/resource`) — MP pode enviar qualquer um dos dois
- **E-mail via `mail()` nativo:** sem dependência externa; compatível com hospedagem compartilhada; não funciona em localhost
- **Token de acompanhamento:** `bin2hex(random_bytes(16))` — 32 chars hex, gerado na criação do pedido, armazenado em `pedidos.token_acompanhamento`; permite acesso direto sem login
- **PIX em sandbox:** não aparece no Checkout Pro de teste — limitação do MP; validar em produção com R$ 0,01
- **Campo `imagem` (legado):** mantido na tabela `produtos` para compatibilidade com produtos do seed; não é mais editável pelo admin — gerenciamento exclusivo via `produto_imagens`
- **Cache busting de imagens no admin:** `?v=filemtime()` — parâmetro muda apenas quando o arquivo no disco é alterado
- **EasyMDE + marked.js:** usados respectivamente no admin (edição Markdown) e no frontend (renderização); ambos via CDN, sem build process
- **SortableJS:** drag-and-drop para reordenação de imagens no admin; CDN carregado em `produto-editar.php` e na fase 2 de `produto-novo.php`
- **Validação de PDF por magic bytes:** `finfo_open` retorna MIME incorreto em algumas builds PHP/Windows; substituído por leitura dos primeiros 4 bytes (`%PDF`)
- **Categorias dinâmicas:** tabela `categorias` é a fonte de verdade; filtros do catálogo e selects do admin sempre refletem o banco sem alteração de código
- **Filtro com event delegation:** `setupProductFilter()` usa um único listener no container `#product-filters` e consulta `.product-col` no momento do clique — evita NodeList stale após re-render
- **Migrations via sqlite3 CLI:** colunas adicionadas após o setup inicial foram aplicadas diretamente com `sqlite3 database.db "ALTER TABLE ..."` — não requerem re-executar setup.php
- **Checkout Bricks:** Payment Brick inicializado com `preferenceId` (para pré-carregar valor e métodos da preference) + `payer.email` + `amount`; `transaction_amount` sobrescrito no backend pelo valor do banco via `array_merge($formData, [...])`
- **Modal pós-Bricks (`#bricksResultModal`):** exibido após `onSubmit` do Brick resolver; fecha o modal de checkout (desmontando o Brick via `hidden.bs.modal`) e abre o modal de resultado 450ms depois; exibe nome/e-mail do comprador armazenados em `_buyerName`/`_buyerEmail`
- **Acompanhar.html com busca dupla:** acesso via token na URL (e-mail + modal pós-pagamento) ou via formulário manual (pedido_id + e-mail); sem token na URL o formulário fica visível por padrão (não usa `style="display:none;"` no HTML — o JS esconde apenas quando há token)
- **Timeline unificada em `acompanhar.html`:** 6 etapas combinam status do pedido (`pedidos.status`) e status de entrega (`order_tracking.status`) em uma única barra de progresso — substituiu a dupla timeline anterior (status badge separado + rastreamento separado)
- **Frete via Melhor Envio — proxy PHP obrigatório:** token OAuth2 nunca vai ao frontend; `backend/api/frete.php` recebe só `produto_id` + `cep_destino` e devolve resposta normalizada
- **Token do Melhor Envio em `melhorenvio_auth` (banco):** fonte única após Fase 13; sem fallback para JSON ou constantes; ausência de token resulta em HTTP 503 no endpoint de frete, nunca em chamada à API com token vazio
- **Cache de cotações em `cache_cotacoes`:** chave `md5("v1:{produto_id}:{cep}")`, TTL de 12h verificado em runtime com `INSERT OR UPDATE`; evita chamadas repetidas e respeita limites da API
- **Dias úteis → data calendário:** `_adicionarDiasUteis()` pula sábados, domingos, feriados nacionais fixos + Sexta-Feira Santa e Corpus Christi (calculados via algoritmo de Páscoa gregoriano); cobre ano atual e seguinte
- **CEP salvo em `localStorage('psp_cep_entrega')`:** persiste entre visitas; pré-preenchido ao abrir modais de produto e ao reabrir o checkout (substitui `sessionStorage('psp_frete_cep')` da Fase 11)
- **Frete no checkout calculado automaticamente:** disparado por `_buscarCep()` após ViaCEP com sucesso — sem botão extra para o usuário; `#frete-resultado-checkout` usa `style="display:none;"` inline (padrão do projeto para JS mostrar/esconder)
- **Escolha de frete no checkout — seleção visual:** `_renderFreteCheckout()` é separado de `_renderFrete()` (modais de produto); opções clicáveis com `.frete-opcao--selec`; primeira opção pré-selecionada automaticamente; seleção gravada em `this._selectedFrete` e enviada como `frete_escolhido` ao criar o pedido
- **Escolha de frete — persistência antecipada:** `order_tracking` é criado em `pedidos.php` (no checkout, antes do pagamento) e não apenas na aprovação do pagamento; `webhook.php` e `processar-pagamento.php` continuam com `INSERT OR IGNORE` que não sobrescreve o registro já existente
- **Colunas `chosen_*` imutáveis pelo admin:** o POST de `tracking.php` usa `ON CONFLICT DO UPDATE` apenas em `status`, `tracking_code`, `carrier`, `notes` e `updated_at` — as colunas de escolha do cliente nunca aparecem no SET do upsert admin
- **Campo `carrier` pré-preenchido:** ao criar o registro de rastreio em `pedidos.php`, `carrier` recebe o valor de `chosen_carrier`; o admin pode alterar em `tracking-admin.php` sem afetar `chosen_carrier`
- **Frete somado ao total do pedido:** validação do frete ocorre antes da transação em `pedidos.php`; `total = subtotal + fretePrice` é gravado em `pedidos.total` e repassado ao Mercado Pago como item extra ("Frete — {transportadora} {serviço}") na preference
- **Pagamentos sem frete bloqueados:** botões de pagamento iniciam `disabled`; habilitados apenas quando `_selectedFrete` está definido; aviso "Calcule o frete para continuar" exibido via `_updatePaymentBtns()`
- **Bloco de resumo no checkout (`#checkout-resumo`):** aparece após frete selecionado; exibe subtotal + linha de frete + total; atualizado por `_updateCheckoutTotal()` a cada mudança de quantidade ou opção de frete
- **Botão "Alterar CEP" no checkout:** renderizado dentro de `_renderFreteCheckout()` na label do CEP; chama `_alterarCepCheckout()` que limpa o frete, desabilita botões e foca o campo CEP para re-digitação

## Placeholders pendentes (necessários antes do deploy)
- `SEU_NUMERO` — WhatsApp (2 ocorrências: botão hero + botão flutuante)
- Ícones PWA reais (192×192 e 512×512 PNG) para `manifest.json`
- Texto da seção "Sobre Nós" com história real da empresa
- Preços reais dos produtos (definidos via admin)
- Credenciais de produção do MP em `backend/config/mercadopago.php` (trocar tokens TEST- pelos de produção)
- `MP_BASE_URL` atualizado com domínio real (sem ngrok)
- Senha do admin trocada (padrão de dev documentado no gerenciador de senhas local)
- Credenciais de produção do Melhor Envio em `backend/config/melhorenvio.php` (`MELHORENVIO_BASE_URL`, `CLIENT_ID`, `CLIENT_SECRET`, `REDIRECT_URI`) e reconectar via Admin → Integrações após trocar as credenciais
- Dimensões reais dos produtos cadastradas no admin (defaults de 0.5 kg / 15×10×20 cm são placeholders)

## Acessos do ambiente de desenvolvimento

### Servidor local
```bash
cd "D:\Aréa de Trabalho\DEV\PSP-Website"
php -S localhost:8000
```

| URL | Descrição |
|---|---|
| `http://localhost:8000` | Site principal |
| `http://localhost:8000/setup.php` | Setup do banco (senha: ver gerenciador de senhas local) |
| `http://localhost:8000/backend/admin/` | Área administrativa |

### Admin
| Campo | Valor |
|---|---|
| E-mail | `admin@pspart.com.br` |
| Senha | ver gerenciador de senhas local (**trocar a senha padrão antes do deploy**) |
| Alterar senha | Menu lateral → **Senha** |

### ngrok (Mercado Pago em dev local)
O Mercado Pago exige uma URL pública acessível para `back_urls` e `notification_url` do webhook. Em desenvolvimento local, usar ngrok para expor o servidor.

**Passos:**
1. Subir o servidor: `php -S localhost:8000`
2. Em outro terminal: `ngrok http 8000`
3. Copiar a URL HTTPS gerada (ex: `https://xxxx.ngrok-free.app`)
4. Atualizar `MP_BASE_URL` em `backend/config/mercadopago.php`:
```php
define('MP_BASE_URL', 'https://xxxx.ngrok-free.app');
```

> A URL muda a cada vez que o ngrok é reiniciado — lembrar de atualizar o arquivo.
> Conta ngrok configurada para: Filipe Rodrigues dos Santos (Plan: Free)

### Banco de dados
- Arquivo: `database.db` na raiz do projeto
- Visualizar: abrir no **DB Browser for SQLite**

## Módulo Agente Conversacional (Fase 17)

### Arquivos
| Arquivo | Função |
|---|---|
| `backend/config/loja.php` | Constantes do agente: `AGENTE_MODO_MOCK`, `AGENTE_PROVEDOR`, `AGENTE_MODELO`, `AGENTE_MAX_TOKENS`, `AGENTE_TIMEOUT`, `AGENTE_MAX_TURNOS_TOOLUSE`, `AGENTE_BUSCA_LIMITE_MAX`, `ANTHROPIC_API_KEY`, `GROQ_API_KEY`, `GROQ_MODELO` — **gitignored**, nunca commitar |
| `backend/agente/ferramentas.php` | Definições das 3 tools (`getDefinicoesFerramentas()`) + dispatcher (`executarFerramenta()`) + implementações read-only |
| `backend/api/agente.php` | Endpoint `POST /backend/api/agente.php` — modo mock, loop de tool use, chamada à API, log |
| `assets/css/agente.css` | Estilos do widget usando variáveis CSS existentes |
| `assets/js/agente.js` | Classe `AgenteChat` — botão flutuante, painel de chat, fetch ao endpoint, histórico em memória |
| `logs/agente.log` | Log de interações (runtime, gitignored) |

### Ferramentas (todas somente leitura — apenas SELECT)
- **`buscar_produtos(termo, limite=5)`** — `SELECT` com `LIKE` em nome/descrição/categoria/código; filtra `ativo = 1`; limite máximo `AGENTE_BUSCA_LIMITE_MAX`
- **`calcular_frete(produto_id, cep_destino)`** — busca produto no banco, chama `MelhorEnvio::calcularFrete()` (instância); captura `RuntimeException` e devolve fallback honesto; **não debita carteira**
- **`consultar_pedido(id_pedido, email_cliente, token_acompanhamento)`** — guarda de titularidade obrigatória (e-mail via `mb_strtolower` ou token via `hash_equals`); retorno sem PII (sem e-mail/telefone/endereço); usa `derivarStatusPedido()`

### Helper `derivarStatusPedido()` (`backend/helpers/status.php`)
Ver seção "Helper de Status" para a definição completa. Adicionado na Fase 17 para centralizar a leitura de status de pedido usada por `consultar_pedido`.

### Suporte a múltiplos provedores
- Protocolo interno **sempre em formato Anthropic** (messages, tool use, stop_reason)
- `AGENTE_PROVEDOR = 'anthropic'` → chama `https://api.anthropic.com/v1/messages` diretamente
- `AGENTE_PROVEDOR = 'groq'` → traduz Anthropic→OpenAI, envia ao Groq, traduz resposta de volta; retry automático em `tool_use_failed`; modelo `llama3-groq-70b-8192-tool-use-preview`
- Trocar provedor: mudar só `AGENTE_PROVEDOR` em `loja.php`

### Abertura automática
- Painel abre sozinho após **10 segundos** da primeira visita (`setTimeout` de `10000ms`)
- Uma vez por sessão — controle via `sessionStorage('psp_agente_auto_aberto')`
- Se o usuário já abriu manualmente antes dos 10s, o timeout não faz nada

### Modo mock
- `AGENTE_MODO_MOCK = true` → não chama API; loga `MOCK-SKIP`; retorna resposta fixa
- Nasce `true` (seguro por padrão, igual a `LOJA_DADOS_REAIS`)

### CSS bug crítico (herdado)
- Visibilidade do widget via `element.style.display` no JS — nunca `#id { display:none }` no CSS

## .gitignore — o que é ignorado
- `database.db` — banco de dados runtime
- `img/prod_*` — imagens de produtos enviadas pelo admin
- `docs/` — data sheets PDF enviados pelo admin
- `config/` — diretório de config gerado em runtime na raiz
- `backend/config/mercadopago.php` — credenciais sensíveis do MP
- `backend/config/melhorenvio.php` — credenciais sensíveis do Melhor Envio
- `backend/config/loja.php` — credenciais do agente (Anthropic/Groq) e dados da loja — adicionado na Fase 17
- `backend/config/me_tokens.json` — aposentado (Fase 13); tokens agora em `melhorenvio_auth` no banco
- `vendor/` — dependências Composer
- `logs/` — logs do webhook e do agente

## Seção Pagamentos & Segurança (Fase 18)

- `<section id="pagamentos-seguranca">` inserida imediatamente antes do `<footer>` em `index.html`
- **Bandeiras:** logos SVG flat via `cdn.jsdelivr.net/npm/payment-icons@1.1.0/min/flat/` (Visa, Mastercard, Elo, Amex) + `cdn.jsdelivr.net/gh/aaronfagan/svg-credit-card-payment-icons@main/flat/` (Hipercard)
- **Badges estilizados:** Pix (`#00b133`), Boleto (`#444`), Mercado Pago (`#009ee3`) — sem imagem externa
- **Bloco de segurança:** HTTPS, PCI-DSS (MP), sem armazenamento de cartão, LGPD — copy factualmente verificável
- Logos 54×38px (`object-fit: contain`); CSS em `style.css` com variáveis existentes; dark mode coberto
- Sem JS, sem dependências novas, sem `display:none` no CSS

## Preferências
- Abordagem não agressiva: melhorar sem reescrever seções inteiras
- Mudanças centralizadas no CSS (não inline styles)
- Comunicação em português (pt-BR)
- Sem commit até solicitação explícita
