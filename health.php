<?php
/**
 * ══════════════════════════════════════════════════════════════
 *  فحص صحة النظام — Shashety IPTV
 * ══════════════════════════════════════════════════════════════
 *
 *  لماذا أُضيف: لم تكن هناك أي وسيلة لمعرفة حالة النظام دون فتح
 *  اللوحة وتجربة كل شيء يدوياً. هذا الملف يجيب في ثانية واحدة عن:
 *  هل قاعدة البيانات تعمل؟ هل المجلدات قابلة للكتابة؟ هل الإضافات
 *  المطلوبة مثبّتة؟ هل الملفات الحساسة مكشوفة؟ هل الرخصة سارية؟
 *
 *  الاستخدام:
 *    • من المتصفح (مدير مسجّل الدخول): /health.php
 *    • للمراقبة الآلية:                /health.php?format=json&key=<HEALTH_KEY>
 *
 *  اضبط HEALTH_KEY في .env لتفعيل الوصول الآلي بلا تسجيل دخول.
 */

/* رقم إصدار هذا الملف — يظهر أسفل الصفحة.
   فائدته العملية: عند رفع نسخة جديدة إلى الخادم تعرف فوراً إن كانت
   الصفحة التي تراها هي الجديدة فعلاً أم نسخة قديمة من ذاكرة المتصفح. */
const HEALTH_VERSION = '2.0';

require_once __DIR__ . '/core/config.php';

securityHeaders();
header('X-Robots-Tag: noindex, nofollow');

/* منع المتصفح من عرض نسخة مخزَّنة من هذه الصفحة تحديداً —
   صفحة تشخيص يجب أن تعكس الحالة اللحظية دائماً. */
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$format = ($_GET['format'] ?? '') === 'json' ? 'json' : 'html';

// ── التحكم بالوصول ──
$healthKey = (string) env('HEALTH_KEY', '');
$viaKey    = $healthKey !== '' && hash_equals($healthKey, (string) ($_GET['key'] ?? ''));

if (!$viaKey && !isAdminLoggedIn()) {
    http_response_code(403);
    if ($format === 'json') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'forbidden'], JSON_UNESCAPED_UNICODE);
    } else {
        echo '<!DOCTYPE html><html dir="rtl"><meta charset="utf-8">'
           . '<body style="background:#111;color:#e74c3c;text-align:center;padding:60px;font-family:sans-serif">'
           . '<h2>غير مصرح</h2></body></html>';
    }
    exit;
}

// ══════════════════════════════════════════════════════════════
$checks = [];

/**
 * تسجيل نتيجة فحص.
 *
 * @param string $name    اسم الفحص.
 * @param string $status  ok | warn | fail
 * @param string $detail  تفاصيل.
 * @param string $fix     الإجراء المقترح.
 */
function chk(string $name, string $status, string $detail = '', string $fix = ''): void
{
    global $checks;
    $checks[] = compact('name', 'status', 'detail', 'fix');
}

// ── ① قاعدة البيانات ──
$t0 = microtime(true);
try {
    $pdo->query('SELECT 1')->fetchColumn();
    $ms = round((microtime(true) - $t0) * 1000, 1);
    chk('الاتصال بقاعدة البيانات', $ms < 200 ? 'ok' : 'warn', "زمن الاستجابة {$ms} ms",
        $ms >= 200 ? 'زمن مرتفع — راجع حمل الخادم' : '');
} catch (Throwable $e) {
    chk('الاتصال بقاعدة البيانات', 'fail', 'فشل الاتصال', 'راجع بيانات الاتصال في .env');
}

/* ── ② الجداول الأساسية — مع إصلاح ذاتي صامت ──
   جدولا login_logs و blocked_ips يخصّان حماية الدخول (تسجيل
   المحاولات وحظر العناوين). غيابهما يعني أن الحماية معطّلة بصمت.

   بدل الاعتماد على زر يضغطه المستخدم — وقد يفشل لأسباب جلسة أو
   رمز حماية — نُنشئهما هنا تلقائياً. العملية آمنة تماماً:
   CREATE TABLE IF NOT EXISTS لا تمسّ أي بيانات قائمة، وهذه الصفحة
   لا يفتحها إلا مدير مسجَّل الدخول أصلاً. */
