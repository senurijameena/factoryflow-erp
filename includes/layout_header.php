<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/rbac.php';

require_auth();

$appUrl = rtrim((string)Config::get('APP_URL', 'http://localhost/factoryflow'), '/');
$userName = $_SESSION['user_name'] ?? 'User';
$userEmail = $_SESSION['user_email'] ?? '';
$userRole = $_SESSION['user_role'] ?? ROLE_WORKER;
$pageTitle = $pageTitle ?? 'Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= e(get_csrf_token()) ?>">
    <meta name="app-url" content="<?= e($appUrl) ?>">
    <title><?= e($pageTitle) ?> | <?= e(APP_NAME) ?></title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="<?= e($appUrl) ?>/assets/css/style.css">
</head>
<body>
<div class="ff-app-wrapper">
    <?php require_once __DIR__ . '/layout_sidebar.php'; ?>

    <div class="ff-main">
        <header class="ff-topbar">
            <div class="d-flex align-items-center">
                <button type="button" class="btn btn-sm btn-light border d-lg-none me-2" id="sidebarToggleBtn" aria-label="Toggle navigation">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <h1 class="ff-topbar-title"><?= e($pageTitle) ?></h1>
            </div>

            <div class="d-flex align-items-center">
                <?php if (Config::get('APP_ENV') === 'local'): ?>
                    <span class="badge bg-secondary-subtle text-secondary me-3 d-none d-sm-inline-block">ENV: LOCAL</span>
                <?php endif; ?>

                <div class="dropdown">
                    <button class="btn btn-link text-decoration-none p-0 d-flex align-items-center dropdown-toggle text-dark" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <div class="avatar-bubble me-2"><?= e(strtoupper(substr($userName, 0, 1))) ?></div>
                        <div class="d-none d-md-block text-start me-2">
                            <div class="fw-semibold text-dark small" style="line-height: 1.2;"><?= e($userName) ?></div>
                            <span class="role-badge role-<?= e($userRole) ?>"><?= e($userRole) ?></span>
                        </div>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm" aria-labelledby="userDropdown">
                        <li class="px-3 py-2 border-bottom">
                            <div class="fw-semibold small"><?= e($userName) ?></div>
                            <div class="text-muted small text-truncate" style="max-width: 200px;"><?= e($userEmail) ?></div>
                            <div class="mt-1"><span class="role-badge role-<?= e($userRole) ?>"><?= e(strtoupper($userRole)) ?></span></div>
                        </li>
                        <li>
                            <button class="dropdown-item text-danger py-2" type="button" data-action="logout">
                                <i class="fa-solid fa-right-from-bracket me-2"></i> Sign Out
                            </button>
                        </li>
                    </ul>
                </div>
            </div>
        </header>

        <main class="ff-content">
