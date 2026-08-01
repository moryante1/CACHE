<?php
/**
 * صفحة تفعيل الرخصة
 * License Activation Page
 */

/* ══════════════════════════════════════════════════════════════
   إصلاحات هذه النسخة:
   ① كانت session_start() تُستدعى بلا أي تقوية، وهذه الصفحة قد تكون
      أول صفحة يزورها المستخدم — فتُنشأ كوكي الجلسة بلا HttpOnly ولا
      Secure ولا SameSite، وتبقى كذلك في بقية النظام. الآن تُطبَّق
      نفس إعدادات core/config.php قبل بدء الجلسة.
   ② لم يكن هناك أي تحقق CSRF على نموذج التفعيل.
   ③ كان الملف يفتح اتصالين HTTP بسيرفر الرخص عند كل تحميل صفحة
      (واحد للتحقق وواحد للحالة). الآن اتصال واحد فقط ونعيد استخدام
      نتيجته، مع منع تكرار التحقق عند إعادة تحميل الصفحة.
   ④ لم يكن كاش الرخصة يُبطَل بعد تفعيل مفتاح جديد، فيبقى النظام
      يقرأ حالة المفتاح القديم حتى انتهاء مدة الكاش.
   ⑤ $_POST['license_key'] كان يُقرأ بلا فحص وجود (تحذير في PHP 8).
   ══════════════════════════════════════════════════════════════ */

$__actHttps = (
    (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
    || (($_SERVER['SERVER_PORT'] ?? '') == 443)
    || (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https')
);

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_secure', $__actHttps ? '1' : '0');
    ini_set('session.cookie_samesite', 'Lax');
    session_start();
}

require_once __DIR__ . '/core/client_config.php';

// رؤوس أمان أساسية (الملف لا يُحمّل core/config.php فنكتبها يدوياً)
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-Robots-Tag: noindex, nofollow');
    header_remove('X-Powered-By');
}

// رمز CSRF لنموذج التفعيل
if (empty($_SESSION['activate_csrf'])) {
    $_SESSION['activate_csrf'] = bin2hex(random_bytes(32));
}
$activate_csrf = $_SESSION['activate_csrf'];

$machine_id   = getMachineId();
$message      = '';
$message_type = '';
$status       = null;   // نتيجة التحقق — تُعاد استخدامها لتفادي طلب شبكي ثانٍ

// معالجة التفعيل
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['activate'])) {

    $postedCsrf = (string) ($_POST['csrf_token'] ?? '');

    if (!hash_equals($activate_csrf, $postedCsrf)) {
        $message      = 'انتهت صلاحية الجلسة. أعد تحميل الصفحة وحاول مرة أخرى.';
        $message_type = 'error';

    } else {
        $license_key = trim(strtoupper((string) ($_POST['license_key'] ?? '')));

        if ($license_key === '') {
            $message      = 'يرجى إدخال مفتاح الرخصة';
            $message_type = 'error';
        } else {
            $result = verifyLicenseFromServer($license_key);
            $status = $result; // نعيد استخدامها بدل استدعاء ثانٍ

            if (!empty($result['success']) && !empty($result['valid'])) {
                saveLicenseKey($license_key);

                // إبطال كاش الرخصة حتى تُقرأ حالة المفتاح الجديد فوراً
                $__licCache = __DIR__ . '/storage/cache/.license_state';
                if (is_file($__licCache)) {
                    @unlink($__licCache);
                }

                // تدوير الجلسة والرمز بعد تغيير حالة أمنية مهمة
                session_regenerate_id(true);
                $_SESSION['activate_csrf'] = bin2hex(random_bytes(32));

                $message      = 'تم التفعيل بنجاح! جاري التحويل...';
                $message_type = 'success';
                header('Refresh: 2; url=admin.php');

            } elseif (!empty($result['expired'])) {
                $message      = 'الرخصة منتهية الصلاحية. يرجى التواصل مع المطور للتجديد';
                $message_type = 'error';
            } else {
                $message      = 'مفتاح الرخصة غير صحيح. تأكد من إدخال المفتاح بشكل صحيح';
                $message_type = 'error';
            }
        }
    }
}

$current_key = getLicenseKey();

// لا نستدعي السيرفر مرة ثانية إن كنا قد استدعيناه للتو في نفس الطلب
if ($status === null && $current_key) {
    $status = verifyLicenseFromServer($current_key);
}

