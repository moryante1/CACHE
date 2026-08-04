<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 *  SHASHETY PRO — واجهة AJAX لنظام الاشتراكات
 * ───────────────────────────────────────────────────────────────────────────
 *  نقطة نهاية مستقلة عمداً، ولم تُضف إلى ajax/handlers.php.
 *
 *  السبب: ذلك الملف كتلة `if (isset($_POST['ajax_action'])) { ... }` واحدة
 *  طولها آلاف الأسطر ولا يمكن تقسيمها بين ملفات (PHP يحلّل كل ملف مستقلاً
 *  فينكسر التحليل). أي إضافة هناك تعني تعديل ملف هشّ يمرّ منه كل شيء في
 *  اللوحة. هنا نحصل على نفس الحماية (جلسة + مدير + CSRF) في ملف معزول:
 *  عطل في الاشتراكات لن يُسقط رفع الفيديو أو استيراد Xtream.
 * ═══════════════════════════════════════════════════════════════════════════
 */

require_once __DIR__ . '/config/bootstrap.php';
require_once __DIR__ . '/functions/subscriptions.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');

/** ردّ JSON وإنهاء. */
function sApiSend(array $p, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($p, JSON_UNESCAPED_UNICODE);
    exit;
}
function sOk(array $d = []): void  { sApiSend(array_merge(['success'=>true], $d)); }
function sErr(string $m, int $c = 400): void { sApiSend(['success'=>false,'error'=>$m], $c); }

/**
 * يسجّل التفاصيل الفنية ويُرجع رسالة عامة.
 * رسائل استثناءات قاعدة البيانات تحوي أسماء الجداول والأعمدة وأحياناً
 * أجزاء من الاستعلام — وهي خريطة مجانية للمهاجم.
 */
function sErrLog(string $userMsg, ?Throwable $e = null): void
{
    if ($e && function_exists('logTo')) {
        logTo('error', 'subs_api: ' . $e->getMessage(), ['file'=>$e->getFile(),'line'=>$e->getLine()]);
    }
    sErr($userMsg, 500);
}

// ── الحراسة: ترتيب الفحوص مقصود ──
// المصادقة أولاً ثم CSRF. لو عُكس الترتيب لكشفنا صلاحية الرموز لغير المسجّلين.
if (!isAdminLoggedIn())      sErr('unauthorized', 401);
if (!csrfValidate())         sErr('csrf', 419);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') sErr('method', 405);

if (!subsEnsureSchema()) sErr('schema_failed', 500);

$act   = (string)($_POST['act'] ?? '');
$admin = (string)($_SESSION['admin_username'] ?? 'admin');

// تنظيف دوري رخيص: كل نداء إداري يعطّل ما انتهى
subsExpireDue();

