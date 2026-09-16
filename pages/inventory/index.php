<?php
declare(strict_types=1);

$pageTitle = 'Inventory & Warehouse Ledger';

require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/rbac.php';

require_auth();

$pdo = Database::getConnection();
$appUrl = rtrim((string)(defined('APP_URL') ? APP_URL : Config::get('APP_URL', 'http://localhost/factoryflow')), '/');
$canManage = has_role([ROLE_ADMIN, ROLE_MANAGER]);
$userRole = $_SESSION['user_role'] ?? ROLE_WORKER;

require_once __DIR__ . '/../../includes/layout_header.php';
?>

<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1">Inventory & Warehouse Ledger</h4>
        <p class="text-muted small mb-0">Multi-tier warehouse inventory tracking, live stock valuation, audit reconciliation, and transaction logs.</p>
    </div>
    <?php if ($canManage): ?>
    <div class="d-flex align-items-center gap-2">
        <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalStockAdjustment">
            <i class="fa-solid fa-scale-balanced me-1"></i> Stock Reconciliation
        </button>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalLogMovement">
            <i class="fa-solid fa-dolly me-1"></i> Log Movement
        </button>
    </div>
    <?php endif; ?>
</div>

<!-- Top KPI Summary Cards -->
<div class="row g-3 mb-4" id="kpiContainer">
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Total Inventory Value</span>
                    <div class="p-2 rounded bg-primary-subtle text-primary">
                        <i class="fa-solid fa-coins"></i>
                    </div>
                </div>
                <div class="fs-4 fw-bold text-dark mb-1" id="kpiTotalValuation">$0.00</div>
                <div class="d-flex align-items-center justify-content-between text-muted small">
                    <span>Mat: <strong class="text-dark" id="kpiMatValuation">$0.00</strong></span>
                    <span>Prod: <strong class="text-dark" id="kpiPrdValuation">$0.00</strong></span>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Raw Materials</span>
                    <div class="p-2 rounded bg-info-subtle text-info">
                        <i class="fa-solid fa-cubes-stacked"></i>
                    </div>
                </div>
                <div class="fs-4 fw-bold text-dark mb-1" id="kpiMatSkus">0 SKUs</div>
                <div class="text-muted small">
                    Total on-hand: <strong class="text-dark" id="kpiMatUnits">0</strong> units
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Finished Goods</span>
                    <div class="p-2 rounded bg-success-subtle text-success">
                        <i class="fa-solid fa-box-open"></i>
                    </div>
                </div>
                <div class="fs-4 fw-bold text-dark mb-1" id="kpiPrdSkus">0 SKUs</div>
                <div class="text-muted small">
                    Total on-hand: <strong class="text-dark" id="kpiPrdUnits">0</strong> units
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Stock Health Alerts</span>
                    <div class="p-2 rounded bg-warning-subtle text-warning">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                    </div>
                </div>
                <div class="fs-4 fw-bold text-dark mb-1 d-flex align-items-center gap-2">
                    <span id="kpiAlertCount">0</span>
                    <span class="badge bg-danger-subtle text-danger fs-6 border border-danger-subtle" id="kpiOutCount">0 Out</span>
                </div>
                <div class="text-muted small">
                    <span id="kpiLowCount">0</span> Low stock items needing replenishment
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Main Inventory Tabs Container -->
<div class="card shadow-sm border-0">
    <div class="card-header bg-white border-bottom pt-3 pb-0">
        <ul class="nav nav-tabs card-header-tabs" id="inventoryTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active fw-semibold text-dark" id="tabStockBtn" data-bs-toggle="tab" data-bs-target="#tabStockPane" type="button" role="tab" aria-controls="tabStockPane" aria-selected="true">
                    <i class="fa-solid fa-boxes-stacked me-2 text-primary"></i> Current Stock Balances
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-semibold text-secondary" id="tabMovementsBtn" data-bs-toggle="tab" data-bs-target="#tabMovementsPane" type="button" role="tab" aria-controls="tabMovementsPane" aria-selected="false">
                    <i class="fa-solid fa-timeline me-2 text-info"></i> Stock Movement Ledger
                </button>
            </li>
        </ul>
    </div>

    <div class="tab-content" id="inventoryTabsContent">
        <!-- ==========================================
             TAB 1: CURRENT STOCK BALANCES
             ========================================== -->
        <div class="tab-pane fade show active p-4" id="tabStockPane" role="tabpanel" aria-labelledby="tabStockBtn">
            <!-- Filter Toolbar -->
            <div class="row g-2 mb-3 align-items-center">
                <div class="col-12 col-md-4">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white border-end-0 text-muted">
                            <i class="fa-solid fa-magnifying-glass"></i>
                        </span>
                        <input type="text" class="form-control border-start-0" id="filterStockSearch" placeholder="Search by name, code or SKU...">
                    </div>
                </div>
                <div class="col-6 col-md-2">
                    <select class="form-select form-select-sm" id="filterStockItemType">
                        <option value="all">All Item Types</option>
                        <option value="material">Raw Materials</option>
                        <option value="product">Finished Products</option>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <select class="form-select form-select-sm" id="filterStockCategory">
                        <option value="">All Categories</option>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <select class="form-select form-select-sm" id="filterStockStatus">
                        <option value="all">All Stock Status</option>
                        <option value="healthy">Healthy Stock</option>
                        <option value="low">Low Stock Warning</option>
                        <option value="out">Out of Stock</option>
                    </select>
                </div>
                <div class="col-6 col-md-2 d-flex justify-content-end gap-1">
                    <button type="button" class="btn btn-sm btn-outline-secondary w-100" id="btnResetStockFilter" title="Reset Filters">
                        <i class="fa-solid fa-rotate-left me-1"></i> Reset
                    </button>
                </div>
            </div>

            <!-- Stock Table -->
            <div class="table-responsive rounded border mb-3">
                <table class="table table-hover align-middle mb-0" id="stockTable">
                    <thead class="table-light small text-uppercase text-secondary">
                        <tr>
                            <th style="width: 130px;">Item Code</th>
                            <th>Description</th>
                            <th style="width: 110px;">Type</th>
                            <th style="width: 100px;" class="text-end">Unit Cost</th>
                            <th style="width: 120px;" class="text-end">Current Stock</th>
                            <th style="width: 110px;" class="text-end">Reorder Level</th>
                            <th style="width: 120px;" class="text-end">Total Value</th>
                            <th style="width: 120px;" class="text-center">Status</th>
                            <th style="width: 130px;" class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="stockTableBody">
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted">
                                <div class="spinner-border spinner-border-sm me-2 text-primary" role="status"></div> Loading stock inventory...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Toolbar -->
            <div class="d-flex flex-column flex-sm-row align-items-center justify-content-between gap-2">
                <div class="text-muted small" id="stockPaginationInfo">
                    Showing 0 of 0 items
                </div>
                <div class="btn-group btn-group-sm" role="group">
                    <button type="button" class="btn btn-outline-secondary" id="btnStockPrev" disabled>
                        <i class="fa-solid fa-chevron-left me-1"></i> Prev
                    </button>
                    <button type="button" class="btn btn-outline-secondary" id="btnStockNext" disabled>
                        Next <i class="fa-solid fa-chevron-right ms-1"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- ==========================================
             TAB 2: STOCK MOVEMENT LEDGER
             ========================================== -->
        <div class="tab-pane fade p-4" id="tabMovementsPane" role="tabpanel" aria-labelledby="tabMovementsBtn">
            <!-- Filter Toolbar -->
            <div class="row g-2 mb-3 align-items-center">
                <div class="col-12 col-md-3">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white border-end-0 text-muted">
                            <i class="fa-solid fa-magnifying-glass"></i>
                        </span>
                        <input type="text" class="form-control border-start-0" id="filterMoveSearch" placeholder="Search item, batch, reference...">
                    </div>
                </div>
                <div class="col-6 col-md-2">
                    <select class="form-select form-select-sm" id="filterMoveType">
                        <option value="all">All Movement Types</option>
                        <option value="in">Stock In (Receipt)</option>
                        <option value="out">Stock Out (Dispatch)</option>
                        <option value="adjustment">Reconciliation / Audit</option>
                        <option value="scrap">Scrap / Write-off</option>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <select class="form-select form-select-sm" id="filterMoveItemType">
                        <option value="all">All Item Categories</option>
                        <option value="material">Raw Materials</option>
                        <option value="product">Finished Products</option>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <input type="date" class="form-control form-control-sm" id="filterMoveStartDate" title="Start Date">
                </div>
                <div class="col-6 col-md-2">
                    <input type="date" class="form-control form-control-sm" id="filterMoveEndDate" title="End Date">
                </div>
                <div class="col-12 col-md-1 d-flex justify-content-end">
                    <button type="button" class="btn btn-sm btn-outline-secondary w-100" id="btnResetMoveFilter" title="Reset Filters">
                        <i class="fa-solid fa-rotate-left"></i>
                    </button>
                </div>
            </div>

            <!-- Movements Table -->
            <div class="table-responsive rounded border mb-3">
                <table class="table table-hover align-middle mb-0" id="movementsTable">
                    <thead class="table-light small text-uppercase text-secondary">
                        <tr>
                            <th style="width: 140px;">Date & Time</th>
                            <th>Item Description</th>
                            <th style="width: 110px;">Type</th>
                            <th style="width: 110px;" class="text-end">Quantity</th>
                            <th style="width: 110px;" class="text-end">Value</th>
                            <th style="width: 110px;">Batch #</th>
                            <th style="width: 130px;">Reference</th>
                            <th>Reason / Notes</th>
                            <th style="width: 120px;">Logged By</th>
                        </tr>
                    </thead>
                    <tbody id="movementsTableBody">
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted">
                                <div class="spinner-border spinner-border-sm me-2 text-primary" role="status"></div> Loading transaction history...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Toolbar -->
            <div class="d-flex flex-column flex-sm-row align-items-center justify-content-between gap-2">
                <div class="text-muted small" id="movePaginationInfo">
                    Showing 0 of 0 movements
                </div>
                <div class="btn-group btn-group-sm" role="group">
                    <button type="button" class="btn btn-outline-secondary" id="btnMovePrev" disabled>
                        <i class="fa-solid fa-chevron-left me-1"></i> Prev
                    </button>
                    <button type="button" class="btn btn-outline-secondary" id="btnMoveNext" disabled>
                        Next <i class="fa-solid fa-chevron-right ms-1"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/modals.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const appUrl = '<?= e($appUrl) ?>';
    const canManage = <?= $canManage ? 'true' : 'false' ?>;

    let stockPage = 1;
    let stockTotalPages = 1;
    let movePage = 1;
    let moveTotalPages = 1;

    let materialsLookup = [];
    let productsLookup = [];

    // Elements - Stock
    const stockBody = document.getElementById('stockTableBody');
    const stockInfo = document.getElementById('stockPaginationInfo');
    const btnStockPrev = document.getElementById('btnStockPrev');
    const btnStockNext = document.getElementById('btnStockNext');
    const filterStockSearch = document.getElementById('filterStockSearch');
    const filterStockItemType = document.getElementById('filterStockItemType');
    const filterStockCategory = document.getElementById('filterStockCategory');
    const filterStockStatus = document.getElementById('filterStockStatus');
    const btnResetStock = document.getElementById('btnResetStockFilter');

    // Elements - Movements
    const moveBody = document.getElementById('movementsTableBody');
    const moveInfo = document.getElementById('movePaginationInfo');
    const btnMovePrev = document.getElementById('btnMovePrev');
    const btnMoveNext = document.getElementById('btnMoveNext');
    const filterMoveSearch = document.getElementById('filterMoveSearch');
    const filterMoveType = document.getElementById('filterMoveType');
    const filterMoveItemType = document.getElementById('filterMoveItemType');
    const filterMoveStartDate = document.getElementById('filterMoveStartDate');
    const filterMoveEndDate = document.getElementById('filterMoveEndDate');
    const btnResetMove = document.getElementById('btnResetMoveFilter');

    // Debounce helper
    function debounce(fn, ms = 300) {
        let timer;
        return (...args) => {
            clearTimeout(timer);
            timer = setTimeout(() => fn(...args), ms);
        };
    }

    // Load KPI Overview
    async function loadOverview() {
        try {
            const res = await App.request(`${appUrl}/api/inventory.php?action=overview`);
            if (res && res.success && res.data) {
                const d = res.data;
                document.getElementById('kpiTotalValuation').textContent = `$${Number(d.total_valuation || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
                document.getElementById('kpiMatValuation').textContent = `$${Number(d.materials_valuation || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
                document.getElementById('kpiPrdValuation').textContent = `$${Number(d.products_valuation || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
                document.getElementById('kpiMatSkus').textContent = `${d.total_material_skus || 0} SKUs`;
                document.getElementById('kpiMatUnits').textContent = Number(d.materials_units || 0).toLocaleString('en-US', {maximumFractionDigits: 2});
                document.getElementById('kpiPrdSkus').textContent = `${d.total_product_skus || 0} SKUs`;
                document.getElementById('kpiPrdUnits').textContent = Number(d.products_units || 0).toLocaleString('en-US', {maximumFractionDigits: 2});
                document.getElementById('kpiAlertCount').textContent = `${(d.low_stock_count || 0) + (d.out_of_stock_count || 0)} Alerts`;
                document.getElementById('kpiOutCount').textContent = `${d.out_of_stock_count || 0} Out`;
                document.getElementById('kpiLowCount').textContent = `${d.low_stock_count || 0}`;
            }
        } catch (err) {
            console.error('Failed to load overview KPIs:', err);
        }
    }

    // Load Lookup data for dropdowns
    async function loadLookupData() {
        try {
            const res = await App.request(`${appUrl}/api/inventory.php?action=items_lookup`);
            if (res && res.success && res.data) {
                materialsLookup = res.data.materials || [];
                productsLookup = res.data.products || [];

                // Populate category filter
                const categories = res.data.categories || [];
                filterStockCategory.innerHTML = '<option value="">All Categories</option>';
                categories.forEach(c => {
                    const opt = document.createElement('option');
                    opt.value = c.id;
                    opt.textContent = `${c.name} (${c.type === 'material' ? 'Mat' : 'Prod'})`;
                    filterStockCategory.appendChild(opt);
                });

                // Populate modals dropdowns
                populateLogItemDropdown('material');
                populateAdjItemDropdown('material');
            }
        } catch (err) {
            console.error('Failed to load lookup data:', err);
        }
    }

    // Populate log movement item select
    function populateLogItemDropdown(itemType) {
        const select = document.getElementById('logItemId');
        select.innerHTML = '<option value="" disabled selected>Select an item...</option>';
        const list = itemType === 'material' ? materialsLookup : productsLookup;
        list.forEach(it => {
            const opt = document.createElement('option');
            opt.value = it.id;
            opt.textContent = `${it.item_code} - ${it.item_name} (Stock: ${Number(it.current_stock).toFixed(2)} ${it.unit_symbol})`;
            opt.dataset.stock = it.current_stock;
            opt.dataset.unit = it.unit_symbol;
            select.appendChild(opt);
        });
        updateLogItemBadge();
    }

    // Populate adjustment item select
    function populateAdjItemDropdown(itemType) {
        const select = document.getElementById('adjItemId');
        select.innerHTML = '<option value="" disabled selected>Select an item...</option>';
        const list = itemType === 'material' ? materialsLookup : productsLookup;
        list.forEach(it => {
            const opt = document.createElement('option');
            opt.value = it.id;
            opt.textContent = `${it.item_code} - ${it.item_name} (Stock: ${Number(it.current_stock).toFixed(2)} ${it.unit_symbol})`;
            opt.dataset.stock = it.current_stock;
            opt.dataset.unit = it.unit_symbol;
            select.appendChild(opt);
        });
        updateAdjItemDetails();
    }

    // Update stock badge in Log Movement modal
    function updateLogItemBadge() {
        const select = document.getElementById('logItemId');
        const badge = document.getElementById('logCurrentStockBadge');
        const unitDisplay = document.getElementById('logUnitDisplay');
        const selected = select.options[select.selectedIndex];
        if (selected && selected.value) {
            const stock = selected.dataset.stock || '0.00';
            const unit = selected.dataset.unit || 'units';
            badge.textContent = `Available Stock: ${Number(stock).toFixed(2)} ${unit}`;
            badge.className = Number(stock) <= 0 ? 'badge bg-danger-subtle text-danger border border-danger' : 'badge bg-success-subtle text-success border border-success';
            unitDisplay.textContent = unit;
        } else {
            badge.textContent = 'Select an item';
            badge.className = 'badge bg-light text-secondary border';
            unitDisplay.textContent = 'units';
        }
    }

    // Update item details in Adjustment modal
    function updateAdjItemDetails() {
        const select = document.getElementById('adjItemId');
        const currentDisplay = document.getElementById('adjCurrentStockDisplay');
        const unitDisplays = document.querySelectorAll('.adjUnitDisplay');
        const selected = select.options[select.selectedIndex];
        if (selected && selected.value) {
            const stock = Number(selected.dataset.stock || 0);
            const unit = selected.dataset.unit || 'units';
            currentDisplay.textContent = stock.toFixed(2);
            unitDisplays.forEach(u => u.textContent = unit);
        } else {
            currentDisplay.textContent = '0.00';
            unitDisplays.forEach(u => u.textContent = 'units');
        }
        calculateVariance();
    }

    // Calculate live variance in adjustment modal
    function calculateVariance() {
        const select = document.getElementById('adjItemId');
        const countedInput = document.getElementById('adjCountedStock');
        const badge = document.getElementById('adjVarianceBadge');
        const selected = select.options[select.selectedIndex];

        if (!selected || !selected.value || countedInput.value === '') {
            badge.textContent = '0.00 variance';
            badge.className = 'badge bg-secondary-subtle text-secondary px-3 py-2 fs-6';
            return;
        }

        const current = Number(selected.dataset.stock || 0);
        const counted = Number(countedInput.value || 0);
        const diff = counted - current;
        const unit = selected.dataset.unit || '';

        if (diff > 0.0001) {
            badge.textContent = `+${diff.toFixed(2)} ${unit} (Surplus / Inward)`;
            badge.className = 'badge bg-success text-white px-3 py-2 fs-6';
        } else if (diff < -0.0001) {
            badge.textContent = `${diff.toFixed(2)} ${unit} (Deficit / Write-off)`;
            badge.className = 'badge bg-danger text-white px-3 py-2 fs-6';
        } else {
            badge.textContent = `0.00 ${unit} (Exact Match / Balanced)`;
            badge.className = 'badge bg-secondary-subtle text-dark px-3 py-2 fs-6 border';
        }
    }

    // Load Stock Table
    async function loadStock(page = 1) {
        stockPage = page;
        stockBody.innerHTML = `
            <tr>
                <td colspan="9" class="text-center py-5 text-muted">
                    <div class="spinner-border spinner-border-sm me-2 text-primary" role="status"></div> Loading stock inventory...
                </td>
            </tr>
        `;

        const params = new URLSearchParams({
            action: 'stock_list',
            page: stockPage,
            limit: 15,
            search: filterStockSearch.value.trim(),
            item_type: filterStockItemType.value,
            category_id: filterStockCategory.value,
            stock_status: filterStockStatus.value
        });

        try {
            const res = await App.request(`${appUrl}/api/inventory.php?${params.toString()}`);
            if (!res || !res.success) {
                throw new Error(res.error || 'Failed to load stock list.');
            }

            const items = res.data || [];
            const meta = res.meta || { page: 1, limit: 15, total_items: 0, total_pages: 1 };
            stockTotalPages = meta.total_pages;

            renderStockTable(items);
            updateStockPagination(meta);
        } catch (err) {
            stockBody.innerHTML = `
                <tr>
                    <td colspan="9" class="text-center py-4 text-danger">
                        <i class="fa-solid fa-circle-exclamation me-1"></i> ${err.message}
                    </td>
                </tr>
            `;
        }
    }

    // Render Stock Rows
    function renderStockTable(items) {
        if (!items.length) {
            stockBody.innerHTML = `
                <tr>
                    <td colspan="9" class="text-center py-5 text-muted">
                        <i class="fa-solid fa-boxes-stacked d-block mb-2 fs-3 text-secondary"></i>
                        No inventory stock items found matching your filters.
                    </td>
                </tr>
            `;
            return;
        }

        let html = '';
        items.forEach(it => {
            const currentStock = Number(it.current_stock || 0);
            const reorderLevel = Number(it.reorder_level || 0);
            const unitCost = Number(it.unit_cost || 0);
            const totalVal = Number(it.total_valuation || 0);
            const status = it.stock_status;

            let statusBadge = '';
            if (status === 'out') {
                statusBadge = '<span class="badge bg-danger-subtle text-danger border border-danger">Out of Stock</span>';
            } else if (status === 'low') {
                statusBadge = '<span class="badge bg-warning-subtle text-warning border border-warning">Low Stock</span>';
            } else {
                statusBadge = '<span class="badge bg-success-subtle text-success border border-success">Healthy</span>';
            }

            const typeBadge = it.item_type === 'material'
                ? '<span class="badge bg-info-subtle text-info border border-info-subtle">Material</span>'
                : '<span class="badge bg-primary-subtle text-primary border border-primary-subtle">Product</span>';

            html += `
                <tr>
                    <td>
                        <span class="font-monospace fw-bold text-primary">${escapeHtml(it.item_code)}</span>
                    </td>
                    <td>
                        <div class="fw-semibold text-dark">${escapeHtml(it.item_name)}</div>
                        <div class="text-muted small">${escapeHtml(it.category_name || 'Uncategorized')}</div>
                    </td>
                    <td>${typeBadge}</td>
                    <td class="text-end">$${unitCost.toFixed(2)}</td>
                    <td class="text-end fw-bold ${status === 'out' ? 'text-danger' : (status === 'low' ? 'text-warning' : 'text-dark')}">
                        ${currentStock.toFixed(2)} <span class="text-muted small fw-normal">${escapeHtml(it.unit_symbol)}</span>
                    </td>
                    <td class="text-end text-muted">
                        ${reorderLevel.toFixed(2)} <span class="small">${escapeHtml(it.unit_symbol)}</span>
                    </td>
                    <td class="text-end fw-semibold text-dark">
                        $${totalVal.toFixed(2)}
                    </td>
                    <td class="text-center">${statusBadge}</td>
                    <td class="text-end">
                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn btn-outline-secondary btn-item-history" 
                                    data-id="${it.id}" 
                                    data-type="${it.item_type}" 
                                    data-code="${escapeHtml(it.item_code)}" 
                                    data-name="${escapeHtml(it.item_name)}" 
                                    title="View Ledger History">
                                <i class="fa-solid fa-clock-rotate-left"></i>
                            </button>
                            ${canManage ? `
                            <button type="button" class="btn btn-outline-primary btn-quick-move" 
                                    data-id="${it.id}" 
                                    data-type="${it.item_type}" 
                                    data-code="${escapeHtml(it.item_code)}" 
                                    title="Quick Movement">
                                <i class="fa-solid fa-dolly"></i>
                            </button>
                            <button type="button" class="btn btn-outline-warning text-dark btn-quick-adj" 
                                    data-id="${it.id}" 
                                    data-type="${it.item_type}" 
                                    data-code="${escapeHtml(it.item_code)}" 
                                    title="Stock Reconciliation">
                                <i class="fa-solid fa-scale-balanced"></i>
                            </button>
                            ` : ''}
                        </div>
                    </td>
                </tr>
            `;
        });

        stockBody.innerHTML = html;
        bindStockRowActions();
    }

    function updateStockPagination(meta) {
        const start = meta.total_items > 0 ? (meta.page - 1) * meta.limit + 1 : 0;
        const end = Math.min(meta.page * meta.limit, meta.total_items);
        stockInfo.textContent = `Showing ${start} to ${end} of ${meta.total_items} items`;
        btnStockPrev.disabled = meta.page <= 1;
        btnStockNext.disabled = meta.page >= meta.total_pages;
    }

    // Load Movement Table
    async function loadMovements(page = 1) {
        movePage = page;
        moveBody.innerHTML = `
            <tr>
                <td colspan="9" class="text-center py-5 text-muted">
                    <div class="spinner-border spinner-border-sm me-2 text-primary" role="status"></div> Loading transaction history...
                </td>
            </tr>
        `;

        const params = new URLSearchParams({
            action: 'movements',
            page: movePage,
            limit: 15,
            search: filterMoveSearch.value.trim(),
            movement_type: filterMoveType.value,
            item_type: filterMoveItemType.value,
            start_date: filterMoveStartDate.value,
            end_date: filterMoveEndDate.value
        });

        try {
            const res = await App.request(`${appUrl}/api/inventory.php?${params.toString()}`);
            if (!res || !res.success) {
                throw new Error(res.error || 'Failed to load movements.');
            }

            const items = res.data || [];
            const meta = res.meta || { page: 1, limit: 15, total_items: 0, total_pages: 1 };
            moveTotalPages = meta.total_pages;

            renderMovementsTable(items);
            updateMovementsPagination(meta);
        } catch (err) {
            moveBody.innerHTML = `
                <tr>
                    <td colspan="9" class="text-center py-4 text-danger">
                        <i class="fa-solid fa-circle-exclamation me-1"></i> ${err.message}
                    </td>
                </tr>
            `;
        }
    }

    // Render Movement Rows
    function renderMovementsTable(items) {
        if (!items.length) {
            moveBody.innerHTML = `
                <tr>
                    <td colspan="9" class="text-center py-5 text-muted">
                        <i class="fa-solid fa-timeline d-block mb-2 fs-3 text-secondary"></i>
                        No stock movement records found for the selected criteria.
                    </td>
                </tr>
            `;
            return;
        }

        let html = '';
        items.forEach(m => {
            const qty = Number(m.quantity || 0);
            const unitCost = Number(m.unit_cost || 0);
            const totalVal = Math.abs(qty * unitCost);

            let typeBadge = '';
            let qtyClass = 'text-dark';
            let qtySign = '';

            switch (m.movement_type) {
                case 'in':
                    typeBadge = '<span class="badge bg-success-subtle text-success border border-success"><i class="fa-solid fa-arrow-down-left me-1"></i> IN</span>';
                    qtyClass = 'text-success fw-bold';
                    qtySign = '+';
                    break;
                case 'out':
                    typeBadge = '<span class="badge bg-secondary-subtle text-secondary border"><i class="fa-solid fa-arrow-up-right me-1"></i> OUT</span>';
                    qtyClass = 'text-danger fw-bold';
                    qtySign = '-';
                    break;
                case 'adjustment':
                    typeBadge = '<span class="badge bg-primary-subtle text-primary border border-primary"><i class="fa-solid fa-scale-balanced me-1"></i> ADJ</span>';
                    qtyClass = qty >= 0 ? 'text-success fw-bold' : 'text-danger fw-bold';
                    qtySign = qty > 0 ? '+' : '';
                    break;
                case 'scrap':
                    typeBadge = '<span class="badge bg-danger-subtle text-danger border border-danger"><i class="fa-solid fa-trash-can me-1"></i> SCRAP</span>';
                    qtyClass = 'text-danger fw-bold';
                    qtySign = '-';
                    break;
            }

            const formattedDate = new Date(m.created_at).toLocaleString('en-US', {
                month: 'short',
                day: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });

            html += `
                <tr>
                    <td class="text-muted small">${formattedDate}</td>
                    <td>
                        <div class="fw-semibold text-dark">${escapeHtml(m.item_name || 'Item #' + m.item_id)}</div>
                        <div class="small">
                            <span class="font-monospace text-primary">${escapeHtml(m.item_code || '')}</span>
                            <span class="badge bg-light text-secondary border ms-1">${m.item_type === 'material' ? 'Material' : 'Product'}</span>
                        </div>
                    </td>
                    <td>${typeBadge}</td>
                    <td class="text-end ${qtyClass}">
                        ${qtySign}${Math.abs(qty).toFixed(2)} <span class="small text-muted fw-normal">${escapeHtml(m.unit_symbol || '')}</span>
                    </td>
                    <td class="text-end text-muted small">
                        $${totalVal.toFixed(2)}
                    </td>
                    <td>
                        ${m.batch_no ? `<span class="badge bg-light text-dark border font-monospace">${escapeHtml(m.batch_no)}</span>` : '<span class="text-muted small">—</span>'}
                    </td>
                    <td>
                        ${m.reference_no ? `<span class="fw-medium text-dark small">${escapeHtml(m.reference_no)}</span>` : `<span class="text-muted small">${escapeHtml(m.reference_type || '—')}</span>`}
                    </td>
                    <td>
                        <div class="small text-dark">${escapeHtml(m.reason || '—')}</div>
                        ${m.notes ? `<div class="text-muted small fst-italic">${escapeHtml(m.notes)}</div>` : ''}
                    </td>
                    <td class="small text-dark">
                        <div class="d-flex align-items-center gap-1">
                            <i class="fa-solid fa-user-circle text-muted"></i>
                            <span>${escapeHtml(m.user_name || 'System')}</span>
                        </div>
                    </td>
                </tr>
            `;
        });

        moveBody.innerHTML = html;
    }

    function updateMovementsPagination(meta) {
        const start = meta.total_items > 0 ? (meta.page - 1) * meta.limit + 1 : 0;
        const end = Math.min(meta.page * meta.limit, meta.total_items);
        moveInfo.textContent = `Showing ${start} to ${end} of ${meta.total_items} movements`;
        btnMovePrev.disabled = meta.page <= 1;
        btnMoveNext.disabled = meta.page >= meta.total_pages;
    }

    // Bind Quick Action buttons on table rows
    function bindStockRowActions() {
        // Quick Log Movement
        document.querySelectorAll('.btn-quick-move').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = btn.dataset.id;
                const type = btn.dataset.type;
                const modal = new bootstrap.Modal(document.getElementById('modalLogMovement'));

                // Set radio
                if (type === 'material') {
                    document.getElementById('logTypeMaterial').checked = true;
                } else {
                    document.getElementById('logTypeProduct').checked = true;
                }
                populateLogItemDropdown(type);
                document.getElementById('logItemId').value = id;
                updateLogItemBadge();
                modal.show();
            });
        });

        // Quick Reconciliation
        document.querySelectorAll('.btn-quick-adj').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = btn.dataset.id;
                const type = btn.dataset.type;
                const modal = new bootstrap.Modal(document.getElementById('modalStockAdjustment'));

                if (type === 'material') {
                    document.getElementById('adjTypeMaterial').checked = true;
                } else {
                    document.getElementById('adjTypeProduct').checked = true;
                }
                populateAdjItemDropdown(type);
                document.getElementById('adjItemId').value = id;
                updateAdjItemDetails();
                document.getElementById('adjCountedStock').value = '';
                calculateVariance();
                modal.show();
            });
        });

        // Item History Modal
        document.querySelectorAll('.btn-item-history').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = btn.dataset.id;
                const type = btn.dataset.type;
                const code = btn.dataset.code;
                const name = btn.dataset.name;
                loadItemHistory(id, type, code, name);
            });
        });
    }

    // Load History for specific item
    async function loadItemHistory(itemId, itemType, itemCode, itemName) {
        const modalEl = document.getElementById('modalItemHistory');
        const modal = new bootstrap.Modal(modalEl);
        document.getElementById('modalItemHistoryLabel').textContent = `Stock Ledger: ${itemCode}`;
        document.getElementById('histItemSubtitle').textContent = `${itemName} (${itemType === 'material' ? 'Raw Material' : 'Finished Product'})`;

        const tbody = document.getElementById('histTableBody');
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="text-center py-4 text-muted">
                    <div class="spinner-border spinner-border-sm me-2 text-primary" role="status"></div> Loading ledger...
                </td>
            </tr>
        `;
        modal.show();

        try {
            const res = await App.request(`${appUrl}/api/inventory.php?action=movements&item_type=${encodeURIComponent(itemType)}&item_id=${encodeURIComponent(itemId)}&limit=50`);
            if (!res || !res.success) throw new Error(res.error || 'Failed to load item history.');

            const list = res.data || [];
            if (!list.length) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">
                            No ledger movements recorded for this item yet.
                        </td>
                    </tr>
                `;
                return;
            }

            let html = '';
            list.forEach(m => {
                const qty = Number(m.quantity || 0);
                const sign = m.movement_type === 'in' ? '+' : (m.movement_type === 'out' || m.movement_type === 'scrap' ? '-' : (qty > 0 ? '+' : ''));
                const qtyClass = m.movement_type === 'in' || (m.movement_type === 'adjustment' && qty >= 0) ? 'text-success fw-bold' : 'text-danger fw-bold';

                const dateStr = new Date(m.created_at).toLocaleString('en-US', {
                    month: 'short', day: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit'
                });

                html += `
                    <tr>
                        <td class="text-muted small">${dateStr}</td>
                        <td><span class="badge bg-light text-dark border text-uppercase">${m.movement_type}</span></td>
                        <td class="text-end ${qtyClass}">${sign}${Math.abs(qty).toFixed(2)} <span class="small text-muted fw-normal">${escapeHtml(m.unit_symbol || '')}</span></td>
                        <td class="small font-monospace">${escapeHtml(m.batch_no || '—')}</td>
                        <td class="small">${escapeHtml(m.reference_no || m.reference_type || '—')}</td>
                        <td class="small">${escapeHtml(m.reason || m.notes || '—')}</td>
                        <td class="small text-muted">${escapeHtml(m.user_name || 'System')}</td>
                    </tr>
                `;
            });
            tbody.innerHTML = html;
        } catch (err) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="7" class="text-center py-3 text-danger">
                        ${err.message}
                    </td>
                </tr>
            `;
        }
    }

    // Modal Interaction Bindings
    // 1. Movement Type selector buttons
    document.querySelectorAll('#moveTypeBtnGroup [data-move-type]').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('#moveTypeBtnGroup [data-move-type]').forEach(b => {
                b.classList.remove('active', 'btn-success', 'btn-danger');
                b.classList.add('btn-outline-secondary');
            });
            const type = btn.dataset.moveType;
            document.getElementById('logMovementType').value = type;
            btn.classList.remove('btn-outline-secondary');
            if (type === 'in') {
                btn.classList.add('btn-success', 'active');
            } else if (type === 'scrap') {
                btn.classList.add('btn-danger', 'active');
            } else {
                btn.classList.add('btn-secondary', 'active');
            }
        });
    });

    // 2. Radio changes in Log Movement Modal
    document.querySelectorAll('input[name="item_type"][id^="logType"]').forEach(r => {
        r.addEventListener('change', () => {
            populateLogItemDropdown(r.value);
        });
    });

    // 3. Item Select change in Log Movement Modal
    document.getElementById('logItemId').addEventListener('change', updateLogItemBadge);

    // 4. Radio changes in Adjustment Modal
    document.querySelectorAll('input[name="item_type"][id^="adjType"]').forEach(r => {
        r.addEventListener('change', () => {
            populateAdjItemDropdown(r.value);
        });
    });

    // 5. Item Select change in Adjustment Modal
    document.getElementById('adjItemId').addEventListener('change', updateAdjItemDetails);

    // 6. Counted Stock input change in Adjustment Modal
    document.getElementById('adjCountedStock').addEventListener('input', calculateVariance);

    // Filter Listeners - Stock
    filterStockSearch.addEventListener('input', debounce(() => loadStock(1), 350));
    filterStockItemType.addEventListener('change', () => loadStock(1));
    filterStockCategory.addEventListener('change', () => loadStock(1));
    filterStockStatus.addEventListener('change', () => loadStock(1));
    btnResetStock.addEventListener('click', () => {
        filterStockSearch.value = '';
        filterStockItemType.value = 'all';
        filterStockCategory.value = '';
        filterStockStatus.value = 'all';
        loadStock(1);
    });
    btnStockPrev.addEventListener('click', () => {
        if (stockPage > 1) loadStock(stockPage - 1);
    });
    btnStockNext.addEventListener('click', () => {
        if (stockPage < stockTotalPages) loadStock(stockPage + 1);
    });

    // Filter Listeners - Movements
    filterMoveSearch.addEventListener('input', debounce(() => loadMovements(1), 350));
    filterMoveType.addEventListener('change', () => loadMovements(1));
    filterMoveItemType.addEventListener('change', () => loadMovements(1));
    filterMoveStartDate.addEventListener('change', () => loadMovements(1));
    filterMoveEndDate.addEventListener('change', () => loadMovements(1));
    btnResetMove.addEventListener('click', () => {
        filterMoveSearch.value = '';
        filterMoveType.value = 'all';
        filterMoveItemType.value = 'all';
        filterMoveStartDate.value = '';
        filterMoveEndDate.value = '';
        loadMovements(1);
    });
    btnMovePrev.addEventListener('click', () => {
        if (movePage > 1) loadMovements(movePage - 1);
    });
    btnMoveNext.addEventListener('click', () => {
        if (movePage < moveTotalPages) loadMovements(movePage + 1);
    });

    // Tab switch listener
    document.getElementById('tabMovementsBtn').addEventListener('shown.bs.tab', () => {
        loadMovements(1);
    });

    // Form Submission: Log Movement Modal
    App.bindAjaxForm('formLogMovement', (res) => {
        const modalEl = document.getElementById('modalLogMovement');
        const modal = bootstrap.Modal.getInstance(modalEl);
        if (modal) modal.hide();

        App.toast('success', res.message || 'Movement logged successfully.');
        document.getElementById('formLogMovement').reset();
        document.getElementById('logMovementType').value = 'in';
        document.querySelectorAll('#moveTypeBtnGroup [data-move-type]').forEach(b => {
            b.classList.remove('active', 'btn-success', 'btn-danger');
            b.classList.add('btn-outline-secondary');
        });
        document.querySelector('#moveTypeBtnGroup [data-move-type="in"]').classList.add('btn-success', 'active');
        document.getElementById('logTypeMaterial').checked = true;

        // Reload data
        loadOverview();
        loadLookupData();
        loadStock(stockPage);
        loadMovements(1);
    });

    // Form Submission: Stock Reconciliation Modal
    App.bindAjaxForm('formStockAdjustment', (res) => {
        const modalEl = document.getElementById('modalStockAdjustment');
        const modal = bootstrap.Modal.getInstance(modalEl);
        if (modal) modal.hide();

        App.toast('success', res.message || 'Stock reconciliation completed.');
        document.getElementById('formStockAdjustment').reset();
        document.getElementById('adjTypeMaterial').checked = true;

        // Reload data
        loadOverview();
        loadLookupData();
        loadStock(stockPage);
        loadMovements(1);
    });

    // Helper: Escape HTML strings
    function escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    // Initial Boots
    loadOverview();
    loadLookupData();
    loadStock(1);
});
</script>

<?php require_once __DIR__ . '/../../includes/layout_footer.php'; ?>
