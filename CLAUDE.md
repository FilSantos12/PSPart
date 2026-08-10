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
- Bootstrap 5.3, Font Awesome 6.4, AOS 2.3 (CDN — link ainda presente, mas **não é mais usado no catálogo de produtos**; sem animação nos cards desde o refresh visual)
- Google Fonts: Poppins (corpo) + Montserrat (display, pesos 700/800) + **JetBrains Mono** (código interno, eyebrow de categoria) — um único `<link>` estende os três, sem request novo por fonte
- **marked.js** (CDN) — renderiza Markdown da Especificação Técnica no frontend
- FormSubmit.co (formulário sem backend) → filipe@pentasis.com.br
- Google Analytics GA4: `G-XQBJ002YQC` com Consent Mode v2 (LGPD)

> Histórico do redesign visual/estrutural: ver "Módulo Cards de Produto", "Módulo Header Fixo + Reordenação de Seções" e "Módulo Carrinho (Fase 1)" mais abaixo. O que segue nesta seção já reflete o estado atual.

## Estrutura da página
Ordem real do miolo (pós-reordenação): **hero → produtos → diferenciais → sobre → contato → pagamentos-seguranca → footer**. IDs de seção não mudam mesmo quando a ordem visual muda (âncoras/JS dependem deles).

1. **Header** (`<nav id="mainNavbar" class="custom-navbar">`) — `position: fixed` no topo (não mais `sticky-top`), sempre visível durante a rolagem; Início | Sobre | Produtos | Contato + toggle dark mode + **ícone de carrinho com badge** (`#cartToggle`, fora do `.collapse` — visível mesmo com o menu mobile fechado). Classe `.storefront` no `<body>` escopa a tipografia de heading e o `padding-top` compensatório só à home (não vaza para `acompanhar.html`/admin)
2. `#inicio` — Hero: gradiente de marca original + overlay de malha "blueprint" sutil; headline de benefício (não "Bem-vindo à PSPart"); **busca proeminente** que alimenta a busca já existente da seção de produtos (sem lógica duplicada); chips de confiança estruturais e verdadeiros (frete no checkout, pagamento MP, pedido rastreável) — **sem métricas inventadas/não verificadas** (o antigo bloco 13+/10+/50+/100% foi removido)
3. `#produtos` — cards dinâmicos (renderizados via API) + filtro de categorias dinâmico + toolbar de busca/ordenação/disponibilidade + modais de detalhe; ver "Módulo Cards de Produto" para o layout atual
4. Diferenciais — 4 cards (sem animação AOS — só o cabeçalho da seção anima)
5. `#sobre` — Sobre Nós (métricas 10+/50+/6+ ainda presentes, não removidas — não confundir com as do hero, que foram removidas)
6. `#contato` — Formulário + info de contato
7. `#pagamentos-seguranca` — faixa de confiança (bandeiras, Pix/Boleto/MP, bloco de segurança) — posição fixa antes do footer, não reordenada
8. Footer — marca + link "Acompanhar Pedido" (`acompanhar.html`) + back-to-top; **sem ícones de rede social** (removidos por não terem URL real); botão WhatsApp flutuante **comentado no HTML**, pendente de número real
9. **Drawer do carrinho** (`#cartDrawer` + `#cartBackdrop`) — painel lateral direito, ver "Módulo Carrinho"
10. **Modal de Checkout item único** (`#checkoutModal`, `modal-lg`) — dados do comprador + endereço com ViaCEP + controle de quantidade. **Não é mais aberto pelo botão "Comprar"** desde o Patch B — máquina de estado mantida dormente (não reconectada; o carrinho tem seu próprio modal, `#cartCheckoutModal`, ver "Módulo Carrinho"). Suas funções de Brick (`_renderBrick`/`_showBricksResult`/`_showPaymentError`/`_unmountBrick`) foram parametrizadas na Fase 2c e são reaproveitadas pelo `#cartCheckoutModal` sem duplicar a lógica de tokenização
11. **Modal de Redirecionamento MP** (`#paymentRedirectModal`) — spinner enquanto redireciona
12. **Lightbox de imagem** (`#lightboxOverlay`) — overlay customizado para ampliar imagens

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
- `medirAlturaHeader()` — mede `#mainNavbar.offsetHeight` (nunca chuta px fixo) e publica em `--header-h` no `:root`; recalcula em `resize`, ao abrir/fechar o menu mobile (`shown.bs.collapse`/`hidden.bs.collapse`) e quando as fontes terminam de carregar (`document.fonts.ready`); chamado bem no início de `init()`, antes de tudo, para o primeiro paint já ter o offset certo
- `setupScrollHandlers()` — scroll spy + back-to-top unificados com `requestAnimationFrame`
- `updateActiveNavLink()` — decide o link ativo do menu lendo `--header-h` (+ ~20px de folga) via `getComputedStyle`, com fallback pro `offsetHeight` de `#mainNavbar`; **não usa mais o `100px` fixo antigo** (consolidado no Patch A/B para não dessincronizar da altura real do header)
- `setupThemeToggle()` — dark/light, persiste em `localStorage('psp_theme')`
- `setupHeroSearch()` — busca do hero (`#heroSearchForm`); no submit, popula `#product-search` (a busca já existente da toolbar), dispara um evento `input` real nela e rola até `#produtos` — **não duplica a lógica de filtro**, só aciona a que já existe
- `setupCart()` / métodos `_cart*` — módulo do carrinho; ver "Módulo Carrinho" para a lista completa
- `setupProductFilter()` — registra listener com **event delegation** no `#product-filters`; consulta `.product-col` no momento do clique (sem NodeList stale); chamado uma vez em `init()`; atualiza `this._activeCategory` e delega a visibilidade para `_applyProductVisibility()`
- `setupProductToolbar()` — listeners estáticos (não delegados, os controles já existem no HTML) para busca (`#product-search`), disponibilidade (`#product-instock`) e ordenação (`#product-sort`); ver seção "Módulo Produtos — Deep Link, Compartilhamento e Busca/Ordenação"
- `setupProductSharing()` — event delegation para `.btn-whatsapp-share`, `.btn-share-product` e `.btn-copy-code`
- `setupLGPD()` — banner de cookies, persiste em `localStorage('psp_lgpd_consent')`
- `setupContactForm()` → `submitForm()` — fetch para FormSubmit.co
- `setupCounters()` — Intersection Observer nos `.stat-number`; **hoje é código inerte** — o único bloco que usava essa classe (métricas do hero) foi removido; guard `if (counters.length === 0) return` evita erro, método mantido sem uso (a seção "Sobre" usa `.sobre-stat-number`, uma classe diferente, sem animação)
- `setupModalButtons()` — botão "Solicitar Orçamento" preenche formulário de contato
- `setupCheckout()` — **desde o Patch B, `.btn-buy` não abre mais este modal** (ver "Módulo Carrinho"); o método continua registrando os listeners internos do modal de item único (quantidade, CEP, submit por redirect) como código dormente — não reconectado. As funções de Brick que ele também registra (Bricks, `bricks-use-redirect`) foram generalizadas na Fase 2c e passaram a ser compartilhadas com `setupCartCheckout()`
- `setupImageLightbox()` — lightbox customizado para `img.card-img-top`, `img.carousel-img`, `img.modal-product-image`; **padrão de visibilidade de referência do projeto**: overlay sempre `display:flex` no CSS, show/hide real via classe `.active` alternando `opacity`/`pointer-events` (nunca `display`) — reaproveitado pelo backdrop do carrinho
- `renderProducts()` — busca categorias e produtos em paralelo (`Promise.all`); gera botões de filtro dinamicamente em `#product-filters`; gera cards e modais em `#products-grid` / `#product-modals`; card mostra `codigo_interno` em pílula mono (se preenchido, senão omitido — sem buraco no layout) e badge de disponibilidade de **3 estados** (`.stock-badge--ok/--warn/--req`, ponto colorido, sobreposto no topo do thumb — ver "Módulo Cards de Produto"); desabilita botão se `estoque = 0` (agora idêntico no caminho estático); sem `data-aos` (removido do template); em caso de falha na API, mantém o markup estático de fallback em `#products-grid` (6 produtos hardcoded, com os mesmos campos de código/estoque para consistência visual)
- `handleRetornoMP()` — detecta `?pagamento=recusado|pendente` na URL após retorno do MP; limpa o param com `history.replaceState`; exibe modal apropriado
- `_validateCheckoutForm()` — valida campos obrigatórios com `is-invalid`, foca no primeiro inválido
- `_doCheckoutSubmit(mode)` — fluxo do item único: valida, grava pedido (`pedidos.php`), cria preference; bifurca por `mode`: `'redirect'` abre `paymentRedirectModal` e redireciona; `'bricks'` esconde form/footer e renderiza o Brick. **Dormente desde o Patch B** — não é mais alcançável a partir do botão "Comprar" (o carrinho usa `_cartCheckoutSubmit()` + `pedido-carrinho.php`, não este método)
- `_renderBrick(preferenceId, email, amount, { containerId = 'bricks-container', sourceModalId = 'checkoutModal' } = {})` — **(Fase 2c: parametrizado)** busca Public Key em `public-config.php`, inicializa `MercadoPago` SDK com locale pt-BR e tema dark/light; cria Payment Brick no container informado (`bricks-container` para item único, `cart-bricks-container` para o carrinho); guarda `sourceModalId` em `this._brickSourceModalId` (usado por `_showBricksResult`/`_showPaymentError` pra saber qual modal fechar); no `onSubmit` chama `processar-pagamento.php` (sempre com `this._currentPedidoId`) e exibe `_showBricksResult()`. `onError` ignora `type` `'recoverable_error'` **e `'non_critical'`** (ex.: `no_payment_method_for_provided_bin` disparado enquanto o BIN do cartão ainda está incompleto) — só erros reais abrem o modal de falha
- `_showBricksResult(result)` / `_showPaymentError(message)` — fecham `this._brickSourceModalId` (não mais sempre `#checkoutModal`) e abrem `#bricksResultModal`, compartilhado pelos dois fluxos; três estados: `approved` (✅ ícone verde, botão "Acompanhar pedido"), `rejected` (❌ ícone vermelho, mensagem traduzida via `_traduzirStatusDetail()`), outros (⏳ ícone amarelo, "em análise")
- `_traduzirStatusDetail(detail)` — converte `status_detail` do MP (`cc_rejected_insufficient_amount` etc.) em mensagem legível em PT-BR para o modal de recusa
- `_unmountBrick({ formId = 'checkoutForm', footerId = 'checkout-footer', sectionId = 'bricks-section', containerId = 'bricks-container' } = {})` — **(Fase 2c: parametrizado)** desmonta instância do Brick, reseta estado (`_bricksInstance`, `_brickSourceModalId`, `_currentPedidoId`, `_currentToken`, `_currentInitPoint`); `formId`/`footerId` nulos (usado pelo carrinho) pulam a restauração de visibilidade de form/footer que não existem nesse contexto
- `_openCheckoutModal()` — chama `_unmountBrick()` (defaults do item único) antes de abrir para garantir estado limpo
- `_buscarCep()` — consulta ViaCEP e preenche campos de endereço automaticamente

