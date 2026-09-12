<?php
declare(strict_types=1);

$pageTitle = 'User Management';

require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/rbac.php';

require_role(ROLE_ADMIN);

$pdo = Database::getConnection();
$appUrl = rtrim((string)(defined('APP_URL') ? APP_URL : Config::get('APP_URL', 'http://localhost/factoryflow')), '/');

$usersStmt = $pdo->query("SELECT id, name, email, role, is_active, last_login, created_at FROM users ORDER BY id ASC");
$users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../../includes/layout_header.php';
?>

<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1">User Management</h4>
        <p class="text-muted small mb-0">Administer system users, role allocations (Admin, Manager, Accountant, Worker), and security privileges.</p>
    </div>
    <button type="button" class="btn btn-primary" onclick="App.toast('info', 'User invitation modal will be activated in Step 6.')">
        <i class="fa-solid fa-user-plus me-1"></i> Add User
    </button>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
        <span class="fw-semibold">All System Accounts (<?= count($users) ?>)</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Email</th>
                        <th class="text-center">Role</th>
                        <th class="text-center">Status</th>
                        <th>Last Login</th>
                        <th>Joined</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="avatar-bubble me-2" style="width: 32px; height: 32px; font-size: 0.8rem;">
                                        <?= e(strtoupper(substr($u['name'], 0, 1))) ?>
                                    </div>
                                    <span class="fw-medium text-dark"><?= e($u['name']) ?></span>
                                </div>
                            </td>
                            <td><?= e($u['email']) ?></td>
                            <td class="text-center">
                                <span class="role-badge role-<?= e($u['role']) ?>">
                                    <?= e(strtoupper($u['role'])) ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <span class="badge <?= (int)$u['is_active'] === 1 ? 'bg-success-subtle text-success border border-success' : 'bg-secondary-subtle text-secondary border' ?>">
                                    <?= (int)$u['is_active'] === 1 ? 'Active' : 'Disabled' ?>
                                </span>
                            </td>
                            <td class="text-muted small"><?= $u['last_login'] ? date('M d, Y H:i', strtotime($u['last_login'])) : 'Never' ?></td>
                            <td class="text-muted small"><?= date('M d, Y', strtotime($u['created_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/layout_footer.php'; ?>
