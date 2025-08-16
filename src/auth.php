<?php

/**
 * إنشاء وإرجاع اتصال PDO بقاعدة بيانات SQLite.
 * @return PDO
 * @throws Exception
 */
if (!function_exists('get_db_connection')) {
    function get_db_connection() {
        $db_path = __DIR__ . '/../database/users.db';
        if (!file_exists($db_path)) {
        // This should not happen if the installer ran correctly.
        throw new Exception("قاعدة بيانات المستخدمين غير موجودة. يرجى تشغيل معالج التثبيت.");
    }
    try {
        $pdo = new PDO('sqlite:' . $db_path);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        throw new Exception("فشل الاتصال بقاعدة البيانات: " . $e->getMessage());
    }
    }
}

if (!function_exists('login')) {
    /**
     * التحقق من بيانات تسجيل الدخول وإنشاء الجلسة.
     * @param string $username
     * @param string $password
     * @return bool
     */
    function login($username, $password) {
        try {
            $pdo = get_db_connection();
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                return true;
            }
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
        }
        return false;
    }
}

if (!function_exists('logout')) {
    /**
     * تسجيل الخروج (تدمير الجلسة).
     */
    function logout() {
        session_unset();
        session_destroy();
        header('Location: index.php');
        exit;
    }
}

if (!function_exists('is_logged_in')) {
    /**
     * التحقق مما إذا كان المستخدم قد سجل دخوله.
     * @return bool
     */
    function is_logged_in() {
        return isset($_SESSION['user_id']);
    }
}

if (!function_exists('get_current_user')) {
    /**
     * الحصول على بيانات المستخدم الحالي من الجلسة.
     * @return array|null
     */
    function get_current_user() {
        if (!is_logged_in()) {
            return null;
        }
        return [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
        ];
    }
}
