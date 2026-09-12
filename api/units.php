<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/rbac.php';

header('Content-Type: application/json; charset=UTF-8');

require_role([ROLE_ADMIN, ROLE_MANAGER]);

$pdo = Database::getConnection();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$rawInput = file_get_contents('php://input');
$jsonInput = json_decode($rawInput, true);
$input = is_array($jsonInput) ? array_merge($_POST, $jsonInput) : $_POST;

$action = trim((string)($input['action'] ?? $_GET['action'] ?? 'list'));

if ($method === 'POST') {
    enforce_csrf();
}

switch ($action) {
    case 'list':
        $search = trim((string)($_GET['search'] ?? ''));

        $sql = "
            SELECT u.*,
                   (SELECT COUNT(*) FROM products p WHERE p.unit_id = u.id) as products_count,
                   (SELECT COUNT(*) FROM materials m WHERE m.unit_id = u.id) as materials_count
            FROM units u
            WHERE 1=1
        ";
        $params = [];

        if ($search !== '') {
            $sql .= " AND (u.name LIKE :search_name OR u.symbol LIKE :search_symbol)";
            $params[':search_name'] = '%' . $search . '%';
            $params[':search_symbol'] = '%' . $search . '%';
        }

        $sql .= " ORDER BY u.name ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $units = $stmt->fetchAll(PDO::FETCH_ASSOC);

        json_response(true, $units);
        break;

    case 'create':
        if ($method !== 'POST') {
            json_response(false, null, 'Method not allowed.', null, 405);
        }

        $name = trim((string)($input['name'] ?? ''));
        $symbol = trim((string)($input['symbol'] ?? ''));

        if ($name === '' || $symbol === '') {
            json_response(false, null, 'Unit name and symbol are required.', null, 422);
        }

        if (mb_strlen($name) > 50) {
            json_response(false, null, 'Unit name cannot exceed 50 characters.', null, 422);
        }

        if (mb_strlen($symbol) > 10) {
            json_response(false, null, 'Unit symbol cannot exceed 10 characters.', null, 422);
        }

        $dupStmt = $pdo->prepare("SELECT id FROM units WHERE name = :name OR symbol = :symbol LIMIT 1");
        $dupStmt->execute([':name' => $name, ':symbol' => $symbol]);
        if ($dupStmt->fetch()) {
            json_response(false, null, 'A unit with this name or symbol already exists.', null, 409);
        }

        $insertStmt = $pdo->prepare("INSERT INTO units (name, symbol) VALUES (:name, :symbol)");
        $insertStmt->execute([':name' => $name, ':symbol' => $symbol]);

        $newId = (int)$pdo->lastInsertId();

        json_response(true, [
            'id'      => $newId,
            'message' => 'Unit of measurement created successfully.'
        ], null, 201);
        break;

    case 'update':
        if ($method !== 'POST') {
            json_response(false, null, 'Method not allowed.', null, 405);
        }

        $id = (int)($input['id'] ?? 0);
        $name = trim((string)($input['name'] ?? ''));
        $symbol = trim((string)($input['symbol'] ?? ''));

        if ($id <= 0) {
            json_response(false, null, 'Invalid unit ID.', null, 422);
        }

        if ($name === '' || $symbol === '') {
            json_response(false, null, 'Unit name and symbol are required.', null, 422);
        }

        if (mb_strlen($name) > 50) {
            json_response(false, null, 'Unit name cannot exceed 50 characters.', null, 422);
        }

        if (mb_strlen($symbol) > 10) {
            json_response(false, null, 'Unit symbol cannot exceed 10 characters.', null, 422);
        }

        $existStmt = $pdo->prepare("SELECT id FROM units WHERE id = :id LIMIT 1");
        $existStmt->execute([':id' => $id]);
        if (!$existStmt->fetch()) {
            json_response(false, null, 'Unit of measurement not found.', null, 404);
        }

        $dupStmt = $pdo->prepare("
            SELECT id FROM units 
            WHERE (name = :name OR symbol = :symbol) AND id != :id 
            LIMIT 1
        ");
        $dupStmt->execute([
            ':name'   => $name,
            ':symbol' => $symbol,
            ':id'     => $id
        ]);
        if ($dupStmt->fetch()) {
            json_response(false, null, 'Another unit with this name or symbol already exists.', null, 409);
        }

        $updateStmt = $pdo->prepare("UPDATE units SET name = :name, symbol = :symbol WHERE id = :id");
        $updateStmt->execute([
            ':name'   => $name,
            ':symbol' => $symbol,
            ':id'     => $id
        ]);

        json_response(true, ['message' => 'Unit of measurement updated successfully.']);
        break;

    case 'delete':
        if ($method !== 'POST') {
            json_response(false, null, 'Method not allowed.', null, 405);
        }

        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            json_response(false, null, 'Invalid unit ID.', null, 422);
        }

        $prodCountStmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE unit_id = :id");
        $prodCountStmt->execute([':id' => $id]);
        $prodCount = (int)$prodCountStmt->fetchColumn();

        $matCountStmt = $pdo->prepare("SELECT COUNT(*) FROM materials WHERE unit_id = :id");
        $matCountStmt->execute([':id' => $id]);
        $matCount = (int)$matCountStmt->fetchColumn();

        if ($prodCount > 0 || $matCount > 0) {
            $details = [];
            if ($prodCount > 0) $details[] = "{$prodCount} product(s)";
            if ($matCount > 0) $details[] = "{$matCount} raw material(s)";
            $msg = 'Cannot delete unit: It is currently linked to ' . implode(' and ', $details) . '. Reassign items before deleting.';
            json_response(false, null, $msg, null, 400);
        }

        $deleteStmt = $pdo->prepare("DELETE FROM units WHERE id = :id");
        $deleteStmt->execute([':id' => $id]);

        json_response(true, ['message' => 'Unit of measurement deleted successfully.']);
        break;

    default:
        json_response(false, null, 'Invalid action specified.', null, 400);
        break;
}
