<?php
declare(strict_types=1);

$pageTitle = 'Suppliers Directory';

require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/rbac.php';

require_role([ROLE_ADMIN, ROLE_MANAGER]);

$pdo = Database::getConnection();
$appUrl = rtrim((string)(defined('APP_URL') ? APP_URL : Config::get('APP_URL', 'http://localhost/factoryflow')), '/');

$suppliersStmt = $pdo->query("SELECT * FROM suppliers ORDER BY name ASC");
$suppliers = $suppliersStmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../../includes/layout_header.php';
?>

<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1">Suppliers Directory</h4>
        <p class="text-muted small mb-0">Manage vendor contact profiles, tax numbers, and procurement histories.</p>
    </div>
    <button type="button" class="btn btn-primary" onclick="App.toast('info', 'Supplier registration modal will be enabled in Step 4.')">
        <i class="fa-solid fa-plus me-1"></i> Add Supplier
    </button>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
        <span class="fw-semibold">All Registered Suppliers (<?= count($suppliers) ?>)</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Supplier Name</th>
                        <th>Contact Person</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>GST / Tax No</th>
                        <th class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($suppliers)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-truck d-block mb-2" style="font-size: 2rem;"></i>
                                No suppliers registered yet.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($suppliers as $s): ?>
                            <tr>
                                <td class="fw-semibold text-dark"><?= e($s['name']) ?></td>
                                <td><?= e($s['contact_person'] ?? '—') ?></td>
                                <td><?= e($s['email'] ?? '—') ?></td>
                                <td><?= e($s['phone'] ?? '—') ?></td>
                                <td><span class="font-monospace small"><?= e($s['gst_no'] ?? '—') ?></span></td>
                                <td class="text-center">
                                    <span class="badge <?= (int)$s['is_active'] === 1 ? 'bg-success-subtle text-success border border-success' : 'bg-secondary-subtle text-secondary border' ?>">
                                        <?= (int)$s['is_active'] === 1 ? 'Active' : 'Inactive' ?>
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
