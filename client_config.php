<?php
/**
 * client_config.php (الجذر) — ملف تحويل فقط
 * ══════════════════════════════════════════════════════════════
 *
 * كان هذا الملف نسخة ثانية مطابقة من core/client_config.php، يعرّف
 * نفس الدوال (getMachineId, verifyLicenseFromServer, getLicenseKey ...)
 * ونفس الثوابت (LICENSE_SERVER_URL, SECURITY_SALT, HWID_PRIMARY ...)
 * بلا أي حماية من إعادة التعريف.
 *
 * المخاطر التي كانت قائمة:
 *   ① تحميل النسختين في طلب واحد = خطأ قاتل "Cannot redeclare function".
 *   ② تعديل إعداد الرخصة في نسخة دون الأخرى يجعل سلوك النظام مختلفاً
 *      بحسب الصفحة التي فتحها المستخدم — عطل يصعب تفسيره.
 *
 * مصدر الحقيقة الآن: core/client_config.php
 * النسخة الأصلية محفوظة في: _backup_before_fix/client_config.root.php
 */

require_once __DIR__ . '/core/client_config.php';
