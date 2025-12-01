<?php
// CONFIGURATION
define('CA_URL', 'https://step-ca:9000');
define('PROVISIONER', 'admin');
define('ROOT_CERT', '/home/step/certs/root_ca.crt');
define('CERT_DIR', '/var/www/html/certs');

// Créer le dossier
@mkdir(CERT_DIR, 0755, true);

// Récupérer le hostname
$hostname = trim($_POST['hostname'] ?? '');

// Validation
if (empty($hostname)) {
    die(header('Location: index.php?error=Hostname manquant'));
}

if (!preg_match('/^[a-zA-Z0-9.-]+$/', $hostname)) {
    die(header('Location: index.php?error=Hostname invalide'));
}

// Fichiers
$cert = CERT_DIR . "/{$hostname}.crt";
$key = CERT_DIR . "/{$hostname}.key";

// Nettoyer
@unlink($cert);
@unlink($key);

// COMMANDE STEP - JUSTE LE HOSTNAME
$cmd = sprintf(
    'step ca certificate %s %s %s --provisioner %s --ca-url %s --root %s --force --insecure 2>&1',
    escapeshellarg($hostname),
    escapeshellarg($cert),
    escapeshellarg($key),
    escapeshellarg(PROVISIONER),
    escapeshellarg(CA_URL),
    escapeshellarg(ROOT_CERT)
);

exec($cmd, $output, $code);

// Vérifier
if ($code !== 0 || !file_exists($cert)) {
    die(header('Location: index.php?error=' . urlencode(implode(' ', $output))));
}

chmod($cert, 0644);
chmod($key, 0600);

header('Location: index.php?success=1&hostname=' . urlencode($hostname));
