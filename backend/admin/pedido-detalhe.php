<?php
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/../helpers/email.php';
require_once __DIR__ . '/../helpers/status.php';
require_once __DIR__ . '/../config/loja.php';

$pdo = getDB();
$id  = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM pedidos WHERE id = :id");
$stmt->execute([':id' => $id]);
$pedido = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pedido) {
    header('Location: pedidos.php');
    exit;
}

$numeroPedido = $pedido['numero_pedido'] ?? $id;

$itens = $pdo->prepare("
    SELECT ip.quantidade, ip.preco_unitario,
           pr.nome AS produto_nome, pr.imagem, pr.codigo_interno
    FROM itens_pedido ip
    JOIN produtos pr ON pr.id = ip.produto_id
    WHERE ip.pedido_id = :id
");
$itens->execute([':id' => $id]);
$itens = $itens->fetchAll(PDO::FETCH_ASSOC);

$stmtTracking = $pdo->prepare("SELECT * FROM order_tracking WHERE order_id = :oid LIMIT 1");
$stmtTracking->execute([':oid' => (string) $id]);
$tracking = $stmtTracking->fetch(PDO::FETCH_ASSOC);

$statusValidos   = ['pendente','aprovado','em_analise','recusado','cancelado','reembolsado','contestado','em_processamento'];
$mensagemSucesso = '';
$mensagemFicha   = '';
$tipoFicha       = '';

// Envio da Ficha de Separação por e-mail
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'enviar_ficha') {
    $host    = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
    $isLocal = str_contains($host, 'localhost') || str_contains($host, '127.0.0.1');
    emailFichaSeparacao($pedido, $itens);
    if ($isLocal) {
        $mensagemFicha = 'Em ambiente local o e-mail não é enviado — registrado no log.';
        $tipoFicha     = 'warning';
    } else {
        $mensagemFicha = 'Ficha de separação enviada com sucesso para o setor interno.';
        $tipoFicha     = 'success';
    }
}

// Atualização de status manual
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['status'])) {
    $novoStatus = $_POST['status'];

    if (in_array($novoStatus, $statusValidos)) {
        $pdo->prepare("UPDATE pedidos SET status = :status WHERE id = :id")
            ->execute([':status' => $novoStatus, ':id' => $id]);

        $pedido['status'] = $novoStatus;
        $mensagemSucesso  = 'Status atualizado com sucesso.';
    }
}

$statusBadge = [
    'aprovado'         => 'success',
    'pendente'         => 'warning',
    'em_analise'       => 'info',
    'recusado'         => 'danger',
    'cancelado'        => 'secondary',
    'reembolsado'      => 'secondary',
    'contestado'       => 'danger',
    'em_processamento' => 'purple',
    'enviado'          => 'primary',
];

