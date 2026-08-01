<script>
(function(){
  'use strict';

  /* 1) Lazy-loading تلقائي لكل الصور + ظهور تدريجي ناعم
        يطبّق loading=lazy و decoding=async على أي صورة تفوتها، ويضيف
        تأثير ظهور لطيف. يعمل على الصور الحالية والمضافة لاحقاً (MutationObserver). */
  function enhanceImg(img){
    if(img.dataset.perfDone) return;
    img.dataset.perfDone = '1';
    if(!img.hasAttribute('loading')) img.loading = 'lazy';
    if(!img.hasAttribute('decoding')) img.decoding = 'async';
    // ظهور ناعم (نتجنّب الشعار في الناف لئلا يومض)
    if(!img.classList.contains('nav-logo-img')){
      img.classList.add('perf-img');
      if(img.complete && img.naturalWidth>0){ img.classList.add('perf-loaded'); }
      else{
        img.addEventListener('load', ()=>img.classList.add('perf-loaded'), {once:true});
        img.addEventListener('error',()=>img.classList.add('perf-loaded'), {once:true});
      }
    }
  }
  function scanImgs(root){ (root||document).querySelectorAll('img').forEach(enhanceImg); }
  scanImgs(document);
  // راقب الصور المُضافة ديناميكياً (شبكة نتفليكس، الحلقات، نتائج البحث…)
  try{
    new MutationObserver(muts=>{
      for(const m of muts){
        m.addedNodes && m.addedNodes.forEach(n=>{
          if(n.nodeType!==1) return;
          if(n.tagName==='IMG') enhanceImg(n);
          else if(n.querySelectorAll) n.querySelectorAll('img').forEach(enhanceImg);
        });
      }
    }).observe(document.body, {childList:true, subtree:true});
  }catch(e){}

  /* 2) Prefetch ذكي عند المرور/اللمس على بطاقة مسلسل
        يجلب حلقات المسلسل مسبقاً (في الكاش الموجود) فيصبح الفتح فورياً.
        يعتمد على كاش api.php المضاف سابقاً، فلا طلب مكرر فعلي عند النقر. */
  const prefetched = new Set();
  const connection = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
  const prefetchAllowed = !(connection && (connection.saveData || /^(slow-2g|2g|3g)$/.test(connection.effectiveType || '')));
  function prefetchSeries(id){
    if(!prefetchAllowed || id==null || prefetched.has(id)) return;
    prefetched.add(id);
    // طلب صامت — النتيجة تُخزَّن في الكاش الذكي
    try{ fetch('api.php?action=episodes&series_id='+encodeURIComponent(id)).catch(()=>{}); }catch(e){}
  }
  // نلتقط أي عنصر يفتح مسلسلاً عبر onclick يحوي openSeriesEpisodes(ID
  function seriesIdFromEl(el){
    if(!el || !el.closest) return null;
    // 1) عبر وسم البيانات على البطاقة (الطريقة المعتمدة للشبكة الرئيسية)
    const card = el.closest('[data-prefetch-series]');
    if(card){ const v=card.getAttribute('data-prefetch-series'); if(v) return v; }
    // 2) عبر onclick نصي يحوي openSeriesEpisodes(ID
    const host = el.closest('[onclick]');
    if(host){
      const oc = host.getAttribute('onclick')||'';
      const mm = oc.match(/openSeriesEpisodes\(\s*['"]?(\d+)/);
      if(mm) return mm[1];
    }
    return null;
  }
  let hoverTimer=null;
  document.addEventListener('mouseover', e=>{
    const t = e.target;
    if(!(t instanceof Element)) return;
    const id = seriesIdFromEl(t);
    // Avoid spending bandwidth for accidental pointer passes over a card.
    if(id){ clearTimeout(hoverTimer); hoverTimer=setTimeout(()=>prefetchSeries(id), 350); }
  }, {passive:true});
  // على الجوال: عند بدء اللمس
  document.addEventListener('touchstart', e=>{
    const t = e.target;
    if(t instanceof Element){ const id=seriesIdFromEl(t); if(id) prefetchSeries(id); }
  }, {passive:true});

  /* 3) ربط الـ prefetch ببطاقات تُنشأ عبر addEventListener (لا onclick نصي)
        نلتقط أقرب عنصر يحمل بيانات مسلسل عبر التفويض — احتياطي إضافي. */
  // (مغطّى أعلاه عبر seriesIdFromEl للعناصر ذات onclick)

  /* 4) تأجيل المهام غير الحرجة حتى يهدأ المتصفح */
  const idle = window.requestIdleCallback || function(fn){ return setTimeout(fn, 200); };
  idle(function(){
    // تلميح للمتصفح بأن api.php على نفس الأصل (يسرّع أول طلب)
    try{
      const l=document.createElement('link');
      l.rel='preconnect'; l.href=location.origin;
      document.head.appendChild(l);
    }catch(e){}
  });

  /* 5) تأجيل المهام غير الحرجة حتى يهدأ المتصفح (إضافي) */
  // (مغطّى في النقطة 4)

})();
</script>
<!-- ════════════ تحسينات واجهة شاشتي المدمجة — إضافة آمنة ════════════ -->
