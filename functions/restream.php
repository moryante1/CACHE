<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 *  إعادة بثّ Xtream مع تحويل الصوت — المكتبة الأساسية
 * ───────────────────────────────────────────────────────────────────────────
 *  المشكلة: بثّ Xtream يحمل صوت AC3 ولا متصفح يفكّه. الفيديو h264 مدعوم.
 *  فالمطلوب تحويل الصوت وحده — قياسه على خادم حقيقي: 5% من نواة و55MB
 *  للقناة 720p، لأن الفيديو يُنسخ بلا إعادة ترميز.
 *
 *  ═══ التصميم: عملية لكل قناة لا لكل مشاهد ═══
 *
 *  الخطأ الشائع هنا هو تشغيل ffmpeg لكل مشاهد. عند 500 مشاهد يعني ذلك
 *  500 عملية و500 نسخة مسحوبة من المزوّد — مستحيل. الصحيح أن تكتب عملية
 *  واحدة مقاطع HLS إلى القرص، ويقرأها كل مشاهدي القناة عبر Apache
 *  كملفات ساكنة. 500 مشاهد على 20 قناة = 20 عملية = نواة واحدة.
 *
 *  المقاطع تُكتب في /dev/shm (ذاكرة) لا على القرص: بثّ حيّ يكتب ويحذف
 *  آلاف الملفات في الساعة، وقرص SSD يتآكل بذلك بلا داعٍ.
 *
 *  ⚠ القيد الحقيقي هو النطاق الترددي لا المعالج. الوسيط ينقل نطاق كل
 *    المشاهدين إلى خادمك بينما هو الآن على مزوّد Xtream.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (defined('RESTREAM_LIB')) return;
define('RESTREAM_LIB', '1.0.0');

/* ── الإعدادات (تُقرأ من .env) ── */
function rsCfg(string $k, $d = null) {
    $v = function_exists('env') ? env($k, $d) : ($_SERVER[$k] ?? $d);
    return ($v === null || $v === '') ? $d : $v;
}

/** جذر مقاطع HLS — ذاكرة إن توفّرت، وإلا storage. */
function rsRoot(): string {
    static $r = null;
    if ($r !== null) return $r;
    $pref = (string)rsCfg('RESTREAM_DIR', '');
    if ($pref !== '') return $r = rtrim($pref, '/');
    if (is_dir('/dev/shm') && is_writable('/dev/shm')) return $r = '/dev/shm/shs_hls';
    return $r = dirname(__DIR__) . '/storage/hls';
}

function rsMaxChannels(): int { return max(1, (int)rsCfg('RESTREAM_MAX_CHANNELS', 25)); }
function rsIdleTimeout(): int { return max(20, (int)rsCfg('RESTREAM_IDLE', 60)); }

/**
 * هل الوسيط مفعّل؟
 *
 * الأولوية لجدول settings لا لملف .env: المفتاح في لوحة الإدارة يجب أن
 * يعمل فوراً وبلا صلاحية كتابة على .env. و.env يبقى المرجع عند غياب
 * الإعداد من قاعدة البيانات، فلا ينكسر تركيب قائم لم يمرّ باللوحة.
 *
 * ملاحظة: RESTREAM_ENABLED=0 في .env يعطّل الوسيط نهائياً مهما قال
 * الجدول — مفتاح أمان يعمل حتى لو تعطّلت قاعدة البيانات.
 */
function rsEnabled(): bool
{
    static $cached = null;
    if ($cached !== null) return $cached;

    /* القفل الصلب: يتجاوز قاعدة البيانات ولوحة الإدارة معاً.
       يضعه setup_restream.sh --off، وهو مخرج الطوارئ حين لا تعمل اللوحة
       أو حين تريد إيقافاً مؤكّداً من الطرفية لا يستطيع أحد نقضه من الويب. */
    if ((string)rsCfg('RESTREAM_HARD_OFF', '0') === '1') {
        return $cached = false;
    }

    try {
        if (function_exists('db') && db()) {
            $st = db()->prepare("SELECT setting_value FROM settings WHERE setting_key='restream_enabled' LIMIT 1");
            $st->execute();
            $v = $st->fetchColumn();
            if ($v !== false && $v !== null) return $cached = ((string)$v === '1');
        }
    } catch (Throwable $e) { /* نرتدّ إلى .env */ }

    return $cached = ((string)rsCfg('RESTREAM_ENABLED', '0') === '1');
}

