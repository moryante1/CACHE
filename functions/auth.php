<?php
/**
 * بوابة المصادقة والرخصة للوحة التحكم — Shashety IPTV
 * (كانت الأسطر 74-99 من admin.php الأصلي)
 *
 * ══════════════════════════════════════════════════════════════
 *  الأعطال التي عولجت في هذه النسخة
 * ══════════════════════════════════════════════════════════════
 *
 *  ① عطل الأداء الأكبر في النظام:
 *     كانت verifyLicenseFromServer() تُستدعى في *كل* طلب — كل فتح
 *     صفحة وكل نداء AJAX — وهي تفتح اتصال HTTP خارجياً بمهلة 10 ثوانٍ.
 *     النتيجة: كل نقرة في اللوحة تنتظر السيرفر الخارجي، وأي عملية
 *     استيراد أو رفع تصبح بطيئة جداً.
 *     الحل: تخزين نتيجة التحقق مؤقتاً (12 ساعة افتراضياً) على القرص
 *     بتوقيع HMAC يمنع التزوير.
 *
 *  ② عطل توقف كامل عند انقطاع سيرفر الرخص:
 *     النظام يعرّف OFFLINE_GRACE_DAYS (7 أيام سماح أوفلاين) لكن
 *     verifyLicenseFromServer لم تكن تستخدمها إطلاقاً — كانت تُرجع
 *     valid=false عند فشل الاتصال فيُطرد المدير إلى activate.php.
 *     أي انقطاع شبكة = قفل اللوحة بالكامل.
 *     الحل: التمييز بين "فشل اتصال" (نطبّق فترة السماح) و"رخصة
 *     مرفوضة فعلاً" (نمنع فوراً).
 *
 *  ③ CREATE TABLE settings كان يُنفَّذ في كل طلب.
 *     الحل: يُنفَّذ مرة واحدة ويُسجَّل في ملف علامة.
 *
 *  ④ استعلام الإعدادات كان يُقرأ صفاً صفاً بلا كاش.
 *     الحل: fetchAll + كاش 60 ثانية.
 *
 *  ⑤ نداءات AJAX كانت تُعاد توجيهها إلى login.php بصفحة HTML،
 *     فيرى المستخدم خطأ JSON غامضاً. الآن تُرجع JSON واضحاً.
 */

/* ════════════════════ نهاية تحسينات شاشتي المدمجة ════════════════════ */

