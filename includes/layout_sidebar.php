<?php
declare(strict_types=1);

if (defined('FF_SIDEBAR_INCLUDED')) {
    return;
}
define('FF_SIDEBAR_INCLUDED', true);

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/rbac.php';

$appUrl = rtrim((string)(defined('APP_URL') ? APP_URL : Config::get('APP_URL', 'http://localhost/factoryflow')), '/');
$currentUri = $_SERVER['REQUEST_URI'] ?? '';
$currentScript = $_SERVER['SCRIPT_NAME'] ?? '';

function is_ff_nav_active(string $path, string $currentUri, string $currentScript): string
{
    if (str_contains($currentUri, $path) || str_contains($currentScript, $path)) {
        return ' active';
    }
    return '';
}

$userRole = $_SESSION['user_role'] ?? ROLE_WORKER;
$userName = $_SESSION['user_name'] ?? 'User';
?>
<aside class="ff-sidebar">
    <a href="<?= e($appUrl) ?>/pages/dashboard.php" class="ff-sidebar-brand">
        <i class="fa-solid fa-industry me-2"></i>
        <span>FactoryFlow</span>
    </a>

    <div class="ff-sidebar-nav">
        <div class="ff-sidebar-section">Main</div>
        <a href="<?= e($appUrl) ?>/pages/dashboard.php" class="ff-sidebar-link<?= is_ff_nav_active('/pages/dashboard.php', $currentUri, $currentScript) ?>">
            <i class="fa-solid fa-gauge-high"></i>
            <span>Dashboard</span>
        </a>
        <a href="<?= e($appUrl) ?>/pages/production/index.php" class="ff-sidebar-link<?= is_ff_nav_active('/pages/production', $currentUri, $currentScript) ?>">
            <i class="fa-solid fa-clipboard-list"></i>
            <span>Production Orders</span>
        </a>

        <?php if (has_role([ROLE_ADMIN, ROLE_MANAGER])): ?>
        <div class="ff-sidebar-section">Production & BOM</div>
        <a href="<?= e($appUrl) ?>/pages/boms/index.php" class="ff-sidebar-link<?= is_ff_nav_active('/pages/boms', $currentUri, $currentScript) ?>">
            <i class="fa-solid fa-layer-group"></i>
            <span>Bill of Materials</span>
        </a>
        <?php endif; ?>

        <div class="ff-sidebar-section">Inventory & Master Data</div>
        <a href="<?= e($appUrl) ?>/pages/inventory/index.php" class="ff-sidebar-link<?= is_ff_nav_active('/pages/inventory', $currentUri, $currentScript) ?>">
            <i class="fa-solid fa-boxes-stacked"></i>
            <span>Inventory Stock</span>
        </a>
        <?php if (has_role([ROLE_ADMIN, ROLE_MANAGER])): ?>
        <a href="<?= e($appUrl) ?>/pages/products/index.php" class="ff-sidebar-link<?= is_ff_nav_active('/pages/products', $currentUri, $currentScript) ?>">
            <i class="fa-solid fa-box-open"></i>
            <span>Products</span>
        </a>
        <a href="<?= e($appUrl) ?>/pages/materials/index.php" class="ff-sidebar-link<?= is_ff_nav_active('/pages/materials', $currentUri, $currentScript) ?>">
            <i class="fa-solid fa-cubes-stacked"></i>
            <span>Raw Materials</span>
        </a>
        <a href="<?= e($appUrl) ?>/pages/categories/index.php" class="ff-sidebar-link<?= is_ff_nav_active('/pages/categories', $currentUri, $currentScript) ?>">
            <i class="fa-solid fa-tags"></i>
            <span>Categories</span>
        </a>
        <a href="<?= e($appUrl) ?>/pages/units/index.php" class="ff-sidebar-link<?= is_ff_nav_active('/pages/units', $currentUri, $currentScript) ?>">
            <i class="fa-solid fa-ruler"></i>
            <span>Units of Measure</span>
        </a>

        <div class="ff-sidebar-section">Procurement & Sales</div>
        <a href="<?= e($appUrl) ?>/pages/purchase/index.php" class="ff-sidebar-link<?= is_ff_nav_active('/pages/purchase', $currentUri, $currentScript) ?>">
            <i class="fa-solid fa-cart-shopping"></i>
            <span>Purchase Orders</span>
        </a>
        <a href="<?= e($appUrl) ?>/pages/suppliers/index.php" class="ff-sidebar-link<?= is_ff_nav_active('/pages/suppliers', $currentUri, $currentScript) ?>">
            <i class="fa-solid fa-truck"></i>
            <span>Suppliers</span>
        </a>
        <a href="<?= e($appUrl) ?>/pages/sales/index.php" class="ff-sidebar-link<?= is_ff_nav_active('/pages/sales', $currentUri, $currentScript) ?>">
            <i class="fa-solid fa-file-invoice-dollar"></i>
            <span>Sales Orders</span>
        </a>
        <a href="<?= e($appUrl) ?>/pages/customers/index.php" class="ff-sidebar-link<?= is_ff_nav_active('/pages/customers', $currentUri, $currentScript) ?>">
            <i class="fa-solid fa-users"></i>
            <span>Customers</span>
        </a>
        <a href="<?= e($appUrl) ?>/pages/reports/index.php" class="ff-sidebar-link<?= is_ff_nav_active('/pages/reports', $currentUri, $currentScript) ?>">
            <i class="fa-solid fa-chart-line"></i>
            <span>Reports & Analytics</span>
        </a>
        <?php endif; ?>

        <?php if (has_role(ROLE_ADMIN)): ?>
        <div class="ff-sidebar-section">Administration</div>
        <a href="<?= e($appUrl) ?>/pages/users/index.php" class="ff-sidebar-link<?= is_ff_nav_active('/pages/users', $currentUri, $currentScript) ?>">
            <i class="fa-solid fa-user-gear"></i>
            <span>User Management</span>
        </a>
        <a href="<?= e($appUrl) ?>/pages/settings/index.php" class="ff-sidebar-link<?= is_ff_nav_active('/pages/settings', $currentUri, $currentScript) ?>">
            <i class="fa-solid fa-credit-card"></i>
            <span>Payment Gateways</span>
        </a>
        <?php endif; ?>
    </div>

    <div class="ff-sidebar-footer">
        <div class="d-flex align-items-center text-truncate me-2">
            <div class="avatar-bubble me-2 flex-shrink-0" style="width: 30px; height: 30px; font-size: 0.75rem;">
                <?= e(strtoupper(substr($userName, 0, 1))) ?>
            </div>
            <div class="text-truncate">
                <div class="text-white small text-truncate" style="line-height: 1.1;"><?= e($userName) ?></div>
                <span class="role-badge role-<?= e($userRole) ?>" style="font-size: 0.65rem; padding: 1px 4px;"><?= e($userRole) ?></span>
            </div>
        </div>
        <button type="button" class="btn btn-sm btn-outline-secondary text-light p-1" data-action="logout" title="Sign Out" style="width: 28px; height: 28px; display: flex; align-items: center; justify-content: center;">
            <i class="fa-solid fa-right-from-bracket" style="font-size: 0.8rem;"></i>
        </button>
    </div>
</aside>
<div class="ff-sidebar-backdrop"></div>
