<?php
// ── جلب إعدادات المسابقة مباشرة من DB (بدون API) ──────────
require_once __DIR__ . '/../api/config.php';

$comp = [
    'title'      => 'تسجيل المسابقة',
    'subtitle'   => 'سجّل بياناتك وكن جزءاً من المسابقة',
    'active'     => true,
    'ref_prefix' => 'MK',
];

try {
    $db   = getDB();
    $rows = $db->query("SELECT `key`, value FROM settings WHERE `key` IN
        ('comp_title','comp_subtitle','comp_active','comp_ref_prefix')")->fetchAll();
    foreach ($rows as $r) {
        match ($r['key']) {
            'comp_title'      => $comp['title']      = $r['value'],
            'comp_subtitle'   => $comp['subtitle']   = $r['value'],
            'comp_active'     => $comp['active']      = ($r['value'] !== '0'),
            'comp_ref_prefix' => $comp['ref_prefix']  = $r['value'],
            default           => null,
        };
    }
} catch (Exception $e) { /* fallback to defaults above */ }

$title    = htmlspecialchars($comp['title'],    ENT_QUOTES, 'UTF-8');
$subtitle = htmlspecialchars($comp['subtitle'], ENT_QUOTES, 'UTF-8');
$isActive = $comp['active'];
?><!doctype html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= $title ?> - مخازن العناية</title>
  <link rel="icon" type="image/x-icon" href="../favicon.jpeg" />
  <!-- Preload LCP image (first slider frame) -->
  <link rel="preload" as="image" href="../assets/brands/slider-4.webp" fetchpriority="high" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin />
  <link rel="stylesheet" href="../api/minify.php?f=assets/css/style.css&v=3" />
  <!-- Font Awesome subsets (smaller than all.min.css) -->
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
  <!-- font-display: swap for Font Awesome -->
  <style>
    @font-face{font-family:'Font Awesome 6 Free';font-style:normal;font-weight:900;font-display:swap;src:url('https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/webfonts/fa-solid-900.woff2') format('woff2')}
    @font-face{font-family:'Font Awesome 6 Brands';font-style:normal;font-weight:400;font-display:swap;src:url('https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/webfonts/fa-brands-400.woff2') format('woff2')}
  </style>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11" defer></script>
  <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.9.3/dist/confetti.browser.min.js" defer></script>

  <style>
    html, body { background: #0a0a0a; min-height: 100%; }
    body { padding-top: 0; }
    .site-header { transform: translateY(0) !important; }

    .hero-banner {
      position: relative; overflow: hidden;
      background: #0a0a0a;
      border-bottom: 1px solid rgba(255,207,6,0.25);
      text-align: center; padding: 0; margin-top: 60px;
    }
    .hero-bg-grid {
      position: absolute; inset: 0;
      display: grid; grid-template-columns: 1fr 1fr 1fr;
    }
    .hero-bg-grid img {
      width: 100%; height: 100%; object-fit: cover;
      filter: brightness(0.32) saturate(0.7); display: block;
    }
    .hero-overlay {
      position: absolute; inset: 0;
      background:
        linear-gradient(to bottom, rgba(10,10,10,0.55) 0%, rgba(10,10,10,0.75) 100%),
        linear-gradient(135deg, rgba(10,10,0,0.5) 0%, rgba(26,18,0,0.4) 50%, rgba(10,10,0,0.5) 100%);
    }
    .hero-content { position: relative; z-index: 1; padding: 48px 20px 40px; }
    .hero-banner .trophy {
      font-size: 58px; line-height: 1; margin-bottom: 18px;
      display: block; color: #FFCF06;
      filter: drop-shadow(0 0 20px rgba(255,207,6,0.5));
    }

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
}
.hero-content {
    padding:0;}
    
    .hero-content {
  
    gap: 0;

}
}

  </style>
</head>
<body>

<header id="site-header" class="site-header visible scrolled">
  <div class="header-inner">
    <a href="../index.php" class="header-logo">
      <img src="../logob.webp" alt="مخازن العناية" />
    </a>
    <nav class="header-nav">
      <a href="../index.php" style="font-size:18px;">الرئيسية</a>
      <a href="https://wa.me/966920029921"
         style="padding: 0px; margin-bottom: -6px; font-size: 19px">
        <i class="fa-brands fa-whatsapp"></i>
      </a>
      <i class="fa-solid fa-phone">
        <a href="tel:+966920029921">92002 9921</a>
      </i>
    </nav>
  </div>
</header>

