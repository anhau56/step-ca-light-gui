<?php
/**
 * Logout - Destroy session and redirect to login
 */

session_start();

// Log logout if logger available
if (file_exists('lib/logger.php') && isset($_SESSION['username'])) {
    require_once 'lib/logger.php';
    AuditLogger::log('logout', null, ['username' => $_SESSION['username']]);
}

// Destroy session
$_SESSION = [];

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

// Redirect to login
header('Location: login.php');
exit;
