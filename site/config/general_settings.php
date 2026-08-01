<?php
// index.php lines 121-274

/* ══════════════════════════════════════════════════════════════════════════
   ║  الإعدادات العامة الحساسة (يُتحكم بها من admin.php > الإعدادات العامة)     ║
   ║  كل قيمة معلّقة بوظيفتها. لا حاجة لتعديل أي ملف لتغييرها.                  ║
   ══════════════════════════════════════════════════════════════════════════ */
$gs_maintenance_mode    = ($settings['maintenance_mode']    ?? '0') === '1'; // وضع الصيانة (إغلاق الموقع)
$gs_maintenance_message = $settings['maintenance_message']  ?? 'الموقع تحت الصيانة حالياً، نعود قريباً بإذن الله'; // نص صفحة الصيانة
$gs_gate_enabled        = ($settings['gate_enabled']        ?? '0') === '1'; // قفل الموقع بكلمة مرور
$gs_gate_password       = $settings['gate_password']        ?? '';          // كلمة سر الدخول للموقع
$gs_force_https         = ($settings['force_https']         ?? '0') === '1'; // إجبار HTTPS
$gs_block_devtools      = ($settings['block_devtools']      ?? '0') === '1'; // منع أدوات المطور
$gs_disable_download    = ($settings['disable_download']    ?? '0') === '1'; // منع تحميل الفيديوهات
$gs_announce_enabled    = ($settings['announcement_enabled']?? '0') === '1'; // إظهار الشريط الإعلاني
$gs_announce_text       = $settings['announcement_text']    ?? '';          // نص الإعلان
$gs_announce_link       = $settings['announcement_link']    ?? '';          // رابط الإعلان
$gs_custom_head_code    = $settings['custom_head_code']     ?? '';          // كود مخصص داخل head
$gs_custom_body_code    = $settings['custom_body_code']     ?? '';          // كود مخصص قبل body
$gs_contact_whatsapp    = $settings['contact_whatsapp']     ?? '9647512328848';
$gs_contact_facebook    = $settings['contact_facebook']     ?? 'facebook.com/xxkpq';
$gs_contact_email       = $settings['contact_email']        ?? 'info@shashety-pro.com';

/* ══════════════════════════════════════════════════════════════════════════
   ║  إعدادات المجموعات المتقدمة (يُتحكم بها من admin.php > الإعدادات المتقدمة) ║
   ║  كل قيمة تُقرأ من جدول settings مع قيمتها الأصلية معلّقة بجانبها.          ║
   ║  المتغيّر $cfg يجمع كل الإعدادات؛ استعملها في أي مكان بـ $cfg['key'].     ║
   ══════════════════════════════════════════════════════════════════════════ */
$cfg = [];
// ── إعدادات البث الخادمية (20 إعداد) ──
$cfg['srv_hls_segment_duration'] = $settings['srv_hls_segment_duration'] ?? '6'; // الأصلي: 6
$cfg['srv_playlist_length'] = $settings['srv_playlist_length'] ?? '5'; // الأصلي: 5
$cfg['srv_llhls_enable'] = ($settings['srv_llhls_enable'] ?? '0') === '1'; // الأصلي: 0
$cfg['srv_ffmpeg_params'] = $settings['srv_ffmpeg_params'] ?? ''; // الأصلي: فارغ
$cfg['srv_hwaccel'] = $settings['srv_hwaccel'] ?? 'none'; // الأصلي: none
$cfg['srv_thread_count'] = $settings['srv_thread_count'] ?? '0'; // الأصلي: 0
$cfg['srv_tcp_udp_buffer'] = $settings['srv_tcp_udp_buffer'] ?? '8192'; // الأصلي: 8192
$cfg['srv_socket_buffer'] = $settings['srv_socket_buffer'] ?? '65536'; // الأصلي: 65536
$cfg['srv_cdn_failover'] = ($settings['srv_cdn_failover'] ?? '0') === '1'; // الأصلي: 0
$cfg['srv_stream_priority'] = $settings['srv_stream_priority'] ?? 'normal'; // الأصلي: normal
$cfg['srv_health_check_interval'] = $settings['srv_health_check_interval'] ?? '30'; // الأصلي: 30
$cfg['srv_auto_restart_stream'] = ($settings['srv_auto_restart_stream'] ?? '1') === '1'; // الأصلي: 1
$cfg['srv_stream_timeout'] = $settings['srv_stream_timeout'] ?? '20'; // الأصلي: 20
$cfg['srv_packet_loss_recovery'] = ($settings['srv_packet_loss_recovery'] ?? '1') === '1'; // الأصلي: 1
$cfg['srv_jitter_buffer'] = $settings['srv_jitter_buffer'] ?? '500'; // الأصلي: 500
$cfg['srv_abr_enable'] = ($settings['srv_abr_enable'] ?? '1') === '1'; // الأصلي: 1
$cfg['srv_max_bitrate'] = $settings['srv_max_bitrate'] ?? '8000'; // الأصلي: 8000
$cfg['srv_min_bitrate'] = $settings['srv_min_bitrate'] ?? '800'; // الأصلي: 800
$cfg['srv_gop_size'] = $settings['srv_gop_size'] ?? '48'; // الأصلي: 48
$cfg['srv_keyframe_interval'] = $settings['srv_keyframe_interval'] ?? '2'; // الأصلي: 2

