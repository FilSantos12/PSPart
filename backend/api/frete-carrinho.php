<?php
/**
 * POST /backend/api/frete-carrinho.php
 * Cotação de frete agregada para o carrinho (multi-item) via Melhor Envio.
 *
 * Body JSON esperado:
 * { "cart": [{"produto_id": 1, "quantidade": 2}, ...], "cep_destino": "01310100" }
 *
 * O browser envia só { produto_id, quantidade } por item + CEP — nunca preço nem
 * dimensão. Lógica de resolução/cache compartilhada com backend/api/pedido-carrinho.php
 * (B2b) em backend/helpers/frete_carrinho.php — mesma chave de cache dos dois lados.
 *
 * Resposta de sucesso: { ok, cep, servicos[], cache }.
 */

require_once __DIR__ . '/_core.php';
require_once __DIR__ . '/../config/melhorenvio.php';
require_once __DIR__ . '/../helpers/frete_carrinho.php';

exigir_metodo('POST');

$body   = body_json();
$cart   = $body['cart'] ?? [];
$cepRaw = preg_replace('/\D/', '', $body['cep_destino'] ?? '');

if (!is_array($cart) || empty($cart)) json_erro('Carrinho vazio.', 422);
if (strlen($cepRaw) !== 8)            json_erro('CEP inválido. Informe 8 dígitos.', 422);

try {
    $pdo               = getDB();
    $carrinhoResolvido = resolverCarrinho($pdo, $cart);
    $resposta          = cotarFreteCarrinho($pdo, $carrinhoResolvido, $cepRaw);

    json_ok($resposta);

} catch (InvalidArgumentException $e) {
    json_erro($e->getMessage(), 422);
} catch (FreteIndisponivelException $e) {
    json_erro($e->getMessage(), 422);
} catch (RuntimeException $e) {
    if ($e->getMessage() === 'integracao_nao_conectada') {
        json_erro('Cálculo de frete temporariamente indisponível.', 503);
    }
    json_erro($e->getMessage(), 502);
} catch (Exception $e) {
    json_erro('Erro ao calcular frete.', 500);
}
