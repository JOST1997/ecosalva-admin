<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/Auth.php';

Auth::start();
Auth::logout();

header('Location: ' . BASE_URL . '/login.php?msg=logout');
exit;
