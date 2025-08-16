<?php
session_start();

// --- التأكد من التثبيت أولاً ---
if (!file_exists(__DIR__ . '/../install.lock')) {
    header('Location: install.php');
    exit;
}

require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/helpers.php';

$error = null;
$action = $_GET['action'] ?? null;

// --- معالجة الطلبات ---
if ($action === 'logout') {
    logout();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'login') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    if (login($username, $password)) {
        header('Location: index.php'); // توجيه إلى لوحة التحكم بعد تسجيل الدخول
        exit;
    } else {
        $error = "اسم المستخدم أو كلمة المرور غير صحيحة.";
    }
}

// --- متغيرات التصميم ---
$styles = "
    body { font-family: sans-serif; background-color: #f4f4f9; color: #333; margin: 0; padding: 0; }
    .container { max-width: 800px; margin: 50px auto; padding: 20px; background: #fff; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
    .login-container { max-width: 400px; }
    h1 { color: #444; }
    .btn { display: inline-block; padding: 10px 20px; background-color: #007bff; color: #fff; text-decoration: none; border-radius: 5px; border: none; cursor: pointer; }
    .btn-danger { background-color: #dc3545; }
    .input-field { width: 100%; padding: 10px; margin-bottom: 15px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box; }
    .error { color: #dc3545; background-color: #f8d7da; border: 1px solid #f5c6cb; padding: 10px; border-radius: 5px; margin-bottom: 15px; }
    .header { background-color: #fff; padding: 10px 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; }
";

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة تحكم النسخ الاحتياطي</title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css">
    <style>
        body { background-color: #f8f9fa; }
        .container { margin-top: 30px; }
        .card-header { font-weight: bold; }
        .log-viewer {
            background-color: #212529;
            color: #f8f9fa;
            font-family: monospace;
            height: 300px;
            overflow-y: scroll;
            padding: 15px;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <?php if (is_logged_in()):
        // Load config file for logged-in users to get access to constants like BACKUP_DIR
        if (file_exists(__DIR__ . '/../config/config.php')) {
            require_once __DIR__ . '/../config/config.php';
        } else {
            // This case should ideally not be reached if install was successful
            die("CRITICAL: Configuration file is missing for a logged-in user.");
        }
    ?>
        <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
            <div class="container-fluid">
                <a class="navbar-brand" href="#">لوحة التحكم</a>
                <div class="d-flex">
                    <span class="navbar-text me-3">
                        مرحباً, <strong><?php echo htmlspecialchars(get_current_user()['username']); ?></strong>
                    </span>
                    <a href="?action=logout" class="btn btn-danger">تسجيل الخروج</a>
                </div>
            </div>
        </nav>

        <div class="container">
            <div class="row">
                <!-- Actions and Logs -->
                <div class="col-md-8">
                    <div class="card mb-4">
                        <div class="card-header">بدء عملية نسخ احتياطي جديدة</div>
                        <div class="card-body" id="backup-actions">
                            <p>اختر نوع النسخ الاحتياطي الذي تريد إجراءه:</p>
                            <button class="btn btn-primary" data-backup-type="all">نسخ قاعدة البيانات والملفات</button>
                            <button class="btn btn-secondary" data-backup-type="db">نسخ قاعدة البيانات فقط</button>
                            <button class="btn btn-secondary" data-backup-type="files">نسخ الملفات فقط</button>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-header">سجل العمليات المباشر</div>
                        <div class="card-body">
                            <div class="log-viewer" id="log-viewer">
                                جاري تحميل السجل...
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Backup List -->
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">النسخ الاحتياطية المتاحة</div>
                        <ul class="list-group list-group-flush">
                            <?php
                                $backup_files = get_backup_files();
                                if (empty($backup_files)):
                            ?>
                                <li class="list-group-item">لا توجد نسخ احتياطية.</li>
                            <?php else: ?>
                                <?php foreach ($backup_files as $backup): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong class="d-block" style="font-size: 0.9rem;"><?php echo htmlspecialchars($backup['name']); ?></strong>
                                            <small class="text-muted">
                                                <?php echo date('Y-m-d H:i', $backup['date']); ?> -
                                                <?php echo round($backup['size'] / 1024 / 1024, 2); ?> MB
                                            </small>
                                        </div>
                                        <div>
                                            <a href="download.php?file=<?php echo urlencode($backup['name']); ?>" class="btn btn-sm btn-success">تحميل</a>
                                            <button class="btn btn-sm btn-danger btn-delete" data-file="<?php echo htmlspecialchars($backup['name']); ?>">حذف</button>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bootstrap JS -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const backupListContainer = document.querySelector('.list-group');

                function escapeHTML(str) {
                    if (typeof str !== 'string') return '';
                    return str.replace(/[&<>"']/g, function(match) {
                        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[match];
                    });
                }

                async function refreshBackupList() {
                    try {
                        const response = await fetch('ajax.php?action=get_backups');
                        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                        const data = await response.json();

                        if (data.status === 'success') {
                            let html = '';
                            if (data.backups.length === 0) {
                                html = '<li class="list-group-item">لا توجد نسخ احتياطية.</li>';
                            } else {
                                data.backups.forEach(backup => {
                                    html += `
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong class="d-block" style="font-size: 0.9rem;">${escapeHTML(backup.name)}</strong>
                                                <small class="text-muted">${escapeHTML(backup.date_formatted)} - ${escapeHTML(backup.size_formatted)}</small>
                                            </div>
                                            <div>
                                                <a href="download.php?file=${encodeURIComponent(backup.name)}" class="btn btn-sm btn-success">تحميل</a>
                                                <button class="btn btn-sm btn-danger btn-delete" data-file="${escapeHTML(backup.name)}">حذف</button>
                                            </div>
                                        </li>`;
                                });
                            }
                            backupListContainer.innerHTML = html;
                        }
                    } catch (error) {
                        console.error('Failed to refresh backup list:', error);
                        backupListContainer.innerHTML = '<li class="list-group-item text-danger">فشل في تحميل قائمة النسخ الاحتياطية.</li>';
                    }
                }

                backupListContainer.addEventListener('click', async function(e) {
                    if (e.target && e.target.classList.contains('btn-delete')) {
                        const button = e.target;
                        const filename = button.dataset.file;

                        if (confirm(`هل أنت متأكد أنك تريد حذف الملف: ${filename}؟`)) {
                            button.disabled = true;
                            button.textContent = 'جاري الحذف...';

                            const formData = new FormData();
                            formData.append('action', 'delete_backup');
                            formData.append('file', filename);

                            try {
                                const response = await fetch('ajax.php', { method: 'POST', body: formData });
                                const data = await response.json();

                                if (data.status === 'success') {
                                    await refreshBackupList();
                                } else {
                                    alert('فشل الحذف: ' + (data.message || 'خطأ غير معروف'));
                                    button.disabled = false;
                                    button.textContent = 'حذف';
                                }
                            } catch (error) {
                                alert('حدث خطأ في الشبكة أو استجابة غير صالحة من الخادم.');
                                button.disabled = false;
                                button.textContent = 'حذف';
                            }
                        }
                    }
                });

                const backupActionsContainer = document.getElementById('backup-actions');
                const logViewer = document.getElementById('log-viewer');

                async function updateLogViewer() {
                    try {
                        const response = await fetch('ajax.php?action=get_log');
                        const data = await response.json();
                        if (data.status === 'success') {
                            logViewer.textContent = data.log || 'السجل فارغ.';
                            logViewer.scrollTop = logViewer.scrollHeight;
                        }
                    } catch (error) { console.error('Failed to update log:', error); }
                }

                backupActionsContainer.addEventListener('click', async function(e) {
                    if (e.target && e.target.tagName === 'BUTTON' && e.target.dataset.backupType) {
                        const button = e.target;
                        const backupType = button.dataset.backupType;

                        button.disabled = true;
                        const originalText = button.textContent;
                        button.textContent = 'جاري البدء...';

                        const formData = new FormData();
                        formData.append('action', 'start_backup');
                        formData.append('type', backupType);

                        try {
                            const response = await fetch('ajax.php', { method: 'POST', body: formData });
                            const data = await response.json();

                            if (data.status === 'success') {
                                alert(data.message);
                                setTimeout(updateLogViewer, 1000);
                                setTimeout(refreshBackupList, 5000); // Give it time to generate
                            } else {
                                alert('فشل بدء النسخ الاحتياطي: ' + data.message);
                            }
                        } catch (error) {
                            alert('حدث خطأ في الشبكة.');
                        } finally {
                            button.disabled = false;
                            button.textContent = originalText;
                        }
                    }
                });

                // Initial and periodic updates
                updateLogViewer();
                setInterval(updateLogViewer, 10000); // every 10 seconds
                setInterval(refreshBackupList, 30000); // every 30 seconds
            });
        </script>
    <?php else: // عرض فورم تسجيل الدخول ?>

        <div class="container login-container">
            <h1>تسجيل الدخول</h1>
            <p>الرجاء إدخال بيانات حساب المدير للمتابعة.</p>

            <?php if ($error): ?>
                <div class="error"><?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST" action="?action=login">
                <label for="username">اسم المستخدم:</label>
                <input type="text" id="username" name="username" class="input-field" required>

                <label for="password">كلمة المرور:</label>
                <input type="password" id="password" name="password" class="input-field" required>

                <button type="submit" class="btn" style="width: 100%;">دخول</button>
            </form>
        </div>

    <?php endif; ?>

</body>
</html>
