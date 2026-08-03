<?php
// Keep the public site safe on hosts with or without the zlib extension.
if (!ob_get_level()) {
    $acceptEncoding = strtolower((string) ($_SERVER['HTTP_ACCEPT_ENCODING'] ?? ''));
    if (extension_loaded('zlib') && !ini_get('zlib.output_compression') && strpos($acceptEncoding, 'gzip') !== false) {
        ob_start('ob_gzhandler');
    } else {
        ob_start();
    }
}

require_once __DIR__ . '/../../core/config.php';
require_once __DIR__ . '/../../core/client_config.php';
