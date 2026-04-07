<?php
// ============================================================
// index.php — كل الداتا تيجي من DB مباشرة (zero client requests)
// ============================================================
require_once __DIR__ . '/api/config.php';

// ── File-based cache (5-minute TTL) ──────────────────────────
$cacheDir  = __DIR__ . '/cache/data';
$cacheFile = $cacheDir . '/page_data.json';
$cacheTTL  = 300; // seconds

$pageData = null;
if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTTL) {
    $pageData = file_get_contents($cacheFile);
}

if (!$pageData) {
    // ── Defaults ──────────────────────────────────────────────
    $settings   = [];
    $branches   = [];
    $brands     = [];
    $categories = [];
    $articles   = [];
    $social     = [];
    $contact    = [];

    try {
        $db = getDB();

        // Settings
        $rows = $db->query("SELECT `key`, value FROM settings")->fetchAll();
        foreach ($rows as $r) $settings[$r['key']] = $r['value'];

        // Branches
        $branches = $db->query("
            SELECT id, name_ar, name_en, city_ar, city_en, address_ar, address_en, phone, map_url, sort_order
            FROM branches WHERE is_active = 1
            ORDER BY sort_order ASC, city_ar ASC LIMIT 200
        ")->fetchAll();

        // Branch hours (one query, no N+1)
        $hoursRows = $db->query("
            SELECT branch_id, day_type, day_label, opens_at, closes_at, is_closed, note, sort_order
            FROM branch_hours WHERE is_active = 1
            ORDER BY branch_id ASC, sort_order ASC, id ASC
        ")->fetchAll();
        $hoursMap = [];
        foreach ($hoursRows as $h) {
            $hoursMap[$h['branch_id']][] = [
                'day_type'  => $h['day_type'],
                'day_label' => $h['day_label'],
                'opens_at'  => substr($h['opens_at'],  0, 5),
                'closes_at' => substr($h['closes_at'], 0, 5),
                'is_closed' => (bool)$h['is_closed'],
                'note'      => $h['note'],
            ];
        }
        foreach ($branches as &$b) {
            $b['working_hours'] = $hoursMap[$b['id']] ?? [];
        }
        unset($b);

        // Categories
        $categories = $db->query("
            SELECT id, name_ar, slug, icon, description
            FROM categories WHERE is_active = 1
            ORDER BY sort_order ASC, id ASC LIMIT 200
        ")->fetchAll();

        // Brands
        $brands = $db->query("
            SELECT id, name_ar, name_en, logo_url, website_url, sort_order
            FROM brands WHERE is_active = 1
            ORDER BY sort_order ASC, name_en ASC LIMIT 200
        ")->fetchAll();

        // Social
        $social = $db->query("
            SELECT id, platform, platform_ar, url, username, icon, color, sort_order
            FROM social_media WHERE is_active = 1
            ORDER BY sort_order ASC LIMIT 50
        ")->fetchAll();

        // Contact
        $contact = $db->query("
            SELECT id, type, value, label_ar
            FROM contact_info WHERE is_active = 1
            ORDER BY sort_order ASC LIMIT 50
        ")->fetchAll();

        // Articles (separate DB, 3s timeout)
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

    } catch (Exception $e) {
        // DB unavailable — JS will use fallback static data
    }

    // ── Build & cache payload ──────────────────────────────────
    $pageData = json_encode([
        'success'    => true,
        'branches'   => $branches,
        'brands'     => $brands,
        'categories' => $categories,
        'articles'   => $articles,
        'social'     => $social,
        'contact'    => $contact,
        'settings'   => $settings,
    ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);

    // Atomic write: tmp → rename (prevents partial reads)
    if (!is_dir($cacheDir)) @mkdir($cacheDir, 0755, true);
    $tmp = $cacheFile . '.tmp';
    if (@file_put_contents($tmp, $pageData, LOCK_EX) !== false) {
        @rename($tmp, $cacheFile);
    }
}

// settings needed for header/body codes
$_decoded   = json_decode($pageData, true);
$settings   = $_decoded['settings'] ?? [];

// ── Inline codes (no JS fetch needed) ────────────────────────
$headerCode = $settings['header_code'] ?? '';
$bodyCode   = $settings['body_code']   ?? '';

// ── Inline minified CSS to eliminate render-blocking request ──
function minifyCSS(string $css): string {
    $css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);
    $css = preg_replace('/\s*([{}:;,>~+])\s*/', '$1', $css);
    $css = preg_replace('/\s+/', ' ', $css);
    return trim(str_replace(';}', '}', $css));
}
$cssFile  = __DIR__ . '/assets/css/style.css';
$cssCache = __DIR__ . '/cache/minify/' . md5('assets/css/style.css') . '.css';
if (is_file($cssCache) && filemtime($cssCache) >= filemtime($cssFile)) {
    $inlineCSS = file_get_contents($cssCache);
} else {
    $inlineCSS = minifyCSS(file_get_contents($cssFile));
    file_put_contents($cssCache, $inlineCSS);
}
?><!doctype html>
<html lang="ar" dir="rtl">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="theme-color" content="#000000" />
    <title>مخازن العناية | Makhazen Alenayah</title>
    <meta name="description" content="مخازن العناية - وجهتك الأولى للجمال والعناية. 25 فرع في أنحاء المملكة العربية السعودية." />
    <link rel="icon" type="image/x-icon" href="favicon.jpeg" />
    <meta name="keywords" content="مخازن العناية" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <style>
           /* ── Hero Slider (replaces trophy when images exist) ──── */
    .hero-slider-wrap {
      position: relative; overflow: hidden; margin-bottom: 24px;
      border-radius: 14px;
      max-width: 560px; margin-left: auto; margin-right: auto;
      box-shadow: 0 4px 32px rgba(0,0,0,0.5);
    }
    .hero-slider-track {
      display: flex; transition: transform .5s ease;
    }
    .hero-slider-track img {
      min-width: 100%; width: 100%; height: 220px;
      object-fit: cover; display: block; border-radius: 14px;
    }
    .hero-slider-btn {
      position: absolute; top: 50%; transform: translateY(-50%);
      background: rgba(0,0,0,0.55); border: 1px solid rgba(255,207,6,0.4);
      color: #FFCF06; width: 32px; height: 32px; border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      cursor: pointer; font-size: 12px; z-index: 2;
    }
    .hero-slider-btn.prev { right: 8px; }
    .hero-slider-btn.next { left: 8px; }
    .hero-slider-dots {
      position: absolute; bottom: 8px; left: 50%; transform: translateX(-50%);
      display: flex; gap: 5px;
    }
    .hero-slider-dot {
      width: 6px; height: 6px; border-radius: 50%;
      background: rgba(255,255,255,0.35); cursor: pointer; transition: background .25s;
    }
    .hero-slider-dot.active { background: #FFCF06; }
    .hero-banner h1 {
      font-size: clamp(22px, 5vw, 36px); font-weight: 800;
      color: #FFCF06; margin-bottom: 10px; font-family: 'Tajawal', sans-serif;
    }
    .hero-banner p { font-size: 16px; color: #888; max-width: 480px; margin: 0 auto; font-family: 'Tajawal', sans-serif; }

    .form-wrap { max-width: 640px; margin: 36px auto 0; padding: 0 16px; }
    .form-card {
      background: #1a1a1a; border: 1px solid rgba(255,207,6,0.25);
      border-radius: 20px; padding: 36px 32px;
    }
    .form-section-title {
      font-size: 13px; font-weight: 700; color: #FFCF06;
      text-transform: uppercase; letter-spacing: 1px;
      margin-bottom: 20px; padding-bottom: 10px;
      border-bottom: 1px solid rgba(255,207,6,0.25);
      font-family: 'Tajawal', sans-serif;
    }

    .field-group { margin-bottom: 20px; }
    .field-group > label {
      display: block; font-size: 14px; font-weight: 700;
      color: #f0f0f0; margin-bottom: 7px; font-family: 'Tajawal', sans-serif;
    }
    .field-group label span.req { color: #FFCF06; margin-right: 2px; }
    .input-wrap { position: relative; }
    .input-wrap > i {
      position: absolute; right: 14px; top: 50%;
      transform: translateY(-50%); color: #888; font-size: 15px; pointer-events: none;
    }
    .field-input {
      width: 100%; background: #0e0e0e;
      border: 1.5px solid rgba(255,255,255,0.1); border-radius: 10px;
      padding: 13px 42px 13px 14px; font-size: 15px;
      font-family: 'Tajawal', sans-serif; color: #f0f0f0; outline: none;
      transition: border-color .25s ease, box-shadow .25s ease;
      appearance: none; -webkit-appearance: none;
    }
    .field-input::placeholder { color: #888; }
    .field-input:focus { border-color: #FFCF06; box-shadow: 0 0 0 3px rgba(255,207,6,0.12); }
    .field-input.error { border-color: #ff5c5c; box-shadow: 0 0 0 3px rgba(255,92,92,0.1); }
    select.field-input {
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%23888' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");
      background-repeat: no-repeat; background-position: left 14px center; padding-left: 36px;
    }
    .field-hint { font-size: 12px; color: #888; margin-top: 5px; font-family: 'Tajawal', sans-serif; }
    .field-error { font-size: 12px; color: #ff5c5c; margin-top: 5px; display: none; font-family: 'Tajawal', sans-serif; }
    .field-error.show { display: block; }
    .row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
    @media (max-width: 520px) { .row-2 { grid-template-columns: 1fr; } }

    .gender-row { display: flex; gap: 16px; }
    .gender-option { flex: 1; position: relative; }
    .gender-option input[type="radio"] { position: absolute; opacity: 0; width: 0; }
    .gender-label {
      display: flex; align-items: center; justify-content: center; gap: 8px;
      padding: 13px; border: 1.5px solid rgba(255,255,255,0.1); border-radius: 10px;
      background: #0e0e0e; cursor: pointer; font-size: 15px; font-weight: 700;
      color: #888; transition: all .25s ease; user-select: none;
      font-family: 'Tajawal', sans-serif;
    }
    .gender-option input:checked + .gender-label {
      border-color: #FFCF06; color: #FFCF06;
      background: rgba(255,207,6,0.08); box-shadow: 0 0 0 3px rgba(255,207,6,0.12);
    }
    .gender-label:hover { border-color: rgba(255,207,6,0.4); }

    .terms-wrap {
      display: flex; align-items: flex-start; gap: 12px;
      background: rgba(255,207,6,0.04); border: 1px solid rgba(255,207,6,0.25);
      border-radius: 10px; padding: 14px; cursor: pointer;
    }
    .terms-wrap input[type="checkbox"] {
      width: 20px; height: 20px; min-width: 20px;
      accent-color: #FFCF06; cursor: pointer; margin-top: 1px;
    }
    .terms-text { font-size: 13px; color: #888; line-height: 1.7; font-family: 'Tajawal', sans-serif; }
    .terms-text a { color: #FFCF06; text-decoration: underline; }
    .terms-text strong { color: #ff5c5c; }

    .btn-submit {
      width: 100%; margin-top: 28px; padding: 16px;
      background: #FFCF06; color: #000; border: none; border-radius: 12px;
      font-family: 'Tajawal', sans-serif; font-size: 18px; font-weight: 800;
      cursor: pointer; transition: opacity .25s ease, transform .25s ease;
      display: flex; align-items: center; justify-content: center; gap: 10px;
    }
    .btn-submit:hover { opacity: .9; transform: translateY(-1px); }
    .btn-submit:active { transform: scale(.98); }
    .btn-submit:disabled { opacity: .5; cursor: not-allowed; transform: none; }
    .btn-submit .spinner {
      width: 20px; height: 20px; border: 2.5px solid rgba(0,0,0,0.2);
      border-top-color: #000; border-radius: 50%;
      animation: spin .7s linear infinite; display: none;
    }
    .btn-submit.loading .spinner { display: block; }
    .btn-submit.loading .btn-text { display: none; }
    @keyframes spin { to { transform: rotate(360deg); } }

    .closed-banner {
      background: rgba(255,92,92,0.08); border: 1px solid rgba(255,92,92,0.3);
      border-radius: 12px; padding: 24px; text-align: center; color: #ff5c5c;
      font-size: 16px; font-weight: 700; font-family: 'Tajawal', sans-serif;
    }

    .swal2-popup { font-family: 'Tajawal', sans-serif !important; direction: rtl; }
    .swal-congrats { text-align: center; padding: 8px 0; }
    .swal-congrats .big-icon { font-size: 60px; margin-bottom: 12px; }
    .swal-congrats h2 { font-size: 22px; font-weight: 800; color: #FFCF06; margin-bottom: 8px; font-family: 'Tajawal', sans-serif; }
    .swal-congrats .sub-msg { font-size: 15px; color: #aaa; margin-bottom: 20px; line-height: 1.6; font-family: 'Tajawal', sans-serif; }
    .swal-ref-card {
      background: rgba(255,207,6,0.06); border: 1px solid rgba(255,207,6,0.25);
      border-radius: 12px; padding: 16px 20px; text-align: right;
    }
    .swal-ref-card .ref-row {
      display: flex; align-items: center; gap: 10px; padding: 6px 0;
      font-size: 14px; color: #ddd; border-bottom: 1px solid rgba(255,255,255,0.05);
      font-family: 'Tajawal', sans-serif;
    }
    .swal-ref-card .ref-row:last-child { border-bottom: none; }
    .swal-ref-card .ref-row i { color: #FFCF06; width: 18px; text-align: center; }
    .swal-ref-card .ref-val { font-weight: 700; color: #fff; margin-right: auto; }

    .site-footer { margin-top: 60px; }
    
    
    .hero-slider-track img{height: 317px;}
    .hero-slider-wrap {
 
    max-width: 1080px;
 
}

@media (max-width: 768px) {
  .hero-slider-track img {
    min-width: 100%;
    width: 100%;
    height: 220px;
    object-fit: contain;
    display: block;
    border-radius: 14px;
  }
  
  .hero-slider-wrap {
    padding: 0;
    margin: 0;
    margin-bottom: 0;
    margin-left: 0;
    margin-right: 0px;
    box-shadow:none;
}
.hero-content {
    padding:0;}
    
    .hero-content {
  
    gap: 0;

}
}
    </style>
    <link rel="preload" as="style"
      href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;700;800&display=swap"
      onload="this.onload=null;this.rel='stylesheet'" />
    <noscript>
      <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;700;800&display=swap" rel="stylesheet" />
    </noscript>
    <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin />
    <!-- FontAwesome subset: base + solid + brands only (saves ~18KB unused CSS) -->
    <link rel="preload" as="style"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/fontawesome.min.css"
      onload="this.onload=null;this.rel='stylesheet'" />
    <link rel="preload" as="style"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/solid.min.css"
      onload="this.onload=null;this.rel='stylesheet'" />
    <link rel="preload" as="style"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/brands.min.css"
      onload="this.onload=null;this.rel='stylesheet'" />
    <noscript>
      <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/fontawesome.min.css" />
      <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/solid.min.css" />
      <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/brands.min.css" />
    </noscript>
    <link rel="preload" as="image" href="api/img.php?src=pattern-1.webp&w=1440&q=55" fetchpriority="high" />
    <link rel="preload" as="image"
      href="api/img.php?src=logob.webp&w=260"
      imagesrcset="api/img.php?src=logob.webp&w=260 260w, api/img.php?src=logob.webp&w=520 520w"
      imagesizes="260px"
      fetchpriority="high" />
    <!-- Inline CSS — eliminates render-blocking stylesheet request -->
    <style><?= $inlineCSS ?>
/* FontAwesome font-display: swap — shows fallback instead of invisible text */
@font-face{font-family:'Font Awesome 6 Free';font-style:normal;font-weight:900;font-display:swap;src:url('https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/webfonts/fa-solid-900.woff2') format('woff2')}
@font-face{font-family:'Font Awesome 6 Brands';font-style:normal;font-weight:400;font-display:swap;src:url('https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/webfonts/fa-brands-400.woff2') format('woff2')}
</style>
    <!-- Inline site data — zero client API requests -->
    <script>window.__DATA__ = <?= $pageData ?>;</script>
    <?php if ($headerCode): ?>
    <!-- Header code (from admin settings) -->
    <?= $headerCode ?>
    <?php endif; ?>
    <!-- Google Tag Manager -->
    <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','GTM-WZSZSGPN');</script>
  </head>
  <body>
    <?php if ($bodyCode): ?>
    <!-- Body code (from admin settings) -->
    <div style="position:absolute;width:0;height:0;overflow:hidden"><?= $bodyCode ?></div>
    <?php endif; ?>
    <div id="preloader" class="preloader">
      <div class="preloader-inner">
        <img src="api/img.php?src=logob.webp&w=200" alt="مخازن العناية" class="preloader-logo" width="200" height="92" />
        <div class="preloader-dots">
          <span></span><span></span><span></span>
        </div>
      </div>
    </div>
    <header id="site-header" class="site-header">
      <div class="header-inner">
        <a href="#hero" class="header-logo">
          <img src="api/img.php?src=logob.webp&w=180" alt="مخازن العناية" width="180" height="83" />
        </a>
        <nav class="header-nav">
          <a href="#pranches" style="font-size:18px;">الفروع</a>
          <a href="https://wa.me/966920029921" style="padding: 0px; margin-bottom: -6px; font-size: 19px">
            <i class="fa-brands fa-whatsapp"></i>
          </a>
          <i class="fa-solid fa-phone"><a href="tel:+966920029921">92002 9921</a></i>
        </nav>
      </div>
    </header>
    <section id="hero" class="hero-section">
      <div class="hero-pattern-top">
        <img src="api/img.php?src=pattern-1.webp&w=1440&q=55" alt="" aria-hidden="true" width="1440" height="456" fetchpriority="high" />
      </div>
      <div class="hero-particles" id="hero-particles"></div>
      <div class="hero-content">
        <div class="hero-logo-wrap" id="hero-logo">
          <img src="api/img.php?src=logob.webp&w=260"
            srcset="api/img.php?src=logob.webp&w=260 260w, api/img.php?src=logob.webp&w=520 520w"
            sizes="260px"
            alt="مخازن العناية" class="hero-logo-img" fetchpriority="high" width="260" height="120" />
        </div>
      <!--  <h1 style='color:#fff' class="section-title" id="hero-tagline">
          العروض القوية ماتلقينها اون لاين <br />تشوفينها بعينك بمخازن العناية
        </h1>-->
        
            <!-- ════════════════════════════════════════════════════
         سلايدر الصور — أضف أو احذف <img> حسب ما تريد
         ════════════════════════════════════════════════════ -->
    <div class="hero-slider-wrap">
     <a href="#pranches">  <div class="hero-slider-track" id="slider-track">

        <!-- صورة 1 — غيّر الـ src بمسار صورتك -->
       <img src="../assets/brands/slider-4.webp" alt="" loading="eager" fetchpriority="high" />

        <!-- صورة 2 -->
        <img src="../assets/brands/slider-5.webp" alt="" loading="lazy" />

        <!-- صورة 3 -->
        <img src="../assets/brands/slider2.webp" alt="" loading="lazy" />

        <!-- لإضافة صورة جديدة انسخ السطر ↓ والصقه هنا وغيّر المسار
        <img src="../assets/slider/slide_X.webp" alt="" loading="lazy" />
        -->

      </div>
</a>
      <!-- أزرار التنقل (تظهر تلقائياً إذا أكثر من صورة) -->
      <button class="hero-slider-btn prev" id="sl-prev" aria-label="السابق"><i class="fas fa-chevron-right"></i></button>
      <button class="hero-slider-btn next" id="sl-next" aria-label="التالي"><i class="fas fa-chevron-left"></i></button>

      <!-- نقاط التنقل (تُنشأ تلقائياً من JS) -->
      <div class="hero-slider-dots" id="slider-dots"></div>
    </div>
    <!-- ══════════════════════════════════════════════════ -->
    
    
        <div class="hero-stats" id="hero-stats">
          <div class="stat-item">
            <span class="stat-num">+ 22</span>
            <span class="stat-label">فرع حول المملكة</span>
          </div>
          <div class="stat-divider"></div>
          <div class="stat-item">
            <span class="stat-num">+ 252</span>
            <span class="stat-label"> براند عالمي </span>
          </div>
          <div class="stat-divider"></div>
          <div class="stat-item">
            <span class="stat-num">+ 745 k </span>
            <span class="stat-label">عميل راضي</span>
          </div>
        </div>
      </div>
      <div class="hero-pattern-bottom">
        <img src="api/img.php?src=pattern-2.webp&w=800&q=45" alt="" aria-hidden="true" width="800" height="253" loading="lazy" />
      </div>
    </section>
    <section id="social" class="social-section">
      <div class="container">
        <div class="section-header">
          <h2 class="section-title">تابعونا على حساباتنا في شبكات التواصل الاجتماعي</h2>
        </div>
        <div class="social-grid" id="social-grid">
          <div class="social-skeleton"></div>
          <div class="social-skeleton"></div>
          <div class="social-skeleton"></div>
          <div class="social-skeleton"></div>
          <div class="social-skeleton"></div>
          <div class="social-skeleton"></div>
        </div>
      </div>
    </section>
    <section id="branches" class="branches-section">
      <div class="section-pattern-accent">
        <img src="api/img.php?src=pattern-3.webp&w=800&q=45" alt="" aria-hidden="true" loading="lazy" width="800" height="253" />
      </div>
      <div class="container" id="pranches">
        <div class="section-header">
          <span class="section-badge">لأن جمالك يستحق</span>
          <h2 class="section-title">أكثر من 20 فرعًا لخدمتك حول المملكة</h2>
          <div class="title-line"></div>
        </div>
        <div class="city-filter" id="city-filter">
          <button class="city-btn active" data-city="all">الكل</button>
        </div>
        <div class="branches-grid" id="branches-grid"></div>
      </div>
    </section>
    <section id="contact" class="contact-section">
      <div class="contact-bg-pattern">
        <img src="api/img.php?src=pattern-4.webp&w=800&q=40" alt="" aria-hidden="true" loading="lazy" width="800" height="253" />
      </div>
      <div class="container">
        <div class="contact-card" id="contact-card">
          <div class="contact-icon-wrap">
            <i class="fas fa-headset"></i>
          </div>
          <h2 class="contact-title">خدمة العملاء</h2>
          <p class="contact-sub">مواعيد العمل خلال شهر رمضان: من 10 صباحاً حتى 2 فجراً</p>
          <div class="contact-phones" id="contact-phones"></div>
          <div class="contact-actions" id="contact-actions"></div>
        </div>
      </div>
    </section>
    <section id="categories" class="categories-section">
      <div class="container">
        <div class="section-header">
          <h2 class="section-title">اكتشفي عالم العناية</h2>
          <div class="title-line"></div>
        </div>
        <div class="categories-slider" id="categories-slider"></div>
      </div>
    </section>
    <section id="brands" class="brands-section">
      <div class="container">
        <div class="section-header">
          <h2 class="section-title">الوكيل الرسمي لأهم العلامات التجارية العالمية في عالم التجميل والعناية والعطور</h2>
          <div class="title-line"></div>
        </div>
        <div class="brands-grid" id="brands-grid"></div>
      </div>
      <div class="brands-pattern-bottom">
        <img src="api/img.php?src=pattern-6.webp&w=800&q=45" alt="" aria-hidden="true" loading="lazy" width="800" height="253" />
      </div>
    </section>
    <section id="articles" class="articles-section">
      <div class="section-pattern-accent">
        <img src="api/img.php?src=pattern-3.webp&w=800&q=45" alt="" aria-hidden="true" loading="lazy" width="800" height="253" />
      </div>
      <div class="container">
        <div class="section-header">
          <span class="section-badge">اقرئي واكتشفي</span>
          <h2 class="section-title">المقالات</h2>
          <div class="title-line"></div>
        </div>
        <div class="articles-grid" id="articles-grid"></div>
      </div>
    </section>
    <footer class="site-footer">
      <div class="footer-pattern">
        <img src="api/img.php?src=pattern-5.webp&w=800&q=45" alt="" aria-hidden="true" loading="lazy" width="800" height="253" />
      </div>
      <div class="container">
        <div class="footer-inner">
          <img src="api/img.php?src=logob.webp&w=200" alt="مخازن العناية" class="footer-logo" loading="lazy" width="200" height="92" />
          <p class="footer-copy">© 2025 مخازن العناية. جميع الحقوق محفوظة.</p>
        </div>
      </div>
    </footer>
    <a href="https://wa.me/966920029921" target="_blank" rel="noopener noreferrer"
      id="whatsapp-float" aria-label="تواصل معنا عبر واتساب">
      <i class="fa-brands fa-whatsapp" style="color:#fff;font-size:32px;position:relative;z-index:1"></i>
    </a>
    <style>
      #whatsapp-float {
        position: fixed;
        bottom: 24px; right: 24px; z-index: 9999;
        width: 70px; height: 70px;
        background: #25d366;
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        box-shadow: 0 4px 12px rgba(37,211,102,0.4);
        transition: transform 0.3s ease;
        text-decoration: none;
      }
      #whatsapp-float::before {
        content: '';
        position: absolute;
        inset: 0;
        border-radius: 50%;
        background: rgba(37,211,102,0.5);
        animation: whatsapp-pulse 2s infinite;
        will-change: transform, opacity;
      }
      #whatsapp-float:hover { transform: scale(1.1); }
      @keyframes whatsapp-pulse {
        0%   { transform: scale(1);   opacity: 0.6; }
        70%  { transform: scale(1.5); opacity: 0;   }
        100% { transform: scale(1.5); opacity: 0;   }
      }
    </style>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js" defer></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/ScrollTrigger.min.js" defer></script>
    <script src="api/minify.php?f=assets/js/main.js" defer></script>
    <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-WZSZSGPN" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
    
    <script>
        // ── Slider ────────────────────────────────────────────────
(function () {
  const track  = document.getElementById('slider-track');
  const dotsEl = document.getElementById('slider-dots');
  const btnP   = document.getElementById('sl-prev');
  const btnN   = document.getElementById('sl-next');
  if (!track) return;

  // فلتر عناصر الصور فقط (يتجاهل text nodes وcomments)
  const slides = Array.from(track.children).filter(el => el.tagName === 'IMG');
  const total  = slides.length;

  // صورة واحدة → إخفاء الأزرار والنقاط
  if (total <= 1) {
    if (btnP) btnP.style.display = 'none';
    if (btnN) btnN.style.display = 'none';
    return;
  }

  let cur = 0, timer;

  // أنشئ نقاط التنقل تلقائياً
  slides.forEach((_, i) => {
    const dot = document.createElement('div');
    dot.className = 'hero-slider-dot' + (i === 0 ? ' active' : '');
    dot.addEventListener('click', () => { goTo(i); resetTimer(); });
    dotsEl.appendChild(dot);
  });

  function goTo(n) {
    cur = (n + total) % total;
    track.style.transform = 'translateX(' + (cur * 100) + '%)';
    dotsEl.querySelectorAll('.hero-slider-dot')
          .forEach((d, i) => d.classList.toggle('active', i === cur));
  }

  function resetTimer() {
    clearInterval(timer);
    timer = setInterval(() => goTo(cur + 1), 4500);
  }

  if (btnP) btnP.addEventListener('click', () => { goTo(cur - 1); resetTimer(); });
  if (btnN) btnN.addEventListener('click', () => { goTo(cur + 1); resetTimer(); });

  // Swipe على الموبايل
  let touchX = 0;
  track.addEventListener('touchstart', e => { touchX = e.touches[0].clientX; }, { passive: true });
  track.addEventListener('touchend',   e => {
    const diff = touchX - e.changedTouches[0].clientX;
    if (Math.abs(diff) > 40) { goTo(cur + (diff > 0 ? 1 : -1)); resetTimer(); }
  }, { passive: true });

  resetTimer();
})();
    </script>
  </body>
</html>
