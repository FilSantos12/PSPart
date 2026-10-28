<?php
/**
 * Migração idempotente (Patch K): adiciona pedidos.estoque_devolvido — marca que o estoque
 * do pedido já foi devolvido por devolverEstoquePedido() (proteção contra webhook duplicado).
 * Pedidos existentes ficam com 0; nenhum é devolvido por esta migração.
 * Executar via browser (ou `php migrations/migrate_estoque_devolvido.php`); pode rodar 2x.
 *
 * URL: http://localhost:8000/migrations/migrate_estoque_devolvido.php
 */

require_once __DIR__ . '/../backend/config/database.php';

$pdo = getDB();

$saida = [];

$info = $pdo->query("PRAGMA table_info(pedidos)")->fetchAll(PDO::FETCH_ASSOC);
$existentes = array_column($info, 'name');

if (!in_array('estoque_devolvido', $existentes, true)) {
    $pdo->exec("ALTER TABLE pedidos ADD COLUMN estoque_devolvido INTEGER NOT NULL DEFAULT 0");
    $saida[] = "Coluna estoque_devolvido ADICIONADA.";
} else {
    $saida[] = "Coluna estoque_devolvido já existe (ignorada).";
}

$saida[] = "Pedidos com estoque_devolvido = 1: " . (int) $pdo->query("SELECT COUNT(*) FROM pedidos WHERE estoque_devolvido = 1")->fetchColumn();

header('Content-Type: text/plain; charset=utf-8');
echo "=== migrate_estoque_devolvido.php ===\n\n";
foreach ($saida as $linha) echo "$linha\n";
echo "\nMigração concluída. Mantenha este arquivo em migrations/ como histórico.\n";
