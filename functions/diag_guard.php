<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 *  حارس أدوات التشخيص
 * ───────────────────────────────────────────────────────────────────────────
 *  أدوات التشخيص تكشف عمداً ما نُخفيه عادةً: أسماء المستخدمين وقواعد
 *  البيانات، صلاحيات الملفات، حالة الجلسات، بنية النظام، وأسباب الأعطال.
 *  هذا مفيد لك ومفيد لمن يهاجمك بالقدر نفسه.
 *
 *  كتبتُ في كل أداة «احذفه بعد الانتهاء» — وهذا ليس ضابط أمان بل أمنية.
 *  الملفات تُنسى، وخصوصاً حين تُصلح المشكلة فينصرف الذهن عنها. الحارس
 *  هنا يجعل النسيان غير مكلف:
 *
 *    ① جلسة إدارة       → مسموح
 *    ② عنوان محلي/خاص   → مسموح (تشخيص من داخل الشبكة)
 *    ③ ?key=HEALTH_KEY  → مسموح (من الطرفية أو عن بُعد)
 *    ④ ما عدا ذلك       → 404 لا 403
 *
 *  لماذا 404 لا 403: «ممنوع» يؤكّد للماسح الآلي أن الملف موجود فيُدرجه
 *  هدفاً. «غير موجود» لا يُعطيه شيئاً.
 *
 *  ويرفض العمل بعد DIAG_MAX_AGE_DAYS من تعديل الملف: أداة عمرها شهر
 *  على خادم إنتاج نُسيت يقيناً.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (!defined('DIAG_MAX_AGE_DAYS')) define('DIAG_MAX_AGE_DAYS', 14);

/**
 * @param string $file  __FILE__ الخاص بالأداة
 * @param bool   $needsDb هل تحتاج الأداة قاعدة بيانات عاملة؟
 *                        (db_check لا تحتاجها — هي تشخّص عطلها)
 */
function diagGuard(string $file, bool $needsDb = true): void
{
    // ── ① جلسة الإدارة ──
    if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
    if (function_exists('isAdminLoggedIn') && isAdminLoggedIn()) { diagAgeNotice($file); return; }

    // ── ② الشبكة المحلية ──
    // التشخيص من داخل الشبكة مقبول: من يصل إلى 192.168.x يملك وصولاً
    // ماديّاً أو شبكياً أوسع من هذه الصفحة أصلاً.
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $isLocal = $ip !== '' && (
        $ip === '127.0.0.1' || $ip === '::1' ||
        !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)
    );
    if ($isLocal) { diagAgeNotice($file); return; }

    // ── ③ مفتاح صريح ──
    $key = (string)($_GET['key'] ?? $_SERVER['HTTP_X_DIAG_KEY'] ?? '');
    $want = function_exists('env') ? (string)env('HEALTH_KEY', '') : '';
    if ($want !== '' && $key !== '' && hash_equals($want, $key)) { diagAgeNotice($file); return; }

    // ── ④ الرفض ──
    if (function_exists('logTo')) {
        logTo('security', 'محاولة وصول إلى أداة تشخيص: ' . basename($file) . ' من ' . $ip);
    }
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    exit('<!DOCTYPE html><html><head><meta charset="utf-8"><title>404</title></head>'
       . '<body style="font-family:sans-serif;padding:40px"><h1>404</h1>'
       . '<p>Not Found</p></body></html>');
}

/** يرفض العمل إن قدُم الملف، ويشرح السبب. */
function diagAgeNotice(string $file): void
{
    $age = @filemtime($file);
    if (!$age) return;
    $days = (int)floor((time() - $age) / 86400);
    if ($days < DIAG_MAX_AGE_DAYS) return;

    http_response_code(410);
    header('Content-Type: text/html; charset=utf-8');
    $f = htmlspecialchars($file, ENT_QUOTES, 'UTF-8');
    exit('<!DOCTYPE html><html lang="ar" dir="rtl"><head><meta charset="utf-8">'
       . '<title>أداة تشخيص منتهية</title></head>'
       . '<body style="background:#0b0b0d;color:#e8e8ee;font-family:Tahoma,sans-serif;padding:40px;line-height:1.9">'
       . '<h2 style="color:#F5A623">أداة تشخيص قديمة — عُطِّلت تلقائياً</h2>'
       . '<p>مضى <b>' . $days . '</b> يوماً على هذا الملف. أدوات التشخيص تكشف تفاصيل '
       . 'داخلية عن الخادم ولا يجوز بقاؤها على خادم إنتاج.</p>'
       . '<p>احذفه:</p><pre style="background:#151518;padding:14px;border-radius:8px;'
       . 'direction:ltr;text-align:left;color:#00D084;overflow-x:auto">sudo rm ' . $f . '</pre>'
       . '<p style="color:#8a8a94;font-size:.85rem">إن كنت تحتاجه فعلاً، ارفع نسخة جديدة '
       . 'أو حدّث تاريخ الملف بـ <code>touch</code>.</p>'
       . '</body></html>');
}
