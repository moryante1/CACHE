<?php
/**
 * SHASHITY PRO — Main Controller
 * Refactored from single-file admin.php (10,813 lines). Order = original.
 */
require_once __DIR__ . '/config/bootstrap.php';
require_once __DIR__ . '/functions/security_cache.php';
require_once __DIR__ . '/functions/auth.php';
require_once __DIR__ . '/functions/helpers.php';
// نظام الاشتراكات والكوبونات (مكتبة مشتركة مع واجهة الموقع)
require_once __DIR__ . '/functions/subscriptions.php';
subsEnsureSchema();   // إنشاء الجداول عند أول تشغيل — يُتخطّى لاحقاً بعلامة ملف
subsExpireDue();      // تعطيل الحسابات المنتهية
// إظهار المحتوى المُضاف: يصحّح صفوف is_active التي لم تُضبط قط.
// مرّة واحدة ثم يُتخطّى بعلامة ملف (storage/.content_active_ok).
contentEnsureActiveFlags();
require_once __DIR__ . '/ajax/router.php';
require_once __DIR__ . '/handlers/categories.php';
require_once __DIR__ . '/handlers/channels.php';
require_once __DIR__ . '/handlers/roles.php';
// معالج تغيير كلمة المرور — كان مفقوداً تماماً فكان النموذج لا يفعل شيئاً
require_once __DIR__ . '/handlers/change_password.php';

/* ---------- VIEW ---------- */
require_once __DIR__ . '/includes/head.php';
?>
<?php readfile(__DIR__ . '/assets/css/main.css'); ?>
<?php require_once __DIR__ . '/includes/style_tags_a.php'; ?>
<?php readfile(__DIR__ . '/assets/css/theme.css'); ?>
<?php require_once __DIR__ . '/includes/style_tags_b.php'; ?>
<?php readfile(__DIR__ . '/assets/css/extra.css'); ?>
<?php
require_once __DIR__ . '/includes/head_tail.php';
require_once __DIR__ . '/includes/inline1.php';
require_once __DIR__ . '/includes/scripts_head.php';
require_once __DIR__ . '/includes/topbar.php';
require_once __DIR__ . '/includes/alerts.php';
require_once __DIR__ . '/includes/nav_open.php';

foreach (['dashboard','categories','channels','m3u_import','xtream','api_settings','series',
          'vupload','vmanage','site_settings','change_password','system_tools','backup',
          'users','login_logs','dashboard_js','company_info','general_settings',
          'frontend_control','subscriptions','coupons','subscribers','_rest_view'] as $__p) {
    require_once __DIR__ . '/pages/' . $__p . '.php';
}

require_once __DIR__ . '/includes/hls.php';
require_once __DIR__ . '/includes/main_js.php';
require_once __DIR__ . '/includes/js_extra.php';
require_once __DIR__ . '/includes/improve.php';
// جافاسكربت الاشتراكات — بعد main_js.php لأنه يستخدم OM/CM/csrfToken
require_once __DIR__ . '/includes/subs_js.php';
require_once __DIR__ . '/includes/final_js.php';
require_once __DIR__ . '/includes/footer.php';
