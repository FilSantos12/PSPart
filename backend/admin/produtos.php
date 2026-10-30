<?php
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/_ui.php';
require_once __DIR__ . '/_confirmar.php';
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

layout_head('Produtos');
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

<?php admin_alerta_msg(); ?>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 adm-table">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>Produto</th>
                    <th>Código</th>
                    <th>Categoria</th>
                    <th class="text-end">Preço</th>
                    <th>Disponibilidade</th>
                    <th class="text-end">Estoque</th>
                    <th>Status</th>
                    <th class="text-end">Ações</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($produtos as $p): ?>
                <tr>
                    <td class="text-muted small text-nowrap"><?= $p['id'] ?></td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <?php if ($p['img_thumb']): ?>
                                <?php
                                    $full = __DIR__ . '/../../' . $p['img_thumb'];
                                    $bust = file_exists($full) ? '?v=' . filemtime($full) : '';
                                ?>
                                <img src="/<?= htmlspecialchars($p['img_thumb']) . $bust ?>" width="40" height="40" class="adm-thumb" alt="">
                            <?php endif; ?>
                            <div class="adm-produto-texto">
                                <div class="fw-semibold text-truncate" title="<?= htmlspecialchars($p['nome']) ?>"><?= htmlspecialchars($p['nome']) ?></div>
                                <div class="text-muted small text-truncate"><?= htmlspecialchars($p['descricao'] ?? '') ?></div>
                            </div>
                        </div>
                    </td>
                    <td class="font-monospace small text-muted text-nowrap"><?= $p['codigo_interno'] ? htmlspecialchars($p['codigo_interno']) : '—' ?></td>
                    <td><span class="badge bg-secondary"><?= htmlspecialchars($p['categoria']) ?></span></td>
                    <td class="text-end text-nowrap">R$ <?= number_format($p['preco'], 2, ',', '.') ?></td>
                    <td>
                        <?php
                            // Badge só com o estado, sem quantidade; o número fica na coluna Estoque
                            $disp = disponibilidade_produto($p);
                        ?>
                        <span class="stock-badge <?= htmlspecialchars($disp['badge_class']) ?> mb-0 text-nowrap"><span class="stock-dot"></span><?= htmlspecialchars(disponibilidade_label_estado($disp)) ?></span>
                        <?php if ($disp['texto_consulta']): ?>
                            <div class="small text-primary text-nowrap" title="Limiar de consulta comercial (informativo)">
                                <i class="fas fa-handshake me-1"></i><?= htmlspecialchars($disp['texto_consulta']) ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td class="text-end text-nowrap"><?= (int) $p['estoque'] ?></td>
                    <td class="text-nowrap">
                        <span class="adm-pill<?= $p['ativo'] ? '' : ' adm-pill--off' ?>"><?= $p['ativo'] ? 'Ativo' : 'Inativo' ?></span>
                    </td>
                    <td>
                        <?php
                            $pedidosVinc = (int) $p['pedidos_vinculados'];
                            $dataProduto = 'data-id="' . (int) $p['id'] . '" data-nome="' . htmlspecialchars($p['nome']) . '"'
                                         . ' data-codigo="' . htmlspecialchars($p['codigo_interno'] ?? '') . '"';
                            $motivoExcluir = $pedidosVinc . ' ' . ($pedidosVinc === 1 ? 'pedido vinculado' : 'pedidos vinculados') . ' — só pode ser inativado';
                        ?>
                        <div class="adm-acoes">
                            <a href="produto-editar.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary" title="Editar" aria-label="Editar <?= htmlspecialchars($p['nome']) ?>">
                                <i class="fas fa-edit" aria-hidden="true"></i>
                            </a>
                            <?php if ($p['ativo']): ?>
                                <button type="button" class="btn btn-sm btn-outline-warning btn-estado" data-acao="inativar" <?= $dataProduto ?>
                                        title="Inativar" aria-label="Inativar <?= htmlspecialchars($p['nome']) ?>">
                                    <i class="fas fa-eye-slash" aria-hidden="true"></i>
                                </button>
                            <?php else: ?>
                                <button type="button" class="btn btn-sm btn-outline-success btn-estado" data-acao="reativar" <?= $dataProduto ?>
                                        title="Reativar" aria-label="Reativar <?= htmlspecialchars($p['nome']) ?>">
                                    <i class="fas fa-eye" aria-hidden="true"></i>
                                </button>
                            <?php endif; ?>
                            <?php if ($pedidosVinc > 0): ?>
                                <?php // Patch P: bloqueado fica desabilitado com o motivo, nunca escondido. Botão desabilitado
                                      // não recebe foco nem hover, então a dica fica no <span> em volta (padrão do Bootstrap). ?>
                                <span class="d-inline-block" tabindex="0" data-bs-toggle="tooltip" data-bs-title="<?= htmlspecialchars($motivoExcluir) ?>">
                                    <button type="button" class="btn btn-sm btn-outline-danger btn-estado" data-acao="excluir" <?= $dataProduto ?>
                                            title="Excluir definitivamente" aria-label="Excluir definitivamente <?= htmlspecialchars($p['nome']) ?>"
                                            disabled aria-describedby="motivo-excluir-<?= (int) $p['id'] ?>">
                                        <i class="fas fa-trash" aria-hidden="true"></i>
                                    </button>
                                </span>
                                <span class="visually-hidden" id="motivo-excluir-<?= (int) $p['id'] ?>"><?= htmlspecialchars($motivoExcluir) ?></span>
                            <?php else: ?>
                                <button type="button" class="btn btn-sm btn-outline-danger btn-estado" data-acao="excluir" <?= $dataProduto ?>
                                        title="Excluir definitivamente" aria-label="Excluir definitivamente <?= htmlspecialchars($p['nome']) ?>">
                                    <i class="fas fa-trash" aria-hidden="true"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($produtos)): ?>
                <tr><td colspan="9" class="text-center text-muted py-4"><?= $filtro === 'todos' ? 'Nenhum produto cadastrado.' : 'Nenhum produto ' . ($filtro === 'ativos' ? 'ativo' : 'inativo') . '.' ?></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Confirmação (Patch P) pelo modal único de _confirmar.php: título, texto e botão trocados por
     ação, com o texto descrevendo exatamente o que o servidor vai fazer; a ação vai explícita no POST. -->
<form method="POST" action="produto-estado.php" id="formEstado">
    <input type="hidden" name="id" id="idProduto">
    <input type="hidden" name="acao" id="acaoProduto">
    <input type="hidden" name="estado" value="<?= htmlspecialchars($filtro) ?>">
</form>

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
    btn.addEventListener('click', async () => {
        const conf = CONFIRMACOES_ESTADO[btn.dataset.acao];
        if (!conf || btn.disabled) return;
        const ok = await admConfirmar({
            titulo: conf.titulo,
            texto:  conf.texto(escaparHtml(btn.dataset.nome), escaparHtml(btn.dataset.codigo)),
            botao:  conf.botao,
            classe: conf.classe,
        });
        if (!ok) return;
        document.getElementById('idProduto').value   = btn.dataset.id;
        document.getElementById('acaoProduto').value = btn.dataset.acao;
        document.getElementById('formEstado').submit();
    });
});

// Dica com o motivo do bloqueio — o bundle do Bootstrap só carrega no layout_foot()
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));
});
</script>

<?php admin_modal_confirmar(); ?>
<?php layout_foot(); ?>
