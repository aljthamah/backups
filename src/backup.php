<?php

// تفعيل عرض جميع الأخطاء للمساعدة في التصحيح
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ضبط المنطقة الزمنية لتجنب التحذيرات
date_default_timezone_set('UTC');

// --- تحميل الإعدادات ---
$configFile = __DIR__ . '/../config/config.php';
if (!file_exists($configFile)) {
    die("خطأ: ملف الإعدادات config/config.php غير موجود. يرجى نسخ ملف config.php.dist وتعديله.\n");
}
require_once $configFile;

// --- تعريف الثوابت والمتغيرات العامة ---
define('BACKUP_ID', date('Y-m-d_H-i-s'));
$tempDir = rtrim(sys_get_temp_dir(), '/') . '/backup_' . BACKUP_ID;
$backupResult = [
    'db_file' => null,
    'files_archive' => null,
    'final_archive' => null,
];

// --- تحليل مُدخلات سطر الأوامر ---
$options = getopt('', ['only-db', 'only-files']);
$backupDb = isset($options['only-db']) || !isset($options['only-files']);
$backupFiles = isset($options['only-files']) || !isset($options['only-db']);

/**
 * دالة لتسجيل الرسائل في ملف السجل
 * @param string $message الرسالة المراد تسجيلها
 * @param string $level مستوى الرسالة (INFO, WARNING, ERROR)
 */
function log_message($message, $level = 'INFO') {
    $formattedMessage = sprintf("[%s] [%s]: %s\n", date('Y-m-d H:i:s'), $level, $message);
    file_put_contents(LOG_FILE, $formattedMessage, FILE_APPEND);
}

/**
 * دالة لتنظيف الملفات والمجلدات المؤقتة بشكل تعاودي (Recursive)
 * @param string $dir المسار للمجلد المؤقت
 */
function cleanup_temp_files($dir) {
    if (!is_dir($dir)) {
        return;
    }
    log_message("بدء تنظيف المجلد المؤقت: {$dir}");
    try {
        $it = new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS);
        $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
        foreach($files as $file) {
            if ($file->isDir()){
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }
        rmdir($dir);
        log_message("تم تنظيف المجلد المؤقت بنجاح.");
    } catch (Exception $e) {
        log_message("حدث خطأ أثناء تنظيف المجلد المؤقت: " . $e->getMessage(), 'WARNING');
    }
}


