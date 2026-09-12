<?php
declare(strict_types=1);

$pageTitle = 'Payment Gateways & Settings';

require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/rbac.php';

require_role(ROLE_ADMIN);

$pdo = Database::getConnection();
$appUrl = rtrim((string)(defined('APP_URL') ? APP_URL : Config::get('APP_URL', 'http://localhost/factoryflow')), '/');

$gatewaysStmt = $pdo->query("SELECT * FROM payment_gateways ORDER BY name ASC");
$gateways = $gatewaysStmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../../includes/layout_header.php';
?>

<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1">Payment Gateways & Integrations</h4>
        <p class="text-muted small mb-0">Configure merchant credentials, webhook verification keys, and test/live payment processing.</p>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
        <span class="fw-semibold">Configured Merchant Gateways</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Gateway</th>
                        <th>Operating Mode</th>
                        <th>Public Key</th>
                        <th class="text-center">Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($gateways)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-credit-card d-block mb-2" style="font-size: 2rem;"></i>
                                No payment gateways initialized yet. Stripe gateway configuration will be enabled in Step 5.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($gateways as $gw): ?>
                            <tr>
                                <td class="fw-semibold text-dark text-capitalize">
                                    <i class="fa-brands fa-stripe me-2 text-primary"></i> <?= e($gw['name']) ?>
                                </td>
                                <td>
                                    <span class="badge bg-secondary-subtle text-secondary font-monospace">
                                        <?= e(strtoupper($gw['mode'])) ?>
                                    </span>
                                </td>
                                <td class="font-monospace small text-muted">
                                    <?= e($gw['public_key'] ? substr($gw['public_key'], 0, 16) . '...' : 'Not configured') ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge <?= (int)$gw['is_active'] === 1 ? 'bg-success-subtle text-success border border-success' : 'bg-secondary-subtle text-secondary border' ?>">
                                        <?= (int)$gw['is_active'] === 1 ? 'Active' : 'Disabled' ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="App.toast('info', 'Gateway configuration will be managed in Step 5.')">
                                        Configure
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/layout_footer.php'; ?>
