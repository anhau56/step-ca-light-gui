<?php
require_once 'auth.php';
session_start();
$success = $_SESSION['success'] ?? null;
$error = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate Generator - Step CA</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-content">
                <div class="icon">🔒</div>
                <h1>Certificate Generator</h1>
                <p class="subtitle">Generate a TLS certificate for your server</p>
            </div>
            <button type="button" class="theme-toggle" id="theme-toggle" title="Toggle theme">🌙</button>
        </div>

        <?php
        if ($success) {
            $hostname = htmlspecialchars($success);
            echo '<div class="alert alert-success">';
            echo '✓ Certificate successfully generated for <strong>' . $hostname . '</strong>';
            echo '<div class="download-links">';
            echo '<a href="download.php?file=' . urlencode($hostname) . '.crt">📥 Download Certificate</a>';
            echo '<a href="download.php?file=' . urlencode($hostname) . '.key">📥 Download Private Key</a>';
            echo '</div>';
            echo '</div>';
        }

        if ($error) {
            echo '<div class="alert alert-error">';
            echo '✗ Error: ' . htmlspecialchars($error);
            echo '</div>';
        }
        ?>

        <form action="generate.php" method="POST">
            <div class="form-group">
                <label for="hostname">Hostname (FQDN)</label>
                <input
                    type="text"
                    id="hostname"
                    name="hostname"
                    placeholder="server.example.local"
                    required
                    pattern="[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?)*"
                    title="Enter a valid domain name"
                >
                <div class="help-text">
                    Example: server.example.local or api.mydomain.com
                </div>
            </div>

            <div class="form-group">
                <label>Subject Alternative Names (SANs)</label>
                <div class="san-container" id="san-container">
                    <div class="san-row">
                        <input type="text" name="san[]" placeholder="Additional DNS or IP" required>
                    </div>
                </div>
                <button type="button" class="btn-add" id="add-san">+ Add SAN</button>
                <div class="help-text">
                    Add all DNS names and IP addresses through which the server will be accessible.
                </div>
            </div>

            <div class="form-group">
                <label for="duration">Certificate Validity</label>
                <select id="duration" name="duration">
                    <option value="24h" selected>24 hours (default)</option>
                    <option value="168h">7 days</option>
                    <option value="720h">30 days</option>
                    <option value="2160h">90 days</option>
                    <option value="8760h">1 year</option>
                </select>
                <div class="help-text">
                    How long the certificate will be valid. Shorter durations are more secure.
                </div>
            </div>

            <button type="submit" class="full-width">🔐 Generate Certificate</button>
        </form>

        <div class="nav-links">
            <a href="download-cert.php">My Certificates</a>
            <a href="download-ca.php">CA Certificates</a>
            <a href="logs.php">Audit Logs</a>
        </div>
    </div>

    <script src="js/app.js"></script>
</body>
</html>
