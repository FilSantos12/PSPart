<?php
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/../helpers/disponibilidade.php';

$pdo     = getDB();

// Filtro por estado (Patch P) — padrão "todos", para um produto inativado nunca sumir da vista
$FILTROS_ESTADO = ['todos' => 'Todos', 'ativos' => 'Ativos', 'inativos' => 'Inativos'];
$filtro = isset($FILTROS_ESTADO[$_GET['estado'] ?? '']) ? $_GET['estado'] : 'todos';

$stmt = $pdo->prepare("
    SELECT p.*,
           COALESCE(
               (SELECT caminho FROM produto_imagens WHERE produto_id = p.id AND principal = 1 LIMIT 1),
               p.imagem
           ) AS img_thumb,
           (SELECT COUNT(DISTINCT pedido_id) FROM itens_pedido WHERE produto_id = p.id) AS pedidos_vinculados
    FROM produtos p
    WHERE (:filtro = 'todos' OR p.ativo = CASE :filtro WHEN 'ativos' THEN 1 ELSE 0 END)
    ORDER BY p.id ASC
");
$stmt->execute([':filtro' => $filtro]);
$produtos = $stmt->fetchAll();

$contagem = $pdo->query("SELECT COUNT(*) AS todos, COALESCE(SUM(ativo = 1), 0) AS ativos, COALESCE(SUM(ativo = 0), 0) AS inativos FROM produtos")
    ->fetch(PDO::FETCH_ASSOC);

layout_head('Produtos', '<link rel="stylesheet" href="../../assets/css/disponibilidade.css">');
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div class="d-flex flex-wrap align-items-center gap-2">
        <div class="btn-group btn-group-sm" role="group" aria-label="Filtrar por estado">
            <?php foreach ($FILTROS_ESTADO as $chave => $rotulo): ?>
                <a href="produtos.php<?= $chave === 'todos' ? '' : '?estado=' . $chave ?>"
                   class="btn btn-outline-secondary<?= $filtro === $chave ? ' active' : '' ?>"
                   <?= $filtro === $chave ? 'aria-current="page"' : '' ?>>
                    <?= $rotulo ?> (<?= (int) $contagem[$chave] ?>)
                </a>
            <?php endforeach; ?>
        </div>
        <span class="text-muted small"><?= count($produtos) ?> produto(s) exibido(s)</span>
    </div>
    <a href="produto-novo.php" class="btn btn-primary btn-sm">
        <i class="fas fa-plus me-1"></i> Novo Produto
    </a>
</div>

<?php if (isset($_GET['msg'])): ?>
    <?php $alertaErro = ($_GET['tipo'] ?? '') === 'erro'; ?>
    <div class="alert alert-<?= $alertaErro ? 'danger' : 'success' ?> alert-dismissible fade show py-2" role="<?= $alertaErro ? 'alert' : 'status' ?>">
        <?php if ($alertaErro): ?><i class="fas fa-circle-exclamation me-1"></i><?php endif; ?>
        <?= htmlspecialchars($_GET['msg']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>Produto</th>
                    <th>Código</th>
                    <th>Categoria</th>
                    <th>Preço</th>
                    <th>Disponibilidade</th>
                    <th>Status</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($produtos as $p): ?>
                <tr>
                    <td class="text-muted small"><?= $p['id'] ?></td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <?php if ($p['img_thumb']): ?>
                                <?php
                                    $full = __DIR__ . '/../../' . $p['img_thumb'];
                                    $bust = file_exists($full) ? '?v=' . filemtime($full) : '';
                                ?>
                                <img src="/<?= htmlspecialchars($p['img_thumb']) . $bust ?>" width="40" height="40"
                                     style="object-fit:cover; border-radius:6px;">
                            <?php endif; ?>
                            <div>
                                <div class="fw-semibold"><?= htmlspecialchars($p['nome']) ?></div>
                                <div class="text-muted small text-truncate" style="max-width:200px">
                                    <?= htmlspecialchars($p['descricao'] ?? '') ?>
                                </div>
                            </div>
                        </div>
                    </td>
                    <td class="font-monospace small text-muted"><?= $p['codigo_interno'] ? htmlspecialchars($p['codigo_interno']) : '—' ?></td>
                    <td><span class="badge bg-secondary"><?= htmlspecialchars($p['categoria']) ?></span></td>
                    <td class="text-nowrap">R$ <?= number_format($p['preco'], 2, ',', '.') ?></td>
                    <td>
                        <?php $disp = disponibilidade_produto($p); ?>
                        <span class="stock-badge <?= htmlspecialchars($disp['badge_class']) ?> mb-0"><span class="stock-dot"></span><?= htmlspecialchars($disp['label_admin']) ?></span>
                        <div class="text-muted small">Estoque: <?= (int) $p['estoque'] ?></div>
                        <?php if ($disp['texto_consulta']): ?>
                            <div class="small text-primary" title="Limiar de consulta comercial (informativo)">
                                <i class="fas fa-handshake me-1"></i><?= htmlspecialchars($disp['texto_consulta']) ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="stock-badge stock-badge--<?= $p['ativo'] ? 'ok' : 'req' ?> mb-0"><span class="stock-dot"></span><?= $p['ativo'] ? 'Ativo' : 'Inativo' ?></span>
                    </td>
                    <td class="text-nowrap">
                        <?php
                            $pedidosVinc = (int) $p['pedidos_vinculados'];
                            $dataProduto = 'data-id="' . (int) $p['id'] . '" data-nome="' . htmlspecialchars($p['nome']) . '"'
                                         . ' data-codigo="' . htmlspecialchars($p['codigo_interno'] ?? '') . '"';
                        ?>
                        <a href="produto-editar.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary" title="Editar" aria-label="Editar <?= htmlspecialchars($p['nome']) ?>">
                            <i class="fas fa-edit"></i>
                        </a>
                        <?php if ($p['ativo']): ?>
                            <button type="button" class="btn btn-sm btn-outline-warning btn-estado" data-acao="inativar" <?= $dataProduto ?>
                                    title="Inativar" aria-label="Inativar <?= htmlspecialchars($p['nome']) ?>">
                                <i class="fas fa-eye-slash me-md-1" aria-hidden="true"></i><span class="d-none d-md-inline">Inativar</span>
                            </button>
                        <?php else: ?>
                            <button type="button" class="btn btn-sm btn-outline-success btn-estado" data-acao="reativar" <?= $dataProduto ?>
                                    title="Reativar" aria-label="Reativar <?= htmlspecialchars($p['nome']) ?>">
                                <i class="fas fa-eye me-md-1" aria-hidden="true"></i><span class="d-none d-md-inline">Reativar</span>
                            </button>
                        <?php endif; ?>
                        <button type="button" class="btn btn-sm btn-outline-danger btn-estado" data-acao="excluir" <?= $dataProduto ?>
                                title="Excluir definitivamente" aria-label="Excluir definitivamente <?= htmlspecialchars($p['nome']) ?>"
                                <?= $pedidosVinc > 0 ? 'disabled aria-describedby="motivo-excluir-' . (int) $p['id'] . '"' : '' ?>>
                            <i class="fas fa-trash me-md-1" aria-hidden="true"></i><span class="d-none d-md-inline">Excluir definitivamente</span>
                        </button>
                        <?php if ($pedidosVinc > 0): ?>
                            <div class="small text-muted text-wrap mt-1" id="motivo-excluir-<?= (int) $p['id'] ?>">
                                <?= $pedidosVinc ?> <?= $pedidosVinc === 1 ? 'pedido vinculado' : 'pedidos vinculados' ?> — só pode ser inativado
                            </div>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($produtos)): ?>
                <tr><td colspan="8" class="text-center text-muted py-4"><?= $filtro === 'todos' ? 'Nenhum produto cadastrado.' : 'Nenhum produto ' . ($filtro === 'ativos' ? 'ativo' : 'inativo') . '.' ?></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal de confirmação (Patch P) — um só, com título, texto e botão trocados por ação.
     O texto descreve exatamente o que o servidor vai fazer; a ação vai explícita no POST. -->
<div class="modal fade" id="modalExcluir" tabindex="-1" aria-labelledby="modalEstadoTitulo">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title" id="modalEstadoTitulo"></h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body" id="modalEstadoTexto"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <form method="POST" action="produto-estado.php">
                    <input type="hidden" name="id" id="idProduto">
                    <input type="hidden" name="acao" id="acaoProduto">
                    <input type="hidden" name="estado" value="<?= htmlspecialchars($filtro) ?>">
                    <button type="submit" class="btn btn-sm" id="modalEstadoBotao"></button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
const CONFIRMACOES_ESTADO = {
    inativar: {
        titulo: 'Inativar produto',
        botao:  'Inativar', classe: 'btn-warning',
        texto:  (nome) => `Inativar <strong>${nome}</strong>? Ele deixa de aparecer na loja e pode ser reativado depois.`,
    },
    reativar: {
        titulo: 'Reativar produto',
        botao:  'Reativar', classe: 'btn-success',
        texto:  (nome) => `Reativar <strong>${nome}</strong>? Ele volta a aparecer na loja.`,
    },
    excluir: {
        titulo: 'Excluir definitivamente',
        botao:  'Excluir definitivamente', classe: 'btn-danger',
        texto:  (nome, codigo) => `Excluir definitivamente <strong>${nome}</strong> (${codigo ? `código <code>${codigo}</code>` : 'sem código interno'})?`
              + ' O cadastro e as imagens associadas são apagados do banco.<br><strong class="text-danger">Esta ação é irreversível.</strong>',
    },
};

function escaparHtml(valor) {
    const div = document.createElement('div');
    div.textContent = valor;
    return div.innerHTML;
}

document.querySelectorAll('.btn-estado').forEach(btn => {
    btn.addEventListener('click', () => {
        const conf = CONFIRMACOES_ESTADO[btn.dataset.acao];
        if (!conf || btn.disabled) return;
        document.getElementById('idProduto').value   = btn.dataset.id;
        document.getElementById('acaoProduto').value = btn.dataset.acao;
        document.getElementById('modalEstadoTitulo').textContent = conf.titulo;
        document.getElementById('modalEstadoTexto').innerHTML    = conf.texto(escaparHtml(btn.dataset.nome), escaparHtml(btn.dataset.codigo));
        const botao = document.getElementById('modalEstadoBotao');
        botao.textContent = conf.botao;
        botao.className   = 'btn btn-sm ' + conf.classe;
        new bootstrap.Modal(document.getElementById('modalExcluir')).show();
    });
});
</script>

<?php layout_foot(); ?>
