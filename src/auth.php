<?php

/**
 * إنشاء وإرجاع اتصال PDO بقاعدة بيانات SQLite.
 * @return PDO
 * @throws Exception
 */
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
            // Password is correct, start the session
            session_regenerate_id(true); // Prevent session fixation
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            return true;
        }
    } catch (Exception $e) {
        // Log error or handle it
        error_log("Login error: " . $e->getMessage());
    }
    return false;
}

/**
 * تسجيل الخروج (تدمير الجلسة).
 */
function logout() {
    session_unset();
    session_destroy();
    // Redirect to login page to prevent issues with stale pages
    header('Location: index.php');
    exit;
}

/**
 * التحقق مما إذا كان المستخدم قد سجل دخوله.
 * @return bool
 */
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

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
