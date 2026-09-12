<?php
declare(strict_types=1);

$pageTitle = 'Inventory Stock';

require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/rbac.php';

require_role([ROLE_ADMIN, ROLE_MANAGER]);

$pdo = Database::getConnection();
$appUrl = rtrim((string)(defined('APP_URL') ? APP_URL : Config::get('APP_URL', 'http://localhost/factoryflow')), '/');

$productsStmt = $pdo->query("
    SELECT p.id, p.sku as item_code, p.name as item_name, p.current_stock, p.reorder_level, 'Product' as item_type, u.symbol as unit_symbol
    FROM products p
    JOIN units u ON u.id = p.unit_id
    WHERE p.is_active = 1
    ORDER BY p.current_stock ASC
");
$products = $productsStmt->fetchAll(PDO::FETCH_ASSOC);

$materialsStmt = $pdo->query("
    SELECT m.id, m.code as item_code, m.name as item_name, m.current_stock, m.reorder_level, 'Material' as item_type, u.symbol as unit_symbol
    FROM materials m
    JOIN units u ON u.id = m.unit_id
    WHERE m.is_active = 1
    ORDER BY m.current_stock ASC
");
$materials = $materialsStmt->fetchAll(PDO::FETCH_ASSOC);

$inventoryItems = array_merge($products, $materials);

require_once __DIR__ . '/../../includes/layout_header.php';
?>

<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1">Inventory Stock Management</h4>
        <p class="text-muted small mb-0">Track real-time stock balances across finished goods and raw materials.</p>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
        <span class="fw-semibold">All Warehouse Stock Items (<?= count($inventoryItems) ?>)</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Item Code</th>
                        <th>Name</th>
                        <th>Type</th>
                        <th class="text-end">Current Stock</th>
                        <th class="text-end">Reorder Level</th>
                        <th class="text-center">Stock Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($inventoryItems)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-boxes-stacked d-block mb-2" style="font-size: 2rem;"></i>
                                No stock inventory recorded yet.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($inventoryItems as $item): 
                            $isLow = (float)$item['current_stock'] <= (float)$item['reorder_level'];
                            $isOut = (float)$item['current_stock'] <= 0;
                        ?>
                            <tr>
                                <td class="fw-semibold text-primary"><?= e($item['item_code']) ?></td>
                                <td class="fw-medium text-dark"><?= e($item['item_name']) ?></td>
                                <td>
                                    <span class="badge bg-light text-secondary border">
                                        <?= e($item['item_type']) ?>
                                    </span>
                                </td>
                                <td class="text-end fw-bold <?= $isLow ? 'text-danger' : 'text-dark' ?>">
                                    <?= number_format((float)$item['current_stock'], 2) ?> <?= e($item['unit_symbol']) ?>
                                </td>
                                <td class="text-end text-muted">
                                    <?= number_format((float)$item['reorder_level'], 2) ?> <?= e($item['unit_symbol']) ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($isOut): ?>
                                        <span class="badge bg-danger-subtle text-danger border border-danger">Out of Stock</span>
                                    <?php elseif ($isLow): ?>
                                        <span class="badge bg-warning-subtle text-warning border border-warning">Low Stock</span>
                                    <?php else: ?>
                                        <span class="badge bg-success-subtle text-success border border-success">Healthy</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/layout_footer.php'; ?>
