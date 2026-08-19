<?php
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/../config/database.php';

$pdo = getDB();

$labels = ['Em Preparação', 'Embalado', 'Enviado', 'Código de Rastreio'];
$labelColors = ['secondary', 'primary', 'warning', 'success'];

// Busca todos os registros com dados do pedido
$rows = $pdo->query("
    SELECT ot.order_id, ot.status, ot.tracking_code, ot.carrier, ot.notes, ot.updated_at,
           ot.chosen_carrier, ot.chosen_service, ot.shipping_price, ot.shipping_deadline, ot.destination_cep,
           p.nome_comprador, p.email_comprador, p.status AS pedido_status, p.numero_pedido
    FROM order_tracking ot
    LEFT JOIN pedidos p ON p.id = CAST(ot.order_id AS INTEGER)
    ORDER BY ot.updated_at DESC
")->fetchAll();

// Busca itens de todos os pedidos listados para exibir no modal
$itensMap = [];
if (!empty($rows)) {
    $orderIds    = array_map('intval', array_column($rows, 'order_id'));
    $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
    $stmtItens   = $pdo->prepare("
        SELECT ip.pedido_id, ip.quantidade, pr.nome AS produto_nome, pr.codigo_interno
        FROM itens_pedido ip
        JOIN produtos pr ON pr.id = ip.produto_id
        WHERE ip.pedido_id IN ($placeholders)
        ORDER BY ip.pedido_id, ip.id
    ");
    $stmtItens->execute($orderIds);
    foreach ($stmtItens->fetchAll(PDO::FETCH_ASSOC) as $item) {
        $itensMap[(string) $item['pedido_id']][] = $item;
    }
}

layout_head('Rastreamento de Pedidos');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <p class="text-muted mb-0">Gerencie o status de envio dos pedidos.</p>
    <button class="btn btn-primary btn-sm" onclick="abrirModal(null)">
        <i class="fas fa-plus me-1"></i> Novo Registro
    </button>
</div>

<?php if (empty($rows)): ?>
<div class="alert alert-info">
    <i class="fas fa-info-circle me-2"></i>Nenhum registro de rastreamento. Clique em "Novo Registro" para começar.
</div>
<?php else: ?>
<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Pedido</th>
                    <th>Comprador</th>
                    <th>Status</th>
                    <th>Envio escolhido</th>
                    <th>Transportadora efetiva</th>
                    <th>Código</th>
                    <th>Atualizado em</th>
                    <th class="text-center">Ações</th>
                </tr>
            </thead>
            <tbody id="trackingTableBody">
            <?php
            $pedidoStatusFalha = [
                'recusado'   => ['label' => 'Recusado',   'cor' => 'danger'],
                'cancelado'  => ['label' => 'Cancelado',  'cor' => 'danger'],
                'reembolsado'=> ['label' => 'Reembolsado','cor' => 'secondary'],
                'contestado' => ['label' => 'Contestado', 'cor' => 'warning'],
            ];
            foreach ($rows as $r):
                $s          = (int) $r['status'];
                $lbl        = $labels[$s] ?? '?';
                $clr        = $labelColors[$s] ?? 'secondary';
                $upd        = $r['updated_at'] ? date('d/m/Y H:i', strtotime($r['updated_at'])) : '—';
                $pStatus    = $r['pedido_status'] ?? '';
                $isFalha    = isset($pedidoStatusFalha[$pStatus]);
                $falhaInfo  = $isFalha ? $pedidoStatusFalha[$pStatus] : null;
            ?>
            <tr data-orderid="<?= htmlspecialchars($r['order_id']) ?>"<?= $isFalha ? ' class="table-danger opacity-75"' : '' ?>>
                <td>
                    <strong>#<?= htmlspecialchars($r['numero_pedido'] ?? $r['order_id']) ?></strong>
                    <div class="text-muted" style="font-size:.7rem;">ID interno: <?= htmlspecialchars($r['order_id']) ?></div>
                </td>
                <td>
                    <div class="small"><?= htmlspecialchars($r['nome_comprador'] ?? '—') ?></div>
                    <div class="text-muted" style="font-size:.75rem;"><?= htmlspecialchars($r['email_comprador'] ?? '') ?></div>
                </td>
                <td>
                    <?php if ($isFalha): ?>
                        <span class="badge bg-<?= $falhaInfo['cor'] ?> td-status">
                            <i class="fas fa-times-circle me-1"></i><?= $falhaInfo['label'] ?>
                        </span>
                    <?php else: ?>
                        <span class="badge bg-<?= $clr ?> td-status"><?= htmlspecialchars($lbl) ?></span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($r['chosen_carrier']): ?>
                        <span class="badge-envio">
                            <span class="badge-envio-carrier"><?= htmlspecialchars($r['chosen_carrier']) ?></span>
                            <span class="badge-envio-service"><?= htmlspecialchars($r['chosen_service'] ?? '') ?></span>
                        </span>
                    <?php else: ?>
                        <span class="text-muted small">—</span>
                    <?php endif; ?>
                </td>
                <td class="td-carrier"><?= htmlspecialchars($r['carrier'] ?? '—') ?></td>
                <td class="td-code" style="font-family:monospace;"><?= htmlspecialchars($r['tracking_code'] ?? '—') ?></td>
                <td class="td-updated small text-muted"><?= $upd ?></td>
                <td class="text-center">
                    <button class="btn btn-outline-primary btn-sm"
                            onclick='abrirModal(<?= json_encode([
                                "order_id"        => $r["order_id"],
                                "numero_pedido"   => $r["numero_pedido"] ?? $r["order_id"],
                                "status"          => $s,
                                "tracking_code"   => $r["tracking_code"] ?? "",
                                "carrier"         => $r["carrier"] ?? "",
                                "notes"           => $r["notes"] ?? "",
                                "itens"           => $itensMap[$r["order_id"]] ?? [],
                                "chosen_carrier"  => $r["chosen_carrier"]  ?? "",
                                "chosen_service"  => $r["chosen_service"]  ?? "",
                                "shipping_price"  => $r["shipping_price"]  ?? null,
                                "shipping_deadline" => $r["shipping_deadline"] ?? null,
                                "destination_cep" => $r["destination_cep"] ?? "",
                            ]) ?>)'>
                        <i class="fas fa-edit"></i>
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Modal Editar / Novo -->
<div class="modal fade" id="trackingModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="trackingModalTitle">Rastreamento</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="modalAlert" class="alert d-none mb-3"></div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Pedido #</label>
                    <input type="text" id="mOrderId" class="form-control" placeholder="Ex: 42">
                </div>

                <!-- Escolha do cliente (read-only / informativo) -->
                <div id="mEscolhaContainer" class="mb-3" style="display:none;">
                    <div class="entrega-bloco-escolha p-2 rounded small">
                        <p class="mb-1 fw-semibold text-muted" style="font-size:.7rem;text-transform:uppercase;letter-spacing:.04em;">
                            <i class="fas fa-user me-1"></i>Escolha do cliente
                        </p>
                        <div id="mEscolhaInfo"></div>
                    </div>
                </div>

                <div class="mb-3" id="mItensContainer">
                    <label class="form-label fw-semibold text-muted small">Itens do Pedido</label>
                    <div id="mItens" class="border rounded p-2 bg-light small" style="max-height:140px;overflow-y:auto;"></div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Status</label>
                    <select id="mStatus" class="form-select">
                        <option value="0">0 — Em Preparação</option>
                        <option value="1">1 — Embalado</option>
                        <option value="2">2 — Enviado</option>
                        <option value="3">3 — Código de Rastreio</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Código de Rastreio</label>
                    <input type="text" id="mCode" class="form-control" placeholder="Ex: AA123456789BR" style="font-family:monospace; text-transform:uppercase;">
                    <div class="form-text">Formato Correios: 2 letras + 9 dígitos + BR</div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Transportadora</label>
                    <input type="text" id="mCarrier" class="form-control" placeholder="Ex: Correios, Jadlog, Total Express">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Observações internas</label>
                    <textarea id="mNotes" class="form-control" rows="2" placeholder="Anotações para controle interno (não visível ao cliente)"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnSalvar" onclick="salvar()">
                    <i class="fas fa-save me-1"></i> Salvar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Toast de feedback -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:1100">
    <div id="feedbackToast" class="toast align-items-center text-white border-0" role="alert" aria-live="assertive">
        <div class="d-flex">
            <div class="toast-body" id="toastMsg"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<script>