$required = ['categories', 'channels', 'series', 'episodes', 'settings', 'admin_users', 'login_logs', 'blocked_ips'];

$selfHeal = [
    'login_logs' => "CREATE TABLE IF NOT EXISTS login_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ip_address VARCHAR(45) NOT NULL,
        username VARCHAR(100),
        status VARCHAR(50) DEFAULT 'failed',
        attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_ip_time (ip_address, attempt_time),
        KEY idx_status_time (status, attempt_time)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'blocked_ips' => "CREATE TABLE IF NOT EXISTS blocked_ips (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ip_address VARCHAR(45) NOT NULL UNIQUE,
        reason VARCHAR(255) DEFAULT 'محاولات دخول فاشلة متكررة',
        blocked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_ip (ip_address)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
];

$missing = [];
$healed  = [];
$noPriv  = false;

foreach ($required as $t) {
    try {
        $pdo->query("SELECT 1 FROM `{$t}` LIMIT 1");
        continue;                      // موجود
    } catch (Throwable $e) {
        // غير موجود — نحاول إنشاءه إن كان ضمن الجداول التي نعرف بنيتها
    }

    if (isset($selfHeal[$t]) && isAdminLoggedIn()) {
        try {
            $pdo->exec($selfHeal[$t]);
            $pdo->query("SELECT 1 FROM `{$t}` LIMIT 1");   // تحقق فعلي
            $healed[] = $t;

            // إبطال علامة التهيئة ليكمل helpers.php الفهارس والأعمدة
            $flag = (defined('CACHE_DIR') ? CACHE_DIR : APP_ROOT . '/storage/cache') . '/.schema_ok';
            if (is_file($flag)) { @unlink($flag); }

        } catch (PDOException $e) {
            $missing[] = $t;
            if ((string) $e->getCode() === '42000') { $noPriv = true; }
            error_log('health self-heal ' . $t . ': ' . $e->getMessage());
        }
    } else {
        $missing[] = $t;
    }
}

if ($missing) {
    chk('الجداول الأساسية', 'fail',
        'مفقودة: ' . implode(', ', $missing),
        $noPriv
            ? 'مستخدم قاعدة البيانات لا يملك صلاحية CREATE — امنحه إياها ثم حدّث الصفحة'
            : 'نفّذ sql/performance_indexes.sql على قاعدة البيانات');
} elseif ($healed) {
    chk('الجداول الأساسية', 'ok',
        'أُنشئت تلقائياً: ' . implode(', ', $healed) . ' — حماية الدخول تعمل الآن',
        'افتح لوحة التحكم مرة واحدة لإكمال بناء الفهارس');
} else {
    chk('الجداول الأساسية', 'ok', count($required) . ' جدول موجود');
}

// ── ③ الفهارس الحرجة ──
try {
    $idx = $pdo->query("SHOW INDEX FROM episodes WHERE Column_name = 'series_id'")->fetchAll();
    chk('فهرس episodes.series_id', $idx ? 'ok' : 'warn',
        $idx ? 'موجود' : 'مفقود — عرض الحلقات يمسح الجدول كاملاً',
        $idx ? '' : 'شغّل sql/performance_indexes.sql');
} catch (Throwable $e) {
    chk('فهرس episodes.series_id', 'warn', 'تعذّر الفحص');
}

// ── ④ السجلات اليتيمة ──
try {
    $orphans = (int) $pdo->query(
        "SELECT COUNT(*) FROM episodes e LEFT JOIN series s ON e.series_id = s.id WHERE s.id IS NULL"
    )->fetchColumn();
    chk('حلقات يتيمة', $orphans === 0 ? 'ok' : 'warn',
        $orphans === 0 ? 'لا يوجد' : "{$orphans} حلقة بمسلسل محذوف",
        $orphans > 0 ? 'شغّل sql/integrity_and_cleanup.sql' : '');
} catch (Throwable $e) {
    chk('حلقات يتيمة', 'warn', 'تعذّر الفحص');
}

