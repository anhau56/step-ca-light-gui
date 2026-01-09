<?php
require_once 'auth.php';
session_start();

// CONFIGURATION
define('CA_URL', getenv('CA_URL') ?: 'https://step-ca:9000');
define('PROVISIONER', getenv('CA_PROVISIONER') ?: 'admin');
define('ROOT_CERT', getenv('STEP_ROOT') ?: '/home/step/certs/root_ca.crt');
define('CERT_DIR', '/var/www/html/certs');

// Read password from file or environment
$passwordFile = getenv('CA_PASSWORD_FILE') ?: '/home/step/secrets/password';
if (file_exists($passwordFile)) {
    define('CA_PASSWORD', trim(file_get_contents($passwordFile)));
} else {
    define('CA_PASSWORD', getenv('CA_PASSWORD') ?: '');
}

// Create directory
@mkdir(CERT_DIR, 0755, true);

// Get hostname
$hostname = trim($_POST['hostname'] ?? '');

// Get SANs
$sans = array_filter(array_map('trim', $_POST['san'] ?? []), fn($s) => $s !== '');

// Helper function to redirect with error
function redirectError($msg) {
    $_SESSION['error'] = $msg;
    header('Location: index.php');
    exit;
}

// Validation
if (empty($hostname)) {
    redirectError('Hostname missing');
}

if (empty($sans)) {
    redirectError('At least one SAN is required');
}

if (!preg_match('/^[a-zA-Z0-9.-]+$/', $hostname)) {
    redirectError('Invalid hostname');
}

// Validate each SAN (DNS or IP)
foreach ($sans as $san) {
    if (!preg_match('/^[a-zA-Z0-9.-]+$/', $san) && !filter_var($san, FILTER_VALIDATE_IP)) {
        redirectError("Invalid SAN: $san");
    }
}

// Files
$cert = CERT_DIR . "/{$hostname}.crt";
$key = CERT_DIR . "/{$hostname}.key";

// Cleanup
@unlink($cert);
@unlink($key);

// Build --san flags
$sanFlags = '';
foreach ($sans as $san) {
    $sanFlags .= ' --san ' . escapeshellarg($san);
}

// Create temporary file for password
$pwdFile = tempnam(sys_get_temp_dir(), 'step_pwd_');
file_put_contents($pwdFile, CA_PASSWORD);

// STEP COMMAND - HOSTNAME + SANs
$cmd = sprintf(
    'step ca certificate %s %s %s --provisioner %s --provisioner-password-file %s --ca-url %s --root %s --force%s 2>&1',
    escapeshellarg($hostname),
    escapeshellarg($cert),
    escapeshellarg($key),
    escapeshellarg(PROVISIONER),
    escapeshellarg($pwdFile),
    escapeshellarg(CA_URL),
    escapeshellarg(ROOT_CERT),
    $sanFlags
);

exec($cmd, $output, $code);

// Delete password file
@unlink($pwdFile);

// Verify
if ($code !== 0 || !file_exists($cert)) {
    $errorMsg = implode(' ', $output);
    if (strlen($errorMsg) > 500) {
        $errorMsg = substr($errorMsg, 0, 500) . '...';
    }
    redirectError($errorMsg);
}

chmod($cert, 0644);
chmod($key, 0600);

$_SESSION['success'] = $hostname;
header('Location: index.php');