// ── إعدادات الواجهة (7 إعداد) ──
$cfg['ui_theme'] = $settings['ui_theme'] ?? 'dark'; // الأصلي: dark
$cfg['theme_color'] = $settings['theme_color'] ?? '#e50914'; // الأصلي: #e50914
$cfg['ui_font'] = $settings['ui_font'] ?? 'Tajawal'; // الأصلي: Tajawal
$cfg['ui_font_size'] = $settings['ui_font_size'] ?? '16'; // الأصلي: 16
$cfg['ui_transitions'] = ($settings['ui_transitions'] ?? '1') === '1'; // الأصلي: 1
$cfg['ui_banner'] = $settings['ui_banner'] ?? ''; // الأصلي: فارغ
$cfg['ui_icon_style'] = $settings['ui_icon_style'] ?? 'solid'; // الأصلي: solid

// ── إعدادات الصور (5 إعداد) ──
$cfg['img_default_channel'] = $settings['img_default_channel'] ?? ''; // الأصلي: فارغ
$cfg['img_default_movie'] = $settings['img_default_movie'] ?? ''; // الأصلي: فارغ
$cfg['img_default_series'] = $settings['img_default_series'] ?? ''; // الأصلي: فارغ
$cfg['img_quality'] = $settings['img_quality'] ?? '85'; // الأصلي: 85
$cfg['img_compression'] = ($settings['img_compression'] ?? '1') === '1'; // الأصلي: 1

// ── إعدادات المستخدم (7 إعداد) ──
$cfg['usr_save_last_watch'] = ($settings['usr_save_last_watch'] ?? '1') === '1'; // الأصلي: 1
$cfg['usr_autoplay'] = ($settings['usr_autoplay'] ?? '1') === '1'; // الأصلي: 1
$cfg['usr_dark_mode'] = ($settings['usr_dark_mode'] ?? '1') === '1'; // الأصلي: 1
$cfg['usr_language'] = $settings['usr_language'] ?? 'ar'; // الأصلي: ar
$cfg['usr_notifications'] = ($settings['usr_notifications'] ?? '1') === '1'; // الأصلي: 1
$cfg['usr_favorites'] = ($settings['usr_favorites'] ?? '1') === '1'; // الأصلي: 1
$cfg['usr_watch_history'] = ($settings['usr_watch_history'] ?? '1') === '1'; // الأصلي: 1

