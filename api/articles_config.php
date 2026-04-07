<?php
// ================================================
// Articles Database Configuration (SEPARATE DB)
// قاعدة بيانات المقالات المنفصلة
// ================================================

define('ART_DB_HOST',    'localhost');
define('ART_DB_NAME',    'makhazenalenaya_blogs');
define('ART_DB_USER',    'makhazenalenaya_blogs');
define('ART_DB_PASS',    '?BN0Mn5x$(K$');
define('ART_DB_CHARSET', 'utf8mb4');

// اسم الجلسة — مختلف تماماً عن الأدمن الرئيسي
define('ART_SESSION_NAME', 'ARTICLES_ADMIN');

// ------------------------------------------------
// PDO Connection → articles DB
// ------------------------------------------------
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            ART_DB_HOST, ART_DB_NAME, ART_DB_CHARSET
        );
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, ART_DB_USER, ART_DB_PASS, $options);
        } catch (PDOException $e) {
            http_response_code(500);
            die(json_encode(['success' => false, 'message' => 'Database connection failed']));
        }
    }
    return $pdo;
}

// ------------------------------------------------
// JSON Response Helper
// ------------------------------------------------
function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH');
    header('Access-Control-Allow-Headers: Content-Type');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// ------------------------------------------------
// Auth Guard — Articles Admin Session
// ------------------------------------------------
function requireAuth(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_name(ART_SESSION_NAME);
        session_start();
    }
    if (empty($_SESSION['articles_admin_id'])) {
        jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    }
}

// ------------------------------------------------
// Sanitize input
// ------------------------------------------------
function clean(string $str): string {
    return htmlspecialchars(strip_tags(trim($str)), ENT_QUOTES, 'UTF-8');
}