// --- منطق النسخ الاحتياطي الرئيسي ---
try {
    log_message("--- بدء عملية النسخ الاحتياطي (ID: " . BACKUP_ID . ") ---");

    // التحقق من وجود الأدوات المطلوبة
    if ($backupDb) {
        // البحث عن mysqldump في مسارات شائعة
        $mysqldumpPath = '';
        $commonPaths = ['/usr/bin/mysqldump', '/bin/mysqldump', '/usr/local/bin/mysqldump'];
        foreach ($commonPaths as $path) {
            if (is_executable($path)) {
                $mysqldumpPath = $path;
                break;
            }
        }
        if (empty($mysqldumpPath)) {
            throw new Exception("الأداة 'mysqldump' غير موجودة أو لا يمكن الوصول إليها في المسارات الشائعة.");
        }
        define('MYSQLDUMP_PATH', $mysqldumpPath);
        log_message("تم العثور على 'mysqldump' في: " . MYSQLDUMP_PATH);
    }
    if ($backupFiles && !class_exists('ZipArchive')) {
        throw new Exception("مكتبة 'ZipArchive' الخاصة بـ PHP غير مفعلة. يرجى تفعيلها.");
    }
    log_message("تم التحقق من الأدوات المطلوبة بنجاح.");


    // إنشاء مجلد مؤقت
    if (!mkdir($tempDir, 0700, true)) {
        throw new Exception("فشل في إنشاء المجلد المؤقت: {$tempDir}");
    }
    log_message("تم إنشاء المجلد المؤقت: {$tempDir}");

    // التحقق من أن مجلد النسخ الاحتياطي موجود وقابل للكتابة
    if (!is_dir(BACKUP_DIR)) {
        log_message("مجلد النسخ الاحتياطي '" . BACKUP_DIR . "' غير موجود، سيتم محاولة إنشائه.", "INFO");
        if (!mkdir(BACKUP_DIR, 0755, true)) {
            throw new Exception("مجلد النسخ الاحتياطي '" . BACKUP_DIR . "' لا يمكن إنشاؤه. تحقق من الصلاحيات.");
        }
    }
    if (!is_writable(BACKUP_DIR)) {
        throw new Exception("مجلد النسخ الاحتياطي '" . BACKUP_DIR . "' غير قابل للكتابة. تحقق من الصلاحيات.");
    }
    log_message("تم التحقق من مجلد النسخ الاحتياطي.");


    // 1. نسخ قاعدة البيانات
    if ($backupDb) {
        log_message("بدء النسخ الاحتياطي لقاعدة البيانات: " . DB_NAME);
        $sqlFile = "{$tempDir}/" . DB_NAME . ".sql";

        // استخدام متغير البيئة MYSQL_PWD لتجنب كتابة كلمة المرور في الأمر مباشرة
        putenv('MYSQL_PWD=' . DB_PASS);

        $dumpCommand = sprintf(
            '%s --host=%s --user=%s %s > %s',
            MYSQLDUMP_PATH,
            escapeshellarg(DB_HOST),
            escapeshellarg(DB_USER),
            escapeshellarg(DB_NAME),
            escapeshellarg($sqlFile)
        );

        $output = null;
        $return_var = null;
        exec($dumpCommand, $output, $return_var);

        // إزالة متغير البيئة بعد الاستخدام
        putenv('MYSQL_PWD=');

        if ($return_var !== 0) {
            throw new Exception("فشل النسخ الاحتياطي لقاعدة البيانات. خطأ من mysqldump. تأكد من صحة بيانات الاتصال.");
        }

        if (!file_exists($sqlFile) || filesize($sqlFile) === 0) {
            // قد يكون الملف فارغًا إذا كانت قاعدة البيانات فارغة، وهذا ليس خطأ بالضرورة
            // سنقوم فقط بتسجيل تحذير إذا كان الملف فارغًا
            log_message("ملف قاعدة البيانات الناتج فارغ. قد تكون قاعدة البيانات فارغة.", 'WARNING');
        }

        $backupResult['db_file'] = $sqlFile;
        log_message("تم الانتهاء من نسخ قاعدة البيانات بنجاح إلى: {$sqlFile}");
    }

    // 2. نسخ الملفات
    if ($backupFiles) {
        log_message("بدء النسخ الاحتياطي للملفات...");
        $filesZip = "{$tempDir}/files_only.zip";
        $zip = new ZipArchive();

        if ($zip->open($filesZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new Exception("فشل في إنشاء ملف الـ ZIP: {$filesZip}");
        }

        $totalFiles = 0;
        foreach (SOURCE_DIRECTORIES as $sourceDir) {
            $realSourceDir = realpath($sourceDir);
            if (!$realSourceDir || !is_dir($realSourceDir)) {
                log_message("المجلد المصدر '{$sourceDir}' غير موجود أو ليس مجلداً. سيتم تخطيه.", 'WARNING');
                continue;
            }
            $sourceDir = rtrim($realSourceDir, '/');
            log_message("جاري إضافة المجلد: {$sourceDir}");

            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );

            $sourceBaseName = basename($sourceDir);

            foreach ($files as $file) {
                $filePath = $file->getRealPath();
                // إنشاء مسار نسبي يبدأ باسم المجلد المصدر
                $relativePath = $sourceBaseName . '/' . substr($filePath, strlen($sourceDir) + 1);

                // التحقق من الاستثناءات
                $excluded = false;
                foreach (EXCLUDE_PATTERNS as $pattern) {
                    // تحقق من اسم الملف أو المسار النسبي بأكمله
                    if (fnmatch($pattern, basename($filePath)) || fnmatch($pattern, $relativePath)) {
                        $excluded = true;
                        break;
                    }
                }

                if ($excluded) {
                    // تخطي المجلدات المستثناة وكل ما بداخلها
                    if ($file->isDir()) {
                        // This part is tricky with RecursiveIteratorIterator.
                        // A simple continue is enough to not add the entry.
                    }
                    continue;
                }

                if ($file->isDir()) {
                    $zip->addEmptyDir($relativePath);
                } else {
                    $zip->addFile($filePath, $relativePath);
                    $totalFiles++;
                }
            }
        }

        $zip->close();
        if ($totalFiles > 0) {
            $backupResult['files_archive'] = $filesZip;
            log_message("تم الانتهاء من نسخ الملفات. تم ضغط {$totalFiles} ملف في '{$filesZip}'.");
        } else {
            log_message("لم يتم العثور على ملفات لنسخها احتياطياً.", "WARNING");
            unlink($filesZip); // حذف الأرشيف الفارغ
        }
    }

    // 3. إنشاء الأرشيف النهائي
    log_message("بدء إنشاء الأرشيف النهائي...");
    $finalArchiveName = "backup-" . BACKUP_ID . ".zip";
    $finalArchivePath = rtrim(BACKUP_DIR, '/') . '/' . $finalArchiveName;

    $dbFile = $backupResult['db_file'];
    $filesArchive = $backupResult['files_archive'];

    if (!$dbFile && !$filesArchive) {
        log_message("لم يتم إنشاء أي نسخ احتياطية (لا قاعدة بيانات ولا ملفات). العملية تتوقف.", "WARNING");
    } else {
        $finalZip = new ZipArchive();

        if ($filesArchive && $dbFile) { // كليهما
            // إضافة ملف قاعدة البيانات إلى أرشيف الملفات الموجود
            rename($filesArchive, $finalArchivePath); // أولاً، انقله إلى الوجهة النهائية بالاسم الصحيح
            if ($finalZip->open($finalArchivePath) !== TRUE) {
                 throw new Exception("فشل في فتح أرشيف الملفات لإضافة قاعدة البيانات.");
            }
            $finalZip->addFile($dbFile, basename($dbFile));
            $finalZip->close();
        } elseif ($filesArchive) { // ملفات فقط
            // فقط انقل أرشيف الملفات
            rename($filesArchive, $finalArchivePath);
        } elseif ($dbFile) { // قاعدة بيانات فقط
            // إنشاء أرشيف جديد وإضافة ملف قاعدة البيانات
            if ($finalZip->open($finalArchivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
                throw new Exception("فشل في إنشاء الأرشيف النهائي لقاعدة البيانات.");
            }
            $finalZip->addFile($dbFile, basename($dbFile));
            $finalZip->close();
        }

        if (!file_exists($finalArchivePath)) {
            throw new Exception("فشل في إنشاء ملف الأرشيف النهائي.");
        }

        $backupResult['final_archive'] = $finalArchivePath;
        log_message("تم إنشاء الأرشيف النهائي بنجاح: {$finalArchivePath}");
    }


    // 4. رفع النسخة إلى FTP (إذا تم تفعيله)
    if (FTP_ENABLED && !empty($backupResult['final_archive']) && file_exists($backupResult['final_archive'])) {
        log_message("بدء الرفع إلى سيرفر FTP...");
        $finalArchivePath = $backupResult['final_archive'];

        $conn_id = ftp_connect(FTP_HOST);
        if ($conn_id === false) {
            log_message("فشل الاتصال بسيرفر الـ FTP: " . FTP_HOST, 'ERROR');
        } else {
            $login_result = ftp_login($conn_id, FTP_USER, FTP_PASS);
            if ($login_result === false) {
                log_message("فشل تسجيل الدخول إلى سيرفر الـ FTP كمستخدم: " . FTP_USER, 'ERROR');
            } else {
                // تفعيل الوضع السلبي (Passive mode) وهو ضروري في أغلب الحالات
                ftp_pasv($conn_id, true);

                $remoteFile = rtrim(FTP_DIR, '/') . '/' . basename($finalArchivePath);
                if (ftp_put($conn_id, $remoteFile, $finalArchivePath, FTP_BINARY)) {
                    log_message("تم رفع ملف النسخ الاحتياطي بنجاح إلى: " . FTP_HOST . $remoteFile);
                } else {
                    log_message("فشل رفع النسخة الاحتياطية إلى سيرفر الـ FTP.", 'ERROR');
                }
            }
            ftp_close($conn_id);
        }
    }

    // 5. حذف النسخ الاحتياطية القديمة
    if (DELETE_OLD_BACKUPS && defined('KEEP_BACKUPS_FOR_DAYS') && is_numeric(KEEP_BACKUPS_FOR_DAYS)) {
        log_message("بدء عملية حذف النسخ القديمة...");
        $backupDir = realpath(BACKUP_DIR);
        if (!$backupDir) {
            log_message("مجلد النسخ الاحتياطية غير موجود، لا يمكن حذف النسخ القديمة.", 'WARNING');
        } else {
            $files = scandir($backupDir);
            $retentionDate = strtotime('-' . KEEP_BACKUPS_FOR_DAYS . ' days');
            $deletedCount = 0;

            foreach ($files as $file) {
                $filePath = $backupDir . '/' . $file;
                // التأكد من أنه ملف وليس مجلد، وأنه يطابق نمط اسم النسخة الاحتياطية
                if (is_file($filePath) && strpos($file, 'backup-') === 0 && pathinfo($file, PATHINFO_EXTENSION) === 'zip') {
                    if (filemtime($filePath) < $retentionDate) {
                        if (unlink($filePath)) {
                            log_message("تم حذف النسخة الاحتياطية القديمة: {$file}");
                            $deletedCount++;
                        } else {
                            log_message("فشل حذف النسخة الاحتياطية القديمة: {$file}", 'WARNING');
                        }
                    }
                }
            }
            log_message("تم الانتهاء من حذف النسخ القديمة. تم حذف {$deletedCount} ملف.");
        }
    }

    log_message("--- اكتملت عملية النسخ الاحتياطي بنجاح ---");

} catch (Exception $e) {
    log_message("!!! حدث خطأ فادح: " . $e->getMessage(), 'ERROR');
    log_message("--- فشلت عملية النسخ الاحتياطي ---", 'ERROR');
    // الخروج مع رمز خطأ
    exit(1);
} finally {
    // التنظيف النهائي للملفات المؤقتة
    cleanup_temp_files($tempDir);
}

exit(0);