const LABELS       = ['Em Preparação', 'Embalado', 'Enviado', 'Código de Rastreio'];
const LABEL_COLORS = ['secondary', 'primary', 'warning', 'success'];
let   modalBS      = null;
let   isEditing    = false;

const escHtml = s => String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');

function abrirModal(data) {
    const title  = document.getElementById('trackingModalTitle');
    const oId    = document.getElementById('mOrderId');
    const alert  = document.getElementById('modalAlert');

    alert.className = 'alert d-none mb-3';
    alert.textContent = '';

    const itensEl        = document.getElementById('mItens');
    const itensContainer = document.getElementById('mItensContainer');

    const escolhaContainer = document.getElementById('mEscolhaContainer');
    const escolhaInfo      = document.getElementById('mEscolhaInfo');

    if (data) {
        isEditing         = true;
        title.textContent = 'Editar Rastreamento — Pedido #' + (data.numero_pedido || data.order_id);
        oId.value         = data.order_id;
        oId.readOnly      = true;
        document.getElementById('mStatus').value  = data.status;
        document.getElementById('mCode').value    = data.tracking_code || '';
        // Pré-preenche carrier com a escolha do cliente se estiver vazio
        document.getElementById('mCarrier').value = data.carrier || data.chosen_carrier || '';
        document.getElementById('mNotes').value   = data.notes || '';

        // Exibe escolha do cliente
        if (data.chosen_carrier) {
            const preco = data.shipping_price != null
                ? (parseFloat(data.shipping_price) === 0
                    ? '<span class="badge bg-success">Grátis</span>'
                    : `R$ ${parseFloat(data.shipping_price).toLocaleString('pt-BR',{minimumFractionDigits:2})}`)
                : '';
            const prazo = data.shipping_deadline ? `· ${data.shipping_deadline} dias úteis` : '';
            const cep   = data.destination_cep   ? `· CEP ${data.destination_cep}` : '';
            escolhaInfo.innerHTML = `
                <strong>${escHtml(data.chosen_carrier)}</strong> — ${escHtml(data.chosen_service || '')}
                ${preco ? ' · ' + preco : ''} ${prazo} ${cep}`;
            escolhaContainer.style.display = '';
        } else {
            escolhaContainer.style.display = 'none';
        }

        if (data.itens && data.itens.length) {
            itensEl.innerHTML = data.itens.map(item => {
                const cod  = item.codigo_interno || '—';
                const nome = item.produto_nome.replace(/</g, '&lt;');
                return `<div class="d-flex justify-content-between py-1 border-bottom">
                    <span><code class="text-muted">${cod}</code>&nbsp;${nome}</span>
                    <span class="fw-bold ms-2">×${item.quantidade}</span>
                </div>`;
            }).join('');
            itensContainer.style.display = '';
        } else {
            itensContainer.style.display = 'none';
        }
    } else {
        isEditing         = false;
        title.textContent = 'Novo Registro de Rastreamento';
        oId.value         = '';
        oId.readOnly      = false;
        document.getElementById('mStatus').value  = '0';
        document.getElementById('mCode').value    = '';
        document.getElementById('mCarrier').value = '';
        document.getElementById('mNotes').value   = '';
        itensContainer.style.display   = 'none';
        escolhaContainer.style.display = 'none';
        itensEl.innerHTML              = '';
        escolhaInfo.innerHTML          = '';
    }

    if (!modalBS) modalBS = new bootstrap.Modal(document.getElementById('trackingModal'));
    modalBS.show();
}

