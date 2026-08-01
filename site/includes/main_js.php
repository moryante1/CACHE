<script>
'use strict';

/* ════ SMART CACHE ════ */
(function(){}());

/* ════ SMART CACHE+ — تسريع شامل لطلبات api.php (إضافة) ════
   - كاش لكل الطلبات القرائية (channels/series/episodes/all_content)
   - منع تكرار الطلبات المتزامنة المتطابقة (deduplication)
   - تجاهل طلبات increment_view (كتابة) من الكاش
   ──────────────────────────────────────────────────────────── */
(function(){
  const orig=window.fetch;
  const mem=new Map();          // كاش في الذاكرة (أسرع من sessionStorage)
  const inflight=new Map();     // طلبات جارية الآن
  const TTL=10*60*1000;         // صلاحية الكاش: ١٠ دقائق (كانت دقيقة واحدة → إعادة جلب مستمرة)
  const SS_PREFIX='shs_c_';     // كاش يبقى بين تنقّلات الصفحة وإعادة التحميل

  // استرجاع ما خُزّن في الجلسة السابقة إلى الذاكرة
  try{
    const now0=Date.now();
    Object.keys(sessionStorage).forEach(k=>{
      if(k.indexOf(SS_PREFIX)!==0) return;
      try{
        const o=JSON.parse(sessionStorage.getItem(k));
        if(o && (now0-o.t)<TTL) mem.set(k.slice(SS_PREFIX.length),{body:o.body,t:o.t});
        else sessionStorage.removeItem(k);
      }catch(e){ try{sessionStorage.removeItem(k);}catch(_){} }
    });
  }catch(e){}
  // الإجراءات القرائية القابلة للتخزين
  const READ=/action=(channels|series|episodes|all_content)\b/;
  // إجراءات كتابة لا تُخزَّن إطلاقاً
  const SKIP=/action=(increment_view)\b/;

  window.fetch=function(){
    const url=arguments[0];
    // نتعامل فقط مع طلبات api.php القرائية البسيطة (GET بدون body)
    const simple = (typeof url==='string') && url.includes('api.php')
                 && READ.test(url) && !SKIP.test(url)
                 && (arguments.length<2 || !arguments[1] || (arguments[1].method||'GET').toUpperCase()==='GET');
    if(!simple) return orig.apply(this,arguments);

    const k=url;
    const now=Date.now();
    // 1) كاش في الذاكرة
    const c=mem.get(k);
    if(c && (now-c.t)<TTL){
      return Promise.resolve(new Response(c.body,{status:200,headers:new Headers({'Content-Type':'application/json'})}));
    }
    // 2) طلب جارٍ بنفس الرابط → شاركه
    if(inflight.has(k)) return inflight.get(k).then(b=>new Response(b,{status:200,headers:new Headers({'Content-Type':'application/json'})}));

    const p=orig.apply(this,arguments).then(r=>{
      // Never persist an upstream error: a cached failure is worse than a retry.
      if(!r.ok){
        inflight.delete(k);
        return r;
      }
      const clone=r.clone();
      return clone.text().then(body=>{
        const t=Date.now();
        try{ mem.set(k,{body,t}); }catch(e){}
        // نحفظ الاستجابات الصغيرة فقط في sessionStorage (حدّها ~5MB)
        try{
          if(body.length < 600000) sessionStorage.setItem(SS_PREFIX+k, JSON.stringify({body,t}));
        }catch(e){ /* الحصة ممتلئة — الذاكرة تكفي */ }
        inflight.delete(k);
        return body;
      }).then(()=>r).catch(()=>{inflight.delete(k);return r;});
    }).catch(e=>{ inflight.delete(k); throw e; });

    // نخزّن وعد النص للمشاركة بين الطلبات المتزامنة
    inflight.set(k, p.then(r=>{
      if(!r.ok) throw new Error('HTTP '+r.status);
      return r.clone().text();
    }));
    return p;
  };

  // أداة إبطال الكاش يدوياً عند الحاجة
  window.scInvalidate=function(){
    mem.clear();
    try{ Object.keys(sessionStorage).forEach(k=>{ if(k.indexOf(SS_PREFIX)===0) sessionStorage.removeItem(k); }); }catch(e){}
  };
})();

/* ══════════════════════════════════════════════════════════════════════
   ║  التحديث اللحظي للمحتوى (Live Updates)                              ║
   ║  ───────────────────────────────────────────────────────────────    ║
   ║  الفكرة: بدل جلب كل المحتوى كل فترة (ثقيل جداً)، نسأل الخادم سؤالاً  ║
   ║  واحداً صغيراً: "ما هي بصمة المحتوى الحالية؟" الرد ~40 بايت.        ║
   ║  إن تغيّرت البصمة = هناك إضافة جديدة → نُبطل الكاش ونحدّث الواجهة.  ║
   ║                                                                      ║
   ║  لماذا ليس WebSocket؟ لأنه يحتاج عملية دائمة ومنفذاً خاصاً لا        ║
   ║  توفّرهما الاستضافات المشتركة، ولأن المحتوى هنا يتغيّر نادراً        ║
   ║  (إضافة إدارية)، فاتصال دائم لكل زائر تكلفة بلا فائدة.              ║
   ══════════════════════════════════════════════════════════════════════ */
(function(){
  var POLL_ACTIVE   = 30000;   // كل ٣٠ ثانية والتبويب مفتوح أمام المستخدم
  var POLL_HIDDEN   = 0;       // ٠ = نوقف تماماً عندما يكون التبويب في الخلفية
  var _lastVersion  = null;
  var _timer        = null;
  var _busy         = false;
  var _failCount    = 0;

  // لا نزعج المستخدم أثناء المشاهدة: التحديث يُؤجَّل حتى يغلق المشغّل
  function playerOpen(){
    var o = document.getElementById('playerOverlay');
    return !!(o && o.classList.contains('active'));
  }

  function currentScreen(){
    function vis(id){ var e=document.getElementById(id); return e && !e.classList.contains('hidden'); }
    if(vis('epSection'))            return 'episodes';
    if(vis('searchViewSection'))    return 'search';
    if(vis('categoryViewSection'))  return 'category';
    return 'home';
  }

  /* إعادة بناء الشاشة الحالية بالبيانات الجديدة — بلا إعادة تحميل الصفحة */
  async function refreshCurrentScreen(){
    try{
      if(typeof window.scInvalidate==='function') window.scInvalidate();
      var scr = currentScreen();

      if(scr==='category' && window.App && App.currentCategoryView){
        // إعادة فتح نفس القسم يعيد جلبه ورسمه بالمحتوى الجديد
        if(typeof openCategoryView==='function')
          await openCategoryView(App.currentCategoryView.id, App.currentCategoryView.name||'');
        return;
      }
      if(scr==='episodes' && window.App && App.currentSeriesId){
        if(typeof openSeriesEpisodes==='function')
          await openSeriesEpisodes(App.currentSeriesId, App.currentSeriesName||'', App.currentSeriesPoster||'');
        return;
      }
      if(scr==='search'){
        if(typeof handleSearch==='function') handleSearch();
        return;
      }
      // الرئيسية: نعيد بناء الصفوف من الصفر (الأقسام قد تكون تغيّرت أيضاً)
      if(typeof loadAndBuildNetflixHome==='function'){
        // نُفرغ فهرس البحث حتى لا تتراكم نسخ قديمة
        try{ if(window._shsKeys) _shsKeys.clear(); if(window.App) App.allContent=[]; }catch(e){}
        await loadAndBuildNetflixHome();
      }
    }catch(e){ /* فشل التحديث لا يجب أن يكسر الصفحة */ }
  }

  function showUpdateToast(){
    // إشعار خفيف بدل مفاجأة المستخدم بتغيّر الشاشة
    try{ if(typeof toast==='function') toast('تم تحديث المحتوى'); }catch(e){}
  }

  // --- الاستماع لتحديثات الويب سوكت اللحظية ---
  window.addEventListener('shashety_ws_update', async function(){
    if(playerOpen()){
      window.__shsPendingRefresh = true;
      return;
    }
    await refreshCurrentScreen();
  });

  async function checkOnce(){
    // إذا كان الويب سوكت متصلاً، نوقف الضغط على السيرفر (HTTP Polling) ونعتمد عليه!
    if(window.isWebSocketActive) {
      schedule(); // استمرار الجدولة كإجراء احتياطي في حال انقطع السوكت
      return;
    }

    if(_busy) return;
    _busy = true;
    try{
      // cache:'no-store' ضروري: لا نريد أي طبقة كاش بيننا وبين البصمة
      var r = await window.fetch('api.php?action=content_version&_t='+Date.now(), {cache:'no-store'});
      if(!r.ok) throw new Error('HTTP '+r.status);
      var d = await r.json();
      var v = d && (d.version!==undefined ? String(d.version) : null);
      if(v===null) throw new Error('no version field');

      _failCount = 0;
      if(_lastVersion===null){ _lastVersion = v; return; }   // أول قراءة = خط الأساس
      if(v === _lastVersion) return;                          // لا جديد

      _lastVersion = v;
      if(playerOpen()){
        // المستخدم يشاهد الآن — نحدّث بعد إغلاق المشغّل مباشرة
        window.__shsPendingRefresh = true;
        return;
      }
      await refreshCurrentScreen();
      showUpdateToast();
    }catch(e){
      // تراجع تدريجي عند الفشل حتى لا نُغرق خادماً معطّلاً
      _failCount++;
    }finally{
      _busy = false;
      schedule();
    }
  }

  function nextDelay(){
    if(document.hidden) return POLL_HIDDEN;           // ٠ = لا جدولة إطلاقاً
    var base = POLL_ACTIVE;
    if(_failCount>0) base = Math.min(base * Math.pow(2,_failCount), 10*60*1000);
    return base;
  }

  function schedule(){
    if(_timer){ clearTimeout(_timer); _timer=null; }
    var d = nextDelay();
    if(d<=0) return;                                  // التبويب مخفي → توقف تام
    _timer = setTimeout(checkOnce, d);
  }

  // عند عودة المستخدم للتبويب: افحص فوراً (هذا يغطي الغياب الطويل بلا استهلاك)
  document.addEventListener('visibilitychange', function(){
    if(document.hidden){ if(_timer){clearTimeout(_timer);_timer=null;} }
    else { checkOnce(); }
  });

  // تحديث مؤجَّل بعد إغلاق المشغّل
  function hookClose(){
    if(typeof window.closePlayer!=='function'){ setTimeout(hookClose,300); return; }
    if(window.closePlayer.__shsLiveHooked) return;
    var _o = window.closePlayer;
    window.closePlayer = function(){
      var r = _o.apply(this, arguments);
      if(window.__shsPendingRefresh){
        window.__shsPendingRefresh = false;
        setTimeout(function(){ refreshCurrentScreen().then(showUpdateToast); }, 300);
      }
      return r;
    };
    window.closePlayer.__shsLiveHooked = true;
  }

  // أدوات يدوية للتحكم من الكونسول أو من أزرار مستقبلية
  window.shsLiveUpdates = {
    checkNow: function(){ return checkOnce(); },
    stop:  function(){ if(_timer){clearTimeout(_timer);_timer=null;} POLL_ACTIVE=0; },
    start: function(ms){ POLL_ACTIVE = ms||30000; schedule(); }
  };

  document.addEventListener('DOMContentLoaded', function(){
    hookClose();
    // فحص أول بعد ٥ ثوانٍ حتى لا نزاحم تحميل الصفحة الأولى
    setTimeout(checkOnce, 5000);
  });
})();

/* ════ APP STATE ════ */
const App={
  allContent:[],cats:[],
  currentType:'',currentSeriesId:0,currentSeriesName:'',allEpisodes:[],
  currentEpisodeIdx:-1,currentCategoryView:null,
  _catCache:{}, /* [SHS-CATVIEW] كاش محتوى الأقسام للاستجابة الفورية */
  currentSeriesPoster:'', /* [SHS-EPPOSTER] بوستر المسلسل الحالي كخلفية احتياطية للحلقات */
  license:<?php echo $license_expired?'true':'false'; ?>
};
/* جسر App على window — لنفس سبب _shsKeys أعلاه:
   refreshCurrentScreen تفحص window.App قبل إعادة بناء شاشة
   القسم أو الحلقات، وكانت النتيجة undefined دائماً فلا تُعاد
   الشاشة أبداً بعد تحديث المحتوى. */
try{ window.App = App; }catch(e){}

/* ════ DEVICE DETECTION — مرة واحدة في أول الكود ════ */
const _UA=(function(){
  const ua=navigator.userAgent||'';
  const isIOS=/iPad|iPhone|iPod/.test(ua)&&!window.MSStream;
  const isAndroidTV=/Android/i.test(ua)&&(/TV|STB|BOX|bravia|shield|mibox/i.test(ua)||!/Mobile/i.test(ua));
  const isAndroidMobile=/Android/i.test(ua)&&/Mobile/i.test(ua)&&!isAndroidTV;
  // TV الحقيقي فقط — لا نصنف الكمبيوتر كـ TV مطلقاً
  const isSmartTV=/SmartTV|SMART-TV|Tizen|WebOS|HbbTV|VIDAA|NetCast|Hisense|Philips|TCL|BRAVIA/i.test(ua);
  return{
    ua,
    isIOS,
    isAndroid:/Android/i.test(ua),
    isAndroidMobile,
    isAndroidTV,
    isSmartTV,
    isTV:isAndroidTV||isSmartTV,   // الكمبيوتر ليس TV — fullscreen API يعمل عليه
    isWindows:/Windows NT/i.test(ua),
    isMobile:/iPhone|iPad|iPod|Android/i.test(ua)
  };
})();
var _isTV=_UA.isTV, _isIOS=_UA.isIOS, _isAndroid=_UA.isAndroid, _isWindows=_UA.isWindows;

/* ════ DEVTOOLS PROTECTION ════ */
<?php if ($gs_block_devtools): ?>
(function(){
  const overlay=document.getElementById('devtoolsOverlay'),lockIcon=document.getElementById('lockIcon');
  if(!overlay) return;
  function show(){overlay.classList.add('show');lockIcon.classList.remove('shake');void lockIcon.offsetWidth;lockIcon.classList.add('shake')}
  document.addEventListener('keydown',function(e){
    if(e.keyCode===123||e.ctrlKey&&e.shiftKey&&(e.keyCode===73||e.keyCode===74||e.keyCode===67)||e.ctrlKey&&e.keyCode===85){e.preventDefault();e.stopPropagation();show();return false}
  },true);
  let open=false;
  setInterval(function(){
    const w=!_UA.isMobile&&((window.outerWidth-window.innerWidth>160)||(window.outerHeight-window.innerHeight>160));
    if(w&&!open){open=true;show();}else if(!w&&open){open=false;overlay.classList.remove('show');}
  },800);
  document.addEventListener('contextmenu',function(e){e.preventDefault();return false});
  ['log','debug','warn','info','dir','table','trace','error'].forEach(function(m){try{console[m]=function(){}}catch(e){}});
})();
<?php endif; ?>

/* ════ FAVORITES ════ */
let MyFavs={channels:[],series:[]};
try{const s=localStorage.getItem('shashety_favs_v2');if(s){const p=JSON.parse(s);if(p&&Array.isArray(p.channels)&&Array.isArray(p.series))MyFavs=p;}}catch(e){}
function saveFavs(){try{localStorage.setItem('shashety_favs_v2',JSON.stringify(MyFavs));}catch(e){toast('تعذر حفظ المفضلة');}
  // مزامنة فهرس المفضلة السريع بعد أي تعديل
  if(typeof rebuildFavSets==='function') rebuildFavSets();
}
function toggleMyFav(id,name,type,icon_url,streamUrl='',subUrl=''){
  if(!MyFavs[type])return;
  const list=MyFavs[type];
  const idx=list.findIndex(x=>String(x.id)===String(id));
  if(idx>=0){list.splice(idx,1);toast('أزيل من المفضلة');}
  else{list.push({id,name,icon_url,stream_url:streamUrl,subtitle_url:subUrl,t_stamp:Date.now()});toast('أضيف للمفضلة');}
  saveFavs();buildFavPanel();
}
function buildFavPanel(){
  const b=document.getElementById('favPanelBody');
  const merged=[...MyFavs.channels.map(c=>({...c,ftype:'channels'})),...MyFavs.series.map(s=>({...s,ftype:'series'}))];
  merged.sort((a,b_)=>(b_.t_stamp||0)-(a.t_stamp||0));
  if(!merged.length){b.innerHTML='<div style="text-align:center;padding:40px;color:var(--text-muted)">قائمة المفضلة فارغة</div>';return;}
  b.innerHTML='';
  merged.forEach(item=>{
    const d=document.createElement('div');d.className='m3u-item';
    const ic=item.icon_url?`<img class="m3u-item-logo" src="${esc(item.icon_url)}" loading="lazy">`:`<div class="m3u-item-logo" style="display:flex;align-items:center;justify-content:center;color:#666;font-size:1.2rem">${item.ftype==='series'?'🎬':'📺'}</div>`;
    const del=`<button onclick="event.stopPropagation();toggleMyFav('${item.id}','','${item.ftype}')" style="background:rgba(229,9,20,.15);border-radius:6px;width:30px;height:30px;display:flex;align-items:center;justify-content:center;color:#ff4d57;cursor:pointer;border:none">🗑</button>`;
    d.innerHTML=`${ic}<div style="flex:1;min-width:0"><div class="m3u-item-name">${esc(item.name)}</div><div class="m3u-item-group">${item.ftype==='channels'?'بث مباشر':'مسلسلات وأفلام'}</div></div>${del}`;
    d.onclick=()=>{if(item.ftype==='channels')openPlayerChannel({id:item.id,name:item.name,stream_url:item.stream_url,subtitle_url:item.subtitle_url});else openSeriesEpisodes(item.id,item.name,item.poster_url||item.img||'');};
    b.appendChild(d);
  });
}

/* ════ NOTIFICATIONS ════ */
const PendingNotifsKey='shashety_notifs_v4';
const NOTIF_SYNC_KEY='shashety_sync_v5';
const MAX_STORED_NOTIFICATIONS=100;
const MAX_NOTIFICATION_REQUESTS=4;
const MAX_NEW_ITEMS_PER_REQUEST=25;
let MyNotifsQueue=[];
let notifSyncInProgress=false;
try{
  const saved=JSON.parse(localStorage.getItem(PendingNotifsKey)||'[]');
  MyNotifsQueue=Array.isArray(saved)?saved.slice(0,MAX_STORED_NOTIFICATIONS):[];
  // Older builds could save thousands of notifications and freeze this panel.
  if(Array.isArray(saved)&&saved.length!==MyNotifsQueue.length)localStorage.setItem(PendingNotifsKey,JSON.stringify(MyNotifsQueue));
}catch(e){}
// v4 kept one growing "seen" array per category.  It is no longer used and
// can occupy enough storage to make subsequent localStorage writes fail.
try{localStorage.removeItem('shashety_sync_v4');}catch(e){}
function updateNotifBadge(){const b=document.getElementById('notifBadge');if(b)b.style.display=MyNotifsQueue.length>0?'block':'none';}
async function syncNotifications(cats){
  if(!Array.isArray(cats)||!cats.length)return;
  if(notifSyncInProgress)return;
  notifSyncInProgress=true;
  try{
    // This is a tiny uncached response containing only counts and maximum IDs.
    // It keeps notification detection current even when the home catalogue is
    // intentionally served from the browser cache.
    const r=await fetch('api.php?action=notification_state&_='+Date.now(),{cache:'no-store'});
    if(r.ok){const d=await r.json();if(d&&Array.isArray(d.categories))cats=d.categories;}
  }catch(e){}
  try{
  let state={};
  try{state=JSON.parse(localStorage.getItem(NOTIF_SYNC_KEY)||'{}')||{};}catch(e){}
  const isFirst=!localStorage.getItem(NOTIF_SYNC_KEY);
  let requestBudget=MAX_NOTIFICATION_REQUESTS;
  const discovered=[];
  for(const cat of cats){
    const cid=cat.id;
    if(!state[cid])state[cid]={srCount:0,chCount:0,maxSrId:0,maxChId:0};
    const st=state[cid];
    const curSr=parseInt(cat.series_count||0),curCh=parseInt(cat.channel_count||0);
    const mSr=parseInt(cat.max_sr_id||0), mCh=parseInt(cat.max_ch_id||0);
    const st_mSr=parseInt(st.maxSrId||0), st_mCh=parseInt(st.maxChId||0);
    // First visit establishes a baseline without downloading the whole catalogue.
    if(isFirst){
      st.srCount=curSr; st.chCount=curCh;
      st.maxSrId=mSr; st.maxChId=mCh;
      continue;
    }
    if(mSr>st_mSr && requestBudget>0){
      requestBudget--;
      try{const r=await fetch('api.php?action=series&category_id='+encodeURIComponent(cid)+'&after_id='+encodeURIComponent(st_mSr)+'&limit='+MAX_NEW_ITEMS_PER_REQUEST);const d=await r.json();
        (d.series||[]).forEach(s=>discovered.push({id:s.id,type:'series',name:s.name,img:s.poster_url||'',catName:cat.name}));
      }catch(e){}
    }
    if(mCh>st_mCh && requestBudget>0){
      requestBudget--;
      try{const r=await fetch('api.php?action=channels&category_id='+encodeURIComponent(cid)+'&after_id='+encodeURIComponent(st_mCh)+'&limit='+MAX_NEW_ITEMS_PER_REQUEST);const d=await r.json();
        (d.channels||[]).forEach(c=>discovered.push({id:c.id,type:'channel',name:c.name,img:c.logo_url||'',catName:cat.name,streamUrl:c.stream_url,subUrl:c.subtitle_url}));
      }catch(e){}
    }
    // Deletions/reorders update the baseline but do not need a notification.
    // Advancing it also prevents an old bulk import being retried on every visit.
    st.srCount=curSr; st.chCount=curCh; st.maxSrId=mSr; st.maxChId=mCh;
  }
  try{localStorage.setItem(NOTIF_SYNC_KEY,JSON.stringify(state));}catch(e){}
  if(discovered.length){
    const known=new Set(MyNotifsQueue.map(x=>String(x.type)+':'+String(x.id)));
    discovered.forEach(nd=>{const key=String(nd.type)+':'+String(nd.id);if(!known.has(key)){known.add(key);MyNotifsQueue.unshift(nd);}});
    MyNotifsQueue=MyNotifsQueue.slice(0,MAX_STORED_NOTIFICATIONS);
    try{localStorage.setItem(PendingNotifsKey,JSON.stringify(MyNotifsQueue));}catch(e){}
  }
  updateNotifBadge();
  }finally{notifSyncInProgress=false;}
}
function buildNotifPanel(){
  const b=document.getElementById('notifPanelBody');b.innerHTML='';
  if(!MyNotifsQueue.length){b.innerHTML='<div style="text-align:center;padding:40px;color:var(--text-muted)">لا توجد إشعارات</div>';return;}
  const fragment=document.createDocumentFragment();
  MyNotifsQueue.slice(0,MAX_STORED_NOTIFICATIONS).forEach(item=>{
    const d=document.createElement('div');d.className='m3u-item';
    d.style.cssText='background:#1a1a1a;padding:12px;border:1px solid rgba(229,9,20,.15);border-radius:10px;margin-bottom:8px;position:relative;align-items:flex-start';
    const ph=item.img?`<img src="${esc(item.img)}" style="width:48px;height:68px;object-fit:cover;border-radius:6px;flex-shrink:0;background:#222">`:`<div style="width:48px;height:68px;display:flex;align-items:center;justify-content:center;background:#222;border-radius:6px;flex-shrink:0;color:#666;font-size:1.4rem">${item.type==='channel'?'📺':'🎬'}</div>`;
    const ap=`openFromNotif('${item.id}','${item.type}','${escA(item.name)}','${escA(item.streamUrl||'')}','${escA(item.subUrl||'')}')`;
    d.innerHTML=`${ph}<div style="flex:1;min-width:0"><div style="font-weight:bold;font-size:.88rem;color:#fff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:4px">${esc(item.name)}</div><div style="font-size:.7rem;color:var(--text-dim);margin-bottom:8px">في <span style="color:#B36BFF">${esc(item.catName||'')}</span></div><button onclick="event.stopPropagation();${ap}" style="background:var(--red);color:#fff;border:none;padding:3px 10px;border-radius:6px;font-size:.74rem;font-weight:700;cursor:pointer">▶ تشغيل</button></div><button onclick="event.stopPropagation();removeNotif('${item.id}')" style="position:absolute;top:8px;left:8px;background:rgba(255,255,255,.07);color:#ccc;border:none;border-radius:50%;width:22px;height:22px;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:.65rem">✕</button>`;
    fragment.appendChild(d);
  });
  b.appendChild(fragment);
}
function removeNotif(id){MyNotifsQueue=MyNotifsQueue.filter(n=>String(n.id)!==String(id));localStorage.setItem(PendingNotifsKey,JSON.stringify(MyNotifsQueue));buildNotifPanel();updateNotifBadge();}
function openFromNotif(id,type,name,sUrl='',subUrl=''){
  if(type==='channel')openPlayerChannel({id:id,name:name,stream_url:sUrl,subtitle_url:subUrl});
  else openSeriesEpisodes(id,name);
}