try {
switch ($act) {

// ═══════════════════════════════════════════════════════════════════
//  الخطط
// ═══════════════════════════════════════════════════════════════════
case 'plans_list':
    sOk(['plans' => subsPlans(), 'currency' => subsCurrency()]);
    break;

case 'plan_save': {
    $id   = (int)($_POST['id'] ?? 0);
    $code = strtolower(trim((string)($_POST['code'] ?? '')));
    $days = (int)($_POST['duration_days'] ?? 0);

    if (!preg_match('/^[a-z0-9_]{2,32}$/', $code)) sErr('bad_code');
    if ($days < 1 || $days > 36500)                sErr('bad_duration');

    $nameAr = trim((string)($_POST['name_ar'] ?? ''));
    if ($nameAr === '') sErr('bad_name');

    $price  = round((float)($_POST['price'] ?? 0), 2);
    if ($price < 0) $price = 0;

    $args = [
        $code, $nameAr,
        trim((string)($_POST['name_en'] ?? '')),
        trim((string)($_POST['name_tr'] ?? '')),
        $days, $price,
        (int)!empty($_POST['is_active']),
        (int)($_POST['sort_order'] ?? 0),
    ];

    if ($id > 0) {
        $st = db()->prepare(
            "UPDATE subscription_plans
             SET code=?,name_ar=?,name_en=?,name_tr=?,duration_days=?,price=?,is_active=?,sort_order=?
             WHERE id=?"
        );
        $args[] = $id;
        $st->execute($args);
        sOk(['id'=>$id]);
    } else {
        $st = db()->prepare(
            "INSERT INTO subscription_plans (code,name_ar,name_en,name_tr,duration_days,price,is_active,sort_order)
             VALUES (?,?,?,?,?,?,?,?)"
        );
        $st->execute($args);
        sOk(['id'=>(int)db()->lastInsertId()]);
    }
    break;
}

case 'plan_toggle': {
    $id = (int)($_POST['id'] ?? 0);
    if ($id < 1) sErr('bad_id');
    $st = db()->prepare("UPDATE subscription_plans SET is_active = 1 - is_active WHERE id=?");
    $st->execute([$id]);
    $p = subsPlan($id);
    sOk(['is_active' => (int)($p['is_active'] ?? 0)]);
    break;
}

case 'plan_delete': {
    $id = (int)($_POST['id'] ?? 0);
    if ($id < 1) sErr('bad_id');

    // لا نحذف خطة يعتمد عليها مشتركون أو كوبونات — الحذف الصامت
    // كان سيترك مشتركين بخطة مجهولة وكوبونات مباعة بلا قيمة.
    $c1 = db()->prepare("SELECT COUNT(*) FROM site_users WHERE plan_id=?");
    $c1->execute([$id]);
    $c2 = db()->prepare("SELECT COUNT(*) FROM coupons WHERE plan_id=? AND is_active=1");
    $c2->execute([$id]);
    $u = (int)$c1->fetchColumn();
    $k = (int)$c2->fetchColumn();
    if ($u > 0 || $k > 0) sApiSend(['success'=>false,'error'=>'plan_in_use','users'=>$u,'coupons'=>$k], 409);

    $st = db()->prepare("DELETE FROM subscription_plans WHERE id=?");
    $st->execute([$id]);
    sOk();
    break;
}

// ═══════════════════════════════════════════════════════════════════
//  الكوبونات
// ═══════════════════════════════════════════════════════════════════
case 'coupons_list': {
    $q      = trim((string)($_POST['q'] ?? ''));
    $status = (string)($_POST['status'] ?? 'all');
    $plan   = (int)($_POST['plan_id'] ?? 0);
    $page   = max(1, (int)($_POST['page'] ?? 1));
    $per    = 50;

    $w = []; $a = [];
    if ($q !== '') {
        // نبحث في الكود والملاحظة والمستخدم الأخير
        $w[] = "(c.code LIKE ? OR c.note LIKE ? OR c.last_used_by LIKE ?)";
        $like = '%' . $q . '%';
        array_push($a, $like, $like, $like);
    }
    if ($plan > 0)              { $w[] = "c.plan_id = ?";  $a[] = $plan; }
    if ($status === 'active')   { $w[] = "c.is_active=1 AND c.used_count < c.max_uses"; }
    if ($status === 'used')     { $w[] = "c.used_count >= c.max_uses"; }
    if ($status === 'disabled') { $w[] = "c.is_active=0"; }
    $where = $w ? ('WHERE ' . implode(' AND ', $w)) : '';

    $cnt = db()->prepare("SELECT COUNT(*) FROM coupons c $where");
    $cnt->execute($a);
    $total = (int)$cnt->fetchColumn();

    // LIMIT/OFFSET مُدرجان كأعداد صحيحة مُصرَّح بها لا كمعاملات:
    // MySQL يرفض ربط LIMIT في الوضع المُهيَّأ، والقيمتان مرّتا بـ (int).
    $off = ($page - 1) * $per;
    $st = db()->prepare(
        "SELECT c.*, p.name_ar AS plan_name, p.code AS plan_code
         FROM coupons c LEFT JOIN subscription_plans p ON p.id=c.plan_id
         $where ORDER BY c.id DESC LIMIT $per OFFSET $off"
    );
    $st->execute($a);
    sOk(['coupons'=>$st->fetchAll(PDO::FETCH_ASSOC), 'total'=>$total, 'page'=>$page, 'per'=>$per]);
    break;
}

case 'coupons_create': {
    $planId  = (int)($_POST['plan_id'] ?? 0);
    $count   = (int)($_POST['count'] ?? 1);
    $maxUses = (int)($_POST['max_uses'] ?? 1);
    $exp     = trim((string)($_POST['expires_at'] ?? ''));
    $note    = mb_substr(trim((string)($_POST['note'] ?? '')), 0, 255);

    if ($exp !== '') {
        $d = DateTime::createFromFormat('Y-m-d', $exp) ?: DateTime::createFromFormat('Y-m-d H:i:s', $exp);
        if (!$d) sErr('bad_date');
        $exp = $d->format('Y-m-d 23:59:59');
    }

    $r = subsCreateCoupons($planId, $count, $maxUses, $exp ?: null, $note, $admin);
    if (!$r['ok']) sErr($r['error']);
    sOk(['codes'=>$r['codes'], 'count'=>count($r['codes'])]);
    break;
}

case 'coupon_toggle': {
    $id = (int)($_POST['id'] ?? 0);
    if ($id < 1) sErr('bad_id');
    $st = db()->prepare("UPDATE coupons SET is_active = 1 - is_active WHERE id=?");
    $st->execute([$id]);
    $g = db()->prepare("SELECT is_active FROM coupons WHERE id=?");
    $g->execute([$id]);
    sOk(['is_active'=>(int)$g->fetchColumn()]);
    break;
}

case 'coupon_delete': {
    $id = (int)($_POST['id'] ?? 0);
    if ($id < 1) sErr('bad_id');
    $st = db()->prepare("DELETE FROM coupons WHERE id=?");
    $st->execute([$id]);
    sOk();
    break;
}

case 'coupons_delete_used': {
    // تنظيف: حذف الكوبونات المستهلكة بالكامل
    $st = db()->query("DELETE FROM coupons WHERE used_count >= max_uses");
    sOk(['deleted' => $st->rowCount()]);
    break;
}

case 'coupon_history': {
    $id = (int)($_POST['id'] ?? 0);
    if ($id < 1) sErr('bad_id');
    $st = db()->prepare(
        "SELECT r.*, u.username AS uname
         FROM coupon_redemptions r LEFT JOIN site_users u ON u.id=r.user_id
         WHERE r.coupon_id=? ORDER BY r.id DESC LIMIT 200"
    );
    $st->execute([$id]);
    sOk(['rows'=>$st->fetchAll(PDO::FETCH_ASSOC)]);
    break;
}

// ═══════════════════════════════════════════════════════════════════
//  المشتركون
// ═══════════════════════════════════════════════════════════════════
case 'subs_list': {
    $q      = trim((string)($_POST['q'] ?? ''));
    $status = (string)($_POST['status'] ?? 'all');
    $page   = max(1, (int)($_POST['page'] ?? 1));
    $per    = 50;

    $w = []; $a = [];
    if ($q !== '') {
        $w[] = "(u.username LIKE ? OR u.email LIKE ?)";
        $like = '%' . $q . '%';
        array_push($a, $like, $like);
    }
    if ($status === 'active')   $w[] = "u.is_active=1 AND (u.sub_end IS NULL OR u.sub_end>=NOW())";
    if ($status === 'expired')  $w[] = "u.sub_end IS NOT NULL AND u.sub_end<NOW()";
    if ($status === 'pending')  $w[] = "u.is_active=0 AND u.sub_end IS NULL";
    if ($status === 'disabled') $w[] = "u.is_active=0 AND u.sub_end IS NOT NULL AND u.sub_end>=NOW()";
    $where = $w ? ('WHERE ' . implode(' AND ', $w)) : '';

    $cnt = db()->prepare("SELECT COUNT(*) FROM site_users u $where");
    $cnt->execute($a);
    $total = (int)$cnt->fetchColumn();

    $off = ($page - 1) * $per;
    $st = db()->prepare(
        "SELECT u.id,u.username,u.email,u.is_active,u.plan_id,u.sub_start,u.sub_end,
                u.activated_via,u.last_login,u.last_ip,u.notes,u.created_at,
                p.name_ar AS plan_name, p.code AS plan_code
         FROM site_users u LEFT JOIN subscription_plans p ON p.id=u.plan_id
         $where ORDER BY u.id DESC LIMIT $per OFFSET $off"
    );
    $st->execute($a);

    // نحسب الحالة والأيام المتبقية بنفس دالة الموقع — لا منطق مكرر
    $rows = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $s = subsUserStatus($r);
        $r['state']     = $s['state'];
        $r['days_left'] = $s['days_left'];
        unset($r['password']);
        $rows[] = $r;
    }
    sOk(['users'=>$rows, 'total'=>$total, 'page'=>$page, 'per'=>$per, 'stats'=>subsStats()]);
    break;
}

case 'sub_create': {
    $r = subsRegister(
        (string)($_POST['username'] ?? ''),
        (string)($_POST['password'] ?? ''),
        (string)($_POST['email'] ?? '')
    );
    if (!$r['ok']) sErr($r['error']);

    // تفعيل فوري إن طُلب
    $days = (int)($_POST['days'] ?? 0);
    $plan = (int)($_POST['plan_id'] ?? 0);
    if (!empty($_POST['activate'])) {
        if ($days === 0 && $plan > 0) { $p = subsPlan($plan); $days = (int)($p['duration_days'] ?? 0); }
        subsAdminActivate((int)$r['id'], $plan ?: null, $days, $admin);
    }
    sOk(['id'=>$r['id']]);
    break;
}

case 'sub_update': {
    $id = (int)($_POST['id'] ?? 0);
    if ($id < 1 || !subsUserById($id)) sErr('user_not_found', 404);

    $email = trim((string)($_POST['email'] ?? ''));
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) sErr('invalid_email');
    $notes = mb_substr(trim((string)($_POST['notes'] ?? '')), 0, 2000);

    $st = db()->prepare("UPDATE site_users SET email=?, notes=? WHERE id=?");
    $st->execute([$email ?: null, $notes ?: null, $id]);

    // كلمة المرور اختيارية — لا نلمسها إن تُركت فارغة
    $pw = (string)($_POST['password'] ?? '');
    if ($pw !== '') {
        if (strlen($pw) < 8) sErr('weak_password');
        $p = db()->prepare("UPDATE site_users SET password=? WHERE id=?");
        $p->execute([password_hash($pw, PASSWORD_DEFAULT), $id]);
    }
    sOk();
    break;
}

