<?php
/**
 * POST /backend/api/pedidos.php
 * Cria um novo pedido com seus itens.
 *
 * Body JSON esperado:
 * {
 *   "nome_comprador":     "João Silva",
 *   "email_comprador":    "joao@email.com",
 *   "telefone_comprador": "11999999999",
 *   "cep":                "01310-100",
 *   "endereco":           "Avenida Paulista",
 *   "numero":             "1000",
 *   "complemento":        "Apto 42",
 *   "bairro":             "Bela Vista",
 *   "cidade":             "São Paulo",
 *   "estado":             "SP",
 *   "produto_id":         1,
 *   "quantidade":         2
 * }
 */

require_once __DIR__ . '/_core.php';
require_once __DIR__ . '/../config/mercadopago.php';
require_once __DIR__ . '/../helpers/email.php';
require_once __DIR__ . '/../helpers/numero_pedido.php';

exigir_metodo('POST');

$body = body_json();

$nome        = campo($body, 'nome_comprador');
$email       = campo($body, 'email_comprador');
$telefone    = campo($body, 'telefone_comprador');
$cep         = trim($body['cep']         ?? '');
$endereco    = trim($body['endereco']    ?? '');
$numero      = trim($body['numero']      ?? '');
$complemento = trim($body['complemento'] ?? '');
$bairro      = trim($body['bairro']      ?? '');
$cidade      = trim($body['cidade']      ?? '');
$estado      = trim($body['estado']      ?? '');
$produtoId   = (int) ($body['produto_id']  ?? 0);
$quantidade  = (int) ($body['quantidade']  ?? 0);
$freteEscolhido = $body['frete_escolhido'] ?? null; // opcional

if ($produtoId <= 0)  json_erro('produto_id inválido.', 422);
if ($quantidade <= 0) json_erro('quantidade deve ser maior que zero.', 422);

// Valida formato de e-mail
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_erro('E-mail inválido.', 422);
}

