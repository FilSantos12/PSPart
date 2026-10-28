<?php
/**
 * Migração idempotente (Patch N): adiciona produtos.qtd_consulta — limiar COMERCIAL por produto,
 * apenas informativo ("Acima de N un.: consulte o comercial"); não limita a compra.
 * NULL = produto sem limiar; todo produto existente fica NULL (nenhum aviso até o admin configurar).
 * Executar via browser (ou `php migrations/migrate_qtd_consulta.php`); pode rodar 2x.
 *
 * URL: http://localhost:8000/migrations/migrate_qtd_consulta.php
 */

require_once __DIR__ . '/../backend/config/database.php';

$pdo = getDB();

$saida = [];

$info = $pdo->query("PRAGMA table_info(produtos)")->fetchAll(PDO::FETCH_ASSOC);
$existentes = array_column($info, 'name');

if (!in_array('qtd_consulta', $existentes, true)) {
    $pdo->exec("ALTER TABLE produtos ADD COLUMN qtd_consulta INTEGER DEFAULT NULL");
    $saida[] = "Coluna qtd_consulta ADICIONADA.";
} else {
    $saida[] = "Coluna qtd_consulta já existe (ignorada).";
}

$total = (int) $pdo->query("SELECT COUNT(*) FROM produtos")->fetchColumn();
$nulos = (int) $pdo->query("SELECT COUNT(*) FROM produtos WHERE qtd_consulta IS NULL")->fetchColumn();
$saida[] = "Produtos com qtd_consulta NULL: {$nulos} de {$total}";

header('Content-Type: text/plain; charset=utf-8');
echo "=== migrate_qtd_consulta.php ===\n\n";
foreach ($saida as $linha) echo "$linha\n";
echo "\nMigração concluída. Mantenha este arquivo em migrations/ como histórico.\n";
