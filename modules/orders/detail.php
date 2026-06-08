<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/Auth.php';
require_once __DIR__ . '/../../includes/functions.php';

Auth::requireModule('orders');

$id = $_GET['id'] ?? '';
if (!$id) {
    redirect('/modules/orders/index.php');
}

// ── Cargar pedido ─────────────────────────────────────────
$order = DB::queryOne(
    "SELECT o.*, u.name AS customer_name, u.email AS customer_email, u.phone AS customer_phone
       FROM orders o
       LEFT JOIN users u ON u.id = o.user_id
      WHERE o.id = :id",
    [':id' => $id]
);

if (!$order) {
    redirect('/modules/orders/index.php');
}

Auth::logActivity(Auth::id(), 'view_order_detail', 'orders', "Pedido: {$order['order_number']}");

// ── Items del pedido ──────────────────────────────────────
$items = DB::query(
    "SELECT * FROM order_items WHERE order_id = :id ORDER BY product_name",
    [':id' => $id]
);

// ── Historial / tracking ──────────────────────────────────
$tracking = DB::query(
    "SELECT * FROM order_tracking WHERE order_id = :id ORDER BY created_at DESC",
    [':id' => $id]
);

// ── Reseñas relacionadas ──────────────────────────────────
$reviews = DB::query(
    "SELECT pr.rating, pr.comment, pr.created_at, u.name AS reviewer
       FROM product_reviews pr
       LEFT JOIN users u ON u.id = pr.user_id
      WHERE pr.order_id = :id",
    [':id' => $id]
);

$pageTitle = 'Detalle Pedido ' . e($order['order_number']);
$pageIcon  = 'fa-solid fa-receipt';

include __DIR__ . '/../../includes/header.php';
?>

<!-- ── Breadcrumb ─────────────────────────────────────── -->
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/modules/orders/index.php">Pedidos</a></li>
        <li class="breadcrumb-item active"><?= e($order['order_number']) ?></li>
    </ol>
</nav>

