<?php
// ============================================================
// Articles API — CRUD كامل
// GET  public  → قائمة المقالات / مقال واحد
// CRUD admin   → إضافة / تعديل / حذف
// ============================================================
require_once __DIR__ . '/articles_config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

$method = $_SERVER['REQUEST_METHOD'];
$id     = intval($_GET['id']   ?? 0);
$slug   = trim($_GET['slug']   ?? '');
$admin  = !empty($_GET['admin']);

$db = getDB();

// ============================================================
// GET PUBLIC — قائمة المقالات
// ============================================================
if ($method === 'GET' && !$admin && !$id && !$slug) {
    $featured = !empty($_GET['featured']) ? 1 : null;
    $limit    = min(intval($_GET['limit'] ?? 20), 100);
    $offset   = intval($_GET['offset'] ?? 0);

    $where = 'WHERE is_active = 1';
    $params = [];
    if ($featured !== null) { $where .= ' AND is_featured = ?'; $params[] = $featured; }

    $stmt = $db->prepare("
        SELECT id, title, slug, excerpt, cover_image, category, tags,
               seo_title, seo_description, og_image,
               is_featured, sort_order, view_count, published_at, created_at
        FROM articles $where
        ORDER BY is_featured DESC, sort_order ASC, published_at DESC
        LIMIT ? OFFSET ?
    ");
    $params[] = $limit;
    $params[] = $offset;
    $stmt->execute($params);

    $countStmt = $db->prepare("SELECT COUNT(*) FROM articles $where");
    $countStmt->execute(array_slice($params, 0, -2));
    $total = $countStmt->fetchColumn();

    echo json_encode([
        'success' => true,
        'data'    => $stmt->fetchAll(),
        'total'   => (int)$total,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ============================================================
// GET PUBLIC — مقال واحد بالـ slug
// ============================================================
if ($method === 'GET' && !$admin && $slug) {
    $stmt = $db->prepare("SELECT * FROM articles WHERE slug = ? LIMIT 1");
    $stmt->execute([$slug]);
    $row = $stmt->fetch();
    if (!$row) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'المقال غير موجود'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // زيادة view_count
    $db->prepare("UPDATE articles SET view_count = view_count + 1 WHERE id = ?")->execute([$row['id']]);

    echo json_encode(['success' => true, 'data' => $row], JSON_UNESCAPED_UNICODE);
    exit;
}

// ============================================================
// GET PUBLIC — مقال واحد بالـ id
// ============================================================
if ($method === 'GET' && !$admin && $id) {
    $stmt = $db->prepare("SELECT * FROM articles WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) { http_response_code(404); echo json_encode(['success' => false, 'message' => 'المقال غير موجود']); exit; }
    echo json_encode(['success' => true, 'data' => $row], JSON_UNESCAPED_UNICODE);
    exit;
}

// ============================================================
// Admin routes — تحتاج مصادقة
// ============================================================
requireAuth();

// GET ADMIN — كل المقالات (بما فيها المعطلة)
if ($method === 'GET' && $admin && !$id) {
    $stmt = $db->query("SELECT * FROM articles ORDER BY sort_order ASC, published_at DESC");
    echo json_encode(['success' => true, 'data' => $stmt->fetchAll()], JSON_UNESCAPED_UNICODE);
    exit;
}

// GET ADMIN — مقال واحد
if ($method === 'GET' && $admin && $id) {
    $stmt = $db->prepare("SELECT * FROM articles WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) { http_response_code(404); echo json_encode(['success' => false, 'message' => 'غير موجود']); exit; }
    echo json_encode(['success' => true, 'data' => $row], JSON_UNESCAPED_UNICODE);
    exit;
}

// ============================================================
// POST — إضافة مقال
// ============================================================
if ($method === 'POST') {
    $b = json_decode(file_get_contents('php://input'), true) ?? [];

    // توليد slug تلقائي لو مش موجود
    if (empty($b['slug'])) {
        $b['slug'] = generateSlug($b['title'] ?? '', $db);
    } else {
        $b['slug'] = generateSlug($b['slug'], $db);
    }

    $stmt = $db->prepare("
        INSERT INTO articles
            (title, slug, excerpt, body, cover_image, category, tags,
             seo_title, seo_description, seo_keywords, canonical_url,
             og_title, og_description, og_image,
             twitter_title, twitter_description, twitter_image,
             schema_type, author_name,
             is_active, is_featured, sort_order, published_at)
        VALUES
            (:title, :slug, :excerpt, :body, :cover_image, :category, :tags,
             :seo_title, :seo_description, :seo_keywords, :canonical_url,
             :og_title, :og_description, :og_image,
             :twitter_title, :twitter_description, :twitter_image,
             :schema_type, :author_name,
             :is_active, :is_featured, :sort_order, :published_at)
    ");

    $stmt->execute(buildParams($b));
    $newId = $db->lastInsertId();

    echo json_encode(['success' => true, 'message' => 'تم إضافة المقال', 'id' => $newId, 'slug' => $b['slug']], JSON_UNESCAPED_UNICODE);
    exit;
}

// ============================================================
// PUT — تعديل مقال
// ============================================================
if ($method === 'PUT' && $id) {
    $b = json_decode(file_get_contents('php://input'), true) ?? [];

    // تحديث الـ slug لو تغير
    if (!empty($b['slug'])) {
        $b['slug'] = generateSlug($b['slug'], $db, $id);
    }

    $stmt = $db->prepare("
        UPDATE articles SET
            title              = :title,
            slug               = :slug,
            excerpt            = :excerpt,
            body               = :body,
            cover_image        = :cover_image,
            category           = :category,
            tags               = :tags,
            seo_title          = :seo_title,
            seo_description    = :seo_description,
            seo_keywords       = :seo_keywords,
            canonical_url      = :canonical_url,
            og_title           = :og_title,
            og_description     = :og_description,
            og_image           = :og_image,
            twitter_title      = :twitter_title,
            twitter_description= :twitter_description,
            twitter_image      = :twitter_image,
            schema_type        = :schema_type,
            author_name        = :author_name,
            is_active          = :is_active,
            is_featured        = :is_featured,
            sort_order         = :sort_order,
            published_at       = :published_at
        WHERE id = $id
    ");

    $stmt->execute(buildParams($b));
    echo json_encode(['success' => true, 'message' => 'تم التحديث', 'slug' => $b['slug'] ?? ''], JSON_UNESCAPED_UNICODE);
    exit;
}

// ============================================================
// PATCH — toggle is_active
// ============================================================
if ($method === 'PATCH' && $id) {
    $stmt = $db->prepare("UPDATE articles SET is_active = 1 - is_active WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode(['success' => true, 'message' => 'تم تغيير الحالة'], JSON_UNESCAPED_UNICODE);
    exit;
}

// ============================================================
// DELETE
// ============================================================
if ($method === 'DELETE' && $id) {
    $db->prepare("DELETE FROM articles WHERE id = ?")->execute([$id]);
    echo json_encode(['success' => true, 'message' => 'تم الحذف'], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Bad request'], JSON_UNESCAPED_UNICODE);

// ============================================================
// HELPERS
// ============================================================
function buildParams(array $b): array {
    return [
        'title'               => clean($b['title']               ?? ''),
        'slug'                => clean($b['slug']                ?? ''),
        'excerpt'             => clean($b['excerpt']             ?? ''),
        'body'                => $b['body']                      ?? '',    // HTML — لا تنظفه
        'cover_image'         => clean($b['cover_image']         ?? ''),
        'category'            => clean($b['category']            ?? ''),
        'tags'                => clean($b['tags']                ?? ''),
        'seo_title'           => clean($b['seo_title']           ?? ''),
        'seo_description'     => clean($b['seo_description']     ?? ''),
        'seo_keywords'        => clean($b['seo_keywords']        ?? ''),
        'canonical_url'       => clean($b['canonical_url']       ?? ''),
        'og_title'            => clean($b['og_title']            ?? ''),
        'og_description'      => clean($b['og_description']      ?? ''),
        'og_image'            => clean($b['og_image']            ?? ''),
        'twitter_title'       => clean($b['twitter_title']       ?? ''),
        'twitter_description' => clean($b['twitter_description'] ?? ''),
        'twitter_image'       => clean($b['twitter_image']       ?? ''),
        'schema_type'         => in_array($b['schema_type'] ?? '', ['Article','BlogPosting','NewsArticle'])
                                   ? $b['schema_type'] : 'Article',
        'author_name'         => clean($b['author_name']         ?? 'مخازن العناية'),
        'is_active'           => intval($b['is_active']          ?? 1),
        'is_featured'         => intval($b['is_featured']        ?? 0),
        'sort_order'          => intval($b['sort_order']         ?? 0),
        'published_at'        => !empty($b['published_at']) ? $b['published_at'] : date('Y-m-d H:i:s'),
    ];
}

function generateSlug(string $text, $db, int $excludeId = 0): string {
    // تحويل العربية والإنجليزية لـ slug
    $slug = mb_strtolower(trim($text));
    $slug = preg_replace('/\s+/', '-', $slug);
    $slug = preg_replace('/[^\p{Arabic}a-z0-9\-]/u', '', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    $slug = trim($slug, '-');

    if (empty($slug)) $slug = 'article-' . time();

    // التحقق من عدم التكرار
    $base   = $slug;
    $suffix = 1;
    while (true) {
        $stmt = $db->prepare("SELECT id FROM articles WHERE slug = ? AND id != ?");
        $stmt->execute([$slug, $excludeId]);
        if (!$stmt->fetch()) break;
        $slug = $base . '-' . $suffix++;
    }
    return $slug;
}
