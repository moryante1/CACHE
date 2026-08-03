<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 *  بوابة المشتركين — حماية الصفحة الرئيسية
 * ───────────────────────────────────────────────────────────────────────────
 *  تُستدعى من index.php **قبل أي إخراج**. هذا شرط أمني لا تجميلي: لو طُبعت
 *  بايت واحدة قبلها لفشل session_start ولخرج المحتوى إلى الشبكة قبل أن نقرّر
 *  إن كان الزائر يملك حق رؤيته — "الإخفاء بـ CSS" ليس حماية، فالمصدر مقروء.
 *
 *  المنطق:
 *    الحماية موقوفة            → لا شيء، الموقع مفتوح للجميع
 *    زائر إدارة                → تجاوز (يظل بإمكانك معاينة موقعك)
 *    غير مسجّل                 → صفحة دخول / إنشاء حساب  ← exit
 *    مسجّل لكن بلا اشتراك فعّال → صفحة إدخال الكوبون        ← exit
 *    مسجّل باشتراك فعّال        → المتابعة إلى المحتوى
 * ═══════════════════════════════════════════════════════════════════════════
 */

require_once __DIR__ . '/../../functions/subscriptions.php';

if (!subsEnsureSchema()) {
    // فشل تهيئة الجداول: نمرّر الزائر بدل إسقاط الموقع كله.
    // قفل الموقع بسبب عطل في قاعدة البيانات يحوّل خللاً صغيراً إلى انقطاع كامل.
    if (function_exists('logTo')) logTo('error', 'subscriber_gate: تعذّرت تهيئة جداول الاشتراكات');
    return;
}

if (!subsProtectionOn())        return;   // الخاصية موقوفة

if (!empty($__is_admin_visitor)) {
    // الإدارة تتجاوز البوابة لتستطيع معاينة الموقع.
    // نرفع علماً ليعرض subscriber_badge.php شريطاً واضحاً: بدونه يبدو
    // للمدير أن الحماية لا تعمل إطلاقاً، لأنه الشخص الوحيد المستثنى منها
    // وهو نفسه من يختبرها.
    $GLOBALS['__sg_admin_bypass'] = true;
    return;
}

if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

// ── اللغة ──
// index.php لا يحمّل ملفات اللغة إطلاقاً (لوحة الإدارة وحدها تفعل ذلك في
// includes/head.php)، لذا $t و$__cur_lang غير معرّفين هنا. نحمّلهما بأنفسنا،
// وإلا ظهرت البوابة بالعربية فقط مهما اختار الزائر — وبتحذيرات PHP.
if (isset($_GET['lang']) && in_array($_GET['lang'], ['ar','en','tr'], true)) {
    $_SESSION['lang'] = $_GET['lang'];
}
if (!isset($__cur_lang)) $__cur_lang = $_SESSION['lang'] ?? 'ar';
if (!isset($t) || !is_array($t)) {
    $__lf = dirname(__DIR__, 2) . '/lang/lang_' . $__cur_lang . '.php';
    $t = is_file($__lf) ? require $__lf : [];
    if (!is_array($t)) $t = [];
}

// ── تسجيل الخروج ──
if (isset($_GET['__su_logout'])) {
    unset($_SESSION['site_user_id'], $_SESSION['site_username']);
    @session_regenerate_id(true);
    $__q = strtok((string)($_SERVER['REQUEST_URI'] ?? '/'), '?');
    header('Location: ' . $__q);
    exit;
}

$__sg_err  = '';
$__sg_ok   = '';
$__sg_view = 'login';                       // login | register | activate | expired
$__sg_user = null;

// رمز CSRF خاص بالبوابة، منفصل عن رمز لوحة الإدارة
if (empty($_SESSION['sg_csrf'])) $_SESSION['sg_csrf'] = bin2hex(random_bytes(32));
$__sg_csrf = $_SESSION['sg_csrf'];

/** يتحقق من رمز البوابة بمقارنة ثابتة الزمن. */
$__sg_check = static function (): bool {
    return !empty($_SESSION['sg_csrf'])
        && !empty($_POST['sg_csrf'])
        && hash_equals((string)$_SESSION['sg_csrf'], (string)$_POST['sg_csrf']);
};

