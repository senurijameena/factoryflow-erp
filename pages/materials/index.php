<?php
declare(strict_types=1);

$pageTitle = 'Raw Materials';

require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/rbac.php';

require_role([ROLE_ADMIN, ROLE_MANAGER]);

$pdo = Database::getConnection();
$appUrl = rtrim((string)(defined('APP_URL') ? APP_URL : Config::get('APP_URL', 'http://localhost/factoryflow')), '/');

$categoriesStmt = $pdo->query("SELECT id, name FROM categories WHERE type = 'material' ORDER BY name ASC");
$materialCategories = $categoriesStmt->fetchAll(PDO::FETCH_ASSOC);

$unitsStmt = $pdo->query("SELECT id, name, symbol FROM units ORDER BY name ASC");
$unitsList = $unitsStmt->fetchAll(PDO::FETCH_ASSOC);

$suppliersStmt = $pdo->query("SELECT id, name FROM suppliers WHERE is_active = 1 ORDER BY name ASC");
$suppliersList = $suppliersStmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../../includes/layout_header.php';
?>

<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1">Raw Materials</h4>
        <p class="text-muted small mb-0">Maintain specifications, procurement pricing, safety thresholds, and inventory levels for manufacturing components.</p>
    </div>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalMaterialCreate">
        <i class="fa-solid fa-plus me-1"></i> Add Material
    </button>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-body py-3">
        <div class="row g-2 align-items-center">
            <div class="col-12 col-md-4">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                    <input type="text" id="searchMaterial" class="form-control" placeholder="Search by name or material code...">
                </div>
            </div>
            <div class="col-6 col-md-3">
                <select id="filterCategory" class="form-select form-select-sm">
                    <option value="">All Categories</option>
                    <?php foreach ($materialCategories as $cat): ?>
                        <option value="<?= (int)$cat['id'] ?>"><?= e($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <div class="form-check form-switch mb-0 d-flex align-items-center gap-2 pt-1">
                    <input class="form-check-input" type="checkbox" role="switch" id="filterLowStockOnly">
                    <label class="form-check-label small text-danger fw-semibold user-select-none" for="filterLowStockOnly">
                        <i class="fa-solid fa-triangle-exclamation me-1"></i> Low Stock Alert Only
                    </label>
                </div>
            </div>
            <div class="col-12 col-md-2">
                <button type="button" id="btnResetFilters" class="btn btn-sm btn-outline-secondary w-100" title="Reset Filters">
                    Reset Filters
                </button>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
        <span class="fw-semibold">Materials Inventory</span>
        <span class="badge bg-light text-secondary border" id="materialCountBadge">Loading...</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="materialsTable">
                <thead>
                    <tr>
                        <th>Material Code</th>
                        <th>Material Name</th>
                        <th>Category</th>
                        <th>Unit</th>
                        <th class="text-end">Unit Cost</th>
                        <th class="text-end">Current Stock</th>
                        <th class="text-end">Reorder Level</th>
                        <th class="text-center">Stock Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="materialsTableBody">
                    <tr>
                        <td colspan="9" class="text-center py-5 text-muted">
                            <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                            Loading raw materials...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer bg-white py-2 d-flex align-items-center justify-content-between" id="paginationWrapper" style="display: none !important;">
        <span class="text-muted small" id="paginationInfo">Showing 0 of 0 materials</span>
        <nav aria-label="Materials pagination">
            <ul class="pagination pagination-sm mb-0" id="paginationList"></ul>
        </nav>
    </div>
</div>

<!-- Modal: Create Raw Material -->
<div class="modal fade" id="modalMaterialCreate" tabindex="-1" aria-labelledby="modalMaterialCreateLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form id="formMaterialCreate" action="<?= e($appUrl) ?>/api/materials.php" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create">

                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="modalMaterialCreateLabel">Add Raw Material</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger py-2 small mb-3 form-error-alert d-none"></div>

                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label for="create_code" class="form-label small fw-semibold text-secondary">Material Code</label>
                            <input type="text" class="form-control" id="create_code" name="material_code" placeholder="Leave blank to auto-generate (MAT-YYYY-XXXX)" maxlength="50">
                            <div class="form-text small">Auto-generated formatted identifier if left blank.</div>
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="create_name" class="form-label small fw-semibold text-secondary">Material Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="create_name" name="name" placeholder="e.g. Cold Rolled Steel Sheet 2mm" required maxlength="150">
                        </div>

                        <div class="col-12 col-md-6">
                            <label for="create_category_id" class="form-label small fw-semibold text-secondary">Category <span class="text-danger">*</span></label>
                            <select class="form-select" id="create_category_id" name="category_id" required>
                                <option value="">Select Material Category...</option>
                                <?php foreach ($materialCategories as $cat): ?>
                                    <option value="<?= (int)$cat['id'] ?>"><?= e($cat['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="create_unit_id" class="form-label small fw-semibold text-secondary">Unit of Measure <span class="text-danger">*</span></label>
                            <select class="form-select" id="create_unit_id" name="unit_id" required>
                                <option value="">Select Unit...</option>
                                <?php foreach ($unitsList as $u): ?>
                                    <option value="<?= (int)$u['id'] ?>"><?= e($u['name']) ?> (<?= e($u['symbol']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-12 col-md-4">
                            <label for="create_unit_cost" class="form-label small fw-semibold text-secondary">Standard Unit Cost ($) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0" class="form-control" id="create_unit_cost" name="unit_cost" placeholder="0.00" value="0.00" required>
                        </div>
                        <div class="col-12 col-md-4">
                            <label for="create_reorder_level" class="form-label small fw-semibold text-secondary">Safety Reorder Level <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0" class="form-control" id="create_reorder_level" name="reorder_level" placeholder="10.00" value="10.00" required>
                        </div>
                        <div class="col-12 col-md-4">
                            <label for="create_current_stock" class="form-label small fw-semibold text-secondary">Initial Stock Balance</label>
                            <input type="number" step="0.01" min="0" class="form-control" id="create_current_stock" name="current_stock" placeholder="0.00" value="0.00">
                        </div>

                        <div class="col-12">
                            <label for="create_supplier_id" class="form-label small fw-semibold text-secondary">Preferred Supplier</label>
                            <select class="form-select" id="create_supplier_id" name="supplier_id">
                                <option value="">None (Select Preferred Vendor)...</option>
                                <?php foreach ($suppliersList as $s): ?>
                                    <option value="<?= (int)$s['id'] ?>"><?= e($s['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-12">
                            <label for="create_description" class="form-label small fw-semibold text-secondary">Description & Technical Specifications</label>
                            <textarea class="form-control" id="create_description" name="description" rows="2" placeholder="Grade, alloy specs, dimensions, storage constraints..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary">Save Material</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Edit Raw Material -->
<div class="modal fade" id="modalMaterialEdit" tabindex="-1" aria-labelledby="modalMaterialEditLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form id="formMaterialEdit" action="<?= e($appUrl) ?>/api/materials.php" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="edit_id">

                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="modalMaterialEditLabel">Edit Raw Material</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger py-2 small mb-3 form-error-alert d-none"></div>

                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label for="edit_code" class="form-label small fw-semibold text-secondary">Material Code <span class="text-danger">*</span></label>
                            <input type="text" class="form-control font-monospace" id="edit_code" name="material_code" required maxlength="50">
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="edit_name" class="form-label small fw-semibold text-secondary">Material Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_name" name="name" required maxlength="150">
                        </div>

                        <div class="col-12 col-md-6">
                            <label for="edit_category_id" class="form-label small fw-semibold text-secondary">Category <span class="text-danger">*</span></label>
                            <select class="form-select" id="edit_category_id" name="category_id" required>
                                <?php foreach ($materialCategories as $cat): ?>
                                    <option value="<?= (int)$cat['id'] ?>"><?= e($cat['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="edit_unit_id" class="form-label small fw-semibold text-secondary">Unit of Measure <span class="text-danger">*</span></label>
                            <select class="form-select" id="edit_unit_id" name="unit_id" required>
                                <?php foreach ($unitsList as $u): ?>
                                    <option value="<?= (int)$u['id'] ?>"><?= e($u['name']) ?> (<?= e($u['symbol']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-12 col-md-4">
                            <label for="edit_unit_cost" class="form-label small fw-semibold text-secondary">Standard Unit Cost ($) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0" class="form-control" id="edit_unit_cost" name="unit_cost" required>
                        </div>
                        <div class="col-12 col-md-4">
                            <label for="edit_reorder_level" class="form-label small fw-semibold text-secondary">Safety Reorder Level <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0" class="form-control" id="edit_reorder_level" name="reorder_level" required>
                        </div>
                        <div class="col-12 col-md-4">
                            <label for="edit_is_active" class="form-label small fw-semibold text-secondary">Status</label>
                            <select class="form-select" id="edit_is_active" name="is_active">
                                <option value="1">Active</option>
                                <option value="0">Disabled / Inactive</option>
                            </select>
                        </div>

                        <div class="col-12">
                            <label for="edit_supplier_id" class="form-label small fw-semibold text-secondary">Preferred Supplier</label>
                            <select class="form-select" id="edit_supplier_id" name="supplier_id">
                                <option value="">None (No Preferred Vendor)</option>
                                <?php foreach ($suppliersList as $s): ?>
                                    <option value="<?= (int)$s['id'] ?>"><?= e($s['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-12">
                            <label for="edit_description" class="form-label small fw-semibold text-secondary">Description & Technical Specifications</label>
                            <textarea class="form-control" id="edit_description" name="description" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary">Update Material</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const appUrl = '<?= e($appUrl) ?>';
    const tbody = document.getElementById('materialsTableBody');
    const searchInput = document.getElementById('searchMaterial');
    const categorySelect = document.getElementById('filterCategory');
    const lowStockSwitch = document.getElementById('filterLowStockOnly');
    const btnReset = document.getElementById('btnResetFilters');
    const badgeCount = document.getElementById('materialCountBadge');

    const paginationWrapper = document.getElementById('paginationWrapper');
    const paginationInfo = document.getElementById('paginationInfo');
    const paginationList = document.getElementById('paginationList');

    let currentPage = 1;
    let debounceTimer = null;

    async function loadMaterials(page = 1) {
        currentPage = page;
        const search = searchInput ? searchInput.value.trim() : '';
        const categoryId = categorySelect ? categorySelect.value : '';
        const lowStock = (lowStockSwitch && lowStockSwitch.checked) ? '1' : '0';

        const params = new URLSearchParams({
            action: 'list',
            page: currentPage,
            per_page: 15,
            search: search,
            category_id: categoryId,
            low_stock: lowStock
        });

        try {
            const res = await App.request(`${appUrl}/api/materials.php?${params.toString()}`);
            const items = res.data || [];
            const meta = res.meta || { page: 1, per_page: 15, total_items: items.length, total_pages: 1 };

            if (badgeCount) {
                badgeCount.textContent = `${meta.total_items} items`;
            }

            if (!items.length) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="9" class="text-center py-5 text-muted">
                            <i class="fa-solid fa-cubes-stacked d-block mb-2" style="font-size: 2rem;"></i>
                            No raw materials found matching your criteria.
                        </td>
                    </tr>
                `;
                if (paginationWrapper) paginationWrapper.style.setProperty('display', 'none', 'important');
                return;
            }

            tbody.innerHTML = items.map(function (m) {
                const isLow = Boolean(m.is_low_stock);
                const currentStock = parseFloat(m.current_stock || 0);
                const reorderLevel = parseFloat(m.reorder_level || 0);
                const unitCost = parseFloat(m.unit_cost || 0);

                let statusBadge = '';
                if (currentStock <= 0) {
                    statusBadge = '<span class="badge bg-danger-subtle text-danger border border-danger"><i class="fa-solid fa-circle-xmark me-1"></i>Out of Stock</span>';
                } else if (isLow) {
                    statusBadge = '<span class="badge bg-warning-subtle text-warning border border-warning"><i class="fa-solid fa-triangle-exclamation me-1"></i>Low Stock Alert</span>';
                } else {
                    statusBadge = '<span class="badge bg-success-subtle text-success border border-success"><i class="fa-solid fa-circle-check me-1"></i>Normal</span>';
                }

                const safeName = (m.name || '').replace(/"/g, '&quot;');
                const safeCode = (m.code || '').replace(/"/g, '&quot;');
                const safeDesc = (m.description || '').replace(/"/g, '&quot;');

                return `
                    <tr id="material-row-${m.id}">
                        <td><span class="font-monospace fw-bold text-primary">${m.code}</span></td>
                        <td>
                            <div class="fw-semibold text-dark">${m.name}</div>
                            ${m.description ? `<div class="text-muted small text-truncate" style="max-width: 250px;">${m.description}</div>` : ''}
                        </td>
                        <td><span class="badge bg-light text-dark border">${m.category_name}</span></td>
                        <td><span class="badge bg-secondary-subtle text-secondary">${m.unit_symbol}</span></td>
                        <td class="text-end fw-semibold">$${unitCost.toFixed(2)}</td>
                        <td class="text-end fw-bold ${isLow ? 'text-danger' : 'text-dark'}">${currentStock.toFixed(2)}</td>
                        <td class="text-end text-muted">${reorderLevel.toFixed(2)}</td>
                        <td class="text-center">${statusBadge}</td>
                        <td class="text-end">
                            <button type="button" 
                                    class="btn btn-sm btn-outline-primary me-1 btn-edit-material" 
                                    data-id="${m.id}"
                                    data-code="${safeCode}"
                                    data-name="${safeName}"
                                    data-category-id="${m.category_id}"
                                    data-unit-id="${m.unit_id}"
                                    data-unit-cost="${unitCost}"
                                    data-reorder-level="${reorderLevel}"
                                    data-supplier-id="${m.supplier_id || ''}"
                                    data-description="${safeDesc}"
                                    data-is-active="${m.is_active}"
                                    title="Edit Material">
                                <i class="fa-solid fa-pen-to-square"></i>
                            </button>
                            <button type="button" 
                                    class="btn btn-sm btn-outline-danger btn-delete-material" 
                                    data-id="${m.id}"
                                    data-name="${safeName}"
                                    title="Delete Material">
                                <i class="fa-solid fa-trash-can"></i>
                            </button>
                        </td>
                    </tr>
                `;
            }).join('');

            renderPagination(meta);
            bindRowActions();
        } catch (err) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="9" class="text-center py-4 text-danger">
                        ${err.message || 'Failed to load materials.'}
                    </td>
                </tr>
            `;
        }
    }

    function renderPagination(meta) {
        if (!paginationWrapper || !paginationList || !paginationInfo) return;

        if (meta.total_pages <= 1) {
            paginationWrapper.style.setProperty('display', 'none', 'important');
            return;
        }

        paginationWrapper.style.removeProperty('display');
        const start = ((meta.page - 1) * meta.per_page) + 1;
        const end = Math.min(meta.page * meta.per_page, meta.total_items);
        paginationInfo.textContent = `Showing ${start} to ${end} of ${meta.total_items} materials`;

        let html = '';
        html += `
            <li class="page-item ${meta.page <= 1 ? 'disabled' : ''}">
                <button class="page-link" data-page="${meta.page - 1}" aria-label="Previous">&laquo;</button>
            </li>
        `;

        for (let p = 1; p <= meta.total_pages; p++) {
            if (p === 1 || p === meta.total_pages || (p >= meta.page - 2 && p <= meta.page + 2)) {
                html += `
                    <li class="page-item ${p === meta.page ? 'active' : ''}">
                        <button class="page-link" data-page="${p}">${p}</button>
                    </li>
                `;
            } else if (p === meta.page - 3 || p === meta.page + 3) {
                html += `<li class="page-item disabled"><span class="page-link">&hellip;</span></li>`;
            }
        }

        html += `
            <li class="page-item ${meta.page >= meta.total_pages ? 'disabled' : ''}">
                <button class="page-link" data-page="${meta.page + 1}" aria-label="Next">&raquo;</button>
            </li>
        `;

        paginationList.innerHTML = html;

        paginationList.querySelectorAll('button.page-link').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const targetPage = parseInt(this.dataset.page);
                if (targetPage && targetPage !== currentPage && targetPage >= 1 && targetPage <= meta.total_pages) {
                    loadMaterials(targetPage);
                }
            });
        });
    }

    function bindRowActions() {
        document.querySelectorAll('.btn-edit-material').forEach(function (btn) {
            btn.addEventListener('click', function () {
                document.getElementById('edit_id').value = this.dataset.id;
                document.getElementById('edit_code').value = this.dataset.code;
                document.getElementById('edit_name').value = this.dataset.name;
                document.getElementById('edit_category_id').value = this.dataset.categoryId;
                document.getElementById('edit_unit_id').value = this.dataset.unitId;
                document.getElementById('edit_unit_cost').value = this.dataset.unitCost;
                document.getElementById('edit_reorder_level').value = this.dataset.reorderLevel;
                document.getElementById('edit_supplier_id').value = this.dataset.supplierId || '';
                document.getElementById('edit_description').value = this.dataset.description || '';
                document.getElementById('edit_is_active').value = this.dataset.isActive;

                const modal = new bootstrap.Modal(document.getElementById('modalMaterialEdit'));
                modal.show();
            });
        });

        document.querySelectorAll('.btn-delete-material').forEach(function (btn) {
            btn.addEventListener('click', async function () {
                const id = this.dataset.id;
                const name = this.dataset.name;

                const confirmed = await App.confirm(
                    'Delete Material',
                    `Are you sure you want to delete "${name}"? This action cannot be undone if linked to active manufacturing operations.`,
                    'Yes, Delete'
                );

                if (!confirmed) return;

                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('id', id);

                try {
                    const res = await App.request(`${appUrl}/api/materials.php`, {
                        method: 'POST',
                        body: formData
                    });
                    App.toast('success', (res && res.message) || (res && res.data && res.data.message) || 'Material deleted.');
                    loadMaterials(currentPage);
                } catch (err) {
                    App.toast('error', err.message || 'Failed to delete material.');
                }
            });
        });
    }

    if (searchInput) {
        searchInput.addEventListener('input', function () {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function () {
                loadMaterials(1);
            }, 300);
        });
    }

    if (categorySelect) {
        categorySelect.addEventListener('change', function () {
            loadMaterials(1);
        });
    }

    if (lowStockSwitch) {
        lowStockSwitch.addEventListener('change', function () {
            loadMaterials(1);
        });
    }

    if (btnReset) {
        btnReset.addEventListener('click', function () {
            if (searchInput) searchInput.value = '';
            if (categorySelect) categorySelect.value = '';
            if (lowStockSwitch) lowStockSwitch.checked = false;
            loadMaterials(1);
        });
    }

    App.bindAjaxForm('formMaterialCreate', function () {
        loadMaterials(1);
    });

    App.bindAjaxForm('formMaterialEdit', function () {
        loadMaterials(currentPage);
    });

    loadMaterials(1);
});
</script>

<?php require_once __DIR__ . '/../../includes/layout_footer.php'; ?>
