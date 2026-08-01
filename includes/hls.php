<?php
/**
 * تحميل مكتبة hls.js للوحة التحكم + سكربتات واجهة صغيرة.
 *
 * ══════════════════════════════════════════════════════════════
 *  ما كان معطلاً في النسخة السابقة
 * ══════════════════════════════════════════════════════════════
 *
 *  ① الكود الداخلي لم يكن يعمل إطلاقاً:
 *     كان الملف وسماً واحداً بهذا الشكل:
 *
 *         <script src="https://.../hls.js@latest">
 *             document.addEventListener('click', ... );
 *         </script>
 *
 *     وفق معيار HTML، أي وسم <script> يحمل الخاصية src فإن محتواه
 *     الداخلي **يُتجاهَل بالكامل**. أي أن كود إغلاق قائمة اللغات
 *     ومبدّل الحسابات عند النقر خارجهما كان كوداً ميتاً منذ البداية —
 *     القائمتان لا تُغلقان عند النقر بالخارج.
 *     الحل: فصل الكود في وسم <script> مستقل.
 *
 *  ② إصدار غير مثبَّت (@latest):
 *     لوحة التحكم كانت تسحب "أحدث إصدار" من المكتبة، بينما واجهة
 *     الموقع مثبَّتة على 1.5.17. النتيجة: نسختان مختلفتان من نفس
 *     المكتبة في نفس المشروع، وأي إصدار جديد فيه تغيير كاسر يعطّل
 *     معاينة القنوات في اللوحة فوراً ودون أي تعديل منك.
 *     الحل: تثبيت نفس إصدار الموقع (اتساق + استقرار).
 *
 *  ③ تحميل متزامن يحجب الصفحة:
 *     الوسم كان بلا defer، فإن كان الـ CDN بطيئاً أو محجوباً تتجمّد
 *     لوحة التحكم بالكامل حتى تنتهي المهلة.
 *     الحل: defer (المكتبة تُستخدم فقط عند ضغط المستخدم على معاينة).
 *
 *  ④ لا مصدر بديل:
 *     إن حُجب jsDelivr (وهو أمر شائع في عدة دول) تتوقف معاينة HLS
 *     نهائياً بلا أي تعافٍ. الملفت أن دالة _ensureMpegts في
 *     includes/main_js.php تطبّق سلسلة بدائل صحيحة أصلاً —
 *     المكتبة الأهم (hls.js) كانت وحدها بلا حماية.
 *     الحل: سلسلة بدائل (محلي ← jsDelivr ← unpkg ← cdnjs).
 *
 *  ملاحظة: نسخة محلية اختيارية. إن وضعت الملف في lib/hls.min.js
 *  فسيُحمَّل منه أولاً بلا أي اتصال خارجي.
 */

$__hlsVersion = '1.5.17'; // نفس إصدار واجهة الموقع (site/includes/cdn_scripts.php)
$__hlsLocal   = __DIR__ . '/../lib/hls.min.js';
$__hasLocal   = is_file($__hlsLocal);

// مسار الويب للنسخة المحلية (يعمل داخل مجلد فرعي أيضاً)
$__base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
?>
<script>
/* ── تحميل hls.js مع سلسلة مصادر بديلة ──
   يُجرَّب كل مصدر بالترتيب؛ عند فشل مصدر يُنتقل تلقائياً للتالي.
   يُصدَّر وعد window.__hlsReady ليستطيع المشغّل انتظار الجاهزية. */
(function () {
  'use strict';

  var SOURCES = [
<?php if ($__hasLocal): ?>
    <?= json_encode($__base . '/lib/hls.min.js') ?>,           /* نسخة محلية — بلا اتصال خارجي */
<?php endif; ?>
    'https://cdn.jsdelivr.net/npm/hls.js@<?= $__hlsVersion ?>/dist/hls.min.js',
    'https://unpkg.com/hls.js@<?= $__hlsVersion ?>/dist/hls.min.js',
    'https://cdnjs.cloudflare.com/ajax/libs/hls.js/<?= $__hlsVersion ?>/hls.min.js'
  ];

  if (typeof window.Hls !== 'undefined') {
    window.__hlsReady = Promise.resolve(true);
    return;
  }

  window.__hlsReady = new Promise(function (resolve) {
    var i = 0;

    function tryNext() {
      if (i >= SOURCES.length) {
        console.warn('[hls] تعذّر تحميل المكتبة من كل المصادر.');
        resolve(false);
        return;
      }

      var url = SOURCES[i++];
      var s   = document.createElement('script');
      s.src   = url;
      s.defer = true;

      s.onload = function () {
        if (typeof window.Hls !== 'undefined') {
          resolve(true);
        } else {
          tryNext(); // حُمّل الملف لكنه ليس المكتبة المتوقعة
        }
      };
      s.onerror = function () {
        console.warn('[hls] فشل المصدر: ' + url + ' — جاري تجربة التالي.');
        tryNext();
      };

      document.head.appendChild(s);
    }

    tryNext();
  });
})();
</script>

<script>
/* ── إغلاق قائمة اللغات ومبدّل الحسابات عند النقر بالخارج ──
   ⚠️ هذا الكود كان داخل وسم <script src="..."> فلم يكن يُنفَّذ إطلاقاً.
      نُقل إلى وسم مستقل ليعمل فعلاً.
   تحسين إضافي: كان الكود يُغلق القائمة عند أي نقرة — حتى النقرة على
   القائمة نفسها، ما يجعل اختيار عنصر منها صعباً. الآن يُغلق فقط عند
   النقر *خارجها* فعلاً، وأُضيف الإغلاق بمفتاح Escape. */
(function () {
  'use strict';

  function closeIfOutside(el, event) {
    if (!el || !el.classList.contains('op')) return;
    if (el.contains(event.target)) return;   // نقرة داخل القائمة → لا نغلق
    el.classList.remove('op');
  }

  document.addEventListener('click', function (e) {
    closeIfOutside(document.getElementById('langDrop'), e);
    closeIfOutside(document.getElementById('profSw'), e);
  });

  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    ['langDrop', 'profSw'].forEach(function (id) {
      var el = document.getElementById(id);
      if (el) el.classList.remove('op');
    });
  });
})();
</script>
