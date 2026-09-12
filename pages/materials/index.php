<?php
declare(strict_types=1);

$pageTitle = 'Raw Materials';

require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/rbac.php';

require_role([ROLE_ADMIN, ROLE_MANAGER]);

$pdo = Database::getConnection();
$appUrl = rtrim((string)(defined('APP_URL') ? APP_URL : Config::get('APP_URL', 'http://localhost/factoryflow')), '/');

$materialsStmt = $pdo->query("
    SELECT m.*, c.name as category_name, u.symbol as unit_symbol, s.name as supplier_name
    FROM materials m
    JOIN categories c ON c.id = m.category_id
    JOIN units u ON u.id = m.unit_id
    LEFT JOIN suppliers s ON s.id = m.supplier_id
    ORDER BY m.id DESC
");
$materials = $materialsStmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../../includes/layout_header.php';
?>

<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1">Raw Materials</h4>
        <p class="text-muted small mb-0">Maintain procurement specifications, inventory stocks, and unit costs for manufacturing components.</p>
    </div>
    <button type="button" class="btn btn-primary" onclick="App.toast('info', 'Raw material entry modal will be activated in Step 3.')">
        <i class="fa-solid fa-plus me-1"></i> Add Material
    </button>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
        <span class="fw-semibold">All Raw Material Items (<?= count($materials) ?>)</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Material Code</th>
                        <th>Material Name</th>
                        <th>Category</th>
                        <th>Preferred Supplier</th>
                        <th class="text-end">Unit Cost</th>
                        <th class="text-end">Current Stock</th>
                        <th class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($materials)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-cubes-stacked d-block mb-2" style="font-size: 2rem;"></i>
                                No raw materials added yet.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($materials as $m): ?>
                            <tr>
                                <td class="fw-semibold text-primary"><?= e($m['code']) ?></td>
                                <td class="fw-medium text-dark"><?= e($m['name']) ?></td>
                                <td><span class="badge bg-light text-secondary border"><?= e($m['category_name']) ?></span></td>
                                <td class="text-muted"><?= e($m['supplier_name'] ?? '—') ?></td>
                                <td class="text-end fw-semibold">$<?= number_format((float)$m['unit_cost'], 2) ?></td>
                                <td class="text-end fw-semibold"><?= number_format((float)$m['current_stock'], 2) ?> <?= e($m['unit_symbol']) ?></td>
                                <td class="text-center">
                                    <span class="badge <?= (int)$m['is_active'] === 1 ? 'bg-success-subtle text-success border border-success' : 'bg-secondary-subtle text-secondary border' ?>">
                                        <?= (int)$m['is_active'] === 1 ? 'Active' : 'Disabled' ?>
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