try {
    $pdo = getDB();

    // Busca o produto para obter preço atual
    $stmt = $pdo->prepare("SELECT id, nome, preco, estoque, ativo FROM produtos WHERE id = :id");
    $stmt->execute([':id' => $produtoId]);
    $produto = $stmt->fetch();

    if (!$produto) {
        json_erro('Produto não encontrado.', 404);
    }
    if (!$produto['ativo']) {
        json_erro('Produto indisponível.', 422);
    }
    if ($produto['estoque'] < $quantidade) {
        json_erro('Estoque insuficiente.', 422);
    }

    $precoUnitario = (float) $produto['preco'];
    $subtotal      = $precoUnitario * $quantidade;

    // Valida frete antes da transação para incluir no total
    $fretePrice     = 0.0;
    $freteValidado  = false;
    $freteCarrier   = null;
    $freteService   = null;
    $freteServiceId = null;
    $freteDeadline  = null;
    $destCep        = null;

    if (is_array($freteEscolhido) && isset($freteEscolhido['id'])) {
        $serviceId  = (int)   ($freteEscolhido['id']            ?? 0);
        $carrier    = trim(   $freteEscolhido['transportadora']  ?? '');
        $service    = trim(   $freteEscolhido['nome']            ?? '');
        $price      = (float) ($freteEscolhido['preco']          ?? 0);
        $deadline   = (int)   ($freteEscolhido['prazo_max']      ?? 0);
        $destCep    = preg_replace('/\D/', '', $cep);

        // Valida contra o cache de cotações (anti-adulteração de preço)
        $cacheKey = md5('v1:' . $produtoId . ':' . $destCep);
        $cHit = $pdo->prepare("SELECT payload, criado_em FROM cache_cotacoes WHERE cache_key = :k");
        $cHit->execute([':k' => $cacheKey]);
        $cached = $cHit->fetch();

        if ($cached && (time() - strtotime($cached['criado_em'])) < 12 * 3600) {
            $payload = json_decode($cached['payload'], true);
            foreach (($payload['servicos'] ?? []) as $sv) {
                if ((int) $sv['id'] === $serviceId) {
                    if (abs((float) $sv['preco'] - $price) <= 0.10) {
                        $carrier       = $sv['transportadora'];
                        $service       = $sv['nome'];
                        $price         = (float) $sv['preco'];
                        $deadline      = (int) $sv['prazo_max'];
                        $freteValidado = true;
                    }
                    break;
                }
            }
        } else {
            $freteValidado = ($serviceId > 0 && $carrier !== '' && $service !== '');
        }

        if ($freteValidado) {
            $fretePrice     = $price;
            $freteCarrier   = $carrier;
            $freteService   = $service;
            $freteServiceId = $serviceId;
            $freteDeadline  = $deadline;
        }
    }

    $total = $subtotal + $fretePrice;

    $token = bin2hex(random_bytes(16));

    // Insere o pedido (total já inclui frete). Retry em caso de corrida rara no
    // UNIQUE(numero_pedido) — dois requests gerando o mesmo próximo sequencial do mês.
    $maxTentativas = 5;
    for ($tentativa = 0; $tentativa < $maxTentativas; $tentativa++) {
        $numeroPedido = proximoNumeroPedido($pdo);
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                INSERT INTO pedidos (nome_comprador, email_comprador, telefone_comprador,
                                     cep, endereco, numero, complemento, bairro, cidade, estado,
                                     total, status, token_acompanhamento, numero_pedido, criado_em)
                VALUES (:nome, :email, :telefone,
                        :cep, :endereco, :numero, :complemento, :bairro, :cidade, :estado,
                        :total, 'pendente', :token, :numero_pedido, :criado_em)
            ");
            $stmt->execute([
                ':nome'          => $nome,
                ':email'         => $email,
                ':telefone'      => $telefone,
                ':cep'           => $cep,
                ':endereco'      => $endereco,
                ':numero'        => $numero,
                ':complemento'   => $complemento,
                ':bairro'        => $bairro,
                ':cidade'        => $cidade,
                ':estado'        => $estado,
                ':total'         => $total,
                ':token'         => $token,
                ':numero_pedido' => $numeroPedido,
                ':criado_em'     => date('Y-m-d H:i:s'),
            ]);
            $pedidoId = (int) $pdo->lastInsertId();
            break;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if (strpos($e->getMessage(), 'numero_pedido') !== false && $tentativa < $maxTentativas - 1) {
                continue;
            }
            throw $e;
        }
    }

    // Insere o item do pedido
    $stmt = $pdo->prepare("
        INSERT INTO itens_pedido (pedido_id, produto_id, quantidade, preco_unitario)
        VALUES (:pedido_id, :produto_id, :quantidade, :preco_unitario)
    ");
    $stmt->execute([
        ':pedido_id'      => $pedidoId,
        ':produto_id'     => $produtoId,
        ':quantidade'     => $quantidade,
        ':preco_unitario' => $precoUnitario,
    ]);

    // Decrementa o estoque
    $pdo->prepare("UPDATE produtos SET estoque = estoque - :qty WHERE id = :id")
        ->execute([':qty' => $quantidade, ':id' => $produtoId]);

    $pdo->commit();

    // Persiste escolha de frete em order_tracking
    if ($freteValidado) {
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
            ':oid'            => (string) $pedidoId,
            ':carrier'        => $freteCarrier,
            ':chosen_carrier' => $freteCarrier,
            ':chosen_service' => $freteService,
            ':service_id'     => $freteServiceId,
            ':price'          => $fretePrice,
            ':deadline'       => $freteDeadline,
            ':dest_cep'       => $destCep,
            ':now'            => date('Y-m-d H:i:s'),
        ]);
    }

    // Envia e-mail de confirmação de pedido recebido
    $pedidoEmail = [
        'id'               => $pedidoId,
        'numero_pedido'    => $numeroPedido,
        'nome_comprador'   => $nome,
        'email_comprador'  => $email,
        'total'            => $total,
        'status'           => 'pendente',
        'criado_em'        => date('d/m/Y H:i'),
        'endereco'         => $endereco,
        'numero'           => $numero,
        'complemento'      => $complemento,
        'bairro'           => $bairro,
        'cidade'           => $cidade,
        'estado'           => $estado,
        'cep'              => $cep,
    ];
    $itensEmail = [['produto_nome' => $produto['nome'] ?? 'Produto', 'quantidade' => $quantidade, 'preco_unitario' => $precoUnitario]];
    emailPedidoCriado($pedidoEmail, $itensEmail, $token);

    json_ok([
        'pedido_id'     => $pedidoId,
        'numero_pedido' => $numeroPedido,
        'total'         => $total,
        'subtotal'      => $subtotal,
        'frete'         => $fretePrice,
        'status'        => 'pendente',
        'token'         => $token,
    ], 201);

} catch (Exception $e) {
    $pdo->inTransaction() && $pdo->rollBack();
    json_erro('Erro ao criar pedido.', 500);
}