// ── هل هذا طلب AJAX/JSON؟ نحتاجه لإرجاع رد مناسب بدل صفحة HTML ──
if (!function_exists('shashetyIsAjaxRequest')) {
    function shashetyIsAjaxRequest(): bool
    {
        if (isset($_POST['ajax_action'])) {
            return true;
        }
        if (strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest') {
            return true;
        }
        return strpos((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json') !== false;
    }
}

/**
 * إنهاء الطلب بسبب مشكلة رخصة/جلسة، بالشكل المناسب لنوع الطلب.
 */
if (!function_exists('shashetyGateExit')) {
    function shashetyGateExit(string $location, string $message): void
    {
        if (shashetyIsAjaxRequest()) {
            while (ob_get_level()) {
                ob_end_clean();
            }
            http_response_code(403);
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
            }
            echo json_encode(
                ['success' => false, 'error' => $message, 'redirect' => $location],
                JSON_UNESCAPED_UNICODE
            );
            exit;
        }

        if (!headers_sent()) {
            header('Location: ' . $location);
        }
        exit;
    }
}

/* ══════════════════════════════════════════════════════════════
   كاش نتيجة التحقق من الرخصة
   ══════════════════════════════════════════════════════════════ */

if (!defined('SHASHETY_LICENSE_CACHE_TTL')) {
    // مدة الثقة بنتيجة تحقق ناجحة قبل سؤال السيرفر مجدداً.
    define('SHASHETY_LICENSE_CACHE_TTL', 12 * 3600); // 12 ساعة
}

if (!function_exists('shashetyLicenseCacheFile')) {
    function shashetyLicenseCacheFile(): string
    {
        $dir = defined('CACHE_DIR') ? CACHE_DIR : (dirname(__DIR__) . '/storage/cache');
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        return $dir . '/.license_state';
    }
}

/**
 * قراءة نتيجة تحقق مخزَّنة وصالحة، أو null.
 * الملف موقَّع بـ HMAC حتى لا يستطيع أحد تزوير رخصة صالحة بتحرير الملف.
 */
if (!function_exists('shashetyLicenseCacheGet')) {
    function shashetyLicenseCacheGet(string $licenseKey): ?array
    {
        $file = shashetyLicenseCacheFile();
        if (!is_readable($file)) {
            return null;
        }

        $raw = @file_get_contents($file);
        if ($raw === false || strpos($raw, '|') === false) {
            return null;
        }

        [$sig, $json] = explode('|', $raw, 2);

        $secret   = (defined('APP_KEY') ? APP_KEY : '') . SECURITY_SALT . $licenseKey;
        $expected = hash_hmac('sha256', $json, $secret);

        if (!hash_equals($sig, $expected)) {
            return null; // ملف معدَّل يدوياً → نتجاهله
        }

        $data = json_decode($json, true);
        if (!is_array($data) || !isset($data['exp'], $data['result'])) {
            return null;
        }

        if ((int) $data['exp'] < time()) {
            return null; // انتهت صلاحية الكاش
        }

        return is_array($data['result']) ? $data['result'] : null;
    }
}

/** حفظ نتيجة تحقق ناجحة في الكاش. */
if (!function_exists('shashetyLicenseCacheSet')) {
    function shashetyLicenseCacheSet(string $licenseKey, array $result, int $ttl): void
    {
        $json = json_encode(
            ['exp' => time() + $ttl, 'result' => $result],
            JSON_UNESCAPED_UNICODE
        );

        $secret = (defined('APP_KEY') ? APP_KEY : '') . SECURITY_SALT . $licenseKey;
        $sig    = hash_hmac('sha256', $json, $secret);

        $file = shashetyLicenseCacheFile();
        @file_put_contents($file, $sig . '|' . $json, LOCK_EX);
        @chmod($file, 0600);
    }
}

/** إبطال كاش الرخصة (يُستدعى من صفحة التفعيل عند تغيير المفتاح). */
if (!function_exists('shashetyLicenseCacheClear')) {
    function shashetyLicenseCacheClear(): void
    {
        $file = shashetyLicenseCacheFile();
        if (is_file($file)) {
            @unlink($file);
        }
    }
}

/**
 * التحقق من الرخصة مع كاش وفترة سماح أوفلاين.
 *
 * @return array{ok:bool, license:array, offline:bool, reason:string}
 */
if (!function_exists('shashetyResolveLicense')) {
    function shashetyResolveLicense(string $licenseKey): array
    {
        // ① نتيجة مخزَّنة صالحة → لا اتصال شبكي إطلاقاً (المسار السريع)
        $cached = shashetyLicenseCacheGet($licenseKey);
        if ($cached !== null) {
            return [
                'ok'      => true,
                'license' => $cached,
                'offline' => false,
                'reason'  => 'cache',
            ];
        }

        // ② سؤال سيرفر الرخص
        $res = verifyLicenseFromServer($licenseKey);

        $success = !empty($res['success']);
        $valid   = !empty($res['valid']);

        if ($success && $valid) {
            $license = is_array($res['license'] ?? null) ? $res['license'] : [];
            shashetyLicenseCacheSet($licenseKey, $license, SHASHETY_LICENSE_CACHE_TTL);
            return [
                'ok'      => true,
                'license' => $license,
                'offline' => false,
                'reason'  => 'server',
            ];
        }

        // ③ التمييز الحاسم: هل السيرفر رفض الرخصة، أم أن الاتصال فشل؟
        //    الرفض الصريح (success=true, valid=false) يعني رخصة منتهية/ملغاة → منع فوري.
        if ($success && !$valid) {
            shashetyLicenseCacheClear();
            return [
                'ok'      => false,
                'license' => [],
                'offline' => false,
                'reason'  => 'rejected',
            ];
        }

        // ④ فشل اتصال → فترة السماح الأوفلاين (كانت معطَّلة تماماً قبل الإصلاح)
        $lastOk = function_exists('getLastVerifySuccess') ? getLastVerifySuccess() : null;
        if ($lastOk !== null) {
            $daysSince = (time() - $lastOk) / 86400;
            $grace     = defined('OFFLINE_GRACE_DAYS') ? (int) OFFLINE_GRACE_DAYS : 7;

            if ($daysSince <= $grace) {
                $license = [
                    'offline_mode'    => true,
                    'grace_days_left' => round($grace - $daysSince, 1),
                    'days_left'       => (int) ceil($grace - $daysSince),
                ];
                // كاش قصير (15 دقيقة) حتى لا نُعيد محاولة الاتصال في كل طلب
                shashetyLicenseCacheSet($licenseKey, $license, 900);
                return [
                    'ok'      => true,
                    'license' => $license,
                    'offline' => true,
                    'reason'  => 'grace',
                ];
            }
        }

        return [
            'ok'      => false,
            'license' => [],
            'offline' => true,
            'reason'  => 'unreachable',
        ];
    }
}

/* ══════════════════════════════════════════════════════════════
   البوابة الفعلية
   ══════════════════════════════════════════════════════════════ */

$license_key = getLicenseKey();
if (!$license_key) {
    shashetyGateExit('activate.php', 'النظام غير مُفعَّل — يرجى تفعيل الرخصة.');
}

$license_state = shashetyResolveLicense($license_key);

if (!$license_state['ok']) {
    $license_message = $license_state['reason'] === 'rejected'
        ? 'الرخصة غير صالحة أو منتهية الصلاحية.'
        : 'تعذّر التحقق من الرخصة وانتهت فترة السماح دون اتصال.';
    shashetyGateExit('activate.php', $license_message);
}

$_SESSION['license_info']      = $license_state['license'];
$_SESSION['license_days_left'] = $license_state['license']['days_left'] ?? 0;
$_SESSION['license_offline']   = $license_state['offline'];

// ── التحقق من تسجيل دخول المدير ──
if (!isAdminLoggedIn()) {
    shashetyGateExit('login.php', 'انتهت صلاحية الجلسة، يرجى تسجيل الدخول من جديد.');
}

/* ══════════════════════════════════════════════════════════════
   جدول الإعدادات — يُنشأ مرة واحدة فقط لا في كل طلب
   ══════════════════════════════════════════════════════════════ */

$__settingsFlag = (defined('CACHE_DIR') ? CACHE_DIR : dirname(__DIR__) . '/storage/cache')
    . '/.settings_table_ok';

if (!is_file($__settingsFlag)) {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) NOT NULL UNIQUE,
            setting_value TEXT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        if (!is_dir(dirname($__settingsFlag))) {
            @mkdir(dirname($__settingsFlag), 0750, true);
        }
        @file_put_contents($__settingsFlag, '1', LOCK_EX);

    } catch (PDOException $e) {
        error_log('auth.php CREATE TABLE settings: ' . $e->getMessage());
    }
}

/* ══════════════════════════════════════════════════════════════
   جلب الإعدادات — fetchAll مع كاش قصير بدل حلقة fetch في كل طلب
   ══════════════════════════════════════════════════════════════ */

$settings = function_exists('cacheGet') ? cacheGet('site_settings') : null;

if (!is_array($settings)) {
    $settings = [];
    try {
        $rows = $pdo->query("SELECT setting_key, setting_value FROM settings")
                    ->fetchAll(PDO::FETCH_KEY_PAIR);
        if (is_array($rows)) {
            $settings = $rows;
        }
        if (function_exists('cacheSet')) {
            cacheSet('site_settings', $settings, 60);
        }
    } catch (PDOException $e) {
        error_log('auth.php load settings: ' . $e->getMessage());
    }
}
