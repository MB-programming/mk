<?php
require_once __DIR__ . '/api/articles_config.php';

$slug    = trim($_GET['slug'] ?? '');
$article = null;

if ($slug) {
    try {
        $db   = getDB();
        $stmt = $db->prepare("SELECT * FROM articles WHERE slug = ? LIMIT 1");
        $stmt->execute([$slug]);
        $row  = $stmt->fetch();
        if ($row) {
            $article = $row;
            // زيادة المشاهدات
            $db->prepare("UPDATE articles SET view_count = view_count + 1 WHERE id = ?")->execute([$row['id']]);
        }
    } catch (Exception $e) {
        // سنتعامل معه في الـ HTML
    }
}

/* ── SEO helpers ── */
function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

$pageTitle  = $article
    ? h($article['seo_title'] ?: $article['title'])
    : 'مقال | مخازن العناية';

$pageDesc   = $article
    ? h($article['seo_description'] ?: $article['excerpt'] ?: '')
    : '';

$ogTitle    = $article ? h($article['og_title']       ?: $article['seo_title']       ?: $article['title'])     : $pageTitle;
$ogDesc     = $article ? h($article['og_description'] ?: $article['seo_description'] ?: $article['excerpt'] ?: '') : '';
$ogImage    = $article ? h($article['og_image']       ?: $article['cover_image']     ?: '')                    : '';
$canonical  = $article && $article['canonical_url']
    ? h($article['canonical_url'])
    : 'https://' . ($_SERVER['HTTP_HOST'] ?? '') . '/article.php?slug=' . urlencode($slug);
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<!-- SEO — server-side -->
<title><?= $pageTitle ?></title>
<?php if ($pageDesc): ?>
<meta name="description" content="<?= $pageDesc ?>">
<?php endif; ?>
<?php if ($article && $article['seo_keywords']): ?>
<meta name="keywords" content="<?= h($article['seo_keywords']) ?>">
<?php endif; ?>
<link rel="canonical" href="<?= $canonical ?>">

<!-- Open Graph -->
<meta property="og:type"        content="article">
<meta property="og:title"       content="<?= $ogTitle ?>">
<meta property="og:description" content="<?= $ogDesc ?>">
<?php if ($ogImage): ?>
<meta property="og:image"       content="<?= $ogImage ?>">
<?php endif; ?>
<meta property="og:url"         content="<?= $canonical ?>">

<!-- Twitter Card -->
<meta name="twitter:card"        content="summary_large_image">
<meta name="twitter:title"       content="<?= $ogTitle ?>">
<meta name="twitter:description" content="<?= $ogDesc ?>">
<?php if ($ogImage): ?>
<meta name="twitter:image"       content="<?= $ogImage ?>">
<?php endif; ?>

<link rel="icon" href="/favicon.jpeg">
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>

