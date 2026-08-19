<?php
/**
 * Gera o número de pedido exibido ao cliente/admin, no formato AAAAMM + sequencial
 * de 3 dígitos que reinicia todo mês (ex.: 202608000, 202608001, ... 202609000).
 * Não é a PK — pedidos.id continua sendo a chave interna usada em joins/FKs.
 */
function proximoNumeroPedido(PDO $pdo): string
{
    $prefixo = date('Ym'); // AAAAMM — timezone America/Sao_Paulo já setado em _core.php/_auth.php

    $stmt = $pdo->prepare("
        SELECT MAX(CAST(SUBSTR(numero_pedido, 7, 3) AS INTEGER))
        FROM pedidos
        WHERE numero_pedido LIKE :prefixo
    ");
    $stmt->execute([':prefixo' => $prefixo . '%']);
    $max = $stmt->fetchColumn();

    $seq = ($max !== null && $max !== false) ? ((int) $max + 1) : 0;

    return $prefixo . str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
}
