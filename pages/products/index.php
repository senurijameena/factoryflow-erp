<?php
declare(strict_types=1);

$pageTitle = 'Products Catalog';

require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/rbac.php';

require_role([ROLE_ADMIN, ROLE_MANAGER]);

$pdo = Database::getConnection();
$appUrl = rtrim((string)(defined('APP_URL') ? APP_URL : Config::get('APP_URL', 'http://localhost/factoryflow')), '/');

$categoriesStmt = $pdo->query("SELECT id, name FROM categories WHERE type = 'product' ORDER BY name ASC");
$productCategories = $categoriesStmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../../includes/layout_header.php';
?>

<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1">Finished Products</h4>
        <p class="text-muted small mb-0">Maintain master specifications, standard manufacturing costs, and customer price sheets.</p>
    </div>
    <a href="<?= e($appUrl) ?>/pages/products/form.php" class="btn btn-primary">
        <i class="fa-solid fa-plus me-1"></i> Add Product
    </a>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-body py-3">
        <div class="row g-2 align-items-center">
            <div class="col-12 col-md-4">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                    <input type="text" id="searchProduct" class="form-control" placeholder="Search by SKU, barcode, or name...">
                </div>
            </div>
            <div class="col-6 col-md-3">
                <select id="filterCategory" class="form-select form-select-sm">
                    <option value="">All Categories</option>
                    <?php foreach ($productCategories as $cat): ?>
                        <option value="<?= (int)$cat['id'] ?>"><?= e($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <select id="filterStockStatus" class="form-select form-select-sm">
                    <option value="all">All Stock Levels</option>
                    <option value="healthy">Adequate Stock</option>
                    <option value="low">Low Stock Alert</option>
                    <option value="out">Out of Stock</option>
                </select>
            </div>
            <div class="col-6 col-md-2 d-flex align-items-center">
                <div class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox" role="switch" id="filterShowArchived">
                    <label class="form-check-label small text-secondary user-select-none" for="filterShowArchived">Show Archived</label>
                </div>
            </div>
            <div class="col-6 col-md-1">
                <button type="button" id="btnResetFilters" class="btn btn-sm btn-outline-secondary w-100" title="Reset Filters">
                    Reset
                </button>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
        <span class="fw-semibold">All Products</span>
        <span class="badge bg-light text-secondary border" id="productCountBadge">Loading...</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="productsTable">
                <thead>
                    <tr>
                        <th style="width: 50px;"></th>
                        <th>SKU / Product</th>
                        <th>Category</th>
                        <th class="text-end">Cost Price</th>
                        <th class="text-end">Sale Price</th>
                        <th class="text-end">Stock Level</th>
                        <th class="text-center">Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="productsTableBody">
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                            Loading products catalog...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer bg-white py-2 d-flex align-items-center justify-content-between" id="paginationWrapper" style="display: none !important;">
        <span class="text-muted small" id="paginationInfo">Showing 0 of 0 products</span>
        <nav aria-label="Products pagination">
            <ul class="pagination pagination-sm mb-0" id="paginationList"></ul>
        </nav>
    </div>
</div>

<div class="modal fade" id="modalProductView" tabindex="-1" aria-labelledby="modalProductViewLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="modalProductViewLabel">Product Specifications</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="modalProductViewBody">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Close</button>
                <a href="#" id="modalProductEditLink" class="btn btn-sm btn-primary">Edit Product</a>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const appUrl = '<?= e($appUrl) ?>';
    const tbody = document.getElementById('productsTableBody');
    const searchInput = document.getElementById('searchProduct');
    const categorySelect = document.getElementById('filterCategory');
    const stockStatusSelect = document.getElementById('filterStockStatus');
    const showArchivedToggle = document.getElementById('filterShowArchived');
    const btnReset = document.getElementById('btnResetFilters');
    const badgeCount = document.getElementById('productCountBadge');
    const paginationWrapper = document.getElementById('paginationWrapper');
    const paginationInfo = document.getElementById('paginationInfo');
    const paginationList = document.getElementById('paginationList');

    let debounceTimer = null;
    let currentPage = 1;

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    async function loadProducts(page = 1) {
        currentPage = page;
        const search = searchInput ? searchInput.value.trim() : '';
        const categoryId = categorySelect ? categorySelect.value : '';
        const stockStatus = stockStatusSelect ? stockStatusSelect.value : 'all';
        const showArchived = showArchivedToggle && showArchivedToggle.checked;
        const status = showArchived ? 'all' : 'active';

        const params = new URLSearchParams({
            action: 'list',
            page: currentPage,
            per_page: 25,
            search: search,
            category_id: categoryId,
            stock_status: stockStatus,
            status: status
        });

        try {
            const res = await App.request(`${appUrl}/api/products.php?${params.toString()}`);
            const products = res.data || [];
            const meta = res.meta || {};
            const totalItems = meta.total_items ?? products.length;
            const totalPages = meta.total_pages ?? 1;

            if (badgeCount) {
                badgeCount.textContent = `${totalItems} products`;
            }

            if (!products.length) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <i class="fa-solid fa-box-open d-block mb-2" style="font-size: 2.25rem;"></i>
                            No products found matching your filter criteria.
                        </td>
                    </tr>
                `;
                if (paginationWrapper) paginationWrapper.setAttribute('style', 'display: none !important');
                return;
            }

            tbody.innerHTML = products.map(function (p) {
                const cost = parseFloat(p.cost_price || 0);
                const sale = parseFloat(p.sale_price || 0);
                const stock = parseFloat(p.current_stock || 0);
                const minStock = parseFloat(p.reorder_level || 0);
                const margin = sale > 0 ? ((sale - cost) / sale) * 100 : 0.0;
                const isOut = stock <= 0;
                const isLow = stock <= minStock && stock > 0;
                const isActive = parseInt(p.is_active || 0) === 1;

                let imageHtml = '';
                if (p.image) {
                    const cleanPath = p.image.replace(/^\/+/, '');
                    imageHtml = `<img src="${appUrl}/${cleanPath}" alt="${escapeHtml(p.name)}" class="rounded border" style="width: 42px; height: 42px; object-fit: cover;">`;
                } else {
                    imageHtml = `<div class="rounded border bg-light d-flex align-items-center justify-content-center text-muted" style="width: 42px; height: 42px;"><i class="fa-solid fa-image"></i></div>`;
                }

                let statusBadge = '';
                if (isActive) {
                    if (isOut) {
                        statusBadge = '<span class="badge bg-danger-subtle text-danger border border-danger">Out of Stock</span>';
                    } else if (isLow) {
                        statusBadge = '<span class="badge bg-warning-subtle text-warning border border-warning">Low Stock</span>';
                    } else {
                        statusBadge = '<span class="badge bg-success-subtle text-success border border-success">Active</span>';
                    }
                } else {
                    statusBadge = '<span class="badge bg-secondary-subtle text-secondary border">Archived</span>';
                }

                const barcodeHtml = p.barcode 
                    ? `<small class="text-muted d-block"><i class="fa-solid fa-barcode me-1"></i>${escapeHtml(p.barcode)}</small>` 
                    : '';

                const marginClass = margin >= 25 ? 'text-success' : (margin > 0 ? 'text-secondary' : 'text-danger');
                const stockClass = isOut ? 'text-danger' : (isLow ? 'text-warning' : 'text-dark');

                return `
                    <tr id="product-row-${p.id}">
                        <td>${imageHtml}</td>
                        <td>
                            <div class="fw-bold text-primary mb-0 font-monospace">${escapeHtml(p.sku)}</div>
                            <div class="fw-medium text-dark">${escapeHtml(p.name)}</div>
                            ${barcodeHtml}
                        </td>
                        <td>
                            <span class="badge bg-light text-secondary border">${escapeHtml(p.category_name || 'Unassigned')}</span>
                        </td>
                        <td class="text-end text-muted font-monospace">$${cost.toFixed(2)}</td>
                        <td class="text-end fw-bold font-monospace">
                            $${sale.toFixed(2)}
                            <div class="small fw-normal ${marginClass}">
                                ${margin.toFixed(1)}% margin
                            </div>
                        </td>
                        <td class="text-end">
                            <div class="fw-bold font-monospace ${stockClass}">
                                ${stock.toFixed(2)} ${escapeHtml(p.unit_symbol || '')}
                            </div>
                            <small class="text-muted">Min: ${minStock.toFixed(0)} ${escapeHtml(p.unit_symbol || '')}</small>
                        </td>
                        <td class="text-center">${statusBadge}</td>
                        <td class="text-end">
                            <button type="button" 
                                    class="btn btn-sm btn-outline-info me-1 btn-view-product" 
                                    data-id="${p.id}"
                                    title="View Details">
                                <i class="fa-solid fa-eye"></i>
                            </button>
                            <a href="${appUrl}/pages/products/form.php?id=${p.id}" 
                               class="btn btn-sm btn-outline-primary me-1" 
                               title="Edit Product">
                                <i class="fa-solid fa-pen-to-square"></i>
                            </a>
                            <button type="button" 
                                    class="btn btn-sm btn-outline-danger btn-delete-product" 
                                    data-id="${p.id}"
                                    data-name="${escapeHtml(p.name)}"
                                    title="Delete Product">
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
                    <td colspan="8" class="text-center py-4 text-danger">
                        ${escapeHtml(err.message || 'Failed to load products.')}
                    </td>
                </tr>
            `;
        }
    }

    window.loadProducts = loadProducts;

    function renderPagination(meta) {
        if (!paginationWrapper || !paginationList) return;

        const totalPages = meta.total_pages || 1;
        const totalItems = meta.total_items || 0;
        const page = meta.page || 1;

        if (totalPages <= 1) {
            paginationWrapper.setAttribute('style', 'display: none !important');
            return;
        }

        paginationWrapper.removeAttribute('style');
        if (paginationInfo) {
            paginationInfo.textContent = `Page ${page} of ${totalPages} (${totalItems} products total)`;
        }

        let pagesHtml = '';
        pagesHtml += `
            <li class="page-item ${page <= 1 ? 'disabled' : ''}">
                <button class="page-link" type="button" data-page="${page - 1}">Previous</button>
            </li>
        `;

        for (let i = 1; i <= totalPages; i++) {
            if (i === 1 || i === totalPages || (i >= page - 2 && i <= page + 2)) {
                pagesHtml += `
                    <li class="page-item ${i === page ? 'active' : ''}">
                        <button class="page-link" type="button" data-page="${i}">${i}</button>
                    </li>
                `;
            } else if (i === page - 3 || i === page + 3) {
                pagesHtml += `<li class="page-item disabled"><span class="page-link">&hellip;</span></li>`;
            }
        }

        pagesHtml += `
            <li class="page-item ${page >= totalPages ? 'disabled' : ''}">
                <button class="page-link" type="button" data-page="${page + 1}">Next</button>
            </li>
        `;

        paginationList.innerHTML = pagesHtml;

        paginationList.querySelectorAll('button[data-page]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const targetPage = parseInt(this.dataset.page);
                if (targetPage > 0 && targetPage <= totalPages && targetPage !== currentPage) {
                    loadProducts(targetPage);
                }
            });
        });
    }

    function bindRowActions() {
        document.querySelectorAll('.btn-view-product').forEach(function (btn) {
            btn.addEventListener('click', async function () {
                const id = this.dataset.id;
                const modalEl = document.getElementById('modalProductView');
                const bodyEl = document.getElementById('modalProductViewBody');
                const editLink = document.getElementById('modalProductEditLink');

                editLink.href = `${appUrl}/pages/products/form.php?id=${id}`;
                bodyEl.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></div>';

                const modal = new bootstrap.Modal(modalEl);
                modal.show();

                try {
                    const res = await App.request(`${appUrl}/api/products.php?action=get&id=${id}`);
                    const p = res.data;

                    let imgHtml = '';
                    if (p.image) {
                        const cleanPath = p.image.replace(/^\/+/, '');
                        imgHtml = `<img src="${appUrl}/${cleanPath}" class="img-fluid rounded border shadow-sm" style="max-height: 220px; object-fit: contain;">`;
                    } else {
                        imgHtml = `<div class="bg-light rounded border d-flex align-items-center justify-content-center text-muted" style="height: 180px;"><i class="fa-solid fa-image fa-3x"></i></div>`;
                    }

                    bodyEl.innerHTML = `
                        <div class="row g-4">
                            <div class="col-12 col-md-4 text-center">
                                ${imgHtml}
                                <div class="mt-3">
                                    <span class="badge ${parseInt(p.is_active) === 1 ? 'bg-success-subtle text-success border border-success' : 'bg-secondary-subtle text-secondary border'}">
                                        ${parseInt(p.is_active) === 1 ? 'Active Product' : 'Archived'}
                                    </span>
                                </div>
                            </div>
                            <div class="col-12 col-md-8">
                                <div class="d-flex align-items-center justify-content-between mb-1">
                                    <span class="badge bg-primary-subtle text-primary font-monospace">${escapeHtml(p.sku)}</span>
                                    <span class="text-muted small">${escapeHtml(p.category_name)}</span>
                                </div>
                                <h4 class="fw-bold text-dark mb-3">${escapeHtml(p.name)}</h4>

                                <div class="row g-2 mb-3">
                                    <div class="col-6 col-sm-4">
                                        <div class="p-2 border rounded bg-light">
                                            <div class="text-muted small">Sale Price</div>
                                            <div class="fw-bold text-dark font-monospace">$${parseFloat(p.sale_price).toFixed(2)}</div>
                                        </div>
                                    </div>
                                    <div class="col-6 col-sm-4">
                                        <div class="p-2 border rounded bg-light">
                                            <div class="text-muted small">Unit Cost</div>
                                            <div class="fw-bold text-dark font-monospace">$${parseFloat(p.cost_price).toFixed(2)}</div>
                                        </div>
                                    </div>
                                    <div class="col-6 col-sm-4">
                                        <div class="p-2 border rounded bg-light">
                                            <div class="text-muted small">Current Stock</div>
                                            <div class="fw-bold text-primary font-monospace">${parseFloat(p.current_stock).toFixed(2)} ${escapeHtml(p.unit_symbol)}</div>
                                        </div>
                                    </div>
                                    <div class="col-6 col-sm-4">
                                        <div class="p-2 border rounded bg-light">
                                            <div class="text-muted small">Min Reorder Level</div>
                                            <div class="fw-bold text-dark font-monospace">${parseFloat(p.reorder_level).toFixed(2)} ${escapeHtml(p.unit_symbol)}</div>
                                        </div>
                                    </div>
                                    <div class="col-6 col-sm-4">
                                        <div class="p-2 border rounded bg-light">
                                            <div class="text-muted small">Lead Time</div>
                                            <div class="fw-bold text-dark">${p.lead_time_days || 0} days</div>
                                        </div>
                                    </div>
                                    <div class="col-6 col-sm-4">
                                        <div class="p-2 border rounded bg-light">
                                            <div class="text-muted small">Barcode</div>
                                            <div class="fw-bold text-dark font-monospace">${escapeHtml(p.barcode) || '&mdash;'}</div>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <div class="fw-semibold small text-secondary mb-1">Description / Notes</div>
                                    <p class="text-muted small mb-0">${p.description ? escapeHtml(p.description) : 'No technical description provided.'}</p>
                                </div>

                                <div class="border-top pt-3 d-flex gap-3 text-muted small">
                                    <div><i class="fa-solid fa-layer-group text-primary me-1"></i> Active BOMs: <strong>${p.active_boms_count || 0}</strong></div>
                                    <div><i class="fa-solid fa-gears text-primary me-1"></i> Active Orders: <strong>${p.active_production_orders || 0}</strong></div>
                                </div>
                            </div>
                        </div>
                    `;
                } catch (err) {
                    bodyEl.innerHTML = `<div class="alert alert-danger py-2 small">${escapeHtml(err.message || 'Failed to load product specifications.')}</div>`;
                }
            });
        });

        document.querySelectorAll('.btn-delete-product').forEach(function (btn) {
            btn.addEventListener('click', async function () {
                const id = this.dataset.id;
                const name = this.dataset.name;

                const confirmed = await App.confirm(
                    'Delete Product',
                    `Are you sure you want to delete or archive "${name}"?`,
                    'Yes, Proceed'
                );

                if (!confirmed) return;

                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('id', id);

                try {
                    const res = await App.request(`${appUrl}/api/products.php`, {
                        method: 'POST',
                        body: formData
                    });
                    App.toast('success', (res && res.message) || (res && res.data && res.data.message) || 'Product processed successfully.');
                    loadProducts(currentPage);
                } catch (err) {
                    App.toast('error', err.message || 'Failed to delete product.');
                }
            });
        });
    }

    if (searchInput) {
        searchInput.addEventListener('input', function () {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function () {
                loadProducts(1);
            }, 300);
        });
    }

    if (categorySelect) {
        categorySelect.addEventListener('change', function () {
            loadProducts(1);
        });
    }

    if (stockStatusSelect) {
        stockStatusSelect.addEventListener('change', function () {
            loadProducts(1);
        });
    }

    if (showArchivedToggle) {
        showArchivedToggle.addEventListener('change', function () {
            loadProducts(1);
        });
    }

    if (btnReset) {
        btnReset.addEventListener('click', function () {
            if (searchInput) searchInput.value = '';
            if (categorySelect) categorySelect.value = '';
            if (stockStatusSelect) stockStatusSelect.value = 'all';
            if (showArchivedToggle) showArchivedToggle.checked = false;
            loadProducts(1);
        });
    }

    loadProducts(1);
});
</script>

<?php require_once __DIR__ . '/../../includes/layout_footer.php'; ?>
