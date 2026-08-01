'use strict';

/* ════ SMART CACHE ════ */
(function(){
  const orig=window.fetch;
  window.fetch=async function(){
    const url=arguments[0];
    if(typeof url==='string'&&url.includes('api.php')&&url.includes('action=all_content')){
      const k='sc_'+url;
      const cached=sessionStorage.getItem(k);
      if(cached)return new Response(cached,{status:200,headers:new Headers({'Content-Type':'application/json'})});
      try{
        const r=await orig.apply(this,arguments);
        r.clone().text().then(t=>{try{sessionStorage.setItem(k,t);}catch(e){}});
        return r;
      }catch(e){return orig.apply(this,arguments);}
    }
    return orig.apply(this,arguments);
  };
})();

/* ════ APP STATE ════ */
const App={
  allContent:[],cats:[],
  currentType:'',currentSeriesId:0,currentSeriesName:'',allEpisodes:[],
  currentEpisodeIdx:-1,
  license: window._APP_LICENSE_EXP
};

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
(function(){
  const overlay=document.getElementById('devtoolsOverlay'),lockIcon=document.getElementById('lockIcon');
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

/* ════ FAVORITES ════ */
let MyFavs={channels:[],series:[]};
try{const s=localStorage.getItem('shashety_favs_v2');if(s){const p=JSON.parse(s);if(p&&Array.isArray(p.channels)&&Array.isArray(p.series))MyFavs=p;}}catch(e){}
function saveFavs(){try{localStorage.setItem('shashety_favs_v2',JSON.stringify(MyFavs));}catch(e){toast('تعذر حفظ المفضلة');}}
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
    d.onclick=()=>{if(item.ftype==='channels')openPlayerChannel({id:item.id,name:item.name,stream_url:item.stream_url,subtitle_url:item.subtitle_url});else openSeriesEpisodes(item.id,item.name);};
    b.appendChild(d);
  });
}

