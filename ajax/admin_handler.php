<?php
// ========================================================
// SwiftScan Express - Super Admin AJAX Handler
// (ajax/admin_handler.php)
// Handles Hub Creation, Staff Account Provisioning & Master Overrides
// ========================================================

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_guard.php';

// Strict Role Check: Super Administrator only
if (!isLoggedIn()) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized session. Please log in as Administrator.']);
    exit;
}

$currentUser = getCurrentUser();
if ($currentUser['role'] !== 'admin') {
    echo json_encode(['status' => 'error', 'message' => 'Access denied. Tier 1 Super Admin authority required.']);
    exit;
}

$action = $_REQUEST['action'] ?? '';

switch ($action) {
    case 'create_hub':
        handleCreateHub();
        break;

    case 'create_user':
        handleCreateUser();
        break;

    case 'override_status':
        handleOverrideStatus();
        break;

    default:
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid or unknown administrator action.'
        ]);
        exit;
}

/**
 * Handle Adding a New Regional Logistics Hub
 */
function handleCreateHub() {
    $hubName = trim($_POST['hub_name'] ?? '');
    $hubCode = strtoupper(trim($_POST['hub_code'] ?? ''));
    $city    = trim($_POST['city'] ?? '');
    $state   = strtoupper(trim($_POST['state'] ?? ''));
    $pincode = trim($_POST['pincode'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if (empty($hubName) || empty($hubCode) || empty($city) || empty($state) || empty($address)) {
        echo json_encode(['status' => 'error', 'message' => 'Please provide complete hub details and location.']);
        exit;
    }

    try {
        $pdo = getDB();

        // Check if hub code already exists
        $check = $pdo->prepare("SELECT id FROM hubs WHERE UPPER(hub_code) = :code LIMIT 1");
        $check->execute([':code' => $hubCode]);
        if ($check->fetch()) {
            echo json_encode(['status' => 'error', 'message' => "Hub code {$hubCode} is already registered."]);
            exit;
        }

        $sql = "
            INSERT INTO hubs (hub_name, hub_code, city, state, pincode, address, created_at)
            VALUES (:name, :code, :city, :state, :pincode, :address, NOW())
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':name'    => $hubName,
            ':code'    => $hubCode,
            ':city'    => $city,
            ':state'   => $state,
            ':pincode' => $pincode,
            ':address' => $address
        ]);

        echo json_encode([
            'status'   => 'success',
            'message'  => "Logistics Hub '{$hubName}' ({$hubCode}) successfully created!",
            'hub_code' => $hubCode
        ]);
        exit;

    } catch (PDOException $e) {
        error_log('Create Hub DB Error: ' . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Database error while creating hub.']);
        exit;
    }
}

/**
 * Handle Staff / User Account Creation by Admin
 */
function handleCreateUser() {
    $fullName = trim($_POST['full_name'] ?? '');
    $email    = strtolower(trim($_POST['email'] ?? ''));
    $phone    = trim($_POST['phone'] ?? '');
    $role     = trim($_POST['role'] ?? 'customer');
    $hubId    = !empty($_POST['hub_id']) ? (int)$_POST['hub_id'] : null;
    $password = $_POST['password'] ?? '';

    if (empty($fullName) || empty($email) || empty($password)) {
        echo json_encode(['status' => 'error', 'message' => 'Name, email, and temporary password are required.']);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['status' => 'error', 'message' => 'Please enter a valid corporate email.']);
        exit;
    }

    if (!in_array($role, ['admin', 'hub_manager', 'driver', 'customer'])) {
        $role = 'customer';
    }

    try {
        $pdo = getDB();

        // Check duplicate email
        $check = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
        $check->execute([':email' => $email]);
        if ($check->fetch()) {
            echo json_encode(['status' => 'error', 'message' => "An account with email {$email} already exists."]);
            exit;
        }

        $passwordHash = password_hash($password, PASSWORD_BCRYPT);

        $sql = "
            INSERT INTO users (full_name, email, password_hash, role, hub_id, phone, created_at)
            VALUES (:name, :email, :pwd, :role, :hub_id, :phone, NOW())
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':name'    => $fullName,
            ':email'   => $email,
            ':pwd'     => $passwordHash,
            ':role'    => $role,
            ':hub_id'  => $hubId,
            ':phone'   => $phone
        ]);

        echo json_encode([
            'status'  => 'success',
            'message' => "Account for {$fullName} ({$role}) successfully provisioned!",
            'email'   => $email
        ]);
        exit;

    } catch (PDOException $e) {
        error_log('Create User DB Error: ' . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Failed to create user account.']);
        exit;
    }
}

/**
 * Handle Administrative Consignment Status Override
 */
function handleOverrideStatus() {
    global $currentUser;

    $parcelId  = (int)($_POST['parcel_id'] ?? 0);
    $newStatus = trim($_POST['new_status'] ?? '');
    $remarks   = trim($_POST['remarks'] ?? 'Administrative status update by Operations Command.');

    $validStatuses = ['booked', 'picked_up', 'in_transit', 'out_for_delivery', 'delivered', 'failed', 'returned'];
    if (!in_array($newStatus, $validStatuses)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid status selected.']);
        exit;
    }

    try {
        $pdo = getDB();

        $stmt = $pdo->prepare("SELECT id, tracking_number, status FROM parcels WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $parcelId]);
        $parcel = $stmt->fetch();

        if (!$parcel) {
            echo json_encode(['status' => 'error', 'message' => 'Consignment not found.']);
            exit;
        }

        $pdo->beginTransaction();

        $deliveredAtClause = ($newStatus === 'delivered') ? ', delivered_at = NOW()' : '';
        $upd = $pdo->prepare("UPDATE parcels SET status = :status {$deliveredAtClause} WHERE id = :id");
        $upd->execute([':status' => $newStatus, ':id' => $parcelId]);

        $log = $pdo->prepare("
            INSERT INTO tracking_logs (parcel_id, status, hub_id, updated_by, location_note, remarks, scan_timestamp)
            VALUES (:parcel_id, :status, NULL, :updated_by, 'Admin Command Center', :remarks, NOW())
        ");
        $log->execute([
            ':parcel_id'  => $parcelId,
            ':status'     => $newStatus,
            ':updated_by' => $currentUser['id'],
            ':remarks'    => "System Admin Override: {$remarks}"
        ]);

        $pdo->commit();

        echo json_encode([
            'status'          => 'success',
            'message'         => "Consignment {$parcel['tracking_number']} status updated to '{$newStatus}'!",
            'tracking_number' => $parcel['tracking_number'],
            'new_status'      => $newStatus
        ]);
        exit;

    } catch (PDOException $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Admin Override DB Error: ' . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Status override failed.']);
        exit;
    }
}
