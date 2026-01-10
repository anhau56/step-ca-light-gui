<?php
require_once 'auth.php';
require_once 'lib/logger.php';

define('CA_URL', getenv('CA_URL') ?: 'https://step-ca:9000');
define('PROVISIONER', getenv('CA_PROVISIONER') ?: 'admin');
define('ROOT_CERT', getenv('STEP_ROOT') ?: '/home/step/certs/root_ca.crt');
define('CERT_DIR', '/var/www/html/certs');
define('REVOKED_FILE', '/var/www/html/certs/.revoked.json');

// Check if certificate is revoked
function isRevoked($certName) {
    if (!file_exists(REVOKED_FILE)) {
        return false;
    }
    $data = json_decode(file_get_contents(REVOKED_FILE), true);
    return is_array($data) && isset($data[$certName]);
}

// Read password from file
$passwordFile = getenv('CA_PASSWORD_FILE') ?: '/home/step/secrets/password';
if (file_exists($passwordFile)) {
    define('CA_PASSWORD', trim(file_get_contents($passwordFile)));
} else {
    define('CA_PASSWORD', getenv('CA_PASSWORD') ?: '');
}

$name = $_GET['name'] ?? '';

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

// Check if certificate is revoked
if (isRevoked($name)) {
    $_SESSION['error'] = 'Cannot renew a revoked certificate';
    header('Location: download-cert.php');
    exit;
}

// Parse existing certificate to get SANs
$certContent = file_get_contents($certPath);
$certInfo = openssl_x509_parse($certContent);

if (!$certInfo) {
    $_SESSION['error'] = 'Failed to parse certificate';
    header('Location: download-cert.php');
    exit;
}

// Get CN as hostname
$hostname = $certInfo['subject']['CN'] ?? $name;

// Extract SANs
$sans = [];
if (isset($certInfo['extensions']['subjectAltName'])) {
    $sanString = $certInfo['extensions']['subjectAltName'];
    $sanParts = explode(',', $sanString);
    foreach ($sanParts as $part) {
        $part = trim($part);
        // Remove DNS: or IP Address: prefix
        $part = preg_replace('/^(DNS:|IP Address:)\s*/', '', $part);
        if (!empty($part) && $part !== $hostname) {
            $sans[] = $part;
        }
    }
}

// Delete old files
@unlink($certPath);
@unlink($keyPath);

// Build SAN flags
$sanFlags = '';
foreach ($sans as $san) {
    $sanFlags .= ' --san ' . escapeshellarg($san);
}

// Create temporary password file
$pwdFile = tempnam(sys_get_temp_dir(), 'step_pwd_');
file_put_contents($pwdFile, CA_PASSWORD);

// Generate new certificate with same parameters (default 1 year)
$cmd = sprintf(
    'step ca certificate %s %s %s --provisioner %s --provisioner-password-file %s --ca-url %s --root %s --not-after 8760h --force%s 2>&1',
    escapeshellarg($hostname),
    escapeshellarg($certPath),
    escapeshellarg($keyPath),
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
if ($code !== 0 || !file_exists($certPath)) {
    $errorMsg = implode(' ', $output);
    if (strlen($errorMsg) > 500) {
        $errorMsg = substr($errorMsg, 0, 500) . '...';
    }
    AuditLogger::log(LOG_RENEW, $name, ['status' => 'failed', 'error' => $errorMsg]);
    $_SESSION['error'] = 'Failed to renew certificate: ' . $errorMsg;
    header('Location: download-cert.php');
    exit;
}

chmod($certPath, 0644);
chmod($keyPath, 0600);

// Log success
AuditLogger::log(LOG_RENEW, $name, [
    'status' => 'success',
    'sans' => $sans
]);

$_SESSION['success'] = "Certificate '$name' has been renewed";
header('Location: download-cert.php');
