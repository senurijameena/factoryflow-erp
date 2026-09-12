<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/csrf.php';

init_session();

$appUrl = rtrim((string)Config::get('APP_URL', 'http://localhost/factoryflow'), '/');

if (!empty($_SESSION['user_id'])) {
    header('Location: ' . $appUrl . '/pages/dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= e(get_csrf_token()) ?>">
    <meta name="app-url" content="<?= e($appUrl) ?>">
    <title>Create Account | <?= e(APP_NAME) ?></title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="<?= e($appUrl) ?>/assets/css/style.css">
</head>
<body>
<div class="ff-auth-container">
    <div class="ff-auth-card">
        <div class="ff-auth-brand">
            <i class="fa-solid fa-industry"></i>
            <h2>FactoryFlow ERP</h2>
            <p>Create your enterprise user profile</p>
        </div>

        <div class="alert alert-danger py-2 small mb-3 form-error-alert d-none" role="alert"></div>

        <form id="registerForm" action="<?= e($appUrl) ?>/api/auth.php?action=register" method="POST" novalidate>
            <?= csrf_field() ?>

            <div class="mb-3">
                <label for="name" class="form-label small fw-semibold text-secondary">Full Name</label>
                <div class="input-group">
                    <span class="input-group-text bg-light text-muted"><i class="fa-regular fa-user"></i></span>
                    <input type="text" class="form-control" id="name" name="name" placeholder="John Doe" required autofocus autocomplete="name">
                </div>
            </div>

            <div class="mb-3">
                <label for="email" class="form-label small fw-semibold text-secondary">Work Email</label>
                <div class="input-group">
                    <span class="input-group-text bg-light text-muted"><i class="fa-regular fa-envelope"></i></span>
                    <input type="email" class="form-control" id="email" name="email" placeholder="name@company.com" required autocomplete="email">
                </div>
            </div>

            <div class="mb-3">
                <label for="password" class="form-label small fw-semibold text-secondary">Password</label>
                <div class="input-group">
                    <span class="input-group-text bg-light text-muted"><i class="fa-solid fa-lock"></i></span>
                    <input type="password" class="form-control" id="password" name="password" placeholder="••••••••" required autocomplete="new-password">
                </div>
                <div class="form-text small text-muted">Minimum 8 characters with at least 1 number.</div>
            </div>

            <div class="mb-4">
                <label for="password_confirm" class="form-label small fw-semibold text-secondary">Confirm Password</label>
                <div class="input-group">
                    <span class="input-group-text bg-light text-muted"><i class="fa-solid fa-shield-halved"></i></span>
                    <input type="password" class="form-control" id="password_confirm" name="password_confirm" placeholder="••••••••" required autocomplete="new-password">
                </div>
            </div>

            <div class="d-grid mb-3">
                <button type="submit" class="btn btn-primary py-2 fw-semibold">
                    <i class="fa-solid fa-user-plus me-2"></i> Register Account
                </button>
            </div>

            <div class="text-center small text-muted">
                Already registered? <a href="<?= e($appUrl) ?>/pages/auth/login.php" class="fw-semibold text-decoration-none">Sign In</a>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="<?= e($appUrl) ?>/assets/js/app.js"></script>
<script>
    App.bindAjaxForm('registerForm', function (res) {
        App.toast('success', res.data.message || 'Account created successfully!');
        setTimeout(function () {
            if (res && res.data && res.data.redirect) {
                window.location.href = res.data.redirect;
            } else {
                window.location.href = '<?= e($appUrl) ?>/pages/dashboard.php';
            }
        }, 800);
    });
</script>
</body>
</html>
