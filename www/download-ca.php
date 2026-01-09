<?php
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
    <title>Download CA Certificates</title>
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
            max-width: 600px;
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

        .cert-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .cert-card h2 {
            font-size: 18px;
            color: #333;
            margin-bottom: 15px;
        }

        .cert-info {
            font-size: 13px;
            color: #666;
            margin-bottom: 15px;
        }

        .cert-info p {
            margin-bottom: 5px;
        }

        .cert-info strong {
            color: #333;
        }

        .btn-download {
            display: inline-block;
            padding: 12px 24px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .btn-download:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.4);
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

        .info-box {
            background: #e7f3ff;
            border: 1px solid #b6d4fe;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            font-size: 13px;
            color: #0a58ca;
        }

        .not-found {
            color: #dc3545;
            font-style: italic;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">🏛️</div>
        <h1>CA Certificates</h1>
        <p class="subtitle">Download and install the CA certificates to trust issued certificates</p>

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
                <a href="?type=root" class="btn-download">Download Root CA</a>
            <?php else: ?>
                <p class="not-found">Root CA certificate not found</p>
            <?php endif; ?>
        </div>

        <div class="cert-card">
            <h2>Intermediate CA Certificate</h2>
            <?php if ($intermediateInfo): ?>
                <div class="cert-info">
                    <p><strong>Subject:</strong> <?= htmlspecialchars($intermediateInfo['subject']['CN'] ?? 'N/A') ?></p>
                    <p><strong>Issuer:</strong> <?= htmlspecialchars($intermediateInfo['issuer']['CN'] ?? 'N/A') ?></p>
                    <p><strong>Valid From:</strong> <?= date('Y-m-d', $intermediateInfo['validFrom_time_t']) ?></p>
                    <p><strong>Valid To:</strong> <?= date('Y-m-d', $intermediateInfo['validTo_time_t']) ?></p>
                </div>
                <a href="?type=intermediate" class="btn-download">Download Intermediate CA</a>
            <?php else: ?>
                <p class="not-found">Intermediate CA certificate not found</p>
            <?php endif; ?>
        </div>

        <a href="index.php" class="back-link">← Back to Certificate Generator</a>
    </div>
</body>
</html>