/* ════ PANELS ════ */
function openPanelOverlay(){document.getElementById('panelOverlay').classList.add('show');document.body.style.overflow='hidden';history.pushState({depth:'panel'},'');}
function closePanelOverlay(){document.getElementById('panelOverlay').classList.remove('show');document.body.style.overflow='';}
function closeAllPanels(){['favPanel','m3uPanel','notifPanel','epPanel'].forEach(id=>document.getElementById(id)?.classList.remove('open'));closePanelOverlay();}
function toggleFavPanel(){const p=document.getElementById('favPanel');const o=p.classList.toggle('open');if(o){openPanelOverlay();buildFavPanel();}else closePanelOverlay();}
function toggleNotifPanel(){const p=document.getElementById('notifPanel');const o=p.classList.toggle('open');if(o){openPanelOverlay();buildNotifPanel();}else closePanelOverlay();}
function toggleM3UPanel(){PL.m3uPanelOpen=!PL.m3uPanelOpen;document.getElementById('m3uPanel').classList.toggle('open',PL.m3uPanelOpen);if(PL.m3uPanelOpen)history.pushState({depth:'panel'},'');}
function toggleEpPanel(){PL.epPanelOpen=!PL.epPanelOpen;document.getElementById('epPanel').classList.toggle('open',PL.epPanelOpen);if(PL.epPanelOpen)history.pushState({depth:'panel'},'');}
window.addEventListener('scroll',()=>document.getElementById('navbar').classList.toggle('scrolled',window.scrollY>10),{passive:true});

/* ════ FORMAT ════ */
function detectFmt(url){
  const c=(url||'').split('?')[0].toLowerCase().trim();
  if(c.endsWith('.m3u8')||c.endsWith('.m3u'))return 'hls';
  if(c.endsWith('.mpd'))return 'dash';
  if(c.endsWith('.flv'))return 'flv';
  if(c.endsWith('.mp4')||c.endsWith('.m4v'))return 'mp4';
  if(c.endsWith('.mkv'))return 'mkv';
  if(c.endsWith('.webm'))return 'webm';
  if(c.endsWith('.ts')||c.endsWith('.mts'))return 'ts';
  /* ⚠️ حاويات لا يدعمها أي متصفح (تعمل في VLC فقط).
     كانت هذه الامتدادات تسقط في الحالة الافتراضية 'hls' فيحاول
     المشغّل تشغيل ملف AVI عبر hls.js — فشل مؤكد برسالة غامضة.
     الآن نصنّفها بوضوح لنعطي المستخدم سبباً مفهوماً. */
  if(c.endsWith('.avi')||c.endsWith('.wmv')||c.endsWith('.mpg')||
     c.endsWith('.mpeg')||c.endsWith('.vob')||c.endsWith('.rmvb')||
     c.endsWith('.divx')||c.endsWith('.3gp'))return 'unsupported';
  if(c.endsWith('.mov'))return 'mp4';   // MOV يعمل غالباً عبر مشغّل MP4
  return 'hls';
}
/* هل الحاوية قابلة للتشغيل في المتصفح أصلاً؟
   MKV و AVI وأخواتها تعمل في VLC لكن لا يدعمها أي متصفح. */
function fmtBrowserSupport(fmt){
  if(fmt==='unsupported')return{ok:false,why:'هذه الصيغة (AVI/WMV/MPG) لا يدعمها أي متصفح — تعمل في VLC فقط.'};
  if(fmt==='mkv'){
    const v=document.createElement('video');
    const can=v.canPlayType('video/x-matroska')||v.canPlayType('video/x-matroska; codecs="avc1.42E01E"');
    if(!can)return{ok:false,why:'ملفات MKV لا تعمل في المتصفحات (تعمل في VLC). يلزم تحويلها إلى MP4.'};
  }
  return{ok:true,why:''};
}
function fmtLabel(url){return{hls:'HLS',dash:'DASH',flv:'FLV',mp4:'MP4',mkv:'MKV',webm:'WEBM',ts:'TS'}[detectFmt(url)]||'HLS';}
function isLiveFormat(url){return['hls','dash','flv','ts'].includes(detectFmt(url));}

/* ════ HELPERS ════ */
function esc(s){const d=document.createElement('div');d.textContent=String(s||'');return d.innerHTML;}
function escA(s){return String(s||'').replace(/\\/g,'\\\\').replace(/'/g,"\\'").replace(/"/g,'\\"').replace(/\n/g,'\\n');}
function toast(msg){
  const c=document.getElementById('toastContainer'),t=document.createElement('div');
  t.className='toast';t.textContent=msg;
  c.appendChild(t);
  setTimeout(()=>{t.classList.add('out');t.addEventListener('animationend',()=>t.remove());},3200);
}

/* ════ TMDB ════ */
/* [أمان] المفتاح لم يعد يصل للمتصفح إطلاقاً — كل الطلبات عبر وكيل الخادم. */
async function showTmdbInfoClient(query,defaultType){
  const modal=document.getElementById('tmdbInfoM');const body=document.getElementById('tmdbInfoBody');
  modal.classList.add('open');document.body.style.overflow='hidden';
  body.innerHTML='<div style="text-align:center;padding:40px;color:var(--text-muted)">جاري الجلب...</div>';
  try{
    const cq=query.replace(/(1080p|720p|4k|fhd|hd|ar|en)/gi,'').trim();
    const sd=await(await fetch(`index.php?tmdb_proxy=search&q=${encodeURIComponent(cq)}`)).json();
    if(sd.error==='disabled'){body.innerHTML='<div style="text-align:center;padding:40px;color:var(--text-muted)">ميزة التفاصيل غير مفعلة</div>';return;}
    if(sd.error==='bad key'){body.innerHTML='<div style="text-align:center;padding:40px;color:#ff4d57">مفتاح API غير صحيح</div>';return;}
    if(!sd.results||!sd.results.length){body.innerHTML='<div style="text-align:center;padding:40px;color:var(--text-muted)">لم يتم العثور على معلومات</div>';return;}
    const item=sd.results.find(i=>i.media_type==='movie'||i.media_type==='tv')||sd.results[0];
    const type=(item.media_type||defaultType)==='movie'?'movie':'tv';
    let d=await(await fetch(`index.php?tmdb_proxy=detail&type=${type}&id=${encodeURIComponent(item.id)}`)).json();
    if(!d.overview){
      const en=await(await fetch(`index.php?tmdb_proxy=detail&type=${type}&id=${encodeURIComponent(item.id)}&lang=en`)).json();
      d.overview=en.overview;
    }
    const title=d.title||d.name||cq;
    /* [أمان] بيانات TMDB خارجية — تُهرَّب قبل الإدراج لمنع XSS.
       poster_path يُبنى من رقم/مسار TMDB فقط، ونتحقق من شكله. */
    const posterOk=typeof d.poster_path==='string'&&/^\/[A-Za-z0-9._-]+$/.test(d.poster_path);
    const poster=posterOk?`https://image.tmdb.org/t/p/w300${d.poster_path}`:'';
    const year=String(d.release_date||d.first_air_date||'').substring(0,4);
    const rating=d.vote_average?Number(d.vote_average).toFixed(1):'—';
    const genres=(d.genres||[]).map(g=>`<span class="tmdb-genre-badge">${esc(g.name)}</span>`).join(' ');
    body.innerHTML=`<div class="tmdb-info-wrap">${poster?`<img src="${esc(poster)}" class="tmdb-info-poster">`:''}<div class="tmdb-info-details"><div class="tmdb-info-title">${esc(title)} ${year?'('+esc(year)+')':''}</div><div class="tmdb-info-meta"><span style="color:#f5c518;font-weight:bold">★ ${esc(rating)}</span></div><div style="margin-bottom:12px">${genres}</div><div class="tmdb-info-overview">${esc(d.overview||'لا توجد قصة متوفرة')}</div></div></div>`;
  }catch(e){body.innerHTML='<div style="text-align:center;padding:40px;color:#ff4d57">خطأ في الاتصال</div>';}
}
function closeTmdbModal(){document.getElementById('tmdbInfoM').classList.remove('open');document.body.style.overflow='';}
document.getElementById('tmdbInfoM').addEventListener('click',function(e){if(e.target===this)closeTmdbModal();});

