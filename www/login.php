<?php
/**
 * Login page - Session-based authentication
 */

// Include auth functions but don't auto-require (we're on the login page)
define('PASSWD_FILE', getenv('PASSWD_FILE') ?: '/var/www/html/.passwd');

session_start();

// Load users
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

$users = loadUsers();
$error = '';
$redirect = $_GET['redirect'] ?? 'index.php';

// If no users configured, redirect to main page
if (empty($users)) {
    header('Location: index.php');
    exit;
}

// If already authenticated, redirect
if (isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true) {
    header('Location: ' . $redirect);
    exit;
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password';
    } elseif (isset($users[$username]) && password_verify($password, $users[$username])) {
        // Successful login
        session_regenerate_id(true);
        $_SESSION['authenticated'] = true;
        $_SESSION['username'] = $username;
        $_SESSION['login_time'] = time();

        // Log successful login
        if (file_exists('lib/logger.php')) {
            require_once 'lib/logger.php';
            AuditLogger::log('login', null, ['status' => 'success']);
        }

        header('Location: ' . $redirect);
        exit;
    } else {
        $error = 'Invalid username or password';

        // Log failed login attempt
        if (file_exists('lib/logger.php')) {
            require_once 'lib/logger.php';
            AuditLogger::log('login', null, ['status' => 'failed', 'username' => $username]);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Step CA</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .login-container {
            max-width: 400px;
            margin: 0 auto;
        }

        .login-box {
            background: var(--bg-container);
            border-radius: 16px;
            padding: 40px;
            box-shadow: var(--shadow-container);
        }

        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .login-header .logo {
            max-width: 180px;
            margin-bottom: 20px;
        }

        .login-header h1 {
            color: var(--text-primary);
            font-size: 24px;
            margin-bottom: 8px;
        }

        .login-header p {
            color: var(--text-secondary);
            font-size: 14px;
        }

        .login-form .form-group {
            margin-bottom: 20px;
        }

        .login-form label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-primary);
            font-weight: 500;
            font-size: 14px;
        }

        .login-form input[type="text"],
        .login-form input[type="password"] {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid var(--border-input);
            border-radius: 8px;
            font-size: 16px;
            background: var(--bg-input);
            color: var(--text-primary);
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .login-form input:focus {
            outline: none;
            border-color: var(--border-input-focus);
            box-shadow: 0 0 0 3px rgba(101, 188, 123, 0.1);
        }

        .login-form button {
            width: 100%;
            padding: 14px;
            background: var(--primary-gradient);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .login-form button:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-button);
        }

        .login-error {
            background: var(--error-bg);
            color: var(--error-text);
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            border: 1px solid var(--error-border);
        }

        .login-footer {
            text-align: center;
            margin-top: 24px;
            padding-top: 24px;
            border-top: 1px solid var(--border-color);
        }

        .login-footer p {
            color: var(--text-muted);
            font-size: 12px;
        }

        .secure-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: var(--text-muted);
            font-size: 12px;
            margin-top: 16px;
        }

        .secure-badge svg {
            width: 14px;
            height: 14px;
        }
    </style>
</head>
<body>
    <div class="container login-container">
        <div class="login-box">
            <div class="login-header">
                <div class="icon">🔐</div>
                <h1>Welcome Back</h1>
                <p>Sign in to manage your certificates</p>
            </div>

            <?php if ($error): ?>
                <div class="login-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" class="login-form" autocomplete="off">
                <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">

                <div class="form-group">
                    <label for="username">Username</label>
                    <input
                        type="text"
                        id="username"
                        name="username"
                        placeholder="Enter your username"
                        required
                        autofocus
                        value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                    >
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Enter your password"
                        required
                    >
                </div>

                <button type="submit">Sign In</button>

                <div class="secure-badge">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                    </svg>
                    Secure connection
                </div>
            </form>

            <div class="login-footer">
                <p>Contact your administrator if you need access</p>
            </div>
        </div>
    </div>

    <script src="js/app.js"></script>
</body>
</html>
