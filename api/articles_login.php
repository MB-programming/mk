<?php
// ================================================
// Articles Admin Login / Logout
// جلسة مستقلة عن الأدمن الرئيسي
// ================================================
require_once __DIR__ . '/articles_config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

// بدء الجلسة بالاسم المخصص للمقالات
if (session_status() === PHP_SESSION_NONE) {
    session_name(ART_SESSION_NAME);
    session_start();
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'login';

// تسجيل الخروج
if ($action === 'logout') {
    $_SESSION = [];
    session_destroy();
    jsonResponse(['success' => true, 'message' => 'تم تسجيل الخروج']);
}

// فحص الجلسة
if ($method === 'GET' && $action === 'check') {
    if (!empty($_SESSION['articles_admin_id'])) {
        jsonResponse(['success' => true, 'admin' => ['name' => $_SESSION['articles_admin_name']]]);
    }
    jsonResponse(['success' => false], 401);
}

// تسجيل الدخول
if ($method === 'POST') {
    $body     = json_decode(file_get_contents('php://input'), true) ?? [];
    $username = clean($body['username'] ?? '');
    $password = $body['password'] ?? '';

    if (empty($username) || empty($password)) {
        jsonResponse(['success' => false, 'message' => 'الرجاء إدخال اسم المستخدم وكلمة المرور'], 400);
    }

    $db   = getDB();
    $stmt = $db->prepare("SELECT id, username, password, name FROM admins WHERE username = ? LIMIT 1");
    $stmt->execute([$username]);
    $admin = $stmt->fetch();

    if ($admin && hash('sha256', $password) === $admin['password']) {
        $_SESSION['articles_admin_id']   = $admin['id'];
        $_SESSION['articles_admin_name'] = $admin['name'];
        jsonResponse(['success' => true, 'message' => 'تم تسجيل الدخول بنجاح', 'name' => $admin['name']]);
    }

    jsonResponse(['success' => false, 'message' => 'اسم المستخدم أو كلمة المرور غير صحيحة'], 401);
}

jsonResponse(['success' => false, 'message' => 'Bad request'], 400);
