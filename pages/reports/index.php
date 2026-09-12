<?php
declare(strict_types=1);

$pageTitle = 'Reports & Analytics';

require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/rbac.php';

require_role([ROLE_ADMIN, ROLE_MANAGER]);

$pdo = Database::getConnection();
$appUrl = rtrim((string)(defined('APP_URL') ? APP_URL : Config::get('APP_URL', 'http://localhost/factoryflow')), '/');

$totalInventoryVal = (float)$pdo->query("SELECT COALESCE(SUM(current_stock * cost_price), 0) FROM products WHERE is_active = 1")->fetchColumn();
$totalSalesRevenue = (float)$pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM sales_orders WHERE status IN ('confirmed', 'shipped', 'delivered')")->fetchColumn();
$totalProcurementSpend = (float)$pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM purchase_orders WHERE status IN ('received', 'partial')")->fetchColumn();

require_once __DIR__ . '/../../includes/layout_header.php';
?>

<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1">Operational Reports & Analytics</h4>
        <p class="text-muted small mb-0">High-level financial summaries, operational yields, and inventory valuation.</p>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-12 col-md-4">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small fw-semibold text-uppercase mb-2">Total Finished Inventory Value</div>
                <div class="fs-3 fw-bold text-primary mb-1">$<?= number_format($totalInventoryVal, 2) ?></div>
                <div class="small text-muted">Valuation based on product standard unit costs.</div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small fw-semibold text-uppercase mb-2">Total Realized Sales</div>
                <div class="fs-3 fw-bold text-success mb-1">$<?= number_format($totalSalesRevenue, 2) ?></div>
                <div class="small text-muted">Confirmed, shipped, and delivered customer orders.</div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small fw-semibold text-uppercase mb-2">Procurement Spend</div>
                <div class="fs-3 fw-bold text-dark mb-1">$<?= number_format($totalProcurementSpend, 2) ?></div>
                <div class="small text-muted">Total expenditure for received raw material deliveries.</div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white py-3">
        <span class="fw-semibold">Available Intelligence Reports</span>
    </div>
    <div class="card-body">
        <div class="list-group list-group-flush">
            <div class="list-group-item d-flex justify-content-between align-items-center px-0 py-3">
                <div>
                    <h6 class="mb-1 fw-bold text-dark"><i class="fa-solid fa-file-invoice text-primary me-2"></i> Production Yield & Scrap Report</h6>
                    <p class="mb-0 text-muted small">Analyze scrap percentages, planned vs produced variance, and manufacturing cycle runtimes.</p>
                </div>
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="App.toast('info', 'Report generator will be built in Step 6.')">Generate</button>
            </div>
            <div class="list-group-item d-flex justify-content-between align-items-center px-0 py-3">
                <div>
                    <h6 class="mb-1 fw-bold text-dark"><i class="fa-solid fa-warehouse text-primary me-2"></i> Material Consumption Analysis</h6>
                    <p class="mb-0 text-muted small">Detailed audit trail of raw materials consumed across shop floor work orders.</p>
                </div>
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="App.toast('info', 'Report generator will be built in Step 6.')">Generate</button>
            </div>
            <div class="list-group-item d-flex justify-content-between align-items-center px-0 py-3">
                <div>
                    <h6 class="mb-1 fw-bold text-dark"><i class="fa-solid fa-chart-line text-primary me-2"></i> Monthly Sales & Tax Audit Summary</h6>
                    <p class="mb-0 text-muted small">Complete tax ledger and revenue distribution for financial compliance and accounting.</p>
                </div>
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="App.toast('info', 'Report generator will be built in Step 6.')">Generate</button>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/layout_footer.php'; ?>
