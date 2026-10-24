<?php
// ========================================================
// Authentication & Role-Based Access Control Guard
// includes/auth_guard.php
// ========================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check if a user is currently logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Get current logged in user information
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    return [
        'id'        => $_SESSION['user_id'] ?? null,
        'full_name' => $_SESSION['user_name'] ?? 'User',
        'email'     => $_SESSION['user_email'] ?? '',
        'role'      => $_SESSION['user_role'] ?? 'customer',
        'hub_id'    => $_SESSION['user_hub_id'] ?? null,
        'hub_name'  => $_SESSION['user_hub_name'] ?? null
    ];
}

/**
 * Require user to be authenticated, otherwise redirect to login
 */
function requireLogin($redirectUrl = null) {
    if (!isLoggedIn()) {
        $target = $redirectUrl ?? (defined('BASE_URL') ? BASE_URL . 'login.php' : '../login.php');
        header("Location: " . $target);
        exit();
    }
}

/**
 * Require specific role(s) to access page
 */
function requireRole($allowedRoles = []) {
    requireLogin();
    $role = $_SESSION['user_role'] ?? '';

    if (!in_array($role, (array)$allowedRoles)) {
        // Redirect to appropriate dashboard based on their real role
        $redirect = getDashboardUrl($role);
        header("Location: " . $redirect);
        exit();
    }
}

/**
 * Get the proper dashboard URL for a given role
 */
function getDashboardUrl($role) {
    $base = defined('BASE_URL') ? BASE_URL : '/courier_service_system/';
    switch ($role) {
        case 'admin':
            return $base . 'admin/';
        case 'hub_manager':
            return $base . 'hub/';
        case 'driver':
            return $base . 'driver/';
        default:
            return $base . 'customer/';
    }
}
