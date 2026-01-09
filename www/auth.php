<?php
// Password file location
$PASSWD_FILE = getenv('PASSWD_FILE') ?: '/var/www/html/.passwd';

function authenticate($passwdFile) {
    if (!file_exists($passwdFile)) {
        return true; // No password file = no auth required
    }

    // Read password file (format: username:password_hash)
    $users = [];
    $lines = file($passwdFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, ':') !== false) {
            list($user, $hash) = explode(':', $line, 2);
            $users[trim($user)] = trim($hash);
        }
    }

    if (empty($users)) {
        return true; // Empty file = no auth required
    }

    // Check credentials
    if (isset($_SERVER['PHP_AUTH_USER']) && isset($_SERVER['PHP_AUTH_PW'])) {
        $username = $_SERVER['PHP_AUTH_USER'];
        $password = $_SERVER['PHP_AUTH_PW'];

        if (isset($users[$username]) && password_verify($password, $users[$username])) {
            return true;
        }
    }

    // Request authentication
    header('WWW-Authenticate: Basic realm="Step CA GUI"');
    header('HTTP/1.0 401 Unauthorized');
    echo 'Authentication required';
    exit;
}

authenticate($PASSWD_FILE);
