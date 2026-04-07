-- ================================================
-- جدول الأقسام
-- يُضاف إلى قاعدة البيانات الرئيسية: makhazenalenaya_maindb
-- ================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS categories (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name_ar     VARCHAR(200) NOT NULL,
    slug        VARCHAR(200) NOT NULL UNIQUE,
    icon        VARCHAR(100) DEFAULT 'fa-star',
    description TEXT,
    body        LONGTEXT,
    is_active   TINYINT(1) DEFAULT 1,
    sort_order  INT DEFAULT 0,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- الأقسام الافتراضية الثمانية
INSERT INTO categories (name_ar, slug, icon, description, sort_order) VALUES
('العناية بالبشرة',                    'skincare',        'fa-spa',          'بشرتك تستحق الأفضل — منتجات أصيلة من أشهر الماركات العالمية والكورية', 1),
('المكياج وأدوات التجميل',             'makeup',          'fa-paint-brush',  'اكتشفي عالم المكياج بكل ألوانه — ماركات عالمية وأدوات احترافية', 2),
('العناية بالجسم',                     'body-care',       'fa-leaf',         'دللي جسمك بأفضل منتجات الترطيب والعناية من الرأس حتى القدم', 3),
('العناية بالشعر',                     'hair-care',       'fa-cut',          'شعر صحي ولامع مع تشكيلة متكاملة من منتجات العناية بالشعر', 4),
('العناية بالأم والطفل',               'mother-baby',     'fa-baby',         'منتجات آمنة وموثوقة لحماية ورعاية الأم والطفل', 5),
('الأطعمة الصحية والمكملات الغذائية', 'healthy-food',    'fa-apple-alt',    'تغذية سليمة وحياة أفضل — مكملات غذائية وأطعمة صحية مختارة', 6),
('الأجهزة الطبية وأجهزة الشعر',       'medical-devices', 'fa-heartbeat',    'أجهزة طبية منزلية وأجهزة تصفيف الشعر الاحترافية', 7),
('العطور',                             'perfumes',        'fa-spray-can',    'أطياف من العطور العالمية الأصيلة لكل مناسبة وذوق', 8)
ON DUPLICATE KEY UPDATE sort_order = VALUES(sort_order);
