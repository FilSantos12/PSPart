<?php
/**
 * POST /backend/api/pagamento.php
 * Cria (ou reaproveita) uma preference no Mercado Pago e retorna o init_point para redirect.
 *
 * Body JSON esperado:
 * { "pedido_id": 1 }
 *
 * Lógica de montagem de itens/frete em backend/helpers/mercadopago_preference.php —
 * compartilhada com o checkout do carrinho (pedido-carrinho.php, B2b). Suporta
 * pedidos com 1 ou N itens (itens_pedido) transparentemente.
 */

require_once __DIR__ . '/_core.php';
require_once __DIR__ . '/../helpers/mercadopago_preference.php';

exigir_metodo('POST');

$body     = body_json();
$pedidoId = (int) ($body['pedido_id'] ?? 0);

if ($pedidoId <= 0) {
    json_erro('pedido_id inválido.', 422);
}

try {
    $pdo = getDB();
    json_ok(criarOuObterPreferencePedido($pdo, $pedidoId));

} catch (Exception $e) {
    json_erro('Erro ao processar pagamento: ' . $e->getMessage(), 500);
}
