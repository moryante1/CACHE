-- ══════════════════════════════════════════════════════════════
--  Shashety IPTV — فهارس الأداء والجداول المفقودة
--  ------------------------------------------------------------
--  النظام يطبّق هذه التغييرات تلقائياً عند أول تشغيل بعد التحديث
--  (عبر functions/helpers.php). هذا الملف للتطبيق اليدوي عبر
--  phpMyAdmin أو سطر الأوامر إن فضّلت ذلك، أو للمراجعة.
--
--  آمن للتشغيل المتكرر: الأوامر التي تفشل لأن الفهرس موجود مسبقاً
--  لا تؤثر على البيانات. شغّل كل سطر على حدة إن توقف التنفيذ.
--
--  الاستخدام:
--    mysql -u USER -p DBNAME < sql/performance_indexes.sql
-- ══════════════════════════════════════════════════════════════


-- ══════════════════════════════════════════════════════════════
-- 1) جدول blocked_ips — كان مفقوداً من المشروع بالكامل
-- ══════════════════════════════════════════════════════════════
-- ⚠️ صفحة login.php تستعلم عن هذا الجدول لحظر عناوين IP بعد 5
--    محاولات فاشلة، لكنه لم يكن يُنشأ في أي ملف. الاستعلام كان
--    يفشل داخل try/catch فارغ، فتبدو الحماية موجودة وهي معطّلة.

CREATE TABLE IF NOT EXISTS `blocked_ips` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `ip_address` VARCHAR(45)  NOT NULL UNIQUE,
  `reason`     VARCHAR(255) DEFAULT 'محاولات دخول فاشلة متكررة',
  `blocked_at` TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_ip` (`ip_address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ══════════════════════════════════════════════════════════════
-- 2) جدول login_logs — تأكيد وجوده مع الفهارس
-- ══════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS `login_logs` (
  `id`           INT AUTO_INCREMENT PRIMARY KEY,
  `ip_address`   VARCHAR(45)  NOT NULL,
  `username`     VARCHAR(100),
  `status`       VARCHAR(50)  DEFAULT 'failed',
  `attempt_time` TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_ip_time`     (`ip_address`, `attempt_time`),
  KEY `idx_status_time` (`status`, `attempt_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ══════════════════════════════════════════════════════════════
-- 3) فهارس الأداء
-- ══════════════════════════════════════════════════════════════

-- ── episodes ──
-- الاستعلام المتكرر:
--   SELECT * FROM episodes WHERE series_id = ?
--   ORDER BY episode_number, display_order, id
-- كان جدول episodes يملك PRIMARY KEY فقط، أي مسح كامل للجدول عند
-- عرض حلقات أي مسلسل. مع آلاف الحلقات يصبح الفرق هائلاً.
ALTER TABLE `episodes` ADD INDEX `idx_series`       (`series_id`);
ALTER TABLE `episodes` ADD INDEX `idx_series_order` (`series_id`, `episode_number`, `display_order`, `id`);

-- ── series ──
-- الاستعلام: WHERE category_id = ? ORDER BY display_order, id
ALTER TABLE `series`   ADD INDEX `idx_category`  (`category_id`);
ALTER TABLE `series`   ADD INDEX `idx_cat_order` (`category_id`, `display_order`, `id`);

-- ── channels ──
-- فهرس مركّب يطابق ترتيب لوحة التحكم:
--   ORDER BY category_id, display_order, id
-- الفهارس المفردة الموجودة لا تغطي الفرز المركّب.
ALTER TABLE `channels` ADD INDEX `idx_cat_order` (`category_id`, `display_order`, `id`);

-- ── view_stats ──
-- إحصاءات المشاهدة تُقرأ حسب القناة والفترة الزمنية.
ALTER TABLE `view_stats` ADD INDEX `idx_channel_time` (`channel_id`, `viewed_at`);


-- ══════════════════════════════════════════════════════════════
-- 4) صيانة اختيارية — تقليل حجم سجل الدخول
-- ══════════════════════════════════════════════════════════════
-- جدول login_logs ينمو بلا حد. شغّل هذا شهرياً لحذف ما مضى عليه
-- أكثر من 90 يوماً:
--
--   DELETE FROM login_logs WHERE attempt_time < DATE_SUB(NOW(), INTERVAL 90 DAY);
