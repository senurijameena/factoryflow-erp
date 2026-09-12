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

$id = (int)($_GET['id'] ?? 0);
$isEdit = $id > 0;
$product = null;

if ($isEdit) {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        set_flash('error', 'Product not found.');
        header('Location: ' . $appUrl . '/pages/products/index.php');
        exit;
    }
}

$pageTitle = $isEdit ? 'Edit Product: ' . ($product['name'] ?? '') : 'New Product Registration';

$categoriesStmt = $pdo->query("SELECT id, name FROM categories WHERE type = 'product' ORDER BY name ASC");
$categories = $categoriesStmt->fetchAll(PDO::FETCH_ASSOC);

$unitsStmt = $pdo->query("SELECT id, name, symbol FROM units ORDER BY name ASC");
$units = $unitsStmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../../includes/layout_header.php';
?>

<div class="mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-1">
            <li class="breadcrumb-item"><a href="<?= e($appUrl) ?>/pages/products/index.php">Products Catalog</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?= $isEdit ? 'Edit Product' : 'Create Product' ?></li>
        </ol>
    </nav>
    <div class="d-flex align-items-center justify-content-between">
        <h4 class="fw-bold mb-0"><?= $isEdit ? 'Update Product Specifications' : 'New Product Registration' ?></h4>
        <a href="<?= e($appUrl) ?>/pages/products/index.php" class="btn btn-sm btn-outline-secondary">
            <i class="fa-solid fa-arrow-left me-1"></i> Back to Catalog
        </a>
    </div>
</div>