/**
 * حساب الوقت المتبقي الحقيقي من تاريخ الانتهاء.
 * يرجع مصفوفة فيها الأيام والساعات والنص الجاهز، أو null إن تعذّر.
 */
function computeRemaining($expiryDate) {
    if (empty($expiryDate)) return null;
    $expiryTs = strtotime($expiryDate);
    if ($expiryTs === false) return null;

    $now  = time();
    $diff = $expiryTs - $now;          // بالثواني
    $expired = $diff <= 0;
    $absSec  = abs($diff);

    $days  = (int) floor($absSec / 86400);
    $hours = (int) floor(($absSec % 86400) / 3600);

    return [
        'expired'    => $expired,
        'days'       => $days,
        'hours'      => $hours,
        'expiry_fmt' => date('Y-m-d H:i', $expiryTs),
    ];
}

// حساب المتبقي الحقيقي إن توفّر تاريخ الانتهاء
/* ⚠️ كان يُقرأ $status['valid'] مباشرة، وسيرفر الرخص قد يُرجع رداً
   لا يحتوي هذا المفتاح (خطأ اتصال، أو رد بصيغة مختلفة)، فيمتلئ سجل
   أخطاء Apache بتحذيرات "Undefined array key valid" مع كل زيارة.
   نستخدم isset لتفادي ذلك دون تغيير أي منطق. */
