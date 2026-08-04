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
    <?= $extra_head ?>
    <style>
        body { background: #f4f6f9; }
        .sidebar {
            width: 240px; min-height: 100vh;
            background: #1e2a3a; color: #c8d6e5;
            position: fixed; top: 0; left: 0; z-index: 100;
            display: flex; flex-direction: column;
        }
        .sidebar-brand {
            padding: 1.25rem 1.5rem;
            font-size: 1.1rem; font-weight: 700;
            color: #fff; border-bottom: 1px solid #2e3f54;
            text-decoration: none;
        }
        .sidebar-brand span { color: #4e9af1; }
        .sidebar nav { flex: 1; padding: .5rem 0; }
        .sidebar .nav-link {
            color: #c8d6e5; padding: .6rem 1.5rem;
            font-size: .9rem; display: flex; align-items: center; gap: .6rem;
            border-radius: 0; transition: background .15s;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background: #2e3f54; color: #fff;
        }
        .sidebar .nav-section {
            font-size: .7rem; text-transform: uppercase;
            letter-spacing: .08em; color: #6b7f93;
            padding: 1rem 1.5rem .3rem;
        }
        .sidebar-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid #2e3f54;
            font-size: .85rem;
        }
        .main-wrapper { margin-left: 240px; min-height: 100vh; }
        .topbar {
            background: #fff; border-bottom: 1px solid #dee2e6;
            padding: .75rem 1.5rem;
            display: flex; align-items: center; justify-content: space-between;
        }
        .topbar h1 { font-size: 1.15rem; font-weight: 600; margin: 0; }
        .content { padding: 1.5rem; }
        .stat-card { border: none; border-radius: 10px; }
        .stat-card .icon { width: 48px; height: 48px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; }
        @media (max-width: 768px) {
            .sidebar { display: none; }
            .main-wrapper { margin-left: 0; }
        }
    </style>
</head>
<body>

<div class="sidebar">
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
        <h1><?= htmlspecialchars($titulo) ?></h1>
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
</body>
</html>
<?php
}
