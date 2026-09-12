<?php
declare(strict_types=1);

$pageTitle = 'Executive Dashboard';

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/rbac.php';

require_auth();

$pdo = Database::getConnection();
$appUrl = rtrim((string)Config::get('APP_URL', 'http://localhost/factoryflow'), '/');

$totalProducts = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE is_active = 1")->fetchColumn();
$lowMaterials = (int)$pdo->query("SELECT COUNT(*) FROM materials WHERE is_active = 1 AND current_stock <= reorder_level")->fetchColumn();
$lowProducts = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE is_active = 1 AND current_stock <= reorder_level")->fetchColumn();
$activeOrders = (int)$pdo->query("SELECT COUNT(*) FROM production_orders WHERE status IN ('approved', 'in_progress')")->fetchColumn();
$unpaidInvoices = (int)$pdo->query("SELECT COUNT(*) FROM invoices WHERE status IN ('unpaid', 'overdue')")->fetchColumn();

$monthlyOutputStmt = $pdo->query("
    SELECT COALESCE(SUM(quantity_produced), 0) 
    FROM production_orders 
    WHERE status = 'completed' 
      AND completed_at IS NOT NULL 
      AND MONTH(completed_at) = MONTH(CURRENT_DATE()) 
      AND YEAR(completed_at) = YEAR(CURRENT_DATE())
");
$monthlyOutput = (float)$monthlyOutputStmt->fetchColumn();

$prodDays = [];
$prodValues = [];
for ($i = 29; $i >= 0; $i--) {
    $dateKey = date('Y-m-d', strtotime("-{$i} days"));
    $prodDays[$dateKey] = date('M d', strtotime($dateKey));
    $prodValues[$dateKey] = 0.0;
}

$historyStmt = $pdo->query("
    SELECT DATE(completed_at) as p_date, COALESCE(SUM(quantity_produced), 0) as total_units
    FROM production_orders
    WHERE status = 'completed' 
      AND completed_at >= DATE_SUB(CURRENT_DATE(), INTERVAL 30 DAY)
    GROUP BY DATE(completed_at)
");
while ($row = $historyStmt->fetch(PDO::FETCH_ASSOC)) {
    if (isset($prodValues[$row['p_date']])) {
        $prodValues[$row['p_date']] = (float)$row['total_units'];
    }
}

$topProductsStmt = $pdo->query("
    SELECT p.name, COALESCE(SUM(po.quantity_produced), 0) as total_qty
    FROM products p
    LEFT JOIN production_orders po ON p.id = po.product_id AND po.status = 'completed'
    WHERE p.is_active = 1
    GROUP BY p.id, p.name
    ORDER BY total_qty DESC, p.id ASC
    LIMIT 5
");
$topProducts = $topProductsStmt->fetchAll(PDO::FETCH_ASSOC);
$topProductLabels = [];
$topProductData = [];

if (empty($topProducts)) {
    $topProductLabels = ['No Production Data'];
    $topProductData = [1];
} else {
    foreach ($topProducts as $tp) {
        $topProductLabels[] = $tp['name'];
        $topProductData[] = (float)$tp['total_qty'];
    }
}

$recentProdOrdersStmt = $pdo->query("
    SELECT po.id, po.order_no, po.quantity_planned, po.quantity_produced, po.status, po.start_date, po.due_date,
           p.name as product_name, p.sku as product_sku
    FROM production_orders po
    JOIN products p ON p.id = po.product_id
    ORDER BY po.id DESC
    LIMIT 5
");
$recentProdOrders = $recentProdOrdersStmt->fetchAll(PDO::FETCH_ASSOC);

$recentSalesOrdersStmt = $pdo->query("
    SELECT so.id, so.so_no, so.order_date, so.status, so.total_amount,
           c.name as customer_name
    FROM sales_orders so
    JOIN customers c ON c.id = so.customer_id
    ORDER BY so.id DESC
    LIMIT 5
");
$recentSalesOrders = $recentSalesOrdersStmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../includes/layout_header.php';
?>

<div class="row g-3 mb-4">
    <div class="col-12 col-sm-6 col-xl-2">
        <div class="kpi-card">
            <div class="kpi-icon bg-primary bg-opacity-10 text-primary">
                <i class="fa-solid fa-box-open"></i>
            </div>
            <div>
                <div class="kpi-value"><?= number_format($totalProducts) ?></div>
                <div class="kpi-label">Active Products</div>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-2">
        <div class="kpi-card">
            <div class="kpi-icon bg-danger bg-opacity-10 text-danger">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>
            <div>
                <div class="kpi-value"><?= number_format($lowMaterials) ?></div>
                <div class="kpi-label">Low Materials</div>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-2">
        <div class="kpi-card">
            <div class="kpi-icon bg-warning bg-opacity-10 text-warning">
                <i class="fa-solid fa-cubes"></i>
            </div>
            <div>
                <div class="kpi-value"><?= number_format($lowProducts) ?></div>
                <div class="kpi-label">Low Products</div>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-2">
        <div class="kpi-card">
            <div class="kpi-icon bg-info bg-opacity-10 text-info">
                <i class="fa-solid fa-gears"></i>
            </div>
            <div>
                <div class="kpi-value"><?= number_format($activeOrders) ?></div>
                <div class="kpi-label">Active Orders</div>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-2">
        <div class="kpi-card">
            <div class="kpi-icon bg-danger bg-opacity-10 text-danger">
                <i class="fa-solid fa-file-invoice-dollar"></i>
            </div>
            <div>
                <div class="kpi-value"><?= number_format($unpaidInvoices) ?></div>
                <div class="kpi-label">Unpaid Invoices</div>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-2">
        <div class="kpi-card">
            <div class="kpi-icon bg-success bg-opacity-10 text-success">
                <i class="fa-solid fa-chart-column"></i>
            </div>
            <div>
                <div class="kpi-value"><?= number_format($monthlyOutput) ?></div>
                <div class="kpi-label">Monthly Units</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-12 col-lg-8">
        <div class="card h-100 shadow-sm">
            <div class="card-header d-flex align-items-center justify-content-between">
                <div class="fw-semibold text-dark">
                    <i class="fa-solid fa-chart-area text-primary me-2"></i> Production Output (Past 30 Days)
                </div>
                <span class="badge bg-light text-secondary border">Units Completed</span>
            </div>
            <div class="card-body">
                <div style="height: 280px; position: relative;">
                    <canvas id="productionOutputChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-4">
        <div class="card h-100 shadow-sm">
            <div class="card-header d-flex align-items-center justify-content-between">
                <div class="fw-semibold text-dark">
                    <i class="fa-solid fa-chart-pie text-primary me-2"></i> Top 5 Products
                </div>
                <span class="badge bg-light text-secondary border">Output Distribution</span>
            </div>
            <div class="card-body d-flex align-items-center justify-content-center">
                <div style="height: 260px; width: 100%; position: relative;">
                    <canvas id="topProductsChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-12 col-xl-6">
        <div class="card shadow-sm h-100">
            <div class="card-header d-flex align-items-center justify-content-between">
                <div class="fw-semibold text-dark">
                    <i class="fa-solid fa-industry text-primary me-2"></i> Recent Production Orders
                </div>
                <?php if (has_role([ROLE_ADMIN, ROLE_MANAGER])): ?>
                <a href="<?= e($appUrl) ?>/pages/production/orders.php" class="btn btn-sm btn-outline-primary">
                    View All
                </a>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Order #</th>
                                <th>Product</th>
                                <th class="text-center">Target Qty</th>
                                <th class="text-center">Status</th>
                                <th>Due Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentProdOrders)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">
                                        <i class="fa-regular fa-folder-open d-block mb-2" style="font-size: 1.5rem;"></i>
                                        No production orders recorded yet.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recentProdOrders as $order): ?>
                                    <tr>
                                        <td class="fw-semibold text-primary">
                                            <?= e($order['order_no']) ?>
                                        </td>
                                        <td>
                                            <div class="fw-medium text-dark"><?= e($order['product_name']) ?></div>
                                            <small class="text-muted"><?= e($order['product_sku']) ?></small>
                                        </td>
                                        <td class="text-center fw-semibold">
                                            <?= number_format((float)$order['quantity_planned'], 0) ?>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge badge-<?= e($order['status']) ?>">
                                                <?= e(ucfirst(str_replace('_', ' ', $order['status']))) ?>
                                            </span>
                                        </td>
                                        <td class="text-muted small">
                                            <?= $order['due_date'] ? date('M d, Y', strtotime($order['due_date'])) : '&mdash;' ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-6">
        <div class="card shadow-sm h-100">
            <div class="card-header d-flex align-items-center justify-content-between">
                <div class="fw-semibold text-dark">
                    <i class="fa-solid fa-cart-flatbed text-primary me-2"></i> Recent Sales Orders
                </div>
                <?php if (has_role([ROLE_ADMIN, ROLE_MANAGER])): ?>
                <a href="<?= e($appUrl) ?>/pages/sales/orders.php" class="btn btn-sm btn-outline-primary">
                    View All
                </a>
                <?php endif; ?>
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
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentSalesOrders)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">
                                        <i class="fa-regular fa-folder-open d-block mb-2" style="font-size: 1.5rem;"></i>
                                        No sales orders placed yet.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recentSalesOrders as $so): ?>
                                    <tr>
                                        <td class="fw-semibold text-primary">
                                            <?= e($so['so_no']) ?>
                                        </td>
                                        <td>
                                            <span class="fw-medium text-dark"><?= e($so['customer_name']) ?></span>
                                        </td>
                                        <td class="text-end fw-semibold">
                                            $<?= number_format((float)$so['total_amount'], 2) ?>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge badge-<?= e($so['status']) ?>">
                                                <?= e(ucfirst($so['status'])) ?>
                                            </span>
                                        </td>
                                        <td class="text-muted small">
                                            <?= date('M d, Y', strtotime($so['order_date'])) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const prodDays = <?= json_encode(array_values($prodDays)) ?>;
    const prodValues = <?= json_encode(array_values($prodValues)) ?>;

    const ctxProd = document.getElementById('productionOutputChart');
    if (ctxProd) {
        new Chart(ctxProd, {
            type: 'line',
            data: {
                labels: prodDays,
                datasets: [{
                    label: 'Units Produced',
                    data: prodValues,
                    borderColor: '#2563eb',
                    backgroundColor: 'rgba(37, 99, 235, 0.08)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.35,
                    pointRadius: 2,
                    pointHoverRadius: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: '#f1f5f9' },
                        ticks: { precision: 0 }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { maxTicksLimit: 10 }
                    }
                }
            }
        });
    }

    const ctxTop = document.getElementById('topProductsChart');
    if (ctxTop) {
        const topLabels = <?= json_encode($topProductLabels) ?>;
        const topData = <?= json_encode($topProductData) ?>;

        new Chart(ctxTop, {
            type: 'doughnut',
            data: {
                labels: topLabels,
                datasets: [{
                    data: topData,
                    backgroundColor: [
                        '#2563eb',
                        '#0ea5e9',
                        '#10b981',
                        '#f59e0b',
                        '#8b5cf6'
                    ],
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { boxWidth: 12, font: { size: 11 } }
                    }
                },
                cutout: '68%'
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/layout_footer.php'; ?>
