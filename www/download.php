<?php
$CERT_DIR = '/var/www/html/certs';

if (empty($_GET['file'])) {
    die('Fichier non spécifié');
}

$filename = basename($_GET['file']);
$filepath = $CERT_DIR . '/' . $filename;

// Vérifier que le fichier existe
if (!file_exists($filepath)) {
    die('Fichier introuvable');
}

// Vérifier l'extension (sécurité)
$ext = pathinfo($filename, PATHINFO_EXTENSION);
if (!in_array($ext, ['crt', 'key'])) {
    die('Type de fichier non autorisé');
}

// Télécharger le fichier
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($filepath));
readfile($filepath);
exit;
?>
