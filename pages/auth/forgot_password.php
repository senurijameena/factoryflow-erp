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
    <title>Forgot Password | <?= e(APP_NAME) ?></title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="<?= e($appUrl) ?>/assets/css/style.css">
</head>
<body>
<div class="ff-auth-container">
    <div class="ff-auth-card">
        <div class="ff-auth-brand">
            <i class="fa-solid fa-key"></i>
            <h2>Password Recovery</h2>
            <p>Enter your email to receive recovery instructions</p>
        </div>

        <div class="alert alert-danger py-2 small mb-3 form-error-alert d-none" role="alert"></div>
        <div class="alert alert-success py-2 small mb-3 d-none" id="successNotice" role="alert"></div>

        <form id="forgotForm" action="<?= e($appUrl) ?>/api/auth.php?action=forgot_password" method="POST" novalidate>
            <?= csrf_field() ?>

            <div class="mb-4">
                <label for="email" class="form-label small fw-semibold text-secondary">Registered Email</label>
                <div class="input-group">
                    <span class="input-group-text bg-light text-muted"><i class="fa-regular fa-envelope"></i></span>
                    <input type="email" class="form-control" id="email" name="email" placeholder="name@company.com" required autofocus autocomplete="email">
                </div>
            </div>

            <div class="d-grid mb-3">
                <button type="submit" class="btn btn-primary py-2 fw-semibold">
                    <i class="fa-solid fa-paper-plane me-2"></i> Send Reset Link
                </button>
            </div>

            <div class="text-center small text-muted">
                Remember your credentials? <a href="<?= e($appUrl) ?>/pages/auth/login.php" class="fw-semibold text-decoration-none">Return to Sign In</a>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="<?= e($appUrl) ?>/assets/js/app.js"></script>
<script>
    App.bindAjaxForm('forgotForm', function (res) {
        const notice = document.getElementById('successNotice');
        const form = document.getElementById('forgotForm');
        if (notice) {
            notice.textContent = res.data.message || 'If an account exists with this email address, password reset instructions have been dispatched.';
            notice.classList.remove('d-none');
        }
        form.reset();
        App.toast('info', 'Instructions dispatched if account exists.');
    });
</script>
</body>
</html>
