# CHANGELOG — PSPart-Website

Histórico das fases de desenvolvimento do projeto. O estado atual do sistema (como as coisas funcionam hoje) vive no `CLAUDE.md`; este arquivo registra como se chegou até lá.

## Roadmap de e-commerce
| Fase | Descrição | Status |
|---|---|---|
| Fase 1 | Banco de dados SQLite (schema + setup.php) | ✅ Concluído |
| Fase 2 | Backend PHP + API (produtos, pedidos, pagamento stub, webhook stub) | ✅ Concluído |
| Fase 3 | Integração Mercado Pago Checkout Pro | ✅ Concluído |
| Fase 4 | Área Admin (dashboard, CRUD produtos, pedidos, login) | ✅ Concluído |
| Fase 5 | Frontend — preços, checkout modal, lightbox | ✅ Concluído |
| Fase 6 | Acompanhamento de pedido — página + e-mails transacionais | ✅ Concluído |
| Fase 7 | Admin produtos: código interno, data sheet, múltiplas imagens, especificação técnica | ✅ Concluído |
| Fase 8 | Gestão de categorias + filtros dinâmicos + retorno MP pós-pagamento | ✅ Concluído |
| Fase 9 | Checkout Bricks (pagamento inline) + modal pós-pagamento + simplificação de acompanhamento | ✅ Concluído |
| Fase 10 | Rastreamento de entrega (order_tracking) + timeline unificada + correção de timezone | ✅ Concluído |
| Fase 11 | Prazo de entrega via API Melhor Envio — proxy PHP + cache + frontend nos modais de produto e checkout | ✅ Concluído |
| Fase 12 | Transportadora escolhida pelo cliente — captura no checkout, persistência em order_tracking, exibição no painel admin | ✅ Concluído |
| Fase 13 | Autenticação OAuth2 com Melhor Envio — fluxo authorization_code, token no banco, refresh automático, painel de integração | ✅ Concluído |
| Fase 14 | Frete cobrado no total + CEP lembrado — frete somado ao total do pedido e à preference MP; botão "Alterar CEP"; localStorage persistente | ✅ Concluído |
| Fase 15 | Status automático + frete na ficha/detalhe — `derivarStatusPedido` centralizado; frete em `pedido-detalhe.php` e `pedido-ficha.php`; status derivado automático no Bricks; `pedido_status` exposto em `tracking.php` | ✅ Concluído |
| Fase 16 | Emissão de etiquetas Melhor Envio — fluxo 4 etapas (cart→checkout→generate→print); serviço em `backend/melhorenvio/shipment.php`; endpoint admin `etiqueta-action.php`; bloco UI em `pedido-detalhe.php`; trava `LOJA_DADOS_REAIS`; tracking_code automático via `meTracking()` | ✅ Concluído |
| Fase 17 | Agente conversacional de compras (v1, somente leitura) — widget de chat flutuante; 3 ferramentas read-only (`buscar_produtos`, `calcular_frete`, `consultar_pedido`); suporte a Anthropic e Groq via flag `AGENTE_PROVEDOR`; modo mock para testes sem custo | ✅ Concluído |
| Fase 18 | Seção "Pagamentos & Segurança" — logos SVG de bandeiras (Visa, Mastercard, Elo, Amex, Hipercard via CDN); badges para Pix, Boleto e Mercado Pago; bloco de segurança (HTTPS, PCI-DSS, LGPD); dark mode coberto | ✅ Concluído |

## Módulo Frete no Total + CEP Persistente (Fase 14) — o que mudou em relação à Fase 12

### Backend

| Arquivo | Mudança |
|---|---|
| `backend/api/pedidos.php` | Validação do frete movida para antes da transação; `total = subtotal + fretePrice` gravado em `pedidos.total` |
| `backend/api/pagamento.php` | Busca `order_tracking` após carregar o pedido; adiciona item `"Frete — {carrier} {service}"` à lista `items` da preference MP se `shipping_price > 0` |

- `pedidos.php` retorna agora `{ pedido_id, total, subtotal, frete, status, token }`
- `processar-pagamento.php` não precisou de alteração — `transaction_amount` já vem de `pedidos.total` no banco

### Frontend (`script.js`)

| Método | Mudança |
|---|---|
| `_updateCheckoutTotal(price, qty)` | Calcula `subtotal + frete`; atualiza `#checkout-resumo` (exibe quando frete definido); chama `_updatePaymentBtns()` |
| `_updatePaymentBtns()` | **Novo** — habilita/desabilita botões; mostra/oculta `#checkout-frete-aviso` |
| `_renderFreteCheckout()` | Adicionado botão "Alterar CEP" (`.frete-alterar-cep-btn`) na label; ao selecionar opção chama `_updateCheckoutTotal()`; ao carregar primeira opção já atualiza totais |
| `_alterarCepCheckout()` | **Novo** — limpa frete, reseta totais, foca campo CEP |
| `_calcularFreteCheckout()` | Salva CEP em `localStorage('psp_cep_entrega')` após sucesso |
| `_buscarCep()` | Salva CEP em `localStorage('psp_cep_entrega')` após ViaCEP bem-sucedido |
| `_openCheckoutModal()` | Move `_selectedFrete = null` para antes de `_updateCheckoutTotal`; pré-preenche `#checkout-cep` do localStorage; adiciona listener `shown.bs.modal` (once) para disparar `_buscarCep()` |
| `_doCheckoutSubmit()` | Guard no início: retorna erro se `_selectedFrete` for null |
| `setupFrete()` | Trocou `sessionStorage('psp_frete_cep')` → `localStorage('psp_cep_entrega')` em toda a lógica de modais de produto |

### HTML / CSS
- Botões `#checkout-submit-redirect` e `#checkout-submit-bricks` iniciam com `disabled`
- `#checkout-frete-aviso` — "Calcule o frete para continuar" (visível quando sem frete, oculto via JS)
- `#checkout-resumo` — bloco com subtotal + frete + total (oculto via `style="display:none;"`, revelado pelo JS)
- `.checkout-resumo` em `style.css` — borda superior + espaçamento + dark mode
