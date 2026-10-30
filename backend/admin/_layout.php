<?php
function layout_head(string $titulo, string $extra_head = ''): void {
    $base = '/backend/admin/';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($titulo) ?> — PSPart Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="/assets/css/disponibilidade.css" rel="stylesheet">
    <?= $extra_head ?>
    <link href="<?= $base ?>admin.css?v=<?= filemtime(__DIR__ . '/admin.css') ?>" rel="stylesheet">
</head>
<body>

<div class="sidebar" id="adminSidebar" tabindex="-1" aria-label="Menu do admin">
    <a class="sidebar-brand" href="<?= $base ?>dashboard.php">
        <span>PSPart</span> Admin
    </a>
    <nav>
        <div class="nav-section">Principal</div>
        <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : '' ?>" href="<?= $base ?>dashboard.php">
            <i class="fas fa-chart-pie fa-fw"></i> Dashboard
        </a>

        <div class="nav-section">Catálogo</div>
        <a class="nav-link <?= in_array(basename($_SERVER['PHP_SELF']), ['produtos.php','produto-novo.php','produto-editar.php']) ? 'active' : '' ?>" href="<?= $base ?>produtos.php">
            <i class="fas fa-box fa-fw"></i> Produtos
        </a>
        <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'categorias.php' ? 'active' : '' ?>" href="<?= $base ?>categorias.php">
            <i class="fas fa-tags fa-fw"></i> Categorias
        </a>

        <div class="nav-section">Conteúdo</div>
        <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'carrossel.php' ? 'active' : '' ?>" href="<?= $base ?>carrossel.php">
            <i class="fas fa-images fa-fw"></i> Carrossel
        </a>

        <div class="nav-section">Vendas</div>
        <a class="nav-link <?= in_array(basename($_SERVER['PHP_SELF']), ['pedidos.php','pedido-detalhe.php']) ? 'active' : '' ?>" href="<?= $base ?>pedidos.php">
            <i class="fas fa-shopping-cart fa-fw"></i> Pedidos
        </a>
        <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'tracking-admin.php' ? 'active' : '' ?>" href="<?= $base ?>tracking-admin.php">
            <i class="fas fa-truck fa-fw"></i> Rastreamento
        </a>

        <div class="nav-section">Configurações</div>
        <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'melhorenvio.php' ? 'active' : '' ?>" href="<?= $base ?>melhorenvio.php">
            <i class="fas fa-plug fa-fw"></i> Integrações
        </a>
    </nav>
    <div class="sidebar-footer">
        <div class="text-truncate mb-1" style="color:#fff;"><?= htmlspecialchars($_SESSION['admin_nome'] ?? '') ?></div>
        <div class="d-flex gap-3">
            <a href="<?= $base ?>minha-conta.php" class="text-secondary text-decoration-none small">
                <i class="fas fa-key me-1"></i>Senha
            </a>
            <a href="<?= $base ?>logout.php" class="text-danger text-decoration-none small">
                <i class="fas fa-sign-out-alt me-1"></i>Sair
            </a>
        </div>
    </div>
</div>

<div class="main-wrapper">
    <div class="topbar">
        <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn btn-sm btn-outline-secondary adm-menu-btn"
                    data-bs-toggle="offcanvas" data-bs-target="#adminSidebar"
                    aria-controls="adminSidebar" aria-label="Abrir menu">
                <i class="fas fa-bars"></i>
            </button>
            <h1><?= htmlspecialchars($titulo) ?></h1>
        </div>
        <span class="text-muted small"><?= date('d/m/Y H:i') ?></span>
    </div>
    <div class="content">
<?php
}

function layout_foot(): void {
?>
    </div><!-- .content -->
</div><!-- .main-wrapper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Menu aberto no celular e janela alargada acima de 768px: fecha, senão o fundo escuro fica
window.matchMedia('(max-width: 768px)').addEventListener('change', function (e) {
    if (e.matches) return;
    var menu = bootstrap.Offcanvas.getInstance(document.getElementById('adminSidebar'));
    if (menu) menu.hide();
});
</script>
</body>
</html>
<?php
}
