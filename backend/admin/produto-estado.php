<?php
/**
 * Estado do produto no admin (Patch P): inativar | reativar | excluir — sempre ação explícita.
 * Excluir só sem pedidos vinculados; com pedidos é RECUSADO (nunca inativa em silêncio no lugar).
 * A FK itens_pedido.produto_id ON DELETE RESTRICT é a garantia no banco; a contagem aqui dá a
 * mensagem. Toda ação executada grava logs/admin-produtos.log.
 */
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/admin-produtos-log.php';

const ACOES_ESTADO_PRODUTO = ['inativar', 'reativar', 'excluir'];
const FILTROS_ESTADO       = ['todos', 'ativos', 'inativos'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: produtos.php');
    exit;
}

$id     = (int) ($_POST['id'] ?? 0);
$acao   = (string) ($_POST['acao'] ?? '');
$filtro = in_array($_POST['estado'] ?? '', FILTROS_ESTADO, true) ? $_POST['estado'] : 'todos';

// Volta para a listagem (no filtro em que o admin estava) com a mensagem e o tipo do alerta
function voltar(string $msg, string $tipo, string $filtro): void
{
    $q = ['msg' => $msg, 'tipo' => $tipo];
    if ($filtro !== 'todos') $q['estado'] = $filtro;
    header('Location: produtos.php?' . http_build_query($q));
    exit;
}

if (!in_array($acao, ACOES_ESTADO_PRODUTO, true)) {
    voltar('Ação inválida — nada foi alterado.', 'erro', $filtro);
}

$pdo  = getDB();
$stmt = $pdo->prepare("SELECT id, nome, ativo FROM produtos WHERE id = :id");
$stmt->execute([':id' => $id]);
$produto = $stmt->fetch(PDO::FETCH_ASSOC);
$stmt->closeCursor();

if (!$produto) {
    voltar('Produto não encontrado — nada foi alterado.', 'erro', $filtro);
}
$nome = $produto['nome'];

if ($acao === 'excluir') {
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT pedido_id) FROM itens_pedido WHERE produto_id = :id");
    $stmt->execute([':id' => $id]);
    $pedidos = (int) $stmt->fetchColumn();
    $stmt->closeCursor();

    if ($pedidos > 0) {
        voltar("Não excluído: \"{$nome}\" tem {$pedidos} " . ($pedidos === 1 ? 'pedido vinculado' : 'pedidos vinculados')
             . ' — só pode ser inativado. Nada foi alterado.', 'erro', $filtro);
    }

    try {
        $pdo->prepare("DELETE FROM produtos WHERE id = :id")->execute([':id' => $id]);
    } catch (PDOException $e) {
        // Pedido criado entre a contagem e o DELETE: a FK RESTRICT barra a exclusão
        voltar("Não excluído: \"{$nome}\" passou a ter pedido vinculado — só pode ser inativado. Nada foi alterado.", 'erro', $filtro);
    }
    logAdminProduto($id, 'excluir', $nome);
    voltar("Produto \"{$nome}\" excluído definitivamente.", 'sucesso', $filtro);
}

// inativar | reativar
$novoAtivo = $acao === 'reativar' ? 1 : 0;
if ((int) $produto['ativo'] === $novoAtivo) {
    voltar("\"{$nome}\" já está " . ($novoAtivo ? 'ativo' : 'inativo') . ' — nada foi alterado.', 'erro', $filtro);
}

$pdo->prepare("UPDATE produtos SET ativo = :ativo, atualizado_em = :agora WHERE id = :id")
    ->execute([':ativo' => $novoAtivo, ':agora' => date('Y-m-d H:i:s'), ':id' => $id]);
logAdminProduto($id, $acao, $nome);

voltar($novoAtivo
    ? "Produto \"{$nome}\" reativado — voltou a aparecer na loja."
    : "Produto \"{$nome}\" inativado — não aparece mais na loja e pode ser reativado depois.", 'sucesso', $filtro);
