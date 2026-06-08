-- ============================================================
-- EcoSalva Admin Panel - Tablas adicionales
-- Base de datos: ecosalva_db (PostgreSQL)
-- Ejecutar una sola vez sobre la BD existente
-- ============================================================

-- ── 1. Roles del panel administrativo ────────────────────────
CREATE TABLE IF NOT EXISTS admin_roles (
    id          SERIAL PRIMARY KEY,
    name        VARCHAR(50)  NOT NULL UNIQUE,  -- 'super_admin', 'admin', 'viewer'
    label       VARCHAR(100) NOT NULL,
    description TEXT,
    created_at  TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);

INSERT INTO admin_roles (name, label, description) VALUES
  ('super_admin', 'Super Administrador', 'Acceso total, gestión de usuarios admin'),
  ('admin',       'Administrador',       'Acceso a reportes, pedidos y estadísticas'),
  ('viewer',      'Visor',               'Solo lectura — dashboard y reportes')
ON CONFLICT (name) DO NOTHING;

-- ── 2. Usuarios internos del panel ───────────────────────────
CREATE TABLE IF NOT EXISTS admin_users (
    id            UUID         PRIMARY KEY DEFAULT gen_random_uuid(),
    name          VARCHAR(100) NOT NULL,
    email         VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role_id       INT          NOT NULL REFERENCES admin_roles(id) ON DELETE RESTRICT,
    is_active     BOOLEAN      NOT NULL DEFAULT TRUE,
    last_login_at TIMESTAMPTZ,
    created_at    TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    updated_at    TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_admin_users_email   ON admin_users(email);
CREATE INDEX IF NOT EXISTS idx_admin_users_role_id ON admin_users(role_id);

-- Usuario super_admin inicial (password: Admin@1234)
-- Hash generado con password_hash en PHP 8.x (bcrypt)
INSERT INTO admin_users (name, email, password_hash, role_id)
SELECT
    'Super Admin',
    'admin@ecosalva.com',
    '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.',  -- password: password (reemplazar en prod)
    (SELECT id FROM admin_roles WHERE name = 'super_admin')
WHERE NOT EXISTS (SELECT 1 FROM admin_users WHERE email = 'admin@ecosalva.com');

-- ── 3. Bitácora de actividad del panel ───────────────────────
CREATE TABLE IF NOT EXISTS admin_activity_log (
    id          UUID        PRIMARY KEY DEFAULT gen_random_uuid(),
    admin_id    UUID        REFERENCES admin_users(id) ON DELETE SET NULL,
    action      VARCHAR(100) NOT NULL,   -- 'login', 'view_orders', 'export_csv', etc.
    module      VARCHAR(50),             -- 'dashboard', 'orders', 'reports', etc.
    description TEXT,
    ip_address  VARCHAR(45),
    user_agent  TEXT,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_admin_activity_admin_id  ON admin_activity_log(admin_id);
CREATE INDEX IF NOT EXISTS idx_admin_activity_created_at ON admin_activity_log(created_at);
CREATE INDEX IF NOT EXISTS idx_admin_activity_module    ON admin_activity_log(module);

-- ============================================================
-- Verificación
-- ============================================================
SELECT 'admin_roles'        AS tabla, COUNT(*) AS filas FROM admin_roles
UNION ALL
SELECT 'admin_users',        COUNT(*) FROM admin_users
UNION ALL
SELECT 'admin_activity_log', COUNT(*) FROM admin_activity_log;
