<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/Auth.php';
require_once __DIR__ . '/../../includes/functions.php';

Auth::requireModule('statistics');
Auth::logActivity(Auth::id(), 'view_statistics', 'statistics');

$year  = (int)($_GET['year']  ?? date('Y'));
$month = (int)($_GET['month'] ?? 0);     // 0 = todos los meses

// ── Ventas por mes (año seleccionado) ─────────────────────
$salesByMonth = DB::query(
    "SELECT EXTRACT(MONTH FROM created_at)::int AS m,
            TO_CHAR(created_at, 'Mon') AS month_name,
            COALESCE(SUM(total),0) AS revenue,
            COUNT(*) AS orders
       FROM orders
      WHERE EXTRACT(YEAR FROM created_at) = :year
        AND status NOT IN ('CANCELLED','REFUNDED')
      GROUP BY m, month_name
      ORDER BY m",
    [':year' => $year]
);

// ── Ventas por semana (año actual) ────────────────────────
$salesByWeek = DB::query(
    "SELECT EXTRACT(WEEK FROM created_at)::int AS week_num,
            COALESCE(SUM(total),0) AS revenue,
            COUNT(*) AS orders
       FROM orders
      WHERE EXTRACT(YEAR FROM created_at) = :year
        AND status NOT IN ('CANCELLED','REFUNDED')
      GROUP BY week_num
      ORDER BY week_num",
    [':year' => $year]
);

// ── Top 10 productos más vendidos ────────────────────────
$topProducts = DB::query(
    "SELECT oi.product_name, oi.product_category,
            SUM(oi.quantity)   AS units_sold,
            SUM(oi.line_total) AS revenue,
            COUNT(DISTINCT o.user_id) AS unique_buyers
       FROM order_items oi
       JOIN orders o ON o.id = oi.order_id
      WHERE o.status NOT IN ('CANCELLED','REFUNDED')
        AND EXTRACT(YEAR FROM o.created_at) = :year
      GROUP BY oi.product_id, oi.product_name, oi.product_category
      ORDER BY units_sold DESC
      LIMIT 10",
    [':year' => $year]
);

// ── 10 productos menos vendidos (> 0) ────────────────────
$bottomProducts = DB::query(
    "SELECT oi.product_name, oi.product_category,
            SUM(oi.quantity) AS units_sold
       FROM order_items oi
       JOIN orders o ON o.id = oi.order_id
      WHERE o.status NOT IN ('CANCELLED','REFUNDED')
        AND EXTRACT(YEAR FROM o.created_at) = :year
      GROUP BY oi.product_id, oi.product_name, oi.product_category
      ORDER BY units_sold ASC
      LIMIT 10",
    [':year' => $year]
);

// ── Ventas por categoría ──────────────────────────────────
$salesByCategory = DB::query(
    "SELECT oi.product_category,
            SUM(oi.line_total) AS revenue,
            SUM(oi.quantity)   AS units_sold,
            COUNT(DISTINCT o.id) AS orders
       FROM order_items oi
       JOIN orders o ON o.id = oi.order_id
      WHERE o.status NOT IN ('CANCELLED','REFUNDED')
        AND EXTRACT(YEAR FROM o.created_at) = :year
      GROUP BY oi.product_category
      ORDER BY revenue DESC",
    [':year' => $year]
);

// ── Top 10 clientes por gasto ────────────────────────────
$topCustomers = DB::query(
    "SELECT u.name, u.email,
            COUNT(DISTINCT o.id)    AS total_orders,
            COALESCE(SUM(o.total),0) AS total_spent,
            MAX(o.created_at)        AS last_order
       FROM orders o
       JOIN users u ON u.id = o.user_id
      WHERE o.status NOT IN ('CANCELLED','REFUNDED')
        AND EXTRACT(YEAR FROM o.created_at) = :year
      GROUP BY o.user_id, u.name, u.email
      ORDER BY total_spent DESC
      LIMIT 10",
    [':year' => $year]
);

// ── Clientes recurrentes (más de 1 pedido) ────────────────
$recurringCount = (int)DB::scalar(
    "SELECT COUNT(*) FROM (
        SELECT user_id FROM orders
         WHERE status NOT IN ('CANCELLED','REFUNDED')
           AND EXTRACT(YEAR FROM created_at) = :year
         GROUP BY user_id HAVING COUNT(*) > 1
     ) t",
    [':year' => $year]
);

