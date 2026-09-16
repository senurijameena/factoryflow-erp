<?php
declare(strict_types=1);

ob_start();

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/rbac.php';

header('Content-Type: application/json; charset=UTF-8');

require_role([ROLE_ADMIN, ROLE_MANAGER]);

$pdo = Database::getConnection();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$rawInput = file_get_contents('php://input');
$jsonInput = json_decode($rawInput, true);
$input = is_array($jsonInput) ? array_merge($_POST, $jsonInput) : $_POST;

$action = trim((string)($input['action'] ?? $_GET['action'] ?? 'list'));

if ($method === 'POST') {
    enforce_csrf();
}

switch ($action) {
    case 'list':
        $search = trim((string)($_GET['search'] ?? ''));
        $status = trim((string)($_GET['status'] ?? 'all'));

        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(100, max(5, (int)($_GET['per_page'] ?? 15)));
        $offset = ($page - 1) * $perPage;

        $where = ["1=1"];
        $params = [];

        if ($search !== '') {
            $where[] = "(c.name LIKE :search_name OR c.company LIKE :search_company OR c.contact_person LIKE :search_contact OR c.email LIKE :search_email OR c.phone LIKE :search_phone OR c.gst_no LIKE :search_gst)";
            $params[':search_name'] = '%' . $search . '%';
            $params[':search_company'] = '%' . $search . '%';
            $params[':search_contact'] = '%' . $search . '%';
            $params[':search_email'] = '%' . $search . '%';
            $params[':search_phone'] = '%' . $search . '%';
            $params[':search_gst'] = '%' . $search . '%';
        }

        if ($status === 'active') {
            $where[] = "c.is_active = 1";
        } elseif ($status === 'inactive') {
            $where[] = "c.is_active = 0";
        }

        $whereClause = implode(' AND ', $where);

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM customers c WHERE {$whereClause}");
        $countStmt->execute($params);
        $totalItems = (int)$countStmt->fetchColumn();

        $sql = "
            SELECT c.*,
                   (SELECT COUNT(*) FROM sales_orders so WHERE so.customer_id = c.id) AS orders_count,
                   (SELECT COALESCE(SUM(inv.total_amount - inv.paid_amount), 0) 
                    FROM invoices inv 
                    JOIN sales_orders so2 ON so2.id = inv.so_id 
                    WHERE so2.customer_id = c.id AND inv.status != 'paid') AS outstanding_balance
            FROM customers c
            WHERE {$whereClause}
            ORDER BY c.name ASC
            LIMIT :offset, :per_page
        ";

        $stmt = $pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue(':per_page', $perPage, PDO::PARAM_INT);
        $stmt->execute();
        $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $totalPages = (int)ceil($totalItems / $perPage);

        json_response(true, $customers, null, [
            'page'        => $page,
            'per_page'    => $perPage,
            'total_items' => $totalItems,
            'total_pages' => $totalPages
        ]);
        break;

    case 'get':
        $id = (int)($_GET['id'] ?? $input['id'] ?? 0);
        if ($id <= 0) {
            json_response(false, null, 'Invalid customer ID.', null, 422);
        }

        $stmt = $pdo->prepare("
            SELECT c.*,
                   (SELECT COUNT(*) FROM sales_orders so WHERE so.customer_id = c.id) AS orders_count,
                   (SELECT COALESCE(SUM(inv.total_amount - inv.paid_amount), 0) 
                    FROM invoices inv 
                    JOIN sales_orders so2 ON so2.id = inv.so_id 
                    WHERE so2.customer_id = c.id AND inv.status != 'paid') AS outstanding_balance
            FROM customers c
            WHERE c.id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$customer) {
            json_response(false, null, 'Customer account not found.', null, 404);
        }

        json_response(true, $customer);
        break;

    case 'create':
        if ($method !== 'POST') {
            json_response(false, null, 'Method not allowed.', null, 405);
        }

        $name = trim((string)($input['name'] ?? ''));
        $company = trim((string)($input['company'] ?? ''));
        $contactPerson = trim((string)($input['contact_person'] ?? ''));
        $email = trim((string)($input['email'] ?? ''));
        $phone = trim((string)($input['phone'] ?? ''));
        $billingAddress = trim((string)($input['billing_address'] ?? $input['address'] ?? ''));
        $shippingAddress = trim((string)($input['shipping_address'] ?? ''));
        $taxId = trim((string)($input['tax_id'] ?? $input['gst_no'] ?? ''));
        $creditLimit = filter_var($input['credit_limit'] ?? 0, FILTER_VALIDATE_FLOAT);
        $paymentTerms = filter_var($input['payment_terms'] ?? 30, FILTER_VALIDATE_INT);

        if ($name === '') {
            json_response(false, null, 'Customer name is required.', null, 422);
        }
        if (mb_strlen($name) > 150) {
            json_response(false, null, 'Customer name cannot exceed 150 characters.', null, 422);
        }

        if ($company !== '' && mb_strlen($company) > 150) {
            json_response(false, null, 'Company name cannot exceed 150 characters.', null, 422);
        }

        if ($contactPerson !== '' && mb_strlen($contactPerson) > 100) {
            json_response(false, null, 'Contact person name cannot exceed 100 characters.', null, 422);
        }

        if ($email !== '') {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                json_response(false, null, 'Please enter a valid email address.', null, 422);
            }
            if (mb_strlen($email) > 191) {
                json_response(false, null, 'Email cannot exceed 191 characters.', null, 422);
            }
        }

        if ($phone !== '' && mb_strlen($phone) > 30) {
            json_response(false, null, 'Phone number cannot exceed 30 characters.', null, 422);
        }

        if ($taxId !== '' && mb_strlen($taxId) > 50) {
            json_response(false, null, 'Tax ID / GST number cannot exceed 50 characters.', null, 422);
        }

        if ($creditLimit === false || $creditLimit < 0) {
            $creditLimit = 0.00;
        }

        if ($paymentTerms === false || $paymentTerms < 0) {
            $paymentTerms = 30;
        }

        $dupStmt = $pdo->prepare("SELECT id FROM customers WHERE name = :name LIMIT 1");
        $dupStmt->execute([':name' => $name]);
        if ($dupStmt->fetch()) {
            json_response(false, null, "A customer named '{$name}' is already registered.", null, 409);
        }

        $insertStmt = $pdo->prepare("
            INSERT INTO customers (
                name, company, contact_person, email, phone, address, shipping_address, gst_no, credit_limit, payment_terms, is_active
            ) VALUES (
                :name, :company, :contact_person, :email, :phone, :address, :shipping_address, :gst_no, :credit_limit, :payment_terms, 1
            )
        ");

        $insertStmt->execute([
            ':name'             => $name,
            ':company'          => $company !== '' ? $company : null,
            ':contact_person'   => $contactPerson !== '' ? $contactPerson : null,
            ':email'            => $email !== '' ? $email : null,
            ':phone'            => $phone !== '' ? $phone : null,
            ':address'          => $billingAddress !== '' ? $billingAddress : null,
            ':shipping_address' => $shippingAddress !== '' ? $shippingAddress : null,
            ':gst_no'           => $taxId !== '' ? $taxId : null,
            ':credit_limit'     => number_format((float)$creditLimit, 2, '.', ''),
            ':payment_terms'    => $paymentTerms
        ]);

        $newId = (int)$pdo->lastInsertId();

        json_response(true, [
            'id'      => $newId,
            'name'    => $name,
            'message' => "Customer '{$name}' created successfully."
        ], null, 201);
        break;

    case 'update':
        if ($method !== 'POST') {
            json_response(false, null, 'Method not allowed.', null, 405);
        }

        $id = (int)($input['id'] ?? 0);
        $name = trim((string)($input['name'] ?? ''));
        $company = trim((string)($input['company'] ?? ''));
        $contactPerson = trim((string)($input['contact_person'] ?? ''));
        $email = trim((string)($input['email'] ?? ''));
        $phone = trim((string)($input['phone'] ?? ''));
        $billingAddress = trim((string)($input['billing_address'] ?? $input['address'] ?? ''));
        $shippingAddress = trim((string)($input['shipping_address'] ?? ''));
        $taxId = trim((string)($input['tax_id'] ?? $input['gst_no'] ?? ''));
        $creditLimit = filter_var($input['credit_limit'] ?? 0, FILTER_VALIDATE_FLOAT);
        $paymentTerms = filter_var($input['payment_terms'] ?? 30, FILTER_VALIDATE_INT);
        $isActive = isset($input['is_active']) ? (int)(bool)$input['is_active'] : 1;

        if ($id <= 0) {
            json_response(false, null, 'Invalid customer ID.', null, 422);
        }

        $existStmt = $pdo->prepare("SELECT id FROM customers WHERE id = :id LIMIT 1");
        $existStmt->execute([':id' => $id]);
        if (!$existStmt->fetch()) {
            json_response(false, null, 'Customer account not found.', null, 404);
        }

        if ($name === '') {
            json_response(false, null, 'Customer name is required.', null, 422);
        }
        if (mb_strlen($name) > 150) {
            json_response(false, null, 'Customer name cannot exceed 150 characters.', null, 422);
        }

        if ($company !== '' && mb_strlen($company) > 150) {
            json_response(false, null, 'Company name cannot exceed 150 characters.', null, 422);
        }

        if ($contactPerson !== '' && mb_strlen($contactPerson) > 100) {
            json_response(false, null, 'Contact person name cannot exceed 100 characters.', null, 422);
        }

        if ($email !== '') {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                json_response(false, null, 'Please enter a valid email address.', null, 422);
            }
            if (mb_strlen($email) > 191) {
                json_response(false, null, 'Email cannot exceed 191 characters.', null, 422);
            }
        }

        if ($phone !== '' && mb_strlen($phone) > 30) {
            json_response(false, null, 'Phone number cannot exceed 30 characters.', null, 422);
        }

        if ($taxId !== '' && mb_strlen($taxId) > 50) {
            json_response(false, null, 'Tax ID / GST number cannot exceed 50 characters.', null, 422);
        }

        if ($creditLimit === false || $creditLimit < 0) {
            $creditLimit = 0.00;
        }

        if ($paymentTerms === false || $paymentTerms < 0) {
            $paymentTerms = 30;
        }

        $dupStmt = $pdo->prepare("SELECT id FROM customers WHERE name = :name AND id != :id LIMIT 1");
        $dupStmt->execute([':name' => $name, ':id' => $id]);
        if ($dupStmt->fetch()) {
            json_response(false, null, "Another customer named '{$name}' is already registered.", null, 409);
        }

        $updateStmt = $pdo->prepare("
            UPDATE customers SET 
                name = :name,
                company = :company,
                contact_person = :contact_person,
                email = :email,
                phone = :phone,
                address = :address,
                shipping_address = :shipping_address,
                gst_no = :gst_no,
                credit_limit = :credit_limit,
                payment_terms = :payment_terms,
                is_active = :is_active
            WHERE id = :id
        ");

        $updateStmt->execute([
            ':name'             => $name,
            ':company'          => $company !== '' ? $company : null,
            ':contact_person'   => $contactPerson !== '' ? $contactPerson : null,
            ':email'            => $email !== '' ? $email : null,
            ':phone'            => $phone !== '' ? $phone : null,
            ':address'          => $billingAddress !== '' ? $billingAddress : null,
            ':shipping_address' => $shippingAddress !== '' ? $shippingAddress : null,
            ':gst_no'           => $taxId !== '' ? $taxId : null,
            ':credit_limit'     => number_format((float)$creditLimit, 2, '.', ''),
            ':payment_terms'    => $paymentTerms,
            ':is_active'        => $isActive,
            ':id'               => $id
        ]);

        json_response(true, ['message' => "Customer '{$name}' updated successfully."]);
        break;

    case 'delete':
        if ($method !== 'POST') {
            json_response(false, null, 'Method not allowed.', null, 405);
        }

        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            json_response(false, null, 'Invalid customer ID.', null, 422);
        }

        $existStmt = $pdo->prepare("SELECT id, name FROM customers WHERE id = :id LIMIT 1");
        $existStmt->execute([':id' => $id]);
        $customer = $existStmt->fetch(PDO::FETCH_ASSOC);
        if (!$customer) {
            json_response(false, null, 'Customer account not found.', null, 404);
        }

        // Check for open sales orders
        $soStmt = $pdo->prepare("
            SELECT COUNT(*) FROM sales_orders 
            WHERE customer_id = :id AND status NOT IN ('cancelled')
        ");
        $soStmt->execute([':id' => $id]);
        $openOrdersCount = (int)$soStmt->fetchColumn();

        // Check for unpaid or partial invoices
        $invStmt = $pdo->prepare("
            SELECT COUNT(*) FROM invoices inv
            JOIN sales_orders so ON so.id = inv.so_id
            WHERE so.customer_id = :id AND inv.status IN ('unpaid', 'partial', 'overdue')
        ");
        $invStmt->execute([':id' => $id]);
        $unpaidInvoicesCount = (int)$invStmt->fetchColumn();

        if ($openOrdersCount > 0 || $unpaidInvoicesCount > 0) {
            $reasons = [];
            if ($openOrdersCount > 0) {
                $reasons[] = "{$openOrdersCount} active sales order(s)";
            }
            if ($unpaidInvoicesCount > 0) {
                $reasons[] = "{$unpaidInvoicesCount} unpaid/overdue invoice(s)";
            }

            json_response(
                false, 
                null, 
                "Cannot delete customer '{$customer['name']}' because it is linked to: " . implode(' and ', $reasons) . ".",
                null,
                409
            );
        }

        $deleteStmt = $pdo->prepare("DELETE FROM customers WHERE id = :id");
        $deleteStmt->execute([':id' => $id]);

        json_response(true, ['message' => "Customer '{$customer['name']}' deleted successfully."]);
        break;

    default:
        json_response(false, null, "Unknown or unsupported action '{$action}'.", null, 400);
}