// ═════════════════════════════════════════════════════════════════
//  معالجة النماذج
// ═════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sg_action'])) {

    if (!$__sg_check()) {
        $__sg_err = $t['sg_session_expired'] ?? 'انتهت صلاحية الجلسة، أعد المحاولة.';
        $__sg_view = ($_POST['sg_action'] === 'register') ? 'register' : 'login';
    } else {
        switch ($_POST['sg_action']) {

        case 'login': {
            $r = subsLogin((string)($_POST['username'] ?? ''), (string)($_POST['password'] ?? ''));
            if (!$r['ok']) {
                $__sg_err = [
                    'too_many'        => $t['sg_too_many']  ?? 'محاولات كثيرة. انتظر بضع دقائق ثم حاول مجدداً.',
                    'bad_credentials' => $t['sg_bad_creds'] ?? 'اسم المستخدم أو كلمة المرور غير صحيحة.',
                ][$r['error']] ?? ($t['sg_login_failed'] ?? 'تعذّر تسجيل الدخول.');
                $__sg_view = 'login';
            } else {
                // تدوير معرّف الجلسة بعد نجاح المصادقة — يبطل أي معرّف
                // كان المهاجم قد زرعه في المتصفح قبل الدخول (session fixation).
                @session_regenerate_id(true);
                $_SESSION['site_user_id']  = (int)$r['user']['id'];
                $_SESSION['site_username'] = (string)$r['user']['username'];
                $_SESSION['sg_csrf'] = bin2hex(random_bytes(32));   // رمز جديد للجلسة الجديدة
                $__sg_csrf = $_SESSION['sg_csrf'];
            }
            break;
        }

        case 'register': {
            if (!subsRegistrationOpen()) {
                $__sg_err  = $t['sg_reg_closed'] ?? 'إنشاء الحسابات موقوف حالياً.';
                $__sg_view = 'login';
                break;
            }
            $pw  = (string)($_POST['password'] ?? '');
            $pw2 = (string)($_POST['password2'] ?? '');
            if (!hash_equals($pw, $pw2)) {
                $__sg_err  = $t['sg_pw_mismatch'] ?? 'كلمتا المرور غير متطابقتين.';
                $__sg_view = 'register';
                break;
            }
            $r = subsRegister((string)($_POST['username'] ?? ''), $pw, (string)($_POST['email'] ?? ''));
            if (!$r['ok']) {
                $__sg_err = [
                    'invalid_username' => $t['sg_bad_username'] ?? 'اسم المستخدم غير صالح (٣–٥٠ حرفاً إنجليزياً أو رقماً أو _ . -).',
                    'weak_password'    => $t['sg_weak_pw']      ?? 'كلمة المرور قصيرة — ٨ أحرف على الأقل.',
                    'invalid_email'    => $t['sg_bad_email']    ?? 'البريد الإلكتروني غير صالح.',
                    'username_taken'   => $t['sg_taken']        ?? 'اسم المستخدم محجوز، اختر غيره.',
                ][$r['error']] ?? ($t['sg_reg_failed'] ?? 'تعذّر إنشاء الحساب.');
                $__sg_view = 'register';
            } else {
                // ندخله مباشرة ليصل إلى شاشة الكوبون بلا خطوة إضافية
                @session_regenerate_id(true);
                $_SESSION['site_user_id']  = (int)$r['id'];
                $_SESSION['site_username'] = trim((string)$_POST['username']);
                $_SESSION['sg_csrf'] = bin2hex(random_bytes(32));
                $__sg_csrf = $_SESSION['sg_csrf'];
                $__sg_ok = $t['sg_reg_ok'] ?? 'أُنشئ حسابك. أدخل كوبوناً لتفعيل الاشتراك.';
            }
            break;
        }

        case 'redeem': {
            if (empty($_SESSION['site_user_id'])) { $__sg_view = 'login'; break; }
            $r = subsRedeemCoupon((int)$_SESSION['site_user_id'], (string)($_POST['coupon'] ?? ''));
            if (!$r['ok']) {
                $__sg_err = [
                    'invalid_code'       => $t['sg_cp_invalid']   ?? 'الكوبون غير صحيح.',
                    'coupon_disabled'    => $t['sg_cp_disabled']  ?? 'هذا الكوبون معطّل.',
                    'coupon_exhausted'   => $t['sg_cp_used']      ?? 'استُهلك هذا الكوبون بالكامل.',
                    'coupon_expired'     => $t['sg_cp_expired']   ?? 'انتهت صلاحية هذا الكوبون.',
                    'already_used_by_you'=> $t['sg_cp_mine']      ?? 'سبق أن استخدمت هذا الكوبون.',
                    'too_many'           => $t['sg_too_many']     ?? 'محاولات كثيرة. انتظر بضع دقائق ثم حاول مجدداً.',
                ][$r['error']] ?? ($t['sg_cp_failed'] ?? 'تعذّر تفعيل الكوبون.');
            } else {
                // نجاح: نعيد التوجيه بدل عرض الصفحة مباشرة.
                // بدون هذا، تحديث المتصفح يعيد إرسال النموذج (POST) ويُظهر
                // "الكوبون مستهلك" للمستخدم الذي نجح قبل ثانية.
                $__q = strtok((string)($_SERVER['REQUEST_URI'] ?? '/'), '?');
                header('Location: ' . $__q);
                exit;
            }
            break;
        }

        }
    }
}

