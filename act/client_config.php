<?php
/**
 * إعدادات العميل المحدثة - Shashety IPTV
 * حل مشكلة تداخل السيرفرات المتعددة على نفس الجهاز
 */

// 1. معلومات سيرفر الرخص (تأكد من الرابط الصحيح)
define('LICENSE_SERVER_URL', 'http://yourserver.com/act/api.php'); 
define('LICENSE_API_KEY', 'your-secret-key-change-this-2024'); 
define('SECURITY_SALT', 'SHASHETY_PRO_LOCK_2024'); // ملح أمني للتشفير

/**
 * توليد معرف فريد وثابت للجهاز يعتمد على الهاردوير (BIOS/OS)
 * تم تعديلها لتكون ثابتة ولا تتأثر بتغيير مكان المجلد
 */
function getMachineId() {
    $data = "";
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        // ويندوز: استخدام سيريال اللوحة الأم
        @exec('wmic baseboard get serialnumber', $out);
        $data = isset($out[1]) ? trim($out[1]) : php_uname();
    } else {
        // لينكس: استخدام UUID النظام الفريد
        if (is_readable('/sys/class/dmi/id/product_uuid')) {
            $data = trim(file_get_contents('/sys/class/dmi/id/product_uuid'));
        } else {
            $data = php_uname();
        }
    }
    return hash('sha256', 'FIXED_HWID_' . $data);
}

/**
 * قراءة مفتاح الرخصة المخزن محلياً
 */
function getLicenseKey() {
    $file = __DIR__ . '/license_key.txt';
    return file_exists($file) ? trim(file_get_contents($file)) : null;
}

/**
 * التحقق من الرخصة عند التفعيل لأول مرة
 */
function verifyLicenseFromServer($license_key) {
    $machine_id = getMachineId();
    
    // توكن أمني لمنع التلاعب بالطلب
    $token = md5($license_key . $machine_id . SECURITY_SALT);
    
    $params = http_build_query([
        'action' => 'verify',
        'hwid' => $machine_id,
        'key' => $license_key,
        'api_key' => LICENSE_API_KEY,
        'token' => $token
    ]);
    
    $url = LICENSE_SERVER_URL . '?' . $params;
    
    try {
        $response = @file_get_contents($url);
        return json_decode($response, true) ?: ['success' => false, 'error' => 'خطأ في استجابة السيرفر'];
    } catch(Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * فحص حالة الرخصة (تم تعديلها لترسل المفتاح لعدم تخريب السيرفرات الأخرى)
 */
function checkLicenseStatus() {
    $machine_id = getMachineId();
    $license_key = getLicenseKey(); // جلب المفتاح الخاص بهذه النسخة
    
    if (!$license_key) {
        return ['success' => true, 'has_license' => false];
    }
    
    $params = http_build_query([
        'action' => 'check_status',
        'machine_id' => $machine_id,
        'license_key' => $license_key, // إرسال المفتاح لضمان فحص هذه النسخة تحديداً
        'api_key' => LICENSE_API_KEY
    ]);
    
    $url = LICENSE_SERVER_URL . '?' . $params;
    
    try {
        $response = @file_get_contents($url);
        if ($response === false) return null;
        return json_decode($response, true);
    } catch(Exception $e) {
        return null;
    }
}
