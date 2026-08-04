<?php
// orig 100-234

/* ══════════════════════════════════════════════════════════════
   الأعطال التي عولجت في هذا الملف
   ══════════════════════════════════════════════════════════════

   ① أوامر بناء الجداول (7 استعلامات DDL + استعلام COUNT) كانت تُنفَّذ
      في *كل* طلب — كل فتح صفحة وكل نداء AJAX. هذا حمل ثابت على قاعدة
      البيانات بلا فائدة بعد أول تشغيل.
      الحل: تُنفَّذ مرة واحدة، وتُسجَّل في ملف علامة داخل storage/cache.
      لإعادة تشغيلها بعد تحديث: احذف الملف storage/cache/.schema_ok

   ② جدول blocked_ips لم يكن يُنشأ في أي مكان في المشروع إطلاقاً!
      login.php يستعلم عنه داخل try/catch فارغ، فالاستثناء يُبتلع
      بصمت. النتيجة: ميزة حظر عناوين IP بعد المحاولات الفاشلة كانت
      *لا تعمل مطلقاً* رغم وجود كل الكود الخاص بها. أُنشئ الجدول الآن.

   ③ السطر الأول كان تعبيراً نصياً معلّقاً بلا استدعاء:
        ("INSERT IGNORE INTO login_logs ...");
      نصّ لا يُنفَّذ ولا يفعل شيئاً — كود ميت أُزيل.

   ④ بذرة المدير الأول كانت بكلمة مرور 'admin' الثابتة. أصبحت كلمة
      مرور عشوائية تُكتب في سجل الخادم، فلا يبقى حساب بكلمة معروفة.

   ⑤ جدولا episodes و series بلا فهارس على الأعمدة المستخدمة في البحث
      (series_id, category_id). كل عرض لحلقات مسلسل كان يمسح الجدول
      بالكامل. أُضيفت الفهارس اللازمة.
   ══════════════════════════════════════════════════════════════ */

$__schemaFlag = (defined('CACHE_DIR') ? CACHE_DIR : dirname(__DIR__) . '/storage/cache')
    . '/.schema_ok';

