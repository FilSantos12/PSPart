<?php
/**
 * Devolução de estoque de pedido (Patch K). Único caminho que devolve estoque — nenhum
 * outro arquivo soma em produtos.estoque por fora daqui.
 *
 * Chamada só na transição para 'cancelado' (webhook.php, processar-pagamento.php e
 * admin/pedido-detalhe.php), sempre DEPOIS da gravação do status. Recusado e abandonado
 * não devolvem (previsto para o Patch M, expiração por tempo).
 */

/**
 * Devolve ao estoque o que o pedido de fato tirou dele: quantidade − qtd_sob_encomenda
 * por item (o que foi sob encomenda nunca esteve no estoque).
 *
 * Não faz nada quando o status anterior era aprovado/em_processamento (devolução de
 * pedido pago é decisão humana) ou quando o pedido já teve o estoque devolvido
 * (webhook duplicado, retry). A marcação em pedidos.estoque_devolvido é feita por
 * UPDATE condicional, então duas chamadas concorrentes devolvem uma vez só.
 *
 * Nunca lança: falha é registrada em logs/estoque.log e devolve false, para não
 * interferir na resposta de pagamento/webhook/admin que a chamou.
 *
 * @return bool true se devolveu nesta chamada
 */
function devolverEstoquePedido(PDO $pdo, int $pedidoId, string $statusAnterior): bool
{
    if (in_array($statusAnterior, ['aprovado', 'em_processamento'], true)) {
        _logEstoque("pedido #{$pedidoId}: devolução ignorada (status anterior {$statusAnterior})");
        return false;
    }

    try {
        $pdo->beginTransaction();

        $marca = $pdo->prepare("UPDATE pedidos SET estoque_devolvido = 1 WHERE id = :id AND estoque_devolvido = 0");
        $marca->execute([':id' => $pedidoId]);
        if ($marca->rowCount() === 0) {
            $pdo->rollBack();
            _logEstoque("pedido #{$pedidoId}: devolução ignorada (já devolvido ou pedido inexistente)");
            return false;
        }

        $itens = $pdo->prepare("SELECT produto_id, quantidade, qtd_sob_encomenda FROM itens_pedido WHERE pedido_id = :id");
        $itens->execute([':id' => $pedidoId]);

        $soma   = $pdo->prepare("UPDATE produtos SET estoque = estoque + :qtd WHERE id = :id");
        $partes = [];
        foreach ($itens->fetchAll(PDO::FETCH_ASSOC) as $item) {
            $qtd = max(0, (int) $item['quantidade'] - (int) $item['qtd_sob_encomenda']);
            if ($qtd === 0) continue;
            $soma->execute([':qtd' => $qtd, ':id' => $item['produto_id']]);
            $partes[] = "produto {$item['produto_id']} +{$qtd}";
        }

        $pdo->commit();
        _logEstoque("pedido #{$pedidoId}: estoque devolvido (" . ($partes ? implode(', ', $partes) : 'nada saiu do estoque') . ')');
        return true;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        _logEstoque("pedido #{$pedidoId}: ERRO na devolução — " . $e->getMessage());
        return false;
    }
}

function _logEstoque(string $mensagem): void
{
    $dir = __DIR__ . '/../../logs';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    @file_put_contents($dir . '/estoque.log', date('Y-m-d H:i:s') . ' | ' . $mensagem . PHP_EOL, FILE_APPEND | LOCK_EX);
}
