<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/Auth.php';
require_once __DIR__ . '/../../includes/functions.php';

Auth::requireModule('reports');
Auth::logActivity(Auth::id(), 'view_reports', 'reports');

$pageTitle = 'Reportes';
$pageIcon  = 'fa-solid fa-file-export';

include __DIR__ . '/../../includes/header.php';
?>

<div class="row g-4">

    <!-- ── Reporte de pedidos ─────────────────────────── -->
    <div class="col-md-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">
                <i class="fa-solid fa-bag-shopping text-primary me-2"></i>Reporte de Pedidos
            </div>
            <div class="card-body">
                <form method="GET" action="<?= BASE_URL ?>/modules/reports/export_csv.php" target="_blank">
                    <input type="hidden" name="report" value="orders">
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label small">Desde</label>
                            <input type="date" name="date_from" class="form-control form-control-sm"
                                   value="<?= date('Y-m-01') ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label small">Hasta</label>
                            <input type="date" name="date_to" class="form-control form-control-sm"
                                   value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label small">Estado</label>
                            <select name="status" class="form-select form-select-sm">
                                <option value="">Todos los estados</option>
                                <option value="PENDING">Pendiente</option>
                                <option value="CONFIRMED">Confirmado</option>
                                <option value="DELIVERED">Entregado</option>
                                <option value="CANCELLED">Cancelado</option>
                                <option value="REFUNDED">Reembolsado</option>
                            </select>
                        </div>
                    </div>
                    <hr>
                    <div class="d-flex gap-2 flex-wrap">
                        <button type="submit" name="format" value="csv" class="btn btn-sm btn-outline-success">
                            <i class="fa-solid fa-file-csv me-1"></i>CSV
                        </button>
                        <button type="submit" name="format" value="excel" class="btn btn-sm btn-outline-success"
                                formaction="<?= BASE_URL ?>/modules/reports/export_excel.php">
                            <i class="fa-solid fa-file-excel me-1"></i>Excel
                        </button>
                        <button type="submit" name="format" value="pdf" class="btn btn-sm btn-outline-danger"
                                formaction="<?= BASE_URL ?>/modules/reports/export_pdf.php">
                            <i class="fa-solid fa-file-pdf me-1"></i>PDF
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ── Reporte de ventas ──────────────────────────── -->
    <div class="col-md-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">
                <i class="fa-solid fa-chart-line text-success me-2"></i>Reporte de Ventas
            </div>
            <div class="card-body">
                <form method="GET" action="<?= BASE_URL ?>/modules/reports/export_csv.php" target="_blank">
                    <input type="hidden" name="report" value="sales">
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label small">Desde</label>
                            <input type="date" name="date_from" class="form-control form-control-sm"
                                   value="<?= date('Y-01-01') ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label small">Hasta</label>
                            <input type="date" name="date_to" class="form-control form-control-sm"
                                   value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label small">Agrupar por</label>
                            <select name="group_by" class="form-select form-select-sm">
                                <option value="day">Día</option>
                                <option value="week">Semana</option>
                                <option value="month" selected>Mes</option>
                            </select>
                        </div>
                    </div>
                    <hr>
                    <div class="d-flex gap-2 flex-wrap">
                        <button type="submit" name="format" value="csv" class="btn btn-sm btn-outline-success">
                            <i class="fa-solid fa-file-csv me-1"></i>CSV
                        </button>
                        <button type="submit" name="format" value="excel" class="btn btn-sm btn-outline-success"
                                formaction="<?= BASE_URL ?>/modules/reports/export_excel.php">
                            <i class="fa-solid fa-file-excel me-1"></i>Excel
                        </button>
                        <button type="submit" name="format" value="pdf" class="btn btn-sm btn-outline-danger"
                                formaction="<?= BASE_URL ?>/modules/reports/export_pdf.php">
                            <i class="fa-solid fa-file-pdf me-1"></i>PDF
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ── Reporte de productos ───────────────────────── -->
    <div class="col-md-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">
                <i class="fa-solid fa-box text-warning me-2"></i>Reporte de Productos
            </div>
            <div class="card-body">
                <form method="GET" action="<?= BASE_URL ?>/modules/reports/export_csv.php" target="_blank">
                    <input type="hidden" name="report" value="products">
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label small">Desde</label>
                            <input type="date" name="date_from" class="form-control form-control-sm"
                                   value="<?= date('Y-m-01') ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label small">Hasta</label>
                            <input type="date" name="date_to" class="form-control form-control-sm"
                                   value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label small">Categoría</label>
                            <select name="category" class="form-select form-select-sm">
                                <option value="">Todas</option>
                                <?php
                                $cats = DB::query("SELECT DISTINCT product_category FROM order_items ORDER BY product_category");
                                foreach ($cats as $cat):
                                ?>
                                <option value="<?= e($cat['product_category']) ?>">
                                    <?= categoryLabel($cat['product_category']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <hr>
                    <div class="d-flex gap-2 flex-wrap">
                        <button type="submit" name="format" value="csv" class="btn btn-sm btn-outline-success">
                            <i class="fa-solid fa-file-csv me-1"></i>CSV
                        </button>
                        <button type="submit" name="format" value="excel" class="btn btn-sm btn-outline-success"
                                formaction="<?= BASE_URL ?>/modules/reports/export_excel.php">
                            <i class="fa-solid fa-file-excel me-1"></i>Excel
                        </button>
                        <button type="submit" name="format" value="pdf" class="btn btn-sm btn-outline-danger"
                                formaction="<?= BASE_URL ?>/modules/reports/export_pdf.php">
                            <i class="fa-solid fa-file-pdf me-1"></i>PDF
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ── Reporte de clientes ───────────────────────── -->
    <div class="col-md-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">
                <i class="fa-solid fa-users text-info me-2"></i>Reporte de Clientes
            </div>
            <div class="card-body">
                <form method="GET" action="<?= BASE_URL ?>/modules/reports/export_csv.php" target="_blank">
                    <input type="hidden" name="report" value="customers">
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label small">Desde</label>
                            <input type="date" name="date_from" class="form-control form-control-sm"
                                   value="<?= date('Y-m-01') ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label small">Hasta</label>
                            <input type="date" name="date_to" class="form-control form-control-sm"
                                   value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label small">Tipo de cliente</label>
                            <select name="customer_type" class="form-select form-select-sm">
                                <option value="">Todos</option>
                                <option value="new">Nuevos (registrados en el período)</option>
                                <option value="recurring">Recurrentes (+1 pedido)</option>
                                <option value="inactive">Inactivos (+90 días)</option>
                            </select>
                        </div>
                    </div>
                    <hr>
                    <div class="d-flex gap-2 flex-wrap">
                        <button type="submit" name="format" value="csv" class="btn btn-sm btn-outline-success">
                            <i class="fa-solid fa-file-csv me-1"></i>CSV
                        </button>
                        <button type="submit" name="format" value="excel" class="btn btn-sm btn-outline-success"
                                formaction="<?= BASE_URL ?>/modules/reports/export_excel.php">
                            <i class="fa-solid fa-file-excel me-1"></i>Excel
                        </button>
                        <button type="submit" name="format" value="pdf" class="btn btn-sm btn-outline-danger"
                                formaction="<?= BASE_URL ?>/modules/reports/export_pdf.php">
                            <i class="fa-solid fa-file-pdf me-1"></i>PDF
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
