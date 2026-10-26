<?php
/**
 * Migração idempotente (Patch I): colunas de disponibilidade em produtos e
 * itens_pedido.qtd_sob_encomenda. Os defaults reproduzem exatamente o comportamento
 * anterior — todo produto existente fica 'automatico' + permite_venda_sem_estoque = 0.
 * Executar via browser (ou `php migrations/migrate_disponibilidade.php`); pode rodar 2x.
 *
 * URL: http://localhost:8000/migrations/migrate_disponibilidade.php
 */

require_once __DIR__ . '/../backend/config/database.php';

$pdo = getDB();

$saida = [];

$colunas = [
    'produtos' => [
        'modo_venda'                => "TEXT NOT NULL DEFAULT 'automatico'",
        'prazo_encomenda_dias'      => 'INTEGER DEFAULT NULL',
        'permite_venda_sem_estoque' => 'INTEGER NOT NULL DEFAULT 0',
    ],
    'itens_pedido' => [
        'qtd_sob_encomenda' => 'INTEGER NOT NULL DEFAULT 0',
    ],
];

foreach ($colunas as $tabela => $defs) {
    $info = $pdo->query("PRAGMA table_info($tabela)")->fetchAll(PDO::FETCH_ASSOC);
    $existentes = array_column($info, 'name');

    foreach ($defs as $coluna => $tipo) {
        if (!in_array($coluna, $existentes, true)) {
            $pdo->exec("ALTER TABLE $tabela ADD COLUMN $coluna $tipo");
            $saida[] = "Coluna $tabela.$coluna ADICIONADA.";
        } else {
            $saida[] = "Coluna $tabela.$coluna já existe (ignorada).";
        }
    }
}

// Conferência: nenhum produto pode ter saído do comportamento anterior
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM produtos
    WHERE modo_venda <> :modo OR permite_venda_sem_estoque <> :permite
");
$stmt->execute([':modo' => 'automatico', ':permite' => 0]);
$saida[] = "Produtos fora de 'automatico' + permite=0: " . (int) $stmt->fetchColumn();

header('Content-Type: text/plain; charset=utf-8');
echo "=== migrate_disponibilidade.php ===\n\n";
foreach ($saida as $linha) echo "$linha\n";
echo "\nMigração concluída. Apague este arquivo após verificar.\n";
