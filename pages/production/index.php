<?php
declare(strict_types=1);

$pageTitle = 'Production Orders';

require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/rbac.php';

require_auth();

$pdo = Database::getConnection();
$appUrl = rtrim((string)(defined('APP_URL') ? APP_URL : Config::get('APP_URL', 'http://localhost/factoryflow')), '/');

$ordersStmt = $pdo->query("
    SELECT po.*, p.name as product_name, p.sku as product_sku
    FROM production_orders po
    JOIN products p ON p.id = po.product_id
    ORDER BY po.id DESC
");
$orders = $ordersStmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../../includes/layout_header.php';
?>

<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1">Production Orders</h4>
        <p class="text-muted small mb-0">Manage work scheduling, batch runs, and shop-floor execution.</p>
    </div>
    <?php if (has_role([ROLE_ADMIN, ROLE_MANAGER])): ?>
    <button type="button" class="btn btn-primary" onclick="App.toast('info', 'Production order creation modal will be initialized in Step 3.')">
        <i class="fa-solid fa-plus me-1"></i> New Production Order
    </button>
    <?php endif; ?>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
        <span class="fw-semibold">All Production Orders (<?= count($orders) ?>)</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Product</th>
                        <th class="text-center">Planned Qty</th>
                        <th class="text-center">Produced Qty</th>
                        <th class="text-center">Status</th>
                        <th>Start Date</th>
                        <th>Due Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($orders)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-clipboard-list d-block mb-2" style="font-size: 2rem;"></i>
                                No production orders created yet.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td class="fw-semibold text-primary"><?= e($order['order_no']) ?></td>
                                <td>
                                    <div class="fw-medium text-dark"><?= e($order['product_name']) ?></div>
                                    <small class="text-muted"><?= e($order['product_sku']) ?></small>
                                </td>
                                <td class="text-center fw-semibold"><?= number_format((float)$order['quantity_planned'], 0) ?></td>
                                <td class="text-center"><?= number_format((float)$order['quantity_produced'], 0) ?></td>
                                <td class="text-center">
                                    <span class="badge badge-<?= e($order['status']) ?>">
                                        <?= e(ucfirst(str_replace('_', ' ', $order['status']))) ?>
                                    </span>
                                </td>
                                <td class="text-muted small"><?= $order['start_date'] ? date('M d, Y', strtotime($order['start_date'])) : '&mdash;' ?></td>
                                <td class="text-muted small"><?= $order['due_date'] ? date('M d, Y', strtotime($order['due_date'])) : '&mdash;' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/layout_footer.php'; ?>
