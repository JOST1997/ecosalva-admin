<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/Auth.php';
require_once __DIR__ . '/includes/functions.php';

Auth::start();

// Si ya está logueado, redirigir al dashboard
if (Auth::check()) {
    header('Location: ' . BASE_URL . '/modules/dashboard/index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!$email || !$password) {
        $error = 'Por favor ingresa tu correo y contraseña.';
    } elseif (!Auth::login($email, $password)) {
        $error = 'Credenciales incorrectas o cuenta inactiva.';
    } else {
        $redirect = $_GET['redirect'] ?? (BASE_URL . '/modules/dashboard/index.php');
        header('Location: ' . $redirect);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso | <?= APP_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/app.css">
</head>
<body>
<div class="login-page">
    <div class="card login-card shadow-lg">
        <div class="card-body p-5">
            <!-- Logo -->
            <div class="text-center mb-4">
                <div class="bg-ecosalva text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3"
                     style="width:64px;height:64px;font-size:1.8rem;">
                    <i class="fa-solid fa-leaf"></i>
                </div>
                <h4 class="fw-bold text-ecosalva mb-0"><?= APP_NAME ?></h4>
                <small class="text-muted">Panel de Monitoreo Operacional</small>
            </div>

            <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible" role="alert">
                <i class="fa-solid fa-circle-exclamation me-2"></i><?= e($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <form method="POST" action="" novalidate>
                <?= csrfInput() ?>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Correo electrónico</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa-solid fa-envelope text-muted"></i></span>
                        <input type="email" name="email" class="form-control"
                               value="<?= e($_POST['email'] ?? '') ?>"
                               placeholder="admin@ecosalva.com" required autofocus>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-semibold">Contraseña</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa-solid fa-lock text-muted"></i></span>
                        <input type="password" name="password" class="form-control"
                               placeholder="••••••••" required>
                        <button type="button" class="btn btn-outline-secondary" id="togglePass">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-ecosalva btn-lg fw-semibold">
                        <i class="fa-solid fa-right-to-bracket me-2"></i>Ingresar
                    </button>
                </div>
            </form>

            <hr class="my-4">
            <p class="text-center text-muted small mb-0">
                Acceso restringido a personal autorizado de EcoSalva
            </p>
        </div>
    </div>
</div>

<style>
.btn-ecosalva { background:#1a7c3e; color:#fff; border:none; }
.btn-ecosalva:hover { background:#145a2c; color:#fff; }
</style>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('togglePass').addEventListener('click', function() {
    const inp = this.previousElementSibling;
    const icon = this.querySelector('i');
    if (inp.type === 'password') {
        inp.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        inp.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
});
</script>
</body>
</html>
