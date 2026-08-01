<?php
require_once __DIR__ . '/core/config.php';

// This page can create and erase ZFS pools. Referer headers are forgeable, so
// access must be protected by the authenticated session and CSRF validation.
if (!isAdminLoggedIn()) {
    http_response_code(403);
    exit('Forbidden');
}
if (!in_array($_SESSION['admin_role'] ?? '', ['administrator', 'super'], true)) {
    http_response_code(403);
    exit('Forbidden');
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && !csrfValidate()) {
    http_response_code(403);
    exit('Invalid request');
}
/**
 * ============================================================
 *  storage_manager.php  —  لوحة تخزين ذكية (ZFS) شاملة
 * ------------------------------------------------------------
 *  تقوم بكل شيء من الويب:
 *   • كشف الأقراص تلقائياً (وتستبعد قرص النظام)
 *   • إنشاء الـ pool (mirror / raidz1 / raidz2 / single)
 *   • توسيع المساحة بإضافة أقراص لاحقاً
 *   • استبدال قرص تالف
 *   • فحص (scrub) ومسح عدّادات الأخطاء
 *   • مراقبة الحالة والمساحة والأخطاء
 *
 *  الأمان: جلسة من admin + تأكيد كتابة CONFIRM + استبعاد قرص النظام.
 * ============================================================
 */

/* 🔒 --- التحقق من مصدر الطلب --- 🔒
 *
 * ⚠️ عطل كان هنا: بوابة Referer قديمة كانت ترفض الطلب إن لم يحتوِ
 *    الـ Referer على "admin.php". هذه البوابة تعطّل الصفحة تماماً في
 *    الحالات الطبيعية التالية:
 *      • المشروع نفسه يرسل Referrer-Policy: strict-origin-when-cross-origin،
 *        وعندها يرسل المتصفح الأصل فقط (https://site/) بلا اسم الملف.
 *      • فتح الصفحة من إشارة مرجعية أو كتابة العنوان يدوياً.
 *      • متصفحات/إضافات الخصوصية التي تحذف الـ Referer.
 *    النتيجة كانت إعادة توجيه صامتة إلى index.php بلا أي تفسير.
 *
 *    كما أنها لم تكن تضيف أماناً حقيقياً: ترويسة Referer قابلة للتزوير
 *    بالكامل من خارج المتصفح. الحماية الفعلية هي جلسة المدير + CSRF،
 *    وهما مطبَّقتان أعلى الملف بالفعل.
 *
 *    البديل الصحيح: التحقق من تطابق الأصل (Origin/Referer) مع أصل
 *    الموقع في طلبات POST فقط — وهذا يعمل مع كل المتصفحات لأن Origin
 *    يُرسَل دائماً مع POST، ولا يمنع فتح الصفحة العادي.
 */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $host       = (string) ($_SERVER['HTTP_HOST'] ?? '');
    $selfOrigin = (IS_HTTPS ? 'https://' : 'http://') . $host;

    $sent = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
    if ($sent === '' && !empty($_SERVER['HTTP_REFERER'])) {
        $rp = @parse_url((string) $_SERVER['HTTP_REFERER']);
        if (!empty($rp['host'])) {
            $sent = ($rp['scheme'] ?? 'http') . '://' . $rp['host']
                  . (isset($rp['port']) ? ':' . $rp['port'] : '');
        }
    }

    // نرفض فقط عند وجود أصل مختلف صراحةً؛ الغياب لا يُعتبر هجوماً
    // لأن رمز CSRF أعلاه هو خط الدفاع الأساسي.
    if ($sent !== '' && strcasecmp($sent, $selfOrigin) !== 0) {
        http_response_code(403);
        exit('Invalid origin');
    }
}
// 🔒 --- نهاية الحماية --- 🔒

// ============ الإعدادات ============
const POOL   = 'storage';
const MOUNT  = '/mnt/storage';
const SUDO   = 'sudo -n ';
const ZPOOL  = '/usr/sbin/zpool';
const ZFS    = '/usr/sbin/zfs';

