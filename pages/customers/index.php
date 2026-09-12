<?php
declare(strict_types=1);

$pageTitle = 'Customers Directory';

require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/rbac.php';

require_role([ROLE_ADMIN, ROLE_MANAGER]);

$pdo = Database::getConnection();
$appUrl = rtrim((string)(defined('APP_URL') ? APP_URL : Config::get('APP_URL', 'http://localhost/factoryflow')), '/');

$customersStmt = $pdo->query("SELECT * FROM customers ORDER BY name ASC");
$customers = $customersStmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../../includes/layout_header.php';
?>

<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1">Customers Directory</h4>
        <p class="text-muted small mb-0">Manage client accounts, contact details, credit limits, and billing history.</p>
    </div>
    <button type="button" class="btn btn-primary" onclick="App.toast('info', 'Customer profile creation will be configured in Step 5.')">
        <i class="fa-solid fa-plus me-1"></i> Add Customer
    </button>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
        <span class="fw-semibold">All Registered Customers (<?= count($customers) ?>)</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Customer Name</th>
                        <th>Contact Person</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th class="text-end">Credit Limit</th>
                        <th class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($customers)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-users d-block mb-2" style="font-size: 2rem;"></i>
                                No customers registered yet.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($customers as $c): ?>
                            <tr>
                                <td class="fw-semibold text-dark"><?= e($c['name']) ?></td>
                                <td><?= e($c['contact_person'] ?? '—') ?></td>
                                <td><?= e($c['email'] ?? '—') ?></td>
                                <td><?= e($c['phone'] ?? '—') ?></td>
                                <td class="text-end fw-semibold">$<?= number_format((float)$c['credit_limit'], 2) ?></td>
                                <td class="text-center">
                                    <span class="badge <?= (int)$c['is_active'] === 1 ? 'bg-success-subtle text-success border border-success' : 'bg-secondary-subtle text-secondary border' ?>">
                                        <?= (int)$c['is_active'] === 1 ? 'Active' : 'Inactive' ?>
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