<form id="productForm" action="<?= e($appUrl) ?>/api/products.php" method="POST" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="<?= $isEdit ? 'update' : 'create' ?>">
    <?php if ($isEdit): ?>
        <input type="hidden" name="id" value="<?= (int)$product['id'] ?>">
    <?php endif; ?>

    <div class="alert alert-danger py-2 small mb-4 form-error-alert d-none" role="alert"></div>

    <div class="row g-4">
        <div class="col-12 col-lg-8">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <span class="fw-semibold text-dark"><i class="fa-solid fa-circle-info text-primary me-2"></i>General Information</span>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="prod_name" class="form-label small fw-semibold text-secondary">Product Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="prod_name" name="name" placeholder="e.g. Industrial Turbine Blade 120mm" required value="<?= e($product['name'] ?? '') ?>" maxlength="150">
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-12 col-sm-6">
                            <label for="prod_sku" class="form-label small fw-semibold text-secondary">
                                SKU / Item Code 
                                <?php if (!$isEdit): ?>
                                    <span class="text-muted fw-normal">(Leave blank to auto-generate)</span>
                                <?php endif; ?>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted"><i class="fa-solid fa-barcode"></i></span>
                                <input type="text" class="form-control font-monospace text-uppercase" id="prod_sku" name="sku" placeholder="e.g. PRD-2026-0001" value="<?= e($product['sku'] ?? '') ?>" maxlength="50" <?= $isEdit ? 'required' : '' ?>>
                            </div>
                        </div>

                        <div class="col-12 col-sm-6">
                            <label for="prod_barcode" class="form-label small fw-semibold text-secondary">Barcode / UPC / EAN</label>
                            <input type="text" class="form-control font-monospace" id="prod_barcode" name="barcode" placeholder="e.g. 784592018342" value="<?= e($product['barcode'] ?? '') ?>" maxlength="100">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="prod_description" class="form-label small fw-semibold text-secondary">Technical Description & Notes</label>
                        <textarea class="form-control" id="prod_description" name="description" rows="4" placeholder="Specify production tolerances, metallurgy, quality guidelines, or packaging standards..."><?= e($product['description'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <span class="fw-semibold text-dark"><i class="fa-solid fa-dollar-sign text-primary me-2"></i>Costing & Pricing</span>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12 col-sm-6">
                            <label for="prod_cost_price" class="form-label small fw-semibold text-secondary">Cost Price ($) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light">$</span>
                                <input type="number" step="0.01" min="0" class="form-control font-monospace" id="prod_cost_price" name="cost_price" placeholder="0.00" required value="<?= e($product['cost_price'] ?? '0.00') ?>">
                            </div>
                            <div class="form-text small text-muted">Standard baseline manufacturing cost per unit.</div>
                        </div>

                        <div class="col-12 col-sm-6">
                            <label for="prod_sale_price" class="form-label small fw-semibold text-secondary">Selling Price ($) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light">$</span>
                                <input type="number" step="0.01" min="0" class="form-control font-monospace" id="prod_sale_price" name="sale_price" placeholder="0.00" required value="<?= e($product['sale_price'] ?? '0.00') ?>">
                            </div>
                            <div class="form-text small text-muted">Default wholesale customer sale price.</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header bg-white py-3">
                    <span class="fw-semibold text-dark"><i class="fa-solid fa-warehouse text-primary me-2"></i>Inventory Stock & Planning</span>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12 col-sm-4">
                            <label for="prod_reorder_level" class="form-label small fw-semibold text-secondary">Min Reorder Level <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0" class="form-control font-monospace" id="prod_reorder_level" name="reorder_level" placeholder="0.00" required value="<?= e($product['reorder_level'] ?? '10.00') ?>">
                            <div class="form-text small text-muted">Safety threshold triggering low stock alerts.</div>
                        </div>

                        <div class="col-12 col-sm-4">
                            <label for="prod_lead_time" class="form-label small fw-semibold text-secondary">Lead Time (Days)</label>
                            <input type="number" min="0" class="form-control" id="prod_lead_time" name="lead_time_days" placeholder="0" value="<?= e($product['lead_time_days'] ?? '3') ?>">
                            <div class="form-text small text-muted">Manufacturing cycle lead time.</div>
                        </div>

                        <?php if (!$isEdit): ?>
                        <div class="col-12 col-sm-4">
                            <label for="prod_initial_stock" class="form-label small fw-semibold text-secondary">Initial Stock Balance</label>
                            <input type="number" step="0.01" min="0" class="form-control font-monospace" id="prod_initial_stock" name="current_stock" placeholder="0.00" value="0.00">
                            <div class="form-text small text-muted">Starting physical stock quantity.</div>
                        </div>
                        <?php else: ?>
                        <div class="col-12 col-sm-4">
                            <label class="form-label small fw-semibold text-secondary">Current Physical Stock</label>
                            <div class="form-control bg-light font-monospace fw-bold text-primary">
                                <?= number_format((float)$product['current_stock'], 2) ?>
                            </div>
                            <div class="form-text small text-muted">Adjustable via stock movements module.</div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-4">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <span class="fw-semibold text-dark"><i class="fa-solid fa-tags text-primary me-2"></i>Classification</span>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="prod_category" class="form-label small fw-semibold text-secondary">Category <span class="text-danger">*</span></label>
                        <select class="form-select" id="prod_category" name="category_id" required>
                            <option value="">-- Choose Category --</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= (int)$cat['id'] ?>" <?= isset($product['category_id']) && (int)$product['category_id'] === (int)$cat['id'] ? 'selected' : '' ?>>
                                    <?= e($cat['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="prod_unit" class="form-label small fw-semibold text-secondary">Unit of Measure (UOM) <span class="text-danger">*</span></label>
                        <select class="form-select" id="prod_unit" name="unit_id" required>
                            <option value="">-- Choose Unit --</option>
                            <?php foreach ($units as $u): ?>
                                <option value="<?= (int)$u['id'] ?>" <?= isset($product['unit_id']) && (int)$product['unit_id'] === (int)$u['id'] ? 'selected' : '' ?>>
                                    <?= e($u['name']) ?> (<?= e($u['symbol']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <?php if ($isEdit): ?>
                    <div class="form-check form-switch mt-3">
                        <input class="form-check-input" type="checkbox" id="prod_is_active" name="is_active" value="1" <?= (int)$product['is_active'] === 1 ? 'checked' : '' ?>>
                        <label class="form-check-label small fw-semibold" for="prod_is_active">Product Active for Sales & Production</label>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <span class="fw-semibold text-dark"><i class="fa-solid fa-image text-primary me-2"></i>Product Image</span>
                </div>
                <div class="card-body text-center">
                    <div class="mb-3">
                        <?php if (!empty($product['image'])): ?>
                            <img id="imgPreview" src="<?= e($appUrl . '/' . ltrim($product['image'], '/')) ?>" alt="Preview" class="img-fluid rounded border shadow-sm mb-2" style="max-height: 180px; object-fit: contain;">
                        <?php else: ?>
                            <div id="imgPlaceholder" class="bg-light rounded border d-flex align-items-center justify-content-center text-muted mx-auto mb-2" style="width: 100%; height: 160px;">
                                <i class="fa-solid fa-image fa-3x opacity-50"></i>
                            </div>
                            <img id="imgPreview" src="#" alt="Preview" class="img-fluid rounded border shadow-sm mb-2 d-none" style="max-height: 180px; object-fit: contain;">
                        <?php endif; ?>
                    </div>

                    <div class="mb-2">
                        <input type="file" class="form-control form-control-sm" id="prod_image" name="image" accept="image/jpeg,image/png,image/webp">
                        <div class="form-text small text-muted">JPG, PNG or WebP up to 2MB.</div>
                    </div>

                    <?php if (!empty($product['image'])): ?>
                    <div class="form-check text-start mt-2">
                        <input class="form-check-input" type="checkbox" id="remove_image" name="remove_image" value="1">
                        <label class="form-check-label small text-danger" for="remove_image">Remove current image</label>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary py-2 fw-semibold">
                    <i class="fa-solid fa-check me-2"></i> <?= $isEdit ? 'Save Product Changes' : 'Register Product' ?>
                </button>
                <a href="<?= e($appUrl) ?>/pages/products/index.php" class="btn btn-light border py-2 text-muted">Cancel</a>
            </div>
        </div>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const appUrl = '<?= e($appUrl) ?>';
    const imgInput = document.getElementById('prod_image');
    const imgPreview = document.getElementById('imgPreview');
    const imgPlaceholder = document.getElementById('imgPlaceholder');

    if (imgInput) {
        imgInput.addEventListener('change', function () {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    imgPreview.src = e.target.result;
                    imgPreview.classList.remove('d-none');
                    if (imgPlaceholder) imgPlaceholder.classList.add('d-none');
                };
                reader.readAsDataURL(file);
            }
        });
    }

    App.bindAjaxForm('productForm', function (res) {
        // App.bindAjaxForm automatically displays toast and navigates to the redirect target.
        // If not already redirected, provide safe fallback:
        const target = (res && res.redirect) || (res && res.data && res.data.redirect);
        if (!target) {
            setTimeout(function () {
                window.location.href = `${appUrl}/pages/products/index.php`;
            }, 600);
        }
    });
});
</script>

<?php require_once __DIR__ . '/../../includes/layout_footer.php'; ?>
