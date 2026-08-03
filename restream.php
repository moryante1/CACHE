<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 *  نقطة دخول إعادة البثّ
 * ───────────────────────────────────────────────────────────────────────────
 *  restream.php?ch=<id>            → يشغّل القناة ويُعيد رابط m3u8 (JSON)
 *  restream.php?ch=<id>&play=1     → يُعيد التوجيه مباشرةً إلى القائمة
 *  restream.php?ch=<id>&ping=1     → تجديد النشاط (يمنع إنهاء القناة)
 *
 *  الأمان:
 *    • رقم القناة فقط يُقبل — الرابط يُقرأ من قاعدة البيانات لا من الطلب.
 *      قبول رابط من المستخدم يحوّل الخادم إلى وسيط مفتوح لأي وجهة (SSRF).
 *    • يتطلّب مشتركاً باشتراك فعّال حين تكون حماية الصفحة الرئيسية مفعّلة.
 * ═══════════════════════════════════════════════════════════════════════════
 */
declare(strict_types=1);

require_once __DIR__ . '/config/bootstrap.php';
require_once __DIR__ . '/functions/restream.php';
require_once __DIR__ . '/functions/subscriptions.php';

header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');

function rxJson(array $p, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($p, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (!rsEnabled()) rxJson(['success' => false, 'error' => 'restream_disabled'], 503);

/* ── التحقّق من الصلاحية ──
   ⚠ نُغلق الجلسة فور قراءتها ولا نتركها مفتوحة.

   PHP يقفل ملف الجلسة من session_start حتى نهاية الطلب، ويمنع أي طلب
   آخر من المستخدم نفسه طوال ذلك. وهذا الملف قد ينتظر 3.5 ثانية حتى
   تجهز القناة — فيتجمّد تصفّح المشاهد بالكامل في تلك المدة: لا تحميل
   صفحة، ولا نداء api، ولا فتح قناة أخرى.

   ومع 500 مشترك ينبض كلٌّ منهم كل 25 ثانية (≈1200 طلب في الدقيقة)
   يصير قفل الجلسة عنق الزجاجة الحقيقي لا المعالج ولا ffmpeg.

   نقرأ ما نحتاجه ثم نغلق: لا شيء هنا يكتب في الجلسة. */
if (session_status() !== PHP_SESSION_ACTIVE) @session_start();

$isAdmin  = function_exists('isAdminLoggedIn') && isAdminLoggedIn();
$__siteUid = (int)($_SESSION['site_user_id'] ?? 0);

// تحرير القفل فوراً — كل ما بعده لا يمسّ الجلسة
if (session_status() === PHP_SESSION_ACTIVE) @session_write_close();

$allowed = $isAdmin;
if (!$allowed) {
    if (subsEnsureSchema() && subsProtectionOn()) {
        // الحماية مفعّلة: لا بثّ إلا لمشترك فعّال
        $u = $__siteUid > 0 ? subsUserById($__siteUid) : null;
        $allowed = $u && subsUserStatus($u)['active'];
    } else {
        // الحماية موقوفة: الموقع مفتوح أصلاً، فالوسيط كذلك
        $allowed = true;
    }
}
if (!$allowed) rxJson(['success' => false, 'error' => 'login_required'], 401);

// حدّ معدّل: يمنع سكربتاً من إشعال كل القنوات دفعةً واحدة
if (function_exists('rateLimit') && !rateLimit('restream:' . ($_SERVER['REMOTE_ADDR'] ?? '0'), 60, 60)) {
    rxJson(['success' => false, 'error' => 'rate_limited'], 429);
}

/* النوع: ch لقناة مباشرة، ep لفيلم أو حلقة.
   محتوى Xtream موزّع على جدولين، وقصر الوسيط على channels كان يترك
   كل الأفلام والمسلسلات بلا صوت لأن الطلب لا يجد لها صفاً أصلاً. */
$chId = (int)($_GET['ch'] ?? 0);
$epId = (int)($_GET['ep'] ?? 0);
if ($chId < 1 && $epId < 1) rxJson(['success' => false, 'error' => 'bad_channel'], 400);

$kind = $epId > 0 ? 'e' : 'c';
$id   = $epId > 0 ? $epId : $chId;
$key  = rsKey($kind, $id);

// ── نبضة النشاط: أرخص مسار، لا يلمس قاعدة البيانات ──
if (!empty($_GET['ping'])) {
    if (rsRunning($key)) { rsTouch($key); rxJson(['success' => true, 'alive' => true]); }
    rxJson(['success' => false, 'alive' => false], 410);
}

// ── الرابط من قاعدة البيانات حصراً ──
try {
    if ($kind === 'e') {
        $st = db()->prepare('SELECT title AS name, stream_url FROM episodes WHERE id = ? LIMIT 1');
    } else {
        $st = db()->prepare('SELECT name, stream_url FROM channels WHERE id = ? LIMIT 1');
    }
    $st->execute([$id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    if (function_exists('logTo')) logTo('error', 'restream db: ' . $e->getMessage());
    rxJson(['success' => false, 'error' => 'server'], 500);
}
if (!$row) rxJson(['success' => false, 'error' => 'channel_not_found'], 404);

$src = trim((string)$row['stream_url']);
if (!preg_match('~^https?://~i', $src)) {
    rxJson(['success' => false, 'error' => 'unsupported_source'], 400);
}

// ── التشغيل ──
$r = rsStart($key, $src);

// تنظيف انتهازي: كل تشغيل جديد فرصة لإنهاء ما هُجر.
// لا نعتمد على cron وحده — لو تعطّل لتراكمت العمليات حتى تلتهم الخادم.
if (!empty($r['started'])) { rsReapIdle(); }

if (empty($r['ok'])) {
    $msg = [
        'capacity'      => 'الخادم يشغّل أقصى عدد من القنوات حالياً',
        'ffmpeg_failed' => 'تعذّر بدء البثّ من المصدر',
        'disabled'      => 'الوسيط معطّل',
        'bad_url'       => 'رابط المصدر غير صالح',
        'start_timeout' => 'انتهت مهلة بدء البثّ',
        'vod_quota'     => 'مساحة تحويل الأفلام ممتلئة — أعد المحاولة بعد دقائق',
        'shell_disabled'=> 'shell_exec معطّلة في php.ini — الوسيط لا يستطيع تشغيل ffmpeg',
    ][$r['error'] ?? ''] ?? 'تعذّر تشغيل القناة';
    rxJson(['success' => false, 'error' => $r['error'] ?? 'unknown', 'message' => $msg,
            'detail' => $r['detail'] ?? null], 503);
}

// المسار العام للقائمة (Apache يخدم المقاطع كملفات ساكنة)
$url = rsPublicUrl($key);

// القناة تُحضَّر ولمّا تجهز قائمتها بعد: نُعيد 202 ليعيد العميل السؤال.
// لا نحبس الطلب حتى الجاهزية — عامل Apache محجوز طوال ذلك، و20 بدايةً
// باردة متزامنة تكفي لتجميد الموقع عند 500 مشترك.
if (!empty($r['pending'])) {
    rxJson([
        'success'     => false,
        'error'       => 'starting',
        'message'     => 'جارٍ تجهيز القناة…',
        'retry_after' => 2,
        'url'         => $url,
    ], 202);
}

if (!empty($_GET['play'])) {
    header('Location: ' . $url, true, 302);
    exit;
}

rxJson([
    'success' => true,
    'url'     => $url,
    'channel' => $row['name'],
    'started' => (bool)($r['started'] ?? false),
    'ping'    => 'restream.php?' . ($kind === 'e' ? 'ep=' : 'ch=') . $id . '&ping=1',
]);
