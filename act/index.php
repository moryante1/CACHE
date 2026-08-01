<?php
/**
 * SHASHETY PRO - لوحة تحكم إدارية متكاملة
 * Server & License Control System - Advanced Dashboard
 * 
 * تتضمن: إدارة كاملة للمشتركين / تمديد / تعويض / تغيير الباقة / تعطيل / إنهاء / حذف
 * نسخ احتياطي (تصدير/استيراد) / إحصائيات حية / المتصلين / مدة عمل النظام
 */

session_start();
require_once 'config.php';

date_default_timezone_set('Asia/Baghdad');

// === إعدادات البوت وتيليجرام ===
define('TG_BOT_TOKEN', '8791227293:AAEJVPDyG_-KcABLIu0KOHQu_kpreuwMsgY');
define('TG_CHAT_ID', '-5404694125');

// وقت بدء تشغيل النظام (يُحفظ في جدول الإعدادات)
// ---------------------------------------------------------

// دالة الإرسال لتيليجرام
function sendToTelegram($data, $license_key, $expiry_date = null) {
    // تنظيف القيم لمنع كسر تنسيق HTML في الرسالة
    $e = function($v) { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); };

    // خريطة أسماء الباقات للعرض بالعربي
    $planMap = [
        'trial_1day' => 'تجريبي - يوم', 'trial_1week' => 'تجريبي - أسبوع',
        'trial_1month' => 'تجريبي - شهر', 'monthly' => 'شهري',
        'yearly' => 'سنوي', 'lifetime' => 'مدى الحياة',
    ];
    $planRaw  = $data['license_type'] ?? '';
    $planName = $planMap[$planRaw] ?? $planRaw;

    $text  = "🆕 <b>طلب تفعيل جديد — SHASHETY PRO</b>\n";
    $text .= "━━━━━━━━━━━━━━━━━━\n";
    $text .= "👤 <b>العميل:</b> " . $e($data['customer_name']) . "\n";
    $text .= "📱 <b>الهاتف:</b> " . $e($data['phone']) . "\n";
    if (!empty($data['email']))  $text .= "📧 <b>الإيميل:</b> " . $e($data['email']) . "\n";
    if (!empty($data['domain'])) $text .= "🌐 <b>الدومين:</b> " . $e($data['domain']) . "\n";
    $text .= "💻 <b>Machine ID:</b>\n<code>" . $e($data['machine_id']) . "</code>\n";
    $text .= "━━━━━━━━━━━━━━━━━━\n";
    $text .= "📦 <b>الباقة:</b> " . $e($planName) . "\n";
    $text .= "🔑 <b>مفتاح التفعيل:</b>\n<code>" . $e($license_key) . "</code>\n";
    $text .= "📅 <b>تاريخ التفعيل:</b> " . date('Y-m-d H:i') . "\n";
    if ($planRaw === 'lifetime') {
        $text .= "♾️ <b>الانتهاء:</b> دائم (مدى الحياة)\n";
    } elseif (!empty($expiry_date)) {
        $text .= "⏳ <b>تاريخ الانتهاء:</b> " . $e(date('Y-m-d H:i', strtotime($expiry_date))) . "\n";
    }
    if (!empty($data['notes'])) $text .= "📝 <b>ملاحظات:</b> " . $e($data['notes']) . "\n";
    $text .= "━━━━━━━━━━━━━━━━━━\n";
    $text .= "✅ <b>الحالة:</b> مسجّل بنجاح على السيرفر";

    $url = "https://api.telegram.org/bot" . TG_BOT_TOKEN . "/sendMessage";
    $post_fields = ['chat_id' => TG_CHAT_ID, 'text' => $text, 'parse_mode' => 'HTML'];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    $response = curl_exec($ch);
    curl_close($ch);
    return $response;
}

// حماية CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// === تجهيز جداول مساعدة (الإعدادات + الأدمن) ===
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_auth (id INT PRIMARY KEY, password VARCHAR(255))");
    $pdo->exec("CREATE TABLE IF NOT EXISTS sys_settings (k VARCHAR(50) PRIMARY KEY, v TEXT)");

    // وقت بدء النظام
    $stmt = $pdo->query("SELECT v FROM sys_settings WHERE k = 'system_start'");
    $sys_start = $stmt->fetchColumn();
    if (!$sys_start) {
        $sys_start = time();
        $pdo->prepare("INSERT INTO sys_settings (k, v) VALUES ('system_start', ?)")->execute([$sys_start]);
    }

    // باسورد الأدمن
    $stmt = $pdo->query("SELECT password FROM admin_auth WHERE id = 1");
    $db_pass = $stmt->fetchColumn();
    if ($db_pass) {
        $ADMIN_PASSWORD = $db_pass;
    } else {
        $ADMIN_PASSWORD = 'admin@2024';
        $pdo->exec("INSERT INTO admin_auth (id, password) VALUES (1, 'admin@2024')");
    }
} catch (PDOException $e) {
    $ADMIN_PASSWORD = 'admin@2024';
    $sys_start = time();
}

$logged_in = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'];
$client_logged_in = isset($_SESSION['client_logged_in']) && $_SESSION['client_logged_in'];
$user_role = $_SESSION['user_role'] ?? null;

$success_message = $_SESSION['flash_success'] ?? null;
$error_message = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// ==================== تسجيل الدخول والخروج ====================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    $login_role = $_POST['login_role'] ?? 'admin'; // admin أو client
    $login_pass = $_POST['password'] ?? '';

    if ($login_role === 'admin') {
        // دخول المدير: صلاحيات كاملة
        if ($login_pass === $ADMIN_PASSWORD) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['user_role'] = 'admin';
            session_regenerate_id(true);
            header('Location: index.php'); exit;
        } else {
            $_SESSION['flash_error'] = 'بيانات دخول المدير غير مطابقة.';
            header('Location: index.php'); exit;
        }
    } else {
        // دخول العميل: التحقق عبر مفتاح الرخصة أو Machine ID
        $ident = trim($login_pass);
        try {
            $stmt = $pdo->prepare("SELECT * FROM licenses WHERE (license_key = ? OR machine_id = ?) LIMIT 1");
            $stmt->execute([$ident, $ident]);
            $lic = $stmt->fetch();
        } catch (PDOException $e) {
            $lic = false;
        }

        if ($lic) {
            $is_lifetime = ($lic['license_type'] === 'lifetime');
            $exp = !empty($lic['expiry_date']) ? strtotime($lic['expiry_date']) : null;
            $is_expired = (!$is_lifetime && $exp && $exp < time());

            if (!$lic['is_active']) {
                $_SESSION['flash_error'] = 'الاشتراك موقوف حالياً. تواصل مع الدعم.';
            } elseif ($is_expired) {
                $_SESSION['flash_error'] = 'انتهى اشتراكك. يرجى التجديد.';
            } else {
                $_SESSION['client_logged_in'] = true;
                $_SESSION['user_role'] = 'client';
                $_SESSION['client_license_id'] = (int)$lic['id'];
                session_regenerate_id(true);
                header('Location: index.php'); exit;
            }
        } else {
            $_SESSION['flash_error'] = 'مفتاح الرخصة أو المعرّف غير صحيح.';
        }
        header('Location: index.php'); exit;
    }
}

if (isset($_GET['logout'])) {
    // إغلاق صلاحية التعديل وكل الجلسة
    unset($_SESSION['edit_unlocked'], $_SESSION['otp_pending']);
    $_SESSION = [];
    session_destroy();
    header('Location: index.php'); exit;
}

// ==================== تحديث النظام (سحب واستخراج تلقائي من GitHub) ====================
define('UPDATE_ZIP_URL', 'https://github.com/moryante1/act/archive/refs/heads/main.zip');

function performSystemUpdate() {
    // رفع حدود التنفيذ لتفادي توقف السكربت بصمت أثناء التنزيل/الاستخراج
    @set_time_limit(300);
    @ini_set('max_execution_time', '300');
    @ini_set('memory_limit', '256M');

    $result = ['ok' => false, 'msg' => '', 'files' => 0, 'log' => [], 'file_list' => []];
    $log = function($m) use (&$result) { $result['log'][] = $m; };

    // 1) تحقق من توفر ZipArchive
    if (!class_exists('ZipArchive')) {
        $result['msg'] = 'إضافة ZipArchive غير مفعّلة على الخادم. فعّلها من إعدادات PHP.';
        return $result;
    }
    $log('✓ ZipArchive متاحة');

    // اختيار مجلد مؤقت قابل للكتابة
    $tmpBase = sys_get_temp_dir();
    if (!is_writable($tmpBase)) {
        $tmpBase = __DIR__; // بديل: مجلد النظام نفسه
    }
    $tmpZip = $tmpBase . '/shashety_update_' . time() . '.zip';
    $extractDir = $tmpBase . '/shashety_extract_' . time();
    $log('مجلد مؤقت: ' . $tmpBase);

    // 2) تنزيل الأرشيف — نجرّب cURL أولاً، ثم file_get_contents تلقائياً عند الفشل
    $zipData = false;
    $dlErr = '';
    if (function_exists('curl_init')) {
        $ch = curl_init(UPDATE_ZIP_URL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // بعض الاستضافات بلا شهادات CA محدثة
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_TIMEOUT, 90);
        curl_setopt($ch, CURLOPT_USERAGENT, 'SHASHETY-Updater');
        $tmp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);
        if ($tmp !== false && $httpCode === 200 && strlen($tmp) > 100) {
            $zipData = $tmp;
            $log('✓ تم التنزيل عبر cURL (' . strlen($zipData) . ' بايت)');
        } else {
            $dlErr = 'cURL كود:' . $httpCode . ' ' . $curlErr;
            $log('✗ فشل cURL (' . $dlErr . ') — تجربة file_get_contents');
        }
    }

    // بديل: file_get_contents إن لم ينجح cURL
    if ($zipData === false && ini_get('allow_url_fopen')) {
        $ctx = stream_context_create([
            'http' => ['timeout' => 90, 'follow_location' => 1, 'header' => "User-Agent: SHASHETY-Updater\r\n"],
            'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false],
        ]);
        $tmp = @file_get_contents(UPDATE_ZIP_URL, false, $ctx);
        if ($tmp !== false && strlen($tmp) > 100) {
            $zipData = $tmp;
            $log('✓ تم التنزيل عبر file_get_contents (' . strlen($zipData) . ' بايت)');
        }
    }

    if ($zipData === false) {
        $result['msg'] = 'تعذّر تنزيل الأرشيف من GitHub. ' . $dlErr
                       . ' | allow_url_fopen: ' . (ini_get('allow_url_fopen') ? 'مفعّل' : 'معطّل')
                       . ' — تحقق من اتصال الخادم بالإنترنت أو جرّب الرفع اليدوي.';
        return $result;
    }

    // 3) حفظ الأرشيف مؤقتاً
    if (@file_put_contents($tmpZip, $zipData) === false) {
        $result['msg'] = 'تعذّر حفظ الملف المؤقت في: ' . $tmpBase . ' (صلاحيات الكتابة).';
        return $result;
    }
    $log('✓ حُفظ الأرشيف المؤقت');

    // 4) فك الضغط
    $zip = new ZipArchive();
    $open = $zip->open($tmpZip);
    if ($open !== true) {
        @unlink($tmpZip);
        $result['msg'] = 'الملف المنزّل ليس أرشيف ZIP صالح (كود ZipArchive: ' . $open . ').';
        return $result;
    }
    if (!@mkdir($extractDir, 0755, true) && !is_dir($extractDir)) {
        $zip->close(); @unlink($tmpZip);
        $result['msg'] = 'تعذّر إنشاء مجلد الاستخراج المؤقت.';
        return $result;
    }
    if (!$zip->extractTo($extractDir)) {
        $zip->close(); @unlink($tmpZip);
        $result['msg'] = 'فشل استخراج الأرشيف إلى المجلد المؤقت.';
        return $result;
    }
    $zip->close();
    @unlink($tmpZip);
    $log('✓ تم فك الضغط');

    // 5) الدخول في المجلدات الأحادية (تجاوز act-main) للوصول للمحتوى الفعلي
    $rootInside = $extractDir;
    while (true) {
        $entries = array_values(array_diff(scandir($rootInside), ['.', '..']));
        if (count($entries) === 1 && is_dir($rootInside . '/' . $entries[0])) {
            $rootInside = $rootInside . '/' . $entries[0];
        } else {
            break;
        }
    }
    $rootInside = rtrim($rootInside, '/\\');
    $log('جذر المحتوى: ' . basename($rootInside));

    // 6) التحقق من إمكانية الكتابة في مجلد النظام
    $destBase = rtrim(__DIR__, '/\\');
    if (!is_writable($destBase)) {
        $result['msg'] = 'مجلد النظام غير قابل للكتابة: ' . $destBase . ' — عدّل الصلاحيات (chmod).';
        return $result;
    }
    $log('مجلد الوجهة: ' . $destBase);

    // 7) نسخ الملفات مباشرة إلى جذر النظام (بدون مجلد وسيط)
    $protected = ['config.php'];      // ملفات محمية لا تُستبدل
    $copied = 0; $failed = 0;
    $fileList = [];                   // قائمة الملفات المُحدّثة للعرض
    $prefixLen = strlen($rootInside) + 1;

    $rii = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($rootInside, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($rii as $item) {
        $relPath = substr($item->getPathname(), $prefixLen);
        if ($relPath === '' || $relPath === false) continue;
        $relPath = str_replace('\\', '/', $relPath);
        $target  = $destBase . '/' . $relPath;

        if ($item->isDir()) {
            if (!is_dir($target)) @mkdir($target, 0755, true);
        } else {
            if (in_array(basename($relPath), $protected, true) && file_exists($target)) {
                $fileList[] = ['name' => $relPath, 'size' => @filesize($item->getPathname()) ?: 0, 'status' => 'skip'];
                continue;
            }
            $dir = dirname($target);
            if (!is_dir($dir)) @mkdir($dir, 0755, true);
            $sz = @filesize($item->getPathname()) ?: 0;
            // محاولة النسخ، ثم rename كبديل
            if (@copy($item->getPathname(), $target)) {
                @chmod($target, 0644);
                $copied++;
                $fileList[] = ['name' => $relPath, 'size' => $sz, 'status' => 'ok'];
            } elseif (@rename($item->getPathname(), $target)) {
                $copied++;
                $fileList[] = ['name' => $relPath, 'size' => $sz, 'status' => 'ok'];
            } else {
                $failed++;
                $fileList[] = ['name' => $relPath, 'size' => $sz, 'status' => 'fail'];
            }
        }
    }
    $log("نُسخ: {$copied} | فشل: {$failed}");
    $result['file_list'] = $fileList;

    // 8) تنظيف مجلد الاستخراج المؤقت
    if (is_dir($extractDir)) {
        $rii2 = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($extractDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($rii2 as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($extractDir);
    }

    if ($copied === 0) {
        $result['msg'] = 'لم يُنسخ أي ملف (فشل: ' . $failed . '). تحقق من صلاحيات الكتابة في مجلد النظام.';
        return $result;
    }

    $result['ok'] = true;
    $result['files'] = $copied;
    $result['msg'] = "تم التحديث بنجاح ✓ — الملفات المُحدّثة: {$copied}" . ($failed ? " | تعذّر: {$failed}" : "");
    return $result;
}

// ==================== فحص بيئة التحديث (تشخيص) ====================
if ($logged_in && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_diagnose'])) {
    checkToken();
    $d = [];
    $d[] = 'PHP: ' . phpversion();
    $d[] = 'ZipArchive: ' . (class_exists('ZipArchive') ? '✓ متاحة' : '✗ غير متاحة');
    $d[] = 'cURL: ' . (function_exists('curl_init') ? '✓ متاحة' : '✗ غير متاحة');
    $d[] = 'allow_url_fopen: ' . (ini_get('allow_url_fopen') ? '✓ مفعّل' : '✗ معطّل');
    $d[] = 'مجلد النظام (__DIR__): ' . __DIR__;
    $d[] = 'قابل للكتابة: ' . (is_writable(__DIR__) ? '✓ نعم' : '✗ لا — عدّل الصلاحيات chmod 755');
    $d[] = 'مجلد temp: ' . sys_get_temp_dir();
    $d[] = 'temp قابل للكتابة: ' . (is_writable(sys_get_temp_dir()) ? '✓ نعم' : '✗ لا');

    // اختبار اتصال فعلي بـ GitHub (رأس فقط)
    if (function_exists('curl_init')) {
        $ch = curl_init(UPDATE_ZIP_URL);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_USERAGENT, 'SHASHETY-Updater');
        curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);
        $d[] = 'اتصال GitHub: ' . ($code == 200 ? '✓ ناجح (200)' : '✗ فشل (كود: ' . $code . ') ' . $err);
    }

    $_SESSION['flash_success'] = '🔍 فحص البيئة: [ ' . implode('  |  ', $d) . ' ]';
    header('Location: index.php'); exit;
}

