<?php
declare(strict_types=1);

$pageTitle = 'Customers Directory';

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
        <h4 class="fw-bold mb-1">Customers Directory</h4>
        <p class="text-muted small mb-0">Manage customer accounts, delivery destinations, credit allocations, and payment terms for sales fulfillment.</p>
    </div>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCustomerCreate">
        <i class="fa-solid fa-plus me-1"></i> Add Customer
    </button>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-body py-3">
        <div class="row g-2 align-items-center">
            <div class="col-12 col-md-6">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                    <input type="text" id="searchCustomer" class="form-control" placeholder="Search by customer name, company, email, or phone...">
                </div>
            </div>
            <div class="col-6 col-md-3">
                <select id="filterCustomerStatus" class="form-select form-select-sm">
                    <option value="all">All Statuses</option>
                    <option value="active">Active Accounts Only</option>
                    <option value="inactive">Inactive / Suspended</option>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <button type="button" id="btnResetCustomerFilters" class="btn btn-sm btn-outline-secondary w-100">
                    Reset Filter
                </button>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
        <span class="fw-semibold">Registered Customers</span>
        <span class="badge bg-light text-secondary border" id="customerCountBadge">Loading...</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="customersTable">
                <thead>
                    <tr>
                        <th>Customer / Contact</th>
                        <th>Company</th>
                        <th>Email & Phone</th>
                        <th>Shipping Address</th>
                        <th class="text-end">Credit Limit</th>
                        <th class="text-center">Payment Terms</th>
                        <th class="text-center">Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="customersTableBody">
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                            Loading customers directory...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer bg-white py-2 d-flex align-items-center justify-content-between" id="paginationWrapper" style="display: none !important;">
        <span class="text-muted small" id="paginationInfo">Showing 0 of 0 customers</span>
        <nav aria-label="Customers pagination">
            <ul class="pagination pagination-sm mb-0" id="paginationList"></ul>
        </nav>
    </div>
</div>

<!-- Modal: Create Customer -->
<div class="modal fade" id="modalCustomerCreate" tabindex="-1" aria-labelledby="modalCustomerCreateLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form id="formCustomerCreate" action="<?= e($appUrl) ?>/api/customers.php" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create">

                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="modalCustomerCreateLabel">Add New Customer</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger py-2 small mb-3 form-error-alert d-none"></div>

                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label for="create_cust_name" class="form-label small fw-semibold text-secondary">Customer Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="create_cust_name" name="name" placeholder="e.g. Robert Hawkins" required maxlength="150">
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="create_cust_company" class="form-label small fw-semibold text-secondary">Company Name</label>
                            <input type="text" class="form-control" id="create_cust_company" name="company" placeholder="e.g. Pacific Coast Distribution Inc" maxlength="150">
                        </div>

                        <div class="col-12 col-md-6">
                            <label for="create_cust_contact" class="form-label small fw-semibold text-secondary">Point of Contact Person</label>
                            <input type="text" class="form-control" id="create_cust_contact" name="contact_person" placeholder="e.g. Sarah Miller (Purchasing Mgr)" maxlength="100">
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="create_cust_tax" class="form-label small fw-semibold text-secondary">Tax ID / GST Number</label>
                            <input type="text" class="form-control" id="create_cust_tax" name="tax_id" placeholder="e.g. GSTIN / EIN" maxlength="50">
                        </div>

                        <div class="col-12 col-md-6">
                            <label for="create_cust_email" class="form-label small fw-semibold text-secondary">Email Address</label>
                            <input type="email" class="form-control" id="create_cust_email" name="email" placeholder="billing@clientcorp.com" maxlength="191">
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="create_cust_phone" class="form-label small fw-semibold text-secondary">Phone Number</label>
                            <input type="text" class="form-control" id="create_cust_phone" name="phone" placeholder="+1 (555) 349-9201" maxlength="30">
                        </div>

                        <div class="col-12 col-md-6">
                            <label for="create_cust_credit" class="form-label small fw-semibold text-secondary">Credit Limit ($)</label>
                            <input type="number" step="0.01" min="0" class="form-control" id="create_cust_credit" name="credit_limit" placeholder="0.00" value="0.00">
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="create_cust_terms" class="form-label small fw-semibold text-secondary">Payment Terms (Days)</label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="create_cust_terms" name="payment_terms" min="0" max="365" value="30" required>
                                <span class="input-group-text bg-light small">Days</span>
                            </div>
                        </div>

                        <div class="col-12 col-md-6">
                            <label for="create_cust_billing" class="form-label small fw-semibold text-secondary">Billing Address</label>
                            <textarea class="form-control" id="create_cust_billing" name="billing_address" rows="2" placeholder="Accounts payable billing address..."></textarea>
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="create_cust_shipping" class="form-label small fw-semibold text-secondary">Shipping / Delivery Destination</label>
                            <textarea class="form-control" id="create_cust_shipping" name="shipping_address" rows="2" placeholder="Warehouse address, dock instructions, city, state..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary">Save Customer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Edit Customer -->
