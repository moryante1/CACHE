<?php
/**
 * SHASHITY PRO — Front Site Controller
 * Refactored from single-file index.php (6,232 lines).
 * Execution order is IDENTICAL to the original.
 */
require_once __DIR__ . '/site/config/bootstrap.php';
require_once __DIR__ . '/site/functions/security_cache.php';
require_once __DIR__ . '/site/functions/license.php';
require_once __DIR__ . '/site/config/settings_load.php';
require_once __DIR__ . '/site/config/general_settings.php';
require_once __DIR__ . '/site/functions/admin_visitor.php';
require_once __DIR__ . '/site/api/tmdb_proxy.php';
require_once __DIR__ . '/site/functions/force_https.php';
require_once __DIR__ . '/site/gates/site_gate.php';
require_once __DIR__ . '/site/gates/maintenance.php';
// بوابة المشتركين — يجب أن تسبق أي إخراج، وبعد الصيانة حتى تبقى
// صفحة الصيانة أولوية على شاشة الدخول.
require_once __DIR__ . '/site/gates/subscriber_gate.php';
require_once __DIR__ . '/site/config/db_extra.php';

/* ---------- VIEW ---------- */
require_once __DIR__ . '/site/includes/head.php';
require_once __DIR__ . '/site/includes/main_css.php';   // has PHP inside (theme_color) - cannot be a static .css
require_once __DIR__ . '/site/includes/head_css_tail.php';
require_once __DIR__ . '/site/includes/announce.php';
require_once __DIR__ . '/site/includes/devtools.php';
require_once __DIR__ . '/site/includes/license_notice.php';
require_once __DIR__ . '/site/pages/main.php';
require_once __DIR__ . '/site/includes/cdn_scripts.php';
require_once __DIR__ . '/site/includes/main_js.php';
require_once __DIR__ . '/site/includes/js_small.php';
require_once __DIR__ . '/site/includes/js_block2.php';
require_once __DIR__ . '/site/includes/improve_js.php';
require_once __DIR__ . '/site/includes/player_enhance.php';
require_once __DIR__ . '/site/includes/screensaver.php';
require_once __DIR__ . '/site/includes/saver_toggle.php';
require_once __DIR__ . '/site/includes/player_fixes.php';
// الحزمة الاحترافية للمشغّل (إضافية بالكامل — احذف هذا السطر للعودة للسلوك السابق)
require_once __DIR__ . '/site/includes/player_pro.php';
require_once __DIR__ . '/site/includes/catnav_fix.php';
require_once __DIR__ . '/site/includes/groups_export.php';
// شارة المشترك (تظهر فقط عند تفعيل حماية الصفحة الرئيسية)
require_once __DIR__ . '/site/includes/subscriber_badge.php';
require_once __DIR__ . '/site/includes/final_block.php';
require_once __DIR__ . '/site/includes/footer.php';
