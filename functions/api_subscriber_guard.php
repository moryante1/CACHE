<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 *  حارس الاشتراك على واجهة api.php
 * ───────────────────────────────────────────────────────────────────────────
 *  لماذا هذا الملف موجود:
 *
 *  حماية index.php وحدها **لا تحمي المحتوى**. صفحة الموقع ليست إلا غلافاً
 *  حول api.php، وهذه الأخيرة كانت مفتوحة تماماً بلا أي مصادقة ومع
 *  Access-Control-Allow-Origin: *. أي شخص يفتح:
 *
 *      http://موقعك/api.php?action=all_content
 *
 *  كان يحصل على مكتبة القنوات والأفلام كاملة دون تسجيل دخول ولا كوبون —
 *  فيصبح نظام الاشتراكات حاجزاً بصرياً لا أكثر. الاختبار الصحيح لأي بوابة
 *  ليس "هل تختفي الواجهة؟" بل "هل تُمنع البيانات؟".
 *
 *  السلوك:
 *    • الحماية موقوفة              → لا شيء (السلوك السابق تماماً)
 *    • جلسة إدارة                  → مسموح
 *    • جلسة مشترك باشتراك فعّال     → مسموح
 *    • ما عدا ذلك                  → 401 JSON ويتوقف
 *
 *  نقاط عامة مستثناة (content_version وstats) لأنها لا تكشف محتوى وتحتاجها
 *  الواجهة لتقرر هل تعرض شاشة الدخول أصلاً.
 * ═══════════════════════════════════════════════════════════════════════════
 */

require_once __DIR__ . '/subscriptions.php';

/**
 * @param string $action الإجراء المطلوب من api.php
 */
function apiSubscriberGuard(string $action = ''): void
{
    if (!subsEnsureSchema())  return;   // عطل في الجداول لا يجوز أن يقفل الـAPI
    if (!subsProtectionOn())  return;   // الخاصية موقوفة

    // نقاط لا تكشف محتوى
    static $public = ['content_version', 'stats'];
    if (in_array($action, $public, true)) return;

    /* نقرأ الجلسة ثم نغلقها فوراً.
       api.php يُنادى عشرات المرات لبناء الصفحة الواحدة، وترك القفل
       مفتوحاً يجعل نداءات المستخدم تصطفّ واحداً تلو الآخر بدل أن
       تتوازى — فتبطؤ الصفحة بلا سبب ظاهر. لا شيء هنا يكتب في الجلسة. */
    if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
    $isAdmin = function_exists('isAdminLoggedIn') && isAdminLoggedIn();
    $uid     = (int)($_SESSION['site_user_id'] ?? 0);
    if (session_status() === PHP_SESSION_ACTIVE) { @session_write_close(); }

    // الإدارة تتجاوز (المعاينة من اللوحة)
    if ($isAdmin) return;

    if ($uid > 0) {
        $u = subsUserById($uid);
        if ($u) {
            $s = subsUserStatus($u);
            if ($s['active']) return;                       // ✔ مسموح

            apiSubscriberDeny(
                $s['state'] === 'expired' ? 'subscription_expired' : 'subscription_inactive',
                $s['state']
            );
        }
        /* الحساب حُذف من اللوحة والجلسة باقية.
           ⚠ الجلسة مُغلقة الآن، والكتابة في $_SESSION بعد الإغلاق تُعدّل
           المصفوفة في الذاكرة ولا تُحفظ — فيبقى المعرّف الميت إلى الأبد
           ويُستعلَم عنه في كل طلب. نفتحها لحظةً لنكتب ثم نغلقها. */
        if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
        unset($_SESSION['site_user_id'], $_SESSION['site_username']);
        if (session_status() === PHP_SESSION_ACTIVE) { @session_write_close(); }
    }

    apiSubscriberDeny('login_required', 'guest');
}

/** ردّ رفض موحّد بصيغة JSON. */
function apiSubscriberDeny(string $code, string $state): void
{
    while (ob_get_level() > 0) { @ob_end_clean(); }
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    // نلغي CORS المفتوح في ردّ الرفض: لا داعي لأن يقرأ أي موقع خارجي
    // تفاصيل حالة اشتراك مستخدمينا.
    header_remove('Access-Control-Allow-Origin');
    echo json_encode([
        'success'   => false,
        'error'     => $code,
        'state'     => $state,
        'login_url' => './',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
