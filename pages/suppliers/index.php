<?php
declare(strict_types=1);

$pageTitle = 'Suppliers Directory';

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
        <h4 class="fw-bold mb-1">Suppliers Directory</h4>
        <p class="text-muted small mb-0">Maintain verified vendor master records, commercial terms, tax identifiers, and procurement points of contact.</p>
    </div>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalSupplierCreate">
        <i class="fa-solid fa-plus me-1"></i> Add Supplier
    </button>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-body py-3">
        <div class="row g-2 align-items-center">
            <div class="col-12 col-md-6">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                    <input type="text" id="searchSupplier" class="form-control" placeholder="Search by company name, contact, email, or phone...">
                </div>
            </div>
            <div class="col-6 col-md-3">
                <select id="filterSupplierStatus" class="form-select form-select-sm">
                    <option value="all">All Statuses</option>
                    <option value="active">Active Vendors Only</option>
                    <option value="inactive">Inactive / Suspended</option>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <button type="button" id="btnResetSupplierFilters" class="btn btn-sm btn-outline-secondary w-100">
                    Reset Filter
                </button>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
        <span class="fw-semibold">Registered Suppliers</span>
        <span class="badge bg-light text-secondary border" id="supplierCountBadge">Loading...</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="suppliersTable">
                <thead>
                    <tr>
                        <th>Company Name</th>
                        <th>Contact Person</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>GST / Tax No</th>
                        <th class="text-center">Payment Terms</th>
                        <th class="text-center">Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="suppliersTableBody">
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                            Loading suppliers directory...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer bg-white py-2 d-flex align-items-center justify-content-between" id="paginationWrapper" style="display: none !important;">
        <span class="text-muted small" id="paginationInfo">Showing 0 of 0 suppliers</span>
        <nav aria-label="Suppliers pagination">
            <ul class="pagination pagination-sm mb-0" id="paginationList"></ul>
        </nav>
    </div>
</div>

<!-- Modal: Create Supplier -->
<div class="modal fade" id="modalSupplierCreate" tabindex="-1" aria-labelledby="modalSupplierCreateLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form id="formSupplierCreate" action="<?= e($appUrl) ?>/api/suppliers.php" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create">

                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="modalSupplierCreateLabel">Register New Supplier</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger py-2 small mb-3 form-error-alert d-none"></div>

                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label for="create_sup_company" class="form-label small fw-semibold text-secondary">Company Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="create_sup_company" name="company_name" placeholder="e.g. Apex Industrial Metals Ltd" required maxlength="150">
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="create_sup_contact" class="form-label small fw-semibold text-secondary">Primary Contact Person</label>
                            <input type="text" class="form-control" id="create_sup_contact" name="contact_person" placeholder="e.g. Johnathan Vance" maxlength="100">
                        </div>

                        <div class="col-12 col-md-6">
                            <label for="create_sup_email" class="form-label small fw-semibold text-secondary">Email Address</label>
                            <input type="email" class="form-control" id="create_sup_email" name="email" placeholder="orders@vendor.com" maxlength="191">
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="create_sup_phone" class="form-label small fw-semibold text-secondary">Phone Number</label>
                            <input type="text" class="form-control" id="create_sup_phone" name="phone" placeholder="+1 (555) 019-2834" maxlength="30">
                        </div>

                        <div class="col-12 col-md-6">
                            <label for="create_sup_gst" class="form-label small fw-semibold text-secondary">Tax ID / GST Number</label>
                            <input type="text" class="form-control" id="create_sup_gst" name="gst_no" placeholder="e.g. 27AAAAA0000A1Z5" maxlength="50">
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="create_sup_terms" class="form-label small fw-semibold text-secondary">Commercial Payment Terms (Days)</label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="create_sup_terms" name="payment_terms" min="0" max="365" value="30" required>
                                <span class="input-group-text bg-light small">Days</span>
                            </div>
                            <div class="form-text small">e.g. 30 for Net 30 days.</div>
                        </div>

                        <div class="col-12">
                            <label for="create_sup_address" class="form-label small fw-semibold text-secondary">Corporate / Warehouse Address</label>
                            <textarea class="form-control" id="create_sup_address" name="address" rows="2" placeholder="Street address, city, state, postal code..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary">Save Supplier</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Edit Supplier -->
