<?php
declare(strict_types=1);

$pageTitle = 'Units of Measure';

require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/rbac.php';

require_role([ROLE_ADMIN, ROLE_MANAGER]);

$pdo = Database::getConnection();
$appUrl = rtrim((string)(defined('APP_URL') ? APP_URL : Config::get('APP_URL', 'http://localhost/factoryflow')), '/');

$unitsStmt = $pdo->query("SELECT * FROM units ORDER BY name ASC");
$units = $unitsStmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../../includes/layout_header.php';
?>

<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1">Units of Measure (UOM)</h4>
        <p class="text-muted small mb-0">Standardize weight, volume, dimension, and packaging units across bills of materials.</p>
    </div>
    <button type="button" class="btn btn-primary" onclick="App.toast('info', 'Unit creation modal will be configured in Step 3.')">
        <i class="fa-solid fa-plus me-1"></i> Add Unit
    </button>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
        <span class="fw-semibold">All Standard Units (<?= count($units) ?>)</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Unit Name</th>
                        <th>Standard Symbol</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($units)): ?>
                        <tr>
                            <td colspan="2" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-ruler d-block mb-2" style="font-size: 2rem;"></i>
                                No units of measure added yet.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($units as $u): ?>
                            <tr>
                                <td class="fw-semibold text-dark"><?= e($u['name']) ?></td>
                                <td>
                                    <span class="badge bg-secondary-subtle text-secondary font-monospace">
                                        <?= e($u['symbol']) ?>
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
