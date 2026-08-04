<?php
/**
 * Migração idempotente: adiciona pedidos.checkout_hash (idempotência do checkout
 * do carrinho, B2b) + índice único parcial. Executar uma vez via CLI:
 *   php migrations/migrate_checkout_hash.php
 */

require_once __DIR__ . '/../backend/config/database.php';

$pdo = getDB();

$info = $pdo->query("PRAGMA table_info(pedidos)")->fetchAll(PDO::FETCH_ASSOC);
$existentes = array_column($info, 'name');

if (in_array('checkout_hash', $existentes)) {
    echo "Coluna checkout_hash já existe — nada a fazer.\n";
} else {
    $pdo->exec("ALTER TABLE pedidos ADD COLUMN checkout_hash TEXT DEFAULT NULL");
    echo "Coluna checkout_hash adicionada.\n";
}

// Índice único parcial — só se aplica a linhas com checkout_hash preenchido (item-único
// nunca preenche essa coluna, então não colide com o fluxo existente).
$pdo->exec("
    CREATE UNIQUE INDEX IF NOT EXISTS idx_pedidos_checkout_hash
    ON pedidos(checkout_hash)
    WHERE checkout_hash IS NOT NULL
");
echo "Índice único idx_pedidos_checkout_hash garantido.\n";

echo "Migração concluída.\n";
