<?php
if (defined('APP_NAME')) return;
// ============================================================
// Configuración general de la aplicación
// ============================================================

define('APP_NAME',    'EcoSalva Admin');
define('APP_VERSION', '1.0.0');
define('BASE_URL',    '');   // Servidor PHP built-in sirve desde la raíz

// Zona horaria
date_default_timezone_set('America/Lima');

// Sesión segura
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Strict');

// Mostrar errores solo en desarrollo
if (getenv('APP_ENV') === 'production' || !getenv('APP_ENV')) {
    ini_set('display_errors', 0);
    error_reporting(0);
} else {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
}

// Roles
define('ROLE_SUPER_ADMIN', 'super_admin');
define('ROLE_ADMIN',       'admin');
define('ROLE_VIEWER',      'viewer');

// Módulos permitidos por rol
define('ROLE_PERMISSIONS', [
    ROLE_SUPER_ADMIN => ['dashboard','orders','statistics','reports','complaints','admin'],
    ROLE_ADMIN       => ['dashboard','orders','statistics','reports','complaints'],
    ROLE_VIEWER      => ['dashboard','reports'],
]);

// Moneda
define('CURRENCY_SYMBOL', 'S/');
define('CURRENCY_CODE',   'PEN');

// Paginación
define('ITEMS_PER_PAGE', 25);
