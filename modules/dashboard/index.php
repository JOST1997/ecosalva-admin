<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/Auth.php';
require_once __DIR__ . '/../../includes/functions.php';

Auth::requireModule('dashboard');
Auth::logActivity(Auth::id(), 'view_dashboard', 'dashboard');

$db = DB::getInstance();

// ── Fechas de referencia ───────────────────────────────────
$now          = new DateTime();
$todayStr     = $now->format('Y-m-d');
$monthStart   = $now->format('Y-m-01');
$yearStart    = $now->format('Y-01-01');
$prevMonthStart = (new DateTime('first day of last month'))->format('Y-m-d');
$prevMonthEnd   = (new DateTime('last day of last month'))->format('Y-m-d');

// ── KPIs principales ──────────────────────────────────────
// Ventas del día
$salesDay = DB::queryOne(
    "SELECT COALESCE(SUM(total),0) AS total, COUNT(*) AS orders
       FROM orders
      WHERE DATE(created_at) = :today
        AND status NOT IN ('CANCELLED','REFUNDED')",
    [':today' => $todayStr]
);

// Ventas del mes
$salesMonth = DB::queryOne(
    "SELECT COALESCE(SUM(total),0) AS total, COUNT(*) AS orders
       FROM orders
      WHERE created_at >= :start AND created_at < :end
        AND status NOT IN ('CANCELLED','REFUNDED')",
    [':start' => $monthStart, ':end' => $now->format('Y-m-d') . ' 23:59:59']
);

// Ventas del año
$salesYear = DB::queryOne(
    "SELECT COALESCE(SUM(total),0) AS total, COUNT(*) AS orders
       FROM orders
      WHERE created_at >= :start
        AND status NOT IN ('CANCELLED','REFUNDED')",
    [':start' => $yearStart]
);

// Mes anterior (comparativo)
$salesPrevMonth = DB::queryOne(
    "SELECT COALESCE(SUM(total),0) AS total FROM orders
      WHERE created_at BETWEEN :s AND :e
        AND status NOT IN ('CANCELLED','REFUNDED')",
    [':s' => $prevMonthStart, ':e' => $prevMonthEnd . ' 23:59:59']
);

// Pedidos por estado
$ordersByStatus = DB::query(
    "SELECT status, COUNT(*) AS total FROM orders GROUP BY status ORDER BY total DESC"
);
$statusMap = [];
foreach ($ordersByStatus as $row) {
    $statusMap[$row['status']] = (int)$row['total'];
}

// Ticket promedio (mes actual)
$avgTicket = DB::scalar(
    "SELECT COALESCE(AVG(total),0) FROM orders
      WHERE created_at >= :start AND status NOT IN ('CANCELLED','REFUNDED')",
    [':start' => $monthStart]
);

// Clientes nuevos (mes actual)
$newCustomers = DB::scalar(
    "SELECT COUNT(*) FROM users WHERE created_at >= :start AND role = 'CUSTOMER'",
    [':start' => $monthStart]
);

// Clientes totales
$totalCustomers = DB::scalar("SELECT COUNT(*) FROM users WHERE role = 'CUSTOMER'");

// Productos más vendidos (top 5)
$topProducts = DB::query(
    "SELECT oi.product_name, oi.product_category,
            SUM(oi.quantity) AS units_sold,
            SUM(oi.line_total) AS revenue
       FROM order_items oi
       JOIN orders o ON o.id = oi.order_id
      WHERE o.status NOT IN ('CANCELLED','REFUNDED')
        AND o.created_at >= :start
      GROUP BY oi.product_id, oi.product_name, oi.product_category
      ORDER BY units_sold DESC
      LIMIT 5",
    [':start' => $monthStart]
);

// Productos menos vendidos (con ventas > 0)
$bottomProducts = DB::query(
    "SELECT oi.product_name, SUM(oi.quantity) AS units_sold
       FROM order_items oi
       JOIN orders o ON o.id = oi.order_id
      WHERE o.status NOT IN ('CANCELLED','REFUNDED')
        AND o.created_at >= :start
      GROUP BY oi.product_id, oi.product_name
      ORDER BY units_sold ASC
      LIMIT 5",
    [':start' => $monthStart]
);

// Ventas últimos 30 días (para gráfico diario)
$last30Days = DB::query(
    "SELECT DATE(created_at) AS day,
            COALESCE(SUM(total),0) AS revenue,
            COUNT(*) AS orders
       FROM orders
      WHERE created_at >= NOW() - INTERVAL '30 days'
        AND status NOT IN ('CANCELLED','REFUNDED')
      GROUP BY DATE(created_at)
      ORDER BY day"
);

