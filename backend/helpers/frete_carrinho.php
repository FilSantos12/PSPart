<?php
/**
 * Cotação/cache de frete agregado do carrinho — compartilhado entre
 * backend/api/frete-carrinho.php (cotação para exibição, B2a) e
 * backend/api/pedido-carrinho.php (rederivação server-side no checkout, B2b).
 *
 * Peso/dimensão/preço vêm sempre do banco (resolverCarrinho) — nunca do browser.
 */

require_once __DIR__ . '/MelhorEnvio.php';

define('FRETE_CARRINHO_CACHE_TTL', 30 * 60); // 30 min — carrinho muda mais que item único (12h)

/** Falha "de negócio" (produto indisponível, sem serviço pro CEP) — sempre HTTP 422, nunca 502. */
class FreteIndisponivelException extends RuntimeException {}

/**
 * Normaliza o carrinho bruto do browser ({produto_id, quantidade}), agrega
 * duplicatas e resolve cada item no banco. Lança em qualquer inconsistência.
 *
 * @param PDO   $pdo
 * @param array $cart [{produto_id, quantidade}] — payload cru do browser
 * @return array Carrinho resolvido, ordenado por id: [{produto: linha do banco, quantidade}]
 * @throws InvalidArgumentException carrinho malformado (erro do cliente, HTTP 422)
 * @throws RuntimeException         produto indisponível/sem dimensão (HTTP 422)
 */
function resolverCarrinho(PDO $pdo, array $cart): array
{
    if (empty($cart)) {
        throw new InvalidArgumentException('Carrinho vazio.');
    }

    $itensCarrinho = [];
    foreach ($cart as $item) {
        $id  = (int) ($item['produto_id'] ?? 0);
        $qty = (int) ($item['quantidade'] ?? 0);
        if ($id <= 0 || $qty <= 0 || $qty > 99) {
            throw new InvalidArgumentException('Item de carrinho inválido.');
        }
        $itensCarrinho[$id] = ($itensCarrinho[$id] ?? 0) + $qty;
    }
    ksort($itensCarrinho);

    $ids          = array_keys($itensCarrinho);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("
        SELECT id, nome, preco, peso, largura, altura, comprimento, estoque, ativo
        FROM produtos
        WHERE id IN ($placeholders) AND ativo = 1
    ");
    $stmt->execute($ids);
    $produtosDb = $stmt->fetchAll();

    if (count($produtosDb) !== count($ids)) {
        throw new FreteIndisponivelException('Um ou mais produtos do carrinho não estão mais disponíveis.');
    }

    $produtosPorId = [];
    foreach ($produtosDb as $p) {
        if ((float) $p['peso'] <= 0) {
            throw new FreteIndisponivelException("Produto \"{$p['nome']}\" sem dimensões cadastradas. Configure peso e dimensões no admin.");
        }
        $produtosPorId[(int) $p['id']] = $p;
    }

    $carrinhoResolvido = [];
    foreach ($itensCarrinho as $id => $qty) {
        $carrinhoResolvido[] = ['produto' => $produtosPorId[$id], 'quantidade' => $qty];
    }

    return $carrinhoResolvido;
}

/**
 * Chave de cache determinística — hash do carrinho já resolvido no servidor (nunca
 * do payload cru do browser) + CEP. Namespace "v1:cart:" — não colide com o cache
 * item-único ("v1:{produto_id}:{cep}") de backend/api/frete.php.
 */
function chaveCacheCarrinho(array $carrinhoResolvido, string $cepRaw): string
{
    $hashBase = implode('|', array_map(
        fn($c) => $c['produto']['id'] . ':' . $c['quantidade'],
        $carrinhoResolvido
    ));
    return md5('v1:cart:' . $hashBase . ':' . $cepRaw);
}

/**
 * Cotação de frete agregada, com cache (hit reaproveita; miss recota e grava).
 * Sempre retorna o payload completo { ok, cep, servicos[], cache }.
 *
 * @throws RuntimeException em falha de cotação real (integração fora do ar, etc.)
 */
function cotarFreteCarrinho(PDO $pdo, array $carrinhoResolvido, string $cepRaw): array
{
    $cacheKey = chaveCacheCarrinho($carrinhoResolvido, $cepRaw);

    $cStmt = $pdo->prepare("SELECT payload, criado_em FROM cache_cotacoes WHERE cache_key = :k");
    $cStmt->execute([':k' => $cacheKey]);
    $hit = $cStmt->fetch();

    if ($hit && (time() - strtotime($hit['criado_em'])) < FRETE_CARRINHO_CACHE_TTL) {
        $cached          = json_decode($hit['payload'], true);
        $cached['cache'] = true;
        return $cached;
    }

    $meStatus = MelhorEnvio::getStatus();
    if (!in_array($meStatus['status'], ['ok', 'expira_em_breve'])) {
        throw new RuntimeException('Cálculo de frete temporariamente indisponível.');
    }

    $me       = new MelhorEnvio();
    $servicos = $me->calcularFreteCarrinho($carrinhoResolvido, $cepRaw);

    if (empty($servicos)) {
        throw new FreteIndisponivelException('Nenhum serviço de entrega disponível para este CEP.');
    }

    $resposta = [
        'ok'       => true,
        'cep'      => substr($cepRaw, 0, 5) . '-' . substr($cepRaw, 5),
        'servicos' => $servicos,
        'cache'    => false,
    ];

    $pdo->prepare("
        INSERT INTO cache_cotacoes (cache_key, payload, criado_em)
        VALUES (:k, :p, :t)
        ON CONFLICT(cache_key) DO UPDATE SET payload = :p, criado_em = :t
    ")->execute([
        ':k' => $cacheKey,
        ':p' => json_encode($resposta, JSON_UNESCAPED_UNICODE),
        ':t' => date('Y-m-d H:i:s'),
    ]);

    return $resposta;
}
