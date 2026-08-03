<?php
/**
 * شارة المشترك — تظهر أسفل الشاشة عند تفعيل حماية الصفحة الرئيسية فقط.
 * تعرض اسم المستخدم والأيام المتبقية وزر خروج.
 *
 * تُدرَج في نهاية index.php (قبل الفوتر) وهي إضافية بالكامل: احذف سطر
 * require_once وسيعود الموقع إلى سلوكه السابق تماماً.
 */

// ── حالة المدير المتجاوِز ──
// يرى المحتوى كاملاً رغم تفعيل الحماية، فيظنّ أنها لا تعمل. نخبره صراحةً.
if (!empty($GLOBALS['__sg_admin_bypass'])) : ?>
<style>
#shsAdminBypass{
  position:fixed;inset-inline-start:14px;bottom:14px;z-index:9998;
  display:flex;align-items:center;gap:9px;max-width:min(92vw,420px);
  background:rgba(245,166,35,.14);border:1px solid rgba(245,166,35,.45);
  border-radius:14px;padding:10px 15px;backdrop-filter:blur(10px);
  font-family:'Tajawal','Segoe UI',system-ui,Tahoma,sans-serif;
  font-size:.76rem;color:#F5A623;line-height:1.6;box-shadow:0 6px 24px rgba(0,0,0,.5)
}
#shsAdminBypass b{display:block;font-size:.8rem;margin-bottom:1px}
#shsAdminBypass span.x{cursor:pointer;opacity:.6;font-size:1rem;margin-inline-start:auto;flex-shrink:0}
#shsAdminBypass span.x:hover{opacity:1}
@media(max-width:600px){#shsAdminBypass{font-size:.7rem;inset-inline:10px;max-width:none}}
/* يشغل نفس ركن أزرار المشغّل، فيختفي معها */
#shsAdminBypass.shs-hide{opacity:0 !important;pointer-events:none;transition:opacity .2s}
</style>
<div id="shsAdminBypass">
  <span style="font-size:1.15rem;flex-shrink:0">🛡️</span>
  <div>
    <b><?= htmlspecialchars($t['sg_admin_bypass'] ?? 'الحماية مفعّلة — وأنت تتجاوزها كمدير', ENT_QUOTES, 'UTF-8') ?></b>
    <?= htmlspecialchars($t['sg_admin_bypass_hint'] ?? 'الزائر العادي يرى صفحة تسجيل الدخول. اختبرها في نافذة تصفّح خفي.', ENT_QUOTES, 'UTF-8') ?>
  </div>
  <span class="x" onclick="this.parentNode.remove()">✕</span>
</div>
<script>
/* الشريط يشغل ركن أزرار المشغّل نفسه — نخفيه أثناء المشاهدة. */
(function () {
  var b = document.getElementById('shsAdminBypass');
  if (!b) return;
  function sync() {
    var ov = document.getElementById('playerOverlay');
    var open = !!(ov && ov.classList.contains('active'))
            || !!(document.fullscreenElement || document.webkitFullscreenElement);
    b.classList.toggle('shs-hide', open);
  }
  var ov = document.getElementById('playerOverlay');
  if (ov && window.MutationObserver) {
    new MutationObserver(sync).observe(ov, { attributes: true, attributeFilter: ['class'] });
  }
  document.addEventListener('fullscreenchange', sync);
  document.addEventListener('webkitfullscreenchange', sync);
  sync();

  /* يختفي وحده بعد ثوانٍ.
     غرضه إبلاغ المدير مرة واحدة أن الحماية تعمل وأنه مستثنى منها —
     لا أن يبقى لافتة دائمة على صفحته الرئيسية. ويُعرض مرة كل جلسة
     لا في كل تحديث للصفحة. */
  try {
    if (sessionStorage.getItem('shs_bypass_seen')) { b.remove(); return; }
    sessionStorage.setItem('shs_bypass_seen', '1');
  } catch (e) {}
  setTimeout(function () {
    b.style.transition = 'opacity .5s, transform .5s';
    b.style.opacity = '0';
    b.style.transform = 'translateY(12px)';
    setTimeout(function () { b.remove(); }, 520);
  }, 9000);
})();
</script>
<?php return; endif;

$__u = $GLOBALS['__site_user']   ?? null;
$__s = $GLOBALS['__site_status'] ?? null;
if (!$__u || !$__s) return;      // الحماية موقوفة

$__dl   = (int)($__s['days_left'] ?? 0);
$__unl  = $__dl < 0;             // اشتراك مفتوح
$__warn = (!$__unl && $__dl <= 7);
$__col  = $__unl ? '#4CC9F0' : ($__dl <= 3 ? '#e50914' : ($__warn ? '#F5A623' : '#00D084'));
?>
<style>
#shsSubBadge{
  position:fixed;inset-inline-start:14px;bottom:14px;z-index:9998;
  display:flex;align-items:center;gap:10px;
  background:rgba(18,18,21,.93);border:1px solid #2a2a30;border-radius:30px;
  padding:7px 8px 7px 14px;backdrop-filter:blur(10px);
  box-shadow:0 6px 24px rgba(0,0,0,.5);
  font-family:'Tajawal','Segoe UI',system-ui,Tahoma,sans-serif;
  font-size:.76rem;color:#c9c9d1;transition:opacity .25s;opacity:.55
}
#shsSubBadge:hover{opacity:1}
#shsSubBadge .sbd{width:8px;height:8px;border-radius:50%;background:<?= $__col ?>;
  box-shadow:0 0 8px <?= $__col ?>;flex-shrink:0}
