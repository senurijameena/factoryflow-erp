<?php
declare(strict_types=1);

$pageTitle = 'Item Categories';

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/rbac.php';

require_role([ROLE_ADMIN, ROLE_MANAGER]);

$appUrl = rtrim((string)(defined('APP_URL') ? APP_URL : Config::get('APP_URL', 'http://localhost/factoryflow')), '/');

require_once __DIR__ . '/../includes/layout_header.php';
?>

<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1">Item Categories</h4>
        <p class="text-muted small mb-0">Organize finished goods and raw materials into structured classifications.</p>
    </div>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCategoryCreate">
        <i class="fa-solid fa-plus me-1"></i> Add Category
    </button>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-body py-3">
        <div class="row g-2 align-items-center">
            <div class="col-12 col-md-6">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                    <input type="text" id="searchCategory" class="form-control" placeholder="Type to search by name or description...">
                </div>
            </div>
            <div class="col-6 col-md-4">
                <select id="filterCategoryType" class="form-select form-select-sm">
                    <option value="">All Category Types</option>
                    <option value="product">Finished Products</option>
                    <option value="material">Raw Materials</option>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <button type="button" id="btnResetCategories" class="btn btn-sm btn-outline-secondary w-100">
                    Reset
                </button>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
        <span class="fw-semibold">Categories Directory</span>
        <span class="badge bg-light text-secondary border" id="categoryCountBadge">Loading...</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="categoriesTable">
                <thead>
                    <tr>
                        <th>Category Name</th>
                        <th class="text-center">Classification</th>
                        <th>Description</th>
                        <th class="text-center">Linked Items</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="categoriesTableBody">
                    <tr>
                        <td colspan="5" class="text-center py-5 text-muted">
                            <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                            Loading categories...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="modalCategoryCreate" tabindex="-1" aria-labelledby="modalCategoryCreateLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="formCategoryCreate" action="<?= e($appUrl) ?>/api/categories.php" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create">

                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="modalCategoryCreateLabel">Add New Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger py-2 small mb-3 form-error-alert d-none"></div>

                    <div class="mb-3">
                        <label for="create_cat_name" class="form-label small fw-semibold text-secondary">Category Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="create_cat_name" name="name" placeholder="e.g. Electrical Components" required maxlength="100">
                    </div>

                    <div class="mb-3">
                        <label for="create_cat_type" class="form-label small fw-semibold text-secondary">Classification Type <span class="text-danger">*</span></label>
                        <select class="form-select" id="create_cat_type" name="type" required>
                            <option value="product">Finished Product</option>
                            <option value="material">Raw Material</option>
                        </select>
                    </div>

                    <div class="mb-2">
                        <label for="create_cat_desc" class="form-label small fw-semibold text-secondary">Description</label>
                        <textarea class="form-control" id="create_cat_desc" name="description" rows="3" placeholder="Optional notes regarding this category..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary">Save Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalCategoryEdit" tabindex="-1" aria-labelledby="modalCategoryEditLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="formCategoryEdit" action="<?= e($appUrl) ?>/api/categories.php" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="edit_cat_id">

                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="modalCategoryEditLabel">Edit Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger py-2 small mb-3 form-error-alert d-none"></div>

                    <div class="mb-3">
                        <label for="edit_cat_name" class="form-label small fw-semibold text-secondary">Category Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit_cat_name" name="name" required maxlength="100">
                    </div>

                    <div class="mb-3">
                        <label for="edit_cat_type" class="form-label small fw-semibold text-secondary">Classification Type <span class="text-danger">*</span></label>
                        <select class="form-select" id="edit_cat_type" name="type" required>
                            <option value="product">Finished Product</option>
                            <option value="material">Raw Material</option>
                        </select>
                    </div>

                    <div class="mb-2">
                        <label for="edit_cat_desc" class="form-label small fw-semibold text-secondary">Description</label>
                        <textarea class="form-control" id="edit_cat_desc" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary">Update Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const appUrl = '<?= e($appUrl) ?>';
    const tbody = document.getElementById('categoriesTableBody');
    const searchInput = document.getElementById('searchCategory');
    const typeSelect = document.getElementById('filterCategoryType');
    const badgeCount = document.getElementById('categoryCountBadge');
    const btnReset = document.getElementById('btnResetCategories');

    let debounceTimer = null;

    async function loadCategories() {
        const search = searchInput ? searchInput.value.trim() : '';
        const type = typeSelect ? typeSelect.value : '';

        const params = new URLSearchParams({
            action: 'list',
            search: search,
            type: type
        });

        try {
            const res = await App.request(`${appUrl}/api/categories.php?${params.toString()}`);
            const items = res.data || [];

            if (badgeCount) {
                badgeCount.textContent = `${items.length} categories`;
            }

            if (!items.length) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="5" class="text-center py-5 text-muted">
                            <i class="fa-solid fa-tags d-block mb-2" style="font-size: 2rem;"></i>
                            No categories found matching your criteria.
                        </td>
                    </tr>
                `;
                return;
            }

            tbody.innerHTML = items.map(function (c) {
                const isProd = c.type === 'product';
                const badgeClass = isProd ? 'bg-primary-subtle text-primary border border-primary' : 'bg-info-subtle text-info border border-info';
                const countBadge = isProd 
                    ? `<span class="badge bg-light text-dark border">${parseInt(c.products_count || 0)} products</span>` 
                    : `<span class="badge bg-light text-dark border">${parseInt(c.materials_count || 0)} materials</span>`;

                const safeName = (c.name || '').replace(/"/g, '&quot;');
                const safeDesc = (c.description || '').replace(/"/g, '&quot;');

                return `
                    <tr id="category-row-${c.id}">
                        <td class="fw-semibold text-dark">${c.name}</td>
                        <td class="text-center">
                            <span class="badge ${badgeClass}">${isProd ? 'Product' : 'Material'}</span>
                        </td>
                        <td class="text-muted small">${c.description || '&mdash;'}</td>
                        <td class="text-center">${countBadge}</td>
                        <td class="text-end">
                            <button type="button" 
                                    class="btn btn-sm btn-outline-primary me-1 btn-edit-category" 
                                    data-id="${c.id}"
                                    data-name="${safeName}"
                                    data-type="${c.type}"
                                    data-description="${safeDesc}"
                                    title="Edit Category">
                                <i class="fa-solid fa-pen-to-square"></i>
                            </button>
                            <button type="button" 
                                    class="btn btn-sm btn-outline-danger btn-delete-category" 
                                    data-id="${c.id}"
                                    data-name="${safeName}"
                                    title="Delete Category">
                                <i class="fa-solid fa-trash-can"></i>
                            </button>
                        </td>
                    </tr>
                `;
            }).join('');

            bindRowActions();
        } catch (err) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="5" class="text-center py-4 text-danger">
                        ${err.message || 'Failed to load categories.'}
                    </td>
                </tr>
            `;
        }
    }

    function bindRowActions() {
        document.querySelectorAll('.btn-edit-category').forEach(function (btn) {
            btn.addEventListener('click', function () {
                document.getElementById('edit_cat_id').value = this.dataset.id;
                document.getElementById('edit_cat_name').value = this.dataset.name;
                document.getElementById('edit_cat_type').value = this.dataset.type;
                document.getElementById('edit_cat_desc').value = this.dataset.description || '';

                const modal = new bootstrap.Modal(document.getElementById('modalCategoryEdit'));
                modal.show();
            });
        });

        document.querySelectorAll('.btn-delete-category').forEach(function (btn) {
            btn.addEventListener('click', async function () {
                const id = this.dataset.id;
                const name = this.dataset.name;

                const confirmed = await App.confirm(
                    'Delete Category',
                    `Are you sure you want to delete the category "${name}"?`,
                    'Yes, Delete'
                );

                if (!confirmed) return;

                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('id', id);

                try {
                    const res = await App.request(`${appUrl}/api/categories.php`, {
                        method: 'POST',
                        body: formData
                    });
                    App.toast('success', (res && res.message) || (res && res.data && res.data.message) || 'Category deleted.');
                    loadCategories();
                } catch (err) {
                    App.toast('error', err.message || 'Failed to delete category.');
                }
            });
        });
    }

    if (searchInput) {
        searchInput.addEventListener('input', function () {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(loadCategories, 300);
        });
    }

    if (typeSelect) {
        typeSelect.addEventListener('change', loadCategories);
    }

    if (btnReset) {
        btnReset.addEventListener('click', function () {
            if (searchInput) searchInput.value = '';
            if (typeSelect) typeSelect.value = '';
            loadCategories();
        });
    }

    App.bindAjaxForm('formCategoryCreate', function () {
        loadCategories();
    });

    App.bindAjaxForm('formCategoryEdit', function () {
        loadCategories();
    });

    loadCategories();
});
</script>

<?php require_once __DIR__ . '/../includes/layout_footer.php'; ?>