case 'sub_activate': {
    $id   = (int)($_POST['id'] ?? 0);
    $plan = (int)($_POST['plan_id'] ?? 0);
    $days = (int)($_POST['days'] ?? 0);
    if ($id < 1) sErr('bad_id');

    // مدة من الخطة إن لم تُحدَّد صراحةً
    if ($days === 0 && $plan > 0) { $p = subsPlan($plan); $days = (int)($p['duration_days'] ?? 0); }
    if ($days < 0 || $days > 36500) sErr('bad_duration');

    if (!subsAdminActivate($id, $plan ?: null, $days, $admin)) sErr('server', 500);
    $u = subsUserById($id);
    sOk(['user'=>['id'=>$id,'sub_end'=>$u['sub_end'] ?? null] + subsUserStatus($u)]);
    break;
}

case 'sub_deactivate': {
    $id = (int)($_POST['id'] ?? 0);
    if ($id < 1) sErr('bad_id');
    if (!subsAdminDeactivate($id)) sErr('server', 500);
    sOk();
    break;
}

case 'sub_extend': {
    // تمديد بعدد أيام دون تغيير الخطة
    $id   = (int)($_POST['id'] ?? 0);
    $days = (int)($_POST['days'] ?? 0);
    if ($id < 1) sErr('bad_id');
    if ($days === 0 || $days < -36500 || $days > 36500) sErr('bad_duration');

    $u = subsUserById($id);
    if (!$u) sErr('user_not_found', 404);

    // نبني على تاريخ الانتهاء إن كان مستقبلياً، وإلا من الآن
    $base = (!empty($u['sub_end']) && strtotime($u['sub_end']) > time())
            ? $u['sub_end'] : date('Y-m-d H:i:s');
    $newEnd = date('Y-m-d H:i:s', strtotime($base . ($days >= 0 ? " +$days days" : " $days days")));

    $st = db()->prepare(
        "UPDATE site_users
         SET sub_end=?, sub_start=COALESCE(sub_start,NOW()), is_active=IF(?>NOW(),1,0)
         WHERE id=?"
    );
    $st->execute([$newEnd, $newEnd, $id]);
    sOk(['sub_end'=>$newEnd]);
    break;
}

