<?php
/**
 * ملف الاتصال بقاعدة البيانات والدوال المساعدة - Shashety IPTV
 * -------------------------------------------------------------
 * تم التحسين للأداء والأمان مع الحفاظ الكامل على السلوك القديم.
 *
 * جميع الدوال القديمة محفوظة بنفس الأسماء والتواقيع:
 *   sanitizeInput, validateEmail, generateSlug, logActivity,
 *   isAdminLoggedIn, redirect, jsonResponse
 *
 * الجديد (إضافات لا تكسر أي شيء):
 *   env(), csrfToken(), csrfValidate(), csrfField(), rateLimit(),
 *   cacheGet(), cacheSet(), cacheDelete(), cacheFlush(),
 *   securityHeaders(), db(), clientIp(), safeInt(), logTo()
 *
 * @package Shashety
 */

// ══════════════════════════════════════════════════════════════
// 0) إعدادات بيئة التشغيل
// ══════════════════════════════════════════════════════════════

// عرض الأخطاء في السجل فقط — لا تُعرض للمستخدم أبداً (منع تسريب معلومات)
@ini_set('display_errors', '0');
@ini_set('log_errors', '1');
error_reporting(E_ALL);

// المنطقة الزمنية (يمنع تحذير Deprecated في بعض الإعدادات)
if (!ini_get('date.timezone')) {
    @date_default_timezone_set('UTC');
}

