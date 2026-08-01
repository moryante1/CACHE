<?php
/**
 * ملف الإعداد الأولي - Shashety IPTV
 * إنشاء حساب المدير أو إعادة تعيين كلمة المرور.
 *
 * ⚠️  إصلاح أمني حرج (v2):
 *  - النسخة السابقة كانت تُعيد تعيين كلمة مرور المدير إلى كلمة مرور
 *    مكتوبة داخل الملف عند أي زيارة، ودون أي مصادقة. أي شخص يفتح
 *    /setup.php كان يستولي على لوحة التحكم فوراً.
 *  - الآن: لا يعمل الملف إلا (أ) عند عدم وجود أي حساب مدير بعد،
 *    أو (ب) إذا كان الزائر مديراً مسجّل الدخول بالفعل.
 *  - كلمة المرور لم تعد مكتوبة في الكود؛ تُدخل من النموذج أو تُولَّد عشوائياً.
 *  - النموذج محمي بـ CSRF ولا يُنفَّذ إلا عبر POST.
 */

require_once __DIR__ . '/core/config.php';

securityHeaders();

/* ────────────────────────────────────────────────────────────
   1) هل يوجد حساب مدير بالفعل؟
   ──────────────────────────────────────────────────────────── */
function setupAdminCount(PDO $pdo): int
{
    $total = 0;
    foreach (['admin_users', 'admins', 'users'] as $table) {
        try {
            $total += (int) $pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
        } catch (PDOException $e) {
            // الجدول غير موجود في هذا التركيب — نتجاهله
        }
    }
    return $total;
}

$hasAdmin   = setupAdminCount($pdo) > 0;
$isLoggedIn = isAdminLoggedIn();

// قفل التركيب: بعد اكتمال الإعداد لا يعمل الملف إلا لمدير مسجّل الدخول.
$lockFile = APP_ROOT . '/storage/.setup_done';
if ((is_file($lockFile) || $hasAdmin) && !$isLoggedIn) {
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    exit('<!DOCTYPE html><html dir="rtl"><meta charset="utf-8">'
        . '<body style="background:#111;color:#e74c3c;text-align:center;padding:60px;font-family:sans-serif">'
        . '<h2>الإعداد مكتمل بالفعل</h2>'
        . '<p style="color:#888">لإعادة تعيين كلمة المرور سجّل الدخول أولاً، '
        . 'أو احذف الملف <code>storage/.setup_done</code> من الخادم.</p>'
        . '<p style="color:#888">يُنصح بحذف <code>setup.php</code> بعد انتهاء التركيب.</p>'
        . '</body></html>');
}

/* ────────────────────────────────────────────────────────────
   2) تنفيذ الإعداد (POST فقط + CSRF)
   ──────────────────────────────────────────────────────────── */
