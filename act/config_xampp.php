<?php
/**
 * License Server Configuration - XAMPP Version
 * إعدادات سيرفر الرخص - نسخة XAMPP
 */

// إعدادات قاعدة البيانات لـ XAMPP
define('DB_HOST', 'localhost');
define('DB_NAME', 'license_server');
define('DB_USER', 'root');
define('DB_PASS', ''); // فارغ في XAMPP

// مفتاح API السري (غيّره!)
define('API_SECRET_KEY', 'your-secret-key-change-this-2024');

// الاتصال بقاعدة البيانات
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch(PDOException $e) {
    // عرض الخطأ بالتفصيل للتشخيص
    die(json_encode([
        'success' => false,
        'error' => 'Database connection failed',
        'details' => $e->getMessage(),
        'settings' => [
            'host' => DB_HOST,
            'database' => DB_NAME,
            'user' => DB_USER
        ]
    ]));
}

// دالة توليد مفتاح رخصة
function generateLicenseKey() {
    $prefix = 'IPTV';
    $random = strtoupper(bin2hex(random_bytes(8)));
    $formatted = $prefix . '-' . substr($random, 0, 4) . '-' . substr($random, 4, 4) . '-' . substr($random, 8, 4);
    return $formatted;
}

// دالة حساب تاريخ الانتهاء
function calculateExpiry($license_type) {
    $now = new DateTime();
    
    switch($license_type) {
        case 'trial_1day':
            $now->modify('+1 day');
            break;
        case 'trial_1week':
            $now->modify('+7 days');
            break;
        case 'trial_1month':
            $now->modify('+1 month');
            break;
        case 'monthly':
            $now->modify('+1 month');
            break;
        case 'yearly':
            $now->modify('+1 year');
            break;
        case 'lifetime':
            return null;
        default:
            $now->modify('+1 day');
    }
    
    return $now->format('Y-m-d H:i:s');
}

// دالة تسجيل العمليات
function logVerification($pdo, $machine_id, $license_key, $action, $response) {
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        $stmt = $pdo->prepare("
            INSERT INTO verification_logs (machine_id, license_key, action, ip_address, user_agent, response)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$machine_id, $license_key, $action, $ip, $user_agent, $response]);
    } catch(PDOException $e) {
        // تجاهل أخطاء التسجيل
    }
}

// أسماء أنواع الرخص
function getLicenseTypeName($type) {
    $names = [
        'trial_1day' => 'تجريبي - يوم واحد',
        'trial_1week' => 'تجريبي - أسبوع',
        'trial_1month' => 'تجريبي - شهر',
        'monthly' => 'شهري',
        'yearly' => 'سنوي',
        'lifetime' => 'مفتوح - مدى الحياة'
    ];
    
    return $names[$type] ?? $type;
}
