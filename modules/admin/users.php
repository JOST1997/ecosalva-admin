<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/Auth.php';
require_once __DIR__ . '/../../includes/functions.php';

Auth::requireModule('admin');

$error   = '';
$success = '';

// ── Crear usuario ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Token de seguridad inválido.';
    } else {
        $name    = trim($_POST['name']    ?? '');
        $email   = strtolower(trim($_POST['email'] ?? ''));
        $pass    = $_POST['password'] ?? '';
        $roleId  = (int)($_POST['role_id'] ?? 0);

        if (!$name || !$email || !$pass || !$roleId) {
            $error = 'Todos los campos son requeridos.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Email inválido.';
        } elseif (strlen($pass) < 8) {
            $error = 'La contraseña debe tener al menos 8 caracteres.';
        } else {
            $exists = DB::scalar('SELECT COUNT(*) FROM admin_users WHERE email = :e', [':e' => $email]);
            if ($exists) {
                $error = 'Ya existe un usuario con ese email.';
            } else {
                $hash = password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]);
                DB::execute(
                    'INSERT INTO admin_users (name, email, password_hash, role_id) VALUES (:n,:e,:h,:r)',
                    [':n' => $name, ':e' => $email, ':h' => $hash, ':r' => $roleId]
                );
                Auth::logActivity(Auth::id(), 'create_admin_user', 'admin', "Creó usuario: $email");
                $success = 'Usuario creado correctamente.';
            }
        }
    }
}

// ── Cambiar estado activo/inactivo ────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Token de seguridad inválido.';
    } elseif ($_POST['user_id'] !== Auth::id()) { // no puede desactivarse a sí mismo
        $uid = $_POST['user_id'];
        DB::execute('UPDATE admin_users SET is_active = NOT is_active, updated_at = NOW() WHERE id = :id', [':id' => $uid]);
        Auth::logActivity(Auth::id(), 'toggle_admin_user', 'admin', "Toggle usuario: $uid");
        $success = 'Estado actualizado.';
    }
}

// ── Cambiar contraseña ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change_password') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Token de seguridad inválido.';
    } else {
        $uid  = $_POST['user_id'] ?? '';
        $pass = $_POST['new_password'] ?? '';
        if (!$uid || strlen($pass) < 8) {
            $error = 'Contraseña mínima de 8 caracteres.';
        } else {
            $hash = password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]);
            DB::execute('UPDATE admin_users SET password_hash = :h, updated_at = NOW() WHERE id = :id',
                [':h' => $hash, ':id' => $uid]);
            Auth::logActivity(Auth::id(), 'change_admin_password', 'admin', "Cambió contraseña: $uid");
            $success = 'Contraseña actualizada.';
        }
    }
}

// ── Listar usuarios ───────────────────────────────────────
$users = DB::query(
    'SELECT au.*, ar.name AS role_name, ar.label AS role_label
       FROM admin_users au JOIN admin_roles ar ON ar.id = au.role_id
      ORDER BY au.created_at DESC'
);

$roles = DB::query('SELECT * FROM admin_roles ORDER BY id');

Auth::logActivity(Auth::id(), 'view_admin_users', 'admin');

$pageTitle = 'Gestión de Usuarios Admin';
$pageIcon  = 'fa-solid fa-users-gear';

include __DIR__ . '/../../includes/header.php';
?>

<?php if ($error):   ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>