$message       = '';
$success       = null;
$shownPassword = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {

    if (!csrfValidate()) {
        $success = false;
        $message = '❌ انتهت صلاحية الجلسة. أعد تحميل الصفحة وحاول مرة أخرى.';
    } else {
        $username = trim((string) ($_POST['username'] ?? 'admin'));
        $email    = trim((string) ($_POST['email'] ?? 'admin@shashety.tv'));
        $password = (string) ($_POST['password'] ?? '');

        // توليد كلمة مرور قوية إن تُركت فارغة
        $generated = false;
        if ($password === '') {
            $password  = bin2hex(random_bytes(9)); // 18 حرفاً
            $generated = true;
        }

        if (!preg_match('/^[A-Za-z0-9._-]{3,50}$/', $username)) {
            $success = false;
            $message = '❌ اسم المستخدم غير صالح (أحرف/أرقام و . _ - فقط، 3–50 حرفاً).';
        } elseif (strlen($password) < 10) {
            $success = false;
            $message = '❌ كلمة المرور قصيرة — 10 أحرف على الأقل.';
        } elseif ($email !== '' && !validateEmail($email)) {
            $success = false;
            $message = '❌ البريد الإلكتروني غير صالح.';
        } else {
            // كلفة 12 أقوى من الافتراضي وتُبطئ محاولات التخمين
            $hash = password_hash($password, PASSWORD_DEFAULT, ['cost' => 12]);

            try {
                $pdo->exec("CREATE TABLE IF NOT EXISTS admin_users (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    username VARCHAR(50) NOT NULL UNIQUE,
                    password_hash VARCHAR(255) NOT NULL,
                    email VARCHAR(190) NULL,
                    role VARCHAR(30) NOT NULL DEFAULT 'administrator',
                    allowed_sections TEXT NULL,
                    is_active TINYINT(1) NOT NULL DEFAULT 1,
                    last_login DATETIME NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

                $check = $pdo->prepare("SELECT id FROM admin_users WHERE username = ? LIMIT 1");
                $check->execute([$username]);
                $existing = $check->fetchColumn();

                if ($existing) {
                    $pdo->prepare(
                        "UPDATE admin_users SET password_hash = ?, email = ?, is_active = 1 WHERE id = ?"
                    )->execute([$hash, $email, $existing]);
                    $message = '✅ تم تحديث كلمة المرور بنجاح!';
                } else {
                    $pdo->prepare(
                        "INSERT INTO admin_users (username, password_hash, email, role, allowed_sections, is_active)
                         VALUES (?, ?, ?, 'administrator', '[]', 1)"
                    )->execute([$username, $hash, $email]);
                    $message = '✅ تم إنشاء حساب المدير بنجاح!';
                }

                $success = true;
                if ($generated) {
                    $shownPassword = $password; // تُعرض مرة واحدة فقط
                }

                // إنشاء قفل التركيب حتى لا يُستغل الملف لاحقاً
                if (!is_dir(dirname($lockFile))) {
                    @mkdir(dirname($lockFile), 0750, true);
                }
                @file_put_contents($lockFile, date('c'), LOCK_EX);

                logActivity('setup: تعيين حساب مدير', 'user=' . $username);

            } catch (PDOException $e) {
                // لا نعرض رسالة قاعدة البيانات للمستخدم (تسريب معلومات)
                error_log('setup.php: ' . $e->getMessage());
                $success = false;
                $message = '❌ تعذّر إتمام العملية. راجع سجل أخطاء الخادم.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>إعداد النظام - Shashety IPTV</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; font-family:'Cairo', sans-serif; }
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh; display:flex; align-items:center; justify-content:center; padding:20px;
        }
        .box {
            background:#fff; border-radius:18px; padding:38px; max-width:520px; width:100%;
            box-shadow:0 20px 60px rgba(0,0,0,.3);
        }
        h1 { font-size:22px; color:#2d3748; margin-bottom:6px; text-align:center; }
        .sub { color:#718096; font-size:13px; text-align:center; margin-bottom:26px; }
        label { display:block; font-size:13px; color:#4a5568; margin:14px 0 6px; font-weight:600; }
        input {
            width:100%; padding:12px 14px; border:1px solid #e2e8f0; border-radius:10px;
            font-size:14px; direction:ltr; text-align:left;
        }
        input:focus { outline:none; border-color:#667eea; }
        .hint { font-size:12px; color:#a0aec0; margin-top:5px; }
        button {
            width:100%; margin-top:24px; padding:13px; border:0; border-radius:10px; cursor:pointer;
            background:linear-gradient(135deg,#667eea,#764ba2); color:#fff; font-size:15px; font-weight:700;
        }
        .msg { padding:14px 16px; border-radius:10px; margin-bottom:20px; font-size:14px; }
        .msg.ok  { background:#f0fff4; color:#22543d; border:1px solid #9ae6b4; }
        .msg.err { background:#fff5f5; color:#742a2a; border:1px solid #feb2b2; }
        .cred {
            background:#1a202c; color:#68d391; padding:14px; border-radius:10px; margin-top:14px;
            font-family:monospace; direction:ltr; text-align:left; font-size:14px; word-break:break-all;
        }
        .warn {
            margin-top:22px; padding:13px 15px; background:#fffaf0; border:1px solid #fbd38d;
            border-radius:10px; color:#7b341e; font-size:13px; line-height:1.7;
        }
        a.go { display:block; text-align:center; margin-top:18px; color:#667eea; font-size:14px; }
    </style>
</head>
<body>
<div class="box">
    <h1>إعداد النظام</h1>
    <p class="sub">Shashety IPTV — تهيئة حساب المدير</p>

    <?php if ($message !== ''): ?>
        <div class="msg <?= $success ? 'ok' : 'err' ?>"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
        <?php if ($shownPassword !== ''): ?>
            <div class="cred">
                كلمة المرور المولَّدة (احفظها الآن — لن تُعرض ثانية):<br><br>
                <strong><?= htmlspecialchars($shownPassword, ENT_QUOTES, 'UTF-8') ?></strong>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($success !== true): ?>
    <form method="post" autocomplete="off">
        <?= csrfField() ?>
        <label>اسم المستخدم</label>
        <input type="text" name="username" value="admin" required pattern="[A-Za-z0-9._\-]{3,50}">

        <label>البريد الإلكتروني</label>
        <input type="email" name="email" value="admin@shashety.tv">

        <label>كلمة المرور</label>
        <input type="password" name="password" minlength="10" autocomplete="new-password">
        <p class="hint">10 أحرف على الأقل. اتركها فارغة لتوليد كلمة مرور قوية تلقائياً.</p>

        <button type="submit">حفظ الإعدادات</button>
    </form>
    <?php else: ?>
        <a class="go" href="login.php">→ الانتقال إلى صفحة الدخول</a>
    <?php endif; ?>

    <div class="warn">
        <strong>مهم:</strong> بعد انتهاء التركيب احذف الملف <code>setup.php</code> من الخادم،
        أو أبقِ الملف <code>storage/.setup_done</code> موجوداً. الملف الآن مقفل تلقائياً
        بعد أول استخدام ولا يعمل إلا لمدير مسجّل الدخول.
    </div>
</div>
</body>
</html>
