<?php
/**
 * Step CA GUI - REST API
 *
 * Endpoints:
 * GET    /api/                      - API info
 * GET    /api/certificates          - List all certificates
 * POST   /api/certificates          - Generate new certificate
 * GET    /api/certificates/{name}   - Get certificate details
 * DELETE /api/certificates/{name}   - Delete certificate
 * POST   /api/certificates/{name}/revoke - Revoke certificate
 * POST   /api/certificates/{name}/renew  - Renew certificate
 * POST   /api/certificates/{name}/export - Export as PFX
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../lib/logger.php';

// Configuration
define('CERT_DIR', '/var/www/html/certs');
define('CA_URL', getenv('CA_URL') ?: 'https://step-ca:9000');
define('PROVISIONER', getenv('CA_PROVISIONER') ?: 'admin');
define('ROOT_CERT', getenv('STEP_ROOT') ?: '/home/step/certs/root_ca.crt');
define('INTERMEDIATE_CERT', '/home/step/certs/intermediate_ca.crt');

$passwordFile = getenv('CA_PASSWORD_FILE') ?: '/home/step/secrets/password';
if (file_exists($passwordFile)) {
    define('CA_PASSWORD', trim(file_get_contents($passwordFile)));
} else {
    define('CA_PASSWORD', getenv('CA_PASSWORD') ?: '');
}

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key, Authorization');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Authenticate
apiAuthenticate();

// Parse request
$method = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];
$path = parse_url($uri, PHP_URL_PATH);

// Remove /api prefix
$path = preg_replace('#^/api#', '', $path);
$path = trim($path, '/');
$segments = $path ? explode('/', $path) : [];

// Route request
if (empty($segments) || $segments[0] === '') {
    // GET /api/ - API info
    jsonResponse([
        'name' => 'Step CA GUI API',
        'version' => '1.0',
        'endpoints' => [
            'GET /api/certificates' => 'List all certificates',
            'POST /api/certificates' => 'Generate new certificate',
            'GET /api/certificates/{name}' => 'Get certificate details',
            'DELETE /api/certificates/{name}' => 'Delete certificate',
            'POST /api/certificates/{name}/revoke' => 'Revoke certificate',
            'POST /api/certificates/{name}/renew' => 'Renew certificate',
            'POST /api/certificates/{name}/export' => 'Export as PFX'
        ]
    ]);
}

if ($segments[0] === 'certificates') {
    $certName = $segments[1] ?? null;
    $action = $segments[2] ?? null;

    if ($certName === null) {
        // /api/certificates
        if ($method === 'GET') {
            listCertificates();
        } elseif ($method === 'POST') {
            generateCertificate();
        } else {
            jsonError('Method not allowed', 405);
        }
    } else {
        // /api/certificates/{name}
        $certName = urldecode($certName);

        if ($action === null) {
            if ($method === 'GET') {
                getCertificateDetails($certName);
            } elseif ($method === 'DELETE') {
                deleteCertificate($certName);
            } else {
                jsonError('Method not allowed', 405);
            }
        } elseif ($action === 'revoke' && $method === 'POST') {
            revokeCertificate($certName);
        } elseif ($action === 'renew' && $method === 'POST') {
            renewCertificate($certName);
        } elseif ($action === 'export' && $method === 'POST') {
            exportCertificate($certName);
        } else {
            jsonError('Not found', 404);
        }
    }
}

jsonError('Not found', 404);

// ============ API Functions ============

function listCertificates() {
    $certs = [];

    if (!is_dir(CERT_DIR)) {
        jsonResponse(['certificates' => []]);
    }

    $files = scandir(CERT_DIR);
    foreach ($files as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) === 'crt') {
            $name = pathinfo($file, PATHINFO_FILENAME);
            $certPath = CERT_DIR . '/' . $file;

            $certInfo = openssl_x509_parse(file_get_contents($certPath));
            if ($certInfo) {
                $certs[] = [
                    'name' => $name,
                    'cn' => $certInfo['subject']['CN'] ?? $name,
                    'issuer' => $certInfo['issuer']['CN'] ?? 'N/A',
                    'valid_from' => date('c', $certInfo['validFrom_time_t']),
                    'valid_to' => date('c', $certInfo['validTo_time_t']),
                    'is_expired' => time() > $certInfo['validTo_time_t'],
                    'has_key' => file_exists(CERT_DIR . '/' . $name . '.key')
                ];
            }
        }
    }

    AuditLogger::log(LOG_API, null, ['action' => 'list_certificates']);
    jsonResponse(['certificates' => $certs]);
}

function generateCertificate() {
    $input = json_decode(file_get_contents('php://input'), true);

    $hostname = trim($input['hostname'] ?? '');
    $sans = $input['sans'] ?? [];
    $duration = $input['duration'] ?? '720h';

    if (empty($hostname)) {
        jsonError('Hostname is required');
    }

    if (!preg_match('/^[a-zA-Z0-9.-]+$/', $hostname)) {
        jsonError('Invalid hostname');
    }

    if (empty($sans)) {
        jsonError('At least one SAN is required');
    }

    $validDurations = ['24h', '168h', '720h', '2160h', '8760h'];
    if (!in_array($duration, $validDurations)) {
        $duration = '24h';
    }

    // Validate SANs
    foreach ($sans as $san) {
        if (!preg_match('/^[a-zA-Z0-9.-]+$/', $san) && !filter_var($san, FILTER_VALIDATE_IP)) {
            jsonError("Invalid SAN: $san");
        }
    }

    @mkdir(CERT_DIR, 0755, true);

    $cert = CERT_DIR . "/{$hostname}.crt";
    $key = CERT_DIR . "/{$hostname}.key";

    @unlink($cert);
    @unlink($key);

    $sanFlags = '';
    foreach ($sans as $san) {
        $sanFlags .= ' --san ' . escapeshellarg($san);
    }

    $pwdFile = tempnam(sys_get_temp_dir(), 'step_pwd_');
    file_put_contents($pwdFile, CA_PASSWORD);

    $cmd = sprintf(
        'step ca certificate %s %s %s --provisioner %s --provisioner-password-file %s --ca-url %s --root %s --not-after %s --force%s 2>&1',
        escapeshellarg($hostname),
        escapeshellarg($cert),
        escapeshellarg($key),
        escapeshellarg(PROVISIONER),
        escapeshellarg($pwdFile),
        escapeshellarg(CA_URL),
        escapeshellarg(ROOT_CERT),
        escapeshellarg($duration),
        $sanFlags
    );

    exec($cmd, $output, $code);
    @unlink($pwdFile);

    if ($code !== 0 || !file_exists($cert)) {
        AuditLogger::log(LOG_GENERATE, $hostname, ['status' => 'failed', 'source' => 'api']);
        jsonError('Failed to generate certificate: ' . implode(' ', $output), 500);
    }

    chmod($cert, 0644);
    chmod($key, 0600);

    AuditLogger::log(LOG_GENERATE, $hostname, ['status' => 'success', 'source' => 'api', 'duration' => $duration]);

    $certInfo = openssl_x509_parse(file_get_contents($cert));

    jsonResponse([
        'success' => true,
        'certificate' => [
            'name' => $hostname,
            'cn' => $certInfo['subject']['CN'] ?? $hostname,
            'valid_from' => date('c', $certInfo['validFrom_time_t']),
            'valid_to' => date('c', $certInfo['validTo_time_t']),
            'sans' => $sans
        ]
    ], 201);
}

function getCertificateDetails($name) {
    $name = basename($name);
    $certPath = CERT_DIR . '/' . $name . '.crt';

    if (!file_exists($certPath)) {
        jsonError('Certificate not found', 404);
    }

    $certContent = file_get_contents($certPath);
    $certInfo = openssl_x509_parse($certContent);
    $certResource = openssl_x509_read($certContent);

    if (!$certInfo) {
        jsonError('Failed to parse certificate', 500);
    }

    $pubKey = openssl_pkey_get_public($certResource);
    $pubKeyDetails = openssl_pkey_get_details($pubKey);
    $fingerprint = openssl_x509_fingerprint($certResource, 'sha256');

    $sans = [];
    if (isset($certInfo['extensions']['subjectAltName'])) {
        $sanParts = explode(',', $certInfo['extensions']['subjectAltName']);
        foreach ($sanParts as $part) {
            $sans[] = trim($part);
        }
    }

    jsonResponse([
        'name' => $name,
        'cn' => $certInfo['subject']['CN'] ?? 'N/A',
        'subject' => $certInfo['subject'],
        'issuer' => $certInfo['issuer'],
        'serial' => strtoupper(dechex($certInfo['serialNumber'])),
        'valid_from' => date('c', $certInfo['validFrom_time_t']),
        'valid_to' => date('c', $certInfo['validTo_time_t']),
        'is_expired' => time() > $certInfo['validTo_time_t'],
        'days_remaining' => max(0, floor(($certInfo['validTo_time_t'] - time()) / 86400)),
        'sans' => $sans,
        'signature_algorithm' => $certInfo['signatureTypeSN'] ?? 'N/A',
        'public_key' => [
            'type' => $pubKeyDetails['type'],
            'bits' => $pubKeyDetails['bits']
        ],
        'fingerprint_sha256' => strtoupper(implode(':', str_split($fingerprint, 2))),
        'has_key' => file_exists(CERT_DIR . '/' . $name . '.key')
    ]);
}

function deleteCertificate($name) {
    $name = basename($name);
    $certPath = CERT_DIR . '/' . $name . '.crt';
    $keyPath = CERT_DIR . '/' . $name . '.key';

    if (!file_exists($certPath)) {
        jsonError('Certificate not found', 404);
    }

    $deleted = true;
    if (file_exists($certPath)) $deleted = $deleted && unlink($certPath);
    if (file_exists($keyPath)) $deleted = $deleted && unlink($keyPath);

    if ($deleted) {
        AuditLogger::log(LOG_DELETE, $name, ['status' => 'success', 'source' => 'api']);
        jsonResponse(['success' => true, 'message' => "Certificate '$name' deleted"]);
    } else {
        AuditLogger::log(LOG_DELETE, $name, ['status' => 'failed', 'source' => 'api']);
        jsonError('Failed to delete certificate files', 500);
    }
}

function revokeCertificate($name) {
    $name = basename($name);
    $certPath = CERT_DIR . '/' . $name . '.crt';

    if (!file_exists($certPath)) {
        jsonError('Certificate not found', 404);
    }

    $certInfo = openssl_x509_parse(file_get_contents($certPath));
    if (!$certInfo) {
        jsonError('Failed to parse certificate', 500);
    }

    $serialNumber = $certInfo['serialNumber'];

    $pwdFile = tempnam(sys_get_temp_dir(), 'step_pwd_');
    file_put_contents($pwdFile, CA_PASSWORD);

    $cmd = sprintf(
        'step ca revoke %s --provisioner %s --provisioner-password-file %s --ca-url %s --root %s 2>&1',
        escapeshellarg($serialNumber),
        escapeshellarg(PROVISIONER),
        escapeshellarg($pwdFile),
        escapeshellarg(CA_URL),
        escapeshellarg(ROOT_CERT)
    );

    exec($cmd, $output, $code);
    @unlink($pwdFile);

    if ($code !== 0) {
        $errorMsg = implode(' ', $output);
        if (stripos($errorMsg, 'already revoked') !== false) {
            jsonError('Certificate is already revoked');
        }
        AuditLogger::log(LOG_REVOKE, $name, ['status' => 'failed', 'source' => 'api']);
        jsonError('Failed to revoke certificate: ' . $errorMsg, 500);
    }

    AuditLogger::log(LOG_REVOKE, $name, ['status' => 'success', 'source' => 'api']);
    jsonResponse(['success' => true, 'message' => "Certificate '$name' revoked"]);
}

function renewCertificate($name) {
    $name = basename($name);
    $certPath = CERT_DIR . '/' . $name . '.crt';
    $keyPath = CERT_DIR . '/' . $name . '.key';

    if (!file_exists($certPath)) {
        jsonError('Certificate not found', 404);
    }

    $certInfo = openssl_x509_parse(file_get_contents($certPath));
    if (!$certInfo) {
        jsonError('Failed to parse certificate', 500);
    }

    $hostname = $certInfo['subject']['CN'] ?? $name;

    $sans = [];
    if (isset($certInfo['extensions']['subjectAltName'])) {
        $sanParts = explode(',', $certInfo['extensions']['subjectAltName']);
        foreach ($sanParts as $part) {
            $part = trim($part);
            $part = preg_replace('/^(DNS:|IP Address:)\s*/', '', $part);
            if (!empty($part) && $part !== $hostname) {
                $sans[] = $part;
            }
        }
    }

    @unlink($certPath);
    @unlink($keyPath);

    $sanFlags = '';
    foreach ($sans as $san) {
        $sanFlags .= ' --san ' . escapeshellarg($san);
    }

    $pwdFile = tempnam(sys_get_temp_dir(), 'step_pwd_');
    file_put_contents($pwdFile, CA_PASSWORD);

    $cmd = sprintf(
        'step ca certificate %s %s %s --provisioner %s --provisioner-password-file %s --ca-url %s --root %s --not-after 24h --force%s 2>&1',
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
    @unlink($pwdFile);

    if ($code !== 0 || !file_exists($certPath)) {
        AuditLogger::log(LOG_RENEW, $name, ['status' => 'failed', 'source' => 'api']);
        jsonError('Failed to renew certificate: ' . implode(' ', $output), 500);
    }

    chmod($certPath, 0644);
    chmod($keyPath, 0600);

    AuditLogger::log(LOG_RENEW, $name, ['status' => 'success', 'source' => 'api']);

    $newCertInfo = openssl_x509_parse(file_get_contents($certPath));

    jsonResponse([
        'success' => true,
        'certificate' => [
            'name' => $name,
            'valid_from' => date('c', $newCertInfo['validFrom_time_t']),
            'valid_to' => date('c', $newCertInfo['validTo_time_t'])
        ]
    ]);
}

