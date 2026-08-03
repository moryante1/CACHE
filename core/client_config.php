<?php
/**
 * إعدادات الاتصال بسيرفر الرخص - Shashety IPTV
 * License Server Connection Settings (v4 - HWID Fix)
 * ============================================================
 * 🎯 إصلاح: HWID لم يعد يعتمد على IP السيرفر أو HTTP_HOST
 *   - عند تغيير IP السيرفر → HWID يبقى كما هو
 *   - عند تغيير Domain → HWID يبقى كما هو
 *   - عند نقل المجلد → HWID يبقى كما هو
 *   - عند تغيير إصدار Apache/Nginx → HWID يبقى كما هو
 * 
 * 🔒 الحماية من النسخ:
 *   - HWID يُولّد مرة واحدة فقط بقيمة عشوائية فريدة
 *   - يُحفظ في 3 ملفات مخفية مشفّرة وموقّعة بـ HMAC
 *   - عند نسخ النظام لسيرفر آخر، نقل ملفات HWID ينقل البصمة معه
 *     (وهذا مقبول لأن السيرفر يتحقق من تطابق HWID + License Key)
 *   - إذا أراد العميل تشغيل النظام على سيرفر ثاني، يحتاج رخصة ثانية
 */

// ════════════════════════════════════════════════════════════
// إعدادات سيرفر الرخص - غيّرها!
// ════════════════════════════════════════════════════════════

define('LICENSE_SERVER_URL', 'http://localhost/act/api.php');
define('LICENSE_API_KEY', 'your-secret-key-change-this-2024');
define('SECURITY_SALT', 'SHASHETY_PRO_LOCK_2024');

// مدة السماح بالعمل أوفلاين عند فشل الاتصال
define('OFFLINE_GRACE_DAYS', 7);

// مسارات تخزين HWID (3 نسخ احتياطية)
define('HWID_PRIMARY',  dirname(__DIR__) . '/.hwid_primary.dat');
define('HWID_BACKUP_1', dirname(__DIR__) . '/.system_id.dat');
define('HWID_BACKUP_2', dirname(__DIR__) . '/cache/.identity.dat');


// ════════════════════════════════════════════════════════════
// دوال النظام
// ════════════════════════════════════════════════════════════

/**
 * 🔑 الحصول على HWID ثابت 100%
 * ============================================
 * المنطق:
 * - إذا الملف موجود: اقرأه وأعِده (لن يتغير أبداً)
 * - إذا غير موجود: ولّده مرة واحدة فقط ثم احفظه دائماً
 * 
 * ⚠️ ملاحظة: لا نستخدم أي قيمة من $_SERVER لأنها متغيرة
 */
function getMachineId() {
    // محاولة القراءة من الملفات الثلاثة بالترتيب
    foreach ([HWID_PRIMARY, HWID_BACKUP_1, HWID_BACKUP_2] as $file) {
        $hwid = readHwidFromFile($file);
        if ($hwid !== null) {
            // التأكد من وجود كل النسخ الاحتياطية
            ensureAllBackups($hwid);
            return $hwid;
        }
    }
    
    // ✨ أول مرة فقط: نولّد البصمة ونحفظها للأبد
    $hwid = generateInitialHwid();
    ensureAllBackups($hwid);
    
    return $hwid;
}


/**
 * توليد HWID لأول مرة فقط
 * يُستدعى مرة واحدة طوال عمر النظام
 */
