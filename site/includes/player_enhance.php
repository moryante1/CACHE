<style id="shashety-player-enhance-css">
  /* زر صورة-داخل-صورة (PiP) */
  .shs-pip-btn{display:inline-flex;align-items:center;justify-content:center;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.14);color:#fff;width:38px;height:38px;border-radius:10px;cursor:pointer;transition:.2s;font-size:17px;}
  .shs-pip-btn:hover{background:rgba(229,9,20,.85);border-color:rgba(229,9,20,.9);transform:translateY(-1px);}
  /* تلميح سرعة التشغيل */
  .shs-rate-tag{position:absolute;top:14px;left:50%;transform:translateX(-50%);background:rgba(0,0,0,.78);color:#fff;padding:6px 16px;border-radius:99px;font-weight:800;font-size:.9rem;z-index:60;opacity:0;pointer-events:none;transition:opacity .25s ease;font-family:inherit;}
  .shs-rate-tag.show{opacity:1;}
  /* تلميح اختصارات لوحة المفاتيح */
  .shs-keys-hint{position:fixed;inset:0;background:rgba(0,0,0,.82);z-index:200;display:none;align-items:center;justify-content:center;padding:20px;}
  .shs-keys-hint.open{display:flex;}
  .shs-keys-card{background:#1a1a1a;border:1px solid rgba(255,255,255,.12);border-radius:16px;max-width:440px;width:100%;padding:24px 26px;color:#eee;font-family:inherit;direction:rtl;}
  .shs-keys-card h3{margin:0 0 16px;color:#fff;font-size:1.15rem;display:flex;align-items:center;gap:8px;}
  .shs-keys-row{display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid rgba(255,255,255,.06);font-size:.92rem;}
  .shs-keys-row:last-child{border-bottom:none;}
  .shs-keys-row kbd{background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.18);border-radius:6px;padding:2px 9px;font-family:monospace;font-size:.85rem;color:#fff;min-width:22px;text-align:center;display:inline-block;}
  .shs-keys-close{margin-top:18px;width:100%;background:var(--red,#e50914);color:#fff;border:none;padding:11px;border-radius:10px;font-weight:800;cursor:pointer;font-family:inherit;font-size:.95rem;}
</style>

<div class="shs-rate-tag" id="shsRateTag"></div>
<div class="shs-keys-hint" id="shsKeysHint">
  <div class="shs-keys-card">
    <h3>⌨️ اختصارات المشغّل</h3>
    <div class="shs-keys-row"><span>تشغيل / إيقاف</span><kbd>مسافة</kbd></div>
    <div class="shs-keys-row"><span>تقديم ١٠ ثوانٍ</span><kbd>→</kbd></div>
    <div class="shs-keys-row"><span>تأخير ١٠ ثوانٍ</span><kbd>←</kbd></div>
    <div class="shs-keys-row"><span>رفع الصوت</span><kbd>↑</kbd></div>
    <div class="shs-keys-row"><span>خفض الصوت</span><kbd>↓</kbd></div>
    <div class="shs-keys-row"><span>كتم الصوت</span><kbd>M</kbd></div>
    <div class="shs-keys-row"><span>ملء الشاشة</span><kbd>F</kbd></div>
    <div class="shs-keys-row"><span>صورة داخل صورة</span><kbd>P</kbd></div>
    <div class="shs-keys-row"><span>تسريع التشغيل</span><kbd>&gt;</kbd></div>
    <div class="shs-keys-row"><span>إبطاء التشغيل</span><kbd>&lt;</kbd></div>
    <div class="shs-keys-row"><span>قفز لنسبة من الفيديو</span><kbd>0</kbd>…<kbd>9</kbd></div>
    <button class="shs-keys-close" onclick="document.getElementById('shsKeysHint').classList.remove('open')">إغلاق</button>
  </div>
</div>

<script id="shashety-player-enhance-js">
(function(){
  'use strict';
  function vid(){ return document.getElementById('html5Player'); }
  function playerOpen(){
    var ov = document.getElementById('playerOverlay');
    return ov && ov.classList.contains('active');
  }
  function typingInField(){
    var a = document.activeElement;
    return a && (a.tagName==='INPUT' || a.tagName==='TEXTAREA' || a.isContentEditable);
  }

  /* ── سرعة التشغيل ── */
  var RATES = [0.5, 0.75, 1, 1.25, 1.5, 2];
  function showRate(){
    var v = vid(); if(!v) return;
    var tag = document.getElementById('shsRateTag');
    if(!tag) return;
    tag.textContent = '⏩ السرعة: ' + v.playbackRate + '×';
    tag.classList.add('show');
    clearTimeout(showRate._t);
    showRate._t = setTimeout(function(){ tag.classList.remove('show'); }, 1200);
  }
  function bumpRate(dir){
    var v = vid(); if(!v) return;
    var i = RATES.indexOf(v.playbackRate);
    if(i === -1) i = 2; // 1x افتراضي
    i = Math.max(0, Math.min(RATES.length-1, i + dir));
    v.playbackRate = RATES[i];
    showRate();
  }

  /* ── صورة داخل صورة (PiP) ── */
  function togglePiP(){
    var v = vid(); if(!v) return;
    try{
      if(document.pictureInPictureElement){
        document.exitPictureInPicture();
      }else if(document.pictureInPictureEnabled && !v.disablePictureInPicture){
        v.requestPictureInPicture();
      }
    }catch(e){ /* بصمت */ }
  }

  /* ── حقن زر PiP بجوار زر ملء الشاشة (إن وُجد) ── */
  function injectPipButton(){
    if(document.getElementById('shsPipBtn')) return;
    var fsBtn = document.querySelector('.p-fs-btn');
    if(!fsBtn || !fsBtn.parentNode) return;
    if(!('pictureInPictureEnabled' in document) || !document.pictureInPictureEnabled) return;
    var b = document.createElement('button');
    b.type = 'button';
    b.id = 'shsPipBtn';
    b.className = 'shs-pip-btn';
    b.title = 'صورة داخل صورة (P)';
    b.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 10h6V4"/><path d="m2 4 6 6"/><path d="M21 10V7a2 2 0 0 0-2-2h-7"/><path d="M3 14v2a2 2 0 0 0 2 2h3"/><rect width="10" height="7" x="12" y="13.5" rx="2"/></svg>';
    b.addEventListener('click', togglePiP);
    fsBtn.parentNode.insertBefore(b, fsBtn);
  }

  /* ── قفز لنسبة (0-9) ── */
  function seekPercent(p){
    var v = vid(); if(!v || !v.duration || isNaN(v.duration)) return;
    v.currentTime = v.duration * (p/10);
    if(typeof showControls === 'function') showControls();
  }

  /* ── اختصارات لوحة المفاتيح (كمبيوتر فقط، لا تمسّ منطق الريموت) ── */
  document.addEventListener('keydown', function(e){
    if(!playerOpen() || typingInField()) return;
    if(e.ctrlKey || e.altKey || e.metaKey) return;
    var k = e.key;

    switch(k){
      case ' ': case 'k': case 'K':
        if(typeof togglePlay==='function'){ e.preventDefault(); togglePlay(); }
        return;
      case 'ArrowRight':
        if(typeof skip==='function'){ e.preventDefault(); skip(10); }
        return;
      case 'ArrowLeft':
        if(typeof skip==='function'){ e.preventDefault(); skip(-10); }
        return;
      case 'ArrowUp':
        if(typeof changeVol==='function'){ e.preventDefault(); changeVol(0.1); }
        return;
      case 'ArrowDown':
        if(typeof changeVol==='function'){ e.preventDefault(); changeVol(-0.1); }
        return;
      case 'm': case 'M':
        if(typeof toggleMute==='function'){ e.preventDefault(); toggleMute(); }
        return;
      case 'f': case 'F':
        if(typeof toggleFullscreen==='function'){ e.preventDefault(); toggleFullscreen(); }
        return;
      case 'p': case 'P':
        e.preventDefault(); togglePiP();
        return;
      case '>': case '.':
        e.preventDefault(); bumpRate(1);
        return;
      case '<': case ',':
        e.preventDefault(); bumpRate(-1);
        return;
      case '?':
        e.preventDefault();
        document.getElementById('shsKeysHint').classList.toggle('open');
        return;
    }
    // أرقام 0..9 للقفز لنسبة
    if(k >= '0' && k <= '9'){
      e.preventDefault();
      seekPercent(parseInt(k,10));
    }
  }, false);

  /* محاولة حقن زر PiP عند فتح المشغّل */
  var _origOpen = window.openPlayerChannel;
  if(typeof _origOpen === 'function'){
    window.openPlayerChannel = function(){
      var r = _origOpen.apply(this, arguments);
      setTimeout(injectPipButton, 300);
      return r;
    };
  }
  var _origOpenEp = window.openPlayerEpisode;
  if(typeof _origOpenEp === 'function'){
    window.openPlayerEpisode = function(){
      var r = _origOpenEp.apply(this, arguments);
      setTimeout(injectPipButton, 300);
      return r;
    };
  }
  // محاولة إضافية بعد التحميل (احتياط)
  document.addEventListener('DOMContentLoaded', function(){
    setTimeout(injectPipButton, 1000);
  });
})();
</script>
<!-- ════════════════════ نهاية تحسينات مشغّل شاشتي ════════════════════ -->
<!-- ════════════ شاشة توقف احترافية — تحسين بصري آمن (لا يحذف المنطق الأصلي) ════════════ -->
