<?php
/**
 * ══════════════════════════════════════════════════════════════
 *  بوابة البث — تفكّ الرمز الموقَّع وتوجّه إلى المصدر الحقيقي
 * ══════════════════════════════════════════════════════════════
 *
 *  الاستخدام:  /stream.php/v.m3u8?t=<TOKEN>
 *
 *  الغرض: ألا يخرج رابط Xtream الحقيقي (وفيه اسم المستخدم وكلمة
 *  المرور) من الخادم ضمن ردود الـAPI العامة.
 *  راجع التفاصيل الكاملة في functions/stream_token.php
 */

require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/functions/stream_token.php';

// لا نريد أي تخزين مؤقت لهذه الاستجابة
while (ob_get_level()) {
    ob_end_clean();
}

header('Cache-Control: no-store, no-cache, must-revalidate, private');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: no-referrer');

/** إنهاء بخطأ نصي مختصر. */
function streamFail(int $code, string $msg): void
{
    http_response_code($code);
    header('Content-Type: text/plain; charset=utf-8');
    echo $msg;
    exit;
}

// ── حدّ معدل: يمنع استخدام البوابة لفحص الروابط آلياً ──
if (function_exists('rateLimit') && !rateLimit('stream:gate', 240, 60)) {
    header('Retry-After: 30');
    streamFail(429, 'تم تجاوز الحد المسموح من الطلبات.');
}

// ── قراءة الرمز: من ?t= أو من نهاية المسار ──
$token = (string) ($_GET['t'] ?? '');

if ($token === '') {
    // دعم الشكل /stream.php/<token>/v.m3u8 إن استُخدم لاحقاً
    $pi = trim((string) ($_SERVER['PATH_INFO'] ?? ''), '/');
    if ($pi !== '' && strpos($pi, '.') !== false) {
        $parts = explode('/', $pi);
        if (count($parts) > 1) {
            $token = $parts[0];
        }
    }
}

if ($token === '') {
    streamFail(400, 'رابط غير مكتمل.');
}

// ── التحقق من الرمز ──
$real = streamTokenOpen($token, $why);

if ($real === null) {
    // لا نكشف تفاصيل تقنية تساعد على التخمين
    if ($why === 'انتهت صلاحية الرابط') {
        streamFail(410, 'انتهت صلاحية هذا الرابط. أعد تحميل الصفحة.');
    }
    logTo('security', 'رمز بث غير صالح', ['why' => $why]);
    streamFail(403, 'رابط غير صالح.');
}

/* ── إعادة التوجيه إلى المصدر الحقيقي ──
   302 مؤقّت: يجعل المشغّل يحلّ مقاطع HLS النسبية مقابل الرابط
   النهائي بشكل صحيح، ويحافظ على توافق hls.js و mpegts.js
   والتشغيل الأصلي في Safari و iOS. */
header('Location: ' . str_replace(["\r", "\n"], '', $real), true, 302);
exit;
