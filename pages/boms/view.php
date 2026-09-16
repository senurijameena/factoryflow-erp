<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/rbac.php';

require_role([ROLE_ADMIN, ROLE_MANAGER]);

$pdo = Database::getConnection();
$appUrl = rtrim((string)(defined('APP_URL') ? APP_URL : Config::get('APP_URL', 'http://localhost/factoryflow')), '/');

$bomId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($bomId <= 0) {
    header('Location: ' . $appUrl . '/pages/boms/index.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT b.*,
           p.name AS product_name,
           p.sku AS product_sku,
           p.sale_price AS product_sale_price,
           p.cost_price AS product_cost_price,
           u.name AS product_unit_name,
           u.symbol AS product_unit,
           usr.name AS creator_name,
           (SELECT COUNT(*) FROM production_orders po WHERE po.bom_id = b.id) AS orders_count
    FROM boms b
    JOIN products p ON p.id = b.product_id
    JOIN units u ON u.id = p.unit_id
    JOIN users usr ON usr.id = b.created_by
    WHERE b.id = :id
    LIMIT 1
");
$stmt->execute([':id' => $bomId]);
$bom = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$bom) {
    set_flash('danger', 'Bill of Materials not found.');
    header('Location: ' . $appUrl . '/pages/boms/index.php');
    exit;
}

$itemsStmt = $pdo->prepare("
    SELECT bi.*,
           m.name AS material_name,
           m.code AS material_code,
           m.current_stock AS material_stock,
           u.symbol AS unit_symbol,
           u.name AS unit_name
    FROM bom_items bi
    JOIN materials m ON m.id = bi.material_id
    JOIN units u ON u.id = m.unit_id
    WHERE bi.bom_id = :bom_id
    ORDER BY bi.id ASC
");
$itemsStmt->execute([':bom_id' => $bomId]);
$items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

$yieldQty = max(0.01, (float)$bom['yield_quantity']);
$totalCost = (float)$bom['total_cost'];
$costPerUnit = round($totalCost / $yieldQty, 2);
$salePrice = (float)$bom['product_sale_price'];
$margin = ($salePrice > 0) ? (($salePrice - $costPerUnit) / $salePrice) * 100 : 0.00;

$pageTitle = 'BOM ' . $bom['bom_code'];
require_once __DIR__ . '/../../includes/layout_header.php';
?>

<style>
/* Responsive BOM table styling for screen */
.table-bom {
    width: 100% !important;
    margin-bottom: 0;
}
.table-bom th, .table-bom td {
    padding: 0.6rem 0.65rem;
    font-size: 0.85rem;
    vertical-align: middle;
}
.table-bom th {
    letter-spacing: 0.02em;
}

/* =========================================================
   PRINT & PDF EXPORT OPTIMIZATIONS
   ========================================================= */
@media print {
    @page {
        size: auto;
        margin: 10mm 12mm;
    }

    /* Force background colors and prevent scrollbars from rendering */
    *, *::before, *::after {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
        scrollbar-width: none !important;
        -ms-overflow-style: none !important;
    }

    ::-webkit-scrollbar {
        display: none !important;
        width: 0 !important;
        height: 0 !important;
    }

    html, body {
        background-color: #ffffff !important;
        color: #000000 !important;
        font-size: 10pt !important;
        width: 100% !important;
        height: auto !important;
        margin: 0 !important;
        padding: 0 !important;
        overflow: visible !important;
    }

    /* Hide non-printable navigation, sidebar, topbar, and action buttons */
    .ff-sidebar, 
    .ff-sidebar-backdrop, 
    .ff-topbar, 
    .ff-footer, 
    .btn-print-hide, 
    .breadcrumb-wrapper,
    nav {
        display: none !important;
    }

    /* Reset app layout margins so sidebar width doesn't offset print page */
    .ff-app-wrapper,
    .ff-main, 
    .ff-content {
        margin: 0 !important;
        padding: 0 !important;
        width: 100% !important;
        max-width: 100% !important;
        min-height: auto !important;
        display: block !important;
    }

    /* Clean printable card styles */
    .card {
        border: 1px solid #dee2e6 !important;
        box-shadow: none !important;
        break-inside: avoid;
        page-break-inside: avoid;
        margin-bottom: 1.25rem !important;
        width: 100% !important;
    }
    .card-header {
        background-color: #f8f9fa !important;
        border-bottom: 1px solid #dee2e6 !important;
        padding: 0.6rem 1rem !important;
    }
    .card-body {
        padding: 1rem !important;
    }

    /* CRITICAL FIX: Eliminate horizontal scrolling container in print/PDF */
    .table-responsive {
        overflow: visible !important;
        overflow-x: visible !important;
        display: block !important;
        width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
    }

    /* Compact, perfectly proportioned table layout for print */
    .table-bom,
    .table {
        width: 100% !important;
        max-width: 100% !important;
        table-layout: auto !important;
        border-collapse: collapse !important;
        margin-bottom: 0 !important;
    }

    .table th, .table td {
        padding: 4px 6px !important;
        font-size: 8.5pt !important;
        line-height: 1.25 !important;
        vertical-align: middle !important;
        border-color: #dee2e6 !important;
    }

    .table th {
        font-size: 7.5pt !important;
        background-color: #f8f9fa !important;
        color: #495057 !important;
        text-transform: uppercase !important;
        white-space: nowrap !important;
    }

    .table td .small, .table td .text-muted {
        font-size: 7.5pt !important;
    }

    .table tfoot td {
        font-size: 8.5pt !important;
        font-weight: bold !important;
        background-color: #f8f9fa !important;
    }

    .badge {
        border: 1px solid #ced4da !important;
        color: #212529 !important;
        background-color: transparent !important;
        font-weight: 600 !important;
        font-size: 7pt !important;
        padding: 2px 4px !important;
    }
}
</style>

<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4 btn-print-hide">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1 breadcrumb-wrapper">
            <a href="<?= e($appUrl) ?>/pages/boms/index.php" class="text-secondary text-decoration-none">
                <i class="fa-solid fa-arrow-left me-1"></i> Bills of Materials
            </a>
            <span class="text-muted">/</span>
            <span class="text-dark fw-semibold"><?= e($bom['bom_code']) ?></span>
        </div>
        <h4 class="fw-bold mb-0">Engineering Specification & Cost Sheet</h4>
    </div>
    <div class="d-flex align-items-center gap-2">
        <button type="button" class="btn btn-outline-secondary" onclick="window.print()">
            <i class="fa-solid fa-print me-1"></i> Print / Export PDF
        </button>
        <a href="<?= e($appUrl) ?>/pages/boms/form.php?id=<?= (int)$bom['id'] ?>" class="btn btn-primary">
            <i class="fa-solid fa-pen-to-square me-1"></i> Edit BOM
        </a>
    </div>
</div>

<!-- Main Recipe Header Card -->
<div class="card shadow-sm mb-4">
    <div class="card-body p-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-start gap-3 border-bottom pb-4 mb-4">
            <div>
                <span class="badge bg-primary-subtle text-primary font-monospace px-3 py-2 fs-6 mb-2">
                    <?= e($bom['bom_code']) ?>
                </span>
                <h3 class="fw-bold text-dark mb-1"><?= e($bom['product_name']) ?></h3>
                <div class="text-muted">
                    <span class="me-3">Finished SKU: <strong class="font-monospace text-dark"><?= e($bom['product_sku']) ?></strong></span>
                    <span class="me-3">Recipe Version: <strong>v<?= (int)$bom['version'] ?></strong></span>
                    <span>Status: 
                        <span class="badge <?= $bom['status'] === 'active' ? 'bg-success text-white' : ($bom['status'] === 'draft' ? 'bg-warning text-dark' : 'bg-secondary text-white') ?>">
                            <?= ucfirst($bom['status']) ?>
                        </span>
                    </span>
                </div>
            </div>
            <div class="text-md-end">
                <div class="text-muted small">Standard Cost Per Unit:</div>
                <div class="fs-2 fw-bolder text-primary">$<?= number_format($costPerUnit, 2) ?></div>
                <div class="text-muted small">Based on batch yield of <?= number_format($yieldQty, 2) ?> <?= e($bom['product_unit']) ?></div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-6 col-md-3">
                <div class="text-muted small">Total Batch Cost</div>
                <div class="fw-bold fs-5 text-dark">$<?= number_format($totalCost, 2) ?></div>
            </div>
            <div class="col-6 col-md-3">
                <div class="text-muted small">Selling Price</div>
                <div class="fw-bold fs-5 text-dark">$<?= number_format($salePrice, 2) ?></div>
            </div>
            <div class="col-6 col-md-3">
                <div class="text-muted small">Estimated Gross Margin</div>
                <div class="fw-bold fs-5 <?= $margin >= 20 ? 'text-success' : ($margin > 0 ? 'text-warning' : 'text-danger') ?>">
                    <?= number_format($margin, 1) ?>%
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="text-muted small">Configured By / Date</div>
                <div class="fw-medium text-dark"><?= e($bom['creator_name']) ?></div>
                <div class="text-muted small"><?= date('M d, Y', strtotime($bom['created_at'])) ?></div>
            </div>
        </div>

        <?php if (!empty($bom['notes'])): ?>
            <div class="mt-4 pt-3 border-top">
                <div class="text-secondary small fw-semibold mb-1"><i class="fa-solid fa-clipboard-check me-1 text-primary"></i>Engineering Instructions & Notes:</div>
                <div class="text-dark bg-light p-3 rounded small" style="white-space: pre-wrap;"><?= e($bom['notes']) ?></div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Itemized Component Breakdown Table -->
<div class="card shadow-sm mb-4">
    <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
        <span class="fw-semibold">Itemized Bill of Materials Components (<?= count($items) ?> items)</span>
        <span class="text-muted small">Output Ratio: 1 batch = <?= number_format($yieldQty, 2) ?> <?= e($bom['product_unit']) ?></span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 table-bom">
                <thead class="table-light small text-uppercase text-secondary">
                    <tr>
                        <th style="width: 36px;" class="text-center">#</th>
                        <th>Component Material</th>
                        <th>Code</th>
                        <th class="text-center">Unit</th>
                        <th class="text-end" title="Standard Unit Cost">Unit Cost</th>
                        <th class="text-end" title="Gross Quantity Required">Qty Req.</th>
                        <th class="text-end" title="Scrap Allowance Percentage">Scrap %</th>
                        <th class="text-end" title="Subtotal Line Cost">Subtotal</th>
                        <th class="text-end" title="Cost Share Percentage">% Share</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $netTotal = 0.00;
                    foreach ($items as $idx => $it): 
                        $lineCost = (float)$it['subtotal_cost'];
                        $netTotal += $lineCost;
                        $share = ($totalCost > 0) ? ($lineCost / $totalCost) * 100 : 0.00;
                    ?>
                        <tr>
                            <td class="text-center text-muted small fw-semibold"><?= $idx + 1 ?></td>
                            <td>
                                <div class="fw-semibold text-dark"><?= e($it['material_name']) ?></div>
                                <div class="text-muted small">Stock: <?= number_format((float)$it['material_stock'], 2) ?> <?= e($it['unit_symbol']) ?></div>
                            </td>
                            <td><span class="font-monospace small text-primary"><?= e($it['material_code']) ?></span></td>
                            <td class="text-center"><span class="badge bg-light text-secondary border"><?= e($it['unit_symbol']) ?></span></td>
                            <td class="text-end">$<?= number_format((float)$it['unit_cost'], 2) ?></td>
                            <td class="text-end fw-semibold"><?= number_format((float)$it['quantity_required'], 4) ?></td>
                            <td class="text-end text-muted"><?= number_format((float)$it['wastage_percent'], 2) ?>%</td>
                            <td class="text-end fw-bold text-dark">$<?= number_format($lineCost, 2) ?></td>
                            <td class="text-end text-muted small"><?= number_format($share, 1) ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="table-light fw-bold">
                    <tr>
                        <td colspan="7" class="text-end text-uppercase small text-secondary">Total Bill of Materials Cost:</td>
                        <td class="text-end fs-6 text-dark">$<?= number_format($netTotal, 2) ?></td>
                        <td class="text-end text-secondary small">100.0%</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<div class="text-center text-muted small mt-4 mb-5">
    Document generated by FactoryFlow Manufacturing ERP &bull; Internal Engineering Document
</div>

<?php require_once __DIR__ . '/../../includes/layout_footer.php'; ?>