<div class="modal fade" id="modalCustomerEdit" tabindex="-1" aria-labelledby="modalCustomerEditLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form id="formCustomerEdit" action="<?= e($appUrl) ?>/api/customers.php" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="edit_cust_id">

                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="modalCustomerEditLabel">Edit Customer Profile</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger py-2 small mb-3 form-error-alert d-none"></div>

                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label for="edit_cust_name" class="form-label small fw-semibold text-secondary">Customer Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_cust_name" name="name" required maxlength="150">
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="edit_cust_company" class="form-label small fw-semibold text-secondary">Company Name</label>
                            <input type="text" class="form-control" id="edit_cust_company" name="company" maxlength="150">
                        </div>

                        <div class="col-12 col-md-6">
                            <label for="edit_cust_contact" class="form-label small fw-semibold text-secondary">Point of Contact</label>
                            <input type="text" class="form-control" id="edit_cust_contact" name="contact_person" maxlength="100">
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="edit_cust_tax" class="form-label small fw-semibold text-secondary">Tax ID / GST Number</label>
                            <input type="text" class="form-control" id="edit_cust_tax" name="tax_id" maxlength="50">
                        </div>

                        <div class="col-12 col-md-6">
                            <label for="edit_cust_email" class="form-label small fw-semibold text-secondary">Email Address</label>
                            <input type="email" class="form-control" id="edit_cust_email" name="email" maxlength="191">
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="edit_cust_phone" class="form-label small fw-semibold text-secondary">Phone Number</label>
                            <input type="text" class="form-control" id="edit_cust_phone" name="phone" maxlength="30">
                        </div>

                        <div class="col-12 col-md-4">
                            <label for="edit_cust_credit" class="form-label small fw-semibold text-secondary">Credit Limit ($)</label>
                            <input type="number" step="0.01" min="0" class="form-control" id="edit_cust_credit" name="credit_limit">
                        </div>
                        <div class="col-12 col-md-4">
                            <label for="edit_cust_terms" class="form-label small fw-semibold text-secondary">Payment Terms (Days)</label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="edit_cust_terms" name="payment_terms" min="0" max="365" required>
                                <span class="input-group-text bg-light small">Days</span>
                            </div>
                        </div>
                        <div class="col-12 col-md-4">
                            <label for="edit_cust_status" class="form-label small fw-semibold text-secondary">Account Status</label>
                            <select class="form-select" id="edit_cust_status" name="is_active">
                                <option value="1">Active</option>
                                <option value="0">Inactive / Suspended</option>
                            </select>
                        </div>

                        <div class="col-12 col-md-6">
                            <label for="edit_cust_billing" class="form-label small fw-semibold text-secondary">Billing Address</label>
                            <textarea class="form-control" id="edit_cust_billing" name="billing_address" rows="2"></textarea>
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="edit_cust_shipping" class="form-label small fw-semibold text-secondary">Shipping / Delivery Destination</label>
                            <textarea class="form-control" id="edit_cust_shipping" name="shipping_address" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary">Update Customer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const appUrl = '<?= e($appUrl) ?>';
    const tbody = document.getElementById('customersTableBody');
    const searchInput = document.getElementById('searchCustomer');
    const statusSelect = document.getElementById('filterCustomerStatus');
    const btnReset = document.getElementById('btnResetCustomerFilters');
    const badgeCount = document.getElementById('customerCountBadge');

    const paginationWrapper = document.getElementById('paginationWrapper');
    const paginationInfo = document.getElementById('paginationInfo');
    const paginationList = document.getElementById('paginationList');

    let currentPage = 1;
    let debounceTimer = null;

    async function loadCustomers(page = 1) {
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
            const res = await App.request(`${appUrl}/api/customers.php?${params.toString()}`);
            const items = res.data || [];
            const meta = res.meta || { page: 1, per_page: 15, total_items: items.length, total_pages: 1 };

            if (badgeCount) {
                badgeCount.textContent = `${meta.total_items} customers`;
            }

            if (!items.length) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <i class="fa-solid fa-users d-block mb-2" style="font-size: 2rem;"></i>
                            No customers found matching your query.
                        </td>
                    </tr>
                `;
                if (paginationWrapper) paginationWrapper.style.setProperty('display', 'none', 'important');
                return;
            }

            tbody.innerHTML = items.map(function (c) {
                const isActive = parseInt(c.is_active || 1) === 1;
                const creditLimit = parseFloat(c.credit_limit || 0);
                const terms = parseInt(c.payment_terms || 30);

                const safeName = (c.name || '').replace(/"/g, '&quot;');
                const safeCompany = (c.company || '').replace(/"/g, '&quot;');
                const safeContact = (c.contact_person || '').replace(/"/g, '&quot;');
                const safeEmail = (c.email || '').replace(/"/g, '&quot;');
                const safePhone = (c.phone || '').replace(/"/g, '&quot;');
                const safeAddress = (c.address || '').replace(/"/g, '&quot;');
                const safeShipping = (c.shipping_address || '').replace(/"/g, '&quot;');
                const safeGst = (c.gst_no || '').replace(/"/g, '&quot;');

                return `
                    <tr id="customer-row-${c.id}">
                        <td>
                            <div class="fw-semibold text-dark">${c.name}</div>
                            ${c.contact_person ? `<div class="text-muted small">${c.contact_person}</div>` : ''}
                        </td>
                        <td>
                            ${c.company ? `<span class="fw-medium text-secondary">${c.company}</span>` : '<span class="text-muted">&mdash;</span>'}
                        </td>
                        <td>
                            <div>${c.email ? `<a href="mailto:${c.email}" class="text-decoration-none text-primary small">${c.email}</a>` : '<span class="text-muted small">No email</span>'}</div>
                            <div>${c.phone ? `<a href="tel:${c.phone}" class="text-decoration-none text-dark small">${c.phone}</a>` : ''}</div>
                        </td>
                        <td>
                            ${c.shipping_address 
                                ? `<div class="text-muted small text-truncate" style="max-width: 200px;" title="${safeShipping}">${c.shipping_address}</div>` 
                                : (c.address ? `<div class="text-muted small text-truncate" style="max-width: 200px;" title="${safeAddress}">${c.address}</div>` : '<span class="text-muted">&mdash;</span>')}
                        </td>
                        <td class="text-end fw-semibold text-dark">$${creditLimit.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
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
                                    class="btn btn-sm btn-outline-primary me-1 btn-edit-customer" 
                                    data-id="${c.id}"
                                    data-name="${safeName}"
                                    data-company="${safeCompany}"
                                    data-contact="${safeContact}"
                                    data-email="${safeEmail}"
                                    data-phone="${safePhone}"
                                    data-address="${safeAddress}"
                                    data-shipping="${safeShipping}"
                                    data-gst="${safeGst}"
                                    data-credit="${creditLimit}"
                                    data-terms="${terms}"
                                    data-is-active="${c.is_active}"
                                    title="Edit Customer">
                                <i class="fa-solid fa-pen-to-square"></i>
                            </button>
                            <button type="button" 
                                    class="btn btn-sm btn-outline-danger btn-delete-customer" 
                                    data-id="${c.id}"
                                    data-name="${safeName}"
                                    title="Delete Customer">
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
                        ${err.message || 'Failed to load customers.'}
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
        paginationInfo.textContent = `Showing ${start} to ${end} of ${meta.total_items} customers`;

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
                    loadCustomers(targetPage);
                }
            });
        });
    }

    function bindRowActions() {
        document.querySelectorAll('.btn-edit-customer').forEach(function (btn) {
            btn.addEventListener('click', function () {
                document.getElementById('edit_cust_id').value = this.dataset.id;
                document.getElementById('edit_cust_name').value = this.dataset.name;
                document.getElementById('edit_cust_company').value = this.dataset.company || '';
                document.getElementById('edit_cust_contact').value = this.dataset.contact || '';
                document.getElementById('edit_cust_tax').value = this.dataset.gst || '';
                document.getElementById('edit_cust_email').value = this.dataset.email || '';
                document.getElementById('edit_cust_phone').value = this.dataset.phone || '';
                document.getElementById('edit_cust_credit').value = this.dataset.credit || '0.00';
                document.getElementById('edit_cust_terms').value = this.dataset.terms || '30';
                document.getElementById('edit_cust_status').value = this.dataset.isActive;
                document.getElementById('edit_cust_billing').value = this.dataset.address || '';
                document.getElementById('edit_cust_shipping').value = this.dataset.shipping || '';

                const modal = new bootstrap.Modal(document.getElementById('modalCustomerEdit'));
                modal.show();
            });
        });

        document.querySelectorAll('.btn-delete-customer').forEach(function (btn) {
            btn.addEventListener('click', async function () {
                const id = this.dataset.id;
                const name = this.dataset.name;

                const confirmed = await App.confirm(
                    'Delete Customer',
                    `Are you sure you want to delete customer account "${name}"? Customers with linked sales orders or pending invoices cannot be removed.`,
                    'Yes, Delete'
                );

                if (!confirmed) return;

                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('id', id);

                try {
                    const res = await App.request(`${appUrl}/api/customers.php`, {
                        method: 'POST',
                        body: formData
                    });
                    App.toast('success', (res && res.message) || (res && res.data && res.data.message) || 'Customer deleted.');
                    loadCustomers(currentPage);
                } catch (err) {
                    App.toast('error', err.message || 'Failed to delete customer.');
                }
            });
        });
    }

    if (searchInput) {
        searchInput.addEventListener('input', function () {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function () {
                loadCustomers(1);
            }, 300);
        });
    }

    if (statusSelect) {
        statusSelect.addEventListener('change', function () {
            loadCustomers(1);
        });
    }

    if (btnReset) {
        btnReset.addEventListener('click', function () {
            if (searchInput) searchInput.value = '';
            if (statusSelect) statusSelect.value = 'all';
            loadCustomers(1);
        });
    }

    App.bindAjaxForm('formCustomerCreate', function () {
        loadCustomers(1);
    });

    App.bindAjaxForm('formCustomerEdit', function () {
        loadCustomers(currentPage);
    });

    loadCustomers(1);
});
</script>

<?php require_once __DIR__ . '/../../includes/layout_footer.php'; ?>
