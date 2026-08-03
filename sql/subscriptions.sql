-- ═══════════════════════════════════════════════════════════════════════════
--  SHASHETY PRO — نظام الاشتراكات والكوبونات
--  ───────────────────────────────────────────────────────────────────────────
--  هذا الملف مرجعي فقط. التطبيق يُنشئ الجداول تلقائياً عند أول تشغيل
--  عبر subsEnsureSchema() في functions/subscriptions.php، لذا لا حاجة
--  لاستيراده يدوياً — لكنه موجود لمن يفضّل الاستيراد من phpMyAdmin.
--
--  كل الجداول InnoDB بمفاتيح أجنبية حقيقية: حذف خطة يمنعه المفتاح إن كان
--  هناك مشتركون عليها، وحذف كوبون يحذف سجلات استخدامه معه.
-- ═══════════════════════════════════════════════════════════════════════════

SET NAMES utf8mb4;

-- ─────────────────────────────────────────────────────────────
--  1) خطط الاشتراك
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `subscription_plans` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `code`          VARCHAR(32)  NOT NULL,              -- weekly / monthly / yearly / custom_x
  `name_ar`       VARCHAR(100) NOT NULL,
  `name_en`       VARCHAR(100) NOT NULL DEFAULT '',
  `name_tr`       VARCHAR(100) NOT NULL DEFAULT '',
  `duration_days` INT          NOT NULL DEFAULT 30,   -- مدة الاشتراك بالأيام
  `price`         DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `is_active`     TINYINT(1)   NOT NULL DEFAULT 1,    -- تفعيل / إلغاء الخطة
  `sort_order`    INT          NOT NULL DEFAULT 0,
  `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_plan_code` (`code`),
  KEY `idx_plan_active` (`is_active`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
--  2) مشتركو الموقع
--     منفصل تماماً عن `users` / `admin_users` (وهما لمديري اللوحة).
--     خلطهما يعني أن ثغرة في تسجيل المشتركين = صلاحية إدارة.
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `site_users` (
  `id`             INT AUTO_INCREMENT PRIMARY KEY,
  `username`       VARCHAR(50)  NOT NULL,
  `password`       VARCHAR(255) NOT NULL,             -- password_hash() — bcrypt
  `email`          VARCHAR(100) DEFAULT NULL,
  `is_active`      TINYINT(1)   NOT NULL DEFAULT 0,   -- الحساب يُنشأ غير مفعّل
  `plan_id`        INT          DEFAULT NULL,
  `sub_start`      DATETIME     DEFAULT NULL,
  `sub_end`        DATETIME     DEFAULT NULL,
  `activated_via`  ENUM('coupon','admin','none') NOT NULL DEFAULT 'none',
  `last_login`     DATETIME     DEFAULT NULL,
  `last_ip`        VARCHAR(45)  DEFAULT NULL,
  `notes`          TEXT         DEFAULT NULL,
  `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_site_user` (`username`),
  KEY `idx_su_active` (`is_active`, `sub_end`),
  KEY `idx_su_plan`   (`plan_id`),
  CONSTRAINT `fk_su_plan` FOREIGN KEY (`plan_id`)
      REFERENCES `subscription_plans`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
--  3) الكوبونات
--     duration_days منسوخة من الخطة وقت الإنشاء عمداً: تغيير الخطة
--     لاحقاً يجب ألا يغيّر قيمة كوبون بيع بالفعل.
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `coupons` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `code`          VARCHAR(32)  NOT NULL,
  `plan_id`       INT          DEFAULT NULL,
  `duration_days` INT          NOT NULL DEFAULT 30,
  `max_uses`      INT          NOT NULL DEFAULT 1,
  `used_count`    INT          NOT NULL DEFAULT 0,
  `is_active`     TINYINT(1)   NOT NULL DEFAULT 1,
  `expires_at`    DATETIME     DEFAULT NULL,          -- صلاحية الكوبون نفسه (اختياري)
  `note`          VARCHAR(255) DEFAULT NULL,
  `created_by`    VARCHAR(50)  DEFAULT NULL,
  `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_used_at`  DATETIME     DEFAULT NULL,
  `last_used_by`  VARCHAR(50)  DEFAULT NULL,
  UNIQUE KEY `uq_coupon_code` (`code`),
  KEY `idx_cp_active` (`is_active`),
  KEY `idx_cp_plan`   (`plan_id`),
  CONSTRAINT `fk_cp_plan` FOREIGN KEY (`plan_id`)
      REFERENCES `subscription_plans`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
--  4) سجل استهلاك الكوبونات
--     UNIQUE(coupon_id, user_id) يمنع المستخدم نفسه من استهلاك
--     الكوبون مرتين حتى لو كان max_uses كبيراً.
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `coupon_redemptions` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `coupon_id`   INT NOT NULL,
  `user_id`     INT NOT NULL,
  `username`    VARCHAR(50) DEFAULT NULL,
  `days_added`  INT NOT NULL DEFAULT 0,
  `sub_end`     DATETIME DEFAULT NULL,
  `ip`          VARCHAR(45) DEFAULT NULL,
  `redeemed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_redeem` (`coupon_id`, `user_id`),
  KEY `idx_rd_user` (`user_id`),
  CONSTRAINT `fk_rd_coupon` FOREIGN KEY (`coupon_id`)
      REFERENCES `coupons`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_rd_user` FOREIGN KEY (`user_id`)
      REFERENCES `site_users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
--  5) سجل دخول المشتركين (منفصل عن سجل دخول الإدارة)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `site_login_logs` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `user_id`    INT DEFAULT NULL,
  `username`   VARCHAR(50) DEFAULT NULL,
  `ip`         VARCHAR(45) DEFAULT NULL,
  `user_agent` VARCHAR(255) DEFAULT NULL,
  `status`     ENUM('success','fail','blocked','register','expired') NOT NULL DEFAULT 'success',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_sll_user` (`user_id`),
  KEY `idx_sll_time` (`created_at`),
  KEY `idx_sll_ip`   (`ip`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
--  الخطط الافتراضية الثلاث
-- ─────────────────────────────────────────────────────────────
INSERT IGNORE INTO `subscription_plans`
  (`code`,`name_ar`,`name_en`,`name_tr`,`duration_days`,`price`,`is_active`,`sort_order`) VALUES
  ('weekly',  'اشتراك أسبوعي','Weekly Plan', 'Haftalık Abonelik',   7,   5.00, 1, 1),
  ('monthly', 'اشتراك شهري',  'Monthly Plan','Aylık Abonelik',      30, 15.00, 1, 2),
  ('yearly',  'اشتراك سنوي',  'Yearly Plan', 'Yıllık Abonelik',    365,150.00, 1, 3);

-- ─────────────────────────────────────────────────────────────
--  إعدادات النظام الجديدة
-- ─────────────────────────────────────────────────────────────
INSERT IGNORE INTO `settings` (`setting_key`,`setting_value`) VALUES
  ('index_protection', '0'),   -- حماية الصفحة الرئيسية (0 = مفتوحة للجميع)
  ('allow_registration','1'),  -- السماح بإنشاء حسابات جديدة
  ('currency_symbol', '$'),    -- رمز العملة المعروض
  ('currency_code',   'USD');  -- كود العملة
