<style id="shashety-screensaver-pro-css">
  /* ── خلفية أعمق وتدرّج سينمائي أنعم (يتجاوز القديم بنفس المحدّدات) ── */
  #nxScreensaver{background:#050505 !important;}
  #nxScreensaver .nx-bg{
    inset:-12% !important;
    filter:blur(60px) saturate(1.6) brightness(.28) !important;
    animation:nxKenBurnsPro 28s infinite alternate ease-in-out !important;
    transition:background-image 1.2s ease !important;
  }
  @keyframes nxKenBurnsPro{
    0%{transform:scale(1.05) translate(0,0)}
    100%{transform:scale(1.18) translate(-2%,-2%)}
  }
  /* ── طبقة حُبيبات/توهّج خفيف فوق الخلفية (احترافية) ── */
  #nxScreensaver .nx-vignette{
    background:
      radial-gradient(ellipse at 30% 40%, rgba(229,9,20,.10) 0%, transparent 45%),
      radial-gradient(circle at center, transparent 25%, rgba(0,0,0,.88) 100%),
      linear-gradient(0deg,#000 0%, rgba(0,0,0,0) 42%) !important;
  }

  /* ── البوستر: ظل أعمق + إطار زجاجي + انعكاس ضوئي ── */
  #nxScreensaver .nx-poster{
    border-radius:16px !important;
    box-shadow:
      0 40px 100px rgba(0,0,0,.95),
      0 0 0 1px rgba(255,255,255,.10),
      0 0 60px rgba(229,9,20,.12) !important;
    animation:nxFloatPro 7s ease-in-out infinite alternate !important;
  }
  @keyframes nxFloatPro{
    0%{transform:perspective(1200px) rotateY(-6deg) translateY(0) scale(1)}
    100%{transform:perspective(1200px) rotateY(-3deg) translateY(-16px) scale(1.015)}
  }

  /* ── العنوان: ظهور تدريجي أنيق عند كل شريحة ── */
  #nxScreensaver .nx-title{
    text-shadow:0 6px 30px rgba(0,0,0,.9) !important;
    letter-spacing:-.5px;
  }
  #nxScreensaver:not(.nx-faded) .nx-info-box > *{animation:nxRise .9s cubic-bezier(.2,.7,.2,1) both;}
  #nxScreensaver .nx-info-box > *:nth-child(1){animation-delay:.05s}
  #nxScreensaver .nx-info-box > *:nth-child(2){animation-delay:.14s}
  #nxScreensaver .nx-info-box > *:nth-child(3){animation-delay:.22s}
  @keyframes nxRise{from{opacity:0;transform:translateY(18px)}to{opacity:1;transform:translateY(0)}}

  /* ── انتقال الحاوية أنعم (slide + fade بدل fade فقط) ── */
  #nxScreensaver .nx-container{transition:opacity .9s ease, transform .9s cubic-bezier(.2,.7,.2,1) !important;}
  #nxScreensaver .nx-container.nx-faded{opacity:0 !important; transform:translateX(40px) scale(.985);}

  /* ── شارة المطابقة: توهّج أخضر ناعم ── */
  #nxScreensaver .nx-match{text-shadow:0 0 16px rgba(70,211,105,.5);}

  /* ════ الساعة والتاريخ (جديد) ════ */
  .nx-clock{
    position:absolute; top:6vh; left:7vw; z-index:4; direction:ltr;
    text-align:left; color:#fff; pointer-events:none;
    opacity:0; transition:opacity 1.2s ease .4s;
    text-shadow:0 4px 24px rgba(0,0,0,.7);
  }
  #nxScreensaver.nx-active .nx-clock{opacity:1;}
  .nx-clock-time{
    font-size:clamp(3rem,7vw,6rem); font-weight:200; line-height:1;
    font-family:'Cairo','SF Pro Display',sans-serif; letter-spacing:2px;
    font-variant-numeric:tabular-nums;
  }
  .nx-clock-time .nx-ampm{font-size:.32em;font-weight:600;opacity:.7;margin-inline-start:.3em;}
  .nx-clock-date{
    font-size:clamp(.9rem,1.6vw,1.3rem); font-weight:600; color:rgba(255,255,255,.62);
    margin-top:10px; letter-spacing:.5px; font-family:'Cairo',sans-serif; direction:rtl; text-align:left;
  }
  .nx-brand{
    position:absolute; bottom:48px; right:7vw; z-index:4;
    display:flex; align-items:center; gap:10px; direction:rtl;
    opacity:0; transition:opacity 1.2s ease .6s;
    color:rgba(255,255,255,.5); font-weight:800; font-size:1rem; letter-spacing:.5px;
  }
  #nxScreensaver.nx-active .nx-brand{opacity:1;}
  .nx-brand-dot{width:8px;height:8px;border-radius:50%;background:#E50914;box-shadow:0 0 12px rgba(229,9,20,.8);animation:nxPulse 2s infinite ease-in-out;}
  @keyframes nxPulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.4;transform:scale(.8)}}

  @media (max-width:768px){
    .nx-clock{top:4vh;left:6vw;}
    .nx-clock-time{font-size:clamp(2.2rem,9vw,3.5rem);}
    .nx-brand{bottom:90px;}
  }
  @media (prefers-reduced-motion: reduce){
    #nxScreensaver .nx-bg,#nxScreensaver .nx-poster{animation:none !important;}
    #nxScreensaver .nx-container{transition:opacity .5s ease !important;}
  }
