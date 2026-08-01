<?php /* ═══ تصدير إعدادات المجموعات إلى JS + تطبيق فعلي على الواجهة والمشغّل ═══ */ ?>
<script>
/* [أمان] لا نُصدّر كل $cfg إلى المتصفح — كان يكشف إعدادات الخادم لأي زائر.
   نُصدّر فقط المفاتيح التي تحتاجها الواجهة فعلاً (قائمة بيضاء). */
window.SITE_CFG = <?php
$__cfg_public_keys = [
    // الواجهة
    'theme_color','ui_font','ui_font_size','ui_transitions','usr_dark_mode',
    // المشغّل (سلوك مرئي فقط)
    'pl_autoplay','pl_mute_on_start','pl_pip','pl_playback_speed','pl_auto_fullscreen',
    'pl_show_channel_name','pl_show_channel_logo','pl_webcast','pl_show_clock',
    'pl_seek_buttons','pl_show_viewers','pl_show_share','pl_show_report',
    // الترجمة
    'sub_font_size','sub_font_color','sub_bg_color','sub_bg_opacity',
    // ميزات المستخدم
    'usr_notifications','usr_favorites','usr_watch_history',
    // الأفلام
    'mv_play_trailer','mv_show_similar',
    // إعدادات hls.js من جهة العميل فقط
    'st_default_quality','st_low_latency','st_max_buffer','st_back_buffer',
    'st_buffer_size','st_live_sync','st_auto_quality',
    'st_reconnect_attempts','st_reconnect_timeout',
];
$__cfg_public = [];
foreach ($__cfg_public_keys as $__k) {
    if (array_key_exists($__k, $cfg)) $__cfg_public[$__k] = $cfg[$__k];
}
echo json_encode($__cfg_public, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?>;
(function(){
  var C = window.SITE_CFG || {};
  // ── تطبيق إعدادات الواجهة (لون/خط/حجم الخط/الانتقالات/الخ) ──
  try {
    if (C.theme_color) document.documentElement.style.setProperty('--accent', C.theme_color);
    if (C.ui_font_size) document.documentElement.style.setProperty('--base-font-size', C.ui_font_size + 'px');
    if (C.ui_font) document.body.style.fontFamily = "'" + C.ui_font + "', Tajawal, sans-serif";
    if (C.ui_transitions === false) { var st=document.createElement('style'); st.textContent='*{transition:none !important;animation:none !important}'; document.head.appendChild(st); }
    
    // تطبيق إعدادات الإظهار والإخفاء المرئية
    var extraCss = '';
    if (C.pl_show_channel_name === false) extraCss += '#pChannelName{display:none !important;} ';
    if (C.pl_show_channel_logo === false) extraCss += '.p-channel-logo{display:none !important;} ';
    if (C.pl_webcast === false) extraCss += '[onclick="castToSmartWvc()"]{display:none !important;} ';
    if (C.pl_show_clock === false) extraCss += '#nxClock{display:none !important;} ';
    if (C.usr_notifications === false) extraCss += '.p-notifications,[onclick*="syncNotifications"]{display:none !important;} ';
    if (C.usr_favorites === false) extraCss += '.p-favorites,[onclick*="MyFavs"]{display:none !important;} ';
    if (C.usr_watch_history === false) extraCss += '.p-history,[onclick*="resumeWatch"]{display:none !important;} ';
    if (C.pl_seek_buttons === false) extraCss += '.p-seek-btn{display:none !important;} ';
    if (C.pl_show_viewers === false) extraCss += '#pViewers,.viewers-count{display:none !important;} ';
    if (C.pl_show_share === false) extraCss += '.p-share,[onclick*="share"]{display:none !important;} ';
    if (C.pl_show_report === false) extraCss += '.p-report,[onclick*="report"]{display:none !important;} ';
    if (C.mv_play_trailer === false) extraCss += '#trailerBtn,[onclick*="trailer"]{display:none !important;} ';
    if (C.mv_show_similar === false) extraCss += '#similarBox,.similar-section{display:none !important;} ';
    if (C.usr_dark_mode === false) extraCss += 'body{background:#f0f0f0 !important; color:#000 !important;} '; // تطبيق بسيط للوضع الفاتح
    
    // إعدادات الترجمة
    if (C.sub_font_size) extraCss += 'video#html5Player::cue{font-size:'+C.sub_font_size+'px !important;} ';
    if (C.sub_font_color) extraCss += 'video#html5Player::cue{color:'+C.sub_font_color+' !important;} ';
    if (C.sub_bg_color) {
      let opacity = C.sub_bg_opacity ? parseInt(C.sub_bg_opacity)/100 : 0.78;
      let alpha = Math.round(opacity * 255).toString(16).padStart(2,'0');
      let hex = String(C.sub_bg_color).substring(0,7);
      extraCss += 'video#html5Player::cue{background:'+hex+alpha+' !important;} ';
    }
    
    if (extraCss) { var style = document.createElement('style'); style.textContent = extraCss; document.head.appendChild(style); }
  } catch(e){}

  // ── تطبيق إعدادات مشغّل الفيديو فعلياً على أي <video> في الصفحة ──
  function applyPlayer(v){
    try{
      if (C.pl_autoplay) { v.autoplay = true; }
      if (C.pl_mute_on_start) { v.muted = true; }
      if (C.pl_pip === false) { v.setAttribute('disablePictureInPicture',''); }
      if (C.pl_playback_speed) { var s=parseFloat(C.pl_playback_speed); if(!isNaN(s)) v.playbackRate = s; }
      // منع التحميل حسب إعداد الأداء/الأفلام
      var cl = 'nodownload';
      if (C.st_default_quality) v.setAttribute('data-default-quality', C.st_default_quality);
      v.setAttribute('controlsList', cl);
      if (C.pl_auto_fullscreen) {
        v.addEventListener('play', function once(){ if(v.requestFullscreen) v.requestFullscreen().catch(function(){}); v.removeEventListener('play', once); });
      }
    }catch(e){}
  }
  function scanVideos(){ document.querySelectorAll('video').forEach(applyPlayer); }
  document.addEventListener('DOMContentLoaded', scanVideos);
  // مراقبة الفيديوهات المُضافة ديناميكياً
  try{
    var mo = new MutationObserver(function(muts){ muts.forEach(function(m){ m.addedNodes && m.addedNodes.forEach(function(n){ if(n.tagName==='VIDEO') applyPlayer(n); else if(n.querySelectorAll) n.querySelectorAll('video').forEach(applyPlayer); }); }); });
    mo.observe(document.documentElement,{childList:true,subtree:true});
  }catch(e){}

  // ── تطبيق إعدادات HLS.js إن كان مستخدماً (Buffer / ABR / Low Latency / إعادة الاتصال) ──
  // نضع القيم في window.SITE_HLS_CONFIG ليقرأها كود التشغيل عند إنشاء new Hls()
  window.SITE_HLS_CONFIG = {
    lowLatencyMode: !!C.st_low_latency,                                  // Low Latency Mode
    maxBufferLength: parseInt(C.st_max_buffer) || 60,                    // Max Buffer Length
    backBufferLength: parseInt(C.st_back_buffer) || 90,                  // Back Buffer Length
    maxMaxBufferLength: parseInt(C.st_buffer_size) || 30,                // Buffer Size
    liveSyncDuration: parseInt(C.st_live_sync) || 3,                     // Live Sync Duration
    startLevel: (C.st_auto_quality === false ? 0 : -1),                 // Auto Quality (ABR): -1 = تلقائي
    manifestLoadingMaxRetry: parseInt(C.st_reconnect_attempts) || 5,     // عدد محاولات إعادة الاتصال
    levelLoadingMaxRetry: parseInt(C.st_reconnect_attempts) || 5,
    fragLoadingMaxRetry: parseInt(C.st_reconnect_attempts) || 5,
    manifestLoadingRetryDelay: (parseInt(C.st_reconnect_timeout) || 3) * 1000, // المهلة قبل إعادة الاتصال
  };
})();
</script>

<?php /* ═══ إعدادات الأمان الحساسة من الإعدادات العامة ═══ */ ?>
<?php /* الكود المكرر لحماية devtools تم نقله بالكامل للأعلى */ ?>

