<?php
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/_layout.php';

$pdo     = getDB();
$erros   = [];
$sucesso = '';

// Vigência: null início = já vale; null fim = não expira. Cálculo sempre server-side.
function campanhaStatus(?string $inicio, ?string $fim, int $ativo): array {
    if (!$ativo) return ['label' => 'Inativa', 'cls' => 'secondary'];

    $hoje = date('Y-m-d');
    if ($inicio && $hoje < $inicio) return ['label' => 'Agendada', 'cls' => 'info'];
    if ($fim && $hoje > $fim)       return ['label' => 'Expirada', 'cls' => 'danger'];
    return ['label' => 'Vigente', 'cls' => 'success'];
}

function salvarUpload(array $arquivo, array &$erros): ?string {
    if (empty($arquivo['name']) || $arquivo['error'] !== UPLOAD_ERR_OK) {
        $erros[] = 'Nenhum arquivo enviado ou erro no upload.';
        return null;
    }
    if ($arquivo['size'] > 5 * 1024 * 1024) {
        $erros[] = 'Imagem muito grande. Máximo: 5 MB.';
        return null;
    }

    $ext = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
        $erros[] = 'Formato inválido. Use JPG, PNG ou WebP.';
        return null;
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $arquivo['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'])) {
        $erros[] = 'Arquivo não é uma imagem válida.';
        return null;
    }

    $nomeArquivo = 'carrossel_' . uniqid() . '.' . $ext;
    $destino     = __DIR__ . '/../../img/' . $nomeArquivo;

    if (!move_uploaded_file($arquivo['tmp_name'], $destino)) {
        $erros[] = 'Erro ao salvar arquivo no servidor.';
        return null;
    }

    return 'img/' . $nomeArquivo;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── Criar campanha ───────────────────────────────────────────────────────
    if ($action === 'criar') {
        $titulo      = trim($_POST['titulo'] ?? '');
        $linkDestino = trim($_POST['link_destino'] ?? '') ?: null;
        $ordem       = (int) ($_POST['ordem'] ?? 0);
        $ativo       = isset($_POST['ativo']) ? 1 : 0;
        $dataInicio  = trim($_POST['data_inicio'] ?? '') ?: null;
        $dataFim     = trim($_POST['data_fim'] ?? '') ?: null;

        if (!$titulo) $erros[] = 'Título é obrigatório.';
        if ($dataInicio && $dataFim && $dataInicio > $dataFim) {
            $erros[] = 'Data de início não pode ser depois da data de fim.';
        }

        $caminho = null;
        if (empty($erros)) {
            $caminho = salvarUpload($_FILES['arquivo'] ?? [], $erros);
        }

        if (empty($erros) && $caminho) {
            $agora = date('Y-m-d H:i:s');
            $pdo->prepare("
                INSERT INTO carrossel_campanhas
                    (titulo, arquivo, link_destino, ordem, ativo, data_inicio, data_fim, created_at, updated_at)
                VALUES (:titulo, :arquivo, :link, :ordem, :ativo, :inicio, :fim, :now, :now)
            ")->execute([
                ':titulo' => $titulo, ':arquivo' => $caminho, ':link' => $linkDestino,
                ':ordem' => $ordem, ':ativo' => $ativo,
                ':inicio' => $dataInicio, ':fim' => $dataFim, ':now' => $agora,
            ]);
            $sucesso = "Campanha <b>" . htmlspecialchars($titulo) . "</b> criada com sucesso.";
        }
    }

    // ── Editar campanha (período/ordem/link/título/ativo; imagem opcional) ───
    if ($action === 'editar') {
        $id          = (int) ($_POST['campanha_id'] ?? 0);
        $titulo      = trim($_POST['titulo'] ?? '');
        $linkDestino = trim($_POST['link_destino'] ?? '') ?: null;
        $ordem       = (int) ($_POST['ordem'] ?? 0);
        $ativo       = isset($_POST['ativo']) ? 1 : 0;
        $dataInicio  = trim($_POST['data_inicio'] ?? '') ?: null;
        $dataFim     = trim($_POST['data_fim'] ?? '') ?: null;

        $existente = $pdo->prepare("SELECT * FROM carrossel_campanhas WHERE id = :id");
        $existente->execute([':id' => $id]);
        $campanha = $existente->fetch();

        if (!$campanha) {
            $erros[] = 'Campanha não encontrada.';
        } else {
            if (!$titulo) $erros[] = 'Título é obrigatório.';
            if ($dataInicio && $dataFim && $dataInicio > $dataFim) {
                $erros[] = 'Data de início não pode ser depois da data de fim.';
            }

            $novoCaminho = $campanha['arquivo'];
            if (!empty($_FILES['arquivo']['name'])) {
                $upload = salvarUpload($_FILES['arquivo'], $erros);
                if ($upload) $novoCaminho = $upload;
            }

            if (empty($erros)) {
                $pdo->prepare("
                    UPDATE carrossel_campanhas SET
                        titulo = :titulo, arquivo = :arquivo, link_destino = :link,
                        ordem = :ordem, ativo = :ativo,
                        data_inicio = :inicio, data_fim = :fim, updated_at = :now
                    WHERE id = :id
                ")->execute([
                    ':titulo' => $titulo, ':arquivo' => $novoCaminho, ':link' => $linkDestino,
                    ':ordem' => $ordem, ':ativo' => $ativo,
                    ':inicio' => $dataInicio, ':fim' => $dataFim,
                    ':now' => date('Y-m-d H:i:s'), ':id' => $id,
                ]);

                // Remove o arquivo antigo só se foi substituído por um novo
                if ($novoCaminho !== $campanha['arquivo']) {
                    $antigo = __DIR__ . '/../../' . $campanha['arquivo'];
                    if (file_exists($antigo)) unlink($antigo);
                }

                $sucesso = "Campanha <b>" . htmlspecialchars($titulo) . "</b> atualizada.";
            }
        }
    }

    // ── Alternar ativo/inativo ───────────────────────────────────────────────
    if ($action === 'toggle') {
        $id = (int) ($_POST['campanha_id'] ?? 0);
        $pdo->prepare("UPDATE carrossel_campanhas SET ativo = 1 - ativo, updated_at = :now WHERE id = :id")
            ->execute([':now' => date('Y-m-d H:i:s'), ':id' => $id]);
    }

    // ── Excluir campanha ─────────────────────────────────────────────────────
    if ($action === 'excluir') {
        $id = (int) ($_POST['campanha_id'] ?? 0);

        $stmt = $pdo->prepare("SELECT titulo, arquivo FROM carrossel_campanhas WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $campanha = $stmt->fetch();

        if ($campanha) {
            $pdo->prepare("DELETE FROM carrossel_campanhas WHERE id = :id")->execute([':id' => $id]);

            $arquivo = __DIR__ . '/../../' . $campanha['arquivo'];
            if (file_exists($arquivo)) unlink($arquivo);

            $sucesso = "Campanha <b>" . htmlspecialchars($campanha['titulo']) . "</b> removida.";
        }
    }
}