<div class="row g-4">

    <!-- Tabla de usuarios -->
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-semibold">Usuarios del Panel</span>
                <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#modalCreate">
                    <i class="fa-solid fa-user-plus me-1"></i>Nuevo Usuario
                </button>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr>
                        <th>Nombre</th>
                        <th>Email</th>
                        <th>Rol</th>
                        <th>Estado</th>
                        <th>Último acceso</th>
                        <th>Acciones</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($users as $u): ?>
                    <tr>
                        <td class="fw-semibold"><?= e($u['name']) ?></td>
                        <td><small><?= e($u['email']) ?></small></td>
                        <td>
                            <span class="badge bg-<?= $u['role_name'] === 'super_admin' ? 'danger' : ($u['role_name'] === 'admin' ? 'primary' : 'secondary') ?>">
                                <?= e($u['role_label']) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($u['is_active']): ?>
                            <span class="badge bg-success">Activo</span>
                            <?php else: ?>
                            <span class="badge bg-secondary">Inactivo</span>
                            <?php endif; ?>
                        </td>
                        <td><small><?= $u['last_login_at'] ? formatDate($u['last_login_at']) : 'Nunca' ?></small></td>
                        <td>
                            <?php if ($u['id'] !== Auth::id()): ?>
                            <!-- Toggle activo -->
                            <form method="POST" class="d-inline">
                                <?= csrfInput() ?>
                                <input type="hidden" name="action"  value="toggle">
                                <input type="hidden" name="user_id" value="<?= e($u['id']) ?>">
                                <button type="submit" class="btn btn-sm btn-outline-<?= $u['is_active'] ? 'warning' : 'success' ?>"
                                        title="<?= $u['is_active'] ? 'Desactivar' : 'Activar' ?>">
                                    <i class="fa-solid fa-<?= $u['is_active'] ? 'toggle-on' : 'toggle-off' ?>"></i>
                                </button>
                            </form>
                            <!-- Cambiar contraseña -->
                            <button class="btn btn-sm btn-outline-secondary ms-1"
                                    data-bs-toggle="modal" data-bs-target="#modalPass"
                                    data-uid="<?= e($u['id']) ?>" data-name="<?= e($u['name']) ?>">
                                <i class="fa-solid fa-key"></i>
                            </button>
                            <?php else: ?>
                            <span class="text-muted small">(tú)</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Roles disponibles -->
    <div class="col-lg-4">
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold">
                <i class="fa-solid fa-shield-halved me-2 text-primary"></i>Roles y Permisos
            </div>
            <div class="card-body">
                <?php foreach ($roles as $r): ?>
                <div class="mb-3">
                    <div class="fw-semibold"><?= e($r['label']) ?></div>
                    <small class="text-muted"><?= e($r['description']) ?></small>
                    <div class="mt-1">
                        <?php
                        $perms = ROLE_PERMISSIONS[$r['name']] ?? [];
                        foreach ($perms as $p):
                        ?>
                        <span class="badge bg-light text-dark border me-1"><?= $p ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal: crear usuario -->
<div class="modal fade" id="modalCreate" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <?= csrfInput() ?>
            <input type="hidden" name="action" value="create">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa-solid fa-user-plus me-2"></i>Nuevo Usuario Admin</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Nombre completo</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Contraseña</label>
                    <input type="password" name="password" class="form-control"
                           minlength="8" required placeholder="Mínimo 8 caracteres">
                </div>
                <div class="mb-3">
                    <label class="form-label">Rol</label>
                    <select name="role_id" class="form-select" required>
                        <?php foreach ($roles as $r): ?>
                        <option value="<?= $r['id'] ?>"><?= e($r['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-success">
                    <i class="fa-solid fa-check me-1"></i>Crear Usuario
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: cambiar contraseña -->
<div class="modal fade" id="modalPass" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <?= csrfInput() ?>
            <input type="hidden" name="action" value="change_password">
            <input type="hidden" name="user_id" id="passUserId">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa-solid fa-key me-2"></i>Cambiar Contraseña</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Usuario: <strong id="passUserName"></strong></p>
                <div class="mb-3">
                    <label class="form-label">Nueva contraseña</label>
                    <input type="password" name="new_password" class="form-control"
                           minlength="8" required placeholder="Mínimo 8 caracteres">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-warning">Actualizar</button>
            </div>
        </form>
    </div>
</div>

<?php
$extraScripts = <<<'JS'
<script>
document.getElementById('modalPass').addEventListener('show.bs.modal', function(e) {
    const btn = e.relatedTarget;
    document.getElementById('passUserId').value  = btn.dataset.uid;
    document.getElementById('passUserName').textContent = btn.dataset.name;
});
</script>
JS;
include __DIR__ . '/../../includes/footer.php';
?>