if ($logged_in && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['system_update'])) {
    checkToken();
    try {
        $upd = performSystemUpdate();
    } catch (Throwable $e) {
        $upd = ['ok' => false, 'msg' => 'خطأ داخلي: ' . $e->getMessage(), 'files' => 0, 'log' => [], 'file_list' => []];
    }
    // استجابة JSON للواجهة الاحترافية (AJAX)
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok'        => $upd['ok'],
        'msg'       => $upd['msg'],
        'files'     => $upd['files'],
        'log'       => $upd['log'] ?? [],
        'file_list' => $upd['file_list'] ?? [],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// حارس: كل ما يلي يتطلب تسجيل الدخول
function requireLogin($logged_in) {
    if (!$logged_in) { header('Location: index.php'); exit; }
}

// دالة مساعدة للتحقق من التوكن
function checkToken() {
    $t = $_POST['csrf_token'] ?? $_GET['token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $t)) die('مرفوض: رمز الحماية غير صالح.');
}

// ==================== تغيير كلمة مرور الأدمن ====================
if ($logged_in && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_admin_password'])) {
    checkToken();
    $old_pw = $_POST['old_password']; $new_pw = $_POST['new_password'];
    if ($old_pw === $ADMIN_PASSWORD) {
        if (strlen($new_pw) >= 5) {
            try {
                $pdo->prepare("UPDATE admin_auth SET password = ? WHERE id = 1")->execute([$new_pw]);
                $_SESSION['flash_success'] = "تم تغيير الرمز السري بنجاح وتحديث القاعدة!";
            } catch (PDOException $e) {
                $_SESSION['flash_error'] = 'حدث خطأ أثناء تحديث البيانات.';
            }
        } else {
            $_SESSION['flash_error'] = 'يجب ألا تقل كلمة المرور عن 5 أحرف.';
        }
    } else {
        $_SESSION['flash_error'] = 'كلمة المرور السابقة غير متطابقة.';
    }
    header('Location: index.php'); exit;
}

// ==================== إصدار / تفعيل رخصة جديدة ====================
if ($logged_in && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_license'])) {
    checkToken();
    $machine_id = trim($_POST['machine_id']);
    $customer_name = trim($_POST['customer_name']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $domain = trim($_POST['domain']);
    $license_type = $_POST['license_type'];
    $notes = trim($_POST['notes']);

    $license_key = function_exists('generateLicenseKey') ? generateLicenseKey() : strtoupper(md5(uniqid('', true)));
    $expiry_date = function_exists('calculateExpiry') ? calculateExpiry($license_type) : null;

    try {
        $stmt = $pdo->prepare("
            INSERT INTO licenses (machine_id, customer_name, phone, email, domain, license_key, license_type, activation_date, expiry_date, ip_address, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?)
        ");
        if ($stmt->execute([$machine_id, $customer_name, $phone, $email, $domain, $license_key, $license_type, $expiry_date, null, $notes])) {
            sendToTelegram($_POST, $license_key, $expiry_date);
            $_SESSION['flash_success'] = "تم توليد وتوثيق التفعيل للعميل بنجاح! تم إرسال المفتاح إلى الإدارة.";
            header('Location: index.php'); exit;
        }
    } catch(PDOException $e) {
        if ($e->getCode() == 23000) $_SESSION['flash_error'] = 'المُعرف متواجد بالنظام لعميل آخر.';
        else $_SESSION['flash_error'] = 'خطأ في خوادم النظام.';
        header('Location: index.php'); exit;
    }
}

// ==================== تعديل بيانات المشترك (الاسم/الهاتف/الإيميل/الدومين/الملاحظات) ====================
if ($logged_in && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_subscriber'])) {
    checkToken();
    $id = (int)$_POST['id'];
    try {
        $stmt = $pdo->prepare("UPDATE licenses SET customer_name=?, phone=?, email=?, domain=?, notes=? WHERE id=?");
        $stmt->execute([
            trim($_POST['customer_name']), trim($_POST['phone']), trim($_POST['email']),
            trim($_POST['domain']), trim($_POST['notes']), $id
        ]);
        $_SESSION['flash_success'] = "تم تعديل بيانات المشترك بنجاح.";
    } catch(PDOException $e) {
        $_SESSION['flash_error'] = 'تعذّر تعديل البيانات.';
    }
    header('Location: index.php'); exit;
}

// ==================== نظام كود التحقق (OTP) عبر الإدارة للعمليات الحساسة ====================
// إرسال كود تحقق إلى الإدارة
function sendOtpToTelegram($code, $action_label = '', $target_info = '') {
    $text  = "🔓 <b>كود فتح صلاحية التعديل — SHASHETY PRO</b>\n";
    $text .= "━━━━━━━━━━━━━━━━━━\n";
    $text .= "🔑 <b>الكود:</b> <code>" . $code . "</code>\n";
    $text .= "⏱️ <b>يفتح كل عمليات التعديل حتى:</b> تسجيل الخروج\n";
    $text .= "📋 <b>يشمل:</b> تعديل التاريخ · تمديد · تعويض · تغيير باقة · إنهاء · حذف\n";
    $text .= "━━━━━━━━━━━━━━━━━━\n";
    $text .= "⚠️ لا تشارك هذا الكود مع أحد.";

    $url = "https://api.telegram.org/bot" . TG_BOT_TOKEN . "/sendMessage";
    $post = ['chat_id' => TG_CHAT_ID, 'text' => $text, 'parse_mode' => 'HTML'];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    $res = curl_exec($ch);
    $ok = ($res !== false && strpos($res, '"ok":true') !== false);
    curl_close($ch);
    return $ok;
}

// خريطة أسماء العمليات الحساسة
$OTP_ACTIONS = [
    'set_expiry'     => 'تعديل تاريخ الانتهاء',
    'extend_license' => 'تمديد الصلاحية',
    'compensate'     => 'تعويض المشترك',
    'change_plan'    => 'تغيير الباقة',
    'terminate'      => 'إنهاء الاشتراك',
    'delete'         => 'حذف نهائي',
];

// معالج AJAX 1: توليد كود وإرساله إلى الإدارة
if ($logged_in && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['otp_request'])) {
    checkToken();
    header('Content-Type: application/json; charset=utf-8');

    // توليد كود من 6 أرقام لفتح صلاحية عامة
    $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $_SESSION['otp_pending'] = [
        'code'    => $code,
        'expires' => time() + 300, // الكود نفسه صالح للإدخال لمدة 5 دقائق
    ];

    $sent = sendOtpToTelegram($code);
    if ($sent) {
        echo json_encode(['ok' => true, 'msg' => 'تم إرسال الكود إلى الإدارة.'], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(['ok' => false, 'msg' => 'تعذّر إرسال الكود. حاول مرة أخرى.'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// معالج AJAX 2: التحقق من الكود وفتح صلاحية التعديل لمدة محددة
if ($logged_in && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['otp_verify'])) {
    checkToken();
    header('Content-Type: application/json; charset=utf-8');

    $entered = trim($_POST['otp_code'] ?? '');
    $pending = $_SESSION['otp_pending'] ?? null;

    if (!$pending) {
        echo json_encode(['ok' => false, 'msg' => 'لم يُطلب كود بعد. اضغط إرسال الكود.'], JSON_UNESCAPED_UNICODE); exit;
    }
    if (time() > $pending['expires']) {
        unset($_SESSION['otp_pending']);
        echo json_encode(['ok' => false, 'msg' => 'انتهت صلاحية الكود. اطلب كوداً جديداً.'], JSON_UNESCAPED_UNICODE); exit;
    }
    if (!hash_equals($pending['code'], $entered)) {
        echo json_encode(['ok' => false, 'msg' => 'الكود غير صحيح.'], JSON_UNESCAPED_UNICODE); exit;
    }

    // نجح — نفتح صلاحية التعديل لكل العمليات طوال الجلسة (حتى تسجيل الخروج)
    unset($_SESSION['otp_pending']);
    $_SESSION['edit_unlocked'] = true;
    echo json_encode([
        'ok' => true,
        'msg' => 'تم فتح صلاحية التعديل. كل الخيارات متاحة الآن حتى تسجيل الخروج.',
        'session' => true,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// دالة التحقق: هل صلاحية التعديل مفتوحة حالياً؟
function isEditUnlocked() {
    return !empty($_SESSION['edit_unlocked']);
}

// حارس: يُستدعى قبل تنفيذ أي عملية حساسة
function requireOtp($action = '', $id = 0) {
    if (!isEditUnlocked()) {
        $_SESSION['flash_error'] = 'صلاحية التعديل مغلقة. أدخل كود الفتح أولاً لتفعيل الخيارات.';
        header('Location: index.php'); exit;
    }
    // الصلاحية مفتوحة طوال الجلسة — نسمح بكل العمليات
    return true;
}

// ==================== تعديل تاريخ الانتهاء يدوياً ====================
if ($logged_in && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['set_expiry'])) {
    checkToken();
    requireOtp('set_expiry', (int)$_POST['id']);
    $id = (int)$_POST['id'];
    $new_date = trim($_POST['expiry_date']);
    try {
        // صيغة datetime-local => Y-m-d H:i
        $dt = $new_date ? date('Y-m-d H:i:s', strtotime($new_date)) : null;
        $pdo->prepare("UPDATE licenses SET expiry_date=? WHERE id=?")->execute([$dt, $id]);
        $_SESSION['flash_success'] = "تم تعديل تاريخ الاشتراك بنجاح.";
    } catch(PDOException $e) {
        $_SESSION['flash_error'] = 'تعذّر تعديل التاريخ.';
    }
    header('Location: index.php'); exit;
}

// ==================== تمديد الصلاحية (بعدد أيام) ====================
if ($logged_in && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['extend_license'])) {
    checkToken();
    requireOtp('extend_license', (int)$_POST['id']);
    $id = (int)$_POST['id'];
    $days = (int)$_POST['days'];
    try {
        $stmt = $pdo->prepare("SELECT expiry_date, license_type FROM licenses WHERE id=?");
        $stmt->execute([$id]); $row = $stmt->fetch();
        // نبدأ من التاريخ الحالي للاشتراك أو من الآن إن كان منتهياً/فارغاً
        $base = (!empty($row['expiry_date']) && strtotime($row['expiry_date']) > time())
                ? strtotime($row['expiry_date']) : time();
        $new_expiry = date('Y-m-d H:i:s', $base + ($days * 86400));
        // عند التمديد نعيد تفعيل الاشتراك تلقائياً
        $pdo->prepare("UPDATE licenses SET expiry_date=?, is_active=1 WHERE id=?")->execute([$new_expiry, $id]);
        $_SESSION['flash_success'] = "تم تمديد الصلاحية {$days} يوم. تاريخ الانتهاء الجديد: " . date('Y-m-d', strtotime($new_expiry));
    } catch(PDOException $e) {
        $_SESSION['flash_error'] = 'تعذّر تمديد الصلاحية.';
    }
    header('Location: index.php'); exit;
}

// ==================== تعويض (إضافة أيام مجانية كتعويض + تسجيل ملاحظة) ====================
if ($logged_in && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['compensate'])) {
    checkToken();
    requireOtp('compensate', (int)$_POST['id']);
    $id = (int)$_POST['id'];
    $days = (int)$_POST['comp_days'];
    $reason = trim($_POST['comp_reason']);
    try {
        $stmt = $pdo->prepare("SELECT expiry_date, notes FROM licenses WHERE id=?");
        $stmt->execute([$id]); $row = $stmt->fetch();
        $base = (!empty($row['expiry_date']) && strtotime($row['expiry_date']) > time())
                ? strtotime($row['expiry_date']) : time();
        $new_expiry = date('Y-m-d H:i:s', $base + ($days * 86400));
        $note = trim(($row['notes'] ?? '') . "\n[تعويض " . date('Y-m-d') . "] +{$days} يوم - السبب: {$reason}");
        $pdo->prepare("UPDATE licenses SET expiry_date=?, notes=?, is_active=1 WHERE id=?")->execute([$new_expiry, $note, $id]);
        $_SESSION['flash_success'] = "تم تعويض المشترك بـ {$days} يوم إضافي.";
    } catch(PDOException $e) {
        $_SESSION['flash_error'] = 'تعذّر إجراء التعويض.';
    }
    header('Location: index.php'); exit;
}

// ==================== تغيير الباقة ====================
if ($logged_in && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_plan'])) {
    checkToken();
    requireOtp('change_plan', (int)$_POST['id']);
    $id = (int)$_POST['id'];
    $new_type = $_POST['new_license_type'];
    $recalc = isset($_POST['recalc_expiry']);
    try {
        if ($recalc) {
            // إعادة حساب تاريخ الانتهاء حسب الباقة الجديدة ابتداءً من الآن
            $new_expiry = function_exists('calculateExpiry') ? calculateExpiry($new_type) : null;
            $pdo->prepare("UPDATE licenses SET license_type=?, expiry_date=?, is_active=1 WHERE id=?")
                ->execute([$new_type, $new_expiry, $id]);
        } else {
            $pdo->prepare("UPDATE licenses SET license_type=? WHERE id=?")->execute([$new_type, $id]);
        }
        $_SESSION['flash_success'] = "تم تغيير الباقة بنجاح.";
    } catch(PDOException $e) {
        $_SESSION['flash_error'] = 'تعذّر تغيير الباقة.';
    }
    header('Location: index.php'); exit;
}

// ==================== تعطيل / تفعيل المشترك (Toggle) ====================
if ($logged_in && isset($_GET['toggle']) && isset($_GET['id'])) {
    checkToken();
    $pdo->prepare("UPDATE licenses SET is_active = NOT is_active WHERE id = ?")->execute([(int)$_GET['id']]);
    $_SESSION['flash_success'] = "تم تغيير حالة الاشتراك.";
    header('Location: index.php'); exit;
}

// ==================== إنهاء الاشتراك (تعيين الانتهاء للآن + تعطيل) ====================
if ($logged_in && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['terminate'])) {
    checkToken();
    $id = (int)$_POST['id'];
    requireOtp('terminate', $id);
    $pdo->prepare("UPDATE licenses SET expiry_date = NOW(), is_active = 0 WHERE id = ?")->execute([$id]);
    $_SESSION['flash_success'] = "تم إنهاء الاشتراك فوراً.";
    header('Location: index.php'); exit;
}

// ==================== حذف نهائي ====================
if ($logged_in && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_sub'])) {
    checkToken();
    $id = (int)$_POST['id'];
    requireOtp('delete', $id);
    $pdo->prepare("DELETE FROM licenses WHERE id = ?")->execute([$id]);
    $_SESSION['flash_success'] = "تم حذف المشترك نهائياً.";
    header('Location: index.php'); exit;
}

// ==================== تصدير نسخة احتياطية (JSON) ====================
if ($logged_in && isset($_GET['export']) && isset($_GET['token']) && hash_equals($_SESSION['csrf_token'], $_GET['token'])) {
    $data = $pdo->query("SELECT * FROM licenses ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
    $backup = [
        'meta' => ['system' => 'SHASHETY PRO', 'exported_at' => date('Y-m-d H:i:s'), 'count' => count($data)],
        'licenses' => $data
    ];
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="shashety_backup_' . date('Y-m-d_His') . '.json"');
    echo json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// ==================== استيراد نسخة احتياطية (JSON) ====================
if ($logged_in && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['import_backup'])) {
    checkToken();
    $mode = $_POST['import_mode'] ?? 'merge'; // merge أو replace
    if (!empty($_FILES['backup_file']['tmp_name'])) {
        $raw = file_get_contents($_FILES['backup_file']['tmp_name']);
        $json = json_decode($raw, true);
        if (isset($json['licenses']) && is_array($json['licenses'])) {
            try {
                $pdo->beginTransaction();
                if ($mode === 'replace') {
                    $pdo->exec("DELETE FROM licenses");
                }
                $cols = ['machine_id','customer_name','phone','email','domain','license_key','license_type','activation_date','expiry_date','is_active','ip_address','notes'];
                $imported = 0; $skipped = 0;
                foreach ($json['licenses'] as $r) {
                    // تجاهل التكرار في وضع الدمج
                    if ($mode === 'merge') {
                        $chk = $pdo->prepare("SELECT id FROM licenses WHERE machine_id=? OR license_key=? LIMIT 1");
                        $chk->execute([$r['machine_id'] ?? '', $r['license_key'] ?? '']);
                        if ($chk->fetch()) { $skipped++; continue; }
                    }
                    $vals = [];
                    foreach ($cols as $c) { $vals[] = $r[$c] ?? null; }
                    $ph = implode(',', array_fill(0, count($cols), '?'));
                    $stmt = $pdo->prepare("INSERT INTO licenses (".implode(',', $cols).") VALUES ($ph)");
                    $stmt->execute($vals);
                    $imported++;
                }
                $pdo->commit();
                $_SESSION['flash_success'] = "تم الاستيراد بنجاح. مُضاف: {$imported}" . ($skipped ? " | متجاوز (مكرر): {$skipped}" : "");
            } catch(PDOException $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $_SESSION['flash_error'] = 'فشل الاستيراد: تحقق من صحة الملف. (' . $e->getMessage() . ')';
            }
        } else {
            $_SESSION['flash_error'] = 'ملف النسخة الاحتياطية غير صالح.';
        }
    } else {
        $_SESSION['flash_error'] = 'لم يتم اختيار ملف.';
    }
    header('Location: index.php'); exit;
}

// ==================== جلب البيانات والإحصائيات ====================
$licenses = [];
$stats = ['total'=>0, 'active_sub'=>0, 'expired'=>0, 'lifetime'=>0, 'expiring_soon'=>0, 'inactive'=>0, 'online'=>0];
$expiring_list = [];
$online_list = [];

if ($logged_in) {
    $licenses = $pdo->query("SELECT * FROM licenses ORDER BY created_at DESC")->fetchAll();
    $stats['total'] = count($licenses);
    $now = time();
    $soon_threshold = $now + (3 * 86400); // خلال 3 أيام

    foreach($licenses as $lic) {
        $is_lifetime = ($lic['license_type'] === 'lifetime');
        $exp = $lic['expiry_date'] ? strtotime($lic['expiry_date']) : null;
        $is_expired = (!$is_lifetime && $exp && $exp < $now);
        $is_valid_now = $lic['is_active'] && !$is_expired;

        if ($is_lifetime) $stats['lifetime']++;
        if (!$lic['is_active']) $stats['inactive']++;
        if ($is_expired) $stats['expired']++;

        // المشتركون الفعالون: نشط وغير منتهي
        if ($is_valid_now) $stats['active_sub']++;

        // ينتهي خلال 3 أيام (نشط وغير دائم وغير منتهٍ بعد)
        if ($is_valid_now && !$is_lifetime && $exp && $exp <= $soon_threshold && $exp >= $now) {
            $stats['expiring_soon']++;
            $lic['_days_left'] = ceil(($exp - $now) / 86400);
            $expiring_list[] = $lic;
        }

        // المتصلون: آخر فحص خلال 10 دقائق
        if (!empty($lic['last_check']) && (strtotime($lic['last_check']) > ($now - 600))) {
            $stats['online']++;
            $online_list[] = $lic;
        }
    }

    // ترتيب قائمة "ينتهي قريباً" بالأقرب أولاً
    usort($expiring_list, fn($a, $b) => $a['_days_left'] <=> $b['_days_left']);
}

// حساب مدة عمل النظام
$uptime_seconds = time() - (int)$sys_start;
$up_days = floor($uptime_seconds / 86400);
$up_hours = floor(($uptime_seconds % 86400) / 3600);
$up_mins = floor(($uptime_seconds % 3600) / 60);
$uptime_str = "{$up_days} يوم، {$up_hours} ساعة، {$up_mins} دقيقة";

$TKN = $_SESSION['csrf_token'];

// خريطة أسماء الباقات للعرض
$PLAN_OPTIONS = [
    'trial_1day'   => 'تجريبي - يوم',
    'trial_1week'  => 'تجريبي - أسبوع',
    'trial_1month' => 'تجريبي - شهر',
    'monthly'      => 'شهري',
    'yearly'       => 'سنوي',
    'lifetime'     => 'مدى الحياة',
];
function planName($t, $map) { return $map[$t] ?? $t; }
?>
<!-- ▼▼▼ بداية الواجهة (HTML) ستُضاف في القسم التالي ▼▼▼ -->
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SHASHETY PRO | لوحة التحكم الإدارية</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root{
            --m-bg:#0f0f13; --m-card:#18181e; --m-card2:#1f1f26;
            --main-red:#D20000; --main-hover:#b30000; --m-border:#282833;
            --text-l:#fff; --text-d:#8c8c8c;
            --green:#2ed573; --gold:#eccc68; --blue:#3742fa; --orange:#ffa502;
        }
        *{margin:0;padding:0;box-sizing:border-box;}
        body{font-family:'Tajawal',sans-serif;background:var(--m-bg);color:var(--text-l);min-height:100vh;overflow-x:hidden;
            scrollbar-width:thin;scrollbar-color:var(--main-red) var(--m-bg);}
        body::-webkit-scrollbar{width:8px;}
        body::-webkit-scrollbar-track{background:var(--m-bg);}
        body::-webkit-scrollbar-thumb{background:var(--main-red);border-radius:4px;}
        a{text-decoration:none;}

        /* ===== تسجيل الدخول ===== */
        .login-container{display:flex;align-items:center;justify-content:center;min-height:100vh;padding:20px;}
        .login-box{background:rgba(24,24,30,.85);padding:50px 40px;border-radius:15px;border:1px solid var(--m-border);width:100%;max-width:450px;text-align:center;}
        .login-box h1{font-family:Impact,sans-serif;color:var(--main-red);margin-bottom:5px;font-size:35px;letter-spacing:2px;}
        .login-box h1 span{color:#fff;}

        /* محدد الدور (مدير/عميل) */
        .role-switch{display:flex;gap:8px;background:#1a1a20;border:1px solid var(--m-border);
            border-radius:10px;padding:5px;margin-bottom:20px;}
        .role-btn{flex:1;background:transparent;border:none;color:#999;padding:11px;border-radius:7px;
            cursor:pointer;font-size:14px;font-weight:700;font-family:'Tajawal';transition:.2s;
            display:flex;align-items:center;justify-content:center;gap:7px;}
        .role-btn.active{background:linear-gradient(135deg,var(--main-red),#ff2a2a);color:#fff;
            box-shadow:0 3px 12px rgba(210,0,0,.3);}

        .form-group{margin-bottom:18px;position:relative;}
        .form-group label{display:block;text-align:right;margin-bottom:7px;font-size:13px;color:var(--text-d);font-weight:600;}
        input,select,textarea{width:100%;padding:14px;background:#202028;color:#fff;border:1px solid #333;border-radius:8px;font-size:15px;
            transition:.25s;outline:none;font-family:'Tajawal',sans-serif;}
        input:focus,select:focus,textarea:focus{border-color:var(--main-red);background:#252530;}
        select option{background:#202028;}

        .btn{width:100%;padding:14px;background:linear-gradient(135deg,var(--main-red),#ff2a2a);color:#fff;border:none;border-radius:8px;
            font-size:16px;font-weight:bold;cursor:pointer;transition:.25s;}
        .btn:hover{filter:brightness(1.1);transform:translateY(-1px);}
        .btn-sm{width:auto;padding:9px 16px;font-size:13px;}
        .btn-dark{background:linear-gradient(135deg,#1f2029,#292a35);border:1px solid var(--m-border);color:#ccc;}
        .btn-dark:hover{color:#fff;}
        .btn-green{background:linear-gradient(135deg,#1e9e54,var(--green));}
        .btn-blue{background:linear-gradient(135deg,#2733d4,var(--blue));}
        .btn-orange{background:linear-gradient(135deg,#e08e00,var(--orange));}

        /* ===== التخطيط العام مع الشريط الجانبي ===== */
        .app-layout{display:flex;min-height:100vh;}

        /* ===== الشريط الجانبي (Sidebar) ===== */
        .sidebar{width:260px;background:var(--m-card);border-left:1px solid var(--m-border);
            display:flex;flex-direction:column;position:fixed;top:0;right:0;height:100vh;z-index:900;
            transition:transform .3s ease;}
        .sidebar-brand{padding:24px 22px;border-bottom:1px solid var(--m-border);text-align:center;}
        .sidebar-brand h1{font-family:Impact,sans-serif;color:var(--main-red);font-size:26px;letter-spacing:2px;}
        .sidebar-brand h1 span{color:#fff;}
        .sidebar-brand p{color:var(--text-d);font-size:12px;margin-top:4px;}
        .sidebar-nav{flex:1;padding:18px 12px;overflow-y:auto;display:flex;flex-direction:column;gap:6px;}
        .sidebar-nav::-webkit-scrollbar{width:5px;}
        .sidebar-nav::-webkit-scrollbar-thumb{background:var(--main-red);border-radius:3px;}
        .nav-item{background:transparent;border:1px solid transparent;color:#bbb;padding:13px 16px;border-radius:9px;
            cursor:pointer;font-size:14.5px;font-weight:600;font-family:'Tajawal';transition:.2s;
            display:flex;align-items:center;gap:12px;text-align:right;width:100%;}
        .nav-item i{width:20px;text-align:center;font-size:16px;}
        .nav-item:hover{background:var(--m-card2);color:#fff;}
        .nav-item.active{background:linear-gradient(135deg,var(--main-red),#ff2a2a);color:#fff;box-shadow:0 4px 14px rgba(210,0,0,.3);}
        .nav-badge{margin-right:auto;background:rgba(255,255,255,.18);font-size:11px;padding:2px 8px;border-radius:20px;font-weight:bold;}
        .nav-item:not(.active) .nav-badge{background:var(--m-card2);color:var(--orange);}
        .sidebar-foot{padding:14px 16px;border-top:1px solid var(--m-border);}
        .logout{background:#222;border:1px solid #444;color:#ccc;padding:12px;border-radius:8px;transition:.25s;font-size:14px;display:flex;align-items:center;justify-content:center;gap:8px;width:100%;}
        .logout:hover{background:var(--main-red);color:#fff;border-color:var(--main-red);}

        /* ===== المحتوى الرئيسي ===== */
        .main-area{flex:1;margin-right:260px;min-width:0;}
        .dashboard{padding:22px 26px;max-width:1400px;margin:0 auto;}
        .topbar{display:flex;justify-content:space-between;align-items:center;margin-bottom:22px;padding-bottom:16px;
            border-bottom:1px solid var(--m-border);flex-wrap:wrap;gap:12px;}
        .topbar .page-title{font-size:22px;font-weight:800;display:flex;align-items:center;gap:9px;}
        .topbar .page-title i{color:var(--main-red);}
        .uptime-chip{background:var(--m-card);border:1px solid var(--m-border);padding:9px 15px;border-radius:8px;font-size:13px;color:#bbb;}
        .uptime-chip i{color:var(--green);margin-left:5px;}

        /* زر فتح القائمة بالجوال */
        .menu-toggle{display:none;background:var(--m-card);border:1px solid var(--m-border);color:#fff;width:44px;height:44px;border-radius:8px;font-size:18px;cursor:pointer;}
        .sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:899;}
        .sidebar-overlay.active{display:block;}

        .tab-content{display:none;animation:fade .3s;}
        .tab-content.active{display:block;}
        @keyframes fade{from{opacity:0;transform:translateY(8px);}to{opacity:1;transform:none;}}

        /* ===== الإحصائيات ===== */
        .stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px;margin-bottom:25px;}
        .stat-card{background:var(--m-card);padding:22px;border-radius:12px;border:1px solid var(--m-border);position:relative;overflow:hidden;transition:.25s;}
        .stat-card:hover{transform:translateY(-3px);border-color:#3a3a45;}
        .stat-card h3{font-size:34px;margin:6px 0;font-weight:800;}
        .stat-card p{color:var(--text-d);font-size:13px;font-weight:600;}
        .stat-card i.bgi{position:absolute;left:15px;top:28%;font-size:55px;opacity:.06;}
        .stat-card .topline{height:3px;width:40px;border-radius:3px;margin-bottom:10px;}

        /* ===== البطاقات ===== */
        .card{background:var(--m-card);padding:28px;border-radius:12px;margin-bottom:25px;border:1px solid var(--m-border);}
        .card h2{font-size:20px;margin-bottom:22px;border-right:4px solid var(--main-red);padding-right:14px;display:flex;align-items:center;gap:8px;}
        .form-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:14px;}

        /* ===== الجداول ===== */
        .table-responsive{overflow-x:auto;border-radius:10px;border:1px solid var(--m-border);}
        table{width:100%;border-collapse:collapse;text-align:right;min-width:850px;}
        th{background:var(--m-card2);padding:14px 13px;color:#fff;font-size:13px;border-bottom:1px solid #333;white-space:nowrap;}
        td{padding:13px;border-bottom:1px solid #202028;font-size:13px;white-space:nowrap;vertical-align:middle;}
        tr:hover td{background:#22222b;}
        .allow-copy{user-select:text;font-family:monospace;}

        .badge{padding:4px 10px;border-radius:6px;font-size:11px;background:#333;font-weight:bold;display:inline-block;}
        .b-green{background:rgba(46,213,115,.15);color:var(--green);border:1px solid var(--green);}
        .b-red{background:rgba(210,0,0,.15);color:#ff4c4c;border:1px solid var(--main-red);}
        .b-gold{background:rgba(236,204,104,.12);color:var(--gold);border:1px solid var(--gold);}
        .b-orange{background:rgba(255,165,2,.15);color:var(--orange);border:1px solid var(--orange);}
        .b-gray{background:#2a2a33;color:#999;border:1px solid #444;}

        /* أزرار الإجراءات في الجدول */
        .act-btns{display:flex;gap:4px;flex-wrap:wrap;}
        .ico-btn{width:32px;height:32px;border-radius:6px;display:inline-flex;align-items:center;justify-content:center;
            background:#23232c;border:1px solid #34343f;color:#aaa;cursor:pointer;transition:.2s;font-size:13px;}
        .ico-btn:hover{color:#fff;transform:translateY(-2px);}
        .ico-btn.green:hover{background:var(--green);border-color:var(--green);}
        .ico-btn.blue:hover{background:var(--blue);border-color:var(--blue);}
        .ico-btn.orange:hover{background:var(--orange);border-color:var(--orange);}
        .ico-btn.red:hover{background:var(--main-red);border-color:var(--main-red);}
        .ico-btn.gold:hover{background:var(--gold);border-color:var(--gold);color:#000;}

        /* ===== النوافذ المنبثقة (Modals) ===== */
        .modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.8);backdrop-filter:blur(6px);z-index:9999;
            display:none;align-items:center;justify-content:center;padding:20px;}
        .modal-overlay.active{display:flex;animation:fade .2s;}
        .modal{background:var(--m-card);border:1px solid var(--m-border);border-radius:14px;width:100%;max-width:520px;max-height:90vh;overflow-y:auto;}
        .modal-head{padding:20px 25px;border-bottom:1px solid var(--m-border);display:flex;justify-content:space-between;align-items:center;}
        .modal-head h3{font-size:18px;display:flex;align-items:center;gap:9px;}
        .modal-close{background:none;border:none;color:#888;font-size:22px;cursor:pointer;width:auto;}
        .modal-close:hover{color:#fff;}
        .modal-body{padding:25px;}
        .modal-info{background:var(--m-card2);padding:12px 15px;border-radius:8px;margin-bottom:18px;font-size:13px;color:#bbb;border-right:3px solid var(--main-red);}

        /* شارة صلاحية التعديل */
        .unlock-badge{display:inline-flex;align-items:center;gap:8px;padding:10px 16px;border-radius:8px;
            font-size:13px;font-weight:700;cursor:pointer;margin-bottom:16px;transition:.25s;user-select:none;}
        .unlock-badge.closed{background:rgba(255,165,2,.12);border:1px solid var(--orange);color:var(--orange);}
        .unlock-badge.closed:hover{background:rgba(255,165,2,.2);}
        .unlock-badge.open{background:rgba(46,213,115,.12);border:1px solid var(--green);color:var(--green);cursor:default;}

        /* ===== واجهة تحديث النظام الاحترافية ===== */
        .upd-source{display:flex;align-items:center;gap:14px;background:var(--m-card2);
            border:1px solid var(--m-border);border-radius:12px;padding:16px 18px;margin-bottom:22px;}
        .upd-source-icon{width:48px;height:48px;border-radius:12px;background:#0d1117;border:1px solid #30363d;
            display:flex;align-items:center;justify-content:center;font-size:24px;color:#fff;flex-shrink:0;}
        .upd-source-info{flex:1;min-width:0;}
        .upd-source-title{font-size:16px;font-weight:800;color:#fff;}
        .upd-source-sub{font-size:12px;color:var(--text-d);margin-top:2px;}
        .upd-source-badge{font-size:12px;font-weight:700;color:var(--green);
            background:rgba(46,213,115,.12);border:1px solid var(--green);padding:5px 12px;border-radius:20px;white-space:nowrap;}
        .upd-source-badge.busy{color:var(--orange);background:rgba(255,165,2,.12);border-color:var(--orange);}
        .upd-source-badge.err{color:#ff4c4c;background:rgba(210,0,0,.12);border-color:var(--main-red);}

        /* المراحل */
        .upd-stage{background:var(--m-card2);border:1px solid var(--m-border);border-radius:12px;padding:22px 20px;margin-bottom:20px;}
        .upd-steps{display:flex;justify-content:space-between;position:relative;margin-bottom:24px;}
        .upd-steps::before{content:'';position:absolute;top:16px;right:8%;left:8%;height:2px;background:#30303c;z-index:0;}
        .upd-step{display:flex;flex-direction:column;align-items:center;gap:7px;position:relative;z-index:1;flex:1;}
        .upd-step .us-dot{width:34px;height:34px;border-radius:50%;background:#26262f;border:2px solid #3a3a45;
            display:flex;align-items:center;justify-content:center;transition:.35s;}
        .upd-step i{position:absolute;top:8px;font-size:15px;color:#666;transition:.35s;}
        .upd-step span{font-size:12px;color:#777;font-weight:600;transition:.25s;}
        .upd-step.active .us-dot{background:var(--blue);border-color:var(--blue);box-shadow:0 0 0 4px rgba(55,66,250,.18);}
        .upd-step.active i{color:#fff;}
        .upd-step.active span{color:#fff;}
        .upd-step.done .us-dot{background:var(--green);border-color:var(--green);}
        .upd-step.done i{color:#fff;}
        .upd-step.done span{color:var(--green);}

        /* شريط التقدّم */
        .upd-progress-wrap{height:10px;background:#26262f;border-radius:20px;overflow:hidden;margin-bottom:9px;}
        .upd-progress-bar{height:100%;width:0%;border-radius:20px;
            background:linear-gradient(90deg,var(--blue),#6b7bff);transition:width .4s ease;
            background-size:200% 100%;animation:barflow 1.5s linear infinite;}
        @keyframes barflow{0%{background-position:0 0;}100%{background-position:-200% 0;}}
        .upd-progress-meta{display:flex;justify-content:space-between;font-size:12px;color:#999;margin-bottom:16px;}
        .upd-progress-meta #updPercent{color:#fff;font-weight:800;}

        /* قائمة الملفات */
        .upd-files{max-height:280px;overflow-y:auto;display:flex;flex-direction:column;gap:6px;}
        .upd-files::-webkit-scrollbar{width:6px;}
        .upd-files::-webkit-scrollbar-thumb{background:var(--main-red);border-radius:3px;}
        .upd-file{display:flex;align-items:center;gap:10px;background:#1b1b22;border:1px solid #26262f;
            border-radius:8px;padding:9px 12px;font-size:13px;animation:fileIn .3s ease;}
        @keyframes fileIn{from{opacity:0;transform:translateX(10px);}to{opacity:1;transform:none;}}
        .upd-file .uf-ico{width:26px;height:26px;border-radius:6px;background:#0d1117;
            display:flex;align-items:center;justify-content:center;font-size:12px;flex-shrink:0;}
        .upd-file .uf-name{flex:1;color:#ddd;font-family:monospace;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
        .upd-file .uf-size{color:#666;font-size:11px;white-space:nowrap;}
        .upd-file .uf-check{color:var(--green);font-size:14px;}
        .upd-file .uf-check.fail{color:#ff4c4c;}
        .upd-file .uf-check.skip{color:var(--gold);}

        /* النتيجة */
        .upd-result{border-radius:10px;padding:16px 18px;margin-bottom:18px;font-size:14px;font-weight:600;}
        .upd-result.ok{background:rgba(46,213,115,.1);border:1px solid var(--green);color:var(--green);}
        .upd-result.err{background:rgba(210,0,0,.1);border:1px solid var(--main-red);color:#ff6b6b;}
        .upd-result .ur-log{margin-top:10px;font-size:11px;color:#888;font-weight:400;line-height:1.7;
            background:#141419;border-radius:6px;padding:10px 12px;max-height:130px;overflow-y:auto;direction:ltr;text-align:left;}

        .upd-actions{display:flex;gap:10px;flex-wrap:wrap;}

        /* تنبيهات */
        .flash{padding:14px 18px;border-radius:8px;margin-bottom:22px;font-weight:bold;font-size:14px;}
        .flash.ok{background:rgba(46,213,115,.1);border-right:4px solid var(--green);color:var(--green);}
        .flash.err{background:rgba(210,0,0,.1);border-right:4px solid var(--main-red);color:#ff4c4c;}

        .empty{text-align:center;padding:40px;color:#666;}
        .online-dot{width:9px;height:9px;border-radius:50%;background:var(--green);display:inline-block;box-shadow:0 0 8px var(--green);animation:pulse 1.5s infinite;}
        @keyframes pulse{0%,100%{opacity:1;}50%{opacity:.4;}}

        .search-bar{margin-bottom:18px;}
        .search-bar input{max-width:400px;}

        @media(max-width:992px){
            .sidebar{transform:translateX(100%);box-shadow:-5px 0 25px rgba(0,0,0,.5);}
            .sidebar.open{transform:translateX(0);}
            .main-area{margin-right:0;}
            .menu-toggle{display:flex;align-items:center;justify-content:center;}
        }
        @media(max-width:768px){
            .dashboard{padding:14px;}
            .topbar{align-items:center;}
            .topbar .page-title{font-size:18px;}
            .card{padding:18px 14px;}
            .form-grid{grid-template-columns:1fr;}
            .uptime-chip{font-size:11px;padding:7px 10px;}
        }
    </style>
</head>
<body>

<?php if (!$logged_in && !$client_logged_in): ?>
    <!-- ===================== شاشة تسجيل الدخول (مدير / عميل) ===================== -->
    <div class="login-container">
        <div class="login-box">
            <h1>SHASHETY <span>PRO</span></h1>
            <p style="color:#aaa;font-size:14px;margin-bottom:22px;">لوحة التحكم | Admin &amp; Client Panel V5</p>
            <?php if ($error_message): ?>
                <div class="flash err" style="text-align:right;"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <!-- محدد الدور -->
            <div class="role-switch">
                <button type="button" class="role-btn active" data-role="admin" onclick="switchRole('admin')">
                    <i class="fas fa-user-shield"></i> مدير
                </button>
                <button type="button" class="role-btn" data-role="client" onclick="switchRole('client')">
                    <i class="fas fa-user"></i> عميل
                </button>
            </div>

            <form method="POST">
                <input type="hidden" name="login_role" id="login_role" value="admin">
                <div class="form-group">
                    <input type="password" name="password" id="login_pass" placeholder="كود المصادقة الخاص بالمدير..." required autofocus>
                </div>
                <p id="role_hint" style="color:#777;font-size:12px;margin:-8px 0 15px;text-align:right;">
                    أدخل كلمة مرور المدير للوصول للوحة الكاملة.
                </p>
                <button type="submit" name="login" class="btn" id="login_btn">تأكيد دخول المدير</button>
            </form>
        </div>
    </div>

<?php elseif ($client_logged_in): ?>
    <!-- ===================== لوحة العميل ===================== -->
    <?php
        $me = null;
        try {
            $st = $pdo->prepare("SELECT * FROM licenses WHERE id = ?");
            $st->execute([(int)($_SESSION['client_license_id'] ?? 0)]);
            $me = $st->fetch();
        } catch (PDOException $e) { $me = null; }

        $c_lifetime = $me && $me['license_type'] === 'lifetime';
        $c_exp = ($me && !empty($me['expiry_date'])) ? strtotime($me['expiry_date']) : null;
        $c_expired = (!$c_lifetime && $c_exp && $c_exp < time());
        $c_days_left = ($c_exp && !$c_expired) ? ceil(($c_exp - time()) / 86400) : null;
    ?>
    <div class="login-container">
        <div class="login-box" style="max-width:520px;text-align:right;">
            <div style="text-align:center;margin-bottom:20px;">
                <h1>SHASHETY <span>PRO</span></h1>
                <p style="color:#aaa;font-size:13px;">لوحة العميل</p>
            </div>

            <?php if ($success_message): ?><div class="flash ok"><?php echo htmlspecialchars($success_message); ?></div><?php endif; ?>
            <?php if ($error_message): ?><div class="flash err"><?php echo htmlspecialchars($error_message); ?></div><?php endif; ?>

            <?php if ($me): ?>
            <div style="line-height:2.2;font-size:14px;color:#ddd;">
                <div>👤 الاسم: <b style="color:#fff;"><?php echo htmlspecialchars($me['customer_name']); ?></b></div>
                <div>📦 الباقة: <span class="badge <?php echo $c_lifetime?'b-gold':'b-gray'; ?>"><?php echo planName($me['license_type'],$PLAN_OPTIONS); ?></span></div>
                <div>🔑 المفتاح: <span class="allow-copy" style="color:#ccc;"><?php echo htmlspecialchars($me['license_key']); ?></span></div>
                <div>📅 الانتهاء:
                    <?php if ($c_lifetime): ?>
                        <span class="badge b-gold">دائم</span>
                    <?php elseif ($c_expired): ?>
                        <span class="badge b-red">منتهي</span>
                    <?php else: ?>
                        <b style="color:#fff;"><?php echo $c_exp ? date('Y-m-d', $c_exp) : '—'; ?></b>
                        <span class="badge b-green"><?php echo $c_days_left; ?> يوم متبقٍ</span>
                    <?php endif; ?>
                </div>
                <div>🔵 الحالة:
                    <?php if ($me['is_active'] && !$c_expired): ?>
                        <span class="badge b-green">فعّال</span>
                    <?php else: ?>
                        <span class="badge b-red">غير فعّال</span>
                    <?php endif; ?>
                </div>
            </div>
            <?php else: ?>
                <div class="empty">تعذّر جلب بيانات الاشتراك.</div>
            <?php endif; ?>

            <a href="?logout" class="logout" style="margin-top:22px;"><i class="fas fa-sign-out-alt"></i> تسجيل الخروج</a>
        </div>
    </div>

<?php else: ?>
    <!-- ===================== لوحة التحكم ===================== -->
    <div class="app-layout">

        <!-- ===== الشريط الجانبي (Sidebar) ===== -->
        <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-brand">
                <h1>SHASHETY <span>PRO</span></h1>
                <p><i class="fas fa-server"></i> لوحة التحكم الإدارية</p>
            </div>
            <nav class="sidebar-nav">
                <button class="nav-item active" data-tab="home"><i class="fas fa-gauge-high"></i> الواجهة الرئيسية
                    <?php if($stats['expiring_soon']>0): ?><span class="nav-badge"><?php echo $stats['expiring_soon']; ?></span><?php endif; ?></button>
                <button class="nav-item" data-tab="subs"><i class="fas fa-users-gear"></i> إدارة المشتركين
                    <span class="nav-badge"><?php echo $stats['total']; ?></span></button>
                <button class="nav-item" data-tab="online"><i class="fas fa-wifi"></i> المتصلون
                    <?php if($stats['online']>0): ?><span class="nav-badge"><?php echo $stats['online']; ?></span><?php endif; ?></button>
                <button class="nav-item" data-tab="add"><i class="fas fa-plus-circle"></i> تفعيل مستخدم</button>
                <button class="nav-item" data-tab="backup"><i class="fas fa-database"></i> النسخ الاحتياطي</button>
                <button class="nav-item" data-tab="update"><i class="fas fa-cloud-arrow-down"></i> تحديث النظام</button>
                <button class="nav-item" data-tab="settings"><i class="fas fa-shield-halved"></i> الإعدادات</button>
            </nav>
            <div class="sidebar-foot">
                <a href="?logout" class="logout"><i class="fas fa-sign-out-alt"></i> إنهاء الجلسة</a>
            </div>
        </aside>

        <!-- ===== المحتوى الرئيسي ===== -->
        <div class="main-area">
        <div class="dashboard">

            <!-- شريط علوي -->
            <div class="topbar">
                <div style="display:flex;align-items:center;gap:12px;">
                    <button class="menu-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
                    <div class="page-title"><i class="fas fa-gauge-high" id="pageTitleIcon"></i> <span id="pageTitleText">الواجهة الرئيسية</span></div>
                </div>
                <div class="uptime-chip"><i class="fas fa-clock"></i> مدة عمل النظام: <?php echo $uptime_str; ?></div>
            </div>

            <?php if ($success_message): ?><div class="flash ok">🟢 <?php echo htmlspecialchars($success_message); ?></div><?php endif; ?>
            <?php if ($error_message): ?><div class="flash err">🔴 <?php echo htmlspecialchars($error_message); ?></div><?php endif; ?>

        <!-- ============ التبويب 1: الواجهة الرئيسية ============ -->
        <div class="tab-content active" id="tab-home">

        <!-- ===== بطاقات الإحصائيات ===== -->
        <div class="stats">
            <div class="stat-card">
                <div class="topline" style="background:#fff;"></div>
                <i class="fas fa-users bgi"></i>
                <p>إجمالي المشتركين</p><h3 style="color:#fff;"><?php echo $stats['total']; ?></h3>
            </div>
            <div class="stat-card">
                <div class="topline" style="background:var(--green);"></div>
                <i class="fas fa-check-circle bgi"></i>
                <p>المشتركون الفعّالون</p><h3 style="color:var(--green);"><?php echo $stats['active_sub']; ?></h3>
            </div>
            <div class="stat-card">
                <div class="topline" style="background:var(--main-red);"></div>
                <i class="fas fa-ban bgi"></i>
                <p>المنتهي اشتراكهم</p><h3 style="color:var(--main-red);"><?php echo $stats['expired']; ?></h3>
            </div>
            <div class="stat-card">
                <div class="topline" style="background:var(--orange);"></div>
                <i class="fas fa-hourglass-half bgi"></i>
                <p>ينتهي خلال 3 أيام</p><h3 style="color:var(--orange);"><?php echo $stats['expiring_soon']; ?></h3>
            </div>
            <div class="stat-card">
                <div class="topline" style="background:var(--blue);"></div>
                <i class="fas fa-wifi bgi"></i>
                <p>المتصلون الآن</p><h3 style="color:var(--blue);"><?php echo $stats['online']; ?> <span class="online-dot"></span></h3>
            </div>
            <div class="stat-card">
                <div class="topline" style="background:var(--gold);"></div>
                <i class="fas fa-star bgi"></i>
                <p>باقات مدى الحياة</p><h3 style="color:var(--gold);"><?php echo $stats['lifetime']; ?></h3>
            </div>
        </div>
            <div class="card">
                <h2><i class="fas fa-hourglass-half" style="color:var(--orange);"></i> اشتراكات تنتهي خلال 3 أيام</h2>
                <?php if (empty($expiring_list)): ?>
                    <div class="empty"><i class="fas fa-circle-check" style="font-size:40px;color:var(--green);margin-bottom:12px;display:block;"></i>لا توجد اشتراكات على وشك الانتهاء خلال الأيام الثلاثة القادمة.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table>
                            <thead><tr><th>#</th><th>العميل</th><th>الهاتف</th><th>الباقة</th><th>تاريخ الانتهاء</th><th>المتبقي</th><th>إجراء سريع</th></tr></thead>
                            <tbody>
                            <?php foreach($expiring_list as $lic): ?>
                                <tr>
                                    <td>#<?php echo $lic['id']; ?></td>
                                    <td><b><?php echo htmlspecialchars($lic['customer_name']); ?></b></td>
                                    <td class="allow-copy"><?php echo htmlspecialchars($lic['phone']); ?></td>
                                    <td><span class="badge b-gray"><?php echo planName($lic['license_type'],$PLAN_OPTIONS); ?></span></td>
                                    <td><?php echo date('Y-m-d H:i', strtotime($lic['expiry_date'])); ?></td>
                                    <td>
                                        <span class="badge <?php echo $lic['_days_left']<=1?'b-red':'b-orange'; ?>">
                                            <?php echo $lic['_days_left']; ?> يوم
                                        </span>
                                    </td>
                                    <td>
                                        <button class="ico-btn green" title="تمديد سريع"
                                            onclick='openExtend(<?php echo $lic["id"]; ?>,<?php echo json_encode($lic["customer_name"]); ?>)'>
                                            <i class="fas fa-calendar-plus"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- ملخص سريع للحالة -->
            <div class="card">
                <h2><i class="fas fa-chart-pie" style="color:var(--blue);"></i> ملخص الحالة</h2>
                <div class="stats" style="margin-bottom:0;">
                    <div class="stat-card"><p>اشتراكات معطّلة (موقوفة)</p><h3 style="color:var(--orange);font-size:28px;"><?php echo $stats['inactive']; ?></h3></div>
                    <div class="stat-card"><p>اشتراكات فعّالة</p><h3 style="color:var(--green);font-size:28px;"><?php echo $stats['active_sub']; ?></h3></div>
                    <div class="stat-card"><p>متصل خلال 10 دقائق</p><h3 style="color:var(--blue);font-size:28px;"><?php echo $stats['online']; ?></h3></div>
                    <div class="stat-card"><p>مدة عمل النظام</p><h3 style="color:#fff;font-size:18px;margin-top:14px;"><?php echo $up_days; ?> يوم</h3></div>
                </div>
            </div>
        </div>

        <!-- ============ التبويب 2: إدارة المشتركين ============ -->
        <div class="tab-content" id="tab-subs">
            <div class="card">
                <h2><i class="fas fa-users-gear" style="color:var(--main-red);"></i> جميع المشتركين (<?php echo $stats['total']; ?>)</h2>
                <div class="unlock-badge <?php echo isEditUnlocked() ? 'open' : 'closed'; ?>" id="unlockBadge" onclick="<?php echo isEditUnlocked() ? '' : 'openUnlock()'; ?>">
                    <?php if (isEditUnlocked()): ?>
                        <i class="fas fa-lock-open"></i> صلاحية التعديل مفتوحة · كل الخيارات متاحة حتى تسجيل الخروج
                    <?php else: ?>
                        <i class="fas fa-lock"></i> صلاحية التعديل مغلقة — اضغط للفتح
                    <?php endif; ?>
                </div>
                <div class="search-bar">
                    <input type="text" id="subSearch" placeholder="🔍 بحث بالاسم / الهاتف / المفتاح / المعرّف..." onkeyup="filterTable()">
                </div>
                <div class="table-responsive">
                    <table id="subsTable">
                        <thead>
                            <tr>
                                <th>#</th><th>العميل</th><th>البصمة (Machine ID)</th><th>المفتاح</th>
                                <th>الباقة</th><th>الانتهاء</th><th>الحالة</th><th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($licenses)): ?>
                            <tr><td colspan="8"><div class="empty">لا يوجد مشتركون بعد.</div></td></tr>
                        <?php else: foreach($licenses as $lic):
                            $is_lifetime = ($lic['license_type']==='lifetime');
                            $exp = $lic['expiry_date'] ? strtotime($lic['expiry_date']) : null;
                            $is_expired = (!$is_lifetime && $exp && $exp < time());
                            // تحديد شارة الحالة
                            if (!$lic['is_active']) { $st='b-orange'; $stt='موقوف'; }
                            elseif ($is_expired) { $st='b-red'; $stt='منتهي'; }
                            else { $st='b-green'; $stt='فعّال'; }
                            $j = json_encode([
                                'id'=>$lic['id'],'customer_name'=>$lic['customer_name'],'phone'=>$lic['phone'],
                                'email'=>$lic['email'],'domain'=>$lic['domain'],'notes'=>$lic['notes'],
                                'license_type'=>$lic['license_type'],'expiry_date'=>$lic['expiry_date']
                            ], JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT);
                        ?>
                            <tr data-search="<?php echo htmlspecialchars(strtolower($lic['customer_name'].' '.$lic['phone'].' '.$lic['license_key'].' '.$lic['machine_id'])); ?>"
                                style="<?php echo !$lic['is_active']?'opacity:.55':''; ?>">
                                <td>#<?php echo $lic['id']; ?></td>
                                <td><b style="color:#fff;"><?php echo htmlspecialchars($lic['customer_name']); ?></b><br>
                                    <small style="color:#777;"><?php echo htmlspecialchars($lic['phone']); ?></small></td>
                                <td class="allow-copy" style="color:#999;max-width:160px;overflow:hidden;text-overflow:ellipsis;" title="<?php echo htmlspecialchars($lic['machine_id']); ?>">
                                    <?php echo htmlspecialchars(substr($lic['machine_id'],0,18)).'…'; ?></td>
                                <td style="color:#555;letter-spacing:2px;" title="المفتاح مخفي لأسباب أمنية">•••• •••• ••••</td>
                                <td><span class="badge <?php echo $is_lifetime?'b-gold':'b-gray'; ?>"><?php echo planName($lic['license_type'],$PLAN_OPTIONS); ?></span></td>
                                <td><?php echo $exp ? date('Y-m-d', $exp) : 'دائم'; ?></td>
                                <td><span class="badge <?php echo $st; ?>"><?php echo $stt; ?></span></td>
                                <td>
                                    <div class="act-btns">
                                        <button class="ico-btn blue" title="تعديل البيانات" onclick='openEdit(<?php echo $j; ?>)'><i class="fas fa-pen"></i></button>
                                        <button class="ico-btn gold" title="تعديل تاريخ الانتهاء" onclick='openSetExpiry(<?php echo $j; ?>)'><i class="fas fa-calendar-day"></i></button>
                                        <button class="ico-btn green" title="تمديد صلاحية" onclick='openExtend(<?php echo $lic["id"]; ?>,<?php echo json_encode($lic["customer_name"],JSON_UNESCAPED_UNICODE); ?>)'><i class="fas fa-calendar-plus"></i></button>
                                        <button class="ico-btn orange" title="تعويض" onclick='openComp(<?php echo $lic["id"]; ?>,<?php echo json_encode($lic["customer_name"],JSON_UNESCAPED_UNICODE); ?>)'><i class="fas fa-gift"></i></button>
                                        <button class="ico-btn blue" title="تغيير الباقة" onclick='openPlan(<?php echo $j; ?>)'><i class="fas fa-box-open"></i></button>
                                        <a class="ico-btn <?php echo $lic['is_active']?'orange':'green'; ?>" title="<?php echo $lic['is_active']?'تعطيل':'تفعيل'; ?>" href="?toggle&id=<?php echo $lic['id']; ?>&token=<?php echo $TKN; ?>"><i class="fas fa-power-off"></i></a>
                                        <button type="button" class="ico-btn red" title="إنهاء الاشتراك فوراً" onclick='otpAction("terminate",<?php echo $lic["id"]; ?>,<?php echo json_encode($lic["customer_name"],JSON_UNESCAPED_UNICODE); ?>)'><i class="fas fa-hand"></i></button>
                                        <button type="button" class="ico-btn red" title="حذف نهائي" onclick='otpAction("delete",<?php echo $lic["id"]; ?>,<?php echo json_encode($lic["customer_name"],JSON_UNESCAPED_UNICODE); ?>)'><i class="fas fa-trash"></i></button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ============ التبويب 3: المتصلون ============ -->
        <div class="tab-content" id="tab-online">
            <div class="card">
                <h2><i class="fas fa-wifi" style="color:var(--blue);"></i> المتصلون الآن (آخر فحص خلال 10 دقائق)</h2>
                <?php if (empty($online_list)): ?>
                    <div class="empty"><i class="fas fa-plug-circle-xmark" style="font-size:40px;margin-bottom:12px;display:block;"></i>لا يوجد مشتركون متصلون حالياً.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table>
                            <thead><tr><th>الحالة</th><th>العميل</th><th>الباقة</th><th>آخر اتصال</th><th>IP</th></tr></thead>
                            <tbody>
                            <?php foreach($online_list as $lic): ?>
                                <tr>
                                    <td><span class="online-dot"></span> <span style="color:var(--green);font-size:12px;">متصل</span></td>
                                    <td><b><?php echo htmlspecialchars($lic['customer_name']); ?></b><br><small style="color:#777;"><?php echo htmlspecialchars($lic['phone']); ?></small></td>
                                    <td><span class="badge b-gray"><?php echo planName($lic['license_type'],$PLAN_OPTIONS); ?></span></td>
                                    <td><?php echo date('Y-m-d H:i:s', strtotime($lic['last_check'])); ?></td>
                                    <td class="allow-copy" style="color:#888;"><?php echo htmlspecialchars($lic['ip_address'] ?: '—'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <p style="margin-top:14px;color:#666;font-size:12px;"><i class="fas fa-info-circle"></i> يعتمد "المتصل" على آخر فحص للرخصة من جهاز العميل (حقل last_check). يُحدّث تلقائياً عند كل تحقق.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- ============ التبويب 4: تفعيل مستخدم ============ -->
        <div class="tab-content" id="tab-add">
            <div class="card">
                <h2><i class="fas fa-plus-circle" style="color:var(--green);"></i> تفعيل / إنشاء رخصة لمستخدم جديد</h2>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $TKN; ?>">
                    <div class="form-grid">
                        <div class="form-group"><label>اسم العميل *</label><input type="text" name="customer_name" required></div>
                        <div class="form-group"><label>رقم الاتصال *</label><input type="tel" name="phone" required></div>
                        <div class="form-group"><label>البصمة / Machine ID *</label><input class="allow-copy" type="text" name="machine_id" placeholder="الصق السيريال هنا" required></div>
                        <div class="form-group"><label>البريد الإلكتروني</label><input type="email" name="email"></div>
                        <div class="form-group"><label>الدومين</label><input type="text" name="domain"></div>
                        <div class="form-group"><label>نوع الباقة *</label>
                            <select name="license_type" required>
                                <option value="" disabled selected>اختر الباقة...</option>
                                <option value="trial_1day">تجريبي - يوم (24 ساعة)</option>
                                <option value="trial_1week">تجريبي - أسبوع</option>
                                <option value="trial_1month">تجريبي - شهر</option>
                                <option value="monthly">شهري (30 يوم)</option>
                                <option value="yearly">سنوي (12 شهر)</option>
                                <option value="lifetime">مدى الحياة (دائم)</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group"><label>ملاحظات</label><textarea name="notes" rows="2"></textarea></div>
                    <button type="submit" name="add_license" class="btn btn-green" style="width:auto;padding:13px 35px;"><i class="fas fa-check"></i> إنشاء وتوليد المفتاح</button>
                </form>
            </div>
        </div>

        <!-- ============ التبويب 5: النسخ الاحتياطي ============ -->
        <div class="tab-content" id="tab-backup">
            <div class="card">
                <h2><i class="fas fa-cloud-arrow-down" style="color:var(--blue);"></i> تصدير نسخة احتياطية</h2>
                <p style="color:#bbb;margin-bottom:18px;font-size:14px;">قم بتنزيل نسخة كاملة من جميع المشتركين والرخص بصيغة JSON. احتفظ بها في مكان آمن.</p>
                <a href="?export&token=<?php echo $TKN; ?>" class="btn btn-blue btn-sm" style="display:inline-flex;align-items:center;gap:8px;">
                    <i class="fas fa-download"></i> تصدير الآن (<?php echo $stats['total']; ?> سجل)
                </a>
            </div>

            <div class="card">
                <h2><i class="fas fa-cloud-arrow-up" style="color:var(--green);"></i> استيراد نسخة احتياطية</h2>
                <div class="modal-info">
                    <b>وضع الدمج:</b> يضيف السجلات الجديدة ويتجاوز المكرر.<br>
                    <b>وضع الاستبدال:</b> ⚠️ يحذف كل البيانات الحالية ويستبدلها بالملف (لا يمكن التراجع).
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo $TKN; ?>">
                    <div class="form-grid">
                        <div class="form-group"><label>ملف النسخة (JSON)</label><input type="file" name="backup_file" accept=".json" required></div>
                        <div class="form-group"><label>وضع الاستيراد</label>
                            <select name="import_mode">
                                <option value="merge">دمج (آمن - يتجاوز المكرر)</option>
                                <option value="replace">استبدال كامل (حذف ثم إضافة)</option>
                            </select>
                        </div>
                    </div>
                    <button type="submit" name="import_backup" class="btn btn-sm" onclick="return confirm('تأكيد عملية الاستيراد؟')"><i class="fas fa-upload"></i> بدء الاستيراد</button>
                </form>
            </div>
        </div>

        <!-- ============ تبويب: تحديث النظام ============ -->
        <div class="tab-content" id="tab-update">
            <div class="card">
                <h2 style="justify-content:space-between;">
                    <span style="display:flex;align-items:center;gap:8px;"><i class="fas fa-cloud-arrow-down" style="color:var(--blue);"></i> تحديث النظام</span>
                    <span class="upd-source-badge" id="updBadge" style="font-size:12px;"><i class="fas fa-circle-check"></i> جاهز</span>
                </h2>

                <!-- شاشة التحميل الاحترافية (تظهر أثناء التحديث) -->
                <div class="upd-stage" id="updStage" style="display:none;">
                    <!-- الحلقات/المراحل -->
                    <div class="upd-steps" id="updSteps">
                        <div class="upd-step" data-step="download"><span class="us-dot"></span><i class="fas fa-download"></i><span>تنزيل</span></div>
                        <div class="upd-step" data-step="extract"><span class="us-dot"></span><i class="fas fa-box-open"></i><span>استخراج</span></div>
                        <div class="upd-step" data-step="install"><span class="us-dot"></span><i class="fas fa-copy"></i><span>تثبيت</span></div>
                        <div class="upd-step" data-step="done"><span class="us-dot"></span><i class="fas fa-check"></i><span>اكتمال</span></div>
                    </div>

                    <!-- شريط التقدّم -->
                    <div class="upd-progress-wrap">
                        <div class="upd-progress-bar" id="updBar"></div>
                    </div>
                    <div class="upd-progress-meta">
                        <span id="updPercent">0%</span>
                        <span id="updStatusText">جارٍ التحضير…</span>
                    </div>

                    <!-- قائمة الملفات المُحدّثة (تظهر كالمواقع العالمية) -->
                    <div class="upd-files" id="updFiles"></div>
                </div>

                <!-- النتيجة النهائية -->
                <div class="upd-result" id="updResult" style="display:none;"></div>

                <!-- أزرار التحكم -->
                <div class="upd-actions">
                    <button type="button" class="btn btn-blue btn-sm" id="btnUpdate" onclick="startUpdate()" style="display:inline-flex;align-items:center;gap:8px;">
                        <i class="fas fa-download"></i> تحديث النظام الآن
                    </button>
                    <button type="button" class="btn btn-dark btn-sm" id="btnDiagnose" onclick="document.getElementById('diagForm').submit();" style="display:inline-flex;align-items:center;gap:8px;">
                        <i class="fas fa-stethoscope"></i> فحص البيئة
                    </button>
                </div>
                <form method="POST" id="diagForm" style="display:none;">
                    <input type="hidden" name="csrf_token" value="<?php echo $TKN; ?>">
                    <input type="hidden" name="update_diagnose" value="1">
                </form>

                <p style="margin-top:16px;color:#666;font-size:12px;">
                    <i class="fas fa-shield-halved" style="color:var(--gold);"></i>
                    ملف <code>config.php</code> محمي ولن يُستبدل · يُنصح بأخذ نسخة احتياطية قبل التحديث.
                </p>
            </div>
        </div>

        <!-- ============ التبويب 6: الإعدادات ============ -->
        <div class="tab-content" id="tab-settings">
            <div class="card">
                <h2><i class="fas fa-key" style="color:var(--gold);"></i> تغيير كلمة مرور الإدارة</h2>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $TKN; ?>">
                    <div class="form-grid">
                        <div class="form-group"><label>كلمة المرور الحالية</label><input type="password" name="old_password" required></div>
                        <div class="form-group"><label>كلمة المرور الجديدة (5 أحرف+)</label><input type="password" name="new_password" required></div>
                    </div>
                    <button type="submit" name="update_admin_password" class="btn btn-dark btn-sm"><i class="fas fa-shield-alt"></i> تحديث كلمة المرور</button>
                </form>
            </div>
            <div class="card">
                <h2><i class="fas fa-circle-info" style="color:var(--blue);"></i> معلومات النظام</h2>
                <div style="line-height:2;color:#ccc;font-size:14px;">
                    <div>🕐 مدة عمل النظام: <b style="color:#fff;"><?php echo $uptime_str; ?></b></div>
                    <div>👥 إجمالي المشتركين: <b style="color:#fff;"><?php echo $stats['total']; ?></b></div>
                    <div>✅ الفعّالون: <b style="color:var(--green);"><?php echo $stats['active_sub']; ?></b></div>
                    <div>❌ المنتهون: <b style="color:var(--main-red);"><?php echo $stats['expired']; ?></b></div>
                    <div>📡 المتصلون: <b style="color:var(--blue);"><?php echo $stats['online']; ?></b></div>
                </div>
            </div>
        </div>

        </div><!-- /dashboard -->
        </div><!-- /main-area -->
    </div><!-- /app-layout -->

    <!-- ===================== النوافذ المنبثقة (Modals) ===================== -->

    <!-- تعديل البيانات -->
    <div class="modal-overlay" id="m-edit">
        <div class="modal">
            <div class="modal-head"><h3><i class="fas fa-pen" style="color:var(--blue);"></i> تعديل بيانات المشترك</h3><button class="modal-close" onclick="closeModal('m-edit')">&times;</button></div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $TKN; ?>">
                    <input type="hidden" name="id" id="e-id">
                    <div class="form-group"><label>اسم العميل</label><input type="text" name="customer_name" id="e-name" required></div>
                    <div class="form-group"><label>الهاتف</label><input type="tel" name="phone" id="e-phone" required></div>
                    <div class="form-group"><label>البريد الإلكتروني</label><input type="email" name="email" id="e-email"></div>
                    <div class="form-group"><label>الدومين</label><input type="text" name="domain" id="e-domain"></div>
                    <div class="form-group"><label>ملاحظات</label><textarea name="notes" id="e-notes" rows="2"></textarea></div>
                    <button type="submit" name="edit_subscriber" class="btn btn-blue"><i class="fas fa-save"></i> حفظ التعديلات</button>
                </form>
            </div>
        </div>
    </div>

    <!-- تعديل تاريخ الانتهاء -->
    <div class="modal-overlay" id="m-expiry">
        <div class="modal">
            <div class="modal-head"><h3><i class="fas fa-calendar-day" style="color:var(--gold);"></i> تعديل تاريخ الانتهاء</h3><button class="modal-close" onclick="closeModal('m-expiry')">&times;</button></div>
            <div class="modal-body">
                <div class="modal-info" id="ex-info"></div>
                <form method="POST" id="form-expiry" onsubmit="return gateOtp(event,'set_expiry',document.getElementById('ex-id').value)">
                    <input type="hidden" name="csrf_token" value="<?php echo $TKN; ?>">
                    <input type="hidden" name="id" id="ex-id">
                    <input type="hidden" name="otp_code" class="otp-code-field">
                    <div class="form-group"><label>تاريخ ووقت الانتهاء الجديد</label><input type="datetime-local" name="expiry_date" id="ex-date"></div>
                    <p style="color:#666;font-size:12px;margin-bottom:15px;">اتركه فارغاً لجعل الاشتراك دائماً (بلا انتهاء).</p>
                    <button type="submit" name="set_expiry" class="btn btn-orange" style="background:linear-gradient(135deg,#d4a017,var(--gold));color:#000;"><i class="fas fa-save"></i> حفظ التاريخ</button>
                </form>
            </div>
        </div>
    </div>

    <!-- تمديد الصلاحية -->
    <div class="modal-overlay" id="m-extend">
        <div class="modal">
            <div class="modal-head"><h3><i class="fas fa-calendar-plus" style="color:var(--green);"></i> تمديد الصلاحية</h3><button class="modal-close" onclick="closeModal('m-extend')">&times;</button></div>
            <div class="modal-body">
                <div class="modal-info" id="ext-info"></div>
                <form method="POST" id="form-extend" onsubmit="return gateOtp(event,'extend_license',document.getElementById('ext-id').value)">
                    <input type="hidden" name="csrf_token" value="<?php echo $TKN; ?>">
                    <input type="hidden" name="id" id="ext-id">
                    <input type="hidden" name="otp_code" class="otp-code-field">
                    <div class="form-group"><label>عدد الأيام المراد إضافتها</label>
                        <input type="number" name="days" id="ext-days" value="30" min="1" required>
                    </div>
                    <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:15px;">
                        <button type="button" class="btn btn-dark btn-sm" onclick="document.getElementById('ext-days').value=7">+7</button>
                        <button type="button" class="btn btn-dark btn-sm" onclick="document.getElementById('ext-days').value=30">+30</button>
                        <button type="button" class="btn btn-dark btn-sm" onclick="document.getElementById('ext-days').value=90">+90</button>
                        <button type="button" class="btn btn-dark btn-sm" onclick="document.getElementById('ext-days').value=365">+365</button>
                    </div>
                    <button type="submit" name="extend_license" class="btn btn-green"><i class="fas fa-plus"></i> تمديد الآن</button>
                </form>
            </div>
        </div>
    </div>

    <!-- تعويض -->
    <div class="modal-overlay" id="m-comp">
        <div class="modal">
            <div class="modal-head"><h3><i class="fas fa-gift" style="color:var(--orange);"></i> تعويض المشترك</h3><button class="modal-close" onclick="closeModal('m-comp')">&times;</button></div>
            <div class="modal-body">
                <div class="modal-info" id="comp-info"></div>
                <form method="POST" id="form-comp" onsubmit="return gateOtp(event,'compensate',document.getElementById('comp-id').value)">
                    <input type="hidden" name="csrf_token" value="<?php echo $TKN; ?>">
                    <input type="hidden" name="id" id="comp-id">
                    <input type="hidden" name="otp_code" class="otp-code-field">
                    <div class="form-group"><label>أيام التعويض المجانية</label><input type="number" name="comp_days" value="3" min="1" required></div>
                    <div class="form-group"><label>سبب التعويض</label><input type="text" name="comp_reason" placeholder="مثال: انقطاع الخدمة" required></div>
                    <button type="submit" name="compensate" class="btn btn-orange"><i class="fas fa-gift"></i> إضافة التعويض</button>
                </form>
            </div>
        </div>
    </div>

    <!-- تغيير الباقة -->
    <div class="modal-overlay" id="m-plan">
        <div class="modal">
            <div class="modal-head"><h3><i class="fas fa-box-open" style="color:var(--blue);"></i> تغيير الباقة</h3><button class="modal-close" onclick="closeModal('m-plan')">&times;</button></div>
            <div class="modal-body">
                <div class="modal-info" id="plan-info"></div>
                <form method="POST" id="form-plan" onsubmit="return gateOtp(event,'change_plan',document.getElementById('plan-id').value)">
                    <input type="hidden" name="csrf_token" value="<?php echo $TKN; ?>">
                    <input type="hidden" name="id" id="plan-id">
                    <input type="hidden" name="otp_code" class="otp-code-field">
                    <div class="form-group"><label>الباقة الجديدة</label>
                        <select name="new_license_type" id="plan-select" required>
                            <option value="trial_1day">تجريبي - يوم</option>
                            <option value="trial_1week">تجريبي - أسبوع</option>
                            <option value="trial_1month">تجريبي - شهر</option>
                            <option value="monthly">شهري</option>
                            <option value="yearly">سنوي</option>
                            <option value="lifetime">مدى الحياة</option>
                        </select>
                    </div>
                    <div class="form-group" style="display:flex;align-items:center;gap:10px;">
                        <input type="checkbox" name="recalc_expiry" id="plan-recalc" style="width:auto;" checked>
                        <label for="plan-recalc" style="margin:0;cursor:pointer;">إعادة حساب تاريخ الانتهاء حسب الباقة الجديدة (يبدأ من الآن)</label>
                    </div>
                    <button type="submit" name="change_plan" class="btn btn-blue"><i class="fas fa-exchange-alt"></i> تطبيق التغيير</button>
                </form>
            </div>
        </div>
    </div>

    <!-- ===== نافذة كود التحقق (OTP) ===== -->
    <div class="modal-overlay" id="m-otp">
        <div class="modal" style="max-width:420px;">
            <div class="modal-head">
                <h3><i class="fas fa-shield-halved" style="color:var(--green);"></i> كود التحقق</h3>
                <button class="modal-close" onclick="cancelOtp()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="modal-info" id="otp-info" style="text-align:center;">
                    <i class="fas fa-shield-halved" style="color:var(--green);font-size:26px;display:block;margin-bottom:8px;"></i>
                    سيُرسل كود تحقق إلى الإدارة لتأكيد العملية.
                </div>

                <!-- خطوة 1: إرسال الكود -->
                <div id="otp-step-send">
                    <button type="button" class="btn btn-green" onclick="requestOtp()" id="otp-send-btn">
                        <i class="fas fa-paper-plane"></i> إرسال الكود إلى الإدارة
                    </button>
                </div>

                <!-- خطوة 2: إدخال الكود -->
                <div id="otp-step-verify" style="display:none;">
                    <div class="form-group">
                        <label>أدخل الكود المكوّن من 6 أرقام</label>
                        <input type="text" id="otp-input" inputmode="numeric" maxlength="6" placeholder="______"
                            style="text-align:center;letter-spacing:10px;font-size:24px;font-weight:800;font-family:monospace;">
                    </div>
                    <div id="otp-msg" style="font-size:12px;text-align:center;margin-bottom:12px;min-height:16px;"></div>
                    <button type="button" class="btn btn-green" onclick="confirmOtp()" id="otp-confirm-btn">
                        <i class="fas fa-check"></i> تأكيد وتنفيذ العملية
                    </button>
                    <button type="button" class="btn btn-dark btn-sm" style="width:100%;margin-top:8px;" onclick="requestOtp()">
                        <i class="fas fa-rotate-right"></i> إعادة إرسال الكود
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- نموذج مخفي لعمليات الإنهاء/الحذف (يُرسل عبر OTP) -->
    <form method="POST" id="form-danger" style="display:none;">
        <input type="hidden" name="csrf_token" value="<?php echo $TKN; ?>">
        <input type="hidden" name="id" id="danger-id">
        <input type="hidden" name="otp_code" class="otp-code-field" id="danger-otp">
        <input type="hidden" name="danger_action" id="danger-action-field">
    </form>

    <!-- زر واتساب عائم -->
    <a href="https://wa.me/009647512328848" target="_blank" style="position:fixed;bottom:25px;left:25px;width:55px;height:55px;background:linear-gradient(135deg,#25D366,#128C7E);color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:26px;z-index:998;box-shadow:0 4px 20px rgba(37,211,102,.4);">
        <i class="fab fa-whatsapp"></i>
    </a>

<?php endif; ?>

<script>
    // ===== التنقل عبر الشريط الجانبي =====
    const tabMeta = {
        home:    {icon:'fa-gauge-high',     title:'الواجهة الرئيسية'},
        subs:    {icon:'fa-users-gear',     title:'إدارة المشتركين'},
        online:  {icon:'fa-wifi',           title:'المتصلون الآن'},
        add:     {icon:'fa-plus-circle',    title:'تفعيل مستخدم جديد'},
        backup:  {icon:'fa-database',       title:'النسخ الاحتياطي'},
        update:  {icon:'fa-cloud-arrow-down',title:'تحديث النظام'},
        settings:{icon:'fa-shield-halved',  title:'الإعدادات'}
    };
    document.querySelectorAll('.nav-item').forEach(btn=>{
        btn.addEventListener('click',()=>{
            const t = btn.dataset.tab;
            document.querySelectorAll('.nav-item').forEach(b=>b.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c=>c.classList.remove('active'));
            btn.classList.add('active');
            document.getElementById('tab-'+t).classList.add('active');
            // تحديث العنوان والأيقونة بالأعلى
            const meta = tabMeta[t];
            if(meta){
                document.getElementById('pageTitleText').textContent = meta.title;
                document.getElementById('pageTitleIcon').className = 'fas '+meta.icon;
            }
            // إغلاق الشريط الجانبي تلقائياً على الجوال
            if(window.innerWidth <= 992) closeSidebar();
            // التمرير لأعلى المحتوى
            window.scrollTo({top:0,behavior:'smooth'});
        });
    });

    // ===== فتح/إغلاق الشريط الجانبي (الجوال) =====
    function toggleSidebar(){
        document.getElementById('sidebar').classList.toggle('open');
        document.getElementById('sidebarOverlay').classList.toggle('active');
    }
    function closeSidebar(){
        document.getElementById('sidebar').classList.remove('open');
        document.getElementById('sidebarOverlay').classList.remove('active');
    }

    // ===== النوافذ =====
    function openModal(id){document.getElementById(id).classList.add('active');}
    function closeModal(id){document.getElementById(id).classList.remove('active');}
    document.querySelectorAll('.modal-overlay').forEach(o=>{
        o.addEventListener('click',e=>{if(e.target===o)o.classList.remove('active');});
    });

    // تعديل البيانات
    function openEdit(d){
        document.getElementById('e-id').value=d.id;
        document.getElementById('e-name').value=d.customer_name||'';
        document.getElementById('e-phone').value=d.phone||'';
        document.getElementById('e-email').value=d.email||'';
        document.getElementById('e-domain').value=d.domain||'';
        document.getElementById('e-notes').value=d.notes||'';
        openModal('m-edit');
    }

    // تعديل تاريخ الانتهاء
    function openSetExpiry(d){
        document.getElementById('ex-id').value=d.id;
        document.getElementById('ex-info').innerHTML='المشترك: <b>'+d.customer_name+'</b><br>الانتهاء الحالي: '+(d.expiry_date||'دائم');
        if(d.expiry_date){
            // تحويل "Y-m-d H:i:s" إلى صيغة datetime-local
            document.getElementById('ex-date').value=d.expiry_date.replace(' ','T').substring(0,16);
        }else{
            document.getElementById('ex-date').value='';
        }
        openModal('m-expiry');
    }

    // تمديد
    function openExtend(id,name){
        document.getElementById('ext-id').value=id;
        document.getElementById('ext-info').innerHTML='تمديد اشتراك: <b>'+name+'</b>';
        openModal('m-extend');
    }

    // تعويض
    function openComp(id,name){
        document.getElementById('comp-id').value=id;
        document.getElementById('comp-info').innerHTML='تعويض المشترك: <b>'+name+'</b>';
        openModal('m-comp');
    }

    // تغيير الباقة
    function openPlan(d){
        document.getElementById('plan-id').value=d.id;
        document.getElementById('plan-select').value=d.license_type;
        document.getElementById('plan-info').innerHTML='المشترك: <b>'+d.customer_name+'</b><br>الباقة الحالية: '+d.license_type;
        openModal('m-plan');
    }

    // ===== بحث في الجدول =====
    function filterTable(){
        const q=document.getElementById('subSearch').value.toLowerCase();
        document.querySelectorAll('#subsTable tbody tr').forEach(tr=>{
            const s=tr.getAttribute('data-search')||'';
            tr.style.display=s.includes(q)?'':'none';
        });
    }

    // إزالة رسالة إعادة الإرسال عند F5
    if(window.history.replaceState) window.history.replaceState(null,null,window.location.href);

    // ===== تبديل دور تسجيل الدخول (مدير / عميل) =====
    function switchRole(role){
        var roleField = document.getElementById('login_role');
        if(!roleField) return; // لسنا في صفحة الدخول
        roleField.value = role;
        document.querySelectorAll('.role-btn').forEach(function(b){
            b.classList.toggle('active', b.dataset.role === role);
        });
        var pass = document.getElementById('login_pass');
        var hint = document.getElementById('role_hint');
        var btn  = document.getElementById('login_btn');
        if(role === 'admin'){
            pass.placeholder = 'كود المصادقة الخاص بالمدير...';
            pass.type = 'password';
            if(hint) hint.textContent = 'أدخل كلمة مرور المدير للوصول للوحة الكاملة.';
            if(btn) btn.textContent = 'تأكيد دخول المدير';
        }else{
            pass.placeholder = 'مفتاح الرخصة أو المعرّف (Machine ID)...';
            pass.type = 'text';
            if(hint) hint.textContent = 'أدخل مفتاح الرخصة الخاص بك أو معرّف الجهاز لعرض حالة اشتراكك.';
            if(btn) btn.textContent = 'دخول العميل';
        }
        pass.value = '';
        pass.focus();
    }

    // ===== حماية النظام: منع F12 وأدوات المطور والقائمة اليمنى =====
    (function(){
        function blockMsg(){
            alert('🔒 النظام محمي — أدوات المطور معطّلة.');
        }
        // منع النقر بالزر الأيمن
        document.addEventListener('contextmenu', function(e){
            e.preventDefault();
            blockMsg();
            return false;
        });
        // منع اختصارات لوحة المفاتيح
        document.addEventListener('keydown', function(e){
            var k = e.keyCode || e.which;
            // F12
            if(k === 123){ e.preventDefault(); blockMsg(); return false; }
            // Ctrl+Shift+I / J / C  (أدوات المطور / الكونسول / الفاحص)
            if(e.ctrlKey && e.shiftKey && (k === 73 || k === 74 || k === 67)){
                e.preventDefault(); blockMsg(); return false;
            }
            // Ctrl+U (عرض المصدر)
            if(e.ctrlKey && k === 85){ e.preventDefault(); blockMsg(); return false; }
            // Ctrl+S (حفظ الصفحة)
            if(e.ctrlKey && k === 83){ e.preventDefault(); blockMsg(); return false; }
        });
    })();

    // ===== تحديث النظام الاحترافي (AJAX + شريط تقدّم + قائمة ملفات) =====
    function fmtSize(b){
        if(!b || b < 1024) return (b||0) + ' B';
        if(b < 1048576) return (b/1024).toFixed(1) + ' KB';
        return (b/1048576).toFixed(2) + ' MB';
    }
    function fileIcon(name){
        var ext = (name.split('.').pop()||'').toLowerCase();
        var map = {php:['#8892bf','fa-php','fab'], js:['#f7df1e','fa-js','fab'],
                   css:['#2965f1','fa-css3-alt','fab'], html:['#e34f26','fa-html5','fab'],
                   json:['#f0a500','fa-code','fas'], md:['#ccc','fa-file-lines','fas'],
                   png:['#2ed573','fa-image','fas'], jpg:['#2ed573','fa-image','fas'],
                   sql:['#ffa502','fa-database','fas']};
        var m = map[ext] || ['#999','fa-file','fas'];
        return '<i class="'+m[2]+' '+m[1]+'" style="color:'+m[0]+'"></i>';
    }
    function setStep(name, state){
        var el = document.querySelector('.upd-step[data-step="'+name+'"]');
        if(el){ el.classList.remove('active','done'); if(state) el.classList.add(state); }
    }
    function setBar(pct, txt){
        document.getElementById('updBar').style.width = pct + '%';
        document.getElementById('updPercent').textContent = Math.round(pct) + '%';
        if(txt) document.getElementById('updStatusText').textContent = txt;
    }

    function startUpdate(){
        if(!confirm('تأكيد تحديث النظام الآن؟')) return;
        var btn = document.getElementById('btnUpdate');
        var badge = document.getElementById('updBadge');
        var stage = document.getElementById('updStage');
        var result = document.getElementById('updResult');
        var filesBox = document.getElementById('updFiles');

        btn.disabled = true; btn.style.opacity = .6;
        result.style.display = 'none'; result.className = 'upd-result';
        filesBox.innerHTML = '';
        stage.style.display = 'block';
        badge.className = 'upd-source-badge busy';
        badge.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جارٍ التحديث';

        // مراحل تقديرية بصرية أثناء انتظار الخادم
        setStep('download','active'); setBar(15,'جارٍ تنزيل الأرشيف من GitHub…');
        var p = 15;
        var tick = setInterval(function(){
            if(p < 60){ p += 3; setBar(p); }
            if(p >= 30){ setStep('download','done'); setStep('extract','active'); setBar(p,'جارٍ استخراج الملفات…'); }
        }, 300);

        var fd = new FormData();
        fd.append('csrf_token', '<?php echo $TKN; ?>');
        fd.append('system_update', '1');

        fetch(window.location.href, {method:'POST', body:fd, headers:{'X-Requested-With':'XMLHttpRequest'}})
        .then(function(r){ return r.text(); })
        .then(function(txt){
            clearInterval(tick);
            var data;
            try { data = JSON.parse(txt); }
            catch(e){
                showResult(false, 'استجابة غير متوقعة من الخادم (قد تكون هناك رسالة خطأ PHP).', [txt.substring(0,400)], []);
                return;
            }
            setStep('download','done'); setStep('extract','done');
            setStep('install','active'); setBar(80,'جارٍ تثبيت الملفات…');

            var list = data.file_list || [];
            // عرض الملفات تدريجياً كالمواقع العالمية
            var i = 0;
            function drop(){
                if(i >= list.length){
                    setStep('install','done'); setStep('done','done');
                    setBar(100, data.ok ? 'اكتمل التحديث بنجاح' : 'انتهى مع أخطاء');
                    showResult(data.ok, data.msg, data.log || [], list);
                    return;
                }
                var f = list[i];
                var st = f.status === 'ok' ? '<i class="fas fa-circle-check uf-check"></i>'
                        : f.status === 'skip' ? '<i class="fas fa-shield-halved uf-check skip" title="محمي"></i>'
                        : '<i class="fas fa-circle-xmark uf-check fail"></i>';
                var row = document.createElement('div');
                row.className = 'upd-file';
                row.innerHTML = '<span class="uf-ico">'+fileIcon(f.name)+'</span>'
                              + '<span class="uf-name">'+f.name+'</span>'
                              + '<span class="uf-size">'+fmtSize(f.size)+'</span>' + st;
                filesBox.appendChild(row);
                filesBox.scrollTop = filesBox.scrollHeight;
                i++;
                setBar(80 + (i/list.length)*20);
                setTimeout(drop, 70);
            }
            drop();
        })
        .catch(function(err){
            clearInterval(tick);
            showResult(false, 'تعذّر الاتصال بالخادم: ' + err.message, [], []);
        });
    }

    function showResult(ok, msg, log, list){
        var btn = document.getElementById('btnUpdate');
        var badge = document.getElementById('updBadge');
        var result = document.getElementById('updResult');
        btn.disabled = false; btn.style.opacity = 1;

        if(ok){
            badge.className = 'upd-source-badge';
            badge.innerHTML = '<i class="fas fa-circle-check"></i> مُحدّث';
        }else{
            badge.className = 'upd-source-badge err';
            badge.innerHTML = '<i class="fas fa-triangle-exclamation"></i> فشل';
        }
        var logHtml = (log && log.length) ? '<div class="ur-log">'+log.join('<br>')+'</div>' : '';
        result.className = 'upd-result ' + (ok ? 'ok' : 'err');
        result.innerHTML = (ok ? '<i class="fas fa-circle-check"></i> ' : '<i class="fas fa-circle-exclamation"></i> ') + msg + logHtml;
        result.style.display = 'block';
    }

    // ===== نظام كود التحقق (OTP) =====
    var otpState = { action:null, id:null, pendingForm:null, isDanger:false, name:null };
    var TKN = '<?php echo $TKN; ?>';
    // حالة صلاحية التعديل — تُحقن من الخادم (مفتوحة طوال الجلسة)
    var editUnlocked = <?php echo isEditUnlocked() ? 'true' : 'false'; ?>;

    function isUnlocked(){ return editUnlocked === true; }

    // تنفيذ العملية المعلّقة مباشرة (بعد التأكد أن الصلاحية مفتوحة)
    function runPendingAction(){
        if(otpState.isDanger){
            document.getElementById('danger-id').value = otpState.id;
            var af = document.getElementById('danger-action-field');
            af.name = (otpState.action === 'terminate') ? 'terminate' : 'delete_sub';
            af.value = '1';
            document.getElementById('form-danger').submit();
        }else if(otpState.pendingForm){
            var f = otpState.pendingForm;
            if(!f.querySelector('input[name="'+otpState.action+'"]')){
                var hf = document.createElement('input');
                hf.type='hidden'; hf.name=otpState.action; hf.value='1';
                f.appendChild(hf);
            }
            f.submit();
        }
    }

    // بوابة العمليات ذات النماذج (تعديل/تمديد/تعويض/باقة)
    function gateOtp(ev, action, id){
        var form = ev.target;
        otpState = { action:action, id:id, pendingForm:form, isDanger:false };
        if(isUnlocked()){ return true; } // الصلاحية مفتوحة → إرسال مباشر
        ev.preventDefault();
        openOtp();
        return false;
    }

    // بوابة العمليات الخطرة (إنهاء/حذف) من أزرار الجدول
    function otpAction(action, id, name){
        var labels = { terminate:'إنهاء اشتراك', delete:'حذف نهائي للمشترك' };
        otpState = { action:action, id:id, pendingForm:null, isDanger:true, name:name };
        if(isUnlocked()){
            if(confirm((labels[action]||'تنفيذ') + ' للمشترك: ' + name + '؟')) runPendingAction();
            return;
        }
        openOtp();
    }

    function openOtp(){
        document.getElementById('otp-step-send').style.display = 'block';
        document.getElementById('otp-step-verify').style.display = 'none';
        document.getElementById('otp-input').value = '';
        document.getElementById('otp-msg').textContent = '';
        var sb = document.getElementById('otp-send-btn');
        sb.disabled = false; sb.innerHTML = '<i class="fas fa-paper-plane"></i> إرسال كود الفتح إلى الإدارة';
        document.getElementById('otp-info').innerHTML =
            '<i class="fas fa-shield-halved" style="color:var(--green);font-size:26px;display:block;margin-bottom:8px;"></i>'
            + 'سيُرسل كود إلى الإدارة يفتح <b>كل عمليات التعديل</b> حتى تسجيل الخروج.';
        openModal('m-otp');
    }

    function cancelOtp(){ closeModal('m-otp'); }

    // فتح نافذة الكود يدوياً من زر "فتح صلاحية التعديل"
    function openUnlock(){
        otpState = { action:null, id:null, pendingForm:null, isDanger:false };
        openOtp();
    }

    // طلب توليد الكود وإرساله إلى الإدارة
    function requestOtp(){
        var sb = document.getElementById('otp-send-btn');
        sb.disabled = true; sb.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جارٍ الإرسال…';
        var fd = new FormData();
        fd.append('csrf_token', TKN);
        fd.append('otp_request', '1');
        fetch(window.location.href, {method:'POST', body:fd, headers:{'X-Requested-With':'XMLHttpRequest'}})
        .then(function(r){ return r.json(); })
        .then(function(d){
            if(d.ok){
                document.getElementById('otp-step-send').style.display = 'none';
                document.getElementById('otp-step-verify').style.display = 'block';
                document.getElementById('otp-input').focus();
            }else{
                sb.disabled = false; sb.innerHTML = '<i class="fas fa-paper-plane"></i> إعادة المحاولة';
                alert(d.msg || 'تعذّر إرسال الكود.');
            }
        })
        .catch(function(){ sb.disabled = false; sb.innerHTML = '<i class="fas fa-paper-plane"></i> إعادة المحاولة'; alert('خطأ في الاتصال.'); });
    }

    // تأكيد الكود → فتح الصلاحية → تنفيذ العملية المعلّقة (إن وُجدت)
    function confirmOtp(){
        var code = document.getElementById('otp-input').value.trim();
        var msg = document.getElementById('otp-msg');
        if(!/^\d{6}$/.test(code)){
            msg.style.color = '#ff6b6b'; msg.textContent = 'أدخل كوداً صحيحاً من 6 أرقام.'; return;
        }
        msg.style.color = 'var(--green)'; msg.textContent = 'جارٍ التحقق…';

        var fd = new FormData();
        fd.append('csrf_token', TKN);
        fd.append('otp_verify', '1');
        fd.append('otp_code', code);
        fetch(window.location.href, {method:'POST', body:fd, headers:{'X-Requested-With':'XMLHttpRequest'}})
        .then(function(r){ return r.json(); })
        .then(function(d){
            if(d.ok){
                editUnlocked = true;
                msg.style.color = 'var(--green)'; msg.textContent = d.msg;
                updateUnlockBadge();
                // إن كانت هناك عملية معلّقة نفّذها فوراً، وإلا نغلق النافذة
                if(otpState.action || otpState.pendingForm || otpState.isDanger){
                    setTimeout(runPendingAction, 400);
                }else{
                    setTimeout(function(){ closeModal('m-otp'); }, 900);
                }
            }else{
                msg.style.color = '#ff6b6b'; msg.textContent = d.msg || 'الكود غير صحيح.';
            }
        })
        .catch(function(){ msg.style.color = '#ff6b6b'; msg.textContent = 'خطأ في الاتصال.'; });
    }

    // مؤشر حالة الصلاحية (مفتوح/مغلق طوال الجلسة)
    function updateUnlockBadge(){
        var badge = document.getElementById('unlockBadge');
        if(!badge) return;
        if(isUnlocked()){
            badge.className = 'unlock-badge open';
            badge.onclick = null;
            badge.innerHTML = '<i class="fas fa-lock-open"></i> صلاحية التعديل مفتوحة · كل الخيارات متاحة حتى تسجيل الخروج';
        }else{
            badge.className = 'unlock-badge closed';
            badge.onclick = openUnlock;
            badge.innerHTML = '<i class="fas fa-lock"></i> صلاحية التعديل مغلقة — اضغط للفتح';
        }
    }
    document.addEventListener('DOMContentLoaded', updateUnlockBadge);

    // تأكيد بالضغط على Enter داخل حقل الكود
    document.addEventListener('DOMContentLoaded', function(){
        var oi = document.getElementById('otp-input');
        if(oi){ oi.addEventListener('keydown', function(e){ if(e.key === 'Enter'){ e.preventDefault(); confirmOtp(); } }); }
    });
</script>
</body>
</html>
