<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 *  SHASHETY PRO — مكتبة الاشتراكات والكوبونات
 * ───────────────────────────────────────────────────────────────────────────
 *  ملف واحد يحوي كل منطق العمل، تستدعيه لوحة الإدارة وواجهة الموقع معاً.
 *  السبب في توحيده: لو تكرّر حساب "هل الاشتراك منتهٍ؟" في مكانين، فسينحرف
 *  أحدهما عن الآخر مع الوقت — وستُتاح الخدمة لحساب منتهٍ من جهة واحدة فقط.
 *  هنا مصدر حقيقة واحد: subsUserStatus().
 *
 *  يعتمد على: core/config.php  (db(), logTo(), rateLimit())
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (!defined('SUBS_LIB')) {
    define('SUBS_LIB', '1.0.0');

// ═══════════════════════════════════════════════════════════════════════════
//  1) تهيئة المخطط — تُنفَّذ مرة واحدة ثم تُعطَّل بعلامة ملف
// ═══════════════════════════════════════════════════════════════════════════

/**
 * ينشئ جداول النظام إن لم تكن موجودة.
 *
 * لماذا علامة ملف؟ لأن تنفيذ خمسة CREATE TABLE في كل طلب HTTP يكلّف
 * رحلات ذهاب وإياب إلى MySQL بلا داعٍ. لكن — وهذا درس من خطأ سابق في
 * helpers.php — العلامة تُكتب **فقط بعد التأكد من وجود الجداول فعلاً**،
 * لا بمجرد انتهاء الاستعلامات. وإلا فإن فشل CREATE مرة واحدة يجعل النظام
 * يعتقد للأبد أن كل شيء جاهز.
 */
function subsEnsureSchema(): bool
{
    static $done = null;
    if ($done !== null) return $done;

    $flag = __DIR__ . '/../storage/.subs_schema_ok';
    if (is_file($flag)) { return $done = true; }

    try {
        $pdo = db();
        if (!$pdo) return $done = false;

        // ── إنشاء الجداول ──
        // كل جملة في try خاصّ بها. لو جُمعت في try واحد لأوقف فشلُ الأولى
        // تنفيذَ الأربع الباقية، فيتحوّل خلل في جدول واحد إلى غياب الخمسة
        // ويصعب معرفة أيها السبب.
        $ddl = [
            'subscription_plans' => "CREATE TABLE IF NOT EXISTS `subscription_plans` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `code` VARCHAR(32) NOT NULL,
            `name_ar` VARCHAR(100) NOT NULL,
            `name_en` VARCHAR(100) NOT NULL DEFAULT '',
            `name_tr` VARCHAR(100) NOT NULL DEFAULT '',
            `duration_days` INT NOT NULL DEFAULT 30,
            `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `sort_order` INT NOT NULL DEFAULT 0,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `uq_plan_code` (`code`),
            KEY `idx_plan_active` (`is_active`,`sort_order`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'site_users' => "CREATE TABLE IF NOT EXISTS `site_users` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `username` VARCHAR(50) NOT NULL,
            `password` VARCHAR(255) NOT NULL,
            `email` VARCHAR(100) DEFAULT NULL,
            `is_active` TINYINT(1) NOT NULL DEFAULT 0,
            `plan_id` INT DEFAULT NULL,
            `sub_start` DATETIME DEFAULT NULL,
            `sub_end` DATETIME DEFAULT NULL,
            `activated_via` ENUM('coupon','admin','none') NOT NULL DEFAULT 'none',
            `last_login` DATETIME DEFAULT NULL,
            `last_ip` VARCHAR(45) DEFAULT NULL,
            `notes` TEXT,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `uq_site_user` (`username`),
            KEY `idx_su_active` (`is_active`,`sub_end`),
            KEY `idx_su_plan` (`plan_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'coupons' => "CREATE TABLE IF NOT EXISTS `coupons` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `code` VARCHAR(32) NOT NULL,
            `plan_id` INT DEFAULT NULL,
            `duration_days` INT NOT NULL DEFAULT 30,
            `max_uses` INT NOT NULL DEFAULT 1,
            `used_count` INT NOT NULL DEFAULT 0,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `expires_at` DATETIME DEFAULT NULL,
            `note` VARCHAR(255) DEFAULT NULL,
            `created_by` VARCHAR(50) DEFAULT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `last_used_at` DATETIME DEFAULT NULL,
            `last_used_by` VARCHAR(50) DEFAULT NULL,
            UNIQUE KEY `uq_coupon_code` (`code`),
            KEY `idx_cp_active` (`is_active`),
            KEY `idx_cp_plan` (`plan_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'coupon_redemptions' => "CREATE TABLE IF NOT EXISTS `coupon_redemptions` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `coupon_id` INT NOT NULL,
            `user_id` INT NOT NULL,
            `username` VARCHAR(50) DEFAULT NULL,
            `days_added` INT NOT NULL DEFAULT 0,
            `sub_end` DATETIME DEFAULT NULL,
            `ip` VARCHAR(45) DEFAULT NULL,
            `redeemed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `uq_redeem` (`coupon_id`,`user_id`),
            KEY `idx_rd_user` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'site_login_logs' => "CREATE TABLE IF NOT EXISTS `site_login_logs` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT DEFAULT NULL,
            `username` VARCHAR(50) DEFAULT NULL,
            `ip` VARCHAR(45) DEFAULT NULL,
            `user_agent` VARCHAR(255) DEFAULT NULL,
            `status` ENUM('success','fail','blocked','register','expired') NOT NULL DEFAULT 'success',
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY `idx_sll_user` (`user_id`),
            KEY `idx_sll_time` (`created_at`),
            KEY `idx_sll_ip` (`ip`,`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        ];
        foreach ($ddl as $__name => $__sql) {
            try { $pdo->exec($__sql); }
            catch (Throwable $e) { subsSchemaNote("create $__name: " . $e->getMessage()); }
        }

        // ── البيانات الأولية ──
        // كل إدراج في try خاصّ به: فشل ملء الخطط الافتراضية مشكلة تُصحَّح
        // من اللوحة بضغطة، أما إسقاط تهيئة المخطط كلها بسببه فيعطّل النظام.
        try {
            $pdo->exec("INSERT IGNORE INTO `subscription_plans`
                (`code`,`name_ar`,`name_en`,`name_tr`,`duration_days`,`price`,`is_active`,`sort_order`) VALUES
                ('weekly','اشتراك أسبوعي','Weekly Plan','Haftalık Abonelik',7,5.00,1,1),
                ('monthly','اشتراك شهري','Monthly Plan','Aylık Abonelik',30,15.00,1,2),
                ('yearly','اشتراك سنوي','Yearly Plan','Yıllık Abonelik',365,150.00,1,3)");
        } catch (Throwable $e) { subsSchemaNote('seed_plans: ' . $e->getMessage()); }

        try {
            // لا نستخدم INSERT IGNORE هنا: جدول settings في التركيبات القديمة
            // قد لا يحمل فهرساً فريداً على setting_key، فيُدرج مفاتيح مكرّرة
            // في كل مرة. subsSetSetting تفحص الوجود أولاً.
            foreach ([
                'index_protection'   => '0',
                'allow_registration' => '1',
                'currency_symbol'    => '$',
                'currency_code'      => 'USD',
            ] as $k => $v) {
                $q = $pdo->prepare("SELECT 1 FROM settings WHERE setting_key=? LIMIT 1");
                $q->execute([$k]);
                if (!$q->fetchColumn()) {
                    $i = $pdo->prepare("INSERT INTO settings (setting_key,setting_value) VALUES (?,?)");
                    $i->execute([$k, $v]);
                }
            }
        } catch (Throwable $e) { subsSchemaNote('seed_settings: ' . $e->getMessage()); }

        // ── التحقق الفعلي قبل كتابة العلامة ──
        // ⚠ لا تستخدم "SHOW TABLES LIKE ?" هنا.
        // الاتصال مضبوط على PDO::ATTR_EMULATE_PREPARES => false أي تهيئة
        // أصلية في MySQL، وعبارات SHOW لا تقبل المعاملات في هذا الوضع
        // فترمي استثناءً. النتيجة كانت فشلاً كاذباً: الجداول تُنشأ فعلاً
        // ثم يفشل التحقق نفسه فيُبلَّغ "تعذّر إنشاء جداول النظام".
        // information_schema جدول عادي يقبل المعاملات بلا التباس.
        $need = ['subscription_plans','site_users','coupons','coupon_redemptions','site_login_logs'];
        $missing = [];
        foreach ($need as $tbl) {
            try {
                $st = $pdo->prepare(
                    "SELECT COUNT(*) FROM information_schema.TABLES
                     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?"
                );
                $st->execute([$tbl]);
                if ((int)$st->fetchColumn() === 0) $missing[] = $tbl;
            } catch (Throwable $e) {
                subsSchemaNote("verify $tbl: " . $e->getMessage());
                $missing[] = $tbl;
            }
        }

        if ($missing) {
            subsSchemaNote('جداول مفقودة: ' . implode(', ', $missing));
            if (function_exists('logTo')) {
                logTo('error', 'subsEnsureSchema: ' . implode(' | ', subsSchemaNote()));
            }
            return $done = false;
        }

        @mkdir(dirname($flag), 0755, true);
        @file_put_contents($flag, date('c'));
        return $done = true;

    } catch (Throwable $e) {
        subsSchemaNote('fatal: ' . $e->getMessage());
        if (function_exists('logTo')) logTo('error', 'subsEnsureSchema: ' . $e->getMessage());
        return $done = false;
    }
}

/**
 * يجمع أخطاء التهيئة ليقرأها التشخيص.
 * بلا هذا تختفي رسالة MySQL الحقيقية خلف "تعذّر إنشاء جداول النظام"
 * ولا يبقى أمام المستخدم إلا التخمين.
 *
 * @param string|null $msg رسالة للإضافة، أو null لاسترجاع كل الرسائل.
 * @return array
 */
function subsSchemaNote(?string $msg = null): array
{
    static $notes = [];
    if ($msg !== null) $notes[] = $msg;
    return $notes;
}

// ═══════════════════════════════════════════════════════════════════════════
//  2) الإعدادات
// ═══════════════════════════════════════════════════════════════════════════

/** يقرأ إعداداً من جدول settings مع تخزين مؤقت داخل الطلب. */
function subsSetting(string $key, string $default = ''): string
{
    static $cache = [];
    if (array_key_exists($key, $cache)) return $cache[$key];
    try {
        $st = db()->prepare("SELECT setting_value FROM settings WHERE setting_key=? LIMIT 1");
        $st->execute([$key]);
        $v = $st->fetchColumn();
        return $cache[$key] = ($v === false || $v === null) ? $default : (string)$v;
    } catch (Throwable $e) {
        return $cache[$key] = $default;
    }
}

/** يكتب إعداداً (UPSERT). */
function subsSetSetting(string $key, string $value): bool
{
    try {
        // ON DUPLICATE KEY يتطلب فهرساً فريداً على setting_key.
        // بعض النسخ القديمة من قاعدة البيانات لا تحمله، لذا نتحقق يدوياً.
        $st = db()->prepare("SELECT id FROM settings WHERE setting_key=? LIMIT 1");
        $st->execute([$key]);
        if ($st->fetchColumn()) {
            $u = db()->prepare("UPDATE settings SET setting_value=? WHERE setting_key=?");
            return $u->execute([$value, $key]);
        }
        $i = db()->prepare("INSERT INTO settings (setting_key,setting_value) VALUES (?,?)");
        return $i->execute([$key, $value]);
    } catch (Throwable $e) {
        if (function_exists('logTo')) logTo('error', 'subsSetSetting: ' . $e->getMessage());
        return false;
    }
}

/** رمز العملة المختار من الإعدادات العامة. */
function subsCurrency(): string { return subsSetting('currency_symbol', '$'); }

/** هل حماية الصفحة الرئيسية مفعّلة؟ */
function subsProtectionOn(): bool { return subsSetting('index_protection', '0') === '1'; }

/** هل يُسمح بإنشاء حسابات جديدة؟ */
function subsRegistrationOpen(): bool { return subsSetting('allow_registration', '1') === '1'; }

// ═══════════════════════════════════════════════════════════════════════════
//  3) الخطط
// ═══════════════════════════════════════════════════════════════════════════

/** كل الخطط، أو الفعّالة فقط. */
function subsPlans(bool $activeOnly = false): array
{
    try {
        $sql = "SELECT * FROM subscription_plans";
        if ($activeOnly) $sql .= " WHERE is_active=1";
        $sql .= " ORDER BY sort_order, id";
        return db()->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) { return []; }
}

function subsPlan(int $id): ?array
{
    try {
        $st = db()->prepare("SELECT * FROM subscription_plans WHERE id=? LIMIT 1");
        $st->execute([$id]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $e) { return null; }
}

/** اسم الخطة باللغة المطلوبة مع ارتداد إلى العربية. */
function subsPlanName(?array $plan, string $lang = 'ar'): string
{
    if (!$plan) return '—';
    $k = 'name_' . (in_array($lang, ['ar','en','tr'], true) ? $lang : 'ar');
    $v = trim((string)($plan[$k] ?? ''));
    return $v !== '' ? $v : (string)($plan['name_ar'] ?? '—');
}

// ═══════════════════════════════════════════════════════════════════════════
//  4) حالة الاشتراك — مصدر الحقيقة الوحيد
// ═══════════════════════════════════════════════════════════════════════════

/**
 * يحسب حالة اشتراك مستخدم.
 *
 * @return array{state:string,days_left:int,expired:bool,active:bool,end:?string}
 *   state ∈ pending | active | expired | disabled
 *     pending  = حساب جديد لم يُفعَّل بعد (لا كوبون ولا تفعيل إداري)
 *     active   = مفعّل والاشتراك سارٍ
 *     expired  = كان مفعّلاً وانتهت المدة
 *     disabled = أوقفته الإدارة يدوياً
 */
function subsUserStatus(?array $u): array
{
    $out = ['state'=>'pending','days_left'=>0,'expired'=>false,'active'=>false,'end'=>null];
    if (!$u) return $out;

    $end = $u['sub_end'] ?? null;
    $out['end'] = $end;

    // لا اشتراك قط
    if (empty($end)) {
        $out['state'] = ((int)($u['is_active'] ?? 0) === 1) ? 'active' : 'pending';
        $out['active'] = $out['state'] === 'active';
        // مفعّل بلا تاريخ انتهاء = اشتراك مفتوح (تفعيل إداري دائم)
        $out['days_left'] = $out['active'] ? -1 : 0;
        return $out;
    }

    $now  = new DateTimeImmutable('now');
    try { $exp = new DateTimeImmutable($end); }
    catch (Throwable $e) { return $out; }

    // الفرق بالأيام — نستخدم ceil على الثواني بدل ->days
    // لأن ->days يقتطع: اشتراك يبقى منه 23 ساعة كان سيظهر "0 يوم".
    $secs = $exp->getTimestamp() - $now->getTimestamp();
    $out['days_left'] = $secs > 0 ? (int)ceil($secs / 86400) : 0;

    if ($secs <= 0) {
        $out['state']   = 'expired';
        $out['expired'] = true;
        return $out;
    }
    if ((int)($u['is_active'] ?? 0) !== 1) {
        $out['state'] = 'disabled';
        return $out;
    }
    $out['state']  = 'active';
    $out['active'] = true;
    return $out;
}

/**
 * يعطّل تلقائياً كل الحسابات التي انتهت مدتها.
 * يُستدعى بكسل عند تحميل لوحة الإدارة وعند دخول أي مشترك، وهو رخيص
 * (يمسّ الصفوف المنتهية فقط بفضل الفهرس idx_su_active).
 */
function subsExpireDue(): int
{
    try {
        $st = db()->prepare(
            "UPDATE site_users SET is_active=0
             WHERE is_active=1 AND sub_end IS NOT NULL AND sub_end < NOW()"
        );
        $st->execute();
        return $st->rowCount();
    } catch (Throwable $e) { return 0; }
}

// ═══════════════════════════════════════════════════════════════════════════
//  5) المستخدمون
// ═══════════════════════════════════════════════════════════════════════════

function subsFindUser(string $username): ?array
{
    try {
        $st = db()->prepare("SELECT * FROM site_users WHERE username=? LIMIT 1");
        $st->execute([$username]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $e) { return null; }
}

function subsUserById(int $id): ?array
{
    try {
        $st = db()->prepare("SELECT * FROM site_users WHERE id=? LIMIT 1");
        $st->execute([$id]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $e) { return null; }
}

/** يتحقق من صلاحية اسم المستخدم. */
function subsValidUsername(string $u): bool
{
    return (bool)preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $u);
}

/**
 * ينشئ حساباً جديداً — غير مفعّل دائماً.
 * @return array{ok:bool,error?:string,id?:int}
 */
function subsRegister(string $username, string $password, string $email = ''): array
{
    $username = trim($username);
    if (!subsValidUsername($username)) {
        return ['ok'=>false,'error'=>'invalid_username'];
    }
    if (strlen($password) < 8) {
        return ['ok'=>false,'error'=>'weak_password'];
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok'=>false,'error'=>'invalid_email'];
    }
    if (subsFindUser($username)) {
        return ['ok'=>false,'error'=>'username_taken'];
    }
    try {
        $st = db()->prepare(
            "INSERT INTO site_users (username,password,email,is_active,activated_via)
             VALUES (?,?,?,0,'none')"
        );
        $st->execute([$username, password_hash($password, PASSWORD_DEFAULT), $email ?: null]);
        $id = (int)db()->lastInsertId();
        subsLog($id, $username, 'register');
        return ['ok'=>true,'id'=>$id];
    } catch (PDOException $e) {
        // 23000 = انتهاك قيد فريد. سباق بين فحص التكرار والإدراج:
        // مستخدمان يرسلان نفس الاسم في نفس اللحظة يجتازان الفحص معاً.
        // القيد الفريد في قاعدة البيانات هو الحارس الحقيقي.
        if ($e->getCode() === '23000') return ['ok'=>false,'error'=>'username_taken'];
        if (function_exists('logTo')) logTo('error', 'subsRegister: ' . $e->getMessage());
        return ['ok'=>false,'error'=>'server'];
    }
}

/**
 * يتحقق من بيانات الدخول.
 * @return array{ok:bool,error?:string,user?:array}
 */
function subsLogin(string $username, string $password): array
{
    $username = trim($username);
    $ip = subsClientIp();

    // حدّ محاولات لكل IP — 15 محاولة كل 10 دقائق
    if (function_exists('rateLimit') && !rateLimit('site_login:' . $ip, 15, 600)) {
        subsLog(null, $username, 'blocked');
        return ['ok'=>false,'error'=>'too_many'];
    }

    $u = subsFindUser($username);

    // مقارنة وهمية عند غياب المستخدم: بدونها يكون زمن الرد على اسم
    // غير موجود أقصر بوضوح، فيستطيع مهاجم تعداد أسماء المستخدمين الصحيحة.
    if (!$u) {
        password_verify($password, '$2y$10$usesomesillystringforsalttoavoidtimingleaksxxxxxxxxxxxx');
        subsLog(null, $username, 'fail');
        return ['ok'=>false,'error'=>'bad_credentials'];
    }

    if (!password_verify($password, (string)$u['password'])) {
        subsLog((int)$u['id'], $username, 'fail');
        return ['ok'=>false,'error'=>'bad_credentials'];
    }

    // ترقية التجزئة إن تغيّرت الخوارزمية الافتراضية في PHP
    if (password_needs_rehash((string)$u['password'], PASSWORD_DEFAULT)) {
        try {
            $r = db()->prepare("UPDATE site_users SET password=? WHERE id=?");
            $r->execute([password_hash($password, PASSWORD_DEFAULT), $u['id']]);
        } catch (Throwable $e) { /* غير حرج */ }
    }

    try {
        $st = db()->prepare("UPDATE site_users SET last_login=NOW(), last_ip=? WHERE id=?");
        $st->execute([$ip, $u['id']]);
    } catch (Throwable $e) { /* غير حرج */ }

    subsLog((int)$u['id'], $username, 'success');
    return ['ok'=>true,'user'=>$u];
}

/**
 * تفعيل إداري مباشر.
 * @param int $days عدد الأيام، أو 0 لاشتراك مفتوح بلا نهاية
 */
function subsAdminActivate(int $userId, ?int $planId, int $days, string $by = 'admin'): bool
{
    try {
        if ($days > 0) {
            $st = db()->prepare(
                "UPDATE site_users
                 SET is_active=1, plan_id=?, activated_via='admin',
                     sub_start=NOW(), sub_end=DATE_ADD(NOW(), INTERVAL ? DAY)
                 WHERE id=?"
            );
            $ok = $st->execute([$planId ?: null, $days, $userId]);
        } else {
            $st = db()->prepare(
                "UPDATE site_users
                 SET is_active=1, plan_id=?, activated_via='admin',
                     sub_start=NOW(), sub_end=NULL
                 WHERE id=?"
            );
            $ok = $st->execute([$planId ?: null, $userId]);
        }
        if ($ok && function_exists('logTo')) {
            logTo('admin', "تفعيل إداري للمشترك #$userId لمدة " . ($days ?: 'غير محدودة') . " يوم بواسطة $by");
        }
        return $ok;
    } catch (Throwable $e) {
        if (function_exists('logTo')) logTo('error', 'subsAdminActivate: ' . $e->getMessage());
        return false;
    }
}

function subsAdminDeactivate(int $userId): bool
{
    try {
        $st = db()->prepare("UPDATE site_users SET is_active=0 WHERE id=?");
        return $st->execute([$userId]);
    } catch (Throwable $e) { return false; }
}

// ═══════════════════════════════════════════════════════════════════════════
//  6) الكوبونات
// ═══════════════════════════════════════════════════════════════════════════

/**
 * يولّد كوداً عشوائياً على شكل XXXX-XXXX-XXXX.
 *
 * الأبجدية تستثني 0/O/1/I/L عمداً: الكوبون يُقرأ من ورقة أو صورة
 * ويُكتب يدوياً، وهذه الأحرف تتشابه بصرياً فتُنتج شكاوى "الكوبون لا يعمل".
 */
function subsGenCode(): string
{
    $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    $n = strlen($alphabet);
    $out = '';
    for ($i = 0; $i < 12; $i++) {
        if ($i > 0 && $i % 4 === 0) $out .= '-';
        $out .= $alphabet[random_int(0, $n - 1)];
    }
    return $out;
}

/**
 * ينشئ كوبونات.
 * @return array{ok:bool,codes?:array,error?:string}
 */
function subsCreateCoupons(int $planId, int $count, int $maxUses, ?string $expiresAt, string $note, string $by): array
{
    $plan = subsPlan($planId);
    if (!$plan) return ['ok'=>false,'error'=>'plan_not_found'];

    $count    = max(1, min(500, $count));      // سقف يمنع إغراق الجدول بالخطأ
    $maxUses  = max(1, min(100000, $maxUses));
    $days     = (int)$plan['duration_days'];

    $codes = [];
    try {
        $st = db()->prepare(
            "INSERT INTO coupons (code,plan_id,duration_days,max_uses,is_active,expires_at,note,created_by)
             VALUES (?,?,?,?,1,?,?,?)"
        );
        for ($i = 0; $i < $count; $i++) {
            // إعادة المحاولة عند التصادم النادر مع كود موجود
            for ($try = 0; $try < 6; $try++) {
                $code = subsGenCode();
                try {
                    $st->execute([$code, $planId, $days, $maxUses, $expiresAt ?: null, $note ?: null, $by]);
                    $codes[] = $code;
                    break;
                } catch (PDOException $e) {
                    if ($e->getCode() !== '23000') throw $e;
                    if ($try === 5) throw $e;
                }
            }
        }
        if (function_exists('logTo')) logTo('admin', "إنشاء " . count($codes) . " كوبون لخطة {$plan['code']} بواسطة $by");
        return ['ok'=>true,'codes'=>$codes];
    } catch (Throwable $e) {
        if (function_exists('logTo')) logTo('error', 'subsCreateCoupons: ' . $e->getMessage());
        return ['ok'=>false,'error'=>'server','codes'=>$codes];
    }
}

/**
 * يستهلك كوبوناً لحساب مستخدم.
 *
 * كل شيء داخل معاملة مع SELECT ... FOR UPDATE. بدون القفل، عشرة طلبات
 * متزامنة بنفس الكوبون تقرأ used_count=0 معاً وتنجح كلها — وهذا بالضبط
 * ما يفعله من يشارك كوداً في مجموعة تيليغرام.
 *
 * @return array{ok:bool,error?:string,days?:int,end?:string,plan?:string}
 */
function subsRedeemCoupon(int $userId, string $code): array
{
    $code = strtoupper(trim($code));
    // تسامح مع من يكتب الكود بلا شرطات أو بمسافات
    $code = preg_replace('/[^A-Z0-9-]/', '', $code);
    if ($code === '') return ['ok'=>false,'error'=>'invalid_code'];

    $ip = subsClientIp();
    if (function_exists('rateLimit') && !rateLimit('coupon:' . $ip, 10, 600)) {
        return ['ok'=>false,'error'=>'too_many'];
    }

    $pdo = db();
    try {
        $pdo->beginTransaction();

        $st = $pdo->prepare("SELECT * FROM coupons WHERE code=? LIMIT 1 FOR UPDATE");
        $st->execute([$code]);
        $c = $st->fetch(PDO::FETCH_ASSOC);

        if (!$c)                                  { $pdo->rollBack(); return ['ok'=>false,'error'=>'invalid_code']; }
        if ((int)$c['is_active'] !== 1)           { $pdo->rollBack(); return ['ok'=>false,'error'=>'coupon_disabled']; }
        if ((int)$c['used_count'] >= (int)$c['max_uses']) {
            $pdo->rollBack(); return ['ok'=>false,'error'=>'coupon_exhausted'];
        }
        if (!empty($c['expires_at']) && strtotime($c['expires_at']) < time()) {
            $pdo->rollBack(); return ['ok'=>false,'error'=>'coupon_expired'];
        }

        $u = $pdo->prepare("SELECT * FROM site_users WHERE id=? LIMIT 1 FOR UPDATE");
        $u->execute([$userId]);
        $user = $u->fetch(PDO::FETCH_ASSOC);
        if (!$user) { $pdo->rollBack(); return ['ok'=>false,'error'=>'user_not_found']; }

        $days = (int)$c['duration_days'];

        // ── تمديد لا استبدال ──
        // من يجدّد قبل انتهاء اشتراكه بيوم يجب ألا يخسر ما تبقّى له.
        // نبني على تاريخ الانتهاء الحالي إن كان في المستقبل، وإلا من الآن.
        $curEnd = $user['sub_end'] ?? null;
        $base = ($curEnd && strtotime($curEnd) > time()) ? $curEnd : date('Y-m-d H:i:s');
        $newEnd = date('Y-m-d H:i:s', strtotime($base . " +{$days} days"));
        $start  = ($curEnd && strtotime($curEnd) > time() && !empty($user['sub_start']))
                  ? $user['sub_start'] : date('Y-m-d H:i:s');

        $up = $pdo->prepare(
            "UPDATE site_users
             SET is_active=1, plan_id=?, activated_via='coupon', sub_start=?, sub_end=?
             WHERE id=?"
        );
        $up->execute([$c['plan_id'] ?: null, $start, $newEnd, $userId]);

        // سجل الاستهلاك — القيد الفريد يمنع نفس المستخدم من تكراره
        $rd = $pdo->prepare(
            "INSERT INTO coupon_redemptions (coupon_id,user_id,username,days_added,sub_end,ip)
             VALUES (?,?,?,?,?,?)"
        );
        try {
            $rd->execute([$c['id'], $userId, $user['username'], $days, $newEnd, $ip]);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $pdo->rollBack();
                return ['ok'=>false,'error'=>'already_used_by_you'];
            }
            throw $e;
        }

        // ⚠ ترتيب الأسناد هنا مقصود ولا يجوز تبديله.
        // MySQL — خلافاً لمعيار SQL — يُقيّم أسناد SET من اليسار إلى اليمين
        // مستخدماً القيم **المحدَّثة** للأعمدة السابقة. لو جاء used_count أولاً
        // لقرأ سطر is_active القيمةَ الجديدة، فيصير الشرط (new+1 >= max)
        // أي old+2، ويُعطَّل الكوبون قبل استهلاك آخر استخدام مدفوع.
        // بوضع is_active أولاً يُقيَّم بالقيمة القديمة وهو الصحيح.
        $cu = $pdo->prepare(
            "UPDATE coupons
             SET is_active = IF(used_count + 1 >= max_uses, 0, is_active),
                 used_count = used_count + 1,
                 last_used_at = NOW(), last_used_by = ?
             WHERE id=?"
        );
        $cu->execute([$user['username'], $c['id']]);

        $pdo->commit();

        $plan = subsPlan((int)($c['plan_id'] ?? 0));
        if (function_exists('logTo')) {
            logTo('info', "استُهلك الكوبون $code بواسطة {$user['username']} — $days يوم");
        }
        return ['ok'=>true,'days'=>$days,'end'=>$newEnd,'plan'=>subsPlanName($plan)];

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if (function_exists('logTo')) logTo('error', 'subsRedeemCoupon: ' . $e->getMessage());
        return ['ok'=>false,'error'=>'server'];
    }
}

// ═══════════════════════════════════════════════════════════════════════════
//  7) أدوات
// ═══════════════════════════════════════════════════════════════════════════

/**
 * عنوان العميل.
 * نتجاهل X-Forwarded-For افتراضياً لأنه رأس يرسله العميل ويمكن تزويره،
 * وحدّ المحاولات المبني عليه يُتجاوز بتغيير الرأس في كل طلب.
 * إن كنت خلف Cloudflare أو بروكسي عكسي فعّل SUBS_TRUST_PROXY في .env.
 */
function subsClientIp(): string
{
    $trust = getenv('SUBS_TRUST_PROXY') === '1'
             || (defined('SUBS_TRUST_PROXY') && SUBS_TRUST_PROXY);
    if ($trust) {
        foreach (['HTTP_CF_CONNECTING_IP','HTTP_X_REAL_IP','HTTP_X_FORWARDED_FOR'] as $h) {
            if (!empty($_SERVER[$h])) {
                $ip = trim(explode(',', $_SERVER[$h])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
            }
        }
    }
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}

/** يسجّل حدثاً في سجل دخول المشتركين. */
function subsLog(?int $userId, ?string $username, string $status): void
{
    try {
        $st = db()->prepare(
            "INSERT INTO site_login_logs (user_id,username,ip,user_agent,status)
             VALUES (?,?,?,?,?)"
        );
        $st->execute([
            $userId ?: null,
            $username ? mb_substr($username, 0, 50) : null,
            subsClientIp(),
            mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            $status,
        ]);
    } catch (Throwable $e) { /* السجل لا يجوز أن يُسقط الطلب */ }
}

/**
 * تقليم سجل دخول المشتركين.
 *
 * بلا هذا ينمو الجدول إلى الأبد: 500 مشترك يعنون نحو 180 ألف صف سنوياً،
 * ونصف مليون خلال ثلاث سنوات. سجلّ بهذا الحجم يُبطئ صفحة السجل ويضخّم
 * كل نسخة احتياطية بلا فائدة — لا أحد يراجع محاولة دخول عمرها سنتان.
 *
 * يُستدعى مرة يومياً من restream_gc، ومحروس بعلامة ملف كي لا يُنفَّذ
 * في كل دقيقة.
 *
 * @param int $days احتفظ بهذا العدد من الأيام
 * @return int عدد الصفوف المحذوفة
 */
function subsPruneLogs(int $days = 90): int
{
    $days = max(7, min(3650, $days));
    $flag = __DIR__ . '/../storage/.subs_prune';
    if (is_file($flag) && (time() - (int)@filemtime($flag)) < 82800) return 0;  // 23 ساعة

    try {
        // نحذف على دفعات: DELETE واحد لمليون صف يقفل الجدول دقائق
        $total = 0;
        for ($i = 0; $i < 50; $i++) {
            $st = db()->prepare(
                "DELETE FROM site_login_logs
                 WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
                 LIMIT 5000"
            );
            $st->execute([$days]);
            $n = $st->rowCount();
            $total += $n;
            if ($n < 5000) break;
            usleep(50000);   // نفسح المجال لطلبات المستخدمين بين الدفعات
        }
        @mkdir(dirname($flag), 0755, true);
        @touch($flag);
        if ($total > 0 && function_exists('logTo')) {
            logTo('info', "subsPruneLogs: حُذف $total صفاً أقدم من $days يوماً");
        }
        return $total;
    } catch (Throwable $e) {
        if (function_exists('logTo')) logTo('error', 'subsPruneLogs: ' . $e->getMessage());
        return 0;
    }
}

/** إحصاءات سريعة للوحة المعلومات. */
function subsStats(): array
{
    $z = ['users'=>0,'active'=>0,'expired'=>0,'pending'=>0,
          'coupons'=>0,'coupons_free'=>0,'coupons_used'=>0,'expiring_7d'=>0];
    try {
        $pdo = db();
        $z['users']   = (int)$pdo->query("SELECT COUNT(*) FROM site_users")->fetchColumn();
        $z['active']  = (int)$pdo->query("SELECT COUNT(*) FROM site_users WHERE is_active=1 AND (sub_end IS NULL OR sub_end>=NOW())")->fetchColumn();
        $z['expired'] = (int)$pdo->query("SELECT COUNT(*) FROM site_users WHERE sub_end IS NOT NULL AND sub_end<NOW()")->fetchColumn();
        $z['pending'] = (int)$pdo->query("SELECT COUNT(*) FROM site_users WHERE is_active=0 AND sub_end IS NULL")->fetchColumn();
        $z['coupons'] = (int)$pdo->query("SELECT COUNT(*) FROM coupons")->fetchColumn();
        $z['coupons_free'] = (int)$pdo->query("SELECT COUNT(*) FROM coupons WHERE is_active=1 AND used_count<max_uses")->fetchColumn();
        $z['coupons_used'] = (int)$pdo->query("SELECT COUNT(*) FROM coupon_redemptions")->fetchColumn();
        $z['expiring_7d']  = (int)$pdo->query("SELECT COUNT(*) FROM site_users WHERE is_active=1 AND sub_end BETWEEN NOW() AND DATE_ADD(NOW(),INTERVAL 7 DAY)")->fetchColumn();
    } catch (Throwable $e) { /* لوحة المعلومات تعرض أصفاراً بدل الانهيار */ }
    return $z;
}

} // SUBS_LIB
