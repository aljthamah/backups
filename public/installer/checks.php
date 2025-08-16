<?php

function get_server_checks() {
    $checks = [
        'php_version' => [
            'label' => 'PHP Version (>= 7.2.0)',
            'status' => version_compare(PHP_VERSION, '7.2.0', '>='),
            'fix' => 'يرجى ترقية نسخة PHP إلى 7.2.0 أو أحدث.',
        ],
        'pdo_sqlite' => [
            'label' => 'PDO SQLite Extension',
            'status' => extension_loaded('pdo_sqlite'),
            'fix' => 'يرجى تفعيل إضافة `pdo_sqlite` في ملف php.ini.',
        ],
        'zip' => [
            'label' => 'Zip Extension',
            'status' => extension_loaded('zip'),
            'fix' => 'يرجى تفعيل إضافة `zip` في ملف php.ini.',
        ],
        'config_writable' => [
            'label' => 'Config Directory Writable',
            'path' => __DIR__ . '/../../config',
            'status' => is_writable(__DIR__ . '/../../config'),
            'fix' => 'يرجى إعطاء صلاحيات الكتابة للمجلد `config`. مثال: `chmod 775 config`',
        ],
        'backups_writable' => [
            'label' => 'Backups Directory Writable',
            'path' => __DIR__ . '/../../backups',
            'status' => is_writable(__DIR__ . '/../../backups'),
            'fix' => 'يرجى إعطاء صلاحيات الكتابة للمجلد `backups`. مثال: `chmod 775 backups`',
        ],
        'logs_writable' => [
            'label' => 'Logs Directory Writable',
            'path' => __DIR__ . '/../../logs',
            'status' => is_writable(__DIR__ . '/../../logs'),
            'fix' => 'يرجى إعطاء صلاحيات الكتابة للمجلد `logs`. مثال: `chmod 775 logs`',
        ],
        'database_writable' => [
            'label' => 'Database Directory Writable',
            'path' => __DIR__ . '/../../database',
            'status' => is_writable(__DIR__ . '/../../database'),
            'fix' => 'يرجى إعطاء صلاحيات الكتابة للمجلد `database`. مثال: `chmod 775 database`',
        ],
    ];
    return $checks;
}

function render_checks_table() {
    $checks = get_server_checks();
    $all_ok = !in_array(false, array_column($checks, 'status'), true);

    $html = '<table class="table table-striped">';
    $html .= '<thead><tr><th>المتطلب</th><th class="text-center">الحالة</th><th>ملاحظات</th></tr></thead>';
    $html .= '<tbody>';

    foreach ($checks as $check) {
        $status_icon = $check['status']
            ? '<i class="bi bi-check-circle-fill text-success"></i>'
            : '<i class="bi bi-x-circle-fill text-danger"></i>';

        $status_text = $check['status'] ? 'متوفر' : 'مطلوب';

        $html .= '<tr>';
        $html .= '<td>' . htmlspecialchars($check['label']) . '</td>';
        $html .= '<td class="text-center">' . $status_icon . ' ' . $status_text . '</td>';
        $html .= '<td>' . (!$check['status'] ? '<small class="text-muted">' . htmlspecialchars($check['fix']) . '</small>' : '') . '</td>';
        $html .= '</tr>';
    }

    $html .= '</tbody></table>';

    // Add a hidden input to track overall status for JS
    $html .= '<input type="hidden" id="all-checks-ok" value="' . ($all_ok ? '1' : '0') . '">';

    return $html;
}
