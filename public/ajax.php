<?php
session_start();
header('Content-Type: application/json');

// --- Require necessary files ---
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/helpers.php';

// Load config only if it exists
if (file_exists(__DIR__ . '/../config/config.php')) {
    require_once __DIR__ . '/../config/config.php';
} else {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Configuration file not found.']);
    exit;
}

// --- Gatekeeper: Only logged-in users can use the API ---
if (!is_logged_in()) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Forbidden: Authentication required.']);
    exit;
}

// --- Router for API actions ---
$action = $_POST['action'] ?? $_GET['action'] ?? null;
$response = ['status' => 'error', 'message' => 'Invalid action specified.'];

switch ($action) {
    case 'get_backups':
        $response = [
            'status' => 'success',
            'backups' => get_backup_files()
        ];
        break;

    case 'delete_backup':
        $file_to_delete = $_POST['file'] ?? null;
        if ($file_to_delete) {
            $baseFilename = basename($file_to_delete);
            if ($baseFilename !== $file_to_delete) {
                $response['message'] = 'Invalid filename.';
            } else {
                $filePath = realpath(BACKUP_DIR) . '/' . $baseFilename;
                // Security check: ensure the file is actually inside the backup directory
                if (file_exists($filePath) && strpos(realpath($filePath), realpath(BACKUP_DIR)) === 0) {
                    if (unlink($filePath)) {
                        $response = ['status' => 'success', 'message' => 'تم حذف الملف بنجاح.'];
                    } else {
                        $response['message'] = 'حدث خطأ أثناء حذف الملف.';
                    }
                } else {
                    $response['message'] = 'الملف غير موجود أو الوصول إليه مرفوض.';
                }
            }
        } else {
            $response['message'] = 'لم يتم تحديد أي ملف للحذف.';
        }
        break;

    case 'start_backup':
        $type = $_POST['type'] ?? 'all';
        $command = 'php ' . escapeshellarg(__DIR__ . '/../src/backup.php');

        switch ($type) {
            case 'db':
                $command .= ' --only-db';
                break;
            case 'files':
                $command .= ' --only-files';
                break;
        }

        // Execute in background
        shell_exec($command . ' > /dev/null 2>&1 &');

        $response = ['status' => 'success', 'message' => 'تم بدء عملية النسخ الاحتياطي في الخلفية.'];
        break;

    case 'get_log':
        $log_content = '';
        if (file_exists(LOG_FILE)) {
            // Read the last 50 lines for performance
            $lines = file(LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $log_content = implode("\n", array_slice($lines, -50));
        }
        $response = ['status' => 'success', 'log' => $log_content];
        break;
}

// --- Send the response ---
echo json_encode($response);
exit;
