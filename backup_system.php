<?php
/**
 * نظام النسخ الاحتياطي الشامل — Shashety IPTV
 * ══════════════════════════════════════════════════════════════
 *
 *  الأعطال التي كانت تمنع النسخ الاحتياطي من العمل
 * ──────────────────────────────────────────────────────────────
 *
 *  ① التصدير كان يُجمِّع قاعدة البيانات كلها في متغيّر نصي واحد
 *     في الذاكرة ثم يطبعه دفعة واحدة:
 *         $output .= ... ;   // لكل صف في كل جدول
 *         echo $output;
 *     مع جدول قنوات فيه عشرات الآلاف من قنوات Xtream، ينفد حدّ
 *     الذاكرة فيتوقف السكربت. وبما أن ترويسات التنزيل أُرسلت قبل
 *     ذلك، ينزّل المتصفح ملف .sql **ناقصاً أو فارغاً** بلا أي رسالة.
 *     الحل: البثّ المباشر إلى المخرجات صفاً صفاً بلا تجميع.
 *
 *  ② كل بيانات الجدول كانت تُقرأ بـ fetchAll() دفعة واحدة —
 *     نسخة ثانية كاملة في الذاكرة. الحل: القراءة صفاً صفاً.
 *
 *  ③ كل صفوف الجدول كانت تُكتب في أمر INSERT **واحد عملاق**.
 *     عند الاستعادة يتجاوز هذا الأمر حدّ max_allowed_packet في
 *     MySQL فيفشل الاستيراد. الحل: تقسيم على دفعات محدودة الحجم.
 *
 *  ④ 🔴 الأخطر: كان الاستيراد يبتلع كل الأخطاء في مصفوفة ويكتبها
 *     في السجل فقط، ثم يعرض للمستخدم:
 *         "✅ تم استعادة النظام بنجاح!"
 *     حتى لو فشل كل أمر. المستخدم يظن أن النسخة استُعيدت وهي لم
 *     تُستعد إطلاقاً. وبما أن الملف يبدأ بـ DROP TABLE، فالنتيجة
 *     قاعدة بيانات **فارغة** مع رسالة نجاح كاذبة.
 *     الحل: تقرير صادق بعدد الأوامر الناجحة والفاشلة وسببها.
 *
 *  ⑤ لم يكن هناك أي حدّ زمني مرفوع — الاستيراد الكبير يتوقف بمهلة
 *     الخادم في منتصف العملية تاركاً القاعدة ناقصة.
 *
 *  ⑥ تقسيم الأوامر كان يعتمد على "سطر ينتهي بفاصلة منقوطة"، وهذا
 *     يكسر أي بيانات تحتوي ; أو أسطراً جديدة داخل النصوص.
 *     الحل: مُقسِّم يحترم علامات الاقتباس وحروف الهروب.
 *
 *  ⑦ لم يكن هناك تحقق CSRF على الاستيراد — وهو إجراء يمسح قاعدة
 *     البيانات بالكامل.
 */

require_once __DIR__ . '/core/config.php';

if (!isAdminLoggedIn()) {
    header('Location: login.php');
    exit;
}

@set_time_limit(0);
@ini_set('memory_limit', '512M');

$action = $_GET['action'] ?? '';

/* ══════════════════════════════════════════════════════════════
   أدوات مساعدة
   ══════════════════════════════════════════════════════════════ */

/** إرسال ترويسات تنزيل ملف SQL وإيقاف أي تخزين مؤقت للمخرجات. */
function bkStartDownload(string $filename): void
{
    while (ob_get_level()) {
        ob_end_clean(); // مهم: أي buffer نشط يُلغي فائدة البثّ المباشر
    }

    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('X-Content-Type-Options: nosniff');
}

/** طباعة نص مباشرة إلى المتصفح مع دفعه فوراً (بلا تجميع في الذاكرة). */
function bkEcho(string $s): void
{
    echo $s;
    if (ob_get_level() === 0) {
        @flush();
    }
}