// ── ⑤ المجلدات القابلة للكتابة ──
foreach ([
    'storage/cache' => CACHE_DIR,
    'storage/logs'  => LOG_DIR,
    'uploads'       => APP_ROOT . '/uploads',
] as $label => $dir) {
    if (!is_dir($dir)) {
        chk("مجلد {$label}", 'warn', 'غير موجود', 'سيُنشأ تلقائياً عند أول استخدام');
    } elseif (!is_writable($dir)) {
        chk("مجلد {$label}", 'fail', 'غير قابل للكتابة', "نفّذ: chmod 755 {$dir}");
    } else {
        chk("مجلد {$label}", 'ok', 'قابل للكتابة');
    }
}

// ── ⑥ إضافات PHP ──
foreach (['pdo_mysql' => true, 'curl' => true, 'mbstring' => true, 'json' => true,
          'fileinfo' => true, 'zlib' => true, 'gd' => false, 'apcu' => false] as $ext => $req) {
    $has = extension_loaded($ext);
    chk("إضافة {$ext}", $has ? 'ok' : ($req ? 'fail' : 'warn'),
        $has ? 'مثبّتة' : ($req ? 'مفقودة — مطلوبة' : 'غير مثبّتة (اختيارية)'),
        !$has && $req ? "ثبّتها: apt install php-{$ext}" : '');
}

// ── ⑦ إصدار PHP ──
chk('إصدار PHP', version_compare(PHP_VERSION, '8.0', '>=') ? 'ok' : 'warn',
    PHP_VERSION, version_compare(PHP_VERSION, '8.0', '<') ? 'يُنصح بـ 8.1 أو أحدث' : '');

// ── ⑧ HTTPS ──
chk('HTTPS', IS_HTTPS ? 'ok' : 'warn',
    IS_HTTPS ? 'مفعّل' : 'غير مفعّل — كلمات المرور تُرسل بلا تشفير',
    IS_HTTPS ? '' : 'ثبّت شهادة SSL (Let\'s Encrypt مجانية)');

// ── ⑨ ملفات حساسة مكشوفة ──
$exposed = [];
foreach (['license_key.txt', 'database.sql', '.env', 'loader.php', 'setup.php'] as $f) {
    if (is_file(APP_ROOT . '/' . $f)) { $exposed[] = $f; }
}
$hasGuard = is_file(APP_ROOT . '/.htaccess');
chk('ملفات حساسة في الجذر', $exposed ? ($hasGuard ? 'warn' : 'fail') : 'ok',
    $exposed ? implode(', ', $exposed) . ($hasGuard ? ' (محجوبة بالإعدادات)' : ' — غير محجوبة!') : 'لا يوجد',
    $exposed ? 'احذف loader.php و setup.php بعد التثبيت' : '');

// ── ⑩ نسخ المشروع داخل جذر الويب ──
$copies = [];
foreach (['Xp', 'xp', 'server', '_backup_before_fix'] as $d) {
    if (is_dir(APP_ROOT . '/' . $d)) { $copies[] = $d . '/'; }
}
chk('نسخ المشروع داخل الجذر', $copies ? 'warn' : 'ok',
    $copies ? implode(', ', $copies) : 'لا يوجد',
    $copies ? 'انقلها خارج جذر الويب — نسخة كاملة من الكود والإعدادات' : '');

// ── ⑪ كلمة مرور قاعدة البيانات ──
chk('كلمة مرور قاعدة البيانات', DB_PASS === '123456' || DB_PASS === '' ? 'fail' : 'ok',
    DB_PASS === '123456' ? 'الكلمة الافتراضية 123456 مستخدمة!' : (DB_PASS === '' ? 'فارغة!' : 'مخصّصة'),
    DB_PASS === '123456' || DB_PASS === '' ? 'غيّرها فوراً واضبطها في .env' : '');

// ── ⑫ ملف .env ──
chk('ملف .env', is_file(APP_ROOT . '/.env') ? 'ok' : 'warn',
    is_file(APP_ROOT . '/.env') ? 'موجود' : 'غير موجود — تُستخدم القيم الافتراضية',
    is_file(APP_ROOT . '/.env') ? '' : 'انسخ .env.example إلى .env');

