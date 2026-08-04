<?php
/**
 * POST /backend/api/pedido-carrinho.php
 * Checkout do carrinho (B2b) — cria o pedido a partir de psp_cart, rederiva o frete
 * no servidor (nunca confia no preço do browser), monta a preference multi-item do
 * Mercado Pago e retorna o init_point para redirect.
 *
 * Body JSON esperado:
 * {
 *   "nome_comprador": "João Silva", "email_comprador": "joao@x.com", "telefone_comprador": "11999999999",
 *   "cep_destino": "01310100", "endereco": "Av Paulista", "numero": "1000", "complemento": "",
 *   "bairro": "Bela Vista", "cidade": "São Paulo", "estado": "SP",
 *   "cart": [{"produto_id": 1, "quantidade": 2}],
 *   "service_id": 3,
 *   "preco_frete_confirmado": 16.34   // opcional — último preço visto pelo cliente (ver divergência)
 * }
 *
 * Linhas vermelhas (B2b):
 * - Frete sempre rederivado aqui (cache quente ou recotação) — nunca aceito do browser.
 * - Itens sempre precificados do banco (× quantidade do carrinho) — nunca do browser.
 * - Sem frete válido para o service_id pedido → recusa (não confia no botão desabilitado do client).
 * - Idempotência via pedidos.checkout_hash: duplo submit reaproveita o pedido/preference
 *   pendente em vez de duplicar (estoque, cobrança, e-mail).
 * - Se o preço rederivado divergir do último visto pelo cliente, recusa com o novo valor
 *   e exige reconfirmação (não cobra silenciosamente um valor diferente do exibido).
 */

require_once __DIR__ . '/_core.php';
require_once __DIR__ . '/../config/mercadopago.php';
require_once __DIR__ . '/../config/melhorenvio.php';
require_once __DIR__ . '/../helpers/frete_carrinho.php';
require_once __DIR__ . '/../helpers/mercadopago_preference.php';
require_once __DIR__ . '/../helpers/email.php';

exigir_metodo('POST');

$body = body_json();

$nome        = campo($body, 'nome_comprador');
$email       = campo($body, 'email_comprador');
$telefone    = campo($body, 'telefone_comprador');
$endereco    = trim($body['endereco']    ?? '');
$numero      = trim($body['numero']      ?? '');
$complemento = trim($body['complemento'] ?? '');
$bairro      = trim($body['bairro']      ?? '');
$cidade      = trim($body['cidade']      ?? '');
$estado      = trim($body['estado']      ?? '');
$cart        = $body['cart']             ?? [];
$serviceId   = (int) ($body['service_id'] ?? 0);
$precoConfirmado = array_key_exists('preco_frete_confirmado', $body) ? (float) $body['preco_frete_confirmado'] : null;
$cepRaw      = preg_replace('/\D/', '', $body['cep_destino'] ?? '');
$cepFmt      = strlen($cepRaw) === 8 ? substr($cepRaw, 0, 5) . '-' . substr($cepRaw, 5) : $cepRaw;

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) json_erro('E-mail inválido.', 422);
if (!is_array($cart) || empty($cart))            json_erro('Carrinho vazio.', 422);
if (strlen($cepRaw) !== 8)                       json_erro('CEP inválido. Informe 8 dígitos.', 422);
if ($serviceId <= 0)                             json_erro('Selecione uma opção de frete.', 422);

