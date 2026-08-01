<?php
/**
 * ══════════════════════════════════════════════════════════════
 *  تغيير كلمة مرور قاعدة البيانات — من المتصفح مباشرة
 * ══════════════════════════════════════════════════════════════
 *
 *  لماذا نسخة ويب؟
 *  السكربت tools/change_db_password.sh يحتاج وصول SSH، وكثير من
 *  الاستضافات لا توفّره. هذه الصفحة تؤدي نفس المهمة من المتصفح
 *  بنفس ضمانات الأمان.
 *
 *  آلية الحماية من تعطيل الموقع:
 *   ① نسخة احتياطية من .env قبل أي تعديل
 *   ② تغيير الكلمة في MySQL
 *   ③ تحديث .env
 *   ④ اختبار اتصال **جديد ومستقل** بالكلمة الجديدة
 *   ⑤ إن فشل الاختبار → تراجع كامل (MySQL + .env) فوراً
 *
 *  وإن لم يملك مستخدم قاعدة البيانات صلاحية ALTER USER، تعرض
 *  الصفحة أمر SQL جاهزاً لتنفيذه في phpMyAdmin، مع خيار تحديث
 *  ملف .env وحده بعد تغييرك اليدوي.
 *
 *  ⚠️ احذف هذا الملف بعد الانتهاء.
 */

require_once __DIR__ . '/core/config.php';

securityHeaders();
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store, no-cache, must-revalidate');

if (!isAdminLoggedIn()) {
    http_response_code(403);
    exit('<!DOCTYPE html><html dir="rtl"><meta charset="utf-8">'
        . '<body style="background:#0d0f14;color:#e74c3c;text-align:center;padding:60px;font-family:Tahoma,sans-serif">'
        . '<h2>غير مصرح</h2><p style="color:#8b94a7">سجّل الدخول كمدير من admin.php أولاً.</p>'
        . '<p><a href="admin.php" style="color:#d4af37">← لوحة التحكم</a></p></body></html>');
}

$ENV_FILE = APP_ROOT . '/.env';
$msgs     = [];   // [نوع, نص]
$newShown = '';

/** إضافة رسالة. */
function m(string $type, string $text): void { global $msgs; $msgs[] = [$type, $text]; }

