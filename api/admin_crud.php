<?php
// ================================================
// Admin CRUD API
// Protected: requires active session
// ================================================
require_once __DIR__ . '/config.php';
requireAuth();

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$table  = clean($_GET['table'] ?? '');
$id     = intval($_GET['id'] ?? 0);

$allowed_tables = ['branches', 'brands', 'articles', 'social_media', 'contact_info', 'categories'];
if (!in_array($table, $allowed_tables)) {
    jsonResponse(['success' => false, 'message' => 'Invalid table'], 400);
}

function bustPageCache(): void {
    @unlink(__DIR__ . '/../cache/data/page_data.json');
}

// ------------------------------------------------
// GET - list all records
// ------------------------------------------------
if ($method === 'GET' && !$id) {
    if ($table === 'categories') {
        // Idempotent migration: ensure SEO columns exist
        try { $db->exec("ALTER TABLE categories ADD COLUMN seo_title VARCHAR(255) NOT NULL DEFAULT ''"); } catch(PDOException $e) {}
        try { $db->exec("ALTER TABLE categories ADD COLUMN seo_description TEXT NOT NULL DEFAULT ''"); } catch(PDOException $e) {}
    }
    $rows = $db->query("SELECT * FROM `$table` ORDER BY sort_order ASC, id ASC")->fetchAll();
    jsonResponse(['success' => true, 'data' => $rows]);
}

