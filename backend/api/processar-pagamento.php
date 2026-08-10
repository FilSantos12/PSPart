<?php
/**
 * POST /backend/api/processar-pagamento.php
 * Processa pagamento via Mercado Pago Payments API (Checkout Bricks).
 *
 * Body JSON esperado:
 * { "pedido_id": 1, "form_data": { ...dados do Brick... } }
 */

require_once __DIR__ . '/_core.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../config/mercadopago.php';
require_once __DIR__ . '/../helpers/email.php';
require_once __DIR__ . '/../helpers/status.php';

use MercadoPago\MercadoPagoConfig;
use MercadoPago\Client\Payment\PaymentClient;

exigir_metodo('POST');

$body     = body_json();
$pedidoId = (int) ($body['pedido_id'] ?? 0);
$formData = $body['form_data'] ?? null;

if ($pedidoId <= 0)       json_erro('pedido_id inválido.', 422);
if (!is_array($formData)) json_erro('form_data inválido.', 422);

try {
    $pdo = getDB();

    $stmt = $pdo->prepare("
        SELECT id, total, token_acompanhamento, nome_comprador, email_comprador, status
        FROM pedidos WHERE id = :id
    ");
    $stmt->execute([':id' => $pedidoId]);
    $pedido = $stmt->fetch();

    if (!$pedido) json_erro('Pedido não encontrado.', 404);

    // Guarda anti-dupla-cobrança: pedido já pago/em processamento não pode ser cobrado de novo
    // por este caminho (ex.: duplo clique, ou já aprovado via redirect noutra aba).
    if (statusPermiteRastreamento($pedido['status'])) {
        json_erro('Este pedido já foi pago. Nenhuma cobrança adicional foi feita.', 409);
    }

    $stmtItens = $pdo->prepare("
        SELECT pr.nome AS produto_nome, ip.quantidade, ip.preco_unitario
        FROM itens_pedido ip JOIN produtos pr ON pr.id = ip.produto_id
        WHERE ip.pedido_id = :id
    ");
    $stmtItens->execute([':id' => $pedidoId]);
    $itensPedido = $stmtItens->fetchAll();

    if (empty($itensPedido)) json_erro('Pedido sem itens.', 404);

    // Descrição honesta do pagamento — lista todos os itens, não só o primeiro do JOIN
    $partesDescricao = array_map(
        fn($i) => $i['produto_nome'] . ' × ' . (int) $i['quantidade'],
        $itensPedido
    );
    $descricao = count($itensPedido) === 1
        ? $partesDescricao[0]
        : count($itensPedido) . ' itens: ' . implode(', ', $partesDescricao);
    if (mb_strlen($descricao) > 250) {
        $descricao = mb_substr($descricao, 0, 247) . '...';
    }

    MercadoPagoConfig::setAccessToken(MP_ACCESS_TOKEN);

    $client = new PaymentClient();

    // preference_id no formData do Brick pode conflitar com a Payments API direta — remover
    unset($formData['preference_id']);

    // transaction_amount vem sempre do banco — nunca do cliente
    // notification_url omitido: status é capturado de forma síncrona na resposta do create().
    // O webhook de Bricks é desnecessário aqui; o Checkout Pro usa o webhook via preference.
    $paymentData = array_merge($formData, [
        'transaction_amount' => (float) $pedido['total'],
        'description'        => $descricao,
        'external_reference' => (string) $pedidoId,
    ]);

    // Log do payload enviado (sem token de cartão)
    $logPayload = $paymentData;
    if (isset($logPayload['token'])) $logPayload['token'] = '***';
    file_put_contents(
        __DIR__ . '/../../logs/pagamento.log',
        '[' . date('Y-m-d H:i:s') . '] Tentativa pedido #' . $pedidoId . ' | ' . json_encode($logPayload, JSON_UNESCAPED_UNICODE) . PHP_EOL,
        FILE_APPEND
    );

    $payment = $client->create($paymentData);

    $novoStatus = mpStatusParaInterno($payment->status);

    // Atualiza status do pedido para qualquer resultado (aprovado, recusado, em_analise, etc.)
    $pdo->prepare("UPDATE pedidos SET status = :status, mp_preferencia_id = :mp_id WHERE id = :id")
        ->execute([':status' => $novoStatus, ':mp_id' => $payment->id, ':id' => $pedidoId]);

    if ($payment->status === 'approved') {
        // Auto-cria registro de rastreamento em "Em Preparação" (ignora se já existir)
        $pdo->prepare("
            INSERT OR IGNORE INTO order_tracking (order_id, status, updated_at)
            VALUES (:oid, 0, :now)
        ")->execute([':oid' => (string) $pedidoId, ':now' => date('Y-m-d H:i:s')]);

        emailPagamentoAprovado($pedido, $itensPedido, $pedido['token_acompanhamento']);
    }

    json_ok([
        'status'        => $payment->status,
        'status_detail' => $payment->status_detail,
        'payment_id'    => $payment->id,
        'pedido_id'     => $pedidoId,
        'token'         => $pedido['token_acompanhamento'],
    ]);

} catch (Exception $e) {
    $apiBody = null;
    $logMsg  = '[' . date('Y-m-d H:i:s') . '] ' . get_class($e) . ': ' . $e->getMessage();
    if (method_exists($e, 'getApiResponse')) {
        $apiBody = $e->getApiResponse()->getContent();
        $logMsg .= ' | API body: ' . json_encode($apiBody, JSON_UNESCAPED_UNICODE);
    }
    file_put_contents(__DIR__ . '/../../logs/pagamento.log', $logMsg . PHP_EOL, FILE_APPEND);

    $mpMsg = is_array($apiBody) ? ($apiBody['message'] ?? '') : '';
    if ($mpMsg === 'internal_error') {
        json_erro('Serviço de pagamento temporariamente indisponível. Aguarde alguns instantes e tente novamente.', 500);
    } elseif ($mpMsg === 'Card Token not found') {
        json_erro('Token do cartão expirado. Feche o modal, abra novamente e tente pagar.', 500);
    } elseif (str_contains($mpMsg, 'Unauthorized')) {
        json_erro('Erro de configuração do sistema de pagamento. Contate o suporte.', 500);
    } else {
        json_erro('Não foi possível processar o pagamento. Tente novamente.', 500);
    }
}
