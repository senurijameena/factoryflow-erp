<?php
declare(strict_types=1);

$pageTitle = 'Bills of Materials (BOM)';

require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/rbac.php';

require_role([ROLE_ADMIN, ROLE_MANAGER]);

$pdo = Database::getConnection();
$appUrl = rtrim((string)(defined('APP_URL') ? APP_URL : Config::get('APP_URL', 'http://localhost/factoryflow')), '/');

$bomsStmt = $pdo->query("
    SELECT b.*, p.name as product_name, p.sku as product_sku, u.name as creator_name,
           (SELECT COUNT(*) FROM bom_items bi WHERE bi.bom_id = b.id) as total_components
    FROM boms b
    JOIN products p ON p.id = b.product_id
    JOIN users u ON u.id = b.created_by
    ORDER BY b.id DESC
");
$boms = $bomsStmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../../includes/layout_header.php';
?>

<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1">Bills of Materials</h4>
        <p class="text-muted small mb-0">Configure product engineering recipes, material consumption ratios, and scrap allowances.</p>
    </div>
    <button type="button" class="btn btn-primary" onclick="App.toast('info', 'BOM creation will be configured in Step 3.')">
        <i class="fa-solid fa-plus me-1"></i> Create BOM
    </button>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
        <span class="fw-semibold">All Engineering BOMs (<?= count($boms) ?>)</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th class="text-center">Version</th>
                        <th class="text-center">Components</th>
                        <th class="text-center">Status</th>
                        <th>Created By</th>
                        <th>Created Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($boms)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-layer-group d-block mb-2" style="font-size: 2rem;"></i>
                                No bills of materials configured yet.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($boms as $bom): ?>
                            <tr>
                                <td>
                                    <div class="fw-medium text-dark"><?= e($bom['product_name']) ?></div>
                                    <small class="text-muted"><?= e($bom['product_sku']) ?></small>
                                </td>
                                <td class="text-center">v<?= (int)$bom['version'] ?></td>
                                <td class="text-center fw-semibold"><?= (int)$bom['total_components'] ?></td>
                                <td class="text-center">
                                    <span class="badge <?= (int)$bom['is_active'] === 1 ? 'bg-success-subtle text-success border border-success' : 'bg-secondary-subtle text-secondary border' ?>">
                                        <?= (int)$bom['is_active'] === 1 ? 'Active' : 'Archived' ?>
                                    </span>
                                </td>
                                <td class="small text-muted"><?= e($bom['creator_name']) ?></td>
                                <td class="small text-muted"><?= date('M d, Y', strtotime($bom['created_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/layout_footer.php'; ?>
