<?php
/**
 * Criação/reaproveitamento de preference do Mercado Pago para um pedido já existente.
 * Compartilhado entre backend/api/pagamento.php (item único, fluxo original) e
 * backend/api/pedido-carrinho.php (carrinho multi-item, B2b) — mesma lógica de
 * montagem de itens, só a quantidade de linhas em itens_pedido muda.
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../config/mercadopago.php';

use MercadoPago\MercadoPagoConfig;
use MercadoPago\Client\Preference\PreferenceClient;

/**
 * Cria a preference para o pedido, ou reaproveita a já existente (idempotência —
 * evita gerar uma segunda cobrança se o pedido já tem mp_preferencia_id salvo).
 *
 * @throws RuntimeException se o pedido não existir/não tiver itens
 */
function criarOuObterPreferencePedido(PDO $pdo, int $pedidoId): array
{
    $stmt = $pdo->prepare("
        SELECT p.id, p.nome_comprador, p.email_comprador, p.total,
               p.token_acompanhamento, p.mp_preferencia_id,
               ip.produto_id, ip.quantidade, ip.preco_unitario,
               pr.nome AS produto_nome
        FROM pedidos p
        JOIN itens_pedido ip ON ip.pedido_id = p.id
        JOIN produtos pr     ON pr.id = ip.produto_id
        WHERE p.id = :id
    ");
    $stmt->execute([':id' => $pedidoId]);
    $linhas = $stmt->fetchAll();

    if (empty($linhas)) {
        throw new RuntimeException('Pedido não encontrado.');
    }
    $pedido = $linhas[0];

    MercadoPagoConfig::setAccessToken(MP_ACCESS_TOKEN);

    // Reaproveita preference já criada — nunca gera uma segunda cobrança pro mesmo pedido
    if (!empty($pedido['mp_preferencia_id'])) {
        try {
            $existente = (new PreferenceClient())->get($pedido['mp_preferencia_id']);
            if ($existente && $existente->init_point) {
                return [
                    'preference_id' => $existente->id,
                    'init_point'    => $existente->init_point,
                    'sandbox_url'   => $existente->sandbox_init_point,
                ];
            }
        } catch (Exception $e) {
            // preference antiga inválida/expirada na API do MP — cria uma nova abaixo
        }
    }

    $items = array_map(fn($l) => [
        'id'          => $pedidoId . '_' . $l['produto_id'],
        'title'       => $l['produto_nome'],
        'quantity'    => (int)   $l['quantidade'],
        'unit_price'  => (float) $l['preco_unitario'],
        'currency_id' => 'BRL',
    ], $linhas);

    $stmtFrete = $pdo->prepare("
        SELECT shipping_price, chosen_carrier, chosen_service
        FROM order_tracking WHERE order_id = :id
    ");
    $stmtFrete->execute([':id' => (string) $pedidoId]);
    $tracking = $stmtFrete->fetch();

    if ($tracking && (float) $tracking['shipping_price'] > 0) {
        $freteNome = trim(($tracking['chosen_carrier'] ?? '') . ' ' . ($tracking['chosen_service'] ?? '')) ?: 'Frete';
        $items[] = [
            'id'          => 'frete_' . $pedidoId,
            'title'       => 'Frete — ' . $freteNome,
            'quantity'    => 1,
            'unit_price'  => (float) $tracking['shipping_price'],
            'currency_id' => 'BRL',
        ];
    }

    $preference = (new PreferenceClient())->create([
        'items' => $items,
        'payer' => [
            'email' => $pedido['email_comprador'],
            'name'  => $pedido['nome_comprador'],
        ],
        'back_urls' => [
            'success' => MP_BASE_URL . '/acompanhar.html?pedido=' . $pedidoId . '&token=' . $pedido['token_acompanhamento'],
            'failure' => MP_BASE_URL . '/?pagamento=recusado&pedido='  . $pedidoId,
            'pending' => MP_BASE_URL . '/?pagamento=pendente&pedido='  . $pedidoId,
        ],
        'payment_methods' => [
            'excluded_payment_types' => [],  // nenhum método excluído — PIX, boleto, débito e crédito disponíveis
            'installments'           => 12,  // máximo de parcelas no cartão
        ],
        'external_reference' => (string) $pedidoId,
        'notification_url'   => MP_BASE_URL . '/backend/api/webhook.php',
    ]);

    $pdo->prepare("UPDATE pedidos SET mp_preferencia_id = :mp_id WHERE id = :id")
        ->execute([':mp_id' => $preference->id, ':id' => $pedidoId]);

    return [
        'preference_id' => $preference->id,
        'init_point'    => $preference->init_point,        // produção
        'sandbox_url'   => $preference->sandbox_init_point, // testes
    ];
}
