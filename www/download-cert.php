<?php
require_once 'auth.php';
require_once 'lib/logger.php';

$CERT_DIR = '/var/www/html/certs';
$REVOKED_FILE = '/var/www/html/certs/.revoked.json';

// Get list of revoked certificates
function getRevokedCerts() {
    global $REVOKED_FILE;
    if (!file_exists($REVOKED_FILE)) {
        return [];
    }
    $data = json_decode(file_get_contents($REVOKED_FILE), true);
    return is_array($data) ? $data : [];
}

// Check if certificate is revoked
function isRevoked($certName) {
    $revoked = getRevokedCerts();
    return isset($revoked[$certName]);
}

// Handle download
if (isset($_GET['file'])) {
    $filename = basename($_GET['file']);
    $filepath = $CERT_DIR . '/' . $filename;

    $ext = pathinfo($filename, PATHINFO_EXTENSION);
    if (!in_array($ext, ['crt', 'key'])) {
        die('File type not allowed');
    }

    if (!file_exists($filepath)) {
        die('File not found');
    }

    // Log download
    $certName = pathinfo($filename, PATHINFO_FILENAME);
    AuditLogger::log(LOG_DOWNLOAD, $certName, ['file' => $filename]);

    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($filepath));
    readfile($filepath);
    exit;
}

// Get all certificates
function getCertificates($dir) {
    $certs = [];
    if (!is_dir($dir)) {
        return $certs;
    }

    $files = scandir($dir);
    foreach ($files as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) === 'crt') {
            $name = pathinfo($file, PATHINFO_FILENAME);
            $certPath = $dir . '/' . $file;
            $keyPath = $dir . '/' . $name . '.key';

            $certInfo = null;
            $cert = file_get_contents($certPath);
            if ($cert) {
                $certInfo = openssl_x509_parse($cert);
            }

            $isExpired = $certInfo && time() > $certInfo['validTo_time_t'];
            $daysRemaining = $certInfo ? max(0, floor(($certInfo['validTo_time_t'] - time()) / 86400)) : 0;

            $certs[] = [
                'name' => $name,
                'cert_file' => $file,
                'key_file' => $name . '.key',
                'has_key' => file_exists($keyPath),
                'info' => $certInfo,
                'created' => filemtime($certPath),
                'is_expired' => $isExpired,
                'days_remaining' => $daysRemaining,
                'revoked' => isRevoked($name)
            ];
        }
    }

    // Sort by creation date (newest first)
    usort($certs, fn($a, $b) => $b['created'] - $a['created']);

    return $certs;
}

$certificates = getCertificates($CERT_DIR);

