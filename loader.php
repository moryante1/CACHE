<?php
/**
 * ══════════════════════════════════════════════════════════════
 *  معالج تثبيت قاعدة البيانات — Shashety IPTV
 * ══════════════════════════════════════════════════════════════
 *
 *  🔴 أخطر ثغرة في المشروع كله كانت في هذا الملف — أُصلحت
 *  ──────────────────────────────────────────────────────────────
 *
 *  ① لا مصادقة إطلاقاً: لم يكن في الملف أي فحص لتسجيل الدخول.
 *
 *  ② فحص Referer كان يُطبَّق على GET فقط:
 *         if ($_SERVER['REQUEST_METHOD'] === 'GET') { ...فحص... }
 *     أي أن طلبات POST — وهي الطلبات التي تنفّذ الملف فعلياً —
 *     كانت **تتخطّى الفحص بالكامل**.
 *
 *  ③ الملف المرفوع كان يُنفَّذ مباشرة على قاعدة البيانات:
 *         $conn->multi_query($sql);
 *     أي **تنفيذ SQL عشوائي** لأي شخص على الإنترنت.
 *
 *  الهجوم كان بهذه البساطة: يرسل المهاجم POST بملف .sql إلى
 *  /loader.php فينفّذ ما يشاء — إنشاء حساب مدير لنفسه، أو حذف
 *  قاعدة البيانات كاملة، أو سحب بياناتك. دون تسجيل دخول ولا رمز.
 *
 *  ④ بيانات اعتماد قاعدة البيانات كانت مكتوبة داخل الكود بنص صريح
 *     (المستخدم iptv_user وكلمة المرور 123456). أي شخص يقرأ الملف —
 *     من نسخة احتياطية أو خطأ في الخادم — يحصل عليها. الآن تُقرأ من
 *     الإعدادات المركزية / ملف .env.
 *
 *  الحماية الآن:
 *   • مدير مسجَّل الدخول، أو تركيب أوّل لم يُنشأ فيه أي حساب بعد.
 *   • قفل تثبيت دائم بعد أول استخدام (storage/.installed).
 *   • POST فقط + رمز CSRF.
 *   • يُنصح بحذف هذا الملف من الخادم بعد انتهاء التثبيت.
 */

require_once __DIR__ . '/core/config.php';

securityHeaders();
header('X-Robots-Tag: noindex, nofollow');

$__lockFile = APP_ROOT . '/storage/.installed';

/** هل يوجد حساب مدير بالفعل؟ (تركيب مكتمل) */
function loaderHasAdmin(PDO $pdo): bool
{
    foreach (['admin_users', 'admins', 'users'] as $t) {
        try {
            if ((int) $pdo->query("SELECT COUNT(*) FROM `{$t}`")->fetchColumn() > 0) {
                return true;
            }
        } catch (PDOException $e) {
            // الجدول غير موجود — تركيب جديد
        }
    }
    return false;
}

$__installed = is_file($__lockFile);
try { $__installed = $__installed || loaderHasAdmin($pdo); } catch (Throwable $e) {}

// بعد اكتمال التثبيت: مدير مسجَّل الدخول فقط
if ($__installed && !isAdminLoggedIn()) {
    http_response_code(403);
    exit('<!DOCTYPE html><html dir="rtl"><meta charset="utf-8">'
        . '<body style="background:#111;color:#e74c3c;text-align:center;padding:60px;font-family:sans-serif">'
        . '<h2>التثبيت مكتمل</h2>'
        . '<p style="color:#888">هذه الأداة تنفّذ أوامر SQL على قاعدة بياناتك، '
        . 'ولا تعمل إلا لمدير مسجّل الدخول.</p>'
        . '<p style="color:#888">يُنصح بحذف الملف <code>loader.php</code> من الخادم بعد انتهاء التثبيت.</p>'
        . '</body></html>');
}

// إخفاء الأخطاء في مسارات السيرفر
error_reporting(E_ALL);
ini_set('display_errors', 0);

$message = "";
$messageType = "";
$isSuccess = false;

