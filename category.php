<?php
require_once __DIR__ . '/api/config.php';

// HTML Minification: controlled by perf_minify_html setting
try {
    $db_perf  = getDB();
    $perf_row = $db_perf->query("SELECT value FROM settings WHERE `key` = 'perf_minify_html' LIMIT 1")->fetch();
    if ($perf_row && $perf_row['value'] !== '0') {
        ob_start(function($buf) {
            return preg_replace(
                ['/>(\s{2,})</','/>(\s+)</','/<(\s+)/'],
                ['><','> <','<'],
                $buf
            );
        });
    }
} catch (Exception $_) {}

$slug     = trim($_GET['slug'] ?? '');
$category = null;

if ($slug) {
    try {
        $db   = getDB();
        $stmt = $db->prepare("SELECT * FROM categories WHERE slug = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$slug]);
        $category = $stmt->fetch();
    } catch (Exception $e) {
        // سنتعامل معه في الـ HTML
    }
}

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

$pageTitle = $category ? h($category['name_ar']) . ' | مخازن العناية' : 'الأقسام | مخازن العناية';
$pageDesc  = $category ? h($category['description'] ?? '') : '';
$canonical = 'https://' . ($_SERVER['HTTP_HOST'] ?? '') . '/category.php?slug=' . urlencode($slug);
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $pageTitle ?></title>
<?php if ($pageDesc): ?>
<meta name="description" content="<?= $pageDesc ?>">
<?php endif; ?>
<link rel="canonical" href="<?= h($canonical) ?>">
<meta property="og:type"  content="website">
<meta property="og:title" content="<?= $pageTitle ?>">
<?php if ($pageDesc): ?><meta property="og:description" content="<?= $pageDesc ?>"><?php endif; ?>

<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800&display=swap" rel="stylesheet" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
<link rel="stylesheet" href="api/minify.php?f=assets/css/style.css&v=3" />
<style>
  .cat-page-hero {
    background: var(--black);
    padding: 60px 0 48px;
    position: relative;
    overflow: hidden;
  }
  .cat-page-hero::after {
    content: '';
    position: absolute;
    inset: 0;
    background: radial-gradient(ellipse at 50% 0%, rgba(255,207,6,0.12) 0%, transparent 70%);
    pointer-events: none;
  }
  .cat-hero-inner {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 18px;
    text-align: center;
    position: relative;
    z-index: 1;
  }
  .cat-hero-icon {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: var(--gold);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 32px;
    color: var(--black);
  }
  .cat-hero-title {
    font-size: 32px;
    font-weight: 800;
    color: #fff;
    margin: 0;
  }
  .cat-hero-desc {
    font-size: 16px;
    color: rgba(255,255,255,0.7);
    max-width: 600px;
    margin: 0 auto;
    line-height: 1.7;
  }
  .cat-body-section {
    background: #fff;
    padding: 60px 0 80px;
  }
  .cat-body-content {
    max-width: 860px;
    margin: 0 auto;
    font-size: 16px;
    line-height: 1.9;
    color: #222;
  }
  .cat-body-content h1,
  .cat-body-content h2,
  .cat-body-content h3 { color: var(--black); margin-top: 1.6em; font-weight: 800; }
  .cat-body-content p   { margin-bottom: 1em; }
  .cat-body-content ul,
  .cat-body-content ol  { padding-right: 1.4em; margin-bottom: 1em; }
  .cat-body-content li  { margin-bottom: 6px; }
  .cat-body-content table {
    width: 100%;
    border-collapse: collapse;
    margin: 1.4em 0;
    font-size: 14px;
  }
  .cat-body-content th,
  .cat-body-content td {
    border: 1px solid #e0e0e0;
    padding: 10px 14px;
    text-align: right;
  }
  .cat-body-content th { background: var(--black); color: var(--gold); font-weight: 700; }
  .cat-body-content tr:nth-child(even) td { background: #f8f8f8; }
  .back-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 32px;
    color: var(--black);
    font-weight: 700;
    font-size: 14px;
    text-decoration: none;
    border: 2px solid var(--black);
    padding: 9px 22px;
    border-radius: 30px;
    transition: all .25s;
  }
  .back-btn:hover { background: var(--black); color: var(--gold); }
  .not-found-wrap {
    text-align: center;
    padding: 100px 20px;
    color: #999;
  }
  .not-found-wrap i { font-size: 60px; color: #ddd; margin-bottom: 20px; display: block; }
  @media (max-width: 768px) {
    .cat-hero-title { font-size: 24px; }
    .cat-hero-icon  { width: 64px; height: 64px; font-size: 26px; }
  }
</style>
</head>
<body>

<!-- Header -->
<header class="site-header visible scrolled" id="site-header">
  <div class="container header-inner">
    <a href="index.php" class="header-logo">
      <img src="logob.webp" alt="مخازن العناية" />
    </a>
    <nav class="header-nav">
      <a href="index.php">الرئيسية</a>
      <a href="index.php#branches">الفروع</a>
      <a href="index.php#brands">البراندات</a>
      <a href="index.php#contact">تواصل معنا</a>
    </nav>
  </div>
</header>
<script>
  // Sticky header on category page (no GSAP needed)
  window.addEventListener('scroll', function() {
    document.getElementById('site-header')
      .classList.toggle('scrolled', window.scrollY > 60);
  });
</script>

<?php if ($category): ?>

<!-- Hero -->
<section class="cat-page-hero">
  <div class="container cat-hero-inner">
    <div class="cat-hero-icon">
      <i class="fas <?= h($category['icon'] ?? 'fa-star') ?>"></i>
    </div>
    <h1 class="cat-hero-title"><?= h($category['name_ar']) ?></h1>
    <?php if (!empty($category['description'])): ?>
    <p class="cat-hero-desc"><?= h($category['description']) ?></p>
    <?php endif; ?>
  </div>
</section>

<!-- Body Content -->
<?php if (!empty($category['body'])): ?>
<section class="cat-body-section">
  <div class="container">
    <a href="index.php#categories" class="back-btn">
      <i class="fas fa-arrow-right"></i> العودة للأقسام
    </a>
    <div class="cat-body-content">
      <?= $category['body'] ?>
    </div>
  </div>
</section>
<?php else: ?>
<section class="cat-body-section">
  <div class="container">
    <a href="index.php#categories" class="back-btn">
      <i class="fas fa-arrow-right"></i> العودة للأقسام
    </a>
  </div>
</section>
<?php endif; ?>

<?php else: ?>

<div class="not-found-wrap">
  <i class="fas fa-box-open"></i>
  <h2>القسم غير موجود</h2>
  <p>تأكد من الرابط أو عد إلى <a href="index.html">الصفحة الرئيسية</a></p>
</div>

<?php endif; ?>

<!-- Footer -->
<footer class="site-footer" style="margin-top:0">
  <div class="footer-pattern">
    <img src="pattern-5.webp" alt="" aria-hidden="true" loading="lazy" />
  </div>
  <div class="container">
    <div class="footer-inner">
      <img src="logob.webp" alt="مخازن العناية" class="footer-logo" loading="lazy" />
      <p class="footer-copy">© 2025 مخازن العناية. جميع الحقوق محفوظة.</p>
    </div>
  </div>
</footer>

<script src="assets/js/main.js" defer></script>
</body>
</html>
