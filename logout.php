<?php
/**
 * صفحة تسجيل الخروج - Shashety IPTV
 */

require_once __DIR__ . '/core/config.php';

// تسجيل النشاط
if (isset($_SESSION['admin_username'])) {
    logActivity('تسجيل خروج', "المستخدم: {$_SESSION['admin_username']}");
}

// تدمير الجلسة
$_SESSION = [];
session_unset();

/* session_destroy() تحذف الجلسة من الخادم لكنها **لا تحذف الكوكي**
   من المتصفح، فيظل يرسل معرّف جلسة ميتاً مع كل طلب. نحذفه صراحةً. */
if (ini_get('session.use_cookies') && !headers_sent()) {
    $p = session_get_cookie_params();
    setcookie(
        session_name(), '', time() - 42000,
        $p['path'] ?? '/', $p['domain'] ?? '',
        (bool)($p['secure'] ?? false), (bool)($p['httponly'] ?? true)
    );
}

session_destroy();

// إعادة التوجيه إلى صفحة تسجيل الدخول
redirect('login.php');
