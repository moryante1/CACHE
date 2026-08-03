#!/usr/bin/env php
<?php
/**
 * منظّف عمليات إعادة البثّ — يُشغَّل من cron كل دقيقة.
 *
 * لماذا هو ضروري: المشاهد يغلق التبويب ولا يخبر الخادم. بلا منظّف تبقى
 * عملية ffmpeg تسحب من المزوّد وتكتب مقاطع إلى الأبد، وتتراكم العمليات
 * حتى تلتهم المعالج والنطاق معاً. كل قناة لم يطلبها أحد منذ RESTREAM_IDLE
 * ثانية تُنهى.
 *
 * الاستخدام:
 *     php /var/www/html/iptv/tools/restream_gc.php          تنظيف (cron)
 *     php ... --status         عرض القنوات العاملة
 *     php ... --list           عرض ما في قاعدة البيانات
 *     php ... --scan 12        فحص عيّنة من المكتبة
 *     php ... --test e123      اختبار تشغيل فيلم أو قناة
 *     php ... --stop e123      إنهاء بثّ بعينه
 *
 * ويقوم أيضاً بصيانة يومية: تقليم سجل دخول المشتركين الأقدم من 90 يوماً.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only\n"); }

require_once dirname(__DIR__) . '/core/config.php';
require_once dirname(__DIR__) . '/functions/restream.php';
require_once dirname(__DIR__) . '/functions/subscriptions.php';

$statusOnly = in_array('--status', $argv, true);

// ── عرض القنوات المتاحة: php restream_gc.php --list ──
if (in_array('--list', $argv, true)) {
    $C = fn($c, $s) => "\033[{$c}m{$s}\033[0m";
    $mask = fn($u) => preg_replace('~/(live|movie|series)/([^/]+)/([^/]+)/~', '/$1/•••/•••/', (string)$u);

    try {
        $tot = (int)db()->query("SELECT COUNT(*) FROM channels")->fetchColumn();
        echo "\n" . $C('1', "══ جدول channels ($tot صفاً) ══") . "\n\n";
        if ($tot === 0) {
            echo "  " . $C('33','لا قنوات في هذا الجدول.') . "\n";
        } else {
            $rows = db()->query(
                "SELECT id, name, stream_url FROM channels
                 WHERE stream_url LIKE 'http%' ORDER BY id LIMIT 30"
            )->fetchAll(PDO::FETCH_ASSOC);
            printf("  %-8s %-34s %s\n", 'الرقم', 'الاسم', 'الرابط');
            foreach ($rows as $r) {
                printf("  %-8d %-34s %s\n", $r['id'],
                    mb_substr((string)$r['name'], 0, 32),
                    mb_substr($mask($r['stream_url']), 0, 60));
            }
            if ($tot > 30) echo "  … و" . ($tot - 30) . " غيرها\n";
        }
    } catch (Throwable $e) { echo "  خطأ: " . $e->getMessage() . "\n"; }

    // المحتوى قد يكون في episodes (أفلام/مسلسلات) لا في channels
    try {
        $et = (int)db()->query("SELECT COUNT(*) FROM episodes WHERE stream_url LIKE 'http%'")->fetchColumn();
        echo "\n" . $C('1', "══ جدول episodes ($et صفاً بروابط) ══") . "\n\n";
        if ($et > 0) {
            $rows = db()->query(
                "SELECT id, title, stream_url FROM episodes
                 WHERE stream_url LIKE 'http%' ORDER BY id DESC LIMIT 10"
            )->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $r) {
                printf("  %-8d %-34s %s\n", $r['id'],
                    mb_substr((string)($r['title'] ?? ''), 0, 32),
                    mb_substr($mask($r['stream_url']), 0, 60));
            }
            echo "\n  " . $C('33','ملاحظة:')
               . " للاختبار استخدم البادئة e مع الأفلام والحلقات: --test e" . ($rows[0]['id'] ?? '123') . "\n";
        }
    } catch (Throwable $e) { /* الجدول قد لا يوجد */ }

    echo "\n  للاختبار:  php " . __FILE__ . " --test 5      (قناة مباشرة)\n";
    echo "            php " . __FILE__ . " --test e123   (فيلم أو حلقة)\n\n";
    exit(0);
}

