<?php
/**
 * POST /backend/api/webhook.php
 * Recebe notificações do Mercado Pago (SDK v3) e atualiza o status do pedido.
 */

require_once __DIR__ . '/_core.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../config/mercadopago.php';
require_once __DIR__ . '/../helpers/email.php';
require_once __DIR__ . '/../helpers/status.php';
require_once __DIR__ . '/../helpers/estoque.php';

use MercadoPago\MercadoPagoConfig;
use MercadoPago\Client\Payment\PaymentClient;

// MP envia GET para validação de URL
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    http_response_code(200);
    exit;
}

exigir_metodo('POST');

// ── Log do payload para diagnóstico ─────────────────────────────────────────
$payload = file_get_contents('php://input');
$logDir  = __DIR__ . '/../../logs';
$logFile = $logDir . '/webhook.log';

if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}
file_put_contents($logFile, date('Y-m-d H:i:s') . ' | ' . $payload . PHP_EOL, FILE_APPEND | LOCK_EX);

// ── Processa a notificação ───────────────────────────────────────────────────
$data = json_decode($payload, true);

// Suporta formato v1 (type/data.id) e formato IPN antigo (topic/resource)
$tipo = $data['type'] ?? $data['topic'] ?? ($_GET['type'] ?? ($_GET['topic'] ?? ''));
$id   = $data['data']['id'] ?? ($_GET['data_id'] ?? null);

// Formato IPN: resource é o ID numérico do pagamento
if (!$id && isset($data['resource']) && is_numeric($data['resource'])) {
    $id = $data['resource'];
}
if (!$id && isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id = $_GET['id'];
}

if ($tipo !== 'payment' || !$id) {
    http_response_code(200);
    exit;
}

try {
    MercadoPagoConfig::setAccessToken(MP_ACCESS_TOKEN);

    $client    = new PaymentClient();
    $pagamento = $client->get((int) $id);

    if (!$pagamento) {
        http_response_code(200);
        exit;
    }

    $pedidoId = (int) $pagamento->external_reference;

    $novoStatus = mpStatusParaInterno($pagamento->status);

    $pdo = getDB();

    // Busca status atual antes de atualizar (para não re-enviar e-mail)
    $pedidoAtual = $pdo->prepare("SELECT status FROM pedidos WHERE id = :id");
    $pedidoAtual->execute([':id' => $pedidoId]);
    $statusAnterior = $pedidoAtual->fetchColumn();

    $pdo->prepare("UPDATE pedidos SET status = :status WHERE id = :id")
        ->execute([':status' => $novoStatus, ':id' => $pedidoId]);

    if ($novoStatus === 'cancelado') devolverEstoquePedido($pdo, $pedidoId, (string) $statusAnterior);

    // Envia e-mail de pagamento aprovado apenas na primeira transição
    if ($novoStatus === 'aprovado' && $statusAnterior !== 'aprovado') {
        // Auto-cria registro de rastreamento em "Em Preparação"
        $pdo->prepare("
            INSERT OR IGNORE INTO order_tracking (order_id, status, updated_at)
            VALUES (:oid, 0, :now)
        ")->execute([':oid' => (string) $pedidoId, ':now' => date('Y-m-d H:i:s')]);

        $stmtP = $pdo->prepare("SELECT * FROM pedidos WHERE id = :id");
        $stmtP->execute([':id' => $pedidoId]);
        $pedido = $stmtP->fetch(PDO::FETCH_ASSOC);

        $stmtI = $pdo->prepare("
            SELECT ip.quantidade, ip.preco_unitario, pr.nome AS produto_nome
            FROM itens_pedido ip
            JOIN produtos pr ON pr.id = ip.produto_id
            WHERE ip.pedido_id = :id
        ");
        $stmtI->execute([':id' => $pedidoId]);
        $itens = $stmtI->fetchAll(PDO::FETCH_ASSOC);

        if ($pedido && !empty($itens)) {
            emailPagamentoAprovado($pedido, $itens, $pedido['token_acompanhamento'] ?? '');
        }
    }

} catch (Exception $e) {
    file_put_contents($logFile, date('Y-m-d H:i:s') . ' | ERRO: ' . $e->getMessage() . PHP_EOL, FILE_APPEND | LOCK_EX);
}

http_response_code(200);
echo json_encode(['recebido' => true], JSON_UNESCAPED_UNICODE);
