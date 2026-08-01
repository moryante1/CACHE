<style id="shashety-improve-css">
  #shsToTop{position:fixed;left:20px;bottom:20px;z-index:9999;width:46px;height:46px;border:none;border-radius:50%;background:rgba(229,9,20,.92);color:#fff;cursor:pointer;font-size:20px;line-height:46px;text-align:center;box-shadow:0 6px 20px rgba(0,0,0,.35);opacity:0;transform:translateY(16px) scale(.9);transition:opacity .25s ease,transform .25s ease;pointer-events:none;}
  #shsToTop.show{opacity:1;transform:translateY(0) scale(1);pointer-events:auto;}
  #shsToTop:hover{background:#ff2b35;}
  #shsProgress{position:fixed;top:0;left:0;height:3px;width:0;background:linear-gradient(90deg,#e50914,#ff6b6b);z-index:10000;transition:width .3s ease,opacity .4s ease;box-shadow:0 0 10px rgba(229,9,20,.6);opacity:0;}
  img.shs-lazy{opacity:0;transition:opacity .4s ease;}
  img.shs-lazy.shs-loaded{opacity:1;}
  @media (prefers-reduced-motion: reduce){#shsToTop,#shsProgress,img.shs-lazy{transition:none !important;}}
  /* إخفاء زر العودة للأعلى وشريط التقدّم عند تشغيل المشغّل (منع التداخل) */
  .player-overlay.active ~ #shsToTop,
  body:has(.player-overlay.active) #shsToTop{opacity:0 !important;pointer-events:none !important;visibility:hidden !important;}
  body:has(.player-overlay.active) #shsProgress{display:none !important;}
</style>
<button id="shsToTop" aria-label="العودة للأعلى" title="العودة للأعلى">↑</button>
<div id="shsProgress"></div>
<script id="shashety-improve-js">
(function(){'use strict';
  function enableLazyImages(){try{document.querySelectorAll('img:not([loading])').forEach(function(img){if(img.src&&img.src.indexOf('data:')===0)return;img.setAttribute('loading','lazy');img.setAttribute('decoding','async');if(!img.complete){img.classList.add('shs-lazy');img.addEventListener('load',function(){img.classList.add('shs-loaded');},{once:true});img.addEventListener('error',function(){img.classList.add('shs-loaded');},{once:true});}});}catch(e){}}
  function initToTop(){var btn=document.getElementById('shsToTop');if(!btn)return;function playerOn(){var ov=document.getElementById('playerOverlay');return ov&&ov.classList.contains('active');}var ticking=false;function onScroll(){if(ticking)return;ticking=true;requestAnimationFrame(function(){if(window.scrollY>400&&!playerOn())btn.classList.add('show');else btn.classList.remove('show');ticking=false;});}window.addEventListener('scroll',onScroll,{passive:true});btn.addEventListener('click',function(){window.scrollTo({top:0,behavior:'smooth'});});
    // مراقبة المشغّل: إخفاء الزر فوراً عند الفتح (يعمل في كل المتصفحات حتى بلا :has)
    var ov=document.getElementById('playerOverlay');
    if(ov){try{var mo=new MutationObserver(function(){if(ov.classList.contains('active'))btn.classList.remove('show');});mo.observe(ov,{attributes:true,attributeFilter:['class']});}catch(e){}}
  }
  function initProgress(){var bar=document.getElementById('shsProgress');if(!bar)return;document.addEventListener('click',function(e){var a=e.target.closest&&e.target.closest('a');if(!a||!a.href)return;if(a.target==='_blank'||a.href.indexOf('#')!==-1)return;if(a.href.indexOf(window.location.origin)!==0)return;bar.style.opacity='1';bar.style.width='70%';});window.addEventListener('beforeunload',function(){bar.style.width='100%';});}
  function guardCdnScripts(){window.addEventListener('error',function(ev){var t=ev.target;if(t&&t.tagName==='SCRIPT'&&t.src&&!t.dataset.shsRetried){t.dataset.shsRetried='1';var s=document.createElement('script');s.src=t.src;s.async=true;s.defer=true;s.dataset.shsRetried='1';document.head.appendChild(s);}},true);}
  function init(){
    try{
      const rs=sessionStorage.getItem('shs_restore');
      /* إن كان العنوان يحمل حالة (#s= / #c= / #q= / #ch=) فالموجّه سيتكفّل بالاستعادة،
         فلا نفتح المشغّل هنا أيضاً حتى لا يُفتح مرتين. نبقي مسار m3u فقط لأنه ليس في العنوان. */
      const hasHash = !!(window.location.hash || '').replace(/^#/,'');
      if(rs){
        const d=JSON.parse(rs);
        if(d.type==='channel'){
          if(!hasHash) setTimeout(()=>openPlayerChannel(d.ch),500);
        }else if(d.type==='episode'){
          App.currentSeriesId=d.seriesId;
          App.currentSeriesName=d.seriesName;
          if(d.seriesId && !hasHash){
            fetch('api.php?action=episodes&series_id='+encodeURIComponent(d.seriesId))
              .then(r=>r.json()).then(res=>{
                App.allEpisodes=res.episodes||[];
                if(App.allEpisodes.length)openPlayerEpisode(d.idx);
              }).catch(()=>{});
          }
        }else if(d.type==='m3u'){
          PL.m3uEntries=d.entries||[];
          if(typeof PL.m3uName === 'undefined') PL.m3uName = d.name||'';
          if(PL.m3uEntries.length)setTimeout(()=>playM3UEntry(d.idx),500);
        }
      }
    }catch(e){}
    enableLazyImages();initToTop();initProgress();guardCdnScripts();
  }
  if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',init);}else{init();}
})();
</script>
<!-- ════════════════════ نهاية تحسينات واجهة شاشتي ════════════════════ -->
<!-- ════════════ تحسينات مشغّل شاشتي — إضافة آمنة (لا تحذف شيئاً) ════════════ -->
