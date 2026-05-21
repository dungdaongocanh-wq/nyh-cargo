<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? APP_NAME) ?> | <?= APP_NAME ?></title>
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="<?= BASE_URL ?>assets/css/app.css" rel="stylesheet">
</head>
<body>

<!-- TOP NAVBAR -->
<nav class="navbar navbar-dark bg-dark-nyh px-3 fixed-top" style="height:56px; z-index:1050;">
    <div class="d-flex align-items-center gap-2">
        <!-- Sidebar toggle (mobile) -->
        <button class="btn btn-sm btn-outline-secondary d-lg-none" id="sidebarToggle">
            <i class="bi bi-list"></i>
        </button>
        <a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="<?= BASE_URL ?>index.php">
            <img src="<?= BASE_URL ?>assets/img/logo.png" alt="Logo" height="32"
                 onerror="this.style.display='none'">
            <span class="text-white"><?= APP_NAME ?></span>
        </a>
    </div>
    <div class="d-flex align-items-center gap-3">
        <span class="text-white-50 small d-none d-md-inline">
            <i class="bi bi-person-circle me-1"></i>
            <?= e(currentUserName()) ?>
            <span class="badge bg-secondary ms-1"><?= ucfirst(currentUserRole()) ?></span>
        </span>
        <div class="dropdown">
            <button class="btn btn-sm btn-outline-light dropdown-toggle" data-bs-toggle="dropdown">
                <i class="bi bi-gear"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="<?= BASE_URL ?>profile.php">
                    <i class="bi bi-person me-2"></i>Profile</a></li>
                <li><a class="dropdown-item" href="<?= BASE_URL ?>change-password.php">
                    <i class="bi bi-key me-2"></i>Change Password</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger" href="<?= BASE_URL ?>logout.php">
                    <i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
            </ul>
        </div>
    </div>
</nav>

<!-- WRAPPER -->
<div class="wrapper d-flex" style="margin-top:56px; min-height:calc(100vh - 56px);">