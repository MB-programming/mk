<?php
// ================================================
// Admin Login / Logout
// ================================================
require_once __DIR__ . '/config.php';
session_start();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'login';

if ($action === 'logout') {
    $_SESSION = [];
    session_destroy();
    jsonResponse(['success' => true, 'message' => 'Logged out']);
}

if ($method === 'GET' && $action === 'check') {
    if (!empty($_SESSION['admin_id'])) {
        jsonResponse(['success' => true, 'admin' => ['name' => $_SESSION['admin_name']]]);
    }
    jsonResponse(['success' => false], 401);
}

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
        $_SESSION['admin_id']   = $admin['id'];
        $_SESSION['admin_name'] = $admin['name'];
        jsonResponse(['success' => true, 'message' => 'تم تسجيل الدخول بنجاح', 'name' => $admin['name']]);
    }

    jsonResponse(['success' => false, 'message' => 'اسم المستخدم أو كلمة المرور غير صحيحة'], 401);
}

jsonResponse(['success' => false, 'message' => 'Bad request'], 400);
