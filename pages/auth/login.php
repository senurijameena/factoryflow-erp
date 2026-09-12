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

$timeoutError = (isset($_GET['error']) && $_GET['error'] === 'timeout');
$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= e(get_csrf_token()) ?>">
    <meta name="app-url" content="<?= e($appUrl) ?>">
    <title>Sign In | <?= e(APP_NAME) ?></title>

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
            <p>Enter your credentials to access your workspace</p>
        </div>

        <?php if ($timeoutError): ?>
            <div class="alert alert-warning py-2 small mb-3">
                <i class="fa-solid fa-clock-rotate-left me-1"></i> Your session has expired. Please sign in again.
            </div>
        <?php endif; ?>

        <?php if ($flash): ?>
            <div class="alert alert-<?= e($flash['type']) ?> py-2 small mb-3">
                <?= e($flash['message']) ?>
            </div>
        <?php endif; ?>

        <div class="alert alert-danger py-2 small mb-3 form-error-alert d-none" role="alert"></div>

        <form id="loginForm" action="<?= e($appUrl) ?>/api/auth.php?action=login" method="POST" novalidate>
            <?= csrf_field() ?>

            <div class="mb-3">
                <label for="email" class="form-label small fw-semibold text-secondary">Work Email</label>
                <div class="input-group">
                    <span class="input-group-text bg-light text-muted"><i class="fa-regular fa-envelope"></i></span>
                    <input type="email" class="form-control" id="email" name="email" placeholder="name@company.com" required autofocus autocomplete="email">
                </div>
            </div>

            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <label for="password" class="form-label small fw-semibold text-secondary mb-0">Password</label>
                    <a href="<?= e($appUrl) ?>/pages/auth/forgot_password.php" class="small text-decoration-none">Forgot password?</a>
                </div>
                <div class="input-group">
                    <span class="input-group-text bg-light text-muted"><i class="fa-solid fa-lock"></i></span>
                    <input type="password" class="form-control" id="password" name="password" placeholder="••••••••" required autocomplete="current-password">
                </div>
            </div>

            <div class="mb-4 form-check">
                <input type="checkbox" class="form-check-input" id="remember" name="remember">
                <label class="form-check-label small text-secondary" for="remember">Keep me logged in</label>
            </div>

            <div class="d-grid mb-3">
                <button type="submit" class="btn btn-primary py-2 fw-semibold">
                    <i class="fa-solid fa-right-to-bracket me-2"></i> Sign In
                </button>
            </div>

            <div class="text-center small text-muted">
                Need an account? <a href="<?= e($appUrl) ?>/pages/auth/register.php" class="fw-semibold text-decoration-none">Register</a>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="<?= e($appUrl) ?>/assets/js/app.js"></script>
<script>
    App.bindAjaxForm('loginForm', function (res) {
        if (res && res.data && res.data.redirect) {
            window.location.href = res.data.redirect;
        } else {
            window.location.href = '<?= e($appUrl) ?>/pages/dashboard.php';
        }
    });
</script>
</body>
</html>
