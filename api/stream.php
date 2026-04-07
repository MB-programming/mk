<?php
// ================================================
// SSE Stream — Pushes site data in real-time
// Clients connect once; server pushes updates
// whenever the cache file changes.
// ================================================
require_once __DIR__ . '/config.php';

// ── SSE headers ──────────────────────────────────
header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');   // nginx: disable proxy buffering
header('Access-Control-Allow-Origin: *');

// Flush output as soon as possible
if (ob_get_level()) ob_end_clean();

// ── Helper: load data directly (no HTTP round-trip) ──
function fetchSiteData(): string {
    $cacheDir  = __DIR__ . '/../cache/data/';
    $cacheFile = $cacheDir . 'api_response.json';
    $metaFile  = $cacheDir . 'api_meta.json';

    // Try the cache file first
    if (is_file($cacheFile) && is_file($metaFile)) {
        $meta = json_decode(file_get_contents($metaFile), true) ?: [];
        $age  = time() - (int)($meta['written_at'] ?? 0);
        $ttl  = max(60, (int)($meta['ttl'] ?? 300));
        if ($age < $ttl) {
            return file_get_contents($cacheFile);
        }
    }

    // Cache miss — query DB (mirrors get_data.php logic)
    $db = getDB();

    $settingsRows = $db->query("SELECT `key`, value FROM settings")->fetchAll();
    $settings = [];
    foreach ($settingsRows as $r) $settings[$r['key']] = $r['value'];

    $cacheTTL     = max(60, (int)($settings['cache_ttl'] ?? 300));
    $cacheEnabled = ($settings['perf_cache_api'] ?? '1') !== '0';

    $branches = $db->query("
        SELECT id, name_ar, name_en, city_ar, city_en, address_ar, address_en, phone, map_url, sort_order
        FROM branches WHERE is_active = 1
        ORDER BY sort_order ASC, city_ar ASC LIMIT 200
    ")->fetchAll();

    $hours_rows = $db->query("
        SELECT branch_id, day_type, day_label, opens_at, closes_at, is_closed, note, sort_order
        FROM branch_hours WHERE is_active = 1
        ORDER BY branch_id ASC, sort_order ASC, id ASC
    ")->fetchAll();

    $hours_map = [];
    foreach ($hours_rows as $h) {
        $hours_map[$h['branch_id']][] = [
            'day_type'  => $h['day_type'],
            'day_label' => $h['day_label'],
            'opens_at'  => substr($h['opens_at'],  0, 5),
            'closes_at' => substr($h['closes_at'], 0, 5),
            'is_closed' => (bool)$h['is_closed'],
            'note'      => $h['note'],
        ];
    }
    foreach ($branches as &$b) {
        $b['working_hours'] = $hours_map[$b['id']] ?? [];
    }
    unset($b);

    $categories = $db->query("
        SELECT id, name_ar, slug, icon, description
        FROM categories WHERE is_active = 1
        ORDER BY sort_order ASC, id ASC LIMIT 200
    ")->fetchAll();

    $brands = $db->query("
        SELECT id, name_ar, name_en, logo_url, website_url, sort_order
        FROM brands WHERE is_active = 1
        ORDER BY sort_order ASC, name_en ASC LIMIT 200
    ")->fetchAll();

    $social = $db->query("
        SELECT id, platform, platform_ar, url, username, icon, color, sort_order
        FROM social_media WHERE is_active = 1
        ORDER BY sort_order ASC LIMIT 50
    ")->fetchAll();

    $contact = $db->query("
        SELECT id, type, value, label_ar
        FROM contact_info WHERE is_active = 1
        ORDER BY sort_order ASC LIMIT 50
    ")->fetchAll();

    $articles = [];
    try {
        $artPDO = new PDO(
            'mysql:host=localhost;dbname=makhazenalenaya_blogs;charset=utf8mb4',
            'makhazenalenaya_blogs',
            '?BN0Mn5x$(K$',
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_TIMEOUT            => 3,
            ]
        );
        $articles = $artPDO->query("
            SELECT id, title, slug, excerpt, cover_image, category, author_name, published_at, is_featured
            FROM articles WHERE is_active = 1
            ORDER BY is_featured DESC, sort_order ASC, created_at DESC LIMIT 50
        ")->fetchAll();
    } catch (Exception $e) {
        $articles = [];
    }

    $response = json_encode([
        'success'    => true,
        'branches'   => $branches,
        'brands'     => $brands,
        'categories' => $categories,
        'articles'   => $articles,
        'social'     => $social,
        'contact'    => $contact,
        'settings'   => $settings,
    ], JSON_UNESCAPED_UNICODE);

    // Write cache so the next poll can use it
    if ($cacheEnabled) {
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
            file_put_contents($cacheDir . '.htaccess', "Deny from all\n");
        }
        $tmp = $cacheFile . '.tmp.' . getmypid();
        if (file_put_contents($tmp, $response, LOCK_EX) !== false) {
            rename($tmp, $cacheFile);
            file_put_contents($metaFile, json_encode([
                'written_at'   => time(),
                'ttl'          => $cacheTTL,
                'generated_at' => date('Y-m-d H:i:s'),
            ]));
        } else {
            @unlink($tmp);
        }
    }

    return $response;
}

// ── Helper: send one SSE event ────────────────────
function sendEvent(string $data, string $event = 'message', ?int $id = null): void {
    if ($id !== null) echo "id: $id\n";
    echo "event: $event\n";
    // SSE data must be single-line; encode newlines
    echo 'data: ' . str_replace("\n", '\n', $data) . "\n\n";
    flush();
}

// ── Main loop ────────────────────────────────────
$metaFile    = __DIR__ . '/../cache/data/api_meta.json';
$lastWritten = 0;
$eventId     = 0;
$pollInterval = 5;   // seconds between DB/cache checks
$maxRuntime   = 55;  // seconds before graceful close (nginx/PHP timeout safety)
$startTime    = time();

// Send an initial "connected" ping so the client knows the stream is alive
sendEvent('{"connected":true}', 'ping');

while (true) {
    // Stop before hitting server-side timeouts
    if ((time() - $startTime) >= $maxRuntime) {
        sendEvent('{"reconnect":true}', 'ping');
        break;
    }

    // Abort if client disconnected
    if (connection_aborted()) break;

    // Read current cache timestamp
    $currentWritten = 0;
    if (is_file($metaFile)) {
        $meta = json_decode(file_get_contents($metaFile), true) ?: [];
        $currentWritten = (int)($meta['written_at'] ?? 0);
    }

    // Push data on first poll OR whenever cache was refreshed
    if ($currentWritten !== $lastWritten) {
        $payload = fetchSiteData();
        $lastWritten = $currentWritten ?: time();
        sendEvent($payload, 'data', ++$eventId);
    } else {
        // Keepalive comment (prevents proxy timeouts)
        echo ": keepalive\n\n";
        flush();
    }

    sleep($pollInterval);
}
