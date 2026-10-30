<?php
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/_layout.php';

$pdo = getDB();

// ── Filtro de período ──────────────────────────────────────────────
$de   = trim($_GET['de']  ?? '');
$ate  = trim($_GET['ate'] ?? '');
$mes  = trim($_GET['mes'] ?? date('Y-m'));
$tudo = array_key_exists('tudo', $_GET);

$nomeMeses = ['','Janeiro','Fevereiro','Março','Abril','Maio','Junho',
              'Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];

$inicio       = null;
$fim          = null;
$periodoLabel = '';
$modoAtivo    = 'mes'; // mes | range | tudo

if ($tudo) {
    $modoAtivo    = 'tudo';
    $periodoLabel = 'Todo o período';
} elseif ($de !== '' && $ate !== '') {
    $deObj  = DateTime::createFromFormat('Y-m-d', $de);
    $ateObj = DateTime::createFromFormat('Y-m-d', $ate);
    if ($deObj && $ateObj && $deObj <= $ateObj) {
        $modoAtivo    = 'range';
        $inicio       = $de  . ' 00:00:00';
        $fim          = $ate . ' 23:59:59';
        $periodoLabel = $deObj->format('d/m/Y') . ' – ' . $ateObj->format('d/m/Y');
    } else {
        // datas inválidas → cai no mês atual
        $mes = date('Y-m');
    }
}

if ($modoAtivo === 'mes') {
    $mesObj = DateTime::createFromFormat('Y-m', $mes);
    if (!$mesObj) { $mes = date('Y-m'); $mesObj = new DateTime($mes . '-01'); }
    $inicio       = $mesObj->format('Y-m-01 00:00:00');
    $fim          = $mesObj->format('Y-m-t 23:59:59');
    $periodoLabel = $nomeMeses[(int)$mesObj->format('m')] . '/' . $mesObj->format('Y');
}

// ── WHERE com prepared statements ─────────────────────────────────
function periodoWhere(?string $inicio, ?string $fim, string $extra = ''): array {
    $conds  = [];
    $params = [];
    if ($inicio !== null && $fim !== null) {
        $conds[]  = 'criado_em >= ?';
        $params[] = $inicio;
        $conds[]  = 'criado_em <= ?';
        $params[] = $fim;
    }
    if ($extra !== '') $conds[] = $extra;
    return [$conds ? 'WHERE ' . implode(' AND ', $conds) : '', $params];
}

// ── Métricas (todas respeitem o período) ──────────────────────────
[$w, $p] = periodoWhere($inicio, $fim);
$stmt = $pdo->prepare("SELECT COUNT(*) FROM pedidos $w");
$stmt->execute($p);
$totalPedidos = (int) $stmt->fetchColumn();

[$w, $p] = periodoWhere($inicio, $fim, "status = 'aprovado'");
$stmt = $pdo->prepare("SELECT COALESCE(SUM(total),0) FROM pedidos $w");
$stmt->execute($p);
$receitaTotal = (float) $stmt->fetchColumn();

[$w, $p] = periodoWhere($inicio, $fim, "status = 'aprovado'");
$stmt = $pdo->prepare("SELECT COUNT(*) FROM pedidos $w");
$stmt->execute($p);
$pedidosAprovados = (int) $stmt->fetchColumn();

[$w, $p] = periodoWhere($inicio, $fim, "status = 'pendente'");
$stmt = $pdo->prepare("SELECT COUNT(*) FROM pedidos $w");
$stmt->execute($p);
$pedidosPendentes = (int) $stmt->fetchColumn();

[$w, $p] = periodoWhere($inicio, $fim, "status IN ('recusado','cancelado','contestado')");
$stmt = $pdo->prepare("SELECT COUNT(*) FROM pedidos $w");
$stmt->execute($p);
$pedidosRecusados = (int) $stmt->fetchColumn();

$ticketMedio = $pedidosAprovados > 0 ? $receitaTotal / $pedidosAprovados : 0.0;

// ── Pedidos do período (tabela) ────────────────────────────────────
[$w, $p] = periodoWhere($inicio, $fim);
$stmt = $pdo->prepare("SELECT id, nome_comprador, total, status, criado_em FROM pedidos $w ORDER BY id DESC LIMIT 20");
$stmt->execute($p);
$ultimosPedidos = $stmt->fetchAll();

// ── Estoque baixo (independente do período) ────────────────────────
$estoqueBaixo = $pdo->query("SELECT id, nome, estoque FROM produtos WHERE ativo = 1 AND estoque < 5 ORDER BY estoque ASC")->fetchAll();

$statusBadge = [
    'aprovado'         => 'success',
    'pendente'         => 'warning',
    'em_analise'       => 'info',
    'recusado'         => 'danger',
    'cancelado'        => 'secondary',
    'reembolsado'      => 'secondary',
    'contestado'       => 'danger',
    'em_processamento' => 'purple',
];
$statusLabel = [
    'aprovado'         => 'Aprovado',
    'pendente'         => 'Pendente',
    'em_analise'       => 'Em Análise',
    'recusado'         => 'Recusado',
    'cancelado'        => 'Cancelado',
    'reembolsado'      => 'Reembolsado',
    'contestado'       => 'Contestado',
    'em_processamento' => 'Em Processamento',
];

$extraHead = <<<'CSS_JS'
<style>
  .dash-filter-bar { background: #fff; border: 1px solid var(--adm-line); border-radius: 10px; padding: .875rem 1rem; margin-bottom: 1.25rem; }
  .dash-periodo-label { font-size: .8rem; color: var(--adm-brand); font-weight: 600; margin-top: .5rem; }
  .dash-cards-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: .625rem; }
  .dash-stat-icon { width: 44px; height: 44px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; flex-shrink: 0; }
  .dash-empty { text-align: center; padding: 2.5rem 1rem; color: #6c757d; }
  .dash-empty i { font-size: 2rem; display: block; margin-bottom: .5rem; opacity: .4; }
</style>
<script>
(function () {
  var CARDS = [
    { id: 'total-pedidos', label: 'Total de Pedidos' },
    { id: 'receita',       label: 'Receita Aprovada' },
    { id: 'ticket-medio',  label: 'Ticket Médio' },
    { id: 'pendentes',     label: 'Pedidos Pendentes' },
    { id: 'aprovados',     label: 'Pedidos Aprovados' },
    { id: 'recusados',     label: 'Recusados / Cancelados' },
  ];
  function loadPrefs() {
    try {
      var s = JSON.parse(localStorage.getItem('psp_dashboard_cards') || 'null');
      if (s && typeof s === 'object') return s;
    } catch (e) {}
    var d = {};
    CARDS.forEach(function (c) { d[c.id] = true; });
    return d;
  }
  function savePrefs(p) { localStorage.setItem('psp_dashboard_cards', JSON.stringify(p)); }
  function applyPrefs(prefs) {
    CARDS.forEach(function (c) {
      var wrap = document.getElementById('cw-' + c.id);
      var chk  = document.getElementById('chk-' + c.id);
      if (!wrap) return;
      var on = prefs[c.id] !== false;
      wrap.style.display = on ? '' : 'none';
      if (chk) chk.checked = on;
    });
  }
  document.addEventListener('DOMContentLoaded', function () {
    var menu = document.getElementById('metricsMenu');
    if (menu) {
      CARDS.forEach(function (c) {
        var div = document.createElement('div');
        div.className = 'form-check mb-1';
        div.innerHTML = '<input class="form-check-input" type="checkbox" id="chk-' + c.id + '">'
          + '<label class="form-check-label small" for="chk-' + c.id + '">' + c.label + '</label>';
        menu.appendChild(div);
      });
    }
    var prefs = loadPrefs();
    applyPrefs(prefs);
    CARDS.forEach(function (c) {
      var chk = document.getElementById('chk-' + c.id);
      if (!chk) return;
      chk.addEventListener('change', function () {
        prefs[c.id] = this.checked;
        savePrefs(prefs);
        applyPrefs(prefs);
      });
    });
  });
})();
</script>
CSS_JS;

layout_head('Dashboard', $extraHead);
?>

<!-- Barra de filtros -->
<div class="dash-filter-bar">
    <form method="GET" class="d-flex gap-2 align-items-end flex-wrap">
        <div>
            <label class="form-label small mb-1 fw-semibold">Mês</label>
            <input type="month" name="mes" class="form-control form-control-sm"
                   value="<?= htmlspecialchars($modoAtivo === 'range' ? date('Y-m') : $mes) ?>">
        </div>
        <div>
            <label class="form-label small mb-1 fw-semibold">De</label>
            <input type="date" name="de" class="form-control form-control-sm"
                   value="<?= htmlspecialchars($de) ?>">
        </div>
        <div>
            <label class="form-label small mb-1 fw-semibold">Até</label>
            <input type="date" name="ate" class="form-control form-control-sm"
                   value="<?= htmlspecialchars($ate) ?>">
        </div>
        <div class="d-flex gap-1">
            <button type="submit" class="btn btn-sm btn-primary">
                <i class="fas fa-filter me-1"></i> Filtrar
            </button>
            <a href="dashboard.php" class="btn btn-sm btn-outline-secondary">Limpar</a>
            <a href="dashboard.php?tudo" class="btn btn-sm btn-outline-secondary <?= $modoAtivo === 'tudo' ? 'active' : '' ?>">
                Todo o período
            </a>
        </div>
    </form>
    <div class="dash-periodo-label">
        <i class="fas fa-calendar-day me-1"></i>
        Exibindo: <?= htmlspecialchars($periodoLabel) ?>
        <span class="fw-normal text-muted ms-1">
            (<?= $totalPedidos ?> pedido<?= $totalPedidos !== 1 ? 's' : '' ?>)
        </span>
    </div>
</div>

<!-- Header dos cards + seletor de métricas -->
<div class="dash-cards-header">
    <span class="text-muted small fw-semibold" style="text-transform:uppercase;letter-spacing:.05em;">Resumo do Período</span>
    <div class="dropdown">
        <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="fas fa-sliders me-1"></i> Métricas
        </button>
        <div class="dropdown-menu dropdown-menu-end p-3" id="metricsMenu"
             style="min-width:190px;" onclick="event.stopPropagation()">
        </div>
    </div>
</div>

<!-- Cards de métricas -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-4" id="cw-total-pedidos">
        <div class="card stat-card shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="dash-stat-icon bg-primary bg-opacity-10 text-primary"><i class="fas fa-shopping-cart"></i></div>
                <div>
                    <div class="fw-bold fs-4"><?= $totalPedidos ?></div>
                    <div class="text-muted small">Total de Pedidos</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-4" id="cw-receita">
        <div class="card stat-card shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="dash-stat-icon bg-success bg-opacity-10 text-success"><i class="fas fa-dollar-sign"></i></div>
                <div>
                    <div class="fw-bold fs-4">R$&nbsp;<?= number_format($receitaTotal, 2, ',', '.') ?></div>
                    <div class="text-muted small">Receita Aprovada</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-4" id="cw-ticket-medio">
        <div class="card stat-card shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="dash-stat-icon bg-info bg-opacity-10 text-info"><i class="fas fa-chart-line"></i></div>
                <div>
                    <div class="fw-bold fs-4">R$&nbsp;<?= number_format($ticketMedio, 2, ',', '.') ?></div>
                    <div class="text-muted small">Ticket Médio</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-4" id="cw-pendentes">
        <div class="card stat-card shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="dash-stat-icon bg-warning bg-opacity-10 text-warning"><i class="fas fa-clock"></i></div>
                <div>
                    <div class="fw-bold fs-4"><?= $pedidosPendentes ?></div>
                    <div class="text-muted small">Pedidos Pendentes</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-4" id="cw-aprovados">
        <div class="card stat-card shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="dash-stat-icon bg-success bg-opacity-10 text-success"><i class="fas fa-check-circle"></i></div>
                <div>
                    <div class="fw-bold fs-4"><?= $pedidosAprovados ?></div>
                    <div class="text-muted small">Pedidos Aprovados</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-4" id="cw-recusados">
        <div class="card stat-card shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="dash-stat-icon bg-danger bg-opacity-10 text-danger"><i class="fas fa-times-circle"></i></div>
                <div>
                    <div class="fw-bold fs-4"><?= $pedidosRecusados ?></div>
                    <div class="text-muted small">Recusados / Cancelados</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Pedidos do período + Estoque baixo -->
<div class="row g-3">
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
                <span>Pedidos do Período</span>
                <a href="pedidos.php" class="btn btn-sm btn-link text-decoration-none p-0 small">Ver todos</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Comprador</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Data</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($ultimosPedidos)): ?>
                        <tr>
                            <td colspan="6">
                                <div class="dash-empty">
                                    <i class="fas fa-inbox"></i>
                                    Nenhuma transação neste período.
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                    <?php foreach ($ultimosPedidos as $p): ?>
                        <tr>
                            <td class="text-muted small"><?= $p['id'] ?></td>
                            <td><?= htmlspecialchars($p['nome_comprador']) ?></td>
                            <td>R$&nbsp;<?= number_format($p['total'], 2, ',', '.') ?></td>
                            <td><span class="badge bg-<?= $statusBadge[$p['status']] ?? 'secondary' ?>"><?= $statusLabel[$p['status']] ?? $p['status'] ?></span></td>
                            <td class="text-muted small"><?= date('d/m/Y H:i', strtotime($p['criado_em'])) ?></td>
                            <td><a href="pedido-detalhe.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-secondary">Ver</a></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold text-warning">
                <i class="fas fa-exclamation-triangle me-1"></i> Estoque Baixo
            </div>
            <div class="table-responsive">
                <table class="table mb-0 align-middle">
                    <thead class="table-light">
                        <tr><th>Produto</th><th>Estoque</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($estoqueBaixo as $p): ?>
                        <tr>
                            <td><?= htmlspecialchars($p['nome']) ?></td>
                            <td><span class="badge bg-<?= $p['estoque'] == 0 ? 'danger' : 'warning' ?> text-dark"><?= $p['estoque'] ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($estoqueBaixo)): ?>
                        <tr><td colspan="2" class="text-center text-muted py-3">Tudo em dia!</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php layout_foot(); ?>
