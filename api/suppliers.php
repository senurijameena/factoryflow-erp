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
            $where[] = "(s.name LIKE :search_name OR s.contact_person LIKE :search_contact OR s.email LIKE :search_email OR s.phone LIKE :search_phone OR s.gst_no LIKE :search_gst)";
            $params[':search_name'] = '%' . $search . '%';
            $params[':search_contact'] = '%' . $search . '%';
            $params[':search_email'] = '%' . $search . '%';
            $params[':search_phone'] = '%' . $search . '%';
            $params[':search_gst'] = '%' . $search . '%';
        }

        if ($status === 'active') {
            $where[] = "s.is_active = 1";
        } elseif ($status === 'inactive') {
            $where[] = "s.is_active = 0";
        }

        $whereClause = implode(' AND ', $where);

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM suppliers s WHERE {$whereClause}");
        $countStmt->execute($params);
        $totalItems = (int)$countStmt->fetchColumn();

        $sql = "
            SELECT s.*,
                   (SELECT COUNT(*) FROM purchase_orders po WHERE po.supplier_id = s.id) AS po_count,
                   (SELECT COUNT(*) FROM materials m WHERE m.supplier_id = s.id) AS materials_count
            FROM suppliers s
            WHERE {$whereClause}
            ORDER BY s.name ASC
            LIMIT :offset, :per_page
        ";

        $stmt = $pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue(':per_page', $perPage, PDO::PARAM_INT);
        $stmt->execute();
        $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $totalPages = (int)ceil($totalItems / $perPage);

        json_response(true, $suppliers, null, [
            'page'        => $page,
            'per_page'    => $perPage,
            'total_items' => $totalItems,
            'total_pages' => $totalPages
        ]);
        break;

    case 'get':
        $id = (int)($_GET['id'] ?? $input['id'] ?? 0);
        if ($id <= 0) {
            json_response(false, null, 'Invalid supplier ID.', null, 422);
        }

        $stmt = $pdo->prepare("
            SELECT s.*,
                   (SELECT COUNT(*) FROM purchase_orders po WHERE po.supplier_id = s.id) AS po_count,
                   (SELECT COUNT(*) FROM materials m WHERE m.supplier_id = s.id) AS materials_count
            FROM suppliers s
            WHERE s.id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $supplier = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$supplier) {
            json_response(false, null, 'Supplier record not found.', null, 404);
        }

        json_response(true, $supplier);
        break;

    case 'create':
        if ($method !== 'POST') {
            json_response(false, null, 'Method not allowed.', null, 405);
        }

        $name = trim((string)($input['company_name'] ?? $input['name'] ?? ''));
        $contactPerson = trim((string)($input['contact_person'] ?? ''));
        $email = trim((string)($input['email'] ?? ''));
        $phone = trim((string)($input['phone'] ?? ''));
        $address = trim((string)($input['address'] ?? ''));
        $gstNo = trim((string)($input['gst_no'] ?? $input['tax_number'] ?? ''));
        $paymentTerms = filter_var($input['payment_terms'] ?? 30, FILTER_VALIDATE_INT);

        if ($name === '') {
            json_response(false, null, 'Company / Supplier name is required.', null, 422);
        }
        if (mb_strlen($name) > 150) {
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

        if ($gstNo !== '' && mb_strlen($gstNo) > 50) {
            json_response(false, null, 'Tax / GST registration number cannot exceed 50 characters.', null, 422);
        }

        if ($paymentTerms === false || $paymentTerms < 0) {
            $paymentTerms = 30;
        }

        $dupStmt = $pdo->prepare("SELECT id FROM suppliers WHERE name = :name LIMIT 1");
        $dupStmt->execute([':name' => $name]);
        if ($dupStmt->fetch()) {
            json_response(false, null, "A supplier with the name '{$name}' is already registered.", null, 409);
        }

        $insertStmt = $pdo->prepare("
            INSERT INTO suppliers (
                name, contact_person, email, phone, address, gst_no, payment_terms, is_active
            ) VALUES (
                :name, :contact_person, :email, :phone, :address, :gst_no, :payment_terms, 1
            )
        ");

        $insertStmt->execute([
            ':name'           => $name,
            ':contact_person' => $contactPerson !== '' ? $contactPerson : null,
            ':email'          => $email !== '' ? $email : null,
            ':phone'          => $phone !== '' ? $phone : null,
            ':address'        => $address !== '' ? $address : null,
            ':gst_no'         => $gstNo !== '' ? $gstNo : null,
            ':payment_terms'  => $paymentTerms
        ]);

        $newId = (int)$pdo->lastInsertId();

        json_response(true, [
            'id'      => $newId,
            'name'    => $name,
            'message' => "Supplier '{$name}' registered successfully."
        ], null, 201);
        break;

    case 'update':
        if ($method !== 'POST') {
            json_response(false, null, 'Method not allowed.', null, 405);
        }

        $id = (int)($input['id'] ?? 0);
        $name = trim((string)($input['company_name'] ?? $input['name'] ?? ''));
        $contactPerson = trim((string)($input['contact_person'] ?? ''));
        $email = trim((string)($input['email'] ?? ''));
        $phone = trim((string)($input['phone'] ?? ''));
        $address = trim((string)($input['address'] ?? ''));
        $gstNo = trim((string)($input['gst_no'] ?? $input['tax_number'] ?? ''));
        $paymentTerms = filter_var($input['payment_terms'] ?? 30, FILTER_VALIDATE_INT);
        $isActive = isset($input['is_active']) ? (int)(bool)$input['is_active'] : 1;

        if ($id <= 0) {
            json_response(false, null, 'Invalid supplier ID.', null, 422);
        }

        $existStmt = $pdo->prepare("SELECT id FROM suppliers WHERE id = :id LIMIT 1");
        $existStmt->execute([':id' => $id]);
        if (!$existStmt->fetch()) {
            json_response(false, null, 'Supplier record not found.', null, 404);
        }

        if ($name === '') {
            json_response(false, null, 'Company / Supplier name is required.', null, 422);
        }
        if (mb_strlen($name) > 150) {
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

        if ($gstNo !== '' && mb_strlen($gstNo) > 50) {
            json_response(false, null, 'Tax / GST registration number cannot exceed 50 characters.', null, 422);
        }

        if ($paymentTerms === false || $paymentTerms < 0) {
            $paymentTerms = 30;
        }

        $dupStmt = $pdo->prepare("SELECT id FROM suppliers WHERE name = :name AND id != :id LIMIT 1");
        $dupStmt->execute([':name' => $name, ':id' => $id]);
        if ($dupStmt->fetch()) {
            json_response(false, null, "Another supplier named '{$name}' is already registered.", null, 409);
        }

        $updateStmt = $pdo->prepare("
            UPDATE suppliers SET 
                name = :name,
                contact_person = :contact_person,
                email = :email,
                phone = :phone,
                address = :address,
                gst_no = :gst_no,
                payment_terms = :payment_terms,
                is_active = :is_active
            WHERE id = :id
        ");

        $updateStmt->execute([
            ':name'           => $name,
            ':contact_person' => $contactPerson !== '' ? $contactPerson : null,
            ':email'          => $email !== '' ? $email : null,
            ':phone'          => $phone !== '' ? $phone : null,
            ':address'        => $address !== '' ? $address : null,
            ':gst_no'         => $gstNo !== '' ? $gstNo : null,
            ':payment_terms'  => $paymentTerms,
            ':is_active'      => $isActive,
            ':id'             => $id
        ]);

        json_response(true, ['message' => "Supplier '{$name}' updated successfully."]);
        break;

    case 'delete':
        if ($method !== 'POST') {
            json_response(false, null, 'Method not allowed.', null, 405);
        }

        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            json_response(false, null, 'Invalid supplier ID.', null, 422);
        }

        $existStmt = $pdo->prepare("SELECT id, name FROM suppliers WHERE id = :id LIMIT 1");
        $existStmt->execute([':id' => $id]);
        $supplier = $existStmt->fetch(PDO::FETCH_ASSOC);
        if (!$supplier) {
            json_response(false, null, 'Supplier record not found.', null, 404);
        }

        $poStmt = $pdo->prepare("SELECT COUNT(*) FROM purchase_orders WHERE supplier_id = :id");
        $poStmt->execute([':id' => $id]);
        $poCount = (int)$poStmt->fetchColumn();

        if ($poCount > 0) {
            json_response(
                false, 
                null, 
                "Cannot delete supplier '{$supplier['name']}' because {$poCount} purchase order(s) are linked to this vendor.",
                null,
                409
            );
        }

        $deleteStmt = $pdo->prepare("DELETE FROM suppliers WHERE id = :id");
        $deleteStmt->execute([':id' => $id]);

        json_response(true, ['message' => "Supplier '{$supplier['name']}' deleted successfully."]);
        break;

    default:
        json_response(false, null, "Unknown or unsupported action '{$action}'.", null, 400);
}
