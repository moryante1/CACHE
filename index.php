<?php
// SHASHITY PRO - Netflix-Grade Server Dashboard
// ================================================

// --- Disk Usage (df -h) ---
function getDiskUsage() {
    $output = [];
    exec('df -h 2>/dev/null', $output);
    $disks = [];
    foreach ($output as $i => $line) {
        if ($i === 0) continue;
        $parts = preg_split('/\s+/', trim($line));
        if (count($parts) >= 6) {
            $size    = $parts[1];
            $used    = $parts[2];
            $avail   = $parts[3];
            $usePercent = intval(str_replace('%', '', $parts[4]));
            $mount   = $parts[5];
            $fs      = $parts[0];
            // Skip unimportant pseudo filesystems
            if (in_array($fs, ['tmpfs', 'devtmpfs', 'udev', 'none']) && strpos($mount, '/dev') === false && $mount !== '/') continue;
            $disks[] = [
                'fs'      => $fs,
                'size'    => $size,
                'used'    => $used,
                'avail'   => $avail,
                'percent' => $usePercent,
                'mount'   => $mount,
            ];
        }
    }
    return $disks;
}

// --- Nginx Access Log (tail -20) ---
function getNginxLog($lines = 20) {
    $logFile = '/var/log/nginx/access.log';
    if (!file_exists($logFile)) return [];
    $output = [];
    exec("tail -n $lines " . escapeshellarg($logFile) . " 2>/dev/null", $output);
    return $output;
}

// --- Nginx Status ---
function getNginxStatus() {
    exec('systemctl is-active nginx 2>/dev/null', $out);
    return trim($out[0] ?? 'unknown');
}

// --- Nginx Cache Info ---
function getCacheInfo() {
    $cacheDir = '/var/cache/nginx/smart';
    if (!is_dir($cacheDir)) return ['exists' => false];
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($cacheDir, FilesystemIterator::SKIP_DOTS));
    $count = 0; $size = 0;
    foreach ($files as $f) { $count++; $size += $f->getSize(); }
    return ['exists' => true, 'files' => $count, 'size' => round($size / 1024 / 1024, 2)];
}

// --- System Info ---
function getUptime() {
    exec('uptime -p 2>/dev/null', $out);
    return $out[0] ?? '';
}
function getLoadAvg() {
    return sys_getloadavg();
}
function getMemory() {
    exec('free -h 2>/dev/null', $out);
    if (isset($out[1])) {
        $parts = preg_split('/\s+/', trim($out[1]));
        return ['total' => $parts[1] ?? '-', 'used' => $parts[2] ?? '-', 'free' => $parts[3] ?? '-'];
    }
    return ['total' => '-', 'used' => '-', 'free' => '-'];
}