// كشف HTTPS (يدعم العمل خلف Proxy / Cloudflare)
$__isHttps = (
    (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
    || (($_SERVER['SERVER_PORT'] ?? '') == 443)
    || (strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https')
);
if (!defined('IS_HTTPS')) {
    define('IS_HTTPS', $__isHttps);
}

// ══════════════════════════════════════════════════════════════
// 1) تحميل الإعدادات من ملف .env إن وُجد (حماية بيانات الاعتماد)
//    إن لم يوجد الملف تُستخدم القيم الافتراضية القديمة كما هي،
//    فلا ينكسر أي تركيب حالي.
// ══════════════════════════════════════════════════════════════

/**
 * قراءة متغير بيئة أو قيمة من ملف .env مع قيمة افتراضية.
 *
 * @param string $key     اسم المتغير.
 * @param mixed  $default القيمة الافتراضية عند غياب المتغير.
 * @return mixed
 */
/**
 * تحليل ملف بصيغة KEY=VALUE إلى مصفوفة.
 *
 * @param string $file
 * @return array<string,string>
 */
function envParseFile(string $file): array
{
    $out   = [];
    $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($lines as $line) {
        $line = trim($line);
        // نتخطّى التعليقات والأسطر بلا "=" — ونتحمّل نهايات أسطر ويندوز
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
            continue;
        }
        [$k, $v] = explode('=', $line, 2);
        $k = trim($k);
        $v = trim($v, " \t\n\r\0\x0B");
        // إزالة علامات الاقتباس المحيطة
        if (strlen($v) > 1
            && (($v[0] === '"' && $v[-1] === '"') || ($v[0] === "'" && $v[-1] === "'"))
        ) {
            $v = substr($v, 1, -1);
        }
        if ($k !== '') { $out[$k] = $v; }
    }
    return $out;
}

/**
 * قراءة متغيّر إعداد من أول مصدر متاح.
 *
 * ══ لماذا سلسلة مصادر بدل ملف واحد ══
 * كان الإعداد كلّه في ‎APP_ROOT/.env‎ — أي داخل المجلد الذي تُرفع
 * نسخته من ويندوز. وهذا يجمع خطرين في موضع واحد:
 *
 *   ① الرفع يستبدل ملف الخادم بنسختك المحلية، فتضيع كلمة مرور
 *      الإنتاج ويحلّ محلّها ما في جهازك. حدث هذا فعلاً أكثر من مرّة.
 *   ② الرفع يعيد ضبط الملكية إلى مستخدم الـFTP، فلا يقرأ Apache
 *      الملف، فترتدّ القيم إلى الافتراضي بصمت.
 *
 * كلاهما ينتهي بنفس رسالة «خطأ في الاتصال بقاعدة البيانات».
 *
 * الحلّ أن تعيش الأسرار خارج المجلد المرفوع. الترتيب هنا من الأعلى
 * أولويةً إلى الأدنى، والأعلى يُلغي ما دونه مفتاحاً بمفتاح — فيمكن
 * أن يبقى ‎.env‎ للإعدادات العامة بينما تُقرأ كلمة المرور من الخارج:
 *
 *   1. متغيّرات الخادم ($_SERVER / $_ENV) — SetEnv في Apache
 *   2. المسار في متغيّر البيئة SHASHETY_ENV — لمن يريد مكاناً خاصاً
 *   3. ‎/etc/shashety/env‎ — الموضع القياسي على خادم تملكه
 *   4. ‎<فوق جذر الويب>/.shashety-env‎ — للاستضافة المشتركة بلا /etc
 *   5. ‎APP_ROOT/.env‎ — القديم، يبقى ليعمل ما هو قائم بلا تعديل
 *
 * @param string $key     اسم المتغيّر.
 * @param mixed  $default القيمة الافتراضية عند غيابه من كل المصادر.
 * @return mixed
 */
function env(string $key, $default = null)
{
    static $vars = null;

    if ($vars === null) {
        $vars = [];
        $root = dirname(__DIR__);

        $candidates = [];
        $explicit = getenv('SHASHETY_ENV');
        if (is_string($explicit) && $explicit !== '') { $candidates[] = $explicit; }
        $candidates[] = '/etc/shashety/env';
        $candidates[] = dirname($root) . '/.shashety-env';
        $candidates[] = $root . '/.env';

        /* نقرأ من الأدنى أولويةً إلى الأعلى، فيَكتب الأعلى فوق الأدنى.
           المصدر الأعلى قد يحمل مفتاحاً واحداً فقط (كلمة المرور مثلاً)
           فلا يُلغي بقيّة الإعدادات، وهو المطلوب. */
        $used = [];
        foreach (array_reverse($candidates) as $file) {
            if (is_file($file) && is_readable($file)) {
                $vars   = array_merge($vars, envParseFile($file));
                $used[] = $file;
            } elseif (is_file($file)) {
                /* موجود ولا يُقرأ — تمييزه عن «غير موجود» جوهري:
                   الأول إعداد صحيح حجبته الصلاحيات، والثاني تثبيت ناقص.
                   بلا هذا التمييز يبدو العطلان واحداً ويقودان للمكان
                   الخطأ في البحث. */
                @error_log(
                    'shashety: ملف الإعدادات ' . $file . ' موجود لكن تعذّرت قراءته بهوية '
                    . (function_exists('posix_getpwuid') && function_exists('posix_geteuid')
                        ? (posix_getpwuid(posix_geteuid())['name'] ?? '?') : '?')
                    . ' — الإصلاح: sudo bash ' . $root . '/tools/fix_permissions.sh'
                );
                if (!defined('ENV_UNREADABLE')) { define('ENV_UNREADABLE', true); }
            }
        }
        /* آخر ملف دُمج هو الأعلى أولويةً — لأننا دمجنا تصاعدياً.
           التقاطُ الأول كان سيبلّغ عن المصدر الأدنى ويضلّل التشخيص. */
        if (!defined('ENV_SOURCE')) { define('ENV_SOURCE', $used ? end($used) : ''); }
    }

    $fromServer = $_SERVER[$key] ?? $_ENV[$key] ?? null;
    if ($fromServer !== null && $fromServer !== '') {
        return $fromServer;
    }

    return $vars[$key] ?? $default;
}

// ══════════════════════════════════════════════════════════════
// 2) إعدادات قاعدة البيانات — نفس الأسماء والقيم الافتراضية
// ══════════════════════════════════════════════════════════════
if (!defined('DB_HOST'))    { define('DB_HOST',    (string) env('DB_HOST', 'localhost')); }
if (!defined('DB_NAME'))    { define('DB_NAME',    (string) env('DB_NAME', 'iptv_db')); }
if (!defined('DB_USER'))    { define('DB_USER',    (string) env('DB_USER', 'iptv_user')); }
/* ⚠ لا قيمة افتراضية لكلمة المرور — والفرق ليس شكلياً.
   كانت '123456'، فحين يتعذّر العثور على الإعدادات (رفعٌ غيّر الملكية،
   أو ملف مفقود) يمضي الكود بهدوء ويحاول اتصالاً محكوماً بالفشل، فيصل
   المستخدم خطأُ MySQL «Access denied» بدل السبب الحقيقي. سلسلةٌ من
   الأعراض المضلّلة كلّها أصلها هذا السطر.
   الآن: الغياب يعني «لم يُقرأ الإعداد»، ويُعالَج صراحةً أدناه. */
if (!defined('DB_PASS'))    { define('DB_PASS',    (string) env('DB_PASS', '')); }
if (!defined('DB_CHARSET')) { define('DB_CHARSET', (string) env('DB_CHARSET', 'utf8mb4')); }

// مسارات النظام
if (!defined('APP_ROOT'))   { define('APP_ROOT', dirname(__DIR__)); } // moved to core/: root is one level up
if (!defined('CACHE_DIR'))  { define('CACHE_DIR', APP_ROOT . '/storage/cache'); }
if (!defined('LOG_DIR'))    { define('LOG_DIR',   APP_ROOT . '/storage/logs'); }

// مفتاح سري لتوقيع الرموز (يُولَّد تلقائياً ويُخزَّن إن لم يُضبط)
if (!defined('APP_KEY')) {
    $__key = (string) env('APP_KEY', '');
    if ($__key === '') {
        $keyFile = APP_ROOT . '/storage/.appkey';
        if (is_readable($keyFile)) {
            $__key = trim((string) @file_get_contents($keyFile));
        }
        if ($__key === '') {
            $__key = bin2hex(random_bytes(32));
            if (!is_dir(dirname($keyFile))) {
                @mkdir(dirname($keyFile), 0750, true);
            }
            @file_put_contents($keyFile, $__key, LOCK_EX);
            @chmod($keyFile, 0600);
        }
    }
    define('APP_KEY', $__key);
}

// ══════════════════════════════════════════════════════════════
// 3) إعدادات الجلسة — حماية Session Hijacking و Cookies
//    يجب أن تكون قبل session_start()
// ══════════════════════════════════════════════════════════════
if (session_status() === PHP_SESSION_NONE && PHP_SAPI !== 'cli') {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');           // منع Session Fixation
    ini_set('session.cookie_secure', IS_HTTPS ? '1' : '0'); // تلقائي حسب HTTPS
    ini_set('session.cookie_samesite', 'Lax');         // حماية إضافية ضد CSRF
    ini_set('session.gc_maxlifetime', '7200');
    ini_set('session.sid_length', '48');
    ini_set('session.sid_bits_per_character', '5');

    session_start();

    // ربط الجلسة ببصمة العميل لمنع اختطافها
    $__fp = hash_hmac(
        'sha256',
        ($_SERVER['HTTP_USER_AGENT'] ?? ''),
        APP_KEY
    );
    if (!isset($_SESSION['__fp'])) {
        $_SESSION['__fp'] = $__fp;
        $_SESSION['__born'] = time();
    } elseif (!hash_equals((string) $_SESSION['__fp'], $__fp)) {
        // بصمة مختلفة → جلسة جديدة نظيفة (لا نكسر الاستخدام العادي)
        $_SESSION = [];
        session_regenerate_id(true);
        $_SESSION['__fp'] = $__fp;
        $_SESSION['__born'] = time();
    }

    // تدوير معرّف الجلسة كل 30 دقيقة (تقليل نافذة الاختطاف)
    if (!isset($_SESSION['__rot']) || (time() - (int) $_SESSION['__rot']) > 1800) {
        session_regenerate_id(true);
        $_SESSION['__rot'] = time();
    }
}

// ══════════════════════════════════════════════════════════════
// 4) الاتصال بقاعدة البيانات (PDO)
// ══════════════════════════════════════════════════════════════

/* فحص مسبق: هل عُثر على إعدادات أصلاً؟
   نميّز حالتين تبدوان واحدة وهما مختلفتان تماماً:
     • ENV_SOURCE فارغ ⇒ لم يُقرأ أي ملف إعدادات. هذا خطأ تثبيت،
       ومحاولة الاتصال ستفشل حتماً وتُنتج رسالة MySQL مضلّلة.
     • ENV_SOURCE موجود وDB_PASS فارغة ⇒ كلمة مرور فارغة مقصودة
       (شائع في بيئات التطوير المحلية) — نمضي بلا اعتراض.
   بلا هذا التمييز إمّا نمنع إعداداً مشروعاً، أو نُخفي عطلاً حقيقياً. */
if (DB_PASS === '' && (!defined('ENV_SOURCE') || ENV_SOURCE === '')) {
    @error_log('shashety FATAL: لم يُعثر على أي ملف إعدادات — لا /etc/shashety/env ولا .env');
    if (!headers_sent()) {
        http_response_code(503);
        header('Content-Type: application/json; charset=utf-8');
    }
    $__cli = PHP_SAPI === 'cli';
    $__ip  = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    $__loc = $__cli || $__ip === '127.0.0.1' || $__ip === '::1'
          || (filter_var($__ip, FILTER_VALIDATE_IP) !== false
              && filter_var($__ip, FILTER_VALIDATE_IP,
                     FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false);
    $__out = ['success' => false, 'error' => 'النظام غير مُهيّأ بعد. يرجى المحاولة لاحقاً.'];
    if ($__loc) {
        $__out['hint'] = 'لم يُعثر على ملف إعدادات في أيٍّ من: /etc/shashety/env · '
                       . dirname(APP_ROOT) . '/.shashety-env · ' . APP_ROOT . '/.env'
                       . '  —  الإصلاح: sudo bash ' . APP_ROOT . '/install_env.sh';
    }
    die(json_encode($__out, JSON_UNESCAPED_UNICODE));
}

try {
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_STRINGIFY_FETCHES  => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES ' . DB_CHARSET,
        // تمت إزالة ATTR_PERSISTENT لتجنب استنزاف الاتصالات والأقفال
    ];

    // جلب غير مخزَّن مؤقتاً غير مفعّل: نُبقي buffered لأن الكود الحالي
    // يعتمد على fetchAll() وعلى تنفيذ استعلامات متداخلة.
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

} catch (PDOException $e) {
    // تسجيل الخطأ بدلاً من عرضه للمستخدم
    error_log('Database Connection Error: ' . $e->getMessage());

    /* السبب الأشيع لهذا الخطأ ليس عطلاً في MySQL بل تعذُّر قراءة .env
       بعد رفع الملفات، فترتدّ كلمة المرور إلى الافتراضية. الرسالة
       العامة صحيحة لكنها تُرسل الباحث إلى قاعدة البيانات بينما العلّة
       في الصلاحيات — لذا نضيف التشخيص، ولمن يشغّل الخادم فقط.

       الشرط هنا شبكة خاصّة أو نفس الجهاز: لا نكشف تفاصيل بنية الخادم
       لزائر من الإنترنت، ولا نكتفي بإخفائها عمّن يحتاجها فعلاً. */
    $ip   = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    $isLocal = PHP_SAPI === 'cli'
            || $ip === '127.0.0.1' || $ip === '::1'
            || (filter_var($ip, FILTER_VALIDATE_IP) !== false
                && filter_var($ip, FILTER_VALIDATE_IP,
                       FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false);

    $msg = 'عذراً، حدث خطأ في الاتصال بقاعدة البيانات. يرجى المحاولة لاحقاً.';
    $out = ['success' => false, 'error' => $msg];

    if ($isLocal) {
        $src = defined('ENV_SOURCE') && ENV_SOURCE !== '' ? ENV_SOURCE : '(لا شيء)';
        $out['hint'] = defined('ENV_UNREADABLE')
            ? 'ملف إعدادات موجود لكن خادم الويب لا يستطيع قراءته. '
              . 'الإصلاح: sudo bash ' . APP_ROOT . '/tools/fix_permissions.sh'
            : 'المصدر المقروء: ' . $src . ' — راجع DB_USER و DB_PASS فيه، '
              . 'ثم: sudo bash ' . APP_ROOT . '/fix_db_password.sh';
    }

    if (!headers_sent()) {
        http_response_code(503);
        header('Content-Type: application/json; charset=utf-8');
    }
    die(json_encode($out, JSON_UNESCAPED_UNICODE));
}

/**
 * إرجاع كائن PDO المشترك (بديل نظيف لـ global $pdo).
 *
 * @return PDO
 */
function db(): PDO
{
    global $pdo;
    return $pdo;
}

// ══════════════════════════════════════════════════════════════
// 5) الدوال المساعدة الأصلية — محفوظة بنفس السلوك
// ══════════════════════════════════════════════════════════════

/**
 * دالة مساعدة لتنظيف المدخلات.
 * نفس السلوك القديم تماماً (trim + stripslashes + htmlspecialchars)
 * مع دعم القيم غير النصية بأمان لتفادي تحذيرات PHP 8.3.
 *
 * @param mixed $data المدخل الخام.
 * @return string النص بعد التنظيف.
 */
function sanitizeInput($data): string
{
    if ($data === null || is_bool($data) || is_array($data) || is_object($data)) {
        $data = is_array($data) || is_object($data) ? '' : (string) $data;
    }
    $data = trim((string) $data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    return $data;
}

/**
 * دالة للتحقق من صحة البريد الإلكتروني.
 *
 * @param string $email البريد المراد فحصه.
 * @return string|false البريد الصالح أو false.
 */
function validateEmail($email)
{
    return filter_var((string) $email, FILTER_VALIDATE_EMAIL);
}

/**
 * دالة لتوليد slug من النص العربي أو الإنجليزي.
 *
 * @param string $text النص المصدر.
 * @return string الـ slug الناتج.
 */
function generateSlug($text): string
{
    $text = (string) $text;

    // تحويل النص إلى أحرف صغيرة
    $text = mb_strtolower($text, 'UTF-8');

    // استبدال المسافات بشرطات
    $text = str_replace(' ', '-', $text);

    // إزالة الأحرف الخاصة
    $text = (string) preg_replace('/[^a-z0-9\p{Arabic}\-]/u', '', $text);

    // إزالة الشرطات المتعددة
    $text = (string) preg_replace('/-+/', '-', $text);

    // إزالة الشرطات من البداية والنهاية
    return trim($text, '-');
}

/**
 * دالة لتسجيل النشاطات.
 * محفوظة بنفس التوقيع؛ أصبحت تكتب أيضاً في ملف سجل مخصص.
 *
 * @param string $action  اسم الإجراء.
 * @param string $details تفاصيل إضافية.
 * @return void
 */
function logActivity($action, $details = ''): void
{
    try {
        $ip = clientIp();

        error_log("Activity: $action | IP: $ip | Details: $details");
        logTo('activity', $action, ['ip' => $ip, 'details' => (string) $details]);

    } catch (Throwable $e) {
        error_log('Logging Error: ' . $e->getMessage());
    }
}

/**
 * دالة للتحقق من صلاحية المدير.
 *
 * @return bool
 */
function isAdminLoggedIn(): bool
{
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

/**
 * دالة لإعادة توجيه المستخدم.
 *
 * @param string $url الوجهة.
 * @return never
 */
function redirect($url)
{
    // منع Header Injection عبر أسطر جديدة
    $url = str_replace(["\r", "\n", "\0"], '', (string) $url);
    header('Location: ' . $url);
    exit();
}

/**
 * دالة لإرجاع استجابة JSON.
 *
 * @param mixed $data       البيانات المُرجَعة.
 * @param int   $statusCode كود HTTP.
 * @return never
 */
function jsonResponse($data, $statusCode = 200)
{
    if (!headers_sent()) {
        http_response_code((int) $statusCode);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit();
}

// ══════════════════════════════════════════════════════════════
// 6) دوال جديدة — أمان
// ══════════════════════════════════════════════════════════════

/**
 * الحصول على عنوان IP الحقيقي للعميل (يدعم Proxy موثوق).
 *
 * @return string
 */
function clientIp(): string
{
    $candidates = [];

    // نثق بـ CF-Connecting-IP و X-Forwarded-For فقط للتسجيل والحد من المعدل
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        $candidates[] = $_SERVER['HTTP_CF_CONNECTING_IP'];
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $parts = explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR']);
        $candidates[] = trim($parts[0]);
    }
    $candidates[] = $_SERVER['REMOTE_ADDR'] ?? '';

    foreach ($candidates as $ip) {
        $ip = trim((string) $ip);
        if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
    }

    return 'unknown';
}

/**
 * تحويل قيمة إلى عدد صحيح ضمن حدود آمنة.
 *
 * @param mixed $value القيمة الخام.
 * @param int   $min   الحد الأدنى.
 * @param int   $max   الحد الأقصى.
 * @param int   $def   القيمة الافتراضية عند الغياب.
 * @return int
 */
function safeInt($value, int $min, int $max, int $def): int
{
    if ($value === null || $value === '' || !is_numeric($value)) {
        $value = $def;
    }
    $n = (int) $value;
    if ($n < $min) { $n = $min; }
    if ($n > $max) { $n = $max; }
    return $n;
}

/**
 * توليد أو إرجاع رمز CSRF الخاص بالجلسة.
 *
 * @return string
 */
function csrfToken(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return '';
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return (string) $_SESSION['csrf_token'];
}

/**
 * التحقق من صحة رمز CSRF المرسل (POST أو رأس AJAX).
 *
 * @param string|null $token الرمز؛ يُقرأ تلقائياً إن كان null.
 * @return bool
 */
function csrfValidate(?string $token = null): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return false;
    }
    if ($token === null) {
        $token = (string) (
            $_POST['csrf_token']
            ?? $_GET['csrf_token']
            ?? $_SERVER['HTTP_X_CSRF_TOKEN']
            ?? ''
        );
    }
    $stored = (string) ($_SESSION['csrf_token'] ?? '');
    return $stored !== '' && $token !== '' && hash_equals($stored, $token);
}

/**
 * إرجاع حقل input مخفي يحمل رمز CSRF (للنماذج).
 *
 * @return string HTML جاهز للطباعة.
 */
function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="'
        . htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * إرسال رؤوس الأمان (CSP, HSTS, Clickjacking, MIME Sniffing).
 *
 * @param bool $isApi هل السياق API؟ (سياسة CSP أكثر صرامة).
 * @return void
 */
function securityHeaders(bool $isApi = false): void
{
    if (headers_sent()) {
        return;
    }

    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-XSS-Protection: 0'); // الاعتماد على CSP بدل الفلتر القديم
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

    if (IS_HTTPS) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }

    if ($isApi) {
        header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'");
    } else {
        // سياسة متساهلة عمداً حتى لا تنكسر أي واجهة أو مشغّل حالي.
        header(
            "Content-Security-Policy: "
            . "default-src 'self' data: blob: https:; "
            . "script-src 'self' 'unsafe-inline' 'unsafe-eval' https: blob:; "
            . "style-src 'self' 'unsafe-inline' https:; "
            . "img-src 'self' data: blob: https: http:; "
            . "media-src 'self' data: blob: https: http:; "
            . "connect-src 'self' https: http: ws: wss:; "
            . "font-src 'self' data: https:; "
            . "frame-ancestors 'self'"
        );
    }
}

/**
 * حدّ المعدل (Rate Limiting) بنافذة زمنية منزلقة بسيطة.
 * يعتمد على ملفات لضمان العمل بلا امتدادات إضافية.
 *
 * @param string $key    مفتاح الحد (مثلاً: 'api:search').
 * @param int    $max    أقصى عدد طلبات.
 * @param int    $window النافذة بالثواني.
 * @return bool true إذا كان الطلب مسموحاً.
 */
function rateLimit(string $key, int $max = 120, int $window = 60): bool
{
    // APCu إن توفّر (أسرع)
    $id = 'rl_' . sha1($key . '|' . clientIp());

    if (function_exists('apcu_enabled') && apcu_enabled()) {
        $cur = apcu_fetch($id, $ok);
        if (!$ok) {
            apcu_store($id, 1, $window);
            return true;
        }
        if ((int) $cur >= $max) {
            return false;
        }
        apcu_inc($id);
        return true;
    }

    // بديل ملفّي
    $dir = CACHE_DIR . '/ratelimit';
    if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
        return true; // لا نمنع المستخدم إن تعذّر التخزين
    }

    $file = $dir . '/' . $id . '.json';
    $now  = time();
    $data = ['start' => $now, 'count' => 0];

    $fh = @fopen($file, 'c+');
    if ($fh === false) {
        return true;
    }

    $allowed = true;
    if (flock($fh, LOCK_EX)) {
        $raw = stream_get_contents($fh);
        if ($raw !== false && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded) && isset($decoded['start'], $decoded['count'])) {
                $data = $decoded;
            }
        }

        if (($now - (int) $data['start']) >= $window) {
            $data = ['start' => $now, 'count' => 0];
        }

        if ((int) $data['count'] >= $max) {
            $allowed = false;
        } else {
            $data['count'] = (int) $data['count'] + 1;
        }

        ftruncate($fh, 0);
        rewind($fh);
        fwrite($fh, json_encode($data));
        fflush($fh);
        flock($fh, LOCK_UN);
    }
    fclose($fh);

    return $allowed;
}

