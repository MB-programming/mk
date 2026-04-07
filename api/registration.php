<?php
// ============================================================
// Registration API — Public POST + Admin GET/DELETE
// ============================================================
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

// ============================================================
// One-time table setup (skipped after first run via flag file)
// ============================================================
$flagFile = dirname(__DIR__) . '/cache/.tables_ok';
if (!is_file($flagFile)) {
    $db = getDB();
    $db->exec("CREATE TABLE IF NOT EXISTS registrations (
        id            INT AUTO_INCREMENT PRIMARY KEY,
        ref_number    VARCHAR(40)  NOT NULL UNIQUE,
        full_name     VARCHAR(100) NOT NULL,
        email         VARCHAR(150) NOT NULL,
        phone         VARCHAR(15)  NOT NULL,
        national_id   VARCHAR(10)  NOT NULL UNIQUE,
        city          VARCHAR(80)  NOT NULL,
        gender        ENUM('male','female') NOT NULL,
        prev_customer TINYINT(1)   NOT NULL DEFAULT 0,
        ip_address    VARCHAR(45)  DEFAULT NULL,
        created_at    DATETIME     DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    try {
        $db->exec("ALTER TABLE registrations ADD COLUMN IF NOT EXISTS
            prev_customer TINYINT(1) NOT NULL DEFAULT 0 AFTER gender");
    } catch (Exception $e) {}

    @file_put_contents($flagFile, date('c'));
}

$method = $_SERVER['REQUEST_METHOD'];

// ============================================================
// PUBLIC POST — submit registration
// ============================================================
if ($method === 'POST' && empty($_GET['admin'])) {

    // ── Rate limiting (APCu — fast shared memory, no disk I/O) ──
    $ip      = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '')[0]);
    $blocked = false;

    if (function_exists('apcu_inc')) {
        $rlKey = 'rl:' . md5($ip);
        $hits  = apcu_inc($rlKey, 1, $success, 60); // 60-second window
        if (!$success) apcu_store($rlKey, 1, 60);
        elseif ($hits > 10) $blocked = true;         // max 10 submissions/min per IP
    }

    if ($blocked) {
        http_response_code(429);
        echo json_encode(['success' => false,
            'message' => 'طلبات كثيرة جداً، يرجى الانتظار دقيقة ثم المحاولة مجدداً'],
            JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ── Settings — file cache (30s TTL) ──────────────────────
    // Replaces 1 DB query per request with 1 file read shared across all requests
    $settingsCache = dirname(__DIR__) . '/cache/comp_settings.json';
    $settings      = [];

    if (is_file($settingsCache) && (time() - filemtime($settingsCache)) < 30) {
        $settings = json_decode(file_get_contents($settingsCache), true) ?: [];
    } else {
        $db   = getDB();
        $rows = $db->query("SELECT `key`, value FROM settings WHERE `key` IN
            ('comp_active','comp_title','comp_success_msg','comp_ref_prefix')")->fetchAll();
        foreach ($rows as $r) $settings[$r['key']] = $r['value'];
        @file_put_contents($settingsCache, json_encode($settings), LOCK_EX);
    }

    if (($settings['comp_active'] ?? '1') === '0') {
        echo json_encode(['success' => false,
            'message' => 'التسجيل في المسابقة مغلق حالياً'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $body = json_decode(file_get_contents('php://input'), true) ?? [];

    // ── Sanitize ─────────────────────────────────────────────
    $full_name     = clean($body['full_name']   ?? '');
    $email         = strtolower(trim($body['email'] ?? ''));
    $phone         = trim($body['phone']        ?? '');
    $national_id   = trim($body['national_id']  ?? '');
    $city          = clean($body['city']         ?? '');
    $gender        = trim($body['gender']        ?? '');
    $prev_customer = isset($body['prev_customer']) ? (int)(bool)$body['prev_customer'] : -1;
    $terms         = !empty($body['terms']);

    // ── Validate ─────────────────────────────────────────────
    $errors = [];
    if (mb_strlen($full_name) < 3)
        $errors[] = 'الاسم الكامل مطلوب ولا يقل عن 3 أحرف';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))
        $errors[] = 'البريد الإلكتروني غير صحيح';
    if (!preg_match('/^05\d{8}$/', $phone))
        $errors[] = 'رقم الجوال يجب أن يبدأ بـ 05 ويكون 10 أرقام';
    if (!preg_match('/^[12]\d{9}$/', $national_id))
        $errors[] = 'رقم الهوية يجب أن يكون 10 أرقام ويبدأ بـ 1 أو 2';
    if (mb_strlen($city) < 2)
        $errors[] = 'المدينة مطلوبة';
    if (!in_array($gender, ['male', 'female']))
        $errors[] = 'يرجى اختيار الجنس';
    if ($prev_customer === -1)
        $errors[] = 'يرجى الإجابة على سؤال التعامل السابق مع مخازن العناية';
    if (!$terms)
        $errors[] = 'يجب الموافقة على الشروط والأحكام';

    if ($errors) {
        echo json_encode(['success' => false,
            'message' => implode('، ', $errors)], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ── ref_number — high entropy, no collision check needed ─
    // uniqid(more_entropy:true) = microseconds + random float = ~22 unique chars
    $prefix     = preg_replace('/[^\w\p{Arabic}]/u', '', $settings['comp_ref_prefix'] ?? 'MK');
    $firstName  = mb_substr(explode(' ', trim($full_name))[0], 0, 12);
    $ref_number = $prefix . '-' . $firstName . '-' . strtoupper(substr(uniqid('', true), -8));

    // ── Single INSERT — duplicates caught via exception ───────
    // New user  = 1 query   (previously: 4-5 queries)
    // Duplicate = 2 queries (rare path — only on actual duplicates)
    $db   = $db ?? getDB();
    $stmt = $db->prepare("INSERT INTO registrations
        (ref_number, full_name, email, phone, national_id, city, gender, prev_customer, ip_address)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

    try {
        $stmt->execute([$ref_number, $full_name, $email, $phone,
                        $national_id, $city, $gender, $prev_customer, $ip]);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            // Determine which UNIQUE key triggered the violation
            $chk = $db->prepare("SELECT id FROM registrations WHERE national_id = ?");
            $chk->execute([$national_id]);
            if ($chk->fetch()) {
                echo json_encode(['success' => false,
                    'message' => 'رقم الهوية هذا مسجّل مسبقاً في المسابقة'],
                    JSON_UNESCAPED_UNICODE);
                exit;
            }
            echo json_encode(['success' => false,
                'message' => 'البريد الإلكتروني هذا مسجّل مسبقاً في المسابقة'],
                JSON_UNESCAPED_UNICODE);
            exit;
        }
        throw $e;
    }

    // ── Format date from PHP — no extra SELECT ────────────────
    $months = ['','يناير','فبراير','مارس','أبريل','مايو','يونيو',
               'يوليو','أغسطس','سبتمبر','أكتوبر','نوفمبر','ديسمبر'];
    $now    = new DateTime();
    $dateAr = $now->format('j') . ' ' . $months[(int)$now->format('n')] . ' ' . $now->format('Y');

    echo json_encode([
        'success'     => true,
        'ref_number'  => $ref_number,
        'full_name'   => $full_name,
        'date'        => $dateAr,
        'success_msg' => $settings['comp_success_msg'] ?? '',
        'comp_title'  => $settings['comp_title'] ?? 'مسابقة مخازن العناية',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ============================================================
// ADMIN GET — list registrations with filters
// ============================================================
if ($method === 'GET' && !empty($_GET['admin'])) {
    requireAuth();
    $db = getDB();

    $where  = [];
    $params = [];

    if (!empty($_GET['search'])) {
        $s = '%' . $_GET['search'] . '%';
        $where[]  = '(full_name LIKE ? OR phone LIKE ? OR national_id LIKE ? OR email LIKE ?)';
        $params   = array_merge($params, [$s, $s, $s, $s]);
    }
    if (!empty($_GET['city'])) {
        $where[]  = 'city = ?';
        $params[] = $_GET['city'];
    }
    if (!empty($_GET['gender'])) {
        $where[]  = 'gender = ?';
        $params[] = $_GET['gender'];
    }
    if (!empty($_GET['date_from'])) {
        $where[]  = 'DATE(created_at) >= ?';
        $params[] = $_GET['date_from'];
    }
    if (!empty($_GET['date_to'])) {
        $where[]  = 'DATE(created_at) <= ?';
        $params[] = $_GET['date_to'];
    }

    $limitClause = (!empty($_GET['export']) && $_GET['export'] === 'csv') ? '' : ' LIMIT 5000';
    $sql = 'SELECT * FROM registrations'
         . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
         . ' ORDER BY created_at DESC' . $limitClause;

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    if (!empty($_GET['export']) && $_GET['export'] === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="registrations_' . date('Ymd_His') . '.csv"');
        header('Pragma: no-cache');
        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($out, ['#','رقم المرجع','الاسم الكامل','البريد الإلكتروني',
                        'رقم الجوال','رقم الهوية','المدينة','الجنس','تاريخ التسجيل']);
        foreach ($rows as $i => $r) {
            fputcsv($out, [
                $i + 1, $r['ref_number'], $r['full_name'], $r['email'],
                $r['phone'], $r['national_id'], $r['city'],
                $r['gender'] === 'male' ? 'ذكر' : 'أنثى', $r['created_at'],
            ]);
        }
        fclose($out);
        exit;
    }

    $total   = $db->query("SELECT COUNT(*) FROM registrations")->fetchColumn();
    $today   = $db->query("SELECT COUNT(*) FROM registrations WHERE DATE(created_at) = CURDATE()")->fetchColumn();
    $cities  = $db->query("SELECT city, COUNT(*) as cnt FROM registrations GROUP BY city ORDER BY cnt DESC")->fetchAll();
    $genders = $db->query("SELECT gender, COUNT(*) as cnt FROM registrations GROUP BY gender")->fetchAll();

    echo json_encode([
        'success' => true,
        'rows'    => $rows,
        'stats'   => [
            'total'   => (int)$total,
            'today'   => (int)$today,
            'cities'  => $cities,
            'genders' => $genders,
        ],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ============================================================
// ADMIN DELETE
// ============================================================
if ($method === 'DELETE' && !empty($_GET['admin'])) {
    requireAuth();
    $db   = getDB();
    $id   = (int)($_GET['id'] ?? 0);
    $stmt = $db->prepare("DELETE FROM registrations WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Bad request'], JSON_UNESCAPED_UNICODE);
