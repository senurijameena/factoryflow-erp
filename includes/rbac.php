<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/csrf.php';

function has_role(array|string $allowedRoles): bool
{
    init_session();

    if (empty($_SESSION['user_id']) || empty($_SESSION['user_role'])) {
        return false;
    }

    $roles = (array)$allowedRoles;
    return in_array($_SESSION['user_role'], $roles, true);
}

function require_role(array|string $allowedRoles): void
{
    init_session();

    if (empty($_SESSION['user_id'])) {
        if (!empty($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json')) {
            json_response(false, null, 'Authentication required.', null, 401);
        }
        header('Location: ' . Config::get('APP_URL', '') . '/pages/auth/login.php');
        exit;
    }

    if (!has_role($allowedRoles)) {
        if (!empty($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json')) {
            json_response(false, null, 'Forbidden: Insufficient privileges for this action.', null, 403);
        }
        http_response_code(403);
        die('403 Forbidden: You do not have permission to access this resource.');
    }
}

function require_auth(): void
{
    init_session();

    if (empty($_SESSION['user_id'])) {
        header('Location: ' . Config::get('APP_URL', '') . '/pages/auth/login.php');
        exit;
    }

    if (!empty($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_LIFETIME_SECONDS)) {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        session_destroy();
        header('Location: ' . Config::get('APP_URL', '') . '/pages/auth/login.php?error=timeout');
        exit;
    }

    $_SESSION['last_activity'] = time();
}
