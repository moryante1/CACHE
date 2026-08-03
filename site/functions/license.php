<?php
// index.php lines 89-95
$license_key     = getLicenseKey();
$license_expired = false;
if ($license_key) {
    $license_result = verifyLicenseFromServer($license_key);
    if (!$license_result['success'] || !$license_result['valid']) $license_expired = true;
} else { $license_expired = true; }

