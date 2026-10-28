<?php
/**
 * Migração idempotente (Patch J): adiciona produtos.preco_export_usd — SEGUNDA LISTA DE PREÇO
 * em USD, na condição INCOTERM_PADRAO (helpers/disponibilidade.php). Nunca conversão de câmbio.
 * NULL = sem preço de exportação publicado ("Sob consulta"); todo produto existente fica NULL.
 * Executar via browser (ou `php migrations/migrate_preco_export.php`); pode rodar 2x.
 *
 * URL: http://localhost:8000/migrations/migrate_preco_export.php
 */

require_once __DIR__ . '/../backend/config/database.php';

$pdo = getDB();

$saida = [];

$info = $pdo->query("PRAGMA table_info(produtos)")->fetchAll(PDO::FETCH_ASSOC);
$existentes = array_column($info, 'name');

if (!in_array('preco_export_usd', $existentes, true)) {
    $pdo->exec("ALTER TABLE produtos ADD COLUMN preco_export_usd REAL DEFAULT NULL");
    $saida[] = "Coluna preco_export_usd ADICIONADA.";
} else {
    $saida[] = "Coluna preco_export_usd já existe (ignorada).";
}

$total = (int) $pdo->query("SELECT COUNT(*) FROM produtos")->fetchColumn();
$nulos = (int) $pdo->query("SELECT COUNT(*) FROM produtos WHERE preco_export_usd IS NULL")->fetchColumn();
$saida[] = "Produtos com preco_export_usd NULL: {$nulos} de {$total}";

header('Content-Type: text/plain; charset=utf-8');
echo "=== migrate_preco_export.php ===\n\n";
foreach ($saida as $linha) echo "$linha\n";
echo "\nMigração concluída. Mantenha este arquivo em migrations/ como histórico.\n";
