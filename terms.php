<?php
// ── جلب المحتوى من DB مباشرة (بدون API) ─────────────────
require_once __DIR__ . '/api/config.php';

$pageContent = '';   // من لوحة التحكم
$branches    = [];

try {
    $db = getDB();

    // محتوى الصفحة من الإعدادات
    $row = $db->query("SELECT value FROM settings WHERE `key` = 'page_terms' LIMIT 1")->fetch();
    if ($row) $pageContent = $row['value'];

    // روابط الفروع
    $branches = $db->query("
        SELECT name_ar, city_ar, map_url
        FROM branches
        WHERE is_active = 1
        ORDER BY sort_order ASC, city_ar ASC
        LIMIT 200
    ")->fetchAll();

} catch (Exception $e) { /* fallback below */ }

// بناء فهرس الفروع (map_url indexed by name)
$branchMap = [];
foreach ($branches as $b) {
    $branchMap[$b['name_ar']] = $b['map_url'];
}

// ── دالة مساعدة: رابط فرع ────────────────────────────────
function branchLink(string $name, array &$map): string {
    $url = $map[$name] ?? '#';
    $href = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
    return '<a href="' . $href . '" target="_blank" rel="noopener" class="branch-link">'
         . htmlspecialchars($name, ENT_QUOTES, 'UTF-8')
         . ' <i class="fas fa-map-marker-alt"></i></a>';
}
?><!doctype html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>الشروط والأحكام | مخازن العناية</title>
  <link rel="icon" type="image/x-icon" href="favicon.jpeg" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link rel="stylesheet" href="api/minify.php?f=assets/css/style.css&v=3" />
  <link rel="preload" as="style"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
        onload="this.onload=null;this.rel='stylesheet'" />
  <noscript>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
  </noscript>
  <style>
    html, body { background: #0a0a0a; }
    .site-header { transform: translateY(0) !important; }

    .page-content-wrap { max-width: 800px; margin: 0 auto; padding: 90px 20px 60px; }
    .page-hero {
      text-align: center; margin-bottom: 40px; padding-bottom: 32px;
      border-bottom: 1px solid rgba(255,207,6,0.2);
    }
    .page-hero h1 {
      font-size: clamp(24px, 4vw, 36px); font-weight: 800;
      color: #FFCF06; font-family: 'Tajawal', sans-serif; margin-bottom: 8px;
    }
    .page-hero p { font-size: 14px; color: #888; font-family: 'Tajawal', sans-serif; }

    .page-body {
      color: #d0d0d0; font-family: 'Tajawal', sans-serif;
      line-height: 1.9; font-size: 15px;
    }
    .page-body h1, .page-body h2, .page-body h3 {
      color: #FFCF06; font-weight: 700; margin: 28px 0 12px;
    }
    .page-body h1 { font-size: 22px; }
    .page-body h2 { font-size: 19px; }
    .page-body h3 { font-size: 16px; color: #f0f0f0; }
    .page-body p  { margin-bottom: 14px; }
    .page-body ul, .page-body ol { padding-right: 24px; margin-bottom: 14px; }
    .page-body li { margin-bottom: 8px; }
    .page-body a  { color: #FFCF06; text-decoration: underline; }
    .page-body strong { color: #fff; }
    .page-body em { color: #aaa; }

    /* ── المحتوى الثابت للمسابقة ── */
    .terms-section {
      background: #111; border: 1px solid rgba(255,207,6,0.15);
      border-radius: 16px; padding: 28px 28px; margin-bottom: 28px;
    }
    .terms-section h2 {
      font-size: 18px; font-weight: 800; color: #FFCF06;
      margin: 0 0 20px; padding-bottom: 12px;
      border-bottom: 1px solid rgba(255,207,6,0.2);
      font-family: 'Tajawal', sans-serif;
    }
    .terms-list { list-style: none; padding: 0; margin: 0; }
    .terms-list li {
      display: flex; gap: 12px; align-items: flex-start;
      padding: 10px 0; border-bottom: 1px solid rgba(255,255,255,0.05);
      font-family: 'Tajawal', sans-serif; font-size: 15px; color: #ccc; line-height: 1.7;
    }
    .terms-list li:last-child { border-bottom: none; }
    .terms-list li::before {
      content: '●'; color: #FFCF06; font-size: 10px;
      margin-top: 7px; flex-shrink: 0;
    }

    .lang-divider {
      text-align: center; margin: 32px 0;
      position: relative; color: #555; font-size: 13px;
      font-family: 'Tajawal', sans-serif;
    }
    .lang-divider::before, .lang-divider::after {
      content: ''; position: absolute; top: 50%;
      width: 42%; height: 1px; background: rgba(255,207,6,0.15);
    }
    .lang-divider::before { right: 0; }
    .lang-divider::after  { left: 0; }

    .branches-grid-terms {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
      gap: 10px; margin-top: 16px;
    }
    .branch-link {
      display: flex; align-items: center; justify-content: space-between;
      padding: 10px 14px; background: #0e0e0e;
      border: 1px solid rgba(255,207,6,0.15); border-radius: 10px;
      color: #ddd; text-decoration: none; font-family: 'Tajawal', sans-serif;
      font-size: 14px; transition: border-color .2s, color .2s;
    }
    .branch-link:hover { border-color: #FFCF06; color: #FFCF06; text-decoration: none; }
    .branch-link i { color: #FFCF06; font-size: 12px; }
    .branch-link[href="#"] { opacity: .5; pointer-events: none; }

    .warning-box {
      background: rgba(255,92,92,0.07); border: 1px solid rgba(255,92,92,0.25);
      border-radius: 12px; padding: 16px 20px; margin-top: 28px;
      font-family: 'Tajawal', sans-serif; font-size: 14px; color: #ff8080;
      display: flex; gap: 12px; align-items: flex-start;
    }
    .warning-box i { color: #ff5c5c; font-size: 18px; margin-top: 2px; flex-shrink: 0; }

    .steps-list { counter-reset: steps; list-style: none; padding: 0; }
    .steps-list li {
      counter-increment: steps; display: flex; gap: 14px;
      align-items: flex-start; padding: 12px 0;
      border-bottom: 1px solid rgba(255,255,255,0.05);
      font-family: 'Tajawal', sans-serif; font-size: 15px; color: #ccc; line-height: 1.7;
    }
    .steps-list li::before {
      content: counter(steps);
      background: #FFCF06; color: #000; font-weight: 800; font-size: 13px;
      width: 26px; height: 26px; border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0; margin-top: 2px;
    }

    .site-footer { margin-top: 60px; }
    @media (max-width: 768px) {
    .terms-list li {
        display: block;
    }
}
  </style>
</head>
<body>

<header id="site-header" class="site-header visible scrolled">
  <div class="header-inner">
    <a href="index.php" class="header-logo">
      <img src="logob.webp" alt="مخازن العناية" />
    </a>
    <nav class="header-nav">
      <a href="index.php" style="font-size:18px;">الرئيسية</a>
      <a href="https://wa.me/966920029921" style="padding:0;margin-bottom:-6px;font-size:19px">
        <i class="fa-brands fa-whatsapp"></i>
      </a>
      <i class="fa-solid fa-phone"><a href="tel:+966920029921">92002 9921</a></i>
    </nav>
  </div>
</header>

<div class="page-content-wrap">
  <div class="page-hero">
    <h1><i class="fas fa-file-contract" style="margin-left:10px;font-size:0.85em"></i>الشروط والأحكام</h1>
    <p>مسابقة مخازن العناية — سيارتين جيلي</p>
  </div>

  <div class="page-body">

<?php if (!empty($pageContent) && trim($pageContent) !== '<p><br></p>'): ?>
    <!-- محتوى من لوحة التحكم -->
    <?= $pageContent ?>
<?php else: ?>

    <!-- ══ المحتوى الثابت للمسابقة ══════════════════════════ -->

    <!-- شروط عربية -->
    <div class="terms-section">
      <h2><i class="fas fa-scroll" style="margin-left:8px"></i>الشروط والأحكام</h2>
      <ul class="terms-list">
        <li>المسابقة مرخصة من الغرفة التجارية الصناعية بالرياض بالترخيص رقم: <strong>13463</strong></li>
        <li>الجوائز هي: <strong>سيارتين من نوع جيلي</strong></li>
        <li>الاشتراك في المسابقة داخل المملكة العربية السعودية فقط يشترط أن يكون المتسابق في المسابقة سعودي الجنسية أو مقيم في المملكة العربية السعودية بشكل نظامي.</li>
        <li>يتوجب على المتسابق التسجيل في رابط المسابقة باسمه الثلاثي المدون في الهوية النظامية ورقم جواله وهويته، ويعتبر إتمام التسجيل قبولاً بالشروط والأحكام.</li>
        <li>تبدأ المسابقة بتاريخ: من <strong>04 فبراير 2026</strong> وحتى <strong>04 أبريل 2026</strong> سيتم اختيار الفائز من المتسابقين بشكل عشوائي من لجنة الفرز التي بحضور ممثل الغرفة التجارية.</li>
        <li>تعلن النتائج النهائية خلال ثلاثين يوم من تاريخ انتهاء المسابقة.</li>
        <li>لا يشترط الشراء أو حضور المتسابق وقت السحب للحصول على الجائزة.</li>
        <li>تسليم الجوائز للفائزين خلال سبعة أيام من تاريخ إعلان نتائج المسابقة.</li>
        <li>لا يجوز لمن صدر له ترخيص تنظيم المسابقة وأبنائه وأزواجه ووالديه وموظفيه الاشتراك في المسابقة.</li>
        <li>يقر الفائز بموافقته لشركة مخازن العناية بتوثيق ونشر صور وفيديوهات الفائز والجائزة.</li>
        <li>لا يحق للفائز استبدال الجائزة نقداً أو مقايضتها بمنتجات أخرى.</li>
        <li>في حال تكرار فوز المتسابق فإنه يستحق أول جائزة حصل عليها في السحب.</li>
        <li>يشترط على الفائزين تقديم هوية نظامية سارية المفعول عند استلام الجائزة.</li>
        <li>في حال لم يتطابق اسم الفائز في رابط الاشتراك في المسابقة مع الاسم الموجود بالهوية النظامية المعتمدة في المملكة العربية السعودية فيحق لشركة مخازن العناية استبعاده وإعادة السحب مرة أخرى.</li>
        <li>في حال اتضح مخالفة الفائز لأي من الشروط والأحكام أعلاه بعد إعلان فوزه، أو لم يحضر لاستلام الجائزة خلال فترة سبعة أيام من تاريخ إعلان اسمه وإبلاغه، فيحق لشركة مخازن العناية اختيار فائز آخر.</li>
        <li>في حال استلم الفائز الجائزة واتضح لاحقاً مخالفته للشروط والأحكام الخاصة بالمسابقة، فيلتزم برد ما استلمه.</li>
        <li>يجب على الفائز استلام الجائزة وفقاً للموعد والمكان المحددين من قبل شركة مخازن العناية.</li>
        <li>يقر الفائز بموافقته لشركة مخازن العناية بتوثيق ونشر صور وفيديوهات الفائز والجائزة.</li>
      </ul>
    </div>

    <div class="lang-divider">Terms and Conditions — English</div>

    <!-- شروط انجليزية -->
    <div class="terms-section" dir="ltr" style="text-align:left">
      <h2 style="text-align:right;direction:rtl"><i class="fas fa-scroll" style="margin-left:8px"></i>Terms and Conditions</h2>
      <ul class="terms-list" style="direction:ltr;text-align:left">
        <li>The competition is licensed by the Riyadh Chamber of Commerce and Industry under license number: <strong>13463</strong>.</li>
        <li>The prizes are: <strong>Two Geely cars</strong>.</li>
        <li>Participation in the competition is limited to within the Kingdom of Saudi Arabia only. The participant must be a Saudi national or a legal resident of the Kingdom of Saudi Arabia.</li>
        <li>The participant must register through the competition link using their full three-part name as stated in their official ID, along with their mobile number and ID details. Completing the registration constitutes acceptance of these Terms and Conditions.</li>
        <li>The competition runs from <strong>February 04, 2026</strong>, until <strong>April 04, 2026</strong>. Winners will be selected randomly from among the participants by a screening committee in the presence of a representative from the Chamber of Commerce.</li>
        <li>The final results will be announced within thirty days from the end date of the competition.</li>
        <li>No purchase or presence at the time of the draw is required to win the prize.</li>
        <li>Prizes will be delivered to the winners within seven days from the date of announcing the competition results.</li>
        <li>The competition license holder, their children, spouses, parents, and employees are not eligible to participate.</li>
        <li>By accepting the prize, the winner agrees to allow Makhazen Al Enaya Company to document and publish photos and videos of the winner and the prize.</li>
        <li>The winner may not exchange the prize for cash or substitute it with other products.</li>
        <li>In the event that a participant wins more than once, they shall be entitled only to the first prize won in the draw.</li>
        <li>Winners must present a valid official ID upon receiving the prize.</li>
        <li>If the winner's name registered in the competition link does not match the name stated in the official ID recognized in the Kingdom of Saudi Arabia, Makhazen Al Enaya Company has the right to disqualify the winner and conduct another draw.</li>
        <li>If it is found that the winner violated any of the above Terms and Conditions after the announcement of their win, or if they fail to claim the prize within seven days from the date of announcing and notifying them, Makhazen Al Enaya Company has the right to select another winner.</li>
        <li>If the winner receives the prize and it is later discovered that they violated the competition Terms and Conditions, they shall be obligated to return what they have received.</li>
        <li>The winner must collect the prize at the date and location specified by Makhazen Al Enaya Company.</li>
        <li>By accepting the prize, the winner agrees to allow Makhazen Al Enaya Company to document and publish photos and videos of the winner and the prize.</li>
      </ul>
    </div>

    <!-- روابط الفروع وطريقة الاشتراك -->
    <div class="terms-section">
      <h2><i class="fas fa-map-marked-alt" style="margin-left:8px"></i>طريقة الاشتراك بالمسابقة</h2>

      <p style="font-family:'Tajawal',sans-serif;font-size:15px;color:#ccc;margin-bottom:16px">
        يوجد رابط تسجيل للمسابقة في فروع مخازن العناية في جميع أنحاء المملكة.
      </p>

      <ul class="steps-list" style="margin-bottom:20px">
        <li>زيارة أي فرع من فروع مخازن العناية والتسجيل في الرابط المنشور في جميع الفروع.</li>
      </ul>

      <p style="font-family:'Tajawal',sans-serif;font-size:15px;color:#aaa;margin-bottom:12px">
        <i class="fas fa-map-marker-alt" style="color:#FFCF06;margin-left:6px"></i>
        للوصول للفروع:
      </p>

      <div class="branches-grid-terms">
        <?php
        // الأسماء المعروضة كما وردت في المتطلبات مع البحث الجزئي في DB
        $termsBranches = [
            'الرياض - حي الياسمين',
            'الرياض - الدائري الشرقي',
            'الرياض - حي الحمراء',
            'الرياض - حي الربيع',
            'الرياض - حي المحمدية',
            'الرياض - حي الفيحاء',
            'الرياض - ظهرة لبن',
            'مكة - الشرائع',
            'جدة - ابحر',
            'حفر الباطن',
            'جازان',
            'خميس مشيط',
            'تبوك',
            'الخبر',
            'حائل',
            'مكة المكرمة',
            'الأحساء',
            'الطائف',
            'جدة',
            'الدمام - حي النزهة',
        ];

        foreach ($termsBranches as $branchName) {
            $mapUrl = '#';
            foreach ($branches as $b) {
                if (mb_stripos($b['name_ar'], $branchName) !== false
                    || mb_stripos($branchName, $b['name_ar']) !== false) {
                    $mapUrl = $b['map_url'] ?: '#';
                    break;
                }
            }
            $href     = htmlspecialchars($mapUrl, ENT_QUOTES, 'UTF-8');
            $label    = htmlspecialchars($branchName, ENT_QUOTES, 'UTF-8');
            $disabled = ($mapUrl === '#') ? ' style="opacity:.5;pointer-events:none"' : '';
            $target   = ($mapUrl !== '#') ? ' target="_blank" rel="noopener"' : '';
            echo '<a href="' . $href . '" class="branch-link"' . $target . $disabled . '>'
               . $label . ' <i class="fas fa-location-arrow"></i></a>' . "\n";
        }
        ?>
      </div>

      <div style="margin-top:20px;padding-top:20px;border-top:1px solid rgba(255,255,255,0.05);direction:ltr;text-align:left">
        <p style="font-size:14px;color:#888;font-family:'Tajawal',sans-serif;margin-bottom:10px">
          Visit any branch of Makhazen Al Enaya and register through the link displayed in all branches.
        </p>
      </div>
    </div>

    <!-- تحذير -->
    <div class="warning-box">
      <i class="fas fa-exclamation-triangle"></i>
      <div>
        <p style="margin:0 0 6px">
          مخازن العناية تحذر التعامل مع أي روابط وهمية أو استغلال غير قانوني للمسابقة أو اسم الشركة وتخلي مسؤوليتها عن ذلك بشكل كامل.
        </p>
        <p style="margin:0;color:#888;font-size:13px;direction:ltr;text-align:left">
          Makhazen Al Enaya warns against dealing with any fake links or illegal exploitation of the competition or the company name, and disclaims full responsibility for such actions.
        </p>
      </div>
    </div>

<?php endif; ?>

  </div><!-- /page-body -->
</div>

<footer class="site-footer">
  <div class="footer-pattern">
    <img src="api/img.php?src=pattern-5.webp&w=800" alt="" aria-hidden="true" loading="lazy" />
  </div>
  <div class="container">
    <div class="footer-inner">
      <img src="logob.webp" alt="مخازن العناية" class="footer-logo" loading="lazy" />
      <p class="footer-copy">© 2025 مخازن العناية. جميع الحقوق محفوظة.</p>
    </div>
  </div>
</footer>

</body>
</html>
