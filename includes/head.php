<!DOCTYPE html>
<?php
if (session_status() === PHP_SESSION_NONE) { @session_start(); }
if (isset($_GET['lang']) && in_array($_GET['lang'], ['ar','en','tr'])) { $_SESSION['lang'] = $_GET['lang']; }
$__cur_lang = $_SESSION['lang'] ?? 'ar';
$__dir = ($__cur_lang === 'ar') ? 'rtl' : 'ltr';
$__lang_file = dirname(__DIR__) . '/lang/lang_' . $__cur_lang . '.php';
$t = file_exists($__lang_file) ? require $__lang_file : [];
if(!is_array($t)) $t = [];
?>
<html lang="<?= $__cur_lang ?>" dir="<?= $__dir ?>">

<head>
    <!-- تسريع التحميل الخاطف للوحة التحكم (مضاف برمجياً) -->
    <link rel="dns-prefetch" href="https://fonts.googleapis.com">
    <link rel="dns-prefetch" href="https://cdnjs.cloudflare.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preload" href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800;900&display=swap" as="style">
    <link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" as="style">

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>SHASHITY PRO</title>
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script>
    window.csrfToken = "<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>";
    window.lang = <?= json_encode([
        'actual_cpu_usage'       => $t['actual_cpu_usage'] ?? 'استهلاك المعالج الفعلي',
        'used'                   => $t['used'] ?? 'مستخدم: ',
        'total'                  => $t['total'] ?? 'الكلي: ',
        'scanning'               => $t['scanning'] ?? 'جاري الفحص...',
        'please_enter_key'       => $t['please_enter_key'] ?? 'يرجى إدخال المفتاح أولاً',
        'connected_successfully' => $t['connected_successfully'] ?? 'متصل ويعمل بنجاح',
        'invalid_key'            => $t['invalid_key'] ?? 'مفتاح غير صالح أو الحساب لا يعمل',
        'connection_error'       => $t['connection_error'] ?? 'خطأ في الاتصال',
        'fill_all_fields'        => $t['fill_all_fields'] ?? 'يرجى ملء المفتاح واسم المستخدم وكلمة المرور',
        'download_balance'       => $t['download_balance'] ?? 'متصل بنجاح — رصيد التنزيل: ',
        'connection_failed'      => $t['connection_failed'] ?? 'فشل الاتصال'
    ], JSON_UNESCAPED_UNICODE) ?>;
    function postDelete(action, id) {
        if(!confirm('هل أنت متأكد من الحذف؟')) return;
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '';
        const inputAction = document.createElement('input');
        inputAction.type = 'hidden';
        inputAction.name = action;
        inputAction.value = id;
        form.appendChild(inputAction);
        const inputCsrf = document.createElement('input');
        inputCsrf.type = 'hidden';
        inputCsrf.name = 'csrf_token';
        inputCsrf.value = window.csrfToken;
        form.appendChild(inputCsrf);
        document.body.appendChild(form);
        form.submit();
    }
</script>
<style>
