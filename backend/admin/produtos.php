<?php
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/../helpers/disponibilidade.php';

$pdo     = getDB();
$produtos = $pdo->query("
    SELECT p.*,
           COALESCE(
               (SELECT caminho FROM produto_imagens WHERE produto_id = p.id AND principal = 1 LIMIT 1),
               p.imagem
           ) AS img_thumb
    FROM produtos p
    ORDER BY p.id ASC
")->fetchAll();

layout_head('Produtos', '<link rel="stylesheet" href="../../assets/css/disponibilidade.css">');
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <span class="text-muted small"><?= count($produtos) ?> produto(s) cadastrado(s)</span>
    <a href="produto-novo.php" class="btn btn-primary btn-sm">
        <i class="fas fa-plus me-1"></i> Novo Produto
    </a>
</div>

<?php if (isset($_GET['msg'])): ?>
    <div class="alert alert-success alert-dismissible fade show py-2">
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
                    <td>R$ <?= number_format($p['preco'], 2, ',', '.') ?></td>
                    <td>
                        <?php $disp = disponibilidade_produto($p); ?>
                        <span class="stock-badge <?= htmlspecialchars($disp['badge_class']) ?> mb-0"><span class="stock-dot"></span><?= htmlspecialchars($disp['label']) ?></span>
                        <div class="text-muted small">Estoque: <?= (int) $p['estoque'] ?></div>
                    </td>
                    <td>
                        <span class="badge bg-<?= $p['ativo'] ? 'success' : 'secondary' ?>">
                            <?= $p['ativo'] ? 'Ativo' : 'Inativo' ?>
                        </span>
                    </td>
                    <td>
                        <a href="produto-editar.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary me-1">
                            <i class="fas fa-edit"></i>
                        </a>
                        <button type="button" class="btn btn-sm btn-outline-danger"
                                onclick="confirmarExclusao(<?= $p['id'] ?>, '<?= htmlspecialchars(addslashes($p['nome'])) ?>')">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($produtos)): ?>
                <tr><td colspan="8" class="text-center text-muted py-4">Nenhum produto cadastrado.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal de confirmação de exclusão -->
<div class="modal fade" id="modalExcluir" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">Confirmar exclusão</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                Excluir <strong id="nomeProduto"></strong>?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <form method="POST" action="produto-excluir.php">
                    <input type="hidden" name="id" id="idProduto">
                    <button type="submit" class="btn btn-danger btn-sm">Excluir</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function confirmarExclusao(id, nome) {
    document.getElementById('idProduto').value  = id;
    document.getElementById('nomeProduto').textContent = nome;
    new bootstrap.Modal(document.getElementById('modalExcluir')).show();
}
</script>

<?php layout_foot(); ?>
