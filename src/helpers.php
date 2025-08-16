<?php

/**
 * يفحص مجلد النسخ الاحتياطي ويعيد مصفوفة بالملفات الموجودة وتفاصيلها.
 * @return array
 */
function get_backup_files() {
    // Ensure config is loaded and constant is available
    if (!defined('BACKUP_DIR') || !is_dir(BACKUP_DIR)) {
        return [];
    }

    $files = array_diff(scandir(BACKUP_DIR), ['.', '..']);
    $backups = [];
    foreach ($files as $file) {
        $filePath = BACKUP_DIR . '/' . $file;
        if (is_file($filePath) && pathinfo($file, PATHINFO_EXTENSION) === 'zip') {
            $backups[] = [
                'name' => $file,
                'size' => filesize($filePath),
                'date' => filemtime($filePath),
                // Format size and date for direct use in API responses
                'size_formatted' => round(filesize($filePath) / 1024 / 1024, 2) . ' MB',
                'date_formatted' => date('Y-m-d H:i:s', filemtime($filePath)),
            ];
        }
    }

    // Sort by date descending (most recent first)
    usort($backups, function($a, $b) {
        return $b['date'] <=> $a['date'];
    });

    return $backups;
}
