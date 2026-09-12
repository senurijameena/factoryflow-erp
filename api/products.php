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
$appUrl = rtrim((string)(defined('APP_URL') ? APP_URL : Config::get('APP_URL', 'http://localhost/factoryflow')), '/');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$rawInput = file_get_contents('php://input');
$jsonInput = json_decode($rawInput, true);
$input = is_array($jsonInput) ? array_merge($_POST, $jsonInput) : $_POST;

$action = trim((string)($input['action'] ?? $_GET['action'] ?? 'list'));

if ($method === 'POST') {
    enforce_csrf();
}

function process_product_image_upload(?array $file, ?string $currentImage = null, bool $removeImage = false): ?string
{
    if ($removeImage && $currentImage) {
        $oldFile = dirname(__DIR__) . '/' . ltrim($currentImage, '/\\');
        if (file_exists($oldFile) && is_file($oldFile)) {
            @unlink($oldFile);
        }
        $currentImage = null;
    }

    if (!$file || !isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return $currentImage;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new InvalidArgumentException('Image upload failed with error code: ' . $file['error']);
    }

    $maxBytes = defined('MAX_UPLOAD_SIZE_BYTES') ? MAX_UPLOAD_SIZE_BYTES : 2097152;
    if ($file['size'] > $maxBytes) {
        throw new InvalidArgumentException('Uploaded image exceeds the 2MB size limit.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $allowedMimes = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp'
    ];

    if (!array_key_exists($mime, $allowedMimes)) {
        throw new InvalidArgumentException('Invalid image format. Allowed formats: JPG, PNG, WebP.');
    }

    $ext = $allowedMimes[$mime];
    $uploadDir = dirname(__DIR__) . '/assets/uploads/products';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $filename = 'prod_' . bin2hex(random_bytes(8)) . '_' . time() . '.' . $ext;
    $destination = $uploadDir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new RuntimeException('Failed to store uploaded image on server.');
    }

    if ($currentImage) {
        $oldFile = dirname(__DIR__) . '/' . ltrim($currentImage, '/\\');
        if (file_exists($oldFile) && is_file($oldFile)) {
            @unlink($oldFile);
        }
    }

    return 'assets/uploads/products/' . $filename;
}