</style>

<script id="shashety-screensaver-pro-js">
(function(){
  'use strict';
  var scr = document.getElementById('nxScreensaver');
  if(!scr) return;

  /* ── حقن عناصر الساعة + العلامة (مرة واحدة) ── */
  function injectExtras(){
    if(!document.getElementById('nxClock')){
      var clock = document.createElement('div');
      clock.className = 'nx-clock';
      clock.id = 'nxClock';
      clock.innerHTML =
        '<div class="nx-clock-time" id="nxClockTime">--:--</div>' +
        '<div class="nx-clock-date" id="nxClockDate"></div>';
      scr.appendChild(clock);
    }
    if(!document.getElementById('nxBrand')){
      // اسم الموقع إن توفّر في الصفحة، وإلا فارغ بدون كسر
      var siteName = (document.querySelector('meta[property="og:title"]') || {}).content || document.title || '';
      siteName = String(siteName).split('—')[0].split('-')[0].trim();
      var brand = document.createElement('div');
      brand.className = 'nx-brand';
      brand.id = 'nxBrand';
      brand.innerHTML = '<span>' + (siteName || 'مباشر') + '</span><span class="nx-brand-dot"></span>';
      scr.appendChild(brand);
    }
  }
  injectExtras();

  /* ── تحديث الساعة (يعمل فقط عندما تكون الشاشة نشطة) ── */
  var AR_DAYS = ['الأحد','الإثنين','الثلاثاء','الأربعاء','الخميس','الجمعة','السبت'];
  var AR_MONTHS = ['يناير','فبراير','مارس','أبريل','مايو','يونيو','يوليو','أغسطس','سبتمبر','أكتوبر','نوفمبر','ديسمبر'];
  function tick(){
    if(!scr.classList.contains('nx-active')) return;
    var t = document.getElementById('nxClockTime');
    var d = document.getElementById('nxClockDate');
    if(!t || !d) return;
    var now = new Date();
    var h = now.getHours();
    var m = now.getMinutes();
    var ampm = h >= 12 ? 'PM' : 'AM';
    var h12 = h % 12; if(h12 === 0) h12 = 12;
    var mm = (m < 10 ? '0' : '') + m;
    t.innerHTML = h12 + ':' + mm + '<span class="nx-ampm">' + ampm + '</span>';
    d.textContent = AR_DAYS[now.getDay()] + '، ' + now.getDate() + ' ' + AR_MONTHS[now.getMonth()] + ' ' + now.getFullYear();
  }
  /* ⚠️ عيب أداء كان هنا: setInterval(tick,1000) دائم بلا توقف.
     الساعة كانت تُعيد كتابة innerHTML كل ثانية — 86,400 مرة يومياً —
     حتى وشاشة التوقف مخفية تماماً ولا يراها أحد. كل كتابة innerHTML
     تُجبر المتصفح على إعادة حساب التخطيط والرسم، فتظهر كتقطيع خفيف
     في التمرير واستنزاف بطارية على الجوال والتلفاز.

     الحل: المؤقّت يعمل فقط أثناء ظهور شاشة التوقف فعلاً. */
  var _clockTimer = null;
  function startClock(){
    if(_clockTimer) return;
    tick();
    _clockTimer = setInterval(tick, 1000);
  }
  function stopClock(){
    if(!_clockTimer) return;
    clearInterval(_clockTimer);
    _clockTimer = null;
  }

  /* ── تشغيل الساعة عند ظهور الشاشة وإيقافها عند اختفائها ── */
  try{
    var mo = new MutationObserver(function(){
      if(scr.classList.contains('nx-active')) startClock();
      else stopClock();
    });
    mo.observe(scr, {attributes:true, attributeFilter:['class']});
    if(scr.classList.contains('nx-active')) startClock();
    else tick(); // ضبط أولي للقيم حتى تكون صحيحة عند أول ظهور
  }catch(e){
    // متصفحات قديمة بلا MutationObserver — نعود للسلوك السابق
    startClock();
  }

  // إيقاف الساعة عند إخفاء التبويب
  document.addEventListener('visibilitychange', function(){
    if(document.visibilityState === 'hidden') stopClock();
    else if(scr.classList.contains('nx-active')) startClock();
  });
})();
</script>
<!-- ════════════════════ نهاية شاشة التوقف الاحترافية ════════════════════ -->
<!-- ════════════ تحكم تشغيل/إطفاء شاشة التوقف — إضافة آمنة ════════════ -->