function exportCertificate($name) {
    $input = json_decode(file_get_contents('php://input'), true);
    $password = $input['password'] ?? '';

    if (strlen($password) < 4) {
        jsonError('Password must be at least 4 characters');
    }

    $name = basename($name);
    $certPath = CERT_DIR . '/' . $name . '.crt';
    $keyPath = CERT_DIR . '/' . $name . '.key';

    if (!file_exists($certPath) || !file_exists($keyPath)) {
        jsonError('Certificate or key not found', 404);
    }

    $cert = file_get_contents($certPath);
    $key = file_get_contents($keyPath);

    $caChain = [];
    if (file_exists(INTERMEDIATE_CERT)) {
        $caChain[] = file_get_contents(INTERMEDIATE_CERT);
    }
    if (file_exists(ROOT_CERT)) {
        $caChain[] = file_get_contents(ROOT_CERT);
    }

    $pkcs12 = '';
    $success = openssl_pkcs12_export($cert, $pkcs12, $key, $password, ['extracerts' => $caChain]);

    if (!$success) {
        AuditLogger::log(LOG_EXPORT, $name, ['status' => 'failed', 'source' => 'api']);
        jsonError('Failed to create PKCS12 file', 500);
    }

    AuditLogger::log(LOG_EXPORT, $name, ['status' => 'success', 'source' => 'api']);

    header('Content-Type: application/x-pkcs12');
    header('Content-Disposition: attachment; filename="' . $name . '.p12"');
    header('Content-Length: ' . strlen($pkcs12));
    echo $pkcs12;
    exit;
}
