#!/usr/bin/env php
<?php
/**
 * User Management CLI for Step CA GUI
 *
 * Usage:
 *   php user-cli.php add <username>           Add a new user (will prompt for password)
 *   php user-cli.php delete <username>        Delete a user
 *   php user-cli.php passwd <username>        Change user password
 *   php user-cli.php list                     List all users
 *   php user-cli.php hash <password>          Generate password hash (for scripting)
 *
 * Examples:
 *   docker exec -it step-ca-gui php /var/www/html/user-cli.php add admin
 *   docker exec -it step-ca-gui php /var/www/html/user-cli.php list
 */

define('PASSWD_FILE', getenv('PASSWD_FILE') ?: '/var/www/html/.passwd');

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
 * Save users to password file
 */
function saveUsers($users) {
    $content = '';
    foreach ($users as $username => $hash) {
        $content .= "$username:$hash\n";
    }
    $dir = dirname(PASSWD_FILE);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    file_put_contents(PASSWD_FILE, $content);
    chmod(PASSWD_FILE, 0600);
}

/**
 * Read password from stdin (hidden input on Unix)
 */
function readPassword($prompt = 'Password: ') {
    // Check if we're in a TTY
    if (function_exists('posix_isatty') && posix_isatty(STDIN)) {
        // Disable echo
        system('stty -echo');
        echo $prompt;
        $password = trim(fgets(STDIN));
        system('stty echo');
        echo "\n";
        return $password;
    } else {
        // Non-interactive mode (piped input)
        echo $prompt;
        return trim(fgets(STDIN));
    }
}

/**
 * Validate username
 */
function validateUsername($username) {
    if (strlen($username) < 3) {
        return 'Username must be at least 3 characters';
    }
    if (strlen($username) > 32) {
        return 'Username must be at most 32 characters';
    }
    if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_-]*$/', $username)) {
        return 'Username must start with a letter and contain only letters, numbers, underscores and hyphens';
    }
    return null;
}

/**
 * Validate password
 */
function validatePassword($password) {
    if (strlen($password) < 8) {
        return 'Password must be at least 8 characters';
    }
    return null;
}

// Main CLI logic
if (php_sapi_name() !== 'cli') {
    die('This script must be run from the command line');
}

$argc = $_SERVER['argc'];
$argv = $_SERVER['argv'];

if ($argc < 2) {
    echo "Usage: php user-cli.php <command> [arguments]\n\n";
    echo "Commands:\n";
    echo "  add <username>      Add a new user\n";
    echo "  delete <username>   Delete a user\n";
    echo "  passwd <username>   Change user password\n";
    echo "  list                List all users\n";
    echo "  hash <password>     Generate password hash\n";
    echo "\n";
    echo "Password file: " . PASSWD_FILE . "\n";
    exit(1);
}

$command = $argv[1];

switch ($command) {
    case 'add':
        if ($argc < 3) {
            echo "Usage: php user-cli.php add <username>\n";
            exit(1);
        }
        $username = $argv[2];

        // Validate username
        $error = validateUsername($username);
        if ($error) {
            echo "Error: $error\n";
            exit(1);
        }

        // Check if user exists
        $users = loadUsers();
        if (isset($users[$username])) {
            echo "Error: User '$username' already exists\n";
            exit(1);
        }

        // Read password
        $password = readPassword('Enter password: ');
        $error = validatePassword($password);
        if ($error) {
            echo "Error: $error\n";
            exit(1);
        }

        $confirm = readPassword('Confirm password: ');
        if ($password !== $confirm) {
            echo "Error: Passwords do not match\n";
            exit(1);
        }

        // Add user
        $users[$username] = password_hash($password, PASSWORD_DEFAULT);
        saveUsers($users);
        echo "User '$username' created successfully\n";
        break;

    case 'delete':
        if ($argc < 3) {
            echo "Usage: php user-cli.php delete <username>\n";
            exit(1);
        }
        $username = $argv[2];

        $users = loadUsers();
        if (!isset($users[$username])) {
            echo "Error: User '$username' not found\n";
            exit(1);
        }

        unset($users[$username]);
        saveUsers($users);
        echo "User '$username' deleted successfully\n";
        break;

    case 'passwd':
        if ($argc < 3) {
            echo "Usage: php user-cli.php passwd <username>\n";
            exit(1);
        }
        $username = $argv[2];

        $users = loadUsers();
        if (!isset($users[$username])) {
            echo "Error: User '$username' not found\n";
            exit(1);
        }

        // Read new password
        $password = readPassword('Enter new password: ');
        $error = validatePassword($password);
        if ($error) {
            echo "Error: $error\n";
            exit(1);
        }

        $confirm = readPassword('Confirm new password: ');
        if ($password !== $confirm) {
            echo "Error: Passwords do not match\n";
            exit(1);
        }

        $users[$username] = password_hash($password, PASSWORD_DEFAULT);
        saveUsers($users);
        echo "Password for '$username' updated successfully\n";
        break;

    case 'list':
        $users = loadUsers();
        if (empty($users)) {
            echo "No users configured (authentication disabled)\n";
        } else {
            echo "Users (" . count($users) . "):\n";
            foreach (array_keys($users) as $username) {
                echo "  - $username\n";
            }
        }
        break;

    case 'hash':
        if ($argc < 3) {
            echo "Usage: php user-cli.php hash <password>\n";
            exit(1);
        }
        $password = $argv[2];
        echo password_hash($password, PASSWORD_DEFAULT) . "\n";
        break;

    default:
        echo "Unknown command: $command\n";
        echo "Run 'php user-cli.php' without arguments for help\n";
        exit(1);
}