header('X-Content-Type-Options: nosniff');

// ============ دوال مساعدة ============
function run(string $cmd, &$rc = null): string {
    $out = []; $rc = 0;
    exec($cmd . ' 2>&1', $out, $rc);
    return trim(implode("\n", $out));
}
function z(string $args, &$rc = null): string {
    return run(SUDO . ZPOOL . ' ' . $args, $rc);
}

/** قرص النظام (الجذر /) — نستبعده دائماً */
function system_disk(): string {
    $src = run("findmnt -no SOURCE /");         // مثال: /dev/sda2
    $src = preg_replace('#p?\d+$#', '', $src);  // جرّد رقم القسم
    return $src ?: '/dev/sda';
}

/** كل الأقراص الفيزيائية (نوع disk فقط، بلا loop/rom) */
function all_disks(): array {
    $sysdisk = system_disk();
    $raw = run("lsblk -dpno NAME,SIZE,MODEL,TYPE");
    $disks = [];
    foreach (explode("\n", $raw) as $ln) {
        $ln = trim($ln);
        if ($ln === '') continue;
        if (!preg_match('/^(\S+)\s+(\S+)\s+(.*?)\s+(\S+)$/', $ln, $m)) {
            if (!preg_match('/^(\S+)\s+(\S+)\s+(\S+)$/', $ln, $m2)) continue;
            $m = [$m2[0], $m2[1], $m2[2], '', $m2[3]];
        }
        [$_, $name, $size, $model, $type] = $m;
        if ($type !== 'disk') continue;
        if (strpos($name, '/dev/loop') === 0) continue;
        $disks[] = [
            'name'   => $name,
            'size'   => $size,
            'model'  => trim($model) ?: '-',
            'is_sys' => ($name === $sysdisk),
            'used'   => disk_in_use($name),
        ];
    }
    return $disks;
}

/** هل القرص مستخدم بالفعل (له أقسام أو ضمن pool)؟ */
function disk_in_use(string $disk): bool {
    $parts = run("lsblk -no NAME " . escapeshellarg($disk));
    if (substr_count(trim($parts), "\n") >= 1) return true;   // له أقسام
    $inpool = z("status");
    $base = basename($disk);
    return (bool)preg_match('/\b' . preg_quote($base, '/') . '\b/', $inpool);
}

function pool_exists(): bool {
    return z("list -H -o name " . escapeshellarg(POOL)) === POOL;
}

/** حالة الـ pool */
function pool_health(): array {
    $out = ['state'=>'MISSING','size'=>'-','alloc'=>'-','free'=>'-','cap'=>0,'raw'=>'','devices'=>[],'scan'=>''];
    if (!pool_exists()) return $out;

    $list = z("list -H -o health,size,alloc,free,capacity " . escapeshellarg(POOL));
    if ($list) {
        [$hh,$s,$a,$f,$c] = array_pad(explode("\t",$list),5,'-');
        $out['state']=$hh; $out['size']=$s; $out['alloc']=$a; $out['free']=$f; $out['cap']=(int)rtrim($c,'%');
    }
    $status = z("status " . escapeshellarg(POOL));
    $out['raw'] = $status;
    if (preg_match('/^\s*scan:\s*(.+)$/m',$status,$m)) $out['scan']=trim($m[1]);

    $inCfg=false;
    foreach (explode("\n",$status) as $ln) {
        if (preg_match('/^\s*NAME\s+STATE/',$ln)) { $inCfg=true; continue; }
        if ($inCfg) {
            if (trim($ln)==='' || preg_match('/^errors:/',$ln)) break;
            if (preg_match('/^(\s+)(\S+)\s+(\S+)\s+(\d+)\s+(\d+)\s+(\d+)/',$ln,$m)) {
                $out['devices'][]=['indent'=>strlen($m[1]),'name'=>$m[2],'state'=>$m[3],
                    'read'=>(int)$m[4],'write'=>(int)$m[5],'cksum'=>(int)$m[6]];
            }
        }
    }
    return $out;
}