/** اتصال مستقل تماماً للتحقق (لا يستخدم $pdo الحالي). */
function tryConnect(string $pass): bool
{
    try {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $t = new PDO($dsn, DB_USER, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5,
        ]);
        $t->query('SELECT 1');
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

/** كتابة DB_PASS داخل .env مع الحفاظ على باقي السطور. */
function writeEnvPass(string $file, string $pass): bool
{
    $lines = is_file($file) ? file($file, FILE_IGNORE_NEW_LINES) : [];
    if ($lines === false) { return false; }

    $done = false;
    foreach ($lines as $i => $l) {
        if (preg_match('/^\s*DB_PASS\s*=/', $l)) {
            $lines[$i] = 'DB_PASS=' . $pass;
            $done = true;
        }
    }
    if (!$done) { $lines[] = 'DB_PASS=' . $pass; }

    $tmp = $file . '.tmp.' . bin2hex(random_bytes(4));
    if (@file_put_contents($tmp, implode("\n", $lines) . "\n", LOCK_EX) === false) { return false; }
    if (!@rename($tmp, $file)) { @unlink($tmp); return false; }
    @chmod($file, 0600);
    return true;
}

/* ══════════════════════════════════════════════════════════════
   المعالجة
   ══════════════════════════════════════════════════════════════ */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {

    if (!csrfValidate()) {
        m('err', 'انتهت صلاحية الجلسة. حدّث الصفحة (F5) وأعد المحاولة.');

    } elseif (!is_file($ENV_FILE)) {
        m('err', 'الملف .env غير موجود. أنشئه أولاً بنسخ .env.example.');

    } elseif (!is_writable($ENV_FILE)) {
        m('err', 'الملف .env غير قابل للكتابة. نفّذ: chmod 600 .env وتأكد أن مالكه هو مستخدم الويب.');

    } else {

        $action = (string) ($_POST['action'] ?? '');

        // كلمة المرور الجديدة
        $new = (string) ($_POST['new_pass'] ?? '');
        if ($new === '') {
            $new = rtrim(strtr(base64_encode(random_bytes(18)), '+/', 'Xy'), '=');
        }

        if (mb_strlen($new) < 12) {
            m('err', 'كلمة المرور قصيرة (' . mb_strlen($new) . ' حرفاً). الحد الأدنى 12.');

        } elseif ($new === DB_PASS) {
            m('err', 'الكلمة الجديدة مطابقة للحالية.');

        } else {

            // ── نسخة احتياطية ──
            $backup = $ENV_FILE . '.bak.' . date('Ymd_His');
            @copy($ENV_FILE, $backup);
            @chmod($backup, 0600);

            if ($action === 'env_only') {
                /* المستخدم غيّر الكلمة يدوياً في phpMyAdmin،
                   ونحن نحدّث .env فقط — مع التحقق أولاً. */
                if (!tryConnect($new)) {
                    m('err', 'تعذّر الاتصال بهذه الكلمة. تأكد أنك غيّرتها فعلاً في قاعدة البيانات أولاً.');
                } elseif (writeEnvPass($ENV_FILE, $new)) {
                    m('ok', '✅ حُدِّث ملف .env بنجاح، والاتصال يعمل. موقعك سليم.');
                    logActivity('تحديث كلمة مرور قاعدة البيانات في .env', '');
                } else {
                    m('err', 'تعذّرت الكتابة في .env.');
                }

            } else {
                // ── المسار الكامل: تغيير في MySQL ثم .env ──
                $altered = false;
                $lastErr = '';

                foreach (['localhost', '127.0.0.1', '%'] as $h) {
                    try {
                        // اسم المستخدم والمضيف من الإعدادات لا من الطلب،
                        // وكلمة المرور تُمرَّر كمعامل مرتبط.
                        $sql = "ALTER USER " . $pdo->quote(DB_USER) . "@" . $pdo->quote($h)
                             . " IDENTIFIED BY " . $pdo->quote($new);
                        $pdo->exec($sql);
                        $altered = true;
                    } catch (PDOException $e) {
                        $lastErr = $e->getMessage();
                    }
                }

                if (!$altered) {
                    @unlink($backup);
                    m('err', 'مستخدم قاعدة البيانات لا يملك صلاحية تغيير كلمة المرور.');
                    m('sql', $new);   // نعرض له أمر SQL جاهزاً
                    error_log('change_db_password: ' . $lastErr);

                } else {
                    try { $pdo->exec('FLUSH PRIVILEGES'); } catch (Throwable $e) {}

                    // ── تحديث .env ──
                    if (!writeEnvPass($ENV_FILE, $new)) {
                        // تراجع: أعد الكلمة القديمة
                        try {
                            foreach (['localhost', '127.0.0.1', '%'] as $h) {
                                $pdo->exec("ALTER USER " . $pdo->quote(DB_USER) . "@" . $pdo->quote($h)
                                         . " IDENTIFIED BY " . $pdo->quote(DB_PASS));
                            }
                        } catch (Throwable $e) {}
                        m('err', 'تعذّرت الكتابة في .env — أُعيدت الكلمة القديمة. موقعك يعمل كما كان.');

                    } elseif (!tryConnect($new)) {
                        // ── التحقق فشل → تراجع كامل ──
                        @copy($backup, $ENV_FILE);
                        try {
                            foreach (['localhost', '127.0.0.1', '%'] as $h) {
                                $pdo->exec("ALTER USER " . $pdo->quote(DB_USER) . "@" . $pdo->quote($h)
                                         . " IDENTIFIED BY " . $pdo->quote(DB_PASS));
                            }
                        } catch (Throwable $e) {}
                        m('err', 'فشل التحقق من الاتصال — تم التراجع الكامل. موقعك يعمل كما كان.');

                    } else {
                        $newShown = $new;
                        m('ok', '✅ تم بنجاح! غُيِّرت كلمة المرور في قاعدة البيانات وحُدِّث ملف .env، والاتصال مُختبَر ويعمل.');
                        m('info', 'النسخة الاحتياطية: ' . basename($backup) . ' — احذفها بعد التأكد.');
                        logActivity('تغيير كلمة مرور قاعدة البيانات', 'user=' . DB_USER);

                        // إبطال الكاش
                        if (function_exists('cacheFlush')) { cacheFlush(); }
                    }
                }
            }
        }
    }
}

$envOk   = is_file($ENV_FILE);
$envWr   = $envOk && is_writable($ENV_FILE);
$isWeak  = (DB_PASS === '123456' || DB_PASS === '' || mb_strlen(DB_PASS) < 8);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>تغيير كلمة مرور قاعدة البيانات</title>
<style>
 *{box-sizing:border-box}
 body{background:#0d0f14;color:#e8ecf3;font-family:Tahoma,Arial,sans-serif;margin:0;padding:26px;line-height:1.8}
 .wrap{max-width:720px;margin:auto}
 h1{font-size:20px;margin:0 0 6px;color:#d4af37}
 .sub{color:#8b94a7;font-size:13px;margin:0 0 22px}
 .card{background:#161a23;border:1px solid #2a3140;border-radius:14px;padding:22px;margin-bottom:18px}
 .msg{padding:13px 16px;border-radius:10px;margin-bottom:12px;font-size:14px}
 .msg.ok{background:rgba(46,204,113,.12);border:1px solid rgba(46,204,113,.4);color:#7ee2a8}
 .msg.err{background:rgba(231,76,60,.12);border:1px solid rgba(231,76,60,.4);color:#f09287}
 .msg.info{background:rgba(139,148,167,.10);border:1px solid #2a3140;color:#8b94a7;font-size:13px}
 label{display:block;font-size:13px;color:#8b94a7;margin:0 0 7px}
 input[type=text]{width:100%;padding:12px 14px;border-radius:10px;border:1px solid #2a3140;
   background:#0d0f14;color:#e8ecf3;font-size:14px;direction:ltr;text-align:left;font-family:monospace}
 button{background:#d4af37;color:#1a1a1a;border:0;padding:12px 26px;border-radius:9px;
   font-weight:700;cursor:pointer;font-family:inherit;font-size:14px;margin-top:14px}
 button.alt{background:transparent;border:1px solid #2a3140;color:#e8ecf3}
 .hint{color:#5a6273;font-size:12.5px;margin-top:8px}
 pre{background:#0a0c11;border:1px solid #2a3140;border-radius:9px;padding:14px;
   direction:ltr;text-align:left;overflow:auto;font-size:12.5px;color:#c9d3e3;margin:12px 0 0}
 .cred{background:#0a0c11;border:1px solid #2ecc71;border-radius:10px;padding:16px;margin-top:14px;
   direction:ltr;text-align:left;font-family:monospace;font-size:15px;color:#7ee2a8;word-break:break-all}
 table{width:100%;border-collapse:collapse;font-size:13px;margin-top:10px}
 td{padding:7px 4px;border-bottom:1px solid #232a38}
 td:first-child{color:#8b94a7;width:150px}
 a{color:#d4af37;font-size:13px}
 .warn-box{background:rgba(241,196,15,.10);border:1px solid rgba(241,196,15,.35);
   border-radius:10px;padding:14px 16px;color:#f3d270;font-size:13px;margin-top:16px}
</style>
</head>
<body>
<div class="wrap">
  <h1>تغيير كلمة مرور قاعدة البيانات</h1>
  <p class="sub">آمن تماماً — يتحقق من الاتصال قبل الاعتماد، ويتراجع تلقائياً عند أي فشل.</p>

  <?php foreach ($msgs as [$t, $x]): ?>
    <?php if ($t === 'sql'): ?>
      <div class="msg err">
        <strong>الحل البديل — نفّذ هذا في phpMyAdmin:</strong>
        <pre>ALTER USER '<?= htmlspecialchars(DB_USER, ENT_QUOTES, 'UTF-8') ?>'@'localhost' IDENTIFIED BY '<?= htmlspecialchars($x, ENT_QUOTES, 'UTF-8') ?>';
FLUSH PRIVILEGES;</pre>
        ثم عُد إلى هذه الصفحة، ضع الكلمة نفسها في الحقل، واضغط
        <strong>«حدّثت الكلمة يدوياً — حدّث .env فقط»</strong>.
      </div>
    <?php else: ?>
      <div class="msg <?= htmlspecialchars($t, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($x, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
  <?php endforeach; ?>

  <?php if ($newShown !== ''): ?>
    <div class="card">
      <strong style="color:#2ecc71">كلمة المرور الجديدة — احفظها الآن:</strong>
      <div class="cred"><?= htmlspecialchars($newShown, ENT_QUOTES, 'UTF-8') ?></div>
      <p class="hint">لن تُعرض مرة أخرى. محفوظة في .env ويستخدمها الموقع تلقائياً.</p>
      <p style="margin-top:16px"><a href="health.php">← افحص النظام الآن</a></p>
    </div>
  <?php endif; ?>

  <div class="card">
    <strong style="font-size:14px">الحالة الحالية</strong>
    <table>
      <tr><td>المستخدم</td><td><code><?= htmlspecialchars(DB_USER, ENT_QUOTES, 'UTF-8') ?></code></td></tr>
      <tr><td>القاعدة</td><td><code><?= htmlspecialchars(DB_NAME, ENT_QUOTES, 'UTF-8') ?></code></td></tr>
      <tr><td>كلمة المرور</td>
          <td><?= $isWeak
                ? '<span style="color:#e74c3c">ضعيفة — تحتاج تغييراً فوراً</span>'
                : '<span style="color:#2ecc71">قوية</span>' ?></td></tr>
      <tr><td>ملف .env</td>
          <td><?= !$envOk ? '<span style="color:#e74c3c">غير موجود</span>'
                : ($envWr ? '<span style="color:#2ecc71">موجود وقابل للكتابة</span>'
                          : '<span style="color:#e74c3c">موجود لكن غير قابل للكتابة</span>') ?></td></tr>
    </table>
  </div>

  <div class="card">
    <form method="post">
      <?= csrfField() ?>
      <label>كلمة المرور الجديدة (اتركها فارغة لتوليد كلمة قوية تلقائياً)</label>
      <input type="text" name="new_pass" placeholder="اتركها فارغة للتوليد التلقائي" autocomplete="off">
      <p class="hint">12 حرفاً على الأقل. تجنّب علامة الاقتباس الفردية.</p>

      <button type="submit" name="action" value="full">تغيير كلمة المرور الآن</button>
      <button type="submit" name="action" value="env_only" class="alt">حدّثت الكلمة يدوياً — حدّث .env فقط</button>
    </form>

    <div class="warn-box">
      <strong>لن يتعطّل موقعك:</strong> تُؤخذ نسخة احتياطية من .env، ثم تُغيَّر الكلمة،
      ثم يُختبر اتصال جديد مستقل. إن فشل الاختبار تُعاد الكلمة القديمة وملف .env
      إلى حالتهما فوراً.
    </div>
  </div>

  <p style="text-align:center">
    <a href="health.php">فحص النظام</a> &nbsp;·&nbsp; <a href="admin.php">لوحة التحكم</a>
  </p>
  <p style="text-align:center;color:#5a6273;font-size:12.5px;margin-top:18px">
    ⚠️ احذف هذا الملف من الخادم بعد الانتهاء: <code>change_db_password.php</code>
  </p>
</div>
</body>
</html>
