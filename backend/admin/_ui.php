<?php
// Peças de interface compartilhadas pelo admin (Patch U).

// Rótulo de cada status de pedido — fonte única para dashboard, pedidos, pedido-detalhe,
// pedido-ficha e tracking-admin. É a união dos mapas que existiam nessas páginas;
// 'enviado' é status legado (ver Pendências no CLAUDE.md), mantido só para exibição.
const ADMIN_STATUS_PEDIDO_ROTULO = [
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

// Cor canônica do badge (sufixo de bg-*). 'purple' vem de .bg-purple em admin.css.
const ADMIN_STATUS_PEDIDO_COR = [
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

// Status fora do mapa aparece com o nome técnico, nunca com um rótulo inventado
function admin_status_rotulo(string $status): string {
    return ADMIN_STATUS_PEDIDO_ROTULO[$status] ?? $status;
}

// Alerta de retorno via ?msg= (redirect depois de uma ação). Tipos: ?tipo=erro → alert-danger
// com ícone e role="alert"; qualquer outro (o 'sucesso' de produto-estado.php, ou ausente) →
// alert-success com role="status". Uma recusa nunca aparece como sucesso (Patch P).
function admin_alerta_msg(): void {
    if (!isset($_GET['msg'])) return;
    $erro = ($_GET['tipo'] ?? '') === 'erro';
?>
    <div class="alert alert-<?= $erro ? 'danger' : 'success' ?> alert-dismissible fade show py-2" role="<?= $erro ? 'alert' : 'status' ?>">
        <?php if ($erro): ?><i class="fas fa-circle-exclamation me-1"></i><?php endif; ?>
        <?= htmlspecialchars($_GET['msg']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php
}

// Badge de status de pedido; $classes extras vão no <span> (ex.: 'fs-6')
function admin_badge_status_pedido(string $status, string $classes = ''): string {
    $cor = ADMIN_STATUS_PEDIDO_COR[$status] ?? 'secondary';
    return '<span class="badge bg-' . $cor . ($classes !== '' ? ' ' . $classes : '') . '">'
         . htmlspecialchars(admin_status_rotulo($status)) . '</span>';
}
