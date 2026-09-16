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

require_auth();

$pdo = Database::getConnection();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId > 0) {
    $userCheck = $pdo->prepare("SELECT id FROM users WHERE id = :id LIMIT 1");
    $userCheck->execute([':id' => $userId]);
    if (!$userCheck->fetchColumn()) {
        $userId = 0;
    }
}
if ($userId <= 0) {
    $fallbackUser = (int)$pdo->query("SELECT id FROM users ORDER BY id ASC LIMIT 1")->fetchColumn();
    $userId = $fallbackUser > 0 ? $fallbackUser : 3;
}



$rawInput = file_get_contents('php://input');
$jsonInput = json_decode($rawInput, true);
$input = is_array($jsonInput) ? array_merge($_POST, $jsonInput) : $_POST;

$action = trim((string)($input['action'] ?? $_GET['action'] ?? 'overview'));

if ($method === 'POST') {
    enforce_csrf();
    require_role([ROLE_ADMIN, ROLE_MANAGER]);
}

switch ($action) {
    case 'overview':
        $materialsKpi = $pdo->query("
            SELECT COUNT(*) AS total_skus,
                   COALESCE(SUM(current_stock), 0) AS total_units,
                   COALESCE(SUM(CASE WHEN current_stock > 0 THEN current_stock * unit_cost ELSE 0 END), 0) AS valuation,
                   COALESCE(SUM(CASE WHEN current_stock <= 0 THEN 1 ELSE 0 END), 0) AS out_of_stock,
                   COALESCE(SUM(CASE WHEN current_stock > 0 AND current_stock <= reorder_level THEN 1 ELSE 0 END), 0) AS low_stock,
                   COALESCE(SUM(CASE WHEN current_stock > reorder_level THEN 1 ELSE 0 END), 0) AS healthy_stock
            FROM materials
            WHERE is_active = 1
        ")->fetch(PDO::FETCH_ASSOC);

        $productsKpi = $pdo->query("
            SELECT COUNT(*) AS total_skus,
                   COALESCE(SUM(current_stock), 0) AS total_units,
                   COALESCE(SUM(CASE WHEN current_stock > 0 THEN current_stock * cost_price ELSE 0 END), 0) AS valuation,
                   COALESCE(SUM(CASE WHEN current_stock <= 0 THEN 1 ELSE 0 END), 0) AS out_of_stock,
                   COALESCE(SUM(CASE WHEN current_stock > 0 AND current_stock <= reorder_level THEN 1 ELSE 0 END), 0) AS low_stock,
                   COALESCE(SUM(CASE WHEN current_stock > reorder_level THEN 1 ELSE 0 END), 0) AS healthy_stock
            FROM products
            WHERE is_active = 1
        ")->fetch(PDO::FETCH_ASSOC);

        $movementsRecent = (int)$pdo->query("
            SELECT COUNT(*) 
            FROM stock_movements 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ")->fetchColumn();

        $matVal = (float)($materialsKpi['valuation'] ?? 0);
        $prdVal = (float)($productsKpi['valuation'] ?? 0);
        $totalValuation = $matVal + $prdVal;

        $matOut = (int)($materialsKpi['out_of_stock'] ?? 0);
        $prdOut = (int)($productsKpi['out_of_stock'] ?? 0);
        $totalOut = $matOut + $prdOut;

        $matLow = (int)($materialsKpi['low_stock'] ?? 0);
        $prdLow = (int)($productsKpi['low_stock'] ?? 0);
        $totalLow = $matLow + $prdLow;

        $matHealthy = (int)($materialsKpi['healthy_stock'] ?? 0);
        $prdHealthy = (int)($productsKpi['healthy_stock'] ?? 0);

        json_response(true, [
            'total_valuation'        => round($totalValuation, 2),
            'materials_valuation'    => round($matVal, 2),
            'products_valuation'     => round($prdVal, 2),
            'total_material_skus'    => (int)($materialsKpi['total_skus'] ?? 0),
            'total_product_skus'     => (int)($productsKpi['total_skus'] ?? 0),
            'total_skus'             => (int)($materialsKpi['total_skus'] ?? 0) + (int)($productsKpi['total_skus'] ?? 0),
            'materials_units'        => (float)($materialsKpi['total_units'] ?? 0),
            'products_units'         => (float)($productsKpi['total_units'] ?? 0),
            'low_stock_count'        => $totalLow,
            'out_of_stock_count'     => $totalOut,
            'healthy_stock_count'    => $matHealthy + $prdHealthy,
            'recent_movements_count' => $movementsRecent
        ]);
        break;

    case 'stock_list':
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = max(5, min(100, (int)($_GET['limit'] ?? 15)));
        $offset = ($page - 1) * $limit;

        $search = trim((string)($_GET['search'] ?? ''));
        $itemType = trim((string)($_GET['item_type'] ?? 'all'));
        $categoryId = isset($_GET['category_id']) && $_GET['category_id'] !== '' ? (int)$_GET['category_id'] : null;
        $stockStatus = trim((string)($_GET['stock_status'] ?? 'all'));
        $sort = trim((string)($_GET['sort'] ?? 'name_asc'));

        $whereParts = [];
        $params = [];

        if ($search !== '') {
            $whereParts[] = "(item_code LIKE :search_code OR item_name LIKE :search_name)";
            $params[':search_code'] = '%' . $search . '%';
            $params[':search_name'] = '%' . $search . '%';
        }

        if ($itemType === 'material') {
            $whereParts[] = "item_type = 'material'";
        } elseif ($itemType === 'product') {
            $whereParts[] = "item_type = 'product'";
        }

        if ($categoryId !== null && $categoryId > 0) {
            $whereParts[] = "category_id = :cat_id";
            $params[':cat_id'] = $categoryId;
        }

        if ($stockStatus === 'low') {
            $whereParts[] = "stock_status = 'low'";
        } elseif ($stockStatus === 'out') {
            $whereParts[] = "stock_status = 'out'";
        } elseif ($stockStatus === 'healthy') {
            $whereParts[] = "stock_status = 'healthy'";
        }

        $whereClause = !empty($whereParts) ? 'WHERE ' . implode(' AND ', $whereParts) : '';

        $orderBy = match ($sort) {
            'stock_asc'      => 'current_stock ASC, item_name ASC',
            'stock_desc'     => 'current_stock DESC, item_name ASC',
            'valuation_desc' => 'total_valuation DESC',
            'code_asc'       => 'item_code ASC',
            'name_desc'      => 'item_name DESC',
            default          => 'item_name ASC'
        };

        $baseUnionQuery = "
            SELECT m.id,
                   'material' AS item_type,
                   m.code AS item_code,
                   m.name AS item_name,
                   m.category_id,
                   c.name AS category_name,
                   m.unit_id,
                   u.symbol AS unit_symbol,
                   m.unit_cost AS unit_cost,
                   m.current_stock,
                   m.reorder_level,
                   (m.current_stock * m.unit_cost) AS total_valuation,
                   CASE 
                       WHEN m.current_stock <= 0 THEN 'out'
                       WHEN m.current_stock <= m.reorder_level THEN 'low'
                       ELSE 'healthy'
                   END AS stock_status
            FROM materials m
            LEFT JOIN categories c ON c.id = m.category_id
            LEFT JOIN units u ON u.id = m.unit_id
            WHERE m.is_active = 1

            UNION ALL

            SELECT p.id,
                   'product' AS item_type,
                   p.sku AS item_code,
                   p.name AS item_name,
                   p.category_id,
                   c.name AS category_name,
                   p.unit_id,
                   u.symbol AS unit_symbol,
                   p.cost_price AS unit_cost,
                   p.current_stock,
                   p.reorder_level,
                   (p.current_stock * p.cost_price) AS total_valuation,
                   CASE 
                       WHEN p.current_stock <= 0 THEN 'out'
                       WHEN p.current_stock <= p.reorder_level THEN 'low'
                       ELSE 'healthy'
                   END AS stock_status
            FROM products p
            LEFT JOIN categories c ON c.id = p.category_id
            LEFT JOIN units u ON u.id = p.unit_id
            WHERE p.is_active = 1
        ";

        $countSql = "SELECT COUNT(*) FROM ({$baseUnionQuery}) AS inv {$whereClause}";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $totalItems = (int)$countStmt->fetchColumn();

        $dataSql = "
            SELECT * 
            FROM ({$baseUnionQuery}) AS inv 
            {$whereClause} 
            ORDER BY {$orderBy} 
            LIMIT :offset, :limit
        ";
        $stmt = $pdo->prepare($dataSql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $totalPages = $totalItems > 0 ? (int)ceil($totalItems / $limit) : 1;

        json_response(true, $items, null, [
            'page'        => $page,
            'limit'       => $limit,
            'total_items' => $totalItems,
            'total_pages' => $totalPages
        ]);
        break;

    case 'movements':
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = max(5, min(100, (int)($_GET['limit'] ?? 15)));
        $offset = ($page - 1) * $limit;

        $search = trim((string)($_GET['search'] ?? ''));
        $movementType = trim((string)($_GET['movement_type'] ?? 'all'));
        $itemType = trim((string)($_GET['item_type'] ?? 'all'));
        $itemId = isset($_GET['item_id']) && $_GET['item_id'] !== '' ? (int)$_GET['item_id'] : null;
        $startDate = trim((string)($_GET['start_date'] ?? ''));
        $endDate = trim((string)($_GET['end_date'] ?? ''));

        $whereParts = [];
        $params = [];

        if ($search !== '') {
            $whereParts[] = "(
                (CASE sm.item_type WHEN 'material' THEN m.code WHEN 'product' THEN p.sku END) LIKE :s1
                OR (CASE sm.item_type WHEN 'material' THEN m.name WHEN 'product' THEN p.name END) LIKE :s2
                OR sm.batch_no LIKE :s3
                OR sm.reference_no LIKE :s4
            )";
            $params[':s1'] = '%' . $search . '%';
            $params[':s2'] = '%' . $search . '%';
            $params[':s3'] = '%' . $search . '%';
            $params[':s4'] = '%' . $search . '%';
        }

        if (in_array($movementType, ['in', 'out', 'adjustment', 'scrap'], true)) {
            $whereParts[] = "sm.movement_type = :movement_type";
            $params[':movement_type'] = $movementType;
        }

        if (in_array($itemType, ['material', 'product'], true)) {
            $whereParts[] = "sm.item_type = :item_type";
            $params[':item_type'] = $itemType;
        }

        if ($itemId !== null && $itemId > 0) {
            $whereParts[] = "sm.item_id = :item_id";
            $params[':item_id'] = $itemId;
        }

        if ($startDate !== '') {
            $whereParts[] = "sm.created_at >= :start_date";
            $params[':start_date'] = $startDate . ' 00:00:00';
        }

        if ($endDate !== '') {
            $whereParts[] = "sm.created_at <= :end_date";
            $params[':end_date'] = $endDate . ' 23:59:59';
        }

        $whereClause = !empty($whereParts) ? 'WHERE ' . implode(' AND ', $whereParts) : '';

        $baseQuery = "
            FROM stock_movements sm
            LEFT JOIN users usr ON usr.id = sm.created_by
            LEFT JOIN materials m ON sm.item_type = 'material' AND m.id = sm.item_id
            LEFT JOIN units mu ON mu.id = m.unit_id
            LEFT JOIN products p ON sm.item_type = 'product' AND p.id = sm.item_id
            LEFT JOIN units pu ON pu.id = p.unit_id
        ";

        $countSql = "SELECT COUNT(*) {$baseQuery} {$whereClause}";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $totalItems = (int)$countStmt->fetchColumn();

        $selectSql = "
            SELECT sm.id,
                   sm.item_type,
                   sm.item_id,
                   sm.movement_type,
                   sm.quantity,
                   sm.unit_cost,
                   (sm.quantity * sm.unit_cost) AS total_value,
                   sm.reference_type,
                   sm.reference_id,
                   sm.reference_no,
                   sm.batch_no,
                   sm.reason,
                   sm.notes,
                   sm.created_by,
                   sm.created_at,
                   COALESCE(usr.name, 'System') AS user_name,
                   CASE sm.item_type WHEN 'material' THEN m.code WHEN 'product' THEN p.sku END AS item_code,
                   CASE sm.item_type WHEN 'material' THEN m.name WHEN 'product' THEN p.name END AS item_name,
                   CASE sm.item_type WHEN 'material' THEN mu.symbol WHEN 'product' THEN pu.symbol END AS unit_symbol,
                   CASE sm.item_type WHEN 'material' THEN m.current_stock WHEN 'product' THEN p.current_stock END AS remaining_stock
            {$baseQuery}
            {$whereClause}
            ORDER BY sm.id DESC
            LIMIT :offset, :limit
        ";

        $stmt = $pdo->prepare($selectSql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $movements = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $totalPages = $totalItems > 0 ? (int)ceil($totalItems / $limit) : 1;

        json_response(true, $movements, null, [
            'page'        => $page,
            'limit'       => $limit,
            'total_items' => $totalItems,
            'total_pages' => $totalPages
        ]);
        break;

    case 'items_lookup':
        $materials = $pdo->query("
            SELECT m.id,
                   'material' AS item_type,
                   m.code AS item_code,
                   m.name AS item_name,
                   m.current_stock,
                   m.reorder_level,
                   m.unit_cost,
                   u.symbol AS unit_symbol
            FROM materials m
            LEFT JOIN units u ON u.id = m.unit_id
            WHERE m.is_active = 1
            ORDER BY m.name ASC
        ")->fetchAll(PDO::FETCH_ASSOC);

        $products = $pdo->query("
            SELECT p.id,
                   'product' AS item_type,
                   p.sku AS item_code,
                   p.name AS item_name,
                   p.current_stock,
                   p.reorder_level,
                   p.cost_price AS unit_cost,
                   u.symbol AS unit_symbol
            FROM products p
            LEFT JOIN units u ON u.id = p.unit_id
            WHERE p.is_active = 1
            ORDER BY p.name ASC
        ")->fetchAll(PDO::FETCH_ASSOC);

        $categories = $pdo->query("
            SELECT id, name, type 
            FROM categories 
            ORDER BY type ASC, name ASC
        ")->fetchAll(PDO::FETCH_ASSOC);

        json_response(true, [
            'materials'  => $materials,
            'products'   => $products,
            'categories' => $categories
        ]);
        break;

    case 'record_movement':
        $itemType = trim((string)($input['item_type'] ?? ''));
        $itemId = (int)($input['item_id'] ?? 0);
        $movementType = trim((string)($input['movement_type'] ?? ''));
        $quantity = (float)($input['quantity'] ?? 0);
        $batchNo = trim((string)($input['batch_no'] ?? ''));
        $referenceType = trim((string)($input['reference_type'] ?? 'manual'));
        $referenceNo = trim((string)($input['reference_no'] ?? ''));
        $notes = trim((string)($input['notes'] ?? ''));

        if (!in_array($itemType, ['material', 'product'], true)) {
            json_response(false, null, 'Invalid item type selected.', 422);
        }
        if ($itemId <= 0) {
            json_response(false, null, 'Please select an item.', 422);
        }
        if (!in_array($movementType, ['in', 'out', 'scrap'], true)) {
            json_response(false, null, 'Invalid movement type. Allowed types: in, out, scrap.', 422);
        }
        if ($quantity <= 0.00001) {
            json_response(false, null, 'Quantity must be greater than zero.', 422);
        }

        try {
            $pdo->beginTransaction();

            if ($itemType === 'material') {
                $lockStmt = $pdo->prepare("SELECT id, code, name, current_stock, unit_cost FROM materials WHERE id = :id FOR UPDATE");
                $lockStmt->execute([':id' => $itemId]);
                $item = $lockStmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $lockStmt = $pdo->prepare("SELECT id, sku AS code, name, current_stock, cost_price AS unit_cost FROM products WHERE id = :id FOR UPDATE");
                $lockStmt->execute([':id' => $itemId]);
                $item = $lockStmt->fetch(PDO::FETCH_ASSOC);
            }

            if (!$item) {
                $pdo->rollBack();
                json_response(false, null, 'Target item not found or inactive.', 404);
            }

            $currentStock = (float)$item['current_stock'];
            $unitCost = (float)$item['unit_cost'];

            if (in_array($movementType, ['out', 'scrap'], true)) {
                if ($quantity > $currentStock) {
                    $pdo->rollBack();
                    json_response(
                        false, 
                        null, 
                        "Insufficient stock. Requested quantity (" . number_format($quantity, 4) . ") exceeds available stock (" . number_format($currentStock, 4) . ").", 
                        422
                    );
                }
                $newStock = round($currentStock - $quantity, 4);
            } else {
                $newStock = round($currentStock + $quantity, 4);
            }

            if ($itemType === 'material') {
                $updStmt = $pdo->prepare("UPDATE materials SET current_stock = :stock WHERE id = :id");
            } else {
                $updStmt = $pdo->prepare("UPDATE products SET current_stock = :stock WHERE id = :id");
            }
            $updStmt->execute([
                ':stock' => $newStock,
                ':id'    => $itemId
            ]);

            $smStmt = $pdo->prepare("
                INSERT INTO stock_movements (
                    item_type, item_id, movement_type, quantity, unit_cost,
                    reference_type, reference_no, batch_no, reason, notes,
                    created_by, created_at
                ) VALUES (
                    :item_type, :item_id, :movement_type, :quantity, :unit_cost,
                    :reference_type, :reference_no, :batch_no, :reason, :notes,
                    :created_by, NOW()
                )
            ");
            $smStmt->execute([
                ':item_type'      => $itemType,
                ':item_id'        => $itemId,
                ':movement_type'  => $movementType,
                ':quantity'       => $quantity,
                ':unit_cost'      => $unitCost,
                ':reference_type' => $referenceType !== '' ? $referenceType : 'manual',
                ':reference_no'   => $referenceNo !== '' ? $referenceNo : null,
                ':batch_no'       => $batchNo !== '' ? $batchNo : null,
                ':reason'         => $movementType === 'scrap' ? 'Scrap / Damaged Write-off' : 'Manual Ledger Entry',
                ':notes'          => $notes !== '' ? $notes : null,
                ':created_by'     => $userId > 0 ? $userId : 1
            ]);

            $pdo->commit();

            json_response(true, [
                'message'        => 'Stock movement recorded successfully.',
                'item_code'      => $item['code'],
                'item_name'      => $item['name'],
                'movement_type'  => $movementType,
                'quantity'       => $quantity,
                'previous_stock' => $currentStock,
                'new_stock'      => $newStock
            ]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[record_movement Error]: ' . $e->getMessage());
            json_response(false, null, 'Failed to record movement: ' . $e->getMessage(), 500);
        }
        break;

    case 'reconcile_adjustment':
        $itemType = trim((string)($input['item_type'] ?? ''));
        $itemId = (int)($input['item_id'] ?? 0);
        $countedStock = isset($input['counted_stock']) ? (float)$input['counted_stock'] : -1.0;
        $reason = trim((string)($input['reason'] ?? 'Routine Cycle Count Discrepancy'));
        $notes = trim((string)($input['notes'] ?? ''));

        if (!in_array($itemType, ['material', 'product'], true)) {
            json_response(false, null, 'Invalid item type selected.', 422);
        }
        if ($itemId <= 0) {
            json_response(false, null, 'Please select an item.', 422);
        }
        if ($countedStock < 0) {
            json_response(false, null, 'Counted quantity cannot be negative.', 422);
        }

        try {
            $pdo->beginTransaction();

            if ($itemType === 'material') {
                $lockStmt = $pdo->prepare("SELECT id, code, name, current_stock, unit_cost FROM materials WHERE id = :id FOR UPDATE");
                $lockStmt->execute([':id' => $itemId]);
                $item = $lockStmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $lockStmt = $pdo->prepare("SELECT id, sku AS code, name, current_stock, cost_price AS unit_cost FROM products WHERE id = :id FOR UPDATE");
                $lockStmt->execute([':id' => $itemId]);
                $item = $lockStmt->fetch(PDO::FETCH_ASSOC);
            }

            if (!$item) {
                $pdo->rollBack();
                json_response(false, null, 'Target item not found or inactive.', 404);
            }

            $currentStock = (float)$item['current_stock'];
            $countedStock = round($countedStock, 4);
            $diff = round($countedStock - $currentStock, 4);
            $unitCost = (float)$item['unit_cost'];

            if ($itemType === 'material') {
                $updStmt = $pdo->prepare("UPDATE materials SET current_stock = :stock WHERE id = :id");
            } else {
                $updStmt = $pdo->prepare("UPDATE products SET current_stock = :stock WHERE id = :id");
            }
            $updStmt->execute([
                ':stock' => $countedStock,
                ':id'    => $itemId
            ]);

            $auditRef = 'ADJ-' . date('Ymd-His');

            $smStmt = $pdo->prepare("
                INSERT INTO stock_movements (
                    item_type, item_id, movement_type, quantity, unit_cost,
                    reference_type, reference_no, reason, notes,
                    created_by, created_at
                ) VALUES (
                    :item_type, :item_id, 'adjustment', :quantity, :unit_cost,
                    'adjustment', :reference_no, :reason, :notes,
                    :created_by, NOW()
                )
            ");
            $smStmt->execute([
                ':item_type'    => $itemType,
                ':item_id'      => $itemId,
                ':quantity'     => $diff,
                ':unit_cost'    => $unitCost,
                ':reference_no' => $auditRef,
                ':reason'       => $reason !== '' ? $reason : 'Physical Count Reconciliation',
                ':notes'        => $notes !== '' ? $notes : null,
                ':created_by'   => $userId > 0 ? $userId : 1
            ]);

            $pdo->commit();

            json_response(true, [
                'message'        => 'Stock reconciliation completed.',
                'item_code'      => $item['code'],
                'item_name'      => $item['name'],
                'previous_stock' => $currentStock,
                'counted_stock'  => $countedStock,
                'variance'       => $diff,
                'reference_no'   => $auditRef
            ]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[reconcile_adjustment Error]: ' . $e->getMessage());
            json_response(false, null, 'Failed to reconcile stock: ' . $e->getMessage(), 500);
        }
        break;

    default:
        json_response(false, null, 'Unknown action requested.', 400);
}
