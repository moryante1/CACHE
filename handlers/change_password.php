<?php
/**
 * معالج تغيير كلمة مرور المدير الحالي
 * ══════════════════════════════════════════════════════════════
 *
 * ⚠️ سبب إنشاء هذا الملف:
 * صفحة pages/change_password.php تعرض نموذجاً كاملاً يرسل الحقول
 * current_password و new_password و confirm_password مع الزر
 * change_password... لكن **لم يكن في المشروع كله أي كود يقرأ هذه
 * الحقول**. بحثت في كل الملفات فلم أجد أي معالج.
 *
 * النتيجة: المستخدم يملأ النموذج ويضغط «حفظ»، فتُعاد الصفحة كما هي
 * بلا رسالة نجاح ولا خطأ — و**كلمة المرور لا تتغيّر إطلاقاً**.
 * وحتى رسالتا $_SESSION['pw_ok'] و $_SESSION['pw_err'] اللتان
 * تعرضهما الصفحة لم يكن أحد يضبطهما أبداً.
 *
 * هذا الملف يوفّر المعالج المفقود:
 *  • يتحقق من كلمة المرور الحالية قبل أي تغيير
 *  • يتحقق من تطابق التأكيد وطول الكلمة الجديدة
 *  • يحدّث admin_users وجدول users القديم معاً (حتى لا يختلفا)
 *  • يجدّد معرّف الجلسة بعد تغيير بيانات الاعتماد
 *  • يحمي الطلب برمز CSRF
 */

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['change_password'])) {

    $__redirect = static function (): void {
        header('Location: admin.php#change-password');
        exit;
    };

    // ── CSRF ──
    if (!csrfValidate()) {
        $_SESSION['pw_err'] = 'انتهت صلاحية الجلسة. أعد تحميل الصفحة وحاول مجدداً.';
        $__redirect();
    }

    $__cur     = (string) ($_POST['current_password'] ?? '');
    $__new     = (string) ($_POST['new_password'] ?? '');
    $__confirm = (string) ($_POST['confirm_password'] ?? '');
    $__user    = (string) ($_SESSION['admin_username'] ?? '');

    if ($__user === '') {
        $_SESSION['pw_err'] = 'تعذّر تحديد المستخدم الحالي. سجّل الدخول من جديد.';
        $__redirect();
    }

    if ($__cur === '' || $__new === '' || $__confirm === '') {
        $_SESSION['pw_err'] = 'يرجى تعبئة جميع الحقول.';
        $__redirect();
    }

    if ($__new !== $__confirm) {
        $_SESSION['pw_err'] = 'كلمة المرور الجديدة وتأكيدها غير متطابقين.';
        $__redirect();
    }

    if (mb_strlen($__new) < 8) {
        $_SESSION['pw_err'] = 'كلمة المرور الجديدة يجب أن تكون 8 أحرف على الأقل.';
        $__redirect();
    }

    if ($__new === $__cur) {
        $_SESSION['pw_err'] = 'كلمة المرور الجديدة مطابقة للحالية.';
        $__redirect();
    }

    // حدّ معدل: يمنع تخمين كلمة المرور الحالية
    if (function_exists('rateLimit') && !rateLimit('pwchange:' . $__user, 10, 600)) {
        $_SESSION['pw_err'] = 'محاولات كثيرة. انتظر بضع دقائق ثم حاول مجدداً.';
        $__redirect();
    }

    try {
        // ── جلب الحساب من admin_users، ثم من users القديم إن لزم ──
        $__row    = null;
        $__source = '';

        try {
            $st = $pdo->prepare("SELECT id, password_hash FROM admin_users WHERE username = ? LIMIT 1");
            $st->execute([$__user]);
            $__row = $st->fetch(PDO::FETCH_ASSOC);
            if ($__row) { $__source = 'admin_users'; }
        } catch (PDOException $e) { /* الجدول غير موجود */ }

        if (!$__row) {
            try {
                $st = $pdo->prepare("SELECT id, password AS password_hash FROM users WHERE username = ? LIMIT 1");
                $st->execute([$__user]);
                $__row = $st->fetch(PDO::FETCH_ASSOC);
                if ($__row) { $__source = 'users'; }
            } catch (PDOException $e) { /* الجدول غير موجود */ }
        }

        if (!$__row) {
            $_SESSION['pw_err'] = 'تعذّر العثور على حسابك في قاعدة البيانات.';
            $__redirect();
        }

        // ── التحقق من كلمة المرور الحالية ──
        if (!password_verify($__cur, (string) $__row['password_hash'])) {
            logActivity('محاولة تغيير كلمة مرور بكلمة حالية خاطئة', 'user=' . $__user);
            $_SESSION['pw_err'] = 'كلمة المرور الحالية غير صحيحة.';
            $__redirect();
        }

        $__hash = password_hash($__new, PASSWORD_DEFAULT, ['cost' => 12]);

        // ── التحديث في الجدولين معاً حتى لا يختلفا ──
        $pdo->beginTransaction();

        if ($__source === 'admin_users') {
            $pdo->prepare("UPDATE admin_users SET password_hash = ? WHERE id = ?")
                ->execute([$__hash, $__row['id']]);
        }

        try {
            $pdo->prepare("UPDATE users SET password = ? WHERE username = ?")
                ->execute([$__hash, $__user]);
        } catch (PDOException $e) {
            // تركيبات جديدة قد لا تحتفظ بالجدول القديم
            if ($e->getCode() !== '42S02') { throw $e; }
        }

        $pdo->commit();

        // تدوير معرّف الجلسة ورمز CSRF بعد تغيير بيانات الاعتماد
        session_regenerate_id(true);
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        logActivity('تغيير كلمة المرور', 'user=' . $__user);
        $_SESSION['pw_ok'] = 'تم تغيير كلمة المرور بنجاح.';
        $__redirect();

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log('change_password: ' . $e->getMessage());
        $_SESSION['pw_err'] = 'تعذّر تغيير كلمة المرور. راجع سجل أخطاء الخادم.';
        $__redirect();
    }
}
