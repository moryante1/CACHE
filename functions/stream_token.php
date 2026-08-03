<?php
/**
 * ══════════════════════════════════════════════════════════════
 *  نظام رموز البث الموقَّعة — حماية روابط البث من السرقة
 * ══════════════════════════════════════════════════════════════
 *
 *  🔴 الثغرة التي يعالجها هذا الملف (أخطر ثغرة خاصة بأنظمة IPTV)
 *  ──────────────────────────────────────────────────────────────
 *
 *  ملف api.php نقطة **عامة بلا مصادقة**، وكان يُرجع الأعمدة كاملة:
 *
 *      SELECT ch.*  →  يشمل stream_url و backup_url
 *
 *  ورابط بث Xtream يحمل بيانات الاشتراك داخل مساره:
 *
 *      http://line.provider.tv:8080/live/USERNAME/PASSWORD/812.m3u8
 *                                        ▲▲▲▲▲▲▲▲ ▲▲▲▲▲▲▲▲
 *
 *  أي أن أمراً واحداً يكشف اشتراكك المدفوع:
 *
 *      curl 'https://موقعك/api.php?action=channels&category_id=1'
 *
 *  الأثر المباشر:
 *   • اشتراكك يُستخدم ويُعاد بيعه من الآخرين مجاناً
 *   • استنفاد حدّ الاتصالات المتزامنة ⇒ مستخدموك لا يستطيعون المشاهدة
 *   • مزوّدك يحظر حسابك بسبب تجاوز الحد أو المشاركة
 *
 *  ──────────────────────────────────────────────────────────────
 *  الحل المطبَّق
 *  ──────────────────────────────────────────────────────────────
 *  لا يخرج الرابط الحقيقي من الخادم إطلاقاً. بدلاً منه يُرسَل رمز
 *  موقَّع بـ HMAC-SHA256 بمفتاح APP_KEY، منتهي الصلاحية، ويُفكّ فقط
 *  داخل stream.php.
 *
 *  ⚠️ حدود هذا الحل — بصراحة تامة:
 *  stream.php يُعيد التوجيه (302) إلى الرابط الحقيقي، فالمستخدم
 *  المتقدّم يستطيع رؤية الوجهة في أدوات المطوّر. لكن الفارق جوهري:
 *   • لم يعد ممكناً **سحب آلاف الروابط دفعةً واحدة** من الـAPI
 *   • الرمز ينتهي خلال ساعات فلا يصلح للنشر أو إعادة البيع
 *   • يمكن ربطه بعنوان IP ومراقبة إساءة الاستخدام
 *  الإخفاء الكامل يتطلب تمرير البث نفسه عبر خادمك (proxy)، وهو
 *  يستهلك كامل عرض نطاق البث ويحتاج خادماً قوياً — قرار يخصّك.
 */

if (!defined('STREAM_TOKEN_TTL')) {
    // مدة صلاحية الرمز — تكفي جلسة مشاهدة طويلة ولا تصلح للنشر
    define('STREAM_TOKEN_TTL', (int) (function_exists('env') ? env('STREAM_TOKEN_TTL', 43200) : 43200)); // 12 ساعة
}

/** هل حماية الروابط مفعّلة؟ (يمكن تعطيلها من .env عند الحاجة) */
function streamProtectionEnabled(): bool
{
    if (!function_exists('env')) {
        return true;
    }
    $v = strtolower((string) env('PROTECT_STREAM_URLS', '1'));
    return !($v === '0' || $v === 'false' || $v === 'off');
}

/** مفتاح التوقيع — مشتق من APP_KEY حتى لا نستخدمه مباشرة. */
function streamTokenKey(): string
{
    $base = defined('APP_KEY') ? APP_KEY : 'shashety-fallback-key';
    return hash_hmac('sha256', 'stream-token-v1', $base, true);
}

/** ترميز Base64 آمن للاستخدام في الروابط. */
function streamB64e(string $bin): string
{
    return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
}

/** فك ترميز Base64 الآمن. */
function streamB64d(string $txt): string
{
    $pad = strlen($txt) % 4;
    if ($pad) {
        $txt .= str_repeat('=', 4 - $pad);
    }
    return (string) base64_decode(strtr($txt, '-_', '+/'), true);
}

/**
 * توليد رمز موقَّع لرابط بث.
 *
 * @param string $url الرابط الحقيقي.
 * @param int    $ttl مدة الصلاحية بالثواني.
 * @return string الرمز.
 */
