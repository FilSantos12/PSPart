<?php
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/_confirmar.php';
require_once __DIR__ . '/../helpers/disponibilidade.php';

$erros        = [];
$produtoCriado = null;
$dados = ['nome' => '', 'descricao' => '', 'preco' => '', 'categoria' => '', 'estoque' => '', 'ativo' => 1, 'codigo_interno' => '', 'especificacao_tecnica' => '', 'peso' => '0.5', 'largura' => '15', 'altura' => '10', 'comprimento' => '20', 'modo_venda' => 'automatico', 'permite_venda_sem_estoque' => 0, 'prazo_encomenda_dias' => null, 'qtd_consulta' => null, 'preco_export_usd' => null];

$pdo = getDB();
$categoriasDb = $pdo->query("SELECT slug, nome FROM categorias ORDER BY ordem ASC, nome ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dados['nome']                  = trim($_POST['nome'] ?? '');
    $dados['descricao']             = trim($_POST['descricao'] ?? '');
    $dados['preco']                 = $_POST['preco'] ?? '';
    $dados['categoria']             = trim($_POST['categoria'] ?? '');
    $dados['estoque']               = $_POST['estoque'] ?? '';
    $dados['ativo']                 = isset($_POST['ativo']) ? 1 : 0;
    $dados['codigo_interno']        = strtoupper(trim($_POST['codigo_interno'] ?? ''));
    $dados['especificacao_tecnica'] = trim($_POST['especificacao_tecnica'] ?? '');
    $dados['peso']                  = $_POST['peso']        ?? '';
    $dados['largura']               = $_POST['largura']     ?? '';
    $dados['altura']                = $_POST['altura']      ?? '';
    $dados['comprimento']           = $_POST['comprimento'] ?? '';

    if (!$dados['nome'])      $erros[] = 'Nome é obrigatório.';
    if (!$dados['categoria']) $erros[] = 'Categoria é obrigatória.';
    if (!is_numeric($dados['preco']) || $dados['preco'] < 0)         $erros[] = 'Preço inválido.';
    if (!is_numeric($dados['estoque']) || $dados['estoque'] < 0)     $erros[] = 'Estoque inválido.';
    if (!is_numeric($dados['peso']) || $dados['peso'] <= 0)          $erros[] = 'Peso inválido (deve ser maior que zero).';
    if (!is_numeric($dados['largura']) || $dados['largura'] <= 0)    $erros[] = 'Largura inválida.';
    if (!is_numeric($dados['altura']) || $dados['altura'] <= 0)      $erros[] = 'Altura inválida.';
    if (!is_numeric($dados['comprimento']) || $dados['comprimento'] <= 0) $erros[] = 'Comprimento inválido.';

    // Disponibilidade — modo_venda validado contra MODOS_VENDA no servidor
    $dados = disponibilidade_dados_post($_POST, $erros) + $dados;
    // Preço de exportação (Patch J) — vazio grava NULL ("Sob consulta"); com erro, o formulário
    // reexibe o que foi digitado
    $dados['preco_export_usd'] = preco_export_dados_post($_POST, $erros) ?? (trim((string) ($_POST['preco_export_usd'] ?? '')) ?: null);

    if ($dados['codigo_interno'] !== '') {
        if (!preg_match('/^[A-Z]{2}\.\d{2}\.\d{5}$/', $dados['codigo_interno'])) {
            $erros[] = 'Código interno inválido. Formato esperado: SE.02.00002';
        } else {
            $dup = $pdo->prepare("SELECT id FROM produtos WHERE codigo_interno = :cod");
            $dup->execute([':cod' => $dados['codigo_interno']]);
            if ($dup->fetch()) $erros[] = 'Código interno já cadastrado para outro produto.';
        }
    }

    $imagem = null;
    if (!empty($_FILES['imagem']['name'])) {
        $ext        = strtolower(pathinfo($_FILES['imagem']['name'], PATHINFO_EXTENSION));
        $permitidos = ['jpg', 'jpeg', 'png', 'webp'];
        if (!in_array($ext, $permitidos)) {
            $erros[] = 'Formato de imagem inválido. Use JPG, PNG ou WebP.';
        } elseif ($_FILES['imagem']['size'] > 5 * 1024 * 1024) {
            $erros[] = 'Imagem muito grande. Máximo: 5 MB.';
        } else {
            $destino = __DIR__ . '/../../img/' . uniqid('prod_') . '.' . $ext;
            move_uploaded_file($_FILES['imagem']['tmp_name'], $destino);
            $imagem = 'img/' . basename($destino);
        }
    }

    $datasheet = null;
    if (!empty($_FILES['datasheet']['name'])) {
        if ($_FILES['datasheet']['error'] !== UPLOAD_ERR_OK) {
            $msgs = [1=>'Arquivo excede o limite do servidor (upload_max_filesize).',2=>'Arquivo excede o limite do formulário.',3=>'Upload interrompido, tente novamente.',6=>'Diretório temporário ausente no servidor.',7=>'Falha ao gravar arquivo temporário.'];
            $erros[] = $msgs[$_FILES['datasheet']['error']] ?? 'Erro no upload do data sheet (código '.$_FILES['datasheet']['error'].').';
        } elseif ($_FILES['datasheet']['size'] > 10 * 1024 * 1024) {
            $erros[] = 'Data sheet muito grande. Máximo: 10 MB.';
        } else {
            $extDs = strtolower(pathinfo($_FILES['datasheet']['name'], PATHINFO_EXTENSION));
            if ($extDs !== 'pdf') {
                $erros[] = 'Data sheet deve ser um arquivo PDF.';
            } else {
                $handle = fopen($_FILES['datasheet']['tmp_name'], 'rb');
                $header = fread($handle, 4);
                fclose($handle);
                if ($header !== '%PDF') {
                    $erros[] = 'Arquivo não é um PDF válido.';
                } else {
                    $docsDir = __DIR__ . '/../../docs/';
                    if (!is_dir($docsDir)) mkdir($docsDir, 0755, true);
                    $nomeDs  = 'ds_' . uniqid() . '.pdf';
                    move_uploaded_file($_FILES['datasheet']['tmp_name'], $docsDir . $nomeDs);
                    $datasheet = 'docs/' . $nomeDs;
                }
            }
        }
    }

    if (empty($erros)) {
        $pdo->prepare("
            INSERT INTO produtos (nome, descricao, preco, categoria, imagem, estoque, ativo, codigo_interno, datasheet, especificacao_tecnica, peso, largura, altura, comprimento, modo_venda, permite_venda_sem_estoque, prazo_encomenda_dias, qtd_consulta, preco_export_usd)
            VALUES (:nome, :descricao, :preco, :categoria, :imagem, :estoque, :ativo, :codigo_interno, :datasheet, :especificacao_tecnica, :peso, :largura, :altura, :comprimento, :modo_venda, :permite_venda_sem_estoque, :prazo_encomenda_dias, :qtd_consulta, :preco_export_usd)
        ")->execute([
            ':nome'                  => $dados['nome'],
            ':descricao'             => $dados['descricao'],
            ':preco'                 => (float) $dados['preco'],
            ':categoria'             => $dados['categoria'],
            ':imagem'                => $imagem,
            ':estoque'               => (int) $dados['estoque'],
            ':ativo'                 => $dados['ativo'],
            ':codigo_interno'        => $dados['codigo_interno'] ?: null,
            ':datasheet'             => $datasheet,
            ':especificacao_tecnica' => $dados['especificacao_tecnica'] ?: null,
            ':peso'                  => (float) $dados['peso'],
            ':largura'               => (float) $dados['largura'],
            ':altura'                => (float) $dados['altura'],
            ':comprimento'           => (float) $dados['comprimento'],
            ':modo_venda'                => $dados['modo_venda'],
            ':permite_venda_sem_estoque' => $dados['permite_venda_sem_estoque'],
            ':prazo_encomenda_dias'      => $dados['prazo_encomenda_dias'],
            ':qtd_consulta'              => $dados['qtd_consulta'],
            ':preco_export_usd'          => $dados['preco_export_usd'],
        ]);
        $produtoCriado = (int) $pdo->lastInsertId();

        if ($imagem) {
            $pdo->prepare("INSERT INTO produto_imagens (produto_id, caminho, ordem, principal) VALUES (:pid, :caminho, 0, 1)")
                ->execute([':pid' => $produtoCriado, ':caminho' => $imagem]);
        }
    }
}