$remaining = null;
if ($status && !empty($status['valid']) && empty($status['lifetime'])
    && !empty($status['license']['expiry_date'])) {
    $remaining = computeRemaining($status['license']['expiry_date']);
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تفعيل النظام — Shashety IPTV</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700;900&family=Bebas+Neue&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --red:       #e50914;
            --red-dark:  #b0060f;
            --red-glow:  rgba(229, 9, 20, 0.35);
            --bg:        #0a0a0a;
            --surface:   #141414;
            --surface2:  #1e1e1e;
            --surface3:  #2a2a2a;
            --border:    rgba(255,255,255,0.07);
            --text:      #e5e5e5;
            --muted:     #a3a3a3;
            --faint:     #525252;
            --gold:      #f5c518;
            --green:     #46d369;
            --font-ar:   'Cairo', sans-serif;
            --font-disp: 'Bebas Neue', sans-serif;
        }

        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

        html { scroll-behavior: smooth; }

        body {
            font-family: var(--font-ar);
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow-x: hidden;
            position: relative;
        }

        /* ── Cinematic Background ── */
        .bg-layer {
            position: fixed;
            inset: 0;
            z-index: 0;
            pointer-events: none;
        }
        .bg-layer::before {
            content: '';
            position: absolute;
            inset: 0;
            background:
                radial-gradient(ellipse 80% 60% at 20% 50%, rgba(229,9,20,0.08) 0%, transparent 60%),
                radial-gradient(ellipse 60% 80% at 80% 20%, rgba(229,9,20,0.05) 0%, transparent 50%),
                radial-gradient(ellipse 100% 100% at 50% 100%, rgba(0,0,0,0.8) 0%, transparent 60%);
        }
        /* Scan lines removed for a cleaner, more professional look */

        /* ── Layout ── */
        .wrapper {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 520px;
            padding: 24px;
            animation: fadeUp 0.7s cubic-bezier(0.16,1,0.3,1) both;
        }
        @keyframes fadeUp {
            from { opacity:0; transform:translateY(28px); }
            to   { opacity:1; transform:translateY(0); }
        }

        /* ── Card ── */
        .card {
            background: rgba(20,20,20,0.92);
            border: 1px solid var(--border);
            border-radius: 4px;
            padding: 44px 40px;
            backdrop-filter: blur(24px);
            box-shadow:
                0 0 0 1px rgba(255,255,255,0.03),
                0 40px 80px rgba(0,0,0,0.7),
                0 0 120px var(--red-glow);
            position: relative;
            overflow: hidden;
        }
        /* top accent bar */
        .card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            background: linear-gradient(90deg, transparent, var(--red), transparent);
        }

        /* ── Header ── */
        .header {
            text-align: center;
            margin-bottom: 36px;
        }
        .logo-mark {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 110px; height: 110px;
            margin-bottom: 16px;
            position: relative;
            filter: drop-shadow(0 8px 24px var(--red-glow));
        }
        .logo-mark i {
            font-size: 56px;
            color: var(--red);
        }
        .logo-mark img {
            width: 100%; height: 100%;
            object-fit: contain;
        }
        .brand-name {
            font-family: var(--font-disp);
            font-size: 36px;
            letter-spacing: 4px;
            color: #fff;
            line-height: 1;
        }
        .brand-name span { color: var(--red); }
        .brand-sub {
            margin-top: 6px;
            font-size: 11px;
            letter-spacing: 3px;
            text-transform: uppercase;
            color: var(--faint);
            font-weight: 300;
        }

        /* ── Divider ── */
        .divider {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 24px 0;
        }
        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border);
        }
        .divider span {
            font-size: 10px;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: var(--faint);
        }

        /* ── Status Banner ── */
        .status-banner {
            border-radius: 4px;
            padding: 14px 16px;
            margin-bottom: 20px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            border: 1px solid;
            animation: slideIn 0.5s cubic-bezier(0.16,1,0.3,1) both;
        }
        @keyframes slideIn {
            from { opacity:0; transform:translateX(16px); }
            to   { opacity:1; transform:translateX(0); }
        }
        .status-banner.active {
            background: rgba(70, 211, 105, 0.07);
            border-color: rgba(70, 211, 105, 0.25);
        }
        .status-banner.expired {
            background: rgba(229,9,20,0.07);
            border-color: rgba(229,9,20,0.25);
        }
        .status-banner .icon {
            font-size: 18px;
            flex-shrink: 0;
            margin-top: 1px;
        }
        .status-banner.active .icon { color: var(--green); }
        .status-banner.expired .icon { color: var(--red); }
        .status-banner .content h4 {
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 4px;
            letter-spacing: 0.5px;
        }
        .status-banner.active .content h4 { color: var(--green); }
        .status-banner.expired .content h4 { color: var(--red); }
        .status-banner .content p {
            font-size: 12px;
            color: var(--muted);
            line-height: 1.6;
        }
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 2px;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            margin-top: 6px;
        }
        .badge-green { background: rgba(70,211,105,0.15); color: var(--green); }
        .badge-red   { background: rgba(229,9,20,0.15);  color: var(--red); }

        /* ── Alert Message ── */
        .alert {
            border-radius: 4px;
            padding: 13px 16px;
            margin-bottom: 20px;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 10px;
            border: 1px solid;
            animation: slideIn 0.4s cubic-bezier(0.16,1,0.3,1) both;
        }
        .alert.success {
            background: rgba(70,211,105,0.08);
            border-color: rgba(70,211,105,0.3);
            color: var(--green);
        }
        .alert.error {
            background: rgba(229,9,20,0.08);
            border-color: rgba(229,9,20,0.3);
            color: #ff6b6b;
        }

        /* ── Machine ID Block ── */
        .machine-block {
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 4px;
            padding: 18px;
            margin-bottom: 24px;
            position: relative;
        }
        .machine-block-label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 10px;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: var(--faint);
            margin-bottom: 12px;
            font-weight: 600;
        }
        .machine-block-label i { color: var(--red); font-size: 11px; }
        .machine-id-display {
            background: var(--bg);
            border: 1px solid rgba(255,255,255,0.05);
            border-radius: 3px;
            padding: 12px 14px;
            font-family: 'Courier New', monospace;
            font-size: 10.5px;
            color: var(--muted);
            word-break: break-all;
            line-height: 1.8;
            letter-spacing: 0.5px;
            position: relative;
        }
        .copy-btn {
            width: 100%;
            margin-top: 10px;
            padding: 10px;
            background: var(--surface3);
            border: 1px solid var(--border);
            border-radius: 3px;
            color: var(--muted);
            font-family: var(--font-ar);
            font-size: 12px;
            letter-spacing: 0.5px;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .copy-btn:hover {
            background: var(--surface3);
            border-color: rgba(255,255,255,0.15);
            color: #fff;
        }
        .copy-btn.copied {
            border-color: rgba(70,211,105,0.4);
            color: var(--green);
        }
        .whatsapp-btn {
            width: 100%;
            margin-top: 8px;
            padding: 11px;
            background: #1faf54;
            border: none;
            border-radius: 3px;
            color: #fff;
            font-family: var(--font-ar);
            font-size: 12.5px;
            font-weight: 600;
            letter-spacing: 0.5px;
            cursor: pointer;
            transition: background 0.2s, transform 0.12s, box-shadow 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
        }
        .whatsapp-btn:hover {
            background: #25c462;
            transform: translateY(-1px);
            box-shadow: 0 6px 18px rgba(31,175,84,0.35);
        }
        .whatsapp-btn:active { transform: translateY(0); }
        .whatsapp-btn i { font-size: 15px; }
        .machine-note {
            margin-top: 10px;
            font-size: 11px;
            color: var(--faint);
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .machine-note i { font-size: 10px; }

        /* ── Form ── */
        .field {
            margin-bottom: 18px;
        }
        .field label {
            display: flex;
            align-items: center;
            gap: 7px;
            font-size: 10px;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: var(--faint);
            font-weight: 600;
            margin-bottom: 8px;
        }
        .field label i { color: var(--red); font-size: 10px; }
        .field input {
            width: 100%;
            padding: 15px 18px;
            background: var(--bg);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 3px;
            color: #fff;
            font-family: 'Courier New', monospace;
            font-size: 16px;
            letter-spacing: 3px;
            text-align: center;
            text-transform: uppercase;
            transition: all 0.3s;
            outline: none;
            caret-color: var(--red);
        }
        .field input::placeholder {
            color: var(--faint);
            letter-spacing: 1px;
            font-size: 13px;
        }
        .field input:focus {
            border-color: var(--red);
            box-shadow: 0 0 0 3px var(--red-glow), 0 0 20px var(--red-glow);
        }
        .field input:valid:not(:placeholder-shown) {
            border-color: rgba(70,211,105,0.4);
        }

        /* ── Submit Button ── */
        .submit-btn {
            width: 100%;
            padding: 16px;
            background: var(--red);
            border: none;
            border-radius: 3px;
            color: white;
            font-family: var(--font-ar);
            font-size: 15px;
            font-weight: 700;
            letter-spacing: 1px;
            cursor: pointer;
            position: relative;
            overflow: hidden;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .submit-btn::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.1) 0%, transparent 60%);
            opacity: 0;
            transition: opacity 0.3s;
        }
        .submit-btn:hover {
            background: #f40612;
            transform: translateY(-1px);
            box-shadow: 0 8px 30px var(--red-glow);
        }
        .submit-btn:hover::before { opacity: 1; }
        .submit-btn:active { transform: translateY(0); }
        .submit-btn .btn-ripple {
            position: absolute;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            transform: scale(0);
            animation: ripple 0.6s linear;
            pointer-events: none;
        }
        @keyframes ripple {
            to { transform: scale(4); opacity: 0; }
        }

        /* ── Info Steps ── */
        .info-block {
            margin-top: 24px;
            border: 1px solid var(--border);
            border-radius: 4px;
            overflow: hidden;
        }
        .info-header {
            padding: 12px 16px;
            background: var(--surface2);
            border-bottom: 1px solid var(--border);
            font-size: 10px;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: var(--faint);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .info-header i { color: var(--red); }
        .steps {
            list-style: none;
        }
        .steps li {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            padding: 12px 16px;
            border-bottom: 1px solid var(--border);
            transition: background 0.2s;
        }
        .steps li:last-child { border-bottom: none; }
        .steps li:hover { background: rgba(255,255,255,0.02); }
        .step-num {
            flex-shrink: 0;
            width: 22px; height: 22px;
            border-radius: 50%;
            background: rgba(229,9,20,0.12);
            border: 1px solid rgba(229,9,20,0.3);
            color: var(--red);
            font-size: 11px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .steps li span {
            font-size: 12.5px;
            color: var(--muted);
            line-height: 1.6;
            padding-top: 2px;
        }

        /* ── Footer ── */
        .footer-text {
            text-align: center;
            margin-top: 24px;
            font-size: 11px;
            color: var(--faint);
            letter-spacing: 0.5px;
        }

        /* ── Animated dots ── */
        .dot-anim::after {
            content: '';
            animation: dots 1.5s steps(3, end) infinite;
        }
        @keyframes dots {
            0%   { content: '.'; }
            33%  { content: '..'; }
            66%  { content: '...'; }
            100% { content: ''; }
        }

        /* Mobile */
        @media (max-width: 480px) {
            .card { padding: 32px 24px; }
            .brand-name { font-size: 28px; }
        }
    </style>
</head>
<body>

<div class="bg-layer"></div>

<div class="wrapper">
    <div class="card">

        <!-- Header -->
        <div class="header">
            <div class="logo-mark">
                <img src="assets/22.png" alt="Shashety"
                     onerror="this.style.display='none';this.insertAdjacentHTML('afterend','<i class=\'fas fa-tv\'></i>');">
            </div>
            <div class="brand-name">SHASHETY<span>&nbsp;IPTV</span></div>
            <div class="brand-sub">License Activation Portal</div>
        </div>

        <!-- Alert Message -->
        <?php if ($message): ?>
        <div class="alert <?php echo $message_type; ?>">
            <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
            <span <?php if($message_type === 'success'): ?>class="dot-anim"<?php endif; ?>><?php echo htmlspecialchars($message); ?></span>
        </div>
        <?php endif; ?>

        <!-- Current License Status -->
        <?php if ($status && !empty($status['valid'])): ?>
        <div class="status-banner active">
            <div class="icon"><i class="fas fa-shield-check"></i></div>
            <div class="content">
                <h4>الرخصة نشطة</h4>
                <p>
                    النوع: <?php echo htmlspecialchars((string)($status['license']['license_type_name'] ?? 'غير محدد')); ?>
                    <?php if (isset($status['lifetime']) && $status['lifetime']): ?>
                        &mdash; <strong style="color:#fff;">مدى الحياة</strong>
                    <?php elseif ($remaining): ?>
                        <br>
                        المتبقي:
                        <strong style="color:#fff;">
                            <?php echo $remaining['days']; ?> يوم
                            <?php if ($remaining['hours'] > 0): ?>
                                و <?php echo $remaining['hours']; ?> ساعة
                            <?php endif; ?>
                        </strong>
                        <br>
                        تنتهي في: <strong style="color:#fff;"><?php echo htmlspecialchars($remaining['expiry_fmt']); ?></strong>
                    <?php else: ?>
                        &mdash; المتبقي: <strong style="color:#fff;"><?php echo (int)($status['days_left'] ?? 0); ?> يوم</strong>
                        <?php if (!empty($status['license']['expiry_date'])): ?>
                            <br>تنتهي في: <?php echo date('Y-m-d', strtotime($status['license']['expiry_date'])); ?>
                        <?php endif; ?>
                    <?php endif; ?>
                </p>
                <span class="badge badge-green">&#x25CF; ACTIVE</span>
            </div>
        </div>
        <?php elseif ($status && !empty($status['expired'])): ?>
        <div class="status-banner expired">
            <div class="icon"><i class="fas fa-exclamation-circle"></i></div>
            <div class="content">
                <h4>الرخصة منتهية</h4>
                <p>يرجى التواصل مع المطور لتجديد الرخصة</p>
                <span class="badge badge-red">&#x25CF; EXPIRED</span>
            </div>
        </div>
        <?php endif; ?>

        <!-- Machine ID -->
        <div class="machine-block">
            <div class="machine-block-label">
                <i class="fas fa-fingerprint"></i>
                معرف الجهاز — Machine ID
            </div>
            <div class="machine-id-display" id="machineId"><?php echo htmlspecialchars($machine_id); ?></div>
            <button class="copy-btn" id="copyBtn" onclick="copyMachineId()">
                <i class="fas fa-copy"></i>
                نسخ المعرف
            </button>
            <?php
                $waNumber  = '9647512328848'; // 00964... بدون 00
                $waMessage = "مرحباً، أرغب بتفعيل النظام.\nمعرف الجهاز (Machine ID):\n" . $machine_id;
                $waLink    = 'https://wa.me/' . $waNumber . '?text=' . rawurlencode($waMessage);
            ?>
            <a class="whatsapp-btn" href="<?php echo htmlspecialchars($waLink); ?>" target="_blank" rel="noopener">
                <i class="fab fa-whatsapp"></i>
                إرسال المعرف عبر واتساب
            </a>
            <div class="machine-note">
                <i class="fas fa-arrow-up-right-from-square"></i>
                أرسل هذا المعرف للمطور للحصول على مفتاح الرخصة
            </div>
        </div>

        <!-- Form -->
        <form method="POST" id="activateForm">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($activate_csrf, ENT_QUOTES, 'UTF-8') ?>">
            <div class="field">
                <label>
                    <i class="fas fa-key"></i>
                    مفتاح الرخصة — License Key
                </label>
                <input
                    type="text"
                    name="license_key"
                    id="licenseInput"
                    placeholder="IPTV-XXXX-XXXX-XXXX"
                    maxlength="19"
                    autocomplete="off"
                    spellcheck="false"
                    required
                    autofocus>
            </div>

            <button type="submit" name="activate" class="submit-btn" id="submitBtn">
                <i class="fas fa-unlock-keyhole"></i>
                تفعيل النظام
            </button>
        </form>

        <!-- Steps -->
        <div class="info-block">
            <div class="info-header">
                <i class="fas fa-circle-info"></i>
                كيفية الحصول على مفتاح الرخصة
            </div>
            <ul class="steps">
                <li><div class="step-num">1</div><span>انسخ معرف الجهاز (Machine ID) أعلاه</span></li>
                <li><div class="step-num">2</div><span>اضغط زر "إرسال المعرف عبر واتساب" أو تواصل مع المطور</span></li>
                <li><div class="step-num">3</div><span>أرسل له معرف الجهاز</span></li>
                <li><div class="step-num">4</div><span>استلم مفتاح الرخصة وأدخله أعلاه</span></li>
                <li><div class="step-num">5</div><span>اضغط تفعيل وابدأ الاستخدام فوراً</span></li>
            </ul>
        </div>

        <div class="footer-text">
            &copy; <?php echo date('Y'); ?> Shashety IPTV &mdash; All rights reserved
        </div>
    </div>
</div>

<script>
    /* ── Copy Machine ID (works on HTTP and HTTPS) ── */
    function showCopied(btn) {
        btn.classList.add('copied');
        btn.innerHTML = '<i class="fas fa-check"></i> تم النسخ بنجاح!';
        setTimeout(() => {
            btn.classList.remove('copied');
            btn.innerHTML = '<i class="fas fa-copy"></i> نسخ المعرف';
        }, 2500);
    }
    function showCopyFailed(btn) {
        btn.innerHTML = '<i class="fas fa-triangle-exclamation"></i> انسخ يدوياً';
        setTimeout(() => {
            btn.innerHTML = '<i class="fas fa-copy"></i> نسخ المعرف';
        }, 2500);
    }
    function legacyCopy(text, btn) {
        const ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.top = '-9999px';
        document.body.appendChild(ta);
        ta.focus();
        ta.select();
        let ok = false;
        try { ok = document.execCommand('copy'); } catch (e) { ok = false; }
        document.body.removeChild(ta);
        ok ? showCopied(btn) : showCopyFailed(btn);
    }
    function copyMachineId() {
        const text = document.getElementById('machineId').textContent.trim();
        const btn  = document.getElementById('copyBtn');

        // navigator.clipboard only works on HTTPS/localhost — fall back otherwise
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text)
                .then(() => showCopied(btn))
                .catch(() => legacyCopy(text, btn));
        } else {
            legacyCopy(text, btn);
        }
    }

    /* ── License Key Auto-format ── */
    const input = document.getElementById('licenseInput');
    input.addEventListener('input', function () {
        let v = this.value.replace(/[^A-Z0-9]/gi, '').toUpperCase();
        let formatted = '';
        for (let i = 0; i < v.length && i < 16; i++) {
            if (i > 0 && i % 4 === 0) formatted += '-';
            formatted += v[i];
        }
        this.value = formatted;
    });

    /* ── Button ripple ── */
    document.getElementById('submitBtn').addEventListener('click', function (e) {
        const rect = this.getBoundingClientRect();
        const ripple = document.createElement('span');
        ripple.className = 'btn-ripple';
        const size = Math.max(rect.width, rect.height);
        ripple.style.width  = ripple.style.height = size + 'px';
        ripple.style.left   = (e.clientX - rect.left - size / 2) + 'px';
        ripple.style.top    = (e.clientY - rect.top  - size / 2) + 'px';
        this.appendChild(ripple);
        setTimeout(() => ripple.remove(), 700);
    });

    /* ── Staggered step animation ── */
    const steps = document.querySelectorAll('.steps li');
    steps.forEach((li, i) => {
        li.style.opacity = '0';
        li.style.transform = 'translateX(10px)';
        li.style.transition = `opacity 0.4s ease ${0.6 + i * 0.08}s, transform 0.4s ease ${0.6 + i * 0.08}s`;
        requestAnimationFrame(() => {
            li.style.opacity = '1';
            li.style.transform = 'translateX(0)';
        });
    });
</script>
</body>
</html>
