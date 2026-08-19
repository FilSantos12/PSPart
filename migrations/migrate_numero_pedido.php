<?php
/**
 * Migração idempotente: adiciona pedidos.numero_pedido (AAAAMM + sequencial de 3 dígitos,
 * reinicia por mês) e faz backfill dos pedidos existentes com base em criado_em.
 * Executar uma vez via browser e apagar o arquivo após confirmar o sucesso.
 *
 * URL: http://localhost:8000/migrations/migrate_numero_pedido.php
 */

require_once __DIR__ . '/../backend/config/database.php';

$pdo = getDB();

$saida = [];

$info = $pdo->query("PRAGMA table_info(pedidos)")->fetchAll(PDO::FETCH_ASSOC);
$existentes = array_column($info, 'name');

if (!in_array('numero_pedido', $existentes, true)) {
    $pdo->exec("ALTER TABLE pedidos ADD COLUMN numero_pedido TEXT DEFAULT NULL");
    $saida[] = "Coluna numero_pedido ADICIONADA.";
} else {
    $saida[] = "Coluna numero_pedido já existe (ignorada).";
}

// Backfill: pedidos sem numero_pedido recebem AAAAMM (de criado_em) + sequencial de 3 dígitos,
// contado em ordem de id dentro de cada mês.
$pendentes = $pdo->query("
    SELECT id, criado_em FROM pedidos WHERE numero_pedido IS NULL ORDER BY id ASC
")->fetchAll(PDO::FETCH_ASSOC);

$contadores  = [];
$atualizados = 0;
$upd = $pdo->prepare("UPDATE pedidos SET numero_pedido = :num WHERE id = :id");

foreach ($pendentes as $row) {
    $prefixo = date('Ym', strtotime($row['criado_em']));
    $contadores[$prefixo] = ($contadores[$prefixo] ?? -1) + 1;
    $numero = $prefixo . str_pad((string) $contadores[$prefixo], 3, '0', STR_PAD_LEFT);
    $upd->execute([':num' => $numero, ':id' => $row['id']]);
    $atualizados++;
}
$saida[] = "Pedidos com numero_pedido preenchido nesta execução: {$atualizados}";

$idx = $pdo->query("
    SELECT name FROM sqlite_master WHERE type = 'index' AND name = 'idx_pedidos_numero_pedido'
")->fetch();

if (!$idx) {
    $pdo->exec("CREATE UNIQUE INDEX idx_pedidos_numero_pedido ON pedidos(numero_pedido) WHERE numero_pedido IS NOT NULL");
    $saida[] = "Índice único idx_pedidos_numero_pedido CRIADO.";
} else {
    $saida[] = "Índice único idx_pedidos_numero_pedido já existe (ignorado).";
}

header('Content-Type: text/plain; charset=utf-8');
echo "=== migrate_numero_pedido.php ===\n\n";
foreach ($saida as $linha) echo "$linha\n";
echo "\nMigração concluída. Apague este arquivo após verificar.\n";
