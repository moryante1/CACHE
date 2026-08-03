<?php
// index.php lines 339-368
/* ── بوابة قفل الموقع بكلمة مرور (تُطبّق قبل أي إخراج، تتجاوزها إدارة الموقع) ── */
if ($gs_gate_enabled && $gs_gate_password !== '' && !$__is_admin_visitor) {
    if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
    // معالجة إرسال كلمة السر
    if (isset($_POST['__gate_pw'])) {
        /* ⚠️ لم يكن هناك أي حدّ لعدد المحاولات: بوابة القفل كانت
           تقبل تخميناً غير محدود من نفس العنوان، فيستطيع سكربت بسيط
           تجربة آلاف الكلمات في دقائق ويتجاوز حماية الموقع بالكامل.
           20 محاولة كل 10 دقائق تكفي المستخدم الحقيقي وتُفشل التخمين. */
        if (function_exists('rateLimit') && !rateLimit('gate:login', 20, 600)) {
            $__gate_error = 'محاولات كثيرة. انتظر بضع دقائق ثم حاول مجدداً.';
        } elseif (hash_equals($gs_gate_password, (string)$_POST['__gate_pw'])) {
            // تدوير معرّف الجلسة بعد النجاح (منع تثبيت الجلسة)
            @session_regenerate_id(true);
            $_SESSION['__gate_ok'] = true;
        } else {
            if (function_exists('logTo')) {
                logTo('security', 'محاولة دخول فاشلة لبوابة الموقع');
            }
            $__gate_error = 'كلمة المرور غير صحيحة';
        }
    }
    if (empty($_SESSION['__gate_ok'])) {
        // عرض صفحة البوابة والتوقف
        $__gn = htmlspecialchars($settings['site_name'] ?? 'Shashety');
        $__gerr = isset($__gate_error) ? '<p style="color:#ff6b6b;margin:0 0 12px">'.htmlspecialchars($__gate_error).'</p>' : '';
        http_response_code(401);
        echo '<!DOCTYPE html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.$__gn.' — دخول محمي</title></head>'
           . '<body style="margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#0f0f0f;font-family:Tahoma,Arial,sans-serif;color:#fff">'
           . '<form method="POST" style="background:#181818;padding:40px 32px;border-radius:16px;max-width:360px;width:90%;text-align:center;border:1px solid #2a2a2a">'
           . '<div style="font-size:2.4rem;margin-bottom:14px">🔒</div>'
           . '<h2 style="margin:0 0 6px;font-size:1.3rem">'.$__gn.'</h2>'
           . '<p style="color:#999;font-size:.9rem;margin:0 0 20px">هذا الموقع محمي. أدخل كلمة المرور للمتابعة.</p>'
           . $__gerr
           . '<input type="password" name="__gate_pw" placeholder="كلمة المرور" autofocus style="width:100%;padding:12px;border-radius:10px;border:1px solid #333;background:#0f0f0f;color:#fff;box-sizing:border-box;margin-bottom:14px;text-align:center">'
           . '<button type="submit" style="width:100%;padding:12px;border:none;border-radius:10px;background:#e50914;color:#fff;font-weight:700;font-size:1rem;cursor:pointer">دخول</button>'
           . '</form></body></html>';
        exit;
    }
}

