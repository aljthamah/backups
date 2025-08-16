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
        'ssh2' => [
            'label' => 'PHP-CLI SSH2 Extension (for SFTP)',
            'status' => (function() {
                // Check if shell_exec is disabled, as it's needed to check the CLI environment.
                if (!function_exists('shell_exec') || strpos(ini_get('disable_functions'), 'shell_exec') !== false) {
                    return null; // Return null to indicate an indeterminate state.
                }
                $php_path = 'php'; // Assume 'php' is in the system's PATH.
                $checker_script_path = escapeshellarg(__DIR__ . '/../../src/cli_check.php');
                $output = @shell_exec("$php_path $checker_script_path");

                if ($output === null) {
                    return false; // shell_exec failed or is disabled.
                }

                $result = json_decode($output, true);
                return $result['ssh2_loaded'] ?? false;
            })(),
            'fix' => 'إضافة ssh2 يجب أن تكون مفعلة في بيئة سطر الأوامر (CLI). الفحص يتطلب صلاحية `shell_exec`.',
            'optional' => true,
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

    $all_ok = true;
    foreach ($checks as $check) {
        // A check fails the 'all_ok' condition if it's not optional AND its status is not true.
        // Status can be true, false, or null (indeterminate).
        if (empty($check['optional']) && $check['status'] !== true) {
            $all_ok = false;
            break;
        }
    }

    $html = '<table class="table table-striped">';
    $html .= '<thead><tr><th>المتطلب</th><th class="text-center">الحالة</th><th>ملاحظات</th></tr></thead>';
    $html .= '<tbody>';

    foreach ($checks as $check) {
        $status = $check['status'];
        $is_optional = !empty($check['optional']);

        if ($status === true) {
            $status_icon = '<i class="bi bi-check-circle-fill text-success"></i>';
        } elseif ($status === null) {
            $status_icon = '<i class="bi bi-question-circle-fill text-warning"></i>'; // Indeterminate
        } elseif ($is_optional) {
            $status_icon = '<i class="bi bi-exclamation-triangle-fill text-warning"></i>'; // Optional and failed
        } else {
            $status_icon = '<i class="bi bi-x-circle-fill text-danger"></i>'; // Required and failed
        }

        $html .= '<tr>';
        $html .= '<td>' . htmlspecialchars($check['label']) . '</td>';
        $html .= '<td class="text-center">' . $status_icon . '</td>';
        $html .= '<td>' . ($status !== true ? '<small class="text-muted">' . htmlspecialchars($check['fix']) . '</small>' : '') . '</td>';
        $html .= '</tr>';
    }

    $html .= '</tbody></table>';

    // Add a hidden input to track overall status for JS
    $html .= '<input type="hidden" id="all-checks-ok" value="' . ($all_ok ? '1' : '0') . '">';

    return $html;
}
