<?php
// index.php lines 369-386
/* ── وضع الصيانة: يُغلق الموقع أمام الزوار (المدير يتجاوزه) ── */
if ($gs_maintenance_mode && !$__is_admin_visitor) {
    $__mn  = htmlspecialchars($settings['site_name'] ?? 'Shashety');
    $__msg = htmlspecialchars($gs_maintenance_message);
    http_response_code(503);
    header('Retry-After: 3600');
    echo '<!DOCTYPE html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.$__mn.' — صيانة</title></head>'
       . '<body style="margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#0f0f0f,#1a0508);font-family:Tahoma,Arial,sans-serif;color:#fff;text-align:center">'
       . '<div style="max-width:480px;padding:40px 24px">'
       . '<div style="font-size:4rem;margin-bottom:16px">🛠️</div>'
       . '<h1 style="margin:0 0 12px;font-size:1.8rem">الموقع تحت الصيانة</h1>'
       . '<p style="color:#bbb;font-size:1.05rem;line-height:1.8;margin:0">'.$__msg.'</p>'
       . '<div style="margin-top:28px;color:#666;font-size:.85rem">'.$__mn.'</div>'
       . '</div></body></html>';
    exit;
}

// ══ الأقسام المعطّلة (مخفية) من الواجهة الأمامية — تُتحكم من admin.php > الأقسام ══
