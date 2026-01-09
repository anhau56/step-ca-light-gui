<?php
require_once 'auth.php';

$CERT_DIR = '/var/www/html/certs';

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

            $certs[] = [
                'name' => $name,
                'cert_file' => $file,
                'key_file' => $name . '.key',
                'has_key' => file_exists($keyPath),
                'info' => $certInfo,
                'created' => filemtime($certPath)
            ];
        }
    }

    // Sort by creation date (newest first)
    usort($certs, fn($a, $b) => $b['created'] - $a['created']);

    return $certs;
}

$certificates = getCertificates($CERT_DIR);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Download Certificates</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 800px;
            width: 100%;
            padding: 40px;
        }

        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }

        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }

        .icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 30px;
        }

        .cert-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .cert-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
        }

        .cert-card h2 {
            font-size: 16px;
            color: #333;
            margin-bottom: 10px;
            word-break: break-all;
        }

        .cert-info {
            font-size: 12px;
            color: #666;
            margin-bottom: 15px;
        }

        .cert-info p {
            margin-bottom: 3px;
        }

        .cert-info strong {
            color: #333;
        }

        .cert-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-key {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }

        .btn-key:hover {
            box-shadow: 0 5px 15px rgba(245, 87, 108, 0.4);
        }

        .back-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
        }

        .back-link:hover {
            text-decoration: underline;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #666;
        }

        .empty-state p {
            margin-bottom: 20px;
        }

        .validity {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
        }

        .validity.valid {
            background: #d4edda;
            color: #155724;
        }

        .validity.expired {
            background: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">📜</div>
        <h1>Generated Certificates</h1>
        <p class="subtitle">Download your generated certificates and private keys</p>

        <?php if (empty($certificates)): ?>
            <div class="empty-state">
                <p>No certificates generated yet.</p>
                <a href="index.php" class="btn">Generate a Certificate</a>
            </div>
        <?php else: ?>
            <div class="cert-list">
                <?php foreach ($certificates as $cert):
                    $isExpired = $cert['info'] && time() > $cert['info']['validTo_time_t'];
                ?>
                    <div class="cert-card">
                        <h2><?= htmlspecialchars($cert['name']) ?></h2>
                        <?php if ($cert['info']): ?>
                            <div class="cert-info">
                                <p><strong>Subject:</strong> <?= htmlspecialchars($cert['info']['subject']['CN'] ?? 'N/A') ?></p>
                                <p><strong>Issuer:</strong> <?= htmlspecialchars($cert['info']['issuer']['CN'] ?? 'N/A') ?></p>
                                <p><strong>Valid:</strong>
                                    <?= date('Y-m-d', $cert['info']['validFrom_time_t']) ?>
                                    to
                                    <?= date('Y-m-d', $cert['info']['validTo_time_t']) ?>
                                    <span class="validity <?= $isExpired ? 'expired' : 'valid' ?>">
                                        <?= $isExpired ? 'EXPIRED' : 'VALID' ?>
                                    </span>
                                </p>
                                <p><strong>Created:</strong> <?= date('Y-m-d H:i:s', $cert['created']) ?></p>
                            </div>
                        <?php endif; ?>
                        <div class="cert-actions">
                            <a href="?file=<?= urlencode($cert['cert_file']) ?>" class="btn">Download Certificate (.crt)</a>
                            <?php if ($cert['has_key']): ?>
                                <a href="?file=<?= urlencode($cert['key_file']) ?>" class="btn btn-key">Download Key (.key)</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <a href="index.php" class="back-link">← Back to Certificate Generator</a>
    </div>
</body>
</html>