// ── مسح عيّنة من المحتوى: php restream_gc.php --scan [عدد] ──
// يفحص ملفات حقيقية من المكتبة ليقول ما الذي يعمل في المتصفح وما لا يعمل.
// عشرة آلاف صفّ لا تُفحص كلها، لكن عيّنة عشوائية تكفي لمعرفة النمط.
$ni = array_search('--scan', $argv, true);
if ($ni !== false) {
    $n = max(3, min(40, (int)($argv[$ni + 1] ?? 8)));
    $C = fn($c, $s) => "\033[{$c}m{$s}\033[0m";
    $OKA = ['aac','mp3','opus','flac'];
    $OKV = ['h264','vp9','av1'];

    echo "\n" . $C('1', "══ مسح $n عيّنة من المكتبة ══") . "\n\n";

    try {
        $rows = db()->query(
            "SELECT id, title, stream_url FROM episodes
             WHERE stream_url LIKE 'http%' ORDER BY RAND() LIMIT $n"
        )->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) { echo "خطأ: " . $e->getMessage() . "\n"; exit(1); }

    $stat = ['ok'=>0, 'audio'=>0, 'video'=>0, 'both'=>0, 'fail'=>0];
    $codecs = [];

    printf("  %-7s %-26s %-6s %-9s %-9s %s\n", 'الرقم', 'العنوان', 'الحاوية', 'فيديو', 'صوت', 'المتصفح');
    echo '  ' . str_repeat('─', 82) . "\n";

    foreach ($rows as $r) {
        $url = (string)$r['stream_url'];
        $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION));
        $out = @shell_exec('ffprobe -v error -show_entries stream=codec_type,codec_name '
            . '-of csv=p=0 -analyzeduration 3000000 -probesize 4000000 '
            . '-rw_timeout 12000000 ' . escapeshellarg($url) . ' 2>&1');

        $v = $a = '—';
        foreach (preg_split('/\R/', trim((string)$out)) ?: [] as $line) {
            $p = array_map('trim', explode(',', $line));
            if (count($p) < 2) continue;
            [$c, $t] = [$p[0], $p[1]];
            if ($t === 'video' && $v === '—') $v = $c;
            if ($t === 'audio' && $a === '—') $a = $c;
        }
        if ($a !== '—') $codecs[$a] = ($codecs[$a] ?? 0) + 1;

        $badV = $v !== '—' && !in_array($v, $OKV, true);
        $badA = $a !== '—' && !in_array($a, $OKA, true);
        // MKV لا يعمل في المتصفح مهما كان ما بداخله
        $badC = in_array($ext, ['mkv','avi','wmv','flv','mpg','ts'], true);

        if ($v === '—' && $a === '—') { $stat['fail']++; $verdict = $C('33','تعذّر الفحص'); }
        elseif ($badC)               { $stat['both']++;  $verdict = $C('31','حاوية غير مدعومة'); }
        elseif ($badV && $badA)      { $stat['both']++;  $verdict = $C('31','فيديو+صوت'); }
        elseif ($badA)               { $stat['audio']++; $verdict = $C('31','لا صوت'); }
        elseif ($badV)               { $stat['video']++; $verdict = $C('31','لا صورة'); }
        else                         { $stat['ok']++;    $verdict = $C('32','يعمل ✔'); }

        printf("  %-7d %-26s %-6s %-9s %-9s %s\n", $r['id'],
            mb_substr(html_entity_decode((string)$r['title']), 0, 24), $ext ?: '?', $v, $a, $verdict);
    }

    $tot = count($rows);
    echo "\n" . $C('1', "── الخلاصة من $tot عيّنة ──") . "\n";
    printf("  %s يعمل في المتصفح:        %d\n", $C('32','✔'), $stat['ok']);
    printf("  %s صوت غير مدعوم فقط:      %d  ← يصلحها الوسيط\n", $C('31','✘'), $stat['audio']);
    printf("  %s حاوية/فيديو غير مدعوم:  %d  ← تحتاج إعادة تغليف أيضاً\n", $C('31','✘'), $stat['both'] + $stat['video']);
    printf("  %s تعذّر الفحص:            %d\n", $C('33','⚠'), $stat['fail']);
    if ($codecs) {
        echo "\n  ترميزات الصوت المرصودة: ";
        $parts = [];
        foreach ($codecs as $c => $k) {
            $parts[] = (in_array($c, $OKA, true) ? $C('32','✔') : $C('31','✘')) . " $c×$k";
        }
        echo implode(' · ', $parts) . "\n";
    }
    echo "\n";
    exit(0);
}

