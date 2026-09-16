<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/rbac.php';

require_role([ROLE_ADMIN, ROLE_MANAGER]);

$pdo = Database::getConnection();
$appUrl = rtrim((string)(defined('APP_URL') ? APP_URL : Config::get('APP_URL', 'http://localhost/factoryflow')), '/');

$bomId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit = ($bomId > 0);
$pageTitle = $isEdit ? 'Edit Bill of Materials' : 'Create Bill of Materials';

$existingBom = null;
$existingItems = [];

if ($isEdit) {
    $stmt = $pdo->prepare("
        SELECT b.*, p.name as product_name, p.sku as product_sku, u.symbol as product_unit
        FROM boms b
        JOIN products p ON p.id = b.product_id
        JOIN units u ON u.id = p.unit_id
        WHERE b.id = :id
        LIMIT 1
    ");
    $stmt->execute([':id' => $bomId]);
    $existingBom = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$existingBom) {
        set_flash('danger', 'Bill of Materials not found.');
        header('Location: ' . $appUrl . '/pages/boms/index.php');
        exit;
    }

    $itemsStmt = $pdo->prepare("
        SELECT bi.*, m.name as material_name, m.code as material_code, u.symbol as unit_symbol
        FROM bom_items bi
        JOIN materials m ON m.id = bi.material_id
        JOIN units u ON u.id = m.unit_id
        WHERE bi.bom_id = :bom_id
        ORDER BY bi.id ASC
    ");
    $itemsStmt->execute([':bom_id' => $bomId]);
    $existingItems = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch active products for recipe selection
$products = $pdo->query("
    SELECT p.id, p.name, p.sku, u.symbol as unit_symbol 
    FROM products p 
    JOIN units u ON u.id = p.unit_id 
    WHERE p.is_active = 1 
    ORDER BY p.name ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Fetch active raw materials for components selector
$materials = $pdo->query("
    SELECT m.id, m.code, m.name, m.unit_cost, m.current_stock, u.symbol as unit_symbol 
    FROM materials m 
    JOIN units u ON u.id = m.unit_id 
    WHERE m.is_active = 1 
    ORDER BY m.name ASC
")->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../../includes/layout_header.php';
?>

<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <a href="<?= e($appUrl) ?>/pages/boms/index.php" class="text-secondary text-decoration-none">
                <i class="fa-solid fa-arrow-left me-1"></i> Bills of Materials
            </a>
            <span class="text-muted">/</span>
            <span class="text-dark fw-semibold"><?= $isEdit ? 'Edit Recipe' : 'New Recipe' ?></span>
        </div>
        <h4 class="fw-bold mb-0"><?= $isEdit ? 'Edit BOM: ' . e($existingBom['bom_code']) : 'Create Bill of Materials' ?></h4>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="<?= e($appUrl) ?>/pages/boms/index.php" class="btn btn-outline-secondary">
            Cancel
        </a>
        <button type="button" class="btn btn-primary" id="btnSubmitBomTop">
            <i class="fa-solid fa-floppy-disk me-1"></i> Save BOM
        </button>
    </div>
</div>

<form id="formBom" action="<?= e($appUrl) ?>/api/boms.php" method="POST">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="<?= $isEdit ? 'update' : 'create' ?>">
    <?php if ($isEdit): ?>
        <input type="hidden" name="id" value="<?= (int)$existingBom['id'] ?>">
    <?php endif; ?>

    <div class="alert alert-danger py-2 small mb-4 form-error-alert d-none" id="formAlertBox"></div>

    <div class="row g-4 mb-4">
        <!-- Header Specifications Card -->
        <div class="col-12 col-lg-8">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white py-3">
                    <span class="fw-semibold"><i class="fa-solid fa-sliders me-2 text-primary"></i>Recipe Header & Target Specifications</span>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12 col-md-8">
                            <label for="bom_product_id" class="form-label small fw-semibold text-secondary">Target Finished Product <span class="text-danger">*</span></label>
                            <select class="form-select" id="bom_product_id" name="product_id" required <?= $isEdit ? 'disabled' : '' ?>>
                                <option value="">Select Finished Product...</option>
                                <?php foreach ($products as $p): ?>
                                    <option value="<?= (int)$p['id'] ?>" 
                                            data-sku="<?= e($p['sku']) ?>" 
                                            data-unit="<?= e($p['unit_symbol']) ?>"
                                            <?= ($isEdit && (int)$existingBom['product_id'] === (int)$p['id']) ? 'selected' : '' ?>>
                                        <?= e($p['name']) ?> (SKU: <?= e($p['sku']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($isEdit): ?>
                                <input type="hidden" name="product_id" value="<?= (int)$existingBom['product_id'] ?>">
                            <?php endif; ?>
                        </div>

                        <div class="col-12 col-md-4">
                            <label for="bom_status" class="form-label small fw-semibold text-secondary">BOM Status <span class="text-danger">*</span></label>
                            <select class="form-select" id="bom_status" name="status" required>
                                <option value="draft" <?= ($isEdit && $existingBom['status'] === 'draft') ? 'selected' : '' ?>>Draft (In Development)</option>
                                <option value="active" <?= ($isEdit && $existingBom['status'] === 'active') ? 'selected' : (!$isEdit ? 'selected' : '') ?>>Active (Production Ready)</option>
                                <?php if ($isEdit): ?>
                                    <option value="archived" <?= ($existingBom['status'] === 'archived') ? 'selected' : '' ?>>Archived (Superseded)</option>
                                <?php endif; ?>
                            </select>
                            <div class="form-text small">Activating automatically archives older versions of this product.</div>
                        </div>

                        <div class="col-12 col-md-5">
                            <label for="bom_code" class="form-label small fw-semibold text-secondary">BOM Code / Formula ID <span class="text-danger">*</span></label>
                            <input type="text" class="form-control font-monospace" id="bom_code" name="bom_code" 
                                   value="<?= $isEdit ? e($existingBom['bom_code']) : '' ?>" 
                                   placeholder="e.g. BOM-PRD-001-V1" required maxlength="50">
                        </div>

                        <div class="col-6 col-md-3">
                            <label for="bom_version" class="form-label small fw-semibold text-secondary">Version <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light small">v</span>
                                <input type="number" class="form-control" id="bom_version" name="version" 
                                       value="<?= $isEdit ? (int)$existingBom['version'] : 1 ?>" min="1" max="999" required>
                            </div>
                        </div>

                        <div class="col-6 col-md-4">
                            <label for="bom_yield_quantity" class="form-label small fw-semibold text-secondary">Batch Yield Output <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="number" step="0.01" min="0.01" class="form-control" id="bom_yield_quantity" name="yield_quantity" 
                                       value="<?= $isEdit ? (float)$existingBom['yield_quantity'] : 1.00 ?>" required>
                                <span class="input-group-text bg-light small" id="yieldUnitBadge">Units</span>
                            </div>
                            <div class="form-text small">Standard finished output batch quantity.</div>
                        </div>

                        <div class="col-12">
                            <label for="bom_notes" class="form-label small fw-semibold text-secondary">Engineering Notes & Instructions</label>
                            <textarea class="form-control" id="bom_notes" name="notes" rows="2" 
                                      placeholder="Assembly specifications, machine tolerances, mixing sequences, quality guidelines..."><?= $isEdit ? e($existingBom['notes']) : '' ?></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Live Cost Rollup Summary Card -->
        <div class="col-12 col-lg-4">
            <div class="card shadow-sm h-100 border-primary-subtle">
                <div class="card-header bg-primary text-white py-3 d-flex align-items-center justify-content-between">
                    <span class="fw-semibold"><i class="fa-solid fa-calculator me-2"></i>Cost Rollup Engine</span>
                    <span class="badge bg-white text-primary fw-bold" id="cardComponentCount">0 Items</span>
                </div>
                <div class="card-body d-flex flex-column justify-content-between">
                    <div>
                        <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                            <span class="text-muted small">Net Material Cost:</span>
                            <span class="fw-semibold text-dark" id="summaryNetMaterialCost">$0.00</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                            <span class="text-muted small">Scrap / Waste Allowance:</span>
                            <span class="fw-semibold text-warning" id="summaryScrapCost">+$0.00</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                            <span class="text-muted small">Total Batch Cost:</span>
                            <span class="fw-bold fs-5 text-dark" id="summaryTotalCost">$0.00</span>
                        </div>
                    </div>

                    <div class="bg-light p-3 rounded border text-center mt-3">
                        <span class="text-secondary small text-uppercase fw-bold letter-spacing-1 d-block mb-1">Calculated Standard Cost Per Unit</span>
                        <div class="fs-2 fw-bolder text-primary mb-1" id="summaryCostPerUnit">$0.00</div>
                        <div class="small text-muted" id="summaryYieldFormula">Divided by 1.00 unit yield</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Materials Table (Repeater) -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
            <div>
                <span class="fw-semibold"><i class="fa-solid fa-cubes-stacked me-2 text-primary"></i>Raw Material Components</span>
                <span class="text-muted small ms-2">Add all required materials, allowances, and scrap percentages</span>
            </div>
            <button type="button" class="btn btn-sm btn-outline-primary" id="btnAddRow">
                <i class="fa-solid fa-plus me-1"></i> Add Component Row
            </button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="itemsTable">
                    <thead class="table-light small text-uppercase text-secondary">
                        <tr>
                            <th style="width: 40px;" class="text-center">#</th>
                            <th style="min-width: 280px;">Raw Material Item <span class="text-danger">*</span></th>
                            <th style="width: 90px;" class="text-center">Unit</th>
                            <th style="width: 140px;" class="text-end">Standard Cost</th>
                            <th style="width: 140px;" class="text-end">Qty Required <span class="text-danger">*</span></th>
                            <th style="width: 140px;" class="text-end">Scrap %</th>
                            <th style="width: 160px;" class="text-end">Subtotal Cost</th>
                            <th style="width: 60px;" class="text-center"></th>
                        </tr>
                    </thead>
                    <tbody id="itemsTableBody">
                        <!-- Populated by JavaScript -->
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-white py-3 d-flex align-items-center justify-content-between">
            <button type="button" class="btn btn-sm btn-outline-primary" id="btnAddRowBottom">
                <i class="fa-solid fa-plus me-1"></i> Add Another Material
            </button>
            <div class="d-flex align-items-center gap-3">
                <span class="text-muted small">Formula: <code class="text-dark">(Qty &times; (1 + Scrap%/100)) &times; Unit Cost</code></span>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-end gap-2 mb-5">
        <a href="<?= e($appUrl) ?>/pages/boms/index.php" class="btn btn-light border">Cancel</a>
        <button type="submit" class="btn btn-primary px-4" id="btnSubmitBom">
            <i class="fa-solid fa-floppy-disk me-1"></i> Save Bill of Materials
        </button>
    </div>
</form>

<script>
window.FACTORYFLOW_MATERIALS = <?= json_encode($materials, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
window.INITIAL_ITEMS = <?= json_encode($existingItems, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

document.addEventListener('DOMContentLoaded', function () {
    const appUrl = '<?= e($appUrl) ?>';
    const form = document.getElementById('formBom');
    const tbody = document.getElementById('itemsTableBody');
    const btnAddTop = document.getElementById('btnAddRow');
    const btnAddBottom = document.getElementById('btnAddRowBottom');
    const productSelect = document.getElementById('bom_product_id');
    const bomCodeInput = document.getElementById('bom_code');
    const versionInput = document.getElementById('bom_version');
    const yieldInput = document.getElementById('bom_yield_quantity');
    const yieldUnitBadge = document.getElementById('yieldUnitBadge');
    const alertBox = document.getElementById('formAlertBox');

    // Summary Elements
    const summaryNetEl = document.getElementById('summaryNetMaterialCost');
    const summaryScrapEl = document.getElementById('summaryScrapCost');
    const summaryTotalEl = document.getElementById('summaryTotalCost');
    const summaryUnitEl = document.getElementById('summaryCostPerUnit');
    const summaryFormulaEl = document.getElementById('summaryYieldFormula');
    const cardCountEl = document.getElementById('cardComponentCount');

    let rowCounter = 0;

    function buildMaterialOptions(selectedId = null) {
        let html = '<option value="">Select Raw Material Component...</option>';
        window.FACTORYFLOW_MATERIALS.forEach(function (m) {
            const isSel = (selectedId && parseInt(selectedId) === parseInt(m.id)) ? 'selected' : '';
            html += `<option value="${m.id}" data-cost="${m.unit_cost}" data-unit="${m.unit_symbol}" data-stock="${m.current_stock}" ${isSel}>${m.name} (${m.code}) - $${parseFloat(m.unit_cost).toFixed(2)}</option>`;
        });
        return html;
    }

    function addRow(data = null) {
        rowCounter++;
        const rowId = `row-${rowCounter}`;
        const matId = data ? data.material_id : '';
        const qty = data ? parseFloat(data.quantity_required || 0) : 1;
        const scrap = data ? parseFloat(data.wastage_percent || 0) : 0;
        const cost = data ? parseFloat(data.unit_cost || 0) : 0;
        const unitSymbol = data ? (data.unit_symbol || '-') : '-';

        const tr = document.createElement('tr');
        tr.id = rowId;
        tr.className = 'component-row';
        tr.innerHTML = `
            <td class="text-center text-muted small fw-semibold row-number"></td>
            <td>
                <select class="form-select form-select-sm material-select" name="items[${rowCounter}][material_id]" required>
                    ${buildMaterialOptions(matId)}
                </select>
            </td>
            <td class="text-center">
                <span class="badge bg-secondary-subtle text-secondary unit-badge">${unitSymbol}</span>
            </td>
            <td>
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light">$</span>
                    <input type="number" step="0.01" min="0" class="form-control text-end unit-cost-input" 
                           name="items[${rowCounter}][unit_cost]" value="${cost > 0 ? cost.toFixed(2) : '0.00'}" required>
                </div>
            </td>
            <td>
                <input type="number" step="0.0001" min="0.0001" class="form-control form-control-sm text-end qty-input" 
                       name="items[${rowCounter}][quantity]" value="${qty > 0 ? qty : '1'}" required>
            </td>
            <td>
                <div class="input-group input-group-sm">
                    <input type="number" step="0.01" min="0" max="100" class="form-control text-end scrap-input" 
                           name="items[${rowCounter}][scrap_percentage]" value="${scrap.toFixed(2)}">
                    <span class="input-group-text bg-light">%</span>
                </div>
            </td>
            <td class="text-end">
                <span class="fw-bold text-dark line-cost-display">$0.00</span>
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-outline-danger btn-remove-row p-1" title="Remove row">
                    <i class="fa-solid fa-trash-can"></i>
                </button>
            </td>
        `;

        tbody.appendChild(tr);

        const select = tr.querySelector('.material-select');
        const costInput = tr.querySelector('.unit-cost-input');
        const qtyInput = tr.querySelector('.qty-input');
        const scrapInput = tr.querySelector('.scrap-input');
        const removeBtn = tr.querySelector('.btn-remove-row');

        select.addEventListener('change', function () {
            const opt = select.selectedOptions[0];
            if (opt && opt.value) {
                const defaultCost = parseFloat(opt.dataset.cost || 0);
                const unit = opt.dataset.unit || '-';
                costInput.value = defaultCost.toFixed(2);
                tr.querySelector('.unit-badge').textContent = unit;
            } else {
                tr.querySelector('.unit-badge').textContent = '-';
            }
            recalculate();
        });

        costInput.addEventListener('input', recalculate);
        qtyInput.addEventListener('input', recalculate);
        scrapInput.addEventListener('input', recalculate);

        removeBtn.addEventListener('click', function () {
            tr.remove();
            updateRowNumbers();
            recalculate();
        });

        updateRowNumbers();
        recalculate();
    }

    function updateRowNumbers() {
        const rows = tbody.querySelectorAll('.component-row');
        rows.forEach(function (r, i) {
            r.querySelector('.row-number').textContent = (i + 1).toString();
        });
        if (cardCountEl) {
            cardCountEl.textContent = `${rows.length} Items`;
        }
    }

    function recalculate() {
        let totalGross = 0.00;
        let totalWithScrap = 0.00;
        const rows = tbody.querySelectorAll('.component-row');

        rows.forEach(function (r) {
            const cost = parseFloat(r.querySelector('.unit-cost-input').value) || 0;
            const qty = parseFloat(r.querySelector('.qty-input').value) || 0;
            const scrap = parseFloat(r.querySelector('.scrap-input').value) || 0;

            const baseCost = qty * cost;
            const lineCost = (qty * (1 + (scrap / 100))) * cost;

            totalGross += baseCost;
            totalWithScrap += lineCost;

            r.querySelector('.line-cost-display').textContent = '$' + lineCost.toFixed(2);
        });

        const scrapCost = Math.max(0, totalWithScrap - totalGross);
        const yieldQty = Math.max(0.01, parseFloat(yieldInput.value) || 1.00);
        const costPerUnit = totalWithScrap / yieldQty;

        if (summaryNetEl) summaryNetEl.textContent = '$' + totalGross.toFixed(2);
        if (summaryScrapEl) summaryScrapEl.textContent = '+$' + scrapCost.toFixed(2);
        if (summaryTotalEl) summaryTotalEl.textContent = '$' + totalWithScrap.toFixed(2);
        if (summaryUnitEl) summaryUnitEl.textContent = '$' + costPerUnit.toFixed(2);
        if (summaryFormulaEl) summaryFormulaEl.textContent = `Divided by ${yieldQty.toFixed(2)} unit yield`;
    }

    function autoGenerateCode() {
        const opt = productSelect.selectedOptions[0];
        if (!opt || !opt.value) {
            App.toast('warning', 'Please select a finished product first.');
            return;
        }
        const sku = opt.dataset.sku || 'PRD';
        const ver = versionInput.value || '1';
        bomCodeInput.value = `BOM-${sku}-V${ver}`;
    }

    if (productSelect) {
        productSelect.addEventListener('change', function () {
            const opt = productSelect.selectedOptions[0];
            if (opt && opt.value) {
                const unit = opt.dataset.unit || 'Units';
                if (yieldUnitBadge) yieldUnitBadge.textContent = unit;
                if (!bomCodeInput.value.trim()) {
                    autoGenerateCode();
                }
            } else {
                if (yieldUnitBadge) yieldUnitBadge.textContent = 'Units';
            }
        });
    }


    if (versionInput) {
        versionInput.addEventListener('change', function () {
            if (bomCodeInput.value.startsWith('BOM-')) {
                autoGenerateCode();
            }
        });
    }

    if (yieldInput) {
        yieldInput.addEventListener('input', recalculate);
    }

    if (btnAddTop) btnAddTop.addEventListener('click', () => addRow());
    if (btnAddBottom) btnAddBottom.addEventListener('click', () => addRow());

    // Populate initial items or blank row
    if (window.INITIAL_ITEMS && window.INITIAL_ITEMS.length > 0) {
        window.INITIAL_ITEMS.forEach(item => addRow(item));
    } else {
        addRow();
    }

    // Top save button proxy
    const btnTop = document.getElementById('btnSubmitBomTop');
    if (btnTop) {
        btnTop.addEventListener('click', function () {
            form.requestSubmit();
        });
    }

    // Form Submission
    form.addEventListener('submit', async function (e) {
        e.preventDefault();

        if (alertBox) {
            alertBox.classList.add('d-none');
            alertBox.textContent = '';
        }

        const rows = tbody.querySelectorAll('.component-row');
        if (!rows.length) {
            App.toast('error', 'Please add at least one material component.');
            return;
        }

        const submitBtn = document.getElementById('btnSubmitBom');
        const origBtnHtml = submitBtn ? submitBtn.innerHTML : '';
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving BOM...';
        }
        if (btnTop) btnTop.disabled = true;

        const formData = new FormData(form);
        const targetUrl = form.getAttribute('action') || `${appUrl}/api/boms.php`;

        try {
            const res = await App.request(targetUrl, {
                method: 'POST',
                body: formData
            });

            App.toast('success', (res && res.message) || (res && res.data && res.data.message) || 'BOM saved successfully.');
            setTimeout(function () {
                window.location.href = `${appUrl}/pages/boms/index.php`;
            }, 600);
        } catch (err) {
            const msg = err.message || 'Failed to save Bill of Materials.';
            if (alertBox) {
                alertBox.textContent = msg;
                alertBox.classList.remove('d-none');
                alertBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
            } else {
                App.toast('error', msg);
            }
        } finally {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = origBtnHtml;
            }
            if (btnTop) btnTop.disabled = false;
        }
    });
});
</script>

<?php require_once __DIR__ . '/../../includes/layout_footer.php'; ?>
