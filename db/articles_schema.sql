-- ================================================
-- قاعدة بيانات المقالات المنفصلة
-- Database: makhazenalenaya_articlesdb
-- ================================================

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

CREATE DATABASE IF NOT EXISTS makhazenalenaya_articlesdb
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE makhazenalenaya_articlesdb;

-- ------------------------------------------------
-- جدول المشرفين (مستقل عن المشرفين الرئيسيين)
-- ------------------------------------------------
CREATE TABLE IF NOT EXISTS admins (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    username   VARCHAR(100) NOT NULL UNIQUE,
    password   VARCHAR(255) NOT NULL,
    name       VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- كلمة المرور الافتراضية: admin123
INSERT INTO admins (username, password, name) VALUES
('admin', SHA2('admin123', 256), 'مدير المقالات')
ON DUPLICATE KEY UPDATE username = username;

-- ------------------------------------------------
-- جدول المقالات
-- ------------------------------------------------
CREATE TABLE IF NOT EXISTS articles (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    title               VARCHAR(255) NOT NULL,
    slug                VARCHAR(255) NOT NULL UNIQUE,
    excerpt             TEXT,
    body                LONGTEXT,
    cover_image         VARCHAR(500),
    category            VARCHAR(100),
    tags                VARCHAR(255),
    seo_title           VARCHAR(255),
    seo_description     TEXT,
    seo_keywords        VARCHAR(255),
    canonical_url       VARCHAR(500),
    og_title            VARCHAR(255),
    og_description      TEXT,
    og_image            VARCHAR(500),
    twitter_title       VARCHAR(255),
    twitter_description TEXT,
    twitter_image       VARCHAR(500),
    schema_type         VARCHAR(50)  DEFAULT 'Article',
    author_name         VARCHAR(255) DEFAULT 'مخازن العناية',
    is_active           TINYINT(1)   DEFAULT 1,
    is_featured         TINYINT(1)   DEFAULT 0,
    sort_order          INT          DEFAULT 0,
    view_count          INT          DEFAULT 0,
    published_at        DATETIME,
    created_at          TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
