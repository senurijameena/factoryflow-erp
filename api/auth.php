<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=UTF-8');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$rawInput = file_get_contents('php://input');
$jsonInput = json_decode($rawInput, true);
$input = is_array($jsonInput) ? array_merge($_POST, $jsonInput) : $_POST;

$action = trim((string)($input['action'] ?? $_GET['action'] ?? ''));

$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
if (str_contains($ip, ',')) {
    $ip = trim(explode(',', $ip)[0]);
}

$pdo = Database::getConnection();
$appUrl = rtrim((string)Config::get('APP_URL', 'http://localhost/factoryflow'), '/');

if ($method === 'POST') {
    enforce_csrf();
}

switch ($action) {
    case 'login':
        if ($method !== 'POST') {
            json_response(false, null, 'Method not allowed.', null, 405);
        }

        $email = trim((string)($input['email'] ?? ''));
        $password = (string)($input['password'] ?? '');

        if ($email === '' || $password === '') {
            json_response(false, null, 'Email and password are required.', null, 422);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            json_response(false, null, 'Please provide a valid email address.', null, 422);
        }

        $result = attempt_login($pdo, $email, $password, $ip);

        if (!$result['success']) {
            json_response(false, null, $result['message'], null, 401);
        }

        json_response(true, [
            'redirect' => $appUrl . '/pages/dashboard.php',
            'user'     => $result['user'],
            'message'  => 'Login successful.'
        ]);
        break;

    case 'register':
        if ($method !== 'POST') {
            json_response(false, null, 'Method not allowed.', null, 405);
        }

        $name = trim((string)($input['name'] ?? ''));
        $email = strtolower(trim((string)($input['email'] ?? '')));
        $password = (string)($input['password'] ?? '');
        $passwordConfirm = (string)($input['password_confirm'] ?? '');

        if ($name === '' || $email === '' || $password === '') {
            json_response(false, null, 'All fields are required.', null, 422);
        }

        if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            json_response(false, null, 'Full name must be between 2 and 100 characters.', null, 422);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            json_response(false, null, 'Please enter a valid email address.', null, 422);
        }

        if (strlen($password) < 8 || !preg_match('/[0-9]/', $password)) {
            json_response(false, null, 'Password must be at least 8 characters long and contain at least 1 number.', null, 422);
        }

        if ($password !== $passwordConfirm) {
            json_response(false, null, 'Password confirmation does not match.', null, 422);
        }

        $checkStmt = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
        $checkStmt->execute([':email' => $email]);
        if ($checkStmt->fetch()) {
            json_response(false, null, 'An account with this email address already exists.', null, 409);
        }

        $pdo->beginTransaction();
        try {
            $countStmt = $pdo->query("SELECT COUNT(*) FROM users");
            $userCount = (int)$countStmt->fetchColumn();
            $role = ($userCount === 0) ? ROLE_ADMIN : ROLE_WORKER;

            $hash = password_hash($password, PASSWORD_BCRYPT);
            $insertStmt = $pdo->prepare("
                INSERT INTO users (name, email, password_hash, role, is_active, created_at) 
                VALUES (:name, :email, :password_hash, :role, 1, NOW())
            ");
            $insertStmt->execute([
                ':name'          => $name,
                ':email'         => $email,
                ':password_hash' => $hash,
                ':role'          => $role
            ]);
            $newUserId = (int)$pdo->lastInsertId();

            $pdo->commit();
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[Registration Error]: ' . $e->getMessage());
            json_response(false, null, 'Unable to create user account. Please try again.', null, 500);
        }

        $loginResult = attempt_login($pdo, $email, $password, $ip);

        json_response(true, [
            'redirect' => $appUrl . '/pages/dashboard.php',
            'user'     => $loginResult['user'] ?? ['id' => $newUserId, 'name' => $name, 'email' => $email, 'role' => $role],
            'message'  => 'Account successfully created.'
        ], null, 201);
        break;

    case 'logout':
        logout_user();

        if ($method === 'POST' || (!empty($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))) {
            json_response(true, [
                'redirect' => $appUrl . '/pages/auth/login.php',
                'message'  => 'Logged out successfully.'
            ]);
        }

        header('Location: ' . $appUrl . '/pages/auth/login.php');
        exit;

    case 'forgot_password':
        if ($method !== 'POST') {
            json_response(false, null, 'Method not allowed.', null, 405);
        }

        $email = strtolower(trim((string)($input['email'] ?? '')));

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            json_response(false, null, 'Please enter a valid email address.', null, 422);
        }

        $stmt = $pdo->prepare("SELECT id, name, email FROM users WHERE email = :email AND is_active = 1 LIMIT 1");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $token = bin2hex(random_bytes(32));

            $insertReset = $pdo->prepare("
                INSERT INTO password_resets (user_id, token, expires_at, used, created_at) 
                VALUES (:user_id, :token, DATE_ADD(NOW(), INTERVAL 1 HOUR), 0, NOW())
            ");
            $insertReset->execute([
                ':user_id' => $user['id'],
                ':token'   => $token
            ]);

            $resetUrl = $appUrl . '/pages/auth/reset_password.php?token=' . urlencode($token);
            $emailHtml = '
                <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 24px; border: 1px solid #e2e8f0; border-radius: 8px; background-color: #ffffff;">
                    <div style="text-align: center; margin-bottom: 24px;">
                        <h2 style="color: #2563eb; margin: 0;">FactoryFlow ERP</h2>
                        <p style="color: #64748b; margin: 4px 0 0;">Manufacturing Production Management</p>
                    </div>
                    <div style="color: #1e293b; line-height: 1.6;">
                        <p>Hello <strong>' . htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') . '</strong>,</p>
                        <p>A password reset request was submitted for your FactoryFlow account. Click the button below to choose a new password:</p>
                        <div style="text-align: center; margin: 30px 0;">
                            <a href="' . htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8') . '" style="background-color: #2563eb; color: #ffffff; text-decoration: none; padding: 12px 24px; font-weight: bold; border-radius: 6px; display: inline-block;">Reset Password</a>
                        </div>
                        <p style="font-size: 13px; color: #64748b;">This password reset link will expire in 60 minutes. If you did not request a password reset, you can safely ignore this email.</p>
                        <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 24px 0;">
                        <p style="font-size: 12px; color: #94a3b8; word-break: break-all;">If the button above does not work, copy and paste this URL into your browser:<br>' . htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8') . '</p>
                    </div>
                </div>
            ';

            $queueStmt = $pdo->prepare("
                INSERT INTO email_queue (to_email, to_name, subject, body_html, status, attempts, created_at) 
                VALUES (:to_email, :to_name, :subject, :body_html, 'pending', 0, NOW())
            ");
            $queueStmt->execute([
                ':to_email'  => $user['email'],
                ':to_name'   => $user['name'],
                ':subject'   => 'Reset Your FactoryFlow Password',
                ':body_html' => $emailHtml
            ]);
        }

        json_response(true, [
            'message' => 'If an account exists with this email address, password reset instructions have been dispatched.'
        ]);
        break;

    case 'reset_password':
        if ($method !== 'POST') {
            json_response(false, null, 'Method not allowed.', null, 405);
        }

        $token = trim((string)($input['token'] ?? ''));
        $password = (string)($input['password'] ?? '');
        $passwordConfirm = (string)($input['password_confirm'] ?? '');

        if ($token === '' || strlen($token) !== 64) {
            json_response(false, null, 'Invalid or missing password reset token.', null, 422);
        }

        if (strlen($password) < 8 || !preg_match('/[0-9]/', $password)) {
            json_response(false, null, 'Password must be at least 8 characters long and contain at least 1 number.', null, 422);
        }

        if ($password !== $passwordConfirm) {
            json_response(false, null, 'Password confirmation does not match.', null, 422);
        }

        $stmt = $pdo->prepare("
            SELECT pr.id, pr.user_id, pr.expires_at, pr.used, u.email 
            FROM password_resets pr 
            JOIN users u ON u.id = pr.user_id 
            WHERE pr.token = :token AND pr.used = 0 AND pr.expires_at > NOW() 
            LIMIT 1
        ");
        $stmt->execute([':token' => $token]);
        $resetRecord = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$resetRecord) {
            json_response(false, null, 'This password reset link is invalid or has expired. Please request a new one.', null, 400);
        }

        $userId = (int)$resetRecord['user_id'];
        $newHash = password_hash($password, PASSWORD_BCRYPT);

        $pdo->beginTransaction();
        try {
            $updateUser = $pdo->prepare("UPDATE users SET password_hash = :hash WHERE id = :id");
            $updateUser->execute([':hash' => $newHash, ':id' => $userId]);

            $markUsed = $pdo->prepare("UPDATE password_resets SET used = 1 WHERE user_id = :user_id");
            $markUsed->execute([':user_id' => $userId]);

            $pdo->commit();
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[Reset Password Error]: ' . $e->getMessage());
            json_response(false, null, 'Unable to update password. Please try again.', null, 500);
        }

        json_response(true, [
            'redirect' => $appUrl . '/pages/auth/login.php',
            'message'  => 'Password successfully updated. You may now sign in.'
        ]);
        break;

    default:
        json_response(false, null, 'Invalid action specified.', null, 400);
        break;
}
