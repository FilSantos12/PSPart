# PSPart - Partes e Peças Automação

Site institucional com e-commerce integrado para a **PSPart**, empresa do segmento de mecatrônica e controle de acesso.  
Desenvolvido com **HTML/CSS/JS puro** no frontend e **PHP + SQLite** no backend. Sem build process — deploy direto para hospedagem estática com suporte PHP.

---

## Funcionalidades

### Frontend
- **Catálogo de produtos dinâmico** — cards e filtros de categoria carregados via API PHP
- **Modal de detalhes do produto** — carrossel de imagens, especificação técnica (Markdown renderizado), botão de data sheet PDF
- **Checkout integrado** — modal com dados do comprador, preenchimento automático de endereço via ViaCEP, redirecionamento para Mercado Pago Checkout Pro
- **Acompanhamento de pedido** — página `acompanhar.html` com timeline de status; acessível por link do e-mail ou busca manual
- **Dark mode** — toggle manual com fallback para `prefers-color-scheme`, anti-FOUC via script inline
- **Lightbox de imagem** — overlay customizado sem conflito com modais Bootstrap
- **Animações de scroll (AOS)** — elementos com fade-in escalonado
- **LGPD** — banner de consentimento de cookies (Consent Mode v2 para GA4)
- **PWA básico** — `manifest.json` + service worker

### Backend (PHP + SQLite)
- **API REST** — produtos, categorias, pedidos
- **Integração Mercado Pago** — Checkout Pro (redirect), webhook com suporte a formatos v1 e IPN
- **E-mails transacionais** — confirmação de pedido e aprovação de pagamento via `mail()` nativo
- **Área Admin** — dashboard, CRUD de produtos (múltiplas imagens, data sheet PDF, especificação técnica Markdown, código interno), gestão de categorias e pedidos, autenticação com senha

---

## Stack

| Camada | Tecnologia |
|---|---|
| Frontend | HTML5, CSS3, JavaScript ES6+ (classe `App`) |
| UI | Bootstrap 5.3, Font Awesome 6.4, AOS 2.3 |
| Tipografia | Google Fonts — Poppins + Montserrat |
| Markdown | marked.js (frontend) + EasyMDE (admin) |
| Ordenação | SortableJS (drag-and-drop de imagens no admin) |
| Formulário | FormSubmit.co → filipe@pentasis.com.br |
| Analytics | Google Analytics GA4 (`G-XQBJ002YQC`) |
| Backend | PHP |
| Banco de dados | SQLite (`database.db`) |
| Pagamentos | Mercado Pago Checkout Pro — SDK `mercadopago/dx-php` |
| CEP | ViaCEP |

---

## Estrutura do Projeto

```
PSPart-Website/
├── index.html                  # Página principal
├── acompanhar.html             # Acompanhamento de pedido
├── style.css                   # Estilos com CSS variables
├── script.js                   # Classe App (toda a lógica frontend)
├── manifest.json               # PWA
├── robots.txt / sitemap.xml    # SEO
├── database.db                 # Banco SQLite (ignorado no git)
├── img/                        # Imagens (prod_* ignorados no git)
├── docs/                       # Data sheets PDF (ignorado no git)
├── vendor/                     # Dependências Composer (ignorado no git)
├── logs/                       # Logs de webhook e e-mail (ignorado no git)
└── backend/
    ├── api/
    │   ├── produtos.php        # GET — lista produtos com imagens
    │   ├── categorias.php      # GET — lista categorias
    │   ├── pedidos.php         # POST — cria pedido e preferência MP
    │   ├── acompanhar.php      # GET — status do pedido por token ou e-mail
    │   └── webhook.php         # POST — notificações do Mercado Pago
    ├── admin/
    │   ├── index.php           # Dashboard
    │   ├── produtos.php        # Listagem de produtos
    │   ├── produto-novo.php    # Criação de produto (2 fases)
    │   ├── produto-editar.php  # Edição de produto
    │   ├── produto-estado.php  # Inativar / reativar / excluir produto
    │   ├── ajax-imagens.php    # AJAX — upload, reorder, set-principal, delete
    │   ├── pedidos.php         # Listagem de pedidos
    │   ├── categorias.php      # CRUD de categorias
    │   ├── login.php           # Autenticação
    │   └── _layout.php         # Layout base do admin
    ├── config/
    │   └── mercadopago.php     # Credenciais MP (ignorado no git)
    └── helpers/
        └── email.php           # Funções de e-mail transacional
```

---

## Ambiente de Desenvolvimento

### Pré-requisitos
- PHP 7.4+
- Extensão `pdo_sqlite` habilitada
- Composer (`composer install` na raiz)

### Subir o servidor local
```bash
cd "D:\Aréa de Trabalho\DEV\PSPart-Website"
php -S localhost:8000
```

| URL | Descrição |
|---|---|
| `http://localhost:8000` | Site principal |
| `http://localhost:8000/setup.php` | Setup do banco (senha: ver gerenciador de senhas local) |
| `http://localhost:8000/backend/admin/` | Área administrativa |

**Admin padrão:** `admin@pspart.com.br` / senha: ver gerenciador de senhas local

### ngrok (Mercado Pago em dev local)
O MP exige URL pública para `back_urls` e `notification_url`. Em desenvolvimento local, usar ngrok:

```bash
# Terminal 1
php -S localhost:8000

# Terminal 2
ngrok http 8000
```

Atualizar `MP_BASE_URL` em `backend/config/mercadopago.php` com a URL HTTPS gerada:
```php
define('MP_BASE_URL', 'https://xxxx.ngrok-free.app');
```

> A URL muda a cada reinicialização do ngrok.

---

## Fluxo de Compra

1. Clicar no botão de carrinho (card) ou "Comprar Agora" (modal de produto)
2. Modal de checkout — preencher nome, e-mail, telefone, CEP (auto-preenche via ViaCEP), número
3. "Ir para pagamento" → validação inline → spinner de redirecionamento → Mercado Pago
4. Pagamento processado → webhook atualiza status no banco
5. Retorno:
   - **Aprovado** → `acompanhar.html?pedido=X&token=Y`
   - **Recusado** → homepage com modal de erro
   - **Pendente** → homepage com modal de análise

---

## Checklist antes do Deploy

- [ ] Substituir `SEU_NUMERO` pelo WhatsApp real (2 ocorrências em `index.html`)
- [ ] Adicionar ícones PWA reais (192×192 e 512×512 PNG) no `manifest.json`
- [ ] Escrever texto real da seção "Sobre Nós"
- [ ] Trocar credenciais TEST- pelas de produção em `backend/config/mercadopago.php`
- [ ] Atualizar `MP_BASE_URL` com o domínio real
- [ ] Trocar a senha padrão do admin (`<definir no servidor>`)
- [ ] Remover ou bloquear `setup.php`
