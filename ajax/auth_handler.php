<?php
// ========================================================
// SwiftScan Express - Authentication AJAX Handler
// (ajax/auth_handler.php)
// Handles Customer Registration, Customer Login & Session Logout
// ========================================================

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_guard.php';

$action = $_REQUEST['action'] ?? '';

switch ($action) {
    case 'customer_register':
        handleCustomerRegister();
        break;

    case 'customer_login':
        handleCustomerLogin();
        break;

    case 'staff_login':
        handleStaffLogin();
        break;

    case 'logout':
        handleLogout();
        break;

    default:
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid or unknown authentication action.'
        ]);
        exit;
}

/**
 * Handle Public Customer Registration
 */
function handleCustomerRegister() {
    $fullName        = trim($_POST['full_name'] ?? '');
    $email           = trim($_POST['email'] ?? '');
    $phone           = trim($_POST['phone'] ?? '');
    $password        = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // Validation
    if (empty($fullName) || strlen($fullName) < 3) {
        echo json_encode(['status' => 'error', 'message' => 'Please enter your full name (at least 3 characters).']);
        exit;
    }

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['status' => 'error', 'message' => 'Please provide a valid email address.']);
        exit;
    }

    if (empty($phone) || strlen($phone) < 7) {
        echo json_encode(['status' => 'error', 'message' => 'Please enter a valid contact phone number.']);
        exit;
    }

    if (empty($password) || strlen($password) < 6) {
        echo json_encode(['status' => 'error', 'message' => 'Password must be at least 6 characters long.']);
        exit;
    }

    if ($password !== $confirmPassword) {
        echo json_encode(['status' => 'error', 'message' => 'Passwords do not match. Please re-enter.']);
        exit;
    }

    try {
        $pdo = getDB();

        // Check if email is already in use
        $checkStmt = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
        $checkStmt->execute([':email' => strtolower($email)]);
        if ($checkStmt->fetch()) {
            echo json_encode(['status' => 'error', 'message' => 'An account with this email address already exists. Please sign in instead.']);
            exit;
        }

        // Hash password securely with bcrypt
        $passwordHash = password_hash($password, PASSWORD_BCRYPT);

        // Insert new customer (Strict role enforcement: only 'customer')
        $insertSql = "
            INSERT INTO users (full_name, email, password_hash, role, hub_id, phone, created_at)
            VALUES (:full_name, :email, :password_hash, 'customer', NULL, :phone, NOW())
        ";
        $insertStmt = $pdo->prepare($insertSql);
        $insertStmt->execute([
            ':full_name'     => $fullName,
            ':email'         => strtolower($email),
            ':password_hash' => $passwordHash,
            ':phone'         => $phone
        ]);

        $newUserId = (int)$pdo->lastInsertId();

        // Automatically establish session upon registration
        $_SESSION['user_id']       = $newUserId;
        $_SESSION['user_name']     = $fullName;
        $_SESSION['user_email']    = strtolower($email);
        $_SESSION['user_role']     = 'customer';
        $_SESSION['user_hub_id']   = null;
        $_SESSION['user_hub_name'] = null;

        $redirectUrl = getDashboardUrl('customer');

        echo json_encode([
            'status'   => 'success',
            'message'  => 'Registration successful! Welcome to SwiftScan Express.',
            'redirect' => $redirectUrl
        ]);
        exit;

    } catch (PDOException $e) {
        error_log('Register DB Error: ' . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Database error while creating your account. Please try again.']);
        exit;
    }
}

/**
 * Handle Public Customer Login
 */
function handleCustomerLogin() {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        echo json_encode(['status' => 'error', 'message' => 'Please enter both your email and password.']);
        exit;
    }

    try {
        $pdo = getDB();

        $stmt = $pdo->prepare("
            SELECT u.id, u.full_name, u.email, u.password_hash, u.role, u.hub_id, h.hub_name
            FROM users u
            LEFT JOIN hubs h ON u.hub_id = h.id
            WHERE u.email = :email
            LIMIT 1
        ");
        $stmt->execute([':email' => strtolower($email)]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid email or password. Please verify and try again.']);
            exit;
        }

        // Establish User Session
        $_SESSION['user_id']       = (int)$user['id'];
        $_SESSION['user_name']     = $user['full_name'];
        $_SESSION['user_email']    = $user['email'];
        $_SESSION['user_role']     = $user['role'];
        $_SESSION['user_hub_id']   = $user['hub_id'];
        $_SESSION['user_hub_name'] = $user['hub_name'] ?? null;

        // Route to the dashboard appropriate for their role
        $redirectUrl = getDashboardUrl($user['role']);

        echo json_encode([
            'status'   => 'success',
            'message'  => 'Login successful! Redirecting to dashboard...',
            'role'     => $user['role'],
            'redirect' => $redirectUrl
        ]);
        exit;

    } catch (PDOException $e) {
        error_log('Login DB Error: ' . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Database connection error during login.']);
        exit;
    }
}

/**
 * Handle Operations & Staff Login (for Drivers, Hub Managers, Admins)
 */
function handleStaffLogin() {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        echo json_encode(['status' => 'error', 'message' => 'Please enter your staff ID / email and password.']);
        exit;
    }

    try {
        $pdo = getDB();

        $stmt = $pdo->prepare("
            SELECT u.id, u.full_name, u.email, u.password_hash, u.role, u.hub_id, h.hub_name
            FROM users u
            LEFT JOIN hubs h ON u.hub_id = h.id
            WHERE u.email = :email
            LIMIT 1
        ");
        $stmt->execute([':email' => strtolower($email)]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid staff credentials. Access denied.']);
            exit;
        }

        // Staff gateway strictly allows admin, hub_manager, driver
        if ($user['role'] === 'customer') {
            echo json_encode([
                'status' => 'error', 
                'message' => 'Customer accounts must sign in via the Customer Portal, not the Operations Gateway.'
            ]);
            exit;
        }

        // Establish Staff Session
        $_SESSION['user_id']       = (int)$user['id'];
        $_SESSION['user_name']     = $user['full_name'];
        $_SESSION['user_email']    = $user['email'];
        $_SESSION['user_role']     = $user['role'];
        $_SESSION['user_hub_id']   = $user['hub_id'];
        $_SESSION['user_hub_name'] = $user['hub_name'] ?? null;

        $redirectUrl = getDashboardUrl($user['role']);

        echo json_encode([
            'status'   => 'success',
            'message'  => 'Staff authentication verified. Access granted.',
            'role'     => $user['role'],
            'redirect' => $redirectUrl
        ]);
        exit;

    } catch (PDOException $e) {
        error_log('Staff Login DB Error: ' . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Database error during staff authentication.']);
        exit;
    }
}

/**
 * Handle Session Logout
 */
function handleLogout() {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();

    echo json_encode([
        'status'   => 'success',
        'message'  => 'You have been safely signed out.',
        'redirect' => (defined('BASE_URL') ? BASE_URL : '/courier_service_system/') . 'login.php'
    ]);
    exit;
}
