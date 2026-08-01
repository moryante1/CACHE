<?php
/**
 * License Verification API
 * API التحقق من الرخص
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'config.php';

$action = $_REQUEST['action'] ?? '';
$machine_id = $_REQUEST['machine_id'] ?? '';
$license_key = $_REQUEST['license_key'] ?? '';

// التحقق من المفتاح السري
$provided_key = $_REQUEST['api_key'] ?? '';
if ($provided_key !== API_SECRET_KEY) {
    echo json_encode(['success' => false, 'error' => 'Invalid API key']);
    exit;
}

switch($action) {
    case 'verify':
        // التحقق من الرخصة
        verifyLicense($pdo, $machine_id, $license_key);
        break;
        
    case 'activate':
        // تفعيل رخصة جديدة (من لوحة التحكم فقط)
        activateLicense($pdo);
        break;
        
    case 'check_status':
        // فحص حالة الرخصة
        checkStatus($pdo, $machine_id);
        break;
        
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}

/**
 * التحقق من الرخصة
 */
function verifyLicense($pdo, $machine_id, $license_key) {
    if (empty($machine_id) || empty($license_key)) {
        logVerification($pdo, $machine_id, $license_key, 'verify', 'missing_data');
        echo json_encode(['success' => false, 'error' => 'Machine ID and License Key required']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM licenses 
            WHERE machine_id = ? AND license_key = ? AND is_active = 1
            LIMIT 1
        ");
        $stmt->execute([$machine_id, $license_key]);
        $license = $stmt->fetch();
        
        if (!$license) {
            logVerification($pdo, $machine_id, $license_key, 'verify', 'not_found');
            echo json_encode([
                'success' => false,
                'valid' => false,
                'error' => 'License not found or inactive'
            ]);
            return;
        }
        
        // التحقق من انتهاء الصلاحية
        if ($license['license_type'] !== 'lifetime' && $license['expiry_date']) {
            $expiry = strtotime($license['expiry_date']);
            $now = time();
            
            if ($expiry < $now) {
                logVerification($pdo, $machine_id, $license_key, 'verify', 'expired');
                echo json_encode([
                    'success' => true,
                    'valid' => false,
                    'expired' => true,
                    'expiry_date' => $license['expiry_date']
                ]);
                return;
            }
            
            $days_left = ceil(($expiry - $now) / 86400);
        } else {
            $days_left = 'unlimited';
        }
        
        // تحديث آخر فحص
        $stmt = $pdo->prepare("UPDATE licenses SET last_check = NOW() WHERE id = ?");
        $stmt->execute([$license['id']]);
        
        logVerification($pdo, $machine_id, $license_key, 'verify', 'success');
        
        echo json_encode([
            'success' => true,
            'valid' => true,
            'license' => [
                'customer_name' => $license['customer_name'],
                'license_type' => $license['license_type'],
                'license_type_name' => getLicenseTypeName($license['license_type']),
                'activation_date' => $license['activation_date'],
                'expiry_date' => $license['expiry_date'],
                'days_left' => $days_left
            ]
        ]);
        
    } catch(PDOException $e) {
        logVerification($pdo, $machine_id, $license_key, 'verify', 'error');
        echo json_encode(['success' => false, 'error' => 'Database error']);
    }
}

/**
 * فحص حالة الرخصة
 */
function checkStatus($pdo, $machine_id) {
    if (empty($machine_id)) {
        echo json_encode(['success' => false, 'error' => 'Machine ID required']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM licenses 
            WHERE machine_id = ? 
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute([$machine_id]);
        $license = $stmt->fetch();
        
        if (!$license) {
            echo json_encode([
                'success' => true,
                'has_license' => false,
                'machine_id' => $machine_id
            ]);
            return;
        }
        
        $is_valid = $license['is_active'] == 1;
        
        if ($is_valid && $license['license_type'] !== 'lifetime' && $license['expiry_date']) {
            $is_valid = strtotime($license['expiry_date']) > time();
        }
        
        echo json_encode([
            'success' => true,
            'has_license' => true,
            'is_valid' => $is_valid,
            'license_key' => $license['license_key'],
            'license_type' => getLicenseTypeName($license['license_type'])
        ]);
        
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Database error']);
    }
}