// بيانات الاتصال من الإعدادات المركزية (.env) لا من داخل الكود
$db_host = DB_HOST;
$db_user = DB_USER;
$db_pass = DB_PASS;
$db_name = DB_NAME;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // رمز CSRF إجباري — الإجراء يعيد بناء قاعدة البيانات
        if (!csrfValidate()) {
            throw new Exception("انتهت صلاحية الجلسة. أعد تحميل الصفحة وحاول مجدداً.");
        }

        // فك قيد الوقت لرفع القواعد الثقيلة
        set_time_limit(0);

        // التأكد من أن السيرفر لم يرفض الملف بسبب حجمه
        if (empty($_FILES) && empty($_POST) && isset($_SERVER['CONTENT_LENGTH']) && $_SERVER['CONTENT_LENGTH'] > 0) {
            throw new Exception("الملف الذي تحاول رفعه يتجاوز الحد المسموح به في إعدادات السيرفر.");
        }

        if (!isset($_FILES['sql_file']) || $_FILES['sql_file']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("الرجاء تحديد ملف .sql بشكل صحيح للبدء.");
        }

        if (!is_uploaded_file($_FILES['sql_file']['tmp_name'])) {
            throw new Exception("ملف غير صالح.");
        }

        $fileExtension = strtolower(pathinfo($_FILES['sql_file']['name'], PATHINFO_EXTENSION));
        if ($fileExtension !== 'sql') {
            throw new Exception("امتداد غير صحيح! يُسمح فقط برفع ملفات تنتهي بـ .sql");
        }

        $sql = file_get_contents($_FILES['sql_file']['tmp_name']);
        if (empty(trim($sql))) {
            throw new Exception("ملف قاعدة البيانات فارغ!");
        }

        mysqli_report(MYSQLI_REPORT_OFF);

        $conn = new mysqli($db_host, $db_user, $db_pass);
        if ($conn->connect_error) {
            throw new Exception("فشل الاتصال: يرجى التأكد من بيانات الاتصال في ملف .env");
        }
        $conn->set_charset('utf8mb4');

        // إنشاء وتحديد القاعدة
        if (!$conn->query("CREATE DATABASE IF NOT EXISTS `" . str_replace('`', '', $db_name) . "` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci")) {
            throw new Exception("تعذر إنشاء القاعدة: " . $conn->error);
        }
        $conn->select_db($db_name);

        // تنفيذ كود القاعدة
        if ($conn->multi_query($sql)) {
            do {
                if ($result = $conn->store_result()) {
                    $result->free();
                }
                if ($conn->errno) {
                    throw new Exception("يوجد خطأ داخل أكواد الملف المرفوع: " . $conn->error);
                }
            } while ($conn->more_results() && $conn->next_result());
        } else {
            throw new Exception("لم يتم استيعاب ملف القاعدة: " . $conn->error);
        }

        $isSuccess = true;
        $conn->close();

        // قفل التثبيت + إبطال الكاش وعلامات المخطط
        if (!is_dir(dirname($__lockFile))) { @mkdir(dirname($__lockFile), 0750, true); }
        @file_put_contents($__lockFile, date('c'), LOCK_EX);
        if (function_exists('cacheFlush')) { cacheFlush(); }
        foreach (['/.schema_ok', '/.settings_table_ok'] as $f) {
            $p = (defined('CACHE_DIR') ? CACHE_DIR : APP_ROOT . '/storage/cache') . $f;
            if (is_file($p)) { @unlink($p); }
        }

        logActivity('loader: تثبيت/استعادة قاعدة البيانات', '');

    } catch (Exception $e) {
        $messageType = "error";
        $message = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IPTV Setup | معالج التثبيت</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;800&display=swap');
        
        :root {
            --netflix-red: #E50914;
            --netflix-hover: #F40612;
            --dark-bg: #141414;
            --card-bg: rgba(0, 0, 0, 0.85);
            --text-muted: #B3B3B3;
            --input-bg: #333333;
        }

        * { box-sizing: border-box; font-family: 'Cairo', sans-serif; margin: 0; padding: 0; }

        body {
            background-color: var(--dark-bg);
            background-image: radial-gradient(circle at center, rgba(40,40,40,0.5) 0%, rgba(0,0,0,1) 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }

        .container {
            width: 100%; max-width: 500px;
            background: var(--card-bg);
            padding: 50px 40px; border-radius: 12px;
            box-shadow: 0 15px 50px rgba(0,0,0,0.8);
            border-top: 3px solid var(--netflix-red);
            animation: fadeIn 0.5s ease;
        }

        @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }

        .brand-title { text-align: center; font-size: 30px; font-weight: 800; margin-bottom: 25px; }

        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 30px; }
        
        .info-box { background: rgba(255, 255, 255, 0.05); border-radius: 8px; padding: 15px; border: 1px solid rgba(255, 255, 255, 0.1); }
        .info-label { font-size: 12px; color: var(--text-muted); margin-bottom: 5px; display: block; }
        .info-value { font-size: 16px; font-weight: 600; color: #fff; direction: ltr; text-align: right; }
        .pass-value { color: var(--netflix-red); letter-spacing: 5px; font-size: 18px;}

        .file-upload-wrapper { position: relative; background: var(--input-bg); border: 2px dashed rgba(255,255,255,0.2); border-radius: 8px; padding: 30px; text-align: center; transition: 0.3s ease; cursor: pointer; margin-bottom: 30px; }
        .file-upload-wrapper:hover, .file-upload-wrapper.selected { border-color: var(--netflix-red); background: rgba(229, 9, 20, 0.05); }
        .file-upload-wrapper input[type="file"] { position: absolute; left: 0; top: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; }
        .file-name { font-size: 16px; color: var(--text-muted); pointer-events: none; }

        .btn-install { width: 100%; background-color: var(--netflix-red); color: white; font-size: 18px; font-weight: 800; padding: 16px; border: none; border-radius: 6px; cursor: pointer; transition: 0.3s ease; text-decoration: none; display: inline-block; text-align: center; }
        .btn-install:hover { background-color: var(--netflix-hover); transform: translateY(-2px); }
        .btn-install:disabled { background-color: #555; cursor: wait; transform: none; }

        .alert-error { background-color: rgba(232, 124, 3, 0.1); color: #e87c03; border: 1px solid #e87c03; padding: 15px; border-radius: 6px; margin-bottom: 25px; text-align: center; font-weight: 600; font-size: 14px; }

        .success-state { text-align: center; padding: 30px 0; }
        .success-state h2 { color: #46d369; margin-bottom: 15px; font-size: 26px; }
        .success-state p { color: var(--text-muted); font-size: 15px; margin-bottom: 20px; }
        
        .loader {
            border: 4px solid rgba(255, 255, 255, 0.1);
            border-left-color: var(--netflix-red);
            border-radius: 50%; width: 50px; height: 50px;
            animation: spin 1s linear infinite; margin: 20px auto;
        }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        .redirect-text { color: white; font-size: 15px; }
        .fallback-btn { margin-top: 25px; }
    </style>
</head>
<body>

<div class="container">
    
    <?php if (!$isSuccess): ?>
        
        <div class="brand-title">⚙️ إدارة قواعد IPTV</div>

        <?php if ($message): ?>
            <div class="alert-error">⚠️ <?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <div class="info-grid">
            <div class="info-box"><span class="info-label">المضيف</span><span class="info-value"><?= htmlspecialchars($db_host) ?></span></div>
            <div class="info-box"><span class="info-label">القاعدة</span><span class="info-value"><?= htmlspecialchars($db_name) ?></span></div>
            <div class="info-box"><span class="info-label">المستخدم</span><span class="info-value"><?= htmlspecialchars($db_user) ?></span></div>
            <div class="info-box"><span class="info-label">المرور</span><span class="info-value pass-value">••••••</span></div>
        </div>

        <form method="post" enctype="multipart/form-data" id="setupForm" onsubmit="return submitForm()">
            <?= csrfField() ?>
            <div class="file-upload-wrapper" id="fileWrapper">
                <input type="file" name="sql_file" id="sql_file" accept=".sql" required onchange="updateFileName()">
                <div class="file-name" id="fileNameDisplay">
                    <span style="font-size: 30px; display: block; margin-bottom: 10px;">📥</span>
                    اسحب ملف القاعدة أو انقر للرفع (.sql)
                </div>
            </div>
            <button type="submit" class="btn-install" id="submitBtn">البدء بالرفع وتحديث القاعدة</button>
        </form>

    <?php else: ?>
        
        <div class="success-state">
            <h2>✔️ اكتمل التحديث بنجاح</h2>
            <p>تم إدراج الجداول والبيانات بالكامل في القاعدة.</p>
            <div class="loader" id="loader"></div>
            <div class="redirect-text" id="redirectText">جاري العودة للوحة التحكم <b>admin.php</b>...</div>
            <div class="fallback-btn" style="display: none;" id="fallbackBtn">
                <a href="admin.php" class="btn-install">العودة للوحة التحكم 🔙</a>
            </div>
        </div>

        <script>
            setTimeout(function() { window.location.replace('admin.php'); }, 3000);
            
            setTimeout(function() {
                document.getElementById('redirectText').style.display = 'none';
                document.getElementById('loader').style.display = 'none';
                document.getElementById('fallbackBtn').style.display = 'block';
            }, 3500);
        </script>

    <?php endif; ?>

</div>

<script>
    function updateFileName() {
        const fileInput = document.getElementById('sql_file');
        const display = document.getElementById('fileNameDisplay');
        const wrapper = document.getElementById('fileWrapper');

        if (fileInput.files.length > 0) {
            let fname = fileInput.files[0].name;
            if(fname.length > 25) { fname = fname.substring(0, 22) + '...sql'; }
            display.innerHTML = '<span style="color:white; font-weight:bold; font-size:18px;">📄 ' + fname + '</span><br><span style="font-size:13px; color:#46d369; margin-top:8px; display:inline-block;">جاهز للرفع والتنفيذ 🚀</span>';
            wrapper.classList.add('selected');
        } else {
            display.innerHTML = '<span style="font-size: 30px; display: block; margin-bottom: 10px;">📥</span> اسحب ملف القاعدة أو انقر للرفع (.sql)';
            wrapper.classList.remove('selected');
        }
    }

    function submitForm() {
        const fileInput = document.getElementById('sql_file');
        if (fileInput.files.length > 0) {
            const btn = document.getElementById('submitBtn');
            btn.innerHTML = 'جاري التحديث، يرجى الانتظار ⏳';
            btn.disabled = true;
            return true;
        }
        return false;
    }
</script>
</body>
</html>
