<?php
session_start();
require_once __DIR__ . '/../src/auth.php';

// Load config only if it exists
if (file_exists(__DIR__ . '/../config/config.php')) {
    require_once __DIR__ . '/../config/config.php';
} else {
    // Should not happen on an installed system
    die("Configuration file not found.");
}

if (!is_logged_in()) {
    http_response_code(403);
    die('Forbidden: You must be logged in to download files.');
}

$filename = $_GET['file'] ?? null;
if (!$filename) {
    http_response_code(400);
    die('Bad Request: No file specified.');
}

// Security: Sanitize the filename to prevent directory traversal attacks.
// We ensure the filename does not contain any path components.
$baseFilename = basename($filename);
if ($baseFilename !== $filename) {
    http_response_code(403);
    die('Forbidden: Invalid filename.');
}

$filePath = realpath(BACKUP_DIR) . '/' . $baseFilename;

// Final check to ensure the file is within the backup directory
if (!file_exists($filePath) || strpos(realpath($filePath), realpath(BACKUP_DIR)) !== 0) {
    http_response_code(404);
    die('Error: File not found or access denied.');
}

header('Content-Description: File Transfer');
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $baseFilename . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($filePath));
flush(); // Flush system output buffer
readfile($filePath);
exit;
