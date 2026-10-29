<?php
/**
 * Migração idempotente (Patch T): cria numero_pedido_seq — contador por mês usado por
 * proximoNumeroPedido(). O contador nunca decresce, então um número apagado não é reemitido.
 * Semeia cada mês já presente em pedidos.numero_pedido com o MAIOR sequencial usado nele
 * (não a contagem de linhas: 202605 começa em 002). Rodar de novo não altera o contador e
 * nunca o faz decrescer (MAX entre o valor gravado e o maior emitido).
 * Executar pelo terminal: `php migrations/migrate_numero_pedido_seq.php`; pode rodar 2x.
 * Rodar ANTES de publicar o novo helper — sem a tabela, o checkout falha.
 */

require_once __DIR__ . '/../backend/config/database.php';

$pdo = getDB();

$saida = [];

$pdo->exec("
    CREATE TABLE IF NOT EXISTS numero_pedido_seq (
        mes    TEXT PRIMARY KEY,   -- AAAAMM
        ultimo INTEGER NOT NULL    -- último sequencial emitido no mês
    )
");
$saida[] = "Tabela numero_pedido_seq OK.";

// Sequencial = tudo após AAAAMM (sem limite de 3 dígitos); só números no formato esperado
$semeia = $pdo->exec("
    INSERT INTO numero_pedido_seq (mes, ultimo)
    SELECT SUBSTR(numero_pedido, 1, 6), MAX(CAST(SUBSTR(numero_pedido, 7) AS INTEGER))
    FROM pedidos
    WHERE numero_pedido GLOB '[0-9][0-9][0-9][0-9][0-9][0-9][0-9]*'
    GROUP BY SUBSTR(numero_pedido, 1, 6)
    ON CONFLICT(mes) DO UPDATE SET ultimo = MAX(ultimo, excluded.ultimo)
");
$saida[] = "Meses semeados/conferidos: {$semeia}";

foreach ($pdo->query("SELECT mes, ultimo FROM numero_pedido_seq ORDER BY mes") as $linha) {
    $saida[] = "  {$linha['mes']}: último = {$linha['ultimo']}";
}

header('Content-Type: text/plain; charset=utf-8');
echo "=== migrate_numero_pedido_seq.php ===\n\n";
foreach ($saida as $linha) echo "$linha\n";
echo "\nMigração concluída. Mantenha este arquivo em migrations/ como histórico.\n";
