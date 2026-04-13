<?php
// ============================================================
//  SHASHITY GLOBAL CACHE — Professional Server Dashboard
//  Version 2.0 | Full Edition
// ============================================================

session_start();

// ─── SECURITY: Basic token protection (optional) ───
// Uncomment and set your password hash to protect the dashboard:
// define('DASH_PASS', 'your_password_hash_here');
// if (!isset($_SESSION['auth']) && (!isset($_POST['pass']) || !password_verify($_POST['pass'], DASH_PASS))) {
//     // Show login form... }

// ─── CLEAR CACHE ACTION ───
$clearResult = null;
if (isset($_POST['clear_cache'])) {
    $cacheDir = '/var/cache/nginx/smart';
    if (is_dir($cacheDir)) {
        exec("find " . escapeshellarg($cacheDir) . " -type f -delete 2>&1", $out, $ret);
        $clearResult = ($ret === 0)
            ? ['status' => 'ok',  'msg' => 'تم تفريغ الكاش بنجاح', 'detail' => 'جميع ملفات الكاش تم حذفها']
            : ['status' => 'err', 'msg' => 'فشل تفريغ الكاش',       'detail' => implode(' ', $out)];
    } else {
        $clearResult = ['status' => 'warn', 'msg' => 'مجلد الكاش غير موجود', 'detail' => $cacheDir];
    }
}

// ─── RELOAD NGINX ACTION ───
$reloadResult = null;
if (isset($_POST['reload_nginx'])) {
    exec('systemctl reload nginx 2>&1', $rout, $rret);
    $reloadResult = ($rret === 0)
        ? ['status' => 'ok',  'msg' => 'تم إعادة تحميل Nginx']
        : ['status' => 'err', 'msg' => 'فشل إعادة التحميل: ' . implode(' ', $rout)];
}

// ─── DATA FUNCTIONS ───

function getDiskUsage(): array {
    $output = [];
    exec('df -h 2>/dev/null', $output);
    $disks = [];
    foreach ($output as $i => $line) {
        if ($i === 0) continue;
        $parts = preg_split('/\s+/', trim($line));
        if (count($parts) >= 6) {
            $fs      = $parts[0];
            $mount   = $parts[5];
            $skip    = ['tmpfs','devtmpfs','udev','none','overlay','shm'];
            if (in_array($fs, $skip) && $mount !== '/') continue;
            if (strpos($mount, '/snap') !== false) continue;
            $disks[] = [
                'fs'      => $fs,
                'size'    => $parts[1],
                'used'    => $parts[2],
                'avail'   => $parts[3],
                'percent' => intval(str_replace('%', '', $parts[4])),
                'mount'   => $mount,
            ];
        }
    }
    return $disks;
}

function getNginxLog(int $lines = 25): array {
    $logFile = '/var/log/nginx/access.log';
    if (!file_exists($logFile)) return [];
    $output = [];
    exec("tail -n $lines " . escapeshellarg($logFile) . " 2>/dev/null", $output);
    return $output;
}

function getNginxStatus(): string {
    exec('systemctl is-active nginx 2>/dev/null', $out);
    return trim($out[0] ?? 'unknown');
}

function getNginxVersion(): string {
    exec('nginx -v 2>&1', $out);
    preg_match('/nginx\/([\d.]+)/', implode('', $out), $m);
    return $m[1] ?? '—';
}

function getCacheInfo(): array {
    $cacheDir = '/var/cache/nginx/smart';
    if (!is_dir($cacheDir)) return ['exists' => false, 'files' => 0, 'size' => 0];
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($cacheDir, FilesystemIterator::SKIP_DOTS));
    $count = 0; $size = 0;
    foreach ($files as $f) { $count++; $size += $f->getSize(); }
    return ['exists' => true, 'files' => $count, 'size' => round($size / 1024 / 1024, 2)];
}

function getUptime(): string {
    exec('uptime -p 2>/dev/null', $out);
    return $out[0] ?? '—';
}

function getUptimeSeconds(): int {
    if (!file_exists('/proc/uptime')) return 0;
    return (int)explode(' ', file_get_contents('/proc/uptime'))[0];
}

function getLoadAvg(): array {
    return sys_getloadavg() ?: [0, 0, 0];
}

function getMemory(): array {
    exec('free -b 2>/dev/null', $out);
    if (isset($out[1])) {
        $parts = preg_split('/\s+/', trim($out[1]));
        $total = (int)($parts[1] ?? 0);
        $used  = (int)($parts[2] ?? 0);
        $free  = (int)($parts[3] ?? 0);
        $pct   = $total > 0 ? round(($used / $total) * 100) : 0;
        $fmt   = fn($b) => $b >= 1073741824
            ? round($b/1073741824,1).'G'
            : round($b/1048576).'M';
        return [
            'total'   => $fmt($total),
            'used'    => $fmt($used),
            'free'    => $fmt($free),
            'percent' => $pct,
        ];
    }
    return ['total'=>'—','used'=>'—','free'=>'—','percent'=>0];
}

function getCpuPercent(): int {
    // Sample over 0.5s for accuracy
    $s1 = file_get_contents('/proc/stat');
    usleep(500000);
    $s2 = file_get_contents('/proc/stat');
    $p  = fn($s) => array_map('intval', array_slice(preg_split('/\s+/', trim(strtok($s, "\n"))), 1));
    $c1 = $p($s1); $c2 = $p($s2);
    $idle1 = $c1[3]; $idle2 = $c2[3];
    $total1 = array_sum($c1); $total2 = array_sum($c2);
    $dt = $total2 - $total1;
    return $dt > 0 ? (int)round((($dt - ($idle2 - $idle1)) / $dt) * 100) : 0;
}

function getTopProcesses(int $n = 5): array {
    exec("ps aux --sort=-%cpu 2>/dev/null | awk 'NR>1{printf \"%s %s %s\\n\",\$3,\$4,\$11}' | head -$n", $out);
    $procs = [];
    foreach ($out as $line) {
        $p = preg_split('/\s+/', trim($line), 3);
        if (count($p) === 3) {
            $name = basename($p[2]);
            if (strlen($name) > 28) $name = substr($name, 0, 25) . '...';
            $procs[] = ['cpu' => $p[0], 'mem' => $p[1], 'name' => $name];
        }
    }
    return $procs;
}

