<?php
// ========================================================
// SwiftScan Express - Session Logout (logout.php)
// Destroys session cleanly and redirects to login
// ========================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$_SESSION = [];

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

session_destroy();

$redirectUrl = (defined('BASE_URL') ? BASE_URL : '/courier_service_system/') . 'login.php?logged_out=1';
header("Location: " . $redirectUrl);
exit();
