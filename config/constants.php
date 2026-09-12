<?php
declare(strict_types=1);

define('APP_NAME', 'FactoryFlow');
define('APP_VERSION', '1.0.0');

define('ROLE_ADMIN', 'admin');
define('ROLE_MANAGER', 'manager');
define('ROLE_ACCOUNTANT', 'accountant');
define('ROLE_WORKER', 'worker');
define('ROLES_ALL', [ROLE_ADMIN, ROLE_MANAGER, ROLE_ACCOUNTANT, ROLE_WORKER]);

define('STATUS_PO_DRAFT', 'draft');
define('STATUS_PO_APPROVED', 'approved');
define('STATUS_PO_IN_PROGRESS', 'in_progress');
define('STATUS_PO_COMPLETED', 'completed');
define('STATUS_PO_CANCELLED', 'cancelled');

define('WO_STAGE_CUTTING', 'cutting');
define('WO_STAGE_ASSEMBLY', 'assembly');
define('WO_STAGE_FINISHING', 'finishing');
define('WO_STAGE_QC', 'qc');
define('WO_STAGE_PACKING', 'packing');
define('WO_STAGES', [
    WO_STAGE_CUTTING,
    WO_STAGE_ASSEMBLY,
    WO_STAGE_FINISHING,
    WO_STAGE_QC,
    WO_STAGE_PACKING
]);

define('WO_STATUS_PENDING', 'pending');
define('WO_STATUS_IN_PROGRESS', 'in_progress');
define('WO_STATUS_COMPLETED', 'completed');

define('STATUS_PUR_DRAFT', 'draft');
define('STATUS_PUR_SENT', 'sent');
define('STATUS_PUR_PARTIAL', 'partial');
define('STATUS_PUR_RECEIVED', 'received');
define('STATUS_PUR_CANCELLED', 'cancelled');

define('STATUS_SO_DRAFT', 'draft');
define('STATUS_SO_CONFIRMED', 'confirmed');
define('STATUS_SO_SHIPPED', 'shipped');
define('STATUS_SO_DELIVERED', 'delivered');
define('STATUS_SO_CANCELLED', 'cancelled');

define('STATUS_INV_UNPAID', 'unpaid');
define('STATUS_INV_PARTIAL', 'partial');
define('STATUS_INV_PAID', 'paid');
define('STATUS_INV_OVERDUE', 'overdue');

define('MOVEMENT_IN', 'in');
define('MOVEMENT_OUT', 'out');
define('MOVEMENT_ADJUSTMENT', 'adjustment');

define('REF_PRODUCTION', 'production');
define('REF_PURCHASE', 'purchase');
define('REF_SALE', 'sale');
define('REF_MANUAL', 'manual');
define('REF_RETURN', 'return');

define('PREFIX_PROD_ORDER', 'PO-');
define('PREFIX_PURCHASE_ORDER', 'PUR-');
define('PREFIX_SALES_ORDER', 'SO-');
define('PREFIX_INVOICE', 'INV-');

define('DEFAULT_PAGE_SIZE', 10);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_MINUTES', 15);
define('PASSWORD_RESET_EXPIRY_HOURS', 1);
define('SESSION_LIFETIME_SECONDS', 3600);
define('MAX_UPLOAD_SIZE_BYTES', 2097152);
define('ALLOWED_IMAGE_MIMES', ['image/jpeg', 'image/png', 'image/webp']);
