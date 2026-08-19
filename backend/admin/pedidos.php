<?php
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/_layout.php';

$pdo    = getDB();
$filtro = $_GET['status'] ?? '';
$busca  = trim($_GET['busca'] ?? '');

$where  = [];
$params = [];

if ($filtro) {
    $where[]  = "status = ?";
    $params[] = $filtro;
}
if ($busca) {
    $where[]  = "(nome_comprador LIKE ? OR email_comprador LIKE ?)";
    $params[] = "%$busca%";
    $params[] = "%$busca%";
}

$sql = "
    SELECT p.*,
           ot.chosen_carrier, ot.chosen_service, ot.tracking_code AS ot_tracking_code
    FROM pedidos p
    LEFT JOIN order_tracking ot ON ot.order_id = CAST(p.id AS TEXT)
";
if ($where) $sql .= " WHERE " . implode(" AND ", $where);
$sql .= " ORDER BY p.id DESC";

$stmt    = $pdo->prepare($sql);
$stmt->execute($params);
$pedidos = $stmt->fetchAll();

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

$statusOpcoes = ['', 'aprovado', 'pendente', 'em_analise', 'em_processamento', 'enviado', 'recusado', 'cancelado'];
$statusLabel  = [
    'aprovado'         => 'Aprovado',
    'pendente'         => 'Pendente',
    'em_analise'       => 'Em Análise',
    'recusado'         => 'Recusado',
    'cancelado'        => 'Cancelado',
    'reembolsado'      => 'Reembolsado',
    'contestado'       => 'Contestado',
    'em_processamento' => 'Em Processamento',
    'enviado'          => 'Enviado',
];

layout_head('Pedidos');
?>
<style>
  .bg-purple { background-color: #6f42c1 !important; }
</style>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <span class="text-muted small"><?= count($pedidos) ?> pedido(s)</span>
    <form method="GET" class="d-flex gap-2 flex-wrap align-items-center">
        <input type="text" name="busca" class="form-control form-control-sm"
               placeholder="Buscar nome ou e-mail"
               value="<?= htmlspecialchars($busca) ?>"
               style="min-width:200px;">
        <select name="status" class="form-select form-select-sm" style="min-width:160px;">
            <option value="">Todos os status</option>
            <?php foreach (array_filter($statusOpcoes) as $s): ?>
                <option value="<?= $s ?>" <?= $filtro === $s ? 'selected' : '' ?>><?= $statusLabel[$s] ?? ucfirst($s) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-sm btn-primary">
            <i class="fas fa-search me-1"></i> Buscar
        </button>
        <?php if ($filtro || $busca): ?>
            <a href="pedidos.php" class="btn btn-sm btn-outline-secondary">Limpar</a>
        <?php endif; ?>
    </form>
</div>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>Comprador</th>
                    <th>E-mail</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Envio</th>
                    <th>Data</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($pedidos as $p): ?>
                <tr>
                    <td class="text-muted small" title="ID interno: <?= $p['id'] ?>"><?= htmlspecialchars($p['numero_pedido'] ?? $p['id']) ?></td>
                    <td class="fw-semibold"><?= htmlspecialchars($p['nome_comprador']) ?></td>
                    <td class="text-muted small"><?= htmlspecialchars($p['email_comprador']) ?></td>
                    <td>R$ <?= number_format($p['total'], 2, ',', '.') ?></td>
                    <td><span class="badge bg-<?= $statusBadge[$p['status']] ?? 'secondary' ?>"><?= $statusLabel[$p['status']] ?? $p['status'] ?></span></td>
                    <td>
                        <?php if ($p['chosen_carrier']): ?>
                            <span class="badge-envio">
                                <span class="badge-envio-carrier"><?= htmlspecialchars($p['chosen_carrier']) ?></span>
                                <span class="badge-envio-service"><?= htmlspecialchars($p['chosen_service'] ?? '') ?></span>
                            </span>
                            <?php if (!$p['ot_tracking_code']): ?>
                                <span class="badge-rastreio-pendente" title="Código de rastreio ainda não inserido">
                                    <i class="fas fa-clock"></i>
                                </span>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="text-muted small">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-muted small"><?= date('d/m/Y H:i', strtotime($p['criado_em'])) ?></td>
                    <td><a href="pedido-detalhe.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-primary"><i class="fas fa-eye me-1"></i> Ver detalhe</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($pedidos)): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">Nenhum pedido encontrado.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php layout_foot(); ?>