async function salvar() {
    const btn     = document.getElementById('btnSalvar');
    const alertEl = document.getElementById('modalAlert');
    const orderId = document.getElementById('mOrderId').value.trim();

    if (!orderId) {
        mostrarAlertaModal('Informe o número do pedido.', 'danger');
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Salvando...';

    try {
        const resp = await fetch('/backend/api/tracking.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                order_id:      orderId,
                status:        parseInt(document.getElementById('mStatus').value),
                tracking_code: document.getElementById('mCode').value.trim().toUpperCase() || null,
                carrier:       document.getElementById('mCarrier').value.trim() || null,
                notes:         document.getElementById('mNotes').value.trim() || null,
            }),
        });
        const json = await resp.json();

        if (!resp.ok) {
            mostrarAlertaModal(json.erro || 'Erro ao salvar.', 'danger');
            return;
        }

        modalBS.hide();
        atualizarLinha(json);
        mostrarToast('Rastreamento salvo com sucesso!', 'success');

    } catch (_) {
        mostrarAlertaModal('Erro de conexão. Tente novamente.', 'danger');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save me-1"></i> Salvar';
    }
}

function atualizarLinha(json) {
    const tbody  = document.getElementById('trackingTableBody');
    const s      = json.status;
    const lbl    = LABELS[s] || '?';
    const clr    = LABEL_COLORS[s] || 'secondary';
    const now    = new Date().toLocaleString('pt-BR', { day:'2-digit', month:'2-digit', year:'numeric', hour:'2-digit', minute:'2-digit' });
    const code   = document.getElementById('mCode').value.trim().toUpperCase() || '—';
    const carrier= document.getElementById('mCarrier').value.trim() || '—';

    let row = tbody.querySelector(`tr[data-orderid="${json.order_id}"]`);
    if (row) {
        row.querySelector('.td-status').className   = `badge bg-${clr} td-status`;
        row.querySelector('.td-status').textContent = lbl;
        row.querySelector('.td-carrier').textContent = carrier;
        row.querySelector('.td-code').textContent    = code;
        row.querySelector('.td-updated').textContent = now;

        // Atualiza o onclick do botão de edição
        const editBtn = row.querySelector('button');
        editBtn.setAttribute('onclick', `abrirModal(${JSON.stringify({
            order_id: json.order_id,
            status: s,
            tracking_code: code === '—' ? '' : code,
            carrier: carrier === '—' ? '' : carrier,
            notes: document.getElementById('mNotes').value.trim(),
        })})`);
    } else {
        // Nova linha no topo
        const tr = document.createElement('tr');
        tr.dataset.orderid = json.order_id;
        tr.innerHTML = `
            <td><strong>#${json.order_id}</strong></td>
            <td><div class="small">—</div></td>
            <td><span class="badge bg-${clr} td-status">${lbl}</span></td>
            <td class="td-carrier">${carrier}</td>
            <td class="td-code" style="font-family:monospace;">${code}</td>
            <td class="td-updated small text-muted">${now}</td>
            <td class="text-center">
                <button class="btn btn-outline-primary btn-sm"
                        onclick='abrirModal(${JSON.stringify({
                            order_id: json.order_id, status: s,
                            tracking_code: code === '—' ? '' : code,
                            carrier: carrier === '—' ? '' : carrier, notes: ''
                        })})'>
                    <i class="fas fa-edit"></i>
                </button>
            </td>`;
        tbody.prepend(tr);

        // Remove aviso "nenhum registro" se existir
        document.querySelector('.alert-info')?.remove();
    }
}

function mostrarAlertaModal(msg, tipo) {
    const el = document.getElementById('modalAlert');
    el.className = `alert alert-${tipo} mb-3`;
    el.textContent = msg;
}

function mostrarToast(msg, tipo) {
    const toast = document.getElementById('feedbackToast');
    const body  = document.getElementById('toastMsg');
    toast.className = `toast align-items-center text-white border-0 bg-${tipo}`;
    body.textContent = msg;
    new bootstrap.Toast(toast, { delay: 3500 }).show();
}

// Uppercase automático no campo de código
document.getElementById('mCode').addEventListener('input', function () {
    this.value = this.value.toUpperCase();
});
</script>

<?php layout_foot(); ?>