case 'sub_delete': {
    $id = (int)($_POST['id'] ?? 0);
    if ($id < 1) sErr('bad_id');
    // coupon_redemptions تُحذف تلقائياً بالمفتاح الأجنبي ON DELETE CASCADE
    $st = db()->prepare("DELETE FROM site_users WHERE id=?");
    $st->execute([$id]);
    sOk();
    break;
}

case 'sub_logs': {
    $id = (int)($_POST['id'] ?? 0);
    $st = $id > 0
        ? db()->prepare("SELECT * FROM site_login_logs WHERE user_id=? ORDER BY id DESC LIMIT 100")
        : db()->prepare("SELECT * FROM site_login_logs ORDER BY id DESC LIMIT 200");
    $id > 0 ? $st->execute([$id]) : $st->execute();
    sOk(['rows'=>$st->fetchAll(PDO::FETCH_ASSOC)]);
    break;
}

// ═══════════════════════════════════════════════════════════════════
//  الإعدادات
// ═══════════════════════════════════════════════════════════════════
case 'settings_get':
    sOk(['settings'=>[
        'index_protection'   => subsSetting('index_protection','0'),
        'allow_registration' => subsSetting('allow_registration','1'),
        'currency_symbol'    => subsSetting('currency_symbol','$'),
        'currency_code'      => subsSetting('currency_code','USD'),
    ]]);
    break;