// ── وضع الاختبار: php restream_gc.php --test <channel_id> ──
// يتجاوز طبقة الويب كلها (جلسة، صلاحيات، CSRF) ليعزل السؤال في:
// هل يستطيع ffmpeg قراءة هذا المصدر وإنتاج مقاطع؟ الخلط بين فشل
// المصادقة وفشل البثّ يضيّع وقتاً طويلاً في المكان الخطأ.
$ti = array_search('--test', $argv, true);
if ($ti !== false) {
    $spec = (string)($argv[$ti + 1] ?? '');
    // يقبل  5  أو  c5  أو  e12
    if (preg_match('/^([ce])?(\d+)$/i', trim($spec), $m)) {
        $kind = strtolower($m[1] ?: 'c'); $chId = (int)$m[2];
    } else { $kind = 'c'; $chId = 0; }
    if ($chId < 1) {
        fwrite(STDERR, "الاستخدام: php restream_gc.php --test <رقم>\n"
             . "            5   أو  c5  لقناة مباشرة\n"
             . "            e12          لفيلم أو حلقة\n");
        exit(1);
    }
    $key = rsKey($kind, $chId);

    $C = fn($c, $s) => "\033[{$c}m{$s}\033[0m";
    echo "\n" . $C('1', "══ اختبار " . ($kind === 'e' ? "الفيلم/الحلقة" : "القناة") . " #$chId ══") . "\n\n";

    // ١) ffmpeg
    $ff = @shell_exec('ffmpeg -version 2>/dev/null');
    printf("  %s ffmpeg: %s\n", $ff ? $C('32','✔') : $C('31','✘'),
           $ff ? trim(strtok((string)$ff, "\n")) : 'غير مثبّت');
    if (!$ff) exit(1);

    // ٢) حالة التفعيل
    printf("  %s الوسيط: %s\n", rsEnabled() ? $C('32','✔') : $C('31','✘'),
           rsEnabled() ? 'مفعّل' : 'معطّل — فعّله من اللوحة أو بـ setup_restream.sh');
    printf("  %s مجلد المقاطع: %s%s\n",
           is_writable(rsRoot()) || @mkdir(rsRoot(), 0755, true) ? $C('32','✔') : $C('31','✘'),
           rsRoot(), is_writable(rsRoot()) ? '' : ' (غير قابل للكتابة!)');

    // ٣) الرابط من قاعدة البيانات
    try {
        $st = $kind === 'e'
            ? db()->prepare('SELECT title AS name, stream_url FROM episodes WHERE id=? LIMIT 1')
            : db()->prepare('SELECT name, stream_url FROM channels WHERE id=? LIMIT 1');
        $st->execute([$chId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) { $row = null; echo "  " . $C('31','✘') . " قاعدة البيانات: " . $e->getMessage() . "\n"; }
    if (!$row) { echo "  " . $C('31','✘') . " القناة غير موجودة\n"; exit(1); }

    $src = trim((string)$row['stream_url']);
    printf("  %s القناة: %s\n", $C('32','✔'), $row['name']);
    printf("    الرابط: %s\n", preg_replace('~/(live|movie|series)/([^/]+)/([^/]+)/~', '/$1/•••/•••/', $src));

    // ٤) هل المصدر قابل للقراءة؟
    echo "\n" . $C('1', "── فحص المصدر بـ ffprobe ──") . "\n";
    $probe = @shell_exec('ffprobe -v error -show_entries stream=codec_type,codec_name '
        . '-of csv=p=0 -rw_timeout 10000000 ' . escapeshellarg($src) . ' 2>&1');
    echo '    ' . str_replace("\n", "\n    ", trim((string)$probe) ?: '(لا ناتج)') . "\n";

    // ٥) التشغيل الفعلي
    echo "\n" . $C('1', "── تشغيل التحويل ──") . "\n";
    rsStop($key);                      // بداية نظيفة
    $t0 = microtime(true);
    $r = rsStart($key, $src);
    $ms = (int)round((microtime(true) - $t0) * 1000);

    if (!empty($r['ok'])) {
        printf("    %s بدأ خلال %dms%s\n", $C('32','✔'), $ms,
               !empty($r['pending']) ? ' (ما زال يجهّز)' : '');
        sleep(6);
        $segs = glob(rsDir($key) . '/*.ts') ?: [];
        printf("    %s المقاطع بعد 6 ثوانٍ: %d\n", $segs ? $C('32','✔') : $C('31','✘'), count($segs));

        if ($segs) {
            $out = @shell_exec('ffprobe -v error -show_entries stream=codec_type,codec_name '
                . '-of csv=p=0 ' . escapeshellarg(rsIndex($key)) . ' 2>&1');
            echo "    ترميز الناتج: " . str_replace("\n", ' · ', trim((string)$out)) . "\n";
            printf("    %s الرابط العام: %s\n", $C('32','✔'), rsPublicUrl($key));
            echo "\n    " . $C('33','جرّب من المتصفح:') . " http://عنوانك" . rsPublicUrl($key) . "\n";
        }
    } else {
        printf("    %s فشل: %s\n", $C('31','✘'), $r['error'] ?? '؟');
        if (!empty($r['detail'])) echo "    التفاصيل: " . $r['detail'] . "\n";
    }

    // ٦) سجل ffmpeg الكامل — أهمّ سطر في الاختبار كله
    $log = rsDir($key) . '/.log';
    if (is_file($log)) {
        $txt = trim((string)@file_get_contents($log));
        if ($txt !== '') {
            echo "\n" . $C('1', "── سجل ffmpeg ──") . "\n    "
               . str_replace("\n", "\n    ", mb_substr($txt, 0, 1500)) . "\n";
        }
    }

    echo "\n" . $C('33','ملاحظة:') . " القناة ما تزال تعمل. لإنهائها:\n";
    echo "    php " . __FILE__ . " --stop $chId\n\n";
    exit(0);
}

// ── إنهاء قناة بعينها ──
$si = array_search('--stop', $argv, true);
if ($si !== false) {
    $chId = (int)($argv[$si + 1] ?? 0);
    if ($chId > 0) { rsStop($key); echo "أُنهيت القناة #$chId\n"; }
    exit(0);
}

if ($statusOnly) {
    $s = rsStatus();
    printf("الوسيط: %s   الجذر: %s\n", $s['enabled'] ? 'مفعّل' : 'معطّل', $s['root']);
    printf("القنوات النشطة: %d / %d   مهلة الخمول: %ds\n\n", $s['active'], $s['max'], $s['idle']);
    if (!$s['channels']) { echo "لا قنوات تعمل.\n"; exit(0); }
    printf("%-26s %-8s %-7s %-9s %s\n", 'المجلد', 'PID', 'حيّة', 'مقاطع', 'خمول');
    foreach ($s['channels'] as $c) {
        printf("%-26s %-8d %-7s %-9d %ds\n",
            $c['slug'], $c['pid'], $c['alive'] ? 'نعم' : 'لا', $c['segments'], $c['idle_s']);
    }
    exit(0);
}

$killed = rsReapIdle();

/* صيانة يومية تركب على نفس المهمة الدقيقية بدل cron إضافي.
   الدالة نفسها محروسة بعلامة ملف فلا تُنفَّذ إلا مرة كل 23 ساعة —
   استدعاؤها كل دقيقة رخيص (فحص وجود ملف) وأبسط من جدولة ثانية
   قد تُنسى عند نقل الخادم. */
$pruned = subsPruneLogs(90);

// تنظيف مجلدات يتيمة بلا .pid (بقايا انهيار)
$orphans = 0;
foreach (glob(rsRoot() . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
    if (is_file($dir . '/.pid')) continue;
    // نمهلها دقيقتين تحسّباً لمجلد قيد الإنشاء الآن
    if ((time() - (int)@filemtime($dir)) < 120) continue;
    foreach (glob($dir . '/*') ?: [] as $f) @unlink($f);
    foreach (glob($dir . '/.*') ?: [] as $f) if (!is_dir($f)) @unlink($f);
    @rmdir($dir);
    $orphans++;
}

if ($killed || $orphans || $pruned) {
    $msg = "restream_gc: أُنهيت $killed قناة خاملة، ونُظّف $orphans مجلداً يتيماً"
         . ($pruned ? "، وحُذف $pruned سجلّ دخول قديم" : '');
    if (function_exists('logTo')) logTo('info', $msg);
    echo $msg . "\n";
}
exit(0);
