<?php
// orig 7-73
/* ════════════ تحسينات شاشتي المدمجة (أمان + كاش) — إضافة آمنة ════════════ */
if (!defined('SHASHETY_IMPROVE_LOADED')) {
    define('SHASHETY_IMPROVE_LOADED', true);

    // رؤوس أمان (لا تُرسل إن سبق إرسال المحتوى)
    if (!headers_sent()) {
        header('X-Frame-Options: SAMEORIGIN');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
        header_remove('X-Powered-By');
    }

    // توكن CSRF (اختياري الاستخدام)
    if (!function_exists('shashety_csrf_token')) {
        function shashety_csrf_token() {
            if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
            if (empty($_SESSION['shashety_csrf'])) {
                $_SESSION['shashety_csrf'] = bin2hex(random_bytes(32));
            }
            return $_SESSION['shashety_csrf'];
        }
        function shashety_csrf_check($stop_on_fail = true) {
            $sent = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
            $real = $_SESSION['shashety_csrf'] ?? '';
            if ($real !== '' && hash_equals($real, (string)$sent)) { return true; }
            if ($stop_on_fail) {
                if (!headers_sent()) { header('Content-Type: application/json; charset=utf-8'); }
                echo json_encode(['success'=>false,'error'=>'انتهت صلاحية الجلسة، أعد تحميل الصفحة']);
                exit;
            }
            return false;
        }
    }

    // كاش بسيط على القرص للاستعلامات الثقيلة (اختياري)
    if (!function_exists('shashety_cache_get')) {
        function shashety_cache_dir() {
            $d = dirname(__DIR__) . '/cache';
            if (!is_dir($d)) @mkdir($d, 0755, true);
            return $d;
        }
        function shashety_cache_get($key, $ttl_seconds, callable $producer) {
            $key  = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $key);
            $file = shashety_cache_dir() . '/' . $key . '.json';
            if (is_file($file) && (time() - filemtime($file) < $ttl_seconds)) {
                $raw = @file_get_contents($file);
                if ($raw !== false) {
                    $data = json_decode($raw, true);
                    if ($data !== null) return $data;
                }
            }
            $data = $producer();
            @file_put_contents($file, json_encode($data), LOCK_EX);
            return $data;
        }
        function shashety_cache_clear($key = null) {
            $dir = shashety_cache_dir();
            if ($key) {
                $key = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $key);
                @unlink($dir . '/' . $key . '.json');
                return;
            }
            foreach (glob($dir . '/*.json') as $f) @unlink($f);
        }
    }
}