<div class="row g-4">

    <!-- ── Info principal ─────────────────────────────── -->
    <div class="col-lg-8">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="fa-solid fa-circle-info me-2 text-primary"></i>Información del Pedido</h6>
                <?= orderStatusBadge($order['status']) ?>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <small class="text-muted d-block">Número de pedido</small>
                        <strong><?= e($order['order_number']) ?></strong>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted d-block">Fecha de creación</small>
                        <strong><?= formatDate($order['created_at'], 'd/m/Y H:i:s') ?></strong>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted d-block">Método de pago</small>
                        <strong><?= paymentMethodLabel($order['payment_method']) ?></strong>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted d-block">Estado de pago</small>
                        <?= paymentStatusBadge($order['payment_status']) ?>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted d-block">Tipo de entrega</small>
                        <strong><?= deliveryTypeLabel($order['delivery_type']) ?></strong>
                    </div>
                    <?php if ($order['pickup_code']): ?>
                    <div class="col-md-6">
                        <small class="text-muted d-block">Código de recojo</small>
                        <span class="badge bg-info fs-6"><?= e($order['pickup_code']) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($order['payment_transaction_id']): ?>
                    <div class="col-md-6">
                        <small class="text-muted d-block">ID Transacción</small>
                        <code><?= e($order['payment_transaction_id']) ?></code>
                    </div>
                    <?php endif; ?>
                    <?php if ($order['notes']): ?>
                    <div class="col-12">
                        <small class="text-muted d-block">Notas</small>
                        <p class="mb-0"><?= e($order['notes']) ?></p>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Dirección de delivery -->
                <?php if ($order['delivery_type'] === 'delivery' && $order['delivery_street']): ?>
                <hr>
                <h6 class="fw-semibold"><i class="fa-solid fa-location-dot me-2 text-danger"></i>Dirección de entrega</h6>
                <p class="mb-1"><?= e($order['delivery_street']) ?></p>
                <?php if ($order['delivery_district']): ?>
                <p class="mb-1 text-muted"><?= e($order['delivery_district']) ?></p>
                <?php endif; ?>
                <?php if ($order['delivery_reference']): ?>
                <small class="text-muted">Ref: <?= e($order['delivery_reference']) ?></small>
                <?php endif; ?>
                <?php if ($order['delivery_phone']): ?>
                <p class="mt-1"><i class="fa-solid fa-phone me-1"></i><?= e($order['delivery_phone']) ?></p>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- ── Productos del pedido ─────────────────────── -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white">
                <h6 class="mb-0 fw-bold"><i class="fa-solid fa-box me-2 text-warning"></i>Productos</h6>
            </div>
            <div class="card-body p-0">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Tienda</th>
                            <th>Categoría</th>
                            <th class="text-center">Cant.</th>
                            <th class="text-end">P. Original</th>
                            <th class="text-end">P. Descuento</th>
                            <th class="text-end">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($items as $item): ?>
                    <tr>
                        <td>
                            <?= e($item['product_name']) ?>
                            <?php if ($item['discount_pct'] > 0): ?>
                            <span class="badge bg-danger ms-1">-<?= $item['discount_pct'] ?>%</span>
                            <?php endif; ?>
                        </td>
                        <td><small><?= e($item['store_name']) ?></small></td>
                        <td><small><?= categoryLabel($item['product_category']) ?></small></td>
                        <td class="text-center"><?= (int)$item['quantity'] ?></td>
                        <td class="text-end text-decoration-line-through text-muted">
                            <?= formatCurrency((float)$item['unit_price_original']) ?>
                        </td>
                        <td class="text-end text-success fw-bold">
                            <?= formatCurrency((float)$item['unit_price_discount']) ?>
                        </td>
                        <td class="text-end fw-bold"><?= formatCurrency((float)$item['line_total']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <td colspan="6" class="text-end fw-semibold">Subtotal</td>
                            <td class="text-end"><?= formatCurrency((float)$order['subtotal']) ?></td>
                        </tr>
                        <?php if ((float)$order['total_saving'] > 0): ?>
                        <tr>
                            <td colspan="6" class="text-end text-success fw-semibold">Ahorro total</td>
                            <td class="text-end text-success">-<?= formatCurrency((float)$order['total_saving']) ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <td colspan="6" class="text-end fw-bold fs-5">TOTAL</td>
                            <td class="text-end fw-bold fs-5 text-ecosalva">
                                <?= formatCurrency((float)$order['total']) ?>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- ── Reseñas ─────────────────────────────────── -->
        <?php if (!empty($reviews)): ?>
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h6 class="mb-0 fw-bold"><i class="fa-solid fa-star me-2 text-warning"></i>Reseñas de este pedido</h6>
            </div>
            <div class="card-body">
                <?php foreach ($reviews as $review): ?>
                <div class="mb-3 pb-3 border-bottom">
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <strong><?= e($review['reviewer']) ?></strong>
                        <span>
                            <?php for ($i=1;$i<=5;$i++): ?>
                            <i class="fa-solid fa-star <?= $i <= (int)$review['rating'] ? 'text-warning' : 'text-muted' ?>" style="font-size:.8rem;"></i>
                            <?php endfor; ?>
                        </span>
                        <small class="text-muted"><?= formatDate($review['created_at'], 'd/m/Y') ?></small>
                    </div>
                    <?php if ($review['comment']): ?>
                    <p class="mb-0 text-muted"><?= e($review['comment']) ?></p>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- ── Panel lateral ──────────────────────────────── -->
    <div class="col-lg-4">

        <!-- Cliente -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white">
                <h6 class="mb-0 fw-bold"><i class="fa-solid fa-user me-2 text-primary"></i>Cliente</h6>
            </div>
            <div class="card-body">
                <p class="mb-1 fw-semibold"><?= e($order['customer_name']) ?></p>
                <p class="mb-1 text-muted small"><?= e($order['customer_email']) ?></p>
                <?php if ($order['customer_phone']): ?>
                <p class="mb-0 small"><i class="fa-solid fa-phone me-1"></i><?= e($order['customer_phone']) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Historial de estados -->
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h6 class="mb-0 fw-bold"><i class="fa-solid fa-timeline me-2 text-info"></i>Historial de Estados</h6>
            </div>
            <div class="card-body">
                <?php if (empty($tracking)): ?>
                <p class="text-muted small mb-0">Sin registros de seguimiento.</p>
                <?php else: ?>
                <ul class="list-unstyled mb-0">
                    <?php foreach ($tracking as $t): ?>
                    <li class="d-flex gap-2 mb-3">
                        <div class="pt-1">
                            <div class="bg-ecosalva rounded-circle" style="width:10px;height:10px;"></div>
                        </div>
                        <div>
                            <?= orderStatusBadge($t['status']) ?>
                            <div class="small text-muted mt-1"><?= formatDate($t['created_at']) ?></div>
                            <?php if ($t['notes']): ?>
                            <div class="small"><?= e($t['notes']) ?></div>
                            <?php endif; ?>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
