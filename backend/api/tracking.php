<?php
/**
 * GET  /backend/api/tracking.php?order_id=X  — consulta pública (rate-limited)
 * POST /backend/api/tracking.php              — upsert (admin autenticado)
 *
 * Status: 0 Em Preparação | 1 Embalado | 2 Enviado | 3 Código de Rastreio
 */

// Sessão antes de qualquer header
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/_core.php';

$method = $_SERVER['REQUEST_METHOD'];

// ── GET — consulta pública ────────────────────────────────────────────────────
if ($method === 'GET') {

    // Rate limiting: 10 req/min por IP
    $ip     = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $window = date('YmdHi');
    $rlKey  = 'rl_tracking_' . md5($ip) . '_' . $window;
    $_SESSION[$rlKey] = ($_SESSION[$rlKey] ?? 0) + 1;
    if ($_SESSION[$rlKey] > 10) {
        json_erro('Muitas requisições. Tente novamente em um minuto.', 429);
    }

    $orderId = trim($_GET['order_id'] ?? '');
    if ($orderId === '') json_erro('order_id é obrigatório.', 400);
    $orderId = htmlspecialchars($orderId, ENT_QUOTES, 'UTF-8');

    $pdo  = getDB();
    $stmt = $pdo->prepare("SELECT * FROM order_tracking WHERE order_id = :oid LIMIT 1");
    $stmt->execute([':oid' => $orderId]);
    $row = $stmt->fetch();

    if (!$row) json_erro('Pedido não encontrado no sistema de rastreio.', 404);

    $stmtPedido = $pdo->prepare("SELECT status, numero_pedido FROM pedidos WHERE id = :id LIMIT 1");
    $stmtPedido->execute([':id' => (int) $orderId]);
    $dadosPedido  = $stmtPedido->fetch();
    $pedidoStatus = $dadosPedido['status'] ?? 'pendente';
    $numeroPedido = $dadosPedido['numero_pedido'] ?? null;

    $labels = ['Em Preparação', 'Embalado', 'Enviado', 'Código de Rastreio'];
    $status = (int) $row['status'];

    $trackingUrl = null;
    if ($row['tracking_code'] && preg_match('/^[A-Z]{2}[0-9]{9}BR$/', $row['tracking_code'])) {
        $trackingUrl = 'https://rastreamento.correios.com.br/app/index.php?objetos='
                     . urlencode($row['tracking_code']);
    }

    json_ok([
        'order_id'         => $row['order_id'],
        'numero_pedido'    => $numeroPedido,
        'pedido_status'    => $pedidoStatus,
        'status'           => $status,
        'status_label'     => $labels[$status] ?? 'Desconhecido',
        'tracking_code'    => $row['tracking_code'],
        'carrier'          => $row['carrier'],
        'tracking_url'     => $trackingUrl,
        'updated_at'       => $row['updated_at'],
        'notes'            => $row['notes'],
        'chosen_carrier'   => $row['chosen_carrier']   ?? null,
        'chosen_service'   => $row['chosen_service']   ?? null,
        'shipping_price'   => isset($row['shipping_price'])   ? (float) $row['shipping_price']   : null,
        'shipping_deadline'=> isset($row['shipping_deadline']) ? (int)   $row['shipping_deadline'] : null,
        'destination_cep'  => $row['destination_cep']  ?? null,
    ]);
}

// ── POST — upsert (admin) ─────────────────────────────────────────────────────
if ($method === 'POST') {

    if (empty($_SESSION['admin_id'])) {
        json_erro('Não autenticado.', 401);
    }

    $body    = body_json();
    $orderId = trim($body['order_id'] ?? '');
    if ($orderId === '') json_erro('order_id é obrigatório.', 400);
    $orderId = htmlspecialchars($orderId, ENT_QUOTES, 'UTF-8');

    $status = isset($body['status']) ? (int) $body['status'] : 0;
    if ($status < 0 || $status > 3) json_erro('status inválido. Use 0, 1, 2 ou 3.', 400);

    $trackingCode = isset($body['tracking_code']) ? trim($body['tracking_code']) : null;
    $carrier      = isset($body['carrier'])       ? trim($body['carrier'])       : null;
    $notes        = isset($body['notes'])         ? trim($body['notes'])         : null;

    $pdo = getDB();

    // Verifica se o pedido existe
    $chk = $pdo->prepare("SELECT id FROM pedidos WHERE id = :id LIMIT 1");
    $chk->execute([':id' => (int) $orderId]);
    if (!$chk->fetch()) json_erro('Pedido #' . $orderId . ' não encontrado.', 404);

    $now = date('Y-m-d H:i:s');
    $pdo->prepare("
        INSERT INTO order_tracking (order_id, status, tracking_code, carrier, notes, updated_at)
        VALUES (:oid, :status, :code, :carrier, :notes, :now)
        ON CONFLICT(order_id) DO UPDATE SET
            status        = excluded.status,
            tracking_code = excluded.tracking_code,
            carrier       = excluded.carrier,
            notes         = excluded.notes,
            updated_at    = :now
        -- chosen_carrier, chosen_service, chosen_service_id, shipping_price,
        -- shipping_deadline, destination_cep nunca são sobrescritas pelo admin
    ")->execute([
        ':oid'     => $orderId,
        ':status'  => $status,
        ':code'    => $trackingCode ?: null,
        ':carrier' => $carrier      ?: null,
        ':notes'   => $notes        ?: null,
        ':now'     => $now,
    ]);

    $labels = ['Em Preparação', 'Embalado', 'Enviado', 'Código de Rastreio'];
    json_ok([
        'message'      => 'Rastreamento atualizado com sucesso.',
        'order_id'     => $orderId,
        'status'       => $status,
        'status_label' => $labels[$status],
    ]);
}

json_erro('Método não permitido.', 405);
