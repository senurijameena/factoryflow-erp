<?php
declare(strict_types=1);

$pageTitle = 'Purchase Orders';

require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/rbac.php';

require_role([ROLE_ADMIN, ROLE_MANAGER]);

$pdo = Database::getConnection();
$appUrl = rtrim((string)(defined('APP_URL') ? APP_URL : Config::get('APP_URL', 'http://localhost/factoryflow')), '/');

$poStmt = $pdo->query("
    SELECT po.*, s.name as supplier_name
    FROM purchase_orders po
    JOIN suppliers s ON s.id = po.supplier_id
    ORDER BY po.id DESC
");
$purchaseOrders = $poStmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../../includes/layout_header.php';
?>

<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1">Purchase Orders</h4>
        <p class="text-muted small mb-0">Procure raw materials from certified industrial vendors and track fulfillment.</p>
    </div>
    <button type="button" class="btn btn-primary" onclick="App.toast('info', 'Purchase requisition will be configured in Step 4.')">
        <i class="fa-solid fa-plus me-1"></i> New Purchase Order
    </button>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
        <span class="fw-semibold">All Purchase Orders (<?= count($purchaseOrders) ?>)</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>PO #</th>
                        <th>Supplier</th>
                        <th class="text-end">Total Amount</th>
                        <th class="text-center">Status</th>
                        <th>Order Date</th>
                        <th>Expected Delivery</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($purchaseOrders)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-cart-shopping d-block mb-2" style="font-size: 2rem;"></i>
                                No purchase orders raised yet.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($purchaseOrders as $po): ?>
                            <tr>
                                <td class="fw-semibold text-primary"><?= e($po['po_no']) ?></td>
                                <td class="fw-medium text-dark"><?= e($po['supplier_name']) ?></td>
                                <td class="text-end fw-semibold">$<?= number_format((float)$po['total_amount'], 2) ?></td>
                                <td class="text-center">
                                    <span class="badge badge-<?= e($po['status']) ?>">
                                        <?= e(ucfirst($po['status'])) ?>
                                    </span>
                                </td>
                                <td class="text-muted small"><?= date('M d, Y', strtotime($po['order_date'])) ?></td>
                                <td class="text-muted small"><?= $po['expected_delivery_date'] ? date('M d, Y', strtotime($po['expected_delivery_date'])) : '&mdash;' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/layout_footer.php'; ?>