## Fluxo de compra (carrinho — estado atual, pós B2a/B2b/Fase 2c)
1. Clicar em **"Comprar"** (ícone no card, dois caminhos) ou **"Comprar Agora"** (modal de detalhe) **adiciona o produto ao carrinho** — não abre checkout direto. Contador do header atualiza + toast de confirmação
2. Ícone de carrinho no header (`#cartToggle`) abre o **drawer lateral** com os itens, quantidade editável, CEP + frete agregado (B2a) — "Finalizar compra" só fica clicável com frete selecionado
3. **"Finalizar compra"** fecha o drawer e abre `#cartCheckoutModal` — passo 1 (comprador + número/complemento; endereço já vem do CEP do drawer). Ao confirmar, `_cartCheckoutSubmit()` chama `pedido-carrinho.php`, que cria (ou reaproveita, via `checkout_hash`) o pedido com `total = subtotal + frete` e devolve `pedido_id` + `init_point` + `preference_id`
4. **Passo 2 (Fase 2c)** — `_cartShowMetodoEscolha()` troca form pelo resumo de N itens e oferece os dois caminhos, sem criar um segundo pedido:

**Opção A — Pagar no Mercado Pago (redirect)**
- `_cartPagarRedirect()` primeiro checa `tracking.php?order_id=X` (guarda anti-dupla-cobrança do lado do cliente); se `pedido_status` já for `aprovado`/`em_processamento`, bloqueia com aviso em vez de redirecionar
- `window.location.href = init_point` (já obtido no passo 1, mesma preference — `criarOuObterPreferencePedido()` é idempotente)
- Pagamento processado no site do MP → webhook atualiza status no banco
- `aprovado` → redireciona para `acompanhar.html?pedido=X&token=Y`; `recusado` → homepage com modal de erro; `pendente` → homepage com modal informando análise

