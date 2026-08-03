<style id="shashety-saver-toggle-css">
  .saver-toggle-btn{transition:var(--transition);position:relative;}
  .saver-toggle-btn:hover{background:rgba(229,9,20,.18);border-color:rgba(229,9,20,.5);color:#ff6b6b;transform:scale(1.08);}
  /* الحالة المُطفأة: لون باهت + شرطة */
  .saver-toggle-btn.saver-off{opacity:.65;color:#888;}
  .saver-toggle-btn.saver-off:hover{opacity:1;color:#46d369;border-color:rgba(70,211,105,.5);background:rgba(70,211,105,.12);}
  /* تلميح حالة صغير يظهر عند الضغط */
  .saver-toast{position:fixed;bottom:28px;left:50%;transform:translateX(-50%) translateY(20px);background:rgba(20,20,20,.96);color:#fff;border:1px solid rgba(255,255,255,.12);padding:11px 22px;border-radius:99px;font-family:'Cairo',sans-serif;font-weight:700;font-size:.9rem;z-index:100000;opacity:0;pointer-events:none;transition:opacity .3s ease,transform .3s ease;display:flex;align-items:center;gap:8px;direction:rtl;box-shadow:0 10px 40px rgba(0,0,0,.5);}
  .saver-toast.show{opacity:1;transform:translateX(-50%) translateY(0);}
  .saver-toast .st-dot{width:9px;height:9px;border-radius:50%;flex-shrink:0;}
  .saver-toast.on  .st-dot{background:#46d369;box-shadow:0 0 10px rgba(70,211,105,.8);}
  .saver-toast.off .st-dot{background:#888;}
</style>

<div class="saver-toast" id="saverToast"><span class="st-dot"></span><span id="saverToastTxt"></span></div>

<script id="shashety-saver-toggle-js">
(function(){
  'use strict';
  var KEY = 'shashety_saver_enabled';
  var scr = document.getElementById('nxScreensaver');

  // قرار الإدارة من لوحة التحكم (إن أطفأها المدير = إطفاء إجباري لكل الزوار)
  var ADMIN_DISABLED = <?php echo $hide_screensaver ? 'true' : 'false'; ?>;

  // الحالة المحفوظة (افتراضياً: مُفعّلة). قرار الإدارة يتجاوز تفضيل الزائر.
  function isEnabled(){
    if(ADMIN_DISABLED) return false;
    try{ return localStorage.getItem(KEY) !== '0'; }catch(e){ return true; }
  }
  function setEnabled(v){
    try{ localStorage.setItem(KEY, v ? '1' : '0'); }catch(e){}
  }

  // عند الإطفاء: نراقب الشاشة ونمنعها فوراً من الظهور
  var guard = null;
  function startGuard(){
    if(guard || !scr) return;
    // إخفاء فوري إن كانت ظاهرة
    if(scr.classList.contains('nx-active')) scr.classList.remove('nx-active');
    try{
      guard = new MutationObserver(function(){
        if(!isEnabled() && scr.classList.contains('nx-active')){
          scr.classList.remove('nx-active');
        }
      });
      guard.observe(scr, {attributes:true, attributeFilter:['class']});
    }catch(e){
      // متصفحات قديمة: فحص دوري بديل
      guard = setInterval(function(){
        if(!isEnabled() && scr && scr.classList.contains('nx-active')) scr.classList.remove('nx-active');
      }, 500);
    }
  }
  function stopGuard(){
    if(!guard) return;
    if(typeof guard.disconnect === 'function') guard.disconnect();
    else clearInterval(guard);
    guard = null;
  }

  // تحديث مظهر الزر
  function syncButton(){
    var btn = document.getElementById('saverToggleBtn');
    var on  = document.getElementById('saverIconOn');
    var off = document.getElementById('saverIconOff');
    if(!btn) return;
    var en = isEnabled();
    btn.classList.toggle('saver-off', !en);
    btn.title = en ? 'شاشة التوقف: مُفعّلة' : 'شاشة التوقف: مُطفأة';
    if(on)  on.style.display  = en ? '' : 'none';
    if(off) off.style.display = en ? 'none' : '';
  }

  // تلميح الحالة
  function toast(en){
    var t = document.getElementById('saverToast');
    var x = document.getElementById('saverToastTxt');
    if(!t || !x) return;
    t.classList.toggle('on', en);
    t.classList.toggle('off', !en);
    x.textContent = en ? 'شاشة التوقف مُفعّلة' : 'شاشة التوقف مُطفأة';
    t.classList.add('show');
    clearTimeout(toast._t);
    toast._t = setTimeout(function(){ t.classList.remove('show'); }, 1800);
  }

  // الدالة العامة التي يستدعيها الزر
  window.toggleScreensaverPref = function(){
    var en = !isEnabled();
    setEnabled(en);
    if(en){ stopGuard(); }
    else  { startGuard(); }
    syncButton();
    toast(en);
  };

  // تطبيق الحالة عند تحميل الصفحة
  function init(){
    // إذا أطفأ المدير الشاشة من اللوحة، نخفي الزر الفردي (القرار للإدارة)
    if(ADMIN_DISABLED){
      var b = document.getElementById('saverToggleBtn');
      if(b) b.style.display = 'none';
      startGuard();
      return;
    }
    syncButton();
    if(!isEnabled()) startGuard();
  }
  if(document.readyState === 'loading'){
    document.addEventListener('DOMContentLoaded', init);
  } else { init(); }
})();
</script>
<!-- ════════════════════ نهاية تحكم شاشة التوقف ════════════════════ -->
<!-- ════════════ إصلاحات المشغّل (دون تغيير المشغّل) — إضافة آمنة ════════════ -->
