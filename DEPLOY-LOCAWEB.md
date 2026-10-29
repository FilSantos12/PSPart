# Deploy — Locaweb (Hospedagem Compartilhada)

> Consulta registrada em: 2026-06-15
> Domínio já reservado na Locaweb.

---

## Plano

Hospedagem compartilhada é suficiente. Requisitos mínimos:

- PHP 8.1 ou superior (`match`, `str_contains`, `str_starts_with` são usados no código)
- Extensão PDO SQLite habilitada (disponível por padrão na Locaweb)
- Acesso FTP ou gerenciador de arquivos cPanel

---

## Estrutura de Upload

Document root na Locaweb: `public_html/` (ou subpasta com nome do domínio).

Subir **tudo** para dentro dessa pasta:

```
public_html/
├── index.html
├── acompanhar.html
├── style.css
├── script.js
├── manifest.json
├── robots.txt
├── sitemap.xml
├── backend/
├── vendor/          ← subir a pasta vendor/ local (não rodar Composer no servidor)
├── img/
├── docs/
├── database.db      ← proteger via .htaccess (ver abaixo)
└── .htaccess        ← versionado no repositório (ver abaixo)
```

---

## Passo a Passo

### 1. Configurar versão do PHP

cPanel → **Selecionar versão PHP** → escolher **8.1** ou **8.2**.

### 2. Ativar SSL (obrigatório para o MP)

cPanel → **Let's Encrypt** → ativar para o domínio.
O Mercado Pago exige HTTPS nos `back_urls` e `notification_url` do webhook.

### 3. Conferir os `.htaccess` (versionados no repositório)

Sobem junto com o código — conferir que chegaram ao servidor:

| Arquivo | Bloqueia |
|---|---|
| `.htaccess` (raiz) | `*.db` (+ `-shm`/`-wal`/`-journal`) e `*.log`; diretórios `logs/`, `vendor/`, `.git/`; `composer.json`/`.lock` |
| `backend/.htaccess` | `backend/config/` (via mod_rewrite) |
| `backend/config/.htaccess` | todo o diretório — reforço que não depende de mod_rewrite |
| `migrations/.htaccess` | todo o diretório — migrações só pelo terminal |

Todos usam só diretivas válidas em `.htaccess`, com `Require all denied` (Apache 2.4) e `Deny from all` (2.2) separados por `<IfModule>`. Depois do upload, abrir no navegador `/database.db`, `/backend/config/database.php` e `/migrations/migrate_qtd_consulta.php` — os três devem responder **403**.

> **Migrações em hospedagem sem SSH:** `migrations/.htaccess` impede a execução pela URL. Sem acesso ao terminal, renomear esse `.htaccess` só durante a execução, rodar as migrações e restaurá-lo (ou apagar os arquivos de migração do servidor) logo em seguida.

### 4. Criar `backend/config/mercadopago.php` no servidor

O arquivo está no `.gitignore` e não sobe com o FTP — criar manualmente via gerenciador de arquivos do cPanel:

```php
<?php
define('MP_ACCESS_TOKEN', 'APP_USR-...');  // chave de PRODUÇÃO
define('MP_PUBLIC_KEY',   'APP_USR-...');  // chave de PRODUÇÃO
define('MP_BASE_URL', 'https://seudominio.com.br');
```

> Ambas as chaves devem ser de **produção** (prefixo `APP_USR-`).
> Misturar chaves de teste + produção causa erro 401 `"Unauthorized use of live credentials"`.
> Onde encontrar: mercadopago.com.br/developers → Suas integrações → Credenciais.

### 5. Ajustar permissões de arquivo (via FTP)

| Caminho | Permissão |
|---|---|
| `database.db` | `664` |
| `logs/` (criada automaticamente pelo PHP) | `755` |
| `img/` | `755` |
| `docs/` | `755` |

---

## Checklist Pós-Upload

> Senhas de produção (do `setup.php` e do admin) são definidas no servidor e **nunca versionadas** — nem neste guia, nem em nenhum outro arquivo do repositório.

- [ ] Acessar `https://seudominio.com.br/setup.php` com a senha `<definir no servidor>` → cria as tabelas
- [ ] **Deletar `setup.php`** do servidor imediatamente após executar
- [ ] Acessar o admin → trocar a senha padrão por uma senha forte `<definir no servidor>`
- [ ] Registrar a URL do webhook no painel do Mercado Pago:
      `https://seudominio.com.br/backend/api/webhook.php`
- [ ] Atualizar os dois números de WhatsApp no `index.html` (botão hero + botão flutuante)
- [ ] Substituir os ícones PWA placeholder (`manifest.json`) por PNGs reais (192×192 e 512×512)
- [ ] Preencher a seção "Sobre Nós" com o texto real da empresa
- [ ] Testar uma compra completa com cartão de teste do MP antes de ir para produção

---

## Pontos de Atenção

### `database.db` acessível via browser
Sem o `.htaccess` da raiz, qualquer pessoa pode fazer download do banco completo (pedidos, e-mails, dados de compradores). Ele é versionado — conferir que subiu e que `/database.db` responde 403.

### `vendor/` — não rodar Composer no servidor
Subir a pasta `vendor/` do ambiente local. Hospedagem compartilhada normalmente não tem Composer disponível via SSH.

### `backend/.htaccess` — corrigido
Usava `<DirectoryMatch>`, que não é permitido em `.htaccess` (só na configuração do servidor) e tende a derrubar todo o `backend/` com erro 500. Substituído por `RewriteRule ^config(/|$) - [F,L]`, com `backend/config/.htaccess` como reforço sem mod_rewrite.

### E-mail via `mail()` nativo
Não funciona em localhost (DEV-SKIP no log). Funciona normalmente em hospedagem compartilhada Locaweb. Logs em `logs/emails.log`.

### Webhook do Mercado Pago
Registrar no painel MP a URL pública de produção. Sem isso, pagamentos via Checkout Pro (redirect) não atualizam o status do pedido automaticamente.

---

## Pendências Antes do Deploy (da documentação do projeto)

- `SEU_NUMERO` — substituir nos 2 lugares em `index.html` (botão hero + botão flutuante)
- Ícones PWA reais (192×192 e 512×512 PNG) para `manifest.json`
- Texto real da seção "Sobre Nós"
- Preços dos produtos definidos via admin
- Senha do admin trocada (`<definir no servidor>`)
- `setup.php` removido após execução
