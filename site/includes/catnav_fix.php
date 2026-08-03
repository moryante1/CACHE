<style id="shashety-catnav-fix-css">
  /* تسريع مذهل لصفحة الواجهة (تأجيل رسم الأقسام غير المرئية) */
  .netflix-slider-row {
    content-visibility: auto;
    contain-intrinsic-size: auto 300px;
  }
  
  /* أزرار تمرير لصفوف القنوات والأفلام (Netflix style) */
  .slider-scroll-mask { position: relative; }
  .shs-row-arrow {
    position: absolute; top: 0; bottom: 0; width: 45px; z-index: 10;
    background: rgba(0,0,0,0.6); color: white; border: none; font-size: 24px;
    cursor: pointer; opacity: 0; transition: opacity 0.2s, background 0.2s;
    display: flex; align-items: center; justify-content: center;
  }
  .shs-row-arrow:hover { background: rgba(0,0,0,0.85); color: #fff; }
  .slider-scroll-mask:hover .shs-row-arrow.shs-show { opacity: 1; }
  .shs-row-arrow.shs-show.shs-left { left: 0; background: linear-gradient(90deg, rgba(0,0,0,.8) 0%, rgba(0,0,0,0) 100%); }
  .shs-row-arrow.shs-show.shs-right { right: 0; background: linear-gradient(270deg, rgba(0,0,0,.8) 0%, rgba(0,0,0,0) 100%); }
  @media (hover: none), (max-width: 768px) { .shs-row-arrow { display: none !important; } }
  
  /* أزرار تمرير عائمة (لا تلمس بنية الشريط) */
  .shs-catnav-arrow{
    position:fixed;z-index:885;width:44px;
    display:flex;align-items:center;justify-content:center;
    border:none;cursor:pointer;color:#fff;
    opacity:0;pointer-events:none;transition:opacity .2s ease;
  }
  .shs-catnav-arrow.shs-show{opacity:1;pointer-events:auto;}
  .shs-catnav-arrow.shs-left{
    background:linear-gradient(90deg, rgba(10,10,10,.96) 35%, rgba(10,10,10,0));
    justify-content:flex-start;padding-left:8px;
  }
  .shs-catnav-arrow.shs-right{
    background:linear-gradient(270deg, rgba(10,10,10,.96) 35%, rgba(10,10,10,0));
    justify-content:flex-end;padding-right:8px;
  }
  .shs-catnav-arrow .shs-arrow-circle{
    width:32px;height:32px;border-radius:50%;
    background:rgba(229,9,20,.92);display:flex;align-items:center;justify-content:center;
    box-shadow:0 3px 12px rgba(0,0,0,.45);transition:transform .15s ease, background .2s ease;
  }
  .shs-catnav-arrow:hover .shs-arrow-circle{transform:scale(1.12);background:#ff2b35;}
</style>

<script id="shashety-catnav-fix-js">
(function(){
  'use strict';

  // ── وظائف تمرير صفوف القنوات/الأفلام ──
  window.shsScrollRow = function(btn, dir) {
    var lane = btn.parentElement.querySelector('.slider-cards-wrapper');
    if(!lane) return;
    var amount = Math.max(lane.clientWidth * 0.7, 200);
    lane.scrollBy({left: dir * amount, behavior: 'smooth'});
  };

  window.shsUpdateRowArrows = function(lane) {
    var mask = lane.parentElement;
    if(!mask) return;
    var leftBtn = mask.querySelector('.shs-row-arrow.shs-left');
    var rightBtn = mask.querySelector('.shs-row-arrow.shs-right');
    if(!leftBtn || !rightBtn) return;
    
    var overflow = lane.scrollWidth - lane.clientWidth;
    if(overflow <= 4) {
        leftBtn.classList.remove('shs-show');
        rightBtn.classList.remove('shs-show');
        return;
    }
    
    var sl = lane.scrollLeft;
    var dir = window.getComputedStyle(lane).direction;
    var atStart, atEnd;
    
    if(dir === 'rtl') {
        if(sl <= 0) {
            atStart = Math.abs(sl) <= 4;
            atEnd = Math.abs(sl) >= overflow - 4;
        } else {
            atStart = sl >= overflow - 4;
            atEnd = sl <= 4;
        }
    } else {
        atStart = sl <= 4;
        atEnd = sl >= overflow - 4;
    }
    
    if (dir === 'rtl') {
        rightBtn.classList.toggle('shs-show', !atStart);
        leftBtn.classList.toggle('shs-show', !atEnd);
    } else {
        leftBtn.classList.toggle('shs-show', !atStart);
        rightBtn.classList.toggle('shs-show', !atEnd);
    }
  };

  function setup(){
    var bar = document.getElementById('catNavbar');
    if(!bar) return false;
    if(bar.__shsCatnavFixed) return true;

    // ── أزرار عائمة مستقلة (لا نلمس الشريط ولا ننقله) ──
    var leftBtn = document.createElement('button');
    leftBtn.type='button';
    leftBtn.className='shs-catnav-arrow shs-left';
    leftBtn.setAttribute('aria-label','تمرير');
    leftBtn.innerHTML='<span class="shs-arrow-circle"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg></span>';

    var rightBtn = document.createElement('button');
    rightBtn.type='button';
    rightBtn.className='shs-catnav-arrow shs-right';
    rightBtn.setAttribute('aria-label','تمرير');
    rightBtn.innerHTML='<span class="shs-arrow-circle"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg></span>';

    document.body.appendChild(leftBtn);
    document.body.appendChild(rightBtn);

    // مزامنة موضع الأزرار مع موضع الشريط (أعلى/ارتفاع)
    function positionArrows(){
      var hidden = (bar.style.display==='none' || bar.offsetParent===null);
      if(hidden){
        // نخفي عبر class فقط (لا نلمس style.display حتى لا نتجاوز قاعدة @media)
        leftBtn.classList.remove('shs-show');
        rightBtn.classList.remove('shs-show');
        return;
      }
      var r = bar.getBoundingClientRect();
      [leftBtn,rightBtn].forEach(function(b){
        b.style.top = r.top+'px';
        b.style.height = r.height+'px';
      });
      leftBtn.style.left = '0px';
      rightBtn.style.right = '0px';
    }

    function scrollByAmount(dir){
      var amount = Math.max(220, bar.clientWidth*0.7);
      bar.scrollBy({ left: dir*amount, behavior:'smooth' });
    }
    rightBtn.addEventListener('click', function(){ scrollByAmount(1); });
    leftBtn.addEventListener('click',  function(){ scrollByAmount(-1); });

    // إظهار/إخفاء حسب وجود محتوى مخفي وموضع التمرير (يدعم جميع المتصفحات LTR و RTL)
    function updateArrows(){
      positionArrows();
      var hidden = (bar.style.display==='none' || bar.offsetParent===null);
      if(hidden) return;
      var overflow = bar.scrollWidth - bar.clientWidth;
      if(overflow <= 4){
        // لا يوجد محتوى زائد → لا حاجة للأزرار
        leftBtn.classList.remove('shs-show');
        rightBtn.classList.remove('shs-show');
        return;
      }
      
      var sl = bar.scrollLeft;
      var dir = window.getComputedStyle(bar).direction;
      var atStart, atEnd;
      
      if(dir === 'rtl') {
          if (sl <= 0) {
              // Chrome/Firefox: 0 إلى -overflow
              atStart = Math.abs(sl) <= 4;
              atEnd = Math.abs(sl) >= overflow - 4;
          } else {
              // Safari: overflow إلى 0
              atStart = sl >= overflow - 4;
              atEnd = sl <= 4;
          }
      } else {
          // LTR: 0 إلى overflow
          atStart = sl <= 4;
          atEnd = sl >= overflow - 4;
      }

      // في RTL البداية على اليمين والنهاية على اليسار
      if (dir === 'rtl') {
          rightBtn.classList.toggle('shs-show', !atStart);
          leftBtn.classList.toggle('shs-show', !atEnd);
      } else {
          leftBtn.classList.toggle('shs-show', !atStart);
          rightBtn.classList.toggle('shs-show', !atEnd);
      }
    }

    // عجلة الماوس العمودية → تمرير أفقي
    bar.addEventListener('wheel', function(e){
      if(bar.scrollWidth <= bar.clientWidth) return;
      if(Math.abs(e.deltaY) > Math.abs(e.deltaX)){
        e.preventDefault();
        bar.scrollLeft += e.deltaY;
      }
    }, {passive:false});

    bar.addEventListener('scroll', updateArrows, {passive:true});
    window.addEventListener('resize', updateArrows, {passive:true});
    window.addEventListener('scroll', positionArrows, {passive:true});

    // مراقبة إعادة بناء الأقسام
    try{
      var mo = new MutationObserver(function(){ setTimeout(updateArrows,50); });
      mo.observe(bar, {childList:true, attributes:true, attributeFilter:['style']});
    }catch(e){}

    updateArrows();
    setTimeout(updateArrows,300);
    setTimeout(updateArrows,1000);

    // ضمان قوي: نغلّف renderCategoryNavBar لتحديث الأزرار بعد كل إعادة بناء للأقسام
    if(typeof window.renderCategoryNavBar === 'function' && !window.renderCategoryNavBar.__shsHooked){
      var _origRender = window.renderCategoryNavBar;
      window.renderCategoryNavBar = function(){
        var r = _origRender.apply(this, arguments);
        setTimeout(updateArrows, 60);
        setTimeout(updateArrows, 400);
        return r;
      };
      window.renderCategoryNavBar.__shsHooked = true;
    }

    bar.__shsCatnavFixed = true;
    return true;
  }

  if(!setup()){
    var tries=0;
    var iv=setInterval(function(){ tries++; if(setup()||tries>40) clearInterval(iv); },250);
  }
})();
</script>
<!-- ════════════════════ نهاية إصلاح شريط الأقسام ════════════════════ -->

