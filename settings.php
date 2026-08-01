<?php
/**
 * معالج حفظ إعدادات الموقع
 * Shashety IPTV - Site Settings Handler
 *
 * الإصلاحات في هذه النسخة:
 *  1. أُضيف تحقق CSRF (كان أي موقع خارجي يستطيع تغيير إعدادات الموقع
 *     على متصفح مدير مسجّل الدخول).
 *  2. لم تعد رسائل استثناءات قاعدة البيانات تُعرض للعميل — تُسجَّل فقط.
 *  3. الحفظ داخل معاملة (transaction) بدل 13 استعلاماً منفصلاً:
 *     إما تُحفظ كل الإعدادات أو لا شيء، وأسرع بكثير.
 *  4. استعلام مُحضَّر واحد يُعاد استخدامه بدل تحضيره في كل دورة.
 *  5. تحقق من صحة theme_color والبريد قبل الحفظ.
 *  6. أُضيف رمز HTTP مناسب لكل حالة خطأ.
 */

require_once __DIR__ . '/core/config.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

/** رد فشل موحّد. */
function settingsFail(string $message, int $code = 400): void
{
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isAdminLoggedIn()) {
    settingsFail('غير مصرح', 403);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    settingsFail('طريقة غير صحيحة', 405);
}

if (!csrfValidate()) {
    settingsFail('انتهت صلاحية الجلسة، يرجى تحديث الصفحة.', 403);
}

$action = (string) ($_POST['action'] ?? '');

switch ($action) {

    case 'save_settings':
        $themeColor = trim((string) ($_POST['theme_color'] ?? '#4cc9f0'));
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $themeColor)) {
            $themeColor = '#4cc9f0';
        }

        $contactEmail = trim((string) ($_POST['contact_email'] ?? ''));
        if ($contactEmail !== '' && !validateEmail($contactEmail)) {
            settingsFail('البريد الإلكتروني غير صالح');
        }

        $settings = [
            'site_name'             => sanitizeInput($_POST['site_name'] ?? 'Shashety IPTV'),
            'site_description'      => sanitizeInput($_POST['site_description'] ?? 'نظام IPTV احترافي'),
            'site_logo'             => sanitizeInput($_POST['site_logo'] ?? ''),
            'welcome_title'         => sanitizeInput($_POST['welcome_title'] ?? 'مرحباً بك في عالم البث المباشر'),
            'welcome_subtitle'      => sanitizeInput($_POST['welcome_subtitle'] ?? 'شاهد آلاف القنوات من جميع أنحاء العالم'),
            'footer_text'           => sanitizeInput($_POST['footer_text'] ?? 'جميع الحقوق محفوظة'),
            'contact_phone'         => sanitizeInput($_POST['contact_phone'] ?? ''),
            'contact_email'         => sanitizeInput($contactEmail),
            'contact_facebook'      => sanitizeInput($_POST['contact_facebook'] ?? ''),
            'contact_whatsapp'      => sanitizeInput($_POST['contact_whatsapp'] ?? ''),
            'theme_color'           => $themeColor,
            'show_categories_count' => isset($_POST['show_categories_count']) ? '1' : '0',
            'show_channels_count'   => isset($_POST['show_channels_count']) ? '1' : '0',
        ];

        try {
            // معاملة واحدة + استعلام مُحضَّر مُعاد الاستخدام = أسرع وأكثر أماناً
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
            );

            foreach ($settings as $key => $value) {
                $stmt->execute([$key, $value]);
            }

            $pdo->commit();

            // إبطال الكاش حتى تظهر الإعدادات الجديدة فوراً على الواجهة
            if (function_exists('cacheDelete')) {
                cacheDelete('site_settings');
            }
            if (function_exists('shashety_cache_clear')) {
                shashety_cache_clear();
            }

            logActivity('تحديث إعدادات الموقع', 'keys=' . count($settings));

            echo json_encode(
                ['success' => true, 'message' => 'تم حفظ الإعدادات بنجاح'],
                JSON_UNESCAPED_UNICODE
            );

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('settings.php save_settings: ' . $e->getMessage());
            settingsFail('تعذّر حفظ الإعدادات. راجع سجل أخطاء الخادم.', 500);
        }
        break;

    case 'get_settings':
        try {
            $rows     = $pdo->query("SELECT setting_key, setting_value FROM settings")->fetchAll(PDO::FETCH_ASSOC);
            $settings = [];
            foreach ($rows as $row) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }

            echo json_encode(
                ['success' => true, 'settings' => $settings],
                JSON_UNESCAPED_UNICODE
            );

        } catch (Throwable $e) {
            error_log('settings.php get_settings: ' . $e->getMessage());
            settingsFail('تعذّر جلب الإعدادات.', 500);
        }
        break;

    default:
        settingsFail('إجراء غير معروف');
}