// ══════════════════════════════════════════════════════════════
// 7) دوال جديدة — نظام كاش (ملفات + ذاكرة + تنظيف تلقائي)
// ══════════════════════════════════════════════════════════════

/**
 * جلب قيمة من الكاش.
 *
 * @param string $key المفتاح.
 * @return mixed|null القيمة أو null إن انتهت صلاحيتها.
 */
function cacheGet(string $key)
{
    static $memory = [];

    if (array_key_exists($key, $memory)) {
        return $memory[$key];
    }

    if (function_exists('apcu_enabled') && apcu_enabled()) {
        $val = apcu_fetch('c_' . $key, $ok);
        if ($ok) {
            $memory[$key] = $val;
            return $val;
        }
    }

    $file = CACHE_DIR . '/data/' . sha1($key) . '.cache';
    if (!is_readable($file)) {
        return null;
    }

    $raw = @file_get_contents($file);
    if ($raw === false || $raw === '') {
        return null;
    }

    $payload = @unserialize($raw, ['allowed_classes' => false]);
    if (!is_array($payload) || !isset($payload['exp'], $payload['val'])) {
        @unlink($file);
        return null;
    }

    if ($payload['exp'] > 0 && $payload['exp'] < time()) {
        @unlink($file); // تنظيف تلقائي عند القراءة
        return null;
    }

    $memory[$key] = $payload['val'];
    return $payload['val'];
}