try {
    $pdo = getDB();

    $carrinhoResolvido = resolverCarrinho($pdo, $cart);

    // ── Rederivação do frete — sempre server-side (linha vermelha 1/3) ──────────
    $cotacao = cotarFreteCarrinho($pdo, $carrinhoResolvido, $cepRaw);

    $servicoEscolhido = null;
    foreach ($cotacao['servicos'] as $s) {
        if ((int) $s['id'] === $serviceId) { $servicoEscolhido = $s; break; }
    }
    if (!$servicoEscolhido) {
        json_erro('A opção de frete selecionada não está mais disponível. Escolha novamente.', 422);
    }

    $subtotal = 0.0;
    foreach ($carrinhoResolvido as $item) {
        $subtotal += (float) $item['produto']['preco'] * $item['quantidade'];
    }
    $fretePrice = (float) $servicoEscolhido['preco'];
    $total      = $subtotal + $fretePrice;

    // ── Política de divergência (Estágio 3 — default do dono: bloquear e reconfirmar) ──
    if ($precoConfirmado !== null && abs($fretePrice - $precoConfirmado) > 0.10) {
        json_ok([
            'ok'        => false,
            'diverge'   => true,
            'erro'      => 'O valor do frete mudou desde a última cotação. Confira o novo valor e confirme novamente.',
            'servicos'  => $cotacao['servicos'],
            'novo_frete'=> $servicoEscolhido,
            'subtotal'  => $subtotal,
            'novo_total'=> $total,
        ], 409);
    }

    // ── Idempotência: reaproveita pedido pendente recente com o mesmo carrinho+cep+serviço+comprador ──
    $checkoutHash = md5(mb_strtolower($email) . '|' . chaveCacheCarrinho($carrinhoResolvido, $cepRaw) . '|' . $serviceId);
    $cutoff       = date('Y-m-d H:i:s', time() - 30 * 60);

    $stmtExistente = $pdo->prepare("
        SELECT id FROM pedidos
        WHERE checkout_hash = :h AND status = 'pendente' AND criado_em > :cutoff
        ORDER BY id DESC LIMIT 1
    ");
    $stmtExistente->execute([':h' => $checkoutHash, ':cutoff' => $cutoff]);
    $pedidoId = $stmtExistente->fetchColumn();

    if ($pedidoId) {
        $pedidoId = (int) $pedidoId;
    } else {
        $pedidoId = criarPedidoCarrinho(
            $pdo, $checkoutHash, $carrinhoResolvido, $servicoEscolhido,
            $subtotal, $total, $cepFmt, $cepRaw,
            $nome, $email, $telefone, $endereco, $numero, $complemento, $bairro, $cidade, $estado
        );
    }

    // ── Preference (idempotente — reaproveita se o pedido já tem uma) ───────────
    $preference = criarOuObterPreferencePedido($pdo, $pedidoId);

    $stmtToken = $pdo->prepare("SELECT token_acompanhamento FROM pedidos WHERE id = :id");
    $stmtToken->execute([':id' => $pedidoId]);

    json_ok([
        'ok'         => true,
        'pedido_id'  => $pedidoId,
        'subtotal'   => $subtotal,
        'frete'      => $fretePrice,
        'total'      => $total,
        'token'      => $stmtToken->fetchColumn(),
        'init_point' => $preference['init_point'],
    ], 201);

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
    json_erro('Erro ao criar pedido: ' . $e->getMessage(), 500);
}

/**
 * Cria o pedido + itens (loop) + order_tracking.chosen_* com o frete já rederivado.
 * Corre em transação; sob corrida rara no UNIQUE(checkout_hash), recua e reaproveita
 * o pedido que o concorrente acabou de criar em vez de falhar.
 */
function criarPedidoCarrinho(
    PDO $pdo, string $checkoutHash, array $carrinhoResolvido, array $servicoEscolhido,
    float $subtotal, float $total, string $cepFmt, string $cepRaw,
    string $nome, string $email, string $telefone,
    string $endereco, string $numero, string $complemento, string $bairro, string $cidade, string $estado
): int {
    foreach ($carrinhoResolvido as $item) {
        if ((int) $item['produto']['estoque'] < $item['quantidade']) {
            throw new FreteIndisponivelException("Estoque insuficiente para \"{$item['produto']['nome']}\".");
        }
    }

    $token = bin2hex(random_bytes(16));

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            INSERT INTO pedidos (nome_comprador, email_comprador, telefone_comprador,
                                 cep, endereco, numero, complemento, bairro, cidade, estado,
                                 total, status, token_acompanhamento, checkout_hash, criado_em)
            VALUES (:nome, :email, :telefone,
                    :cep, :endereco, :numero, :complemento, :bairro, :cidade, :estado,
                    :total, 'pendente', :token, :hash, :criado_em)
        ");
        $stmt->execute([
            ':nome' => $nome, ':email' => $email, ':telefone' => $telefone,
            ':cep' => $cepFmt, ':endereco' => $endereco, ':numero' => $numero,
            ':complemento' => $complemento, ':bairro' => $bairro, ':cidade' => $cidade, ':estado' => $estado,
            ':total' => $total, ':token' => $token, ':hash' => $checkoutHash,
            ':criado_em' => date('Y-m-d H:i:s'),
        ]);
        $pedidoId = (int) $pdo->lastInsertId();

        $stmtItem = $pdo->prepare("
            INSERT INTO itens_pedido (pedido_id, produto_id, quantidade, preco_unitario)
            VALUES (:pedido_id, :produto_id, :quantidade, :preco_unitario)
        ");
        $stmtEstoque = $pdo->prepare("UPDATE produtos SET estoque = estoque - :qty WHERE id = :id");

        $itensEmail = [];
        foreach ($carrinhoResolvido as $item) {
            $produto = $item['produto'];
            $stmtItem->execute([
                ':pedido_id' => $pedidoId, ':produto_id' => $produto['id'],
                ':quantidade' => $item['quantidade'], ':preco_unitario' => $produto['preco'],
            ]);
            $stmtEstoque->execute([':qty' => $item['quantidade'], ':id' => $produto['id']]);
            $itensEmail[] = [
                'produto_nome'   => $produto['nome'],
                'quantidade'     => $item['quantidade'],
                'preco_unitario' => $produto['preco'],
            ];
        }

        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();

        // UNIQUE(checkout_hash) — corrida com outra requisição idêntica: reaproveita o pedido dela.
        $stmtExistente = $pdo->prepare("SELECT id FROM pedidos WHERE checkout_hash = :h ORDER BY id DESC LIMIT 1");
        $stmtExistente->execute([':h' => $checkoutHash]);
        $existenteId = $stmtExistente->fetchColumn();
        if ($existenteId) return (int) $existenteId;

        throw $e;
    }

    // Persiste o frete rederivado em order_tracking (fonte única — nunca o do browser)
    $pdo->prepare("
        INSERT INTO order_tracking
            (order_id, status, carrier, chosen_carrier, chosen_service,
             chosen_service_id, shipping_price, shipping_deadline, destination_cep, updated_at)
        VALUES
            (:oid, 0, :carrier, :chosen_carrier, :chosen_service,
             :service_id, :price, :deadline, :dest_cep, :now)
        ON CONFLICT(order_id) DO UPDATE SET
            chosen_carrier    = excluded.chosen_carrier,
            chosen_service    = excluded.chosen_service,
            chosen_service_id = excluded.chosen_service_id,
            shipping_price    = excluded.shipping_price,
            shipping_deadline = excluded.shipping_deadline,
            destination_cep   = excluded.destination_cep,
            carrier           = excluded.carrier,
            updated_at        = excluded.updated_at
    ")->execute([
        ':oid' => (string) $pedidoId,
        ':carrier' => $servicoEscolhido['transportadora'], ':chosen_carrier' => $servicoEscolhido['transportadora'],
        ':chosen_service' => $servicoEscolhido['nome'], ':service_id' => $servicoEscolhido['id'],
        ':price' => $servicoEscolhido['preco'], ':deadline' => $servicoEscolhido['prazo_max'],
        ':dest_cep' => $cepRaw, ':now' => date('Y-m-d H:i:s'),
    ]);

    $pedidoEmail = [
        'id' => $pedidoId, 'nome_comprador' => $nome, 'email_comprador' => $email,
        'total' => $total, 'status' => 'pendente', 'criado_em' => date('d/m/Y H:i'),
        'endereco' => $endereco, 'numero' => $numero, 'complemento' => $complemento,
        'bairro' => $bairro, 'cidade' => $cidade, 'estado' => $estado, 'cep' => $cepFmt,
    ];
    emailPedidoCriado($pedidoEmail, $itensEmail, $token);

    return $pedidoId;
}
