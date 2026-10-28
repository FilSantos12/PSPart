<?php
/**
 * Migração idempotente (Patch P): adiciona produtos.atualizado_em — data/hora da última alteração
 * feita pelo admin (editar, inativar, reativar), gravada em PHP com date('Y-m-d H:i:s')
 * America/Sao_Paulo (nunca CURRENT_TIMESTAMP). Baixa/devolução de estoque por pedido não mexe nela.
 * NULL = nunca alterado pelo admin desde a migração; todo produto existente fica NULL.
 * Executar via browser (ou `php migrations/migrate_produto_atualizado_em.php`); pode rodar 2x.
 *
 * URL: http://localhost:8000/migrations/migrate_produto_atualizado_em.php
 */

require_once __DIR__ . '/../backend/config/database.php';

$pdo = getDB();

$saida = [];

$info = $pdo->query("PRAGMA table_info(produtos)")->fetchAll(PDO::FETCH_ASSOC);
$existentes = array_column($info, 'name');

if (!in_array('atualizado_em', $existentes, true)) {
    $pdo->exec("ALTER TABLE produtos ADD COLUMN atualizado_em TEXT DEFAULT NULL");
    $saida[] = "Coluna atualizado_em ADICIONADA.";
} else {
    $saida[] = "Coluna atualizado_em já existe (ignorada).";
}

$total = (int) $pdo->query("SELECT COUNT(*) FROM produtos")->fetchColumn();
$nulos = (int) $pdo->query("SELECT COUNT(*) FROM produtos WHERE atualizado_em IS NULL")->fetchColumn();
$saida[] = "Produtos com atualizado_em NULL: {$nulos} de {$total}";

header('Content-Type: text/plain; charset=utf-8');
echo "=== migrate_produto_atualizado_em.php ===\n\n";
foreach ($saida as $linha) echo "$linha\n";
echo "\nMigração concluída. Mantenha este arquivo em migrations/ como histórico.\n";
