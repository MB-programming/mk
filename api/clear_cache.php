<?php
// ================================================
// Cache Management API
// GET  ?status=1  → returns cache status (no clearing)
// POST            → clears all caches
// Protected: requires active admin session
// ================================================
require_once __DIR__ . '/config.php';
requireAuth();

$cacheDir  = __DIR__ . '/../cache/data/';
$cacheFile = $cacheDir . 'api_response.json';
$metaFile  = $cacheDir . 'api_meta.json';

$method = $_SERVER['REQUEST_METHOD'];

// ── GET: return cache status ──────────────────────
if ($method === 'GET') {
    $status = ['exists' => false];
    if (is_file($cacheFile) && is_file($metaFile)) {
        $meta   = json_decode(file_get_contents($metaFile), true) ?: [];
        $age    = time() - (int)($meta['written_at'] ?? 0);
        $ttl    = (int)($meta['ttl'] ?? 300);
        $status = [
            'exists'       => true,
            'age_seconds'  => $age,
            'ttl'          => $ttl,
            'remaining'    => max(0, $ttl - $age),
            'generated_at' => $meta['generated_at'] ?? date('Y-m-d H:i:s', $age ? time() - $age : time()),
            'fresh'        => $age < $ttl,
        ];
    }
    jsonResponse(['success' => true, 'cache' => $status]);
}

if ($method !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

// ── POST: clear all caches ────────────────────────
$cleared = [];

// 1. Data file cache (most impactful — eliminates all DB queries on next hit)
$deleted = 0;
foreach ([$cacheFile, $metaFile] as $f) {
    if (is_file($f)) { unlink($f); $deleted++; }
}
// Also bust index.php page cache
$pageDataCache = $cacheDir . 'page_data.json';
if (is_file($pageDataCache)) { unlink($pageDataCache); $deleted++; }
if ($deleted) $cleared[] = 'كاش البيانات (API + صفحة)';

// 2. Minified CSS/JS cache
$minifyCache = __DIR__ . '/../cache/minify/';
if (is_dir($minifyCache)) {
    $count = 0;
    foreach (glob($minifyCache . '*') as $f) {
        if (is_file($f)) { unlink($f); $count++; }
    }
    if ($count) $cleared[] = "CSS/JS ($count ملف)";
}

// 3. PHP OPcache
if (function_exists('opcache_reset') && opcache_reset()) {
    $cleared[] = 'OPcache';
}

// 4. Add missing DB indexes (idempotent — safe to run repeatedly)
try {
    $db = getDB();
    $indexes = [
        "ALTER TABLE registrations ADD INDEX idx_national_id (national_id(10))",
        "ALTER TABLE registrations ADD INDEX idx_created_at  (created_at)",
        "ALTER TABLE registrations ADD INDEX idx_city        (city(40))",
        "ALTER TABLE registrations ADD INDEX idx_gender      (gender(6))",
        "ALTER TABLE categories    ADD INDEX idx_slug        (slug(80))",
        "ALTER TABLE branches      ADD INDEX idx_active      (is_active)",
        "ALTER TABLE brands        ADD INDEX idx_active      (is_active)",
    ];
    foreach ($indexes as $sql) {
        try { $db->exec($sql); } catch (PDOException $e) { /* already exists */ }
    }
} catch (Exception $e) { /* non-fatal */ }

// 5. Record clear timestamp in settings
try {
    $db   = $db ?? getDB();
    $stmt = $db->prepare("INSERT INTO settings (`key`, value, label_ar)
                          VALUES ('cache_cleared_at', :v, 'آخر مسح للكاش')
                          ON DUPLICATE KEY UPDATE value = :v2");
    $ts = date('Y-m-d H:i:s');
    $stmt->execute(['v' => $ts, 'v2' => $ts]);
} catch (Exception $e) { /* non-fatal */ }

$msg = count($cleared)
    ? 'تم المسح: ' . implode(' + ', $cleared)
    : 'لا يوجد كاش مخزّن حالياً';

jsonResponse(['success' => true, 'message' => $msg, 'cleared' => $cleared]);
