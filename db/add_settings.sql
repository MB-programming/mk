-- Add settings table for custom code injection
ALTER TABLE makhazen_db.settings DROP TABLE IF EXISTS settings;

CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    `key` VARCHAR(100) NOT NULL UNIQUE,
    value LONGTEXT,
    label_ar VARCHAR(255),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO settings (`key`, value, label_ar) VALUES
('header_code',      '',     'كود الهيدر (Google Analytics / Meta Pixel)'),
('body_code',        '',     'كود البودي (Google Tag Manager noscript)'),
('slider_per_view',  '5',    'عدد بطاقات السلايدر'),
('slider_autoplay',  '1',    'تشغيل تلقائي للسلايدر'),
('slider_speed',     '3000', 'سرعة السلايدر (مللي ثانية)'),
('perf_animations',  '1',    'تأثيرات الأنيميشن GSAP'),
('perf_cache_api',   '1',    'كاش API'),
('perf_minify_html', '1',    'ضغط HTML')
ON DUPLICATE KEY UPDATE `key` = `key`;
