<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

function init_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_samesite', 'Lax');
        session_start();
    }
}

function get_csrf_token(): string
{
    init_session();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    $token = get_csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . e($token) . '">';
}

function validate_csrf_token(): bool
{
    init_session();

    $token = null;

    if (!empty($_POST['csrf_token'])) {
        $token = (string)$_POST['csrf_token'];
    } elseif (!empty($_SERVER['HTTP_X_CSRF_TOKEN'])) {
        $token = (string)$_SERVER['HTTP_X_CSRF_TOKEN'];
    } else {
        $raw = file_get_contents('php://input');
        if (!empty($raw)) {
            $data = json_decode($raw, true);
            if (is_array($data) && !empty($data['csrf_token'])) {
                $token = (string)$data['csrf_token'];
            }
        }
    }

    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], $token);
}

function enforce_csrf(): void
{
    if (!validate_csrf_token()) {
        $isApi = (!empty($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))
            || (!empty($_SERVER['CONTENT_TYPE']) && str_contains($_SERVER['CONTENT_TYPE'], 'application/json'));

        if ($isApi) {
            json_response(false, null, 'Invalid or expired CSRF security token.', null, 403);
        } else {
            http_response_code(403);
            die('Access Denied: Invalid or expired CSRF token.');
        }
    }
}
