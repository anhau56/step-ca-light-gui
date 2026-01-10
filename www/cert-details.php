<?php
require_once 'auth.php';

define('CERT_DIR', '/var/www/html/certs');

$name = $_GET['name'] ?? '';
$format = $_GET['format'] ?? 'html';

if (empty($name)) {
    if ($format === 'json') {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Certificate name is required']);
        exit;
    }
    die('Certificate name is required');
}

// Sanitize filename
$name = basename($name);
$certPath = CERT_DIR . '/' . $name . '.crt';

if (!file_exists($certPath)) {
    if ($format === 'json') {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Certificate not found']);
        exit;
    }
    die('Certificate not found');
}

// Parse certificate
$certContent = file_get_contents($certPath);
$certInfo = openssl_x509_parse($certContent);
$certResource = openssl_x509_read($certContent);

if (!$certInfo) {
    if ($format === 'json') {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Failed to parse certificate']);
        exit;
    }
    die('Failed to parse certificate');
}

// Extract public key info
$pubKey = openssl_pkey_get_public($certResource);
$pubKeyDetails = openssl_pkey_get_details($pubKey);
$keyType = match($pubKeyDetails['type']) {
    OPENSSL_KEYTYPE_RSA => 'RSA',
    OPENSSL_KEYTYPE_EC => 'ECDSA',
    OPENSSL_KEYTYPE_DSA => 'DSA',
    OPENSSL_KEYTYPE_DH => 'DH',
    default => 'Unknown'
};
$keyBits = $pubKeyDetails['bits'] ?? 0;

// Get fingerprint
$fingerprint = openssl_x509_fingerprint($certResource, 'sha256');

// Format subject and issuer
function formatDN($dn) {
    $parts = [];
    foreach ($dn as $key => $value) {
        if (is_array($value)) {
            foreach ($value as $v) {
                $parts[] = "$key=$v";
            }
        } else {
            $parts[] = "$key=$value";
        }
    }
    return implode(', ', $parts);
}

// Extract SANs
$sans = [];
if (isset($certInfo['extensions']['subjectAltName'])) {
    $sanString = $certInfo['extensions']['subjectAltName'];
    $sanParts = explode(',', $sanString);
    foreach ($sanParts as $part) {
        $sans[] = trim($part);
    }
}

// Build response
$response = [
    'name' => $name,
    'cn' => $certInfo['subject']['CN'] ?? 'N/A',
    'subject' => formatDN($certInfo['subject']),
    'issuer' => formatDN($certInfo['issuer']),
    'serial' => strtoupper(dechex($certInfo['serialNumber'])),
    'valid_from' => date('Y-m-d H:i:s', $certInfo['validFrom_time_t']),
    'valid_to' => date('Y-m-d H:i:s', $certInfo['validTo_time_t']),
    'sans' => $sans,
    'signature_algorithm' => $certInfo['signatureTypeSN'] ?? 'N/A',
    'public_key' => "$keyType $keyBits bits",
    'fingerprint' => strtoupper(implode(':', str_split($fingerprint, 2))),
    'is_expired' => time() > $certInfo['validTo_time_t'],
    'days_remaining' => max(0, floor(($certInfo['validTo_time_t'] - time()) / 86400))
];

// Return JSON if requested
if ($format === 'json') {
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// HTML output
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate Details - <?= htmlspecialchars($name) ?></title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-content">
                <div class="icon">📋</div>
                <h1>Certificate Details</h1>
                <p class="subtitle"><?= htmlspecialchars($name) ?></p>
            </div>
            <div class="header-actions">
                <?php if (function_exists('isAuthRequired') && isAuthRequired()): ?>
                <div class="user-menu">
                    <span class="username"><?= htmlspecialchars(getCurrentUser()) ?></span>
                    <a href="logout.php" class="logout-link">Logout</a>
                </div>
                <?php endif; ?>
                <button type="button" class="theme-toggle" id="theme-toggle" title="Toggle theme">🌙</button>
            </div>
        </div>

        <div class="cert-card">
            <dl class="details-list">
                <dt>Common Name (CN)</dt>
                <dd><?= htmlspecialchars($response['cn']) ?></dd>

                <dt>Subject</dt>
                <dd><?= htmlspecialchars($response['subject']) ?></dd>

                <dt>Issuer</dt>
                <dd><?= htmlspecialchars($response['issuer']) ?></dd>

                <dt>Serial Number</dt>
                <dd><?= htmlspecialchars($response['serial']) ?></dd>

                <dt>Valid From</dt>
                <dd><?= htmlspecialchars($response['valid_from']) ?></dd>

                <dt>Valid To</dt>
                <dd>
                    <?= htmlspecialchars($response['valid_to']) ?>
                    <?php if ($response['is_expired']): ?>
                        <span class="validity expired">EXPIRED</span>
                    <?php elseif ($response['days_remaining'] <= 7): ?>
                        <span class="validity expiring">EXPIRING (<?= $response['days_remaining'] ?> days)</span>
                    <?php else: ?>
                        <span class="validity valid">VALID (<?= $response['days_remaining'] ?> days)</span>
                    <?php endif; ?>
                </dd>

                <dt>Subject Alternative Names</dt>
                <dd>
                    <?php if (empty($response['sans'])): ?>
                        None
                    <?php else: ?>
                        <?php foreach ($response['sans'] as $san): ?>
                            <?= htmlspecialchars($san) ?><br>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </dd>

                <dt>Signature Algorithm</dt>
                <dd><?= htmlspecialchars($response['signature_algorithm']) ?></dd>

                <dt>Public Key</dt>
                <dd><?= htmlspecialchars($response['public_key']) ?></dd>

                <dt>Fingerprint (SHA-256)</dt>
                <dd style="font-family: monospace; font-size: 11px; word-break: break-all;">
                    <?= htmlspecialchars($response['fingerprint']) ?>
                </dd>
            </dl>
        </div>

        <div class="cert-actions" style="margin-top: 20px;">
            <a href="download.php?file=<?= urlencode($name) ?>.crt" class="btn btn-sm">📥 Download Certificate</a>
            <a href="download.php?file=<?= urlencode($name) ?>.key" class="btn btn-sm btn-key">📥 Download Key</a>
        </div>

        <a href="download-cert.php" class="back-link">← Back to Certificates</a>
    </div>

    <script src="js/app.js"></script>
</body>
</html>