**Opção B — Pagar aqui mesmo (Checkout Bricks, reconectado na Fase 2c)**
- `_cartPagarAqui()` mostra `#cart-bricks-section` e chama `_renderBrick(preference_id, email, total, { containerId: 'cart-bricks-container', sourceModalId: 'cartCheckoutModal' })` — mesma função/lógica de tokenização do item único, só o container/modal-alvo mudam
- `onSubmit` do Brick chama `processar-pagamento.php` → MP Payments API. **Guarda anti-dupla-cobrança server-side**: se `pedidos.status` já `aprovado`/`em_processamento` (`statusPermiteRastreamento()`), recusa com HTTP 409 sem chamar a Payments API. `description` do pagamento lista todos os itens do pedido (não só o primeiro do JOIN — corrigido na Fase 2c)
- Resultado exibido em `#bricksResultModal` (compartilhado com o item único) com nome e e-mail do comprador: `approved` → ✅; `rejected` → ❌ com `status_detail` traduzido; `pending`/`in_process` → ⏳
- Botão "Pagar pelo site do MP" (`cart-bricks-use-redirect`) troca para a Opção A sem reiniciar (mesma guarda de `_cartPagarRedirect()`)

> `transaction_amount` do Brick **sempre** vem de `pedidos.total` já persistido (nunca recalculado no navegador); `pedidos.total` já inclui frete desde a criação em `pedido-carrinho.php` (B2a/B2b). O modal de item único (`#checkoutModal`) e `pedidos.php`/`_doCheckoutSubmit()` continuam existindo mas **dormentes** — o carrinho usa exclusivamente `pedido-carrinho.php` (multi-item nativo desde B2b).

