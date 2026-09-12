<?php
declare(strict_types=1);

$pageTitle = 'Sales Orders';

require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/rbac.php';

require_role([ROLE_ADMIN, ROLE_MANAGER]);

$pdo = Database::getConnection();
$appUrl = rtrim((string)(defined('APP_URL') ? APP_URL : Config::get('APP_URL', 'http://localhost/factoryflow')), '/');

$salesStmt = $pdo->query("
    SELECT so.*, c.name as customer_name
    FROM sales_orders so
    JOIN customers c ON c.id = so.customer_id
    ORDER BY so.id DESC
");
$salesOrders = $salesStmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../../includes/layout_header.php';
?>

<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1">Sales Orders</h4>
        <p class="text-muted small mb-0">Record customer purchase contracts, dispatch dates, and commercial terms.</p>
    </div>
    <button type="button" class="btn btn-primary" onclick="App.toast('info', 'Sales order creation will be enabled in Step 5.')">
        <i class="fa-solid fa-plus me-1"></i> New Sales Order
    </button>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
        <span class="fw-semibold">All Sales Orders (<?= count($salesOrders) ?>)</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>SO #</th>
                        <th>Customer</th>
                        <th class="text-end">Total Amount</th>
                        <th class="text-center">Status</th>
                        <th>Order Date</th>
                        <th>Delivery Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($salesOrders)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-file-invoice-dollar d-block mb-2" style="font-size: 2rem;"></i>
                                No sales orders recorded yet.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($salesOrders as $so): ?>
                            <tr>
                                <td class="fw-semibold text-primary"><?= e($so['so_no']) ?></td>
                                <td class="fw-medium text-dark"><?= e($so['customer_name']) ?></td>
                                <td class="text-end fw-semibold">$<?= number_format((float)$so['total_amount'], 2) ?></td>
                                <td class="text-center">
                                    <span class="badge badge-<?= e($so['status']) ?>">
                                        <?= e(ucfirst($so['status'])) ?>
                                    </span>
                                </td>
                                <td class="text-muted small"><?= date('M d, Y', strtotime($so['order_date'])) ?></td>
                                <td class="text-muted small"><?= $so['delivery_date'] ? date('M d, Y', strtotime($so['delivery_date'])) : '&mdash;' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/layout_footer.php'; ?>