<div class="hero-banner">
  <div class="hero-bg-grid" aria-hidden="true">
   
  </div>
  <div class="hero-overlay"></div>
  <div class="hero-content">

    <!-- ════════════════════════════════════════════════════
         سلايدر الصور — أضف أو احذف <img> حسب ما تريد
         ════════════════════════════════════════════════════ -->
    <div class="hero-slider-wrap">
      <div class="hero-slider-track" id="slider-track">

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

      <!-- أزرار التنقل (تظهر تلقائياً إذا أكثر من صورة) -->
      <button class="hero-slider-btn prev" id="sl-prev" aria-label="السابق"><i class="fas fa-chevron-right"></i></button>
      <button class="hero-slider-btn next" id="sl-next" aria-label="التالي"><i class="fas fa-chevron-left"></i></button>

      <!-- نقاط التنقل (تُنشأ تلقائياً من JS) -->
      <div class="hero-slider-dots" id="slider-dots"></div>
    </div>
    <!-- ══════════════════════════════════════════════════ -->

    <h1><?= $title ?></h1>
    <p><?= $subtitle ?></p>
  </div>
</div>

<div class="form-wrap">
<?php if (!$isActive): ?>
  <div class="closed-banner">
    <i class="fas fa-lock" style="font-size:28px;display:block;margin-bottom:10px;"></i>
    التسجيل في المسابقة مغلق حالياً، تابعونا للمزيد من الأخبار.
  </div>
<?php else: ?>
  <form class="form-card" id="reg-form" novalidate>
    <p class="form-section-title">الرجاء إدخال البيانات</p>

    <div class="field-group">
      <label for="full_name">الاسم الكامل <span class="req">*</span></label>
      <div class="input-wrap">
        <i class="fas fa-user"></i>
        <input type="text" id="full_name" name="full_name"
               class="field-input" placeholder="الاسم "
               autocomplete="name" maxlength="100" />
      </div>
      <div class="field-error" id="err-full_name"></div>
    </div>

    <div class="row-2">
      <div class="field-group">
        <label for="email">البريد الإلكتروني <span class="req">*</span></label>
        <div class="input-wrap">
          <i class="fas fa-envelope"></i>
          <input type="email" id="email" name="email"
                 class="field-input" placeholder="example@email.com"
                 autocomplete="email" />
        </div>
        <div class="field-error" id="err-email"></div>
      </div>

      <div class="field-group">
        <label for="phone">رقم الجوال <span class="req">*</span></label>
        <div class="input-wrap">
          <i class="fas fa-mobile-alt"></i>
          <input type="tel" id="phone" name="phone"
                 class="field-input" placeholder="05XXXXXXXX"
                 maxlength="10" dir="ltr" style="text-align:right"
                 inputmode="numeric" />
        </div>
        <div class="field-hint">10 أرقام يبدأ بـ 05</div>
        <div class="field-error" id="err-phone"></div>
      </div>
    </div>

    <div class="row-2">
      <div class="field-group">
        <label for="national_id">رقم الهوية <span class="req">*</span></label>
        <div class="input-wrap">
          <i class="fas fa-id-card"></i>
          <input type="text" id="national_id" name="national_id"
                 class="field-input" placeholder="1XXXXXXXXX"
                 maxlength="10" dir="ltr" style="text-align:right"
                 inputmode="numeric" />
        </div>
        <div class="field-hint">10 أرقام يبدأ بـ 1 أو 2</div>
        <div class="field-error" id="err-national_id"></div>
      </div>

      <div class="field-group">
        <label for="city">المدينة <span class="req">*</span></label>
        <div class="input-wrap">
          <i class="fas fa-city"></i>
          <select id="city" name="city" class="field-input">
            <option value="">اختر المدينة</option>
            <option>الرياض</option><option>جدة</option><option>مكة المكرمة</option>
            <option>المدينة المنورة</option><option>الدمام</option><option>الخبر</option>
            <option>الظهران</option><option>الطائف</option><option>بريدة</option>
            <option>تبوك</option><option>خميس مشيط</option><option>حائل</option>
            <option>الأحساء</option><option>القطيف</option><option>جازان</option>
            <option>نجران</option><option>ينبع</option><option>الجبيل</option>
            <option>أبها</option><option>الباحة</option><option>عرعر</option>
            <option>سكاكا</option><option>حفر الباطن</option><option>الخرج</option>
            <option>المجمعة</option><option>القصيم</option><option>أخرى</option>
          </select>
        </div>
        <div class="field-error" id="err-city"></div>
      </div>
    </div>

    <div class="field-group">
      <label>الجنس <span class="req">*</span></label>
      <div class="gender-row">
        <div class="gender-option">
          <input type="radio" name="gender" id="gender-male" value="male" />
          <label class="gender-label" for="gender-male"><i class="fas fa-mars"></i> ذكر</label>
        </div>
        <div class="gender-option">
          <input type="radio" name="gender" id="gender-female" value="female" />
          <label class="gender-label" for="gender-female"><i class="fas fa-venus"></i> أنثى</label>
        </div>
      </div>
      <div class="field-error" id="err-gender"></div>
    </div>

    <div class="field-group">
      <label>هل سبق تعاملت مع مخازن العناية؟ </label>
      <div class="gender-row">
        <div class="gender-option">
          <input type="radio" name="prev_customer" id="prev-yes" value="1" />
          <label class="gender-label" for="prev-yes"><i class="fas fa-check-circle"></i> نعم</label>
        </div>
        <div class="gender-option">
          <input type="radio" name="prev_customer" id="prev-no" value="0" />
          <label class="gender-label" for="prev-no"><i class="fas fa-times-circle"></i> لا</label>
        </div>
      </div>
      <div class="field-error" id="err-prev_customer"></div>
    </div>

    <div class="field-group">
      <label class="terms-wrap" for="terms">
        <input type="checkbox" id="terms" name="terms" />
        <span class="terms-text">
          أوافق على
          <a href="../terms.php" target="_blank">الشروط والأحكام</a>
          و
          <a href="../privacy.html" target="_blank">سياسة الخصوصية</a>
          واستقبال العروض والرسائل التسويقية
          <strong>*</strong>
        </span>
      </label>
      <div class="field-error" id="err-terms"></div>
    </div>

    <button type="submit" class="btn-submit" id="submit-btn">
      <div class="spinner"></div>
      <span class="btn-text"><i class="fas fa-paper-plane"></i> إرسال</span>
    </button>
  </form>
