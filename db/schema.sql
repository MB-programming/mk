-- ================================================
-- Makhazen Alenayah - Database Schema
-- ================================================

CREATE DATABASE IF NOT EXISTS makhazen_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE makhazen_db;

-- ------------------------------------------------
-- Settings table (code injection, analytics)
-- ------------------------------------------------
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    `key` VARCHAR(100) NOT NULL UNIQUE,
    value LONGTEXT,
    label_ar VARCHAR(255),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO settings (`key`, value, label_ar) VALUES
('header_code', '', 'كود الهيدر (Google Analytics / Meta Pixel)'),
('body_code',   '', 'كود البودي (Google Tag Manager noscript)')
ON DUPLICATE KEY UPDATE `key` = `key`;

-- ------------------------------------------------
-- Admins table
-- ------------------------------------------------
CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    name VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ------------------------------------------------
-- Branches table
-- ------------------------------------------------
CREATE TABLE IF NOT EXISTS branches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name_ar VARCHAR(255) NOT NULL,
    name_en VARCHAR(255),
    city_ar VARCHAR(100),
    city_en VARCHAR(100),
    address_ar TEXT,
    address_en TEXT,
    phone VARCHAR(30),
    map_url TEXT,
    is_active TINYINT(1) DEFAULT 1,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ------------------------------------------------