switch ($action) {
    case 'list':
        $search = trim((string)($_GET['search'] ?? ''));
        $categoryId = (int)($_GET['category_id'] ?? 0);
        $status = trim((string)($_GET['status'] ?? 'active'));
        $stockStatus = trim((string)($_GET['stock_status'] ?? 'all'));

        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(100, max(5, (int)($_GET['per_page'] ?? 15)));
        $offset = ($page - 1) * $perPage;

        $where = ["1=1"];
        $params = [];

        if ($search !== '') {
            $where[] = "(p.name LIKE :search_name OR p.sku LIKE :search_sku OR p.barcode LIKE :search_barcode)";
            $params[':search_name'] = '%' . $search . '%';
            $params[':search_sku'] = '%' . $search . '%';
            $params[':search_barcode'] = '%' . $search . '%';
        }

        if ($categoryId > 0) {
            $where[] = "p.category_id = :category_id";
            $params[':category_id'] = $categoryId;
        }

        if ($status === 'active') {
            $where[] = "p.is_active = 1";
        } elseif ($status === 'inactive') {
            $where[] = "p.is_active = 0";
        }

        if ($stockStatus === 'low') {
            $where[] = "p.current_stock <= p.reorder_level AND p.current_stock > 0";
        } elseif ($stockStatus === 'out') {
            $where[] = "p.current_stock <= 0";
        } elseif ($stockStatus === 'healthy') {
            $where[] = "p.current_stock > p.reorder_level";
        }

        $whereClause = implode(' AND ', $where);

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM products p WHERE {$whereClause}");
        $countStmt->execute($params);
        $totalItems = (int)$countStmt->fetchColumn();

        $sql = "
            SELECT p.*, 
                   c.name as category_name,
                   u.name as unit_name, 
                   u.symbol as unit_symbol,
                   (SELECT COUNT(*) FROM boms b WHERE b.product_id = p.id AND b.is_active = 1) as active_boms_count
            FROM products p
            JOIN categories c ON c.id = p.category_id
            JOIN units u ON u.id = p.unit_id
            WHERE {$whereClause}
            ORDER BY p.id DESC
            LIMIT :offset, :per_page
        ";

        $stmt = $pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue(':per_page', $perPage, PDO::PARAM_INT);
        $stmt->execute();
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $totalPages = (int)ceil($totalItems / $perPage);

        json_response(true, $products, null, [
            'page'        => $page,
            'per_page'    => $perPage,
            'total_items' => $totalItems,
            'total_pages' => $totalPages
        ]);
        break;

    case 'get':
        $id = (int)($_GET['id'] ?? $input['id'] ?? 0);
        if ($id <= 0) {
            json_response(false, null, 'Invalid product ID.', null, 422);
        }

        $stmt = $pdo->prepare("
            SELECT p.*, 
                   c.name as category_name, 
                   u.name as unit_name, 
                   u.symbol as unit_symbol,
                   (SELECT COUNT(*) FROM boms b WHERE b.product_id = p.id AND b.is_active = 1) as active_boms_count,
                   (SELECT COUNT(*) FROM production_orders po WHERE po.product_id = p.id AND po.status IN ('approved', 'in_progress')) as active_production_orders
            FROM products p
            JOIN categories c ON c.id = p.category_id
            JOIN units u ON u.id = p.unit_id
            WHERE p.id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            json_response(false, null, 'Product not found.', null, 404);
        }

        json_response(true, $product);
        break;

    case 'create':
        if ($method !== 'POST') {
            json_response(false, null, 'Method not allowed.', null, 405);
        }

        $name = trim((string)($input['name'] ?? ''));
        $sku = strtoupper(trim((string)($input['sku'] ?? '')));
        $barcode = trim((string)($input['barcode'] ?? ''));
        $categoryId = (int)($input['category_id'] ?? 0);
        $unitId = (int)($input['unit_id'] ?? 0);
        $costPrice = (float)($input['cost_price'] ?? 0.0);
        $salePrice = (float)($input['sale_price'] ?? 0.0);
        $currentStock = max(0.0, (float)($input['current_stock'] ?? 0.0));
        $reorderLevel = max(0.0, (float)($input['reorder_level'] ?? $input['min_stock_level'] ?? 0.0));
        $leadTimeDays = max(0, (int)($input['lead_time_days'] ?? 0));
        $description = trim((string)($input['description'] ?? ''));

        if ($name === '') {
            json_response(false, null, 'Product name is required.', null, 422);
        }

        if ($categoryId <= 0) {
            json_response(false, null, 'Please select a valid category.', null, 422);
        }

        if ($unitId <= 0) {
            json_response(false, null, 'Please select a valid unit of measure.', null, 422);
        }

        if ($costPrice < 0 || $salePrice < 0) {
            json_response(false, null, 'Prices cannot be negative.', null, 422);
        }

        $catCheck = $pdo->prepare("SELECT id FROM categories WHERE id = :id LIMIT 1");
        $catCheck->execute([':id' => $categoryId]);
        if (!$catCheck->fetch()) {
            json_response(false, null, 'Selected category does not exist.', null, 422);
        }

        $unitCheck = $pdo->prepare("SELECT id FROM units WHERE id = :id LIMIT 1");
        $unitCheck->execute([':id' => $unitId]);
        if (!$unitCheck->fetch()) {
            json_response(false, null, 'Selected unit of measure does not exist.', null, 422);
        }

        if ($sku === '') {
            $sku = generate_document_no($pdo, 'products', 'sku', 'PRD-');
        }

        $skuCheck = $pdo->prepare("SELECT id FROM products WHERE sku = :sku LIMIT 1");
        $skuCheck->execute([':sku' => $sku]);
        if ($skuCheck->fetch()) {
            json_response(false, null, "A product with SKU '{$sku}' already exists.", null, 409);
        }

        if ($barcode !== '') {
            $barCheck = $pdo->prepare("SELECT id FROM products WHERE barcode = :barcode LIMIT 1");
            $barCheck->execute([':barcode' => $barcode]);
            if ($barCheck->fetch()) {
                json_response(false, null, "A product with barcode '{$barcode}' already exists.", null, 409);
            }
        }

        $imagePath = null;
        if (!empty($_FILES['image'])) {
            try {
                $imagePath = process_product_image_upload($_FILES['image']);
            } catch (Exception $e) {
                json_response(false, null, $e->getMessage(), null, 422);
            }
        }

        $insertStmt = $pdo->prepare("
            INSERT INTO products (
                sku, barcode, name, category_id, unit_id, 
                sale_price, cost_price, current_stock, reorder_level, 
                lead_time_days, description, image, is_active, created_at
            ) VALUES (
                :sku, :barcode, :name, :category_id, :unit_id, 
                :sale_price, :cost_price, :current_stock, :reorder_level, 
                :lead_time_days, :description, :image, 1, NOW()
            )
        ");
        $insertStmt->execute([
            ':sku'            => $sku,
            ':barcode'        => $barcode !== '' ? $barcode : null,
            ':name'           => $name,
            ':category_id'    => $categoryId,
            ':unit_id'        => $unitId,
            ':sale_price'     => $salePrice,
            ':cost_price'     => $costPrice,
            ':current_stock'  => $currentStock,
            ':reorder_level'  => $reorderLevel,
            ':lead_time_days' => $leadTimeDays,
            ':description'    => $description !== '' ? $description : null,
            ':image'          => $imagePath
        ]);

        $newId = (int)$pdo->lastInsertId();
        $redirectUrl = $appUrl . '/pages/products/index.php';

        json_response(true, [
            'id'       => $newId,
            'sku'      => $sku,
            'redirect' => $redirectUrl,
            'message'  => 'Product saved successfully'
        ], null, 201);
        break;

    case 'update':
        if ($method !== 'POST') {
            json_response(false, null, 'Method not allowed.', null, 405);
        }

        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            json_response(false, null, 'Invalid product ID.', null, 422);
        }

        $existStmt = $pdo->prepare("SELECT * FROM products WHERE id = :id LIMIT 1");
        $existStmt->execute([':id' => $id]);
        $existing = $existStmt->fetch(PDO::FETCH_ASSOC);
        if (!$existing) {
            json_response(false, null, 'Product not found.', null, 404);
        }

        $name = trim((string)($input['name'] ?? $existing['name']));
        $sku = strtoupper(trim((string)($input['sku'] ?? $existing['sku'])));
        $barcode = trim((string)($input['barcode'] ?? ($existing['barcode'] ?? '')));
        $categoryId = (int)($input['category_id'] ?? $existing['category_id']);
        $unitId = (int)($input['unit_id'] ?? $existing['unit_id']);
        $costPrice = (float)($input['cost_price'] ?? $existing['cost_price']);
        $salePrice = (float)($input['sale_price'] ?? $existing['sale_price']);
        $reorderLevel = max(0.0, (float)($input['reorder_level'] ?? $input['min_stock_level'] ?? $existing['reorder_level']));
        $leadTimeDays = max(0, (int)($input['lead_time_days'] ?? $existing['lead_time_days']));
        $description = trim((string)($input['description'] ?? ($existing['description'] ?? '')));
        $isActive = isset($input['is_active']) ? (int)(bool)$input['is_active'] : (int)$existing['is_active'];
        $removeImage = !empty($input['remove_image']) && $input['remove_image'] !== 'false';

        if ($name === '') {
            json_response(false, null, 'Product name cannot be blank.', null, 422);
        }

        if ($sku === '') {
            json_response(false, null, 'Product SKU cannot be blank.', null, 422);
        }

        $skuCheck = $pdo->prepare("SELECT id FROM products WHERE sku = :sku AND id != :id LIMIT 1");
        $skuCheck->execute([':sku' => $sku, ':id' => $id]);
        if ($skuCheck->fetch()) {
            json_response(false, null, "Another product with SKU '{$sku}' already exists.", null, 409);
        }

        if ($barcode !== '') {
            $barCheck = $pdo->prepare("SELECT id FROM products WHERE barcode = :barcode AND id != :id LIMIT 1");
            $barCheck->execute([':barcode' => $barcode, ':id' => $id]);
            if ($barCheck->fetch()) {
                json_response(false, null, "Another product with barcode '{$barcode}' already exists.", null, 409);
            }
        }

        $catCheck = $pdo->prepare("SELECT id FROM categories WHERE id = :id LIMIT 1");
        $catCheck->execute([':id' => $categoryId]);
        if (!$catCheck->fetch()) {
            json_response(false, null, 'Selected category does not exist.', null, 422);
        }

        $unitCheck = $pdo->prepare("SELECT id FROM units WHERE id = :id LIMIT 1");
        $unitCheck->execute([':id' => $unitId]);
        if (!$unitCheck->fetch()) {
            json_response(false, null, 'Selected unit of measure does not exist.', null, 422);
        }

        $imagePath = $existing['image'];
        try {
            $uploadedFile = $_FILES['image'] ?? null;
            $imagePath = process_product_image_upload($uploadedFile, $imagePath, $removeImage);
        } catch (Exception $e) {
            json_response(false, null, $e->getMessage(), null, 422);
        }

        $updateStmt = $pdo->prepare("
            UPDATE products 
            SET sku = :sku, 
                barcode = :barcode, 
                name = :name, 
                category_id = :category_id, 
                unit_id = :unit_id, 
                sale_price = :sale_price, 
                cost_price = :cost_price, 
                reorder_level = :reorder_level, 
                lead_time_days = :lead_time_days, 
                description = :description, 
                image = :image, 
                is_active = :is_active 
            WHERE id = :id
        ");
        $updateStmt->execute([
            ':sku'            => $sku,
            ':barcode'        => $barcode !== '' ? $barcode : null,
            ':name'           => $name,
            ':category_id'    => $categoryId,
            ':unit_id'        => $unitId,
            ':sale_price'     => $salePrice,
            ':cost_price'     => $costPrice,
            ':reorder_level'  => $reorderLevel,
            ':lead_time_days' => $leadTimeDays,
            ':description'    => $description !== '' ? $description : null,
            ':image'          => $imagePath,
            ':is_active'      => $isActive,
            ':id'             => $id
        ]);

        $redirectUrl = $appUrl . '/pages/products/index.php';
        json_response(true, [
            'id'       => $id,
            'redirect' => $redirectUrl,
            'message'  => 'Product saved successfully'
        ]);
        break;

    case 'delete':
        if ($method !== 'POST') {
            json_response(false, null, 'Method not allowed.', null, 405);
        }

        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            json_response(false, null, 'Invalid product ID.', null, 422);
        }

        $prodOrdersStmt = $pdo->prepare("
            SELECT COUNT(*) FROM production_orders 
            WHERE product_id = :id AND status IN ('approved', 'in_progress')
        ");
        $prodOrdersStmt->execute([':id' => $id]);
        $activeOrders = (int)$prodOrdersStmt->fetchColumn();

        if ($activeOrders > 0) {
            json_response(false, null, "Cannot delete product: It is linked to {$activeOrders} active production order(s).", null, 400);
        }

        $salesOrdersStmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM sales_order_items soi
            JOIN sales_orders so ON so.id = soi.so_id
            WHERE soi.product_id = :id AND so.status IN ('confirmed', 'shipped')
        ");
        $salesOrdersStmt->execute([':id' => $id]);
        $activeSales = (int)$salesOrdersStmt->fetchColumn();

        if ($activeSales > 0) {
            json_response(false, null, "Cannot delete product: It is referenced in {$activeSales} active sales order(s).", null, 400);
        }

        $allPoStmt = $pdo->prepare("SELECT COUNT(*) FROM production_orders WHERE product_id = :id");
        $allPoStmt->execute([':id' => $id]);
        $allPoCount = (int)$allPoStmt->fetchColumn();

        $allSoStmt = $pdo->prepare("SELECT COUNT(*) FROM sales_order_items WHERE product_id = :id");
        $allSoStmt->execute([':id' => $id]);
        $allSoCount = (int)$allSoStmt->fetchColumn();

        if ($allPoCount > 0 || $allSoCount > 0) {
            $update = $pdo->prepare("UPDATE products SET is_active = 0 WHERE id = :id");
            $update->execute([':id' => $id]);
            json_response(true, ['message' => 'Product has historical orders and was archived (deactivated) safely.']);
        } else {
            $prodRow = $pdo->prepare("SELECT image FROM products WHERE id = :id LIMIT 1");
            $prodRow->execute([':id' => $id]);
            $row = $prodRow->fetch(PDO::FETCH_ASSOC);

            if ($row && !empty($row['image'])) {
                $filePath = dirname(__DIR__) . '/' . ltrim($row['image'], '/\\');
                if (file_exists($filePath) && is_file($filePath)) {
                    @unlink($filePath);
                }
            }

            $delete = $pdo->prepare("DELETE FROM products WHERE id = :id");
            $delete->execute([':id' => $id]);
            json_response(true, ['message' => 'Product permanently deleted.']);
        }
        break;

    default:
        json_response(false, null, 'Invalid action specified.', null, 400);
        break;
}
