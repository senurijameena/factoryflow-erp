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
        $type = trim((string)($_GET['type'] ?? ''));

        $sql = "
            SELECT c.*,
                   (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) as products_count,
                   (SELECT COUNT(*) FROM materials m WHERE m.category_id = c.id) as materials_count
            FROM categories c
            WHERE 1=1
        ";
        $params = [];

        if ($search !== '') {
            $sql .= " AND (c.name LIKE :search_name OR c.description LIKE :search_desc)";
            $params[':search_name'] = '%' . $search . '%';
            $params[':search_desc'] = '%' . $search . '%';
        }

        if ($type !== '' && in_array($type, ['product', 'material'], true)) {
            $sql .= " AND c.type = :type";
            $params[':type'] = $type;
        }

        $sql .= " ORDER BY c.type ASC, c.name ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

        json_response(true, $categories);
        break;

    case 'create':
        if ($method !== 'POST') {
            json_response(false, null, 'Method not allowed.', null, 405);
        }

        $name = trim((string)($input['name'] ?? ''));
        $type = trim((string)($input['type'] ?? 'product'));
        $description = trim((string)($input['description'] ?? ''));

        if ($name === '') {
            json_response(false, null, 'Category name is required.', null, 422);
        }

        if (mb_strlen($name) > 100) {
            json_response(false, null, 'Category name cannot exceed 100 characters.', null, 422);
        }

        if (!in_array($type, ['product', 'material'], true)) {
            $type = 'product';
        }

        $checkStmt = $pdo->prepare("SELECT id FROM categories WHERE name = :name AND type = :type LIMIT 1");
        $checkStmt->execute([':name' => $name, ':type' => $type]);
        if ($checkStmt->fetch()) {
            json_response(false, null, "A {$type} category named '{$name}' already exists.", null, 409);
        }

        $insertStmt = $pdo->prepare("
            INSERT INTO categories (name, type, description) 
            VALUES (:name, :type, :description)
        ");
        $insertStmt->execute([
            ':name'        => $name,
            ':type'        => $type,
            ':description' => $description !== '' ? $description : null
        ]);

        $newId = (int)$pdo->lastInsertId();

        json_response(true, [
            'id'      => $newId,
            'message' => 'Category created successfully.'
        ], null, 201);
        break;

    case 'update':
        if ($method !== 'POST') {
            json_response(false, null, 'Method not allowed.', null, 405);
        }

        $id = (int)($input['id'] ?? 0);
        $name = trim((string)($input['name'] ?? ''));
        $type = trim((string)($input['type'] ?? 'product'));
        $description = trim((string)($input['description'] ?? ''));

        if ($id <= 0) {
            json_response(false, null, 'Invalid category ID.', null, 422);
        }

        if ($name === '') {
            json_response(false, null, 'Category name is required.', null, 422);
        }

        if (mb_strlen($name) > 100) {
            json_response(false, null, 'Category name cannot exceed 100 characters.', null, 422);
        }

        if (!in_array($type, ['product', 'material'], true)) {
            $type = 'product';
        }

        $existStmt = $pdo->prepare("SELECT id FROM categories WHERE id = :id LIMIT 1");
        $existStmt->execute([':id' => $id]);
        if (!$existStmt->fetch()) {
            json_response(false, null, 'Category not found.', null, 404);
        }

        $dupStmt = $pdo->prepare("
            SELECT id FROM categories 
            WHERE name = :name AND type = :type AND id != :id 
            LIMIT 1
        ");
        $dupStmt->execute([
            ':name' => $name,
            ':type' => $type,
            ':id'   => $id
        ]);
        if ($dupStmt->fetch()) {
            json_response(false, null, "Another {$type} category named '{$name}' already exists.", null, 409);
        }

        $updateStmt = $pdo->prepare("
            UPDATE categories 
            SET name = :name, type = :type, description = :description 
            WHERE id = :id
        ");
        $updateStmt->execute([
            ':name'        => $name,
            ':type'        => $type,
            ':description' => $description !== '' ? $description : null,
            ':id'          => $id
        ]);

        json_response(true, ['message' => 'Category updated successfully.']);
        break;

    case 'delete':
        if ($method !== 'POST') {
            json_response(false, null, 'Method not allowed.', null, 405);
        }

        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            json_response(false, null, 'Invalid category ID.', null, 422);
        }

        $prodCountStmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE category_id = :id");
        $prodCountStmt->execute([':id' => $id]);
        $prodCount = (int)$prodCountStmt->fetchColumn();

        $matCountStmt = $pdo->prepare("SELECT COUNT(*) FROM materials WHERE category_id = :id");
        $matCountStmt->execute([':id' => $id]);
        $matCount = (int)$matCountStmt->fetchColumn();

        if ($prodCount > 0 || $matCount > 0) {
            $details = [];
            if ($prodCount > 0) $details[] = "{$prodCount} product(s)";
            if ($matCount > 0) $details[] = "{$matCount} material(s)";
            $msg = 'Cannot delete category: It is currently assigned to ' . implode(' and ', $details) . '. Please reassign them before deleting.';
            json_response(false, null, $msg, null, 400);
        }

        $deleteStmt = $pdo->prepare("DELETE FROM categories WHERE id = :id");
        $deleteStmt->execute([':id' => $id]);

        json_response(true, ['message' => 'Category deleted successfully.']);
        break;

    default:
        json_response(false, null, 'Invalid action specified.', null, 400);
        break;
}