## Módulo Carrinho (Patch B → B2a/B2b → Fase 2c)
> Patch B = estado do carrinho + UI (drawer, contador, adicionar/remover/quantidade) + persistência. B2a/B2b = frete agregado + `pedido-carrinho.php` (preference multi-item no servidor, checkout_hash). Fase 2c = reconecta o pagamento inline (Bricks) ao `#cartCheckoutModal`, ao lado do redirect — ver "Fluxo de compra" para o passo a passo completo.

- **Estado:** `localStorage('psp_cart')` guarda **só** `[{produto_id, quantidade}]` — nunca preço, nome ou total. Regra reforçada porque a feature toca dinheiro: preço é sempre relido do DOM (client, para exibição no drawer) ou do banco (servidor, em `pedido-carrinho.php`/`processar-pagamento.php`) — nunca confiado do que já está salvo
- **Lookup de exibição:** `_cartFindProdutoInfo(id)` relê nome/preço/código/imagem do card correspondente em `#products-grid` (funciona igual para catálogo estático ou dinâmico); se o produto não existir mais no DOM (ex.: removido do catálogo), a linha aparece como "Produto indisponível" com opção de remover — não quebra
- **Adicionar:** qualquer `.btn-buy` habilitado (card estático, `renderProducts()` e "Comprar Agora" do modal de detalhe — os três reaproveitam a mesma classe) chama `_cartAdd(id)`; **esgotado é bloqueado nos dois caminhos de card** — corrigida a divergência antiga em que o card estático de "Motoredutor Porta" (id 1, `estoque=0`) tinha o botão habilitado mesmo com badge "Esgotado"
- **Ícone + badge:** `#cartToggle` no header, fora do `.collapse` (sempre visível, inclusive mobile); `#cartBadge` usa `style.display` inline para sumir quando o carrinho está vazio
- **Drawer (`#cartDrawer` + `#cartBackdrop`):** painel desliza da direita via `transform: translateX()`; segue **o mesmo padrão de visibilidade do lightbox** — nunca no DOM como `display:none`, entra/sai via classe (`.open` no painel, `.active` no backdrop alternando `opacity`/`pointer-events`). Fecha com Esc, clique no backdrop ou botão X; foco visível em todos os controles
- **Z-index:** backdrop `1060`, painel `1061` — acima do header (`1030`), banner LGPD (`1040`) e assistente PSPart (`1050`); abaixo do lightbox (`9999`+), que não deveria coexistir com o carrinho aberto
- **Toast de feedback:** `.cart-toast`, mesma técnica opacity/pointer-events, sem lib nova
- **`_cartFinalizar()`** — fecha o drawer e abre `#cartCheckoutModal` (`_cartOpenCheckoutModal()`), passo 1 do checkout do carrinho (ver "Fluxo de compra")

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
> Descreve o modal `#checkoutModal` de item único — **dormente desde o Patch B** (ver "Módulo Carrinho"), não é mais alcançado por nenhum botão da UI hoje. O carrinho tem sua própria validação equivalente em `_cartCheckoutValidar()` (`#cartCheckoutModal`), mesmo padrão de `is-invalid`.
- Inline via classe `is-invalid` do Bootstrap + `<div class="invalid-feedback">` em cada campo
- **Nunca usar `showErrorModal()` dentro do checkout** — modal de erro abre atrás do modal de checkout
- Foco automático no primeiro campo inválido
- Erros da API exibidos em `#checkout-error-msg` (alerta no topo do modal, some após 6s)
- Produto com `estoque = 0` tem botão desabilitado ("Fora de estoque") — não abre checkout