/** يكتب حالة التفعيل في قاعدة البيانات. */
function rsSetEnabled(bool $on): bool
{
    try {
        $pdo = db();
        $q = $pdo->prepare("SELECT id FROM settings WHERE setting_key='restream_enabled' LIMIT 1");
        $q->execute();
        if ($q->fetchColumn()) {
            $u = $pdo->prepare("UPDATE settings SET setting_value=? WHERE setting_key='restream_enabled'");
            $ok = $u->execute([$on ? '1' : '0']);
        } else {
            $i = $pdo->prepare("INSERT INTO settings (setting_key,setting_value) VALUES ('restream_enabled',?)");
            $ok = $i->execute([$on ? '1' : '0']);
        }
        // عند الإطفاء نُنهي كل ما يعمل فوراً بدل انتظار المنظّف
        if ($ok && !$on) rsStopAll();
        return $ok;
    } catch (Throwable $e) {
        if (function_exists('logTo')) logTo('error', 'rsSetEnabled: ' . $e->getMessage());
        return false;
    }
}

/** يُنهي كل عمليات إعادة البثّ. */
function rsStopAll(): int
{
    $n = 0;
    $dirs = [];
    foreach (rsAllRoots() as $root) {
        foreach (glob($root . '/*', GLOB_ONLYDIR) ?: [] as $d) $dirs[] = $d;
    }
    foreach ($dirs as $dir) {
        $pidF = $dir . '/.pid';
        $pid = is_file($pidF) ? (int)@file_get_contents($pidF) : 0;
        if ($pid > 1 && rsAlive($pid)) {
            if (function_exists('posix_kill')) @posix_kill($pid, 15); else @exec('kill ' . $pid . ' 2>/dev/null');
            for ($i = 0; $i < 10 && rsAlive($pid); $i++) usleep(100000);
            if (rsAlive($pid)) {
                if (function_exists('posix_kill')) @posix_kill($pid, 9); else @exec('kill -9 ' . $pid . ' 2>/dev/null');
            }
            $n++;
        }
        foreach (glob($dir . '/*') ?: [] as $f) @unlink($f);
        foreach (glob($dir . '/.*') ?: [] as $f) if (!is_dir($f)) @unlink($f);
        @rmdir($dir);
    }
    return $n;
}
function rsSecret(): string {
    $s = (string)rsCfg('RESTREAM_SECRET', '');
    if ($s !== '') return $s;
    // نشتقّه من مفتاح التطبيق بدل توليد مفتاح جديد كل طلب
    $f = dirname(__DIR__) . '/storage/.appkey';
    return is_file($f) ? hash('sha256', 'restream|' . (string)@file_get_contents($f)) : 'shs-restream-fallback';
}

/**
 * مفتاح البثّ — يميّز النوع لا الرقم وحده.
 *
 * محتوى Xtream موزّع على جدولين: القنوات المباشرة في `channels`،
 * والأفلام والمسلسلات في `episodes`. والرقم 5 قد يوجد في الاثنين
 * لصفّين مختلفين تماماً. لولا البادئة لتصادما على نفس المجلد فأعطى
 * أحدهما بثّ الآخر — عطل يصعب تفسيره لأن كل شيء "يعمل".
 *
 * @param string $kind 'c' لقناة أو 'e' لحلقة/فيلم
 */
function rsKey(string $kind, int $id): string {
    return ($kind === 'e' ? 'e' : 'c') . $id;
}