-- Brands table
-- ------------------------------------------------
CREATE TABLE IF NOT EXISTS brands (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name_ar VARCHAR(255),
    name_en VARCHAR(255) NOT NULL,
    logo_url VARCHAR(500),
    website_url VARCHAR(500),
    is_active TINYINT(1) DEFAULT 1,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ------------------------------------------------
-- Social media table
-- ------------------------------------------------
CREATE TABLE IF NOT EXISTS social_media (
    id INT AUTO_INCREMENT PRIMARY KEY,
    platform VARCHAR(50) NOT NULL,
    platform_ar VARCHAR(50),
    url VARCHAR(500) NOT NULL,
    username VARCHAR(100),
    icon VARCHAR(50),
    color VARCHAR(20),
    is_active TINYINT(1) DEFAULT 1,
    sort_order INT DEFAULT 0
);

-- ------------------------------------------------
-- Contact info table
-- ------------------------------------------------
CREATE TABLE IF NOT EXISTS contact_info (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type VARCHAR(50) NOT NULL,
    value VARCHAR(255) NOT NULL,
    label_ar VARCHAR(100),
    is_active TINYINT(1) DEFAULT 1,
    sort_order INT DEFAULT 0
);

-- ================================================
-- SEED DATA
-- ================================================

-- Admin: username=admin | password=admin123
INSERT INTO admins (username, password, name) VALUES
('admin', SHA2('admin123', 256), 'مدير النظام');

-- ------------------------------------------------
-- الفروع الحقيقية
-- ------------------------------------------------
INSERT INTO branches (name_ar, name_en, city_ar, city_en, address_ar, phone, map_url, sort_order) VALUES
('مكة المكرمة - الشرائع', 'Makkah - Al Sharaie', 'مكة المكرمة', 'Makkah', 'Al Muhandes Umar Qadi، الشرائع، مكة المكرمة', '920029921', 'https://maps.app.goo.gl/3bzfVB1pDvXQtqc46', 1),
('مكة المكرمة - فرع ٢', 'Makkah - Branch 2', 'مكة المكرمة', 'Makkah', 'شارع الخمسين، طريق الملك خالد، مكة المكرمة', '920029921', 'https://maps.app.goo.gl/kiwaqfbjFSaCCzW1A', 2),
('جدة - ابحر', 'Jeddah - Abhur', 'جدة', 'Jeddah', 'حي ابحر الشمالية، جدة', '920029921', 'https://maps.app.goo.gl/XwYU2Mpf8ipFkzxa7', 3),
('جدة - فرع ٢', 'Jeddah - Branch 2', 'جدة', 'Jeddah', 'مقابل سبار ماركت، جدة', '920029921', 'https://maps.app.goo.gl/3zw2UDqX7orh4UqG6', 4),
('جدة - حي الحمراء', 'Jeddah - Al Hamra', 'جدة', 'Jeddah', 'حي الحمراء، جدة', '920029921', NULL, 5),
('الرياض - حي الربيع', 'Riyadh - Al Rabi', 'الرياض', 'Riyadh', 'حي الربيع، الرياض', '920029921', NULL, 6),
('الرياض - حي المحمدية', 'Riyadh - Al Muhammadiyah', 'الرياض', 'Riyadh', 'حي المحمدية، الرياض', '920029921', NULL, 7),
('الرياض - حي الياسمين', 'Riyadh - Al Yasmin', 'الرياض', 'Riyadh', 'حي الياسمين، الرياض', '920029921', NULL, 8),
('الرياض - الدائري الشرقي', 'Riyadh - Eastern Ring', 'الرياض', 'Riyadh', 'الدائري الشرقي، الرياض', '920029921', NULL, 9),
('الرياض - ظهرة لبن', 'Riyadh - Dhahrat Laban', 'الرياض', 'Riyadh', 'حي ظهرة لبن، الرياض', '920029921', NULL, 10),
('الدمام - حي النزهة', 'Dammam - Al Nuzha', 'الدمام', 'Dammam', 'حي النزهة، الدمام', '920029921', NULL, 11),
('الدمام - حي الفيحاء', 'Dammam - Al Fayha', 'الدمام', 'Dammam', 'شارع خالد بن الوليد، حي الفيحاء، الدمام', '920029921', 'https://maps.app.goo.gl/WBePwEkF5ESpTLVn7', 12),
('الخبر', 'Al Khobar', 'الخبر', 'Khobar', 'EKGA7484، الخبر', '920029921', 'https://maps.app.goo.gl/NB1uMntPLAiGfbCL6', 13),
('الطائف', 'Taif', 'الطائف', 'Taif', 'شارع الخمسين، طريق الملك خالد، الطائف', '920029921', 'https://maps.app.goo.gl/q79uN4MrVHdcqroz6', 14),
('الأحساء', 'Al Ahsa', 'الأحساء', 'Al Ahsa', 'طريق عين نجم، المبرز، الأحساء', '920029921', 'https://maps.app.goo.gl/mZcuxXo8ZqqQqtaA8', 15),
('حفر الباطن', 'Hafar Al Batin', 'حفر الباطن', 'Hafar Al Batin', '2811 طريق فيصل بن عبدالعزيز، حفر الباطن', '920029921', 'https://maps.app.goo.gl/M7b9e9q28zkFV8H67', 16),
('خميس مشيط', 'Khamis Mushait', 'خميس مشيط', 'Khamis Mushait', 'طريق الأمير سلطان، خميس مشيط', '920029921', 'https://maps.app.goo.gl/EjWLixBzPt6SnnC18', 17),
('جازان', 'Jizan', 'جازان', 'Jizan', 'كورنيش الملك فهد، جازان', '920029921', 'https://maps.app.goo.gl/byk6aX6Mko4kestT9', 18),
('تبوك', 'Tabuk', 'تبوك', 'Tabuk', 'تبوك', '920029921', 'https://maps.app.goo.gl/P2RdZHZCvifsMxTr7', 19),
('حائل', 'Hail', 'حائل', 'Hail', 'حائل', '920029921', NULL, 20);

-- ------------------------------------------------
-- 25 Brands
-- ------------------------------------------------
INSERT INTO brands (name_ar, name_en, sort_order) VALUES
('لوريال باريس', 'L\'Oréal Paris', 1),
('ميبيلين نيويورك', 'Maybelline New York', 2),
('ناكس برو ميك أب', 'NYX Professional Makeup', 3),
('ماك كوزمتيكس', 'MAC Cosmetics', 4),
('فينتي بيوتي', 'Fenty Beauty', 5),
('شارلوت تيلبيري', 'Charlotte Tilbury', 6),
('أربان ديكاي', 'Urban Decay', 7),
('نارس كوزمتيكس', 'NARS Cosmetics', 8),
('لانكوم باريس', 'Lancôme', 9),
('إيف سان لوران بيوتي', 'YSL Beauty', 10),
('ديور بيوتي', 'Dior Beauty', 11),
('شانيل بيوتي', 'Chanel Beauty', 12),
('جيفنشي بيوتي', 'Givenchy Beauty', 13),
('أرماني بيوتي', 'Armani Beauty', 14),
('توم فورد بيوتي', 'Tom Ford Beauty', 15),
('فالنتينو بيوتي', 'Valentino Beauty', 16),
('باربيري بيوتي', 'Burberry Beauty', 17),
('جو مالون لندن', 'Jo Malone London', 18),
('بارفانز دي مارلي', 'Parfums de Marly', 19),
('كيليان باريس', 'Kilian Paris', 20),
('برادا بيوتي', 'Prada Beauty', 21),
('كارولينا هيريرا', 'Carolina Herrera', 22),
('فيكتور آند رولف', 'Viktor&Rolf', 23),
('ناركيسو رودريغيز', 'Narciso Rodriguez', 24),
('ميمو باريس', 'Memo Paris', 25);

-- ------------------------------------------------
-- Social Media (بيانات حقيقية)
-- ------------------------------------------------
INSERT INTO social_media (platform, platform_ar, url, username, icon, color, sort_order) VALUES
('Instagram', 'انستقرام', 'https://www.instagram.com/makhazenalenaya/', '@makhazenalenaya', 'fa-instagram', '#E1306C', 1),
('TikTok', 'تيك توك', 'https://www.tiktok.com/@makhazenalenaya', '@makhazenalenaya', 'fa-tiktok', '#ffffff', 2),
('Snapchat', 'سناب شات', 'https://www.snapchat.com/add/makhazenalenaya', 'makhazenalenaya', 'fa-snapchat', '#FFFC00', 3),
('Twitter', 'تويتر / X', 'https://x.com/makhazenalenaya', '@makhazenalenaya', 'fa-x-twitter', '#ffffff', 4),
('WhatsApp', 'واتساب', 'https://wa.me/966920029921', '920029921', 'fa-whatsapp', '#25D366', 5);

-- ------------------------------------------------
-- Contact Info (بيانات حقيقية)
-- ------------------------------------------------
INSERT INTO contact_info (type, value, label_ar, sort_order) VALUES
('phone', '920029921', 'خدمة العملاء', 1),
('whatsapp', '+966920029921', 'واتساب', 2);