$easymde_css = $produtoCriado ? '' : '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/easymde/dist/easymde.min.css">';
layout_head('Novo Produto', $easymde_css);
?>

<div class="mb-3">
    <a href="produtos.php" class="text-decoration-none text-muted small">
        <i class="fas fa-arrow-left me-1"></i> Voltar para Produtos
    </a>
</div>

<?php if ($erros): ?>
    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($erros as $e) echo '<li>' . htmlspecialchars($e) . '</li>'; ?></ul></div>
<?php endif; ?>

<?php if ($produtoCriado): ?>

<!-- ── Produto criado: seção de imagens ──────────────────────────────────── -->
<div class="alert alert-success d-flex align-items-center justify-content-between">
    <span><i class="fas fa-check-circle me-2"></i> Produto criado com sucesso!</span>
    <a href="produto-editar.php?id=<?= $produtoCriado ?>" class="btn btn-sm btn-outline-success">
        <i class="fas fa-edit me-1"></i> Editar produto
    </a>
</div>

<div class="card shadow-sm" style="max-width:720px">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span class="fw-semibold">Imagens do Produto</span>
        <label class="btn btn-sm btn-outline-primary mb-0" id="btn-add-img">
            <i class="fas fa-plus me-1"></i> Adicionar Imagem
            <input type="file" id="input-nova-img" accept=".jpg,.jpeg,.png,.webp" multiple hidden>
        </label>
    </div>
    <div class="card-body">
        <p class="text-muted small mb-3">
            Arraste as imagens para reordenar. Clique em <i class="fas fa-star text-warning"></i> para definir a imagem principal.
        </p>
        <div id="img-grid" class="d-flex flex-wrap gap-3">
            <div class="text-muted small" id="img-empty">Nenhuma imagem cadastrada.</div>
        </div>
        <div id="img-upload-progress" class="mt-2 d-none">
            <div class="progress" style="height:4px">
                <div class="progress-bar progress-bar-striped progress-bar-animated w-100"></div>
            </div>
        </div>
    </div>
