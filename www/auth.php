<?php
/**
 * Session-based authentication
 * Users are stored in .passwd file (format: username:password_hash)
 * Manage users with: php user-cli.php
 */

// Password file location
define('PASSWD_FILE', getenv('PASSWD_FILE') ?: '/var/www/html/.passwd');

// Session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);

/**
 * Check if user is authenticated
 */
function isAuthenticated() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true;
}

/**
 * Get current username
 */
function getCurrentUser() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return $_SESSION['username'] ?? 'anonymous';
}

/**
 * Load users from password file
 */
function loadUsers() {
    if (!file_exists(PASSWD_FILE)) {
        return [];
    }

    $users = [];
    $lines = file(PASSWD_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, ':') !== false) {
            list($user, $hash) = explode(':', $line, 2);
            $users[trim($user)] = trim($hash);
        }
    }
    return $users;
}

/**
 * Verify user credentials
 */
function verifyCredentials($username, $password) {
    $users = loadUsers();

    if (!isset($users[$username])) {
        return false;
    }

    return password_verify($password, $users[$username]);
}

/**
 * Check if authentication is required (users exist in passwd file)
 */
function isAuthRequired() {
    $users = loadUsers();
    return !empty($users);
}

/**
 * Require authentication - redirect to login if not authenticated
 */
function requireAuth() {
    // If no users configured, allow access
    if (!isAuthRequired()) {
        return;
    }

    if (!isAuthenticated()) {
        $currentPage = $_SERVER['REQUEST_URI'];
        header('Location: login.php?redirect=' . urlencode($currentPage));
        exit;
    }
}

// Auto-require authentication when this file is included
requireAuth();