function badge_class(string $s): string {
    if ($s === 'ONLINE') return 'ok';
    if ($s === 'DEGRADED' || $s === 'RESILVERING') return 'warn';
    if (in_array($s, ['FAULTED', 'UNAVAIL', 'OFFLINE', 'MISSING', 'REMOVED'], true)) return 'err';
    return 'muted';
}

// ============ معالجة الأوامر (POST) ============
$msg = null;
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $action = $_POST['action'] ?? '';
    $confirm= $_POST['confirm']?? '';

    {
        $sysdisk = system_disk();
        // فلتر أمان: منع أي قرص نظام في أي عملية
        $picked = array_values(array_filter((array)($_POST['disks'] ?? []), function($d) use ($sysdisk){
            return preg_match('#^/dev/[a-zA-Z0-9/_-]+$#',$d) && $d !== $sysdisk;
        }));

        switch ($action) {

            case 'create':
                if (pool_exists()) { $msg=['err','الـ pool موجود مسبقاً.']; break; }
                if ($confirm !== 'CONFIRM') { $msg=['err','اكتب CONFIRM للتأكيد — العملية تمسح الأقراص.']; break; }
                $mode = $_POST['mode'] ?? 'mirror';
                $need = ($mode==='single')?1:(($mode==='raidz2')?4:2);
                if (count($picked) < $need) { $msg=['err',"وضع $mode يحتاج $need أقراص على الأقل."]; break; }

                if ($mode==='mirror') {
                    if (count($picked)%2!==0){ $msg=['err','mirror يحتاج عدداً زوجياً من الأقراص.']; break; }
                    $vdev=''; for($i=0;$i<count($picked);$i+=2){ $vdev.=' mirror '.escapeshellarg($picked[$i]).' '.escapeshellarg($picked[$i+1]); }
                } elseif ($mode==='raidz1') { $vdev = 'raidz1 '.implode(' ',array_map('escapeshellarg',$picked)); }
                elseif ($mode==='raidz2') { $vdev = 'raidz2 '.implode(' ',array_map('escapeshellarg',$picked)); }
                else { $vdev = implode(' ',array_map('escapeshellarg',$picked)); }

                $cmd = "create -f -o ashift=12 -O compression=lz4 -O atime=off -O xattr=sa "
                     . "-O mountpoint=".escapeshellarg(MOUNT)." ".escapeshellarg(POOL)." ".$vdev;
                $r = z($cmd,$rc);
                $msg = $rc===0 ? ['ok','تم إنشاء التخزين بنجاح ✅'] : ['err','فشل الإنشاء: '.$r];
                break;

            case 'expand':
                if (!pool_exists()){ $msg=['err','لا يوجد pool لتوسيعه.']; break; }
                if ($confirm !== 'CONFIRM'){ $msg=['err','اكتب CONFIRM للتأكيد.']; break; }
                $mode = $_POST['emode'] ?? 'mirror';
                if ($mode==='mirror') {
                    if (count($picked)!==2){ $msg=['err','لتوسيع mirror أضف قرصين (زوجاً).']; break; }
                    $vdev='mirror '.escapeshellarg($picked[0]).' '.escapeshellarg($picked[1]);
                } else {
                    if (count($picked)<1){ $msg=['err','اختر قرصاً واحداً على الأقل.']; break; }
                    $vdev=implode(' ',array_map('escapeshellarg',$picked));
                }
                $r=z("add -f ".escapeshellarg(POOL)." ".$vdev,$rc);
                $msg=$rc===0?['ok','تمت إضافة الأقراص وتوسيع المساحة ✅']:['err','فشل التوسيع: '.$r];
                break;

            case 'replace':
                $old=$_POST['old_disk']??''; $new=$picked[0]??'';
                if (!$old||!$new){ $msg=['err','حدّد القرص التالف والقرص الجديد.']; break; }
                if ($confirm !== 'CONFIRM'){ $msg=['err','اكتب CONFIRM للتأكيد.']; break; }
                $r=z("replace -f ".escapeshellarg(POOL)." ".escapeshellarg($old)." ".escapeshellarg($new),$rc);
                $msg=$rc===0?['ok','بدأ استبدال القرص وإعادة البناء (resilver) ✅']:['err','فشل الاستبدال: '.$r];
                break;

            case 'scrub': z("scrub ".escapeshellarg(POOL)); $msg=['ok','بدأ الفحص (scrub).']; break;
            case 'clear': z("clear ".escapeshellarg(POOL)); $msg=['ok','تم مسح عدّادات الأخطاء.']; break;
        }
    }
}

