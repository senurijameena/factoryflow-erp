<?php
declare(strict_types=1);

$pageTitle = 'Bills of Materials (BOM)';

require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/rbac.php';

require_role([ROLE_ADMIN, ROLE_MANAGER]);

$appUrl = rtrim((string)(defined('APP_URL') ? APP_URL : Config::get('APP_URL', 'http://localhost/factoryflow')), '/');

require_once __DIR__ . '/../../includes/layout_header.php';
?>

<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1">Bills of Materials (BOM)</h4>
        <p class="text-muted small mb-0">Maintain multi-level production recipes, scrap allowances, yield ratios, and calculated standard manufacturing costs.</p>
    </div>
    <a href="<?= e($appUrl) ?>/pages/boms/form.php" class="btn btn-primary">
        <i class="fa-solid fa-plus me-1"></i> Create New BOM
    </a>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-body py-3">
        <div class="row g-2 align-items-center">
            <div class="col-12 col-md-6">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                    <input type="text" id="searchBom" class="form-control" placeholder="Search by BOM code, finished product name, or SKU...">
                </div>
            </div>
            <div class="col-6 col-md-3">
                <select id="filterBomStatus" class="form-select form-select-sm">
                    <option value="all">All Statuses</option>
                    <option value="active">Active Only</option>
                    <option value="draft">Drafts Only</option>
                    <option value="archived">Archived Versions</option>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <button type="button" id="btnResetBomFilters" class="btn btn-sm btn-outline-secondary w-100">
                    Reset Filter
                </button>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
        <span class="fw-semibold">Registered Product Recipes</span>
        <span class="badge bg-light text-secondary border" id="bomCountBadge">Loading...</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="bomsTable">
                <thead>
                    <tr>
                        <th>BOM Code</th>
                        <th>Finished Product</th>
                        <th class="text-center">Version</th>
                        <th class="text-center">Components</th>
                        <th class="text-end">Batch Yield</th>
                        <th class="text-end">Recipe Cost</th>
                        <th class="text-end">Cost / Unit</th>
                        <th class="text-center">Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="bomsTableBody">
                    <tr>
                        <td colspan="9" class="text-center py-5 text-muted">
                            <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                            Loading bills of materials...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer bg-white py-2 d-flex align-items-center justify-content-between" id="paginationWrapper" style="display: none !important;">
        <span class="text-muted small" id="paginationInfo">Showing 0 of 0 BOMs</span>
        <nav aria-label="BOM pagination">
            <ul class="pagination pagination-sm mb-0" id="paginationList"></ul>
        </nav>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const appUrl = '<?= e($appUrl) ?>';
    const tbody = document.getElementById('bomsTableBody');
    const searchInput = document.getElementById('searchBom');
    const statusSelect = document.getElementById('filterBomStatus');
    const btnReset = document.getElementById('btnResetBomFilters');
    const badgeCount = document.getElementById('bomCountBadge');

    const paginationWrapper = document.getElementById('paginationWrapper');
    const paginationInfo = document.getElementById('paginationInfo');
    const paginationList = document.getElementById('paginationList');

    let currentPage = 1;
    let debounceTimer = null;

    async function loadBoms(page = 1) {
        currentPage = page;
        const search = searchInput ? searchInput.value.trim() : '';
        const status = statusSelect ? statusSelect.value : 'all';

        const params = new URLSearchParams({
            action: 'list',
            page: currentPage,
            per_page: 15,
            search: search,
            status: status
        });

        try {
            const res = await App.request(`${appUrl}/api/boms.php?${params.toString()}`);
            const items = res.data || [];
            const meta = res.meta || { page: 1, per_page: 15, total_items: items.length, total_pages: 1 };

            if (badgeCount) {
                badgeCount.textContent = `${meta.total_items} BOMs`;
            }

            if (!items.length) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="9" class="text-center py-5 text-muted">
                            <i class="fa-solid fa-layer-group d-block mb-2" style="font-size: 2rem;"></i>
                            No bill of materials found matching your criteria.
                        </td>
                    </tr>
                `;
                if (paginationWrapper) paginationWrapper.style.setProperty('display', 'none', 'important');
                return;
            }

            tbody.innerHTML = items.map(function (b) {
                const totalCost = parseFloat(b.total_cost || 0);
                const costPerUnit = parseFloat(b.cost_per_unit || 0);
                const yieldQty = parseFloat(b.yield_quantity || 1);
                const statusStr = (b.status || 'draft').toLowerCase();

                let statusBadge = '';
                if (statusStr === 'active') {
                    statusBadge = '<span class="badge bg-success-subtle text-success border border-success"><i class="fa-solid fa-circle-check me-1"></i>Active</span>';
                } else if (statusStr === 'draft') {
                    statusBadge = '<span class="badge bg-warning-subtle text-warning border border-warning"><i class="fa-solid fa-pen-ruler me-1"></i>Draft</span>';
                } else {
                    statusBadge = '<span class="badge bg-secondary-subtle text-secondary border"><i class="fa-solid fa-box-archive me-1"></i>Archived</span>';
                }

                const safeCode = (b.bom_code || '').replace(/"/g, '&quot;');
                const safeName = (b.product_name || '').replace(/"/g, '&quot;');

                return `
                    <tr id="bom-row-${b.id}">
                        <td>
                            <a href="${appUrl}/pages/boms/view.php?id=${b.id}" class="font-monospace fw-bold text-primary text-decoration-none" title="View Recipe">
                                ${b.bom_code}
                            </a>
                        </td>
                        <td>
                            <div class="fw-semibold text-dark">${b.product_name}</div>
                            <span class="text-muted small font-monospace">${b.product_sku}</span>
                        </td>
                        <td class="text-center"><span class="badge bg-light text-dark border">v${b.version}</span></td>
                        <td class="text-center fw-medium">${b.items_count} components</td>
                        <td class="text-end fw-medium">${yieldQty.toFixed(2)} <small class="text-muted">${b.product_unit || ''}</small></td>
                        <td class="text-end text-muted">$${totalCost.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                        <td class="text-end fw-bold text-dark">$${costPerUnit.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                        <td class="text-center">${statusBadge}</td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                <a href="${appUrl}/pages/boms/view.php?id=${b.id}" class="btn btn-outline-secondary" title="View Recipe Specifications">
                                    <i class="fa-solid fa-eye"></i>
                                </a>
                                <a href="${appUrl}/pages/boms/form.php?id=${b.id}" class="btn btn-outline-primary" title="Edit BOM">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </a>
                                <button type="button" class="btn btn-outline-secondary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false">
                                    <span class="visually-hidden">Toggle Actions</span>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm small">
                                    ${statusStr !== 'active' ? `
                                        <li>
                                            <a class="dropdown-item btn-status-bom text-success" href="#" data-id="${b.id}" data-status="active">
                                                <i class="fa-solid fa-check me-2"></i>Set as Active
                                            </a>
                                        </li>
                                    ` : ''}
                                    ${statusStr !== 'draft' ? `
                                        <li>
                                            <a class="dropdown-item btn-status-bom text-warning" href="#" data-id="${b.id}" data-status="draft">
                                                <i class="fa-solid fa-pen-ruler me-2"></i>Set as Draft
                                            </a>
                                        </li>
                                    ` : ''}
                                    ${statusStr !== 'archived' ? `
                                        <li>
                                            <a class="dropdown-item btn-status-bom text-muted" href="#" data-id="${b.id}" data-status="archived">
                                                <i class="fa-solid fa-box-archive me-2"></i>Archive Version
                                            </a>
                                        </li>
                                    ` : ''}
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <a class="dropdown-item btn-delete-bom text-danger" href="#" data-id="${b.id}" data-code="${safeCode}">
                                            <i class="fa-solid fa-trash-can me-2"></i>Delete BOM
                                        </a>
                                    </li>
                                </ul>
                            </div>
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
                        ${err.message || 'Failed to load bills of materials.'}
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
        paginationInfo.textContent = `Showing ${start} to ${end} of ${meta.total_items} BOMs`;

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
                    loadBoms(targetPage);
                }
            });
        });
    }

    function bindRowActions() {
        document.querySelectorAll('.btn-status-bom').forEach(function (btn) {
            btn.addEventListener('click', async function (e) {
                e.preventDefault();
                const id = this.dataset.id;
                const status = this.dataset.status;

                const formData = new FormData();
                formData.append('action', 'set_status');
                formData.append('id', id);
                formData.append('status', status);

                try {
                    const res = await App.request(`${appUrl}/api/boms.php`, {
                        method: 'POST',
                        body: formData
                    });
                    App.toast('success', (res && res.message) || (res && res.data && res.data.message) || 'Status updated.');
                    loadBoms(currentPage);
                } catch (err) {
                    App.toast('error', err.message || 'Failed to update status.');
                }
            });
        });

        document.querySelectorAll('.btn-delete-bom').forEach(function (btn) {
            btn.addEventListener('click', async function (e) {
                e.preventDefault();
                const id = this.dataset.id;
                const code = this.dataset.code;

                const confirmed = await App.confirm(
                    'Delete Bill of Materials',
                    `Are you sure you want to delete "${code}"? BOMs linked to production orders cannot be removed.`,
                    'Yes, Delete'
                );

                if (!confirmed) return;

                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('id', id);

                try {
                    const res = await App.request(`${appUrl}/api/boms.php`, {
                        method: 'POST',
                        body: formData
                    });
                    App.toast('success', (res && res.message) || (res && res.data && res.data.message) || 'BOM deleted.');
                    loadBoms(currentPage);
                } catch (err) {
                    App.toast('error', err.message || 'Failed to delete BOM.');
                }
            });
        });
    }

    if (searchInput) {
        searchInput.addEventListener('input', function () {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function () {
                loadBoms(1);
            }, 300);
        });
    }

    if (statusSelect) {
        statusSelect.addEventListener('change', function () {
            loadBoms(1);
        });
    }

    if (btnReset) {
        btnReset.addEventListener('click', function () {
            if (searchInput) searchInput.value = '';
            if (statusSelect) statusSelect.value = 'all';
            loadBoms(1);
        });
    }

    loadBoms(1);
});
</script>

<?php require_once __DIR__ . '/../../includes/layout_footer.php'; ?>
