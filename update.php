<?php
require_once __DIR__ . '/core/config.php';

// The updater downloads and overwrites application files. Referer headers are
// not authentication, therefore both a logged-in admin and CSRF are required.
if (!isAdminLoggedIn()) {
    header('Location: login.php');
    exit;
}
if (!in_array($_SESSION['admin_role'] ?? '', ['administrator', 'super'], true)) {
    http_response_code(403);
    exit('Forbidden');
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && !csrfValidate()) {
    http_response_code(403);
    exit('Invalid request');
}
// 🔒 --- بداية حماية التوجيه الذكي (Referer) --- 🔒
// فحص عند محاولة فتح الصفحة برابط مباشر (GET)
if (false) { // Superseded by the authenticated-session guard above.
    $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
    $current_file = basename(__FILE__); // جلب اسم الملف الحالي ديناميكيا

    // يُسمح بفتح الصفحة فقط إذا كانت الإحالة قادمة من "upgrade.php" 
    // أو إذا قام بالتنقل داخل الملف نفسه (كالضغط على زر إعادة الفحص)
    if (strpos($referer, "admin.php") === false && strpos($referer, $current_file) === false) {
        header("Location: index.php");
        exit(); // إيقاف فوري لتنفيذ الصفحة وتأمينها
    }
}
// 🔒 --- نهاية الحماية --- 🔒

/**
 * ====================================================
 *  loader Auto Updater — نظام تحديث متعدد القنوات
 *  نظام تحديث ذكي بمقارنة version.txt + ما الجديد
 * ====================================================
 */

define('GITHUB_TOKEN',    '');
define('SITE_DIR',        __DIR__);
define('VERSION_LOCAL',   SITE_DIR . '/version.txt');
define('CHANNEL_FILE',    SITE_DIR . '/channel.txt');

// ─── قنوات التحديث ─────────────────────────────────────
$CHANNELS = [
    'stable' => [
        'key'      => 'stable',
        'name'     => 'الإصدار المستقر',
        'tag'      => 'STABLE',
        'icon'     => '🛡️',
        'desc'     => 'إصدار مُختبر بالكامل وموصى به لجميع المستخدمين على السيرفرات الفعلية.',
        'color'    => '#34d399',
        'zip'      => 'https://codeload.github.com/moryante1/iptv-update/zip/refs/heads/main',
        'version'  => 'https://raw.githubusercontent.com/moryante1/iptv-update/main/version.txt',
        'whatsnew' => 'https://raw.githubusercontent.com/moryante1/iptv-update/main/whatsnew.txt',
    ],
    'testing' => [
        'key'      => 'testing',
        'name'     => 'قناة الاختبار',
        'tag'      => 'BETA',
        'icon'     => '🧪',
        'desc'     => 'أحدث المميزات قبل إطلاقها رسمياً — قد تحتوي على أخطاء. للمختبرين فقط.',
        'color'    => '#fbbf24',
        'zip'      => 'https://codeload.github.com/moryante1/channel-testing/zip/refs/heads/main',
        'version'  => 'https://raw.githubusercontent.com/moryante1/channel-testing/main/version.txt',
        'whatsnew' => 'https://raw.githubusercontent.com/moryante1/channel-testing/main/whatsnew.txt',
    ],
];

function saved_channel(): string {
    if (file_exists(CHANNEL_FILE)) {
        $c = trim(@file_get_contents(CHANNEL_FILE));
        if ($c === 'stable' || $c === 'testing') return $c;
    }
    return 'stable';
}

$channel = $_POST['channel'] ?? $_GET['channel'] ?? saved_channel();
if (!isset($CHANNELS[$channel])) $channel = 'stable';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    @file_put_contents(CHANNEL_FILE, $channel, LOCK_EX);
}
$CH = $CHANNELS[$channel];

define('ZIP_URL',         $CH['zip']);
define('VERSION_REMOTE',  $CH['version']);
define('WHATSNEW_REMOTE', $CH['whatsnew']);

// ─── دوال مساعدة ───────────────────────────────────────

function gh_get(string $url) {
    $headers = ['User-Agent: PHP-Updater'];
    if (GITHUB_TOKEN) $headers[] = 'Authorization: token ' . GITHUB_TOKEN;
    $ctx = stream_context_create(['http' => [
        'method' => 'GET', 'timeout' => 15,
        'follow_location' => true, 'header' => $headers,
    ]]);
    return @file_get_contents($url, false, $ctx);
}

function get_local_version(): string {
    if (!file_exists(VERSION_LOCAL)) return '0.0.0';
    return trim(file_get_contents(VERSION_LOCAL)) ?: '0.0.0';
}

function get_remote_version() {
    $v = gh_get(VERSION_REMOTE);
    return $v !== false ? trim($v) : false;
}

function get_whatsnew(): array {
    $raw = gh_get(WHATSNEW_REMOTE);
    if ($raw === false || trim($raw) === '') return [];

    $lines   = explode("\n", str_replace("\r", '', trim($raw)));
    $items   = [];
    $cur_ver = null;
    $cur_items = [];

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') continue;

        if (preg_match('/^(?:\[|#{1,3}\s*v?)(\d+\.\d+[\.\d]*)[\]:]?\s*(.*)/', $line, $m)) {
            if ($cur_ver !== null) $items[$cur_ver] = $cur_items;
            $cur_ver   = $m[1];
            $cur_items = [];
            $subtitle  = trim($m[2] ?? '');
            if ($subtitle !== '') $cur_items[] = ['type' => 'title', 'text' => $subtitle];
            continue;
        }

        if (preg_match('/^([+\-\*•✓✔►▶→✨🔧🐛⚡🚀🎉🔥💡🆕🛠️])\s+(.+)/', $line, $m)) {
            $sym  = $m[1];
            $text = trim($m[2]);
            $type = 'feature';
            if (in_array($sym, ['-', '🐛']))           $type = 'fix';
            if (in_array($sym, ['*', '🔧', '🛠️', '⚡'])) $type = 'change';
            $cur_items[] = ['type' => $type, 'text' => $text];
            continue;
        }

        $cur_items[] = ['type' => 'text', 'text' => $line];
    }

    if ($cur_ver !== null) $items[$cur_ver] = $cur_items;

    if (empty($items) && !empty($lines)) {
        $plain = [];
        foreach ($lines as $l) {
            $l = trim($l);
            if ($l !== '') $plain[] = ['type' => 'text', 'text' => $l];
        }
        $items['latest'] = $plain;
    }

    return $items;
}

