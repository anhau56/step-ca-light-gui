<?php
require_once 'auth.php';
require_once 'lib/logger.php';
session_start();

define('CERT_DIR', '/var/www/html/certs');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    die('Method not allowed');
}

$name = $_POST['name'] ?? '';

if (empty($name)) {
    $_SESSION['error'] = 'Certificate name is required';
    header('Location: download-cert.php');
    exit;
}

// Sanitize filename
$name = basename($name);
$certPath = CERT_DIR . '/' . $name . '.crt';
$keyPath = CERT_DIR . '/' . $name . '.key';

// Check if certificate exists
if (!file_exists($certPath)) {
    $_SESSION['error'] = 'Certificate not found';
    header('Location: download-cert.php');
    exit;
}

// Delete files
$deleted = true;
if (file_exists($certPath)) {
    $deleted = $deleted && unlink($certPath);
}
if (file_exists($keyPath)) {
    $deleted = $deleted && unlink($keyPath);
}

if ($deleted) {
    AuditLogger::log(LOG_DELETE, $name, ['status' => 'success']);
    $_SESSION['success'] = "Certificate '$name' has been deleted";
} else {
    AuditLogger::log(LOG_DELETE, $name, ['status' => 'failed']);
    $_SESSION['error'] = 'Failed to delete certificate files';
}

header('Location: download-cert.php');
