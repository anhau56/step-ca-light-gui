<?php
require_once 'auth.php';
require_once 'lib/logger.php';
session_start();

define('CA_URL', getenv('CA_URL') ?: 'https://step-ca:9000');
define('PROVISIONER', getenv('CA_PROVISIONER') ?: 'admin');
define('ROOT_CERT', getenv('STEP_ROOT') ?: '/home/step/certs/root_ca.crt');
define('CERT_DIR', '/var/www/html/certs');

// Read password from file
$passwordFile = getenv('CA_PASSWORD_FILE') ?: '/home/step/secrets/password';
if (file_exists($passwordFile)) {
    define('CA_PASSWORD', trim(file_get_contents($passwordFile)));
} else {
    define('CA_PASSWORD', getenv('CA_PASSWORD') ?: '');
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    die('Method not allowed');
}

$name = $_POST['name'] ?? '';

if (empty($name)) {
    $_SESSION['error'] = 'Certificate name is required';
    header('Location: download-cert.php');
    exit;
}

// Sanitize
$name = basename($name);
$certPath = CERT_DIR . '/' . $name . '.crt';

if (!file_exists($certPath)) {
    $_SESSION['error'] = 'Certificate not found';
    header('Location: download-cert.php');
    exit;
}

// Parse certificate to get serial number
$certContent = file_get_contents($certPath);
$certInfo = openssl_x509_parse($certContent);

if (!$certInfo) {
    $_SESSION['error'] = 'Failed to parse certificate';
    header('Location: download-cert.php');
    exit;
}

$serialNumber = $certInfo['serialNumber'];

// Create temporary password file
$pwdFile = tempnam(sys_get_temp_dir(), 'step_pwd_');
file_put_contents($pwdFile, CA_PASSWORD);

// Revoke certificate
$cmd = sprintf(
    'step ca revoke %s --provisioner %s --provisioner-password-file %s --ca-url %s --root %s 2>&1',
    escapeshellarg($serialNumber),
    escapeshellarg(PROVISIONER),
    escapeshellarg($pwdFile),
    escapeshellarg(CA_URL),
    escapeshellarg(ROOT_CERT)
);

exec($cmd, $output, $code);

// Delete password file
@unlink($pwdFile);

if ($code !== 0) {
    $errorMsg = implode(' ', $output);
    // Check if already revoked
    if (stripos($errorMsg, 'already revoked') !== false) {
        AuditLogger::log(LOG_REVOKE, $name, ['status' => 'already_revoked']);
        $_SESSION['error'] = 'Certificate is already revoked';
    } else {
        if (strlen($errorMsg) > 500) {
            $errorMsg = substr($errorMsg, 0, 500) . '...';
        }
        AuditLogger::log(LOG_REVOKE, $name, ['status' => 'failed', 'error' => $errorMsg]);
        $_SESSION['error'] = 'Failed to revoke certificate: ' . $errorMsg;
    }
    header('Location: download-cert.php');
    exit;
}

// Log success
AuditLogger::log(LOG_REVOKE, $name, [
    'status' => 'success',
    'serial' => $serialNumber
]);

$_SESSION['success'] = "Certificate '$name' has been revoked";
header('Location: download-cert.php');