## Dark mode
- Classe `body.dark-mode` (não media query)
- Anti-FOUC: script inline logo após `<body>` aplica a classe antes do primeiro render
- Fallback: `prefers-color-scheme` se não houver preferência salva
- **Cobertura de variáveis legadas: 6 de 10** (`--text-dark`, `--text-muted`, `--bg-light`, `--bg-white`, `--shadow-sm`, `--shadow-md`). `--primary` e `--accent` **não** têm override global — têm papel duplo (fundo de elementos "chrome" como navbar/footer/botão de filtro ativo, que devem continuar navy/azul escuro em qualquer tema, **e** cor de texto em alguns badges/ícones sobre fundo claro). Um override de variável global quebra o primeiro uso; correção exigiria overrides pontuais por seletor (padrão já usado em `body.dark-mode .product-price`, `.product-price-modal`) — não feito ainda, ~8 seletores identificados com contraste subótimo em dark mode mas não regredidos por essa decisão (comportamento pré-existente). `--radius` não precisa de versão dark (dimensional, não é cor)
- **Tokens do refresh visual têm cobertura própria** (adicionada junto com os tokens, não é a mesma limitação acima): `--ink`, `--muted`, `--line`, `--bg`, `--card` e `--shadow-lg` **têm** override em `body.dark-mode` — cards, drawer do carrinho e superfícies do refresh usam só esses tokens e por isso já adaptam automaticamente ao tema escuro sem overrides pontuais por seletor