// ── ⑬ حماية روابط البث ──
$sp = is_file(APP_ROOT . '/functions/stream_token.php');
if ($sp) { require_once APP_ROOT . '/functions/stream_token.php'; }
chk('حماية روابط البث', $sp && streamProtectionEnabled() ? 'ok' : 'warn',
    $sp ? (streamProtectionEnabled() ? 'مفعّلة — بيانات Xtream محمية' : 'معطّلة من .env') : 'غير مثبّتة',
    $sp && !streamProtectionEnabled() ? 'اضبط PROTECT_STREAM_URLS=1' : '');

/* ── ⑭ التحديث اللحظي (WebSocket) ──
   ملاحظة مهمة: هذه ميزة **اختيارية وغير موصولة** في المشروع حالياً.
   فحصت المشروع كاملاً فوجدت أن broadcast_ws_event لا تُستدعى من أي
   مكان، وأن core/websocket_helper.php لا يُضمَّن في أي ملف. أي أن
   تشغيل خادم Node لن يغيّر شيئاً في سلوك الموقع — لا شيء يرسل
   أحداثاً ولا شيء ينتظرها.
   لذلك لا نعتبر توقّفه عطلاً ما دام معطّلاً صراحةً. */
$wsWanted = (string) env('ENABLE_WEBSOCKET', '0') === '1';

if (!$wsWanted) {
    chk('التحديث اللحظي (WebSocket)', 'ok',
        'معطّل عمداً — الميزة غير موصولة في المشروع ولا يحتاجها الموقع',
        'لتفعيلها لاحقاً: شغّل خادم Node، أضف وسيط /socket.io/ في Apache، ثم ENABLE_WEBSOCKET=1');
} else {
    $wsSecret = (string) env('WS_SECRET', '');
    if ($wsSecret === '') {
        chk('التحديث اللحظي (WebSocket)', 'fail', 'مفعّل لكن WS_SECRET فارغ — كل عمليات البثّ ستُرفض',
            'ولّد مفتاحاً: openssl rand -hex 24 وضعه في .env وفي بيئة Node');
    } else {
        $ch = curl_init('http://' . env('WS_HOST', '127.0.0.1') . ':' . (int) env('WS_PORT', 3000) . '/health');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 2, CURLOPT_CONNECTTIMEOUT => 1]);
        curl_exec($ch);
        $c = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        chk('التحديث اللحظي (WebSocket)', $c === 200 ? 'ok' : 'fail',
            $c === 200 ? 'الخادم يعمل' : 'مفعّل لكن الخادم لا يستجيب',
            $c === 200 ? '' : 'شغّله: cd websocket && npm start — أو أعد ENABLE_WEBSOCKET=0');
    }
}

// ── ⑮ Apache: الوحدات المطلوبة ──
if (function_exists('apache_get_modules')) {
    $mods = apache_get_modules();
    foreach (['mod_rewrite' => true, 'mod_headers' => true,
              'mod_deflate' => false, 'mod_expires' => false] as $m => $req) {
        $has = in_array($m, $mods, true);
        chk("Apache: {$m}", $has ? 'ok' : ($req ? 'fail' : 'warn'),
            $has ? 'مفعّلة' : 'غير مفعّلة',
            $has ? '' : "نفّذ: a2enmod " . str_replace('mod_', '', $m) . " && systemctl reload apache2");
    }
} else {
    chk('وحدات Apache', 'warn', 'تعذّر الفحص (PHP يعمل عبر FPM أو CGI)',
        'تأكد يدوياً: apache2ctl -M | grep -E "rewrite|headers"');
}

