<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/Auth.php';
require_once __DIR__ . '/../../includes/functions.php';

Auth::requireModule('admin');

// ── Filtros ───────────────────────────────────────────────
$module   = $_GET['module']    ?? '';
$action   = $_GET['action']    ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo   = $_GET['date_to']   ?? '';
$search   = trim($_GET['q']    ?? '');
$logType  = $_GET['log_type']  ?? 'admin'; // 'admin' = admin_activity_log, 'audit' = audit_logs, 'auth' = auth_logs
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = ITEMS_PER_PAGE;
$offset   = ($page - 1) * $perPage;

$rows  = [];
$total = 0;

switch ($logType) {

    case 'auth':
        // Logs de autenticación de clientes (auth_logs)
        $where  = ['1=1'];
        $params = [];
        if ($dateFrom) { $where[] = 'DATE(created_at) >= :df'; $params[':df'] = $dateFrom; }
        if ($dateTo)   { $where[] = 'DATE(created_at) <= :dt'; $params[':dt'] = $dateTo; }
        if ($search)   { $where[] = "(email ILIKE :q OR ip_address ILIKE :q)"; $params[':q'] = "%$search%"; }
        $whereStr = implode(' AND ', $where);

        $total = (int)DB::scalar("SELECT COUNT(*) FROM auth_logs WHERE $whereStr", $params);
        $rows  = DB::query(
            "SELECT al.email, al.ip_address, al.user_agent, al.status, al.reason, al.created_at,
                    u.name AS user_name
               FROM auth_logs al LEFT JOIN users u ON u.id = al.user_id
              WHERE $whereStr ORDER BY al.created_at DESC LIMIT :limit OFFSET :offset",
            array_merge($params, [':limit' => $perPage, ':offset' => $offset])
        );
        break;

    case 'audit':
        // Auditoría de cambios en entidades del sistema (audit_logs)
        $where  = ['1=1'];
        $params = [];
        if ($dateFrom) { $where[] = 'DATE(created_at) >= :df'; $params[':df'] = $dateFrom; }
        if ($dateTo)   { $where[] = 'DATE(created_at) <= :dt'; $params[':dt'] = $dateTo; }
        if ($search)   { $where[] = "(entity_type ILIKE :q OR entity_id ILIKE :q)"; $params[':q'] = "%$search%"; }
        $whereStr = implode(' AND ', $where);

        $total = (int)DB::scalar("SELECT COUNT(*) FROM audit_logs WHERE $whereStr", $params);
        $rows  = DB::query(
            "SELECT al.entity_type, al.entity_id, al.action, al.user_id,
                    al.ip_address, al.created_at,
                    u.name AS actor_name
               FROM audit_logs al LEFT JOIN users u ON u.id = al.user_id::uuid
              WHERE $whereStr ORDER BY al.created_at DESC LIMIT :limit OFFSET :offset",
            array_merge($params, [':limit' => $perPage, ':offset' => $offset])
        );
        break;

    default:
        // Bitácora del panel administrativo (admin_activity_log)
        $where  = ['1=1'];
        $params = [];
        if ($module)   { $where[] = 'aal.module = :module'; $params[':module'] = $module; }
        if ($dateFrom) { $where[] = 'DATE(aal.created_at) >= :df'; $params[':df'] = $dateFrom; }
        if ($dateTo)   { $where[] = 'DATE(aal.created_at) <= :dt'; $params[':dt'] = $dateTo; }
        if ($search)   { $where[] = "(aal.action ILIKE :q OR aal.description ILIKE :q OR au.email ILIKE :q)"; $params[':q'] = "%$search%"; }
        $whereStr = implode(' AND ', $where);

        $total = (int)DB::scalar(
            "SELECT COUNT(*) FROM admin_activity_log aal LEFT JOIN admin_users au ON au.id = aal.admin_id WHERE $whereStr",
            $params
        );
        $rows = DB::query(
            "SELECT aal.action, aal.module, aal.description, aal.ip_address, aal.created_at,
                    au.name AS admin_name, au.email AS admin_email
               FROM admin_activity_log aal
               LEFT JOIN admin_users au ON au.id = aal.admin_id
              WHERE $whereStr ORDER BY aal.created_at DESC LIMIT :limit OFFSET :offset",
            array_merge($params, [':limit' => $perPage, ':offset' => $offset])
        );
        break;
}

// Módulos disponibles para filtro
$modules = DB::query('SELECT DISTINCT module FROM admin_activity_log WHERE module IS NOT NULL ORDER BY module');

$pageTitle = 'Bitácora de Auditoría';
$pageIcon  = 'fa-solid fa-scroll';

include __DIR__ . '/../../includes/header.php';
?>