// ── الأداء (Performance) (8 إعداد) ──
$cfg['perf_cache_duration'] = $settings['perf_cache_duration'] ?? '3600'; // الأصلي: 3600
$cfg['perf_image_cache'] = ($settings['perf_image_cache'] ?? '1') === '1'; // الأصلي: 1
$cfg['perf_api_cache'] = ($settings['perf_api_cache'] ?? '1') === '1'; // الأصلي: 1
$cfg['perf_gzip_brotli'] = ($settings['perf_gzip_brotli'] ?? '1') === '1'; // الأصلي: 1
$cfg['perf_lazy_loading'] = ($settings['perf_lazy_loading'] ?? '1') === '1'; // الأصلي: 1
$cfg['perf_http_version'] = $settings['perf_http_version'] ?? '2'; // الأصلي: 2
$cfg['perf_prefetch'] = ($settings['perf_prefetch'] ?? '1') === '1'; // الأصلي: 1
$cfg['perf_preconnect'] = ($settings['perf_preconnect'] ?? '1') === '1'; // الأصلي: 1

// ── إعدادات الترجمة (6 إعداد) ──
$cfg['sub_default_language'] = $settings['sub_default_language'] ?? 'ar'; // الأصلي: ar
$cfg['sub_font_size'] = $settings['sub_font_size'] ?? '18'; // الأصلي: 18
$cfg['sub_font_color'] = $settings['sub_font_color'] ?? '#ffffff'; // الأصلي: #ffffff
$cfg['sub_bg_color'] = $settings['sub_bg_color'] ?? '#000000'; // الأصلي: #000000
$cfg['sub_position'] = $settings['sub_position'] ?? 'bottom'; // الأصلي: bottom
$cfg['sub_bg_opacity'] = $settings['sub_bg_opacity'] ?? '60'; // الأصلي: 60

// ── إعدادات المسلسلات (5 إعداد) ──
$cfg['sr_resume_last_ep'] = ($settings['sr_resume_last_ep'] ?? '1') === '1'; // الأصلي: 1
$cfg['sr_auto_next_ep'] = ($settings['sr_auto_next_ep'] ?? '1') === '1'; // الأصلي: 1
$cfg['sr_skip_intro'] = ($settings['sr_skip_intro'] ?? '0') === '1'; // الأصلي: 0
$cfg['sr_skip_outro'] = ($settings['sr_skip_outro'] ?? '0') === '1'; // الأصلي: 0
$cfg['sr_season_order'] = $settings['sr_season_order'] ?? 'asc'; // الأصلي: asc

// ── إعدادات الأفلام (7 إعداد) ──
$cfg['mv_per_page'] = $settings['mv_per_page'] ?? '24'; // الأصلي: 24
$cfg['mv_default_quality'] = $settings['mv_default_quality'] ?? 'auto'; // الأصلي: auto
$cfg['mv_auto_subtitle'] = ($settings['mv_auto_subtitle'] ?? '0') === '1'; // الأصلي: 0
$cfg['mv_subtitle_language'] = $settings['mv_subtitle_language'] ?? 'ar'; // الأصلي: ar
$cfg['mv_play_trailer'] = ($settings['mv_play_trailer'] ?? '1') === '1'; // الأصلي: 1
$cfg['mv_show_similar'] = ($settings['mv_show_similar'] ?? '1') === '1'; // الأصلي: 1
$cfg['mv_resume_watch'] = ($settings['mv_resume_watch'] ?? '1') === '1'; // الأصلي: 1

// ── إعدادات القنوات (7 إعداد) ──
$cfg['ch_per_page'] = $settings['ch_per_page'] ?? '40'; // الأصلي: 40
$cfg['ch_order'] = $settings['ch_order'] ?? 'display_order'; // الأصلي: display_order
$cfg['ch_group_order'] = $settings['ch_group_order'] ?? 'display_order'; // الأصلي: display_order
$cfg['ch_hide_offline'] = ($settings['ch_hide_offline'] ?? '0') === '1'; // الأصلي: 0
$cfg['ch_auto_status'] = ($settings['ch_auto_status'] ?? '0') === '1'; // الأصلي: 0
$cfg['ch_check_interval'] = $settings['ch_check_interval'] ?? '60'; // الأصلي: 60
$cfg['ch_resume_last'] = ($settings['ch_resume_last'] ?? '1') === '1'; // الأصلي: 1