if (!is_file($__schemaFlag)) {

    // ══ نظام إدارة المستخدمين والصلاحيات ══
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS login_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ip_address VARCHAR(45) NOT NULL,
            username VARCHAR(100),
            status VARCHAR(50) DEFAULT 'failed',
            attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_ip_time (ip_address, attempt_time),
            KEY idx_status_time (status, attempt_time)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        /* ② الجدول المفقود الذي كان يشلّ الحماية من التخمين */
        $pdo->exec("CREATE TABLE IF NOT EXISTS blocked_ips (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ip_address VARCHAR(45) NOT NULL UNIQUE,
            reason VARCHAR(255) DEFAULT 'محاولات دخول فاشلة متكررة',
            blocked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_ip (ip_address)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS admin_users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(100) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            display_name VARCHAR(100) DEFAULT '',
            role ENUM('administrator','super','normal','custom') DEFAULT 'normal',
            allowed_sections TEXT,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_login TIMESTAMP NULL,
            KEY idx_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // بذر المدير الأول إن كان الجدول فارغاً
        $__au_cnt = $pdo->query("SELECT COUNT(*) FROM admin_users")->fetchColumn();
        if ($__au_cnt == 0 && !empty($_SESSION['admin_username'])) {
            /* ④ كانت كلمة المرور المبذورة هي 'admin' — أي حساب معروف
                 للجميع. الآن كلمة عشوائية تُكتب في سجل الخادم مرة واحدة،
                 ويستطيع المالك تغييرها من صفحة تغيير كلمة المرور. */
            $__au_plain = bin2hex(random_bytes(9));
            $__au_hash  = password_hash($__au_plain, PASSWORD_DEFAULT, ['cost' => 12]);
            $__au_name  = $_SESSION['admin_username'];
            $pdo->prepare("INSERT INTO admin_users (username, password_hash, display_name, role, allowed_sections) VALUES (?, ?, ?, 'administrator', '[]')")
                ->execute([$__au_name, $__au_hash, $__au_name]);
            error_log("Shashety: تم إنشاء حساب المدير '{$__au_name}' بكلمة مرور مؤقتة: {$__au_plain} — غيّرها فوراً.");
        }
    } catch (PDOException $e) {
        error_log('helpers.php users schema: ' . $e->getMessage());
    }

    try {
        $pdo->exec("ALTER TABLE channels ADD COLUMN subtitle_url VARCHAR(1000) DEFAULT '' AFTER stream_url");
    } catch (PDOException $e) { /* العمود موجود مسبقاً */ }

    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS series (
            id INT AUTO_INCREMENT PRIMARY KEY,
            category_id INT NOT NULL,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL UNIQUE,
            description TEXT,
            poster_url VARCHAR(500),
            logo_icon VARCHAR(100) DEFAULT 'fas fa-film',
            display_order INT DEFAULT 0,
            views_count INT DEFAULT 0,
            /* ⚠️ العمود is_active كان مفقوداً من هذا التعريف بينما
               database.sql يحتويه، و api.php يستعلم به:
                   WHERE id = ? AND is_active = 1
               فعلى أي تركيب أُنشئ فيه الجدول من هنا (تنصيب جديد بلا
               استيراد database.sql) كان استعلام الحلقات يفشل بالكامل
               فتظهر رسالة «تعذر تحميل الحلقات» أو صفحة فارغة.
               أُضيف بنفس القيمة الافتراضية الموجودة في database.sql. */
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_category (category_id),
            KEY idx_active (is_active),
            KEY idx_cat_order (category_id, display_order, id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS episodes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            series_id INT NOT NULL,
            episode_number INT DEFAULT 1,
            title VARCHAR(255) NOT NULL,
            stream_url VARCHAR(1000) NOT NULL,
            subtitle_url VARCHAR(1000),
            duration VARCHAR(50),
            description TEXT,
            display_order INT DEFAULT 0,
            views_count INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_series (series_id),
            KEY idx_series_order (series_id, episode_number, display_order, id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    } catch (PDOException $e) {
        error_log('helpers.php content schema: ' . $e->getMessage());
    }

    /* ⑤ فهارس مفقودة على تركيبات قائمة بالفعل (الجداول أُنشئت سابقاً
         بلا فهارس، و CREATE TABLE IF NOT EXISTS لا يضيفها).
         كل أمر داخل try مستقل لأن تكرار الفهرس يرمي استثناءً. */
    /* أعمدة مفقودة في تركيبات قديمة — كل واحد داخل try مستقل
       لأن إعادة إضافة عمود موجود ترمي استثناءً.
       أهمها is_active في series: api.php يستعلم به، وغيابه يجعل
       صفحة الحلقات فارغة أو تعرض «تعذر تحميل الحلقات». */
    $__columns = [
        "ALTER TABLE series ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1",
        "ALTER TABLE series ADD COLUMN poster_url VARCHAR(500) NULL",
        "ALTER TABLE series ADD COLUMN description TEXT NULL",
    ];
    foreach ($__columns as $__sql) {
        try { $pdo->exec($__sql); } catch (PDOException $e) { /* موجود مسبقاً */ }
    }

    // ضمان أن الصفوف القديمة نشطة (لا تختفي بعد إضافة العمود)
    try { $pdo->exec("UPDATE series SET is_active = 1 WHERE is_active IS NULL"); }
    catch (PDOException $e) { /* العمود غير موجود أو لا صفوف */ }

    $__indexes = [
        "ALTER TABLE episodes   ADD INDEX idx_series (series_id)",
        "ALTER TABLE episodes   ADD INDEX idx_series_order (series_id, episode_number, display_order, id)",
        "ALTER TABLE series     ADD INDEX idx_category (category_id)",
        "ALTER TABLE series     ADD INDEX idx_cat_order (category_id, display_order, id)",
        "ALTER TABLE channels   ADD INDEX idx_cat_order (category_id, display_order, id)",
        "ALTER TABLE login_logs ADD INDEX idx_ip_time (ip_address, attempt_time)",
        "ALTER TABLE view_stats ADD INDEX idx_channel_time (channel_id, viewed_at)",
    ];
    foreach ($__indexes as $__sql) {
        try { $pdo->exec($__sql); } catch (PDOException $e) { /* موجود مسبقاً */ }
    }

    /* ⚠️ إصلاح عطل في منطق العلامة نفسه:
       كانت العلامة تُكتب **دائماً** في نهاية الكتلة، حتى لو فشلت
       أوامر CREATE TABLE (فهي داخل try/catch يكتفي بالتسجيل).
       والنتيجة أن جدولاً ناقصاً يبقى ناقصاً إلى الأبد، لأن العلامة
       تمنع إعادة المحاولة في كل الطلبات التالية — وهذا بالضبط ما
       حدث مع جدولَي login_logs و blocked_ips.

       الآن: نتحقق فعلياً من وجود الجداول الأساسية، ولا نكتب العلامة
       إلا إذا اكتملت جميعها. أي نقص يعني إعادة المحاولة في الطلب
       التالي تلقائياً — تهيئة ذاتية الإصلاح. */
    $__mustExist = ['login_logs', 'blocked_ips', 'admin_users', 'series', 'episodes'];
    $__allOk     = true;

    foreach ($__mustExist as $__t) {
        try {
            $pdo->query("SELECT 1 FROM `{$__t}` LIMIT 1");
        } catch (PDOException $e) {
            $__allOk = false;
            error_log("helpers.php: الجدول {$__t} ما زال مفقوداً — ستُعاد المحاولة في الطلب التالي.");
        }
    }

    if ($__allOk) {
        if (!is_dir(dirname($__schemaFlag))) {
            @mkdir(dirname($__schemaFlag), 0750, true);
        }
        @file_put_contents($__schemaFlag, date('c'), LOCK_EX);
    }
}

define('VID_UPLOAD_DIR',   dirname(__DIR__) . '/uploads/videos/');
define('VID_SUB_DIR',      dirname(__DIR__) . '/uploads/subtitles/');
define('VID_MERGED_DIR',   dirname(__DIR__) . '/uploads/merged/');
define('SERIES_DIR',       dirname(__DIR__) . '/uploads/series/');
define('MUSIC_DIR',        dirname(__DIR__) . '/uploads/music/');

$_base = rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME'])),'/');
define('VID_UPLOAD_URL',   $_base . '/uploads/videos/');
define('VID_SUB_URL',      $_base . '/uploads/subtitles/');
define('VID_MERGED_URL',   $_base . '/uploads/merged/');
define('SERIES_URL',       $_base . '/uploads/series/');
define('POSTERS_DIR',      dirname(__DIR__) . '/uploads/posters/');
define('POSTERS_URL',      $_base . '/uploads/posters/');
define('MUSIC_URL',        $_base . '/uploads/music/');
define('OS_API', 'https://api.opensubtitles.com/api/v1');
define('OS_UA',  'ShashetyIPTV v2.0');

foreach ([VID_UPLOAD_DIR,VID_SUB_DIR,VID_MERGED_DIR,SERIES_DIR,POSTERS_DIR,MUSIC_DIR] as $_d)
    if(!is_dir($_d)) @mkdir($_d,0755,true);

/**
 * حذف آمن لملف محلي فقط (فيديو/ترجمة/بوستر).
 * - يتجاهل الروابط الخارجية (http/https لمضيف آخر) فلا تُحذف.
 * - يحوّل رابط الموقع إلى مسار على القرص.
 * - يتحقق أن المسار النهائي يقع داخل مجلد uploads قبل الحذف (حماية من الخروج بـ ../).
 * يُرجع true إذا حُذف الملف فعلياً.
 */
function shashetyDeleteLocalFile($url) {
    $url = trim((string)$url);
    if ($url === '') return false;

    $uploadsBase = realpath(dirname(__DIR__) . '/uploads');
    if ($uploadsBase === false) return false;

    $path = null;

    // 1) رابط كامل: اقبله فقط إن كان يشير لنفس مجلد uploads على هذا الخادم
    if (preg_match('#^https?://#i', $url)) {
        $p = parse_url($url, PHP_URL_PATH);
        if ($p === false || $p === null) return false;
        $p = urldecode($p);
        $pos = strpos($p, '/uploads/');
        if ($pos === false) return false;            // رابط خارجي لا يخص مجلداتنا → اتركه
        $rel = substr($p, $pos + strlen('/uploads/')); // الجزء بعد uploads/
        $path = dirname(__DIR__) . '/uploads/' . $rel;
    }
    // 2) مسار يبدأ بـ /uploads/ أو فيه /uploads/
    elseif (strpos($url, '/uploads/') !== false) {
        $pos  = strpos($url, '/uploads/');
        $rel  = substr($url, $pos + strlen('/uploads/'));
        $path = dirname(__DIR__) . '/uploads/' . urldecode($rel);
    }
    // 3) اسم ملف مجرّد (بحث في المجلدات المعروفة)
    else {
        $name = basename($url);
        foreach ([SERIES_DIR, VID_UPLOAD_DIR, VID_SUB_DIR, VID_MERGED_DIR, POSTERS_DIR] as $dir) {
            if (is_file($dir . $name)) { $path = $dir . $name; break; }
        }
        if ($path === null) return false;
    }

    if (!is_file($path)) return false;
    $real = realpath($path);
    if ($real === false) return false;

    // تأكيد أن الملف داخل مجلد uploads فقط (منع حذف أي شيء خارجه)
    if (strpos($real, $uploadsBase) !== 0) return false;

    return @unlink($real);
}


/**
 * ترحيل لمرّة واحدة: ضمان أن المحتوى المُضاف يظهر على الموقع.
 *
 * ── المشكلة التي يعالجها ──
 * كل جمل `INSERT INTO channels` تضبط is_active = 1 صراحةً، بينما لم
 * تكن أيٌّ من جمل `INSERT INTO series` تضبطها — كانت تعتمد على القيمة
 * الافتراضية للعمود. وفي الوقت نفسه يفلتر api.php المسلسلات بـ
 * `s.is_active = 1` في ثمانية مواضع، ولا تفلترها لوحة الإدارة إطلاقاً.
 *
 * فإن لم يكن للعمود افتراضٌ صحيح، فالنتيجة: الفيلم يُحفَظ، ويظهر في
 * اللوحة، ولا يظهر في index.php أبداً — لا بعد دقيقة ولا بعد يوم. بل
 * إن بصمة المحتوى نفسها تفلتر بـis_active، فلا تتغيّر، فلا يُبطَل أي
 * كاش، ولا يصل إشعار تحديث. غيابٌ صامت تامّ، وهو أسوأ أنواع الأعطال:
 * لا رسالة خطأ تدلّ عليه.
 *
 * الجمل مصحّحة الآن في المصدر، لكن الصفوف التي أُدخلت قبل التصحيح
 * تبقى مخفيّة، ولذلك هذا الترحيل.
 *
 * ⚠ نصلح NULL فقط ولا نمسّ 0 إطلاقاً: NULL تعني «لم تُضبط قط»، أما 0
 * فتعني «عطّلها المدير عمداً» — وإعادة تفعيلها ستُظهر للمشتركين محتوى
 * أُخفي بقرار.
 *
 * @return bool
 */
function contentEnsureActiveFlags(): bool
{
    static $done = null;
    if ($done !== null) return $done;

    $flag = __DIR__ . '/../storage/.content_active_ok';
    if (is_file($flag)) { return $done = true; }

    try {
        $pdo = function_exists('db') ? db() : ($GLOBALS['pdo'] ?? null);
        if (!$pdo instanceof PDO) return $done = false;

        foreach (['series', 'channels'] as $tbl) {
            // هل العمود موجود أصلاً؟ لا نفترض بنية لم نتحقّق منها.
            $c = $pdo->prepare(
                "SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = 'is_active'"
            );
            $c->execute([$tbl]);
            if ((int) $c->fetchColumn() === 0) continue;

            // ① الصفوف التي لم تُضبط قط
            try { $pdo->exec("UPDATE `$tbl` SET is_active = 1 WHERE is_active IS NULL"); }
            catch (PDOException $e) { /* قد يكون العمود NOT NULL — لا ضير */ }

            // ② الافتراض نفسه، حتى لا تعود المشكلة مع أي إدراج مستقبلي
            //    كُتب من خارج هذا المشروع (سكربت استيراد، أداة خارجية…)
            try { $pdo->exec("ALTER TABLE `$tbl` ALTER COLUMN is_active SET DEFAULT 1"); }
            catch (PDOException $e) { /* لا صلاحية ALTER — التصحيح في الكود يكفي */ }
        }

        @file_put_contents($flag, date('c'));
        return $done = true;

    } catch (PDOException $e) {
        error_log('shashety contentEnsureActiveFlags: ' . $e->getMessage());
        return $done = false;
    }
}