<!-- Tabs de tipo de log -->
<ul class="nav nav-tabs mb-4">
    <li class="nav-item">
        <a class="nav-link <?= $logType === 'admin' ? 'active' : '' ?>"
           href="?log_type=admin">
            <i class="fa-solid fa-user-shield me-1"></i>Panel Admin
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $logType === 'audit' ? 'active' : '' ?>"
           href="?log_type=audit">
            <i class="fa-solid fa-database me-1"></i>Cambios en BD
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $logType === 'auth' ? 'active' : '' ?>"
           href="?log_type=auth">
            <i class="fa-solid fa-right-to-bracket me-1"></i>Autenticación Clientes
        </a>
    </li>
</ul>

<!-- Filtros -->
<div class="filter-bar mb-4">
    <form method="GET" class="row g-2 align-items-end">
        <input type="hidden" name="log_type" value="<?= e($logType) ?>">
        <div class="col-md-4">
            <label class="form-label small mb-1">Buscar</label>
            <input type="text" name="q" class="form-control form-control-sm"
                   placeholder="Acción, descripción, email…" value="<?= e($search) ?>">
        </div>
        <?php if ($logType === 'admin'): ?>
        <div class="col-md-2">
            <label class="form-label small mb-1">Módulo</label>
            <select name="module" class="form-select form-select-sm">
                <option value="">Todos</option>
                <?php foreach ($modules as $m): ?>
                <option value="<?= e($m['module']) ?>" <?= $module === $m['module'] ? 'selected' : '' ?>>
                    <?= e($m['module']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <div class="col-md-2">
            <label class="form-label small mb-1">Desde</label>
            <input type="date" name="date_from" class="form-control form-control-sm" value="<?= e($dateFrom) ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label small mb-1">Hasta</label>
            <input type="date" name="date_to" class="form-control form-control-sm" value="<?= e($dateTo) ?>">
        </div>
        <div class="col-md-auto">
            <button type="submit" class="btn btn-sm btn-success">
                <i class="fa-solid fa-search"></i> Filtrar
            </button>
            <a href="?log_type=<?= e($logType) ?>" class="btn btn-sm btn-outline-secondary ms-1">Limpiar</a>
        </div>
    </form>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between">
        <span class="fw-semibold">
            <span class="badge bg-secondary"><?= number_format($total) ?></span> registros
        </span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <?php if ($logType === 'admin'): ?>
                <thead><tr>
                    <th>Admin</th><th>Módulo</th><th>Acción</th>
                    <th>Descripción</th><th>IP</th><th>Fecha</th>
                </tr></thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                <tr>
                    <td>
                        <div class="small fw-semibold"><?= e($r['admin_name'] ?? '—') ?></div>
                        <small class="text-muted"><?= e($r['admin_email'] ?? '') ?></small>
                    </td>
                    <td><span class="badge bg-light text-dark border"><?= e($r['module'] ?? '') ?></span></td>
                    <td><code class="text-success"><?= e($r['action']) ?></code></td>
                    <td class="small"><?= e($r['description'] ?? '') ?></td>
                    <td><small class="text-muted"><?= e($r['ip_address'] ?? '') ?></small></td>
                    <td><small><?= formatDate($r['created_at']) ?></small></td>
                </tr>
                <?php endforeach; ?>
                </tbody>

                <?php elseif ($logType === 'audit'): ?>
                <thead><tr>
                    <th>Entidad</th><th>ID Entidad</th><th>Acción</th>
                    <th>Actor</th><th>IP</th><th>Fecha</th>
                </tr></thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                <tr>
                    <td><span class="badge bg-info text-dark"><?= e($r['entity_type']) ?></span></td>
                    <td><code class="small"><?= e(substr($r['entity_id'] ?? '', 0, 8)) ?>…</code></td>
                    <td><span class="badge bg-warning text-dark"><?= e($r['action']) ?></span></td>
                    <td><small><?= e($r['actor_name'] ?? $r['user_id'] ?? 'Sistema') ?></small></td>
                    <td><small><?= e($r['ip_address'] ?? '') ?></small></td>
                    <td><small><?= formatDate($r['created_at']) ?></small></td>
                </tr>
                <?php endforeach; ?>
                </tbody>

                <?php else: // auth ?>
                <thead><tr>
                    <th>Email</th><th>Usuario</th><th>Estado</th>
                    <th>Razón</th><th>IP</th><th>Fecha</th>
                </tr></thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= e($r['email']) ?></td>
                    <td><small><?= e($r['user_name'] ?? '—') ?></small></td>
                    <td>
                        <span class="badge bg-<?= $r['status'] === 'SUCCESS' ? 'success' : 'danger' ?>">
                            <?= e($r['status']) ?>
                        </span>
                    </td>
                    <td><small class="text-muted"><?= e($r['reason'] ?? '') ?></small></td>
                    <td><small><?= e($r['ip_address'] ?? '') ?></small></td>
                    <td><small><?= formatDate($r['created_at']) ?></small></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>

<div class="mt-3">
    <?php
    $queryStr = http_build_query(array_diff_key($_GET, ['page' => '']));
    $url = BASE_URL . '/modules/admin/audit.php' . ($queryStr ? "?$queryStr" : '');
    echo buildPagination($total, $page, $perPage, $url);
    ?>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