// Campanha em edição (se veio ?editar=ID na URL)
$emEdicao = null;
if (isset($_GET['editar'])) {
    $stmt = $pdo->prepare("SELECT * FROM carrossel_campanhas WHERE id = :id");
    $stmt->execute([':id' => (int) $_GET['editar']]);
    $emEdicao = $stmt->fetch() ?: null;
}

$campanhas = $pdo->query("SELECT * FROM carrossel_campanhas ORDER BY ordem ASC, id ASC")->fetchAll();

layout_head('Carrossel de Campanhas');
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

<div class="row g-4">

    <!-- Lista de campanhas -->
    <div class="col-lg-7">
        <div class="card shadow-sm">
            <div class="card-header fw-semibold">Campanhas cadastradas</div>
            <div class="card-body p-0">
                <?php if (empty($campanhas)): ?>
                    <p class="text-muted p-3 mb-0">Nenhuma campanha cadastrada.</p>
                <?php else: ?>
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Imagem</th>
                                <th>Título</th>
                                <th class="text-center">Ordem</th>
                                <th class="text-center">Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($campanhas as $c): $st = campanhaStatus($c['data_inicio'], $c['data_fim'], (int) $c['ativo']); ?>
                                <tr>
                                    <td><img src="../../<?= htmlspecialchars($c['arquivo']) ?>" alt="" style="width:70px;height:40px;object-fit:cover;border-radius:4px;"></td>
                                    <td>
                                        <div class="fw-medium"><?= htmlspecialchars($c['titulo']) ?></div>
                                        <?php if ($c['data_inicio'] || $c['data_fim']): ?>
                                            <div class="text-muted small">
                                                <?= $c['data_inicio'] ? htmlspecialchars($c['data_inicio']) : '—' ?>
                                                até
                                                <?= $c['data_fim'] ? htmlspecialchars($c['data_fim']) : '—' ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center"><?= (int) $c['ordem'] ?></td>
                                    <td class="text-center">
                                        <span class="badge bg-<?= $st['cls'] ?>"><?= $st['label'] ?></span>
                                    </td>
                                    <td class="text-end">
                                        <div class="d-flex gap-1 justify-content-end">
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="action" value="toggle">
                                                <input type="hidden" name="campanha_id" value="<?= $c['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-secondary" title="<?= $c['ativo'] ? 'Desativar' : 'Ativar' ?>">
                                                    <i class="fas <?= $c['ativo'] ? 'fa-toggle-on' : 'fa-toggle-off' ?>"></i>
                                                </button>
                                            </form>
                                            <a href="?editar=<?= $c['id'] ?>" class="btn btn-sm btn-outline-primary" title="Editar">
                                                <i class="fas fa-pen"></i>
                                            </a>
                                            <form method="POST" class="d-inline"
                                                  onsubmit="return confirm('Remover a campanha \'<?= htmlspecialchars($c['titulo']) ?>\'? A imagem também será apagada.')">
                                                <input type="hidden" name="action" value="excluir">
                                                <input type="hidden" name="campanha_id" value="<?= $c['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Excluir">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Formulário de nova campanha / edição -->
    <div class="col-lg-5">
        <div class="card shadow-sm">
            <div class="card-header fw-semibold">
                <?= $emEdicao ? 'Editar Campanha' : 'Nova Campanha' ?>
                <?php if ($emEdicao): ?>
                    <a href="carrossel.php" class="float-end small text-decoration-none">Cancelar</a>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="<?= $emEdicao ? 'editar' : 'criar' ?>">
                    <?php if ($emEdicao): ?>
                        <input type="hidden" name="campanha_id" value="<?= $emEdicao['id'] ?>">
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label">Título <span class="text-danger">*</span></label>
                        <input type="text" name="titulo" class="form-control" maxlength="120" required
                               value="<?= htmlspecialchars($emEdicao['titulo'] ?? '') ?>">
                        <div class="form-text">Usado como texto alternativo (a11y) da imagem.</div>
                    </div>

                    <div class="alert alert-light border small mb-3">
                        <i class="fas fa-ruler-combined me-1 text-primary"></i>
                        <strong>Tamanho ideal: 2000×480px</strong> (proporção ~4,2:1).
                        O carrossel é full-bleed (ocupa a largura toda da tela) com altura fixa —
                        cada tamanho de tela corta uma parte diferente da imagem para preencher o
                        espaço (mais corte nas laterais em celular, pouco corte em cima/embaixo no
                        desktop). Se sua arte tiver bastante conteúdo (logo + texto + tags, como um
                        banner institucional), respeite bem essa proporção — imagens muito mais
                        "altas" que isso (ex.: 1983×793) perdem uma fatia grande em cima/embaixo.
                        Mantenha texto/logo <strong>centralizados</strong>, dentro de uns 40% da
                        largura e 90% da altura da imagem, para não serem cortados em nenhuma tela.
                    </div>

                    <div class="mb-3">
                        <label class="form-label">
                            Imagem <?= $emEdicao ? '' : '<span class="text-danger">*</span>' ?>
                        </label>
                        <input type="file" name="arquivo" class="form-control" accept=".jpg,.jpeg,.png,.webp" <?= $emEdicao ? '' : 'required' ?>>
                        <?php if ($emEdicao): ?>
                            <div class="form-text">
                                Atual: <img src="../../<?= htmlspecialchars($emEdicao['arquivo']) ?>" alt="" style="height:24px;vertical-align:middle;border-radius:3px;">
                                — deixe em branco para manter.
                            </div>
                        <?php else: ?>
                            <div class="form-text">JPG, PNG ou WebP — máximo 5 MB.</div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Link de destino (opcional)</label>
                        <input type="url" name="link_destino" class="form-control" placeholder="https://..."
                               value="<?= htmlspecialchars($emEdicao['link_destino'] ?? '') ?>">
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label">Início (opcional)</label>
                            <input type="date" name="data_inicio" class="form-control"
                                   value="<?= htmlspecialchars($emEdicao['data_inicio'] ?? '') ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Fim (opcional)</label>
                            <input type="date" name="data_fim" class="form-control"
                                   value="<?= htmlspecialchars($emEdicao['data_fim'] ?? '') ?>">
                        </div>
                        <div class="form-text">Vazio = sem restrição de início/fim (sempre vigente nesse limite).</div>
                    </div>

                    <div class="row g-2 mb-3 align-items-center">
                        <div class="col-6">
                            <label class="form-label">Ordem</label>
                            <input type="number" name="ordem" class="form-control" min="0"
                                   value="<?= (int) ($emEdicao['ordem'] ?? 0) ?>">
                        </div>
                        <div class="col-6 pt-4">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" name="ativo" id="input-ativo"
                                       <?= (!$emEdicao || $emEdicao['ativo']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="input-ativo">Ativa</label>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-<?= $emEdicao ? 'save' : 'plus' ?> me-1"></i>
                        <?= $emEdicao ? 'Salvar alterações' : 'Criar Campanha' ?>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php layout_foot(); ?>
