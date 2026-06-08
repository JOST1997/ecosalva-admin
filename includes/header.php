<?php
// header.php — requiere $pageTitle (string) definido antes de incluir
if (!isset($pageTitle)) $pageTitle = 'Panel Admin';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> | <?= APP_NAME ?></title>

    <!-- Bootstrap 5.3 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <!-- App CSS -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/app.css">
</head>
<body>

<!-- Navbar superior -->
<nav class="navbar navbar-expand-lg navbar-dark bg-ecosalva fixed-top">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="<?= BASE_URL ?>/modules/dashboard/index.php">
            <i class="fa-solid fa-leaf me-2"></i><?= APP_NAME ?>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav ms-auto align-items-center gap-2">
                <li class="nav-item">
                    <span class="nav-link text-light opacity-75">
                        <i class="fa-solid fa-user-shield me-1"></i>
                        <?= e(Auth::name()) ?>
                        <span class="badge bg-light text-dark ms-1 small"><?= e($_SESSION['admin_role_label'] ?? '') ?></span>
                    </span>
                </li>
                <li class="nav-item">
                    <a class="btn btn-sm btn-outline-light" href="<?= BASE_URL ?>/logout.php">
                        <i class="fa-solid fa-right-from-bracket me-1"></i>Salir
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- Layout principal -->
<div class="wrapper d-flex">
    <!-- Sidebar -->
    <?php include __DIR__ . '/sidebar.php'; ?>

    <!-- Contenido principal -->
    <main class="main-content flex-grow-1 p-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="fw-bold mb-0 text-ecosalva">
                <?= isset($pageIcon) ? "<i class=\"{$pageIcon} me-2\"></i>" : '' ?>
                <?= e($pageTitle) ?>
            </h4>
            <small class="text-muted">
                <i class="fa-regular fa-clock me-1"></i>
                <?= date('d/m/Y H:i') ?>
            </small>
        </div>
