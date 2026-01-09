<?php
require_once 'auth.php';

$CERT_DIR = '/var/www/html/certs';

if (empty($_GET['file'])) {
    die('File not specified');
}

$filename = basename($_GET['file']);
$filepath = $CERT_DIR . '/' . $filename;

// Check if file exists
if (!file_exists($filepath)) {
    die('File not found');
}

// Check extension (security)
$ext = pathinfo($filename, PATHINFO_EXTENSION);
if (!in_array($ext, ['crt', 'key'])) {
    die('File type not allowed');
}

// Download file
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($filepath));
readfile($filepath);
exit;
?>