/**
 * تخزين قيمة في الكاش.
 *
 * @param string $key   المفتاح.
 * @param mixed  $value القيمة.
 * @param int    $ttl   مدة الصلاحية بالثواني (0 = دائم).
 * @return bool
 */
function cacheSet(string $key, $value, int $ttl = 300): bool
{
    if (function_exists('apcu_enabled') && apcu_enabled()) {
        apcu_store('c_' . $key, $value, $ttl);
    }

    $dir = CACHE_DIR . '/data';
    if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
        return false;
    }

    $file    = $dir . '/' . sha1($key) . '.cache';
    $payload = serialize(['exp' => $ttl > 0 ? time() + $ttl : 0, 'val' => $value]);

    // كتابة ذرّية لتجنب Race Condition
    $tmp = $file . '.' . bin2hex(random_bytes(6)) . '.tmp';
    if (@file_put_contents($tmp, $payload, LOCK_EX) === false) {
        return false;
    }
    if (!@rename($tmp, $file)) {
        @unlink($tmp);
        return false;
    }
    @chmod($file, 0640);

    // تنظيف دوري خفيف (1% من الطلبات)
    if (random_int(1, 100) === 1) {
        cacheGarbageCollect();
    }

    return true;
}

/**
 * حذف مفتاح من الكاش.
 *
 * @param string $key المفتاح.
 * @return void
 */