## CSS variables principais
```css
/* Tokens originais (nunca substituídos — só adição por cima) */
--primary: #274185
--accent: #3457a6        /* derivado do --primary, contraste 6.86:1 com texto branco */
--accent-rgb: 52, 87, 166 /* para usos em rgba(), ex. anéis de foco, hero-badge */
--accent-hover: #2d4a8f
--cta: #157a52            /* botão de compra — verde harmonizado com a marca, não é o verde padrão do Bootstrap */
--cta-hover: #0f5f3f
--text-dark / --text-muted / --bg-light / --bg-white
--shadow-sm / --shadow-md / --radius: 12px

/* Tokens do refresh visual (adicionados; --card branco sobre --bg cinza-frio é o contraste "vitrine") */
--primary-700: #1d3266
--primary-900: #152449
--cta-600: #0d5940       /* hover dos CTAs verdes — variante mais escura do --cta real, não laranja */
--ink: #16203a           /* texto principal do refresh */
--muted: #5b6b82         /* texto secundário do refresh */
--line: #e4e9f2          /* bordas sutis */
--bg: #f5f8fc            /* fundo de página/seção */
--card: #ffffff          /* superfícies de card (produto, diferencial, contato, pagamentos) */
--ok / --warn / --req    /* estados do badge de disponibilidade — ver Módulo Cards de Produto */
--radius-sm: 8px
--shadow-lg: 0 18px 44px rgba(16,32,74,.18)
--font-display: 'Montserrat', system-ui, sans-serif   /* escopado a .storefront h1-h6 */
--font-body: 'Poppins', system-ui, sans-serif
--font-mono: 'JetBrains Mono', ui-monospace, monospace /* código interno, eyebrow de categoria */
--header-h                /* NÃO é um valor fixo — publicada em runtime por medirAlturaHeader() */
```
- Verde (`--cta`) reservado para ação/status: botão de compra (`.btn-buy`), botão WhatsApp, CTA do hero/drawer (`.btn-cta`, usa `--cta-600` no hover). **Preço do produto usa `--primary`, não `--cta`** (em ambos os contextos, card e modal) — evita poluição visual com verde demais na mesma tela
- `body.dark-mode .product-price` e `body.dark-mode .product-price-modal` compartilham override para `#90b4f5` (mesmo tom usado em outros textos-sobre-fundo-escuro do projeto)
- **`.storefront`** (classe no `<body>` de `index.html`) escopa a regra global de heading (`--font-display`) e o `padding-top: var(--header-h)` — existe justamente para não vazar para `acompanhar.html`/admin, que carregam `style.css` mas não têm essa classe

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

## Módulo Produtos — Deep Link, Compartilhamento e Busca/Ordenação (Fase 19)

> Origem: `docs/produtos-tools-audit.md` mapeou 10 candidatos a "ferramenta faltante" na seção de Produtos; esta fase implementa o bloco marcado "Agora" (deep link, WhatsApp, compartilhar/copiar, busca/ordenação/disponibilidade). Itens #5 (comparação), #6 (ficha exportável) e #10 (orçamento múltiplo) ficaram de fora — ver o audit para o motivo de cada um.

### Deep link de produto (`?produto=ID`)
- `_openDeepLinkProduct()` lê `URLSearchParams`, procura `#productModal{id}` e abre com `new bootstrap.Modal(el).show()` — ID inexistente só loga `console.warn`, não quebra a página
- **Só é chamado num bloco `finally` ao final de `renderProducts()`** — roda tanto no sucesso (produtos dinâmicos recém-injetados) quanto na falha (fallback estático já presente desde o carregamento), nunca antes de os modais existirem no DOM
- Gera a URL canônica de um produto via `_getProductShareUrl(id)`: `${origin}${pathname}?produto={id}`
- **Fora de escopo deliberadamente**: não usa `history.pushState` ao abrir um modal manualmente — o deep link só funciona no sentido "chegar via link", a URL do navegador não se atualiza sozinha ao clicar em "Detalhes"

### Compartilhamento (WhatsApp + Web Share + copiar código)
- `WHATSAPP_NUMBER` — constante central no topo de `script.js` (fora da classe), mesmo placeholder `SEU_NUMERO` já usado nos links estáticos do `index.html`; **não duplicar** o número em outro literal
- `_shareViaWhatsApp(data)` — monta `Tenho interesse no {nome} (código {código}): {url}`, chama `window.open('https://wa.me/{numero}?text=' + encodeURIComponent(mensagem), '_blank', 'noopener')`
- `_shareProduct(btnEl)` — tenta `navigator.share({ title, text, url })`; se a API não existir, ou falhar por qualquer motivo que não seja `AbortError` (cancelamento do usuário), cai para `_copyToClipboard()`
- `_copyToClipboard(value, btnEl, feedbackText)` — usa `navigator.clipboard.writeText()` quando `window.isSecureContext`; senão, fallback via `<textarea>` temporário + `document.execCommand('copy')`; qualquer falha é engolida silenciosamente (sem exceção não tratada)
- `_showCopyFeedback(btnEl, title)` — troca o `innerHTML` do botão só pelo ícone `fa-check` (nunca por texto) por 1,8s; texto estouraria os botões circulares de ação (`.product-share-actions .btn` é 34×34px, `padding:0`)
- Botões: `.btn-whatsapp-share` e `.btn-share-product` no modal de detalhes (dinâmico e nos 6 modais estáticos de fallback); `.btn-copy-code` reaproveita o `codigoHtml` já usado no card e no modal — aparece nos dois lugares automaticamente

