<?php
// No auth required for CA certificates
// Configuration
$ROOT_CERT = getenv('STEP_ROOT') ?: '/home/step/certs/root_ca.crt';
$INTERMEDIATE_CERT = '/home/step/certs/intermediate_ca.crt';

// Handle download requests
if (isset($_GET['type'])) {
    $type = $_GET['type'];

    if ($type === 'root' && file_exists($ROOT_CERT)) {
        header('Content-Type: application/x-pem-file');
        header('Content-Disposition: attachment; filename="root_ca.crt"');
        readfile($ROOT_CERT);
        exit;
    }

    if ($type === 'intermediate' && file_exists($INTERMEDIATE_CERT)) {
        header('Content-Type: application/x-pem-file');
        header('Content-Disposition: attachment; filename="intermediate_ca.crt"');
        readfile($INTERMEDIATE_CERT);
        exit;
    }

    die('Certificate not found');
}

// Get certificate info
function getCertInfo($path) {
    if (!file_exists($path)) {
        return null;
    }
    $cert = file_get_contents($path);
    $data = openssl_x509_parse($cert);
    return $data;
}

$rootInfo = getCertInfo($ROOT_CERT);
$intermediateInfo = getCertInfo($INTERMEDIATE_CERT);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CA Certificates - Step CA</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-content">
                <img src="images/logo.png" alt="Vaultica" class="logo">
                <h1>CA Certificates</h1>
                <p class="subtitle">Download and install the CA certificates to trust issued certificates</p>
            </div>
            <div class="header-actions">
                <button type="button" class="theme-toggle" id="theme-toggle" title="Toggle theme">🌙</button>
            </div>
        </div>

        <div class="info-box">
            <strong>How to use:</strong> Download the Root CA certificate and install it in your browser or operating system's trust store to avoid security warnings when accessing services using certificates issued by this CA.
        </div>

        <div class="cert-card">
            <h2>Root CA Certificate</h2>
            <?php if ($rootInfo): ?>
                <div class="cert-info">
                    <p><strong>Subject:</strong> <?= htmlspecialchars($rootInfo['subject']['CN'] ?? 'N/A') ?></p>
                    <p><strong>Issuer:</strong> <?= htmlspecialchars($rootInfo['issuer']['CN'] ?? 'N/A') ?></p>
                    <p><strong>Valid From:</strong> <?= date('Y-m-d', $rootInfo['validFrom_time_t']) ?></p>
                    <p><strong>Valid To:</strong> <?= date('Y-m-d', $rootInfo['validTo_time_t']) ?></p>
                </div>
                <a href="?type=root" class="btn">📥 Download Root CA</a>
            <?php else: ?>
                <p style="color: var(--error-text); font-style: italic;">Root CA certificate not found</p>
            <?php endif; ?>
        </div>

        <div class="cert-card" style="margin-top: 20px;">
            <h2>Intermediate CA Certificate</h2>
            <?php if ($intermediateInfo): ?>
                <div class="cert-info">
                    <p><strong>Subject:</strong> <?= htmlspecialchars($intermediateInfo['subject']['CN'] ?? 'N/A') ?></p>
                    <p><strong>Issuer:</strong> <?= htmlspecialchars($intermediateInfo['issuer']['CN'] ?? 'N/A') ?></p>
                    <p><strong>Valid From:</strong> <?= date('Y-m-d', $intermediateInfo['validFrom_time_t']) ?></p>
                    <p><strong>Valid To:</strong> <?= date('Y-m-d', $intermediateInfo['validTo_time_t']) ?></p>
                </div>
                <a href="?type=intermediate" class="btn">📥 Download Intermediate CA</a>
            <?php else: ?>
                <p style="color: var(--error-text); font-style: italic;">Intermediate CA certificate not found</p>
            <?php endif; ?>
        </div>

        <a href="index.php" class="back-link">← Back to Certificate Generator</a>
    </div>

    <script src="js/app.js"></script>
</body>
</html>
