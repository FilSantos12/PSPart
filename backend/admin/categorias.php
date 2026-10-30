<?php
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/_layout.php';

$pdo     = getDB();
$erros   = [];
$sucesso = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── Criar categoria ────────────────────────────────────────────────────────
    if ($action === 'criar') {
        $nome = trim($_POST['nome'] ?? '');
        $slug = strtolower(trim($_POST['slug'] ?? ''));
        $slug = preg_replace('/[^a-z0-9_-]/', '', $slug);

        if (!$nome) $erros[] = 'Nome é obrigatório.';
        if (!$slug) $erros[] = 'Slug é obrigatório (apenas letras minúsculas, números e hífen).';

        if (empty($erros)) {
            $dup = $pdo->prepare("SELECT id FROM categorias WHERE slug = ?");
            $dup->execute([$slug]);
            if ($dup->fetch()) {
                $erros[] = "O slug <b>{$slug}</b> já está em uso.";
            } else {
                $maxOrdem = (int) $pdo->query("SELECT COALESCE(MAX(ordem),0)+1 FROM categorias")->fetchColumn();
                $pdo->prepare("INSERT INTO categorias (slug, nome, ordem) VALUES (?,?,?)")
                    ->execute([$slug, $nome, $maxOrdem]);
                $sucesso = "Categoria <b>{$nome}</b> criada com sucesso.";
            }
        }
    }

    // ── Excluir categoria ──────────────────────────────────────────────────────
    if ($action === 'excluir') {
        $id = (int) ($_POST['categoria_id'] ?? 0);

        $cat = $pdo->prepare("SELECT slug, nome FROM categorias WHERE id = ?");
        $cat->execute([$id]);
        $catRow = $cat->fetch();

        if ($catRow) {
            $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM produtos WHERE categoria = ?");
            $stmtCount->execute([$catRow['slug']]);
            $nProd = (int) $stmtCount->fetchColumn();

            if ($nProd > 0) {
                $erros[] = "Não é possível excluir <b>{$catRow['nome']}</b>: {$nProd} produto(s) usam esta categoria. Reatribua os produtos antes de excluí-la.";
            } else {
                $pdo->prepare("DELETE FROM categorias WHERE id = ?")->execute([$id]);
                $sucesso = "Categoria <b>{$catRow['nome']}</b> removida.";
            }
        }
    }
}

// Lista categorias com contagem de produtos
$cats = $pdo->query("
    SELECT c.id, c.slug, c.nome, c.ordem,
           COUNT(p.id) AS total_produtos
    FROM categorias c
    LEFT JOIN produtos p ON p.categoria = c.slug
    GROUP BY c.id
    ORDER BY c.ordem ASC, c.nome ASC
")->fetchAll();

layout_head('Categorias');
?>

<?php if ($sucesso): ?>
    <div class="alert alert-success alert-dismissible fade show py-2">
        <?= $sucesso ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($erros): ?>
    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($erros as $e) echo "<li>{$e}</li>"; ?></ul></div>
<?php endif; ?>

<div class="row g-4" style="max-width:900px">

    <!-- Lista de categorias -->
    <div class="col-md-7">
        <div class="card shadow-sm">
            <div class="card-header fw-semibold">Categorias cadastradas</div>
            <div class="card-body p-0">
                <?php if (empty($cats)): ?>
                    <p class="text-muted p-3 mb-0">Nenhuma categoria cadastrada.</p>
                <?php else: ?>
                    <div class="table-responsive">
                    <table class="table table-hover mb-0 adm-table">
                        <thead class="table-light">
                            <tr>
                                <th>Nome</th>
                                <th>Slug</th>
                                <th class="text-center">Produtos</th>
                                <th><span class="visually-hidden">Ações</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cats as $cat): ?>
                                <tr>
                                    <td class="align-middle fw-medium"><?= htmlspecialchars($cat['nome']) ?></td>
                                    <td class="align-middle"><code><?= htmlspecialchars($cat['slug']) ?></code></td>
                                    <td class="align-middle text-center">
                                        <?php if ($cat['total_produtos'] > 0): ?>
                                            <span class="badge bg-primary"><?= $cat['total_produtos'] ?></span>
                                        <?php else: ?>
                                            <span class="text-muted small">0</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="align-middle">
                                        <div class="adm-acoes">
                                        <?php if ($cat['total_produtos'] == 0): ?>
                                            <form method="POST" class="d-inline"
                                                  onsubmit="return confirm('Remover a categoria \'<?= htmlspecialchars($cat['nome']) ?>\'?')">
                                                <input type="hidden" name="action" value="excluir">
                                                <input type="hidden" name="categoria_id" value="<?= $cat['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Excluir"
                                                        aria-label="Excluir a categoria <?= htmlspecialchars($cat['nome']) ?>">
                                                    <i class="fas fa-trash-alt" aria-hidden="true"></i>
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-muted small" title="Categoria em uso — reatribua os produtos para excluir">
                                                <i class="fas fa-lock fa-sm"></i>
                                            </span>
                                        <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Formulário de nova categoria -->
    <div class="col-md-5">
        <div class="card shadow-sm">
            <div class="card-header fw-semibold">Nova Categoria</div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="criar">

                    <div class="mb-3">
                        <label class="form-label">Nome <span class="text-danger">*</span></label>
                        <input type="text" name="nome" class="form-control" id="input-nome"
                               placeholder="Ex: Eletrônicos" maxlength="60" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">
                            Slug <span class="text-danger">*</span>
                            <span class="text-muted small ms-1">— usado na URL e no banco</span>
                        </label>
                        <input type="text" name="slug" class="form-control font-monospace" id="input-slug"
                               placeholder="Ex: eletronicos" maxlength="40" required>
                        <div class="form-text">Apenas letras minúsculas, números e hífen.</div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-plus me-1"></i> Criar Categoria
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-gera o slug a partir do nome
document.getElementById('input-nome').addEventListener('input', function () {
    const slug = this.value
        .toLowerCase()
        .normalize('NFD').replace(/[̀-ͯ]/g, '')
        .replace(/[^a-z0-9\s-]/g, '')
        .trim()
        .replace(/\s+/g, '-');
    document.getElementById('input-slug').value = slug;
});
</script>

<?php layout_foot(); ?>
