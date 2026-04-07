<?php
// ================================================
// Database Configuration
// ================================================

define('DB_HOST', 'localhost');
define('DB_NAME', 'makhazenalenaya_maindb');
define('DB_USER', 'makhazenalenaya_makhazenalenaya');
define('DB_PASS', 'ZG[pJe%b2+!j');
define('DB_CHARSET', 'utf8mb4');

// ------------------------------------------------
// PDO Connection
// ------------------------------------------------
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_TIMEOUT            => 5, // 5-second query timeout
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
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
    // امسح أي output متراكم (PHP warnings, notices) عشان ميكسرش الـ JSON
    while (ob_get_level() > 0) ob_end_clean();
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// ------------------------------------------------
// Auth Guard for Admin
// ------------------------------------------------
function requireAuth(): void {
    session_start();
    if (empty($_SESSION['admin_id'])) {
        jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    }
}

// ------------------------------------------------
// Sanitize input
// ------------------------------------------------
function clean(string $str): string {
    return htmlspecialchars(strip_tags(trim($str)), ENT_QUOTES, 'UTF-8');
}