// Ventas por categoría (mes)
$salesByCategory = DB::query(
    "SELECT oi.product_category, SUM(oi.line_total) AS revenue
       FROM order_items oi
       JOIN orders o ON o.id = oi.order_id
      WHERE o.status NOT IN ('CANCELLED','REFUNDED')
        AND o.created_at >= :start
      GROUP BY oi.product_category
      ORDER BY revenue DESC
      LIMIT 8",
    [':start' => $monthStart]
);

// ── Preparar datos para Chart.js ──────────────────────────
$chartDays     = array_column($last30Days, 'day');
$chartRevenue  = array_column($last30Days, 'revenue');
$chartOrders   = array_column($last30Days, 'orders');

$catLabels  = array_map(fn($r) => categoryLabel($r['product_category']), $salesByCategory);
$catRevenue = array_column($salesByCategory, 'revenue');

// Pre-codificar arrays como JSON para usar en heredoc
$jsonChartDays    = json_encode($chartDays);
$jsonChartRevenue = json_encode(array_map('floatval', $chartRevenue));
$jsonChartOrders  = json_encode(array_map('intval',   $chartOrders));
$jsonCatLabels    = json_encode($catLabels);
$jsonCatRevenue   = json_encode(array_map('floatval', $catRevenue));

// ── Variables de página ───────────────────────────────────
$pageTitle = 'Dashboard Ejecutivo';
$pageIcon  = 'fa-solid fa-chart-pie';

$extraScripts = <<<JS
<script>
// Gráfico de ventas diarias (línea)
createLineChart('chartSalesDaily',
    $jsonChartDays,
    [{
        label: 'Ingresos (S/)',
        data: $jsonChartRevenue,
        borderColor: chartColors.green,
        backgroundColor: chartColors.greenBg,
        fill: true, tension: .35,
    }],
    'Ventas últimos 30 días'
);

// Gráfico de pedidos diarios (barras)
createBarChart('chartOrdersDaily',
    $jsonChartDays,
    $jsonChartOrders,
    'Pedidos',
    chartColors.blue
);

// Gráfico por categoría (dona)
createDoughnutChart('chartByCategory',
    $jsonCatLabels,
    $jsonCatRevenue
);
</script>
JS;

include __DIR__ . '/../../includes/header.php';
?>

<!-- ── FILA DE KPIs ────────────────────────────────────── -->
<div class="row g-3 mb-4">

    <!-- Ventas del día -->
    <div class="col-xl-3 col-md-6">
        <div class="card kpi-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="kpi-icon bg-success bg-opacity-10 text-success">
                    <i class="fa-solid fa-sun"></i>
                </div>
                <div>
                    <div class="kpi-value text-success"><?= formatCurrency((float)$salesDay['total']) ?></div>
                    <div class="kpi-label">Ventas del día</div>
                    <div class="kpi-delta text-muted"><?= (int)$salesDay['orders'] ?> pedidos hoy</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Ventas del mes -->
    <div class="col-xl-3 col-md-6">
        <div class="card kpi-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="kpi-icon bg-primary bg-opacity-10 text-primary">
                    <i class="fa-solid fa-calendar-days"></i>
                </div>
                <div>
                    <div class="kpi-value text-primary"><?= formatCurrency((float)$salesMonth['total']) ?></div>
                    <div class="kpi-label">Ventas del mes</div>
                    <div class="kpi-delta <?= growthClass((float)$salesMonth['total'], (float)$salesPrevMonth['total']) ?>">
                        <?= growthRate((float)$salesMonth['total'], (float)$salesPrevMonth['total']) ?> vs mes anterior
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Ventas del año -->
    <div class="col-xl-3 col-md-6">
        <div class="card kpi-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="kpi-icon bg-info bg-opacity-10 text-info">
                    <i class="fa-solid fa-chart-line"></i>
                </div>
                <div>
                    <div class="kpi-value text-info"><?= formatCurrency((float)$salesYear['total']) ?></div>
                    <div class="kpi-label">Ventas del año</div>
                    <div class="kpi-delta text-muted"><?= number_format((int)$salesYear['orders']) ?> pedidos</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Ticket promedio -->
    <div class="col-xl-3 col-md-6">
        <div class="card kpi-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="kpi-icon bg-warning bg-opacity-10 text-warning">
                    <i class="fa-solid fa-receipt"></i>
                </div>
                <div>
                    <div class="kpi-value text-warning"><?= formatCurrency((float)$avgTicket) ?></div>
                    <div class="kpi-label">Ticket promedio</div>
                    <div class="kpi-delta text-muted">Este mes</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── FILA ESTADO DE PEDIDOS ─────────────────────────── -->