case 'settings_save': {
    $prot = !empty($_POST['index_protection']) ? '1' : '0';
    $reg  = !empty($_POST['allow_registration']) ? '1' : '0';
    $sym  = mb_substr(trim((string)($_POST['currency_symbol'] ?? '$')), 0, 8);
    $cod  = strtoupper(preg_replace('/[^A-Za-z]/', '', (string)($_POST['currency_code'] ?? 'USD')));
    if ($sym === '') $sym = '$';
    if ($cod === '') $cod = 'USD';

    subsSetSetting('index_protection',   $prot);
    subsSetSetting('allow_registration', $reg);
    subsSetSetting('currency_symbol',    $sym);
    subsSetSetting('currency_code',      mb_substr($cod, 0, 8));

    if (function_exists('logTo')) {
        logTo('admin', "تغيير إعدادات الاشتراكات: حماية=$prot تسجيل=$reg بواسطة $admin");
    }
    sOk();
    break;
}

case 'stats':
    sOk(['stats'=>subsStats()]);
    break;

// ═══════════════════════════════════════════════════════════════════
//  وسيط إعادة البثّ (تحويل صوت AC3 إلى AAC)
// ═══════════════════════════════════════════════════════════════════
case 'restream_status': {
    require_once __DIR__ . '/functions/restream.php';
    $s = rsStatus();

    // هل ffmpeg متاح أصلاً؟ بدونه المفتاح بلا معنى
    $ffOut = @shell_exec('ffmpeg -version 2>/dev/null');
    $s['ffmpeg'] = $ffOut ? trim(strtok((string)$ffOut, "\n")) : '';
    // shell_exec معطّلة تعني أن كل شيء آخر بلا معنى — نُظهرها صراحةً
    $s['can_exec'] = rsCanExec();

    // استهلاك المعالج الكلي لعمليات ffmpeg
    $cpu = 0.0;
    $psOut = @shell_exec('ps -o %cpu= -C ffmpeg 2>/dev/null');
    foreach (preg_split('/\R/', (string)$psOut) ?: [] as $l) {
        $l = trim($l);
        if ($l !== '') $cpu += (float)$l;
    }
    $s['cpu'] = round($cpu, 1);
    $s['cores'] = (int)@shell_exec('nproc 2>/dev/null') ?: 1;

    // المساحة المتاحة في مجلد المقاطع
    $free = @disk_free_space(rsRoot());
    $s['free_mb'] = $free ? (int)round($free / 1048576) : 0;

    sOk(['restream' => $s]);
    break;
}

case 'restream_toggle': {
    require_once __DIR__ . '/functions/restream.php';
    $on = !empty($_POST['on']);

    if ($on && !@shell_exec('ffmpeg -version 2>/dev/null')) {
        sErr('ffmpeg_missing');
    }
    if (!rsSetEnabled($on)) sErr('server', 500);

    if (function_exists('logTo')) {
        logTo('admin', 'وسيط إعادة البثّ: ' . ($on ? 'تفعيل' : 'إيقاف') . " بواسطة $admin");
    }
    sOk(['enabled' => $on]);
    break;
}

case 'restream_stop_all': {
    require_once __DIR__ . '/functions/restream.php';
    $n = rsStopAll();
    if (function_exists('logTo')) logTo('admin', "إنهاء $n قناة إعادة بثّ بواسطة $admin");
    sOk(['stopped' => $n]);
    break;
}

case 'restream_set_limit': {
    require_once __DIR__ . '/functions/restream.php';
    $n = (int)($_POST['limit'] ?? 0);
    if ($n < 1 || $n > 500) sErr('bad_limit');   // نطاق معقول
    if (!rsSetMaxChannels($n)) sErr('server', 500);
    if (function_exists('logTo')) logTo('admin', "حدّ قنوات الوسيط ← $n بواسطة $admin");
    sOk(['max' => rsMaxChannels()]);
    break;
}

default:
    sErr('unknown_action', 404);
}

} catch (Throwable $e) {
    sErrLog('server_error', $e);
}
