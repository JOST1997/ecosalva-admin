<?php
// Determinar módulo activo para resaltar en sidebar
$currentPath = $_SERVER['SCRIPT_NAME'] ?? '';
$activeModule = '';
if (str_contains($currentPath, '/dashboard'))   $activeModule = 'dashboard';
elseif (str_contains($currentPath, '/orders'))  $activeModule = 'orders';
elseif (str_contains($currentPath, '/statistics')) $activeModule = 'statistics';
elseif (str_contains($currentPath, '/reports')) $activeModule = 'reports';
elseif (str_contains($currentPath, '/complaints')) $activeModule = 'complaints';
elseif (str_contains($currentPath, '/admin'))   $activeModule = 'admin';

function sideLink(string $module, string $href, string $icon, string $label, string $active): string
{
    $isActive = $module === $active ? ' active' : '';
    $base = BASE_URL;
    return "<li class=\"nav-item\">
        <a class=\"nav-link{$isActive}\" href=\"{$base}{$href}\">
            <i class=\"{$icon} me-2 fa-fw\"></i>{$label}
        </a>
    </li>";
}
?>

<nav class="sidebar bg-dark">
    <ul class="nav flex-column pt-3 px-2">

        <?php if (Auth::can('dashboard')): ?>
        <?= sideLink('dashboard', '/modules/dashboard/index.php',
            'fa-solid fa-chart-pie', 'Dashboard', $activeModule) ?>
        <?php endif; ?>

        <?php if (Auth::can('orders')): ?>
        <li class="nav-item mt-2">
            <small class="text-muted px-2 text-uppercase fw-bold" style="font-size:.7rem;">Operación</small>
        </li>
        <?= sideLink('orders', '/modules/orders/index.php',
            'fa-solid fa-bag-shopping', 'Pedidos', $activeModule) ?>
        <?php endif; ?>

        <?php if (Auth::can('complaints')): ?>
        <?= sideLink('complaints', '/modules/complaints/index.php',
            'fa-solid fa-triangle-exclamation', 'Quejas y Reclamos', $activeModule) ?>
        <?php endif; ?>

        <?php if (Auth::can('statistics')): ?>
        <li class="nav-item mt-2">
            <small class="text-muted px-2 text-uppercase fw-bold" style="font-size:.7rem;">Análisis</small>
        </li>
        <?= sideLink('statistics', '/modules/statistics/index.php',
            'fa-solid fa-chart-line', 'Estadísticas', $activeModule) ?>
        <?php endif; ?>

        <?php if (Auth::can('reports')): ?>
        <?= sideLink('reports', '/modules/reports/index.php',
            'fa-solid fa-file-export', 'Reportes', $activeModule) ?>
        <?php endif; ?>

        <?php if (Auth::can('admin')): ?>
        <li class="nav-item mt-2">
            <small class="text-muted px-2 text-uppercase fw-bold" style="font-size:.7rem;">Administración</small>
        </li>
        <?= sideLink('admin', '/modules/admin/users.php',
            'fa-solid fa-users-gear', 'Usuarios Admin', $activeModule) ?>
        <?= sideLink('admin', '/modules/admin/audit.php',
            'fa-solid fa-scroll', 'Bitácora', $activeModule) ?>
        <?php endif; ?>

    </ul>

    <!-- Info de versión al fondo del sidebar -->
    <div class="sidebar-footer px-3 py-2 border-top border-secondary mt-auto">
        <small class="text-muted">
            <i class="fa-solid fa-code-branch me-1"></i>
            v<?= APP_VERSION ?>
        </small>
    </div>
</nav>