function getNetworkStats(): array {
    $ifaces = [];
    if (!file_exists('/proc/net/dev')) return $ifaces;
    $lines = file('/proc/net/dev');
    foreach ($lines as $l) {
        if (strpos($l, ':') === false) continue;
        [$iface, $rest] = explode(':', $l, 2);
        $iface = trim($iface);
        if (in_array($iface, ['lo'])) continue;
        $nums = preg_split('/\s+/', trim($rest));
        $rx = (int)($nums[0] ?? 0);
        $tx = (int)($nums[8] ?? 0);
        $fmt = fn($b) => $b >= 1073741824 ? round($b/1073741824,2).'G'
            : ($b >= 1048576 ? round($b/1048576,1).'M'
            : round($b/1024).'K');
        $ifaces[] = ['name' => $iface, 'rx' => $fmt($rx), 'tx' => $fmt($tx)];
    }
    return $ifaces;
}

function parseLogLine(string $line): ?array {
    if (preg_match('/^(\S+) \S+ \S+ \[(.*?)\] "(\w+) (\S+) \S+" (\d+) (\d+)(?:\s+"([^"]*)")?(?:\s+"([^"]*)")?/', $line, $m)) {
        return [
            'ip'     => $m[1],
            'time'   => $m[2],
            'method' => $m[3],
            'path'   => $m[4],
            'status' => $m[5],
            'size'   => (int)$m[6],
            'ref'    => $m[7] ?? '-',
            'ua'     => $m[8] ?? '-',
        ];
    }
    return null;
}

function formatBytes(int $b): string {
    if ($b >= 1073741824) return round($b/1073741824,2).' GB';
    if ($b >= 1048576)    return round($b/1048576,1).' MB';
    if ($b >= 1024)       return round($b/1024).' KB';
    return $b.' B';
}

// ─── COLLECT DATA ───
$disks       = getDiskUsage();
$nginxLog    = getNginxLog(25);
$nginxStatus = getNginxStatus();
$nginxVer    = getNginxVersion();
$cache       = getCacheInfo();
$uptime      = getUptime();
$uptimeSec   = getUptimeSeconds();
$load        = getLoadAvg();
$mem         = getMemory();
$cpu         = getCpuPercent();
$procs       = getTopProcesses(5);
$network     = getNetworkStats();
$now         = date('Y-m-d H:i:s');
$phpVer      = PHP_VERSION;
$hostname    = gethostname();
$serverIp    = $_SERVER['SERVER_ADDR'] ?? trim(shell_exec('hostname -I 2>/dev/null | awk \'{print $1}\'') ?? '—');

// Parsed log lines
$parsedLog = [];
foreach (array_reverse($nginxLog) as $line) {
    $p = parseLogLine($line);
    $parsedLog[] = $p ?? ['raw' => $line];
}

// Log stats
$logStats = ['2xx'=>0,'3xx'=>0,'4xx'=>0,'5xx'=>0,'bytes'=>0];
foreach ($parsedLog as $p) {
    if (!isset($p['status'])) continue;
    $s = $p['status'][0];
    if (isset($logStats[$s.'xx'])) $logStats[$s.'xx']++;
    $logStats['bytes'] += $p['size'] ?? 0;
}

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SHASHITY Global Cache — لوحة التحكم</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700;900&family=JetBrains+Mono:wght@400;600;700&display=swap" rel="stylesheet">
<style>
/* ══════════════════════════════════════════════
   SHASHITY GLOBAL CACHE — Design System
   ══════════════════════════════════════════════ */
:root {
  --bg:        #080810;
  --bg2:       #0e0e1a;
  --bg3:       #13131f;
  --card:      #111120;
  --card2:     #16162a;
  --border:    rgba(255,255,255,0.06);
  --border-h:  rgba(255,255,255,0.12);

  --accent:    #c084fc;      /* purple glow */
  --accent2:   #818cf8;      /* indigo */
  --accent3:   #38bdf8;      /* sky */
  --gold:      #fbbf24;
  --text:      #e2e8f0;
  --muted:     #64748b;
  --muted2:    #475569;

  --green:     #34d399;
  --red:       #f87171;
  --yellow:    #fbbf24;
  --blue:      #60a5fa;
  --purple:    #c084fc;
  --orange:    #fb923c;

  --radius-sm: 6px;
  --radius:    10px;
  --radius-lg: 14px;
  --radius-xl: 18px;
}

*, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }

html { scroll-behavior: smooth; }

body {
  background: var(--bg);
  color: var(--text);
  font-family: 'Cairo', sans-serif;
  min-height: 100vh;
  overflow-x: hidden;
  line-height: 1.5;
}

/* Subtle grid bg */
body::before {
  content: '';
  position: fixed; inset: 0;
  background-image:
    linear-gradient(rgba(192,132,252,0.025) 1px, transparent 1px),
    linear-gradient(90deg, rgba(192,132,252,0.025) 1px, transparent 1px);
  background-size: 40px 40px;
  pointer-events: none;
  z-index: 0;
}

/* Radial glow top */
body::after {
  content: '';
  position: fixed;
  top: -200px; left: 50%;
  transform: translateX(-50%);
  width: 800px; height: 400px;
  background: radial-gradient(ellipse, rgba(192,132,252,0.06) 0%, transparent 70%);
  pointer-events: none;
  z-index: 0;
}

main, header, footer { position: relative; z-index: 1; }

/* ─── SCROLLBAR ─── */
::-webkit-scrollbar { width: 5px; height: 5px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: rgba(192,132,252,0.2); border-radius: 99px; }
::-webkit-scrollbar-thumb:hover { background: rgba(192,132,252,0.4); }

/* ─── HEADER ─── */
header {
  background: rgba(8,8,16,0.85);
  backdrop-filter: blur(20px);
  -webkit-backdrop-filter: blur(20px);
  border-bottom: 1px solid var(--border);
  padding: 0 2rem;
  height: 64px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  position: sticky; top: 0; z-index: 200;
}