// ── Clientes inactivos (sin pedido en los últimos 90 días) ─
$inactiveCustomers = DB::query(
    "SELECT u.name, u.email, MAX(o.created_at) AS last_order,
            COUNT(DISTINCT o.id) AS total_orders
       FROM users u
       JOIN orders o ON o.user_id = u.id
      WHERE o.status NOT IN ('CANCELLED','REFUNDED')
        AND u.role = 'CUSTOMER'
      GROUP BY u.id, u.name, u.email
     HAVING MAX(o.created_at) < NOW() - INTERVAL '90 days'
      ORDER BY last_order ASC
      LIMIT 20"
);

// ── Crecimiento mensual (comparativo año actual vs anterior) ─
$growthData = DB::query(
    "SELECT EXTRACT(MONTH FROM created_at)::int AS m,
            EXTRACT(YEAR  FROM created_at)::int AS y,
            COALESCE(SUM(total),0) AS revenue
       FROM orders
      WHERE EXTRACT(YEAR FROM created_at) IN (:y1, :y2)
        AND status NOT IN ('CANCELLED','REFUNDED')
      GROUP BY y, m
      ORDER BY y, m",
    [':y1' => $year, ':y2' => $year - 1]
);

$growthCurrent  = array_fill(1, 12, 0);
$growthPrevious = array_fill(1, 12, 0);
foreach ($growthData as $row) {
    if ((int)$row['y'] === $year) {
        $growthCurrent[(int)$row['m']] = (float)$row['revenue'];
    } else {
        $growthPrevious[(int)$row['m']] = (float)$row['revenue'];
    }
}

// ── Rentabilidad: mayor ahorro generado por producto ─────
$profitabilityByProduct = DB::query(
    "SELECT oi.product_name,
            SUM(oi.quantity * (oi.unit_price_original - oi.unit_price_discount)) AS total_discount_given,
            SUM(oi.line_total) AS revenue,
            SUM(oi.quantity)   AS units_sold
       FROM order_items oi
       JOIN orders o ON o.id = oi.order_id
      WHERE o.status NOT IN ('CANCELLED','REFUNDED')
        AND EXTRACT(YEAR FROM o.created_at) = :year
      GROUP BY oi.product_id, oi.product_name
      ORDER BY revenue DESC
      LIMIT 10",
    [':year' => $year]
);

// ── Tendencia diaria (últimos 60 días) ───────────────────
$dailyTrend = DB::query(
    "SELECT DATE(created_at) AS day,
            COALESCE(SUM(total),0) AS revenue
       FROM orders
      WHERE created_at >= NOW() - INTERVAL '60 days'
        AND status NOT IN ('CANCELLED','REFUNDED')
      GROUP BY DATE(created_at)
      ORDER BY day"
);

// ── Preparar datos para charts ────────────────────────────
$monthNames   = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
$monthRevMap  = array_fill(0, 12, 0);
$monthOrdMap  = array_fill(0, 12, 0);
foreach ($salesByMonth as $r) {
    $idx = (int)$r['m'] - 1;
    $monthRevMap[$idx]  = (float)$r['revenue'];
    $monthOrdMap[$idx]  = (int)$r['orders'];
}

$catLabels  = array_map(fn($r) => categoryLabel($r['product_category']), $salesByCategory);
$catRevenue = array_map(fn($r) => (float)$r['revenue'], $salesByCategory);

$weekLabels   = array_map(fn($r) => 'Sem ' . $r['week_num'], $salesByWeek);
$weekRevenue  = array_map(fn($r) => (float)$r['revenue'], $salesByWeek);

$trendDays    = array_column($dailyTrend, 'day');
$trendRevenue = array_map(fn($r) => (float)$r['revenue'], $dailyTrend);

$availableYears = DB::query(
    "SELECT DISTINCT EXTRACT(YEAR FROM created_at)::int AS y FROM orders ORDER BY y DESC"
);

$pageTitle = 'Estadísticas';
$pageIcon  = 'fa-solid fa-chart-line';

// Pre-codificar arrays como JSON para usar en heredoc
$jsonMonthNames      = json_encode($monthNames);
$jsonMonthRevMap     = json_encode(array_values($monthRevMap));
$jsonGrowthPrevious  = json_encode(array_values($growthPrevious));
$jsonWeekLabels      = json_encode($weekLabels);
$jsonWeekRevenue     = json_encode($weekRevenue);
$jsonCatLabels       = json_encode($catLabels);
$jsonCatRevenue      = json_encode($catRevenue);
$jsonTrendDays       = json_encode($trendDays);
$jsonTrendRevenue    = json_encode($trendRevenue);
$prevYear            = $year - 1;

