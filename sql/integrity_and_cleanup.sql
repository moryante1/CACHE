-- ══════════════════════════════════════════════════════════════
--  Shashety IPTV — سلامة البيانات وتنظيف السجلات اليتيمة
-- ──────────────────────────────────────────────────────────────
--  ⚠️ اقرأ قبل التشغيل:
--   • خذ نسخة احتياطية أولاً (backup_system.php?action=export_full)
--   • شغّل قسم الفحص (١) أولاً لترى الحجم قبل الحذف
--   • أقسام الحذف (٢) والقيود (٣) اختيارية — راجع النتائج قبلها
-- ══════════════════════════════════════════════════════════════


-- ══════════════════════════════════════════════════════════════
-- ١) الفحص — كم سجلاً يتيماً لديك؟ (آمن تماماً، لا يحذف شيئاً)
-- ══════════════════════════════════════════════════════════════
-- سجل يتيم = صف يشير إلى أب محذوف. يستهلك مساحة، ويظهر في
-- الإحصاءات، وقد يسبب صفوفاً فارغة في الواجهة.

SELECT 'قنوات بقسم محذوف' AS النوع, COUNT(*) AS العدد
FROM channels ch
LEFT JOIN categories c ON ch.category_id = c.id
WHERE c.id IS NULL

UNION ALL SELECT 'مسلسلات/أفلام بقسم محذوف', COUNT(*)
FROM series s
LEFT JOIN categories c ON s.category_id = c.id
WHERE c.id IS NULL

UNION ALL SELECT 'حلقات بمسلسل محذوف', COUNT(*)
FROM episodes e
LEFT JOIN series s ON e.series_id = s.id
WHERE s.id IS NULL

UNION ALL SELECT 'إحصاءات مشاهدة لقناة محذوفة', COUNT(*)
FROM view_stats v
LEFT JOIN channels ch ON v.channel_id = ch.id
WHERE ch.id IS NULL

UNION ALL SELECT 'قنوات مكرّرة (نفس الرابط)', COUNT(*) - COUNT(DISTINCT stream_url)
FROM channels WHERE stream_url <> '';


-- ══════════════════════════════════════════════════════════════
-- ٢) التنظيف — احذف السجلات اليتيمة (بعد مراجعة نتائج القسم ١)
-- ══════════════════════════════════════════════════════════════
-- الترتيب مهم: الأبناء قبل الآباء.

-- DELETE e FROM episodes e
--   LEFT JOIN series s ON e.series_id = s.id
--   WHERE s.id IS NULL;

-- DELETE v FROM view_stats v
--   LEFT JOIN channels ch ON v.channel_id = ch.id
--   WHERE ch.id IS NULL;

-- DELETE ch FROM channels ch
--   LEFT JOIN categories c ON ch.category_id = c.id
--   WHERE c.id IS NULL;

-- DELETE s FROM series s
--   LEFT JOIN categories c ON s.category_id = c.id
--   WHERE c.id IS NULL;


-- ══════════════════════════════════════════════════════════════
-- ٣) القيود المرجعية — تمنع تكرار المشكلة مستقبلاً
-- ══════════════════════════════════════════════════════════════
-- الوضع الحالي: مفتاح أجنبي **واحد فقط** في المشروع كله
--   (channels.category_id → categories.id)
-- بينما series و episodes و view_stats بلا أي قيد، فالحذف من
-- لوحة التحكم يترك أبناءً معلّقين في كل مرة.
--
-- ⚠️ لن تنجح هذه الأوامر قبل تنظيف السجلات اليتيمة (القسم ٢).

-- ALTER TABLE series
--   ADD CONSTRAINT fk_series_category
--   FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE;

-- ALTER TABLE episodes
--   ADD CONSTRAINT fk_episodes_series
--   FOREIGN KEY (series_id) REFERENCES series(id) ON DELETE CASCADE;

-- ALTER TABLE view_stats
--   ADD CONSTRAINT fk_viewstats_channel
--   FOREIGN KEY (channel_id) REFERENCES channels(id) ON DELETE CASCADE;


-- ══════════════════════════════════════════════════════════════
-- ٤) توحيد ترتيب المقارنة (Collation)
-- ══════════════════════════════════════════════════════════════
-- المشروع يخلط ثلاثة ترتيبات:
--   utf8mb4_unicode_ci   (8 جداول)
--   utf8mb4_general_ci   (2 جدول)
--   utf8mb4_0900_ai_ci   (1 جدول)
--
-- الخلط يسبب خطأ "Illegal mix of collations" عند JOIN بين
-- جدولين مختلفَي الترتيب، ويمنع MySQL من استخدام الفهارس أحياناً.
-- utf8mb4_unicode_ci هو الأنسب للعربية والأوسع توافقاً.

-- ALTER TABLE series   CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- ALTER TABLE episodes CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;


-- ══════════════════════════════════════════════════════════════
-- ٥) صيانة دورية — شغّلها شهرياً
-- ══════════════════════════════════════════════════════════════
-- DELETE FROM login_logs  WHERE attempt_time < DATE_SUB(NOW(), INTERVAL 90 DAY);
-- DELETE FROM view_stats  WHERE viewed_at    < DATE_SUB(NOW(), INTERVAL 180 DAY);
-- OPTIMIZE TABLE channels, series, episodes, view_stats, login_logs;