layout_head('Pedido #' . $numeroPedido);
?>
<style>
  .bg-purple { background-color: #6f42c1 !important; }
</style>

<div class="mb-3">
    <a href="pedidos.php" class="text-decoration-none text-muted small">
        <i class="fas fa-arrow-left me-1"></i> Voltar para Pedidos
    </a>
</div>

<?php if ($mensagemSucesso): ?>
<div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
    <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($mensagemSucesso) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if ($mensagemFicha): ?>
<div class="alert alert-<?= $tipoFicha ?> alert-dismissible fade show mb-3" role="alert">
    <i class="fas fa-<?= $tipoFicha === 'success' ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i><?= htmlspecialchars($mensagemFicha) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row g-3">
    <!-- Dados do comprador -->
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">Dados do Comprador</div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-4 text-muted">Nome</dt>
                    <dd class="col-8"><?= htmlspecialchars($pedido['nome_comprador']) ?></dd>
                    <dt class="col-4 text-muted">E-mail</dt>
                    <dd class="col-8"><?= htmlspecialchars($pedido['email_comprador']) ?></dd>
                    <dt class="col-4 text-muted">Telefone</dt>
                    <dd class="col-8"><?= htmlspecialchars($pedido['telefone_comprador']) ?></dd>
                    <?php if ($pedido['cep']): ?>
                    <dt class="col-4 text-muted">Endereço</dt>
                    <dd class="col-8">
                        <?= htmlspecialchars($pedido['endereco'] . ($pedido['numero'] ? ', ' . $pedido['numero'] : '')) ?>
                        <?php if ($pedido['complemento']): ?> — <?= htmlspecialchars($pedido['complemento']) ?><?php endif; ?><br>
                        <?= htmlspecialchars($pedido['bairro']) ?> — <?= htmlspecialchars($pedido['cidade']) ?>/<?= htmlspecialchars($pedido['estado']) ?><br>
                        CEP: <?= htmlspecialchars($pedido['cep']) ?>
                    </dd>
                    <?php endif; ?>
                    <dt class="col-4 text-muted">Data</dt>
                    <dd class="col-8"><?= date('d/m/Y H:i', strtotime($pedido['criado_em'])) ?></dd>
                    <?php if ($pedido['mp_preferencia_id']): ?>
                    <dt class="col-4 text-muted">MP ID</dt>
                    <dd class="col-8 small text-truncate"><?= htmlspecialchars($pedido['mp_preferencia_id']) ?></dd>
                    <?php endif; ?>
                </dl>
            </div>
        </div>
    </div>

    <!-- Status e total -->
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">Status do Pedido</div>
            <div class="card-body">
                <div class="mb-3">
                    <span class="badge bg-<?= $statusBadge[$pedido['status']] ?? 'secondary' ?> fs-6">
                        <?= $pedido['status'] ?>
                    </span>
                </div>
                <div class="fs-4 fw-bold text-success mb-3">
                    R$ <?= number_format($pedido['total'], 2, ',', '.') ?>
                </div>
                <form method="POST">
                    <div class="d-flex gap-2 align-items-center">
                        <select name="status" class="form-select form-select-sm">
                            <?php foreach ($statusValidos as $s): ?>
                                <option value="<?= $s ?>" <?= $pedido['status'] === $s ? 'selected' : '' ?>>
                                    <?= match($s) {
                                        'pendente'         => 'Pendente',
                                        'aprovado'         => 'Aprovado',
                                        'em_analise'       => 'Em Análise',
                                        'recusado'         => 'Recusado',
                                        'cancelado'        => 'Cancelado',
                                        'reembolsado'      => 'Reembolsado',
                                        'contestado'       => 'Contestado',
                                        'em_processamento' => 'Em Processamento',
                                        default            => ucfirst($s),
                                    } ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-sm btn-primary text-nowrap">Atualizar</button>
                    </div>
                    <div class="form-text text-muted mt-2">
                        <i class="fas fa-info-circle me-1"></i>
                        Código de rastreio e envio gerenciados em
                        <a href="tracking-admin.php" class="text-decoration-none">Rastreamento</a>.
                    </div>
                </form>

                <hr class="my-3">
                <div class="d-flex gap-2 flex-wrap">
                    <a href="pedido-ficha.php?id=<?= $id ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-print me-1"></i> Imprimir Ficha
                    </a>
                    <form method="POST" class="d-inline">
                        <input type="hidden" name="action" value="enviar_ficha">
                        <button type="submit" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-envelope me-1"></i> Enviar Ficha por E-mail
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Entrega -->
    <?php if ($tracking && ($tracking['chosen_carrier'] || $tracking['tracking_code'])): ?>
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold d-flex align-items-center gap-2">
                <i class="fas fa-truck text-primary"></i> Entrega
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <?php if ($tracking['chosen_carrier']): ?>
                    <div class="col-md-6">
                        <div class="entrega-bloco-escolha p-3 rounded">
                            <p class="small text-muted mb-2 fw-semibold text-uppercase" style="letter-spacing:.04em;">
                                <i class="fas fa-user me-1"></i>Opção escolhida pelo cliente
                            </p>
                            <dl class="row mb-0 small">
                                <dt class="col-5 text-muted">Transportadora</dt>
                                <dd class="col-7 fw-semibold"><?= htmlspecialchars($tracking['chosen_carrier']) ?></dd>
                                <?php if ($tracking['chosen_service']): ?>
                                <dt class="col-5 text-muted">Serviço</dt>
                                <dd class="col-7"><?= htmlspecialchars($tracking['chosen_service']) ?></dd>
                                <?php endif; ?>
                                <?php if ($tracking['shipping_price'] !== null): ?>
                                <dt class="col-5 text-muted">Valor do frete</dt>
                                <dd class="col-7">
                                    <?php if ((float) $tracking['shipping_price'] === 0.0): ?>
                                        <span class="badge bg-success">Grátis</span>
                                    <?php else: ?>
                                        R$ <?= number_format((float) $tracking['shipping_price'], 2, ',', '.') ?>
                                    <?php endif; ?>
                                </dd>
                                <?php endif; ?>
                                <?php if ($tracking['shipping_deadline']): ?>
                                <dt class="col-5 text-muted">Prazo contratado</dt>
                                <dd class="col-7"><?= (int) $tracking['shipping_deadline'] ?> dias úteis</dd>
                                <?php endif; ?>
                                <?php if ($tracking['destination_cep']): ?>
                                <dt class="col-5 text-muted">CEP de destino</dt>
                                <dd class="col-7" style="font-family:monospace;"><?= htmlspecialchars($tracking['destination_cep']) ?></dd>
                                <?php endif; ?>
                            </dl>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div class="col-md-6">
                        <div class="p-3">
                            <p class="small text-muted mb-2 fw-semibold text-uppercase" style="letter-spacing:.04em;">
                                <i class="fas fa-box me-1"></i>Rastreamento
                            </p>
                            <dl class="row mb-0 small">
                                <?php
                                $trackLabels = ['Em Preparação','Embalado','Enviado','Código de Rastreio'];
                                $trackColors = ['secondary','primary','warning','success'];
                                $ts = (int) ($tracking['status'] ?? 0);
                                $rastreioAtivo = statusPermiteRastreamento($pedido['status']);
                                ?>
                                <dt class="col-5 text-muted">Status</dt>
                                <dd class="col-7">
                                    <?php if ($rastreioAtivo): ?>
                                        <span class="badge bg-<?= $trackColors[$ts] ?? 'secondary' ?>">
                                            <?= htmlspecialchars($trackLabels[$ts] ?? '—') ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted fst-italic small">—</span>
                                    <?php endif; ?>
                                </dd>
                                <?php if ($tracking['carrier']): ?>
                                <dt class="col-5 text-muted">Transportadora efetiva</dt>
                                <dd class="col-7"><?= htmlspecialchars($tracking['carrier']) ?></dd>
                                <?php endif; ?>
                                <?php if ($tracking['tracking_code']): ?>
                                <dt class="col-5 text-muted">Código</dt>
                                <dd class="col-7" style="font-family:monospace;"><?= htmlspecialchars($tracking['tracking_code']) ?></dd>
                                <?php endif; ?>
                            </dl>
                            <a href="tracking-admin.php" class="btn btn-sm btn-outline-primary mt-3">
                                <i class="fas fa-pen me-1"></i>Gerenciar rastreamento
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Etiqueta de Envio — Melhor Envio -->
    <?php if ($tracking && statusPermiteRastreamento($pedido['status'])): ?>
    <?php
    $meOrderId    = $tracking['melhorenvio_order_id'] ?? null;
    $labelUrl     = $tracking['label_url']            ?? null;
    $fretePreco   = isset($tracking['shipping_price']) ? (float) $tracking['shipping_price'] : null;
    $dadosReais   = defined('LOJA_DADOS_REAIS') && LOJA_DADOS_REAIS === true;

    // Estado: sem_carrinho | em_processamento | disponivel
    $meEstado = $meOrderId === null ? 'sem_carrinho'
              : ($labelUrl  !== null ? 'disponivel' : 'em_processamento');
    ?>
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold d-flex align-items-center gap-2">
                <i class="fas fa-tag text-primary"></i> Etiqueta de Envio — Melhor Envio
                <?php if ($meEstado === 'sem_carrinho'): ?>
                    <span class="badge bg-secondary ms-auto">Sem carrinho</span>
                <?php elseif ($meEstado === 'em_processamento'): ?>
                    <span class="badge bg-warning text-dark ms-auto">Em processamento</span>
                <?php else: ?>
                    <span class="badge bg-success ms-auto">Etiqueta disponível</span>
                <?php endif; ?>
            </div>
            <div class="card-body">

                <?php if (!$dadosReais): ?>
                <div class="alert alert-warning py-2 mb-3 small">
                    <i class="fas fa-exclamation-triangle me-1"></i>
                    Dados da loja são <strong>fictícios (sandbox)</strong>.
                    O botão de <strong>Pagar etiqueta</strong> ficará bloqueado até que
                    <code>LOJA_DADOS_REAIS = true</code> seja configurado em <code>backend/config/loja.php</code>.
                </div>
                <?php endif; ?>

                <div id="etiquetaAlerta" class="alert d-none mb-3 py-2 small"></div>

                <?php if ($meEstado === 'disponivel'): ?>
                <!-- Etiqueta disponível -->
                <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
                    <a href="<?= htmlspecialchars($labelUrl) ?>" target="_blank" class="btn btn-success">
                        <i class="fas fa-file-pdf me-1"></i> Baixar / Imprimir Etiqueta
                    </a>
                    <button class="btn btn-outline-secondary btn-sm" onclick="etiquetaAcao('tracking')">
                        <i class="fas fa-sync me-1"></i> Atualizar rastreio
                    </button>
                </div>
                <?php if ($tracking['tracking_code']): ?>
                <div class="small text-muted">
                    <i class="fas fa-barcode me-1"></i>
                    Código de rastreio: <code><?= htmlspecialchars($tracking['tracking_code']) ?></code>
                </div>
                <?php endif; ?>

                <?php elseif ($meEstado === 'em_processamento'): ?>
                <!-- Em processamento: passos 2, 3 e 4 -->
                <p class="small text-muted mb-3">
                    Envio no carrinho ME: <code class="text-primary"><?= htmlspecialchars($meOrderId) ?></code><br>
                    Execute os passos abaixo na ordem. O passo <strong>Pagar</strong> debita saldo da carteira Melhor Envio.
                </p>
                <div class="d-flex flex-wrap gap-2">
                    <button class="btn btn-warning text-dark"
                            <?= $dadosReais ? '' : 'disabled title="Bloqueado: dados fictícios"' ?>
                            onclick="abrirModalCheckout()">
                        <i class="fas fa-wallet me-1"></i> 2. Pagar etiqueta
                    </button>
                    <button class="btn btn-outline-primary" onclick="etiquetaAcao('generate')">
                        <i class="fas fa-cog me-1"></i> 3. Gerar etiqueta
                    </button>
                    <button class="btn btn-primary" onclick="etiquetaAcao('print')">
                        <i class="fas fa-print me-1"></i> 4. Imprimir / Baixar
                        <span class="spinner-border spinner-border-sm d-none ms-1" id="spinPrint"></span>
                    </button>
                    <button class="btn btn-outline-secondary btn-sm" onclick="etiquetaAcao('tracking')">
                        <i class="fas fa-sync me-1"></i> Rastreio
                    </button>
                </div>

                <?php else: ?>
                <!-- Sem carrinho: passo 1 -->
                <?php if (!$tracking['chosen_service_id']): ?>
                <div class="alert alert-danger py-2 small">
                    <i class="fas fa-exclamation-circle me-1"></i>
                    O cliente não escolheu um serviço de frete ME neste pedido — não é possível emitir etiqueta.
                </div>
                <?php else: ?>
                <p class="small text-muted mb-3">
                    Serviço escolhido: <strong><?= htmlspecialchars($tracking['chosen_carrier'] . ' — ' . $tracking['chosen_service']) ?></strong>
                    <?php if ($fretePreco !== null): ?>
                        · R$ <?= number_format($fretePreco, 2, ',', '.') ?>
                    <?php endif; ?>
                </p>
                <button class="btn btn-outline-primary" onclick="etiquetaAcao('cart')">
                    <i class="fas fa-cart-plus me-1"></i> 1. Adicionar ao carrinho ME
                </button>
                <?php endif; ?>
                <?php endif; ?>

            </div><!-- /card-body -->
        </div><!-- /card -->
    </div>

    <!-- Modal de confirmação de checkout (debita saldo) -->
    <div class="modal fade" id="checkoutMeModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0">
                    <h5 class="modal-title text-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>Confirmar pagamento de etiqueta
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body pt-0">
                    <p class="mb-2">Esta ação irá <strong>debitar saldo da carteira Melhor Envio</strong>:</p>
                    <ul class="small mb-3">
                        <li>Transportadora: <strong><?= htmlspecialchars(($tracking['chosen_carrier'] ?? '') . ' — ' . ($tracking['chosen_service'] ?? '')) ?></strong></li>
                        <li>Valor do frete: <strong><?= $fretePreco !== null ? 'R$ ' . number_format($fretePreco, 2, ',', '.') : 'ver carteira ME' ?></strong></li>
                        <li>Pedido: <strong>#<?= htmlspecialchars($numeroPedido) ?></strong></li>
                    </ul>
                    <p class="text-muted small mb-0">Após confirmar, o saldo não pode ser estornado automaticamente.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-warning text-dark fw-semibold" onclick="confirmarCheckout()">
                        <i class="fas fa-wallet me-1"></i> Confirmar e pagar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
    const PEDIDO_ID_ETIQUETA = <?= $id ?>;
    let _checkoutMeModal = null;

    function etiquetaAcao(action) {
        const alertEl = document.getElementById('etiquetaAlerta');
        if (alertEl) { alertEl.className = 'alert d-none mb-3 py-2 small'; alertEl.textContent = ''; }

        // Spinner para print (demora por causa do delay de geração assíncrona)
        const spin = document.getElementById('spinPrint');
        if (action === 'print' && spin) spin.classList.remove('d-none');

        fetch('/backend/admin/etiqueta-action.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ action, pedido_id: PEDIDO_ID_ETIQUETA }),
        })
        .then(r => r.json().then(data => ({ ok: r.ok, data })))
        .then(({ ok, data }) => {
            if (spin) spin.classList.add('d-none');
            if (!ok) {
                mostrarAlertaEtiqueta(data.erro || 'Erro desconhecido.', 'danger');
                return;
            }
            if (action === 'print' && data.label_url) {
                mostrarAlertaEtiqueta('Etiqueta gerada! Atualizando a página...', 'success');
                setTimeout(() => location.reload(), 1500);
                return;
            }
            if (action === 'cart') {
                mostrarAlertaEtiqueta(data.mensagem || 'Adicionado ao carrinho.', 'success');
                setTimeout(() => location.reload(), 1200);
                return;
            }
            mostrarAlertaEtiqueta(data.mensagem || 'Ação concluída.', 'success');
        })
        .catch(() => {
            if (spin) spin.classList.add('d-none');
            mostrarAlertaEtiqueta('Erro de conexão. Tente novamente.', 'danger');
        });
    }

    function abrirModalCheckout() {
        if (!_checkoutMeModal) {
            _checkoutMeModal = new bootstrap.Modal(document.getElementById('checkoutMeModal'));
        }
        _checkoutMeModal.show();
    }

    function confirmarCheckout() {
        _checkoutMeModal?.hide();
        etiquetaAcao('checkout');
    }

    function mostrarAlertaEtiqueta(msg, tipo) {
        const el = document.getElementById('etiquetaAlerta');
        if (!el) return;
        el.className = `alert alert-${tipo} mb-3 py-2 small`;
        el.textContent = msg;
    }
    </script>
    <?php endif; ?>

    <!-- Itens do pedido -->
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold">Itens do Pedido</div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Produto</th>
                            <th>Preço Unitário</th>
                            <th>Quantidade</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($itens as $item): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <?php if ($item['imagem']): ?>
                                        <img src="/<?= htmlspecialchars($item['imagem']) ?>" width="40" height="40"
                                             style="object-fit:cover;border-radius:6px;">
                                    <?php endif; ?>
                                    <div>
                                        <?= htmlspecialchars($item['produto_nome']) ?>
                                        <div class="small text-muted" style="font-family:monospace;font-size:.75rem;">
                                            <?= !empty($item['codigo_interno']) ? htmlspecialchars($item['codigo_interno']) : '—' ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>R$ <?= number_format($item['preco_unitario'], 2, ',', '.') ?></td>
                            <td><?= $item['quantidade'] ?></td>
                            <td>R$ <?= number_format($item['preco_unitario'] * $item['quantidade'], 2, ',', '.') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light">
                        <?php if ($tracking && isset($tracking['shipping_price']) && (float) $tracking['shipping_price'] > 0): ?>
                        <tr class="text-muted small">
                            <td colspan="3" class="text-end">
                                <i class="fas fa-truck me-1"></i>Frete
                                <?php
                                $freteNomeAdmin = trim(($tracking['chosen_carrier'] ?? '') . ' ' . ($tracking['chosen_service'] ?? ''));
                                if ($freteNomeAdmin): ?>
                                    (<?= htmlspecialchars($freteNomeAdmin) ?>)
                                <?php endif; ?>
                            </td>
                            <td>R$ <?= number_format((float) $tracking['shipping_price'], 2, ',', '.') ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <td colspan="3" class="text-end fw-bold">Total</td>
                            <td class="fw-bold">R$ <?= number_format($pedido['total'], 2, ',', '.') ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>

<?php layout_foot(); ?>
