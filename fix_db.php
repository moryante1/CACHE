<?php
/**
 * أداة إصلاح بنية قاعدة البيانات — Shashety IPTV
 *
 * ⚠️ إصلاح أمني: النسخة السابقة كانت تُنفّذ أوامر ALTER TABLE
 *    دون أي مصادقة — أي زائر كان يستطيع تعديل بنية قاعدة البيانات.
 *    الآن: مدير مسجّل الدخول + POST + رمز CSRF.
 */

require_once __DIR__ . '/core/config.php';

securityHeaders();
header('Content-Type: text/html; charset=utf-8');

if (!isAdminLoggedIn()) {
    http_response_code(403);
    exit('<!DOCTYPE html><html dir="rtl"><meta charset="utf-8">'
        . '<body style="background:#111;color:#e74c3c;text-align:center;padding:60px;font-family:sans-serif">'
        . '<h2>غير مصرح</h2><p style="color:#888">يجب تسجيل الدخول كمدير.</p></body></html>');
}

$tables = [
    'admins',
    'admin_users',
    'categories',
    'channels',
    'episodes',
    'series',
    'settings',
    'trial_users',
    'users',
    'view_stats',
    'm3u_playlists',
    'xtream_accounts',
];

$log = [];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {

    if (!csrfValidate()) {
        $log[] = ['err', 'انتهت صلاحية الجلسة — أعد تحميل الصفحة.'];
    } else {
        foreach ($tables as $t) {
            // الأسماء من قائمة ثابتة داخل الكود، لا من المستخدم.
            if (!preg_match('/^[a-z0-9_]+$/i', $t)) {
                continue;
            }

            try {
                db()->exec("ALTER TABLE `{$t}` ADD PRIMARY KEY (`id`)");
                $log[] = ['ok', "تمت إضافة المفتاح الأساسي إلى {$t}"];
            } catch (Throwable $e) {
                // موجود مسبقاً أو الجدول غير موجود — يُتجاهل
            }

            try {
                db()->exec("ALTER TABLE `{$t}` MODIFY `id` INT NOT NULL AUTO_INCREMENT");
                $log[] = ['ok', "تم تفعيل AUTO_INCREMENT في {$t}"];
            } catch (Throwable $e) {
                // يُتجاهل
            }
        }

        logActivity('fix_db: إصلاح بنية قاعدة البيانات', 'tables=' . count($tables));
        $log[] = ['done', 'اكتمل الفحص.'];
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>إصلاح قاعدة البيانات</title>
<style>
 body{background:#0d0f14;color:#e8ecf3;font-family:Tahoma,sans-serif;padding:40px;margin:0}
 .wrap{max-width:720px;margin:auto;background:#161a23;border:1px solid #2a3140;border-radius:14px;padding:26px}
 h1{font-size:19px;margin:0 0 6px;color:#d4af37}
 p.sub{color:#8b94a7;font-size:13px;margin:0 0 22px}
 button{background:#d4af37;color:#1a1a1a;border:0;padding:11px 22px;border-radius:8px;font-weight:700;cursor:pointer}
 a{color:#8b94a7;font-size:13px;margin-inline-start:14px}
 ul{list-style:none;padding:0;margin:22px 0 0}
 li{padding:8px 12px;border-radius:8px;margin-bottom:6px;font-size:13px}
 li.ok{background:rgba(46,204,113,.12);color:#2ecc71}
 li.err{background:rgba(231,76,60,.12);color:#e74c3c}
 li.done{background:rgba(212,175,55,.12);color:#d4af37;font-weight:700}
</style>
</head>
<body>
<div class="wrap">
  <h1>إصلاح بنية قاعدة البيانات</h1>
  <p class="sub">يضيف المفتاح الأساسي و AUTO_INCREMENT للجداول التي تفتقدها. آمن للتشغيل المتكرر.</p>

  <form method="post">
    <?= csrfField() ?>
    <button type="submit">تشغيل الإصلاح</button>
    <a href="admin.php">→ رجوع للوحة التحكم</a>
  </form>

  <?php if ($log): ?>
  <ul>
    <?php foreach ($log as [$type, $text]): ?>
      <li class="<?= htmlspecialchars($type, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($text, ENT_QUOTES, 'UTF-8') ?></li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>
</div>
</body>
</html>
