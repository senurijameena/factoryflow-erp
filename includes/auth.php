<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/csrf.php';

function is_login_rate_limited(PDO $pdo, string $ip, string $email): bool
{
    $windowMinutes = defined('LOGIN_LOCKOUT_MINUTES') ? LOGIN_LOCKOUT_MINUTES : 15;
    $maxAttempts = defined('MAX_LOGIN_ATTEMPTS') ? MAX_LOGIN_ATTEMPTS : 5;

    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM login_attempts 
        WHERE (ip_address = :ip OR email = :email) 
          AND attempted_at >= (NOW() - INTERVAL :minutes MINUTE)
    ");
    $stmt->bindValue(':ip', $ip, PDO::PARAM_STR);
    $stmt->bindValue(':email', $email, PDO::PARAM_STR);
    $stmt->bindValue(':minutes', $windowMinutes, PDO::PARAM_INT);
    $stmt->execute();

    return ((int)$stmt->fetchColumn()) >= $maxAttempts;
}

function record_login_attempt(PDO $pdo, string $ip, string $email): void
{
    $stmt = $pdo->prepare("
        INSERT INTO login_attempts (ip_address, email, attempted_at) 
        VALUES (:ip, :email, NOW())
    ");
    $stmt->execute([
        ':ip'    => $ip,
        ':email' => $email
    ]);
}

function clear_login_attempts(PDO $pdo, string $ip, string $email): void
{
    $stmt = $pdo->prepare("
        DELETE FROM login_attempts 
        WHERE ip_address = :ip OR email = :email
    ");
    $stmt->execute([
        ':ip'    => $ip,
        ':email' => $email
    ]);
}

function attempt_login(PDO $pdo, string $email, string $password, string $ip): array
{
    $email = strtolower(trim($email));

    if (is_login_rate_limited($pdo, $ip, $email)) {
        return [
            'success' => false,
            'message' => 'Too many failed login attempts. Please try again in 15 minutes.'
        ];
    }

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !password_verify($password, $user['password_hash'])) {
        record_login_attempt($pdo, $ip, $email);
        return [
            'success' => false,
            'message' => 'Invalid email or password.'
        ];
    }

    if ((int)$user['is_active'] !== 1) {
        return [
            'success' => false,
            'message' => 'Your account has been deactivated. Please contact an administrator.'
        ];
    }

    clear_login_attempts($pdo, $ip, $email);

    init_session();
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }

    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['user_name'] = (string)$user['name'];
    $_SESSION['user_email'] = (string)$user['email'];
    $_SESSION['user_role'] = (string)$user['role'];
    $_SESSION['last_activity'] = time();

    $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = :id");
    $updateStmt->execute([':id' => $user['id']]);

    return [
        'success' => true,
        'user'    => [
            'id'    => (int)$user['id'],
            'name'  => (string)$user['name'],
            'email' => (string)$user['email'],
            'role'  => (string)$user['role']
        ],
        'message' => 'Login successful.'
    ];
}

function logout_user(): void
{
    init_session();
    $_SESSION = [];

    if (!headers_sent() && ini_get('session.use_cookies')) {
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

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}