<style>
*, *::before, *::after { box-sizing: border-box; }
body { font-family: 'Tajawal', sans-serif; margin:0; padding:0; background:#f5f5f5; color:#333; }
a { text-decoration:none; color:#007bff; }

/* ===== HEADER ===== */
.site-header {
  position:fixed; top:0; right:0; left:0; z-index:999;
  padding:14px 24px;
  background:rgba(0,0,0,0.85);
  backdrop-filter:blur(10px);
  transition:background .3s, padding .3s, box-shadow .3s;
}
.site-header.scrolled {
  background:rgba(255,255,255,0.97);
  box-shadow:0 2px 20px rgba(0,0,0,0.12);
  padding:8px 24px;
}
.header-inner { display:flex; align-items:center; justify-content:space-between; max-width:1200px; margin:0 auto; }
.header-logo img { height:48px; width:auto; }
.site-header:not(.scrolled) .header-logo img { filter:brightness(0) invert(1); }
.site-header.scrolled .header-logo img { filter:none; }
.header-nav { display:flex; align-items:center; gap:18px; }
.header-nav a { color:#fff; font-size:15px; font-weight:500; transition:color .2s; }
.site-header.scrolled .header-nav a { color:#222; }
.header-nav a:hover { color:#FFCF06; }
.site-header.scrolled .header-nav a:hover { color:#B8860B; }
.header-nav .nav-home {
  background:#FFCF06; color:#000 !important;
  padding:7px 18px; border-radius:50px; font-weight:700; font-size:14px;
}
.header-nav .nav-home:hover { background:#e6b800; }

/* ===== HERO ===== */
.hero-section {
  background:<?= $article && $article['cover_image']
    ? 'linear-gradient(rgba(0,0,0,0.65),rgba(0,0,0,0.65)), url("' . h($article['cover_image']) . '") center/cover no-repeat'
    : 'linear-gradient(rgba(0,0,0,0.65),rgba(0,0,0,0.65)), #111 center/cover no-repeat'
  ?>;
  padding:130px 20px 80px;
  color:#fff; text-align:center;
}
.hero-section h1 { font-size:38px; margin:0 0 14px; font-weight:800; line-height:1.4; }
.hero-section p  { font-size:18px; color:#ddd; margin:0; max-width:700px; margin-inline:auto; line-height:1.7; }

/* ===== META BAR ===== */
.article-meta {
  display:flex; flex-wrap:wrap; gap:14px;
  justify-content:center; align-items:center;
  font-size:14px; color:#666;
  padding:22px 15px 0;
  max-width:900px; margin:0 auto;
}
.meta-category {
  background:#FFCF06; color:#000;
  padding:4px 14px; border-radius:50px;
  font-weight:700; font-size:13px;
}
.meta-item { display:flex; align-items:center; gap:6px; }
.meta-item i { color:#007bff; font-size:13px; }

/* ===== ARTICLE ===== */
.article-wrap { max-width:900px; margin:0 auto; padding:30px 20px 60px; }

.article-cover {
  width:100%; border-radius:14px;
  overflow:hidden; margin:28px 0 36px;
  box-shadow:0 4px 20px rgba(0,0,0,0.12);
}
.article-cover img { width:100%; display:block; }

.article-title { font-size:32px; font-weight:800; color:#111; margin:0 0 24px; line-height:1.5; }

.article-body { line-height:2; font-size:18px; color:#444; }
.article-body h2, .article-body h3 { margin-top:36px; color:#222; }
.article-body p  { margin:0 0 20px; }
.article-body img { max-width:100%; border-radius:10px; margin:20px 0; display:block; height:auto; }
.article-body img[src=""], .article-body img:not([src]) { display:none; }
.article-body ul, .article-body ol { padding-right:24px; margin:0 0 20px; }
.article-body blockquote {
  border-right:4px solid #FFCF06;
  margin:20px 0; padding:12px 20px;
  background:#fffbea; color:#555; font-style:italic;
}

.article-tags { margin-top:36px; display:flex; flex-wrap:wrap; gap:8px; }
.article-tags span {
  background:#eef2ff; color:#3d5af1;
  padding:4px 12px; border-radius:20px; font-size:13px;
}

.error-box {
  text-align:center; padding:80px 20px;
  color:#c00; font-size:18px;
}

.article-footer {
  margin-top:60px; padding-top:20px;
  border-top:1px solid #e0e0e0;
  text-align:center; color:#aaa; font-size:14px;
}

@media(max-width:600px){
  .hero-section h1 { font-size:26px; }
  .hero-section    { padding:100px 16px 60px; }
  .article-body    { font-size:16px; }
  .article-title   { font-size:24px; }
}
</style>
</head>

<body>

<!-- ===== HEADER ===== -->
<header class="site-header" id="site-header">
  <div class="header-inner">
    <a href="/" class="header-logo">
      <img src="/logob.webp" alt="مخازن العناية" />
    </a>
    <nav class="header-nav">
      <a href="https://wa.me/966920029921" title="واتساب">
        <i class="fa-brands fa-whatsapp" style="font-size:20px"></i>
      </a>
      <a href="tel:+966920029921">
        <i class="fas fa-phone" style="font-size:16px"></i> 92002 9921
      </a>
      <a href="/" class="nav-home"><i class="fas fa-home"></i> الرئيسية</a>
    </nav>
  </div>
</header>

<!-- ===== HERO ===== -->
<section class="hero-section">
  <div>
    <h1><?= $article ? h($article['title']) : 'مقال غير موجود' ?></h1>
    <?php if ($article && $article['excerpt']): ?>
    <p><?= h($article['excerpt']) ?></p>
    <?php endif; ?>
  </div>
</section>

<?php if ($article): ?>

<!-- ===== META BAR ===== -->
<div class="article-meta">
  <?php if ($article['category']): ?>
  <span class="meta-category"><?= h($article['category']) ?></span>
  <?php endif; ?>

  <?php if ($article['author_name']): ?>
  <span class="meta-item">
    <i class="fa fa-user"></i>
    <?= h($article['author_name']) ?>
  </span>
  <?php endif; ?>

  <?php if ($article['published_at']): ?>
  <span class="meta-item">
    <i class="fa fa-calendar-alt"></i>
    <time datetime="<?= h($article['published_at']) ?>">
      <?= date('d / m / Y', strtotime($article['published_at'])) ?>
    </time>
  </span>
  <?php endif; ?>

  <span class="meta-item">
    <i class="fa fa-eye"></i>
    <?= number_format((int)$article['view_count']) ?> مشاهدة
  </span>
</div>

<!-- ===== ARTICLE ===== -->
<article class="article-wrap">

  <h1 class="article-title"><?= h($article['title']) ?></h1>

  <?php if ($article['cover_image']): ?>
  <div class="article-cover">
    <img src="<?= h($article['cover_image']) ?>" alt="<?= h($article['title']) ?>">
  </div>
  <?php endif; ?>

  <div class="article-body">
    <?= $article['body'] ?>
  </div>

  <?php if ($article['tags']): ?>
  <div class="article-tags">
    <?php foreach (explode(',', $article['tags']) as $tag):
          $tag = trim($tag);
          if ($tag): ?>
    <span><?= h($tag) ?></span>
    <?php endif; endforeach; ?>
  </div>
  <?php endif; ?>

  <div class="article-footer">
    © 2025 مخازن العناية. جميع الحقوق محفوظة.
  </div>

</article>

<?php else: ?>
<div class="article-wrap">
  <div class="error-box">
    <i class="fas fa-exclamation-circle"></i>
    <?= $slug ? 'المقال غير موجود أو تم حذفه' : 'لم يتم تحديد مقال في الرابط' ?>
  </div>
</div>
<?php endif; ?>

<script>
const header = document.getElementById('site-header');
window.addEventListener('scroll', () => header.classList.toggle('scrolled', window.scrollY > 60));

// Add lazy loading to all images in article body
document.querySelectorAll('.article-body img').forEach(img => {
  if (!img.hasAttribute('loading')) img.setAttribute('loading', 'lazy');
  if (!img.hasAttribute('decoding')) img.setAttribute('decoding', 'async');
});
</script>

</body>
</html>