/**
 * اسم مجلد البثّ — غير قابل للتخمين.
 * لو استُخدم الرقم مباشرةً لأمكن تصفّح /hls/1/ و/hls/2/ والوصول إلى
 * كل شيء بلا اشتراك. الاشتقاق بـ HMAC يمنع ذلك.
 */
function rsSlug(string $key): string {
    return substr(hash_hmac('sha256', 'stream:' . $key, rsSecret()), 0, 24);
}

/**
 * هل هذا محتوى مسجَّل (فيلم/حلقة) لا بثّاً حيّاً؟
 * المفتاح يبدأ بـ e للحلقات، وهي دائماً VOD.
 */
function rsIsVod(string $key): bool { return isset($key[0]) && $key[0] === 'e'; }

/**
 * جذر تخزين المقاطع.
 *
 * البثّ الحيّ يذهب إلى الذاكرة: ست مقاطع متدحرجة لا تتجاوز 2MB، وتُحذف
 * خلف المشاهد باستمرار. أما الفيلم فيحتفظ بكل مقاطعه ليستطيع المشاهد
 * الإرجاع — ساعتان بمعدّل 2 ميغابت تساوي 1.8 غيغابايت، وذلك يملأ
 * /dev/shm بفيلم واحد ويُسقط الخادم. الأفلام على القرص إذن.
 */
function rsRootFor(string $key): string
{
    if (!rsIsVod($key)) return rsRoot();
    $d = (string)rsCfg('RESTREAM_VOD_DIR', '');
    return $d !== '' ? rtrim($d, '/') : dirname(__DIR__) . '/storage/vod';
}

/** حصّة القرص القصوى لكل الأفلام مجتمعةً (بالميغابايت). */
function rsVodQuotaMb(): int { return max(512, (int)rsCfg('RESTREAM_VOD_QUOTA_MB', 20480)); }

/** مهلة خمول الأفلام — أقصر من البثّ الحيّ لأنها تشغل قرصاً لا ذاكرة. */
function rsVodIdle(): int { return max(30, (int)rsCfg('RESTREAM_VOD_IDLE', 180)); }

/** الحجم الكلي لمجلد الأفلام بالبايت. */
function rsVodUsedBytes(): int
{
    $root = rsRootFor('e0');
    if (!is_dir($root)) return 0;
    $n = 0;
    foreach (glob($root . '/*/*.ts') ?: [] as $f) $n += (int)@filesize($f);
    return $n;
}

/**
 * المسار العام الذي يخدم منه Apache.
 * لكل جذر تخزين اسمُه المستعار: /hls للذاكرة و/vodhls للقرص. لو أعدنا
 * المسار نفسه للاثنين لبحث Apache عن مقاطع الفيلم في مجلد الذاكرة
 * فأعطى 404 — والملفات موجودة لكن في المكان الآخر.
 */
function rsPublicBase(string $key): string {
    $k = rsIsVod($key) ? 'RESTREAM_VOD_PUBLIC' : 'RESTREAM_PUBLIC';
    $d = rsIsVod($key) ? '/vodhls' : '/hls';
    return rtrim((string)rsCfg($k, $d), '/');
}

/** الرابط العام الكامل لقائمة التشغيل. */
function rsPublicUrl(string $key): string {
    return rsPublicBase($key) . '/' . rsSlug($key) . '/index.m3u8';
}

function rsDir(string $key): string     { return rsRootFor($key) . '/' . rsSlug($key); }
function rsIndex(string $key): string   { return rsDir($key) . '/index.m3u8'; }
function rsPidFile(string $key): string { return rsDir($key) . '/.pid'; }
function rsHitFile(string $key): string { return rsDir($key) . '/.hit'; }

/** هل يُسمح لـ PHP بتشغيل أوامر النظام؟ */
function rsCanExec(): bool
{
    static $ok = null;
    if ($ok !== null) return $ok;
    if (!function_exists('shell_exec')) return $ok = false;
    $dis = (string)@ini_get('disable_functions');
    foreach (preg_split('/\s*,\s*/', $dis) ?: [] as $f) {
        if (strcasecmp(trim($f), 'shell_exec') === 0) return $ok = false;
    }
    return $ok = true;
}