.logo-wrap {
  display: flex; align-items: center; gap: 14px;
}
.logo-mark {
  width: 36px; height: 36px;
  background: linear-gradient(135deg, #c084fc, #818cf8);
  border-radius: var(--radius);
  display: flex; align-items: center; justify-content: center;
  font-size: 18px;
  flex-shrink: 0;
  position: relative;
}
.logo-mark::after {
  content: '';
  position: absolute; inset: -1px;
  border-radius: calc(var(--radius) + 1px);
  background: linear-gradient(135deg, #c084fc, #818cf8);
  z-index: -1;
  opacity: 0.4;
  filter: blur(8px);
}
.logo-name {
  font-size: 1.15rem;
  font-weight: 900;
  letter-spacing: -0.3px;
  line-height: 1.1;
}
.logo-name em {
  font-style: normal;
  background: linear-gradient(90deg, #c084fc, #818cf8);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}
.logo-tagline {
  font-size: 0.6rem;
  color: var(--muted);
  letter-spacing: 2.5px;
  text-transform: uppercase;
}

.header-right { display: flex; align-items: center; gap: 10px; }

.hbadge {
  display: flex; align-items: center; gap: 6px;
  padding: 5px 12px;
  border-radius: var(--radius-sm);
  font-size: 0.72rem;
  font-weight: 700;
  letter-spacing: 0.8px;
  border: 1px solid;
}
.hbadge.active  { background: rgba(52,211,153,0.08); color: var(--green); border-color: rgba(52,211,153,0.2); }
.hbadge.inactive{ background: rgba(248,113,113,0.08); color: var(--red);   border-color: rgba(248,113,113,0.2); }
.hbadge .dot {
  width: 6px; height: 6px;
  border-radius: 50%;
  background: currentColor;
  animation: pulse 2s ease-in-out infinite;
}
@keyframes pulse { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:0.4;transform:scale(0.7)} }

.htime {
  font-family: 'JetBrains Mono', monospace;
  font-size: 0.7rem;
  color: var(--muted);
  background: var(--bg3);
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  padding: 5px 12px;
}

/* ─── REFRESH BAR ─── */
.refresh-bar {
  height: 2px;
  background: var(--border);
  position: relative;
  overflow: hidden;
}
.refresh-bar-fill {
  position: absolute; left: 0; top: 0;
  height: 100%;
  background: linear-gradient(90deg, #c084fc, #818cf8, #38bdf8);
  border-radius: 99px;
  animation: rfill 30s linear forwards;
}
@keyframes rfill { from{width:100%} to{width:0%} }

/* ─── LAYOUT ─── */
.main-wrap {
  max-width: 1440px;
  margin: 0 auto;
  padding: 2rem 2rem 3rem;
}

.g4 { display: grid; grid-template-columns: repeat(4,1fr); gap: 1rem; margin-bottom: 1.5rem; }
.g3 { display: grid; grid-template-columns: repeat(3,1fr); gap: 1rem; margin-bottom: 1.5rem; }
.g2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem; }
.g21{ display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem; }
.g12{ display: grid; grid-template-columns: 1fr 2fr; gap: 1.5rem; margin-bottom: 1.5rem; }
.g1 { margin-bottom: 1.5rem; }

/* ─── SECTION LABEL ─── */
.sec-label {
  font-size: 0.62rem;
  font-weight: 700;
  letter-spacing: 3px;
  text-transform: uppercase;
  color: var(--muted);
  margin-bottom: 0.9rem;
  display: flex;
  align-items: center;
  gap: 10px;
}
.sec-label::before {
  content: '';
  width: 14px; height: 2px;
  background: linear-gradient(90deg, #c084fc, #818cf8);
  border-radius: 99px;
  flex-shrink: 0;
}
.sec-label::after {
  content: '';
  flex: 1; height: 1px;
  background: var(--border);
}

/* ─── CARDS ─── */
.card {
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  padding: 1.3rem 1.4rem;
  position: relative;
  overflow: hidden;
  transition: border-color 0.25s, transform 0.2s;
}
.card:hover {
  border-color: var(--border-h);
  transform: translateY(-1px);
}
.card-top-line::before {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 1px;
  background: linear-gradient(90deg, #c084fc 0%, #818cf8 50%, transparent 100%);
  opacity: 0.6;
}

/* ─── STAT CARDS ─── */
.stat-icon {
  font-size: 1.4rem;
  margin-bottom: 0.7rem;
  display: flex; align-items: center;
}
.stat-label {
  font-size: 0.65rem;
  color: var(--muted);
  text-transform: uppercase;
  letter-spacing: 2px;
  margin-bottom: 5px;
}
.stat-val {
  font-family: 'JetBrains Mono', monospace;
  font-size: 1.75rem;
  font-weight: 700;
  line-height: 1;
  margin-bottom: 5px;
}
.stat-sub {
  font-size: 0.68rem;
  color: var(--muted);
}

/* Ring gauge */
.gauge-wrap {
  display: flex; align-items: center; gap: 1rem;
}
.ring-svg { width: 60px; height: 60px; flex-shrink: 0; }
.ring-track { fill: none; stroke: rgba(255,255,255,0.06); stroke-width: 5; }
.ring-fill  { fill: none; stroke-width: 5; stroke-linecap: round;
  transform: rotate(-90deg); transform-origin: 30px 30px;
  transition: stroke-dashoffset 1.2s cubic-bezier(.4,0,.2,1); }

/* Progress bar */
.pb-track {
  width: 100%; height: 5px;
  background: rgba(255,255,255,0.05);
  border-radius: 99px;
  overflow: hidden;
  margin-top: 8px;
}
.pb-fill {
  height: 100%;
  border-radius: 99px;
  transition: width 1.2s cubic-bezier(.4,0,.2,1);
}
.pb-green  { background: linear-gradient(90deg,#34d399,#059669); }
.pb-yellow { background: linear-gradient(90deg,#fbbf24,#d97706); }
.pb-red    { background: linear-gradient(90deg,#f87171,#dc2626); }
.pb-blue   { background: linear-gradient(90deg,#60a5fa,#2563eb); }
.pb-purple { background: linear-gradient(90deg,#c084fc,#7c3aed); }

/* ─── COLORS ─── */
.c-green  { color: var(--green);  }
.c-red    { color: var(--red);    }
.c-yellow { color: var(--yellow); }
.c-blue   { color: var(--blue);   }
.c-purple { color: var(--purple); }
.c-orange { color: var(--orange); }
.c-muted  { color: var(--muted);  }

/* ─── DISK ─── */
.disk-row {
  padding: 0.85rem 0;
  border-bottom: 1px solid var(--border);
}
.disk-row:last-child { border-bottom: none; padding-bottom: 0; }
.disk-row:first-child { padding-top: 0; }
.disk-row-top {
  display: flex; align-items: center;
  justify-content: space-between;
  margin-bottom: 6px;
}
.disk-mount {
  font-family: 'JetBrains Mono', monospace;
  font-size: 0.83rem;
  font-weight: 700;
  color: var(--blue);
}
.disk-fs { font-size: 0.65rem; color: var(--muted); }
.disk-meta { display: flex; gap: 14px; }
.disk-meta span { font-size: 0.68rem; color: var(--muted); font-family: 'JetBrains Mono', monospace; }
.disk-meta span b { color: var(--text); }
.disk-pct {
  font-family: 'JetBrains Mono', monospace;
  font-size: 0.78rem;
  font-weight: 700;
  padding: 2px 9px;
  border-radius: 5px;
}
.disk-pct.safe  { background:rgba(52,211,153,0.1);  color:var(--green);  }
.disk-pct.warn  { background:rgba(251,191,36,0.1);  color:var(--yellow); }
.disk-pct.crit  { background:rgba(248,113,113,0.1); color:var(--red);    }

/* ─── LOG ─── */
.log-wrap {
  background: rgba(0,0,0,0.3);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  overflow: hidden;
}
.log-topbar {
  background: var(--card);
  padding: 0.75rem 1.2rem;
  display: flex; align-items: center; justify-content: space-between;
  border-bottom: 1px solid var(--border);
  flex-wrap: wrap; gap: 8px;
}
.log-title { font-size: 0.7rem; color: var(--muted); font-family: 'JetBrains Mono', monospace; }
.live-badge {
  display: flex; align-items: center; gap: 5px;
  font-size: 0.62rem; color: var(--green); font-weight: 700; letter-spacing: 1px;
}
.live-badge::before {
  content: '';
  width: 6px; height: 6px;
  border-radius: 50%;
  background: var(--green);
  animation: pulse 1.5s ease-in-out infinite;
}
.log-stats {
  display: flex; gap: 12px; flex-wrap: wrap;
}
.log-stat-pill {
  font-size: 0.62rem;
  font-weight: 700;
  padding: 2px 8px;
  border-radius: 4px;
  font-family: 'JetBrains Mono', monospace;
}
.log-body {
  padding: 0.7rem 1rem;
  max-height: 340px;
  overflow-y: auto;
  font-family: 'JetBrains Mono', monospace;
  font-size: 0.68rem;
  line-height: 2;
}
.ll { display: flex; align-items: center; gap: 10px; padding: 1px 4px; border-radius: 4px; }
.ll:hover { background: rgba(255,255,255,0.03); }
.ll-ip     { color: var(--blue); min-width: 110px; flex-shrink: 0; }
.ll-method { font-weight: 700; min-width: 40px; flex-shrink: 0; }
.ll-GET    { color: var(--green);  }
.ll-POST   { color: var(--yellow); }
.ll-PUT    { color: var(--orange); }
.ll-DELETE { color: var(--red);    }
.ll-HEAD   { color: var(--purple); }
.ll-s2     { color: var(--green);  }
.ll-s3     { color: var(--blue);   }
.ll-s4     { color: var(--yellow); }
.ll-s5     { color: var(--red);    }
.ll-path   { color: var(--muted); flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.ll-size   { color: var(--muted2); font-size:0.62rem; flex-shrink:0; }
.ll-raw    { color: var(--muted); opacity:0.6; }

/* ─── TABLE ─── */
.data-table { width: 100%; border-collapse: collapse; font-size: 0.78rem; }
.data-table th {
  text-align: right;
  font-size: 0.62rem;
  font-weight: 700;
  letter-spacing: 1.5px;
  text-transform: uppercase;
  color: var(--muted);
  padding: 0 0 0.7rem;
  border-bottom: 1px solid var(--border);
}
.data-table td {
  padding: 0.6rem 0;
  border-bottom: 1px solid rgba(255,255,255,0.03);
  vertical-align: middle;
  font-family: 'JetBrains Mono', monospace;
}
.data-table tr:last-child td { border-bottom: none; }
.data-table tr:hover td { background: rgba(192,132,252,0.03); }

/* ─── ACTIONS ─── */
.actions-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.75rem;
}
.action-btn {
  display: flex; align-items: center; justify-content: center; gap: 8px;
  padding: 10px 14px;
  border-radius: var(--radius);
  border: 1px solid;
  font-family: 'Cairo', sans-serif;
  font-size: 0.82rem;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s;
  direction: rtl;
  text-decoration: none;
  text-align: center;
}
.action-btn:active { transform: scale(0.97); }

.btn-danger {
  background: rgba(248,113,113,0.08);
  border-color: rgba(248,113,113,0.25);
  color: var(--red);
  width: 100%;
}
.btn-danger:hover { background: rgba(248,113,113,0.15); border-color: rgba(248,113,113,0.4); }

.btn-info {
  background: rgba(96,165,250,0.08);
  border-color: rgba(96,165,250,0.25);
  color: var(--blue);
  width: 100%;
}
.btn-info:hover { background: rgba(96,165,250,0.15); border-color: rgba(96,165,250,0.4); }

.btn-success {
  background: rgba(52,211,153,0.08);
  border-color: rgba(52,211,153,0.25);
  color: var(--green);
  width: 100%;
}
.btn-success:hover { background: rgba(52,211,153,0.15); border-color: rgba(52,211,153,0.4); }

.btn-purple {
  background: rgba(192,132,252,0.08);
  border-color: rgba(192,132,252,0.25);
  color: var(--purple);
  width: 100%;
}
.btn-purple:hover { background: rgba(192,132,252,0.15); border-color: rgba(192,132,252,0.4); }

.btn:disabled, button:disabled {
  opacity: 0.5; cursor: not-allowed; transform: none !important;
}

/* ─── ALERT ─── */
.alert {
  display: flex; align-items: center; gap: 10px;
  padding: 10px 14px;
  border-radius: var(--radius);
  border: 1px solid;
  font-size: 0.8rem;
  font-weight: 600;
  direction: rtl;
  margin-top: 0.75rem;
  animation: alertIn 0.35s cubic-bezier(.34,1.56,.64,1);
}
@keyframes alertIn { from{opacity:0;transform:translateY(-8px) scale(.96)} to{opacity:1;transform:none} }
.alert-ok   { background:rgba(52,211,153,0.08);  border-color:rgba(52,211,153,0.25);  color:var(--green);  }
.alert-err  { background:rgba(248,113,113,0.08); border-color:rgba(248,113,113,0.25); color:var(--red);   }
.alert-warn { background:rgba(251,191,36,0.08);  border-color:rgba(251,191,36,0.25);  color:var(--yellow); }
.alert-detail { font-size:0.68rem; opacity:0.7; margin-top:2px; font-family:'JetBrains Mono',monospace; }

/* ─── INFO ROWS ─── */
.info-row {
  display: flex; align-items: center; justify-content: space-between;
  padding: 0.65rem 0;
  border-bottom: 1px solid rgba(255,255,255,0.04);
  font-size: 0.8rem;
}
.info-row:last-child { border-bottom: none; }
.info-key { color: var(--muted); }
.info-val { font-family: 'JetBrains Mono', monospace; font-weight: 600; font-size: 0.78rem; }

/* ─── CMD BOX ─── */
.cmd-box {
  background: rgba(0,0,0,0.4);
  border: 1px solid rgba(255,255,255,0.05);
  border-radius: var(--radius-sm);
  padding: 5px 10px;
  font-family: 'JetBrains Mono', monospace;
  font-size: 0.68rem;
  color: var(--green);
  display: block;
  cursor: pointer;
  transition: background 0.15s;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.cmd-box:hover { background: rgba(52,211,153,0.05); }

/* ─── TOASTS ─── */
#toast-portal {
  position: fixed;
  top: 80px; left: 50%;
  transform: translateX(-50%);
  display: flex; flex-direction: column;
  gap: 10px;
  z-index: 9999;
  align-items: center;
  pointer-events: none;
}
.toast {
  display: flex; align-items: flex-start; gap: 10px;
  padding: 12px 18px;
  border-radius: var(--radius-lg);
  border: 1px solid;
  font-size: 0.82rem;
  font-weight: 600;
  direction: rtl;
  min-width: 260px;
  max-width: 380px;
  backdrop-filter: blur(12px);
  animation: toastIn 0.4s cubic-bezier(.34,1.56,.64,1) forwards;
  pointer-events: all;
  position: relative;
  overflow: hidden;
}
.toast.out { animation: toastOut 0.3s ease forwards; }
@keyframes toastIn  { from{opacity:0;transform:translateY(-16px) scale(.93)} to{opacity:1;transform:none} }
@keyframes toastOut { to{opacity:0;transform:translateY(-10px) scale(.95)} }

.toast-loading { background:rgba(14,14,26,0.95); border-color:rgba(96,165,250,0.3); color:var(--blue); }
.toast-ok      { background:rgba(4,22,14,0.95);  border-color:rgba(52,211,153,0.3); color:var(--green); }
.toast-err     { background:rgba(22,4,4,0.95);   border-color:rgba(248,113,113,0.3);color:var(--red); }
.toast-warn    { background:rgba(22,18,4,0.95);  border-color:rgba(251,191,36,0.3); color:var(--yellow); }

.toast-body { flex: 1; }
.toast-title { font-weight: 700; margin-bottom: 2px; }
.toast-sub   { font-size: 0.68rem; opacity: 0.7; font-family: 'JetBrains Mono', monospace; }

.toast-progress {
  position: absolute; bottom: 0; left: 0;
  height: 2px; background: currentColor; opacity: 0.3;
  border-radius: 99px;
}

.spinner {
  width: 16px; height: 16px; flex-shrink: 0; margin-top: 2px;
  border: 2px solid rgba(96,165,250,0.2);
  border-top-color: var(--blue);
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
}
@keyframes spin { to{transform:rotate(360deg)} }

/* ─── NETWORK ─── */
.net-item {
  display: flex; align-items: center; justify-content: space-between;
  padding: 0.5rem 0;
  border-bottom: 1px solid rgba(255,255,255,0.04);
  font-size: 0.75rem;
}
.net-item:last-child { border-bottom: none; }
.net-iface { color: var(--purple); font-family: 'JetBrains Mono', monospace; font-weight: 700; }
.net-vals  { display: flex; gap: 14px; }
.net-arrow { font-size: 0.65rem; color: var(--muted); margin-left: 2px; }

/* ─── FOOTER ─── */
footer {
  border-top: 1px solid var(--border);
  padding: 1.4rem 2rem;
  display: flex; align-items: center; justify-content: space-between;
  position: relative; z-index: 1;
  flex-wrap: wrap; gap: 10px;
}
.footer-brand {
  font-size: 0.7rem;
  font-weight: 700;
  letter-spacing: 2px;
  text-transform: uppercase;
}
.footer-brand em {
  font-style: normal;
  background: linear-gradient(90deg,#c084fc,#818cf8);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}
.footer-meta { font-size: 0.65rem; color: var(--muted); font-family: 'JetBrains Mono', monospace; }

/* ─── RESPONSIVE ─── */
@media (max-width: 1100px) {
  .g4 { grid-template-columns: repeat(2,1fr); }
  .g21,.g12 { grid-template-columns: 1fr; }
}
@media (max-width: 720px) {
  .g3,.g2,.g21,.g12 { grid-template-columns: 1fr; }
  .main-wrap { padding: 1rem; }
  header { padding: 0 1rem; }
  .actions-grid { grid-template-columns: 1fr; }
}
@media (max-width: 480px) {
  .g4 { grid-template-columns: 1fr; }
  .logo-tagline { display: none; }
}
</style>
</head>
<body>

<!-- ═══ HEADER ═══ -->
<header>
  <div class="logo-wrap">
    <div class="logo-mark">🌐</div>
    <div>
      <div class="logo-name"><em>SHASHITY</em> Global Cache</div>
      <div class="logo-tagline">Server Intelligence Dashboard</div>
    </div>
  </div>
  <div class="header-right">
    <div class="htime" id="live-clock"><?= $now ?></div>
    <div class="hbadge <?= $nginxStatus === 'active' ? 'active' : 'inactive' ?>">
      <span class="dot"></span>
      NGINX <?= strtoupper($nginxStatus) ?>
    </div>
  </div>
</header>

<div class="refresh-bar"><div class="refresh-bar-fill"></div></div>

<!-- ═══ TOASTS ═══ -->
<div id="toast-portal"></div>

<!-- ═══ MAIN ═══ -->
<div class="main-wrap">

<?php
// Show PHP-side alerts
$phpAlerts = [];
if ($clearResult) $phpAlerts[] = $clearResult + ['tag' => 'cache'];
if ($reloadResult) $phpAlerts[] = $reloadResult + ['tag' => 'nginx'];
foreach ($phpAlerts as $al):
  $cls = ['ok'=>'alert-ok','err'=>'alert-err','warn'=>'alert-warn'][$al['status']] ?? 'alert-warn';
  $ico = ['ok'=>'✅','err'=>'❌','warn'=>'⚠️'][$al['status']] ?? '⚠️';
?>
<div class="alert <?= $cls ?>" style="margin-bottom:1.2rem;">
  <span><?= $ico ?></span>
  <div>
    <div><?= htmlspecialchars($al['msg']) ?></div>
    <?php if (!empty($al['detail'])): ?>
    <div class="alert-detail"><?= htmlspecialchars($al['detail']) ?></div>
    <?php endif; ?>
  </div>
</div>
<?php endforeach; ?>

  <!-- ═══ QUICK STATS ═══ -->
  <div class="sec-label">نظرة عامة على النظام</div>
  <div class="g4" style="margin-bottom:1.5rem;">

    <!-- CPU -->
    <div class="card card-top-line">
      <div class="stat-label">المعالج — CPU</div>
      <div class="gauge-wrap" style="margin-top:0.5rem;">
        <?php $cpuCls = $cpu >= 80 ? 'red' : ($cpu >= 50 ? 'yellow' : 'green'); ?>
        <svg class="ring-svg" viewBox="0 0 60 60">
          <circle class="ring-track" cx="30" cy="30" r="22"/>
          <circle class="ring-fill"
            cx="30" cy="30" r="22"
            stroke="<?= ['red'=>'#f87171','yellow'=>'#fbbf24','green'=>'#34d399'][$cpuCls] ?>"
            stroke-dasharray="<?= round(2*M_PI*22, 1) ?>"
            stroke-dashoffset="<?= round(2*M_PI*22*(1-$cpu/100), 1) ?>"
            id="cpu-ring"
          />
          <text x="30" y="34" text-anchor="middle" font-family="JetBrains Mono" font-size="10" font-weight="700"
            fill="<?= ['red'=>'#f87171','yellow'=>'#fbbf24','green'=>'#34d399'][$cpuCls] ?>"><?= $cpu ?>%</text>
        </svg>
        <div>
          <div class="stat-val c-<?= $cpuCls ?>" style="font-size:1.3rem;"><?= $cpu ?>%</div>
          <div class="stat-sub">استخدام المعالج</div>
          <div class="stat-sub" style="margin-top:3px;">نوى: <?= shell_exec('nproc 2>/dev/null') ?? '—' ?></div>
        </div>
      </div>
    </div>

    <!-- RAM -->
    <div class="card card-top-line">
      <div class="stat-label">الذاكرة — RAM</div>
      <?php $memCls = $mem['percent'] >= 80 ? 'red' : ($mem['percent'] >= 60 ? 'yellow' : 'blue'); ?>
      <div style="margin-top:0.5rem;">
        <div class="stat-val c-<?= $memCls ?>"><?= htmlspecialchars($mem['used']) ?></div>
        <div class="stat-sub">من <?= htmlspecialchars($mem['total']) ?> — <?= $mem['percent'] ?>% مستخدم</div>
        <div class="pb-track"><div class="pb-fill pb-<?= $memCls ?>" style="width:<?= $mem['percent'] ?>%"></div></div>
        <div class="stat-sub" style="margin-top:5px;">متاح: <?= htmlspecialchars($mem['free']) ?></div>
      </div>
    </div>

    <!-- Load -->
    <div class="card card-top-line">
      <div class="stat-label">حمل النظام</div>
      <?php $ldCls = $load[0] > 3 ? 'red' : ($load[0] > 1.5 ? 'yellow' : 'green'); ?>
      <div style="margin-top:0.5rem;">
        <div class="stat-val c-<?= $ldCls ?>"><?= round($load[0],2) ?></div>
        <div class="stat-sub">1 دقيقة</div>
        <div style="display:flex;gap:12px;margin-top:8px;">
          <div><div class="stat-sub">5 دقائق</div><div style="font-family:'JetBrains Mono',monospace;font-weight:700;font-size:0.9rem;"><?= round($load[1],2) ?></div></div>
          <div><div class="stat-sub">15 دقيقة</div><div style="font-family:'JetBrains Mono',monospace;font-weight:700;font-size:0.9rem;"><?= round($load[2],2) ?></div></div>
        </div>
      </div>
    </div>

    <!-- Uptime / Cache -->
    <div class="card card-top-line">
      <div class="stat-label">وقت التشغيل</div>
      <div style="margin-top:0.5rem;">
        <div class="stat-val c-green" style="font-size:1rem; line-height:1.3;"><?= htmlspecialchars($uptime ?: '—') ?></div>
        <div class="stat-sub" style="margin-top:8px; padding-top:8px; border-top:1px solid var(--border);">
          الكاش:
          <?php if ($cache['exists']): ?>
            <span class="c-yellow" style="font-family:'JetBrains Mono',monospace; font-weight:700;"><?= number_format($cache['files']) ?> ملف</span>
            <span class="c-muted"> · <?= $cache['size'] ?> MB</span>
          <?php else: ?>
            <span class="c-muted">—</span>
          <?php endif; ?>
        </div>
      </div>
    </div>

  </div><!-- /g4 -->

  <!-- ═══ DISK + PROCESSES ═══ -->
  <div class="g21">

    <!-- Disk -->
    <div>
      <div class="sec-label">مساحة التخزين — df -h</div>
      <div class="card">
        <?php if (empty($disks)): ?>
          <p class="c-muted" style="text-align:center;padding:2rem;">لا توجد بيانات</p>
        <?php else: foreach ($disks as $d):
          $p   = $d['percent'];
          $cls = $p >= 90 ? 'crit' : ($p >= 70 ? 'warn' : 'safe');
          $pb  = $p >= 90 ? 'red'  : ($p >= 70 ? 'yellow' : 'green');
        ?>
        <div class="disk-row">
          <div class="disk-row-top">
            <div>
              <div class="disk-mount"><?= htmlspecialchars($d['mount']) ?></div>
              <div class="disk-fs"><?= htmlspecialchars($d['fs']) ?></div>
            </div>
            <div style="display:flex;align-items:center;gap:10px;">
              <div class="disk-meta">
                <span>كلي: <b><?= htmlspecialchars($d['size']) ?></b></span>
                <span>مستخدم: <b><?= htmlspecialchars($d['used']) ?></b></span>
                <span>حر: <b><?= htmlspecialchars($d['avail']) ?></b></span>
              </div>
              <div class="disk-pct <?= $cls ?>"><?= $p ?>%</div>
            </div>
          </div>
          <div class="pb-track">
            <div class="pb-fill pb-<?= $pb ?>" style="width:<?= $p ?>%"></div>
          </div>
        </div>
        <?php endforeach; endif; ?>
      </div>
    </div>

    <!-- Top Processes -->
    <div>
      <div class="sec-label">أعلى العمليات — CPU</div>
      <div class="card" style="height:calc(100% - 1.8rem);">
        <?php if (empty($procs)): ?>
          <p class="c-muted" style="padding:1rem; text-align:center;">غير متاح</p>
        <?php else: ?>
        <table class="data-table">
          <thead>
            <tr>
              <th>العملية</th>
              <th style="text-align:center;">CPU%</th>
              <th style="text-align:center;">MEM%</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($procs as $pr):
              $cpuP = (float)$pr['cpu'];
              $cpuC = $cpuP > 20 ? 'var(--red)' : ($cpuP > 5 ? 'var(--yellow)' : 'var(--green)');
            ?>
            <tr>
              <td style="color:var(--text); max-width:140px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"><?= htmlspecialchars($pr['name']) ?></td>
              <td style="text-align:center; color:<?= $cpuC ?>;"><?= $pr['cpu'] ?></td>
              <td style="text-align:center; color:var(--blue);"><?= $pr['mem'] ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>
      </div>
    </div>

  </div><!-- /g21 -->

  <!-- ═══ LOG + SIDEBAR ═══ -->
  <div class="g21">

    <!-- NGINX Log -->
    <div>
      <div class="sec-label">سجل Nginx الأخير <span class="live-badge" style="margin-right:auto;">LIVE</span></div>
      <div class="log-wrap">
        <div class="log-topbar">
          <span class="log-title">tail -n 25 /var/log/nginx/access.log</span>
          <div class="log-stats">
            <?php if ($logStats['2xx']): ?>
              <span class="log-stat-pill" style="background:rgba(52,211,153,0.1);color:var(--green);">2xx <?= $logStats['2xx'] ?></span>
            <?php endif; ?>
            <?php if ($logStats['3xx']): ?>
              <span class="log-stat-pill" style="background:rgba(96,165,250,0.1);color:var(--blue);">3xx <?= $logStats['3xx'] ?></span>
            <?php endif; ?>
            <?php if ($logStats['4xx']): ?>
              <span class="log-stat-pill" style="background:rgba(251,191,36,0.1);color:var(--yellow);">4xx <?= $logStats['4xx'] ?></span>
            <?php endif; ?>
            <?php if ($logStats['5xx']): ?>
              <span class="log-stat-pill" style="background:rgba(248,113,113,0.1);color:var(--red);">5xx <?= $logStats['5xx'] ?></span>
            <?php endif; ?>
            <?php if ($logStats['bytes']): ?>
              <span class="log-stat-pill" style="background:rgba(192,132,252,0.1);color:var(--purple);"><?= formatBytes($logStats['bytes']) ?></span>
            <?php endif; ?>
          </div>
        </div>
        <div class="log-body">
          <?php if (empty($parsedLog)): ?>
            <div style="color:var(--muted); text-align:center; padding:2rem;">لا توجد سجلات</div>
          <?php else: foreach ($parsedLog as $p): ?>
            <?php if (isset($p['raw'])): ?>
              <div class="ll"><span class="ll-raw"><?= htmlspecialchars(substr($p['raw'],0,120)) ?></span></div>
            <?php else:
              $sCls = 'll-s' . substr($p['status'],0,1);
              $mCls = 'll-' . $p['method'];
            ?>
            <div class="ll">
              <span class="ll-ip"><?= htmlspecialchars($p['ip']) ?></span>
              <span class="ll-method <?= $mCls ?>"><?= htmlspecialchars($p['method']) ?></span>
              <span class="<?= $sCls ?>"><?= htmlspecialchars($p['status']) ?></span>
              <span class="ll-path" title="<?= htmlspecialchars($p['path']) ?>"><?= htmlspecialchars($p['path']) ?></span>
              <span class="ll-size"><?= formatBytes($p['size']) ?></span>
            </div>
            <?php endif; endforeach; endif; ?>
        </div>
      </div>
    </div>

    <!-- Sidebar -->
    <div style="display:flex;flex-direction:column;gap:1.2rem;">

      <!-- Actions -->
      <div>
        <div class="sec-label">الإجراءات السريعة</div>
        <div class="card">
          <div class="actions-grid">

            <!-- Clear Cache -->
            <form method="POST" id="form-cache" onsubmit="handleAction(event,'cache','جاري تفريغ الكاش...')">
              <input type="hidden" name="clear_cache" value="1">
              <button type="submit" class="action-btn btn-danger" id="btn-cache">
                🗑️ تفريغ الكاش
              </button>
            </form>

            <!-- Reload Nginx -->
            <form method="POST" id="form-nginx" onsubmit="handleAction(event,'nginx','جاري إعادة تحميل Nginx...')">
              <input type="hidden" name="reload_nginx" value="1">
              <button type="submit" class="action-btn btn-info" id="btn-nginx">
                🔄 إعادة التحميل
              </button>
            </form>

            <!-- View Error Log -->
            <a href="#" class="action-btn btn-success" onclick="alert('ssh: tail -f /var/log/nginx/error.log'); return false;">
              📋 سجل الأخطاء
            </a>

            <!-- Refresh -->
            <a href="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="action-btn btn-purple">
              ⟳ تحديث الصفحة
            </a>

          </div>
        </div>
      </div>

      <!-- System Info -->
      <div>
        <div class="sec-label">معلومات الخادم</div>
        <div class="card">
          <div class="info-row"><span class="info-key">اسم الخادم</span><span class="info-val"><?= htmlspecialchars($hostname) ?></span></div>
          <div class="info-row"><span class="info-key">IP الخادم</span><span class="info-val c-blue"><?= htmlspecialchars($serverIp) ?></span></div>
          <div class="info-row"><span class="info-key">Nginx</span><span class="info-val c-green">v<?= htmlspecialchars($nginxVer) ?></span></div>
          <div class="info-row"><span class="info-key">PHP</span><span class="info-val c-purple"><?= htmlspecialchars($phpVer) ?></span></div>
          <div class="info-row"><span class="info-key">OS</span><span class="info-val" style="font-size:0.68rem;"><?= htmlspecialchars(trim(shell_exec('uname -sr 2>/dev/null') ?? '—')) ?></span></div>
          <div class="info-row"><span class="info-key">حالة Nginx</span>
            <span class="info-val" style="color:<?= $nginxStatus === 'active' ? 'var(--green)' : 'var(--red)' ?>">
              <?= htmlspecialchars($nginxStatus) ?>
            </span>
          </div>
          <div class="info-row"><span class="info-key">أقراص مكشوفة</span><span class="info-val"><?= count($disks) ?></span></div>
          <div class="info-row"><span class="info-key">ملفات الكاش</span>
            <span class="info-val c-yellow"><?= $cache['exists'] ? number_format($cache['files']) : '—' ?></span>
          </div>
          <div class="info-row"><span class="info-key">حجم الكاش</span>
            <span class="info-val c-blue"><?= $cache['exists'] ? $cache['size'].' MB' : '—' ?></span>
          </div>
        </div>
      </div>

      <!-- Network -->
      <?php if (!empty($network)): ?>
      <div>
        <div class="sec-label">الشبكة</div>
        <div class="card">
          <?php foreach ($network as $n): ?>
          <div class="net-item">
            <span class="net-iface"><?= htmlspecialchars($n['name']) ?></span>
            <div class="net-vals">
              <span><span class="net-arrow">↓</span><span class="c-green"><?= htmlspecialchars($n['rx']) ?></span></span>
              <span><span class="net-arrow">↑</span><span class="c-blue"><?= htmlspecialchars($n['tx']) ?></span></span>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- Commands -->
      <div>
        <div class="sec-label">أوامر SSH</div>
        <div class="card" style="display:flex;flex-direction:column;gap:8px;">
          <?php $cmds = [
            'تفريغ الكاش'    => 'rm -rf /var/cache/nginx/smart/*',
            'إعادة تحميل'    => 'systemctl reload nginx',
            'مشاهدة السجل'   => 'tail -f /var/log/nginx/access.log',
            'حالة الخدمة'    => 'systemctl status nginx',
            'سجل الأخطاء'    => 'tail -f /var/log/nginx/error.log',
          ]; foreach ($cmds as $lbl => $cmd): ?>
          <div>
            <div style="font-size:0.62rem;color:var(--muted);margin-bottom:3px;"><?= $lbl ?></div>
            <code class="cmd-box" onclick="copyCmd(this)"><?= htmlspecialchars($cmd) ?></code>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

    </div><!-- /sidebar -->
  </div><!-- /g21 -->

</div><!-- /main-wrap -->

<!-- ═══ FOOTER ═══ -->
<footer>
  <div class="footer-brand"><em>SHASHITY</em> Global Cache &nbsp;·&nbsp; v2.0</div>
  <div class="footer-meta">Auto-refresh: 30s &nbsp;|&nbsp; <?= $now ?></div>
</footer>

<script>
// ─── LIVE CLOCK ───
function updateClock() {
  const now = new Date();
  const pad = n => String(n).padStart(2,'0');
  document.getElementById('live-clock').textContent =
    now.getFullYear()+'-'+pad(now.getMonth()+1)+'-'+pad(now.getDate())+' '+
    pad(now.getHours())+':'+pad(now.getMinutes())+':'+pad(now.getSeconds());
}
setInterval(updateClock, 1000);

// ─── AUTO REFRESH (30s) ───
setTimeout(() => location.reload(), 30000);

// ─── ANIMATE BARS ───
document.querySelectorAll('.pb-fill, .ring-fill').forEach(el => {
  if (el.classList.contains('pb-fill')) {
    const w = el.style.width;
    el.style.width = '0';
    setTimeout(() => el.style.width = w, 80);
  }
  if (el.classList.contains('ring-fill')) {
    const offset = el.getAttribute('stroke-dashoffset');
    const total  = el.getAttribute('stroke-dasharray');
    el.setAttribute('stroke-dashoffset', total);
    setTimeout(() => el.setAttribute('stroke-dashoffset', offset), 80);
  }
});

// ─── TOAST SYSTEM ───
const portal = document.getElementById('toast-portal');
let toastId = 0;

function showToast(type, title, sub, duration) {
  const id = ++toastId;
  const t = document.createElement('div');
  t.className = 'toast toast-' + type;
  t.id = 'toast-' + id;

  let iconHtml = '';
  if (type === 'loading') {
    iconHtml = '<div class="spinner"></div>';
  } else {
    const icons = {ok:'✅', err:'❌', warn:'⚠️'};
    iconHtml = `<span style="font-size:18px;flex-shrink:0;">${icons[type]||'ℹ️'}</span>`;
  }

  t.innerHTML = `
    ${iconHtml}
    <div class="toast-body">
      <div class="toast-title">${title}</div>
      ${sub ? `<div class="toast-sub">${sub}</div>` : ''}
    </div>
    ${type !== 'loading' ? `<div class="toast-progress" style="animation:toastProgress ${duration/1000}s linear forwards;width:100%;"></div>` : ''}
  `;
  portal.appendChild(t);

  if (type !== 'loading') {
    setTimeout(() => {
      t.classList.add('out');
      setTimeout(() => t.remove(), 300);
    }, duration);
  }
  return t;
}

// Inject progress animation
const style = document.createElement('style');
style.textContent = '@keyframes toastProgress{to{width:0%}}';
document.head.appendChild(style);

// ─── FORM HANDLER ───
function handleAction(e, key, loadingMsg) {
  e.preventDefault();
  const form = e.target;
  const btn  = form.querySelector('button');
  btn.disabled = true;
  const origText = btn.innerHTML;
  btn.innerHTML = '<span class="spinner" style="border-top-color:currentColor;"></span> جاري التنفيذ...';

  const loadingToast = showToast('loading', loadingMsg, '');

  setTimeout(() => {
    loadingToast.remove();
    // Submit form
    form.submit();
  }, 800);
}

// Show PHP-side results as toasts
<?php if ($clearResult): ?>
window.addEventListener('DOMContentLoaded', () => {
  const type = '<?= $clearResult['status'] === 'ok' ? 'ok' : ($clearResult['status'] === 'err' ? 'err' : 'warn') ?>';
  showToast(type, '<?= addslashes($clearResult['msg']) ?>', '<?= addslashes($clearResult['detail'] ?? '') ?>', 5000);
});
<?php endif; ?>
<?php if ($reloadResult): ?>
window.addEventListener('DOMContentLoaded', () => {
  const type = '<?= $reloadResult['status'] === 'ok' ? 'ok' : 'err' ?>';
  showToast(type, '<?= addslashes($reloadResult['msg']) ?>', '', 5000);
});
<?php endif; ?>

// ─── COPY COMMAND ───
function copyCmd(el) {
  navigator.clipboard.writeText(el.textContent.trim()).then(() => {
    showToast('ok', 'تم النسخ ✓', el.textContent.trim().substring(0,40), 2500);
  }).catch(() => {
    showToast('warn', 'تعذّر النسخ', 'انسخ يدوياً', 2500);
  });
}
</script>
</body>
</html>