function version_newer(string $remote, string $local): bool {
    return version_compare($remote, $local, '>');
}

function msg(string $text, string $type = 'info'): void {
    $icons  = ['info'=>'◈','success'=>'◆','error'=>'◉','warn'=>'◇'];
    $colors = ['info'=>'#60a5fa','success'=>'#34d399','error'=>'#f87171','warn'=>'#fbbf24'];
    $bgs    = ['info'=>'rgba(96,165,250,0.06)','success'=>'rgba(52,211,153,0.06)',
               'error'=>'rgba(248,113,113,0.06)','warn'=>'rgba(251,191,36,0.06)'];
    $color  = $colors[$type] ?? '#60a5fa';
    $icon   = $icons[$type]  ?? '◈';
    $bg     = $bgs[$type]    ?? 'rgba(96,165,250,0.06)';
    echo "<div class='log-line' style='border-left-color:{$color};background:{$bg}'>
            <span class='log-icon' style='color:{$color}'>{$icon}</span>
            <span class='log-text'>" . htmlspecialchars($text) . "</span>
          </div>\n";
    ob_flush(); flush();
}

function unzip_manual(string $zip_file, string $dest_dir): array {
    $results = ['ok'=>[],'fail'=>[]];
    $data = file_get_contents($zip_file);
    if ($data === false) return $results;
    $len = strlen($data); $pos = 0;
    while ($pos < $len - 4) {
        if (substr($data,$pos,4) !== "PK\x03\x04") { $pos++; continue; }
        $h = unpack('vversion/vflag/vmethod/vmtime/vmdate/Vcrc/Vcomp_size/Vuncomp_size/vname_len/vextra_len',
                    substr($data,$pos+4,26));
        $name       = str_replace('\\', '/', substr($data,$pos+30,$h['name_len']));
        $data_start = $pos+30+$h['name_len']+$h['extra_len'];
        $comp_data  = substr($data,$data_start,$h['comp_size']);
        $pos        = $data_start+$h['comp_size'];
        if (substr($name,-1)==='/') continue;
        if ($name === '' || $name[0] === '/' || strpos($name, "\0") !== false || preg_match('#(^|/)\.\.(/|$)#', $name)) {
            $results['fail'][] = $name;
            continue;
        }
        $content = false;
        if ($h['method']===0) $content=$comp_data;
        elseif($h['method']===8) $content=@gzinflate($comp_data);
        if ($content===false) { $results['fail'][]=$name; continue; }
        $full_path = $dest_dir.'/'.$name;
        $dir = dirname($full_path);
        if (!is_dir($dir)) mkdir($dir,0755,true);
        if (file_put_contents($full_path,$content)!==false) $results['ok'][]=$name;
        else $results['fail'][]=$name;
    }
    return $results;
}

function download_zip(string $url) {
    $tmp = sys_get_temp_dir().'/gh_update_'.time().'.zip';
    $headers = ['User-Agent: PHP-Updater'];
    if (GITHUB_TOKEN) $headers[] = 'Authorization: token '.GITHUB_TOKEN;
    $ctx = stream_context_create(['http'=>['method'=>'GET','timeout'=>90,'follow_location'=>true,'header'=>$headers]]);
    $data = @file_get_contents($url,false,$ctx);
    if ($data===false||strlen($data)<100) return false;
    file_put_contents($tmp,$data);
    return $tmp;
}

function rrmdir(string $dir): void {
    if (!is_dir($dir)) return;
    foreach (scandir($dir) as $f) {
        if ($f==='.'||$f==='..') continue;
        $p="$dir/$f";
        is_dir($p)?rrmdir($p):unlink($p);
    }
    rmdir($dir);
}

function copy_to_site(string $source_dir): array {
    $done=$failed=$skipped=0;
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source_dir,RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($it as $item) {
        $rel  = ltrim(str_replace($source_dir,'',$item->getPathname()),'/\\');
        $rel  = str_replace('\\','/',$rel);
        $dest = SITE_DIR.DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,$rel);
        if ($item->isDir()) { if (!is_dir($dest)) mkdir($dest,0755,true); continue; }
        if (strpos($rel, '.git') !== false) { $skipped++; continue; }
        $dd=dirname($dest);
        if (!is_dir($dd)) mkdir($dd,0755,true);
        if (copy($item->getPathname(),$dest)) { msg("تم تحديث: $rel","success"); $done++; }
        else                                  { msg("فشل نسخ: $rel","error");    $failed++; }
    }
    return [$done,$failed,$skipped];
}

// ─── منطق الصفحة ───────────────────────────────────────

$do_update = isset($_POST['do_update']);
$force     = isset($_POST['force']);