<div class="modal fade" id="modalSupplierEdit" tabindex="-1" aria-labelledby="modalSupplierEditLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form id="formSupplierEdit" action="<?= e($appUrl) ?>/api/suppliers.php" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="edit_sup_id">

                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="modalSupplierEditLabel">Edit Supplier Profile</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger py-2 small mb-3 form-error-alert d-none"></div>

                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label for="edit_sup_company" class="form-label small fw-semibold text-secondary">Company Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_sup_company" name="company_name" required maxlength="150">
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="edit_sup_contact" class="form-label small fw-semibold text-secondary">Primary Contact Person</label>
                            <input type="text" class="form-control" id="edit_sup_contact" name="contact_person" maxlength="100">
                        </div>

                        <div class="col-12 col-md-6">
                            <label for="edit_sup_email" class="form-label small fw-semibold text-secondary">Email Address</label>
                            <input type="email" class="form-control" id="edit_sup_email" name="email" maxlength="191">
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="edit_sup_phone" class="form-label small fw-semibold text-secondary">Phone Number</label>
                            <input type="text" class="form-control" id="edit_sup_phone" name="phone" maxlength="30">
                        </div>

                        <div class="col-12 col-md-4">
                            <label for="edit_sup_gst" class="form-label small fw-semibold text-secondary">Tax ID / GST Number</label>
                            <input type="text" class="form-control" id="edit_sup_gst" name="gst_no" maxlength="50">
                        </div>
                        <div class="col-12 col-md-4">
                            <label for="edit_sup_terms" class="form-label small fw-semibold text-secondary">Payment Terms (Days)</label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="edit_sup_terms" name="payment_terms" min="0" max="365" required>
                                <span class="input-group-text bg-light small">Days</span>
                            </div>
                        </div>
                        <div class="col-12 col-md-4">
                            <label for="edit_sup_status" class="form-label small fw-semibold text-secondary">Account Status</label>
                            <select class="form-select" id="edit_sup_status" name="is_active">
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>

                        <div class="col-12">
                            <label for="edit_sup_address" class="form-label small fw-semibold text-secondary">Corporate / Warehouse Address</label>
                            <textarea class="form-control" id="edit_sup_address" name="address" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary">Update Supplier</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const appUrl = '<?= e($appUrl) ?>';
    const tbody = document.getElementById('suppliersTableBody');
    const searchInput = document.getElementById('searchSupplier');
    const statusSelect = document.getElementById('filterSupplierStatus');
    const btnReset = document.getElementById('btnResetSupplierFilters');
    const badgeCount = document.getElementById('supplierCountBadge');

    const paginationWrapper = document.getElementById('paginationWrapper');
    const paginationInfo = document.getElementById('paginationInfo');
    const paginationList = document.getElementById('paginationList');

    let currentPage = 1;
    let debounceTimer = null;

    async function loadSuppliers(page = 1) {
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
            const res = await App.request(`${appUrl}/api/suppliers.php?${params.toString()}`);
            const items = res.data || [];
            const meta = res.meta || { page: 1, per_page: 15, total_items: items.length, total_pages: 1 };

            if (badgeCount) {
                badgeCount.textContent = `${meta.total_items} suppliers`;
            }

            if (!items.length) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <i class="fa-solid fa-truck d-block mb-2" style="font-size: 2rem;"></i>
                            No suppliers found matching your query.
                        </td>
                    </tr>
                `;
                if (paginationWrapper) paginationWrapper.style.setProperty('display', 'none', 'important');
                return;
            }

            tbody.innerHTML = items.map(function (s) {
                const isActive = parseInt(s.is_active || 1) === 1;
                const terms = parseInt(s.payment_terms || 30);
                const safeName = (s.name || '').replace(/"/g, '&quot;');
                const safeContact = (s.contact_person || '').replace(/"/g, '&quot;');
                const safeEmail = (s.email || '').replace(/"/g, '&quot;');
                const safePhone = (s.phone || '').replace(/"/g, '&quot;');
                const safeAddress = (s.address || '').replace(/"/g, '&quot;');
                const safeGst = (s.gst_no || '').replace(/"/g, '&quot;');

                return `
                    <tr id="supplier-row-${s.id}">
                        <td>
                            <div class="fw-semibold text-dark">${s.name}</div>
                            ${s.address ? `<div class="text-muted small text-truncate" style="max-width: 250px;">${s.address}</div>` : ''}
                        </td>
                        <td>${s.contact_person ? s.contact_person : '<span class="text-muted">&mdash;</span>'}</td>
                        <td>
                            ${s.phone ? `<a href="tel:${s.phone}" class="text-decoration-none text-dark small">${s.phone}</a>` : '<span class="text-muted">&mdash;</span>'}
                        </td>
                        <td>
                            ${s.email ? `<a href="mailto:${s.email}" class="text-decoration-none text-primary small">${s.email}</a>` : '<span class="text-muted">&mdash;</span>'}
                        </td>
                        <td><span class="font-monospace small text-secondary">${s.gst_no || '&mdash;'}</span></td>
                        <td class="text-center">
                            <span class="badge bg-light text-dark border">Net ${terms} Days</span>
                        </td>
                        <td class="text-center">
                            <span class="badge ${isActive ? 'bg-success-subtle text-success border border-success' : 'bg-secondary-subtle text-secondary border'}">
                                ${isActive ? 'Active' : 'Inactive'}
                            </span>
                        </td>
                        <td class="text-end">
                            <button type="button" 
                                    class="btn btn-sm btn-outline-primary me-1 btn-edit-supplier" 
                                    data-id="${s.id}"
                                    data-company="${safeName}"
                                    data-contact="${safeContact}"
                                    data-email="${safeEmail}"
                                    data-phone="${safePhone}"
                                    data-address="${safeAddress}"
                                    data-gst="${safeGst}"
                                    data-terms="${terms}"
                                    data-is-active="${s.is_active}"
                                    title="Edit Supplier">
                                <i class="fa-solid fa-pen-to-square"></i>
                            </button>
                            <button type="button" 
                                    class="btn btn-sm btn-outline-danger btn-delete-supplier" 
                                    data-id="${s.id}"
                                    data-name="${safeName}"
                                    title="Delete Supplier">
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
                        ${err.message || 'Failed to load suppliers.'}
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
        paginationInfo.textContent = `Showing ${start} to ${end} of ${meta.total_items} suppliers`;

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
                    loadSuppliers(targetPage);
                }
            });
        });
    }

    function bindRowActions() {
        document.querySelectorAll('.btn-edit-supplier').forEach(function (btn) {
            btn.addEventListener('click', function () {
                document.getElementById('edit_sup_id').value = this.dataset.id;
                document.getElementById('edit_sup_company').value = this.dataset.company;
                document.getElementById('edit_sup_contact').value = this.dataset.contact || '';
                document.getElementById('edit_sup_email').value = this.dataset.email || '';
                document.getElementById('edit_sup_phone').value = this.dataset.phone || '';
                document.getElementById('edit_sup_address').value = this.dataset.address || '';
                document.getElementById('edit_sup_gst').value = this.dataset.gst || '';
                document.getElementById('edit_sup_terms').value = this.dataset.terms || '30';
                document.getElementById('edit_sup_status').value = this.dataset.isActive;

                const modal = new bootstrap.Modal(document.getElementById('modalSupplierEdit'));
                modal.show();
            });
        });

        document.querySelectorAll('.btn-delete-supplier').forEach(function (btn) {
            btn.addEventListener('click', async function () {
                const id = this.dataset.id;
                const name = this.dataset.name;

                const confirmed = await App.confirm(
                    'Delete Supplier',
                    `Are you sure you want to delete supplier "${name}"? Vendors with associated purchase orders cannot be removed.`,
                    'Yes, Delete'
                );

                if (!confirmed) return;

                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('id', id);

                try {
                    const res = await App.request(`${appUrl}/api/suppliers.php`, {
                        method: 'POST',
                        body: formData
                    });
                    App.toast('success', (res && res.message) || (res && res.data && res.data.message) || 'Supplier deleted.');
                    loadSuppliers(currentPage);
                } catch (err) {
                    App.toast('error', err.message || 'Failed to delete supplier.');
                }
            });
        });
    }

    if (searchInput) {
        searchInput.addEventListener('input', function () {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function () {
                loadSuppliers(1);
            }, 300);
        });
    }

    if (statusSelect) {
        statusSelect.addEventListener('change', function () {
            loadSuppliers(1);
        });
    }

    if (btnReset) {
        btnReset.addEventListener('click', function () {
            if (searchInput) searchInput.value = '';
            if (statusSelect) statusSelect.value = 'all';
            loadSuppliers(1);
        });
    }

    App.bindAjaxForm('formSupplierCreate', function () {
        loadSuppliers(1);
    });

    App.bindAjaxForm('formSupplierEdit', function () {
        loadSuppliers(currentPage);
    });

    loadSuppliers(1);
});
</script>

<?php require_once __DIR__ . '/../../includes/layout_footer.php'; ?>
