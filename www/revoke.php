<?php
require_once 'auth.php';
require_once 'lib/logger.php';
session_start();

define('CA_URL', getenv('CA_URL') ?: 'https://step-ca:9000');
define('ROOT_CERT', getenv('STEP_ROOT') ?: '/home/step/certs/root_ca.crt');
define('CERT_DIR', '/var/www/html/certs');
define('REVOKED_FILE', '/var/www/html/certs/.revoked.json');

// Save revoked certificate to tracking file
function markAsRevoked($name, $serial) {
    $revoked = [];
    if (file_exists(REVOKED_FILE)) {
        $data = json_decode(file_get_contents(REVOKED_FILE), true);
        if (is_array($data)) {
            $revoked = $data;
        }
    }
    $revoked[$name] = [
        'serial' => $serial,
        'revoked_at' => date('c')
    ];
    file_put_contents(REVOKED_FILE, json_encode($revoked, JSON_PRETTY_PRINT));
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
$keyPath = CERT_DIR . '/' . $name . '.key';

if (!file_exists($certPath)) {
    $_SESSION['error'] = 'Certificate not found';
    header('Location: download-cert.php');
    exit;
}

if (!file_exists($keyPath)) {
    $_SESSION['error'] = 'Private key not found - cannot revoke without key';
    header('Location: download-cert.php');
    exit;
}

// Parse certificate to get serial number
$certContent = file_get_contents($certPath);
$certInfo = openssl_x509_parse($certContent);

if (!$certInfo || !isset($certInfo['serialNumberHex'])) {
    $_SESSION['error'] = 'Failed to parse certificate';
    header('Location: download-cert.php');
    exit;
}

$serial = $certInfo['serialNumberHex'];

// Revoke using mTLS with cert and key
$cmd = sprintf(
    'step ca revoke --cert %s --key %s --ca-url %s --root %s 2>&1',
    escapeshellarg($certPath),
    escapeshellarg($keyPath),
    escapeshellarg(CA_URL),
    escapeshellarg(ROOT_CERT)
);

exec($cmd, $output, $code);

$outputStr = implode(' ', $output);

// Check for success or already revoked
if ($code !== 0) {
    // Check if already revoked
    if (stripos($outputStr, 'already revoked') !== false) {
        markAsRevoked($name, $serial);
        AuditLogger::log(LOG_REVOKE, $name, ['status' => 'already_revoked', 'serial' => $serial]);
        $_SESSION['success'] = "Certificate '$name' was already revoked";
    } else {
        if (strlen($outputStr) > 500) {
            $outputStr = substr($outputStr, 0, 500) . '...';
        }
        AuditLogger::log(LOG_REVOKE, $name, ['status' => 'failed', 'error' => $outputStr, 'serial' => $serial]);
        $_SESSION['error'] = 'Failed to revoke certificate: ' . $outputStr;
    }
} else {
    markAsRevoked($name, $serial);
    AuditLogger::log(LOG_REVOKE, $name, ['status' => 'success', 'serial' => $serial]);
    $_SESSION['success'] = "Certificate '$name' has been revoked";
}

header('Location: download-cert.php');
