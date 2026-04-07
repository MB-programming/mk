<?php
ob_start(); // منع أي output يكسر الـ JSON response
// ================================================
// Image Upload — تحويل تلقائي إلى WebP
// type=logo  → assets/brands/
// type=cover → assets/articles/
// يقبل جلسة الأدمن الرئيسي أو أدمن المقالات
// ================================================
require_once __DIR__ . '/config.php';

// فحص المصادقة — يقبل أي من الجلستين
function checkUploadAuth(): void {
    // فحص جلسة الأدمن الرئيسي
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!empty($_SESSION['admin_id'])) {
        return; // أدمن رئيسي ✓
    }
    session_write_close();

    // فحص جلسة أدمن المقالات
    session_name('ARTICLES_ADMIN');
    session_start();
    if (!empty($_SESSION['articles_admin_id'])) {
        return; // أدمن مقالات ✓
    }

    http_response_code(401);
    die(json_encode(['success' => false, 'message' => 'Unauthorized'], JSON_UNESCAPED_UNICODE));
}

checkUploadAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

if (empty($_FILES['logo'])) {
    jsonResponse(['success' => false, 'message' => 'No file uploaded'], 400);
}

$file    = $_FILES['logo'];
$type    = clean($_GET['type'] ?? 'logo'); // logo | cover
$allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
$maxSize = 5 * 1024 * 1024; // 5MB

if (!in_array($file['type'], $allowed)) {
    jsonResponse(['success' => false, 'message' => 'نوع الملف غير مدعوم. يُسمح بـ JPG, PNG, WebP فقط'], 400);
}

if ($file['size'] > $maxSize) {
    jsonResponse(['success' => false, 'message' => 'حجم الملف يتجاوز 5MB'], 400);
}

// تحديد المجلد حسب النوع
if ($type === 'cover') {
    $uploadDir = __DIR__ . '/../assets/articles/';
    $urlPrefix = 'assets/articles/';
    $prefix    = 'cover_';
} elseif ($type === 'slider') {
    $uploadDir = __DIR__ . '/../assets/slider/';
    $urlPrefix = 'assets/slider/';
    $prefix    = 'slide_';
} else {
    $uploadDir = __DIR__ . '/../assets/brands/';
    $urlPrefix = 'assets/brands/';
    $prefix    = 'brand_';
}

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// الأبعاد القصوى حسب نوع الرفع
$maxWidths = ['logo' => 400, 'cover' => 900, 'slider' => 1440];
$maxW      = $maxWidths[$type] ?? 900;
$quality   = ($type === 'logo') ? 82 : 80;

// اسم الملف الجديد بصيغة WebP
$filename = $prefix . uniqid() . '.webp';
$destPath = $uploadDir . $filename;

// تحويل الصورة إلى WebP بـ GD
ini_set('memory_limit', '512M');
$src = null;
switch ($file['type']) {
    case 'image/jpeg': $src = imagecreatefromjpeg($file['tmp_name']); break;
    case 'image/png':
        $src = imagecreatefrompng($file['tmp_name']);
        imagealphablending($src, false);
        imagesavealpha($src, true);
        break;
    case 'image/webp': $src = imagecreatefromwebp($file['tmp_name']); break;
    case 'image/gif':  $src = imagecreatefromgif($file['tmp_name']);  break;
}

if (!$src) {
    jsonResponse(['success' => false, 'message' => 'فشل قراءة الصورة'], 500);
}

// تصغير الصورة إذا أكبر من الحد الأقصى
$origW = imagesx($src);
$origH = imagesy($src);
if ($origW > $maxW) {
    $newH    = intval($origH * ($maxW / $origW));
    $resized = imagecreatetruecolor($maxW, $newH);
    imagealphablending($resized, false);
    imagesavealpha($resized, true);
    $bg = imagecolorallocatealpha($resized, 0, 0, 0, 127);
    imagefilledrectangle($resized, 0, 0, $maxW, $newH, $bg);
    imagecopyresampled($resized, $src, 0, 0, 0, 0, $maxW, $newH, $origW, $origH);
    imagedestroy($src);
    $src = $resized;
}

$ok = imagewebp($src, $destPath, $quality);
imagedestroy($src);

if (!$ok) {
    jsonResponse(['success' => false, 'message' => 'فشل تحويل الصورة إلى WebP'], 500);
}

$publicUrl = $urlPrefix . $filename;
jsonResponse(['success' => true, 'url' => $publicUrl, 'message' => 'تم رفع الصورة بنجاح']);