#shsSubBadge b{color:#fff;font-weight:700}
#shsSubBadge .sbdays{color:<?= $__col ?>;font-weight:800;white-space:nowrap}
#shsSubBadge a{
  display:flex;align-items:center;justify-content:center;
  width:26px;height:26px;border-radius:50%;text-decoration:none;
  background:rgba(229,9,20,.13);color:#ff6b73;font-size:.8rem;transition:.18s
}
#shsSubBadge a:hover{background:#e50914;color:#fff}
@media(max-width:600px){#shsSubBadge{font-size:.7rem;padding:6px 7px 6px 11px;opacity:.85}}

/* تختفي أثناء المشاهدة: البطاقة ثابتة أسفل يمين الشاشة وهو موضع أزرار
   المشغّل نفسه، فتغطّي زرّ ملء الشاشة على الجوال. وزرّ الخروج بجانب
   أزرار التشغيل دعوة للضغط الخاطئ. */
#shsSubBadge.shs-hide{
  opacity:0 !important; pointer-events:none; transform:translateY(10px);
  transition:opacity .2s, transform .2s;
}
</style>

<div id="shsSubBadge" title="<?= htmlspecialchars($t['sg_badge_tip'] ?? 'حالة اشتراكك', ENT_QUOTES, 'UTF-8') ?>">
  <span class="sbd"></span>
  <span><b><?= htmlspecialchars((string)$__u['username'], ENT_QUOTES, 'UTF-8') ?></b></span>
  <span class="sbdays"><?php
    if ($__unl) {
        echo htmlspecialchars($t['sg_unlimited'] ?? 'بلا نهاية', ENT_QUOTES, 'UTF-8');
    } else {
        echo $__dl . ' ' . htmlspecialchars($t['sg_days'] ?? 'يوم', ENT_QUOTES, 'UTF-8');
    }
  ?></span>
  <a href="?__su_logout=1" title="<?= htmlspecialchars($t['sg_logout'] ?? 'خروج', ENT_QUOTES, 'UTF-8') ?>">⏻</a>
</div>

<script>
/* إخفاء البطاقة أثناء تشغيل أي فيديو.
   نراقب صنف .active على طبقة المشغّل بدل ربط أحداث فتح/إغلاق: المشغّل
   يُفتح من مواضع كثيرة (قناة، حلقة، استعادة جلسة، رابط #hash)، وربط كل
   مسار على حدة يعني أن أحدها سيُنسى. مراقبة الحالة النهائية تغطّيها جميعاً. */
(function () {
  var badge = document.getElementById('shsSubBadge');
  if (!badge) return;

  function sync() {
    var ov = document.getElementById('playerOverlay');
    var open = !!(ov && ov.classList.contains('active'));
    // ملء الشاشة يخفيها أيضاً — عنصر ثابت فوق فيديو ملء الشاشة يبقى ظاهراً
    if (!open && (document.fullscreenElement || document.webkitFullscreenElement)) open = true;
    badge.classList.toggle('shs-hide', open);
  }

  var ov = document.getElementById('playerOverlay');
  if (ov && window.MutationObserver) {
    new MutationObserver(sync).observe(ov, { attributes: true, attributeFilter: ['class'] });
  }
  document.addEventListener('fullscreenchange', sync);
  document.addEventListener('webkitfullscreenchange', sync);

  /* المشغّل قد يُبنى بعد هذا السكربت (استعادة جلسة سابقة مثلاً)،
     فنراقب إضافته إلى الصفحة أيضاً ثم نتوقف عن المراقبة. */
  if (!ov && window.MutationObserver) {
    var bodyObs = new MutationObserver(function () {
      var el = document.getElementById('playerOverlay');
      if (!el) return;
      new MutationObserver(sync).observe(el, { attributes: true, attributeFilter: ['class'] });
      bodyObs.disconnect();
      sync();
    });
    bodyObs.observe(document.body, { childList: true, subtree: true });
  }

  sync();
})();
</script>