// ── إعدادات مشغّل الفيديو (14 إعداد) ──
$cfg['pl_autoplay'] = ($settings['pl_autoplay'] ?? '1') === '1'; // الأصلي: 1
$cfg['pl_mute_on_start'] = ($settings['pl_mute_on_start'] ?? '0') === '1'; // الأصلي: 0
$cfg['pl_auto_fullscreen'] = ($settings['pl_auto_fullscreen'] ?? '0') === '1'; // الأصلي: 0
$cfg['pl_pip'] = ($settings['pl_pip'] ?? '1') === '1'; // الأصلي: 1
$cfg['pl_webcast'] = ($settings['pl_webcast'] ?? '1') === '1'; // الأصلي: 1
$cfg['pl_seek_buttons'] = ($settings['pl_seek_buttons'] ?? '1') === '1'; // الأصلي: 1
$cfg['pl_playback_speed'] = $settings['pl_playback_speed'] ?? '1'; // الأصلي: 1
$cfg['pl_thumbnails'] = ($settings['pl_thumbnails'] ?? '1') === '1'; // الأصلي: 1
$cfg['pl_show_channel_logo'] = ($settings['pl_show_channel_logo'] ?? '1') === '1'; // الأصلي: 1
$cfg['pl_show_channel_name'] = ($settings['pl_show_channel_name'] ?? '1') === '1'; // الأصلي: 1
$cfg['pl_show_clock'] = ($settings['pl_show_clock'] ?? '0') === '1'; // الأصلي: 0
$cfg['pl_show_viewers'] = ($settings['pl_show_viewers'] ?? '0') === '1'; // الأصلي: 0
$cfg['pl_show_share'] = ($settings['pl_show_share'] ?? '1') === '1'; // الأصلي: 1
$cfg['pl_show_report'] = ($settings['pl_show_report'] ?? '1') === '1'; // الأصلي: 1

// ── إعدادات البث (Streaming) (17 إعداد) ──
$cfg['st_low_latency'] = ($settings['st_low_latency'] ?? '0') === '1'; // الأصلي: 0
$cfg['st_buffer_size'] = $settings['st_buffer_size'] ?? '30'; // الأصلي: 30
$cfg['st_startup_buffer'] = $settings['st_startup_buffer'] ?? '2'; // الأصلي: 2
$cfg['st_max_buffer'] = $settings['st_max_buffer'] ?? '60'; // الأصلي: 60
$cfg['st_back_buffer'] = $settings['st_back_buffer'] ?? '90'; // الأصلي: 90
$cfg['st_live_sync'] = $settings['st_live_sync'] ?? '3'; // الأصلي: 3
$cfg['st_auto_quality'] = ($settings['st_auto_quality'] ?? '1') === '1'; // الأصلي: 1
$cfg['st_default_quality'] = $settings['st_default_quality'] ?? 'auto'; // الأصلي: auto
$cfg['st_allow_quality_change'] = ($settings['st_allow_quality_change'] ?? '1') === '1'; // الأصلي: 1
$cfg['st_auto_reconnect'] = ($settings['st_auto_reconnect'] ?? '1') === '1'; // الأصلي: 1
$cfg['st_reconnect_attempts'] = $settings['st_reconnect_attempts'] ?? '5'; // الأصلي: 5
$cfg['st_reconnect_timeout'] = $settings['st_reconnect_timeout'] ?? '3'; // الأصلي: 3
$cfg['st_failover'] = ($settings['st_failover'] ?? '1') === '1'; // الأصلي: 1
$cfg['st_protocol'] = $settings['st_protocol'] ?? 'hls'; // الأصلي: hls
$cfg['st_llhls_support'] = ($settings['st_llhls_support'] ?? '0') === '1'; // الأصلي: 0
$cfg['st_playlist_refresh'] = $settings['st_playlist_refresh'] ?? '6'; // الأصلي: 6
$cfg['st_stream_cache'] = ($settings['st_stream_cache'] ?? '1') === '1'; // الأصلي: 1


// هل الزائر الحالي مدير مسجّل الدخول؟ (لتجاوز الصيانة والقفل)