/**
 * تصدير جدول واحد: البنية ثم البيانات على دفعات.
 *
 * @param PDO    $pdo
 * @param string $table اسم الجدول (من SHOW TABLES — ليس من المستخدم).
 * @param int    $maxPacket أقصى حجم لأمر INSERT الواحد بالبايت.
 */
function bkExportTable(PDO $pdo, string $table, int $maxPacket = 786432): void
{
    $q = '`' . str_replace('`', '``', $table) . '`';

    bkEcho("-- ────────────────────────────────────────────────\n");
    bkEcho("-- Table: {$table}\n");
    bkEcho("-- ────────────────────────────────────────────────\n\n");
    bkEcho("DROP TABLE IF EXISTS {$q};\n\n");

    $create = $pdo->query("SHOW CREATE TABLE {$q}")->fetch(PDO::FETCH_ASSOC);
    $ddl    = $create['Create Table'] ?? ($create['Create View'] ?? '');
    if ($ddl === '') {
        bkEcho("-- تعذّر قراءة بنية الجدول\n\n");
        return;
    }
    bkEcho($ddl . ";\n\n");

    // قراءة صفاً صفاً — لا fetchAll (تفادي استهلاك الذاكرة)
    $stmt = $pdo->query("SELECT * FROM {$q}");

    $columnsList = '';
    $batch       = [];
    $batchBytes  = 0;
    $rowCount    = 0;

    $flushBatch = function () use (&$batch, &$batchBytes, $q, &$columnsList) {
        if (!$batch) {
            return;
        }
        bkEcho("INSERT INTO {$q} ({$columnsList}) VALUES\n" . implode(",\n", $batch) . ";\n");
        $batch      = [];
        $batchBytes = 0;
    };

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if ($columnsList === '') {
            $cols        = array_keys($row);
            $columnsList = '`' . implode('`, `', array_map(function ($c) {
                return str_replace('`', '``', $c);
            }, $cols)) . '`';
        }

        $vals = [];
        foreach ($row as $v) {
            $vals[] = ($v === null) ? 'NULL' : $pdo->quote((string) $v);
        }
        $line = '(' . implode(', ', $vals) . ')';

        $batch[]     = $line;
        $batchBytes += strlen($line) + 2;
        $rowCount++;

        // دفعة جديدة قبل تجاوز حدّ max_allowed_packet عند الاستعادة
        if ($batchBytes >= $maxPacket || count($batch) >= 500) {
            $flushBatch();
        }
    }
    $flushBatch();

    bkEcho("\n-- عدد الصفوف: {$rowCount}\n\n");
}

/**
 * تقسيم ملف SQL إلى أوامر مع احترام علامات الاقتباس والتعليقات.
 *
 * ⚠️ المُقسِّم السابق كان يعتبر «أي سطر ينتهي بفاصلة منقوطة» نهايةَ
 *    أمر، فينكسر مع أي اسم قناة أو رابط يحتوي ; أو سطراً جديداً.
 *
 * @param string $sql محتوى الملف.
 * @return array قائمة الأوامر.
 */
function bkSplitSql(string $sql): array
{
    $out     = [];
    $buf     = '';
    $inStr   = false;
    $strCh   = '';
    $esc     = false;
    $len     = strlen($sql);

    for ($i = 0; $i < $len; $i++) {
        $c = $sql[$i];

        if ($inStr) {
            $buf .= $c;
            if ($esc)                      { $esc = false; continue; }
            if ($c === '\\')               { $esc = true;  continue; }
            if ($c === $strCh) {
                // اقتباس مضاعف داخل النص ('' أو "") ليس نهاية
                if (($i + 1) < $len && $sql[$i + 1] === $strCh) {
                    $buf .= $sql[++$i];
                    continue;
                }
                $inStr = false;
            }
            continue;
        }

        // تعليق سطري -- أو #
        if (($c === '-' && ($i + 1) < $len && $sql[$i + 1] === '-') || $c === '#') {
            while ($i < $len && $sql[$i] !== "\n") { $i++; }
            $buf .= "\n";
            continue;
        }

        // تعليق كتلي /* ... */
        if ($c === '/' && ($i + 1) < $len && $sql[$i + 1] === '*') {
            $i += 2;
            while (($i + 1) < $len && !($sql[$i] === '*' && $sql[$i + 1] === '/')) { $i++; }
            $i++;
            continue;
        }

        if ($c === "'" || $c === '"') {
            $inStr = true;
            $strCh = $c;
            $buf  .= $c;
            continue;
        }

        if ($c === ';') {
            $t = trim($buf);
            if ($t !== '') { $out[] = $t; }
            $buf = '';
            continue;
        }

        $buf .= $c;
    }

    $t = trim($buf);
    if ($t !== '') { $out[] = $t; }

    return $out;
}

