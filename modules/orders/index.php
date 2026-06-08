<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/Auth.php';
require_once __DIR__ . '/../../includes/functions.php';

Auth::requireModule('orders');
Auth::logActivity(Auth::id(), 'view_orders', 'orders');

// ── Filtros ───────────────────────────────────────────────
$status      = $_GET['status']    ?? '';
$method      = $_GET['method']    ?? '';
$delivery    = $_GET['delivery']  ?? '';
$dateFrom    = $_GET['date_from'] ?? '';
$dateTo      = $_GET['date_to']   ?? '';
$search      = trim($_GET['q']    ?? '');
$page        = max(1, (int)($_GET['page'] ?? 1));
$perPage     = ITEMS_PER_PAGE;
$offset      = ($page - 1) * $perPage;

// ── Construir WHERE ───────────────────────────────────────
$where  = ['1=1'];
$params = [];

if ($status) {
    $where[] = 'o.status = :status';
    $params[':status'] = $status;
}
if ($method) {
    $where[] = 'o.payment_method = :method';
    $params[':method'] = $method;
}
if ($delivery) {
    $where[] = 'o.delivery_type = :delivery';
    $params[':delivery'] = $delivery;
}
if ($dateFrom) {
    $where[] = 'DATE(o.created_at) >= :date_from';
    $params[':date_from'] = $dateFrom;
}
if ($dateTo) {
    $where[] = 'DATE(o.created_at) <= :date_to';
    $params[':date_to'] = $dateTo;
}
if ($search) {
    $where[] = "(o.order_number ILIKE :q OR u.name ILIKE :q OR u.email ILIKE :q)";
    $params[':q'] = "%{$search}%";
}

$whereStr = implode(' AND ', $where);

// ── Contar total ──────────────────────────────────────────
$total = (int) DB::scalar(
    "SELECT COUNT(*)
       FROM orders o
       LEFT JOIN users u ON u.id = o.user_id
      WHERE $whereStr",
    $params
);

// ── Obtener página ────────────────────────────────────────
$paramsLimit = $params;
$paramsLimit[':limit']  = $perPage;
$paramsLimit[':offset'] = $offset;

$orders = DB::query(
    "SELECT o.id, o.order_number, o.status, o.payment_status, o.payment_method,
            o.total, o.subtotal, o.total_saving, o.delivery_type, o.created_at,
            o.cancelled_at, o.pickup_code,
            u.name AS customer_name, u.email AS customer_email
       FROM orders o
       LEFT JOIN users u ON u.id = o.user_id
      WHERE $whereStr
      ORDER BY o.created_at DESC
      LIMIT :limit OFFSET :offset",
    $paramsLimit
);

// Statuses disponibles
$allStatuses = [
    'PENDING_PAYMENT', 'PENDING', 'CONFIRMED',
    'READY_FOR_PICKUP', 'DELIVERED', 'CANCELLED', 'REFUNDED',
];
$allMethods  = ['card','transfer','yape','plin','cash'];

$pageTitle = 'Gestión de Pedidos';
$pageIcon  = 'fa-solid fa-bag-shopping';

include __DIR__ . '/../../includes/header.php';
?>