// ── ⑯ 🔴 بوابة البث تعمل فعلاً؟ (الفحص الأهم بعد تفعيل الحماية) ──
if (is_file(APP_ROOT . '/functions/stream_token.php')) {
    $baseWeb  = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? ''))), '/');
    $scheme   = IS_HTTPS ? 'https' : 'http';
    $selfHost = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');

    // رمز لرابط وهمي — لا نتصل بأي مزوّد، نختبر البوابة نفسها فقط
    $probeTok = streamTokenMake('http://127.0.0.1/__healthcheck__.m3u8', 60);
    $probeUrl = $scheme . '://' . $selfHost . $baseWeb . '/stream.php/v.m3u8?t=' . rawurlencode($probeTok);

    $ch = curl_init($probeUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,   // نريد رؤية 302 نفسه
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
    ]);
    curl_exec($ch);
    $pc = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($pc === 302) {
        chk('بوابة البث (stream.php)', 'ok', 'تعمل — أعادت توجيهاً صحيحاً (302)');
    } elseif ($pc === 404) {
        chk('بوابة البث (stream.php)', 'fail',
            'خطأ 404 — لن يعمل أي بث!',
            'إعداد PHP لديك لا يدعم PATH_INFO. فعّل mod_rewrite، أو ضع STREAM_URL_STYLE=query في .env');
    } elseif ($pc === 0) {
        chk('بوابة البث (stream.php)', 'warn', 'تعذّر الاتصال بالخادم ذاتياً',
            'قد يكون جدار ناري يمنع الاتصال المحلي — اختبر الرابط يدوياً من المتصفح');
    } else {
        chk('بوابة البث (stream.php)', 'warn', "استجابة غير متوقعة HTTP {$pc}",
            'افتح رابط بث من الموقع وتحقق من التشغيل');
    }
}

// ── ⑰ مساحة القرص ──
$free = @disk_free_space(APP_ROOT);
$tot  = @disk_total_space(APP_ROOT);
if ($free && $tot) {
    $pct = round(($free / $tot) * 100);
    chk('مساحة القرص', $pct > 15 ? 'ok' : ($pct > 5 ? 'warn' : 'fail'),
        round($free / 1073741824, 1) . ' جيجا متاحة (' . $pct . '%)',
        $pct <= 15 ? 'احذف ملفات .rar والنسخ القديمة' : '');
}

// ══════════════════════════════════════════════════════════════
$counts = ['ok' => 0, 'warn' => 0, 'fail' => 0];
foreach ($checks as $c) { $counts[$c['status']]++; }
$overall = $counts['fail'] > 0 ? 'fail' : ($counts['warn'] > 0 ? 'warn' : 'ok');

