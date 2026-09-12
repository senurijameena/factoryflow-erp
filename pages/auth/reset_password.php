<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/csrf.php';

init_session();

$appUrl = rtrim((string)Config::get('APP_URL', 'http://localhost/factoryflow'), '/');

if (!empty($_SESSION['user_id'])) {
    header('Location: ' . $appUrl . '/pages/dashboard.php');
    exit;
}

$token = trim((string)($_GET['token'] ?? ''));
$isValidToken = false;
$tokenError = null;

if ($token === '' || strlen($token) !== 64) {
    $tokenError = 'Invalid or missing password reset token.';
} else {
    try {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            SELECT id 
            FROM password_resets 
            WHERE token = :token AND used = 0 AND expires_at > NOW() 
            LIMIT 1
        ");
        $stmt->execute([':token' => $token]);
        if ($stmt->fetch()) {
            $isValidToken = true;
        } else {
            $tokenError = 'This password reset link has expired or has already been used.';
        }
    } catch (Exception $e) {
        $tokenError = 'Unable to verify reset link at this time.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= e(get_csrf_token()) ?>">
    <meta name="app-url" content="<?= e($appUrl) ?>">
    <title>Reset Password | <?= e(APP_NAME) ?></title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="<?= e($appUrl) ?>/assets/css/style.css">
</head>
<body>
<div class="ff-auth-container">
    <div class="ff-auth-card">
        <div class="ff-auth-brand">
            <i class="fa-solid fa-lock-open"></i>
            <h2>Set New Password</h2>
            <p>Please enter your updated security credentials</p>
        </div>

        <?php if (!$isValidToken): ?>
            <div class="alert alert-danger py-3 text-center mb-4">
                <i class="fa-solid fa-triangle-exclamation mb-2 d-block" style="font-size: 1.75rem;"></i>
                <div class="fw-semibold"><?= e($tokenError) ?></div>
            </div>
            <div class="text-center">
                <a href="<?= e($appUrl) ?>/pages/auth/forgot_password.php" class="btn btn-primary py-2 px-3 fw-semibold">
                    Request a New Reset Link
                </a>
                <div class="mt-3">
                    <a href="<?= e($appUrl) ?>/pages/auth/login.php" class="small text-decoration-none text-muted">Back to Sign In</a>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-danger py-2 small mb-3 form-error-alert d-none" role="alert"></div>

            <form id="resetForm" action="<?= e($appUrl) ?>/api/auth.php?action=reset_password" method="POST" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="token" value="<?= e($token) ?>">

                <div class="mb-3">
                    <label for="password" class="form-label small fw-semibold text-secondary">New Password</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light text-muted"><i class="fa-solid fa-lock"></i></span>
                        <input type="password" class="form-control" id="password" name="password" placeholder="••••••••" required autofocus autocomplete="new-password">
                    </div>
                    <div class="form-text small text-muted">Minimum 8 characters with at least 1 number.</div>
                </div>

                <div class="mb-4">
                    <label for="password_confirm" class="form-label small fw-semibold text-secondary">Confirm New Password</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light text-muted"><i class="fa-solid fa-shield-halved"></i></span>
                        <input type="password" class="form-control" id="password_confirm" name="password_confirm" placeholder="••••••••" required autocomplete="new-password">
                    </div>
                </div>

                <div class="d-grid mb-3">
                    <button type="submit" class="btn btn-primary py-2 fw-semibold">
                        <i class="fa-solid fa-check me-2"></i> Update Password
                    </button>
                </div>

                <div class="text-center small text-muted">
                    <a href="<?= e($appUrl) ?>/pages/auth/login.php" class="text-decoration-none">Cancel & Return to Sign In</a>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="<?= e($appUrl) ?>/assets/js/app.js"></script>
<script>
    if (document.getElementById('resetForm')) {
        App.bindAjaxForm('resetForm', function (res) {
            App.toast('success', res.data.message || 'Password updated successfully!');
            setTimeout(function () {
                window.location.href = (res.data && res.data.redirect) ? res.data.redirect : '<?= e($appUrl) ?>/pages/auth/login.php';
            }, 1000);
        });
    }
</script>
</body>
</html>
