<?php
// ================================================
// Branch Hours API
// GET public  → أوقات دوام فرع معين
// CRUD admin  → إضافة / تعديل / حذف
// ================================================
require_once __DIR__ . '/config.php';

$method    = $_SERVER['REQUEST_METHOD'];
$branch_id = intval($_GET['branch_id'] ?? 0);
$id        = intval($_GET['id']        ?? 0);
$admin     = !empty($_GET['admin']);

// ------------------------------------------------
// OPTIONS preflight
// ------------------------------------------------
if ($method === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    exit;
}

// ------------------------------------------------
// GET عام — أوقات دوام فرع معين
// GET /api/branch_hours.php?branch_id=5
// ------------------------------------------------
if ($method === 'GET' && !$admin && $branch_id && !$id) {
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    $db   = getDB();
    $stmt = $db->prepare("
        SELECT id, day_type, day_label, opens_at, closes_at, is_closed, note, sort_order
        FROM branch_hours
        WHERE branch_id = ? AND is_active = 1
        ORDER BY sort_order ASC, id ASC
    ");
    $stmt->execute([$branch_id]);
    $rows = $stmt->fetchAll();
    echo json_encode(['success' => true, 'data' => $rows], JSON_UNESCAPED_UNICODE);
    exit;
}

// ------------------------------------------------
// GET عام — كل أوقات الدوام دفعة واحدة
// GET /api/branch_hours.php
// يُستخدم في get_data.php لتحسين الأداء
// ------------------------------------------------
if ($method === 'GET' && !$admin && !$branch_id && !$id) {
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    $db   = getDB();
    $rows = $db->query("
        SELECT id, branch_id, day_type, day_label, opens_at, closes_at, is_closed, note, sort_order
        FROM branch_hours
        WHERE is_active = 1
        ORDER BY branch_id ASC, sort_order ASC, id ASC
    ")->fetchAll();
    echo json_encode(['success' => true, 'data' => $rows], JSON_UNESCAPED_UNICODE);
    exit;
}

// ------------------------------------------------
// من هنا فصاعداً — Admin only
// ------------------------------------------------
requireAuth();
$db = getDB();

// GET admin — كل ساعات فرع معين
if ($method === 'GET' && $admin && $branch_id && !$id) {
    $stmt = $db->prepare("
        SELECT * FROM branch_hours
        WHERE branch_id = ?
        ORDER BY sort_order ASC, id ASC
    ");
    $stmt->execute([$branch_id]);
    jsonResponse(['success' => true, 'data' => $stmt->fetchAll()]);
}

// GET admin — سجل واحد
if ($method === 'GET' && $admin && $id) {
    $stmt = $db->prepare("SELECT * FROM branch_hours WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) jsonResponse(['success' => false, 'message' => 'Not found'], 404);
    jsonResponse(['success' => true, 'data' => $row]);
}

// POST — إضافة وقت دوام
if ($method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    if (empty($body['branch_id'])) jsonResponse(['success' => false, 'message' => 'branch_id مطلوب'], 400);

    $stmt = $db->prepare("
        INSERT INTO branch_hours
            (branch_id, day_type, day_label, opens_at, closes_at, is_closed, note, sort_order, is_active)
        VALUES
            (:branch_id, :day_type, :day_label, :opens_at, :closes_at, :is_closed, :note, :sort_order, :is_active)
    ");
    $stmt->execute([
        'branch_id'  => intval($body['branch_id']),
        'day_type'   => clean($body['day_type']  ?? 'all'),
        'day_label'  => clean($body['day_label'] ?? ''),
        'opens_at'   => clean($body['opens_at']  ?? '09:00'),
        'closes_at'  => clean($body['closes_at'] ?? '22:00'),
        'is_closed'  => intval($body['is_closed'] ?? 0),
        'note'       => clean($body['note']       ?? ''),
        'sort_order' => intval($body['sort_order'] ?? 0),
        'is_active'  => intval($body['is_active']  ?? 1),
    ]);
    jsonResponse(['success' => true, 'message' => 'تم إضافة وقت الدوام', 'id' => $db->lastInsertId()]);
}

// PUT — تعديل
if ($method === 'PUT' && $id) {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $stmt = $db->prepare("
        UPDATE branch_hours SET
            day_type   = :day_type,
            day_label  = :day_label,
            opens_at   = :opens_at,
            closes_at  = :closes_at,
            is_closed  = :is_closed,
            note       = :note,
            sort_order = :sort_order,
            is_active  = :is_active
        WHERE id = :id
    ");
    $stmt->execute([
        'day_type'   => clean($body['day_type']  ?? 'all'),
        'day_label'  => clean($body['day_label'] ?? ''),
        'opens_at'   => clean($body['opens_at']  ?? '09:00'),
        'closes_at'  => clean($body['closes_at'] ?? '22:00'),
        'is_closed'  => intval($body['is_closed'] ?? 0),
        'note'       => clean($body['note']       ?? ''),
        'sort_order' => intval($body['sort_order'] ?? 0),
        'is_active'  => intval($body['is_active']  ?? 1),
        'id'         => $id,
    ]);
    jsonResponse(['success' => true, 'message' => 'تم التحديث']);
}

// DELETE
if ($method === 'DELETE' && $id) {
    $stmt = $db->prepare("DELETE FROM branch_hours WHERE id = ?");
    $stmt->execute([$id]);
    jsonResponse(['success' => true, 'message' => 'تم الحذف']);
}

jsonResponse(['success' => false, 'message' => 'Bad request'], 400);
