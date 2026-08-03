<?php
/**
 * مكتبات المشغّل — واجهة الموقع
 *
 * ══════════════════════════════════════════════════════════════
 *  لماذا ثُبِّتت الإصدارات؟
 * ══════════════════════════════════════════════════════════════
 *
 *  ① dash.js كان يُحمَّل من الرابط "latest":
 *         https://cdn.dashjs.org/latest/dash.all.min.js
 *     هذا الرابط كان يخدم الإصدار 4.x عند كتابة المشروع، وهو اليوم
 *     يخدم 5.2.0. لكن إعدادات DASH في site/includes/main_js.php
 *     مكتوبة بمخطّط الإصدار 4:
 *         streaming.abr.autoSwitchBitrate / maxBitrate / minBitrate
 *     وقد تغيّر هذا المخطّط في الإصدار 5، أي أن إعداداتك للجودة
 *     والمخزن صارت تُتجاهَل بصمت بعد أن قفز "latest" إلى v5 — دون أي
 *     تعديل منك ودون أي رسالة خطأ.
 *     التثبيت على 4.7.4 يعيد الإعدادات للعمل كما صُمِّمت.
 *
 *  ② flv.js كان على @latest أيضاً — أي تحديث كاسر من المطوّر
 *     يعطّل تشغيل FLV لدى كل مستخدميك فوراً وبلا إنذار.
 *
 *  القاعدة: لا تُترك مكتبة مشغّل على "latest" في نظام إنتاجي.
 *  التحديث يجب أن يكون قراراً منك بعد تجربة، لا مفاجأة من الخارج.
 *
 *  ── الإصدارات المتاحة وقت هذا التحديث (للرجوع عند الترقية) ──
 *     hls.js   : مثبَّت 1.5.17  | أحدث 1.6.16
 *     dash.js  : مثبَّت 4.7.4   | أحدث 5.2.0  (ترقية v5 تتطلب تعديل الإعدادات)
 *     flv.js   : مثبَّت 1.6.2   | أحدث 1.6.2
 *     mpegts.js: مثبَّت 1.7.3   | أحدث 1.8.0
 */
?>
<!-- مكتبات المشغّل: defer حتى لا تؤخّر أول رسم للصفحة -->
<script src="https://cdn.jsdelivr.net/npm/hls.js@1.5.17/dist/hls.min.js" defer></script>
<script src="https://cdn.jsdelivr.net/npm/dashjs@4.7.4/dist/dash.all.min.js" defer></script>
<script src="https://cdn.jsdelivr.net/npm/flv.js@1.6.2/dist/flv.min.js" defer></script>
<script src="https://cdn.jsdelivr.net/npm/mpegts.js@1.7.3/dist/mpegts.js" defer></script>

<script>
/* ── مصدر بديل عند حجب jsDelivr ──
   إن حُجب النطاق (وهو أمر شائع في عدة دول) كانت كل المكتبات تفشل
   بصمت ويتوقف المشغّل نهائياً. هنا نتحقق بعد اكتمال التحميل، وأي
   مكتبة لم تُعرَّف نعيد تحميلها من unpkg تلقائياً. */
(function () {
  'use strict';

  var FALLBACKS = [
    { name: 'Hls',    url: 'https://unpkg.com/hls.js@1.5.17/dist/hls.min.js' },
    { name: 'dashjs', url: 'https://unpkg.com/dashjs@4.7.4/dist/dash.all.min.js' },
    { name: 'flvjs',  url: 'https://unpkg.com/flv.js@1.6.2/dist/flv.min.js' },
    { name: 'mpegts', url: 'https://unpkg.com/mpegts.js@1.7.3/dist/mpegts.js' }
  ];

  function loadFallbacks() {
    FALLBACKS.forEach(function (lib) {
      if (typeof window[lib.name] !== 'undefined') return; // حُمِّلت بنجاح
      var s = document.createElement('script');
      s.src = lib.url;
      s.defer = true;
      s.onerror = function () {
        console.warn('[player] تعذّر تحميل ' + lib.name + ' من كل المصادر.');
      };
      document.head.appendChild(s);
      console.warn('[player] ' + lib.name + ' لم تُحمَّل من jsDelivr — جاري التحميل من unpkg.');
    });
  }

  if (document.readyState === 'complete') {
    loadFallbacks();
  } else {
    window.addEventListener('load', loadFallbacks);
  }
})();
</script>
