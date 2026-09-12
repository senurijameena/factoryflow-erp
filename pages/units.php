<?php
declare(strict_types=1);

$pageTitle = 'Units of Measure';

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
        <h4 class="fw-bold mb-1">Units of Measure (UOM)</h4>
        <p class="text-muted small mb-0">Define metric, volumetric, and discrete measurement standards for manufacturing recipes.</p>
    </div>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalUnitCreate">
        <i class="fa-solid fa-plus me-1"></i> Add Unit
    </button>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-body py-3">
        <div class="row g-2 align-items-center">
            <div class="col-12 col-md-8">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                    <input type="text" id="searchUnit" class="form-control" placeholder="Search by unit name or symbol...">
                </div>
            </div>
            <div class="col-12 col-md-4">
                <button type="button" id="btnResetUnits" class="btn btn-sm btn-outline-secondary w-100">
                    Reset
                </button>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
        <span class="fw-semibold">All Standard Units</span>
        <span class="badge bg-light text-secondary border" id="unitCountBadge">Loading...</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="unitsTable">
                <thead>
                    <tr>
                        <th>Unit Name</th>
                        <th class="text-center">Symbol</th>
                        <th class="text-center">Linked Records</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="unitsTableBody">
                    <tr>
                        <td colspan="4" class="text-center py-5 text-muted">
                            <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                            Loading units of measure...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="modalUnitCreate" tabindex="-1" aria-labelledby="modalUnitCreateLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="formUnitCreate" action="<?= e($appUrl) ?>/api/units.php" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create">

                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="modalUnitCreateLabel">Add Measurement Unit</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger py-2 small mb-3 form-error-alert d-none"></div>

                    <div class="mb-3">
                        <label for="create_unit_name" class="form-label small fw-semibold text-secondary">Unit Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="create_unit_name" name="name" placeholder="e.g. Kilogram, Meter, Liter, Piece" required maxlength="50">
                    </div>

                    <div class="mb-2">
                        <label for="create_unit_symbol" class="form-label small fw-semibold text-secondary">Symbol <span class="text-danger">*</span></label>
                        <input type="text" class="form-control font-monospace" id="create_unit_symbol" name="symbol" placeholder="e.g. kg, m, L, pcs" required maxlength="10">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary">Save Unit</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalUnitEdit" tabindex="-1" aria-labelledby="modalUnitEditLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="formUnitEdit" action="<?= e($appUrl) ?>/api/units.php" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="edit_unit_id">

                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="modalUnitEditLabel">Edit Unit of Measure</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger py-2 small mb-3 form-error-alert d-none"></div>

                    <div class="mb-3">
                        <label for="edit_unit_name" class="form-label small fw-semibold text-secondary">Unit Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit_unit_name" name="name" required maxlength="50">
                    </div>

                    <div class="mb-2">
                        <label for="edit_unit_symbol" class="form-label small fw-semibold text-secondary">Symbol <span class="text-danger">*</span></label>
                        <input type="text" class="form-control font-monospace" id="edit_unit_symbol" name="symbol" required maxlength="10">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary">Update Unit</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const appUrl = '<?= e($appUrl) ?>';
    const tbody = document.getElementById('unitsTableBody');
    const searchInput = document.getElementById('searchUnit');
    const badgeCount = document.getElementById('unitCountBadge');
    const btnReset = document.getElementById('btnResetUnits');

    let debounceTimer = null;

    async function loadUnits() {
        const search = searchInput ? searchInput.value.trim() : '';
        const params = new URLSearchParams({
            action: 'list',
            search: search
        });

        try {
            const res = await App.request(`${appUrl}/api/units.php?${params.toString()}`);
            const items = res.data || [];

            if (badgeCount) {
                badgeCount.textContent = `${items.length} units`;
            }

            if (!items.length) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="4" class="text-center py-5 text-muted">
                            <i class="fa-solid fa-ruler d-block mb-2" style="font-size: 2rem;"></i>
                            No measurement units found matching your search.
                        </td>
                    </tr>
                `;
                return;
            }

            tbody.innerHTML = items.map(function (u) {
                const safeName = (u.name || '').replace(/"/g, '&quot;');
                const safeSymbol = (u.symbol || '').replace(/"/g, '&quot;');
                const prodCount = parseInt(u.products_count || 0);
                const matCount = parseInt(u.materials_count || 0);

                return `
                    <tr id="unit-row-${u.id}">
                        <td class="fw-semibold text-dark">${u.name}</td>
                        <td class="text-center">
                            <span class="badge bg-secondary-subtle text-secondary font-monospace px-2 py-1">
                                ${u.symbol}
                            </span>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-light text-dark border me-1">${prodCount} products</span>
                            <span class="badge bg-light text-dark border">${matCount} materials</span>
                        </td>
                        <td class="text-end">
                            <button type="button" 
                                    class="btn btn-sm btn-outline-primary me-1 btn-edit-unit" 
                                    data-id="${u.id}"
                                    data-name="${safeName}"
                                    data-symbol="${safeSymbol}"
                                    title="Edit Unit">
                                <i class="fa-solid fa-pen-to-square"></i>
                            </button>
                            <button type="button" 
                                    class="btn btn-sm btn-outline-danger btn-delete-unit" 
                                    data-id="${u.id}"
                                    data-name="${safeName}"
                                    title="Delete Unit">
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
                    <td colspan="4" class="text-center py-4 text-danger">
                        ${err.message || 'Failed to load measurement units.'}
                    </td>
                </tr>
            `;
        }
    }

    window.loadUnits = loadUnits;

    function bindRowActions() {
        document.querySelectorAll('.btn-edit-unit').forEach(function (btn) {
            btn.addEventListener('click', function () {
                document.getElementById('edit_unit_id').value = this.dataset.id;
                document.getElementById('edit_unit_name').value = this.dataset.name;
                document.getElementById('edit_unit_symbol').value = this.dataset.symbol;

                const modal = new bootstrap.Modal(document.getElementById('modalUnitEdit'));
                modal.show();
            });
        });

        document.querySelectorAll('.btn-delete-unit').forEach(function (btn) {
            btn.addEventListener('click', async function () {
                const id = this.dataset.id;
                const name = this.dataset.name;

                const confirmed = await App.confirm(
                    'Delete Measurement Unit',
                    `Are you sure you want to delete the unit "${name}"? This action cannot be undone.`,
                    'Yes, Delete'
                );

                if (!confirmed) return;

                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('id', id);

                try {
                    const res = await App.request(`${appUrl}/api/units.php`, {
                        method: 'POST',
                        body: formData
                    });
                    App.toast('success', (res && res.message) || (res && res.data && res.data.message) || 'Unit deleted.');
                    loadUnits();
                } catch (err) {
                    App.toast('error', err.message || 'Failed to delete unit.');
                }
            });
        });
    }

    if (searchInput) {
        searchInput.addEventListener('input', function () {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(loadUnits, 300);
        });
    }

    if (btnReset) {
        btnReset.addEventListener('click', function () {
            if (searchInput) searchInput.value = '';
            loadUnits();
        });
    }

    App.bindAjaxForm('formUnitCreate', function () {
        loadUnits();
    });

    App.bindAjaxForm('formUnitEdit', function () {
        loadUnits();
    });

    loadUnits();
});
</script>

<?php require_once __DIR__ . '/../includes/layout_footer.php'; ?>
