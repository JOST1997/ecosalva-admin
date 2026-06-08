<?php
// ============================================================
// Clase de autenticación para el panel admin
// ============================================================

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';

class Auth
{
    // Iniciar sesión y regenerar ID para prevenir session fixation
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function login(string $email, string $password): bool
    {
        self::start();

        $user = DB::queryOne(
            'SELECT au.*, ar.name AS role_name, ar.label AS role_label
               FROM admin_users au
               JOIN admin_roles ar ON ar.id = au.role_id
              WHERE au.email = :email AND au.is_active = TRUE',
            [':email' => strtolower(trim($email))]
        );

        if (!$user || !password_verify($password, $user['password_hash'])) {
            self::logActivity(null, 'login_failed', 'auth', "Intento fallido: $email");
            return false;
        }

        // Actualizar last_login_at
        DB::execute(
            'UPDATE admin_users SET last_login_at = NOW() WHERE id = :id',
            [':id' => $user['id']]
        );

        session_regenerate_id(true);

        $_SESSION['admin_id']        = $user['id'];
        $_SESSION['admin_name']      = $user['name'];
        $_SESSION['admin_email']     = $user['email'];
        $_SESSION['admin_role']      = $user['role_name'];
        $_SESSION['admin_role_label']= $user['role_label'];
        $_SESSION['logged_in']       = true;
        $_SESSION['login_time']      = time();

        self::logActivity($user['id'], 'login', 'auth', 'Inicio de sesión exitoso');

        return true;
    }

    public static function logout(): void
    {
        self::start();
        $adminId = $_SESSION['admin_id'] ?? null;
        self::logActivity($adminId, 'logout', 'auth', 'Cierre de sesión');

        $_SESSION = [];
        session_destroy();

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }
    }

    public static function check(): bool
    {
        self::start();
        return !empty($_SESSION['logged_in']) && !empty($_SESSION['admin_id']);
    }

    // Redirige al login si no está autenticado
    public static function require(): void
    {
        if (!self::check()) {
            header('Location: ' . BASE_URL . '/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
            exit;
        }
    }

    // Verificar permiso de módulo
    public static function can(string $module): bool
    {
        $role  = $_SESSION['admin_role'] ?? '';
        $perms = ROLE_PERMISSIONS[$role] ?? [];
        return in_array($module, $perms, true);
    }

    // Requiere permiso, redirige si no tiene acceso
    public static function requireModule(string $module): void
    {
        self::require();
        if (!self::can($module)) {
            http_response_code(403);
            include __DIR__ . '/../includes/header.php';
            echo '<div class="container mt-5"><div class="alert alert-danger">
                    <h4>Acceso denegado</h4>
                    <p>No tienes permisos para acceder a este módulo.</p>
                    <a href="' . BASE_URL . '/modules/dashboard/index.php" class="btn btn-primary">Ir al Dashboard</a>
                  </div></div>';
            include __DIR__ . '/../includes/footer.php';
            exit;
        }
    }

    public static function id(): ?string   { return $_SESSION['admin_id']    ?? null; }
    public static function name(): string  { return $_SESSION['admin_name']  ?? ''; }
    public static function role(): string  { return $_SESSION['admin_role']  ?? ''; }

    // Registra actividad en admin_activity_log
    public static function logActivity(
        ?string $adminId,
        string  $action,
        string  $module  = '',
        string  $description = ''
    ): void {
        try {
            DB::execute(
                'INSERT INTO admin_activity_log (admin_id, action, module, description, ip_address, user_agent)
                 VALUES (:admin_id, :action, :module, :description, :ip, :ua)',
                [
                    ':admin_id'    => $adminId,
                    ':action'      => $action,
                    ':module'      => $module,
                    ':description' => $description,
                    ':ip'          => $_SERVER['REMOTE_ADDR']  ?? null,
                    ':ua'          => $_SERVER['HTTP_USER_AGENT'] ?? null,
                ]
            );
        } catch (Throwable) {
            // No fallar si la tabla aún no existe
        }
    }
}
