<?php
/**
 * Rastro das alterações de produto feitas no admin (Patch P) — logs/admin-produtos.log.
 * Formato: data/hora | admin_id=N | produto_id=N | ação | nome [| detalhe]
 * Ações: editar | inativar | reativar | excluir
 * Mesmo padrão de _logEstoque() (helpers/estoque.php): cria a pasta, FILE_APPEND | LOCK_EX, nunca lança.
 */

function logAdminProduto(int $produtoId, string $acao, string $nome, string $detalhe = ''): void
{
    $adminId = (int) ($_SESSION['admin_id'] ?? 0);
    $linha   = date('Y-m-d H:i:s') . " | admin_id={$adminId} | produto_id={$produtoId} | {$acao} | "
             . str_replace(["\r", "\n", '|'], [' ', ' ', '/'], $nome)
             . ($detalhe !== '' ? " | {$detalhe}" : '');

    $dir = __DIR__ . '/../../logs';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    @file_put_contents($dir . '/admin-produtos.log', $linha . PHP_EOL, FILE_APPEND | LOCK_EX);
}