$extraScripts = <<<JS
<script>
// Ventas por mes
createLineChart('chartMonthly', $jsonMonthNames, [
    {
        label: 'Ingresos $year',
        data: $jsonMonthRevMap,
        borderColor: chartColors.green, backgroundColor: chartColors.greenBg, fill:true, tension:.35
    },
    {
        label: 'Ingresos $prevYear',
        data: $jsonGrowthPrevious,
        borderColor: chartColors.blue, backgroundColor: chartColors.blueBg, fill:false, tension:.35,
        borderDash: [5,5]
    }
]);

// Ventas por semana
createBarChart('chartWeekly', $jsonWeekLabels, $jsonWeekRevenue, 'Ingresos');

// Por categoría (dona)
createDoughnutChart('chartCategory', $jsonCatLabels, $jsonCatRevenue);

// Tendencia diaria (60 días)
createLineChart('chartDailyTrend', $jsonTrendDays, [{
    label: 'Ingresos diarios',
    data: $jsonTrendRevenue,
    borderColor: chartColors.orange, backgroundColor: 'rgba(255,159,64,.1)', fill:true, tension:.3
}]);
</script>
JS;

include __DIR__ . '/../../includes/header.php';
?>

<!-- Selector de año -->
<div class="d-flex align-items-center gap-3 mb-4">
    <form method="GET" class="d-flex align-items-center gap-2">
        <label class="fw-semibold mb-0">Año:</label>
        <select name="year" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
            <?php foreach ($availableYears as $y): ?>
            <option value="<?= $y['y'] ?>" <?= (int)$y['y'] === $year ? 'selected' : '' ?>>
                <?= $y['y'] ?>
            </option>
            <?php endforeach; ?>
        </select>
    </form>
    <span class="badge bg-success">Mostrando datos de <?= $year ?></span>
</div>

<!-- ── Gráficos principales ───────────────────────────── -->
<div class="row g-4 mb-4">
    <div class="col-lg-8">
        <div class="card chart-card">
            <div class="card-header bg-white fw-semibold border-0">
                <i class="fa-solid fa-chart-area text-ecosalva me-2"></i>
                Comparativo Mensual <?= $year ?> vs <?= $year-1 ?>
            </div>
            <div class="card-body" style="height:280px;"><canvas id="chartMonthly"></canvas></div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card chart-card">
            <div class="card-header bg-white fw-semibold border-0">
                <i class="fa-solid fa-layer-group text-ecosalva me-2"></i>Ventas por Categoría
            </div>
            <div class="card-body" style="height:280px;"><canvas id="chartCategory"></canvas></div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-6">
        <div class="card chart-card">
            <div class="card-header bg-white fw-semibold border-0">
                <i class="fa-solid fa-calendar-week text-primary me-2"></i>Ventas por Semana
            </div>
            <div class="card-body" style="height:250px;"><canvas id="chartWeekly"></canvas></div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card chart-card">
            <div class="card-header bg-white fw-semibold border-0">
                <i class="fa-solid fa-arrow-trend-up text-warning me-2"></i>Tendencia Diaria (60 días)
            </div>
            <div class="card-body" style="height:250px;"><canvas id="chartDailyTrend"></canvas></div>
        </div>
    </div>
</div>