### Busca, ordenação e filtro de disponibilidade
- Critério único de visibilidade em `_isProductVisible(col)`: categoria (`this._activeCategory`) **AND** busca (`this._searchQuery` contra `data-nome`/`data-codigo`) **AND** disponibilidade (`this._onlyInStock` contra `data-estoque`) — evita 3 mecanismos de show/hide paralelos
- `_applyProductVisibility()` aplica o critério a todo `.product-col` via **`style.display` inline** (nunca classe de stylesheet — segue a regra do projeto); mostra `#products-empty-msg` (fora do `.row#products-grid`, para sobreviver ao `innerHTML` dinâmico) quando nenhum produto passa no filtro
- `_applyProductSort(sortValue)` reordena os `.product-col` já existentes via `appendChild` (não re-renderiza); comparadores: `preco-asc`, `preco-desc`, `nome-asc`, `disponibilidade`
- `_applyCurrentToolbarState()` reaplica busca/ordenação/disponibilidade depois de qualquer (re)render do grid — chamado junto com `_openDeepLinkProduct()` no `finally` de `renderProducts()`
- Cada `.product-col` (dinâmico e os 6 estáticos de fallback) carrega `data-nome`, `data-preco`, `data-estoque`, `data-codigo` — tudo client-side sobre o payload que a API já retorna, sem nova chamada de rede
- Controles (`#product-search`, `#product-sort`, `#product-instock`) são estáticos no HTML (não gerados dinamicamente como os botões de categoria) — por isso `setupProductToolbar()` usa listeners diretos, não event delegation

## Módulo Cards de Produto (Refresh visual + Patch 2)
> Layout atual do card na grade (`#products-grid`) — aplicado **identicamente** no HTML estático de `index.html` e em `renderProducts()` (script.js). Qualquer mudança de card precisa ser feita nos dois lugares — regressão conhecida de uma correção anterior que só mexeu no estático.

- **Thumb full-bleed** (`.product-card-thumb`): imagem/vídeo de ponta a ponta, fundo com malha "blueprint" sutil atrás da foto (`.card-img-top`), borda inferior `--line`
- **Badge de disponibilidade — 3 estados**, sobreposto no canto superior esquerdo do thumb (`.stock-badge` + `.stock-dot`), mapeado do `estoque` real sem lógica de negócio nova: `estoque > 3` → `--ok` verde "Em estoque"; `1–3` → `--warn` âmbar "Últimas unidades"; `0` → `--req` cinza "Esgotado" (substituiu as antigas `.badge-disponivel`/`.badge-esgotado`, que não existem mais no markup)
- **Categoria como eyebrow mono** (`.product-eyebrow`, `--font-mono`, maiúscula, cor `--accent`) acima do nome — não é mais pílula colorida no corpo do card (a pílula colorida `.product-badge.badge-{categoria}` continua existindo só no modal de detalhe)
- **Nome** em Montserrat, com `-webkit-line-clamp: 2` (altura reservada mesmo pra nomes curtos, garante linha uniforme entre cards)
- **Código interno** em pílula mono (`.product-code-pill`, reaproveita o botão `.btn-copy-code`) — **omitida inteiramente** quando o produto não tem `codigo_interno` cadastrado (é dado ausente real no banco para alguns produtos, não bug de render; a ausência não deixa buraco no layout porque o elemento simplesmente não é renderizado)
- **Descrição removida da grade** — só aparece no modal de detalhe
- **Preço** grande (`--font-display`, 800), **CTA** (`.btn-buy` = comprar, ver "Módulo Carrinho"; `.btn-outline-primary` = Detalhes; desabilitado com ícone de proibido quando esgotado — **agora idêntico nos dois caminhos**, incluindo o card estático "Motoredutor Porta" que antes divergia)
- **Altura uniforme:** `.product-card-footer` (preço + botões) fixado no rodapé via `margin-top: auto` dentro do `card-body` flex — junto com o clamp do título, garante que todos os cards da linha tenham a mesma altura independente do tamanho do nome
- **Hover:** `translateY(-4px)` + `--shadow-lg` + borda de acento no topo via `::before` com `scaleX`
- Grid responsivo: `col-sm-6 col-md-4` (3 colunas desktop → 2 tablet → 1 mobile)