/** هل العملية حيّة؟ */
function rsAlive(int $pid): bool {
    if ($pid < 2) return false;
    // /proc أدقّ من posix_kill لأنه لا يحتاج امتيازات ولا يخطئ مع PID مُعاد استخدامه حديثاً
    if (is_dir('/proc')) return is_dir('/proc/' . $pid);
    if (function_exists('posix_kill')) return @posix_kill($pid, 0);
    @exec('ps -p ' . (int)$pid . ' -o pid= 2>/dev/null', $o);
    return !empty($o);
}

function rsPid(string $key): int {
    $f = rsPidFile($key);
    return is_file($f) ? (int)@file_get_contents($f) : 0;
}

function rsRunning(string $key): bool { return rsAlive(rsPid($key)); }

/** يسجّل أن أحداً طلب هذه القناة الآن (يمنع القاتل الدوري من إنهائها). */
function rsTouch(string $key): void { @touch(rsHitFile($key)); }

/** كل جذور التخزين (حيّ + أفلام) — تُستخدم في العدّ والتنظيف. */
function rsAllRoots(): array {
    $r = [rsRoot()];
    $v = rsRootFor('e0');
    if ($v !== $r[0]) $r[] = $v;
    return $r;
}

/** عدد العمليات العاملة حالياً في الجذرين معاً. */
function rsActiveCount(): int {
    $n = 0;
    foreach (rsAllRoots() as $root) {
        foreach (glob($root . '/*/.pid') ?: [] as $f) {
            if (rsAlive((int)@file_get_contents($f))) $n++;
        }
    }
    return $n;
}

/**
 * يوقف بثّ قناة وينظّف ملفاتها.
 */
function rsStop(string $key): void {
    $pid = rsPid($key);
    if ($pid > 1 && rsAlive($pid)) {
        // SIGTERM أولاً ليُغلق ffmpeg ملفاته بنظافة، ثم SIGKILL عند العناد
        if (function_exists('posix_kill')) @posix_kill($pid, 15); else @exec('kill ' . $pid . ' 2>/dev/null');
        for ($i = 0; $i < 12 && rsAlive($pid); $i++) usleep(120000);
        if (rsAlive($pid)) {
            if (function_exists('posix_kill')) @posix_kill($pid, 9); else @exec('kill -9 ' . $pid . ' 2>/dev/null');
        }
    }
    $d = rsDir($key);
    foreach (glob($d . '/*') ?: [] as $f) @unlink($f);
    foreach (glob($d . '/.*') ?: [] as $f) { if (!is_dir($f)) @unlink($f); }
    @rmdir($d);
}

/**
 * يشغّل ffmpeg لقناة إن لم يكن يعمل.
 *
 * @return array{ok:bool,error?:string,started?:bool}
 */