<?php endif; ?>
</div>

<footer class="site-footer">
  <div class="footer-pattern">
    <img src="../api/img.php?src=pattern-5.webp&w=800" alt="" aria-hidden="true" loading="lazy" />
  </div>
  <div class="container">
    <div class="footer-inner">
      <img src="../logob.webp" alt="مخازن العناية" class="footer-logo" loading="lazy" />
      <p class="footer-copy">© 2025 مخازن العناية. جميع الحقوق محفوظة.</p>
    </div>
  </div>
</footer>

<?php if ($isActive): ?>
<script>
// ── Validation ────────────────────────────────────────────
const rules = {
  full_name:     v => v.trim().length >= 3 || 'الاسم الكامل لا يقل عن 3 أحرف',
  email:         v => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v.trim()) || 'البريد الإلكتروني غير صحيح',
  phone:         v => /^05\d{8}$/.test(v.trim()) || 'الجوال يبدأ بـ 05 ويكون 10 أرقام',
  national_id:   v => /^[12]\d{9}$/.test(v.trim()) || 'الهوية 10 أرقام وتبدأ بـ 1 أو 2',
  city:          v => v.trim().length > 0 || 'يرجى اختيار المدينة',
  gender:        () => document.querySelector('input[name="gender"]:checked')?.value
                       ? true : 'يرجى اختيار الجنس',
  prev_customer: () => document.querySelector('input[name="prev_customer"]:checked') !== null
                       ? true : 'يرجى الإجابة على سؤال التعامل السابق مع مخازن العناية',
  terms:         () => document.getElementById('terms').checked || 'يجب الموافقة على الشروط والأحكام',
};

function showError(field, msg) {
  const el  = document.getElementById('err-' + field);
  const inp = document.getElementById(field);
  if (!el) return;
  if (msg) {
    el.textContent = msg; el.classList.add('show');
    if (inp) inp.classList.add('error');
  } else {
    el.classList.remove('show');
    if (inp) inp.classList.remove('error');
  }
}

function validateAll() {
  let valid = true;
  ['full_name','email','phone','national_id','city','gender','prev_customer','terms'].forEach(field => {
    const el  = document.getElementById(field);
    const res = rules[field](el ? el.value : '');
    if (res !== true) { showError(field, res); valid = false; }
    else              { showError(field, ''); }
  });
  return valid;
}

['full_name','email','phone','national_id','city'].forEach(field => {
  const el = document.getElementById(field);
  if (!el) return;
  el.addEventListener('blur',  () => { const r = rules[field](el.value); showError(field, r !== true ? r : ''); });
  el.addEventListener('input', () => { if (el.classList.contains('error')) { const r = rules[field](el.value); if (r === true) showError(field, ''); } });
});
document.querySelectorAll('input[name="gender"]').forEach(r => r.addEventListener('change', () => showError('gender', '')));
document.querySelectorAll('input[name="prev_customer"]').forEach(r => r.addEventListener('change', () => showError('prev_customer', '')));
document.getElementById('terms').addEventListener('change', () => showError('terms', ''));

