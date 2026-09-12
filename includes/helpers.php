<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/config.php';

function e(?string $string): string
{
    return htmlspecialchars($string ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function json_response(
    bool $success,
    mixed $data = null,
    mixed $error = null,
    mixed $meta = null,
    int $statusCode = 200
): void {
    if (is_int($meta)) {
        $statusCode = $meta;
        $meta = null;
    } elseif (!is_array($meta)) {
        $meta = null;
    }

    if (is_int($error)) {
        $statusCode = $error;
        $error = null;
    } elseif (!is_string($error) && $error !== null) {
        $error = (string)$error;
    }

    if (!headers_sent()) {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=UTF-8');
    }

    $payload = [
        'success' => $success,
        'data'    => $data,
        'error'   => $error,
        'meta'    => $meta
    ];

    if (is_array($data)) {
        if (isset($data['message'])) {
            $payload['message'] = $data['message'];
        }
        if (isset($data['redirect'])) {
            $payload['redirect'] = $data['redirect'];
        }
        if (isset($data['redirect_url'])) {
            $payload['redirect_url'] = $data['redirect_url'];
        }
    }

    if (ob_get_length()) {
        ob_clean();
    }

    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function encrypt_secret(string $plainText): string
{
    $appKey = Config::get('APP_KEY');
    if (str_starts_with($appKey, 'base64:')) {
        $appKey = base64_decode(substr($appKey, 7));
    }
    $key = hash('sha256', $appKey, true);
    $iv = openssl_random_pseudo_bytes(16);
    $cipherText = openssl_encrypt($plainText, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);

    return base64_encode($iv . $cipherText);
}

function decrypt_secret(string $cipherData): ?string
{
    $appKey = Config::get('APP_KEY');
    if (str_starts_with($appKey, 'base64:')) {
        $appKey = base64_decode(substr($appKey, 7));
    }
    $key = hash('sha256', $appKey, true);
    $raw = base64_decode($cipherData);

    if (strlen($raw) < 17) {
        return null;
    }

    $iv = substr($raw, 0, 16);
    $cipherText = substr($raw, 16);

    $decrypted = openssl_decrypt($cipherText, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    return $decrypted !== false ? $decrypted : null;
}

function set_flash(string $type, string $message): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['flash'] = [
        'type'    => $type,
        'message' => $message
    ];
}

function get_flash(): ?array
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function generate_document_no(PDO $pdo, string $table, string $column, string $prefix): string
{
    $year = date('Y');
    $pattern = $prefix . $year . '-%';
    
    $stmt = $pdo->prepare("SELECT {$column} FROM {$table} WHERE {$column} LIKE :pattern ORDER BY id DESC LIMIT 1");
    $stmt->execute(['pattern' => $pattern]);
    $lastNo = $stmt->fetchColumn();

    if ($lastNo) {
        $parts = explode('-', (string)$lastNo);
        $nextSeq = isset($parts[2]) ? ((int)$parts[2] + 1) : 1;
    } else {
        $nextSeq = 1;
    }

    return sprintf('%s%s-%04d', $prefix, $year, $nextSeq);
}