function rsStart(string $key, string $srcUrl): array {
    if (!rsEnabled()) return ['ok' => false, 'error' => 'disabled'];
    if (!preg_match('~^https?://~i', $srcUrl)) return ['ok' => false, 'error' => 'bad_url'];

    /* كثير من الاستضافات تعطّل shell_exec في php.ini عبر disable_functions.
       بدون هذا الفحص يُرجع @shell_exec القيمة null فيبدو الأمر كأن ffmpeg
       فشل — فيُبحث عن العلة في الترميز أو المصدر بينما الدالة نفسها ممنوعة.
       خطأ صريح هنا يوفّر ساعات في المكان الخطأ. */
    if (!rsCanExec()) return ['ok' => false, 'error' => 'shell_disabled'];

    if (rsRunning($key)) { rsTouch($key); return ['ok' => true, 'started' => false]; }

    // تنظيف بقايا عملية ميتة قبل البدء
    if (is_dir(rsDir($key))) {
        foreach (glob(rsDir($key) . '/*') ?: [] as $f) @unlink($f);
    }

    if (rsActiveCount() >= rsMaxChannels()) {
        return ['ok' => false, 'error' => 'capacity'];
    }

    /* حصّة قرص الأفلام.
       البثّ الحيّ يحدّ نفسه بست مقاطع متدحرجة، أما الفيلم فيتراكم حتى
       نهايته. بلا سقف يكفي عشرون مشاهداً لأفلام مختلفة ليمتلئ القرص —
       وامتلاء القرص لا يُعطّل الأفلام وحدها بل يُسقط قاعدة البيانات
       وسجلّات Apache معها. */
    if (rsIsVod($key)) {
        $usedMb = (int)round(rsVodUsedBytes() / 1048576);
        if ($usedMb >= rsVodQuotaMb()) {
            // ننظّف المهجور أولاً ثم نُعيد القياس قبل الرفض
            rsReapIdle();
            $usedMb = (int)round(rsVodUsedBytes() / 1048576);
            if ($usedMb >= rsVodQuotaMb()) {
                return ['ok' => false, 'error' => 'vod_quota',
                        'detail' => $usedMb . 'MB / ' . rsVodQuotaMb() . 'MB'];
            }
        }
    }

    $dir = rsDir($key);
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
        return ['ok' => false, 'error' => 'mkdir_failed'];
    }

    // قفل يمنع طلبين متزامنين من تشغيل عمليتين للقناة نفسها
    $lock = @fopen($dir . '/.lock', 'c');
    if ($lock === false) return ['ok' => false, 'error' => 'lock_failed'];
    if (!@flock($lock, LOCK_EX | LOCK_NB)) {
        // طلب آخر يشغّلها الآن — ننتظر ظهور القائمة بدل تكرار العملية
        fclose($lock);
        for ($i = 0; $i < 60; $i++) {
            if (is_file(rsIndex($key))) return ['ok' => true, 'started' => false];
            usleep(200000);
        }
        return ['ok' => false, 'error' => 'start_timeout'];
    }
    if (rsRunning($key)) { flock($lock, LOCK_UN); fclose($lock); rsTouch($key); return ['ok'=>true,'started'=>false]; }

    $idx = rsIndex($key);
    $log = $dir . '/.log';

    /* ── سقف عمر صلب للعملية ──
       كل آليات الإنهاء عندنا تمرّ بـ PHP: نبضة المشاهد، ثم cron، ثم
       التنظيف الانتهازي. وكلها تفشل معاً في حالة واحدة: تعطّل cron مع
       عدم ورود أي طلب جديد — فتبقى العملية تسحب من المزوّد إلى الأبد
       لأجل لا أحد.

       timeout يجعل العملية تنهي نفسها بلا وسيط. إن كان أحد يشاهد فعلاً
       عند انتهاء المهلة فنبضته تتلقّى 410 ويُعيد المشغّل التشغيل تلقائياً
       — انقطاع ثوانٍ مرة كل 12 ساعة مقابل ضمان ألا تتراكم عمليات يتيمة. */
    $isVod   = rsIsVod($key);
    $maxLife = max(600, (int)rsCfg('RESTREAM_MAX_LIFE', 43200));
    $timeout = '';
    // -k يرسل SIGKILL بعد 10 ثوانٍ إن تجاهل ffmpeg إشارة الإنهاء اللطيفة
    if (is_executable('/usr/bin/timeout')) {
        $timeout = '/usr/bin/timeout -k 10 ' . (int)$maxLife . ' ';
    } elseif (is_executable('/bin/timeout')) {
        $timeout = '/bin/timeout -k 10 ' . (int)$maxLife . ' ';
    }

    $cmd = $timeout . 'ffmpeg -hide_banner -loglevel error -nostdin'
         // مهلات تمنع تعليق العملية إلى الأبد عند سقوط المصدر
         . ' -rw_timeout 15000000 -reconnect 1 -reconnect_streamed 1 -reconnect_delay_max 5'
         . ' -user_agent ' . escapeshellarg('VLC/3.0.20 LibVLC/3.0.20')
         . ' -i ' . escapeshellarg($srcUrl)
         . ' -map 0:v:0 -map 0:a:0'
         // ★ جوهر الحل: الفيديو يُنسخ كما هو، الصوت وحده يُحوَّل.
         //   هذا يُصلح مشكلتين معاً: ترميز AC3 الذي لا يفكّه المتصفح،
         //   وحاوية MKV التي لا يفتحها أصلاً — لأن الخرج HLS/TS.
         . ' -c:v copy -c:a aac -b:a 128k -ac 2'
         /* hls_init_time=1 يجعل أول مقطع ثانيةً واحدة فقط ثم تعود المقاطع
            إلى 4 ثوانٍ. قياساً: زمن ظهور أول قائمة ينزل من 4.2s إلى 2.1s
            بلا خسارة في كفاءة النقل — المشاهد ينتظر نصف المدة. */
         . ' -f hls -hls_time 4 -hls_init_time 1'
         /* ══ الفارق الجوهري بين الفيلم والبثّ الحيّ ══
            الحيّ: قائمة متدحرجة من ست مقاطع تُحذف خلف المشاهد. لا معنى
                  للإرجاع في بثّ مباشر، والحذف يُبقي الذاكرة ثابتة.
            الفيلم: hls_list_size=0 يبقي كل المقاطع في القائمة، وبلا
                  delete_segments لا يُحذف شيء — فيستطيع المشاهد الإرجاع
                  والتقديم داخل ما أُنتج. ولولا ذلك لكان شريط التقدّم
                  زينةً لا تعمل. playlist_type=event يخبر المشغّل أن
                  المدة تنمو، فلا يعرض Infinity:NaN. */
         . ($isVod
             ? ' -hls_list_size 0 -hls_playlist_type event -hls_flags append_list'
             : ' -hls_list_size 6 -hls_flags delete_segments+append_list+omit_endlist')
         . ' -hls_allow_cache 0'
         . ' -hls_segment_filename ' . escapeshellarg($dir . '/s%05d.ts')
         . ' ' . escapeshellarg($idx)
         . ' > ' . escapeshellarg($log) . ' 2>&1 & echo $!';

    $pid = (int)@shell_exec($cmd);
    if ($pid < 2) {
        flock($lock, LOCK_UN); fclose($lock);
        return ['ok' => false, 'error' => 'spawn_failed'];
    }
    @file_put_contents(rsPidFile($key), (string)$pid);
    rsTouch($key);

    /* ── انتظار قصير ثم تسليم ──
       ننتظر أول قائمة تشغيل لأن إعادتها قبل وجودها تعني 404 عند المشغّل.
       لكن الانتظار محدود بثوانٍ قليلة عمداً: كل طلب منتظر يحجز عاملاً من
       عمّال Apache، وعند 500 مشترك يكفي عشرون بدايةً باردة متزامنة لتجميد
       الموقع كله. إن لم تجهز في المهلة نُعيد "قيد التحضير" ويعيد العميل
       السؤال — العملية تكمل في الخلفية بلا أن يحجزها أحد.
       القياس: أول قائمة تظهر خلال ~2 ثانية مع hls_init_time=1. */
    $waitMs   = 3500;
    $stepMs   = 150;
    $ready    = false;
    for ($i = 0, $n = (int)($waitMs / $stepMs); $i < $n; $i++) {
        if (is_file($idx) && filesize($idx) > 40) { $ready = true; break; }
        if (!rsAlive($pid)) break;
        usleep($stepMs * 1000);
    }
    flock($lock, LOCK_UN); fclose($lock);

    if ($ready) {
        if (function_exists('logTo')) logTo('info', "restream $key بدأ (pid=$pid)");
        return ['ok' => true, 'started' => true];
    }

    if (rsAlive($pid)) {
        // ما زالت تعمل — تحتاج وقتاً أطول فقط (مصدر بطيء أو GOP طويل)
        return ['ok' => true, 'started' => true, 'pending' => true];
    }

    // ماتت العملية: عطل حقيقي في المصدر
    $err = is_file($log) ? trim((string)@file_get_contents($log)) : '';
    rsStop($key);
    if (function_exists('logTo')) logTo('error', "restream $key فشل: " . mb_substr($err, 0, 300));
    return ['ok' => false, 'error' => 'ffmpeg_failed', 'detail' => mb_substr($err, 0, 300)];
}