// ------------------------------------------------
// GET single record
// ------------------------------------------------
if ($method === 'GET' && $id) {
    $stmt = $db->prepare("SELECT * FROM `$table` WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) jsonResponse(['success' => false, 'message' => 'Not found'], 404);
    jsonResponse(['success' => true, 'data' => $row]);
}

// ------------------------------------------------
// POST - create
// ------------------------------------------------
if ($method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];

    switch ($table) {
        case 'branches':
            $stmt = $db->prepare("INSERT INTO branches (name_ar, name_en, city_ar, city_en, address_ar, address_en, phone, map_url, is_active, sort_order)
                                  VALUES (:name_ar,:name_en,:city_ar,:city_en,:address_ar,:address_en,:phone,:map_url,:is_active,:sort_order)");
            $stmt->execute([
                'name_ar'    => clean($body['name_ar'] ?? ''),
                'name_en'    => clean($body['name_en'] ?? ''),
                'city_ar'    => clean($body['city_ar'] ?? ''),
                'city_en'    => clean($body['city_en'] ?? ''),
                'address_ar' => clean($body['address_ar'] ?? ''),
                'address_en' => clean($body['address_en'] ?? ''),
                'phone'      => clean($body['phone'] ?? ''),
                'map_url'    => clean($body['map_url'] ?? ''),
                'is_active'  => intval($body['is_active'] ?? 1),
                'sort_order' => intval($body['sort_order'] ?? 0),
            ]);
            break;

        case 'brands':
            $stmt = $db->prepare("INSERT INTO brands (name_ar, name_en, logo_url, website_url, is_active, sort_order)
                                  VALUES (:name_ar,:name_en,:logo_url,:website_url,:is_active,:sort_order)");
            $stmt->execute([
                'name_ar'     => clean($body['name_ar'] ?? ''),
                'name_en'     => clean($body['name_en'] ?? ''),
                'logo_url'    => clean($body['logo_url'] ?? ''),
                'website_url' => clean($body['website_url'] ?? ''),
                'is_active'   => intval($body['is_active'] ?? 1),
                'sort_order'  => intval($body['sort_order'] ?? 0),
            ]);
            break;

        case 'social_media':
            $stmt = $db->prepare("INSERT INTO social_media (platform, platform_ar, url, username, icon, color, is_active, sort_order)
                                  VALUES (:platform,:platform_ar,:url,:username,:icon,:color,:is_active,:sort_order)");
            $stmt->execute([
                'platform'    => clean($body['platform'] ?? ''),
                'platform_ar' => clean($body['platform_ar'] ?? ''),
                'url'         => clean($body['url'] ?? ''),
                'username'    => clean($body['username'] ?? ''),
                'icon'        => clean($body['icon'] ?? ''),
                'color'       => clean($body['color'] ?? '#ffffff'),
                'is_active'   => intval($body['is_active'] ?? 1),
                'sort_order'  => intval($body['sort_order'] ?? 0),
            ]);
            break;

        case 'contact_info':
            $stmt = $db->prepare("INSERT INTO contact_info (type, value, label_ar, is_active, sort_order)
                                  VALUES (:type,:value,:label_ar,:is_active,:sort_order)");
            $stmt->execute([
                'type'       => clean($body['type'] ?? ''),
                'value'      => clean($body['value'] ?? ''),
                'label_ar'   => clean($body['label_ar'] ?? ''),
                'is_active'  => intval($body['is_active'] ?? 1),
                'sort_order' => intval($body['sort_order'] ?? 0),
            ]);
            break;

        case 'categories':
            $stmt = $db->prepare("INSERT INTO categories (name_ar, slug, icon, description, body, seo_title, seo_description, is_active, sort_order)
                                  VALUES (:name_ar,:slug,:icon,:description,:body,:seo_title,:seo_description,:is_active,:sort_order)");
            $stmt->execute([
                'name_ar'         => clean($body['name_ar']         ?? ''),
                'slug'            => clean($body['slug']            ?? ''),
                'icon'            => clean($body['icon']            ?? 'fa-star'),
                'description'     => clean($body['description']     ?? ''),
                'body'            => $body['body']                  ?? '',
                'seo_title'       => clean($body['seo_title']       ?? ''),
                'seo_description' => clean($body['seo_description'] ?? ''),
                'is_active'       => intval($body['is_active']      ?? 1),
                'sort_order'      => intval($body['sort_order']     ?? 0),
            ]);
            break;

        case 'articles':
            // توليد slug تلقائي
            $slug = trim($body['slug'] ?? '');
            if (empty($slug)) {
                $slug = mb_strtolower(trim($body['title'] ?? ''));
            }
            $slug = preg_replace('/\s+/', '-', $slug);
            $slug = preg_replace('/[^\p{Arabic}a-z0-9\-]/u', '', $slug);
            $slug = trim(preg_replace('/-+/', '-', $slug), '-');
            if (empty($slug)) $slug = 'article-' . time();
            // تفرّد الـ slug
            $base = $slug; $suffix = 1;
            while (true) {
                $chk = $db->prepare("SELECT id FROM articles WHERE slug = ?");
                $chk->execute([$slug]);
                if (!$chk->fetch()) break;
                $slug = $base . '-' . $suffix++;
            }
            $stmt = $db->prepare("INSERT INTO articles
                (title, slug, excerpt, body, cover_image, category, tags,
                 seo_title, seo_description, author_name,
                 is_active, is_featured, sort_order, published_at)
                VALUES
                (:title,:slug,:excerpt,:body,:cover_image,:category,:tags,
                 :seo_title,:seo_description,:author_name,
                 :is_active,:is_featured,:sort_order,:published_at)");
            $stmt->execute([
                'title'           => clean($body['title']           ?? ''),
                'slug'            => $slug,
                'excerpt'         => clean($body['excerpt']         ?? ''),
                'body'            => $body['body']                  ?? '',
                'cover_image'     => clean($body['cover_image']     ?? ''),
                'category'        => clean($body['category']        ?? ''),
                'tags'            => clean($body['tags']            ?? ''),
                'seo_title'       => clean($body['seo_title']       ?? ''),
                'seo_description' => clean($body['seo_description'] ?? ''),
                'author_name'     => clean($body['author_name']     ?? 'مخازن العناية'),
                'is_active'       => intval($body['is_active']      ?? 1),
                'is_featured'     => intval($body['is_featured']    ?? 0),
                'sort_order'      => intval($body['sort_order']     ?? 0),
                'published_at'    => !empty($body['published_at']) ? $body['published_at'] : date('Y-m-d H:i:s'),
            ]);
            break;
    }
    bustPageCache();
    jsonResponse(['success' => true, 'message' => 'تم الإضافة بنجاح', 'id' => $db->lastInsertId()]);
}

// ------------------------------------------------
// PUT - update
// ------------------------------------------------
if ($method === 'PUT' && $id) {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];

    switch ($table) {
        case 'branches':
            $stmt = $db->prepare("UPDATE branches SET name_ar=:name_ar, name_en=:name_en, city_ar=:city_ar, city_en=:city_en,
                                  address_ar=:address_ar, address_en=:address_en, phone=:phone, map_url=:map_url,
                                  is_active=:is_active, sort_order=:sort_order WHERE id=:id");
            $stmt->execute([
                'name_ar'    => clean($body['name_ar'] ?? ''),
                'name_en'    => clean($body['name_en'] ?? ''),
                'city_ar'    => clean($body['city_ar'] ?? ''),
                'city_en'    => clean($body['city_en'] ?? ''),
                'address_ar' => clean($body['address_ar'] ?? ''),
                'address_en' => clean($body['address_en'] ?? ''),
                'phone'      => clean($body['phone'] ?? ''),
                'map_url'    => clean($body['map_url'] ?? ''),
                'is_active'  => intval($body['is_active'] ?? 1),
                'sort_order' => intval($body['sort_order'] ?? 0),
                'id'         => $id,
            ]);
            break;

        case 'brands':
            $stmt = $db->prepare("UPDATE brands SET name_ar=:name_ar, name_en=:name_en, logo_url=:logo_url,
                                  website_url=:website_url, is_active=:is_active, sort_order=:sort_order WHERE id=:id");
            $stmt->execute([
                'name_ar'     => clean($body['name_ar'] ?? ''),
                'name_en'     => clean($body['name_en'] ?? ''),
                'logo_url'    => clean($body['logo_url'] ?? ''),
                'website_url' => clean($body['website_url'] ?? ''),
                'is_active'   => intval($body['is_active'] ?? 1),
                'sort_order'  => intval($body['sort_order'] ?? 0),
                'id'          => $id,
            ]);
            break;

        case 'social_media':
            $stmt = $db->prepare("UPDATE social_media SET platform=:platform, platform_ar=:platform_ar, url=:url,
                                  username=:username, icon=:icon, color=:color, is_active=:is_active, sort_order=:sort_order WHERE id=:id");
            $stmt->execute([
                'platform'    => clean($body['platform'] ?? ''),
                'platform_ar' => clean($body['platform_ar'] ?? ''),
                'url'         => clean($body['url'] ?? ''),
                'username'    => clean($body['username'] ?? ''),
                'icon'        => clean($body['icon'] ?? ''),
                'color'       => clean($body['color'] ?? '#ffffff'),
                'is_active'   => intval($body['is_active'] ?? 1),
                'sort_order'  => intval($body['sort_order'] ?? 0),
                'id'          => $id,
            ]);
            break;

        case 'contact_info':
            $stmt = $db->prepare("UPDATE contact_info SET type=:type, value=:value, label_ar=:label_ar,
                                  is_active=:is_active, sort_order=:sort_order WHERE id=:id");
            $stmt->execute([
                'type'       => clean($body['type'] ?? ''),
                'value'      => clean($body['value'] ?? ''),
                'label_ar'   => clean($body['label_ar'] ?? ''),
                'is_active'  => intval($body['is_active'] ?? 1),
                'sort_order' => intval($body['sort_order'] ?? 0),
                'id'         => $id,
            ]);
            break;

        case 'categories':
            $stmt = $db->prepare("UPDATE categories SET name_ar=:name_ar, slug=:slug, icon=:icon,
                                  description=:description, body=:body,
                                  seo_title=:seo_title, seo_description=:seo_description,
                                  is_active=:is_active, sort_order=:sort_order WHERE id=:id");
            $stmt->execute([
                'name_ar'         => clean($body['name_ar']         ?? ''),
                'slug'            => clean($body['slug']            ?? ''),
                'icon'            => clean($body['icon']            ?? 'fa-star'),
                'description'     => clean($body['description']     ?? ''),
                'body'            => $body['body']                  ?? '',
                'seo_title'       => clean($body['seo_title']       ?? ''),
                'seo_description' => clean($body['seo_description'] ?? ''),
                'is_active'       => intval($body['is_active']      ?? 1),
                'sort_order'      => intval($body['sort_order']     ?? 0),
                'id'              => $id,
            ]);
            break;

        case 'articles':
            $stmt = $db->prepare("UPDATE articles SET
                title=:title, excerpt=:excerpt, body=:body,
                cover_image=:cover_image, category=:category, tags=:tags,
                seo_title=:seo_title, seo_description=:seo_description,
                author_name=:author_name,
                is_active=:is_active, is_featured=:is_featured,
                sort_order=:sort_order, published_at=:published_at
                WHERE id=:id");
            $stmt->execute([
                'title'           => clean($body['title']           ?? ''),
                'excerpt'         => clean($body['excerpt']         ?? ''),
                'body'            => $body['body']                  ?? '',
                'cover_image'     => clean($body['cover_image']     ?? ''),
                'category'        => clean($body['category']        ?? ''),
                'tags'            => clean($body['tags']            ?? ''),
                'seo_title'       => clean($body['seo_title']       ?? ''),
                'seo_description' => clean($body['seo_description'] ?? ''),
                'author_name'     => clean($body['author_name']     ?? 'مخازن العناية'),
                'is_active'       => intval($body['is_active']      ?? 1),
                'is_featured'     => intval($body['is_featured']    ?? 0),
                'sort_order'      => intval($body['sort_order']     ?? 0),
                'published_at'    => !empty($body['published_at']) ? $body['published_at'] : date('Y-m-d H:i:s'),
                'id'              => $id,
            ]);
            break;
    }
    bustPageCache();
    jsonResponse(['success' => true, 'message' => 'تم التحديث بنجاح']);
}

// ------------------------------------------------
// DELETE
// ------------------------------------------------
if ($method === 'DELETE' && $id) {
    $stmt = $db->prepare("DELETE FROM `$table` WHERE id = ?");
    $stmt->execute([$id]);
    bustPageCache();
    jsonResponse(['success' => true, 'message' => 'تم الحذف بنجاح']);
}

// ------------------------------------------------
// Toggle active status
// ------------------------------------------------
if ($method === 'PATCH' && $id) {
    $stmt = $db->prepare("UPDATE `$table` SET is_active = NOT is_active WHERE id = ?");
    $stmt->execute([$id]);
    bustPageCache();
    jsonResponse(['success' => true, 'message' => 'تم تغيير الحالة']);
}

jsonResponse(['success' => false, 'message' => 'Bad request'], 400);