if ($format === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($overall === 'fail' ? 503 : 200);
    echo json_encode([
        'status'  => $overall,
        'summary' => $counts,
        'checks'  => $checks,
        'time'    => date('c'),
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>فحص صحة النظام — Shashety IPTV</title>
<style>
 *{box-sizing:border-box}
 body{background:#0d0f14;color:#e8ecf3;font-family:Tahoma,Arial,sans-serif;margin:0;padding:26px;line-height:1.7}
 .wrap{max-width:940px;margin:auto}
 h1{font-size:21px;margin:0 0 4px;color:#d4af37}
 .sub{color:#8b94a7;font-size:13px;margin:0 0 20px}
 .sum{display:flex;gap:12px;margin-bottom:22px;flex-wrap:wrap}
 .pill{padding:12px 20px;border-radius:12px;font-weight:700;font-size:14px;flex:1;min-width:120px;text-align:center}
 .pill.ok{background:rgba(46,204,113,.14);color:#2ecc71;border:1px solid rgba(46,204,113,.35)}
 .pill.warn{background:rgba(241,196,15,.14);color:#f1c40f;border:1px solid rgba(241,196,15,.35)}
 .pill.fail{background:rgba(231,76,60,.14);color:#e74c3c;border:1px solid rgba(231,76,60,.35)}
 table{width:100%;border-collapse:collapse;background:#161a23;border-radius:12px;overflow:hidden}
 th,td{padding:11px 14px;text-align:right;border-bottom:1px solid #232a38;font-size:13.5px;vertical-align:top}
 th{background:#1c2230;color:#8b94a7;font-size:12px;font-weight:700}
 tr:last-child td{border-bottom:0}
 .s{font-weight:700;white-space:nowrap}
 .s.ok{color:#2ecc71} .s.warn{color:#f1c40f} .s.fail{color:#e74c3c}
 .fix{color:#8b94a7;font-size:12px;display:block;margin-top:3px}
 a{color:#d4af37;font-size:13px}
 .repair-box{background:rgba(231,76,60,.10);border:1px solid rgba(231,76,60,.4);
   border-radius:12px;padding:18px;margin-bottom:20px}
 .repair-box strong{color:#f09287;display:block;margin-bottom:6px;font-size:14px}
 .repair-box p{color:#8b94a7;font-size:13px;margin:0 0 14px}
 .repair-box button{background:#d4af37;color:#1a1a1a;border:0;padding:11px 26px;
   border-radius:9px;font-weight:700;cursor:pointer;font-family:inherit;font-size:14px}
 .repair-msg{background:rgba(46,204,113,.12);border:1px solid rgba(46,204,113,.4);
   border-radius:10px;padding:14px 16px;margin-bottom:18px;color:#7ee2a8;font-size:14px}
 @media(max-width:640px){ td:nth-child(3),th:nth-child(3){display:none} body{padding:14px} }
</style>
</head>
<body>
<div class="wrap">
  <h1>فحص صحة النظام</h1>
  <p class="sub">
    آخر تحديث: <?= date('Y-m-d H:i:s') ?>
    · <span style="color:#2ecc71">إصدار الملف <?= HEALTH_VERSION ?></span>
    · <a href="admin.php">→ رجوع للوحة</a>
  </p>

  <div class="sum">
    <div class="pill ok">سليم: <?= $counts['ok'] ?></div>
    <div class="pill warn">تحذير: <?= $counts['warn'] ?></div>
    <div class="pill fail">فشل: <?= $counts['fail'] ?></div>
  </div>

  <?php if ($healed): ?>
    <div class="repair-msg">
      ✅ أُصلحت البنية تلقائياً — أُنشئ:
      <?= htmlspecialchars(implode('، ', $healed), ENT_QUOTES, 'UTF-8') ?>.
      حماية الدخول (تسجيل المحاولات وحظر العناوين) تعمل الآن.
    </div>
  <?php endif; ?>

  <?php if ($missing): ?>
    <div class="repair-box">
      <strong>جداول ما زالت مفقودة: <?= htmlspecialchars(implode('، ', $missing), ENT_QUOTES, 'UTF-8') ?></strong>
      <p>
        تعذّر إنشاؤها تلقائياً. الأرجح أن مستخدم قاعدة البيانات
        <code><?= htmlspecialchars(DB_USER, ENT_QUOTES, 'UTF-8') ?></code>
        لا يملك صلاحية <code>CREATE</code>. امنحه إياها ثم حدّث الصفحة:
      </p>
      <pre style="background:#0a0c11;padding:12px;border-radius:8px;direction:ltr;text-align:left;
                  overflow:auto;font-size:12px;color:#c9d3e3;margin:0">GRANT CREATE, ALTER, INDEX ON <?= htmlspecialchars(DB_NAME, ENT_QUOTES, 'UTF-8') ?>.* TO '<?= htmlspecialchars(DB_USER, ENT_QUOTES, 'UTF-8') ?>'@'localhost';
FLUSH PRIVILEGES;</pre>
    </div>
  <?php endif; ?>

  <table>
    <tr><th>الفحص</th><th>الحالة</th><th>التفاصيل</th></tr>
    <?php foreach ($checks as $c): ?>
    <tr>
      <td><?= htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8') ?></td>
      <td class="s <?= $c['status'] ?>">
        <?= $c['status'] === 'ok' ? '✔ سليم' : ($c['status'] === 'warn' ? '⚠ تحذير' : '✘ فشل') ?>
      </td>
      <td>
        <?= htmlspecialchars($c['detail'], ENT_QUOTES, 'UTF-8') ?>
        <?php if ($c['fix'] !== ''): ?>
          <span class="fix">← <?= htmlspecialchars($c['fix'], ENT_QUOTES, 'UTF-8') ?></span>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>
</body>
</html>