<div class="row g-3 mb-4">
    <?php
    $statusCards = [
        ['PENDING',          'secondary', 'fa-clock',           'Pendientes'],
        ['CONFIRMED',        'primary',   'fa-check-circle',    'Confirmados'],
        ['READY_FOR_PICKUP', 'info',      'fa-store',           'Listos P/Recoger'],
        ['DELIVERED',        'success',   'fa-circle-check',    'Entregados'],
        ['CANCELLED',        'danger',    'fa-ban',             'Cancelados'],
        ['REFUNDED',         'dark',      'fa-rotate-left',     'Reembolsados'],
    ];
    foreach ($statusCards as [$key, $color, $icon, $label]):
        $count = $statusMap[$key] ?? 0;
    ?>
    <div class="col-xl-2 col-md-4 col-6">
        <div class="card kpi-card text-center">
            <div class="card-body py-3">
                <div class="text-<?= $color ?> mb-1" style="font-size:1.5rem;">
                    <i class="fa-solid <?= $icon ?>"></i>
                </div>
                <div class="fw-bold fs-5 text-<?= $color ?>"><?= number_format($count) ?></div>
                <div class="kpi-label"><?= $label ?></div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- ── Fila de clientes y nuevos clientes ─────────────── -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card kpi-card">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="kpi-icon bg-success bg-opacity-10 text-success">
                    <i class="fa-solid fa-users"></i>
                </div>
                <div>
                    <div class="kpi-value"><?= number_format((int)$totalCustomers) ?></div>
                    <div class="kpi-label">Clientes totales</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card kpi-card">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="kpi-icon bg-primary bg-opacity-10 text-primary">
                    <i class="fa-solid fa-user-plus"></i>
                </div>
                <div>
                    <div class="kpi-value text-primary"><?= number_format((int)$newCustomers) ?></div>
                    <div class="kpi-label">Clientes nuevos (mes)</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── GRÁFICOS ───────────────────────────────────────── -->
<div class="row g-4 mb-4">
    <!-- Ventas diarias (línea) -->
    <div class="col-lg-8">
        <div class="card chart-card">
            <div class="card-header bg-white fw-semibold border-0 pb-0">
                <i class="fa-solid fa-chart-area text-ecosalva me-2"></i>Ingresos — Últimos 30 días
            </div>
            <div class="card-body chart-container" style="height:300px;">
                <canvas id="chartSalesDaily"></canvas>
            </div>
        </div>
    </div>

    <!-- Por categoría (dona) -->
    <div class="col-lg-4">
        <div class="card chart-card">
            <div class="card-header bg-white fw-semibold border-0 pb-0">
                <i class="fa-solid fa-layer-group text-ecosalva me-2"></i>Ventas por Categoría
            </div>
            <div class="card-body chart-container" style="height:300px;">
                <canvas id="chartByCategory"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Pedidos diarios (barras) -->
    <div class="col-lg-8">
        <div class="card chart-card">
            <div class="card-header bg-white fw-semibold border-0 pb-0">
                <i class="fa-solid fa-chart-bar text-ecosalva me-2"></i>Cantidad de Pedidos — Últimos 30 días
            </div>
            <div class="card-body chart-container" style="height:260px;">
                <canvas id="chartOrdersDaily"></canvas>
            </div>
        </div>
    </div>

    <!-- Top 5 productos -->
    <div class="col-lg-4">
        <div class="card chart-card">
            <div class="card-header bg-white fw-semibold border-0 pb-0">
                <i class="fa-solid fa-trophy text-warning me-2"></i>Top Productos (mes)
            </div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead><tr>
                        <th>Producto</th>
                        <th class="text-end">Unid.</th>
                        <th class="text-end">Ingresos</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($topProducts as $i => $p): ?>
                    <tr>
                        <td>
                            <span class="badge bg-warning text-dark me-1"><?= $i + 1 ?></span>
                            <?= e($p['product_name']) ?>
                        </td>
                        <td class="text-end"><?= number_format((int)$p['units_sold']) ?></td>
                        <td class="text-end small"><?= formatCurrency((float)$p['revenue']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
