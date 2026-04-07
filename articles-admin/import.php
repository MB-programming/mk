<?php
require_once __DIR__ . '/../api/articles_config.php';

/* ── Auth ── */
if (session_status() === PHP_SESSION_NONE) {
    session_name(ART_SESSION_NAME);
    session_start();
}
if (empty($_SESSION['articles_admin_id'])) {
    header('Location: index.html'); exit;
}

/* ── Slug map ── */
$SLUGS = [
    1  => ['slug' => 'makhazen-alenaya-home',       'title_fallback' => 'مخازن العناية وجهتك الأولى'],
    2  => ['slug' => 'makhazen-alenaya-riyadh',     'title_fallback' => 'أقرب فرع في الرياض'],
    3  => ['slug' => 'product-consultation',         'title_fallback' => 'تجربة المنتجات واستشارات المختصات'],
    4  => ['slug' => 'best-skin-care-products',      'title_fallback' => 'دليل منتجات العناية بالبشرة'],
    5  => ['slug' => 'body-care-products-bride',     'title_fallback' => 'منتجات العناية بالجسم للعروس'],
    6  => ['slug' => 'original-makeup-brands',       'title_fallback' => 'أشهر ماركات المكياج الأصلية'],
    7  => ['slug' => 'top-10-cosmetics',             'title_fallback' => 'أفضل 10 مستحضرات تجميل مبيعاً'],
    8  => ['slug' => 'makhazen-jeddah-dammam',       'title_fallback' => 'مخازن العناية جدة والدمام'],
    9  => ['slug' => 'choose-body-care-set',         'title_fallback' => 'كيف تختارين مجموعة العناية بالجسم'],
    10 => ['slug' => 'makhazen-alenaya-offers',      'title_fallback' => 'أسرار الحصول على أفضل الأسعار'],
    11 => ['slug' => 'are-products-original',        'title_fallback' => 'هل منتجات مخازن العناية أصلية؟'],
    12 => ['slug' => 'korean-skincare-brands',       'title_fallback' => 'أفضل ماركات العناية الكورية'],
];

$articlesDir  = __DIR__ . '/../articles/';
$converterPy  = __DIR__ . '/../convert_docx.py';

/* ── Scan DOCX files ── */
$files = [];
if (is_dir($articlesDir)) {
    foreach (glob($articlesDir . '*.docx') as $f) {
        $files[] = basename($f);
    }
    natsort($files);
    $files = array_values($files);
}