function cacheDelete(string $key): void
{
    if (function_exists('apcu_enabled') && apcu_enabled()) {
        apcu_delete('c_' . $key);
    }
    $file = CACHE_DIR . '/data/' . sha1($key) . '.cache';
    if (is_file($file)) {
        @unlink($file);
    }
}

/**
 * مسح الكاش بالكامل.
 *
 * @return void
 */
function cacheFlush(): void
{
    if (function_exists('apcu_enabled') && apcu_enabled()) {
        @apcu_clear_cache();
    }
    foreach (glob(CACHE_DIR . '/data/*.cache') ?: [] as $f) {
        @unlink($f);
    }
}

/**
 * تنظيف الملفات المنتهية الصلاحية من الكاش.
 *
 * @return int عدد الملفات المحذوفة.
 */
function cacheGarbageCollect(): int
{
    $removed = 0;
    $now     = time();

    foreach (glob(CACHE_DIR . '/data/*.cache') ?: [] as $f) {
        $raw = @file_get_contents($f);
        if ($raw === false) {
            continue;
        }
        $payload = @unserialize($raw, ['allowed_classes' => false]);
        if (!is_array($payload) || (!empty($payload['exp']) && $payload['exp'] < $now)) {
            @unlink($f);
            $removed++;
        }
    }

    foreach (glob(CACHE_DIR . '/ratelimit/*.json') ?: [] as $f) {
        if (@filemtime($f) < $now - 3600) {
            @unlink($f);
            $removed++;
        }
    }

    return $removed;
}

