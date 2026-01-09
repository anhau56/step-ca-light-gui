<?php
require_once 'auth.php';
require_once 'lib/logger.php';

define('CERT_DIR', '/var/www/html/certs');
define('ROOT_CERT', getenv('STEP_ROOT') ?: '/home/step/certs/root_ca.crt');
define('INTERMEDIATE_CERT', '/home/step/certs/intermediate_ca.crt');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    die('Method not allowed');
}

$name = $_POST['name'] ?? '';
$password = $_POST['password'] ?? '';

if (empty($name) || empty($password)) {
    header('HTTP/1.1 400 Bad Request');
    die('Name and password are required');
}

if (strlen($password) < 4) {
    header('HTTP/1.1 400 Bad Request');
    die('Password must be at least 4 characters');
}

// Sanitize filename
$name = basename($name);
$certPath = CERT_DIR . '/' . $name . '.crt';
$keyPath = CERT_DIR . '/' . $name . '.key';

// Check files exist
if (!file_exists($certPath) || !file_exists($keyPath)) {
    header('HTTP/1.1 404 Not Found');
    die('Certificate or key not found');
}

// Read certificate and key
$cert = file_get_contents($certPath);
$key = file_get_contents($keyPath);

// Read CA chain
$caChain = [];
if (file_exists(INTERMEDIATE_CERT)) {
    $caChain[] = file_get_contents(INTERMEDIATE_CERT);
}
if (file_exists(ROOT_CERT)) {
    $caChain[] = file_get_contents(ROOT_CERT);
}

// Create PKCS12
$pkcs12 = '';
$success = openssl_pkcs12_export(
    $cert,
    $pkcs12,
    $key,
    $password,
    ['extracerts' => $caChain]
);

if (!$success) {
    AuditLogger::log(LOG_EXPORT, $name, ['status' => 'failed', 'error' => openssl_error_string()]);
    header('HTTP/1.1 500 Internal Server Error');
    die('Failed to create PKCS12 file: ' . openssl_error_string());
}

// Log export
AuditLogger::log(LOG_EXPORT, $name, ['status' => 'success']);

// Send file
header('Content-Type: application/x-pkcs12');
header('Content-Disposition: attachment; filename="' . $name . '.p12"');
header('Content-Length: ' . strlen($pkcs12));
echo $pkcs12;