/* ════ LOAD HOME ════ */
const DISABLED_CATEGORY_IDS = <?php echo json_encode($disabled_category_ids); ?>;
const DISABLED_CHANNEL_IDS = <?php echo json_encode($disabled_channel_ids); ?>;
const HIDE_MOST_WATCHED = <?php echo $hide_most_watched ? 'true' : 'false'; ?>;
const HIDE_SUGGESTIONS  = <?php echo $hide_suggestions ? 'true' : 'false'; ?>;
async function loadAndBuildNetflixHome(){
  if(App.license){
    document.getElementById('netflixStyleSliders').innerHTML='<div style="text-align:center;padding:60px 20px;color:var(--text-muted)"><p>الرخصة منتهية</p><a href="activate.php" style="display:inline-block;margin-top:16px;padding:10px 24px;background:var(--red);color:#fff;border-radius:99px;font-weight:800">تجديد الرخصة</a></div>';
    return;
  }
  try{
    // Use the shared cache. It is invalidated when content changes, so a
    // cache-busting timestamp here only made every page load slower.
    const catRes=await fetch('api.php?action=all_content');
    if(!catRes.ok) throw new Error('HTTP '+catRes.status);
    const catData=await catRes.json();
    if(!catData || catData.success===false) throw new Error('Invalid content response');
    App.cats=(catData.categories||[]).filter(c=>!DISABLED_CATEGORY_IDS.includes(parseInt(c.id)));
    renderCategoryNavBar();
    const wrap=document.getElementById('netflixStyleSliders');
    wrap.innerHTML='';
    if(!App.cats.length){wrap.innerHTML='<div style="padding:40px;text-align:center;color:var(--text-muted)">لا يوجد محتوى متاح</div>';return;}
    App.cats.forEach(c=>{
      const seriesCnt=parseInt(c.series_count||0);
      const channelCnt=parseInt(c.channel_count||0);
      if(channelCnt>0&&seriesCnt===0){buildSliderRow(wrap,c,'channels',channelCnt);}
      else if(seriesCnt>0&&channelCnt===0){buildSliderRow(wrap,c,'series',seriesCnt);}
      else if(channelCnt>0&&seriesCnt>0){buildSliderRow(wrap,c,'channels',channelCnt);buildSliderRow(wrap,c,'series',seriesCnt,true);}
      else{buildSliderRow(wrap,c,'channels',6);}
    });
    /* استعادة ما كان المستخدم يشاهده قبل التحديث (مسلسل/قسم/بحث/فيديو).
       نبدأها قبل انتظار صفوف الرئيسية، فلا يرى المستخدم الرئيسية ثم قفزة. */
    const _hadState = !!(window.location.hash || '').replace(/^#/,'');
    const _restorePromise = (async ()=>{
      try{ return await shsRestoreFromHash(); }
      catch(e){ console.error('restore:', e); return false; }
    })();

    if(_hadState){
      // نُخفي الرئيسية فوراً حتى لا تومض قبل الاستعادة
      const _hw = document.getElementById('heroWelcome');
      const _ns = document.getElementById('netflixStyleSliders');
      if(_hw) _hw.classList.add('hidden');
      if(_ns) _ns.classList.add('hidden');
      const _ok = await _restorePromise;
      if(!_ok){
        // فشلت الاستعادة (عنصر محذوف مثلاً) — نُعيد الرئيسية بدل ترك شاشة فارغة
        try{ shsSetHash(null); }catch(e){}
        if(_hw) _hw.classList.remove('hidden');
        if(_ns) _ns.classList.remove('hidden');
      }
      fetchAllRows();          // نبني الرئيسية في الخلفية للرجوع إليها لاحقاً
    } else {
      await fetchAllRows();
      await _restorePromise;
    }

    const syncDelay=window.requestIdleCallback||(fn=>setTimeout(fn,4000));
    syncDelay(()=>syncNotifications(App.cats));
    // الصفوف المميزة ثانوية — نؤجّلها حتى يهدأ المتصفح فلا تزاحم الرسم الأول
    if(!HIDE_MOST_WATCHED||!HIDE_SUGGESTIONS){
      const idle=window.requestIdleCallback||(fn=>setTimeout(fn,1200));
      idle(()=>loadFeaturedRows(wrap));
    }
  }catch(e){
    document.getElementById('netflixStyleSliders').innerHTML=`<div style="padding:40px;text-align:center;color:var(--text-muted)"><p>خطأ في الاتصال</p><button onclick="loadAndBuildNetflixHome()" style="margin-top:16px;padding:10px 24px;background:var(--red);color:#fff;border:none;border-radius:99px;cursor:pointer;font-family:inherit">إعادة المحاولة</button></div>`;
  }
}

function buildSliderRow(wrap,c,type,count,isSubRow){
  const rowId=c.id+'_'+type;
  const isVOD=(type==='series');
  const rowLabel=isSubRow?(isVOD?c.name+' — أفلام':c.name+' — قنوات'):c.name;
  const skelN=Math.min(8,Math.max(4,count));
  const row=document.createElement('div');
  row.className='netflix-slider-row';
  row.dataset.rowId=rowId;row.dataset.catId=c.id;row.dataset.type=type;row.dataset.loaded='0';
  row.innerHTML=`
    <div class="slider-header">
      <div class="slider-title">
        <div class="slider-title-icon">${isVOD?'🎬':'📡'}</div>
        ${esc(rowLabel)}
        <span class="slider-badge" id="badge-${rowId}">${count>0?count+(isVOD?' عمل':' قناة'):'...'}</span>
      </div>
    </div>
    <div class="slider-scroll-mask" onmouseenter="if(window.shsUpdateRowArrows) window.shsUpdateRowArrows(this.querySelector('.slider-cards-wrapper'))">
      <button class="shs-row-arrow shs-left shs-show" aria-label="السابق" onclick="window.shsScrollRow(this, -1)"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg></button>
      <div class="slider-cards-wrapper" id="slider-lane-${rowId}" onscroll="if(window.shsUpdateRowArrows) window.shsUpdateRowArrows(this)">
        ${Array(skelN).fill('<div class="skeleton" style="height:200px;border-radius:10px"></div>').join('')}
      </div>
      <button class="shs-row-arrow shs-right shs-show" aria-label="التالي" onclick="window.shsScrollRow(this, 1)"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg></button>
    </div>`;
  wrap.appendChild(row);
}

async function fetchAllRows(){
  const allRows=Array.from(document.querySelectorAll('.netflix-slider-row[data-loaded="0"]'));
  if(!allRows.length)return;
  // Keep the first paint responsive on phones and slow/data-saving connections.
  const connection=navigator.connection||navigator.mozConnection||navigator.webkitConnection;
  const constrained=!!(connection&&(connection.saveData||/^(slow-2g|2g|3g)$/.test(connection.effectiveType||'')));
  const INITIAL=constrained?1:(_UA.isMobile?2:3);
  const firstBatch=allRows.slice(0,INITIAL);
  const restRows=allRows.slice(INITIAL);
  await Promise.all(firstBatch.map(row=>fetchSingleRow(row)));
  if(restRows.length){
    // تتبع الـ observer لإمكانية قطع الاتصال
    const obs=new IntersectionObserver((entries,ob)=>{
      entries.forEach(entry=>{
        if(!entry.isIntersecting)return;
        if(entry.target.dataset.loaded!=='0')return;
        ob.unobserve(entry.target);
        fetchSingleRow(entry.target).then(()=>{
          // قطع الاتصال إذا تحمّلت كل الصفوف
          const remaining=document.querySelectorAll('.netflix-slider-row[data-loaded="0"]');
          if(!remaining.length)ob.disconnect();
        });
      });
    },{rootMargin:'400px 0px'});
    restRows.forEach(row=>obs.observe(row));
  }
}

async function fetchSingleRow(row){
  const rowId=row.dataset.rowId;
  const catId=row.dataset.catId;
  const type=row.dataset.type;
  const isVOD=(type==='series');
  const laneEl=document.getElementById('slider-lane-'+rowId);
  if(!laneEl)return;
  row.dataset.loaded='1';
  try{
    const action=isVOD?'series':'channels';
    const r=await fetch(`api.php?action=${action}&category_id=${encodeURIComponent(catId)}`);
    if(!r.ok)throw new Error('HTTP '+r.status);
    const payload=await r.json();
    const items=isVOD?(payload.series||[]):(payload.channels||[]);
    if(!items.length){row.remove();return;}
    items.forEach(k=>{ _shsAddContent(k, isVOD?'series':'channel'); });
    const badge=document.getElementById('badge-'+rowId);
    if(badge)badge.textContent=items.length+(isVOD?' عمل':' قناة');
    renderItemsIntoSliderDOM(laneEl,items,type);
  }catch(err){
    if(laneEl)laneEl.innerHTML='<div style="color:var(--text-muted);padding:16px;font-size:.85rem;direction:rtl">تعذر التحميل</div>';
    row.dataset.loaded='0';
  }
}

/* ════ FEATURED ROWS — الأكثر مشاهدة + مقترحات قد تعجبك ════ */
function buildFeaturedRow(wrap,rowId,label,icon){
  const row=document.createElement('div');
  row.className='netflix-slider-row';
  row.dataset.rowId=rowId;
  row.innerHTML=`
    <div class="slider-header">
      <div class="slider-title">
        <div class="slider-title-icon">${icon}</div>
        ${esc(label)}
        <span class="slider-badge" id="badge-${rowId}">...</span>
      </div>
    </div>
    <div class="slider-scroll-mask" onmouseenter="if(window.shsUpdateRowArrows) window.shsUpdateRowArrows(this.querySelector('.slider-cards-wrapper'))">
      <button class="shs-row-arrow shs-left shs-show" aria-label="السابق" onclick="window.shsScrollRow(this, -1)"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg></button>
      <div class="slider-cards-wrapper" id="slider-lane-${rowId}" onscroll="if(window.shsUpdateRowArrows) window.shsUpdateRowArrows(this)">
        ${Array(6).fill('<div class="skeleton" style="height:200px;border-radius:10px"></div>').join('')}
      </div>
      <button class="shs-row-arrow shs-right shs-show" aria-label="التالي" onclick="window.shsScrollRow(this, 1)"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg></button>
    </div>`;
  wrap.prepend(row);
  return row;
}
function fillFeaturedRow(rowId,items,cardType){
  const row=document.querySelector(`.netflix-slider-row[data-row-id="${rowId}"]`);
  const laneEl=document.getElementById('slider-lane-'+rowId);
  if(!row||!laneEl)return;
  if(!items.length){row.remove();return;}
  const badge=document.getElementById('badge-'+rowId);
  if(badge)badge.textContent=items.length+(cardType==='series'?' عمل':' عنصر');
  renderItemsIntoSliderDOM(laneEl,items,cardType==='series'?'series':'channels');
}
function shuffleArr(arr){
  const a=arr.slice();
  for(let i=a.length-1;i>0;i--){const j=Math.floor(Math.random()*(i+1));[a[i],a[j]]=[a[j],a[i]];}
  return a;
}
async function loadFeaturedRows(wrap){
  if(!App.cats||!App.cats.length)return;
  try{
    /* ── تحسين أداء: لا نجلب كل المكتبة ──
       الصفوف المميزة تحتاج ٢٠ عنصراً مخلوطاً + ١٥ الأكثر مشاهدة فقط.
       كنا نجلب كل عنصر في كل قسم (عشرات الآلاف بعد استيراد Xtream) ثم نرمي ٩٩٪ منه.
       الآن: نتخطى الأقسام الفارغة، ونحدّ عدد الأقسام، وننفّذ على دفعات صغيرة. */
    const MAX_CATS = 8;      // أقصى عدد أقسام نقرأ منها للصفوف المميزة
    const BATCH    = 3;      // عدد الطلبات المتوازية في الدفعة الواحدة

    const picks = [];
    App.cats.forEach(c=>{
      const nCh = parseInt(c.channel_count||0);
      const nSr = parseInt(c.series_count ||0);
      if(nCh > 0) picks.push({cat:c, type:'channel', weight:nCh});
      if(nSr > 0) picks.push({cat:c, type:'series',  weight:nSr});
    });
    // نفضّل الأقسام الأغنى محتوى، ثم نقصّ القائمة
    picks.sort((a,b)=>b.weight-a.weight);
    const chosen = shuffleArr(picks.slice(0, MAX_CATS * 2)).slice(0, MAX_CATS);

    const results = [];
    for(let i=0; i<chosen.length; i+=BATCH){
      const slice = chosen.slice(i, i+BATCH);
      const batch = await Promise.all(slice.map(p=>{
        const act = p.type==='channel' ? 'channels' : 'series';
        return fetch(`api.php?action=${act}&category_id=${encodeURIComponent(p.cat.id)}`)
          .then(r=>r.json())
          .then(d=>({type:p.type, items:(p.type==='channel' ? (d.channels||[]) : (d.series||[]))}))
          .catch(()=>({type:p.type, items:[]}));
      }));
      results.push(...batch);
    }
    const allChannels=[],allSeries=[];
    const seenCh=new Set(),seenSr=new Set();
    results.forEach(res=>{
      res.items.forEach(item=>{
        if(res.type==='channel'){ if(!seenCh.has(item.id)){seenCh.add(item.id);allChannels.push(item);} }
        else{ if(!seenSr.has(item.id)){seenSr.add(item.id);allSeries.push(item);} }
      });
    });

    // ── مقترحات قد تعجبك: خليط عشوائي يتغيّر كل زيارة (يُبنى أولاً ليظهر أسفل الأكثر مشاهدة) ──
    if(!HIDE_SUGGESTIONS && (allChannels.length+allSeries.length)>0){
      const mixed=shuffleArr([
        ...allChannels.map(x=>({...x,_ftype:'channels'})),
        ...allSeries.map(x=>({...x,_ftype:'series'}))
      ]).slice(0,20);
      if(mixed.length){
        buildFeaturedRow(wrap,'suggestions','مقترحات قد تعجبك','✨');
        const laneEl=document.getElementById('slider-lane-suggestions');
        const badge=document.getElementById('badge-suggestions');
        if(badge)badge.textContent=mixed.length+' عنصر';
        if(laneEl){
          // كل عنصر بنوعه الصحيح (channels/series) حتى تُبنى البطاقة المناسبة له بترتيب الخلط نفسه
          const frag=document.createDocumentFragment();
          mixed.forEach((item,idx)=>{
            const tmp=document.createElement('div');
            renderItemsIntoSliderDOM(tmp,[item],item._ftype);
            const card=tmp.firstElementChild;
            if(card){card.style.animationDelay=(idx*.03)+'s';frag.appendChild(card);}
          });
          laneEl.innerHTML='';
          laneEl.appendChild(frag);
        }
      }
    }

    // ── الأكثر مشاهدة: صف قنوات + صف مسلسلات منفصلين، الأعلى views_count ──
    if(!HIDE_MOST_WATCHED){
      const topChannels=allChannels.slice().sort((a,b)=>parseInt(b.views_count||0)-parseInt(a.views_count||0)).slice(0,15);
      const topSeries=allSeries.slice().sort((a,b)=>parseInt(b.views_count||0)-parseInt(a.views_count||0)).slice(0,15);
      if(topSeries.length){
        buildFeaturedRow(wrap,'most_watched_series','الأكثر مشاهدة — مسلسلات وأفلام','🔥');
        fillFeaturedRow('most_watched_series',topSeries,'series');
      }
      if(topChannels.length){
        buildFeaturedRow(wrap,'most_watched_channels','الأكثر مشاهدة — قنوات','🔥');
        fillFeaturedRow('most_watched_channels',topChannels,'channels');
      }
    }
  }catch(e){}
}

/* ════ RENDER CARDS — DocumentFragment لأداء أفضل ════ */
/* أقصى عدد بطاقات تُبنى في الشريط الأفقي الواحد.
   بناء ٨٠٠٠ بطاقة لقسم أفلام كامل كان يخنق الصفحة، بينما لا يرى المستخدم سوى ١٠ منها.
   الباقي يُفتح عبر «عرض الكل» في صفحة القسم. */
const MAX_CARDS_PER_ROW = 40;

/* ════ عرض تدريجي (Progressive Rendering) ════
   المشكلة: صفحة القسم والبحث تمرران noCap=true، فتُبنى كل العناصر دفعة واحدة.
   قسم فيه ٥٠٠٠ عنصر = ٥٠٠٠ بطاقة × ~١٢ عنصر DOM = تجميد للتبويب عدة ثوانٍ.
   الحل: نبني أول دفعة فوراً، ثم نكمل الباقي عند التمرير (بلا حذف أي عنصر). */
const FIRST_PAINT_CARDS = 60;   // عدد البطاقات المبنية فوراً
const CHUNK_CARDS       = 60;   // حجم الدفعة التالية عند الوصول للنهاية

/* فهرس المفضلة كـ Set: البحث O(1) بدل .some() لكل بطاقة (O(n×m)).
   يُعاد بناؤه عند أي تعديل على المفضلة. */
let _favChSet = new Set(), _favSrSet = new Set();
function rebuildFavSets(){
  try{
    _favChSet = new Set((MyFavs.channels||[]).map(f=>String(f.id)));
    _favSrSet = new Set((MyFavs.series  ||[]).map(f=>String(f.id)));
  }catch(e){ _favChSet=new Set(); _favSrSet=new Set(); }
}
rebuildFavSets();

function renderItemsIntoSliderDOM(sliderDom,items,cardType,highlightStr='',noCap){
  if(cardType==='channels'&&items&&items.length){
    // إخفاء القنوات غير النشطة (إن أرسل الـ API حقل is_active)
    items=items.filter(it=>it.is_active===undefined||it.is_active===null||parseInt(it.is_active)!==0);
  }
  if(!items||!items.length){
    sliderDom.innerHTML='<div style="color:var(--text-muted);padding:16px;font-size:.82rem;grid-column:1/-1;text-align:center">لا يوجد محتوى</div>';
    return;
  }
  // قصّ العدد المبني في الأشرطة الأفقية فقط (صفحة القسم والبحث تمرران noCap)
  if(!noCap && items.length > MAX_CARDS_PER_ROW) items = items.slice(0, MAX_CARDS_PER_ROW);

  // فهرس المفضلة يُحدّث مرة واحدة لكل عملية رسم بدل بحث خطي لكل بطاقة
  rebuildFavSets();

  // بناء بطاقة واحدة (نفس منطق البناء السابق تماماً، بلا أي حذف)
  function _buildCard(item, idx){
    const div=document.createElement('div');
    div.style.animationDelay=(Math.min(idx,20)*.03)+'s';
    if(cardType==='series'){
      const isFav=_favSrSet.has(String(item.id));
      div.className='sr-card';
      // صورة البوستر
      const poster=document.createElement('div');
      poster.className='sr-poster';
      if(item.poster_url){
        const img=document.createElement('img');
        img.src=esc(item.poster_url);
        img.loading='lazy';
        img.decoding='async';
        img.alt=esc(item.name);
        img.onerror=function(){this.style.display='none';};
        poster.appendChild(img);
      }else{
        poster.innerHTML='<span style="font-size:1.8rem;color:#2e2e2e">🎬</span>';
      }
      poster.innerHTML+='<div class="ch-play-btn">▶</div>';
      // info
      const info=document.createElement('div');
      info.className='sr-info';
      const nameEl=document.createElement('div');
      nameEl.className='sr-name';
      nameEl.title=item.name;
      if(highlightStr){
        const terms = highlightStr.split(' ').filter(Boolean).map(w => w.replace(/[.*+?^${}()|[\\]\\\\]/g, '\\\\$&')).join('|');
        if(terms){
          nameEl.innerHTML=item.name.replace(new RegExp(`(${terms})`, 'gi'), '<mark style="background:var(--red);color:#fff;border-radius:2px;padding:0 2px">$1</mark>');
        }else{
          nameEl.textContent=item.name;
        }
      } else {
        nameEl.textContent=item.name;
      }
      const actions=document.createElement('div');
      actions.style.cssText='display:flex;align-items:center;gap:4px;flex-wrap:wrap';
      const btnInfo=document.createElement('button');
      btnInfo.className='info-action-btn';
      btnInfo.title='معلومات';
      btnInfo.textContent='ℹ';
      btnInfo.onclick=e=>{e.stopPropagation();showTmdbInfoClient(item.name,'tv');};
      const btnFav=document.createElement('button');
      btnFav.className='info-action-btn'+(isFav?' active-fav':'');
      btnFav.textContent='♥';
      btnFav.onclick=e=>{e.stopPropagation();toggleMyFav(item.id,item.name,'series',item.poster_url||'');};
      const badge=document.createElement('span');
      badge.style.cssText='font-size:.6rem;color:var(--text-muted);background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.1);padding:1px 5px;border-radius:3px';
      badge.textContent='VOD';
      actions.append(btnInfo,btnFav,badge);
      info.append(nameEl,actions);
      div.append(poster,info);
      div.dataset.prefetchSeries=item.id;
      div.addEventListener('click',()=>openSeriesEpisodes(item.id,item.name,item.poster_url||''));
    }else{
      const isFav=_favChSet.has(String(item.id));
      const isLive=isLiveFormat(item.stream_url||'');
      const fmt=fmtLabel(item.stream_url||'');
      div.className='ch-card';
      // thumb
      const thumb=document.createElement('div');
      thumb.className='ch-thumb';
      if(item.logo_url){
        const img=document.createElement('img');
        img.src=esc(item.logo_url);
        img.loading='lazy';
        img.decoding='async';
        img.alt=esc(item.name);
        img.onerror=function(){this.style.display='none';};
        thumb.appendChild(img);
      }else{
        thumb.innerHTML='<span style="font-size:1.8rem;color:#2e2e2e">📺</span>';
      }
      const liveBadge=document.createElement('span');
      liveBadge.className='ch-live-badge';
      liveBadge.textContent=isLive?'LIVE':fmt;
      const fmtBadge=document.createElement('span');
      fmtBadge.className='ch-fmt-badge';
      fmtBadge.textContent=fmt;
      thumb.innerHTML+='<div class="ch-play-btn">▶</div>';
      thumb.prepend(liveBadge);
      thumb.appendChild(fmtBadge);
      if(item.quality){
        const qualityBadge=document.createElement('span');
        qualityBadge.className='ch-quality-badge';
        qualityBadge.textContent=item.quality;
        thumb.appendChild(qualityBadge);
      }
      // info
      const info=document.createElement('div');
      info.className='ch-info';
      const nameEl=document.createElement('div');
      nameEl.className='ch-name';
      nameEl.title=item.name;
      if(highlightStr){
        const terms = highlightStr.split(' ').filter(Boolean).map(w => w.replace(/[.*+?^${}()|[\\]\\\\]/g, '\\\\$&')).join('|');
        if(terms){
          nameEl.innerHTML=item.name.replace(new RegExp(`(${terms})`, 'gi'), '<mark style="background:var(--red);color:#fff;border-radius:2px;padding:0 2px">$1</mark>');
        }else{
          nameEl.textContent=item.name;
        }
      } else {
        nameEl.textContent=item.name;
      }
      const actions=document.createElement('div');
      actions.style.cssText='display:flex;align-items:center;gap:4px;flex-wrap:wrap';
      const btnInfo=document.createElement('button');
      btnInfo.className='info-action-btn';
      btnInfo.title='معلومات';
      btnInfo.textContent='ℹ';
      btnInfo.onclick=e=>{e.stopPropagation();showTmdbInfoClient(item.name,'movie');};
      const btnFav=document.createElement('button');
      btnFav.className='info-action-btn'+(isFav?' active-fav':'');
      btnFav.textContent='♥';
      btnFav.onclick=e=>{e.stopPropagation();toggleMyFav(item.id,item.name,'channels',item.logo_url||'',item.stream_url||'',item.subtitle_url||'');};
      const badge=document.createElement('span');
      badge.style.cssText='font-size:.6rem;color:var(--text-muted);background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.1);padding:1px 5px;border-radius:3px';
      badge.textContent=isLive?'LIVE':fmt;
      actions.append(btnInfo,btnFav,badge);
      info.append(nameEl,actions);
      div.append(thumb,info);
      div.addEventListener('click',()=>openPlayerChannel(item));
    }
    return div;
  }

  sliderDom.innerHTML='';

  // ── الرسم على دفعات ──
  // الدفعة الأولى فوراً (المستخدم يرى محتوى خلال أجزاء من الثانية)،
  // والباقي يُضاف عند اقتراب التمرير من النهاية — بلا فقدان أي عنصر.
  let _rendered = 0;
  const _total = items.length;

  function _appendChunk(n){
    if(_rendered >= _total) return;
    const end = Math.min(_rendered + n, _total);
    const frag = document.createDocumentFragment();
    for(let i=_rendered; i<end; i++) frag.appendChild(_buildCard(items[i], i));
    sliderDom.appendChild(frag);
    _rendered = end;
    if(_rendered >= _total && sliderDom.__shsChunkCleanup) sliderDom.__shsChunkCleanup();
  }

  // تنظيف أي مراقب سابق على نفس الحاوية (عند إعادة الرسم)
  if(sliderDom.__shsChunkCleanup){ try{ sliderDom.__shsChunkCleanup(); }catch(e){} }

  _appendChunk(noCap ? FIRST_PAINT_CARDS : _total);

  if(_rendered < _total){
    // حارس في نهاية القائمة: عند ظهوره نبني الدفعة التالية
    const sentinel=document.createElement('div');
    sentinel.style.cssText='grid-column:1/-1;height:1px';
    sliderDom.appendChild(sentinel);
    const io=new IntersectionObserver(entries=>{
      entries.forEach(en=>{
        if(!en.isIntersecting) return;
        _appendChunk(CHUNK_CARDS);
        // نُبقي الحارس آخر عنصر دائماً
        if(_rendered < _total) sliderDom.appendChild(sentinel);
        else sentinel.remove();
      });
    },{rootMargin:'600px'});
    io.observe(sentinel);
    sliderDom.__shsChunkCleanup=function(){
      try{ io.disconnect(); }catch(e){}
      try{ sentinel.remove(); }catch(e){}
      sliderDom.__shsChunkCleanup=null;
    };
  }
}

/* ════ EPISODES ════ */
async function openSeriesEpisodes(seriesId,seriesName,seriesPoster){
  App.currentSeriesId=seriesId;App.currentSeriesName=seriesName;
  shsSetHash({s:seriesId});                       // يبقى بعد التحديث
  /* [SHS-EPPOSTER] تحديد بوستر المسلسل: من الوسيط، وإلا نبحث عنه في المحتوى المخزّن */
  App.currentSeriesPoster=seriesPoster||'';
  if(!App.currentSeriesPoster){
    try{
      var _f=(App.allContent||[]).find(function(x){return String(x.id)===String(seriesId)&&(x.poster_url||x._ftype==='series'||x.ftype==='series');});
      if(_f&&_f.poster_url)App.currentSeriesPoster=_f.poster_url;
    }catch(e){}
  }
  document.getElementById('netflixStyleSliders').classList.add('hidden');
  document.getElementById('heroWelcome').classList.add('hidden');
  document.getElementById('searchViewSection').classList.add('hidden');
  document.getElementById('categoryViewSection').classList.add('hidden');
  document.getElementById('epSection').classList.remove('hidden');
  document.getElementById('epSectionTitle').textContent=seriesName;
  const grid=document.getElementById('epGrid');
  const loading=document.getElementById('epLoading');
  const empty=document.getElementById('epEmpty');
  grid.innerHTML='';loading.classList.remove('hidden');empty.classList.add('hidden');
  window.scrollTo({top:0,behavior:'smooth'});
  try{
    const r=await fetch(`api.php?action=episodes&series_id=${encodeURIComponent(seriesId)}`);
    const d=await r.json();App.allEpisodes=d.episodes||[];
    /* [SHS-EPPOSTER] لو أرجع الـ API بوستر المسلسل، نستخدمه احتياطياً */
    if(!App.currentSeriesPoster){
      var sp=d.series_poster||d.poster_url||(d.series&&d.series.poster_url)||'';
      if(sp)App.currentSeriesPoster=sp;
    }
    loading.classList.add('hidden');
    if(!App.allEpisodes.length){empty.classList.remove('hidden');}else renderEpisodes(App.allEpisodes);
    fetch('api.php?action=increment_view&id='+seriesId+'&type=series').catch(()=>{});
  }catch(e){loading.classList.add('hidden');grid.innerHTML='<div style="color:var(--red);padding:20px">تعذر تحميل الحلقات</div>';}
}
function renderEpisodes(eps){
  const g=document.getElementById('epGrid');g.innerHTML='';
  eps.forEach((ep,i)=>{
    const dv=document.createElement('div');dv.className='ep-card';dv.style.animationDelay=(i*.05)+'s';
    /* [SHS-EPPOSTER] الأولوية لصورة الحلقة، وإلا بوستر المسلسل الأصلي بدل الخلفية الفارغة */
    const epImg=ep.image_url||ep.thumbnail_url||ep.cover_url||ep.poster_url||'';
    const fallback=App.currentSeriesPoster||'';
    const finalImg=epImg||fallback;
    const usingFallback=(!epImg&&!!fallback);
    const imgH=finalImg?`<img class="ep-thumb-video${usingFallback?' ep-thumb-fallback':''}" src="${esc(finalImg)}" loading="lazy" onerror="this.style.display='none'">`:'';
    const title=ep.title||('حلقة '+ep.episode_number);
    dv.innerHTML=`<div class="ep-thumb-area">${imgH}<span class="ep-thumb-icon">▶</span><div class="ep-num-badge">حلقة ${esc(ep.episode_number)}</div></div>
      <div class="ep-info-box">
        <div style="color:#f0f0f0;font-weight:700;font-size:clamp(0.7rem,2.2vw,0.88rem);line-height:1.4;height:2.8em;margin-bottom:5px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;text-overflow:ellipsis" title="${esc(title)}">${esc(title)}</div>
        <div class="ep-date-text">📅 ${ep.added||ep.date||'مشاهدة'}</div>
      </div>`;
    dv.addEventListener('click',()=>openPlayerEpisode(i));
    g.appendChild(dv);
  });
}
function backFromEpisodesToHome(){
  document.getElementById('epSection').classList.add('hidden');
  /* غادرنا صفحة الحلقات — نُنظّف معرّف العمل حتى لا يعيد التحديث فتحه */
  App.currentSeriesId=null; App.currentSeriesName=''; App.currentSeriesPoster='';
  const searchQ = document.getElementById('searchInput') ? document.getElementById('searchInput').value.trim() : '';
  if(searchQ.length > 0){
    document.getElementById('searchViewSection').classList.remove('hidden');
    try{ shsSetHash({q:searchQ.toLowerCase()}); }catch(e){}
  }else if(App.currentCategoryView){
    document.getElementById('categoryViewSection').classList.remove('hidden');
    try{ shsSetHash({c:App.currentCategoryView.id}); }catch(e){}
  }else{
    document.getElementById('netflixStyleSliders').classList.remove('hidden');
    document.getElementById('heroWelcome').classList.remove('hidden');
    try{ shsSetHash(null); }catch(e){}   // رجوع للرئيسية → عنوان نظيف
  }
  window.scrollTo({top:0,behavior:'smooth'});
}

/* ════ SEARCH ════ */
let searchTimer;
function handleSearch(){
  clearTimeout(searchTimer);
  searchTimer=setTimeout(async ()=>{
    const q=document.getElementById('searchInput').value.trim().toLowerCase();
    if(q.length<1){clearSearchAndGoHome();return;}
    document.getElementById('netflixStyleSliders').classList.add('hidden');
    document.getElementById('epSection').classList.add('hidden');
    document.getElementById('heroWelcome').classList.add('hidden');
    document.getElementById('categoryViewSection').classList.add('hidden');
    document.getElementById('searchViewSection').classList.remove('hidden');
    const grid=document.getElementById('searchGrid');
    const empty=document.getElementById('searchEmpty');
    const badge=document.getElementById('searchCountBadge');
    /* ── لا نحجب البحث بانتظار المكتبة كاملة ──
       سابقاً: await _shsEnsureAllContent() كان يجلب عشرات آلاف الصفوف قبل إظهار أي نتيجة.
       الآن: نبحث فوراً فيما هو محمّل، ونُكمل التحميل في الخلفية ثم نعيد البحث تلقائياً. */
    badge.textContent='جاري البحث...';
    if(!_shsAllContentLoaded){
      const qAtStart = document.getElementById('searchInput').value.trim().toLowerCase();
      _shsEnsureAllContent().then(()=>{
        // أعِد العرض فقط إن كان المستخدم ما زال يبحث بنفس الكلمة
        const still = document.getElementById('searchInput').value.trim().toLowerCase();
        if(still && still === qAtStart) _shsRenderSearch(still);
      });
    }
    const qNow=document.getElementById('searchInput').value.trim().toLowerCase();
    if(qNow.length<1){clearSearchAndGoHome();return;}
    shsSetHash({q:qNow});                          // البحث يبقى بعد التحديث
    _shsRenderSearch(qNow);
  },220);
}

/* ── تنفيذ البحث والعرض (مفصول ليُعاد استدعاؤه بعد اكتمال التحميل الخلفي) ── */
const MAX_SEARCH_RESULTS = 120;   // نبني ١٢٠ بطاقة كحد أقصى بدل آلاف

function _shsRenderSearch(qNow){
  const grid  = document.getElementById('searchGrid');
  const empty = document.getElementById('searchEmpty');
  const badge = document.getElementById('searchCountBadge');
  if(!grid || !empty || !badge) return;

  const nq    = _shsNormalizeSearch(qNow);
  const words = nq.split(' ').filter(Boolean);
  const scored= [];

  for(let i=0;i<App.allContent.length;i++){
    const v=App.allContent[i];
    /* الاسم المطبّع يُحسب مرة واحدة ويُخزّن على العنصر.
       سابقاً كان _shsNormalizeSearch يعمل على كل عنصر في كل ضغطة مفتاح
       (٣٠ ألف عنصر × ٦ تعبيرات نمطية × حقلين ≈ ٣٦٠ ألف عملية لكل حرف). */
    if(v._nn===undefined) v._nn=_shsNormalizeSearch(v.name||'');
    if(v._nq===undefined) v._nq=_shsNormalizeSearch(v.quality||'');
    const nameN=v._nn, qualN=v._nq;

    let allFound=true;
    for(let w=0;w<words.length;w++){
      if(nameN.indexOf(words[w])===-1 && qualN.indexOf(words[w])===-1){allFound=false;break;}
    }
    if(!allFound)continue;

    let score=1;
    if(nameN===nq)score=4;
    else if(nameN.indexOf(nq)===0)score=3;
    else if(nameN.indexOf(nq)>-1)score=2;
    scored.push({v:v,score:score});
  }

  scored.sort((a,b)=>b.score-a.score || (a.v.name||'').localeCompare(b.v.name||'','ar'));

  const total   = scored.length;
  const shown   = scored.slice(0, MAX_SEARCH_RESULTS).map(x=>x.v);
  const loading = !_shsAllContentLoaded;

  badge.textContent = total
    ? (total > MAX_SEARCH_RESULTS
        ? ('أفضل '+MAX_SEARCH_RESULTS+' من '+total+' نتيجة' + (loading?' — جارٍ البحث في الباقي...':''))
        : (total+' نتيجة' + (loading?' — جارٍ البحث في الباقي...':'')))
    : (loading ? 'جارٍ البحث...' : '0 نتيجة');

  if(shown.length){
    empty.classList.add('hidden'); grid.classList.remove('hidden');
    const channels=shown.filter(x=>x.globalType==='channel');
    const series  =shown.filter(x=>x.globalType==='series');
    grid.innerHTML='';
    if(channels.length){
      const chHeader = document.createElement('h3');
      chHeader.style.cssText = 'grid-column:1/-1;margin:15px 0 10px 0;color:var(--text-muted);font-size:1.15rem;display:flex;align-items:center;gap:8px;';
      chHeader.innerHTML = '<span class="lcn" style="color:var(--red)">▶</span> القنوات والأفلام <span style="font-size:0.8rem;background:rgba(255,255,255,0.1);padding:2px 8px;border-radius:12px;color:#fff;">'+channels.length+'</span>';
      grid.appendChild(chHeader);
      renderItemsIntoSliderDOM(grid,channels,'channels', qNow, true);
    }
    if(series.length){
      const srHeader = document.createElement('h3');
      srHeader.style.cssText = 'grid-column:1/-1;margin:15px 0 10px 0;color:var(--text-muted);font-size:1.15rem;display:flex;align-items:center;gap:8px;';
      srHeader.innerHTML = '<span class="lcn" style="color:var(--red)">🎬</span> المسلسلات <span style="font-size:0.8rem;background:rgba(255,255,255,0.1);padding:2px 8px;border-radius:12px;color:#fff;">'+series.length+'</span>';
      grid.appendChild(srHeader);
      renderItemsIntoSliderDOM(grid,series,'series', qNow, true);
    }
  } else if(!loading){
    grid.classList.add('hidden');
    empty.classList.remove('hidden');
    empty.innerHTML = `<span class="lcn" style="font-size:3rem;margin-bottom:16px;display:block;opacity:.3"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg></span><p>لم نجد أي نتائج لـ "<b style="color:var(--text)">${qNow}</b>"</p><p style="font-size:0.85rem;margin-top:8px;opacity:0.7">جرب كلمات أخرى أو تأكد من الإملاء</p>`;
  }
}
/* فهرس مفاتيح مشترك لمنع التكرار في O(1) بدل O(n) لكل عنصر */
const _shsKeys = new Set();
/* ⚠️ جسر ضروري (نفس فكرة window.PL في الأسفل):
   المتغيرات المعرّفة بـ const/let لا تُنشئ خاصية على window إطلاقاً —
   خلافاً لـ var و function. وقد كانت دالة refreshCurrentScreen تفحص
   `if(window._shsKeys)` و `if(window.App)` وكلاهما undefined دائماً،
   فلم يكن السطر التالي يُنفَّذ أبداً:
        _shsKeys.clear(); App.allContent=[];
   نتيجتان خطيرتان كانتا تحدثان بصمت:
     ① الفهرس _shsKeys ينمو بلا حد ولا يُفرَّغ → استهلاك ذاكرة متصاعد
        كلما حُدِّث المحتوى (ثِقل وبطء تدريجي في المتصفح).
     ② الأخطر: بعد أي تحديث للمحتوى تبقى المفاتيح القديمة في الفهرس،
        فتُرفض العناصر الجديدة باعتبارها "مكرّرة" ⇒ القنوات المستوردة
        حديثاً لا تظهر إلا بعد إعادة تحميل الصفحة كاملة. */
try{ window._shsKeys = _shsKeys; }catch(e){}
function _shsAddContent(item, type){
  const key = type + '_' + item.id;
  if(_shsKeys.has(key)) return false;
  _shsKeys.add(key);
  App.allContent.push({...item, globalType:type, _key:key});
  return true;
}

/* جلب كل المحتوى من كل الأقسام مرة واحدة (للبحث الشامل) — يُخزّن بعد أول جلب */
let _shsAllContentLoaded=false;
async function _shsEnsureAllContent(){
  if(_shsAllContentLoaded)return;
  if(!App.cats||!App.cats.length)return; // لا أقسام بعد
  try{
    /* دفعات محدودة بدل إطلاق مئات الطلبات دفعة واحدة (كان يخنق المتصفح بعد استيراد Xtream).
       يعمل الآن في الخلفية بلا حجب البحث، فنرفع التوازي قليلاً. */
    const BATCH = 6;
    const jobs = [];
    App.cats.forEach(c=>{
      if(parseInt(c.channel_count||0) > 0) jobs.push({id:c.id, type:'channel', act:'channels'});
      if(parseInt(c.series_count ||0) > 0) jobs.push({id:c.id, type:'series',  act:'series'});
    });

    /* الفهرس المشترك _shsKeys يمنع التكرار في O(1).
       سابقاً كان App.allContent.find() داخل الحلقة → O(n²) يجمّد التبويب مع عشرات الآلاف. */
    for(let i=0; i<jobs.length; i+=BATCH){
      const slice = jobs.slice(i, i+BATCH);
      const res = await Promise.all(slice.map(j=>
        fetch(`api.php?action=${j.act}&category_id=${encodeURIComponent(j.id)}`)
          .then(r=>r.json())
          .then(d=>({type:j.type, items:(j.type==='series' ? (d.series||[]) : (d.channels||[]))}))
          .catch(()=>({type:j.type, items:[]}))
      ));
      res.forEach(r=>{ r.items.forEach(k=>_shsAddContent(k, r.type)); });
      // نترك المتصفح يتنفّس بين الدفعات فلا تتجمّد الواجهة
      await new Promise(r=>setTimeout(r,0));
    }
    _shsAllContentLoaded=true;
  }catch(e){ /* عند الفشل نكمل بالمحتوى المتاح */ }
}
/* تطبيع نص البحث العربي: توحيد الهمزات/الألف/التاء المربوطة + إزالة التشكيل */
function _shsNormalizeSearch(s){
  return (s||'').toString().toLowerCase()
    .replace(/[\u0623\u0625\u0622\u0627]/g,'ا')
    .replace(/\u0629/g,'ه')
    .replace(/\u0649/g,'ي')
    .replace(/[\u064B-\u065F\u0670]/g,'')
    .replace(/\u0640/g,'')
    .replace(/\s+/g,' ').trim();
}

/* ══════════════════════════════════════════════════════════════
   موجّه العناوين (Router) — يحفظ مكانك في رابط الصفحة
   المشكلة: التطبيق كان صفحة واحدة بلا عنوان لكل حالة، فأي تحديث (Refresh)
   يعيدك للرئيسية: تخرج من المسلسل، ومن البحث، ومن الفيديو الشغّال.
   الحل: نكتب الحالة في hash العنوان، ونستعيدها عند التحميل.
   أمثلة:  #s=12         (مسلسل/فيلم رقم ١٢)
           #s=12&e=3     (نفس العمل + الحلقة الثالثة تعمل)
           #c=5          (قسم رقم ٥)
           #q=باتمان     (نتيجة بحث)
           #ch=88        (قناة تعمل)
   ══════════════════════════════════════════════════════════════ */
let _shsRouting = false;          // نمنع الحلقة: تغييرنا للـhash يجب ألا يُطلق الاستعادة
let _shsRestoring = false;        // نمنع الكتابة أثناء الاستعادة
/* عنوان مؤجّل: يُكتب بعد pushState الخاص بالمشغّل حتى يقع على إدخالة المشغّل
   لا على إدخالة الشاشة التي جئنا منها. */
let _pendingHash = null;

function shsSetHash(obj){
  if(_shsRestoring) return;       // لا نكتب ونحن نستعيد
  const parts = [];
  if(obj){
    if(obj.q)  parts.push('q='  + encodeURIComponent(obj.q));
    if(obj.c)  parts.push('c='  + encodeURIComponent(obj.c));
    if(obj.s)  parts.push('s='  + encodeURIComponent(obj.s));
    if(obj.e !== undefined && obj.e !== null && obj.e !== '') parts.push('e=' + encodeURIComponent(obj.e));
    if(obj.ch) parts.push('ch=' + encodeURIComponent(obj.ch));
  }
  const h = parts.length ? ('#' + parts.join('&')) : '';
  const cur = window.location.hash || '';
  if(cur === h) return;
  _shsRouting = true;
  try{
    // replaceState لا يضيف إدخالة جديدة — نترك إدارة الرجوع للمنطق الأصلي
    history.replaceState(history.state, '', window.location.pathname + window.location.search + h);
  }catch(e){ try{ window.location.hash = h; }catch(_){} }
  setTimeout(()=>{ _shsRouting = false; }, 0);
}

function shsGetHash(){
  const h = (window.location.hash || '').replace(/^#/, '');
  if(!h) return {};
  const o = {};
  h.split('&').forEach(kv=>{
    const i = kv.indexOf('=');
    if(i < 0) return;
    const k = kv.slice(0, i), v = decodeURIComponent(kv.slice(i + 1));
    if(v) o[k] = v;
  });
  return o;
}

/* استعادة الحالة بعد تحميل الصفحة */
async function shsRestoreFromHash(){
  const st = shsGetHash();
  if(!st.q && !st.c && !st.s && !st.ch) return false;
  _shsRestoring = true;
  try{
    /* ١) بحث */
    if(st.q){
      const inp = document.getElementById('searchInput');
      if(inp){
        inp.value = st.q;
        _shsRestoring = false;      // نسمح للبحث بكتابة حالته
        handleSearch();
        return true;
      }
    }

    /* ٢) قناة تعمل */
    if(st.ch){
      const chId = String(st.ch);
      let ch = (App.allContent || []).find(x => x.globalType === 'channel' && String(x.id) === chId);

      /* لا نحمّل المكتبة كاملة من أجل قناة واحدة (كان يستغرق ثوانٍ بعد استيراد Xtream).
         أسرع طريق: استرجاع ما حُفظ في الجلسة عند فتح المشغّل. */
      if(!ch){
        try{
          const saved = JSON.parse(sessionStorage.getItem('shs_restore') || 'null');
          if(saved && saved.type === 'channel' && saved.ch && String(saved.ch.id) === chId) ch = saved.ch;
        }catch(e){}
      }
      /* وإن لم يوجد، نسأل الـAPI عن هذه القناة تحديداً */
      if(!ch){
        try{
          const r = await fetch(`api.php?action=channels&id=${encodeURIComponent(chId)}`);
          const d = await r.json();
          const one = (d.channels && (Array.isArray(d.channels) ? d.channels[0] : d.channels)) || d.data || null;
          if(one && String(one.id) === chId) ch = one;
        }catch(e){}
      }
      /* الملاذ الأخير فقط: تحميل المكتبة */
      if(!ch){
        await _shsEnsureAllContent();
        ch = (App.allContent || []).find(x => x.globalType === 'channel' && String(x.id) === chId);
      }

      _shsRestoring = false;
      if(ch){ openPlayerChannel(ch); return true; }
      return false;
    }

    /* ٣) مسلسل/فيلم — مع حلقة اختيارية */
    if(st.s){
      const sid = st.s;
      let name = '', poster = '';
      let hit = (App.allContent || []).find(x => x.globalType === 'series' && String(x.id) === String(sid));

      /* عند التحديث تكون App.allContent شبه فارغة، فلا نجد الاسم ويظهر العنوان فارغاً.
         نجلب بيانات العمل مباشرة من الـAPI بدل تحميل المكتبة كاملة. */
      if(!hit){
        try{
          const r = await fetch(`api.php?action=series&id=${encodeURIComponent(sid)}`);
          const d = await r.json();
          const one = (d.series && (Array.isArray(d.series) ? d.series[0] : d.series)) || d.data || null;
          if(one){ name = one.name || ''; poster = one.poster_url || ''; }
        }catch(e){}
      } else {
        name = hit.name || ''; poster = hit.poster_url || '';
      }

      _shsRestoring = false;
      await openSeriesEpisodes(sid, name, poster);   // ينتظر جلب الحلقات فعلياً

      /* تشغيل الحلقة المطلوبة — بلا setTimeout عشوائي.
         openSeriesEpisodes انتهى بالفعل، فـ App.allEpisodes جاهزة الآن. */
      if(st.e !== undefined && st.e !== ''){
        const idx = parseInt(st.e);
        if(!isNaN(idx) && App.allEpisodes && App.allEpisodes[idx]){
          openPlayerEpisode(idx);
        }
      }
      return true;
    }

    /* ٤) قسم */
    if(st.c){
      const cid = st.c;
      const cat = (App.cats || []).find(x => String(x.id) === String(cid));
      _shsRestoring = false;
      await openCategoryView(cid, cat ? cat.name : '');
      return true;
    }
  }catch(e){
    console.error('restore error:', e);
  }finally{
    _shsRestoring = false;
  }
  return false;
}

function clearSearchAndGoHome(){
  shsSetHash(null);                               // تنظيف العنوان عند العودة للرئيسية
  document.getElementById('searchInput').value='';
  document.getElementById('searchViewSection').classList.add('hidden');
  document.getElementById('searchEmpty').classList.add('hidden');
  document.getElementById('netflixStyleSliders').classList.remove('hidden');
  document.getElementById('heroWelcome').classList.remove('hidden');
  setActiveCatNavBtn(null);
}

/* ════ CATEGORY QUICK NAV — شريط اختصارات الأقسام ════ */
function renderCategoryNavBar(){
  const bar=document.getElementById('catNavbar');
  if(!bar)return;
  bar.innerHTML='';
  if(!App.cats||!App.cats.length){bar.style.display='none';return;}
  bar.style.display='flex';
  const homeBtn=document.createElement('button');
  homeBtn.type='button';
  homeBtn.className='cat-nav-btn active';
  homeBtn.dataset.catId='';
  homeBtn.innerHTML='<span class="lcn"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></span> الرئيسية';
  homeBtn.addEventListener('click',()=>{
    closeCategoryView();
    if(!document.getElementById('searchViewSection').classList.contains('hidden')) clearSearchAndGoHome();
  });
  bar.appendChild(homeBtn);
  App.cats.forEach(c=>{
    const btn=document.createElement('button');
    btn.type='button';
    btn.className='cat-nav-btn';
    btn.dataset.catId=c.id;
    btn.textContent=c.name;
    btn.addEventListener('click',()=>openCategoryView(c.id,c.name));
    bar.appendChild(btn);
  });
  syncCatNavbarOffset();
  try{ shsRenderCatMenu(); }catch(e){}
}
/* [SHS-CATMENU-JS-START] قائمة الأقسام العمودية المنسدلة (إضافة فقط) */

/* [SHS-CATICON] نظام أيقونات احترافي يختار الأيقونة حسب اسم القسم */
var SHS_ICONS={
  tv:'<path d="M7 21h10"/><rect width="20" height="14" x="2" y="3" rx="2"/><path d="m17 7-5 4-5-4"/>',
  live:'<path d="M4.9 19.1C1 15.2 1 8.8 4.9 4.9"/><path d="M7.8 16.2c-2.3-2.3-2.3-6.1 0-8.5"/><circle cx="12" cy="12" r="2"/><path d="M16.2 7.8c2.3 2.3 2.3 6.1 0 8.5"/><path d="M19.1 4.9C23 8.8 23 15.1 19.1 19"/>',
  movie:'<rect width="18" height="18" x="3" y="3" rx="2"/><path d="M7 3v18M17 3v18M3 7.5h4M17 7.5h4M3 12h18M3 16.5h4M17 16.5h4"/>',
  series:'<rect width="20" height="15" x="2" y="7" rx="2"/><path d="m17 2-5 5-5-5"/>',
  sports:'<circle cx="12" cy="12" r="10"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M5.6 5.6l2.1 2.1M16.3 16.3l2.1 2.1M18.4 5.6l-2.1 2.1M7.7 16.3l-2.1 2.1"/><circle cx="12" cy="12" r="3"/>',
  kids:'<circle cx="12" cy="12" r="10"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/><line x1="9" x2="9.01" y1="9" y2="9"/><line x1="15" x2="15.01" y1="9" y2="9"/>',
  news:'<path d="M4 22h14a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2H8a2 2 0 0 0-2 2v16a2 2 0 0 1-2 2Zm0 0a2 2 0 0 1-2-2v-9a1 1 0 0 1 1-1h1"/><path d="M16 6h-6v4h6zM10 14h6M10 18h6"/>',
  doc:'<path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><path d="M14 2v6h6M16 13H8M16 17H8M10 9H8"/>',
  music:'<path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/>',
  radio:'<path d="M4.9 19.1C1 15.2 1 8.8 4.9 4.9"/><path d="M7.8 16.2c-2.3-2.3-2.3-6.1 0-8.5"/><circle cx="12" cy="12" r="2"/><path d="M16.2 7.8c2.3 2.3 2.3 6.1 0 8.5"/><path d="M19.1 4.9C23 8.8 23 15.1 19.1 19"/>',
  quiz:'<circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" x2="12.01" y1="17" y2="17"/>',
  talk:'<path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/><path d="M8 12h.01M12 12h.01M16 12h.01"/>',
  theater:'<path d="M2 10s3-3 3-8M22 10s-3-3-3-8M10 2c0 4.4-3.6 8-8 8M14 2c0 4.4 3.6 8 8 8"/><path d="M2 10a10 10 0 0 0 20 0M12 14v.01"/><path d="M8 17a4 4 0 0 0 8 0"/>',
  person:'<circle cx="12" cy="8" r="4"/><path d="M6 21v-2a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v2"/>',
  fight:'<path d="M6.5 6.5 17.5 17.5M21 21l-3-3M3 3l3 3M18 6l3-3M6 18l-3 3"/><rect width="4" height="4" x="4" y="4" rx="1"/><rect width="4" height="4" x="16" y="16" rx="1"/>',
  grid:'<rect width="7" height="7" x="3" y="3" rx="1.5"/><rect width="7" height="7" x="14" y="3" rx="1.5"/><rect width="7" height="7" x="14" y="14" rx="1.5"/><rect width="7" height="7" x="3" y="14" rx="1.5"/>'
};
function shsPickIconKey(name){
  var n=(name||'').toString();
  var has=function(){for(var i=0;i<arguments.length;i++){if(n.indexOf(arguments[i])!==-1)return true;}return false;};
  if(has('تلفزيون','تلفاز','قنوات','فضائي','بث المباشر','مباشر'))return 'tv';
  if(has('بث'))return 'live';
  if(has('افلام','أفلام','فلم','سينما','movie'))return 'movie';
  if(has('مسلسل','مسلسلات','دراما','series'))return 'series';
  if(has('رياض','كرة','مباريات','دوري','sport'))return 'sports';
  if(has('اطفال','أطفال','كرتون','انمي','أنمي','kids'))return 'kids';
  if(has('اخبار','أخبار','news'))return 'news';
  if(has('وثائق','وثائقي','doc'))return 'doc';
  if(has('موسيق','اغاني','أغاني','طرب','music'))return 'music';
  if(has('راديو','اذاعة','إذاعة','radio'))return 'radio';
  if(has('مسابق','مسابقات','تحدي'))return 'quiz';
  if(has('حوار','برامج حوارية','توك','بودكاست','podcast'))return 'talk';
  if(has('مسرح','مسرحيات','مسرحية'))return 'theater';
  if(has('عروض','مصارع','مصارعة'))return 'fight';
  if(has('سيرة','شخصية','بايوغراف'))return 'person';
  if(has('ترفيه','منوع','منوعات'))return 'grid';
  return 'grid';
}
function shsCatIconSVG(name,size){
  var body=SHS_ICONS[shsPickIconKey(name)]||SHS_ICONS.grid;
  var s=size||'1em';
  return '<svg xmlns="http://www.w3.org/2000/svg" width="'+s+'" height="'+s+'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">'+body+'</svg>';
}
function shsRenderCatMenu(){
  var list=document.getElementById('shsCatMenuList');
  if(!list)return;
  list.innerHTML='';
  if(!App.cats||!App.cats.length){
    list.innerHTML='<div class="shs-catmenu-empty">لا توجد أقسام متاحة</div>';
    return;
  }
  App.cats.forEach(function(c,i){
    var b=document.createElement('button');
    b.type='button';
    b.className='shs-catmenu-item';
    b.dataset.catId=c.id;
    var idx=String(i+1).padStart(2,'0');
    var nm=(c.name||'').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    b.innerHTML=
      '<span class="shs-catmenu-ico">'+shsCatIconSVG(c.name)+'</span>'+
      '<span class="shs-catmenu-name">'+nm+'</span>'+
      '<span class="shs-catmenu-arrow"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg></span>';
    b.addEventListener('click',function(){
      shsCloseCatMenu();
      try{ openCategoryView(c.id,c.name); }catch(e){}
    });
    list.appendChild(b);
  });
  var cnt=document.getElementById('shsCatMenuCount');
  if(cnt)cnt.textContent=App.cats.length+' قسم متاح';
}
function shsOpenCatMenu(){
  try{ shsRenderCatMenu(); }catch(e){}
  var ov=document.getElementById('shsCatMenuOverlay');
  var pn=document.getElementById('shsCatMenuPanel');
  if(ov)ov.classList.add('open');
  if(pn){pn.classList.add('open');pn.setAttribute('aria-hidden','false');}
  document.body.style.overflow='hidden';
}
function shsCloseCatMenu(){
  var ov=document.getElementById('shsCatMenuOverlay');
  var pn=document.getElementById('shsCatMenuPanel');
  if(ov)ov.classList.remove('open');
  if(pn){pn.classList.remove('open');pn.setAttribute('aria-hidden','true');}
  document.body.style.overflow='';
}
function shsCatMenuGoHome(){
  shsCloseCatMenu();
  try{ closeCategoryView(); }catch(e){}
  try{
    if(!document.getElementById('searchViewSection').classList.contains('hidden')) clearSearchAndGoHome();
  }catch(e){}
}
document.addEventListener('keydown',function(e){ if(e.key==='Escape') shsCloseCatMenu(); });
/* [SHS-CATMENU-JS-END] */
function setActiveCatNavBtn(catId){
  document.querySelectorAll('.cat-nav-btn').forEach(b=>{
    b.classList.toggle('active', String(b.dataset.catId)===String(catId??''));
  });
  /* [SHS-CATMENU-ACTIVE] مزامنة التمييز مع القائمة العمودية (إضافة فقط) */
  try{
    document.querySelectorAll('#shsCatMenuList .shs-catmenu-item').forEach(function(b){
      b.classList.toggle('active', String(b.dataset.catId)===String(catId??''));
    });
  }catch(e){}
}
async function openCategoryView(catId,catName){
  App.currentCategoryView={id:catId,name:catName};
  shsSetHash({c:catId});                          // يبقى بعد التحديث
  setActiveCatNavBtn(catId);
  document.getElementById('netflixStyleSliders').classList.add('hidden');
  document.getElementById('heroWelcome').classList.add('hidden');
  document.getElementById('searchViewSection').classList.add('hidden');
  document.getElementById('epSection').classList.add('hidden');
  document.getElementById('categoryViewSection').classList.remove('hidden');
  document.getElementById('categoryViewTitle').textContent=catName||'القسم';
  const grid=document.getElementById('categoryViewGrid');
  const loading=document.getElementById('categoryViewLoading');
  const empty=document.getElementById('categoryViewEmpty');
  const badge=document.getElementById('categoryViewCountBadge');
  grid.innerHTML='';grid.classList.remove('hidden');
  empty.classList.add('hidden');loading.classList.remove('hidden');
  window.scrollTo({top:0,behavior:'smooth'});
  try{
    // إلغاء طلب القسم السابق إن كان المستخدم ينتقل بسرعة بين الأقسام
    if(window.__shsCatAbort){ try{ window.__shsCatAbort.abort(); }catch(e){} }
    const _ac = ('AbortController' in window) ? new AbortController() : null;
    window.__shsCatAbort = _ac;
    const _sig = _ac ? {signal:_ac.signal} : {};
    const [chRes,srRes]=await Promise.all([
      fetch(`api.php?action=channels&category_id=${encodeURIComponent(catId)}`,_sig),
      fetch(`api.php?action=series&category_id=${encodeURIComponent(catId)}`,_sig)
    ]);
    const chData=await chRes.json();
    const srData=await srRes.json();
    const channels=chData.channels||[];
    const series=srData.series||[];
    loading.classList.add('hidden');
    const total=channels.length+series.length;
    badge.textContent=total+' عنصر';
    if(!total){
      grid.classList.add('hidden');empty.classList.remove('hidden');
    }else{
      grid.innerHTML='';
      if(channels.length)renderItemsIntoSliderDOM(grid,channels,'channels','',true);
      if(series.length){
        if(channels.length){
          const sep=document.createElement('div');
          sep.style='grid-column:1/-1;padding-top:8px';
          grid.appendChild(sep);
        }
        renderItemsIntoSliderDOM(grid,series,'series','',true);
      }
    }
  }catch(e){
    // الإلغاء المتعمّد عند تبديل الأقسام ليس خطأ — نتجاهله
    if(e && e.name==='AbortError') return;
    loading.classList.add('hidden');
    grid.classList.add('hidden');
    empty.classList.remove('hidden');
    empty.querySelector('p').textContent='تعذر تحميل محتوى القسم';
  }
}
function closeCategoryView(){
  App.currentCategoryView=null;
  document.getElementById('categoryViewSection').classList.add('hidden');
  document.getElementById('netflixStyleSliders').classList.remove('hidden');
  document.getElementById('heroWelcome').classList.remove('hidden');
  setActiveCatNavBtn(null);
  try{ shsSetHash(null); }catch(e){}   // رجوع للرئيسية → عنوان نظيف
  window.scrollTo({top:0,behavior:'smooth'});
}

/* [SHS-CATVIEW-JS-START] كاش + عرض احترافي فوري داخل الأقسام (إضافة فقط) */
/* بانر عنوان القسم مع شرائح الإحصائيات */
function shsRenderCatBanner(catName,chCount,srCount){
  var host=document.getElementById('categoryViewSection');
  if(!host)return;
  var b=document.getElementById('shsCatViewBanner');
  if(!b){
    b=document.createElement('div');
    b.id='shsCatViewBanner';
    b.className='shs-catview-banner';
    /* نضعه بعد زر الرجوع مباشرة، وقبل عنوان القسم القديم (الذي يبقى مخفياً منطقياً بلا حذف) */
    var backBtn=host.querySelector('.back-btn');
    if(backBtn&&backBtn.nextSibling){host.insertBefore(b,backBtn.nextSibling);}else{host.insertBefore(b,host.firstChild);}
  }
  var total=(chCount|0)+(srCount|0);
  var chips='<span class="shs-catview-chip total"><span class="dot"></span>'+total+' عنصر</span>';
  if(chCount>0)chips+='<span class="shs-catview-chip">قنوات: '+chCount+'</span>';
  if(srCount>0)chips+='<span class="shs-catview-chip">أفلام/مسلسلات: '+srCount+'</span>';
  var nm=(catName||'القسم').replace(/</g,'&lt;').replace(/>/g,'&gt;');
  b.innerHTML=
    '<div class="shs-catview-ico">'+shsCatIconSVG(catName)+'</div>'+
    '<div class="shs-catview-meta"><span class="shs-catview-name">'+nm+'</span><div class="shs-catview-chips">'+chips+'</div></div>';
  b.style.display='flex';
}
function shsBannerLoading(catName){
  shsRenderCatBanner(catName,0,0);
  var b=document.getElementById('shsCatViewBanner');
  if(b){var chips=b.querySelector('.shs-catview-chips'); if(chips)chips.innerHTML='<span class="shs-catview-chip">جارٍ التحميل…</span>';}
}
/* هياكل تحميل بدل السبنر */
function shsShowSkeleton(grid,n){
  n=n||12;
  var html='';
  for(var i=0;i<n;i++)html+='<div class="shs-skel-card"></div>';
  grid.innerHTML=html;
  grid.classList.remove('hidden');
}
/* رسم المحتوى داخل الشبكة بأسلوب موحّد */
function shsPaintCategory(grid,channels,series){
  grid.innerHTML='';
  grid.classList.add('shs-fadein','shs-stagger');
  if(channels&&channels.length)renderItemsIntoSliderDOM(grid,channels,'channels','',true);
  if(series&&series.length){
    if(channels&&channels.length){
      var sep=document.createElement('div');
      sep.className='shs-catview-sep';
      sep.textContent='أفلام ومسلسلات';
      grid.appendChild(sep);
    }
    renderItemsIntoSliderDOM(grid,series,'series','',true);
  }
  /* توزيع تأخير الظهور على البطاقات لإحساس متدرّج */
  try{
    var cards=grid.querySelectorAll('.ch-card,.sr-card');
    for(var i=0;i<cards.length;i++){ cards[i].style.animationDelay=Math.min(i*0.035,0.5)+'s'; }
  }catch(e){}
  /* إعادة تشغيل أنيميشن الظهور */
  void grid.offsetWidth;
}

/* التفاف حول openCategoryView: عرض فوري من الكاش + تحديث بالخلفية */
if(typeof openCategoryView==='function' && !openCategoryView.__shsFast){
  var _shsOrigOpenCategoryView=openCategoryView;
  openCategoryView=async function(catId,catName){
    App.currentCategoryView={id:catId,name:catName};
    try{ setActiveCatNavBtn(catId); }catch(e){}
    /* إظهار قسم العرض وإخفاء البقية فوراً */
    try{
      document.getElementById('netflixStyleSliders').classList.add('hidden');
      document.getElementById('heroWelcome').classList.add('hidden');
      document.getElementById('searchViewSection').classList.add('hidden');
      document.getElementById('epSection').classList.add('hidden');
      document.getElementById('categoryViewSection').classList.remove('hidden');
    }catch(e){}
    var titleEl=document.getElementById('categoryViewTitle');
    if(titleEl)titleEl.textContent=catName||'القسم';
    var grid=document.getElementById('categoryViewGrid');
    var loading=document.getElementById('categoryViewLoading');
    var empty=document.getElementById('categoryViewEmpty');
    var badge=document.getElementById('categoryViewCountBadge');
    if(loading)loading.classList.add('hidden'); /* نستخدم هياكل التحميل بدل السبنر */
    if(empty)empty.classList.add('hidden');
    window.scrollTo({top:0,behavior:'smooth'});

    var cached=App._catCache[catId];
    if(cached){
      /* ⚡ استجابة فورية من الكاش — بلا انتظار الشبكة */
      shsRenderCatBanner(catName,cached.channels.length,cached.series.length);
      var tot=cached.channels.length+cached.series.length;
      if(badge)badge.textContent=tot+' عنصر';
      if(!tot){ grid.classList.add('hidden'); if(empty)empty.classList.remove('hidden'); }
      else{ grid.classList.remove('hidden'); shsPaintCategory(grid,cached.channels,cached.series); }
    }else{
      /* أول مرة: هياكل تحميل ثم جلب */
      shsBannerLoading(catName);
      if(grid){grid.classList.remove('hidden');shsShowSkeleton(grid,12);}
    }
    /* تحديث/جلب من الشبكة (يتم دائماً لتحديث الكاش بالخلفية) */
    try{
      var res=await Promise.all([
        fetch('api.php?action=channels&category_id='+encodeURIComponent(catId)).then(function(r){return r.json();}),
        fetch('api.php?action=series&category_id='+encodeURIComponent(catId)).then(function(r){return r.json();})
      ]);
      var channels=(res[0]&&res[0].channels)||[];
      var series=(res[1]&&res[1].series)||[];
      App._catCache[catId]={channels:channels,series:series,ts:Date.now()};
      /* لا نعيد الرسم إن كان المستخدم غادر القسم */
      if(!App.currentCategoryView||String(App.currentCategoryView.id)!==String(catId))return;
      shsRenderCatBanner(catName,channels.length,series.length);
      var total=channels.length+series.length;
      if(badge)badge.textContent=total+' عنصر';
      if(!total){
        grid.classList.add('hidden'); if(empty)empty.classList.remove('hidden');
      }else{
        grid.classList.remove('hidden'); if(empty)empty.classList.add('hidden');
        shsPaintCategory(grid,channels,series);
      }
    }catch(e){
      if(!cached){ /* أظهر خطأ فقط إن لم يكن هناك كاش يُعرض */
        if(grid)grid.classList.add('hidden');
        if(empty){empty.classList.remove('hidden');var p=empty.querySelector('p');if(p)p.textContent='تعذر تحميل محتوى القسم';}
      }
    }
  };
  openCategoryView.__shsFast=true;
}

/* [SHS-VIEWRESTORE] حفظ/استعادة موضع التصفّح عند تحديث الصفحة (إضافة فقط) */
function shsSaveView(obj){try{sessionStorage.setItem('shs_view',JSON.stringify(obj));}catch(e){}}
function shsClearView(){try{sessionStorage.removeItem('shs_view');}catch(e){}}

/* التفاف حول عرض القسم لحفظ حالته */
if(typeof openCategoryView==='function' && !openCategoryView.__shsViewHook){
  var _shsOCV=openCategoryView;
  openCategoryView=function(catId,catName){
    shsSaveView({type:'category',id:catId,name:catName});
    return _shsOCV.apply(this,arguments);
  };
  openCategoryView.__shsFast=true;
  openCategoryView.__shsViewHook=true;
}
/* التفاف حول قائمة حلقات المسلسل لحفظ حالتها */
if(typeof openSeriesEpisodes==='function' && !openSeriesEpisodes.__shsViewHook){
  var _shsOSE=openSeriesEpisodes;
  openSeriesEpisodes=function(seriesId,seriesName,seriesPoster){
    /* نحفظ أيضاً سياق القسم إن كنا داخل قسم، للرجوع الصحيح */
    var cv=App.currentCategoryView?{id:App.currentCategoryView.id,name:App.currentCategoryView.name}:null;
    shsSaveView({type:'series',id:seriesId,name:seriesName,poster:seriesPoster||'',cat:cv});
    return _shsOSE.apply(this,arguments);
  };
  openSeriesEpisodes.__shsViewHook=true;
}
/* مسح الحالة عند العودة للرئيسية */
['closeCategoryView','clearSearchAndGoHome'].forEach(function(fn){
  if(typeof window[fn]==='function' && !window[fn].__shsClearHook){
    var _o=window[fn];
    window[fn]=function(){shsClearView();return _o.apply(this,arguments);};
    window[fn].__shsClearHook=true;
  }
});
/* الرجوع من الحلقات: إن كنا داخل قسم نحفظ القسم، وإلا نمسح */
if(typeof backFromEpisodesToHome==='function' && !backFromEpisodesToHome.__shsClearHook){
  var _bfe=backFromEpisodesToHome;
  backFromEpisodesToHome=function(){
    try{
      var sq=(document.getElementById('searchInput')||{}).value||'';
      if(sq.trim().length>0){shsClearView();}
      else if(App.currentCategoryView){shsSaveView({type:'category',id:App.currentCategoryView.id,name:App.currentCategoryView.name});}
      else{shsClearView();}
    }catch(e){shsClearView();}
    return _bfe.apply(this,arguments);
  };
  backFromEpisodesToHome.__shsClearHook=true;
}

/* الاستعادة عند تحميل الصفحة */
function shsRestoreView(){
  var raw;try{raw=sessionStorage.getItem('shs_view');}catch(e){return;}
  if(!raw)return;
  var d;try{d=JSON.parse(raw);}catch(e){return;}
  if(!d||!d.type)return;
  /* لا نستعيد إن كان هناك مشغّل قيد الاستعادة (shs_restore له الأولوية) */
  try{if(sessionStorage.getItem('shs_restore'))return;}catch(e){}
  try{
    if(d.type==='category'){
      if(typeof openCategoryView==='function')openCategoryView(d.id,d.name);
    }else if(d.type==='series'){
      /* لو كان داخل قسم، نفتح القسم أولاً (بصمت) ثم قائمة الحلقات */
      if(d.cat&&typeof openCategoryView==='function'){App.currentCategoryView={id:d.cat.id,name:d.cat.name};}
      if(typeof openSeriesEpisodes==='function')openSeriesEpisodes(d.id,d.name,d.poster||'');
    }
  }catch(e){}
}
/* نشغّلها بعد تحميل الأقسام والدوال */
if(document.readyState==='loading'){
  document.addEventListener('DOMContentLoaded',function(){setTimeout(shsRestoreView,350);});
}else{
  setTimeout(shsRestoreView,350);
}
/* [SHS-VIEWRESTORE-END] */
/* [SHS-CATVIEW-JS-END] */
function syncCatNavbarOffset(){
  const navbar=document.getElementById('navbar');
  const catBar=document.getElementById('catNavbar');
  const main=document.getElementById('mainContent');
  if(!navbar||!catBar)return;
  const navH=navbar.offsetHeight||68;
  document.documentElement.style.setProperty('--navbar-h',navH+'px');
  if(main){
    const catH=(App.cats&&App.cats.length)?(catBar.offsetHeight||48):0;
    main.style.paddingTop=(navH+catH+16)+'px';
  }
}
window.addEventListener('resize',()=>{ if(document.getElementById('catNavbar').style.display!=='none') syncCatNavbarOffset(); });

// Voice Search
document.addEventListener('DOMContentLoaded',function(){
  const SR=window.SpeechRecognition||window.webkitSpeechRecognition;
  const btn=document.getElementById('voiceSearchBtn');
  if(SR&&btn){
    btn.style.display='block';
    const rec=new SR();rec.lang='ar-SA';rec.interimResults=false;
    rec.onresult=e=>{document.getElementById('searchInput').value=e.results[0][0].transcript;btn.style.color='var(--text-muted)';handleSearch();};
    rec.onerror=()=>btn.style.color='var(--text-muted)';
    rec.onend=()=>btn.style.color='var(--text-muted)';
    btn.addEventListener('click',()=>{try{rec.start();btn.style.color='var(--red)';}catch(e){}});
  }
});

/* ════ PLAYER STATE ════ */
const PL={hls:null,dash:null,flv:null,vol:1,muted:false,idle:null,subtitleOn:false,epPanelOpen:false,m3uPanelOpen:false,m3uEntries:[],m3uIdx:-1,userPaused:false,backupUrl:'',usedBackup:false};
try{window.PL=PL;}catch(e){} /* جسر: كشف PL على window لإصلاحات المشغّل — لا يغيّر أي منطق */
const _saved={active:false,url:'',subUrl:'',type:'',epIdx:-1,seriesId:0};

/* ════ CAST ════ */
function castToSmartWvc(){
  if(!_saved.url){toast('لا يوجد بث للإرسال');return;}
  const a=document.createElement('a');a.href=_saved.url;const absUrl=a.href;
  toast('جارِ تجهيز الإرسال...');
  setTimeout(()=>{
    if(_UA.isIOS){
      const t=Date.now();
      window.location.href='wvc-x-callback://open?url='+encodeURIComponent(absUrl);
      setTimeout(()=>{if(Date.now()-t<2000)window.location.href='https://apps.apple.com/app/web-video-cast-browser-to-tv/id1400866497';},1500);
    }else{
      const sch=absUrl.startsWith('https')?'https':'http';
      const tc=absUrl.split('://')[1]||absUrl;
      const ws=encodeURIComponent('https://play.google.com/store/apps/details?id=com.instantbits.cast.webvideo');
      window.location.href=`intent://${tc}#Intent;package=com.instantbits.cast.webvideo;action=android.intent.action.VIEW;scheme=${sch};type=video/*;S.browser_fallback_url=${ws};end;`;
    }
  },300);
}

function downloadWithTdm(){
  if(!_saved.url){toast('لا يوجد بث جاهز للتحميل');return;}
  const a=document.createElement('a');a.href=_saved.url;const absUrl=a.href;
  toast('جارٍ تحويلك لتطبيق TDM...');
  setTimeout(()=>{
    const sch=absUrl.startsWith('https')?'https':'http';
    const tc=absUrl.split('://')[1]||absUrl;
    const storeFallback=encodeURIComponent('https://play.google.com/store/apps/details?id=com.tdm.manager&hl=en_GB');
    window.location.href=`intent://${tc}#Intent;package=com.tdm.manager;action=android.intent.action.VIEW;scheme=${sch};type=video/*;S.browser_fallback_url=${storeFallback};end;`;
  },300);
}

document.addEventListener('DOMContentLoaded',function(){
  // استخدام _UA المحسوب مسبقاً — لا قراءة userAgent جديدة
  const dlBtn=document.getElementById('tdmDownloadBtn');
  if(dlBtn&&_UA.isAndroid&&!_UA.isWindows)dlBtn.style.display='flex';
});

/* ════ OPEN PLAYER ════ */
function openPlayerChannel(ch){
  try{sessionStorage.setItem('shs_restore',JSON.stringify({type:'channel',ch:ch}));}catch(e){}
  /* لا نكتب الـhash هنا: pushState لم يُنفَّذ بعد (السطر ~3449)، فالكتابة الآن
     تلوّث إدخالة الشاشة السابقة (الرئيسية) بـ #ch=..، فيعود التحديث للمشغّل
     بعد الخروج منه. نؤجّلها لتقع على إدخالة المشغّل نفسها. */
  _pendingHash = (ch && ch.id) ? {ch:ch.id} : null;
  App.currentType='channel';App.currentEpisodeIdx=-1;
  document.getElementById('pEpNav').style.display='none';
  document.getElementById('epPanelBtn').style.display='none';
  document.getElementById('m3uPanelBtn').style.display='none';
  PL.backupUrl=ch.backup_url||'';PL.usedBackup=false;
  const fmt=fmtLabel(ch.stream_url||'');const isLive=isLiveFormat(ch.stream_url||'');
  document.getElementById('pBadgeLabel').textContent=isLive?'LIVE':'VOD';
  document.getElementById('pChannelName').textContent=ch.name;
  document.getElementById('pFmtTag').textContent=ch.quality||fmt;
  document.getElementById('pTime').textContent=isLive?'بث مباشر':'00:00 / 00:00';
  const f=detectFmt(ch.stream_url||'');
  if(f==='hls'&&(ch.stream_url||'').toLowerCase().endsWith('.m3u')){
    _openOverlay('',ch.subtitle_url||'');
    toast('جارٍ تحميل قائمة M3U...');
    parseM3U(ch.stream_url).then(entries=>{if(!entries.length){toast('القائمة فارغة');return;}PL.m3uEntries=entries;PL.m3uIdx=0;buildM3UPanel();document.getElementById('m3uPanelBtn').style.display='flex';toggleM3UPanel();playM3UEntry(0);});
    return;
  }
  _openOverlay(ch.stream_url,ch.subtitle_url||'');
  if(ch.id)fetch('api.php?action=increment_view&id='+ch.id+'&type=channel').catch(()=>{});
}

function openPlayerEpisode(idx){
  try{sessionStorage.setItem('shs_restore',JSON.stringify({type:'episode',idx:idx,ep:App.allEpisodes[idx],seriesId:App.currentSeriesId,seriesName:App.currentSeriesName}));}catch(e){}
  /* نفس سبب التأجيل في openPlayerChannel: الكتابة قبل pushState تلوّث
     إدخالة صفحة الحلقات، فيعيدك التحديث للفيديو بعد الخروج منه. */
  _pendingHash = (App.currentSeriesId!==undefined && App.currentSeriesId!==null)
    ? {s:App.currentSeriesId, e:idx} : null;
  App.currentType='episode';App.currentEpisodeIdx=idx;
  const ep=App.allEpisodes[idx];if(!ep)return;
  PL.backupUrl='';PL.usedBackup=false;
  const fmt=fmtLabel(ep.stream_url||'');const isLive=isLiveFormat(ep.stream_url||'');
  document.getElementById('pBadgeLabel').textContent=isLive?'LIVE':'EP';
  document.getElementById('pChannelName').textContent=App.currentSeriesName;
  document.getElementById('pFmtTag').textContent=fmt;
  document.getElementById('pEpLabel').textContent=ep.title;
  document.getElementById('pEpNav').style.display='flex';
  document.getElementById('pPrevEp').disabled=(idx===0);
  document.getElementById('pNextEp').disabled=(idx===App.allEpisodes.length-1);
  document.getElementById('epPanelBtn').style.display='flex';
  _openOverlay(ep.stream_url,ep.subtitle_url||'');
  buildEpPanel();
  fetch('api.php?action=increment_view&id='+ep.id+'&type=episode').catch(()=>{});
}
function navEpisode(dir){const ni=App.currentEpisodeIdx+dir;if(ni>=0&&ni<App.allEpisodes.length)openPlayerEpisode(ni);}

var _prevScreen={ep:false,home:false,search:false,category:false};

function _openOverlay(url,subUrl){
  const overlay=document.getElementById('playerOverlay');

  // هل نفس المحتوى ولم يُدمَّر؟
  const same=!_saved.destroyed &&
    _saved.active &&
    _saved.type===App.currentType&&
    (App.currentType==='channel'
      ? _saved.url===url
      : _saved.epIdx===App.currentEpisodeIdx && _saved.seriesId===App.currentSeriesId);

  _prevScreen.ep=!document.getElementById('epSection').classList.contains('hidden');
  _prevScreen.home=!document.getElementById('netflixStyleSliders').classList.contains('hidden');
  _prevScreen.search=!document.getElementById('searchViewSection').classList.contains('hidden');
  _prevScreen.category=!document.getElementById('categoryViewSection').classList.contains('hidden');
  overlay.classList.add('active');
  document.body.style.overflow='hidden';
  /* ندفع إدخالة للمشغّل مرة واحدة فقط. إن كان المشغّل مفتوحاً أصلاً (تبديل حلقة)
     فالإدخالة موجودة، وتكرارها يجعل الرجوع يحتاج ضغطات متعددة. */
  if(!(window.history.state && window.history.state.player==='active')){
    window.history.pushState({player:'active'},'');
  }
  /* الآن فقط نكتب عنوان المشغّل — على إدخالته الخاصة.
     بهذا يبقى التحديث داخل الفيديو يعمل، وعند الخروج تعود الشاشة السابقة نظيفة. */
  if(_pendingHash){ const _ph=_pendingHash; _pendingHash=null; try{ shsSetHash(_ph); }catch(e){} }
  fixPlayerHeight();
  setTimeout(function(){try{overlay.focus();}catch(e){}},100);

  if(same){
    // نفس المحتوى ولم يُغلَق — استئناف فقط
    const v=document.getElementById('html5Player');
    if(v&&v.paused)v.play().catch(()=>{});
  }else{
    // محتوى جديد أو بعد إغلاق — تشغيل من البداية
    if(url)initStream(url,subUrl);
    _saved.active=true;
    _saved.destroyed=false;
    _saved.url=url;
    _saved.subUrl=subUrl;
    _saved.type=App.currentType;
    _saved.epIdx=App.currentEpisodeIdx;
    _saved.seriesId=App.currentSeriesId;
  }
  // عرض شعارات قدرات الجهاز عند كل فتح للمشغل
  _showDeviceBadges();
  showControls();
}

function closePlayer(){
  try{sessionStorage.removeItem('shs_restore');}catch(e){}
  _pendingHash = null;   // إلغاء أي عنوان مؤجّل لم يُكتب بعد
  /* ── العنوان يتبع الشاشة التي نعود إليها فعلاً ──
     الخطأ سابقاً: كنا نكتب {s: App.currentSeriesId} دائماً، حتى لو كان الرجوع للرئيسية.
     فيبقى #s=.. في العنوان، وأي تحديث يعيد فتح العمل/الفيديو من جديد.
     _prevScreen يحفظ الشاشة التي كنا فيها قبل فتح المشغّل — نعتمد عليها. */
  try{
    const q = (document.getElementById('searchInput')||{}).value || '';
    if(_prevScreen.ep && App.currentSeriesId){
      shsSetHash({s:App.currentSeriesId});          // نرجع لصفحة الحلقات (بلا e — الفيديو أُغلق)
    } else if(_prevScreen.search && q.trim()){
      shsSetHash({q:q.trim()});                     // نرجع لنتائج البحث
    } else if(_prevScreen.category && App.currentCategoryView){
      shsSetHash({c:App.currentCategoryView.id});   // نرجع للقسم
    } else {
      shsSetHash(null);                             // نرجع للرئيسية → عنوان نظيف
    }
  }catch(e){}
  // إلغاء أي إعادة تشغيل تلقائية معلّقة (حتى لا تُعاد فتح قناة بعد الإغلاق)
  if(_hardReloadTimer){clearTimeout(_hardReloadTimer);_hardReloadTimer=null;}
  _hardReloadUrl='';
  // خروج من fullscreen أولاً
  try{
    if(document.fullscreenElement||document.webkitFullscreenElement)
      (document.exitFullscreen||document.webkitExitFullscreen).call(document);
  }catch(e){}
  // حفظ موضع التشغيل + تعليم أن المشغل دُمِّر
  const v=document.getElementById('html5Player');
  if(v&&!isNaN(v.currentTime))_saved.time=v.currentTime;
  _saved.destroyed=true; // ← الإصلاح: يمنع same=true من تخطي initStream
  // تنظيف المشغل بالكامل
  destroyPlayer();
  // إخفاء overlay والـ panels
  document.getElementById('playerOverlay').classList.remove('active');
  document.getElementById('epPanel').classList.remove('open');
  document.getElementById('m3uPanel').classList.remove('open');
  PL.epPanelOpen=false; PL.m3uPanelOpen=false;
  document.body.style.overflow='';
  // استعادة الشاشة السابقة
  document.getElementById('epSection').classList.toggle('hidden',!_prevScreen.ep);
  document.getElementById('netflixStyleSliders').classList.toggle('hidden',!_prevScreen.home);
  document.getElementById('heroWelcome').classList.toggle('hidden',!_prevScreen.home);
  document.getElementById('searchViewSection').classList.toggle('hidden',!_prevScreen.search);
  document.getElementById('categoryViewSection').classList.toggle('hidden',!_prevScreen.category);
}

/* ══════════════════════════════════════════════════════════
   DEVICE CAPABILITY DETECTION
   يكشف دعم: الصوت (Dolby/DTS/AAC) + الصورة (HDR/4K/8K) + الهرتزية
   ويعرضها كشعارات عند بدء تشغيل كل فيديو
══════════════════════════════════════════════════════════ */

/* كشف قدرات الجهاز مرة واحدة عند التحميل */
const _DevCaps=(function(){
  const ua=_UA.ua;
  const v=document.createElement('video');

  /* ══ الصوت ══ */
  const audio={
    dolbyAtmos: !!(v.canPlayType('audio/mp4; codecs="ec-3"')||v.canPlayType('video/mp4; codecs="ec-3"')),
    dolbyAudio: !!v.canPlayType('audio/mp4; codecs="ac-3"'),
    dtsX:       !!(v.canPlayType('audio/mp4; codecs="dtsc"')||v.canPlayType('audio/mp4; codecs="dtse"')),
    aac:        !!v.canPlayType('audio/mp4; codecs="mp4a.40.2"'),
    opus:       !!v.canPlayType('audio/webm; codecs="opus"'),
  };

  /* ══ الفيديو / HDR ══ */
  const hdrP3   = window.matchMedia('(color-gamut: p3)').matches;
  const hdrRec2020 = window.matchMedia('(color-gamut: rec2020)').matches;
  const hdrDynamic = window.matchMedia('(dynamic-range: high)').matches;
  const hdr10plus  = hdrRec2020&&hdrDynamic;
  const colorDepth = screen.colorDepth||0;

  const video={
    hdr10plus,
    hdr10: hdrDynamic && hdrP3,
    hlg:   hdrDynamic,
    hdrAny: hdrDynamic||hdrP3,
    h265:  !!(v.canPlayType('video/mp4; codecs="hvc1.1.6.L93.B0"')||v.canPlayType('video/mp4; codecs="hev1.1.6.L93.B0"')),
    av1:   !!v.canPlayType('video/mp4; codecs="av01.0.05M.08"'),
    h264:  !!v.canPlayType('video/mp4; codecs="avc1.42E01E"'),
    res4k: screen.width>=3840||screen.height>=2160,
    res8k: screen.width>=7680||screen.height>=4320,
    colorDepth,
  };

  /* ══ الشاشة / هرتزية ══ */
  // MediaCapabilities API — الأدق
  let hzEst=60;
  if(typeof screen.refreshRate==='number')        hzEst=Math.round(screen.refreshRate);
  else if(window.matchMedia('(min-resolution: 2dppx)').matches && _UA.isTV) hzEst=120;

  // تقدير من UA للتلفازات المعروفة
  if(/TCL|Hisense|Sony|Samsung|LG|BRAVIA/i.test(ua)){
    if(/8K|2160p|75inch|85inch|98inch/i.test(ua)) hzEst=Math.max(hzEst,120);
    else hzEst=Math.max(hzEst,60);
  }
  if(_UA.isAndroidTV&&!_UA.isMobile) hzEst=Math.max(hzEst,60);

  const vrr = window.matchMedia('(update: fast)').matches;
  const display={hz:hzEst, vrr};

  /* ══ نوع الجهاز ══ */
  const deviceType = _UA.isTV        ? 'TV'
                   : _UA.isIOS       ? 'iOS'
                   : _UA.isAndroidMobile ? 'Android'
                   : 'Desktop';

  return{audio,video,display,deviceType};
})();

/* بناء الشعارات وعرضها */
function _showDeviceBadges(){
  const wrap=document.getElementById('deviceBadgesWrap');
  if(!wrap)return;
  wrap.innerHTML='';

  const badges=[];
  const C=_DevCaps;

  /* ── الصوت ── */
  if(C.audio.dolbyAtmos){
    badges.push({cls:'audio-dolby',icon:'🔊',label:'Dolby Atmos'});
  }else if(C.audio.dolbyAudio){
    badges.push({cls:'audio-dolby',icon:'🔊',label:'Dolby Audio'});
  }else if(C.audio.dtsX){
    badges.push({cls:'audio-dts',icon:'🔊',label:'DTS:X'});
  }else if(C.audio.aac){
    badges.push({cls:'audio-std',icon:'🔊',label:'AAC Stereo'});
  }

  /* ── الصورة ── */
  if(C.video.res8k){
    badges.push({cls:'video-4k',icon:'🖥',label:'8K Ultra HD'});
  }else if(C.video.res4k){
    badges.push({cls:'video-4k',icon:'🖥',label:'4K Ultra HD'});
  }

  if(C.video.hdr10plus){
    badges.push({cls:'video-hdr',icon:'☀️',label:'HDR10+'});
  }else if(C.video.hdr10){
    badges.push({cls:'video-hdr',icon:'☀️',label:'HDR10'});
  }else if(C.video.hlg){
    badges.push({cls:'video-hdr',icon:'☀️',label:'HLG'});
  }

  if(C.video.av1){
    badges.push({cls:'video-std',icon:'🎬',label:'AV1'});
  }else if(C.video.h265){
    badges.push({cls:'video-std',icon:'🎬',label:'HEVC / H.265'});
  }else if(C.video.h264){
    badges.push({cls:'video-std',icon:'🎬',label:'H.264'});
  }

  /* ── الهرتزية ── */
  const hz=C.display.hz;
  const vrrTxt=C.display.vrr?' VRR':'';
  if(hz>=240){
    badges.push({cls:'display-hz',icon:'⚡',label:`${hz}Hz${vrrTxt}`});
  }else if(hz>=144){
    badges.push({cls:'display-hz',icon:'⚡',label:`${hz}Hz${vrrTxt}`});
  }else if(hz>=120){
    badges.push({cls:'display-hz',icon:'⚡',label:`${hz}Hz${vrrTxt}`});
  }else{
    badges.push({cls:'display-hz',icon:'⚡',label:`${hz}Hz`});
  }

  /* ── نوع الجهاز ── */
  const typeMap={TV:'📺 تلفاز',iOS:'📱 iOS',Android:'📱 أندرويد',Desktop:'💻 متصفح'};
  badges.push({cls:'video-std',icon:'',label:typeMap[C.deviceType]||C.deviceType});

  /* إنشاء العناصر مع تأخير تتالي */
  badges.forEach((b,i)=>{
    const el=document.createElement('div');
    el.className=`dev-badge ${b.cls}`;
    el.innerHTML=`<span class="db-icon">${b.icon}</span>${b.label}`;
    wrap.appendChild(el);
    // ظهور تتالي
    setTimeout(()=>el.classList.add('visible'), i*80+100);
  });

  /* اختفاء تلقائي بعد 4 ثوانٍ */
  setTimeout(()=>{
    wrap.querySelectorAll('.dev-badge').forEach(el=>{
      el.style.transition='opacity .6s ease, transform .6s ease';
      el.classList.remove('visible');
    });
    setTimeout(()=>{wrap.innerHTML='';},700);
  },4000);
}


/* ════════════════════════════════════════════
   SUBTITLE SYSTEM — يدعم VTT و SRT تلقائياً
   SRT  → يُحوَّل إلى VTT في المتصفح (Blob URL)
   VTT  → يُمرَّر مباشرة
   يكشف النوع من الامتداد أو محتوى الملف
════════════════════════════════════════════ */

/* تحويل SRT نص إلى VTT نص */
function _srtToVtt(srt){
  // أضف رأس VTT
  let vtt = 'WEBVTT\n\n';
  // استبدل فواصل السطر المختلفة بـ \n
  const text = srt.replace(/\r\n/g,'\n').replace(/\r/g,'\n').trim();
  // استبدل الطوابع الزمنية: 00:00:00,000 → 00:00:00.000
  vtt += text.replace(/(\d{2}:\d{2}:\d{2}),(\d{3})/g,'$1.$2');
  return vtt;
}

/* إنشاء Blob URL من نص */
function _makeBlobUrl(text, mime){
  try{
    const blob = new Blob([text], {type: mime});
    return URL.createObjectURL(blob);
  }catch(e){ return null; }
}

/* إضافة track للفيديو */
function _attachTrack(videoEl, srcUrl, isBlob){
  // احذف أي tracks قديمة
  while(videoEl.firstChild && videoEl.firstChild.tagName === 'TRACK'){
    videoEl.removeChild(videoEl.firstChild);
  }
  const t = document.createElement('track');
  t.kind    = 'subtitles';
  t.label   = 'العربية';
  t.srclang = 'ar';
  t.src     = srcUrl;
  t.default = true;
  videoEl.appendChild(t);
  // تفعيل فوري
  if(videoEl.textTracks && videoEl.textTracks[0]){
    videoEl.textTracks[0].mode = 'showing';
  }
  // حذف Blob URL بعد التحميل لتحرير الذاكرة
  if(isBlob){
    t.addEventListener('load', ()=>{ try{ URL.revokeObjectURL(srcUrl); }catch(e){} }, {once:true});
  }
}

/* الدالة الرئيسية — تكشف النوع وتُحمّل */
async function _loadSubtitle(videoEl, subUrl){
  if(!subUrl || !subUrl.trim()) return;

  const ext = subUrl.split('?')[0].split('.').pop().toLowerCase();

  try{
    if(ext === 'vtt'){
      // VTT — مباشر بدون تحويل
      _attachTrack(videoEl, subUrl, false);
      return;
    }

    if(ext === 'srt'){
      // SRT — جلب ثم تحويل إلى VTT
      const resp = await fetch(subUrl);
      if(!resp.ok) throw new Error('fetch failed');
      const raw  = await resp.text();
      const vtt  = _srtToVtt(raw);
      const bUrl = _makeBlobUrl(vtt, 'text/vtt');
      if(bUrl){ _attachTrack(videoEl, bUrl, true); }
      else     { _attachTrack(videoEl, subUrl, false); } // fallback
      return;
    }

    // امتداد غير معروف — جلب وفحص المحتوى
    const resp = await fetch(subUrl);
    if(!resp.ok) throw new Error('fetch failed');
    const raw  = await resp.text();
    const trimmed = raw.trimStart();

    if(trimmed.startsWith('WEBVTT')){
      // المحتوى VTT
      const bUrl = _makeBlobUrl(raw, 'text/vtt');
      if(bUrl){ _attachTrack(videoEl, bUrl, true); }
      else     { _attachTrack(videoEl, subUrl, false); }
    } else {
      // افتراض SRT
      const vtt  = _srtToVtt(raw);
      const bUrl = _makeBlobUrl(vtt, 'text/vtt');
      if(bUrl){ _attachTrack(videoEl, bUrl, true); }
      else     { _attachTrack(videoEl, subUrl, false); }
    }

  }catch(err){
    // فشل الجلب (CORS) — جرب مباشرة كـ fallback
    _attachTrack(videoEl, subUrl, false);
  }
}


/* ══ إعادة تشغيل القناة بالكامل تلقائياً عند توقفها (بديل الخروج والدخول اليدوي) ══
   تعيد بناء البث لنفس الرابط مع تباعد زمني متزايد، ولا تستسلم.
   تُستخدم عندما تفشل محاولات الاسترداد الخفيفة. */
var _hardReloadTimer=null, _hardReloadUrl='', _hardReloadSub='';
function _hardReloadStream(url){
  // إن استُنفدت محاولات الرابط الأساسي ويوجد رابط احتياطي لم يُستخدم بعد — بدّل إليه فوراً
  if(PL.backupUrl && !PL.usedBackup && url!==PL.backupUrl && (PL._hlsHardRetry||0)>=3){
    PL.usedBackup=true;
    PL._hlsHardRetry=0;PL._hlsNetRetry=0;PL._hlsMediaRetry=0;
    if(_hardReloadTimer){clearTimeout(_hardReloadTimer);_hardReloadTimer=null;}
    toast('تعذّر الرابط الأساسي — جارٍ التبديل للرابط الاحتياطي...');
    try{ initStream(PL.backupUrl, _hardReloadSub||''); }catch(_){}
    return;
  }
  // حدّ أقصى للمحاولات السريعة المتتالية قبل المباعدة الأطول
  PL._hlsHardRetry=(PL._hlsHardRetry||0)+1;
  if(_hardReloadTimer)clearTimeout(_hardReloadTimer);
  // تباعد متزايد: 1ث، 2ث، 3ث ... بحد أقصى 8ث (يحاول للأبد بهدوء)
  const wait=Math.min(PL._hlsHardRetry,8)*1000;
  showBuf(true);
  if(PL._hlsHardRetry<=2) toast('إعادة تشغيل القناة...');
  _hardReloadTimer=setTimeout(function(){
    const overlay=document.getElementById('playerOverlay');
    // لا نعيد إن أُغلق المشغل أو غيّر المستخدم القناة
    if(!overlay||!overlay.classList.contains('active'))return;
    if(!_hardReloadUrl || _hardReloadUrl!==url)return;
    try{ initStream(url, _hardReloadSub||''); }catch(_){}
  }, wait);
}

function initStream(url,subUrl){
  // نتذكّر الرابط الحالي حتى يعرف الاسترداد التلقائي ما يعيد تشغيله
  _hardReloadUrl=url; _hardReloadSub=subUrl||'';
  const v=document.getElementById('html5Player');
  destroyPlayer();

  // إعادة إنشاء عنصر الفيديو بالكامل — يضمن حذف الـ tracks القديمة ونظافة كاملة
  const newV=document.createElement('video');
  newV.id='html5Player';
  newV.setAttribute('playsinline','');
  newV.setAttribute('preload','auto');

  // ══ ضمان الجودة الأصلية — CSS inline لا يُلغيه أي قاعدة خارجية ══
  newV.style.cssText=[
    'width:100%',
    'height:100%',
    'object-fit:contain',
    'transform:none',
    'filter:none',
    'opacity:1',
    'image-rendering:high-quality',
    'will-change:auto',
    'display:block'
  ].join(';');

  const pvWrap=document.getElementById('pvWrap');
  const oldV=pvWrap.querySelector('video#html5Player');
  if(oldV)pvWrap.replaceChild(newV,oldV);
  else pvWrap.insertBefore(newV,pvWrap.firstChild);

  // ══ نظام الترجمة — يدعم VTT و SRT تلقائياً ══
  if(subUrl&&subUrl.trim()){
    document.getElementById('subBtn').style.opacity='1';
    PL.subtitleOn=true;
    _loadSubtitle(newV, subUrl);
  }else{
    document.getElementById('subBtn').style.opacity='0.4';
    PL.subtitleOn=false;
  }

  const fmt=detectFmt(url);showBuf(true);

  /* ⚠️ رسالة واضحة بدل الفشل الصامت.
     الحاويات مثل MKV و AVI تعمل في VLC لكن لا يدعمها أي متصفح.
     كان المستخدم يرى شاشة سوداء أو "تعذّر التشغيل" بلا سبب. */
  (function(){
    const sup=(typeof fmtBrowserSupport==='function')?fmtBrowserSupport(fmt):{ok:true};
    if(!sup.ok){
      showBuf(false);
      if(typeof toast==='function') toast(sup.why);
      console.warn('[player] حاوية غير مدعومة في المتصفح:',fmt,url);
    }
  })();

  // FIX: HLS مع إعدادات جودة محسّنة
  if(fmt==='hls'){
    /* hls.js يُحمّل async — لو ضغط المستخدم بسرعة قد لا يكون جاهزاً بعد.
       ننتظره لحظات بدل السقوط مباشرة إلى src (الذي يفشل في أغلب المتصفحات). */
    if(typeof Hls==='undefined' && !newV.canPlayType('application/vnd.apple.mpegurl')){
      let _tries=0;
      const _waitHls=setInterval(()=>{
        if(typeof Hls!=='undefined'){ clearInterval(_waitHls); initStream(url,subUrl); }
        else if(++_tries>40){ clearInterval(_waitHls); showBuf(false); toast('تعذّر تحميل مشغّل البث'); }
      },50);
      return;
    }
    if(typeof Hls!=='undefined'&&Hls.isSupported()){
      PL.hls=new Hls({
        enableWorker:true,
        /* lowLatencyMode كان true — وهو يقلّص المخزن عمداً لتقليل التأخير،
           فيسبّب تقطيعاً وهبوطاً في الجودة. أُطفئ لصالح أعلى جودة واستقرار. */
        lowLatencyMode:false,

        /* ══ بلا أي تحديد للسرعة أو الجودة ══
           كل القيود أُزيلت: لا سقف للجودة، لا سقف للسرعة، لا سقف للمخزن.
           المشغّل يأخذ أعلى جودة متاحة ويستهلك كامل سرعة الاتصال. */

        // ── المخزن: إعدادات احترافية عالمية للاستقرار بدون تقطيع ──
        maxBufferLength: 30,
        maxMaxBufferLength: 60,
        maxBufferSize: 60 * 1000 * 1000,
        backBufferLength: 30,
        maxBufferHole: 1.0,               // تسامح أكبر مع فجوات البث

        // ══ مزامنة البث الحيّ — استقرار عالي جداً ══
        liveSyncDurationCount: 7,         // الابتعاد قليلاً عن حافة البث المباشر لامتصاص التذبذبات
        liveMaxLatencyDurationCount: 20,  // أقصى تأخير مسموح قبل القفز للأمام (واسع جداً)
        liveDurationInfinity: true,
        maxLiveSyncPlaybackRate: 1.0,     // ← يمنع تسريع التشغيل الذي يشوّه الصوت أو يسبب تقطيع

        // ── الجودة: أعلى مستوى دائماً ──
        capLevelToPlayerSize: true,
        capLevelOnFPSDrop: true,
        startLevel: -1,                   // يبدأ بأفضل ما تسمح به السرعة المقدّرة
        autoStartLoad: true,

        // ── ABR: يستغل كامل عرض النطاق ──
        abrEwmaDefaultEstimate: 5000000,
        abrEwmaFastLive: 3.0,             // انتقال تدريجي وناعم للجودة
        abrEwmaSlowLive: 9.0,
        abrBandWidthFactor: 0.8,
        abrBandWidthUpFactor: 0.7,
        abrMaxWithRealBitrate: false,
        testBandwidth: true,

        // ── التحميل: متوازٍ وسريع ومقاوم للانقطاعات ──
        startFragPrefetch: false,
        progressive: false,
        fragLoadingMaxRetry: 6,
        fragLoadingRetryDelay: 1000,
        fragLoadingMaxRetryTimeout: 30000,
        manifestLoadingMaxRetry: 4,
        manifestLoadingRetryDelay: 500,
        levelLoadingMaxRetry: 10,
        levelLoadingRetryDelay: 500,
        maxStarvationDelay: 8,            // مقاومة التوقف المؤقت (Starvation)
        maxLoadingDelay: 6,
        highBufferWatchdogPeriod: 2,
        nudgeMaxRetry: 10,
      });
      PL.hls.attachMedia(newV);
      PL.hls.loadSource(url);
      PL.hls.on(Hls.Events.MANIFEST_PARSED,(e,data)=>{
        /* ══ فرض أعلى جودة متاحة ══
           نختار أعلى مستوى في القائمة صراحةً، ثم نُعيد التبديل التلقائي
           كي يبقى على الأعلى ما دامت السرعة تسمح — بلا أي سقف. */
        try{
          PL.hls.nextLevel = -1;
        }catch(_){}
        newV.play().catch(()=>{});
      });
      // نبدأ التشغيل أيضاً عند أول جزء جاهز (أيّهما أسبق)
      PL.hls.on(Hls.Events.FRAG_LOADED,()=>{ if(newV.paused && !PL.userPaused) newV.play().catch(()=>{}); });
      // ══ استرداد تلقائي قوي — يعيد تشغيل القناة بالكامل عند توقفها، بلا خروج يدوي ══
      PL._hlsNetRetry=0;   // عداد أخطاء الشبكة
      PL._hlsMediaRetry=0; // عداد أخطاء الميديا
      PL._hlsHardRetry=0;  // عداد إعادة البناء الكاملة
      PL.hls.on(Hls.Events.ERROR,(e,d)=>{
        if(!d.fatal){return;} // غير القاتلة يعالجها hls.js وحده
        // ── تبديل ذكي سريع: إن فشل تحميل القائمة/الـ manifest الأساسي كلياً ووُجد رابط احتياطي، انتقل فوراً ──
        if(PL.backupUrl && !PL.usedBackup && url!==PL.backupUrl &&
           (d.details===Hls.ErrorDetails.MANIFEST_LOAD_ERROR ||
            d.details===Hls.ErrorDetails.MANIFEST_LOAD_TIMEOUT ||
            d.details===Hls.ErrorDetails.MANIFEST_PARSING_ERROR)){
          PL.usedBackup=true;
          PL._hlsHardRetry=0;PL._hlsNetRetry=0;PL._hlsMediaRetry=0;
          toast('تعذّر الرابط الأساسي — جارٍ التبديل للرابط الاحتياطي...');
          try{ initStream(PL.backupUrl, _hardReloadSub||''); }catch(_){}
          return;
        }
        if(d.type===Hls.ErrorTypes.NETWORK_ERROR){
          showBuf(true);
          PL._hlsNetRetry++;
          if(PL._hlsNetRetry<=3){
            // محاولة خفيفة: إعادة تشغيل التحميل
            try{ PL.hls.startLoad(); }catch(_){}
          } else {
            // الشبكة قُطعت فعلاً: إعادة بناء كاملة للقناة (مثل الخروج والدخول يدوياً)
            _hardReloadStream(url);
          }
        } else if(d.type===Hls.ErrorTypes.MEDIA_ERROR){
          showBuf(true);
          PL._hlsMediaRetry++;
          try{
            if(PL._hlsMediaRetry<=2){ PL.hls.recoverMediaError(); }
            else { _hardReloadStream(url); }
          }catch(_){ _hardReloadStream(url); }
        } else {
          // أي خطأ قاتل آخر: إعادة بناء كاملة
          _hardReloadStream(url);
        }
      });
      // عند نجاح تحميل أي مقطع، نصفّر كل العدّادات (البث تعافى)
      PL.hls.on(Hls.Events.FRAG_BUFFERED,()=>{ PL._hlsNetRetry=0; PL._hlsMediaRetry=0; PL._hlsHardRetry=0; });
      // ══ حارس البث الحيّ: يعيد الصوت لطبيعته دون تحديث الصفحة ══
      if(PL._liveGuard){ clearInterval(PL._liveGuard); PL._liveGuard=null; }
      PL._liveGuard=setInterval(function(){
        try{
          if(!PL.hls||newV.paused) return;
          if(newV.playbackRate!==1) newV.playbackRate=1;   // تصفير أي تسريع علق عليه المشغّل
          var pos=PL.hls.liveSyncPosition;
          if(pos!=null && (pos-newV.currentTime)>12){
            newV.currentTime=pos;   // قفزة للحافة الحيّة بدل اللحاق بتسريع الصوت
          }
        }catch(_){}
      },5000);
    }else if(newV.canPlayType('application/vnd.apple.mpegurl')){
      newV.src=url;newV.play().catch(()=>{});
    }else{newV.src=url;newV.play().catch(()=>{});}
  }else if(fmt==='dash'){
    if(typeof dashjs!=='undefined'){
      PL.dash=dashjs.MediaPlayer().create();
      PL.dash.initialize(newV,url,true);
      /* ══ DASH بلا تحديد سرعة أو جودة ══ */
      PL.dash.updateSettings({
        streaming:{
          buffer:{
            bufferTimeAtTopQuality:30,
            bufferTimeAtTopQualityLongForm:60,
            bufferToKeep:30,
          },
          abr:{
            autoSwitchBitrate:{video:true, audio:true},
            limitBitrateByPortal:true,
            usePixelRatioInLimitBitrateByPortal:true,
            maxBitrate:{video:-1, audio:-1},   // -1 = بلا سقف
            minBitrate:{video:-1, audio:-1},
            initialBitrate:{video:-1},         // يبدأ بأعلى ما تسمح به السرعة
          },
        },
      });
    }else{newV.src=url;newV.play().catch(()=>{});}
  }else if(fmt==='flv'){
    if(typeof flvjs!=='undefined'&&flvjs.isSupported()){
      PL.flv=flvjs.createPlayer(
        {type:'flv',url},
        {
          enableWorker:true,
          enableStashBuffer:true,     // مخزن مفعّل بدل معطّل
          stashInitialSize:1024,      // مخزن مبدئي أكبر
          autoCleanupSourceBuffer:true,
          lazyLoad:false,             // لا تحميل كسول — حمّل بأقصى سرعة
          lazyLoadMaxDuration:600,
        }
      );
      PL.flv.attachMediaElement(newV);PL.flv.load();PL.flv.play();
      PL.flv.on(flvjs.Events.ERROR,()=>{toast('خطأ في FLV');showBuf(false);});
    }else{toast('المتصفح لا يدعم FLV');showBuf(false);}
      }else if(fmt==='ts'){
        // دمج مشغل mpegts لترجمة ملفات TS
        if(typeof mpegts!=='undefined'&&mpegts.getFeatureList().mseLivePlayback){
          PL.mpegts=mpegts.createPlayer(
            {type:'mpegts', isLive:true, url},
            {
              enableWorker:true,
              lazyLoad:false,
              liveBufferLatencyChasing:true, // تعقب البث الحي لتجنب التقطيع
              liveBufferLatencyMaxLatency: 2
            }
          );
          PL.mpegts.attachMediaElement(newV);PL.mpegts.load();PL.mpegts.play().catch(()=>{});
          PL.mpegts.on(mpegts.Events.ERROR,()=>{toast('خطأ في تشغيل بث TS');showBuf(false);});
        }else{toast('متصفحك لا يدعم مشغل mpegts');showBuf(false);}
      }else{
        // MP4, MKV, WEBM — مشغل مباشر (احترافي ومستقر)
        newV.preload = "auto"; // فرض التحميل المسبق الكامل لمنع التقطيع
    newV.addEventListener('loadedmetadata', ()=>{ newV.play().catch(()=>{}); }, {once:true});
    newV.addEventListener('canplay',        ()=>{ newV.play().catch(()=>{}); }, {once:true});
    
    // Do not reset VOD playback while the browser is buffering.
    newV.addEventListener('stalled', ()=>{
        showBuf(true);
    });

    newV.src=url;
    newV.load();
    newV.play().catch(()=>{});
  }

  newV.volume=PL.vol;newV.muted=PL.muted;
  newV.ontimeupdate=updateProgress;
  newV.onwaiting=()=>showBuf(true);
  newV.onplaying=()=>{showBuf(false);setPlayIcon(false);PL.userPaused=false;};
  newV.onpause=()=>setPlayIcon(true);
  newV.onloadeddata=()=>showBuf(false);
  /* نُخفي مؤشر التحميل بمجرد أن تصبح أول صورة جاهزة — أبكر من loadeddata */
  newV.onloadedmetadata=()=>showBuf(false);
  newV.oncanplay=()=>showBuf(false);
  newV.onerror=()=>{
    showBuf(false);
    if(PL.backupUrl && !PL.usedBackup && url!==PL.backupUrl){
      PL.usedBackup=true;
      toast('تعذّر الرابط الأساسي — جارٍ التبديل للرابط الاحتياطي...');
      try{ initStream(PL.backupUrl, subUrl||''); }catch(_){}
    }else{
      toast('تعذر تحميل الفيديو');
    }
  };
  newV.onended=()=>{
    if(App.currentType==='episode'&&App.currentEpisodeIdx<App.allEpisodes.length-1){
      toast('انتقال للحلقة التالية...');
      setTimeout(()=>navEpisode(1),2000);
    }
    if(PL.m3uEntries.length&&PL.m3uIdx<PL.m3uEntries.length-1)playM3UEntry(PL.m3uIdx+1);
  };

  // FIX: تحديث _lastUrl للـ watchdog
  _lastUrl=url;
}

/* destroyPlayer — تنظيف كامل مع تحرير Blob URLs */
function destroyPlayer(){
  if(PL._liveGuard){ clearInterval(PL._liveGuard); PL._liveGuard=null; }
  if(PL.hls){try{PL.hls.destroy();}catch(e){}PL.hls=null;}
  if(PL.dash){try{PL.dash.reset();}catch(e){}PL.dash=null;}
  if(PL.flv){try{PL.flv.destroy();}catch(e){}PL.flv=null;}
  if(PL.mpegts){try{PL.mpegts.destroy();}catch(e){}PL.mpegts=null;}
  const v=document.getElementById('html5Player');
  if(v){
    v.ontimeupdate=null;v.onwaiting=null;v.onplaying=null;v.onpause=null;
    v.onloadeddata=null;v.onerror=null;v.onended=null;
    try{v.pause();}catch(e){}
    // إزالة tracks وتحرير أي Blob URLs
    const tracks=Array.from(v.querySelectorAll('track'));
    tracks.forEach(t=>{
      try{
        if(t.src && t.src.startsWith('blob:')) URL.revokeObjectURL(t.src);
        v.removeChild(t);
      }catch(e){}
    });
    try{v.removeAttribute('src');v.load();}catch(e){}
  }
  // إعادة ضبط زر الترجمة
  const subBtn=document.getElementById('subBtn');
  if(subBtn){subBtn.style.opacity='0.4';subBtn.style.color='';subBtn.classList.remove('sub-active');}
  PL.subtitleOn=false;
  showBuf(false);
}

/* ════ M3U ════ */
async function parseM3U(urlOrText){
  let text=urlOrText;
  if(urlOrText.startsWith('http')||urlOrText.startsWith('//')){try{const r=await fetch(urlOrText);text=await r.text();}catch(e){toast('تعذر تحميل M3U');return[];}}
  const entries=[];let cur={};
  for(const line of text.split('\n').map(l=>l.trim()).filter(Boolean)){
    if(line.startsWith('#EXTM3U'))continue;
    if(line.startsWith('#EXTINF')){cur={};const ci=line.lastIndexOf(',');cur.name=ci>=0?line.slice(ci+1).trim():'بدون اسم';const lm=line.match(/tvg-logo="([^"]+)"/i);cur.logo=lm?lm[1]:'';const gm=line.match(/group-title="([^"]+)"/i);cur.group=gm?gm[1]:'';}
    else if(!line.startsWith('#')&&(line.startsWith('http')||line.startsWith('/'))){cur.url=line;entries.push({...cur});cur={};}
  }
  return entries;
}
function playM3UEntry(idx){
  if(idx<0||idx>=PL.m3uEntries.length)return;
  try{sessionStorage.setItem('shs_restore',JSON.stringify({type:'m3u',idx:idx,entries:PL.m3uEntries,name:PL.m3uName}));}catch(e){}
  PL.m3uIdx=idx;const e=PL.m3uEntries[idx];
  PL.backupUrl='';PL.usedBackup=false;
  document.getElementById('pChannelName').textContent=e.name;
  document.getElementById('pFmtTag').textContent=fmtLabel(e.url);
  document.getElementById('pBadgeLabel').textContent=isLiveFormat(e.url)?'LIVE':fmtLabel(e.url);
  initStream(e.url,'');
  document.querySelectorAll('.m3u-item').forEach((el,i)=>el.classList.toggle('playing',i===idx));
  toast('▶ '+e.name);
}
function buildM3UPanel(){
  document.getElementById('m3uPanelHead').textContent='قائمة التشغيل ('+PL.m3uEntries.length+')';
  const b=document.getElementById('m3uPanelBody');b.innerHTML='';
  PL.m3uEntries.forEach((e,idx)=>{
    const d=document.createElement('div');d.className='m3u-item'+(idx===PL.m3uIdx?' playing':'');
    const lh=e.logo?`<img class="m3u-item-logo" src="${esc(e.logo)}" loading="lazy">`:`<div class="m3u-item-logo" style="display:flex;align-items:center;justify-content:center">📺</div>`;
    d.innerHTML=`${lh}<div><div class="m3u-item-name">${esc(e.name)}</div><div class="m3u-item-group">${esc(e.group||fmtLabel(e.url))}</div></div>`;
    d.onclick=()=>playM3UEntry(idx);b.appendChild(d);
  });
}

/* ════ EP PANEL ════ */
function buildEpPanel(){
  document.getElementById('epPanelTitle').textContent=App.currentSeriesName;
  const b=document.getElementById('epPanelBody');b.innerHTML='';
  App.allEpisodes.forEach((ep,idx)=>{
    const d=document.createElement('div');d.className='ep-item'+(idx===App.currentEpisodeIdx?' playing':'');
    d.innerHTML=`<div class="ep-item-num">${ep.episode_number}</div><div class="ep-item-info"><div class="ep-item-title">${esc(ep.title)}</div><div style="font-size:.7rem;color:#666">${fmtLabel(ep.stream_url||'')}</div></div><div class="ep-item-play">▶</div>`;
    d.onclick=()=>{openPlayerEpisode(idx);if(window.innerWidth<=768)toggleEpPanel();};
    b.appendChild(d);
  });
}

/* ════ CONTROLS ════ */
function updateProgress(){
  const v=document.getElementById('html5Player');
  if(!v||!v.duration||isNaN(v.duration))return;
  const p=(v.currentTime/v.duration)*100;
  document.getElementById('pFill').style.width=p+'%';
  const cur=ft(v.currentTime),tot=ft(v.duration);
  document.getElementById('pTime').textContent=cur+' / '+tot;
  const ec=document.getElementById('pTimeCur'),et=document.getElementById('pTimeTotal');
  if(ec)ec.textContent=cur;if(et)et.textContent=tot;
}
function seekTo(e){
  const v=document.getElementById('html5Player');
  if(!v||!v.duration||isNaN(v.duration))return;
  const r=document.getElementById('pProgress').getBoundingClientRect();
  v.currentTime=((e.clientX-r.left)/r.width)*v.duration;
  updateProgress();
}
function _syncVolUI(){
  const fill=document.getElementById('volFill');
  const thumb=document.getElementById('volThumb');
  const pct=(PL.muted?0:PL.vol)*100;
  if(fill)fill.style.width=pct+'%';
  if(thumb)thumb.style.right=(100-pct)+'%';
  const ic=document.getElementById('muteIcon');
  if(ic){
    const icon=PL.muted||PL.vol===0?'🔇':PL.vol<0.5?'🔉':'🔊';
    ic.innerHTML=`<span style="font-size:1.2rem">${icon}</span>`;
  }
}
function setVolume(e){
  const r=e.currentTarget.getBoundingClientRect();
  const p=Math.max(0,Math.min(1,(e.clientX-r.left)/r.width));
  const v=document.getElementById('html5Player');
  if(v){v.volume=p;v.muted=(p===0);}
  PL.vol=p;PL.muted=(p===0);_syncVolUI();
}
function changeVol(d){
  const nv=Math.max(0,Math.min(1,PL.vol+d));
  const v=document.getElementById('html5Player');
  if(v){v.volume=nv;v.muted=(nv===0);}
  PL.vol=nv;if(nv>0)PL.muted=false;_syncVolUI();
  toast('الصوت: '+Math.round(nv*100)+'%');
}
function toggleMute(){
  const v=document.getElementById('html5Player');
  PL.muted=!PL.muted;if(v)v.muted=PL.muted;_syncVolUI();
  toast(PL.muted?'كتم الصوت':'تفعيل الصوت');
}
function togglePlay(){
  const v=document.getElementById('html5Player');
  if(!v)return;
  // ── إصلاح التلفاز: نعتمد على نية المستخدم الصريحة (PL.userPaused) بدل v.paused ──
  // لأن التلفاز يجعل v.paused=true مؤقتاً أثناء التقديم/البَفرة، فيختلّ المنطق
  // ويبقى userPaused عالقاً → القائمة لا تختفي. هنا نعكس النية المنطقية لا حالة العنصر.
  if(PL.userPaused){
    // النية: تشغيل
    PL.userPaused=false;
    v.play().catch(()=>{});
  } else {
    // النية: إيقاف
    PL.userPaused=true;
    try{ v.pause(); }catch(e){}
  }
  setPlayIcon(PL.userPaused);
  // إعادة ضبط مؤقّت الإخفاء دائماً بعد التبديل — يضمن اختفاء القائمة تلقائياً
  // حتى لو جاء التبديل بعد تقديم/تأخير على التلفاز.
  showControls();
}
function setPlayIcon(p){
  document.getElementById('playBtn').innerHTML=p?
    '<span class="lcn"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="6 3 20 12 6 21 6 3"/></svg></span>':
    '<span class="lcn"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="4" height="16" x="6" y="4"/><rect width="4" height="16" x="14" y="4"/></svg></span>';
}
function flash(t){
  const el=document.getElementById('pFlash');
  el.innerHTML=`<span style="font-size:2rem">${t==='play'?'▶':'⏸'}</span>`;
  el.classList.add('show');setTimeout(()=>el.classList.remove('show'),400);
}
function skip(s){
  const v=document.getElementById('html5Player');
  if(!v)return;
  v.currentTime=Math.max(0,Math.min(v.currentTime+s,v.duration||0));
  updateProgress();
  showControls(); // إعادة ضبط مؤقّت الإخفاء بعد التقديم/التأخير (مهم للتلفاز)
}
function ft(s){const m=Math.floor(s/60),ss=Math.floor(s%60);return String(m).padStart(2,'0')+':'+String(ss).padStart(2,'0');}

/* ════ SUBTITLE TOGGLE ════ */
function toggleSubtitle(){
  const v=document.getElementById('html5Player');
  if(!v) return;
  const tracks=v.textTracks;
  if(!tracks||!tracks.length){
    toast('لا تتوفر ترجمة');
    return;
  }
  PL.subtitleOn=!PL.subtitleOn;
  for(let i=0;i<tracks.length;i++){
    tracks[i].mode=PL.subtitleOn?'showing':'hidden';
  }
  const btn=document.getElementById('subBtn');
  if(btn){
    btn.style.opacity=PL.subtitleOn?'1':'0.6';
    btn.style.color=PL.subtitleOn?'#ff4d57':'';
  }
  toast(PL.subtitleOn?'✓ الترجمة مفعّلة':'✕ الترجمة مُوقفة');
}

const ENH_MODES=[
  {cls:'',label:'قياسي',msg:'وضع قياسي'},
  {cls:'enh-deblock',label:'DeBlock',msg:'De-Block — إزالة تشوهات البكسل'},
  {cls:'enh-hdr',label:'HDR',msg:'HDR — تحسين الألوان'},
  {cls:'enh-frame',label:'Frame+',msg:'Frame+ — تحسين الوضوح'},
  {cls:'enh-full',label:'Ultra',msg:'Ultra — تحسين شامل'}
];
let _enhIdx=0;
function toggleEnhancements(){
  const v=document.getElementById('html5Player');
  const b=document.getElementById('enhanceBtn');
  const lbl=document.getElementById('enhLabel');
  ENH_MODES.forEach(m=>{if(m.cls&&v)v.classList.remove(m.cls);});
  _enhIdx=(_enhIdx+1)%ENH_MODES.length;
  const mode=ENH_MODES[_enhIdx];
  if(mode.cls&&v)v.classList.add(mode.cls);
  if(lbl)lbl.textContent=mode.label;
  b.classList.toggle('active-magic',_enhIdx>0);
  b.style.opacity=_enhIdx===0?'0.6':'1';
  toast(mode.msg);
}

function showBuf(s){document.getElementById('pBuffer').classList.toggle('show',s);}

function showControls(){
  const r=document.getElementById('playerOverlay');
  const top=document.getElementById('pTop');
  const bot=document.getElementById('pBottom');
  const cen=document.getElementById('pCenter');
  r.classList.remove('idle');
  if(top)top.classList.remove('hide');
  if(bot)bot.classList.remove('hide');
  if(cen)cen.classList.remove('hide');
  clearTimeout(PL.idle);
  const delay=_isTV?6000:4000;
  PL.idle=setTimeout(function(){
    const v=document.getElementById('html5Player');
    if(!v)return;
    // نخفي القوائم طالما المستخدم لم يوقف الفيديو يدوياً.
    // نعتمد على PL.userPaused بدل v.paused لأن التلفاز يجعل v.paused=true
    // مؤقتاً أثناء التقديم/البَفرة فتبقى القوائم ظاهرة بلا داعٍ.
    if(!PL.userPaused&&!PL.epPanelOpen&&!PL.m3uPanelOpen){
      if(top)top.classList.add('hide');
      if(bot)bot.classList.add('hide');
      if(cen)cen.classList.add('hide');
      r.classList.add('idle');
      // مسح تركيز الريموت البصري على التلفاز عند إخفاء القوائم
      if(_isTV&&window._clearPlayerFocus){try{window._clearPlayerFocus();}catch(e){}}
    }
  },delay);
}

function fixPlayerHeight(){const el=document.getElementById('playerOverlay');if(!el)return;el.style.height=window.innerHeight+'px';}

/* ════ PLAYER EVENTS ════ */
document.addEventListener('DOMContentLoaded',function(){
  const wrap=document.getElementById('pvWrap');
  const overlay=document.getElementById('playerOverlay');
  let _lastTap=0;
  wrap.addEventListener('touchstart',function(e){
    const now=Date.now();const diff=now-_lastTap;_lastTap=now;
    if(diff<280&&diff>0){
      e.preventDefault();
      const t=e.changedTouches[0];
      const rect=wrap.getBoundingClientRect();
      const x=t.clientX-rect.left;
      if(x<rect.width/3)skip(-10);else if(x>(rect.width/3)*2)skip(10);else togglePlay();
    }else{showControls();}
  },{passive:false});
  wrap.addEventListener('click',showControls);
  wrap.addEventListener('dblclick',function(e){
    const rect=wrap.getBoundingClientRect();const x=e.clientX-rect.left;
    if(x<rect.width/3)skip(-10);else if(x>(rect.width/3)*2)skip(10);else togglePlay();
  });
  overlay.addEventListener('mousemove',showControls,{passive:true});
  window.addEventListener('resize',fixPlayerHeight,{passive:true});
  window.addEventListener('orientationchange',()=>setTimeout(fixPlayerHeight,300),{passive:true});
  fixPlayerHeight();
});

window.addEventListener('popstate',function(){window._goBack();});

var _fsActive=false, _fsMethod='none';

function _setFsIcon(on){
  const fi=document.getElementById('fsIcon');
  if(!fi)return;
  fi.outerHTML=on?
    '<span class="lcn" id="fsIcon"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 15 6 6m-6-6v4.8m0-4.8h4.8"/><path d="M9 19.8V15m0 0H4.2M9 15l-6 6"/><path d="M15 4.2V9m0 0h4.8M15 9l6-6"/><path d="M9 4.2V9m0 0H4.2M9 9 3 3"/></svg></span>':
    '<span class="lcn" id="fsIcon"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21 21-6-6m6 6v-4.8m0 4.8h-4.8"/><path d="M3 16.2V21m0 0h4.8M3 21l6-6"/><path d="M21 7.8V3m0 0h-4.8M21 3l-6 6"/><path d="M3 7.8V3m0 0h4.8M3 3l6 6"/></svg></span>';
}
function _cssFS(on){
  const ov=document.getElementById('playerOverlay');
  if(on){ov.classList.add('p-native-fs');document.body.style.overflow='hidden';_fsActive=true;_fsMethod='css';}
  else{ov.classList.remove('p-native-fs');document.body.style.overflow='';_fsActive=false;_fsMethod='none';}
}
function _lockL(){try{if(screen.orientation&&typeof screen.orientation.lock==='function')screen.orientation.lock('landscape').catch(()=>{});}catch(e){}}
function _unlockL(){try{if(screen.orientation&&typeof screen.orientation.unlock==='function')screen.orientation.unlock();}catch(e){}}

async function toggleFullscreen(){
  const ov  = document.getElementById('playerOverlay');
  const vid = document.getElementById('html5Player');

  // هل نحن الآن في fullscreen؟
  const inFS = !!(
    document.fullscreenElement       ||
    document.webkitFullscreenElement  ||
    document.mozFullScreenElement     ||
    (_fsActive && _fsMethod === 'css')
  );

  /* ══ دخول fullscreen ══ */
  if(!inFS){

    /* 1. TV حقيقي (Android TV, Smart TV) → CSS fullscreen */
    if(_isTV){
      _cssFS(true);
      _setFsIcon(true);
      return;
    }

    /* 2. iOS Safari → webkitEnterFullscreen على الـ video */
    if(_isIOS){
      try{
        if(vid && vid.webkitEnterFullscreen){
          vid.webkitEnterFullscreen();
          _fsActive = true;
          _fsMethod = 'ios';
          _setFsIcon(true);
        }
      }catch(e){}
      return;
    }

    /* 3. كمبيوتر / Android Mobile → Fullscreen API على الـ overlay */
    const req = ov.requestFullscreen
             || ov.webkitRequestFullscreen
             || ov.mozRequestFullScreen
             || ov.msRequestFullscreen;

    if(req){
      try{
        await req.call(ov);
        _fsActive = true;
        _fsMethod = 'api';
        _setFsIcon(true);
        // قفل landscape على الموبايل فقط
        if(_UA.isAndroidMobile) _lockL();
      }catch(err){
        // Fullscreen API رفض (مثل iframe sandbox) → CSS fallback
        _cssFS(true);
        _setFsIcon(true);
      }
    }else{
      // المتصفح لا يدعم API أصلاً → CSS
      _cssFS(true);
      _setFsIcon(true);
    }

  /* ══ خروج من fullscreen ══ */
  }else{

    _setFsIcon(false);

    /* TV أو CSS mode */
    if(_fsMethod === 'css' || _isTV){
      _cssFS(false);
      return;
    }

    /* iOS */
    if(_fsMethod === 'ios'){
      // iOS يخرج تلقائياً عند الضغط على زر الـ video
      _fsActive = false;
      _fsMethod = 'none';
      return;
    }

    /* API (كمبيوتر / موبايل) */
    _cssFS(false); // أزل CSS class احتياطاً
    _unlockL();
    try{
      const exit = document.exitFullscreen
                || document.webkitExitFullscreen
                || document.mozCancelFullScreen
                || document.msExitFullscreen;
      if(exit && (document.fullscreenElement || document.webkitFullscreenElement)){
        await exit.call(document);
      }
      _fsActive = false;
      _fsMethod = 'none';
    }catch(e){
      _fsActive = false;
      _fsMethod = 'none';
    }
  }
}

(function(){
  function onFSChange(){
    const isFS=!!(
      document.fullscreenElement      ||
      document.webkitFullscreenElement ||
      document.mozFullScreenElement
    );
    if(isFS){
      _fsActive=true;
      _setFsIcon(true);
      // قفل landscape على الموبايل فقط لا الكمبيوتر
      if(_UA.isAndroidMobile) _lockL();
    }else{
      // خرج من fullscreen (زر Esc أو زر المتصفح)
      if(_fsMethod!=='css'){
        _fsActive=false;
        _fsMethod='none';
        _cssFS(false);
        _unlockL();
      }
      _setFsIcon(false);
    }
  }
  document.addEventListener('fullscreenchange',      onFSChange);
  document.addEventListener('webkitfullscreenchange',onFSChange);
  document.addEventListener('mozfullscreenchange',   onFSChange);
})();

/* ════ TV CONTROL ════ */
(function(){
  var _idx=-1,_btns=[];
  window._playerTvFocusActive=false;
  function getBtns(){
    return Array.from(document.querySelectorAll('#playerOverlay .p-btn,#playerOverlay .p-play-btn,#playerOverlay .p-seek-btn'))
      .filter(b=>b.offsetParent!==null&&b.style.display!=='none');
  }
  function applyFocus(idx){
    _btns=getBtns();
    _btns.forEach(b=>{b.style.outline='';b.style.background='';b.style.transform='';b.style.boxShadow='';});
    _idx=(idx>=0&&idx<_btns.length)?idx:-1;
    if(_idx<0){window._playerTvFocusActive=false;return;}
    const b=_btns[_idx];
    b.style.outline='3px solid #fff';b.style.background='rgba(229,9,20,.65)';
    b.style.transform='scale(1.25)';b.style.boxShadow='0 0 0 5px rgba(229,9,20,.35)';
    window._playerTvFocusActive=true;
  }
  function clearAll(){getBtns().forEach(b=>{b.style.outline='';b.style.background='';b.style.transform='';b.style.boxShadow='';});_idx=-1;_btns=[];window._playerTvFocusActive=false;}
  window._clearPlayerFocus=clearAll;
  function activate(){showControls();const all=getBtns();const pi=all.findIndex(b=>b.id==='playBtn');applyFocus(pi>=0?pi:Math.floor(all.length/2));}
  document.addEventListener('keydown',function(e){
    if(!document.getElementById('playerOverlay').classList.contains('active'))return;
    var kc=e.keyCode||e.which||0,ks=e.key||'';
    if(kc===116 || ks==='F5' || (e.ctrlKey && (kc===82 || ks==='r' || ks==='R'))){
      e.preventDefault();
      e.stopPropagation();
      var u = (typeof _lastUrl!=='undefined' && _lastUrl) || (typeof _hardReloadUrl!=='undefined' && _hardReloadUrl);
      if(u && typeof _hardReloadStream === 'function') _hardReloadStream(u);
      return;
    }
    if(kc===27||kc===8||kc===4||kc===10009||ks==='Escape'||ks==='BrowserBack'){
      e.preventDefault();e.stopPropagation();
      var isFS=!!(document.fullscreenElement||document.webkitFullscreenElement||_fsActive);
      if(isFS){toggleFullscreen();}else{clearAll();closePlayer();}
      return;
    }
    if(ks==='MediaPlayPause'||kc===179||kc===415){e.preventDefault();togglePlay();showControls();return;}
    if(ks==='FastFwd'||kc===417){e.preventDefault();skip(30);return;}
    if(ks==='Rewind'||kc===412){e.preventDefault();skip(-30);return;}
    if(kc===175||kc===447){e.preventDefault();changeVol(.1);return;}
    if(kc===174||kc===448){e.preventDefault();changeVol(-.1);return;}
    if(kc===173||kc===449){e.preventDefault();toggleMute();return;}
    if(ks==='ChannelUp'||kc===427){e.preventDefault();if(App.currentType==='episode')navEpisode(1);return;}
    if(ks==='ChannelDown'||kc===428){e.preventDefault();if(App.currentType==='episode')navEpisode(-1);return;}
    var L=(ks==='ArrowLeft'||kc===37||kc===21);
    var R=(ks==='ArrowRight'||kc===39||kc===22);
    var U=(ks==='ArrowUp'||kc===38||kc===19);
    var D=(ks==='ArrowDown'||kc===40||kc===20);
    var OK=(ks==='Enter'||ks==='Select'||kc===13||kc===23);
    if(!L&&!R&&!U&&!D&&!OK)return;
    e.preventDefault();
    var hidden=document.getElementById('pBottom').classList.contains('hide');
    if(hidden||_idx<0){activate();return;}
    if(OK){var c=getBtns()[_idx];if(c)c.click();return;}
    var fresh=getBtns(),len=fresh.length;
    if(R&&_idx<len-1){_btns=fresh;applyFocus(_idx+1);}
    if(L&&_idx>0){_btns=fresh;applyFocus(_idx-1);}
    if(U)changeVol(.1);if(D)changeVol(-.1);
  },true);
  var _oc=window.closePlayer;
  window.closePlayer=function(){clearAll();if(_oc)_oc.apply(this,arguments);};
})();

/* ════ TV NAVIGATION (خارج المشغل) ════ */
var _tvFocus=null;
function _tvSetFocus(el){
  if(_tvFocus){_tvFocus.classList.remove('tv-focus');_tvFocus.style.outline='';}
  _tvFocus=el;if(!el)return;
  el.classList.add('tv-focus');
  el.scrollIntoView({behavior:'smooth',block:'center'});
  if(el.tagName!=='INPUT')try{el.focus({preventScroll:true});}catch(e){}
}
document.addEventListener('keydown',function(e){
  if(document.getElementById('playerOverlay').classList.contains('active'))return;
  if(document.getElementById('tmdbInfoM').classList.contains('open'))return;
  var ks=e.key||'',kc=e.keyCode||e.which||0;
  var K={UP:ks==='ArrowUp'||kc===38||kc===19,DOWN:ks==='ArrowDown'||kc===40||kc===20,LEFT:ks==='ArrowLeft'||kc===37||kc===21,RIGHT:ks==='ArrowRight'||kc===39||kc===22,OK:ks==='Enter'||ks==='Select'||ks===' '||kc===13||kc===23,BACK:ks==='Escape'||ks==='BrowserBack'||kc===27||kc===4||kc===10009||kc===8};
  if(!K.UP&&!K.DOWN&&!K.LEFT&&!K.RIGHT&&!K.OK&&!K.BACK)return;
  if(K.BACK){e.preventDefault();window._goBack();return;}
  var sel='.ch-card,.sr-card,.ep-card,.back-btn,.nav-btn,.info-action-btn,#searchInput,.ep-item,.m3u-item';
  var focusables=Array.from(document.querySelectorAll(sel)).filter(function(el){
    var r=el.getBoundingClientRect();
    return r.width>0&&r.height>0&&!el.closest('.hidden');
  });
  if(!focusables.length)return;
  if(K.OK){if(_tvFocus&&focusables.includes(_tvFocus)){if(_tvFocus.tagName==='INPUT'){try{_tvFocus.focus();}catch(e){}}else _tvFocus.click();e.preventDefault();}return;}
  e.preventDefault();
  if(!_tvFocus||!focusables.includes(_tvFocus)){_tvSetFocus(focusables[0]);return;}
  var cur=_tvFocus.getBoundingClientRect();var best=null,bestScore=Infinity;
  focusables.forEach(function(el){
    if(el===_tvFocus)return;
    var r=el.getBoundingClientRect();var cx=r.left+r.width/2,cy=r.top+r.height/2,ox=cur.left+cur.width/2,oy=cur.top+cur.height/2;
    var dx=cx-ox,dy=cy-oy,ok=false;
    if(K.RIGHT&&dx>20)ok=true;if(K.LEFT&&dx<-20)ok=true;if(K.DOWN&&dy>20)ok=true;if(K.UP&&dy<-20)ok=true;
    if(!ok)return;
    var primary=(K.UP||K.DOWN)?Math.abs(dy):Math.abs(dx),secondary=(K.UP||K.DOWN)?Math.abs(dx):Math.abs(dy);
    var score=primary+secondary*2;if(score<bestScore){bestScore=score;best=el;}
  });
  if(best)_tvSetFocus(best);
});

(function(){
  var s=document.createElement('style');
  s.textContent='.tv-focus{outline:3px solid #fff!important;outline-offset:4px!important;transform:scale(1.08) translateY(-4px)!important;z-index:999!important;border-color:var(--red)!important;box-shadow:0 15px 40px rgba(0,0,0,.9),0 0 35px rgba(229,9,20,.95)!important}.back-btn.tv-focus{outline:3px solid var(--red)!important;background:rgba(229,9,20,.25)!important;border-color:var(--red)!important;color:#fff!important}.nav-btn.tv-focus{outline:3px solid #fff!important;background:var(--red)!important;color:#fff!important}';
  document.head.appendChild(s);
})();

(function(){
  function applyTabindex(){
    document.querySelectorAll('.ch-card,.sr-card,.ep-card,.back-btn,.nav-btn,.ep-item,.m3u-item,.info-action-btn,#searchInput').forEach(function(el){if(!el.getAttribute('tabindex'))el.setAttribute('tabindex','0');});
  }
  if(window.MutationObserver){var obs=new MutationObserver(function(ms){var changed=false;ms.forEach(function(m){if(m.addedNodes.length)changed=true;});if(changed){clearTimeout(obs._t);obs._t=setTimeout(applyTabindex,150);}});obs.observe(document.body,{childList:true,subtree:true});}
  setTimeout(applyTabindex,600);setTimeout(applyTabindex,2000);
})();

/* ════ GESTURES + BACK NAVIGATION ════ */
(function(){
  window._goBack=function(){
    if(document.getElementById('playerOverlay').classList.contains('active')){
      var isFS=!!(document.fullscreenElement||document.webkitFullscreenElement||_fsActive);
      if(isFS){toggleFullscreen();return;}
      closePlayer();return;
    }
    var tmdb=document.getElementById('tmdbInfoM');if(tmdb&&tmdb.classList.contains('open')){closeTmdbModal();return;}
    var panels=['epPanel','m3uPanel','favPanel','notifPanel'];
    for(var i=0;i<panels.length;i++){
      if(document.getElementById(panels[i]).classList.contains('open')){
        document.getElementById(panels[i]).classList.remove('open');
        document.getElementById('panelOverlay').classList.remove('show');
        document.body.style.overflow='';return;
      }
    }
    if(!document.getElementById('epSection').classList.contains('hidden')){backFromEpisodesToHome();return;}
    if(!document.getElementById('searchViewSection').classList.contains('hidden')){clearSearchAndGoHome();return;}
    if(!document.getElementById('categoryViewSection').classList.contains('hidden')){closeCategoryView();return;}
  };
  var gsx=0,gsy=0,gActive=false;
  var EDGE=0.18,MIN_X=65,MAX_Y=65;
  document.addEventListener('touchstart',function(e){var t=e.changedTouches[0];gsx=t.screenX;gsy=t.screenY;var w=window.innerWidth;gActive=(gsx<w*EDGE)||(gsx>w*(1-EDGE));},{passive:true});
  document.addEventListener('touchend',function(e){if(!gActive)return;var t=e.changedTouches[0];var dx=t.screenX-gsx,dy=Math.abs(t.screenY-gsy);gActive=false;if(Math.abs(dx)<MIN_X||dy>MAX_Y)return;window._goBack();},{passive:true});

  document.addEventListener('DOMContentLoaded',function(){
    var wrap=document.getElementById('pvWrap');if(!wrap)return;
    var sx=0,sy=0,st=0;
    wrap.addEventListener('touchstart',function(e){var t=e.changedTouches[0];sx=t.clientX;sy=t.clientY;st=Date.now();},{passive:true});
    wrap.addEventListener('touchend',function(e){
      if(!document.getElementById('playerOverlay').classList.contains('active'))return;
      var t=e.changedTouches[0],dx=t.clientX-sx,dy=Math.abs(t.clientY-sy),dt=Date.now()-st;
      if(Math.abs(dx)>60&&dy<50&&dt<400){if(dx>0)skip(-15);else skip(15);}
    },{passive:true});
  });

  var _origOpenSeries=window.openSeriesEpisodes;
  window.openSeriesEpisodes=function(){history.pushState({depth:'episodes'},'');return _origOpenSeries.apply(this,arguments);};
})();

/* ════ WATCHDOG ════ */
let _lastUrl='',_stallTicks=0,_watchdogInt=null,_bgPauseTimer=null,_hiddenAt=0;
function _watchdogStart(){
  if(_watchdogInt)clearInterval(_watchdogInt);
  _stallTicks=0;let _prev=-1;
  _watchdogInt=setInterval(()=>{
    const v=document.getElementById('html5Player');
    const overlay=document.getElementById('playerOverlay');
    if(!v||!overlay||!overlay.classList.contains('active')){clearInterval(_watchdogInt);_watchdogInt=null;return;}
    if(v.paused||v.ended){_stallTicks=0;return;}
    // القناة "ماتت" تماماً (readyState=0 ومستمرة) — نعيد تشغيلها تلقائياً
    if(v.readyState===0){
      _stallTicks++;
      if(_stallTicks>=4){_stallTicks=0;const u=_lastUrl||_hardReloadUrl;if(u)_hardReloadStream(u);}
      _prev=v.currentTime;return;
    }
    if(v.currentTime===_prev&&v.readyState<3){
      _stallTicks++;
      // المرحلة 1 (تجمّد قصير): استرداد خفيف عبر hls.js دون إعادة بناء — بلا قفزة مرئية
      if(_stallTicks===3 && PL.hls){
        try{ PL.hls.startLoad(); }catch(_){}
      }
      // المرحلة 2 (تجمّد مستمر): إعادة تشغيل كاملة تلقائية لا تستسلم
      if(_stallTicks>=6){_stallTicks=0;const u=_lastUrl||_hardReloadUrl;if(u)_hardReloadStream(u);}
    }else _stallTicks=0;
    _prev=v.currentTime;
  },2000);
}
function _watchdogStop(){if(_watchdogInt){clearInterval(_watchdogInt);_watchdogInt=null;}}
document.addEventListener('play',e=>{if(e.target&&e.target.id==='html5Player'){if(e.target.src&&e.target.src!==window.location.href)_lastUrl=e.target.src;_watchdogStart();}},true);
document.addEventListener('pause',e=>{if(e.target&&e.target.id==='html5Player')_watchdogStop();},true);
document.addEventListener('ended',e=>{if(e.target&&e.target.id==='html5Player')_watchdogStop();},true);

/* ════ RESUME POSITION ════ */
function _resumeKey(){if(App.currentType==='episode')return'resume_ep_'+App.currentSeriesId+'_'+App.currentEpisodeIdx;return null;}
function _resumeSave(){
  const k=_resumeKey();if(!k)return;
  const v=document.getElementById('html5Player');
  if(!v||!v.duration||isNaN(v.duration)||v.currentTime<5)return;
  if(v.duration-v.currentTime<10){_resumeDelete();return;}
  try{localStorage.setItem(k,JSON.stringify({t:Math.floor(v.currentTime),d:Math.floor(v.duration),ts:Date.now()}));}catch(e){}
}
function _resumeGet(){const k=_resumeKey();if(!k)return null;try{const raw=localStorage.getItem(k);if(!raw)return null;const obj=JSON.parse(raw);if(Date.now()-obj.ts>30*24*3600*1000){localStorage.removeItem(k);return null;}return obj;}catch(e){return null;}}
function _resumeDelete(){const k=_resumeKey();if(k)try{localStorage.removeItem(k);}catch(e){}}
function _resumeOffer(pos,dur){
  const old=document.getElementById('resumeBar');if(old)old.remove();
  const bar=document.createElement('div');bar.id='resumeBar';
  const pct=Math.round((pos/dur)*100);
  bar.innerHTML=`<div style="flex:1;min-width:0"><span style="font-weight:800;color:#fff">استئناف من ${ft(pos)}</span><div style="height:3px;background:rgba(255,255,255,.15);border-radius:99px;margin-top:6px"><div style="width:${pct}%;height:100%;background:var(--red);border-radius:99px"></div></div></div><button id="resumeYes" style="background:var(--red);color:#fff;border:none;padding:8px 18px;border-radius:99px;font-weight:800;font-size:.85rem;cursor:pointer;font-family:inherit;flex-shrink:0">استئناف</button><button id="resumeNo" style="background:rgba(255,255,255,.1);color:#ccc;border:none;padding:8px 14px;border-radius:99px;font-weight:700;font-size:.85rem;cursor:pointer;font-family:inherit;flex-shrink:0">من البداية</button>`;
  bar.style.cssText='position:absolute;bottom:110px;left:4%;right:4%;z-index:9999;background:rgba(10,10,10,.97);border:1px solid rgba(255,255,255,.1);border-right:3px solid var(--red);border-radius:12px;padding:14px 18px;display:flex;align-items:center;gap:12px;box-shadow:0 8px 30px rgba(0,0,0,.8);direction:rtl';
  document.getElementById('playerOverlay').appendChild(bar);
  document.getElementById('resumeYes').onclick=function(){
    const v=document.getElementById('html5Player');
    if(v){if(v.readyState>=2)v.currentTime=pos;else v.addEventListener('canplay',function s(){v.removeEventListener('canplay',s);v.currentTime=pos;});}
    bar.remove();
  };
  document.getElementById('resumeNo').onclick=function(){_resumeDelete();bar.remove();};
  setTimeout(()=>{if(bar.parentNode)bar.remove();},12000);
}
let _resumeInterval=null;
function _resumeStartSaving(){if(_resumeInterval)clearInterval(_resumeInterval);_resumeInterval=setInterval(_resumeSave,5000);}
function _resumeStopSaving(){if(_resumeInterval){clearInterval(_resumeInterval);_resumeInterval=null;}_resumeSave();}
document.addEventListener('play',e=>{if(e.target&&e.target.id==='html5Player')_resumeStartSaving();},true);
document.addEventListener('pause',e=>{if(e.target&&e.target.id==='html5Player')_resumeSave();},true);
document.addEventListener('ended',e=>{if(e.target&&e.target.id==='html5Player'){_resumeStopSaving();_resumeDelete();}},true);

document.getElementById('playerOverlay').addEventListener('animationend',function(e){
  if(e.animationName!=='playerSlideIn'||App.currentType!=='episode')return;
  setTimeout(()=>{
    const v=document.getElementById('html5Player');
    const s=_resumeGet();if(!s||s.t<5)return;
    if(v.duration&&!isNaN(v.duration))_resumeOffer(s.t,v.duration);
    else v.addEventListener('loadedmetadata',function m(){v.removeEventListener('loadedmetadata',m);const s2=_resumeGet();if(s2&&s2.t>=5)_resumeOffer(s2.t,v.duration||s2.d);});
  },600);
});

/* ════ VISIBILITY CHANGE ════ */
document.addEventListener('visibilitychange',function(){
  const overlay=document.getElementById('playerOverlay');const v=document.getElementById('html5Player');
  if(!overlay||!overlay.classList.contains('active')||!v)return;
  if(document.hidden){
    _hiddenAt=Date.now();
    _bgPauseTimer=setTimeout(()=>{if(document.hidden&&!v.paused){try{v.pause();}catch(e){}toast('البث متوقف — التبويب مخفي');}},30000);
  }else{
    if(_bgPauseTimer){clearTimeout(_bgPauseTimer);_bgPauseTimer=null;}
    const ms=Date.now()-_hiddenAt;
    if(v.paused&&ms>800){
      if(ms>120000&&_lastUrl){toast('استئناف البث...');initStream(_lastUrl,'');}
      else{v.play().catch(()=>{});}
    }
  }
});

window.addEventListener('beforeunload',(e)=>{
  var ov = document.getElementById('playerOverlay');
  if(ov && ov.classList.contains('active')){
    e.preventDefault();
    e.returnValue = '';
    return;
  }
  try{
    if(typeof _watchdogStop==='function')_watchdogStop();
    const v=document.getElementById('html5Player');
    if(v){try{v.pause();}catch(e){}}
    if(PL.hls){try{PL.hls.destroy();}catch(e){}}
    if(PL.dash){try{PL.dash.reset();}catch(e){}}
    if(PL.flv){try{PL.flv.destroy();}catch(e){}}
  }catch(e){}
});

/* ════ SCREENSAVER ════ */
(function(){
  let nxIdleTime=0,nxSlideLoop=null,nxIdx=0,nxList=[];
  const NX_IDLE=60,NX_SLIDE=10000;
  const scr=document.getElementById('nxScreensaver');
  const bg=document.getElementById('nxBg');
  const wrap=document.getElementById('nxWrap');
  const pImg=document.getElementById('nxImg');
  const pTitle=document.getElementById('nxTitle');
  const pMatch=document.getElementById('nxMatchBadge');
  const pYear=document.getElementById('nxYear');
  function collect(){
    let pool=[];
    if(typeof MyNotifsQueue!=='undefined')MyNotifsQueue.forEach(o=>{if(o.img&&!o.img.includes('undefined'))pool.push({src:o.img,name:o.name});});
    if(pool.length<5)document.querySelectorAll('.sr-card img,.ch-card img').forEach(img=>{if(img.src&&img.style.display!=='none'){const n=img.closest('.sr-card,.ch-card')?.querySelector('.sr-name,.ch-name');pool.push({src:img.src,name:n?n.textContent:''});}});
    return pool.filter((o,i,a)=>a.findIndex(x=>x.src===o.src)===i);
  }
  function slide(){
    if(!nxList.length)return;if(nxIdx>=nxList.length)nxIdx=0;
    wrap.classList.add('nx-faded');
    setTimeout(()=>{
      const c=nxList[nxIdx];bg.style.backgroundImage=`url("${c.src}")`;pImg.src=c.src;pTitle.textContent=c.name||'';
      pMatch.textContent='المطابقة '+(Math.floor(Math.random()*12)+88)+'%';pYear.textContent=(Math.floor(Math.random()*4)+2021);
      wrap.classList.remove('nx-faded');
    },800);nxIdx++;
  }
  function launch(){
    const overlay=document.getElementById('playerOverlay');
    if(overlay?.classList.contains('active'))return;
    nxList=collect();if(!nxList.length)return;
    nxIdx=Math.floor(Math.random()*nxList.length);scr.classList.add('nx-active');slide();
    if(nxSlideLoop)clearInterval(nxSlideLoop);nxSlideLoop=setInterval(slide,NX_SLIDE);
  }
  function kill(){scr.classList.remove('nx-active');if(nxSlideLoop)clearInterval(nxSlideLoop);setTimeout(()=>{pImg.src='';bg.style.backgroundImage='';},1000);nxIdleTime=0;}
  setInterval(()=>{if(!document.getElementById('playerOverlay')?.classList.contains('active')){nxIdleTime++;if(nxIdleTime>=NX_IDLE&&!scr.classList.contains('nx-active'))launch();}else nxIdleTime=0;},1000);
  ['mousemove','mousedown','keydown','touchstart','scroll','click'].forEach(sig=>document.addEventListener(sig,kill,{passive:true}));
})();

/* ════ SITE MUSIC PLAYER ════ */
(function(){
  const INTERO_URL='/iptv/intero.mp3';
  let siteMusic=new Audio(INTERO_URL);
  siteMusic.loop=true;
  /* ⚠️ أثقل عيب أداء في الواجهة كان هنا:
     ملف intero.mp3 حجمه 13.8 ميجابايت، وقيمة preload الافتراضية في
     new Audio() هي "auto" — أي أن المتصفح كان يبدأ تنزيل الـ13.8 ميجا
     كاملة عند فتح الصفحة الرئيسية، لكل زائر، حتى لو لم يشغّل الموسيقى
     أبداً. وهذا التنزيل يزاحم بث الفيديو على عرض النطاق فيسبّب تقطيعاً
     وبطئاً في بداية المشاهدة.
     الآن: لا يُنزَّل شيء حتى يضغط المستخدم زر الموسيقى فعلاً. */
  siteMusic.preload='none';
  let isMusicPlaying=false;
  function initSiteMusic(){
    let saved=localStorage.getItem('shashety_music_play');
    if(saved==='1'){let pp=siteMusic.play();if(pp!==undefined)pp.then(()=>{isMusicPlaying=true;updateSiteMusicUI(true);}).catch(()=>{isMusicPlaying=false;updateSiteMusicUI(false);});}
    else updateSiteMusicUI(false);
  }
  /* مع preload='none' لا يبدأ التحميل إلا عند أول تشغيل فعلي */
  function playSiteMusic(){if(siteMusic.preload==='none'){siteMusic.preload='auto';try{siteMusic.load();}catch(e){}}siteMusic.play().then(()=>{isMusicPlaying=true;localStorage.setItem('shashety_music_play','1');updateSiteMusicUI(true);}).catch(()=>{isMusicPlaying=false;localStorage.setItem('shashety_music_play','0');updateSiteMusicUI(false);});}
  function pauseSiteMusic(){siteMusic.pause();isMusicPlaying=false;localStorage.setItem('shashety_music_play','0');updateSiteMusicUI(false);}
  function toggleSiteMusic(){if(isMusicPlaying)pauseSiteMusic();else playSiteMusic();}
  function updateSiteMusicUI(playing){
    const btn=document.getElementById('musicMiniBtn');const eq=document.getElementById('musicEq');
    if(!btn||!eq)return;
    if(playing){btn.classList.add('playing');eq.classList.remove('paused');}
    else{btn.classList.remove('playing');eq.classList.add('paused');}
  }
  document.addEventListener('play',function(e){if(e.target&&e.target.id==='html5Player'){if(isMusicPlaying){siteMusic.dataset.wasPlaying='1';siteMusic.pause();}}},true);
  document.addEventListener('ended',function(e){if(e.target&&e.target.id==='html5Player'){if(siteMusic.dataset.wasPlaying==='1'){delete siteMusic.dataset.wasPlaying;siteMusic.play().catch(()=>{});}}},true);
  document.addEventListener('pause',function(e){if(e.target&&e.target.id==='html5Player'){if(siteMusic.dataset.wasPlaying==='1'){delete siteMusic.dataset.wasPlaying;siteMusic.play().catch(()=>{});}}},true);
  window.toggleSiteMusic=toggleSiteMusic;
  document.addEventListener('DOMContentLoaded',()=>setTimeout(initSiteMusic,1000));
  if(document.readyState==='complete'||document.readyState==='interactive')setTimeout(initSiteMusic,1000);
})();

updateNotifBadge();
document.addEventListener('DOMContentLoaded',loadAndBuildNetflixHome);
</script>