$disks      = getDiskUsage();
$nginxLog   = getNginxLog(20);
$nginxStatus = getNginxStatus();
$cache      = getCacheInfo();
$uptime     = getUptime();
$load       = getLoadAvg();
$mem        = getMemory();
$now        = date('Y-m-d H:i:s');
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SHASHITY PRO — لوحة التحكم</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700;900&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
<style>
  :root {
    --bg: #0a0a0f;
    --bg2: #111118;
    --bg3: #1a1a24;
    --card: #16161e;
    --border: rgba(255,255,255,0.07);
    --accent: #e50914;
    --accent2: #ff6b35;
    --gold: #f5c518;
    --text: #f0f0f5;
    --muted: #7a7a8c;
    --green: #46d369;
    --red: #e50914;
    --yellow: #f5c518;
    --blue: #4a9eff;
    --glow: 0 0 30px rgba(229,9,20,0.15);
  }
  * { margin:0; padding:0; box-sizing:border-box; }
  body {
    background: var(--bg);
    color: var(--text);
    font-family: 'Cairo', sans-serif;
    min-height: 100vh;
    overflow-x: hidden;
  }

  /* ─── SCANLINE EFFECT ─── */
  body::before {
    content: '';
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: repeating-linear-gradient(
      0deg,
      transparent,
      transparent 2px,
      rgba(0,0,0,0.03) 2px,
      rgba(0,0,0,0.03) 4px
    );
    pointer-events: none;
    z-index: 1000;
  }

  /* ─── HEADER ─── */
  header {
    background: linear-gradient(180deg, #000 0%, rgba(0,0,0,0.8) 100%);
    border-bottom: 1px solid var(--border);
    padding: 1.2rem 2rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    position: sticky;
    top: 0;
    z-index: 100;
    backdrop-filter: blur(10px);
  }
  .logo {
    display: flex;
    align-items: center;
    gap: 12px;
  }
  .logo-icon {
    width: 40px; height: 40px;
    background: var(--accent);
    border-radius: 6px;
    display: flex; align-items: center; justify-content: center;
    font-size: 20px;
    box-shadow: var(--glow);
    animation: pulse-glow 3s ease-in-out infinite;
  }
  @keyframes pulse-glow {
    0%,100% { box-shadow: 0 0 20px rgba(229,9,20,0.3); }
    50% { box-shadow: 0 0 40px rgba(229,9,20,0.6), 0 0 80px rgba(229,9,20,0.2); }
  }
  .logo-text { font-size: 1.6rem; font-weight: 900; letter-spacing: -0.5px; }
  .logo-text span { color: var(--accent); }
  .logo-sub { font-size: 0.7rem; color: var(--muted); letter-spacing: 3px; text-transform: uppercase; }
  .header-right { display: flex; align-items: center; gap: 16px; }
  .time-badge {
    background: var(--bg3);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 6px 14px;
    font-family: 'JetBrains Mono', monospace;
    font-size: 0.78rem;
    color: var(--muted);
  }
  .nginx-badge {
    padding: 6px 16px;
    border-radius: 8px;
    font-size: 0.78rem;
    font-weight: 600;
    letter-spacing: 1px;
  }
  .nginx-badge.active { background: rgba(70,211,105,0.15); color: var(--green); border: 1px solid rgba(70,211,105,0.3); }
  .nginx-badge.inactive { background: rgba(229,9,20,0.15); color: var(--red); border: 1px solid rgba(229,9,20,0.3); }

  /* ─── MAIN LAYOUT ─── */
  main { padding: 2rem; max-width: 1400px; margin: 0 auto; }
  .grid-4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 2rem; }
  .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem; }
  .grid-1 { margin-bottom: 2rem; }

  /* ─── STAT CARDS ─── */
  .stat-card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 1.2rem 1.4rem;
    position: relative;
    overflow: hidden;
    transition: border-color 0.3s, transform 0.2s;
  }
  .stat-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 2px;
    background: linear-gradient(90deg, var(--accent), transparent);
  }
  .stat-card:hover { border-color: rgba(229,9,20,0.3); transform: translateY(-2px); }
  .stat-icon { font-size: 1.8rem; margin-bottom: 0.6rem; }
  .stat-label { font-size: 0.72rem; color: var(--muted); text-transform: uppercase; letter-spacing: 2px; margin-bottom: 4px; }
  .stat-value { font-size: 1.6rem; font-weight: 700; font-family: 'JetBrains Mono', monospace; }
  .stat-sub { font-size: 0.72rem; color: var(--muted); margin-top: 4px; }
  .val-green { color: var(--green); }
  .val-red { color: var(--red); }
  .val-gold { color: var(--gold); }
  .val-blue { color: var(--blue); }

  /* ─── SECTION HEADERS ─── */
  .section-title {
    font-size: 0.7rem;
    font-weight: 700;
    letter-spacing: 3px;
    text-transform: uppercase;
    color: var(--muted);
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 10px;
  }
  .section-title::after {
    content: '';
    flex: 1;
    height: 1px;
    background: var(--border);
  }
  .section-accent { color: var(--accent); }

  /* ─── DISK CARDS ─── */
  .disk-panel {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 1.5rem;
  }
  .disk-item {
    display: grid;
    grid-template-columns: 1fr auto auto auto;
    align-items: center;
    gap: 1rem;
    padding: 1rem 0;
    border-bottom: 1px solid var(--border);
  }
  .disk-item:last-child { border-bottom: none; padding-bottom: 0; }
  .disk-item:first-child { padding-top: 0; }
  .disk-mount {
    font-family: 'JetBrains Mono', monospace;
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--blue);
  }
  .disk-fs { font-size: 0.7rem; color: var(--muted); margin-top: 2px; }
  .disk-bar-wrap { grid-column: 1 / -1; }
  .disk-bar-track {
    width: 100%;
    height: 6px;
    background: var(--bg3);
    border-radius: 99px;
    overflow: hidden;
    margin-top: 0.5rem;
  }
  .disk-bar-fill {
    height: 100%;
    border-radius: 99px;
    transition: width 1s ease;
  }
  .disk-bar-fill.safe { background: linear-gradient(90deg, var(--green), #2ecc71); }
  .disk-bar-fill.warn { background: linear-gradient(90deg, var(--yellow), #f39c12); }
  .disk-bar-fill.danger { background: linear-gradient(90deg, var(--red), #c0392b); }
  .disk-info { text-align: left; }
  .disk-percent-badge {
    font-family: 'JetBrains Mono', monospace;
    font-size: 0.85rem;
    font-weight: 600;
    padding: 2px 10px;
    border-radius: 6px;
  }
  .disk-percent-badge.safe { background: rgba(70,211,105,0.1); color: var(--green); }
  .disk-percent-badge.warn { background: rgba(245,197,24,0.1); color: var(--yellow); }
  .disk-percent-badge.danger { background: rgba(229,9,20,0.1); color: var(--red); }
  .disk-sz { font-size: 0.75rem; color: var(--muted); font-family: 'JetBrains Mono', monospace; }

  /* ─── LOG PANEL ─── */
  .log-panel {
    background: #080810;
    border: 1px solid var(--border);
    border-radius: 12px;
    overflow: hidden;
  }
  .log-header {
    background: var(--card);
    padding: 0.8rem 1.2rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1px solid var(--border);
  }
  .log-title { font-size: 0.78rem; font-weight: 600; color: var(--muted); letter-spacing: 1px; }
  .live-dot {
    width: 8px; height: 8px;
    background: var(--green);
    border-radius: 50%;
    animation: blink 1.5s ease-in-out infinite;
    display: inline-block;
    margin-left: 6px;
  }
  @keyframes blink { 0%,100%{opacity:1;} 50%{opacity:0.2;} }
  .log-body {
    padding: 1rem 1.2rem;
    max-height: 320px;
    overflow-y: auto;
    font-family: 'JetBrains Mono', monospace;
    font-size: 0.72rem;
    line-height: 1.8;
  }
  .log-body::-webkit-scrollbar { width: 4px; }
  .log-body::-webkit-scrollbar-track { background: transparent; }
  .log-body::-webkit-scrollbar-thumb { background: var(--border); border-radius: 99px; }
  .log-line { display: flex; gap: 8px; padding: 2px 0; }
  .log-line:hover { background: rgba(255,255,255,0.03); border-radius: 4px; padding: 2px 4px; }
  .log-ip { color: var(--blue); min-width: 120px; }
  .log-method-GET { color: var(--green); }
  .log-method-POST { color: var(--gold); }
  .log-method-DELETE { color: var(--red); }
  .log-method-PUT { color: var(--accent2); }
  .log-status-2 { color: var(--green); }
  .log-status-3 { color: var(--blue); }
  .log-status-4 { color: var(--yellow); }
  .log-status-5 { color: var(--red); }
  .log-path { color: var(--muted); flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .log-size { color: var(--muted); font-size: 0.65rem; }
  .log-empty { color: var(--muted); font-size: 0.8rem; text-align: center; padding: 2rem; }

  /* ─── CACHE PANEL ─── */
  .cache-panel {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 1.5rem;
  }
  .cache-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.7rem 0;
    border-bottom: 1px solid var(--border);
    font-size: 0.85rem;
  }
  .cache-row:last-child { border-bottom: none; }
  .cache-key { color: var(--muted); }
  .cache-val { font-family: 'JetBrains Mono', monospace; font-weight: 600; }

  /* ─── FOOTER ─── */
  footer {
    text-align: center;
    padding: 2rem;
    color: var(--muted);
    font-size: 0.72rem;
    letter-spacing: 2px;
    border-top: 1px solid var(--border);
    margin-top: 2rem;
  }
  footer span { color: var(--accent); font-weight: 700; }

  /* ─── RESPONSIVE ─── */
  @media (max-width: 900px) {
    .grid-4 { grid-template-columns: repeat(2, 1fr); }
    .grid-2 { grid-template-columns: 1fr; }
    main { padding: 1rem; }
  }
  @media (max-width: 480px) {
    .grid-4 { grid-template-columns: 1fr; }
    header { padding: 1rem; }
    .logo-text { font-size: 1.2rem; }
  }
</style>
</head>
<body>

<!-- HEADER -->
<header>
  <div class="logo">
    <div class="logo-icon">⚡</div>
    <div>
      <div class="logo-text"><span>SHASHITY</span> PRO</div>
      <div class="logo-sub">Server Intelligence Dashboard</div>
    </div>
  </div>
  <div class="header-right">
    <div class="time-badge">🕐 <?= $now ?></div>
    <div class="nginx-badge <?= $nginxStatus === 'active' ? 'active' : 'inactive' ?>">
      NGINX <?= strtoupper($nginxStatus) ?>
    </div>
  </div>
</header>

<!-- MAIN -->
<main>

  <!-- QUICK STATS -->
  <div class="grid-4">
    <div class="stat-card">
      <div class="stat-icon">🖥️</div>
      <div class="stat-label">تحميل النظام</div>
      <div class="stat-value val-<?= $load[0] > 2 ? 'red' : ($load[0] > 1 ? 'gold' : 'green') ?>">
        <?= round($load[0], 2) ?>
      </div>
      <div class="stat-sub">1د / <?= round($load[1], 2) ?> / <?= round($load[2], 2) ?> (5د/15د)</div>
    </div>

    <div class="stat-card">
      <div class="stat-icon">💾</div>
      <div class="stat-label">الذاكرة - RAM</div>
      <div class="stat-value val-blue"><?= htmlspecialchars($mem['used']) ?></div>
      <div class="stat-sub">المستخدم / الكلي: <?= htmlspecialchars($mem['total']) ?> | حر: <?= htmlspecialchars($mem['free']) ?></div>
    </div>

    <div class="stat-card">
      <div class="stat-icon">⏱️</div>
      <div class="stat-label">وقت التشغيل</div>
      <div class="stat-value val-green" style="font-size:1.1rem;"><?= htmlspecialchars($uptime ?: '—') ?></div>
      <div class="stat-sub">منذ آخر إعادة تشغيل</div>
    </div>

    <div class="stat-card">
      <div class="stat-icon">📦</div>
      <div class="stat-label">كاش Nginx</div>
      <?php if ($cache['exists']): ?>
        <div class="stat-value val-gold"><?= $cache['files'] ?></div>
        <div class="stat-sub">ملف | <?= $cache['size'] ?> MB في /var/cache/nginx/smart</div>
      <?php else: ?>
        <div class="stat-value val-muted" style="color:var(--muted);">—</div>
        <div class="stat-sub">المجلد غير موجود</div>
      <?php endif; ?>
    </div>
  </div>

  <!-- DISK USAGE -->
  <div class="section-title"><span class="section-accent">◆</span> مساحة الخزن — df -h</div>
  <div class="grid-1">
    <div class="disk-panel">
      <?php if (empty($disks)): ?>
        <p style="color:var(--muted); text-align:center; padding:2rem;">لا توجد بيانات أقراص متاحة</p>
      <?php else: ?>
        <?php foreach ($disks as $d):
          $p = $d['percent'];
          $cls = $p >= 90 ? 'danger' : ($p >= 70 ? 'warn' : 'safe');
        ?>
        <div class="disk-item">
          <div>
            <div class="disk-mount"><?= htmlspecialchars($d['mount']) ?></div>
            <div class="disk-fs"><?= htmlspecialchars($d['fs']) ?></div>
          </div>
          <div class="disk-info disk-sz">الكلي: <?= htmlspecialchars($d['size']) ?></div>
          <div class="disk-info disk-sz">مستخدم: <?= htmlspecialchars($d['used']) ?> | حر: <?= htmlspecialchars($d['avail']) ?></div>
          <div class="disk-percent-badge <?= $cls ?>"><?= $p ?>%</div>
          <div class="disk-bar-wrap">
            <div class="disk-bar-track">
              <div class="disk-bar-fill <?= $cls ?>" style="width:<?= $p ?>%;"></div>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <!-- LOG + CACHE -->
  <div class="grid-2">
    <!-- NGINX LOG -->
    <div>
      <div class="section-title"><span class="section-accent">◆</span> سجل Nginx الأخير <span class="live-dot"></span></div>
      <div class="log-panel">
        <div class="log-header">
          <span class="log-title">tail -f /var/log/nginx/access.log</span>
          <span style="font-size:0.7rem; color:var(--muted);"><?= count($nginxLog) ?> سطر</span>
        </div>
        <div class="log-body">
          <?php if (empty($nginxLog)): ?>
            <div class="log-empty">لا توجد سجلات متاحة أو الملف فارغ</div>
          <?php else:
            foreach (array_reverse($nginxLog) as $line):
              // Parse common nginx log format
              if (preg_match('/^(\S+) \S+ \S+ \[.*?\] "(\w+) (\S+) \S+" (\d+) (\d+)/', $line, $m)):
                $ip = $m[1]; $method = $m[2]; $path = $m[3]; $status = $m[4]; $size = $m[5];
                $statusClass = 'log-status-' . substr($status, 0, 1);
                $methodClass = 'log-method-' . $method;
          ?>
              <div class="log-line">
                <span class="log-ip"><?= htmlspecialchars($ip) ?></span>
                <span class="<?= $methodClass ?>"><?= htmlspecialchars($method) ?></span>
                <span class="<?= $statusClass ?>"><?= htmlspecialchars($status) ?></span>
                <span class="log-path"><?= htmlspecialchars($path) ?></span>
                <span class="log-size"><?= number_format($size) ?>B</span>
              </div>
          <?php else: ?>
              <div class="log-line"><span class="log-path"><?= htmlspecialchars(substr($line, 0, 120)) ?></span></div>
          <?php endif; endforeach; endif; ?>
        </div>
      </div>
    </div>

    <!-- CACHE + SYSTEM -->
    <div>
      <div class="section-title"><span class="section-accent">◆</span> معلومات الكاش والنظام</div>
      <div class="cache-panel">
        <div class="cache-row">
          <span class="cache-key">حالة Nginx</span>
          <span class="cache-val" style="color:<?= $nginxStatus === 'active' ? 'var(--green)' : 'var(--red)' ?>">
            <?= htmlspecialchars($nginxStatus) ?>
          </span>
        </div>
        <div class="cache-row">
          <span class="cache-key">مسار الكاش</span>
          <span class="cache-val" style="color:var(--muted); font-size:0.72rem;">/var/cache/nginx/smart</span>
        </div>
        <div class="cache-row">
          <span class="cache-key">ملفات الكاش</span>
          <span class="cache-val val-gold"><?= $cache['exists'] ? $cache['files'] : '—' ?></span>
        </div>
        <div class="cache-row">
          <span class="cache-key">حجم الكاش</span>
          <span class="cache-val val-blue"><?= $cache['exists'] ? $cache['size'] . ' MB' : '—' ?></span>
        </div>
        <div class="cache-row">
          <span class="cache-key">أقراص مكشوفة</span>
          <span class="cache-val"><?= count($disks) ?></span>
        </div>
        <div class="cache-row">
          <span class="cache-key">PHP Version</span>
          <span class="cache-val" style="color:var(--accent2);"><?= PHP_VERSION ?></span>
        </div>
        <div class="cache-row">
          <span class="cache-key">اسم الخادم</span>
          <span class="cache-val" style="font-size:0.78rem;"><?= htmlspecialchars(gethostname()) ?></span>
        </div>
        <div class="cache-row">
          <span class="cache-key">IP الخادم</span>
          <span class="cache-val val-blue"><?= htmlspecialchars($_SERVER['SERVER_ADDR'] ?? shell_exec('hostname -I | awk \'{print $1}\'') ?: '—') ?></span>
        </div>
        <div class="cache-row">
          <span class="cache-key">آخر تحديث للصفحة</span>
          <span class="cache-val" style="font-size:0.72rem; color:var(--muted);"><?= $now ?></span>
        </div>
      </div>

      <!-- Quick Commands Info -->
      <div style="margin-top:1rem; background:var(--card); border:1px solid var(--border); border-radius:12px; padding:1.2rem;">
        <div class="section-title" style="margin-bottom:0.8rem;"><span class="section-accent">◆</span> أوامر سريعة</div>
        <?php
        $cmds = [
          'تفريغ الكاش' => 'rm -rf /var/cache/nginx/smart/*',
          'إعادة تحميل Nginx' => 'systemctl reload nginx',
          'مشاهدة السجل' => 'tail -f /var/log/nginx/access.log',
          'حالة الخدمة' => 'systemctl status nginx',
        ];
        foreach ($cmds as $label => $cmd): ?>
        <div style="margin-bottom:0.6rem;">
          <div style="font-size:0.68rem; color:var(--muted); margin-bottom:2px;"><?= $label ?></div>
          <code style="font-family:'JetBrains Mono',monospace; font-size:0.7rem; color:var(--green); background:var(--bg3); padding:3px 8px; border-radius:4px; display:block;"><?= htmlspecialchars($cmd) ?></code>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

</main>

<footer>
  SHASHITY <span>PRO</span> &nbsp;·&nbsp; Netflix-Grade Server Dashboard &nbsp;·&nbsp; <?= date('Y') ?>
</footer>

<script>
// Auto-refresh every 30s
setTimeout(() => location.reload(), 30000);

// Animate disk bars on load
document.querySelectorAll('.disk-bar-fill').forEach(bar => {
  const w = bar.style.width;
  bar.style.width = '0';
  setTimeout(() => { bar.style.width = w; }, 100);
});
</script>
</body>
</html>
