<?php
// Start buffering once and only enable gzip when the server can provide it.
// Session settings are applied by core/config.php before the session starts.
if (!ob_get_level()) {
    $acceptEncoding = strtolower((string) ($_SERVER['HTTP_ACCEPT_ENCODING'] ?? ''));
    if (extension_loaded('zlib') && !ini_get('zlib.output_compression') && strpos($acceptEncoding, 'gzip') !== false) {
        ob_start('ob_gzhandler');
    } else {
        ob_start();
    }
}

require_once __DIR__ . '/../core/config.php';        // was: require_once 'config.php';
require_once __DIR__ . '/../core/client_config.php'; // was: require_once 'client_config.php';
