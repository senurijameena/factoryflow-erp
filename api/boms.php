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

function generate_bom_code(PDO $pdo, int $productId, int $version): string
{
    $skuStmt = $pdo->prepare("SELECT sku FROM products WHERE id = :id LIMIT 1");
    $skuStmt->execute([':id' => $productId]);
    $sku = (string)$skuStmt->fetchColumn();
    $cleanSku = preg_replace('/[^A-Za-z0-9_-]/', '', $sku) ?: "PRD-{$productId}";

    $base = "BOM-{$cleanSku}-V{$version}";
    $code = $base;
    $counter = 1;

    while (true) {
        $stmt = $pdo->prepare("SELECT id FROM boms WHERE bom_code = :code LIMIT 1");
        $stmt->execute([':code' => $code]);
        if (!$stmt->fetch()) {
            return $code;
        }
        $code = "{$base}-" . $counter++;
    }
}

switch ($action) {
    case 'list':
        $search = trim((string)($_GET['search'] ?? ''));
        $status = trim((string)($_GET['status'] ?? 'all'));
        $productId = (int)($_GET['product_id'] ?? 0);

        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(100, max(5, (int)($_GET['per_page'] ?? 15)));
        $offset = ($page - 1) * $perPage;

        $where = ["1=1"];
        $params = [];

        if ($search !== '') {
            $where[] = "(b.bom_code LIKE :search_code OR p.name LIKE :search_name OR p.sku LIKE :search_sku)";
            $params[':search_code'] = '%' . $search . '%';
            $params[':search_name'] = '%' . $search . '%';
            $params[':search_sku']  = '%' . $search . '%';
        }

        if ($status !== '' && $status !== 'all') {
            $where[] = "b.status = :status";
            $params[':status'] = $status;
        }

        if ($productId > 0) {
            $where[] = "b.product_id = :product_id";
            $params[':product_id'] = $productId;
        }

        $whereClause = implode(' AND ', $where);

        $countStmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM boms b
            JOIN products p ON p.id = b.product_id
            WHERE {$whereClause}
        ");
        $countStmt->execute($params);
        $totalItems = (int)$countStmt->fetchColumn();

        $sql = "
            SELECT b.*,
                   p.name AS product_name,
                   p.sku AS product_sku,
                   u.symbol AS product_unit,
                   usr.name AS creator_name,
                   (SELECT COUNT(*) FROM bom_items bi WHERE bi.bom_id = b.id) AS items_count,
                   (SELECT COUNT(*) FROM production_orders po WHERE po.bom_id = b.id) AS orders_count
            FROM boms b
            JOIN products p ON p.id = b.product_id
            JOIN units u ON u.id = p.unit_id
            JOIN users usr ON usr.id = b.created_by
            WHERE {$whereClause}
            ORDER BY b.id DESC
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

        $boms = array_map(function (array $r): array {
            $yield = max(0.01, (float)$r['yield_quantity']);
            $total = (float)$r['total_cost'];
            $r['cost_per_unit'] = round($total / $yield, 2);
            return $r;
        }, $rows);

        $totalPages = (int)ceil($totalItems / $perPage);

        json_response(true, $boms, null, [
            'page'        => $page,
            'per_page'    => $perPage,
            'total_items' => $totalItems,
            'total_pages' => $totalPages
        ]);
        break;

    case 'get':
        $id = (int)($_GET['id'] ?? $input['id'] ?? 0);
        if ($id <= 0) {
            json_response(false, null, 'Invalid BOM ID.', null, 422);
        }

        $stmt = $pdo->prepare("
            SELECT b.*,
                   p.name AS product_name,
                   p.sku AS product_sku,
                   p.cost_price AS product_cost_price,
                   p.sale_price AS product_sale_price,
                   u.name AS product_unit_name,
                   u.symbol AS product_unit,
                   usr.name AS creator_name,
                   (SELECT COUNT(*) FROM production_orders po WHERE po.bom_id = b.id) AS orders_count
            FROM boms b
            JOIN products p ON p.id = b.product_id
            JOIN units u ON u.id = p.unit_id
            JOIN users usr ON usr.id = b.created_by
            WHERE b.id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $bom = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$bom) {
            json_response(false, null, 'Bill of Materials not found.', null, 404);
        }

        $itemsStmt = $pdo->prepare("
            SELECT bi.*,
                   m.name AS material_name,
                   m.code AS material_code,
                   m.current_stock AS material_stock,
                   u.symbol AS unit_symbol
            FROM bom_items bi
            JOIN materials m ON m.id = bi.material_id
            JOIN units u ON u.id = m.unit_id
            WHERE bi.bom_id = :bom_id
            ORDER BY bi.id ASC
        ");
        $itemsStmt->execute([':bom_id' => $id]);
        $bom['items'] = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

        $yield = max(0.01, (float)$bom['yield_quantity']);
        $total = (float)$bom['total_cost'];
        $bom['cost_per_unit'] = round($total / $yield, 2);

        json_response(true, $bom);
        break;

    case 'materials_lookup':
        $stmt = $pdo->query("
            SELECT m.id, 
                   m.code, 
                   m.name, 
                   m.unit_cost, 
                   m.current_stock, 
                   u.symbol AS unit_symbol,
                   u.name AS unit_name
            FROM materials m
            JOIN units u ON u.id = m.unit_id
            WHERE m.is_active = 1
            ORDER BY m.name ASC
        ");
        $materials = $stmt->fetchAll(PDO::FETCH_ASSOC);
        json_response(true, $materials);
        break;

    case 'create':
        if ($method !== 'POST') {
            json_response(false, null, 'Method not allowed.', null, 405);
        }

        $productId = (int)($input['product_id'] ?? 0);
        $version = max(1, (int)($input['version'] ?? 1));
        $yieldQuantity = filter_var($input['yield_quantity'] ?? 1.00, FILTER_VALIDATE_FLOAT);
        $status = strtolower(trim((string)($input['status'] ?? 'draft')));
        $notes = trim((string)($input['notes'] ?? ''));
        $bomCode = trim((string)($input['bom_code'] ?? ''));
        $rawItems = $input['items'] ?? [];

        if (!is_array($rawItems) && is_string($rawItems)) {
            $rawItems = json_decode($rawItems, true) ?: [];
        }

        if ($productId <= 0) {
            json_response(false, null, 'Valid finished product is required.', null, 422);
        }

        $prodStmt = $pdo->prepare("SELECT id, sku FROM products WHERE id = :id LIMIT 1");
        $prodStmt->execute([':id' => $productId]);
        $product = $prodStmt->fetch(PDO::FETCH_ASSOC);
        if (!$product) {
            json_response(false, null, 'Selected finished product does not exist.', null, 422);
        }

        if ($yieldQuantity === false || $yieldQuantity <= 0) {
            json_response(false, null, 'Yield quantity must be greater than 0.', null, 422);
        }

        if (!in_array($status, ['draft', 'active', 'archived'], true)) {
            $status = 'draft';
        }

        if ($bomCode === '') {
            $bomCode = generate_bom_code($pdo, $productId, $version);
        }

        // Uniqueness checks
        $dupCode = $pdo->prepare("SELECT id FROM boms WHERE bom_code = :code LIMIT 1");
        $dupCode->execute([':code' => $bomCode]);
        if ($dupCode->fetch()) {
            json_response(false, null, "BOM code '{$bomCode}' is already in use.", null, 409);
        }

        $dupVer = $pdo->prepare("SELECT id FROM boms WHERE product_id = :pid AND version = :ver LIMIT 1");
        $dupVer->execute([':pid' => $productId, ':ver' => $version]);
        if ($dupVer->fetch()) {
            json_response(false, null, "Version {$version} already exists for this product. Please increment the version.", null, 409);
        }

        if (empty($rawItems)) {
            json_response(false, null, 'A Bill of Materials must have at least one component line item.', null, 422);
        }

        // Validate items and compute costs
        $processedItems = [];
        $totalCost = 0.00;

        foreach ($rawItems as $idx => $line) {
            $matId = (int)($line['material_id'] ?? 0);
            $qty = filter_var($line['quantity'] ?? $line['quantity_required'] ?? 0, FILTER_VALIDATE_FLOAT);
            $scrap = filter_var($line['scrap_percentage'] ?? $line['wastage_percent'] ?? 0, FILTER_VALIDATE_FLOAT);
            $userCost = isset($line['unit_cost']) ? filter_var($line['unit_cost'], FILTER_VALIDATE_FLOAT) : null;

            if ($matId <= 0 || $qty === false || $qty <= 0) {
                continue;
            }

            $matStmt = $pdo->prepare("SELECT id, name, unit_cost FROM materials WHERE id = :id LIMIT 1");
            $matStmt->execute([':id' => $matId]);
            $mat = $matStmt->fetch(PDO::FETCH_ASSOC);
            if (!$mat) {
                json_response(false, null, "Material at line " . ($idx + 1) . " does not exist.", null, 422);
            }

            $scrap = ($scrap === false || $scrap < 0) ? 0.00 : (float)$scrap;
            $unitCost = ($userCost !== null && $userCost !== false && $userCost >= 0) ? (float)$userCost : (float)$mat['unit_cost'];

            $lineCost = round(($qty * (1 + ($scrap / 100))) * $unitCost, 2);
            $totalCost += $lineCost;

            $processedItems[] = [
                'material_id'       => $matId,
                'quantity_required' => $qty,
                'wastage_percent'   => $scrap,
                'unit_cost'         => $unitCost,
                'subtotal_cost'     => $lineCost
            ];
        }

        if (empty($processedItems)) {
            json_response(false, null, 'Please specify at least one valid material with quantity greater than zero.', null, 422);
        }

        $userId = (int)($_SESSION['user_id'] ?? 1);
        $isActive = ($status === 'active') ? 1 : 0;

        $pdo->beginTransaction();
        try {
            // Activating this BOM archives previous active versions for this product
            if ($status === 'active') {
                $archStmt = $pdo->prepare("
                    UPDATE boms 
                    SET status = 'archived', is_active = 0 
                    WHERE product_id = :pid AND status = 'active'
                ");
                $archStmt->execute([':pid' => $productId]);
            }

            $insertBom = $pdo->prepare("
                INSERT INTO boms (
                    bom_code, product_id, version, yield_quantity, status, total_cost, is_active, notes, created_by
                ) VALUES (
                    :bom_code, :product_id, :version, :yield_quantity, :status, :total_cost, :is_active, :notes, :created_by
                )
            ");

            $insertBom->execute([
                ':bom_code'       => $bomCode,
                ':product_id'     => $productId,
                ':version'        => $version,
                ':yield_quantity' => number_format((float)$yieldQuantity, 2, '.', ''),
                ':status'         => $status,
                ':total_cost'     => number_format((float)$totalCost, 2, '.', ''),
                ':is_active'      => $isActive,
                ':notes'          => $notes !== '' ? $notes : null,
                ':created_by'     => $userId
            ]);

            $bomId = (int)$pdo->lastInsertId();

            $insertItem = $pdo->prepare("
                INSERT INTO bom_items (
                    bom_id, material_id, quantity_required, wastage_percent, unit_cost, subtotal_cost
                ) VALUES (
                    :bom_id, :material_id, :quantity_required, :wastage_percent, :unit_cost, :subtotal_cost
                )
            ");

            foreach ($processedItems as $item) {
                $insertItem->execute([
                    ':bom_id'            => $bomId,
                    ':material_id'       => $item['material_id'],
                    ':quantity_required' => number_format((float)$item['quantity_required'], 4, '.', ''),
                    ':wastage_percent'   => number_format((float)$item['wastage_percent'], 2, '.', ''),
                    ':unit_cost'         => number_format((float)$item['unit_cost'], 2, '.', ''),
                    ':subtotal_cost'     => number_format((float)$item['subtotal_cost'], 2, '.', '')
                ]);
            }

            // Sync cost price on product if BOM is active
            if ($status === 'active') {
                $unitCostCalc = round($totalCost / (float)$yieldQuantity, 2);
                $updProd = $pdo->prepare("UPDATE products SET cost_price = :cost WHERE id = :id");
                $updProd->execute([':cost' => $unitCostCalc, ':id' => $productId]);
            }

            $pdo->commit();

            json_response(true, [
                'id'       => $bomId,
                'bom_code' => $bomCode,
                'message'  => "Bill of Materials '{$bomCode}' created successfully."
            ], null, 201);
        } catch (\Throwable $t) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            json_response(false, null, 'Failed to save BOM: ' . $t->getMessage(), null, 500);
        }
        break;

    case 'update':
        if ($method !== 'POST') {
            json_response(false, null, 'Method not allowed.', null, 405);
        }

        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            json_response(false, null, 'Invalid BOM ID.', null, 422);
        }

        $existStmt = $pdo->prepare("SELECT * FROM boms WHERE id = :id LIMIT 1");
        $existStmt->execute([':id' => $id]);
        $existing = $existStmt->fetch(PDO::FETCH_ASSOC);
        if (!$existing) {
            json_response(false, null, 'Bill of Materials not found.', null, 404);
        }

        $productId = (int)($input['product_id'] ?? $existing['product_id']);
        $version = max(1, (int)($input['version'] ?? $existing['version']));
        $yieldQuantity = filter_var($input['yield_quantity'] ?? $existing['yield_quantity'], FILTER_VALIDATE_FLOAT);
        $status = strtolower(trim((string)($input['status'] ?? $existing['status'])));
        $notes = trim((string)($input['notes'] ?? ''));
        $bomCode = trim((string)($input['bom_code'] ?? $existing['bom_code']));
        $rawItems = $input['items'] ?? [];

        if (!is_array($rawItems) && is_string($rawItems)) {
            $rawItems = json_decode($rawItems, true) ?: [];
        }

        if ($yieldQuantity === false || $yieldQuantity <= 0) {
            json_response(false, null, 'Yield quantity must be greater than 0.', null, 422);
        }

        if (!in_array($status, ['draft', 'active', 'archived'], true)) {
            $status = $existing['status'];
        }

        if ($bomCode === '') {
            $bomCode = generate_bom_code($pdo, $productId, $version);
        }

        $dupCode = $pdo->prepare("SELECT id FROM boms WHERE bom_code = :code AND id != :id LIMIT 1");
        $dupCode->execute([':code' => $bomCode, ':id' => $id]);
        if ($dupCode->fetch()) {
            json_response(false, null, "BOM code '{$bomCode}' is already in use by another BOM.", null, 409);
        }

        $dupVer = $pdo->prepare("SELECT id FROM boms WHERE product_id = :pid AND version = :ver AND id != :id LIMIT 1");
        $dupVer->execute([':pid' => $productId, ':ver' => $version, ':id' => $id]);
        if ($dupVer->fetch()) {
            json_response(false, null, "Version {$version} already exists for this product.", null, 409);
        }

        if (empty($rawItems)) {
            json_response(false, null, 'A Bill of Materials must have at least one component line item.', null, 422);
        }

        $processedItems = [];
        $totalCost = 0.00;

        foreach ($rawItems as $idx => $line) {
            $matId = (int)($line['material_id'] ?? 0);
            $qty = filter_var($line['quantity'] ?? $line['quantity_required'] ?? 0, FILTER_VALIDATE_FLOAT);
            $scrap = filter_var($line['scrap_percentage'] ?? $line['wastage_percent'] ?? 0, FILTER_VALIDATE_FLOAT);
            $userCost = isset($line['unit_cost']) ? filter_var($line['unit_cost'], FILTER_VALIDATE_FLOAT) : null;

            if ($matId <= 0 || $qty === false || $qty <= 0) {
                continue;
            }

            $matStmt = $pdo->prepare("SELECT id, name, unit_cost FROM materials WHERE id = :id LIMIT 1");
            $matStmt->execute([':id' => $matId]);
            $mat = $matStmt->fetch(PDO::FETCH_ASSOC);
            if (!$mat) {
                json_response(false, null, "Material at line " . ($idx + 1) . " does not exist.", null, 422);
            }

            $scrap = ($scrap === false || $scrap < 0) ? 0.00 : (float)$scrap;
            $unitCost = ($userCost !== null && $userCost !== false && $userCost >= 0) ? (float)$userCost : (float)$mat['unit_cost'];

            $lineCost = round(($qty * (1 + ($scrap / 100))) * $unitCost, 2);
            $totalCost += $lineCost;

            $processedItems[] = [
                'material_id'       => $matId,
                'quantity_required' => $qty,
                'wastage_percent'   => $scrap,
                'unit_cost'         => $unitCost,
                'subtotal_cost'     => $lineCost
            ];
        }

        if (empty($processedItems)) {
            json_response(false, null, 'Please specify at least one valid material with quantity greater than zero.', null, 422);
        }

        $isActive = ($status === 'active') ? 1 : 0;

        $pdo->beginTransaction();
        try {
            if ($status === 'active') {
                $archStmt = $pdo->prepare("
                    UPDATE boms 
                    SET status = 'archived', is_active = 0 
                    WHERE product_id = :pid AND id != :id AND status = 'active'
                ");
                $archStmt->execute([':pid' => $productId, ':id' => $id]);
            }

            $updBom = $pdo->prepare("
                UPDATE boms SET 
                    bom_code = :bom_code,
                    version = :version,
                    yield_quantity = :yield_quantity,
                    status = :status,
                    total_cost = :total_cost,
                    is_active = :is_active,
                    notes = :notes
                WHERE id = :id
            ");

            $updBom->execute([
                ':bom_code'       => $bomCode,
                ':version'        => $version,
                ':yield_quantity' => number_format((float)$yieldQuantity, 2, '.', ''),
                ':status'         => $status,
                ':total_cost'     => number_format((float)$totalCost, 2, '.', ''),
                ':is_active'      => $isActive,
                ':notes'          => $notes !== '' ? $notes : null,
                ':id'             => $id
            ]);

            // Replace line items
            $delItems = $pdo->prepare("DELETE FROM bom_items WHERE bom_id = :id");
            $delItems->execute([':id' => $id]);

            $insertItem = $pdo->prepare("
                INSERT INTO bom_items (
                    bom_id, material_id, quantity_required, wastage_percent, unit_cost, subtotal_cost
                ) VALUES (
                    :bom_id, :material_id, :quantity_required, :wastage_percent, :unit_cost, :subtotal_cost
                )
            ");

            foreach ($processedItems as $item) {
                $insertItem->execute([
                    ':bom_id'            => $id,
                    ':material_id'       => $item['material_id'],
                    ':quantity_required' => number_format((float)$item['quantity_required'], 4, '.', ''),
                    ':wastage_percent'   => number_format((float)$item['wastage_percent'], 2, '.', ''),
                    ':unit_cost'         => number_format((float)$item['unit_cost'], 2, '.', ''),
                    ':subtotal_cost'     => number_format((float)$item['subtotal_cost'], 2, '.', '')
                ]);
            }

            if ($status === 'active') {
                $unitCostCalc = round($totalCost / (float)$yieldQuantity, 2);
                $updProd = $pdo->prepare("UPDATE products SET cost_price = :cost WHERE id = :id");
                $updProd->execute([':cost' => $unitCostCalc, ':id' => $productId]);
            }

            $pdo->commit();

            json_response(true, [
                'id'       => $id,
                'bom_code' => $bomCode,
                'message'  => "Bill of Materials '{$bomCode}' updated successfully."
            ]);
        } catch (\Throwable $t) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            json_response(false, null, 'Failed to update BOM: ' . $t->getMessage(), null, 500);
        }
        break;

    case 'set_status':
        if ($method !== 'POST') {
            json_response(false, null, 'Method not allowed.', null, 405);
        }

        $id = (int)($input['id'] ?? 0);
        $newStatus = strtolower(trim((string)($input['status'] ?? '')));

        if ($id <= 0 || !in_array($newStatus, ['draft', 'active', 'archived'], true)) {
            json_response(false, null, 'Invalid BOM ID or status parameter.', null, 422);
        }

        $stmt = $pdo->prepare("SELECT id, product_id, bom_code, total_cost, yield_quantity FROM boms WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $bom = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$bom) {
            json_response(false, null, 'BOM record not found.', null, 404);
        }

        $productId = (int)$bom['product_id'];
        $isActive = ($newStatus === 'active') ? 1 : 0;

        $pdo->beginTransaction();
        try {
            if ($newStatus === 'active') {
                $archStmt = $pdo->prepare("
                    UPDATE boms 
                    SET status = 'archived', is_active = 0 
                    WHERE product_id = :pid AND id != :id AND status = 'active'
                ");
                $archStmt->execute([':pid' => $productId, ':id' => $id]);

                // Update product cost price
                $unitCostCalc = round((float)$bom['total_cost'] / max(0.01, (float)$bom['yield_quantity']), 2);
                $updProd = $pdo->prepare("UPDATE products SET cost_price = :cost WHERE id = :id");
                $updProd->execute([':cost' => $unitCostCalc, ':id' => $productId]);
            }

            $upd = $pdo->prepare("UPDATE boms SET status = :status, is_active = :is_active WHERE id = :id");
            $upd->execute([':status' => $newStatus, ':is_active' => $isActive, ':id' => $id]);

            $pdo->commit();

            json_response(true, [
                'status'  => $newStatus,
                'message' => "BOM '{$bom['bom_code']}' status changed to " . ucfirst($newStatus) . "."
            ]);
        } catch (\Throwable $t) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            json_response(false, null, 'Failed to update status: ' . $t->getMessage(), null, 500);
        }
        break;

    case 'delete':
        if ($method !== 'POST') {
            json_response(false, null, 'Method not allowed.', null, 405);
        }

        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            json_response(false, null, 'Invalid BOM ID.', null, 422);
        }

        $stmt = $pdo->prepare("SELECT id, bom_code FROM boms WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $bom = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$bom) {
            json_response(false, null, 'Bill of Materials not found.', null, 404);
        }

        $poStmt = $pdo->prepare("SELECT COUNT(*) FROM production_orders WHERE bom_id = :id");
        $poStmt->execute([':id' => $id]);
        $poCount = (int)$poStmt->fetchColumn();

        if ($poCount > 0) {
            json_response(
                false, 
                null, 
                "Cannot delete BOM '{$bom['bom_code']}' because it is linked to {$poCount} production order(s).",
                null,
                409
            );
        }

        $del = $pdo->prepare("DELETE FROM boms WHERE id = :id");
        $del->execute([':id' => $id]);

        json_response(true, ['message' => "BOM '{$bom['bom_code']}' deleted successfully."]);
        break;

    default:
        json_response(false, null, "Unknown or unsupported action '{$action}'.", null, 400);
}
