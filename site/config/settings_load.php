<?php
// index.php lines 96-120
$stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
$settings = [];
while ($row = $stmt->fetch()) $settings[$row['setting_key']] = $row['setting_value'];

$site_name        = $settings['site_name']        ?? 'Shashety';
$site_description = $settings['site_description'] ?? 'نظام IPTV احترافي';
$site_logo        = $settings['site_logo']        ?? '';
$welcome_title    = $settings['welcome_title']    ?? 'مرحباً بك في عالم البث المباشر';
$welcome_subtitle = $settings['welcome_subtitle'] ?? 'شاهد آلاف القنوات من جميع أنحاء العالم';
$footer_text      = $settings['footer_text']      ?? 'جميع الحقوق محفوظة © 2024 Shashety';
$theme_color      = $settings['theme_color']      ?? '#e50914';
$custom_css_db    = $settings['custom_css']       ?? '';

// ══ إعدادات إخفاء عناصر الواجهة (يُتحكم بها من admin.php) ══
$hide_search        = ($settings['hide_search']        ?? '0') === '1';
$hide_notifications = ($settings['hide_notifications'] ?? '0') === '1';
$hide_favorites     = ($settings['hide_favorites']     ?? '0') === '1';
$hide_music         = ($settings['hide_music']         ?? '0') === '1';
$hide_admin_btn     = ($settings['hide_admin_btn']     ?? '0') === '1';
$hide_social        = ($settings['hide_social']        ?? '0') === '1';
$hide_download      = ($settings['hide_download']      ?? '0') === '1';
$hide_cast          = ($settings['hide_cast']          ?? '0') === '1';
$hide_most_watched  = ($settings['hide_most_watched']  ?? '0') === '1';
$hide_suggestions   = ($settings['hide_suggestions']   ?? '0') === '1';
$hide_screensaver   = ($settings['hide_screensaver']   ?? '0') === '1';
