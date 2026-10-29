<?php
/**
 * Gera o número de pedido exibido ao cliente/admin, no formato AAAAMM + sequencial
 * de 3 dígitos que reinicia todo mês (ex.: 202608000, 202608001, ... 202609000).
 * Não é a PK — pedidos.id continua sendo a chave interna usada em joins/FKs.
 *
 * Contador por mês em numero_pedido_seq (Patch T, migrations/migrate_numero_pedido_seq.php):
 * nunca decresce, então um número apagado não é reemitido. Chamar DENTRO da transação do
 * INSERT do pedido — o UPSERT segura o lock de escrita (checkouts paralelos esperam a vez) e
 * um rollback do pedido devolve o número junto.
 */
function proximoNumeroPedido(PDO $pdo): string
{
    $prefixo = date('Ym'); // AAAAMM — timezone America/Sao_Paulo já setado em _core.php/_auth.php

    $stmt = $pdo->prepare("
        INSERT INTO numero_pedido_seq (mes, ultimo) VALUES (:mes, 0)
        ON CONFLICT(mes) DO UPDATE SET ultimo = ultimo + 1
        RETURNING ultimo
    ");
    $stmt->execute([':mes' => $prefixo]);
    $seq = (int) $stmt->fetchColumn();
    $stmt->closeCursor(); // cursor aberto segura lock (lição do Patch K)

    return $prefixo . str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
}

/**
 * A exceção é colisão no UNIQUE de numero_pedido? (Patch T — decide o retry do INSERT do pedido)
 * O código estendido 2067 (SQLITE_CONSTRAINT_UNIQUE, ativado em getDB()) não diz QUAL índice
 * falhou — pedidos também tem UNIQUE(checkout_hash) —, então confirma no banco que o número
 * tentado já existe. Nunca lê a mensagem do erro. Chamar depois do rollback.
 */
function numeroPedidoColidiu(PDO $pdo, Throwable $e, ?string $numeroTentado): bool
{
    if (!$e instanceof PDOException || $numeroTentado === null) return false;
    if ((int) ($e->errorInfo[1] ?? 0) !== 2067) return false;

    $stmt = $pdo->prepare("SELECT 1 FROM pedidos WHERE numero_pedido = :n LIMIT 1");
    $stmt->execute([':n' => $numeroTentado]);
    $existe = $stmt->fetchColumn() !== false;
    $stmt->closeCursor();

    return $existe;
}
