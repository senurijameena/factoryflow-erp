<?php
declare(strict_types=1);

$pageTitle = 'Product Catalog';

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
    SELECT p.*, c.name as category_name, u.symbol as unit_symbol
    FROM products p
    JOIN categories c ON c.id = p.category_id
    JOIN units u ON u.id = p.unit_id
    ORDER BY p.id DESC
");
$products = $productsStmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../../includes/layout_header.php';
?>

<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1">Product Catalog</h4>
        <p class="text-muted small mb-0">Manage manufactured finished goods, bill of material linkages, and price books.</p>
    </div>
    <button type="button" class="btn btn-primary" onclick="App.toast('info', 'Product addition modal will be enabled in Step 3.')">
        <i class="fa-solid fa-plus me-1"></i> Add Product
    </button>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
        <span class="fw-semibold">All Finished Goods (<?= count($products) ?>)</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>SKU</th>
                        <th>Product Name</th>
                        <th>Category</th>
                        <th class="text-end">Sale Price</th>
                        <th class="text-end">Cost Price</th>
                        <th class="text-end">Current Stock</th>
                        <th class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($products)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-box-open d-block mb-2" style="font-size: 2rem;"></i>
                                No products registered yet.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($products as $p): ?>
                            <tr>
                                <td class="fw-semibold text-primary"><?= e($p['sku']) ?></td>
                                <td class="fw-medium text-dark"><?= e($p['name']) ?></td>
                                <td><span class="badge bg-light text-secondary border"><?= e($p['category_name']) ?></span></td>
                                <td class="text-end fw-semibold">$<?= number_format((float)$p['sale_price'], 2) ?></td>
                                <td class="text-end text-muted">$<?= number_format((float)$p['cost_price'], 2) ?></td>
                                <td class="text-end fw-semibold"><?= number_format((float)$p['current_stock'], 2) ?> <?= e($p['unit_symbol']) ?></td>
                                <td class="text-center">
                                    <span class="badge <?= (int)$p['is_active'] === 1 ? 'bg-success-subtle text-success border border-success' : 'bg-secondary-subtle text-secondary border' ?>">
                                        <?= (int)$p['is_active'] === 1 ? 'Active' : 'Disabled' ?>
                                    </span>
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