/**
 * ينهي القنوات التي لم يطلبها أحد.
 * يُستدعى من cron وأيضاً بعد كل تشغيل جديد — بدونه تتراكم العمليات
 * إلى أن تلتهم الخادم، لأن المشاهد يغلق التبويب ولا يخبر أحداً.
 *
 * @return int عدد ما أُنهي
 */
function rsReapIdle(): int {
    $killed = 0;
    $liveTimeout = rsIdleTimeout();
    $vodTimeout  = rsVodIdle();
    $dirs = [];
    foreach (rsAllRoots() as $root) {
        $isVod = ($root !== rsRoot());
        foreach (glob($root . '/*', GLOB_ONLYDIR) ?: [] as $d) $dirs[] = [$d, $isVod];
    }
    foreach ($dirs as [$dir, $__isVod]) {
        $timeout = $__isVod ? $vodTimeout : $liveTimeout;
        $pidF = $dir . '/.pid';
        $hitF = $dir . '/.hit';
        $pid = is_file($pidF) ? (int)@file_get_contents($pidF) : 0;

        if ($pid > 1 && !rsAlive($pid)) {
            // عملية ماتت وتركت مجلدها
            foreach (glob($dir . '/*') ?: [] as $f) @unlink($f);
            foreach (glob($dir . '/.*') ?: [] as $f) if (!is_dir($f)) @unlink($f);
            @rmdir($dir);
            continue;
        }
        if ($pid < 2) continue;

        $last = is_file($hitF) ? (int)@filemtime($hitF) : 0;
        if ($last > 0 && (time() - $last) > $timeout) {
            if (function_exists('posix_kill')) @posix_kill($pid, 15); else @exec('kill ' . $pid . ' 2>/dev/null');
            usleep(300000);
            if (rsAlive($pid)) {
                if (function_exists('posix_kill')) @posix_kill($pid, 9); else @exec('kill -9 ' . $pid . ' 2>/dev/null');
            }
            foreach (glob($dir . '/*') ?: [] as $f) @unlink($f);
            foreach (glob($dir . '/.*') ?: [] as $f) if (!is_dir($f)) @unlink($f);
            @rmdir($dir);
            $killed++;
        }
    }
    return $killed;
}

/** لوحة حالة مختصرة. */
function rsStatus(): array {
    $out = ['enabled' => rsEnabled(), 'root' => rsRoot(), 'max' => rsMaxChannels(),
            'idle' => rsIdleTimeout(), 'channels' => []];
    $dirs = [];
    foreach (rsAllRoots() as $root) {
        foreach (glob($root . '/*', GLOB_ONLYDIR) ?: [] as $d) $dirs[] = $d;
    }
    foreach ($dirs as $dir) {
        $pid = is_file($dir . '/.pid') ? (int)@file_get_contents($dir . '/.pid') : 0;
        $segs = glob($dir . '/*.ts') ?: [];
        $size = 0;
        foreach ($segs as $s) $size += (int)@filesize($s);
        $out['channels'][] = [
            'slug'     => basename($dir),
            'pid'      => $pid,
            'alive'    => rsAlive($pid),
            'segments' => count($segs),
            'bytes'    => $size,
            'idle_s'   => is_file($dir . '/.hit') ? (time() - (int)@filemtime($dir . '/.hit')) : -1,
        ];
    }
    $out['active'] = count(array_filter($out['channels'], fn($c) => $c['alive']));
    return $out;
}
