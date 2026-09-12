<?php
declare(strict_types=1);

$pageTitle = 'Item Categories';

require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/rbac.php';

require_role([ROLE_ADMIN, ROLE_MANAGER]);

$pdo = Database::getConnection();
$appUrl = rtrim((string)(defined('APP_URL') ? APP_URL : Config::get('APP_URL', 'http://localhost/factoryflow')), '/');

$categoriesStmt = $pdo->query("SELECT * FROM categories ORDER BY type ASC, name ASC");
$categories = $categoriesStmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../../includes/layout_header.php';
?>

<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1">Item Categories</h4>
        <p class="text-muted small mb-0">Classify finished goods and procurement materials into operational taxonomies.</p>
    </div>
    <button type="button" class="btn btn-primary" onclick="App.toast('info', 'Category creation modal will be configured in Step 3.')">
        <i class="fa-solid fa-plus me-1"></i> Add Category
    </button>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
        <span class="fw-semibold">All Categories (<?= count($categories) ?>)</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Category Name</th>
                        <th>Classification Type</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($categories)): ?>
                        <tr>
                            <td colspan="3" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-tags d-block mb-2" style="font-size: 2rem;"></i>
                                No categories configured yet.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($categories as $c): ?>
                            <tr>
                                <td class="fw-semibold text-dark"><?= e($c['name']) ?></td>
                                <td>
                                    <span class="badge <?= $c['type'] === 'product' ? 'bg-primary-subtle text-primary border border-primary' : 'bg-info-subtle text-info border border-info' ?>">
                                        <?= e(ucfirst($c['type'])) ?>
                                    </span>
                                </td>
                                <td class="text-muted"><?= e($c['description'] ?? '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/layout_footer.php'; ?>
