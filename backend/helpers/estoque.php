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
 * pedidos.estoque_devolvido tem três estados (Patch R):
 *   0 = não devolvido (elegível) · 1 = devolvido · 2 = devolução NEGADA (pedido estava pago)
 * Só o 0 é elegível; 1 e 2 são terminais. O 2 existe porque o Mercado Pago reenvia
 * webhooks: a 1ª notificação grava 'cancelado', e no retry o status anterior já não é
 * mais 'aprovado' — sem a recusa persistida, a guarda se apagava e o estoque voltava.
 *
 * Status anterior aprovado/em_processamento → grava 2 e não devolve (devolução de pedido
 * pago é decisão humana). Ambas as marcações são UPDATE condicional (WHERE = 0), então
 * chamadas concorrentes ou repetidas agem uma vez só.
 *
 * Nunca lança: falha é registrada em logs/estoque.log e devolve false, para não
 * interferir na resposta de pagamento/webhook/admin que a chamou.
 *
 * @return bool true se devolveu nesta chamada
 */
function devolverEstoquePedido(PDO $pdo, int $pedidoId, string $statusAnterior): bool
{
    try {
        if (in_array($statusAnterior, ['aprovado', 'em_processamento'], true)) {
            $nega = $pdo->prepare("UPDATE pedidos SET estoque_devolvido = 2 WHERE id = :id AND estoque_devolvido = 0");
            $nega->execute([':id' => $pedidoId]);
            if ($nega->rowCount() === 0) {
                _logEstoque("pedido #{$pedidoId}: devolução ignorada (" . _estadoDevolucao($pdo, $pedidoId) . ')');
                return false;
            }
            _logEstoque("pedido #{$pedidoId}: devolução NEGADA (status anterior {$statusAnterior}, pedido pago — decisão humana); estoque_devolvido = 2");
            return false;
        }

        $pdo->beginTransaction();

        $marca = $pdo->prepare("UPDATE pedidos SET estoque_devolvido = 1 WHERE id = :id AND estoque_devolvido = 0");
        $marca->execute([':id' => $pedidoId]);
        if ($marca->rowCount() === 0) {
            $pdo->rollBack();
            _logEstoque("pedido #{$pedidoId}: devolução ignorada (" . _estadoDevolucao($pdo, $pedidoId) . ')');
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

/** Motivo legível de um pedido não elegível, para o log. */
function _estadoDevolucao(PDO $pdo, int $pedidoId): string
{
    $stmt = $pdo->prepare("SELECT estoque_devolvido FROM pedidos WHERE id = :id");
    $stmt->execute([':id' => $pedidoId]);
    $estado = $stmt->fetchColumn();
    $stmt->closeCursor();
    return match ((string) $estado) {
        '1'     => 'já devolvido',
        '2'     => 'devolução negada antes — pedido estava pago',
        ''      => 'pedido inexistente',
        default => "estoque_devolvido = {$estado}",
    };
}

function _logEstoque(string $mensagem): void
{
    $dir = __DIR__ . '/../../logs';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    @file_put_contents($dir . '/estoque.log', date('Y-m-d H:i:s') . ' | ' . $mensagem . PHP_EOL, FILE_APPEND | LOCK_EX);
}