// ══════════════════════════════════════════════════════════════
// 8) دوال جديدة — نظام سجلات
// ══════════════════════════════════════════════════════════════

/**
 * كتابة سطر سجل في قناة محددة (security, admin, activity, api, ...).
 *
 * @param string $channel اسم القناة.
 * @param string $message الرسالة.
 * @param array  $context بيانات إضافية.
 * @return void
 */
function logTo(string $channel, string $message, array $context = []): void
{
    $channel = preg_replace('/[^a-z0-9_\-]/i', '', $channel) ?: 'app';

    $dir = LOG_DIR;
    if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
        return;
    }

    $line = json_encode([
        'ts'      => date('c'),
        'channel' => $channel,
        'msg'     => $message,
        'ip'      => $context['ip'] ?? clientIp(),
        'uri'     => $_SERVER['REQUEST_URI'] ?? '',
        'ctx'     => $context,
    ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

    @file_put_contents(
        $dir . '/' . $channel . '-' . date('Y-m-d') . '.log',
        $line . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );
}

// ══════════════════════════════════════════════════════════════
// 9) تجهيز مجلدات التخزين وحمايتها من الوصول المباشر
// ══════════════════════════════════════════════════════════════
foreach ([CACHE_DIR, CACHE_DIR . '/data', CACHE_DIR . '/ratelimit', LOG_DIR] as $__dir) {
    if (!is_dir($__dir)) {
        @mkdir($__dir, 0750, true);
    }
}
/* حماية مجلد storage من الوصول المباشر.
   ⚠️ النسخة السابقة كانت تكتب ثلاثة توجيهات غير محاطة:
        Require all denied      ← يحتاج mod_authz_core (Apache 2.4)
        Deny from all           ← يحتاج mod_access_compat (Apache 2.2)
        php_flag engine off     ← يحتاج mod_php (يفشل على PHP-FPM)
   أي توجيه غير معروف داخل .htaccess يجعل Apache يُرجع خطأ 500 لكل
   ما تحت ذلك المجلد. الآن كل توجيه محاط بـ <IfModule> فلا يُطبَّق
   إلا إن كانت وحدته مفعّلة فعلاً. */
if (is_dir(APP_ROOT . '/storage') && !is_file(APP_ROOT . '/storage/.htaccess')) {
    @file_put_contents(
        APP_ROOT . '/storage/.htaccess',
        "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n"
        . "<IfModule !mod_authz_core.c>\n    Order allow,deny\n    Deny from all\n</IfModule>\n"
        . "<IfModule mod_php.c>\n    php_flag engine off\n</IfModule>\n"
        . "<IfModule mod_php7.c>\n    php_flag engine off\n</IfModule>\n"
        . "<IfModule mod_php8.c>\n    php_flag engine off\n</IfModule>\n"
    );
}
