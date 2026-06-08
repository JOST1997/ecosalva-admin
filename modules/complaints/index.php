<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/Auth.php';
require_once __DIR__ . '/../../includes/functions.php';

Auth::requireModule('complaints');
Auth::logActivity(Auth::id(), 'view_complaints', 'complaints');

$statusFilter = $_GET['status'] ?? '';
$typeFilter   = $_GET['type']   ?? '';
$search       = trim($_GET['q'] ?? '');
$dateFrom     = $_GET['date_from'] ?? '';
$dateTo       = $_GET['date_to']   ?? '';
$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = ITEMS_PER_PAGE;
$offset       = ($page - 1) * $perPage;

$where  = ['1=1'];
$params = [];

if ($statusFilter) { $where[] = 'status = :status'; $params[':status'] = $statusFilter; }
if ($typeFilter)   { $where[] = 'type = :type';     $params[':type']   = $typeFilter; }
if ($dateFrom)     { $where[] = 'DATE(created_at) >= :df'; $params[':df'] = $dateFrom; }
if ($dateTo)       { $where[] = 'DATE(created_at) <= :dt'; $params[':dt'] = $dateTo; }
if ($search)       { $where[] = "(case_number ILIKE :q OR name ILIKE :q OR email ILIKE :q OR subject ILIKE :q)"; $params[':q'] = "%$search%"; }

$whereStr = implode(' AND ', $where);

$total = (int)DB::scalar("SELECT COUNT(*) FROM complaints WHERE $whereStr", $params);

$paramsPage = array_merge($params, [':limit' => $perPage, ':offset' => $offset]);
$complaints = DB::query(
    "SELECT * FROM complaints WHERE $whereStr ORDER BY created_at DESC LIMIT :limit OFFSET :offset",
    $paramsPage
);

// KPIs
$kpis = DB::query(
    "SELECT status, COUNT(*) AS total FROM complaints GROUP BY status"
);
$kpiMap = [];
foreach ($kpis as $k) $kpiMap[$k['status']] = (int)$k['total'];

$pageTitle = 'Quejas y Reclamos';
$pageIcon  = 'fa-solid fa-triangle-exclamation';

include __DIR__ . '/../../includes/header.php';
?>

<!-- KPIs rápidos -->
<div class="row g-3 mb-4">
    <?php
    $cards = [
        ['OPEN',      'danger',  'fa-circle-exclamation', 'Abiertas'],
        ['IN_REVIEW', 'warning', 'fa-magnifying-glass',   'En Revisión'],
        ['RESOLVED',  'success', 'fa-circle-check',       'Resueltas'],
        ['CLOSED',    'secondary','fa-lock',              'Cerradas'],
    ];
    foreach ($cards as [$key, $color, $icon, $label]):
    ?>
    <div class="col-md-3">
        <div class="card kpi-card text-center">
            <div class="card-body py-3">
                <div class="text-<?= $color ?> mb-1" style="font-size:1.5rem;">
                    <i class="fa-solid <?= $icon ?>"></i>
                </div>
                <div class="fw-bold fs-4 text-<?= $color ?>"><?= $kpiMap[$key] ?? 0 ?></div>
                <div class="kpi-label"><?= $label ?></div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Filtros -->
<div class="filter-bar mb-4">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-md-3">
            <label class="form-label small mb-1">Buscar</label>
            <input type="text" name="q" class="form-control form-control-sm"
                   placeholder="Nº caso, nombre, email…" value="<?= e($search) ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label small mb-1">Estado</label>
            <select name="status" class="form-select form-select-sm">
                <option value="">Todos</option>
                <?php foreach (['OPEN','IN_REVIEW','RESOLVED','CLOSED'] as $s): ?>
                <option value="<?= $s ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= $s ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small mb-1">Tipo</label>
            <select name="type" class="form-select form-select-sm">
                <option value="">Todos</option>
                <?php foreach (['PRODUCT_QUALITY','LATE_DELIVERY','WRONG_ITEM','MISSING_ITEM','REFUND_REQUEST','OTHER'] as $t): ?>
                <option value="<?= $t ?>" <?= $typeFilter === $t ? 'selected' : '' ?>><?= $t ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small mb-1">Desde</label>
            <input type="date" name="date_from" class="form-control form-control-sm" value="<?= e($dateFrom) ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label small mb-1">Hasta</label>
            <input type="date" name="date_to" class="form-control form-control-sm" value="<?= e($dateTo) ?>">
        </div>
        <div class="col-md-1">
            <button type="submit" class="btn btn-sm btn-success w-100">
                <i class="fa-solid fa-search"></i>
            </button>
        </div>
    </form>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr>
                    <th>N° Caso</th>
                    <th>Tipo</th>
                    <th>Asunto</th>
                    <th>Cliente</th>
                    <th>N° Pedido</th>
                    <th>Estado</th>
                    <th>Fecha</th>
                </tr></thead>
                <tbody>
                <?php if (empty($complaints)): ?>
                <tr><td colspan="7" class="text-center py-4 text-muted">No se encontraron quejas.</td></tr>
                <?php else: ?>
                <?php foreach ($complaints as $c): ?>
                <tr>
                    <td><strong><?= e($c['case_number']) ?></strong></td>
                    <td><span class="badge bg-secondary"><?= e($c['type']) ?></span></td>
                    <td><?= e($c['subject']) ?></td>
                    <td>
                        <div><?= e($c['name']) ?></div>
                        <small class="text-muted"><?= e($c['email']) ?></small>
                    </td>
                    <td>
                        <?php if ($c['order_number']): ?>
                        <a href="<?= BASE_URL ?>/modules/orders/index.php?q=<?= urlencode($c['order_number']) ?>">
                            <?= e($c['order_number']) ?>
                        </a>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td><?= complaintStatusBadge($c['status']) ?></td>
                    <td><small><?= formatDate($c['created_at']) ?></small></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="mt-3">
    <?php
    $queryStr = http_build_query(array_diff_key($_GET, ['page' => '']));
    $url = BASE_URL . '/modules/complaints/index.php' . ($queryStr ? "?$queryStr" : '');
    echo buildPagination($total, $page, $perPage, $url);
    ?>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