$local_ver  = get_local_version();
$remote_ver = get_remote_version();
$has_update = $remote_ver !== false && version_newer($remote_ver,$local_ver);
$up_to_date = $remote_ver !== false && !version_newer($remote_ver,$local_ver);
$whatsnew   = (!$do_update) ? get_whatsnew() : [];

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>IPTV Updater</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700;900&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --bg:#0a0a0f;--card:#13131e;--border:rgba(255,255,255,.07);--border-h:rgba(255,255,255,.14);
  --red:#e50914;--red-glow:rgba(229,9,20,.35);--text:#e8e8f0;--muted:#6b6b80;
  --success:#34d399;--error:#f87171;--warn:#fbbf24;--info:#60a5fa;--purple:#a78bfa;
  --mono:'JetBrains Mono',monospace;--sans:'Cairo',sans-serif;
}
html{scroll-behavior:smooth}
body{
  font-family:var(--sans);background:var(--bg);color:var(--text);min-height:100vh;
  display:flex;flex-direction:column;align-items:center;justify-content:center;
  padding:24px 16px 60px;position:relative;overflow-x:hidden;
}
body::before{
  content:'';position:fixed;inset:0;pointer-events:none;z-index:0;
  background:radial-gradient(ellipse 80% 50% at 50% -10%,rgba(229,9,20,.13) 0%,transparent 65%),
             radial-gradient(ellipse 55% 40% at 100% 80%,rgba(229,9,20,.05) 0%,transparent 60%),
             radial-gradient(ellipse 50% 50% at 0% 100%,rgba(80,20,200,.04) 0%,transparent 60%);
}
body::after{
  content:'';position:fixed;inset:0;pointer-events:none;z-index:0;opacity:.4;
  background-image:url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.03'/%3E%3C/svg%3E");
}
.wrapper{position:relative;z-index:1;width:100%;max-width:720px}
.header{text-align:center;margin-bottom:36px;animation:fadeDown .7s cubic-bezier(.16,1,.3,1) both}
.logo-mark{display:inline-flex;align-items:center;gap:10px;margin-bottom:18px}
.logo-icon{
  width:46px;height:46px;background:var(--red);border-radius:11px;
  display:flex;align-items:center;justify-content:center;font-size:22px;
  box-shadow:0 0 30px var(--red-glow),0 4px 15px rgba(0,0,0,.5);
  position:relative;overflow:hidden;
}
.logo-icon::after{
  content:'';position:absolute;top:0;left:-100%;width:60%;height:100%;
  background:linear-gradient(90deg,transparent,rgba(255,255,255,.2),transparent);
  animation:shimmer 3s ease-in-out infinite;
}
.logo-text{font-size:1.1rem;font-weight:700;letter-spacing:3px;text-transform:uppercase}
.logo-text span{color:var(--red)}
h1{
  font-size:clamp(1.7rem,5vw,2.4rem);font-weight:900;line-height:1.1;letter-spacing:-1px;
  background:linear-gradient(135deg,#fff 0%,rgba(255,255,255,.55) 100%);
  -webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;
  margin-bottom:8px;
}
.subtitle{color:var(--muted);font-size:.88rem;font-weight:300;letter-spacing:.5px}
.card{
  background:var(--card);border:1px solid var(--border);border-radius:20px;padding:34px;
  position:relative;overflow:hidden;
  animation:fadeUp .7s cubic-bezier(.16,1,.3,1) .15s both;
  box-shadow:0 30px 80px rgba(0,0,0,.6),0 0 0 1px var(--border);
}
.card::before{
  content:'';position:absolute;top:0;left:0;right:0;height:1px;
  background:linear-gradient(90deg,transparent,rgba(229,9,20,.5),rgba(255,255,255,.08),transparent);
}
.meta-row{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:28px}
.meta-badge{
  display:flex;align-items:center;gap:6px;padding:5px 12px;
  background:rgba(255,255,255,.04);border:1px solid var(--border);border-radius:6px;
  font-size:.73rem;font-family:var(--mono);color:var(--muted);transition:border-color .2s,color .2s;
}
.meta-badge:hover{border-color:var(--border-h);color:var(--text)}
.dot{width:5px;height:5px;border-radius:50%;background:var(--success);box-shadow:0 0 6px var(--success);animation:pulse-dot 2s ease-in-out infinite}
.divider{height:1px;background:var(--border);margin:26px 0;position:relative}
.divider::after{content:'';position:absolute;left:0;top:0;width:60px;height:1px;background:var(--red);box-shadow:0 0 8px var(--red)}
.version-panel{display:grid;grid-template-columns:1fr auto 1fr;gap:16px;align-items:center;margin-bottom:26px}
.ver-box{background:rgba(255,255,255,.03);border:1px solid var(--border);border-radius:12px;padding:16px 18px;text-align:center}
.ver-box.highlight-new {border-color:rgba(52,211,153,.3);background:rgba(52,211,153,.05)}
.ver-box.highlight-same{border-color:rgba(96,165,250,.2);background:rgba(96,165,250,.04)}
.ver-box.highlight-old {border-color:rgba(229,9,20,.25);background:rgba(229,9,20,.04)}
.ver-label{font-size:.65rem;font-weight:700;letter-spacing:2.5px;text-transform:uppercase;color:var(--muted);margin-bottom:8px}
.ver-number{font-family:var(--mono);font-size:1.3rem;font-weight:600}
.ver-number.green{color:var(--success)}.ver-number.blue{color:var(--info)}
.ver-number.red{color:var(--error)}.ver-number.muted{color:var(--muted)}
.ver-sub{font-size:.7rem;color:var(--muted);margin-top:4px}
.ver-arrow{display:flex;flex-direction:column;align-items:center;gap:4px}
.ver-arrow svg{opacity:.4}
.ver-status-badge{font-size:.65rem;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;padding:3px 10px;border-radius:20px}
.badge-new {background:rgba(52,211,153,.15);color:var(--success);border:1px solid rgba(52,211,153,.3)}
.badge-same{background:rgba(96,165,250,.12);color:var(--info);border:1px solid rgba(96,165,250,.25)}
.badge-fail{background:rgba(251,191,36,.12);color:var(--warn);border:1px solid rgba(251,191,36,.25)}
.banner{display:flex;align-items:flex-start;gap:14px;padding:16px 18px;border-radius:12px;margin-bottom:22px}
.banner-update{background:rgba(52,211,153,.07);border:1px solid rgba(52,211,153,.2)}
.banner-same{background:rgba(96,165,250,.06);border:1px solid rgba(96,165,250,.18)}
.banner-err{background:rgba(251,191,36,.06);border:1px solid rgba(251,191,36,.2)}
.banner-icon{font-size:1.4rem;flex-shrink:0;margin-top:1px}
.banner-title{font-size:.95rem;font-weight:700;margin-bottom:3px}
.banner-title.green{color:var(--success)}.banner-title.blue{color:var(--info)}.banner-title.warn{color:var(--warn)}
.banner-desc{color:var(--muted);font-size:.8rem;line-height:1.5}
.wn-wrap{border:1px solid rgba(167,139,250,.2);border-radius:16px;overflow:hidden;background:rgba(167,139,250,.03);margin-bottom:22px}
.wn-head{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;padding:14px 18px 0;border-bottom:1px solid rgba(255,255,255,.05)}
.wn-head-left{display:flex;align-items:center;gap:9px}
.wn-icon{width:30px;height:30px;background:linear-gradient(135deg,#6d28d9,#a78bfa);border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:15px;box-shadow:0 0 14px rgba(167,139,250,.3);flex-shrink:0}
.wn-title{font-size:.82rem;font-weight:700;color:var(--purple)}
.wn-tabs{display:flex;gap:2px;overflow-x:auto;scrollbar-width:none;padding-bottom:0}
.wn-tabs::-webkit-scrollbar{display:none}
.wn-tab{display:inline-flex;align-items:center;gap:5px;padding:7px 15px;border-radius:8px 8px 0 0;font-size:.74rem;font-family:var(--mono);font-weight:600;cursor:pointer;border:1px solid transparent;border-bottom:none;background:transparent;color:var(--muted);transition:all .2s;white-space:nowrap;position:relative;bottom:-1px}
.wn-tab:hover{color:var(--text);background:rgba(255,255,255,.05)}
.wn-tab.active{color:var(--purple);background:rgba(167,139,250,.08);border-color:rgba(167,139,250,.22);border-bottom-color:#13131e}
.tab-badge{background:rgba(167,139,250,.18);color:var(--purple);padding:1px 6px;border-radius:10px;font-size:.62rem}
.wn-tab.active .tab-badge{background:rgba(167,139,250,.3)}
.wn-panels{padding:18px 18px 14px}
.wn-panel{display:none}
.wn-panel.active{display:block;animation:fadeUp .2s ease}
.wn-item{display:flex;align-items:flex-start;gap:10px;padding:8px 0;border-bottom:1px solid rgba(255,255,255,.04)}
.wn-item:last-child{border-bottom:none;padding-bottom:0}
.wn-ico{width:24px;height:24px;border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:.78rem;flex-shrink:0;margin-top:1px}
.wn-ico.feature{background:rgba(52,211,153,.1);color:var(--success)}
.wn-ico.fix{background:rgba(248,113,113,.1);color:var(--error)}
.wn-ico.change{background:rgba(251,191,36,.1);color:var(--warn)}
.wn-ico.text{background:rgba(96,165,250,.08);color:var(--info)}
.wn-text{font-size:.83rem;color:rgba(232,232,240,.85);line-height:1.55;flex:1}
.wn-lbl{font-size:.6rem;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;padding:2px 8px;border-radius:4px;white-space:nowrap;align-self:flex-start;margin-top:3px}
.wn-lbl.feature{background:rgba(52,211,153,.1);color:var(--success)}
.wn-lbl.fix{background:rgba(248,113,113,.1);color:var(--error)}
.wn-lbl.change{background:rgba(251,191,36,.1);color:var(--warn)}
.wn-sec{font-size:.67rem;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:var(--muted);padding:2px 0 10px;display:flex;align-items:center;gap:8px}
.wn-sec:not(:first-child){margin-top:12px}
.wn-sec::after{content:'';flex:1;height:1px;background:rgba(255,255,255,.05)}
.wn-empty{text-align:center;padding:24px 0;color:var(--muted);font-size:.82rem}
.wn-empty span{font-size:1.8rem;display:block;margin-bottom:6px;opacity:.4}
.wn-legend{display:flex;gap:14px;flex-wrap:wrap;padding:10px 18px 12px;border-top:1px solid rgba(255,255,255,.04);background:rgba(0,0,0,.12)}
.wn-leg{display:flex;align-items:center;gap:5px;font-size:.68rem;color:var(--muted)}
.wn-leg-dot{width:7px;height:7px;border-radius:2px}
/* ── محدد قناة التحديث ── */
.ch-wrap{margin-bottom:26px}
.ch-head{font-size:.67rem;font-weight:700;letter-spacing:2.5px;text-transform:uppercase;color:var(--muted);margin-bottom:12px;display:flex;align-items:center;gap:8px}
.ch-head::after{content:'';flex:1;height:1px;background:rgba(255,255,255,.06)}
.ch-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.ch-card{position:relative;display:block;cursor:pointer;background:rgba(255,255,255,.025);border:1px solid var(--border);border-radius:14px;padding:16px 16px 15px;transition:all .28s cubic-bezier(.16,1,.3,1);overflow:hidden}
.ch-card::before{content:'';position:absolute;inset:0;opacity:0;transition:opacity .3s;background:radial-gradient(circle at 100% 0%,var(--ch-c,#34d399) 0%,transparent 65%);}
.ch-card:hover{border-color:var(--border-h);transform:translateY(-2px)}
.ch-card:hover::before{opacity:.07}
.ch-card.on{border-color:var(--ch-c);background:color-mix(in srgb,var(--ch-c) 7%,transparent);box-shadow:0 0 0 1px var(--ch-c),0 8px 30px -12px var(--ch-c)}
.ch-card.on::before{opacity:.1}
.ch-card input{position:absolute;opacity:0;pointer-events:none}
.ch-top{display:flex;align-items:center;gap:9px;margin-bottom:8px;position:relative;z-index:1}
.ch-ico{width:32px;height:32px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:16px;background:color-mix(in srgb,var(--ch-c) 14%,transparent);border:1px solid color-mix(in srgb,var(--ch-c) 30%,transparent);flex-shrink:0}
.ch-name{font-size:.86rem;font-weight:700;line-height:1.2}
.ch-tag{font-family:var(--mono);font-size:.58rem;font-weight:600;letter-spacing:1.5px;color:var(--ch-c);margin-top:2px}
.ch-desc{font-size:.72rem;color:var(--muted);line-height:1.55;position:relative;z-index:1}
.ch-check{position:absolute;top:14px;left:14px;width:18px;height:18px;border-radius:50%;border:1.5px solid var(--border-h);display:flex;align-items:center;justify-content:center;transition:all .25s;z-index:2}
.ch-card.on .ch-check{border-color:var(--ch-c);background:var(--ch-c);box-shadow:0 0 10px color-mix(in srgb,var(--ch-c) 60%,transparent)}
.ch-check svg{opacity:0;transform:scale(.5);transition:all .25s;color:#0a0a0f}
.ch-card.on .ch-check svg{opacity:1;transform:scale(1)}
.ch-live{position:absolute;top:0;right:0;left:0;height:2px;background:var(--ch-c);transform:scaleX(0);transform-origin:right;transition:transform .35s cubic-bezier(.16,1,.3,1)}
.ch-card.on .ch-live{transform:scaleX(1)}
.btn-row{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
.btn{display:inline-flex;align-items:center;gap:8px;padding:12px 26px;border:none;border-radius:10px;font-family:var(--sans);font-size:.9rem;font-weight:700;cursor:pointer;text-decoration:none;letter-spacing:.3px;transition:all .25s cubic-bezier(.16,1,.3,1);position:relative;overflow:hidden}
.btn::after{content:'';position:absolute;inset:0;background:linear-gradient(135deg,rgba(255,255,255,.1) 0%,transparent 50%);opacity:0;transition:opacity .2s}
.btn:hover::after{opacity:1}
.btn-primary{background:var(--red);color:#fff;box-shadow:0 4px 20px rgba(229,9,20,.4)}
.btn-primary:hover{background:#f50c1c;box-shadow:0 6px 28px rgba(229,9,20,.55);transform:translateY(-1px)}
.btn-primary:active{transform:translateY(0)}
.btn-ghost{background:rgba(255,255,255,.05);color:var(--muted);border:1px solid var(--border)}
.btn-ghost:hover{background:rgba(255,255,255,.09);color:var(--text);border-color:var(--border-h)}
.btn-sm{padding:8px 16px;font-size:.8rem}
.btn-force{background:rgba(251,191,36,.08);color:var(--warn);border:1px solid rgba(251,191,36,.3)}
.btn-force:hover{background:rgba(251,191,36,.16);border-color:rgba(251,191,36,.55);color:#fde68a;transform:translateY(-1px)}
.force-note{font-size:.7rem;color:var(--muted);line-height:1.5;margin-top:10px;display:flex;align-items:flex-start;gap:7px}
.force-note b{color:var(--warn);font-weight:700}
.force-banner{background:rgba(251,191,36,.07);border:1px solid rgba(251,191,36,.25)}
.log-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px}
.log-title{font-size:.68rem;font-weight:700;letter-spacing:3px;text-transform:uppercase;color:var(--muted);display:flex;align-items:center;gap:8px}
.live-dot{width:6px;height:6px;background:var(--red);border-radius:50%;box-shadow:0 0 8px var(--red);animation:pulse-dot 1s ease-in-out infinite}
.log-box{background:#080810;border:1px solid var(--border);border-radius:12px;padding:16px;max-height:380px;overflow-y:auto;font-family:var(--mono);scrollbar-width:thin;scrollbar-color:rgba(229,9,20,.3) transparent}
.log-box::-webkit-scrollbar{width:4px}
.log-box::-webkit-scrollbar-thumb{background:rgba(229,9,20,.3);border-radius:2px}
.log-line{display:flex;align-items:flex-start;gap:10px;padding:6px 10px;margin-bottom:3px;border-left:2px solid;border-radius:4px;font-size:.76rem;line-height:1.5;animation:slideIn .2s ease both}
.log-icon{flex-shrink:0;font-size:.82rem;margin-top:1px}
.log-text{color:rgba(232,232,240,.8);word-break:break-all}
.stats-bar{display:flex;gap:1px;margin-top:14px;border-radius:8px;overflow:hidden;height:4px;background:rgba(255,255,255,.06)}
.stat-seg{height:100%;transition:width .5s ease}
.stat-seg.ok{background:var(--success)}.stat-seg.err{background:var(--error)}.stat-seg.skip{background:var(--warn)}
.stats-nums{display:flex;gap:18px;margin-top:12px}
.stat-item{display:flex;align-items:center;gap:5px;font-size:.78rem}
.stat-item .num{font-family:var(--mono);font-weight:600}
.stat-item .lbl{color:var(--muted);font-size:.7rem}
.footer{text-align:center;margin-top:26px;color:var(--muted);font-size:.72rem;letter-spacing:1px;animation:fadeUp .7s .4s both}
.footer a{color:rgba(229,9,20,.7);text-decoration:none;transition:color .2s}
.footer a:hover{color:var(--red)}
@keyframes fadeDown{from{opacity:0;transform:translateY(-20px)}to{opacity:1;transform:translateY(0)}}
@keyframes fadeUp{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}
@keyframes slideIn{from{opacity:0;transform:translateX(-8px)}to{opacity:1;transform:translateX(0)}}
@keyframes shimmer{0%{left:-100%}50%{left:160%}100%{left:160%}}
@keyframes pulse-dot{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.5;transform:scale(.8)}}
@media(max-width:560px){
  .card{padding:20px 14px}
  .version-panel{grid-template-columns:1fr}
  .ver-arrow{flex-direction:row;justify-content:center}
  .ver-arrow svg{transform:rotate(90deg)}
  .btn-row{flex-direction:column}
  .btn{width:100%;justify-content:center}
  .wn-head{flex-direction:column;align-items:flex-start}
  .ch-grid{grid-template-columns:1fr}
}
</style>
</head>
<body>
<div class="wrapper">

  <div class="header">
    <div class="logo-mark">
      <div class="logo-icon">📡</div>
      <div class="logo-text">IPTV <span>UPDATE</span></div>
    </div>
    <h1>نظام التحديث الذكي</h1>
    <p class="subtitle">يقارن الإصدار المثبّت مع خادم التوزيع ويحدّث فقط عند الحاجة</p>
  </div>

  <div class="card">

    <div class="meta-row">
      <div class="meta-badge"><div class="dot"></div> خادم التوزيع متصل</div>
      <div class="meta-badge" style="color:<?= $CH['color'] ?>"><?= $CH['icon'] ?> <?= $CH['tag'] ?></div>
      <div class="meta-badge">📁 <?= htmlspecialchars(basename(SITE_DIR)) ?></div>
      <div class="meta-badge">PHP <?= PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION ?></div>
    </div>

    <div class="divider"></div>

    <?php if (!$do_update): ?>

    <div class="ch-wrap">
      <div class="ch-head">قناة التحديث</div>
      <form method="get" id="chForm">
      <div class="ch-grid">
        <?php foreach ($CHANNELS as $k => $c): ?>
        <label class="ch-card <?= $channel===$k?'on':'' ?>" style="--ch-c:<?= $c['color'] ?>">
          <span class="ch-live"></span>
          <input type="radio" name="channel" value="<?= $k ?>" <?= $channel===$k?'checked':'' ?>
                 onchange="document.getElementById('chForm').submit()">
          <span class="ch-check">
            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="4"><polyline points="20 6 9 17 4 12"/></svg>
          </span>
          <span class="ch-top">
            <span class="ch-ico"><?= $c['icon'] ?></span>
            <span>
              <span class="ch-name" style="display:block"><?= $c['name'] ?></span>
              <span class="ch-tag" style="display:block"><?= $c['tag'] ?></span>
            </span>
          </span>
          <span class="ch-desc"><?= $c['desc'] ?></span>
        </label>
        <?php endforeach; ?>
      </div>
      </form>
    </div>

    <div class="version-panel">
      <div class="ver-box <?= $up_to_date?'highlight-same':($has_update?'highlight-old':'') ?>">
        <div class="ver-label">الإصدار الحالي</div>
        <div class="ver-number <?= $up_to_date?'blue':($has_update?'red':'muted') ?>">v<?= htmlspecialchars($local_ver) ?></div>
        <div class="ver-sub">على السيرفر</div>
      </div>
      <div class="ver-arrow">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>
        </svg>
        <?php if ($remote_ver===false): ?>
          <span class="ver-status-badge badge-fail">خطأ</span>
        <?php elseif ($has_update): ?>
          <span class="ver-status-badge badge-new">جديد ↑</span>
        <?php else: ?>
          <span class="ver-status-badge badge-same">محدّث</span>
        <?php endif; ?>
      </div>
      <div class="ver-box <?= $has_update?'highlight-new':($up_to_date?'highlight-same':'') ?>">
        <div class="ver-label">أحدث إصدار</div>
        <div class="ver-number <?= $has_update?'green':($up_to_date?'blue':'muted') ?>">
          <?= $remote_ver!==false?'v'.htmlspecialchars($remote_ver):'—' ?>
        </div>
        <div class="ver-sub">على قناة <?= $CH['tag'] ?></div>
      </div>
    </div>

    <?php if ($remote_ver===false): ?>
      <div class="banner banner-err">
        <div class="banner-icon">⚠️</div>
        <div>
          <div class="banner-title warn">تعذّر الاتصال بخادم التحديث</div>
          <div class="banner-desc">تعذّر جلب معلومات الإصدار من القناة المختارة. تحقق من الاتصال بالإنترنت.</div>
        </div>
      </div>
      <div class="btn-row" style="margin-bottom:20px">
        <a class="btn btn-ghost btn-sm" href="update.php?channel=<?= htmlspecialchars($channel) ?>">إعادة الفحص</a>
        <form method="post" onsubmit="return confirm('سيتم إعادة تثبيت جميع ملفات القناة الحالية. هل تريد المتابعة؟');" style="display:inline">
          <?= csrfField() ?>
          <input type="hidden" name="do_update" value="1">
          <input type="hidden" name="force" value="1">
          <input type="hidden" name="channel" value="<?= htmlspecialchars($channel) ?>">
          <button class="btn btn-force btn-sm" type="submit">تحديث إجباري رغم الخطأ</button>
        </form>
      </div>

    <?php elseif ($up_to_date): ?>
      <div class="banner banner-same">
        <div class="banner-icon">✅</div>
        <div>
          <div class="banner-title blue">الموقع محدّث بالكامل</div>
          <div class="banner-desc">الإصدار المثبت <strong style="color:var(--info)">v<?= htmlspecialchars($local_ver) ?></strong> هو أحدث إصدار متاح.</div>
        </div>
      </div>
      <div class="btn-row" style="margin-bottom:8px">
        <a class="btn btn-ghost btn-sm" href="update.php?channel=<?= htmlspecialchars($channel) ?>">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3.5"/></svg>
          إعادة الفحص
        </a>
        <form method="post" onsubmit="return confirm('سيتم إعادة تثبيت جميع ملفات القناة الحالية والكتابة فوق الملفات الموجودة. هل تريد المتابعة؟');" style="display:inline">
          <?= csrfField() ?>
          <input type="hidden" name="do_update" value="1">
          <input type="hidden" name="force" value="1">
          <input type="hidden" name="channel" value="<?= htmlspecialchars($channel) ?>">
          <button class="btn btn-force btn-sm" type="submit">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 9v4"/><path d="M12 17h.01"/><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/></svg>
          تحديث إجباري
          </button>
        </form>
      </div>
      <div class="force-note" style="margin-bottom:20px">
        <span>⚠️</span>
        <span><b>التحديث الإجباري</b> يعيد تنزيل وتثبيت كامل ملفات القناة الحالية حتى لو كان الإصدار محدّثاً — استخدمه فقط عند تلف الملفات أو فشل تحديث سابق.</span>
      </div>

    <?php else: ?>
      <div class="banner banner-update">
        <div class="banner-icon">🚀</div>
        <div>
          <div class="banner-title green">تحديث جديد متاح!</div>
          <div class="banner-desc">
            الترقية من <strong style="color:var(--error)">v<?= htmlspecialchars($local_ver) ?></strong>
            إلى <strong style="color:var(--success)">v<?= htmlspecialchars($remote_ver) ?></strong>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <?php
    if (!empty($whatsnew)):
        $versions = array_keys($whatsnew);
        $type_icons  = ['feature'=>'✨','fix'=>'🐛','change'=>'🔧','text'=>'▸'];
        $type_labels = ['feature'=>'ميزة','fix'=>'إصلاح','change'=>'تحديث'];
    ?>
    <div class="wn-wrap">
      <div class="wn-head">
        <div class="wn-head-left">
          <div class="wn-icon">✨</div>
          <span class="wn-title">ما الجديد في هذا التحديث</span>
        </div>
        <div class="wn-tabs" id="wnTabs">
          <?php foreach ($versions as $i => $ver):
            $count = count(array_filter($whatsnew[$ver], function($x) { return !in_array($x['type'],['title','text']); }));
          ?>
          <button class="wn-tab <?= $i===0?'active':'' ?>"
                  onclick="wnSwitch(this,'wnp<?= $i ?>')">
            v<?= htmlspecialchars($ver) ?>
            <?php if($count>0): ?><span class="tab-badge"><?= $count ?></span><?php endif; ?>
          </button>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="wn-panels">
        <?php foreach ($versions as $i => $ver):
          $items    = $whatsnew[$ver];
          $features = array_values(array_filter($items, function($x) { return $x['type']==='feature'; }));
          $fixes    = array_values(array_filter($items, function($x) { return $x['type']==='fix'; }));
          $changes  = array_values(array_filter($items, function($x) { return $x['type']==='change'; }));
          $texts    = array_values(array_filter($items, function($x) { return $x['type']==='text'; }));
          $titles   = array_values(array_filter($items, function($x) { return $x['type']==='title'; }));
          $has_sections = !empty($features) || !empty($fixes) || !empty($changes);
        ?>
        <div class="wn-panel <?= $i===0?'active':'' ?>" id="wnp<?= $i ?>">
          <?php foreach($titles as $t): ?>
          <div class="wn-sec" style="color:var(--purple)">📋 <?= htmlspecialchars($t['text']) ?></div>
          <?php endforeach; ?>
          <?php if (!empty($features)): ?>
            <?php if($has_sections && (count($features)<count($items))): ?><div class="wn-sec">مميزات جديدة</div><?php endif; ?>
            <?php foreach($features as $it): ?>
            <div class="wn-item">
              <div class="wn-ico feature">✨</div>
              <div class="wn-text"><?= htmlspecialchars($it['text']) ?></div>
              <span class="wn-lbl feature">ميزة</span>
            </div>
            <?php endforeach; ?>
          <?php endif; ?>
          <?php if (!empty($fixes)): ?>
            <?php if($has_sections && !empty($features)): ?><div class="wn-sec">إصلاحات</div><?php endif; ?>
            <?php foreach($fixes as $it): ?>
            <div class="wn-item">
              <div class="wn-ico fix">🐛</div>
              <div class="wn-text"><?= htmlspecialchars($it['text']) ?></div>
              <span class="wn-lbl fix">إصلاح</span>
            </div>
            <?php endforeach; ?>
          <?php endif; ?>
          <?php if (!empty($changes)): ?>
            <?php if($has_sections && (!empty($features)||!empty($fixes))): ?><div class="wn-sec">تحسينات</div><?php endif; ?>
            <?php foreach($changes as $it): ?>
            <div class="wn-item">
              <div class="wn-ico change">🔧</div>
              <div class="wn-text"><?= htmlspecialchars($it['text']) ?></div>
              <span class="wn-lbl change">تحديث</span>
            </div>
            <?php endforeach; ?>
          <?php endif; ?>
          <?php foreach($texts as $it): ?>
          <div class="wn-item">
            <div class="wn-ico text">▸</div>
            <div class="wn-text"><?= htmlspecialchars($it['text']) ?></div>
          </div>
          <?php endforeach; ?>
          <?php if(empty($items)): ?>
          <div class="wn-empty"><span>📋</span>لا تفاصيل لهذا الإصدار</div>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
      <div class="wn-legend">
        <div class="wn-leg"><div class="wn-leg-dot" style="background:var(--success)"></div> ميزة جديدة</div>
        <div class="wn-leg"><div class="wn-leg-dot" style="background:var(--error)"></div> إصلاح خطأ</div>
        <div class="wn-leg"><div class="wn-leg-dot" style="background:var(--warn)"></div> تحسين</div>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($has_update): ?>
    <div class="btn-row">
      <form method="post" style="display:inline">
        <?= csrfField() ?>
        <input type="hidden" name="do_update" value="1">
        <input type="hidden" name="channel" value="<?= htmlspecialchars($channel) ?>">
        <button class="btn btn-primary" type="submit">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="8 17 12 21 16 17"/><line x1="12" y1="21" x2="12" y2="3"/></svg>
          ابدأ التحديث · <?= $CH['tag'] ?> &nbsp; v<?= htmlspecialchars($local_ver) ?> → v<?= htmlspecialchars($remote_ver) ?>
        </button>
      </form>
      <form method="post" onsubmit="return confirm('سيتم إعادة تثبيت جميع ملفات القناة الحالية والكتابة فوق الملفات الموجودة. هل تريد المتابعة؟');" style="display:inline">
        <?= csrfField() ?>
        <input type="hidden" name="do_update" value="1">
        <input type="hidden" name="force" value="1">
        <input type="hidden" name="channel" value="<?= htmlspecialchars($channel) ?>">
        <button class="btn btn-force btn-sm" type="submit">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 9v4"/><path d="M12 17h.01"/><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/></svg>
          إجباري
        </button>
      </form>
    </div>
    <?php endif; ?>

    <?php else: ?>

      <div class="log-header">
        <div class="log-title"><div class="live-dot"></div> <?= $force ? "سجل التحديث الإجباري" : "سجل العمليات" ?></div>
        <div style="font-size:.7rem;color:var(--muted);font-family:var(--mono)"><?= date('H:i:s') ?></div>
      </div>

      <div class="log-box" id="logBox">
      <?php
        if ($force) msg("وضع التحديث الإجباري مُفعّل — سيتم استبدال كل الملفات","warn");
        msg("جاري تنزيل حزمة التحديث من قناة {$CH['name']}...","info");
        $zip_path = download_zip(ZIP_URL);
        if (!$zip_path) {
            msg("فشل تنزيل ZIP — تحقق من الاتصال","error");
        } else {
            $size = round(filesize($zip_path)/1024,1);
            msg("تم تنزيل الملف ({$size} KB) — جاري فك الضغط...","success");
            $extract_dir = sys_get_temp_dir().'/gh_'.time();
            mkdir($extract_dir,0755,true);
            $unzip = unzip_manual($zip_path,$extract_dir);
            unlink($zip_path);
            if (empty($unzip['ok'])) {
                msg("فشل فك الضغط — تأكد من تفعيل zlib","error");
            } else {
                msg("تم فك ضغط ".count($unzip['ok'])." ملف — جاري النسخ...","success");
                $sub = glob($extract_dir.'/*',GLOB_ONLYDIR);
                $src = !empty($sub)?$sub[0]:$extract_dir;
                [$done,$failed,$skipped] = copy_to_site($src);
                rrmdir($extract_dir);
                $new_ver = get_remote_version()?:$local_ver;
                @file_put_contents(VERSION_LOCAL, $new_ver);
                msg($force ? "اكتملت إعادة التثبيت الإجبارية! الإصدار: v{$new_ver}" : "اكتمل التحديث! الإصدار الآن: v{$new_ver}","success");
                $total=$done+$failed+$skipped;
                $pok  =$total>0?round($done/$total*100):0;
                $perr =$total>0?round($failed/$total*100):0;
                $pskip=$total>0?round($skipped/$total*100):0;
      ?>
      </div>
      <div class="stats-bar">
        <div class="stat-seg ok"   style="width:<?= $pok ?>%"></div>
        <div class="stat-seg err"  style="width:<?= $perr ?>%"></div>
        <div class="stat-seg skip" style="width:<?= $pskip ?>%"></div>
      </div>
      <div class="stats-nums">
        <div class="stat-item">
          <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="#34d399" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
          <span class="num" style="color:var(--success)"><?= $done ?></span><span class="lbl">محدّث</span>
        </div>
        <?php if($failed>0): ?>
        <div class="stat-item">
          <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="#f87171" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
          <span class="num" style="color:var(--error)"><?= $failed ?></span><span class="lbl">فشل</span>
        </div>
        <?php endif; ?>
        <div class="stat-item">
          <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="#fbbf24" stroke-width="2.5"><path d="M5 12h14"/></svg>
          <span class="num" style="color:var(--warn)"><?= $skipped ?></span><span class="lbl">تخطى</span>
        </div>
        <div class="stat-item" style="margin-right:auto">
          <span class="num" style="color:var(--info);font-family:var(--mono)">v<?= htmlspecialchars($local_ver) ?></span>
          <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="var(--muted)" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
          <span class="num" style="color:var(--success);font-family:var(--mono)">v<?= htmlspecialchars($new_ver) ?></span>
        </div>
      </div>
      <div class="divider" style="margin:18px 0"></div>
      <div class="btn-row">
        <a class="btn btn-primary" href="update.php?channel=<?= htmlspecialchars($channel) ?>">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3.5"/></svg>
          فحص التحديثات مجدداً
        </a>
      </div>
      <?php
            }
        }
        if (!$zip_path||empty($unzip['ok']??[])):
      ?>
      </div>
      <div class="divider" style="margin:16px 0"></div>
      <div class="btn-row">
        <a class="btn btn-primary" href="update.php?channel=<?= htmlspecialchars($channel) ?>">إعادة المحاولة</a>
      </div>
      <?php endif; ?>

    <?php endif; ?>

  </div>

  <div class="footer">IPTV Updater &nbsp;·&nbsp; Secure Distribution Network</div>

</div>
<script>
// Forms are rendered in several branches of the updater UI; attach the same
// server-issued token to each POST form so every update request is protected.
document.querySelectorAll('form[method="post"]').forEach(function (form) {
  var token = document.createElement('input');
  token.type = 'hidden';
  token.name = 'csrf_token';
  token.value = <?= json_encode(csrfToken(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  form.appendChild(token);
});
function wnSwitch(btn, pid) {
  document.querySelectorAll('.wn-tab').forEach(t=>t.classList.remove('active'));
  document.querySelectorAll('.wn-panel').forEach(p=>p.classList.remove('active'));
  btn.classList.add('active');
  const p=document.getElementById(pid);
  if(p) p.classList.add('active');
}
const log=document.getElementById('logBox');
if(log){
  new MutationObserver(()=>log.scrollTop=log.scrollHeight).observe(log,{childList:true,subtree:true});
  log.scrollTop=log.scrollHeight;
}
</script>
</body>
</html>