$h        = pool_health();
$disks    = all_disks();
$sysdisk  = system_disk();
$overall  = badge_class($h['state']);
$freeDisks= array_values(array_filter($disks, function ($disk) {
    return !$disk['is_sys'] && !$disk['used'];
}));
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>لوحة التخزين الذكية — ZFS</title>
<style>
:root{--bg:#0d0f14;--card:#161a23;--card2:#1c2230;--line:#2a3140;--txt:#e8ecf3;--muted:#8b94a7;
--gold:#d4af37;--ok:#2ecc71;--warn:#f1c40f;--err:#e74c3c;}
*{box-sizing:border-box}
body{margin:0;font-family:"Segoe UI",Tahoma,sans-serif;background:var(--bg);color:var(--txt);padding:24px}
.wrap{max-width:1040px;margin:auto}
h1{font-size:22px;margin:0 0 4px;display:flex;align-items:center;gap:10px}
h1 .dot{width:12px;height:12px;border-radius:50%}
.sub{color:var(--muted);font-size:13px;margin-bottom:20px}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:14px;margin-bottom:20px}
.card{background:var(--card);border:1px solid var(--line);border-radius:14px;padding:16px}
.card .k{color:var(--muted);font-size:12px;margin-bottom:6px}
.card .v{font-size:22px;font-weight:700}
.bar{height:10px;border-radius:6px;background:var(--card2);overflow:hidden;margin-top:10px}
.bar>i{display:block;height:100%;border-radius:6px}
.badge{display:inline-block;padding:3px 12px;border-radius:20px;font-size:12px;font-weight:700}
.ok{background:rgba(46,204,113,.15);color:var(--ok)}
.warn{background:rgba(241,196,15,.15);color:var(--warn)}
.err{background:rgba(231,76,60,.15);color:var(--err)}
.muted{background:rgba(139,148,167,.15);color:var(--muted)}
.panel{background:var(--card);border:1px solid var(--line);border-radius:14px;padding:18px;margin-bottom:18px}
.panel h2{font-size:15px;margin:0 0 14px;color:var(--gold)}
table{width:100%;border-collapse:collapse;font-size:13px}
th,td{text-align:right;padding:9px 8px;border-bottom:1px solid var(--line)}
th{color:var(--muted);font-weight:600}
td.dev{font-family:monospace;direction:ltr;text-align:left}
.alert{padding:12px 16px;border-radius:10px;margin-bottom:16px;font-size:14px}
.alert.err{background:rgba(231,76,60,.12);border:1px solid var(--err)}
.alert.warn{background:rgba(241,196,15,.12);border:1px solid var(--warn)}
.alert.ok{background:rgba(46,204,113,.12);border:1px solid var(--ok)}
pre{background:#0a0c11;border:1px solid var(--line);border-radius:10px;padding:14px;overflow:auto;
font-size:12px;direction:ltr;text-align:left;color:#c9d3e3}
.disk{display:flex;align-items:center;gap:10px;padding:10px 12px;border:1px solid var(--line);
border-radius:10px;margin-bottom:8px;background:var(--card2)}
.disk.sys{opacity:.55}
.nm{font-family:monospace;direction:ltr}
.tag{margin-inline-start:auto;font-size:11px}
label.opt{display:flex;gap:8px;align-items:center;cursor:pointer}
select,input[type=text],input[type=password]{background:var(--card2);border:1px solid var(--line);
color:var(--txt);padding:9px 12px;border-radius:8px}
input[type=text],input[type=password]{direction:ltr}
button{background:var(--gold);color:#1a1a1a;border:0;padding:10px 20px;border-radius:8px;font-weight:700;cursor:pointer}
button.ghost{background:transparent;border:1px solid var(--line);color:var(--txt)}
button.danger{background:var(--err);color:#fff}
.row{display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-top:12px}
.hint{color:var(--muted);font-size:12px;margin-top:8px}
.foot{color:var(--muted);font-size:12px;margin-top:20px;text-align:center}
</style>
</head>
<body>
<div class="wrap">

  <h1><span class="dot" style="background:var(--<?= $overall ?>)"></span>
      لوحة التخزين الذكية — «<?= htmlspecialchars(POOL) ?>»</h1>
  <div class="sub">مسار التركيب: <code><?= htmlspecialchars(MOUNT) ?></code> · قرص النظام المحمي: <code><?= htmlspecialchars($sysdisk) ?></code></div>

  <?php if ($msg): ?><div class="alert <?= $msg[0] ?>"><?= htmlspecialchars($msg[1]) ?></div><?php endif; ?>

  <?php if (!pool_exists()): ?>
    <div class="alert warn">لا يوجد تخزين بعد. اختر الأقراص وأنشئ الـ pool من الأسفل.</div>
    <div class="panel">
      <h2>إنشاء التخزين</h2>
      <?php if (empty($freeDisks)): ?>
        <div class="alert err">لا توجد أقراص فارغة متاحة. أضف أقراصاً (قرصين على الأقل للحماية) ثم حدّث الصفحة.</div>
      <?php endif; ?>
      <form method="post" onsubmit="return confirmCreate(this)">
        <?= csrfField() ?>
        <div style="margin-bottom:14px">
          <div class="hint" style="margin-bottom:8px">اختر الأقراص (قرص النظام مستبعد ومحمي):</div>
          <?php foreach ($disks as $d): ?>
            <div class="disk <?= $d['is_sys']?'sys':'' ?>">
              <?php if ($d['is_sys']): ?>
                <input type="checkbox" disabled>
                <span class="nm"><?= htmlspecialchars($d['name']) ?></span>
                <span class="muted badge tag">قرص النظام — محمي</span>
              <?php elseif ($d['used']): ?>
                <input type="checkbox" disabled>
                <span class="nm"><?= htmlspecialchars($d['name']) ?></span>
                <span class="warn badge tag">مستخدم (به أقسام)</span>
              <?php else: ?>
                <label class="opt" style="width:100%">
                  <input type="checkbox" name="disks[]" value="<?= htmlspecialchars($d['name']) ?>">
                  <span class="nm"><?= htmlspecialchars($d['name']) ?></span>
                  <span class="muted tag"><?= htmlspecialchars($d['size'].' · '.$d['model']) ?></span>
                </label>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="row">
          <label>وضع الحماية:
            <select name="mode">
              <option value="mirror">mirror — أزواج محمية (موصى به للأحجام المختلفة + التوسيع)</option>
              <option value="raidz1">raidz1 — يتحمل تلف قرص (3 أقراص+)</option>
              <option value="raidz2">raidz2 — يتحمل تلف قرصين (4 أقراص+)</option>
              <option value="single">single — قرص واحد بلا حماية</option>
            </select>
          </label>
        </div>
        <div class="row">
          <input type="text" name="confirm" placeholder="اكتب: CONFIRM" required>
          <button type="submit" name="action" value="create" class="danger">إنشاء التخزين (يمسح الأقراص)</button>
        </div>
        <div class="hint">⚠️ الإنشاء يمسح كل بيانات الأقراص المختارة نهائياً. قرص النظام لا يظهر للاختيار.</div>
      </form>
    </div>

  <?php else: ?>

    <?php if ($h['state']!=='ONLINE'): ?>
      <div class="alert <?= $overall ?>">
        ⚠️ حالة التخزين: <strong><?= htmlspecialchars($h['state']) ?></strong>.
        <?php if ($h['state']==='DEGRADED'): ?> أحد الأقراص تالف — <strong>البيانات سليمة والعمل مستمر</strong>. استبدل القرص من قسم «استبدال قرص».<?php endif; ?>
      </div>
    <?php endif; ?>

    <div class="grid">
      <div class="card"><div class="k">الحالة</div><div class="v"><span class="badge <?= $overall ?>"><?= htmlspecialchars($h['state']) ?></span></div></div>
      <div class="card"><div class="k">السعة الكلية</div><div class="v"><?= htmlspecialchars($h['size']) ?></div></div>
      <div class="card"><div class="k">المستخدم</div><div class="v"><?= htmlspecialchars($h['alloc']) ?></div></div>
      <div class="card"><div class="k">المتبقي</div><div class="v"><?= htmlspecialchars($h['free']) ?></div></div>
      <div class="card" style="grid-column:1/-1">
        <div class="k">نسبة الاستخدام — <?= (int)$h['cap'] ?>%</div>
        <div class="bar"><i style="width:<?= min(100,(int)$h['cap']) ?>%;background:<?= $h['cap']>=90?'var(--err)':($h['cap']>=75?'var(--warn)':'var(--ok)') ?>"></i></div>
      </div>
    </div>

    <?php if (!empty($h['scan']) && stripos($h['scan'],'none requested')===false): ?>
      <div class="panel"><h2>عملية جارية (فحص / إعادة بناء)</h2><pre><?= htmlspecialchars($h['scan']) ?></pre></div>
    <?php endif; ?>

    <div class="panel">
      <h2>الأقراص والأجهزة</h2>
      <table>
        <thead><tr><th>الجهاز</th><th>الحالة</th><th>قراءة</th><th>كتابة</th><th>تلف بيانات</th></tr></thead>
        <tbody>
        <?php foreach ($h['devices'] as $d): $db=badge_class($d['state']); $pad=str_repeat('&nbsp;',max(0,$d['indent']-4)*2); ?>
          <tr>
            <td class="dev"><?= $pad.htmlspecialchars($d['name']) ?></td>
            <td><span class="badge <?= $db ?>"><?= htmlspecialchars($d['state']) ?></span></td>
            <td style="color:<?= $d['read']?'var(--err)':'inherit' ?>"><?= $d['read'] ?></td>
            <td style="color:<?= $d['write']?'var(--err)':'inherit' ?>"><?= $d['write'] ?></td>
            <td style="color:<?= $d['cksum']?'var(--err)':'inherit' ?>"><?= $d['cksum'] ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="panel">
      <h2>توسيع المساحة (إضافة أقراص)</h2>
      <?php if (empty($freeDisks)): ?>
        <div class="hint">لا توجد أقراص فارغة لإضافتها. أضف أقراصاً جديدة ثم حدّث الصفحة.</div>
      <?php else: ?>
        <form method="post" onsubmit="return confirmGeneric('توسيع المساحة')">
          <?= csrfField() ?>
          <?php foreach ($freeDisks as $d): ?>
            <label class="opt" style="margin-bottom:6px">
              <input type="checkbox" name="disks[]" value="<?= htmlspecialchars($d['name']) ?>">
              <span class="nm"><?= htmlspecialchars($d['name']) ?></span>
              <span class="muted"><?= htmlspecialchars($d['size'].' · '.$d['model']) ?></span>
            </label>
          <?php endforeach; ?>
          <div class="row">
            <label>الوضع:
              <select name="emode">
                <option value="mirror">إضافة زوج mirror (قرصان)</option>
                <option value="stripe">إضافة للـ raidz/single (قرص فأكثر)</option>
              </select>
            </label>
          </div>
          <div class="row">
            <input type="text" name="confirm" placeholder="اكتب: CONFIRM" required>
            <button type="submit" name="action" value="expand">إضافة وتوسيع</button>
          </div>
        </form>
      <?php endif; ?>
    </div>

    <div class="panel">
      <h2>استبدال قرص تالف</h2>
      <form method="post" onsubmit="return confirmGeneric('استبدال القرص')">
        <?= csrfField() ?>
        <div class="row">
          <label>القرص التالف:
            <select name="old_disk" required>
              <option value="">— اختر —</option>
              <?php foreach ($h['devices'] as $d):
                    $isvdev = (strpos($d['name'],'mirror')===0 || strpos($d['name'],'raidz')===0 || $d['name']===POOL);
                    if (!$isvdev): ?>
                <option value="<?= htmlspecialchars($d['name']) ?>"><?= htmlspecialchars($d['name'].' ('.$d['state'].')') ?></option>
              <?php endif; endforeach; ?>
            </select>
          </label>
        </div>
        <div style="margin-top:10px">
          <div class="hint" style="margin-bottom:8px">القرص الجديد البديل:</div>
          <?php if (empty($freeDisks)): ?>
            <div class="hint">لا يوجد قرص بديل فارغ. أضف قرصاً جديداً أولاً.</div>
          <?php else: foreach ($freeDisks as $d): ?>
            <label class="opt" style="margin-bottom:6px">
              <input type="radio" name="disks[]" value="<?= htmlspecialchars($d['name']) ?>">
              <span class="nm"><?= htmlspecialchars($d['name']) ?></span>
              <span class="muted"><?= htmlspecialchars($d['size']) ?></span>
            </label>
          <?php endforeach; endif; ?>
        </div>
        <div class="row">
          <input type="text" name="confirm" placeholder="اكتب: CONFIRM" required>
          <button type="submit" name="action" value="replace">استبدال</button>
        </div>
      </form>
    </div>

    <div class="panel">
      <h2>صيانة</h2>
      <form method="post" class="row">
        <?= csrfField() ?>
        <button type="submit" name="action" value="scrub">فحص البيانات (Scrub)</button>
        <button type="submit" name="action" value="clear" class="ghost">مسح عدّادات الأخطاء</button>
      </form>
    </div>

    <div class="panel"><h2>zpool status (خام)</h2><pre><?= htmlspecialchars($h['raw']) ?></pre></div>
  <?php endif; ?>

  <div class="foot">storage_manager.php · لوحة ZFS ذكية · لا تكشفها للعامة دون مصادقة</div>
</div>

<script>
// Attach the session token to every state-changing form in this standalone
// maintenance page. This also protects forms added by future edits.
document.querySelectorAll('form[method="post"]').forEach(function (form) {
  if (!form.querySelector('input[name="csrf_token"]')) {
    var token = document.createElement('input');
    token.type = 'hidden';
    token.name = 'csrf_token';
    token.value = <?= json_encode(csrfToken(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    form.appendChild(token);
  }
});
function confirmCreate(f){
  const picked=[...f.querySelectorAll('input[name="disks[]"]:checked')].map(x=>x.value);
  if(!picked.length){alert('اختر قرصاً واحداً على الأقل');return false;}
  return confirm('⚠️ سيتم مسح هذه الأقراص نهائياً:\n'+picked.join('\n')+'\n\nهل أنت متأكد؟');
}
function confirmGeneric(label){ return confirm('تأكيد: '+label+'؟\nراجع اختياراتك جيداً.'); }
</script>
</body>
</html>

<?php
/* ============================================================
 * تعليمات التركيب (مرة واحدة):
 *
 * 1) صلاحية sudoers لمستخدم الويب (www-data) على أوامر ZFS فقط:
 *      sudo visudo -f /etc/sudoers.d/storage-manager
 *    وأضف هذا السطر:
 *      www-data ALL=(root) NOPASSWD: /usr/sbin/zpool, /usr/sbin/zfs
 *    (تحقق من المسار: which zpool — بدّله إن لزم)
 *
 * 2) www-data يحتاج قراءة lsblk/findmnt (متاحة افتراضياً).
 * 3) الحماية عبر Referer: لا تُفتح الصفحة إلا بإحالة من admin.php أو من نفسها.
 *    الدخول المباشر يُحوَّل لـ index.php. (تشمل GET و POST)
 * 4) تنبيه: الـ Referer قابل للتزوير — مناسب لشبكة داخلية موثوقة فقط.
 * ============================================================ */
