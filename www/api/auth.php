<?php
/**
 * API Authentication
 * Supports both API Key and Basic Auth
 */

define('API_KEYS_FILE', '/var/www/html/.apikeys');

function apiAuthenticate() {
    // Check for API Key in header
    $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
    if ($apiKey && validateApiKey($apiKey)) {
        return true;
    }

    // Check for Basic Auth
    if (isset($_SERVER['PHP_AUTH_USER']) && isset($_SERVER['PHP_AUTH_PW'])) {
        if (validateBasicAuth($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'])) {
            return true;
        }
    }

    // Authentication failed
    header('HTTP/1.1 401 Unauthorized');
    header('WWW-Authenticate: Basic realm="Step CA API"');
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

function validateApiKey($key) {
    if (!file_exists(API_KEYS_FILE)) {
        return false;
    }

    $lines = file(API_KEYS_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        // Format: key_name:api_key
        $parts = explode(':', $line, 2);
        if (count($parts) === 2 && trim($parts[1]) === $key) {
            return true;
        }
    }

    return false;
}

function validateBasicAuth($username, $password) {
    $passwdFile = '/var/www/html/.passwd';

    if (!file_exists($passwdFile)) {
        return false;
    }

    $lines = file($passwdFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, ':') !== false) {
            list($user, $hash) = explode(':', $line, 2);
            if (trim($user) === $username && password_verify($password, trim($hash))) {
                return true;
            }
        }
    }

    return false;
}

function jsonResponse($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

function jsonError($message, $code = 400) {
    jsonResponse(['error' => $message], $code);
}
