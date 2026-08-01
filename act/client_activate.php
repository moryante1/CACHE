<?php
/**
 * صفحة التفعيل للعميل
 * Client Activation Page
 * 
 * ضع هذا الملف في نظام IPTV عند العميل
 */

session_start();
require_once 'client_config.php';

$machine_id = getMachineId();
$activation_message = '';
$activation_success = false;

// معالجة التفعيل
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['activate'])) {
    $license_key = trim(strtoupper($_POST['license_key']));
    
    if (empty($license_key)) {
        $activation_message = 'يرجى إدخال مفتاح الرخصة';
    } else {
        $result = verifyLicenseFromServer($license_key);
        
        if ($result['success'] && $result['valid']) {
            // حفظ المفتاح في ملف
            file_put_contents('license_key.txt', $license_key);
            
            $activation_success = true;
            $activation_message = '✅ تم التفعيل بنجاح! جاري التحويل...';
            
            header("Refresh: 2; url=admin.php");
        } elseif (isset($result['expired']) && $result['expired']) {
            $activation_message = '❌ الرخصة منتهية الصلاحية';
        } else {
            $activation_message = '❌ مفتاح الرخصة غير صحيح';
        }
    }
}

// فحص الحالة
$status = checkLicenseStatus();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تفعيل النظام - Shashety IPTV</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            padding: 40px;
            max-width: 500px;
            width: 100%;
        }
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo i {
            font-size: 60px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .logo h1 {
            font-size: 24px;
            color: #333;
            margin-top: 10px;
        }
        .machine-id-box {
            background: #f5f5f5;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            border-left: 4px solid #667eea;
        }
        .machine-id-box h3 {
            color: #333;
            margin-bottom: 10px;
            font-size: 14px;
        }
        .machine-id-box code {
            display: block;
            background: white;
            padding: 15px;
            border-radius: 8px;
            word-break: break-all;
            font-size: 12px;
            color: #666;
            border: 2px dashed #ddd;
        }
        .machine-id-box p {
            margin-top: 10px;
            font-size: 13px;
            color: #666;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }
        .form-group input {
            width: 100%;
            padding: 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 16px;
            text-align: center;
            font-family: monospace;
            letter-spacing: 2px;
            text-transform: uppercase;
        }
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }
        .btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: transform 0.2s;
        }
        .btn:hover {
            transform: translateY(-2px);
        }
        .message {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
        }
        .success {
            background: #4caf50;
            color: white;
        }
        .error {
            background: #f44336;
            color: white;
        }
        .info {
            background: #e3f2fd;
            color: #1976d2;
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
            border-left: 4px solid #2196f3;
        }
        .info h4 {
            margin-bottom: 10px;
        }
        .info ol {
            margin: 10px 0 10px 20px;
            text-align: right;
        }
        .info li {
            margin: 5px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            <i class="fas fa-tv"></i>
            <h1>Shashety IPTV</h1>
            <p style="color: #666; margin-top: 5px;">تفعيل النظام</p>
        </div>

        <div class="machine-id-box">
            <h3><i class="fas fa-fingerprint"></i> معرف الجهاز (Machine ID):</h3>
            <code><?php echo htmlspecialchars($machine_id); ?></code>
            <p>
                <i class="fas fa-info-circle"></i>
                أرسل هذا المعرف للمطور للحصول على مفتاح الرخصة
            </p>
        </div>

        <?php if ($activation_message): ?>
            <div class="message <?php echo $activation_success ? 'success' : 'error'; ?>">
                <?php echo $activation_message; ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label><i class="fas fa-key"></i> مفتاح الرخصة</label>
                <input 
                    type="text" 
                    name="license_key" 
                    placeholder="IPTV-XXXX-XXXX-XXXX" 
                    maxlength="19"
                    required 
                    autofocus
                    style="text-transform: uppercase;">
            </div>

            <button type="submit" name="activate" class="btn">
                <i class="fas fa-check"></i> تفعيل النظام
            </button>
        </form>

        <div class="info">
            <h4><i class="fas fa-question-circle"></i> كيفية الحصول على مفتاح الرخصة:</h4>
            <ol>
                <li>انسخ معرف الجهاز (Machine ID) أعلاه</li>
                <li>تواصل مع المطور</li>
                <li>أرسل له معرف الجهاز</li>
                <li>سيرسل لك مفتاح الرخصة</li>
                <li>أدخل المفتاح وفعّل النظام</li>
            </ol>
        </div>
    </div>
</body>
</html>