/* ════ NOTIFICATIONS ════ */
const PendingNotifsKey='shashety_notifs_v4';
let MyNotifsQueue=[];
try{MyNotifsQueue=JSON.parse(localStorage.getItem(PendingNotifsKey)||'[]');}catch(e){}
function updateNotifBadge(){const b=document.getElementById('notifBadge');if(b)b.style.display=MyNotifsQueue.length>0?'block':'none';}
async function syncNotifications(cats){
  const SK='shashety_sync_v4';
  const isFirst=!localStorage.getItem(SK);
  let state=JSON.parse(localStorage.getItem(SK)||'{}');
  let discovered=[];
  for(const cat of cats){
    const cid=cat.id;
    if(!state[cid])state[cid]={srSeen:[],chSeen:[],srCount:0,chCount:0};
    const st=state[cid];
    const curSr=parseInt(cat.series_count||0),curCh=parseInt(cat.channel_count||0);
    if(curSr>st.srCount||isFirst){
      try{const r=await fetch('api.php?action=series&category_id='+cid);const d=await r.json();
        (d.series||[]).forEach(s=>{if(!st.srSeen.includes(s.id)){if(!isFirst)discovered.push({id:s.id,type:'series',name:s.name,img:s.poster_url||'',catName:cat.name});st.srSeen.push(s.id);}});
        st.srCount=curSr;}catch(e){}
    }
    if(curCh>st.chCount||isFirst){
      try{const r=await fetch('api.php?action=channels&category_id='+cid);const d=await r.json();
        (d.channels||[]).forEach(c=>{if(!st.chSeen.includes(c.id)){if(!isFirst)discovered.push({id:c.id,type:'channel',name:c.name,img:c.logo_url||'',catName:cat.name,streamUrl:c.stream_url,subUrl:c.subtitle_url});st.chSeen.push(c.id);}});
        st.chCount=curCh;}catch(e){}
    }
  }
  localStorage.setItem(SK,JSON.stringify(state));
  if(discovered.length){discovered.forEach(nd=>{if(!MyNotifsQueue.some(x=>String(x.id)===String(nd.id)))MyNotifsQueue.unshift(nd);});localStorage.setItem(PendingNotifsKey,JSON.stringify(MyNotifsQueue));}
  updateNotifBadge();
}
function buildNotifPanel(){
  const b=document.getElementById('notifPanelBody');b.innerHTML='';
  if(!MyNotifsQueue.length){b.innerHTML='<div style="text-align:center;padding:40px;color:var(--text-muted)">لا توجد إشعارات</div>';return;}
  MyNotifsQueue.forEach(item=>{
    const d=document.createElement('div');d.className='m3u-item';
    d.style.cssText='background:#1a1a1a;padding:12px;border:1px solid rgba(229,9,20,.15);border-radius:10px;margin-bottom:8px;position:relative;align-items:flex-start';
    const ph=item.img?`<img src="${esc(item.img)}" style="width:48px;height:68px;object-fit:cover;border-radius:6px;flex-shrink:0;background:#222">`:`<div style="width:48px;height:68px;display:flex;align-items:center;justify-content:center;background:#222;border-radius:6px;flex-shrink:0;color:#666;font-size:1.4rem">${item.type==='channel'?'📺':'🎬'}</div>`;
    const ap=`openFromNotif('${item.id}','${item.type}','${escA(item.name)}','${escA(item.streamUrl||'')}','${escA(item.subUrl||'')}')`;
    d.innerHTML=`${ph}<div style="flex:1;min-width:0"><div style="font-weight:bold;font-size:.88rem;color:#fff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:4px">${esc(item.name)}</div><div style="font-size:.7rem;color:var(--text-dim);margin-bottom:8px">في <span style="color:#B36BFF">${esc(item.catName||'')}</span></div><button onclick="event.stopPropagation();${ap}" style="background:var(--red);color:#fff;border:none;padding:3px 10px;border-radius:6px;font-size:.74rem;font-weight:700;cursor:pointer">▶ تشغيل</button></div><button onclick="event.stopPropagation();removeNotif('${item.id}')" style="position:absolute;top:8px;left:8px;background:rgba(255,255,255,.07);color:#ccc;border:none;border-radius:50%;width:22px;height:22px;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:.65rem">✕</button>`;
    b.appendChild(d);
  });
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
  return 'hls';
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
function getTmdbKey(){const sk=window._TMDB_API_KEY;if(sk.trim())return sk.trim();return localStorage.getItem('tmdb_api_key')||null;}
async function showTmdbInfoClient(query,defaultType){
  const key=getTmdbKey();const modal=document.getElementById('tmdbInfoM');const body=document.getElementById('tmdbInfoBody');
  modal.classList.add('open');document.body.style.overflow='hidden';
  if(!key){body.innerHTML='<div style="text-align:center;padding:40px;color:var(--text-muted)">ميزة التفاصيل غير مفعلة</div>';return;}
  body.innerHTML='<div style="text-align:center;padding:40px;color:var(--text-muted)">جاري الجلب...</div>';
  try{
    const cq=query.replace(/(1080p|720p|4k|fhd|hd|ar|en)/gi,'').trim();
    const sr=await fetch(`https://api.themoviedb.org/3/search/multi?api_key=${key}&query=${encodeURIComponent(cq)}&language=ar`);
    if(sr.status===401){body.innerHTML='<div style="text-align:center;padding:40px;color:#ff4d57">مفتاح API غير صحيح</div>';return;}
    const sd=await sr.json();
    if(!sd.results||!sd.results.length){body.innerHTML='<div style="text-align:center;padding:40px;color:var(--text-muted)">لم يتم العثور على معلومات</div>';return;}
    const item=sd.results.find(i=>i.media_type==='movie'||i.media_type==='tv')||sd.results[0];
    const type=item.media_type||defaultType;
    let d=await(await fetch(`https://api.themoviedb.org/3/${type}/${item.id}?api_key=${key}&language=ar`)).json();
    if(!d.overview){const en=await(await fetch(`https://api.themoviedb.org/3/${type}/${item.id}?api_key=${key}&language=en-US`)).json();d.overview=en.overview;}
    const title=d.title||d.name||cq;const poster=d.poster_path?`https://image.tmdb.org/t/p/w300${d.poster_path}`:'';
    const year=(d.release_date||d.first_air_date||'').substring(0,4);const rating=d.vote_average?d.vote_average.toFixed(1):'—';
    const genres=(d.genres||[]).map(g=>`<span class="tmdb-genre-badge">${g.name}</span>`).join(' ');
    body.innerHTML=`<div class="tmdb-info-wrap">${poster?`<img src="${poster}" class="tmdb-info-poster">`:''}<div class="tmdb-info-details"><div class="tmdb-info-title">${title} ${year?'('+year+')':''}</div><div class="tmdb-info-meta"><span style="color:#f5c518;font-weight:bold">★ ${rating}</span></div><div style="margin-bottom:12px">${genres}</div><div class="tmdb-info-overview">${d.overview||'لا توجد قصة متوفرة'}</div></div></div>`;
  }catch(e){body.innerHTML='<div style="text-align:center;padding:40px;color:#ff4d57">خطأ في الاتصال</div>';}
}
function closeTmdbModal(){document.getElementById('tmdbInfoM').classList.remove('open');document.body.style.overflow='';}
document.getElementById('tmdbInfoM').addEventListener('click',function(e){if(e.target===this)closeTmdbModal();});

/* ════ LOAD HOME ════ */
async function loadAndBuildNetflixHome(){
  if(App.license){
    document.getElementById('netflixStyleSliders').innerHTML='<div style="text-align:center;padding:60px 20px;color:var(--text-muted)"><p>الرخصة منتهية</p><a href="activate.php" style="display:inline-block;margin-top:16px;padding:10px 24px;background:var(--red);color:#fff;border-radius:99px;font-weight:800">تجديد الرخصة</a></div>';
    return;
  }
  try{
    const catRes=await fetch('api.php?action=all_content');
    const catData=await catRes.json();
    App.cats=catData.categories||[];
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
    await fetchAllRows();
    const syncDelay=window.requestIdleCallback||(fn=>setTimeout(fn,4000));
    syncDelay(()=>syncNotifications(App.cats));
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
    <div class="slider-scroll-mask">
      <div class="slider-cards-wrapper" id="slider-lane-${rowId}">
        ${Array(skelN).fill('<div class="skeleton" style="height:200px;border-radius:10px"></div>').join('')}
      </div>
    </div>`;
  wrap.appendChild(row);
}

async function fetchAllRows(){
  const allRows=Array.from(document.querySelectorAll('.netflix-slider-row[data-loaded="0"]'));
  if(!allRows.length)return;
  const INITIAL=3;
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
    items.forEach(k=>{
      const key=(isVOD?'series':'channel')+'_'+k.id;
      if(!App.allContent.find(x=>x._key===key))
        App.allContent.push({...k,globalType:isVOD?'series':'channel',_key:key});
    });
    const badge=document.getElementById('badge-'+rowId);
    if(badge)badge.textContent=items.length+(isVOD?' عمل':' قناة');
    renderItemsIntoSliderDOM(laneEl,items,type);
  }catch(err){
    if(laneEl)laneEl.innerHTML='<div style="color:var(--text-muted);padding:16px;font-size:.85rem;direction:rtl">تعذر التحميل</div>';
    row.dataset.loaded='0';
  }
}

/* ════ RENDER CARDS — DocumentFragment لأداء أفضل ════ */
function renderItemsIntoSliderDOM(sliderDom,items,cardType){
  if(!items||!items.length){
    sliderDom.innerHTML='<div style="color:var(--text-muted);padding:16px;font-size:.82rem;grid-column:1/-1;text-align:center">لا يوجد محتوى</div>';
    return;
  }
  // استخدام DocumentFragment — يُضاف للـ DOM مرة واحدة فقط
  const frag=document.createDocumentFragment();
  items.forEach((item,idx)=>{
    const div=document.createElement('div');
    div.style.animationDelay=(idx*.03)+'s';
    if(cardType==='series'){
      const isFav=MyFavs.series.some(f=>String(f.id)===String(item.id));
      div.className='sr-card';
      // صورة البوستر
      const poster=document.createElement('div');
      poster.className='sr-poster';
      if(item.poster_url){
        const img=document.createElement('img');
        img.src=esc(item.poster_url);
        img.loading='lazy';
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
      nameEl.textContent=item.name;
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
      div.addEventListener('click',()=>openSeriesEpisodes(item.id,item.name));
    }else{
      const isFav=MyFavs.channels.some(f=>String(f.id)===String(item.id));
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
      // info
      const info=document.createElement('div');
      info.className='ch-info';
      const nameEl=document.createElement('div');
      nameEl.className='ch-name';
      nameEl.title=item.name;
      nameEl.textContent=item.name;
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
    frag.appendChild(div);
  });
  sliderDom.innerHTML='';
  sliderDom.appendChild(frag);
}

/* ════ EPISODES ════ */
async function openSeriesEpisodes(seriesId,seriesName){
  App.currentSeriesId=seriesId;App.currentSeriesName=seriesName;
  document.getElementById('netflixStyleSliders').classList.add('hidden');
  document.getElementById('heroWelcome').classList.add('hidden');
  document.getElementById('searchViewSection').classList.add('hidden');
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
    loading.classList.add('hidden');
    if(!App.allEpisodes.length){empty.classList.remove('hidden');}else renderEpisodes(App.allEpisodes);
    fetch('api.php?action=increment_view&id='+seriesId+'&type=series').catch(()=>{});
  }catch(e){loading.classList.add('hidden');grid.innerHTML='<div style="color:var(--red);padding:20px">تعذر تحميل الحلقات</div>';}
}
function renderEpisodes(eps){
  const g=document.getElementById('epGrid');g.innerHTML='';
  eps.forEach((ep,i)=>{
    const dv=document.createElement('div');dv.className='ep-card';dv.style.animationDelay=(i*.05)+'s';
    const imgH=ep.image_url?`<img class="ep-thumb-video" src="${esc(ep.image_url)}" loading="lazy" onerror="this.style.display='none'">`:'';
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
  document.getElementById('netflixStyleSliders').classList.remove('hidden');
  document.getElementById('heroWelcome').classList.remove('hidden');
  window.scrollTo({top:0,behavior:'smooth'});
}

/* ════ SEARCH ════ */
let searchTimer;
function handleSearch(){
  clearTimeout(searchTimer);
  searchTimer=setTimeout(()=>{
    const q=document.getElementById('searchInput').value.trim().toLowerCase();
    if(q.length<1){clearSearchAndGoHome();return;}
    document.getElementById('netflixStyleSliders').classList.add('hidden');
    document.getElementById('epSection').classList.add('hidden');
    document.getElementById('heroWelcome').classList.add('hidden');
    document.getElementById('searchViewSection').classList.remove('hidden');
    const grid=document.getElementById('searchGrid');
    const empty=document.getElementById('searchEmpty');
    const badge=document.getElementById('searchCountBadge');
    const matched=App.allContent.filter(v=>(v.name||'').toLowerCase().includes(q));
    badge.textContent=matched.length+' نتيجة';
    if(matched.length){
      empty.classList.add('hidden');grid.classList.remove('hidden');
      // عرض مختلط: channels أولاً ثم series
      const channels=matched.filter(x=>x.globalType==='channel');
      const series=matched.filter(x=>x.globalType==='series');
      grid.innerHTML='';
      if(channels.length)renderItemsIntoSliderDOM(grid,channels,'channels');
      if(series.length){
        const sr=document.createElement('div');sr.style='grid-column:1/-1;padding-top:8px';grid.appendChild(sr);
        renderItemsIntoSliderDOM(grid,series,'series');
      }
    }else{grid.classList.add('hidden');empty.classList.remove('hidden');}
  },280);
}
function clearSearchAndGoHome(){
  document.getElementById('searchInput').value='';
  document.getElementById('searchViewSection').classList.add('hidden');
  document.getElementById('searchEmpty').classList.add('hidden');
  document.getElementById('netflixStyleSliders').classList.remove('hidden');
  document.getElementById('heroWelcome').classList.remove('hidden');
}

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
const PL={hls:null,dash:null,flv:null,vol:1,muted:false,idle:null,subtitleOn:false,epPanelOpen:false,m3uPanelOpen:false,m3uEntries:[],m3uIdx:-1};
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
  App.currentType='channel';App.currentEpisodeIdx=-1;
  document.getElementById('pEpNav').style.display='none';
  document.getElementById('epPanelBtn').style.display='none';
  document.getElementById('m3uPanelBtn').style.display='none';
  const fmt=fmtLabel(ch.stream_url||'');const isLive=isLiveFormat(ch.stream_url||'');
  document.getElementById('pBadgeLabel').textContent=isLive?'LIVE':'VOD';
  document.getElementById('pChannelName').textContent=ch.name;
  document.getElementById('pFmtTag').textContent=fmt;
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
  App.currentType='episode';App.currentEpisodeIdx=idx;
  const ep=App.allEpisodes[idx];if(!ep)return;
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

var _prevScreen={ep:false,home:false,search:false};

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
  overlay.classList.add('active');
  document.body.style.overflow='hidden';
  window.history.pushState({player:'active'},'');
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


function initStream(url,subUrl){
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

  // FIX: HLS مع إعدادات جودة محسّنة
  if(fmt==='hls'){
    if(typeof Hls!=='undefined'&&Hls.isSupported()){
      PL.hls=new Hls({
        enableWorker:true,
        lowLatencyMode:true,
        // FIX: إعدادات buffer أفضل للجودة
        maxMaxBufferLength:60,
        maxBufferLength:30,
        maxBufferSize:60*1000*1000,
        // FIX: اختيار أعلى جودة تلقائياً
        capLevelToPlayerSize:false,
        startLevel:-1, // -1 = auto (أعلى جودة متاحة)
        abrEwmaDefaultEstimate:5000000, // افتراض سرعة 5Mbps
        abrBandWidthFactor:0.95,
        abrBandWidthUpFactor:0.7,
      });
      PL.hls.loadSource(url);
      PL.hls.attachMedia(newV);
      PL.hls.on(Hls.Events.MANIFEST_PARSED,()=>newV.play().catch(()=>{}));
      PL.hls.on(Hls.Events.ERROR,(e,d)=>{if(d.fatal){toast('خطأ في البث');showBuf(false);}});
    }else if(newV.canPlayType('application/vnd.apple.mpegurl')){
      newV.src=url;newV.play().catch(()=>{});
    }else{newV.src=url;newV.play().catch(()=>{});}
  }else if(fmt==='dash'){
    if(typeof dashjs!=='undefined'){
      PL.dash=dashjs.MediaPlayer().create();
      PL.dash.initialize(newV,url,true);
      // FIX: تفعيل أعلى جودة
      PL.dash.updateSettings({'streaming':{'abr':{'autoSwitchBitrate':{'video':true}}}});
    }else{newV.src=url;newV.play().catch(()=>{});}
  }else if(fmt==='flv'){
    if(typeof flvjs!=='undefined'&&flvjs.isSupported()){
      PL.flv=flvjs.createPlayer({type:'flv',url,enableWorker:true,enableStashBuffer:false});
      PL.flv.attachMediaElement(newV);PL.flv.load();PL.flv.play();
      PL.flv.on(flvjs.Events.ERROR,()=>{toast('خطأ في FLV');showBuf(false);});
    }else{toast('المتصفح لا يدعم FLV');showBuf(false);}
  }else{
    // MP4, MKV, WEBM — مشغل مباشر
    newV.src=url;newV.play().catch(()=>{});
  }

  newV.volume=PL.vol;newV.muted=PL.muted;
  newV.ontimeupdate=updateProgress;
  newV.onwaiting=()=>showBuf(true);
  newV.onplaying=()=>{showBuf(false);setPlayIcon(false);};
  newV.onpause=()=>setPlayIcon(true);
  newV.onloadeddata=()=>showBuf(false);
  newV.onerror=()=>{showBuf(false);toast('تعذر تحميل الفيديو');};
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
  if(PL.hls){try{PL.hls.destroy();}catch(e){}PL.hls=null;}
  if(PL.dash){try{PL.dash.reset();}catch(e){}PL.dash=null;}
  if(PL.flv){try{PL.flv.destroy();}catch(e){}PL.flv=null;}
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
  PL.m3uIdx=idx;const e=PL.m3uEntries[idx];
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
  if(v.paused)v.play().catch(()=>{});else v.pause();
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
    if(!v.paused&&!PL.epPanelOpen&&!PL.m3uPanelOpen){
      if(top)top.classList.add('hide');
      if(bot)bot.classList.add('hide');
      if(cen)cen.classList.add('hide');
      r.classList.add('idle');
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
  function activate(){showControls();const all=getBtns();const pi=all.findIndex(b=>b.id==='playBtn');applyFocus(pi>=0?pi:Math.floor(all.length/2));}
  document.addEventListener('keydown',function(e){
    if(!document.getElementById('playerOverlay').classList.contains('active'))return;
    var kc=e.keyCode||e.which||0,ks=e.key||'';
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
    if(v.paused||v.ended||v.readyState===0){_stallTicks=0;return;}
    if(v.currentTime===_prev&&v.readyState<3){
      _stallTicks++;
      if(_stallTicks>=5){_stallTicks=0;if(_lastUrl){toast('إعادة الاتصال...');initStream(_lastUrl,'');}}
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

window.addEventListener('beforeunload',()=>{
  try{
    _watchdogStop();
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
  let isMusicPlaying=false;
  function initSiteMusic(){
    let saved=localStorage.getItem('shashety_music_play');
    if(saved==='1'){let pp=siteMusic.play();if(pp!==undefined)pp.then(()=>{isMusicPlaying=true;updateSiteMusicUI(true);}).catch(()=>{isMusicPlaying=false;updateSiteMusicUI(false);});}
    else updateSiteMusicUI(false);
  }
  function playSiteMusic(){siteMusic.play().then(()=>{isMusicPlaying=true;localStorage.setItem('shashety_music_play','1');updateSiteMusicUI(true);}).catch(()=>{isMusicPlaying=false;localStorage.setItem('shashety_music_play','0');updateSiteMusicUI(false);});}
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


window.addEventListener('load',function(){
  var loader=document.getElementById('nxInitLoader');
  if(loader){setTimeout(function(){loader.classList.add('loaded');setTimeout(function(){loader.remove();},500);},250);}
});