<?php
declare(strict_types=1);

ob_start();

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

function generate_material_code(PDO $pdo): string
{
    $year = date('Y');
    for ($i = 0; $i < 10; $i++) {
        $suffix = strtoupper(bin2hex(random_bytes(2)));
        $code = "MAT-{$year}-{$suffix}";
        $stmt = $pdo->prepare("SELECT id FROM materials WHERE code = :code LIMIT 1");
        $stmt->execute([':code' => $code]);
        if (!$stmt->fetch()) {
            return $code;
        }
    }
    return "MAT-{$year}-" . time();
}

switch ($action) {
    case 'list':
        $search = trim((string)($_GET['search'] ?? ''));
        $categoryId = (int)($_GET['category_id'] ?? 0);
        $supplierId = (int)($_GET['supplier_id'] ?? 0);
        $stockStatus = trim((string)($_GET['stock_status'] ?? ''));
        $lowStockOnly = isset($_GET['low_stock']) && (string)$_GET['low_stock'] === '1';
        $status = trim((string)($_GET['status'] ?? 'all'));

        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(100, max(5, (int)($_GET['per_page'] ?? 15)));
        $offset = ($page - 1) * $perPage;

        $where = ["1=1"];
        $params = [];

        if ($search !== '') {
            $where[] = "(m.name LIKE :search_name OR m.code LIKE :search_code)";
            $params[':search_name'] = '%' . $search . '%';
            $params[':search_code'] = '%' . $search . '%';
        }

        if ($categoryId > 0) {
            $where[] = "m.category_id = :category_id";
            $params[':category_id'] = $categoryId;
        }

        if ($supplierId > 0) {
            $where[] = "m.supplier_id = :supplier_id";
            $params[':supplier_id'] = $supplierId;
        }

        if ($status === 'active') {
            $where[] = "m.is_active = 1";
        } elseif ($status === 'inactive') {
            $where[] = "m.is_active = 0";
        }

        if ($lowStockOnly || $stockStatus === 'low') {
            $where[] = "m.current_stock <= m.reorder_level";
        } elseif ($stockStatus === 'healthy') {
            $where[] = "m.current_stock > m.reorder_level";
        } elseif ($stockStatus === 'out') {
            $where[] = "m.current_stock <= 0";
        }

        $whereClause = implode(' AND ', $where);

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM materials m WHERE {$whereClause}");
        $countStmt->execute($params);
        $totalItems = (int)$countStmt->fetchColumn();

        $sql = "
            SELECT m.*, 
                   c.name AS category_name, 
                   u.name AS unit_name, 
                   u.symbol AS unit_symbol,
                   s.name AS supplier_name,
                   (SELECT COUNT(*) FROM bom_items bi WHERE bi.material_id = m.id) AS bom_count,
                   (SELECT COUNT(*) FROM purchase_order_items poi WHERE poi.material_id = m.id) AS po_count
            FROM materials m
            JOIN categories c ON c.id = m.category_id
            JOIN units u ON u.id = m.unit_id
            LEFT JOIN suppliers s ON s.id = m.supplier_id
            WHERE {$whereClause}
            ORDER BY m.id DESC
            LIMIT :offset, :per_page
        ";

        $stmt = $pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue(':per_page', $perPage, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $materials = array_map(function (array $row): array {
            $current = (float)$row['current_stock'];
            $reorder = (float)$row['reorder_level'];
            $row['is_low_stock'] = ($current <= $reorder);
            return $row;
        }, $rows);

        $totalPages = (int)ceil($totalItems / $perPage);

        json_response(true, $materials, null, [
            'page'        => $page,
            'per_page'    => $perPage,
            'total_items' => $totalItems,
            'total_pages' => $totalPages
        ]);
        break;

    case 'get':
        $id = (int)($_GET['id'] ?? $input['id'] ?? 0);
        if ($id <= 0) {
            json_response(false, null, 'Invalid raw material ID.', null, 422);
        }

        $stmt = $pdo->prepare("
            SELECT m.*, 
                   c.name AS category_name, 
                   u.name AS unit_name, 
                   u.symbol AS unit_symbol,
                   s.name AS supplier_name,
                   (SELECT COUNT(*) FROM bom_items bi WHERE bi.material_id = m.id) AS bom_count,
                   (SELECT COUNT(*) FROM purchase_order_items poi WHERE poi.material_id = m.id) AS po_count
            FROM materials m
            JOIN categories c ON c.id = m.category_id
            JOIN units u ON u.id = m.unit_id
            LEFT JOIN suppliers s ON s.id = m.supplier_id
            WHERE m.id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $material = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$material) {
            json_response(false, null, 'Raw material item not found.', null, 404);
        }

        $current = (float)$material['current_stock'];
        $reorder = (float)$material['reorder_level'];
        $material['is_low_stock'] = ($current <= $reorder);

        json_response(true, $material);
        break;

    case 'create':
        if ($method !== 'POST') {
            json_response(false, null, 'Method not allowed.', null, 405);
        }

        $code = trim((string)($input['code'] ?? $input['material_code'] ?? ''));
        $name = trim((string)($input['name'] ?? ''));
        $categoryId = (int)($input['category_id'] ?? 0);
        $unitId = (int)($input['unit_id'] ?? 0);
        $supplierId = !empty($input['supplier_id']) ? (int)$input['supplier_id'] : null;
        $unitCost = filter_var($input['unit_cost'] ?? 0, FILTER_VALIDATE_FLOAT);
        $reorderLevel = filter_var($input['reorder_level'] ?? 0, FILTER_VALIDATE_FLOAT);
        $initialStock = filter_var($input['current_stock'] ?? 0, FILTER_VALIDATE_FLOAT);
        $description = trim((string)($input['description'] ?? ''));

        if ($code === '') {
            $code = generate_material_code($pdo);
        }

        if (mb_strlen($code) > 50) {
            json_response(false, null, 'Material code cannot exceed 50 characters.', null, 422);
        }

        if ($name === '') {
            json_response(false, null, 'Material name is required.', null, 422);
        }
        if (mb_strlen($name) > 150) {
            json_response(false, null, 'Material name cannot exceed 150 characters.', null, 422);
        }

        if ($categoryId <= 0) {
            json_response(false, null, 'Valid material category is required.', null, 422);
        }
        $catStmt = $pdo->prepare("SELECT id FROM categories WHERE id = :id AND type = 'material' LIMIT 1");
        $catStmt->execute([':id' => $categoryId]);
        if (!$catStmt->fetch()) {
            json_response(false, null, 'Selected category does not exist or is not a raw material category.', null, 422);
        }

        if ($unitId <= 0) {
            json_response(false, null, 'Valid unit of measurement is required.', null, 422);
        }
        $unitStmt = $pdo->prepare("SELECT id FROM units WHERE id = :id LIMIT 1");
        $unitStmt->execute([':id' => $unitId]);
        if (!$unitStmt->fetch()) {
            json_response(false, null, 'Selected unit of measurement does not exist.', null, 422);
        }

        if ($supplierId !== null) {
            $supStmt = $pdo->prepare("SELECT id FROM suppliers WHERE id = :id LIMIT 1");
            $supStmt->execute([':id' => $supplierId]);
            if (!$supStmt->fetch()) {
                json_response(false, null, 'Selected preferred supplier does not exist.', null, 422);
            }
        }

        if ($unitCost === false || $unitCost < 0) {
            json_response(false, null, 'Unit cost must be a non-negative number.', null, 422);
        }

        if ($reorderLevel === false || $reorderLevel < 0) {
            json_response(false, null, 'Reorder level must be a non-negative number.', null, 422);
        }

        if ($initialStock === false || $initialStock < 0) {
            $initialStock = 0.00;
        }

        $dupStmt = $pdo->prepare("SELECT id FROM materials WHERE code = :code LIMIT 1");
        $dupStmt->execute([':code' => $code]);
        if ($dupStmt->fetch()) {
            json_response(false, null, "Material code '{$code}' is already registered.", null, 409);
        }

        $insertStmt = $pdo->prepare("
            INSERT INTO materials (
                code, name, category_id, unit_id, unit_cost, current_stock, reorder_level, description, supplier_id, is_active
            ) VALUES (
                :code, :name, :category_id, :unit_id, :unit_cost, :current_stock, :reorder_level, :description, :supplier_id, 1
            )
        ");

        $insertStmt->execute([
            ':code'          => $code,
            ':name'          => $name,
            ':category_id'   => $categoryId,
            ':unit_id'       => $unitId,
            ':unit_cost'     => number_format((float)$unitCost, 2, '.', ''),
            ':current_stock' => number_format((float)$initialStock, 2, '.', ''),
            ':reorder_level' => number_format((float)$reorderLevel, 2, '.', ''),
            ':description'   => $description !== '' ? $description : null,
            ':supplier_id'   => $supplierId
        ]);

        $newId = (int)$pdo->lastInsertId();

        json_response(true, [
            'id'      => $newId,
            'code'    => $code,
            'message' => "Raw material '{$name}' created successfully."
        ], null, 201);
        break;

    case 'update':
        if ($method !== 'POST') {
            json_response(false, null, 'Method not allowed.', null, 405);
        }

        $id = (int)($input['id'] ?? 0);
        $code = trim((string)($input['code'] ?? $input['material_code'] ?? ''));
        $name = trim((string)($input['name'] ?? ''));
        $categoryId = (int)($input['category_id'] ?? 0);
        $unitId = (int)($input['unit_id'] ?? 0);
        $supplierId = !empty($input['supplier_id']) ? (int)$input['supplier_id'] : null;
        $unitCost = filter_var($input['unit_cost'] ?? 0, FILTER_VALIDATE_FLOAT);
        $reorderLevel = filter_var($input['reorder_level'] ?? 0, FILTER_VALIDATE_FLOAT);
        $description = trim((string)($input['description'] ?? ''));
        $isActive = isset($input['is_active']) ? (int)(bool)$input['is_active'] : 1;

        if ($id <= 0) {
            json_response(false, null, 'Invalid raw material ID.', null, 422);
        }

        $existStmt = $pdo->prepare("SELECT id FROM materials WHERE id = :id LIMIT 1");
        $existStmt->execute([':id' => $id]);
        if (!$existStmt->fetch()) {
            json_response(false, null, 'Raw material item not found.', null, 404);
        }

        if ($code === '') {
            json_response(false, null, 'Material code cannot be blank.', null, 422);
        }
        if (mb_strlen($code) > 50) {
            json_response(false, null, 'Material code cannot exceed 50 characters.', null, 422);
        }

        if ($name === '') {
            json_response(false, null, 'Material name is required.', null, 422);
        }
        if (mb_strlen($name) > 150) {
            json_response(false, null, 'Material name cannot exceed 150 characters.', null, 422);
        }

        if ($categoryId <= 0) {
            json_response(false, null, 'Valid material category is required.', null, 422);
        }
        $catStmt = $pdo->prepare("SELECT id FROM categories WHERE id = :id AND type = 'material' LIMIT 1");
        $catStmt->execute([':id' => $categoryId]);
        if (!$catStmt->fetch()) {
            json_response(false, null, 'Selected category does not exist or is not a raw material category.', null, 422);
        }

        if ($unitId <= 0) {
            json_response(false, null, 'Valid unit of measurement is required.', null, 422);
        }
        $unitStmt = $pdo->prepare("SELECT id FROM units WHERE id = :id LIMIT 1");
        $unitStmt->execute([':id' => $unitId]);
        if (!$unitStmt->fetch()) {
            json_response(false, null, 'Selected unit of measurement does not exist.', null, 422);
        }

        if ($supplierId !== null) {
            $supStmt = $pdo->prepare("SELECT id FROM suppliers WHERE id = :id LIMIT 1");
            $supStmt->execute([':id' => $supplierId]);
            if (!$supStmt->fetch()) {
                json_response(false, null, 'Selected preferred supplier does not exist.', null, 422);
            }
        }

        if ($unitCost === false || $unitCost < 0) {
            json_response(false, null, 'Unit cost must be a non-negative number.', null, 422);
        }

        if ($reorderLevel === false || $reorderLevel < 0) {
            json_response(false, null, 'Reorder level must be a non-negative number.', null, 422);
        }

        $dupStmt = $pdo->prepare("SELECT id FROM materials WHERE code = :code AND id != :id LIMIT 1");
        $dupStmt->execute([':code' => $code, ':id' => $id]);
        if ($dupStmt->fetch()) {
            json_response(false, null, "Material code '{$code}' is already assigned to another material.", null, 409);
        }

        $updateStmt = $pdo->prepare("
            UPDATE materials SET 
                code = :code,
                name = :name,
                category_id = :category_id,
                unit_id = :unit_id,
                unit_cost = :unit_cost,
                reorder_level = :reorder_level,
                description = :description,
                supplier_id = :supplier_id,
                is_active = :is_active
            WHERE id = :id
        ");

        $updateStmt->execute([
            ':code'          => $code,
            ':name'          => $name,
            ':category_id'   => $categoryId,
            ':unit_id'       => $unitId,
            ':unit_cost'     => number_format((float)$unitCost, 2, '.', ''),
            ':reorder_level' => number_format((float)$reorderLevel, 2, '.', ''),
            ':description'   => $description !== '' ? $description : null,
            ':supplier_id'   => $supplierId,
            ':is_active'     => $isActive,
            ':id'            => $id
        ]);

        json_response(true, ['message' => "Raw material '{$name}' updated successfully."]);
        break;

    case 'delete':
        if ($method !== 'POST') {
            json_response(false, null, 'Method not allowed.', null, 405);
        }

        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            json_response(false, null, 'Invalid raw material ID.', null, 422);
        }

        $existStmt = $pdo->prepare("SELECT id, name FROM materials WHERE id = :id LIMIT 1");
        $existStmt->execute([':id' => $id]);
        $material = $existStmt->fetch(PDO::FETCH_ASSOC);
        if (!$material) {
            json_response(false, null, 'Raw material item not found.', null, 404);
        }

        // Dependency checks
        $bomStmt = $pdo->prepare("SELECT COUNT(*) FROM bom_items WHERE material_id = :id");
        $bomStmt->execute([':id' => $id]);
        $bomCount = (int)$bomStmt->fetchColumn();

        $poStmt = $pdo->prepare("SELECT COUNT(*) FROM purchase_order_items WHERE material_id = :id");
        $poStmt->execute([':id' => $id]);
        $poCount = (int)$poStmt->fetchColumn();

        $mcStmt = $pdo->prepare("SELECT COUNT(*) FROM material_consumption WHERE material_id = :id");
        $mcStmt->execute([':id' => $id]);
        $mcCount = (int)$mcStmt->fetchColumn();

        $smStmt = $pdo->prepare("SELECT COUNT(*) FROM stock_movements WHERE item_type = 'material' AND item_id = :id");
        $smStmt->execute([':id' => $id]);
        $smCount = (int)$smStmt->fetchColumn();

        if ($bomCount > 0 || $poCount > 0 || $mcCount > 0 || $smCount > 0) {
            $reasons = [];
            if ($bomCount > 0) $reasons[] = "{$bomCount} BOM recipe item(s)";
            if ($poCount > 0) $reasons[] = "{$poCount} purchase order line(s)";
            if ($mcCount > 0) $reasons[] = "{$mcCount} production consumption record(s)";
            if ($smCount > 0) $reasons[] = "{$smCount} inventory stock movement(s)";

            json_response(
                false, 
                null, 
                "Cannot delete material '{$material['name']}' because it is actively referenced in: " . implode(', ', $reasons) . ".",
                null,
                409
            );
        }

        $deleteStmt = $pdo->prepare("DELETE FROM materials WHERE id = :id");
        $deleteStmt->execute([':id' => $id]);

        json_response(true, ['message' => "Raw material '{$material['name']}' deleted successfully."]);
        break;

    default:
        json_response(false, null, "Unknown or unsupported action '{$action}'.", null, 400);
}