// ═════════════════════════════════════════════════════════════════
//  تحديد الحالة
// ═════════════════════════════════════════════════════════════════
if (!empty($_SESSION['site_user_id'])) {
    $__sg_user = subsUserById((int)$_SESSION['site_user_id']);

    if (!$__sg_user) {
        // حُذف الحساب من اللوحة والجلسة ما تزال حيّة
        unset($_SESSION['site_user_id'], $_SESSION['site_username']);
        $__sg_view = 'login';
    } else {
        $__st = subsUserStatus($__sg_user);
        if ($__st['active']) {
            // ✔ اشتراك فعّال — يمرّ إلى المحتوى
            $GLOBALS['__site_user']   = $__sg_user;
            $GLOBALS['__site_status'] = $__st;
            return;
        }
        // انتهى / موقوف / بانتظار التفعيل
        if ($__st['state'] === 'expired') {
            $__sg_view = 'expired';
            subsLog((int)$__sg_user['id'], $__sg_user['username'], 'expired');
        } elseif ($__st['state'] === 'disabled') {
            $__sg_view = 'disabled';
        } else {
            $__sg_view = 'activate';
        }
    }
} else {
    // زائر غير مسجّل: نحترم ?sg=register إلا إذا كانت هناك رسالة خطأ
    // من محاولة POST سابقة (فتلك تحدّد العرض بنفسها).
    if ($__sg_err === '' && $__sg_ok === '') {
        $__sg_view = (($_GET['sg'] ?? '') === 'register' && subsRegistrationOpen())
                     ? 'register' : 'login';
    }
}

// ═════════════════════════════════════════════════════════════════
//  العرض
// ═════════════════════════════════════════════════════════════════
while (ob_get_level() > 1) { @ob_end_clean(); }
http_response_code(($__sg_view === 'login' || $__sg_view === 'register') ? 401 : 402);
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Robots-Tag: noindex, nofollow');

$__sg_site  = htmlspecialchars((string)($settings['site_name'] ?? 'Shashety'), ENT_QUOTES, 'UTF-8');
// الشعار: إعدادات الموقع أولاً، ثم assets/22.png، ثم رمز نصّي.
// نتحقق من وجود الملف على القرص لا من كون المسار غير فارغ — الإعداد قد
// يشير إلى صورة محذوفة، فيظهر مربّع مكسور بدل شعار.
$__sg_logo = '';
$__sg_try  = array_filter([
    trim((string)($settings['site_logo'] ?? '')),
    'assets/22.png',
]);
foreach ($__sg_try as $__cand) {
    // الروابط الخارجية تُقبل كما هي (لا يمكن فحصها على القرص)
    if (preg_match('~^https?://~i', $__cand)) { $__sg_logo = $__cand; break; }
    $__rel = ltrim($__cand, '/');
    if (is_file(dirname(__DIR__, 2) . '/' . $__rel)) { $__sg_logo = $__rel; break; }
}
$__sg_wa    = (string)($settings['contact_whatsapp'] ?? '');
/* ── فيديو خلفية البوابة ──
   يُلتقط تلقائياً إن وُجد الملف، فلا يحتاج إعداداً في اللوحة: ضع
   assets/login-bg.mp4 وسيظهر، واحذفه ليختفي. ويُقرأ هنا لا في index
   لأن المطلوب أن يكون في بوابة الدخول وحدها — تحميل فيديو خلفية على
   الصفحة الرئيسية يزاحم بثّ القنوات على النطاق بلا فائدة. */
/* نجمع كل الصيغ الموجودة ولا نكتفي بأولها.
   الاكتفاء بالأولى (webm) كان يحجب mp4 عن المتصفحات التي لا تفكّ VP9 —
   Safari على أجهزة أقدم مثلاً — فتضيع الخلفية عندها بلا سبب مع أن
   البديل موجود على القرص. الترتيب هنا هو ترتيب الأفضلية: الأخفّ أولاً. */
$__sg_bg = ['sources' => [], 'poster' => ''];
$__root  = dirname(__DIR__, 2);
foreach ([['assets/login-bg.webm', 'video/webm'], ['assets/login-bg.mp4', 'video/mp4']] as $cand) {
    if (is_file($__root . '/' . $cand[0])) {
        $__sg_bg['sources'][] = $cand;
    }
}
foreach (['assets/login-bg.jpg', 'assets/login-bg.png', 'assets/login-bg.webp'] as $p) {
    if (is_file($__root . '/' . $p)) { $__sg_bg['poster'] = $p; break; }
}

$__sg_regOn = subsRegistrationOpen();
$__sg_lang  = $__cur_lang ?? 'ar';
$__sg_dir   = ($__sg_lang === 'ar') ? 'rtl' : 'ltr';

require __DIR__ . '/../pages/auth_page.php';
exit;