/* ══════════════════════════════════════════════════════════════
   الإجراءات
   ══════════════════════════════════════════════════════════════ */

try {

    // ─────────────────────────── تصدير كامل ───────────────────────────
    if ($action === 'export_full') {

        bkStartDownload('iptv_full_backup_' . date('Y-m-d_H-i-s') . '.sql');

        bkEcho("-- ════════════════════════════════════════════════\n");
        bkEcho("-- IPTV System Full Backup\n");
        bkEcho("-- Date: " . date('Y-m-d H:i:s') . "\n");
        bkEcho("-- Database: " . DB_NAME . "\n");
        bkEcho("-- ════════════════════════════════════════════════\n\n");
        bkEcho("SET NAMES utf8mb4;\n");
        bkEcho("SET FOREIGN_KEY_CHECKS = 0;\n");
        bkEcho("SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n\n");

        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($tables as $table) {
            bkExportTable($pdo, (string) $table);
        }

        bkEcho("SET FOREIGN_KEY_CHECKS = 1;\n");
        bkEcho("\n-- ════════════════════════════════════════════════\n");
        bkEcho("-- Backup Completed Successfully — " . count($tables) . " tables\n");
        bkEcho("-- ════════════════════════════════════════════════\n");

        logActivity('تصدير نسخة احتياطية كاملة', count($tables) . ' جدول');
        exit;
    }

    // ────────────────────── تصدير القنوات والأقسام ──────────────────────
    if ($action === 'export_channels') {

        bkStartDownload('iptv_channels_backup_' . date('Y-m-d_H-i-s') . '.sql');

        bkEcho("-- IPTV Channels + Categories Backup\n");
        bkEcho("-- Date: " . date('Y-m-d H:i:s') . "\n\n");
        bkEcho("SET NAMES utf8mb4;\n");
        bkEcho("SET FOREIGN_KEY_CHECKS = 0;\n\n");

        /* نصدّر الجدولين بنفس الطريقة العامة بدل كتابة أسماء الأعمدة
           يدوياً — النسخة السابقة كانت تكتب قائمة أعمدة ثابتة، فإن
           أضاف أي تحديث عموداً جديداً (مثل xtream_account_id أو
           backup_url) فشل الاستيراد أو ضاعت البيانات بصمت. */
        foreach (['categories', 'channels'] as $t) {
            try {
                bkExportTable($pdo, $t);
            } catch (PDOException $e) {
                bkEcho("-- تعذّر تصدير {$t}\n\n");
                error_log('backup export_channels: ' . $e->getMessage());
            }
        }

        bkEcho("SET FOREIGN_KEY_CHECKS = 1;\n");
        logActivity('تصدير نسخة القنوات', '');
        exit;
    }

    // ─────────────────────────── استيراد ───────────────────────────
    if ($action === 'import') {

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            throw new Exception('طريقة غير مسموحة.');
        }

        // إجراء مدمّر (يمسح الجداول) — لا بد من رمز CSRF
        if (!csrfValidate()) {
            throw new Exception('انتهت صلاحية الجلسة. أعد تحميل الصفحة وحاول مجدداً.');
        }

        if (!isset($_FILES['sql_file']) || ($_FILES['sql_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $errs = [
                UPLOAD_ERR_INI_SIZE  => 'حجم الملف يتجاوز الحد المسموح في إعدادات الخادم (upload_max_filesize).',
                UPLOAD_ERR_FORM_SIZE => 'حجم الملف يتجاوز الحد المسموح في النموذج.',
                UPLOAD_ERR_PARTIAL   => 'تم رفع جزء من الملف فقط — أعد المحاولة.',
                UPLOAD_ERR_NO_FILE   => 'لم تختر أي ملف.',
                UPLOAD_ERR_CANT_WRITE=> 'تعذّرت الكتابة على القرص.',
            ];
            throw new Exception($errs[$_FILES['sql_file']['error'] ?? UPLOAD_ERR_NO_FILE] ?? 'لم يتم رفع الملف بشكل صحيح.');
        }

        $file = $_FILES['sql_file'];

        if (!is_uploaded_file($file['tmp_name'])) {
            throw new Exception('ملف غير صالح.');
        }

        if (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'sql') {
            throw new Exception('الملف يجب أن يكون بصيغة SQL.');
        }

        $sql = file_get_contents($file['tmp_name']);
        if ($sql === false || trim($sql) === '') {
            throw new Exception('الملف فارغ أو تعذّرت قراءته.');
        }

        $statements = bkSplitSql($sql);
        if (!$statements) {
            throw new Exception('لم يُعثر على أي أمر SQL صالح داخل الملف.');
        }

        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");

        $ok     = 0;
        $failed = 0;
        $errors = [];

        foreach ($statements as $st) {
            if ($st === '') { continue; }
            try {
                $pdo->exec($st);
                $ok++;
            } catch (PDOException $e) {
                $failed++;
                if (count($errors) < 5) {
                    // أول 120 حرفاً من الأمر تكفي لتحديد موضع المشكلة
                    $errors[] = mb_substr($e->getMessage(), 0, 180)
                              . ' — عند: ' . mb_substr(preg_replace('/\s+/', ' ', $st), 0, 120);
                }
            }
        }

        try { $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;"); } catch (PDOException $e) {}

        // إبطال الكاش حتى يظهر المحتوى المستعاد فوراً
        if (function_exists('cacheFlush')) { cacheFlush(); }
        $flag = (defined('CACHE_DIR') ? CACHE_DIR : __DIR__ . '/storage/cache') . '/.schema_ok';
        if (is_file($flag)) { @unlink($flag); }

        logActivity('استيراد نسخة احتياطية', "نجح={$ok} فشل={$failed}");

        /* ⚠️ التقرير الصادق — النسخة السابقة كانت تعرض رسالة نجاح
           دائماً حتى لو فشل كل أمر، فيظن المستخدم أن البيانات عادت
           وهي لم تعد. */
        if ($failed === 0) {
            $_SESSION['success'] = "✅ تمت الاستعادة بنجاح — نُفِّذ {$ok} أمر دون أخطاء.";
        } elseif ($ok === 0) {
            $_SESSION['error'] = "❌ فشلت الاستعادة بالكامل ({$failed} أمر فشل). "
                . "أول خطأ: " . ($errors[0] ?? 'غير معروف');
        } else {
            $_SESSION['error'] = "⚠️ استعادة ناقصة: نجح {$ok} أمر وفشل {$failed}. "
                . "أول خطأ: " . ($errors[0] ?? 'غير معروف')
                . " — راجع سجل أخطاء الخادم للتفاصيل الكاملة.";
        }

        if ($errors) {
            error_log('SQL Import Errors: ' . implode(' | ', $errors));
        }

        header('Location: admin.php#backup');
        exit;
    }

    throw new Exception('إجراء غير متاح.');

} catch (Throwable $e) {
    $_SESSION['error'] = '❌ ' . $e->getMessage();
    if (!headers_sent()) {
        header('Location: admin.php#backup');
    }
    exit;
}
