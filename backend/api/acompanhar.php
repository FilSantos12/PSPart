<?php
/**
 * GET /backend/api/acompanhar.php
 * Retorna dados de um pedido para o cliente acompanhar.
 *
 * Formas de consulta:
 *   ?token=<token_acompanhamento>                  (link do e-mail)
 *   ?pedido_id=<id>&email=<email_comprador>         (busca manual)
 */

require_once __DIR__ . '/_core.php';

exigir_metodo('GET');

$pdo      = getDB();
$token    = trim($_GET['token']      ?? '');
$pidRaw   = trim($_GET['pedido_id']  ?? '');
$email    = trim($_GET['email']      ?? '');

if ($token !== '') {
    $stmt = $pdo->prepare("SELECT * FROM pedidos WHERE token_acompanhamento = :token LIMIT 1");
    $stmt->execute([':token' => $token]);
} elseif ($pidRaw !== '' && $email !== '') {
    // Aceita o número de pedido exibido ao cliente (AAAAMM+sequencial) e,
    // por compatibilidade, o ID interno legado.
    $stmt = $pdo->prepare("
        SELECT * FROM pedidos
        WHERE (numero_pedido = :num OR id = :id) AND LOWER(email_comprador) = LOWER(:email)
        LIMIT 1
    ");
    $stmt->execute([
        ':num'   => $pidRaw,
        ':id'    => ctype_digit($pidRaw) ? (int) $pidRaw : 0,
        ':email' => $email,
    ]);
} else {
    json_erro('Informe o token ou pedido_id + email.', 422);
}

$pedido = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pedido) {
    json_erro('Pedido não encontrado. Verifique os dados informados.', 404);
}

// Busca os itens do pedido
$stmtItens = $pdo->prepare("
    SELECT ip.quantidade, ip.preco_unitario, pr.nome AS produto_nome, pr.imagem
    FROM itens_pedido ip
    JOIN produtos pr ON pr.id = ip.produto_id
    WHERE ip.pedido_id = :id
");
$stmtItens->execute([':id' => $pedido['id']]);
$itens = $stmtItens->fetchAll(PDO::FETCH_ASSOC);

// Remove dados sensíveis antes de retornar
unset($pedido['mp_preferencia_id'], $pedido['token_acompanhamento']);

json_ok([
    'pedido' => $pedido,
    'itens'  => $itens,
]);