document.getElementById('phone').addEventListener('input', function() {
  this.value = this.value.replace(/\D/g, '').slice(0, 10);
});
document.getElementById('national_id').addEventListener('input', function() {
  this.value = this.value.replace(/\D/g, '').slice(0, 10);
});

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

// ── Confetti ──────────────────────────────────────────────
function fireConfetti() {
  const count = 200, defaults = { origin: { y: 0.6 } };
  [
    [0.25, { spread: 26, startVelocity: 55 }],
    [0.20, { spread: 60 }],
    [0.35, { spread: 100, decay: 0.91, scalar: 0.8 }],
    [0.10, { spread: 120, startVelocity: 25, decay: 0.92, scalar: 1.2 }],
    [0.10, { spread: 120, startVelocity: 45 }],
  ].forEach(([r, o]) => confetti(Object.assign({}, defaults, o, { particleCount: Math.floor(count * r) })));
}

function escHtml(str) {
  return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function showSuccess(data) {
  fireConfetti();
  setTimeout(fireConfetti, 800);
  Swal.fire({
    background: '#1a1a1a', color: '#f0f0f0',
    showConfirmButton: true, confirmButtonText: 'حسناً 🎉',
    confirmButtonColor: '#FFCF06', allowOutsideClick: false,
    customClass: { popup: 'swal2-popup' },
    html: `
      <div class="swal-congrats">
        <div class="big-icon"><i class="fas fa-award" style="color:#FFCF06"></i></div>
        <h2>تهانينا يا ${escHtml(data.full_name.split(' ')[0])}!</h2>
        <p class="sub-msg">${escHtml(data.success_msg || 'تم تسجيلك بنجاح ' + data.comp_title)}</p>
        <div class="swal-ref-card">
          <div class="ref-row"><i class="fas fa-user"></i><span>الاسم</span><span class="ref-val">${escHtml(data.full_name)}</span></div>
          <div class="ref-row"><i class="fas fa-hashtag"></i><span>رقم المرجع</span><span class="ref-val" style="color:#FFCF06">${escHtml(data.ref_number)}</span></div>
          <div class="ref-row"><i class="fas fa-calendar-check"></i><span>تاريخ التسجيل</span><span class="ref-val">${escHtml(data.date)}</span></div>
        </div>
      </div>`,
    didOpen: () => {
      const p = document.querySelector('.swal2-popup');
      if (p) Object.assign(p.style, { fontFamily:"'Tajawal',sans-serif", direction:'rtl', borderRadius:'20px', border:'1px solid rgba(255,207,6,0.25)', maxWidth:'480px' });
    },
    willClose: () => {
      window.location.href = '../index.php';
    },
  });
}

// ── Form submit ───────────────────────────────────────────
document.getElementById('reg-form').addEventListener('submit', async function(e) {
  e.preventDefault();
  if (!validateAll()) {
    const firstErr = this.querySelector('.field-input.error, .field-error.show');
    if (firstErr) firstErr.scrollIntoView({ behavior:'smooth', block:'center' });
    return;
  }

  const btn = document.getElementById('submit-btn');
  btn.disabled = true;
  btn.classList.add('loading');

  try {
    const res = await fetch('../api/registration.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        full_name:     document.getElementById('full_name').value.trim(),
        email:         document.getElementById('email').value.trim(),
        phone:         document.getElementById('phone').value.trim(),
        national_id:   document.getElementById('national_id').value.trim(),
        city:          document.getElementById('city').value,
        gender:        document.querySelector('input[name="gender"]:checked')?.value,
        prev_customer: document.querySelector('input[name="prev_customer"]:checked')?.value === '1',
        terms:         document.getElementById('terms').checked,
      }),
    });
    const data = await res.json();

    if (data.success) {
      this.style.display = 'none';
      showSuccess(data);
    } else {
      Swal.fire({
        icon: 'error', title: 'تنبيه',
        text: data.message || 'حدث خطأ، يرجى المحاولة مجدداً',
        confirmButtonText: 'حسناً', confirmButtonColor: '#FFCF06',
        background: '#1a1a1a', color: '#f0f0f0',
        customClass: { popup: 'swal2-popup' },
        didOpen: () => { const p = document.querySelector('.swal2-popup'); if (p) { p.style.fontFamily="'Tajawal',sans-serif"; p.style.direction='rtl'; } },
      });
    }
  } catch {
    Swal.fire({
      icon: 'error', title: 'خطأ في الاتصال',
      text: 'تعذّر الاتصال بالخادم، يرجى المحاولة لاحقاً',
      confirmButtonText: 'حسناً', confirmButtonColor: '#FFCF06',
      background: '#1a1a1a', color: '#f0f0f0',
    });
  } finally {
    btn.disabled = false;
    btn.classList.remove('loading');
  }
});
</script>
<?php endif; ?>
</body>
</html>