function streamTokenMake(string $url, int $ttl = 0): string
{
    $ttl = $ttl > 0 ? $ttl : STREAM_TOKEN_TTL;

    $payload = json_encode([
        'u' => $url,
        'e' => time() + $ttl,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $body = streamB64e((string) $payload);
    $sig  = streamB64e(hash_hmac('sha256', $body, streamTokenKey(), true));

    return $body . '.' . $sig;
}

/**
 * التحقق من رمز وفكّه.
 *
 * @param string      $token الرمز.
 * @param string|null $why   سبب الرفض عند الفشل.
 * @return string|null الرابط الحقيقي أو null.
 */
function streamTokenOpen(string $token, ?string &$why = null): ?string
{
    $why = '';

    if (strpos($token, '.') === false) {
        $why = 'صيغة رمز غير صالحة';
        return null;
    }

    [$body, $sig] = explode('.', $token, 2);

    $expected = streamB64e(hash_hmac('sha256', $body, streamTokenKey(), true));
    if (!hash_equals($expected, $sig)) {
        $why = 'توقيع غير صالح';   // رمز مزوَّر أو معدَّل
        return null;
    }

    $data = json_decode(streamB64d($body), true);
    if (!is_array($data) || empty($data['u']) || empty($data['e'])) {
        $why = 'محتوى رمز تالف';
        return null;
    }

    if ((int) $data['e'] < time()) {
        $why = 'انتهت صلاحية الرابط';
        return null;
    }

    $url = (string) $data['u'];
    if (!preg_match('#^https?://#i', $url)) {
        $why = 'بروتوكول غير مسموح';
        return null;
    }

    return $url;
}

/**
 * تحويل رابط حقيقي إلى رابط عام آمن.
 *
 * ⚠️ ملاحظة مهمة في التكامل مع المشغّل:
 * دالة detectFmt في الواجهة تحدّد نوع المشغّل من **امتداد الرابط**،
 * وهي تتجاهل ما بعد علامة الاستفهام:
 *      const c = url.split('?')[0]
 * لذلك لا يصحّ وضع الامتداد في معامل استعلام، وإلا عُوملت كل الروابط
 * على أنها HLS فتنكسر ملفات MP4 و TS.
 * الحل: نضع الامتداد في **مسار** الرابط عبر PATH_INFO:
 *      stream.php/v.m3u8?t=TOKEN
 * فيبقى الامتداد ظاهراً لـ detectFmt ويعمل المشغّل بشكل صحيح.
 *
 * @param string $url     الرابط الحقيقي.
 * @param string $baseUrl مسار القاعدة للموقع (مثل /iptv).
 * @return string الرابط العام.
 */
function streamPublicUrl(string $url, string $baseUrl = ''): string
{
    $url = trim($url);
    if ($url === '' || !streamProtectionEnabled()) {
        return $url;
    }

    // الروابط المحلية (ملفات مرفوعة على خادمك) لا تحتاج حماية
    if (!preg_match('#^https?://#i', $url)) {
        return $url;
    }

    // نستخرج الامتداد للحفاظ على عمل detectFmt في المشغّل
    $path = (string) parse_url($url, PHP_URL_PATH);
    $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if (!preg_match('/^[a-z0-9]{1,5}$/', $ext)) {
        $ext = 'm3u8'; // الافتراضي الأنسب للبث المباشر
    }

    $token = streamTokenMake($url);
    $base  = rtrim($baseUrl, '/');

    /* شكل الرابط قابل للضبط لأن دعم PATH_INFO يختلف بين إعدادات
       الخوادم:

       path  (الافتراضي): /stream.php/v.m3u8?t=…
             الامتداد في المسار ⇒ detectFmt في المشغّل يتعرّف على
             النوع بشكل صحيح (HLS/MP4/TS). ملف .htaccess يحتوي
             قاعدة RewriteRule تضمن عمله حتى مع PHP-FPM.

       query (احتياطي): /stream.php?e=m3u8&t=…
             لا يعتمد على PATH_INFO إطلاقاً — استخدمه إن ظهرت أخطاء
             404 على روابط البث (اضبط STREAM_URL_STYLE=query في .env).
             ملاحظة: في هذا الشكل يفقد detectFmt الامتداد فيعامل كل
             شيء كـ HLS، لذا لا يُنصح به إلا عند الضرورة. */
    $style = function_exists('env') ? strtolower((string) env('STREAM_URL_STYLE', 'path')) : 'path';

    if ($style === 'query') {
        return $base . '/stream.php?e=' . rawurlencode($ext) . '&t=' . rawurlencode($token);
    }

    return $base . '/stream.php/v.' . $ext . '?t=' . rawurlencode($token);
}

/**
 * استبدال روابط البث في صفوف قادمة من قاعدة البيانات.
 *
 * @param array  $rows    الصفوف.
 * @param array  $fields  أسماء أعمدة الروابط.
 * @param string $baseUrl مسار القاعدة.
 * @return array الصفوف بعد الاستبدال.
 */
function streamProtectRows(array $rows, array $fields = ['stream_url', 'backup_url'], string $baseUrl = ''): array
{
    if (!streamProtectionEnabled()) {
        return $rows;
    }

    foreach ($rows as &$row) {
        if (!is_array($row)) {
            continue;
        }
        foreach ($fields as $f) {
            if (!empty($row[$f]) && is_string($row[$f])) {
                $row[$f] = streamPublicUrl($row[$f], $baseUrl);
            }
        }
    }
    unset($row);

    return $rows;
}