function generateInitialHwid() {
    $parts = [];
    
    // فحص هل exec شغّال
    $disabled = array_map('trim', explode(',', ini_get('disable_functions')));
    $can_exec = function_exists('shell_exec') && !in_array('shell_exec', $disabled);
    
    // 1) معلومات الهاردوير الحقيقية (إن أمكن)
    if ($can_exec) {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $out = @shell_exec('wmic csproduct get uuid 2>nul');
            if ($out) $parts[] = 'win_uuid:' . trim($out);
            
            $out = @shell_exec('wmic baseboard get serialnumber 2>nul');
            if ($out) $parts[] = 'win_mb:' . trim($out);
        } else {
            $out = @shell_exec('cat /etc/machine-id 2>/dev/null');
            if ($out) $parts[] = 'lnx_mid:' . trim($out);
            
            $out = @shell_exec('cat /sys/class/dmi/id/product_uuid 2>/dev/null');
            if ($out) $parts[] = 'lnx_uuid:' . trim($out);
        }
    }
    
    // 2) قراءة مباشرة (لا تحتاج exec)
    if (is_readable('/etc/machine-id')) {
        $parts[] = 'mid:' . trim(@file_get_contents('/etc/machine-id'));
    }
    
    // 3) معلومات PHP الثابتة (OS لا يتغير عادة)
    $parts[] = 'os:' . PHP_OS;
    $parts[] = 'arch:' . php_uname('m');
    
    // 4) ⭐ القيمة العشوائية القوية (32 بايت = 256 بت)
    // هذا هو الضامن الأساسي للتفرّد حتى لو فشل كل ما سبق
    try {
        $parts[] = 'rnd:' . bin2hex(random_bytes(32));
    } catch (Exception $e) {
        // fallback
        $parts[] = 'rnd:' . bin2hex(openssl_random_pseudo_bytes(32));
    }
    
    // 5) Timestamp التوليد
    $parts[] = 'gen:' . microtime(true);
    
    // 6) معرّف العملية (إضافة عشوائية إضافية)
    $parts[] = 'pid:' . getmypid();
    
    return hash('sha256', 'SHASHETY_HWID_v4|' . implode('||', $parts) . '|' . SECURITY_SALT);
}


/**
 * قراءة HWID من ملف مع التحقق من سلامة التوقيع
 */
function readHwidFromFile($file) {
    if (!file_exists($file)) return null;
    
    $encrypted = @file_get_contents($file);
    if (!$encrypted) return null;
    
    $data = base64_decode($encrypted, true);
    if ($data === false || strlen($data) < 32) return null;
    
    $signature = substr($data, 0, 32);
    $payload   = substr($data, 32);
    
    $key = hash('sha256', SECURITY_SALT, true);
    $expected = hash_hmac('sha256', $payload, $key, true);
    
    // التحقق من التوقيع
    if (!hash_equals($signature, $expected)) {
        return null;
    }
    
    // التحقق من شكل HWID (sha256 = 64 hex chars)
    if (!preg_match('/^[a-f0-9]{64}$/', $payload)) {
        return null;
    }
    
    return $payload;
}


/**
 * كتابة HWID في ملف مع توقيع HMAC
 */
function writeHwidToFile($file, $hwid) {
    $dir = dirname($file);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    
    $key = hash('sha256', SECURITY_SALT, true);
    $signature = hash_hmac('sha256', $hwid, $key, true);
    $encrypted = base64_encode($signature . $hwid);
    
    @file_put_contents($file, $encrypted, LOCK_EX);
    @chmod($file, 0644);
    
    // إخفاء الملف على Windows
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        @shell_exec('attrib +H "' . $file . '" 2>nul');
    }
}


/**
 * التأكد من وجود كل النسخ الاحتياطية
 */
function ensureAllBackups($hwid) {
    foreach ([HWID_PRIMARY, HWID_BACKUP_1, HWID_BACKUP_2] as $file) {
        if (!file_exists($file)) {
            writeHwidToFile($file, $hwid);
        }
    }
}


/**
 * ════════════════════════════════════════════════════════════
 * التحقق من الرخصة من السيرفر
 * ════════════════════════════════════════════════════════════
 */
function verifyLicenseFromServer($license_key) {
    $machine_id = getMachineId();
    
    $params = http_build_query([
        'action'      => 'verify',
        'machine_id'  => $machine_id,
        'hwid'        => $machine_id,        // توافق مع API
        'license_key' => $license_key,
        'key'         => $license_key,       // توافق مع API
        'api_key'     => LICENSE_API_KEY,
        'token'       => md5($license_key . $machine_id . SECURITY_SALT)
    ]);
    
    $url = LICENSE_SERVER_URL . '?' . $params;
    
    try {
        $context = stream_context_create([
            'http' => [
                'timeout'       => 10,
                'ignore_errors' => true
            ]
        ]);
        
        $response = @file_get_contents($url, false, $context);
        
        if ($response === false) {
            return [
                'success' => false,
                'valid'   => false,
                'error'   => 'لا يمكن الاتصال بسيرفر الرخص'
            ];
        }
        
        $data = json_decode($response, true);
        
        if (!$data) {
            return [
                'success' => false,
                'valid'   => false,
                'error'   => 'خطأ في استجابة السيرفر'
            ];
        }
        
        // حفظ توقيت آخر تحقق ناجح للـ Offline Grace
        if (!empty($data['success']) && !empty($data['valid'])) {
            saveLastVerifySuccess();
        }
        
        return $data;
        
    } catch(Exception $e) {
        return [
            'success' => false,
            'valid'   => false,
            'error'   => $e->getMessage()
        ];
    }
}