/* ── Handle import POST ── */
$results = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['import'])) {
    $db = getDB();

    foreach ($_POST['import'] as $num => $filename) {
        $num = (int)$num;
        if (!$filename || !isset($SLUGS[$num])) continue;

        $filepath = $articlesDir . basename($filename);
        if (!file_exists($filepath)) {
            $results[] = ['num' => $num, 'file' => $filename, 'status' => 'error', 'msg' => 'الملف غير موجود'];
            continue;
        }

        // Convert DOCX → HTML via Python
        $escaped = escapeshellarg($filepath);
        $json    = shell_exec("python3 " . escapeshellarg($converterPy) . " $escaped 2>&1");
        $data    = json_decode($json, true);

        if (!$data || isset($data['error'])) {
            $results[] = ['num' => $num, 'file' => $filename, 'status' => 'error', 'msg' => $data['error'] ?? 'فشل التحويل'];
            continue;
        }

        $slug  = $SLUGS[$num]['slug'];
        $title = trim($data['title'])   ?: $SLUGS[$num]['title_fallback'];
        $excerpt = trim($data['excerpt']) ?: '';
        $body    = $data['body']         ?: '';

        // Check if slug already exists → UPDATE, else INSERT
        $exists = $db->prepare("SELECT id FROM articles WHERE slug = ? LIMIT 1");
        $exists->execute([$slug]);
        $row = $exists->fetch();

        if ($row) {
            $stmt = $db->prepare("
                UPDATE articles SET
                    title        = :title,
                    excerpt      = :excerpt,
                    body         = :body,
                    is_active    = 1,
                    published_at = COALESCE(published_at, NOW()),
                    updated_at   = NOW()
                WHERE slug = :slug
            ");
            $stmt->execute(['title' => $title, 'excerpt' => $excerpt, 'body' => $body, 'slug' => $slug]);
            $results[] = ['num' => $num, 'file' => $filename, 'status' => 'updated', 'msg' => 'تم التحديث', 'slug' => $slug, 'title' => $title];
        } else {
            $stmt = $db->prepare("
                INSERT INTO articles (title, slug, excerpt, body, author_name, is_active, published_at)
                VALUES (:title, :slug, :excerpt, :body, 'مخازن العناية', 1, NOW())
            ");
            $stmt->execute(['title' => $title, 'slug' => $slug, 'excerpt' => $excerpt, 'body' => $body]);
            $results[] = ['num' => $num, 'file' => $filename, 'status' => 'inserted', 'msg' => 'تمت الإضافة', 'slug' => $slug, 'title' => $title];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>استيراد المقالات</title>
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>
<style>
*, *::before, *::after { box-sizing: border-box; }
body { font-family:'Tajawal',sans-serif; margin:0; background:#0f1117; color:#cdd6f4; }
.wrap { max-width:900px; margin:0 auto; padding:40px 20px; }
h1 { color:#fff; margin-bottom:6px; }
.sub { color:#888; margin-bottom:30px; }
a.back { color:#7aa2f7; text-decoration:none; font-size:14px; }
a.back:hover { text-decoration:underline; }

.info-box { background:#1e2030; border:1px solid #2a2d3e; border-radius:10px; padding:16px 20px; margin-bottom:24px; font-size:14px; color:#aaa; }
.info-box code { background:#2a2d3e; padding:2px 8px; border-radius:4px; color:#e0af68; font-size:13px; }

table { width:100%; border-collapse:collapse; }
th { background:#1e2030; color:#7aa2f7; padding:12px; text-align:right; font-size:13px; border-bottom:1px solid #2a2d3e; }
td { padding:12px; border-bottom:1px solid #1a1d27; vertical-align:middle; font-size:14px; }
tr:hover td { background:#1e2030; }

select { background:#2a2d3e; border:1px solid #3a3d4e; color:#cdd6f4; padding:6px 10px; border-radius:6px; font-family:inherit; font-size:13px; width:100%; }
select:focus { outline:none; border-color:#7aa2f7; }

.badge { display:inline-block; padding:3px 10px; border-radius:20px; font-size:12px; font-weight:700; }
.badge-ok  { background:#1a3a2a; color:#9ece6a; border:1px solid #2a5a3a; }
.badge-err { background:#3a1a1a; color:#f7768e; border:1px solid #5a2a2a; }
.badge-upd { background:#1a2a4a; color:#7aa2f7; border:1px solid #2a3a6a; }
.badge-new { background:#2a3a1a; color:#b9f27c; border:1px solid #3a5a2a; }

.btn-import {
  background:#7aa2f7; color:#1a1b26; border:none;
  padding:12px 32px; border-radius:8px; font-family:inherit;
  font-size:16px; font-weight:700; cursor:pointer; margin-top:20px;
  display:flex; align-items:center; gap:8px;
}
.btn-import:hover { background:#6592e7; }
.btn-import:disabled { opacity:.5; cursor:not-allowed; }

.result-box { margin-top:30px; }
.result-item {
  display:flex; align-items:center; gap:14px;
  padding:10px 16px; border-radius:8px;
  margin-bottom:8px; background:#1e2030;
  border:1px solid #2a2d3e;
}
.result-icon { font-size:18px; width:24px; text-align:center; }
.result-ok   .result-icon { color:#9ece6a; }
.result-err  .result-icon { color:#f7768e; }
.result-upd  .result-icon { color:#7aa2f7; }
.result-text { flex:1; }
.result-text strong { display:block; color:#fff; }
.result-text small  { color:#888; font-size:12px; }
.file-missing { color:#888; font-style:italic; font-size:13px; }
</style>
</head>
<body>
<div class="wrap">
  <a href="dashboard.html" class="back"><i class="fas fa-arrow-right"></i> العودة للوحة التحكم</a>
  <h1 style="margin-top:16px"><i class="fas fa-file-import"></i> استيراد المقالات من Word</h1>
  <p class="sub">ضع ملفات الـ DOCX في فولدر <code>articles/</code> ثم حدد كل ملف للمقالة المناسبة</p>

  <div class="info-box">
    <i class="fas fa-folder-open" style="color:#e0af68;margin-left:8px"></i>
    مسار الفولدر: <code><?= htmlspecialchars($articlesDir) ?></code>
    &nbsp;—&nbsp;
    <?= count($files) ?> ملف DOCX موجود
    <?php if (!$files): ?>
    <br><br><i class="fas fa-exclamation-triangle" style="color:#f7768e"></i>
    <span style="color:#f7768e">لا توجد ملفات DOCX في الفولدر. ضع الملفات ثم أعد تحميل الصفحة.</span>
    <?php endif; ?>
  </div>

  <?php if ($results): ?>
  <div class="result-box">
    <h3 style="color:#fff;margin-bottom:12px">نتائج الاستيراد</h3>
    <?php foreach ($results as $r):
      $cls  = $r['status'] === 'error' ? 'result-err' : ($r['status'] === 'updated' ? 'result-upd' : 'result-ok');
      $icon = $r['status'] === 'error' ? 'fa-times-circle' : ($r['status'] === 'updated' ? 'fa-sync' : 'fa-check-circle');
    ?>
    <div class="result-item <?= $cls ?>">
      <div class="result-icon"><i class="fas <?= $icon ?>"></i></div>
      <div class="result-text">
        <strong><?= htmlspecialchars($r['title'] ?? $r['file']) ?></strong>
        <small>
          <?= htmlspecialchars($r['msg']) ?>
          <?php if (!empty($r['slug'])): ?>
          &nbsp;—&nbsp; <a href="../article.php?slug=<?= urlencode($r['slug']) ?>" target="_blank" style="color:#7aa2f7">معاينة <i class="fas fa-external-link-alt" style="font-size:11px"></i></a>
          <?php endif; ?>
        </small>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if ($files): ?>
  <form method="POST">
    <table>
      <thead>
        <tr>
          <th width="40">#</th>
          <th>المقالة</th>
          <th>Slug</th>
          <th>الملف المقابل</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($SLUGS as $num => $info): ?>
        <tr>
          <td style="color:#888"><?= $num ?></td>
          <td><?= htmlspecialchars($info['title_fallback']) ?></td>
          <td style="direction:ltr;font-size:12px;color:#e0af68"><?= htmlspecialchars($info['slug']) ?></td>
          <td>
            <select name="import[<?= $num ?>]">
              <option value="">— اختر ملف —</option>
              <?php
              // Auto-select if file named like "1.docx" or "01.docx" or "مقالة 1.docx"
              foreach ($files as $f):
                $autoMatch = preg_match('/^0?' . $num . '[\.\-_ ]|^مقالة\s*0?' . $num . '[\.\-_ ]/u', $f)
                          || $f === $num . '.docx'
                          || $f === sprintf('%02d', $num) . '.docx';
              ?>
              <option value="<?= htmlspecialchars($f) ?>" <?= $autoMatch ? 'selected' : '' ?>>
                <?= htmlspecialchars($f) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <button type="submit" class="btn-import" id="import-btn">
      <i class="fas fa-upload"></i> استيراد المحدد
    </button>
  </form>

  <script>
  document.querySelector('form').addEventListener('submit', function() {
    const btn = document.getElementById('import-btn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري الاستيراد...';
  });
  </script>
  <?php endif; ?>

</div>
</body>
</html>
