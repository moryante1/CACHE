<?php
// index.php lines 330-338
/* ── إجبار HTTPS: إعادة توجيه أي http إلى https ── */
if ($gs_force_https && empty($_SERVER['HTTPS']) && ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') !== 'https' && PHP_SAPI !== 'cli') {
    if (!headers_sent()) {
        $__redir = 'https://' . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '');
        header('Location: ' . $__redir, true, 301);
        exit;
    }
}

