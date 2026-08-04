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

/* ── ما الامتداد المطلوب؟ يحدّد أنُوجّه أم أُمرّر ──
   الشكل /stream.php/v.<ext>?t=… يضع الامتداد في PATH_INFO. */
$__reqExt = '';
$__pi = (string) ($_SERVER['PATH_INFO'] ?? '');
if ($__pi !== '') {
    $__reqExt = strtolower(pathinfo($__pi, PATHINFO_EXTENSION));
}
if ($__reqExt === '') {
    $__reqExt = strtolower(pathinfo((string) parse_url($real, PHP_URL_PATH), PATHINFO_EXTENSION));
}

/* ══ بثّ TS: نُمرّره لا نوجّهه ══
   🔴 سبب هذا الفرع: قناة .ts تعمل في لوحة الإدارة (رابط خام) وتفشل
   في الموقع. الفرق أن الموقع يمرّ بهذه البوابة التي كانت تعيد
   توجيهاً 302 إلى أصل Xtream — ومشغّل mpegts.js لا يُكمل قراءة بثّ
   TS متواصل عبر تحويل عابر للأصل. أمّا HLS فيتبع التوجيه بلا مشكلة
   لأن قوائمه ومقاطعه طلبات منفصلة قصيرة.

   الحلّ: نقرأ البثّ من المصدر ونكتبه للمتصفح مباشرةً. المتصفح يرى
   بايتات من أصل الموقع نفسه — فلا قيد CORS، ولا يُكشف رابط المصدر
   (وفيه اسم المستخدم وكلمة المرور). أي أننا نحصل على تشغيل مباشر
   بلا ffmpeg ولا وسيط، وهو ما يجعل القناة تعمل حتى وإعادة البثّ
   مطفأة — تماماً كما يريده المستخدم.

   ⚠ الثمن: كل مشاهد يحجز اتصال PHP طوال المشاهدة ويسحب من نطاقك.
   مقبول لعدد محدود من المشاهدين؛ للأعداد الكبيرة فعّل وسيط إعادة
   البثّ الذي يخدم مقاطعه من Apache بلا حجز عامل لكل مشاهد. */
$__proxyExts = ['ts', 'mts', 'm2ts', 'mpegts'];
if (in_array($__reqExt, $__proxyExts, true) && function_exists('curl_init')) {

    // نمنع أي مؤقّت يقطع البثّ الطويل
    @set_time_limit(0);
    ignore_user_abort(false);
    while (ob_get_level()) { ob_end_clean(); }

    header('Content-Type: video/mp2t');
    header('Cache-Control: no-store, no-cache, must-revalidate, private');
    header('X-Accel-Buffering: no');    // يمنع nginx/الوسطاء من تجميع البثّ

    $ch = curl_init($real);
    curl_setopt_array($ch, [
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_CONNECTTIMEOUT => 12,
        CURLOPT_TIMEOUT        => 0,                 // بلا سقف: بثّ حيّ
        CURLOPT_BUFFERSIZE     => 65536,
        // بعض مزوّدي Xtream يرفضون الطلب بلا وكيل معروف
        CURLOPT_USERAGENT      => 'VLC/3.0.20 LibVLC/3.0.20',
        CURLOPT_HTTPHEADER     => ['Accept: */*'],
        // نكتب كل دفعة فور وصولها ونتوقّف إن أغلق المشاهد الاتصال
        CURLOPT_WRITEFUNCTION  => function ($c, $chunk) {
            if (connection_aborted()) { return 0; }   // يُنهي curl فيتحرّر العامل
            echo $chunk;
            flush();
            return strlen($chunk);
        },
    ]);
    curl_exec($ch);
    curl_close($ch);
    exit;
}

/* ── إعادة التوجيه إلى المصدر الحقيقي (HLS/DASH/غيرها) ──
   302 مؤقّت: يجعل المشغّل يحلّ مقاطع HLS النسبية مقابل الرابط
   النهائي بشكل صحيح، ويحافظ على توافق hls.js و mpegts.js
   والتشغيل الأصلي في Safari و iOS. */
header('Location: ' . str_replace(["\r", "\n"], '', $real), true, 302);
exit;