// Get session messages
$success = $_SESSION['success'] ?? null;
$error = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Certificates - Step CA</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container wide">
        <div class="header">
            <div class="header-content">
                <div class="icon">📜</div>
                <h1>My Certificates</h1>
                <p class="subtitle">Download and manage your generated certificates</p>
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

        <?php if ($success): ?>
            <div class="alert alert-success">✓ <?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error">✗ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if (empty($certificates)): ?>
            <div class="empty-state">
                <p>No certificates generated yet.</p>
                <a href="index.php" class="btn">Generate a Certificate</a>
            </div>
        <?php else: ?>
            <div class="search-box">
                <input type="search" id="cert-search" placeholder="Search certificates...">
                <div class="search-results" id="search-results"></div>
            </div>

            <div class="cert-list">
                <?php foreach ($certificates as $cert): ?>
                    <div class="cert-card">
                        <h2>
                            <a href="cert-details.php?name=<?= urlencode($cert['name']) ?>">
                                <?= htmlspecialchars($cert['name']) ?>
                            </a>
                        </h2>
                        <?php if ($cert['info']): ?>
                            <div class="cert-info">
                                <p><strong>Subject:</strong> <?= htmlspecialchars($cert['info']['subject']['CN'] ?? 'N/A') ?></p>
                                <p><strong>Issuer:</strong> <?= htmlspecialchars($cert['info']['issuer']['CN'] ?? 'N/A') ?></p>
                                <p><strong>Valid:</strong>
                                    <?= date('Y-m-d', $cert['info']['validFrom_time_t']) ?>
                                    to
                                    <?= date('Y-m-d', $cert['info']['validTo_time_t']) ?>
                                    <?php if ($cert['revoked']): ?>
                                        <span class="validity revoked">REVOKED</span>
                                    <?php elseif ($cert['is_expired']): ?>
                                        <span class="validity expired">EXPIRED</span>
                                    <?php elseif ($cert['days_remaining'] <= 7): ?>
                                        <span class="validity expiring">EXPIRING</span>
                                    <?php else: ?>
                                        <span class="validity valid">VALID</span>
                                    <?php endif; ?>
                                </p>
                                <p><strong>Created:</strong> <?= date('Y-m-d H:i:s', $cert['created']) ?></p>
                            </div>
                        <?php endif; ?>
                        <div class="cert-actions">
                            <a href="?file=<?= urlencode($cert['cert_file']) ?>" class="btn btn-sm">📥 Certificate</a>
                            <?php if ($cert['has_key']): ?>
                                <a href="?file=<?= urlencode($cert['key_file']) ?>" class="btn btn-sm btn-key">📥 Key</a>
                            <?php endif; ?>
                            <a href="javascript:void(0)" onclick="PFXExport.show('<?= htmlspecialchars($cert['name'], ENT_QUOTES) ?>')" class="btn btn-sm btn-secondary">📦 Export PFX</a>
                            <?php if (!$cert['revoked']): ?>
                            <a href="renew.php?name=<?= urlencode($cert['name']) ?>" class="btn btn-sm btn-success">🔄 Renew</a>
                            <?php endif; ?>
                            <?php if (!$cert['revoked']): ?>
                            <form action="revoke.php" method="POST" style="display:inline" onsubmit="return Confirm.revoke('<?= htmlspecialchars($cert['name'], ENT_QUOTES) ?>')">
                                <input type="hidden" name="name" value="<?= htmlspecialchars($cert['name']) ?>">
                                <button type="submit" class="btn btn-sm btn-secondary">🚫 Revoke</button>
                            </form>
                            <?php endif; ?>
                            <form action="delete.php" method="POST" style="display:inline" onsubmit="return Confirm.delete('<?= htmlspecialchars($cert['name'], ENT_QUOTES) ?>')">
                                <input type="hidden" name="name" value="<?= htmlspecialchars($cert['name']) ?>">
                                <button type="submit" class="btn btn-sm btn-danger">🗑️ Delete</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="nav-links">
            <a href="index.php">← Generate Certificate</a>
            <a href="download-ca.php">CA Certificates</a>
            <a href="logs.php">Audit Logs</a>
        </div>
    </div>

    <!-- PFX Export Modal -->
    <div class="modal-overlay" id="pfx-modal">
        <div class="modal">
            <h2>Export as PFX/P12</h2>
            <p style="margin-bottom: 20px; color: var(--text-secondary); font-size: 14px;">
                Enter a password to protect the exported file.
            </p>
            <form id="pfx-form" action="export-pfx.php" method="POST">
                <input type="hidden" name="name" id="pfx-cert-name">
                <div class="form-group">
                    <label for="pfx-password">Password</label>
                    <input type="password" id="pfx-password" name="password" required minlength="4">
                </div>
                <div class="form-group">
                    <label for="pfx-password-confirm">Confirm Password</label>
                    <input type="password" id="pfx-password-confirm" required minlength="4">
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="PFXExport.hide()">Cancel</button>
                    <button type="button" class="btn" onclick="PFXExport.submit()">Export</button>
                </div>
            </form>
        </div>
    </div>

    <script src="js/app.js"></script>
</body>
</html>