/**
 * ════════════════════════════════════════════════════════════
 * فحص حالة الرخصة (مع Offline Grace Period)
 * ════════════════════════════════════════════════════════════
 */
function checkLicenseStatus() {
    $machine_id  = getMachineId();
    $license_key = getLicenseKey();
    
    $params = http_build_query([
        'action'      => 'check_status',
        'machine_id'  => $machine_id,
        'license_key' => $license_key,    // إرسال المفتاح للتحقق الدقيق
        'api_key'     => LICENSE_API_KEY
    ]);
    
    $url = LICENSE_SERVER_URL . '?' . $params;
    
    try {
        $context = stream_context_create([
            'http' => [
                'timeout'       => 8,
                'ignore_errors' => true
            ]
        ]);
        
        $response = @file_get_contents($url, false, $context);
        
        // الاتصال نجح
        if ($response !== false) {
            $data = json_decode($response, true);
            if ($data && !empty($data['success'])) {
                if (!empty($data['is_valid'])) {
                    saveLastVerifySuccess();
                }
                return $data;
            }
        }
        
        // فشل الاتصال → فحص Grace Period
        if ($license_key) {
            $last_success = getLastVerifySuccess();
            if ($last_success !== null) {
                $days_since = (time() - $last_success) / 86400;
                if ($days_since <= OFFLINE_GRACE_DAYS) {
                    return [
                        'success'         => true,
                        'has_license'     => true,
                        'is_valid'        => true,
                        'offline_mode'    => true,
                        'grace_days_left' => round(OFFLINE_GRACE_DAYS - $days_since, 1),
                        'license_key'     => $license_key
                    ];
                }
            }
        }
        
        return null;
        
    } catch(Exception $e) {
        return null;
    }
}


/**
 * حفظ مفتاح الرخصة
 */
function saveLicenseKey($license_key) {
    return file_put_contents(dirname(__DIR__) . '/license_key.txt', $license_key);
}


/**
 * قراءة مفتاح الرخصة
 */
function getLicenseKey() {
    $file = dirname(__DIR__) . '/license_key.txt';
    if (file_exists($file)) {
        return trim(file_get_contents($file));
    }
    return null;
}


/**
 * حذف مفتاح الرخصة
 */
function deleteLicenseKey() {
    $file = dirname(__DIR__) . '/license_key.txt';
    if (file_exists($file)) {
        return unlink($file);
    }
    return true;
}


/**
 * ════════════════════════════════════════════════════════════
 * Offline Grace Period - الحفاظ على آخر تحقق ناجح
 * ════════════════════════════════════════════════════════════
 */
function saveLastVerifySuccess() {
    $file = dirname(__DIR__) . '/.last_verify.dat';
    $timestamp = (string) time();
    $key = hash('sha256', SECURITY_SALT . '_VERIFY', true);
    $sig = hash_hmac('sha256', $timestamp, $key);
    @file_put_contents($file, $sig . '|' . $timestamp, LOCK_EX);
    
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        @shell_exec('attrib +H "' . $file . '" 2>nul');
    }
}


function getLastVerifySuccess() {
    $file = dirname(__DIR__) . '/.last_verify.dat';
    if (!file_exists($file)) return null;
    
    $content = @file_get_contents($file);
    if (!$content || strpos($content, '|') === false) return null;
    
    list($sig, $timestamp) = explode('|', $content, 2);
    $key = hash('sha256', SECURITY_SALT . '_VERIFY', true);
    $expected = hash_hmac('sha256', $timestamp, $key);
    
    if (!hash_equals($sig, $expected)) return null;
    
    return (int) $timestamp;
}
