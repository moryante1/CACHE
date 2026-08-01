<?php
/**
 * رفع شعارات القنوات
 * Channel Logo Upload Handler
 *
 * الإصلاحات في هذه النسخة:
 *  1. حُذف session_start() المبكّر — كان يبدأ الجلسة قبل core/config.php
 *     فتُلغى إعدادات الأمان (httponly / secure / samesite / strict_mode).
 *  2. أُضيف تحقق CSRF (كان الرفع ممكناً من أي موقع خارجي).
 *  3. المسار أصبح مطلقاً عبر __DIR__ بدل مسار نسبي يعتمد على مجلد العمل.
 *  4. أُضيف .htaccess داخل مجلد الرفع لمنع تنفيذ PHP (حماية من Web Shell).
 *  5. تحقق فعلي من أن الملف صورة صالحة عبر getimagesize لا MIME فقط.
 *  6. صلاحيات المجلد 0755 والملف 0644، ومنع الأسماء المتوقّعة.
 */

require_once __DIR__ . '/core/config.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

/** إرسال رد JSON وإنهاء التنفيذ. */
function logoFail(string $error, int $code = 400): void
{
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $error], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── 1) المصادقة ──
if (!isAdminLoggedIn()) {
    logoFail('غير مصرح', 403);
}

// ── 2) الطريقة + CSRF ──
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    logoFail('طريقة غير مسموحة', 405);
}
if (!csrfValidate()) {
    logoFail('انتهت صلاحية الجلسة، يرجى تحديث الصفحة.', 403);
}

// ── 3) حدّ المعدل (منع إغراق القرص) ──
if (!rateLimit('upload:logo', 60, 60)) {
    logoFail('عدد كبير من الطلبات، حاول بعد قليل.', 429);
}

// ── 4) التحقق من وجود الملف ──
if (!isset($_FILES['logo']) || !is_array($_FILES['logo'])) {
    logoFail('لم يتم رفع الملف');
}

$file = $_FILES['logo'];

if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    $errors = [
        UPLOAD_ERR_INI_SIZE   => 'حجم الملف يتجاوز الحد المسموح في إعدادات الخادم',
        UPLOAD_ERR_FORM_SIZE  => 'حجم الملف يتجاوز الحد المسموح في النموذج',
        UPLOAD_ERR_PARTIAL    => 'تم رفع جزء من الملف فقط',
        UPLOAD_ERR_NO_FILE    => 'لم يتم رفع الملف',
        UPLOAD_ERR_NO_TMP_DIR => 'المجلد المؤقت غير موجود على الخادم',
        UPLOAD_ERR_CANT_WRITE => 'تعذّرت الكتابة على القرص',
        UPLOAD_ERR_EXTENSION  => 'امتداد PHP أوقف عملية الرفع',
    ];
    logoFail($errors[$file['error']] ?? 'فشل رفع الملف');
}

// حماية أساسية: يجب أن يكون ملفاً مرفوعاً فعلاً عبر HTTP POST
if (!is_uploaded_file($file['tmp_name'])) {
    logoFail('ملف غير صالح');
}

// ── 5) حجم الملف (2MB) ──
if ((int) $file['size'] > 2 * 1024 * 1024) {
    logoFail('حجم الملف كبير جداً (الحد الأقصى 2MB)');
}
if ((int) $file['size'] <= 0) {
    logoFail('الملف فارغ');
}

// ── 6) نوع الملف الفعلي (MIME) ──
$mimeToExt = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/gif'  => 'gif',
    'image/webp' => 'webp',
];

$mime = '';
if (function_exists('finfo_open')) {
    $finfo = @finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo !== false) {
        $mime = (string) @finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
    }
}
if ($mime === '' && function_exists('mime_content_type')) {
    $mime = (string) @mime_content_type($file['tmp_name']);
}

if (!isset($mimeToExt[$mime])) {
    logoFail('نوع الملف غير مسموح');
}

// ── 7) تحقق مزدوج: هل هي صورة فعلاً؟ (يمنع ملفاً نصياً بترويسة صورة) ──
$info = @getimagesize($file['tmp_name']);
$allowedImageTypes = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP];
if ($info === false || !in_array((int) ($info[2] ?? 0), $allowedImageTypes, true)) {
    logoFail('الملف ليس صورة صالحة');
}
// حدّ معقول للأبعاد يمنع هجمات "قنبلة الصور" (decompression bomb)
if ((int) $info[0] > 5000 || (int) $info[1] > 5000) {
    logoFail('أبعاد الصورة كبيرة جداً (الحد الأقصى 5000×5000)');
}

$safeExtension = $mimeToExt[$mime];

// ── 8) تجهيز مجلد الرفع (مسار مطلق) ──
$uploadDir = __DIR__ . '/uploads/logos/';
if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
    logoFail('تعذّر إنشاء مجلد الرفع', 500);
}
if (!is_writable($uploadDir)) {
    logoFail('مجلد الرفع غير قابل للكتابة', 500);
}

/* منع تنفيذ PHP داخل مجلد الرفع — حماية حاسمة ضد Web Shell.
   ⚠️ كل توجيه محاط بـ <IfModule>: التوجيه php_flag لا يوجد إلا مع
   mod_php، وعلى استضافات PHP-FPM/FastCGI يُعتبر أمراً مجهولاً
   ويُسقط المجلد بخطأ 500. الإحاطة تمنع ذلك تماماً. */
$guard = dirname($uploadDir) . '/.htaccess';
if (!is_file($guard)) {
    @file_put_contents(
        $guard,
        "<IfModule mod_php.c>\n    php_flag engine off\n</IfModule>\n"
        . "<IfModule mod_php7.c>\n    php_flag engine off\n</IfModule>\n"
        . "<IfModule mod_php8.c>\n    php_flag engine off\n</IfModule>\n"
        . "<IfModule mod_mime.c>\n"
        . "    RemoveHandler .php .phtml .php3 .php4 .php5 .php7 .php8 .phps\n"
        . "    RemoveType    .php .phtml .php3 .php4 .php5 .php7 .php8 .phps\n"
        . "    AddType text/plain .php .phtml .php3 .php4 .php5 .php7 .php8 .phps\n"
        . "</IfModule>\n"
        . "<IfModule mod_autoindex.c>\n    Options -Indexes\n</IfModule>\n"
    );
}

// ── 9) اسم ملف عشوائي غير قابل للتخمين ──
$filename = 'logo_' . bin2hex(random_bytes(12)) . '.' . $safeExtension;
$filepath = $uploadDir . $filename;

// ── 10) النقل ──
if (!move_uploaded_file($file['tmp_name'], $filepath)) {
    logoFail('فشل رفع الملف', 500);
}
@chmod($filepath, 0644);

logActivity('رفع شعار قناة', $filename);

echo json_encode([
    'success'  => true,
    'url'      => 'uploads/logos/' . $filename,
    'filename' => $filename,
], JSON_UNESCAPED_UNICODE);