<!-- ── Barra de filtros ───────────────────────────────── -->
<div class="filter-bar mb-4">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-md-3">
            <label class="form-label small mb-1">Buscar</label>
            <input type="text" name="q" class="form-control form-control-sm"
                   placeholder="Nº pedido, cliente, email…" value="<?= e($search) ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label small mb-1">Estado</label>
            <select name="status" class="form-select form-select-sm">
                <option value="">Todos</option>
                <?php foreach ($allStatuses as $s): ?>
                <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>>
                    <?= orderStatusBadge($s) ?> <?= $s ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small mb-1">Método de pago</label>
            <select name="method" class="form-select form-select-sm">
                <option value="">Todos</option>
                <?php foreach ($allMethods as $m): ?>
                <option value="<?= $m ?>" <?= $method === $m ? 'selected' : '' ?>>
                    <?= paymentMethodLabel($m) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small mb-1">Tipo entrega</label>
            <select name="delivery" class="form-select form-select-sm">
                <option value="">Todos</option>
                <option value="pickup"   <?= $delivery === 'pickup'   ? 'selected' : '' ?>>🏪 Recojo</option>
                <option value="delivery" <?= $delivery === 'delivery' ? 'selected' : '' ?>>🚚 Delivery</option>
            </select>
        </div>
        <div class="col-md-1">
            <label class="form-label small mb-1">Desde</label>
            <input type="date" name="date_from" class="form-control form-control-sm" value="<?= e($dateFrom) ?>">
        </div>
        <div class="col-md-1">
            <label class="form-label small mb-1">Hasta</label>
            <input type="date" name="date_to" class="form-control form-control-sm" value="<?= e($dateTo) ?>">
        </div>
        <div class="col-md-1">
            <button type="submit" class="btn btn-sm btn-success w-100">
                <i class="fa-solid fa-search"></i> Filtrar
            </button>
        </div>
        <?php if ($search || $status || $method || $delivery || $dateFrom || $dateTo): ?>
        <div class="col-md-auto">
            <a href="<?= BASE_URL ?>/modules/orders/index.php" class="btn btn-sm btn-outline-secondary">
                <i class="fa-solid fa-xmark"></i> Limpiar
            </a>
        </div>
        <?php endif; ?>
    </form>
</div>

<!-- ── Resumen ────────────────────────────────────────── -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <span class="text-muted small">
        <strong><?= number_format($total) ?></strong> pedidos encontrados
    </span>
    <a href="<?= BASE_URL ?>/modules/reports/export_csv.php?<?= http_build_query($_GET) ?>"
       class="btn btn-sm btn-outline-success">
        <i class="fa-solid fa-file-csv me-1"></i>Exportar CSV
    </a>
</div>

<!-- ── Tabla de pedidos ───────────────────────────────── -->
<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Nº Pedido</th>
                        <th>Cliente</th>
                        <th>Estado</th>
                        <th>Pago</th>
                        <th>Método</th>
                        <th>Entrega</th>
                        <th class="text-end">Total</th>
                        <th>Fecha</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($orders)): ?>
                <tr><td colspan="9" class="text-center py-4 text-muted">
                    <i class="fa-solid fa-inbox me-2"></i>No se encontraron pedidos con los filtros aplicados.
                </td></tr>
                <?php else: ?>
                <?php foreach ($orders as $o): ?>
                <tr>
                    <td>
                        <a href="<?= BASE_URL ?>/modules/orders/detail.php?id=<?= urlencode($o['id']) ?>"
                           class="fw-semibold text-decoration-none text-ecosalva">
                            <?= e($o['order_number']) ?>
                        </a>
                    </td>
                    <td>
                        <div><?= e($o['customer_name']) ?></div>
                        <small class="text-muted"><?= e($o['customer_email']) ?></small>
                    </td>
                    <td><?= orderStatusBadge($o['status']) ?></td>
                    <td><?= paymentStatusBadge($o['payment_status']) ?></td>
                    <td><small><?= paymentMethodLabel($o['payment_method']) ?></small></td>
                    <td><small><?= deliveryTypeLabel($o['delivery_type']) ?></small></td>
                    <td class="text-end fw-bold"><?= formatCurrency((float)$o['total']) ?></td>
                    <td><small><?= formatDate($o['created_at']) ?></small></td>
                    <td>
                        <a href="<?= BASE_URL ?>/modules/orders/detail.php?id=<?= urlencode($o['id']) ?>"
                           class="btn btn-sm btn-outline-primary" title="Ver detalle"
                           data-bs-toggle="tooltip">
                            <i class="fa-solid fa-eye"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ── Paginación ─────────────────────────────────────── -->
<div class="mt-3">
    <?php
    $queryStr = http_build_query(array_diff_key($_GET, ['page' => '']));
    $url = BASE_URL . '/modules/orders/index.php' . ($queryStr ? "?$queryStr" : '');
    echo buildPagination($total, $page, $perPage, $url);
    ?>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