</div>

<div class="mt-3" style="max-width:720px">
    <a href="produto-novo.php" class="btn btn-outline-secondary btn-sm me-2">
        <i class="fas fa-plus me-1"></i> Criar outro produto
    </a>
    <a href="produtos.php" class="btn btn-outline-primary btn-sm">
        <i class="fas fa-list me-1"></i> Ver todos os produtos
    </a>
</div>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
(function () {
    const PRODUTO_ID = <?= $produtoCriado ?>;
    const PRODUTO_NOME = <?= json_encode($dados['nome'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;
    const AJAX_URL   = 'ajax-imagens.php';
    const grid       = document.getElementById('img-grid');
    const empty      = document.getElementById('img-empty');
    const progress   = document.getElementById('img-upload-progress');
    const inputFile  = document.getElementById('input-nova-img');

    let sortable;

    function showError(msg) {
        admToast(msg, 'danger');
    }

    function buildCard(img) {
        const div = document.createElement('div');
        div.className  = 'img-card position-relative';
        div.dataset.id = img.id;
        div.style.cssText = 'width:120px;cursor:grab;user-select:none';

        const star = img.principal
            ? '<i class="fas fa-star text-warning"></i>'
            : '<i class="far fa-star text-muted"></i>';

        div.innerHTML = `
            <img src="/${img.caminho}" style="width:120px;height:90px;object-fit:cover;border-radius:8px;display:block;"
                 class="${img.principal ? 'border border-2 border-warning' : 'border'}">
            <div class="d-flex justify-content-between align-items-center mt-1 px-1">
                <button type="button" class="btn btn-link p-0 btn-star" title="Definir como principal">${star}</button>
                <button type="button" class="btn btn-link p-0 text-danger btn-del" title="Remover imagem">
                    <i class="fas fa-trash-alt fa-sm"></i>
                </button>
            </div>`;

        div.querySelector('.btn-star').addEventListener('click', () => setPrincipal(img.id));
        div.querySelector('.btn-del').addEventListener('click', () => deleteImg(img.id, div, !!img.principal));
        return div;
    }

    function renderGrid(imgs) {
        grid.innerHTML = '';
        if (!imgs.length) { grid.appendChild(empty); return; }
        imgs.forEach(img => grid.appendChild(buildCard(img)));
        initSortable();
    }

    function initSortable() {
        if (sortable) sortable.destroy();
        sortable = Sortable.create(grid, {
            animation: 150,
            ghostClass: 'opacity-50',
            onEnd() {
                const ids = [...grid.querySelectorAll('.img-card')].map(el => el.dataset.id);
                const fd  = new FormData();
                fd.append('action', 'reorder');
                ids.forEach(id => fd.append('ids[]', id));
                fetch(AJAX_URL, { method: 'POST', body: fd });
            }
        });
    }

    function setPrincipal(imgId) {
        const fd = new FormData();
        fd.append('action', 'set-principal');
        fd.append('produto_id', PRODUTO_ID);
        fd.append('img_id', imgId);
        fetch(AJAX_URL, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => { if (res.ok) loadImages(); });
    }


    function escaparHtml(valor) {
        const div = document.createElement('div');
        div.textContent = valor;
        return div.innerHTML;
    }

    async function deleteImg(imgId, cardEl, principal) {
        const outras = grid.querySelectorAll('.img-card').length > 1;
        const ok = await admConfirmar({
            titulo: 'Remover imagem',
            texto:  'Remover esta imagem de <strong>' + escaparHtml(PRODUTO_NOME) + '</strong>?'
                  + ' O arquivo é apagado do servidor.'
                  + (principal && outras ? ' Ela é a imagem principal: a próxima da ordem passa a ser a principal.' : '')
                  + '<br><strong class="text-danger">A remoção não pode ser desfeita.</strong>',
            botao:  'Remover',
        });
        if (!ok) return;
        const fd = new FormData();
        fd.append('action', 'delete');
        fd.append('produto_id', PRODUTO_ID);
        fd.append('img_id', imgId);
        fetch(AJAX_URL, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if (res.ok) {
                    cardEl.remove();
                    if (!grid.querySelector('.img-card')) { grid.innerHTML = ''; grid.appendChild(empty); }
                } else {
                    showError(res.erro || 'Erro ao remover imagem.');
                }
            });
    }

    function loadImages() {
        fetch(AJAX_URL + '?produto_id=' + PRODUTO_ID)
            .then(r => r.json())
            .then(res => { if (res.ok) renderGrid(res.data); });
    }

    function uploadFiles(files) {
        const uploads = Array.from(files).map(file => {
            const fd = new FormData();
            fd.append('action', 'upload');
            fd.append('produto_id', PRODUTO_ID);
            fd.append('imagem', file);
            return fetch(AJAX_URL, { method: 'POST', body: fd }).then(r => r.json());
        });
        progress.classList.remove('d-none');
        Promise.all(uploads).then(results => {
            progress.classList.add('d-none');
            const erros = results.filter(r => !r.ok).map(r => r.erro);
            if (erros.length) showError(erros.join('\n'));
            loadImages();
        });
    }

    inputFile.addEventListener('change', function () {
        if (this.files.length) uploadFiles(this.files);
        this.value = '';
    });

    grid.addEventListener('dragover', e => { e.preventDefault(); grid.classList.add('border-primary'); });
    grid.addEventListener('dragleave', () => grid.classList.remove('border-primary'));
    grid.addEventListener('drop', e => {
        e.preventDefault();
        grid.classList.remove('border-primary');
        if (e.dataTransfer.files.length) uploadFiles(e.dataTransfer.files);
    });

    loadImages();
})();
</script>

<?php else: ?>

<!-- ── Formulário de criação ─────────────────────────────────────────────── -->
<div class="card shadow-sm" style="max-width:720px">
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data">
            <div class="row g-3">

                <div class="col-md-8">
                    <label class="form-label">Nome <span class="text-danger">*</span></label>
                    <input type="text" name="nome" class="form-control" required value="<?= htmlspecialchars($dados['nome']) ?>">
                </div>

                <div class="col-md-4">
                    <label class="form-label">Código Interno
                        <span class="text-muted small" data-bs-toggle="tooltip" title="Formato: SE.02.00002 (2 letras maiúsculas, ponto, 2 dígitos, ponto, 5 dígitos)">
                            <i class="fas fa-circle-info"></i>
                        </span>
                    </label>
                    <input type="text" name="codigo_interno" id="codigo_interno" class="form-control font-monospace"
                           placeholder="SE.02.00002" maxlength="11"
                           value="<?= htmlspecialchars($dados['codigo_interno']) ?>"
                           pattern="[A-Z]{2}\.[0-9]{2}\.[0-9]{5}"
                           oninput="this.value=this.value.toUpperCase()">
                    <div class="invalid-feedback">Formato: SE.02.00002</div>
                </div>

                <div class="col-12">
                    <label class="form-label">Descrição</label>
                    <textarea name="descricao" class="form-control" rows="3"><?= htmlspecialchars($dados['descricao']) ?></textarea>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Preço (R$) <span class="text-danger">*</span></label>
                    <input type="number" name="preco" class="form-control" step="0.01" min="0" required value="<?= htmlspecialchars($dados['preco']) ?>">
                </div>

                <div class="col-md-4">
                    <label class="form-label" for="preco-export-usd">Preço de exportação (USD)</label>
                    <div class="input-group">
                        <input type="number" name="preco_export_usd" id="preco-export-usd" class="form-control" step="0.01" min="0"
                               placeholder="vazio = Sob consulta" value="<?= htmlspecialchars((string) ($dados['preco_export_usd'] ?? '')) ?>">
                        <span class="input-group-text small" title="Incoterm vigente (constante do sistema)"><?= htmlspecialchars(INCOTERM_PADRAO) ?></span>
                    </div>
                    <div class="form-text">Segunda lista de preço — não é conversão do preço em reais.</div>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Estoque <span class="text-danger">*</span></label>
                    <input type="number" name="estoque" class="form-control" min="0" required value="<?= htmlspecialchars($dados['estoque']) ?>">
                </div>

                <div class="col-md-4">
                    <label class="form-label">Categoria <span class="text-danger">*</span></label>
                    <select name="categoria" class="form-select" required>
                        <option value="">Selecione...</option>
                        <?php foreach ($categoriasDb as $cat): ?>
                            <option value="<?= htmlspecialchars($cat['slug']) ?>" <?= $dados['categoria'] === $cat['slug'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php require __DIR__ . '/_disponibilidade-campos.php'; ?>

                <!-- Dimensões para cálculo de frete -->
                <div class="col-12">
                    <label class="form-label fw-semibold">
                        <i class="fas fa-box me-1 text-muted"></i> Dimensões para frete
                        <span class="text-danger">*</span>
                    </label>
                    <div class="row g-2">
                        <div class="col-6 col-md-3">
                            <label class="form-label form-label-sm text-muted">Peso (kg)</label>
                            <input type="number" name="peso" class="form-control form-control-sm" step="0.01" min="0.01" required value="<?= htmlspecialchars($dados['peso']) ?>">
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label form-label-sm text-muted">Largura (cm)</label>
                            <input type="number" name="largura" class="form-control form-control-sm" step="0.1" min="0.1" required value="<?= htmlspecialchars($dados['largura']) ?>">
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label form-label-sm text-muted">Altura (cm)</label>
                            <input type="number" name="altura" class="form-control form-control-sm" step="0.1" min="0.1" required value="<?= htmlspecialchars($dados['altura']) ?>">
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label form-label-sm text-muted">Comprimento (cm)</label>
                            <input type="number" name="comprimento" class="form-control form-control-sm" step="0.1" min="0.1" required value="<?= htmlspecialchars($dados['comprimento']) ?>">
                        </div>
                    </div>
                    <div class="form-text">Dimensões da embalagem com o produto. Usadas no cálculo de frete.</div>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Imagem Principal</label>
                    <input type="file" name="imagem" class="form-control" accept=".jpg,.jpeg,.png,.webp">
                    <div class="form-text">JPG, PNG ou WebP — máx. 5 MB.</div>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Data Sheet <span class="text-muted small">(PDF)</span></label>
                    <input type="file" name="datasheet" class="form-control" accept=".pdf">
                    <div class="form-text">Apenas PDF — máx. 10 MB.</div>
                </div>

                <div class="col-12">
                    <label class="form-label">Especificação Técnica
                        <span class="text-muted small ms-1">— suporta Markdown (negrito, listas, etc.)</span>
                    </label>
                    <textarea name="especificacao_tecnica" id="especificacao_tecnica"
                              class="form-control"><?= htmlspecialchars($dados['especificacao_tecnica']) ?></textarea>
                </div>

                <div class="col-12">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="ativo" id="ativo" <?= $dados['ativo'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="ativo">Produto ativo (visível no site)</label>
                    </div>
                </div>

                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> Salvar e Continuar
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/easymde/dist/easymde.min.js"></script>
<script>
new EasyMDE({
    element: document.getElementById('especificacao_tecnica'),
    toolbar: ['bold', 'italic', '|', 'unordered-list', 'ordered-list', '|', 'preview', 'guide'],
    spellChecker: false,
    status: false,
    placeholder: 'Descreva as especificações técnicas do produto...',
    minHeight: '160px',
});

document.getElementById('codigo_interno').addEventListener('blur', function () {
    const pattern = /^[A-Z]{2}\.\d{2}\.\d{5}$/;
    if (this.value && !pattern.test(this.value)) {
        this.classList.add('is-invalid');
    } else {
        this.classList.remove('is-invalid');
    }
});

document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));
});
</script>

<?php endif; ?>

<?php admin_modal_confirmar(); ?>
<?php layout_foot(); ?>
