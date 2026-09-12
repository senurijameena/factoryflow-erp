<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/csrf.php';

init_session();

$appUrl = rtrim((string)Config::get('APP_URL', 'http://localhost/factoryflow'), '/');

if (!empty($_SESSION['user_id'])) {
    header('Location: ' . $appUrl . '/pages/dashboard.php');
} else {
    header('Location: ' . $appUrl . '/pages/auth/login.php');
}
exit;