<!-- ── Tablas estadísticas ────────────────────────────── -->
<div class="row g-4 mb-4">

    <!-- Top productos -->
    <div class="col-lg-6">
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold">
                <i class="fa-solid fa-trophy text-warning me-2"></i>Top 10 Productos Más Vendidos
            </div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead><tr>
                        <th>#</th><th>Producto</th><th>Cat.</th>
                        <th class="text-end">Unid.</th><th class="text-end">Ingresos</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($topProducts as $i => $p): ?>
                    <tr>
                        <td><span class="badge bg-warning text-dark"><?= $i+1 ?></span></td>
                        <td><?= e($p['product_name']) ?></td>
                        <td><small><?= categoryLabel($p['product_category']) ?></small></td>
                        <td class="text-end"><?= number_format((int)$p['units_sold']) ?></td>
                        <td class="text-end fw-bold"><?= formatCurrency((float)$p['revenue']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Bottom productos -->
    <div class="col-lg-6">
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold">
                <i class="fa-solid fa-arrow-down text-danger me-2"></i>10 Productos Menos Vendidos
            </div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead><tr>
                        <th>#</th><th>Producto</th><th>Cat.</th><th class="text-end">Unid.</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($bottomProducts as $i => $p): ?>
                    <tr>
                        <td><span class="badge bg-secondary"><?= $i+1 ?></span></td>
                        <td><?= e($p['product_name']) ?></td>
                        <td><small><?= categoryLabel($p['product_category']) ?></small></td>
                        <td class="text-end text-danger"><?= number_format((int)$p['units_sold']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ── Top clientes y recurrentes ─────────────────────── -->
<div class="row g-4 mb-4">
    <div class="col-lg-7">
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold">
                <i class="fa-solid fa-crown text-warning me-2"></i>Top 10 Clientes por Gasto
            </div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead><tr>
                        <th>#</th><th>Cliente</th><th class="text-center">Pedidos</th>
                        <th class="text-end">Gasto total</th><th>Último pedido</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($topCustomers as $i => $c): ?>
                    <tr>
                        <td><span class="badge bg-primary"><?= $i+1 ?></span></td>
                        <td>
                            <div><?= e($c['name']) ?></div>
                            <small class="text-muted"><?= e($c['email']) ?></small>
                        </td>
                        <td class="text-center"><?= (int)$c['total_orders'] ?></td>
                        <td class="text-end fw-bold"><?= formatCurrency((float)$c['total_spent']) ?></td>
                        <td><small><?= formatDate($c['last_order'], 'd/m/Y') ?></small></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <!-- Resumen clientes -->
        <div class="card shadow-sm mb-3">
            <div class="card-body">
                <h6 class="fw-bold mb-3"><i class="fa-solid fa-users me-2 text-success"></i>Resumen de Clientes</h6>
                <div class="d-flex justify-content-between mb-2">
                    <span>Clientes recurrentes (<?= $year ?>)</span>
                    <span class="badge bg-success fs-6"><?= number_format($recurringCount) ?></span>
                </div>
            </div>
        </div>

        <!-- Clientes inactivos -->
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold">
                <i class="fa-solid fa-user-clock text-warning me-2"></i>Clientes Inactivos (+90 días)
            </div>
            <div class="card-body p-0" style="max-height:300px;overflow-y:auto;">
                <table class="table table-sm mb-0">
                    <thead><tr>
                        <th>Cliente</th><th>Último pedido</th><th class="text-center">Pedidos</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($inactiveCustomers as $c): ?>
                    <tr>
                        <td>
                            <div class="small"><?= e($c['name']) ?></div>
                            <small class="text-muted"><?= e($c['email']) ?></small>
                        </td>
                        <td><small class="text-danger"><?= formatDate($c['last_order'], 'd/m/Y') ?></small></td>
                        <td class="text-center"><?= (int)$c['total_orders'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ── Rentabilidad ───────────────────────────────────── -->
<div class="card shadow-sm mb-4">
    <div class="card-header bg-white fw-semibold">
        <i class="fa-solid fa-dollar-sign text-success me-2"></i>
        Productos con Mayor Rentabilidad (Ingreso generado)
    </div>
    <div class="card-body p-0">
        <table class="table table-sm mb-0">
            <thead><tr>
                <th>Producto</th>
                <th class="text-end">Unidades</th>
                <th class="text-end">Ingresos</th>
                <th class="text-end">Descuento total otorgado</th>
            </tr></thead>
            <tbody>
            <?php foreach ($profitabilityByProduct as $p): ?>
            <tr>
                <td><?= e($p['product_name']) ?></td>
                <td class="text-end"><?= number_format((int)$p['units_sold']) ?></td>
                <td class="text-end fw-bold text-success"><?= formatCurrency((float)$p['revenue']) ?></td>
                <td class="text-end text-muted"><?= formatCurrency((float)$p['total_discount_given']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ── Tabla mensual detallada ────────────────────────── -->
<div class="card shadow-sm mb-4">
    <div class="card-header bg-white fw-semibold">
        <i class="fa-solid fa-table me-2 text-primary"></i>Detalle Mensual <?= $year ?>
    </div>
    <div class="card-body p-0">
        <table class="table mb-0">
            <thead><tr>
                <th>Mes</th>
                <th class="text-end">Pedidos</th>
                <th class="text-end">Ingresos</th>
                <th class="text-end">Crecimiento vs anterior</th>
            </tr></thead>
            <tbody>
            <?php
            $prevRevenue = 0;
            foreach ($salesByMonth as $row):
                $rev = (float)$row['revenue'];
                $growth = growthRate($rev, $prevRevenue);
                $growthCls = growthClass($rev, $prevRevenue);
                $prevRevenue = $rev;
            ?>
            <tr>
                <td><?= $monthNames[(int)$row['m'] - 1] ?></td>
                <td class="text-end"><?= number_format((int)$row['orders']) ?></td>
                <td class="text-end fw-bold"><?= formatCurrency($rev) ?></td>
                <td class="text-end <?= $growthCls ?>"><?= $growth ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
