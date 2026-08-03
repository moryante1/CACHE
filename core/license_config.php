<?php
/**
 * نظام الرخص - إعدادات
 * License System Configuration
 */

// توليد معرف فريد للجهاز
function getMachineId() {
    // استخدام عدة عوامل لتوليد معرف فريد
    $factors = [
        php_uname('n'), // اسم الجهاز
        $_SERVER['SERVER_ADDR'] ?? '', // IP السيرفر
        $_SERVER['SERVER_SOFTWARE'] ?? '', // برنامج السيرفر
        $_SERVER['DOCUMENT_ROOT'] ?? '', // مسار الجذر
    ];
    
    $unique_string = implode('|', $factors);
    return hash('sha256', $unique_string);
}

// التحقق من الرخصة
function checkLicense($pdo) {
    $machine_id = getMachineId();
    
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM licenses 
            WHERE machine_id = ? AND is_active = 1
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute([$machine_id]);
        $license = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$license) {
            return [
                'valid' => false,
                'message' => 'لا توجد رخصة مفعلة',
                'machine_id' => $machine_id
            ];
        }
        
        // التحقق من انتهاء الصلاحية
        if ($license['license_type'] !== 'lifetime') {
            $expiry = strtotime($license['expiry_date']);
            $now = time();
            
            if ($expiry < $now) {
                return [
                    'valid' => false,
                    'message' => 'الرخصة منتهية الصلاحية',
                    'license' => $license,
                    'machine_id' => $machine_id,
                    'expired' => true
                ];
            }
            
            // حساب الأيام المتبقية
            $days_left = ceil(($expiry - $now) / 86400);
            
            return [
                'valid' => true,
                'license' => $license,
                'machine_id' => $machine_id,
                'days_left' => $days_left,
                'expiry_date' => $license['expiry_date']
            ];
        }
        
        return [
            'valid' => true,
            'license' => $license,
            'machine_id' => $machine_id,
            'days_left' => 'مفتوح',
            'lifetime' => true
        ];
        
    } catch(PDOException $e) {
        return [
            'valid' => false,
            'message' => 'خطأ في التحقق من الرخصة',
            'machine_id' => $machine_id
        ];
    }
}

// تسجيل عملية في سجل الرخص
function logLicenseAction($pdo, $machine_id, $action, $details = null) {
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $stmt = $pdo->prepare("
            INSERT INTO license_logs (machine_id, action, details, ip_address)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$machine_id, $action, $details, $ip]);
    } catch(PDOException $e) {
        // تجاهل الأخطاء في التسجيل
    }
}

// حساب تاريخ انتهاء الرخصة
function calculateExpiryDate($license_type) {
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
            return null; // لا تاريخ انتهاء
        default:
            $now->modify('+1 day');
    }
    
    return $now->format('Y-m-d H:i:s');
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
