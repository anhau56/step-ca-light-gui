<?php
// CONFIGURATION
define('CA_URL', getenv('CA_URL') ?: 'https://step-ca:9000');
define('PROVISIONER', getenv('CA_PROVISIONER') ?: 'admin');
define('CA_PASSWORD', getenv('CA_PASSWORD') ?: '');
define('ROOT_CERT', getenv('STEP_ROOT') ?: '/home/step/certs/root_ca.crt');
define('CERT_DIR', '/var/www/html/certs');

// Créer le dossier
@mkdir(CERT_DIR, 0755, true);

// Récupérer le hostname
$hostname = trim($_POST['hostname'] ?? '');

// Récupérer les SANs
$sans = array_filter(array_map('trim', $_POST['san'] ?? []), fn($s) => $s !== '');

// Validation
if (empty($hostname)) {
    die(header('Location: index.php?error=Hostname manquant'));
}

if (!preg_match('/^[a-zA-Z0-9.-]+$/', $hostname)) {
    die(header('Location: index.php?error=Hostname invalide'));
}

// Valider chaque SAN (DNS ou IP)
foreach ($sans as $san) {
    if (!preg_match('/^[a-zA-Z0-9.-]+$/', $san) && !filter_var($san, FILTER_VALIDATE_IP)) {
        die(header('Location: index.php?error=' . urlencode("SAN invalide: $san")));
    }
}

// Fichiers
$cert = CERT_DIR . "/{$hostname}.crt";
$key = CERT_DIR . "/{$hostname}.key";

// Nettoyer
@unlink($cert);
@unlink($key);

// Construire les flags --san
$sanFlags = '';
foreach ($sans as $san) {
    $sanFlags .= ' --san ' . escapeshellarg($san);
}

// Créer fichier temporaire pour le mot de passe
$pwdFile = tempnam(sys_get_temp_dir(), 'step_pwd_');
file_put_contents($pwdFile, CA_PASSWORD);

// COMMANDE STEP - HOSTNAME + SANs
$cmd = sprintf(
    'step ca certificate %s %s %s --provisioner %s --provisioner-password-file %s --ca-url %s --root %s --force%s 2>&1',
    escapeshellarg($hostname),
    escapeshellarg($cert),
    escapeshellarg($key),
    escapeshellarg(PROVISIONER),
    escapeshellarg($pwdFile),
    escapeshellarg(CA_URL),
    escapeshellarg(ROOT_CERT),
    $sanFlags
);

exec($cmd, $output, $code);

// Supprimer le fichier de mot de passe
@unlink($pwdFile);

// Vérifier
if ($code !== 0 || !file_exists($cert)) {
    $errorMsg = implode(' ', $output);
    // Limiter la taille du message d'erreur pour éviter URL trop longue
    if (strlen($errorMsg) > 200) {
        $errorMsg = substr($errorMsg, 0, 200) . '...';
    }
    die(header('Location: index.php?error=' . urlencode($errorMsg)));
}

chmod($cert, 0644);
chmod($key, 0600);

header('Location: index.php?success=1&hostname=' . urlencode($hostname));
