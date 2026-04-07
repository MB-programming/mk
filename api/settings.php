<?php
// ================================================
// Settings API - Code Injection (header/body)
// ================================================
require_once __DIR__ . '/config.php';

$method = $_SERVER['REQUEST_METHOD'];

// ---- Public GET (used by landing page to inject codes) ----
if ($method === 'GET' && empty($_GET['admin'])) {
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    $db   = getDB();
    $rows = $db->query("SELECT `key`, value FROM settings")->fetchAll();
    $out  = [];
    foreach ($rows as $r) $out[$r['key']] = $r['value'];
    echo json_encode(['success' => true, 'settings' => $out], JSON_UNESCAPED_UNICODE);
    exit;
}

// ---- Admin GET (returns all rows with labels) ----
if ($method === 'GET' && !empty($_GET['admin'])) {
    requireAuth();
    $db   = getDB();
    $rows = $db->query("SELECT * FROM settings ORDER BY id ASC")->fetchAll();
    jsonResponse(['success' => true, 'data' => $rows]);
}

// ---- Admin POST (save/update settings) ----
if ($method === 'POST') {
    requireAuth();
    $db   = getDB();
    $body = json_decode(file_get_contents('php://input'), true) ?? [];

    $stmt = $db->prepare("INSERT INTO settings (`key`, value, label_ar)
                          VALUES (:key, :value, :label_ar)
                          ON DUPLICATE KEY UPDATE value = :value2");

    $labels = [
        'header_code'      => 'كود الهيدر',
        'body_code'        => 'كود البودي',
        'slider_per_view'  => 'عدد بطاقات السلايدر',
        'slider_autoplay'  => 'تشغيل تلقائي للسلايدر',
        'slider_speed'     => 'سرعة السلايدر (مللي ثانية)',
        'perf_animations'  => 'تأثيرات الأنيميشن (GSAP)',
        'perf_cache_api'   => 'كاش API',
        'perf_minify_html' => 'ضغط HTML',
        // Competition settings
        'comp_active'        => 'تفعيل المسابقة',
        'comp_title'         => 'عنوان المسابقة',
        'comp_subtitle'      => 'وصف المسابقة',
        'comp_success_msg'   => 'رسالة النجاح (تحت التهنئة)',
        'comp_ref_prefix'    => 'بادئة رقم المرجع',
        'comp_slider_images' => 'صور سلايدر المسابقة (JSON)',
        // Static pages
        'page_terms'       => 'الشروط والأحكام',
        'page_privacy'     => 'سياسة الخصوصية',
        // Cache control
        'cache_ttl'        => 'مدة كاش API (ثانية)',
        'cache_cleared_at' => 'آخر مسح للكاش',
    ];
    foreach ($body as $key => $value) {
        if (!array_key_exists($key, $labels)) continue;
        $stmt->execute([
            'key'      => $key,
            'value'    => $value,
            'label_ar' => $labels[$key],
            'value2'   => $value,
        ]);
    }

    // Bust page cache so changes appear immediately
    @unlink(__DIR__ . '/../cache/data/page_data.json');

    jsonResponse(['success' => true, 'message' => 'تم حفظ الإعدادات بنجاح']);
}

jsonResponse(['success' => false, 'message' => 'Bad request'], 400);