## Módulo Header Fixo + Reordenação de Seções (Patch A)
- `.custom-navbar` é `position: fixed` (não mais `sticky-top` do Bootstrap), `z-index: 1030`
- **Altura do header nunca é um valor fixo em CSS** — `medirAlturaHeader()` mede `#mainNavbar.offsetHeight` em runtime e publica em `--header-h` (fallback `76px` só cobre o primeiro paint antes do JS rodar)
- `.storefront { padding-top: var(--header-h) }` compensa o hero (escopado só à home, ver seção CSS variables)
- `html { scroll-padding-top: var(--header-h) }` faz todas as âncoras (menu, botão "Ver Produtos", busca do hero) pararem abaixo do header — substituiu o antigo `section { scroll-margin-top: 70px }` fixo
- Ordem das seções no HTML foi trocada (produtos logo após o hero) — ver "Estrutura da página"; **IDs de seção não mudaram**, só a posição do bloco inteiro no arquivo

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
- **Deep link só dispara em `finally`:** `_openDeepLinkProduct()` e `_applyCurrentToolbarState()` rodam depois do `try/catch` de `renderProducts()`, nunca dentro — garante que o DOM (dinâmico ou fallback estático) já está no estado final antes de procurar `#productModal{id}` ou reaplicar filtros
- **Ordenação de produtos via `appendChild`, não re-render:** evita perder o estado de busca/categoria/estoque já aplicado; o critério de visibilidade e a ordem do DOM são independentes um do outro
- **`#products-empty-msg` fora de `#products-grid`:** fica como irmão do `.row`, não dentro dele — se estivesse dentro, o `grid.innerHTML = cardsHtml` do `renderProducts()` apagaria o elemento a cada re-render bem-sucedido

## Pendências conhecidas (não são placeholder de conteúdo, mas ficaram em aberto)
- **Investigação do "fundo creme" (Patch 3):** o dono relatou ver um fundo bege/creme na página real, mas 3 varreduras exaustivas (hex, `rgb()/hsl()`, nomes de cor CSS, overrides do Bootstrap, gradientes, inline styles, JS, segundo stylesheet) não encontraram nenhuma cor creme em lugar nenhum do código — todos os fundos resolvem para `--bg`/`--bg-light` (cinza-frio). Ficou pendente o valor computado do DevTools pra continuar; **Estágios 2 e 3 do Patch 3 foram aplicados** (cards viraram `--card` branco, heading escopado a `.storefront`), mas o **Estágio 1 (troca do creme em si) nunca foi executado** por falta desse dado
- **Checkout do carrinho (B2a/B2b/Fase 2c):** construído — preference multi-item no servidor (`pedido-carrinho.php`), frete agregado, CEP unificado (`psp_cep_entrega`) e os dois caminhos de pagamento (redirect + Bricks inline) já reconectados. Pendência real remanescente: guarda anti-dupla-cobrança do lado do redirect (`_cartGuardPedidoNaoPago()`) é uma checagem client-side best-effort via `tracking.php` — a guarda autoritativa fica em `processar-pagamento.php` (server-side, só protege o caminho Bricks); um MP preference já pago reaberto via redirect depende do próprio MP recusar novo pagamento na página deles
- `updateActiveNavLink()` observação menor: o offset de 100px foi consolidado em `--header-h`, mas isso é sobre *qual* link fica marcado ativo no scroll — não afeta onde as âncoras param (isso é `scroll-padding-top`, mecanismo separado e já correto)

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
- **Patch 3:** fundo da seção e superfícies (`.ps-bandeira`, `.ps-seguranca`) migrados de hex hardcoded (`#fff`) para `--bg`/`--card` — mesmo contraste "cartão branco sobre fundo frio" do resto do refresh; overrides de dark mode (hex customizados, não tokens) mantidos como estavam, já funcionavam

## Preferências
- Abordagem não agressiva: melhorar sem reescrever seções inteiras
- Mudanças centralizadas no CSS (não inline styles)
- Comunicação em português (pt-BR)
- Sem commit até solicitação explícita
