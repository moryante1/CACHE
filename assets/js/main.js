if(localStorage.getItem('shashety_sidebar')==='collapsed'){document.body.classList.add('sidebar-collapsed');}

  window.addEventListener('load', function(){
    setTimeout(function(){
      var l = document.getElementById('nfx-loader');
      if(l){ l.style.opacity='0'; l.style.visibility='hidden'; setTimeout(function(){ l.remove(); },650); }
    }, 900);
  });


// إغلاق قائمة اللغات عند النقر بالخارج
document.addEventListener('click', function() {
    var drop = document.getElementById('langDrop');
    if(drop && drop.classList.contains('op')) { drop.classList.remove('op'); }
});



function initFolderSelects() {
    let opts = '<option value="0" style="color:#00D084;font-weight:bold;">✨ + إنشاء (عمل / مجلد) جديد ومستقل</option>';
    if (typeof _allFoldersGlobal !== 'undefined') {
        _allFoldersGlobal.forEach(f => {
            opts += `<option value="${f.id}">📂 حفظ بداخل مجلد: ${esc(f.name)}</option>`;
        });
    }
    if($('vTargetSeries')) $('vTargetSeries').innerHTML = opts;
    if($('vmSaveTargetSeries')) $('vmSaveTargetSeries').innerHTML = opts;
}

document.addEventListener("DOMContentLoaded", initFolderSelects);

function vToggleSeriesFields(val, context) {
    let nameLblId = (context === 'upload') ? 'vNameLabel' : 'vmNameLabel';
    let catDivId =  (context === 'upload') ? 'vCatDiv'    : 'vmCatDiv';
    
    if (val == "0") {
        $(nameLblId).innerHTML = "اسم العمل/المجلد الجديد <small style='color:#00D084'>(مطلوب)</small>";
        $(catDivId).style.display = 'block';
    } else {
        $(nameLblId).innerHTML = "عنوان الحلقة أو الفيديو (سيوضع بداخل المجلد المختار) <small style='color:var(--t3)'>(اختياري/يمكنك تعديله)</small>";
        $(catDivId).style.display = 'none'; 
    }
}

const $=id=>document.getElementById(id);
function api(data){const fd=new FormData();for(const[k,v]of Object.entries(data))fd.append(k,String(v??''));return fetch(location.href,{method:'POST',body:fd}).then(r=>r.json()).catch(()=>({success:false,error:'خطأ في الاتصال'}));}
function esc(s){return(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;')}
function escA(s){return(s||'').replace(/\\/g,'\\\\').replace(/'/g,"\\'").replace(/"/g,'&quot;').replace(/\n/g,'\\n').replace(/\r/g,'\\r')}
function fmtSz(b){if(b>=1073741824)return(b/1073741824).toFixed(1)+' GB';if(b>=100048576)return(b/100048576).toFixed(1)+' MB';if(b>=100024)return(b/100024).toFixed(0)+' KB';return b+' B'}
function al(id,msg,type){const icons={s:'check-circle',e:'exclamation-circle',i:'info-circle'};const cls={s:'al-s',e:'al-e',i:'al-i'};const el=$(id);if(!el)return;if(!msg){el.innerHTML='';return;}el.innerHTML=`<div class="al ${cls[type]||'al-i'}" style="margin:0"><i class="fas fa-${icons[type]||'info-circle'}"></i> ${msg}</div>`;}
function S(id){document.querySelectorAll('.sec').forEach(s=>{s.classList.remove('on')});document.querySelectorAll('.si').forEach(s=>{s.classList.remove('on')});const sec=$(id);if(sec)sec.classList.add('on');$('tbTitle').textContent=titles[id]||'';document.querySelectorAll('.si').forEach(b=>{if(b.getAttribute('onclick')&&b.getAttribute('onclick').includes(`'${id}'`))b.classList.add('on')});}
function OM(id){const m=$(id);if(m){m.classList.add('op');document.body.style.overflow='hidden'}}
function CM(id){const m=$(id);if(m){m.classList.remove('op');document.body.style.overflow=''}}
document.addEventListener('keydown',e=>{if(e.key==='Escape'){document.querySelectorAll('.mbd.op').forEach(m=>{m.classList.remove('op')});document.body.style.overflow='';closePlayer()}});
document.querySelectorAll('.mbd').forEach(m=>m.addEventListener('click',e=>{if(e.target===m){m.classList.remove('op');document.body.style.overflow=''}}));
function FT(inp,tblId){const q=inp.value.toLowerCase();document.querySelectorAll('#'+tblId+' tbody tr').forEach(r=>{r.style.display=r.textContent.toLowerCase().includes(q)?'':'none';});}
function addRipple(e,btn){const r=document.createElement('span');r.className='si-ripple';const rect=btn.getBoundingClientRect();const sz=Math.max(btn.clientWidth,btn.clientHeight);r.style.cssText=`width:${sz}px;height:${sz}px;left:${e.clientX-rect.left-sz/2}px;top:${e.clientY-rect.top-sz/2}px`;btn.appendChild(r);setTimeout(()=>r.remove(),600);}
function toggleSidebar(){const sb=$('sidebar'),ov=$('sbOverlay'),hb=$('hamburgerBtn');const isOpen=sb.classList.contains('open');if(isOpen){closeSidebar();}else{sb.classList.add('open');ov.classList.add('on');document.body.style.overflow='hidden';if(hb)hb.classList.add('active');}}
function closeSidebar(){$('sidebar').classList.remove('open');$('sbOverlay').classList.remove('on');document.body.style.overflow='';const hb=$('hamburgerBtn');if(hb)hb.classList.remove('active');}
function toggleDesktopSidebar(){
  document.body.classList.toggle('sidebar-collapsed');
  if(document.body.classList.contains('sidebar-collapsed')) {
    localStorage.setItem('shashety_sidebar', 'collapsed');
  } else {
    localStorage.removeItem('shashety_sidebar');
  }
}
function uploadChannelLogo(inp,inputId,previewId,statusId){
    const f=inp.files[0];if(!f)return;
    const statusEl=$(statusId),previewEl=$(previewId);
    statusEl.innerHTML='<span class="sp"></span> جاري رفع الصورة...';
    const fd=new FormData();fd.append('ajax_action','upload_channel_logo');fd.append('logo',f);
    const xhr=new XMLHttpRequest();
    xhr.upload.onprogress=e=>{if(e.lengthComputable)statusEl.innerHTML=`<span class="sp"></span> ${Math.round(e.loaded/e.total*100)}%`;};
    xhr.onload=()=>{
        try{
            const d=JSON.parse(xhr.responseText);
            if(d.success){$(inputId).value=d.url;statusEl.innerHTML=`<span style="color:#00D084"><i class="fas fa-check-circle"></i> تم رفع الصورة</span>`;previewEl.style.display='block';previewEl.querySelector('img').src=d.url;}
            else statusEl.innerHTML=`<span style="color:#ff6b6b">${d.error||'خطأ في الرفع'}</span>`;
        }catch(e){statusEl.innerHTML=`<span style="color:#ff6b6b">خطأ</span>`;}
        inp.value='';
    };
    xhr.onerror=()=>{statusEl.innerHTML=`<span style="color:#ff6b6b">انقطع الاتصال</span>`;};
    xhr.open('POST',location.href);xhr.send(fd);
}

function previewImage(previewId,url){
    const el=$(previewId);if(!el)return;
    if(!url){el.style.display='none';return;}
    el.style.display='block';
    const img=el.querySelector('img');
    img.src=url;
    img.onerror=()=>el.style.display='none';
}

function saveApiSettings(){
    const tmdb_key = $('api_tmdb_key').value.trim();
    const os_user  = $('api_os_user').value.trim();
    const os_pass  = $('api_os_pass').value.trim();
    const os_key   = $('api_os_key').value.trim();
    const omdb_key = $('api_omdb_key').value.trim();
    
    al('apiSaveAlert', '<span class="sp"></span> جاري حفظ الإعدادات...', 'i');
    
    api({
        ajax_action: 'save_api_settings',
        tmdb_key: tmdb_key,
        os_user: os_user,
        os_pass: os_pass,
        os_key: os_key,
        omdb_key: omdb_key
    }).then(d => {
        if(d.success){
            al('apiSaveAlert', '✅ تم حفظ إعدادات الـ API بنجاح في قاعدة البيانات', 's');
            $('osU').value = os_user;
            $('osP').value = os_pass;
            $('osApiKey').value = os_key;
            
            // حقن تشغيل تسجيل الدخول التلقائي فور نجاح الحفظ
            if(os_user && os_pass && os_key){
                al('apiSaveAlert', '✅ تم الحفظ، يتم الآن ربط اتصال OpenSubtitles تلقائياً...', 's');
                setTimeout(osLogin, 800); 
            }
        }else{
            al('apiSaveAlert', d.error || 'حدث خطأ أثناء الحفظ', 'e');
        }
    });
}

let _tmdbTimer={};
function getTmdbKey(){ return SERVER_TMDB_KEY; }
function getOmdbKey(){ return SERVER_OMDB_KEY; }
let _currentSource = { add: 'tmdb', edit: 'tmdb' };
let _mediaSearchTimer = {};

function tmdbAutoSearch(ctx,val){clearTimeout(_tmdbTimer[ctx]);const res=$('tmdbRes_'+ctx);if(!val||val.length<3){res.style.display='none';return;}_tmdbTimer[ctx]=setTimeout(()=>_tmdbSearch(ctx,val),600);}
async function tmdbFetch(ctx){const nameId=ctx==='add'?'addChName':'eChName';const val=$(nameId).value.trim();if(!val){$(nameId).focus();return;}if(!getTmdbKey()){tmdbAskKey(ctx,val);return;}await _tmdbSearch(ctx,val);}

function tmdbAskKey(ctx, pendingQuery){
    alert('يرجى إضافة مفتاح TMDB API في قسم "إعدادات API" أولاً لكي تعمل هذه الميزة.');
    S('api-settings');
    closeSidebar();
}

async function _tmdbSearch(ctx,q){const key=getTmdbKey();if(!key){tmdbAskKey(ctx,q);return;}const res=$('tmdbRes_'+ctx);res.style.display='block';res.innerHTML='<div class="tmdb-item"><div class="tmdb-item-info" style="color:var(--t3)"><span class="sp"></span> جارٍ البحث في TMDB…</div></div>';try{const[rAr,rEn]=await Promise.all([fetch(`https://api.themoviedb.org/3/search/multi?api_key=${encodeURIComponent(key)}&query=${encodeURIComponent(q)}&language=ar`),fetch(`https://api.themoviedb.org/3/search/multi?api_key=${encodeURIComponent(key)}&query=${encodeURIComponent(q)}&language=en-US`)]);if(rAr.status===401||rEn.status===401){res.innerHTML='<div class="tmdb-item" onclick="S(\'api-settings\')" style="cursor:pointer"><div class="tmdb-item-info"><span style="color:#ff6b6b"><i class="fas fa-key"></i> مفتاح API غير صحيح — انقر هنا لتعديله</span></div></div>';return;}const[dAr,dEn]=await Promise.all([rAr.json(),rEn.json()]);const seen=new Set();const combined=[...(dAr.results||[]),...(dEn.results||[])].filter(item=>{const id=item.id;if(seen.has(id))return false;seen.add(id);return(item.title||item.name)&&item.poster_path;}).slice(0,8);if(!combined.length){const rFallback=await fetch(`https://api.themoviedb.org/3/search/multi?api_key=${encodeURIComponent(key)}&query=${encodeURIComponent(q)}`);const dFallback=await rFallback.json();const fallbackItems=(dFallback.results||[]).filter(i=>i.title||i.name).slice(0,8);if(!fallbackItems.length){res.innerHTML='<div class="tmdb-item"><div class="tmdb-item-info" style="color:var(--t3)"><i class="fas fa-search"></i> لا توجد نتائج — جرب اسم آخر أو بالإنجليزية</div></div>';return;}renderTmdbResults(res,fallbackItems,ctx);return;}renderTmdbResults(res,combined,ctx);}catch(e){res.innerHTML='<div class="tmdb-item"><div class="tmdb-item-info" style="color:#ff6b6b"><i class="fas fa-exclamation-triangle"></i> خطأ في الاتصال — تحقق من الإنترنت</div></div>';}}

function renderTmdbResults(res,items,ctx){
    res.innerHTML=items.map(item=>{
        const title=item.title||item.name||'';
        const year=(item.release_date||item.first_air_date||'').substring(0,4);
        const poster=item.poster_path?`https://image.tmdb.org/t/p/w92${item.poster_path}`:'';
        const posterFull=item.poster_path?`https://image.tmdb.org/t/p/w500${item.poster_path}`:'';
        const mediaType=item.media_type||'movie';
        const typeHtml=mediaType==='tv'?'<span class="bdg bp" style="font-size:.6rem">مسلسل</span>':'<span class="bdg bc" style="font-size:.6rem">فيلم</span>';
        const rating=item.vote_average?`<span style="color:var(--gold);font-size:.65rem"><i class="fas fa-star"></i> ${item.vote_average.toFixed(1)}</span>`:'';
        return `<div class="tmdb-item" onclick="tmdbPick('${ctx}','${escA(title)}','${escA(posterFull)}')">
            <img src="${esc(poster)}" onerror="this.style.opacity='.2'">
            <div class="tmdb-item-info">
                <div class="tmdb-item-title">${esc(title)}</div>
                <div class="tmdb-item-year" style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">${year||'—'} ${typeHtml} ${rating}</div>
            </div>
            <button type="button" class="tmdb-info-btn" onclick="event.preventDefault(); event.stopPropagation(); showTmdbInfo(${item.id}, '${mediaType}')" title="التفاصيل"><i class="fas fa-info"></i></button>
        </div>`;
    }).join('');
}

async function showTmdbInfo(id, type) {
    const key = getTmdbKey();
    if (!key) { alert('مفتاح TMDB مفقود! يرجى إضافته في الإعدادات.'); return; }
    OM('tmdbInfoM');
    const body = $('tmdbInfoBody');
    body.innerHTML = '<div style="text-align:center;padding:40px;color:var(--t3)"><div class="pspin" style="margin:0 auto 12px"></div>جاري جلب التفاصيل...</div>';
    try {
        let res = await fetch(`https://api.themoviedb.org/3/${type}/${id}?api_key=${encodeURIComponent(key)}&language=ar`);
        let data = await res.json();
        if (!data.overview) {
            let resEn = await fetch(`https://api.themoviedb.org/3/${type}/${id}?api_key=${encodeURIComponent(key)}&language=en-US`);
            let dataEn = await resEn.json();
            data.overview = dataEn.overview;
        }
        const title = data.title || data.name || 'بدون عنوان';
        const poster = data.poster_path ? `https://image.tmdb.org/t/p/w300${data.poster_path}` : '';
        const year = (data.release_date || data.first_air_date || '').substring(0, 4);
        const rating = data.vote_average ? data.vote_average.toFixed(1) : '—';
        const genres = (data.genres || []).map(g => `<span class="bdg bc">${g.name}</span>`).join(' ');
        const overview = data.overview || 'لا توجد قصة متوفرة لهذا العمل في الوقت الحالي.';
        const status = data.status || '—';
        const runTime = data.runtime ? `${data.runtime} دقيقة` : (data.episode_run_time && data.episode_run_time[0] ? `${data.episode_run_time[0]} دقيقة للحلقة` : '');

        body.innerHTML = `
            <div class="tmdb-info-wrap">
                ${poster ? `<img src="${poster}" class="tmdb-info-poster">` : `<div class="tmdb-info-poster" style="display:flex;align-items:center;justify-content:center;height:195px"><i class="fas fa-film fa-2x"></i></div>`}
                <div class="tmdb-info-details">
                    <div class="tmdb-info-title">${title} ${year ? `(${year})` : ''}</div>
                    <div class="tmdb-info-meta">
                        <span style="color:var(--gold);font-weight:bold;"><i class="fas fa-star"></i> ${rating}</span>
                        ${runTime ? `<span><i class="fas fa-clock"></i> ${runTime}</span>` : ''}
                        <span style="color:var(--t2)">الحالة: ${status}</span>
                    </div>
                    <div style="margin-bottom:14px">${genres}</div>
                    <div style="font-size:0.8rem;font-weight:bold;margin-bottom:6px;color:var(--t2)">القصة:</div>
                    <div class="tmdb-info-overview">${overview}</div>
                </div>
            </div>
        `;
    } catch (e) {
        body.innerHTML = '<div style="text-align:center;padding:40px;color:#ff6b6b"><i class="fas fa-exclamation-triangle fa-2x"></i><br><br>حدث خطأ أثناء الاتصال بخوادم TMDB.</div>';
    }
}

function tmdbPick(ctx,title,poster){const res=$('tmdbRes_'+ctx);if(ctx==='add'){$('addChName').value=title;if(poster){$('addChLogo').value=poster;previewImage('addPrev',poster);}}else{$('eChName').value=title;if(poster){$('eChLogo').value=poster;previewImage('editPrev',poster);}}res.style.display='none';}
function tmdbPreviewUrl(elId,url){const el=$(elId);if(!el)return;if(!url||(!url.startsWith('http')&&!url.startsWith('/'))){el.style.display='none';return;}el.style.display='block';const img=el.querySelector('img');img.src=url;img.onerror=()=>{el.style.display='none';};}
document.addEventListener('click',e=>{if(!e.target.closest('.fg-rel'))document.querySelectorAll('.tmdb-results').forEach(r=>r.style.display='none');});

/* ════ PLAYER STATE — FIXED v2 ════ */
let _hls = null, _pUrl = '', _pSub = '';
let _watchdogTimer = null;
let _lastTime = -1;
let _frozenCount = 0;

/* اكتشاف صيغة الرابط */
function detectFmt(url) {
    if (!url) return 'hls';
    const clean = url.split('?')[0].split('#')[0].toLowerCase();
    if (clean.endsWith('.m3u8') || clean.endsWith('.m3u')) return 'hls';
    if (clean.includes('m3u8') || clean.includes('/hls/') || clean.includes('type=m3u')) return 'hls';
    if (clean.endsWith('.ts') || clean.endsWith('.mts')) return 'hls';
    if (clean.endsWith('.mpd')) return 'dash';
    if (clean.endsWith('.mp4') || clean.endsWith('.m4v')) return 'mp4';
    if (clean.endsWith('.mkv') || clean.endsWith('.avi') || clean.endsWith('.webm')) return 'direct';
    return 'hls'; // افتراضي دائماً HLS
}

/* تدمير كامل للبلاير السابق */
function _destroyAll() {
    // إيقاف watchdog
    if (_watchdogTimer) { clearInterval(_watchdogTimer); _watchdogTimer = null; }
    _lastTime = -1; _frozenCount = 0;
    // تدمير HLS
    if (_hls) { try { _hls.destroy(); } catch(e) {} _hls = null; }
    // تنظيف عنصر الفيديو
    const vid = $('tv');
    if (!vid) return;
    vid.oncanplay = null; vid.onwaiting = null; vid.onplaying = null;
    vid.onstalled = null; vid.onerror = null;
    try {
        vid.pause();
        // إزالة كل المصادر والـ tracks
        while (vid.firstChild) vid.removeChild(vid.firstChild);
        vid.removeAttribute('src');
        vid.load();
    } catch(e) {}
}

/* watchdog: يراقب التجمّد ويُعيد التشغيل تلقائياً */
function _startWatchdog(url, name, sub) {
    if (_watchdogTimer) clearInterval(_watchdogTimer);
    _lastTime = -1; _frozenCount = 0;
    _watchdogTimer = setInterval(function() {
        const vid = $('tv');
        if (!vid || vid.paused || vid.ended) return;
        const ct = vid.currentTime;
        if (ct === _lastTime) {
            _frozenCount++;
            if (_frozenCount >= 5) { // 10 ثوانٍ تجمّد → إعادة تشغيل
                _frozenCount = 0;
                console.warn('Watchdog: stream frozen, reconnecting...');
                testChannel(url, name, sub);
            }
        } else {
            _frozenCount = 0;
            _lastTime = ct;
        }
    }, 2000);
}

function testChannel(url, name, subUrl) {
    _pUrl = url || '';
    _pSub = subUrl || '';

    // تحديث الواجهة
    $('ptitle').textContent = name || url;
    $('purl').textContent = url;
    $('pm').classList.add('op');
    document.body.style.overflow = 'hidden';
    $('pload').classList.remove('hid');
    $('perr').classList.remove('sh');
    $('pdot').className = 'pdot';
    $('pfmt').style.display = 'none';

    // ═══ تدمير كل شيء قبل البدء ═══
    _destroyAll();

    const vid = $('tv');
    vid.setAttribute('playsinline', '');
    vid.setAttribute('webkit-playsinline', '');
    vid.removeAttribute('crossorigin'); // قد يسبب CORS مشاكل مع بعض السيرفرات

    // ═══ الترجمة ═══
    if (_pSub && _pSub.trim()) {
        $('psubbar').style.display = 'flex';
        $('psubLabel').textContent = 'ترجمة: ' + _pSub.split('/').pop();
        const tr = document.createElement('track');
        tr.kind = 'subtitles'; tr.srclang = 'ar'; tr.label = 'عربي';
        tr.src = _pSub; tr.default = true;
        vid.appendChild(tr);
        setTimeout(function() {
            if (vid.textTracks[0]) vid.textTracks[0].mode = 'showing';
        }, 800);
        $('psubToggleIc').className = 'fas fa-toggle-on';
        $('psubToggleTxt').textContent = 'إخفاء';
    } else {
        $('psubbar').style.display = 'none';
    }

    
    // --- START ADVANCED AUDIO/VIDEO CODEC DETECTION (Dolby Atmos / HEVC) ---
    function autoDetectAndConfigureCodecs(vidElement) {
        let codecs = [];
        
        // 1. فحص دعم جودة 4K/HDR (H.265 / HEVC)
        const canPlayHEVC = window.MediaSource && MediaSource.isTypeSupported('video/mp4; codecs="hev1.1.6.L93.B0"');
        if (canPlayHEVC) codecs.push('HEVC/4K');
        
        // 2. فحص دعم الصوت المحيطي (Dolby Digital Plus / Dolby Atmos / E-AC-3)
        const canPlayDolby = vidElement.canPlayType('audio/mp4; codecs="ec-3"') || vidElement.canPlayType('audio/mp4; codecs="mp4a.a6"');
        if (canPlayDolby) {
            codecs.push('Dolby Atmos');
        } else {
            codecs.push('AAC/Standard'); // التبديل التلقائي العادي
        }
        
        // عرض النتيجة في الشارة
        const codecBadge = document.getElementById('pcodec');
        if (codecBadge) {
            codecBadge.style.display = 'inline';
            codecBadge.textContent = codecs.join(' + ');
            if (canPlayDolby) {
                // شكل احترافي إذا تم التقاط تقنية دولبي
                codecBadge.style.color = '#fff';
                codecBadge.style.background = 'linear-gradient(45deg, #000000, #0055ff)';
                codecBadge.style.border = '1px solid #0055ff';
            } else {
                codecBadge.style.color = '#B36BFF';
                codecBadge.style.background = 'rgba(179,107,255,.15)';
                codecBadge.style.border = '1px solid rgba(179,107,255,.3)';
            }
        }

        // 3. التوجيه التلقائي للصوت (Spatial Audio Context) للتلفاز والمسرح المنزلي
        try {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            if (AudioContext && !vidElement._audioRouted) {
                const audioCtx = new AudioContext();
                // فتح جميع القنوات المتاحة في كرت الصوت الخاص بالشاشة (مثلاً 5.1 أو 7.1)
                if (audioCtx.destination.maxChannelCount > 2) {
                    audioCtx.destination.channelCount = audioCtx.destination.maxChannelCount;
                }
                vidElement._audioRouted = true;
            }
        } catch(e) {
            // تجاهل بصمت، النظام سيعمل بالوضع القياسي
        }
    }
    
    autoDetectAndConfigureCodecs($('tv'));
    // --- END ADVANCED CODEC DETECTION ---
    
    const fmt = detectFmt(url);

    // ══════════════ HLS ══════════════
    if (fmt === 'hls') {
        $('pfmt').style.display = '';
        $('pfmt').textContent = 'HLS';
        $('pfmt').style.cssText = 'display:inline;font-size:.65rem;font-weight:800;padding:2px 8px;border-radius:4px;background:rgba(229,9,20,.15);border:1px solid rgba(229,9,20,.3);color:var(--red)';

        if (typeof Hls !== 'undefined' && Hls.isSupported()) {
            _hls = new Hls({
                enableWorker: true,
                lowLatencyMode: false,         // ← السبب الرئيسي للتوقف بعد ثانية — أُوقف
                capLevelToPlayerSize: false,
                maxMaxBufferLength: 120,        // ← بفر أكبر = استقرار أفضل
                maxBufferLength: 60,
                maxBufferSize: 60 * 1000 * 1000,
                backBufferLength: 30,
                startLevel: -1,                 // اختيار جودة تلقائي
                abrEwmaDefaultEstimate: 1000000,
                // --- ADDED: Professional Codec Handling ---
                altAudio: true,                // التعرف التلقائي واختيار مسارات الصوت البديلة (دولبي)
                enableSoftwareAES: true,       // معالجة أفضل لفك تشفير القنوات المعقدة
                audioCodecSetup: "audio/mp4",  // تجهيز مساحة الصوت العالي
                // ------------------------------------------
                // إعادة المحاولة عند فشل التحميل
                fragLoadingMaxRetry: 8,
                manifestLoadingMaxRetry: 6,
                levelLoadingMaxRetry: 6,
                fragLoadingRetryDelay: 1500,
                manifestLoadingRetryDelay: 1000,
                levelLoadingRetryDelay: 1000,
                // لا تضغط بيانات manifest
                xhrSetup: function(xhr) {
                    xhr.withCredentials = false;
                }
            });

            _hls.loadSource(url);
            _hls.attachMedia(vid);

            _hls.on(Hls.Events.MANIFEST_PARSED, function() {
                vid.play().catch(function() {});
                _startWatchdog(url, name, subUrl);
            });

            _hls.on(Hls.Events.FRAG_LOADED, function() {
                $('pload').classList.add('hid');
                $('pdot').className = 'pdot ok';
            });

            var _mediaErrCount = 0;
            _hls.on(Hls.Events.ERROR, function(event, data) {
                console.warn('HLS Error:', data.type, data.details, 'fatal:', data.fatal);
                if (!data.fatal) return; // تجاهل الأخطاء غير المميتة تماماً

                if (data.type === Hls.ErrorTypes.NETWORK_ERROR) {
                    // استئناف تلقائي عند خطأ شبكة
                    setTimeout(function() {
                        if (_hls) { try { _hls.startLoad(); } catch(e) {} }
                    }, 2000);
                } else if (data.type === Hls.ErrorTypes.MEDIA_ERROR) {
                    _mediaErrCount++;
                    if (_mediaErrCount <= 3) {
                        try { _hls.recoverMediaError(); } catch(e) {}
                    } else {
                        pShowErr('خطأ في فك ترميز الفيديو');
                    }
                } else {
                    pShowErr('خطأ HLS: ' + data.details);
                }
            });

        } else if (vid.canPlayType('application/vnd.apple.mpegurl')) {
            // Safari / iOS — HLS أصلي
            vid.src = url;
            vid.play().catch(function() {});
        } else {
            vid.src = url;
            vid.play().catch(function() {});
        }

    // ══════════════ MP4 / Direct ══════════════
    } else {
        $('pfmt').style.display = '';
        $('pfmt').textContent = fmt.toUpperCase();
        $('pfmt').style.cssText = 'display:inline;font-size:.65rem;font-weight:800;padding:2px 8px;border-radius:4px;background:rgba(0,208,132,.15);border:1px solid rgba(0,208,132,.3);color:#00D084';
        vid.src = url;
        vid.play().catch(function() {});
    }

    // ═══ أحداث الفيديو ═══
    vid.oncanplay = function() {
        $('pload').classList.add('hid');
        $('pdot').className = 'pdot ok';
    };
    vid.onwaiting = function() {
        $('pload').classList.remove('hid');
    };
    vid.onplaying = function() {
        $('pload').classList.add('hid');
        $('pdot').className = 'pdot ok';
    };
    vid.onstalled = function() {
        // محاولة استئناف تلقائية عند توقف البفر
        setTimeout(function() {
            if (vid.paused && _pUrl) { vid.play().catch(function() {}); }
        }, 3000);
    };
    vid.onerror = function() {
        pShowErr('تعذر تشغيل الفيديو — تحقق من الرابط');
    };
}

function pShowErr(msg) {
    $('pload').classList.add('hid');
    $('perr').classList.add('sh');
    $('pdot').className = 'pdot err';
    var em = document.getElementById('perrMsg');
    if (em) em.textContent = msg || 'تعذر تشغيل الفيديو';
}
function pRetry() { testChannel(_pUrl, $('ptitle').textContent, _pSub); }
function pOpenNew() { if (_pUrl) window.open(_pUrl, '_blank'); }
function pCopyUrl() {
    if (!_pUrl) return;
    navigator.clipboard && navigator.clipboard.writeText(_pUrl).then(function() {
        var b = document.querySelector('#pm .pbtn');
        if (b) { var old = b.innerHTML; b.innerHTML = '<i class="fas fa-check"></i> نُسخ'; setTimeout(function() { b.innerHTML = old; }, 1500); }
    });
}
function pToggleSub() {
    const vid = $('tv'), trk = vid.querySelector('track');
    if (!trk) return;
    const on = trk.track.mode === 'showing';
    trk.track.mode = on ? 'disabled' : 'showing';
    $('psubToggleIc').className = on ? 'fas fa-toggle-off' : 'fas fa-toggle-on';
    $('psubToggleTxt').textContent = on ? 'إظهار' : 'إخفاء';
}
function closePlayer() {
    $('pm').classList.remove('op');
    document.body.style.overflow = '';
    _destroyAll();
}

function editCat(d){$('eCatId').value=d.id;$('eCatName').value=d.name;$('eCatIcon').value=d.icon||'fas fa-th-large';const sel=$('eCatParent');for(let o of sel.options)o.selected=(o.value===(d.parent_id||'').toString());OM('editCatM');}
function editCh(d){$('eChId').value=d.id;$('eChName').value=d.name;$('eChUrl').value=d.stream_url;$('eChIcon').value=d.logo_icon||'fas fa-tv';$('eChLogo').value=d.logo_url||'';const sel=$('eChCat');for(let o of sel.options)o.selected=(o.value===d.category_id.toString());if(d.logo_url)previewImage('editPrev',d.logo_url);else $('editPrev').style.display='none';OM('editChM');}
let _srAll=[],_srCurId=0,_srCurName='';
function loadSeries(){$('srGrid').style.display='none';$('srEmpty').style.display='none';$('epsPanel').style.display='none';$('srLoading').style.display='block';$('srBackBtn').style.display='none';$('srBulkBtn').style.display='none';$('srBreadcrumb').style.display='none';$('srAddBtn').innerHTML='<i class="fas fa-plus"></i>مسلسل / فيلم جديد';$('srAddBtn').setAttribute('onclick',"OM('addSeriesM')");const cid=$('srCatFilter').value;api({ajax_action:'get_series',category_id:cid}).then(d=>{$('srLoading').style.display='none';if(!d.success){return;}_srAll=d.data||[];srRender(_srAll);});}
function srFilter(){const q=$('srSearch').value.toLowerCase();srRender(_srAll.filter(s=>s.name.toLowerCase().includes(q)));}
function srRender(arr){const g=$('srGrid'),e=$('srEmpty');$('srCount').textContent=arr.length+' مسلسلات/أفلام';if(!arr.length){g.style.display='none';e.style.display='block';return;}e.style.display='none';g.style.display='grid';g.innerHTML=arr.map(s=>`<div class="src" id="sr-${s.id}"><div class="src-poster" onclick="srOpen(${s.id},'${escA(s.name)}')">${s.poster_url?`<img src="${esc(s.poster_url)}" onerror="this.parentElement.innerHTML='<i class=\\'fas fa-film\\'></i>'">`:'<i class="fas fa-film"></i>'}</div><div class="src-body" onclick="srOpen(${s.id},'${escA(s.name)}')"><div class="src-name">${esc(s.name)}</div><div class="src-meta"><span class="bdg bc">${esc(s.cat_name||'—')}</span><span class="bdg bp">${s.ep_count||0} فيديو</span></div></div><div class="src-acts"><button class="ib ed" onclick="srEdit(_srAll.find(x => x.id === ${s.id}))"><i class="fas fa-pen"></i></button><button class="ib dl" onclick="srDel(${s.id},'${escA(s.name)}')"><i class="fas fa-trash"></i></button></div></div>`).join('');}
function srOpen(id,name){_srCurId=id;_srCurName=name;$('srGrid').style.display='none';$('srEmpty').style.display='none';$('srFilterBar').style.display='none';$('epsPanel').style.display='block';$('srBackBtn').style.display='';$('srBulkBtn').style.display='';$('srBreadcrumb').style.display='flex';$('srBCName').textContent=name;$('srAddBtn').innerHTML='<i class="fas fa-plus"></i>إضافة فيديو';$('srAddBtn').setAttribute('onclick',"OM('addEpM')");loadEps();}
function srBack(){$('epsPanel').style.display='none';$('srBackBtn').style.display='none';$('srBulkBtn').style.display='none';$('srBreadcrumb').style.display='none';$('srFilterBar').style.display='flex';$('srAddBtn').innerHTML='<i class="fas fa-plus"></i>مسلسل / فيلم جديد';$('srAddBtn').setAttribute('onclick',"OM('addSeriesM')");loadSeries();}
function srAdd(){const n=$('srName').value.trim(),cid=$('srCat').value,desc=$('srDesc').value.trim(),poster=$('srPoster').value.trim();if(!n||!cid){al('srAddAlert','أدخل الاسم واختر القسم','e');return;}api({ajax_action:'add_series',name:n,category_id:cid,description:desc,poster_url:poster}).then(d=>{if(d.success){CM('addSeriesM');loadSeries();$('srName').value='';$('srCat').value='';$('srDesc').value='';$('srPoster').value='';$('srPosterThumb').style.display='none';$('srPosterStatus').innerHTML='';}else al('srAddAlert',d.error||'خطأ','e');});}
function srEdit(s){$('eSrId').value=s.id;$('eSrName').value=s.name;$('eSrDesc').value=s.description||'';$('eSrPoster').value=s.poster_url||'';const sel=$('eSrCat');for(let o of sel.options)o.selected=(o.value===s.category_id.toString());const thumbEl=$('eSrPosterThumb'),statusEl=$('eSrPosterStatus');if(s.poster_url){thumbEl.style.display='block';thumbEl.querySelector('img').src=s.poster_url;statusEl.innerHTML='';}else{thumbEl.style.display='none';statusEl.innerHTML='';}OM('editSeriesM');}
function srEditSave(){const id=$('eSrId').value,n=$('eSrName').value.trim(),cid=$('eSrCat').value,desc=$('eSrDesc').value.trim(),poster=$('eSrPoster').value.trim();if(!n||!cid){al('eSrAlert','البيانات ناقصة','e');return;}api({ajax_action:'edit_series',id,name:n,category_id:cid,description:desc,poster_url:poster}).then(d=>{if(d.success){CM('editSeriesM');loadSeries();}else al('eSrAlert',d.error||'خطأ','e');});}
function srDel(id,name){if(!confirm(`حذف "${name}" مع جميع فيديوهاته/حلقاته؟`))return;api({ajax_action:'delete_series',id}).then(d=>{if(d.success)loadSeries();});}

let _epGlobalCache=[]; 

            function _renderEpRows() {
                const t = $('epsTbody');
                t.innerHTML = _epGlobalCache.map((e, index) => `<tr class="drag-row" draggable="true" data-index="${index}" style="cursor: grab;">
                    <td style="display:flex; align-items:center; gap:8px">
                        <i class="fas fa-grip-lines" style="color:var(--t3); font-size:1.1rem;" title="اسحبني لأي مكان للترتيب"></i>
                        <input type="checkbox" class="ep-chk" value="${e.id}" onchange="epChkCtrl()" style="width:16px;height:16px; cursor:pointer; accent-color:var(--red);">
                    </td>
                    <td><div style="color:var(--red);font-size:1.15rem;padding-left:4px;"><i class="fas fa-play-circle"></i></div></td>
                    <td style="color:var(--t1);font-weight:700;font-size:.87rem;">${esc(e.title)}</td>
                    <td style="font-size:.65rem;color:var(--t3);max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" dir="ltr">${esc(e.stream_url.split('/').pop())}</td>
                    <td>${e.subtitle_url?'<span class="bdg bg"><i class="fas fa-closed-captioning"></i></span>':'<span style="color:var(--t3);">-</span>'}</td>
                    <td style="color:var(--t3);font-size:.75rem">${e.duration||'-'}</td>
                    <td><div class="acts">
                        <button class="ib pl" onclick='testChannel("${escA(e.stream_url)}","${escA(e.title)}","${escA(e.subtitle_url||'')}")'><i class="fas fa-play"></i></button>
                        <button class="ib ed" onclick="epEdit(_epGlobalCache.find(x => x.id === ${e.id}))"><i class="fas fa-pen"></i></button>
                        <button class="ib dl" onclick="epDel(${e.id},'${escA(e.title)}')"><i class="fas fa-trash"></i></button>
                    </div></td>
                </tr>`).join('');
                epChkCtrl();
                _setupDragAndDrop();
            }

            let _dragSrcEl = null;
            function _setupDragAndDrop() {
                const rows = document.querySelectorAll('#epsTbody tr.drag-row');
                rows.forEach(row => {
                    row.addEventListener('dragstart', function(e) {
                        _dragSrcEl = this;
                        e.dataTransfer.effectAllowed = 'move';
                        e.dataTransfer.setData('text/html', this.innerHTML);
                        this.style.opacity = '0.4';
                        this.style.background = 'rgba(229,9,20,.1)';
                    });
                    row.addEventListener('dragover', function(e) {
                        e.preventDefault();
                        e.dataTransfer.dropEffect = 'move';
                        return false;
                    });
                    row.addEventListener('dragenter', function(e) {
                        this.style.borderTop = '2px solid var(--red)';
                        this.style.borderBottom = '2px solid var(--red)';
                        this.style.background = 'rgba(255,255,255,.05)';
                    });
                    row.addEventListener('dragleave', function(e) {
                        this.style.borderTop = '';
                        this.style.borderBottom = '';
                        this.style.background = '';
                    });
                    row.addEventListener('drop', function(e) {
                        e.stopPropagation();
                        if (_dragSrcEl !== this) {
                            let srcIdx = parseInt(_dragSrcEl.getAttribute('data-index'));
                            let targetIdx = parseInt(this.getAttribute('data-index'));
                            
                            // تبديل وتعديل مصفوفات الداتا بناءً على الإفلات بالماوس 
                            const movedItem = _epGlobalCache.splice(srcIdx, 1)[0];
                            _epGlobalCache.splice(targetIdx, 0, movedItem);
                            
                            // تفعيل إشعار "ترتيب يدوي" لعدم خلط الاختيارات
                            $('epSortAZ').value = 'manual';
                            
                            _renderEpRows(); // ريندر لضبط الشكل المقلوب
                            _saveOrderToDB(); // تسريبه للسيرفر وتأمينه مباشرة!
                        }
                        return false;
                    });
                    row.addEventListener('dragend', function(e) {
                        this.style.opacity = '1';
                        this.style.background = '';
                        rows.forEach(r => { r.style.borderTop = ''; r.style.borderBottom = ''; r.style.background = ''; });
                    });
                });
            }

            function _saveOrderToDB() {
                const sorted_payload = _epGlobalCache.map((v, i) => ({id: v.id, order: i + 1}));
                al('epsEmpty','<span class="sp"></span> يُرسِل خريطة الأماكن للمتصلين لدمج النظام مع (index) الأمامي ..','i');
                $('epsEmpty').style.display = 'block';
                
                api({ ajax_action: 'update_episodes_order', orders: JSON.stringify(sorted_payload) }).then(res => {
                    if(res.success){ al('epsEmpty','✅ تم الحفظ واستنساخ هندستك اليدوية وتأمينها !','s'); setTimeout(()=>{ $('epsEmpty').style.display='none'; al('epsEmpty','','');}, 3000); }
                    else { al('epsEmpty', res.error, 'e'); }
                });
            }

            function _sortAndSaveEps() {
                const m = $('epSortAZ').value;
                if(m === 'manual') return; // مجرد مؤشر للتوقف في حال سحب الماوس
                
                if(m === 'az') _epGlobalCache.sort((a,b) => a.title.localeCompare(b.title));
                else if(m === 'za') _epGlobalCache.sort((a,b) => b.title.localeCompare(a.title));
                else _epGlobalCache.sort((a,b) => a.id - b.id); // تسلسل الافتراضات السحابية الأصلي
                
                _renderEpRows();
                _saveOrderToDB();
            }

            function loadEps() {
                $('epsTbody').innerHTML = '<tr><td colspan="7" style="text-align:center;padding:30px;"><div class="sp"></div> تجهيز وتعديل العناصر </td></tr>';
                $('epsEmpty').style.display = 'none';
                api({ajax_action:'get_episodes',series_id:_srCurId}).then(d=>{
                    _epGlobalCache = d.data || [];
                    $('srBCCount').textContent = _epGlobalCache.length + ' إمتـداد محجـوز';
                    $('epSortAZ').value = 'def'; // مبدأ الافتراضي الزمني مع أول تحميل 
                    
                    if(!_epGlobalCache.length) { $('epsTbody').innerHTML=''; $('epsEmpty').style.display='block'; return;}
                    $('epsEmpty').style.display = 'none';
                    _renderEpRows();
                });
            }
            
            // خواص التشيك والحذف العنيف (Bulk Options)
            function toggleChkEps(me){
                document.querySelectorAll('.ep-chk').forEach(c => c.checked = me.checked);
                epChkCtrl();
            }
            
           function epChkCtrl() {
    const checked = document.querySelectorAll('.ep-chk:checked').length;
    const dbtn = $('delBulkBtn');
    const cbtn = $('convertMp4Btn');
    if(checked > 0){ 
        dbtn.style.display = 'inline-flex'; 
        dbtn.innerHTML = `<i class="fas fa-trash-alt"></i> نسف ( ${checked} )`; 
        if(cbtn) {
            cbtn.style.display = 'inline-flex'; 
            cbtn.innerHTML = `<i class="fas fa-magic"></i> ذكي MP4 ( ${checked} )`; 
        }
    } else { 
        dbtn.style.display = 'none'; 
        if(cbtn) cbtn.style.display = 'none'; 
        $('chkEpsMaster').checked = false;
    }
}

// ── الوظيفة التفاعلية الجديدة للزر ──
function convertCheckedEpsToMp4() {
    let targets = [];
    document.querySelectorAll('.ep-chk:checked').forEach(c => targets.push(c.value));
    if(!targets.length) return;
    
    if(!confirm(`سيتم تجميد مسار الـ TS واستبداله بشكل كامل إلى صيغة MP4 فائقة السرعة للملفات المختارة (${targets.length}) ملف. موافق؟`)) return;
    
    const cbtn = $('convertMp4Btn');
    cbtn.innerHTML = `<i class="fas fa-spinner fa-spin"></i> السيرفر يعمل بقوة، الرجاء الانتظار...`; 
    cbtn.disabled = true;
    $('delBulkBtn').disabled = true;
    
    api({ ajax_action: 'convert_to_mp4_bulk', ids: JSON.stringify(targets) }).then(x => {
        cbtn.disabled = false;
        $('delBulkBtn').disabled = false;
        
        if(x.success) {
             let msg = '';
             if (x.success_count > 0) {
                 msg += `✅ عبقرية! تم تبديل وضغط وإزالة ( ${x.success_count} ) ملف إلى نظام MP4 المعتمد، وجاهز للبث.\n\n`;
             }
             if (x.failed_count > 0) {
                 msg += `⚠️ عذراً، تم إسقاط عملية التحويل لـ ( ${x.failed_count} ) مسار!\n\n`;
                 // هنا مربط الفرس: نطبع الخطأ المصدري من البي أتش بي مباشرة لتراه أمام عينيك:
                 if (x.debug) {
                     msg += `تفاصيل كشف النظام للسبب:\n=================\n${x.debug}`;
                 } else {
                     msg += "فشلت الأوامر لأسباب تقنية تخص بايثون أو Xampp.";
                 }
             }
             
             alert(msg);
             $('chkEpsMaster').checked = false;
             loadEps(); // سحب بيانات المجلد بعد هندسته لتجد كل شيء تغير بصيغته أمامت.
             
        } else { 
            alert('❌ رُفضت الإعدادات الخادمة: ' + (x.error || 'عقدة انصات')); 
        }
        cbtn.innerHTML = `<i class="fas fa-magic"></i> التحويل السريع لـ MP4`;
    }).catch(() => {
        alert("انقطع اتصالك بالمتصفح ولكن الأباتشي مستمر بالحرق من خلف الكواليس. أعمل تحديث للمتصفح لترا النتائج لاحقاً.");
        cbtn.innerHTML = `<i class="fas fa-magic"></i> التحويل السريع لـ MP4`;
        cbtn.disabled = false;
    });
}
        
    function deleteCheckedEps() {
                let targets = [];
                document.querySelectorAll('.ep-chk:checked').forEach(c => targets.push(c.value));
                if(!targets.length) return;
                
                if(!confirm(`⚠️ خطـــــر: أنت تُصدّق مسح ${targets.length} فيلم / مسار من جذوره الفعلية؟ ( لن يرجع أثره! )`)) return;
                
                const dbtn = $('delBulkBtn');
                dbtn.innerHTML = `<i class="fas fa-spinner fa-spin"></i> الحفر والاستئصال..`; dbtn.disabled = true;
                
                api({ ajax_action: 'delete_episodes_bulk', ids: JSON.stringify(targets) }).then(x => {
                    dbtn.disabled = false;
                    if(x.success) {
                         alert('✅ عملية مُطهِّرة تمت باحتراف. تم الاستئصال تماماً للمساحات المختارة!');
                         $('chkEpsMaster').checked = false;
                         loadEps();
                    } else { alert('انفلات جزئي أو فشل المسار المعمّق'); }
                });
            }
            
function etab(t){document.querySelectorAll('#addEpM .etab').forEach(b=>b.classList.remove('on'));event.target.classList.add('on');$('etab-url').style.display=t==='url'?'':'none';$('etab-file').style.display=t==='file'?'':'none';}
let _epSubUpUrl='',_epFileUpUrl='';
function epFileUpload(inp){const f=inp.files[0];if(!f)return;const fd=new FormData();fd.append('ajax_action','upload_episode_video');fd.append('episode',f);fd.append('series_id',_srCurId);$('epFilePBar').style.width='0%';$('epFileProgress').style.display='block';$('epFileChip').style.display='none';const xhr=new XMLHttpRequest();xhr.upload.onprogress=e=>{if(e.lengthComputable){const p=Math.round(e.loaded/e.total*100);$('epFilePBar').style.width=p+'%';}};xhr.onload=()=>{$('epFileProgress').style.display='none';try{const d=JSON.parse(xhr.responseText);if(d.success){_epFileUpUrl=d.url;$('epUploadedUrl').value=d.url;$('epFileChip').style.display='flex';$('epFileChipName').textContent=d.original;$('epNum').value=d.episode_number||1;if(!$('epTitle').value.trim())$('epTitle').value=(d.original).replace(/\.[^.]+$/,'');}else al('addEpAlert',d.error||'خطأ في الرفع','e');}catch(e){al('addEpAlert','خطأ في الاستجابة','e');}};xhr.onerror=()=>{$('epFileProgress').style.display='none';al('addEpAlert','انقطع الاتصال','e');};xhr.open('POST',location.href);xhr.send(fd);}
function epSubUpload(inp){const f=inp.files[0];if(!f)return;const fd=new FormData();fd.append('ajax_action','upload_episode_subtitle');fd.append('subtitle',f);fd.append('series_id',_srCurId);fetch(location.href,{method:'POST',body:fd}).then(r=>r.json()).then(d=>{if(d.success){_epSubUpUrl=d.url;$('epSubUrl').value=d.url;$('epSubChip').style.display='flex';$('epSubChipName').textContent=f.name;}else al('addEpAlert',d.error||'خطأ','e');});}
function epAdd(){const num=parseInt($('epNum').value)||1;const title=$('epTitle').value.trim()||'الحلقة '+num;const urlTab=$('etab-url').style.display!=='none';let url=urlTab?$('epUrl').value.trim():($('epUploadedUrl').value.trim());const sub=$('epSubUrl').value.trim()||_epSubUpUrl;const dur=$('epDur').value.trim();if(!url){al('addEpAlert','أدخل رابط الفيديو أو ارفع ملفاً','e');return;}api({ajax_action:'add_episode',series_id:_srCurId,episode_number:num,title,stream_url:url,subtitle_url:sub,duration:dur}).then(d=>{if(d.success){CM('addEpM');loadEps();$('epNum').value=parseInt($('epNum').value)+1;$('epTitle').value='';$('epUrl').value='';$('epSubUrl').value='';$('epDur').value='';$('epUploadedUrl').value='';$('epFileChip').style.display='none';$('epSubChip').style.display='none';_epSubUpUrl='';_epFileUpUrl='';}else al('addEpAlert',d.error||'خطأ','e');});}

function eEpSubUpload(inp){const f=inp.files[0];if(!f)return;const fd=new FormData();fd.append('ajax_action','upload_subtitle_file');fd.append('subtitle',f);$('eEpSubStatus').innerHTML='<span style="color:var(--gold)"><i class="fas fa-spinner fa-spin"></i> جارٍ الرفع...</span>';fetch(location.href,{method:'POST',body:fd}).then(r=>r.json()).then(d=>{if(d.success){$('eEpSub').value=d.vtt_url||d.url;$('eEpSubStatus').innerHTML='<span style="color:#00D084"><i class="fas fa-check-circle"></i> تم رفع الترجمة بنجاح</span>';}else{$('eEpSubStatus').innerHTML='<span style="color:#ff6b6b"><i class="fas fa-exclamation-triangle"></i> '+d.error+'</span>';}}).catch(()=>{if(window.al)al('eEpAlert','خطأ في الاتصال','e');});}
function eEpOpenOS(){$('eEpOsQ').value=$('eEpTitle').value.trim();$('eEpOsRes').style.display='none';$('eEpOsAl').innerHTML='';OM('eEpOsM');}
function eEpOsSearch(){const q=$('eEpOsQ').value.trim(),lang=$('eEpOsLang').value;if(!q){$('eEpOsAl').innerHTML='<span style="color:#ff6b6b">أدخل اسم الفيلم</span>';return;}$('eEpOsSearchBtn').disabled=true;$('eEpOsSearchBtn').innerHTML='...';$('eEpOsRes').style.display='block';$('eEpOsRes').innerHTML='<div style="padding:14px;color:var(--t3);text-align:center"><i class="fas fa-spinner fa-spin"></i> جارٍ البحث...</div>';api({ajax_action:'search_subtitles',query:q,language:lang}).then(d=>{$('eEpOsSearchBtn').disabled=false;$('eEpOsSearchBtn').innerHTML='<i class="fas fa-search"></i> بحث';if(!d.success){$('eEpOsRes').innerHTML='<div style="padding:14px;color:#ff6b6b;text-align:center">'+(d.error||'لا توجد نتائج')+'</div>';return;}$('eEpOsRes').innerHTML=d.data.map((s,i)=>`<div class="sri" onclick="eEpDlSub(${s.file_id})"><div class="sri-main"><div class="sri-title">${s.title.replace(/</g,'&lt;').replace(/>/g,'&gt;')}</div><div class="sri-meta"><span>${s.language.replace(/</g,'&lt;').replace(/>/g,'&gt;')}</span><span>${s.downloads} تنزيل</span></div></div><button class="btn btn-g bsm"><i class="fas fa-download"></i></button></div>`).join('');});}
function eEpDlSub(fid){$('eEpOsAl').innerHTML='<span style="color:var(--gold)"><i class="fas fa-spinner fa-spin"></i> جارٍ التنزيل...</span>';api({ajax_action:'download_subtitle',file_id:fid}).then(d=>{if(!d.success){$('eEpOsAl').innerHTML='<span style="color:#ff6b6b">'+(d.error||'خطأ')+'</span>';return;}$('eEpSub').value=d.vtt_url||d.url;$('eEpSubStatus').innerHTML='<span style="color:#00D084"><i class="fas fa-check-circle"></i> تم جلب الترجمة من OS</span>';CM('eEpOsM');});}

function epEdit(e){
    $('eEpId').value=e.id;
    $('eEpNum').value=e.episode_number;
    $('eEpTitle').value=e.title;
    $('eEpUrl').value=e.stream_url;
    $('eEpSub').value=e.subtitle_url||'';
    $('eEpDur').value=e.duration||'';
    
    let folderOpts = '';
    if (typeof _allFoldersGlobal !== 'undefined') {
        _allFoldersGlobal.forEach(folder => {
            let isSelected = (folder.id == e.series_id) ? 'selected' : '';
            folderOpts += `<option value="${folder.id}" ${isSelected}>${esc(folder.name)}</option>`;
        });
    }
    $('eEpSeriesId').innerHTML = folderOpts;
    OM('editEpM');
}

function epEditSave(){
    const id=$('eEpId').value,
          num=parseInt($('eEpNum').value)||1,
          title=$('eEpTitle').value.trim(),
          url=$('eEpUrl').value.trim(),
          sub=$('eEpSub').value.trim(),
          dur=$('eEpDur').value.trim(),
          newSeriesId=$('eEpSeriesId').value;
          
    if(!title||!url){al('eEpAlert','البيانات ناقصة','e');return;}
    
    api({
        ajax_action: 'edit_episode',
        id: id,
        episode_number: num,
        title: title,
        stream_url: url,
        subtitle_url: sub,
        duration: dur,
        series_id: newSeriesId
    }).then(d=>{
        if(d.success){
            CM('editEpM');
            if (newSeriesId != _srCurId) { alert("✅ تم سحب ونقل هذا الملف إلى المسلسل الآخر ببراعة!"); }
            loadEps();
        } else al('eEpAlert', d.error||'خطأ','e');
    });
}

function epDel(id,name){if(!confirm(`حذف الفيديو/الحلقة "${name}"؟`))return;api({ajax_action:'delete_episode',id}).then(d=>{if(d.success)loadEps();});}

let _bulkFiles=[];
function bulkPreview(files){_bulkFiles=Array.from(files);if(!_bulkFiles.length)return;$('bulkStartBtn').style.display='';$('bulkPreviewList').style.display='block';$('bulkPreviewTitle').textContent=_bulkFiles.length+' مسار للملفات جاهز';const totalSz=_bulkFiles.reduce((s,f)=>s+f.size,0);$('bulkTotalSize').textContent=fmtSz(totalSz);$('bulkItems').innerHTML=_bulkFiles.map((f,i)=>{return`<div class="ep-item" id="bitem-${i}"><div style="width:28px;height:28px;border-radius:50%;background:var(--s3);color:var(--red);display:flex;align-items:center;justify-content:center;font-size:.7rem"><i class="fas fa-play-circle"></i></div><div style="flex:1;min-width:0;font-size:.8rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-weight:bold">${esc(f.name.replace(/\.[^.]+$/, ''))}</div><span style="font-size:.72rem;color:var(--t3)">${fmtSz(f.size)}</span><span class="ep-stat" id="bstat-${i}">تأهب للرفع..</span></div>`;}).join('');}
async function bulkUpload(){if(!_bulkFiles.length||!_srCurId){al('bulkAlert','تأكد من اختيار الملفات','e');return;}$('bulkStartBtn').disabled=true;$('bulkProgress').style.display='block';let done=0,errs=0;for(let i=0;i<_bulkFiles.length;i++){const f=_bulkFiles[i];$('bstat-'+i).textContent='في العملية..';$('bstat-'+i).className='ep-stat up';let vidName=(f.name).replace(/\.[^.]+$/,'');$('bulkCurFile').innerHTML=`<b style="color:var(--t1)">${esc(vidName)}</b>`;const fd=new FormData();fd.append('ajax_action','upload_episode_video');fd.append('episode',f);fd.append('series_id',_srCurId);try{const d=await new Promise((res,rej)=>{const x=new XMLHttpRequest();let t0=Date.now(), l0=0; x.upload.onprogress=(e)=>{if(e.lengthComputable){const p=Math.round((e.loaded/e.total)*100);$('bulkPBar').style.width=p+'%';let t1=Date.now(),dt=(t1-t0)/1000;let sp='جاري التوزيع الشبكي..';if(dt>=0.65){sp=fmtSz((e.loaded-l0)/dt)+'/ث';t0=t1;l0=e.loaded;} $('bulkProgPct').innerHTML=`<span style="color:#00D084" dir="ltr">⏳ ${sp}</span> &nbsp; <b>${p}%</b>`;}};x.onload=()=>{try{res(JSON.parse(x.responseText));}catch(err){rej();}};x.onerror=()=>rej();x.open('POST',location.href);x.send(fd);});if(d.success){const title=(d.original||f.name).replace(/\.[^.]+$/,'');const d2=await api({ajax_action:'add_episode',series_id:_srCurId,episode_number:(i+1),title:title,stream_url:d.url,subtitle_url:'',duration:''});if(d2.success){done++;$('bstat-'+i).textContent='✅ دُمج واكتمل';$('bstat-'+i).className='ep-stat ok';}else{errs++;$('bstat-'+i).textContent='❌ صُد بخادم الداتا';$('bstat-'+i).className='ep-stat err';}}else{errs++;$('bstat-'+i).textContent='❌ رُفض الرفع كلياً';$('bstat-'+i).className='ep-stat err';}}catch(e){errs++;$('bstat-'+i).textContent='❌ فُصل من العوامل';$('bstat-'+i).className='ep-stat err';}}$('bulkPBar').style.width='100%';$('bulkProgPct').textContent='100%';$('bulkCurFile').textContent='';$('bulkResult').style.display='block';$('bulkResult').innerHTML=`<div class="al ${errs?'al-e':'al-s'}" style="margin:0"><i class="fas fa-${errs?'exclamation-circle':'check-circle'}"></i> خلاصة النتائج الحسابية: تم تأمين وتسجيل ( ${done} ) ملفات خاضعة للاستدامة، وسُقط (${errs}).</div>`;$('bulkStartBtn').disabled=false;_bulkFiles=[];if(_srCurId)loadEps();}

let VID={file:null,filename:'',url:'',subFile:'',subUrl:'',subVttUrl:'',opt:'none'};
let smartDlInterval = null;
let currentSmartDlFile = '';

function vtab(t){
    document.querySelectorAll('#vp1 .etab').forEach(b=>b.classList.remove('on'));
    event.target.classList.add('on');
    $('vtab-url').style.display = t==='url'?'':'none';
    $('vtab-file').style.display = t==='file'?'':'none';
}

function vidSmartDl(){
    const url = $('smartUrlInp').value.trim();
    if(!url){ al('v1alert','أدخل رابط مباشر صالح','e'); return; }

    const btn = $('smartDlBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="sp"></span> جاري تهيئة الاتصال بالرابط...';

    $('vidProg').style.display = 'block';
    $('vidPBar').style.width = '0%';
    $('vidPBar').style.animation = 'none'; 
    $('vidPct').textContent = '0%';
    $('vidPLabel').textContent = 'جاري سحب خصائص الرابط...';
    $('cancelDlBtn').style.display = 'none';
    $('vidProgSp').style.display = 'inline-block';
    $('vidChip').style.display = 'none';
    $('vNext1').disabled = true;
    al('v1alert', '', '');

    // إرسال طلب تجهيز الاستيراد الذكي أولاً 
    api({ajax_action:'prep_smart_dl', url: url}).then(initData => {
        if(!initData.success) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-download"></i> محاولة سحب الرابط مجدداً';
            $('vidProg').style.display = 'none';
            al('v1alert', initData.error || 'عذراً الرابط يرفض الاتصال، قم بتجربة رابط آخر مباشر!', 'e');
            return;
        }

        const fname = initData.filename;
        const originalName = initData.original;
        let expectedTotalSize = initData.total || 0;
        
        currentSmartDlFile = fname;
        $('cancelDlBtn').style.display = 'inline-block';
        $('cancelDlBtn').onclick = () => cancelSmartDl(fname);
        btn.innerHTML = '<span class="sp"></span> السيرفر يسحب الرابط بأقصى سرعته!';

        let lastLoaded = 0; let lastTime = performance.now();

        // نبض الاستعلام الحي لتوضيح حالة التحميل على الشاشة
        smartDlInterval = setInterval(() => {
            api({ajax_action: 'check_smart_dl', filename: fname}).then(pd => {
                if(pd.success && typeof pd.loaded !== 'undefined') {
                    let curLoaded = pd.loaded || 0; let tot = pd.total || expectedTotalSize;
                    let nowTime = performance.now(); let timeDiff = (nowTime - lastTime) / 1000; 
                    let loadedDiff = curLoaded - lastLoaded; 
                    
                    let speedTxt = "جاري الحساب";
                    if(timeDiff > 0 && loadedDiff > 0) { speedTxt = fmtSz(loadedDiff / timeDiff) + '/ث'; }
                    lastLoaded = curLoaded; lastTime = nowTime;

                    let pct = 0;
                    if(tot > 0) {
                        pct = Math.round((curLoaded / tot) * 100);
                        if(pct > 100) pct = 100;
                        $('vidPLabel').innerHTML = `<span style="color:#00D084;font-weight:bold;margin-left:8px;" dir="ltr">[ سرعة السيرفر: ${speedTxt} ]</span> <span dir="ltr">${fmtSz(curLoaded)} / ${fmtSz(tot)}</span>`;
                        $('vidPBar').style.width = pct + '%';
                        $('vidPct').textContent = pct + '%';
                    } else {
                        // الحجم غير معلن (رابط مُشفر لكنه يعمل) يتم اظهار انه يسحب فقط
                        $('vidPBar').style.width = '100%';
                        $('vidPBar').style.animation = 'bk 1.5s ease infinite'; 
                        $('vidPLabel').innerHTML = `<span style="color:#00D084;font-weight:bold;margin-left:8px;" dir="ltr">[ سرعة السيرفر: ${speedTxt} ]</span> <span dir="ltr">سحب إلى الان: ${fmtSz(curLoaded)}</span>`;
                        $('vidPct').textContent = 'جارٍ...';
                    }
                }
            }).catch(()=>{}); // منع تدمير المتصفح من الاخطاء الدورية 
        }, 1500);

        // هنا السحب الرئيسي بالخلفية
        api({ajax_action:'do_smart_dl', url: url, filename: fname}).then(d => {
            clearInterval(smartDlInterval);
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-download"></i>سحب فيلم آخر جديد';
            $('vidProg').style.display = 'none';
            $('cancelDlBtn').style.display = 'none';

            if(d.success) {
                VID.filename = d.filename; VID.url = d.url; VID.file = null;
                $('vidChip').style.display = 'flex'; $('vidChipName').textContent = originalName;
                $('vidChipSize').textContent = fmtSz(d.size); $('vNext1').disabled = false;
                const title = originalName.replace(/\.[^.]+$/,'').replace(/[._\-]/g,' ').replace(/\b(720p|1080p|4k|bdrip|web|hdtv|bluray)\b/gi,'').trim();
                $('osQ').value = title; $('vChanName').value = title;
                al('v1alert', '🚀 انتهى الحفظ تماماً وأصبح الملف في قلب خوادمك!', 's');
                $('smartUrlInp').value = '';
            } else { al('v1alert', d.error || 'لقد أمرت النظام بوقف التحميل أو توقف المزوّد.', 'e'); }
        }).catch(err =>{
             clearInterval(smartDlInterval); btn.disabled = false; btn.innerHTML = '<i class="fas fa-download"></i>حاول مرة اخرى';
             $('vidProg').style.display='none'; $('cancelDlBtn').style.display = 'none';
             al('v1alert','انتهت مهلة المراقبة في المتصفح، ولكن التحميل الفعلي قد يكون شغال خلف الكواليس داخل إدارة الفيديوهات.', 'i');
        });
    });
}

function cancelSmartDl(fname) {
    if(!confirm('سيتسبب هذا بقطع تدفق السحب الخارجي وحذف بقاياه. متابعة؟')) return;
    $('cancelDlBtn').disabled = true;
    $('cancelDlBtn').innerHTML = '<i class="fas fa-spinner fa-spin"></i> أبلغنا السيرفر.. يتم الإعدام!';
    api({ajax_action: 'abort_smart_dl', filename: fname}); // إلقاء إشارة الإيقاف القسرية للمتغير
}

function vidUpload(inp){const f=inp.files[0];if(!f)return;const fd=new FormData();fd.append('ajax_action','upload_video');fd.append('video',f);$('vidProg').style.display='block';$('cancelDlBtn').style.display='none';$('vidProgSp').style.display='inline-block';$('vidPBar').style.animation='none';$('vidChip').style.display='none';const xhr=new XMLHttpRequest();xhr.upload.onprogress=e=>{if(e.lengthComputable){const p=Math.round(e.loaded/e.total*100);$('vidPBar').style.width=p+'%';$('vidPct').textContent=p+'%';$('vidPLabel').textContent=p<100?'رفع '+fmtSz(e.loaded)+' / '+fmtSz(f.size):'معالجة…';}};xhr.onload=()=>{$('vidProg').style.display='none';const raw=xhr.responseText.trim();if(!raw){al('v1alert','الخادم لم يُرجع رداً — تحقق من إعدادات PHP','e');return;}let d;try{d=JSON.parse(raw);}catch(ex){const preview=raw.replace(/<[^>]+>/g,'').substring(0,300);al('v1alert','خطأ في الاستجابة: '+preview,'e');return;}if(d.success){VID.filename=d.filename;VID.url=d.url;VID.file=f;$('vidChip').style.display='flex';$('vidChipName').textContent=d.original;$('vidChipSize').textContent=fmtSz(f.size);$('vNext1').disabled=false;const title=d.original.replace(/\.[^.]+$/,'').replace(/[._\-]/g,' ').replace(/\b(720p|1080p|4k|bdrip|web|hdtv|bluray)\b/gi,'').trim();$('osQ').value=title;$('vChanName').value=title;al('v1alert','✅ تم رفع الفيديو بنجاح','s');}else{let msg=d.error||'خطأ غير معروف';if(d.debug)msg+=' — '+d.debug;al('v1alert',msg,'e');}};xhr.onerror=()=>{$('vidProg').style.display='none';al('v1alert','انقطع الاتصال بالخادم','e');};xhr.open('POST',location.href);xhr.send(fd);}
function vidDebug(){api({ajax_action:'debug_upload'}).then(d=>{const dbg=$('v1debug');dbg.style.display='block';if(d.success){const ok='✅',no='❌';dbg.innerHTML=`<strong>إعدادات PHP:</strong><br>upload_max_filesize: <b>${d.upload_max_filesize}</b><br>post_max_size: <b>${d.post_max_size}</b><br>مجلد الرفع: <b>${d.upload_dir}</b><br>المجلد موجود: ${d.dir_exists?ok:no}<br>قابل للكتابة: ${d.dir_writable?ok:no}<br>PHP: ${d.php_version}<br><br><small style="color:var(--t3)">إذا كانت القيم 8M أو أقل، أضف للـ .htaccess:<br>php_value upload_max_filesize 2048M<br>php_value post_max_size 2048M</small>`;}else dbg.innerHTML='خطأ: '+d.error;});}
function vidReset(){VID={file:null,filename:'',url:'',subFile:'',subUrl:'',subVttUrl:'',opt:'none'};$('vidChip').style.display='none';$('vidFileIn').value='';$('vNext1').disabled=true;al('v1alert','','');}
function vidGo(step){if(step===3){$('mSumV').textContent=VID.filename||'—';$('mSumS').textContent=VID.subFile?(VID.subFile+' ✅'):'بدون ترجمة';}document.querySelectorAll('.vp').forEach(p=>p.classList.remove('act'));document.querySelectorAll('.vs').forEach(v=>v.classList.remove('act'));$('vp'+step).classList.add('act');$('vs'+step).classList.add('act');for(let i=1;i<step;i++)$('vs'+i).classList.add('done');}
function vidSubOpt(opt){VID.opt=opt;document.querySelectorAll('.so').forEach(s=>s.classList.remove('sel'));$('so-'+opt).classList.add('sel');$('osCard').style.display=opt==='search'?'block':'none';$('subUpCard').style.display=opt==='upload'?'block':'none';}
function subFileUpload(inp){const f=inp.files[0];if(!f)return;const fd=new FormData();fd.append('ajax_action','upload_subtitle_file');fd.append('subtitle',f);al('subAl','<span class="sp"></span> جارٍ الرفع…','i');fetch(location.href,{method:'POST',body:fd}).then(r=>r.json()).then(d=>{if(d.success){VID.subFile=d.filename;VID.subUrl=d.url;$('upSubChip').style.display='flex';$('upSubName').textContent=f.name;al('subAl','✅ تم','s');}else al('subAl',d.error||'خطأ','e');});}

function osLogin(){
    const u=$('osU').value.trim(),p=$('osP').value.trim(),k=$('osApiKey').value.trim();
    if(!u||!p){al('osLAlert','أدخل اسم المستخدم وكلمة المرور','e');return;}
    if(!k){al('osLAlert','أدخل مفتاح API','e');return;}
    $('osLBtn').disabled=true;$('osLBtn').innerHTML='<span class="sp"></span>';
    api({ajax_action:'os_login',username:u,password:p,api_key:k}).then(d=>{
        $('osLBtn').disabled=false;$('osLBtn').innerHTML='<i class="fas fa-sign-in-alt"></i>تسجيل الدخول';
        if(d.success){
            $('osNL').style.display='none';$('osL').style.display='flex';$('osLUser').textContent=d.username;
        }else al('osLAlert',d.error||'خطأ','e');
    });
}
function osLogout(){
    api({ajax_action:'os_logout'}).then(()=>{
        $('osNL').style.display='block';$('osL').style.display='none';$('osRes').innerHTML='';$('osLUser').textContent='';
    });
}

function osSearch(){const q=$('osQ').value.trim(),lang=$('osLang').value;if(!q){al('osAl','أدخل اسم الفيلم','e');return;}$('osSearchBtn').disabled=true;$('osSearchBtn').innerHTML='<span class="sp"></span>';$('osRes').style.display='flex';$('osRes').innerHTML=`<div style="padding:14px;color:var(--t3);text-align:center"><span class="sp"></span> جارٍ البحث…</div>`;al('osAl','','');api({ajax_action:'search_subtitles',query:q,language:lang}).then(d=>{$('osSearchBtn').disabled=false;$('osSearchBtn').innerHTML='<i class="fas fa-search"></i>بحث';if(!d.success){al('osAl',d.error||'لا توجد نتائج','e');$('osRes').style.display='none';return;}$('osRes').innerHTML=d.data.map((s,i)=>`<div class="sri" id="sri-${i}" onclick="srClick(${i},${s.file_id},'${escA(s.filename)}')"><div class="sri-main"><div class="sri-title">${esc(s.title)} ${s.year?`(${s.year})`:''}</div><div class="sri-meta"><span>${esc(s.release||'')}</span><span class="stag stag-l">${esc(s.language)}</span><span>${s.downloads} تنزيل</span></div></div><button class="btn btn-g bsm" onclick="event.stopPropagation();dlSub(${s.file_id},'${escA(s.filename)}')"><i class="fas fa-download"></i></button></div>`).join('');});}
function srClick(i,fid,fname){document.querySelectorAll('.sri').forEach(s=>s.classList.remove('sel'));$('sri-'+i)&&$('sri-'+i).classList.add('sel');dlSub(fid,fname);}
function dlSub(fid,fname){al('osAl','<span class="sp"></span> جارٍ تنزيل الترجمة…','i');api({ajax_action:'download_subtitle',file_id:fid}).then(d=>{if(!d.success){al('osAl',d.error||'خطأ','e');return;}VID.subFile=d.filename;VID.subUrl=d.url;VID.subVttUrl=d.vtt_url||d.url;$('selSubChip').style.display='flex';$('selSubName').textContent=fname;al('osAl','✅ تم تنزيل الترجمة — باقي '+d.remaining+' تنزيل اليوم','s');});}
function clearSub(){VID.subFile='';VID.subUrl='';VID.subVttUrl='';$('selSubChip').style.display='none';}

function vidSave(){
    const name=$('vChanName').value.trim();
    const cid=$('vChanCat').value;
    const targetId=$('vTargetSeries').value; 
    
    if(targetId == "0" && !cid){ al('v3alert','يُرجى إختيار أي قسم ليتأسس العمل فيه.','e');return;}
    if(!name && targetId == "0"){ al('v3alert','ما هو أسم فيلمك؟ أدخله بوضوح.','e');return;}
    if(!name && targetId > "0"){ $('vChanName').value = 'عنصر / حلقة تابعه للمسلسل المختار'; }

    if(!VID.url){al('v3alert','ألم ترفع أي فيديو إلى الان! عُد لليمين للخطوات.','e');return;}
    const btn=document.querySelector('#vp3 .btn-s');
    if(btn){btn.disabled=true;btn.innerHTML='<span class="sp"></span> أرقام القاعدة تقيّد إعداداتك حالياً...';}
    
    if(VID.subFile){
        al('v3alert','<span class="sp"></span> المبرمج يدمج سطورك لملفك، انتظر للحظة…','i');
        api({ajax_action:'merge_subtitle',video_file:VID.filename,subtitle_file:VID.subFile}).then(d=>{
            if(!d.success){
                al('v3alert',d.error||'خطأ بملف الترجمة','e');
                if(btn){btn.disabled=false;btn.innerHTML='<i class="fas fa-check"></i>حاول إصلاحه وانقره';} return;
            }
            api({
               ajax_action:'save_to_shashety_auto', 
               category_id: cid, name:$('vChanName').value.trim(), 
               url:VID.url, subtitle_url:(d.method==='no_ffmpeg')?(d.subtitle_url||VID.subUrl):'',
               target_series_id: targetId 
            }).then(d2=>{
                if(btn){btn.disabled=false;btn.innerHTML='<i class="fas fa-check"></i> حفظ جديد لمكتتبك';}
                if(d2.success){
                    $('vidResult').style.display='block';
                    $('vidResultInfo').innerHTML= (targetId=="0") ? 'الفيلم الان حر طليق بعمل جديد وخاص.' : 'طاعة المطور اكتملت واصطُف بجوار بقية اخوانه للمسلسل المحدد!';
                    al('v3alert','','');
                }else al('v3alert',d2.error||'انقطعت شاشتك بالقواعد المبرمجة','e');
            });
        });
    }else{
        api({
           ajax_action:'save_to_shashety_auto',
           category_id:cid, name:$('vChanName').value.trim(), url:VID.url, subtitle_url:'',
           target_series_id: targetId 
        }).then(d=>{
            if(btn){btn.disabled=false;btn.innerHTML='<i class="fas fa-check"></i> تسجيل ملفه وإرسائه.';}
            if(d.success){
                $('vidResult').style.display='block';
                $('vidResultInfo').innerHTML= (targetId=="0") ? 'سيبدأ الجمهور رؤية فيلمك، شاهده بادارة شاشتي.' : 'أضيفت الحلقة في شاشتك بنجاح لتشكيلات مسلسلك المطلوب.';
                al('v3alert','','');
            }else al('v3alert',d.error||'حدث امر خارجي بمنظومات الخوادم','e');
        });
    }
}

// ══ SERIES POSTER UPLOAD ══
function srPosterUpload(inp,urlInputId,thumbId,statusId){const f=inp.files[0];if(!f)return;const statusEl=$(statusId),thumbEl=$(thumbId);statusEl.innerHTML='<span class="sp"></span> <span style="color:var(--t2)">جارٍ رفع الصورة…</span>';const fd=new FormData();fd.append('ajax_action','upload_series_poster');fd.append('poster',f);const xhr=new XMLHttpRequest();xhr.upload.onprogress=e=>{if(e.lengthComputable){const p=Math.round(e.loaded/e.total*100);statusEl.innerHTML=`<span class="sp"></span> <span style="color:var(--gold)">${p}%</span>`;}};xhr.onload=()=>{try{const d=JSON.parse(xhr.responseText);if(d.success){$(urlInputId).value=d.url;statusEl.innerHTML=`<span style="color:#00D084"><i class="fas fa-check-circle"></i> تم رفع الصورة بنجاح — ${fmtSz(d.size)}</span>`;thumbEl.style.display='block';thumbEl.querySelector('img').src=d.url;thumbEl.querySelector('img').style.borderColor='#00D084';}else statusEl.innerHTML=`<span style="color:#ff6b6b"><i class="fas fa-exclamation-circle"></i> ${d.error||'خطأ في الرفع'}</span>`;}catch(e){statusEl.innerHTML=`<span style="color:#ff6b6b"><i class="fas fa-exclamation-circle"></i> خطأ في الاستجابة</span>`;}inp.value='';};xhr.onerror=()=>{statusEl.innerHTML=`<span style="color:#ff6b6b"><i class="fas fa-exclamation-circle"></i> انقطع الاتصال</span>`;};xhr.open('POST',location.href);xhr.send(fd);}
function srPosterPreview(thumbId,url){const thumbEl=$(thumbId);if(!url||!url.startsWith('http')){thumbEl.style.display='none';return;}thumbEl.style.display='block';const img=thumbEl.querySelector('img');img.src=url;img.onerror=()=>{thumbEl.style.display='none';};}

// ══ VIDEO MANAGE (With isolated moves directly interacting Shashety vs Public Videos without transferring dir variables logic directly mapping physical files internally easily managed visually by CSS filtering.)
let _vmAll=[],_vmCtx={},_vmMoveCtx={};

function vmTriggerSub(fn, type){
    _vmCtx = {fn, type};
    $('vmSubUp').click();
}

function vmHandleSubUp(inp){
    const f=inp.files[0]; if(!f) return;
    al('vmLoad','<span class="sp"></span> جارٍ رفع الترجمة وتجهيزها...','i'); 
    $('vmLoad').style.display='block';
    const fd=new FormData(); fd.append('ajax_action','upload_subtitle_file'); fd.append('subtitle',f);
    fetch(location.href,{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
        $('vmLoad').style.display='none';
        inp.value='';
        if(d.success) vmOpenSave(_vmCtx.fn, _vmCtx.type, d.url);
        else alert('خطأ في الترجمة السريعة: ' + (d.error||''));
    });
}

function vmLoad(){$('vmGrid').style.display='none';$('vmEmpty').style.display='none';$('vmLoad').style.display='block';api({ajax_action:'list_videos'}).then(d=>{$('vmLoad').style.display='none';if(!d.success)return;_vmAll=d.videos||[];vmRender(_vmAll);});}
function vmFilter(){const q=$('vmSearch').value.toLowerCase(),t=$('vmType').value;vmRender(_vmAll.filter(v=>(!q||v.filename.toLowerCase().includes(q))&&(t==='all'||v.type===t)));}

function vmRender(vids){
    const g=$('vmGrid'),e=$('vmEmpty');
    $('vmCnt').textContent=vids.length+' ملف بالخادم المربوطة.';
    if(!vids.length){g.style.display='none';e.style.display='block';return;}
    e.style.display='none';g.style.display='grid';
    
    // المسميات بالالوان تريح النفسية وتفرق المجلدات بسهولة للتحكم المستقر.
    const typeLabels = {uploaded:'تم استخراجة للعام (حراً طليقا)', merged:'خاضع لملف الترجمة المستقل', series:'الآن يسكن متجذر لشاشتي (حلقة ومسلسلات).'};
    const typeColors = {uploaded:'rgba(76,201,240,.9)', merged:'rgba(0,208,132,.9)', series:'rgba(245,166,35,.9)'};
    
    g.innerHTML=vids.map(v=>`<div class="vmc" id="vmc-${esc(v.filename)}">
        <div class="vmt" onclick='testChannel("${escA(v.url)}","${escA(v.filename)}")'>
            <video src="${esc(v.url)}" preload="none" muted style="pointer-events:none"></video>
            <div class="vmt-ic"><i class="fas fa-play"></i></div>
            <span class="vmbdg" style="background:${typeColors[v.type]||'#333'};color:${v.type==='uploaded'?'#000':'#fff'}">${typeLabels[v.type]||v.type}</span>
        </div>
        <div class="vminfo">
            <div class="vmname" title="${esc(v.filename)}">${esc(v.filename)}</div>
            <div class="vmmeta"><span><i class="fas fa-hdd"></i> ${v.size_mb} MB</span><span>${esc(v.date)}</span></div>
        </div>
        <div class="vmacts">
            <button class="vmb pl" onclick='testChannel("${escA(v.url)}","${escA(v.filename)}")' title="إلعب مقطعاً مرئياً من داخل هذا الفيديو الان!"><i class="fas fa-play"></i></button>
            <button class="vmb sub" onclick="vmTriggerSub('${escA(v.filename)}','${v.type}')" title="أوّل ما تلحق بهذا المسار ملفات ال vtt الخاص بك سينظر إليها اللاعب باختيار اللمس!"><i class="fas fa-closed-captioning"></i></button>
            <button class="vmb mv" onclick="vmOpenMove('${escA(v.filename)}','${v.type}')" title="تحول جسدي داخلي للـ Filesystem الخاص بنا بالاعماق للملف للقفز للمسلسل أو خارج المجمعات الكتلية.."><i class="fas fa-exchange-alt"></i></button>
            <button class="vmb sv" onclick="vmOpenSave('${escA(v.filename)}','${v.type}')" title="طِور المسار الموثق وإرمه الى داخل مسلسلك المراد !"><i class="fas fa-save"></i></button>
            <button class="vmb dl" onclick="vmDel('${escA(v.filename)}','${v.type}')" title="الإتلاف الكُلي المروع من الجُذر للقرص الصلب السرفر خاصتك!"><i class="fas fa-trash-alt"></i></button>
        </div>
    </div>`).join('');
}

function vmOpenMove(fn, type){
    _vmMoveCtx = {fn, type};
    $('vmMoveFile').textContent = 'هندسة نقل جذر هذا الملف ببراعة السرفرات: ' + fn;
    
    let folderOpts = '<optgroup label="نقل لعراء السيرفر الرئيسي وخروجه للـ Public Videos File!">';
    folderOpts += '<option value="videos">🌐 استخراج وتعريه الملف للرفع العام (أقضي أرسالاتة ومسيراته لشبكة خارج ال Series.)</option>';
    folderOpts += '</optgroup>';
    
    folderOpts += '<optgroup label="إدخاله وحصاره داخل مجمع لجدول مسلسلات شاشتي.">';
    if (typeof _allFoldersGlobal !== 'undefined') {
        _allFoldersGlobal.forEach(folder => {
            folderOpts += `<option value="${folder.id}">🎬 دمجة وإيواه فوراً كحلقة مستجدة لمجمع ومجلد : ${esc(folder.name)}</option>`;
        });
    }
    folderOpts += '</optgroup>';
    
    $('vmMoveTarget').innerHTML = folderOpts;
    
    al('vmMoveAlert','','');
    OM('vmMoveM');
}

function vmDoMove(){
    const target = $('vmMoveTarget').value;
    al('vmMoveAlert', '<span class="sp"></span> يُتخذُ هذا الإيعاز حاسوبياً بمركز الاتصال الخاصك.. جاري توجية ال Path للـ Route الجديد وقطع الارتباط السابق، ابقه متفتح.', 'i');
    
    api({ajax_action: 'move_video_file', filename: _vmMoveCtx.fn, type: _vmMoveCtx.type, target_folder: target}).then(d => {
        if(d.success) {
            al('vmMoveAlert', '✅ ' + d.message, 's');
            setTimeout(() => { CM('vmMoveM'); vmLoad(); }, 1600);
        } else {
            al('vmMoveAlert', d.error || 'عقدة مستعصية جارية حدثت ولم يتحول.', 'e');
        }
    });
}

function vmOpenSave(fn,type, subUrl=''){
    _vmCtx={fn,type};
    $('vmSaveFile').textContent='ترخيص: '+fn;
    $('vmSaveTitle').value=fn.replace(/^(vid_|merged_|vid_dl_|ep_)[a-z0-9]+_?/i,'').replace(/\.[^.]+$/,'').replace(/[_\-.]/g,' ').trim();
    $('vmSaveSubUrl').value = subUrl;
    $('vmSaveSub').style.display = subUrl ? 'block' : 'none';
    al('vmSaveAlert','','');
    vToggleSeriesFields($('vmSaveTargetSeries').value, 'manage');
    OM('vmSaveM');
}

function vmDoSave(){
    const title = $('vmSaveTitle').value.trim(), 
          cid = $('vmSaveCat').value, 
          subUrl = $('vmSaveSubUrl').value,
          targetId = $('vmSaveTargetSeries').value; 

    if(targetId == "0" && (!title || !cid)) { al('vmSaveAlert','الترسانة العصبية المجهزة بالمكتب تمنع حفظها الا عند جردك لإمضاء الفصول للفيلم او الحلقه للنوع الجديد.','e'); return;}
    
    al('vmSaveAlert', '<span class="sp"></span> تدريجات الاضافات تعمل لحفر الداتا بالأساس...', 'i');
    
    api({
        ajax_action:'save_video_manual', 
        filename: _vmCtx.fn, 
        video_type: _vmCtx.type, 
        title: title || 'انشاء مستحدث من إدارة المحرر.', 
        category_id: cid, 
        subtitle_url: subUrl,
        target_series_id: targetId
    }).then(d=>{
        if(d.success){
            CM('vmSaveM');
            alert(targetId == "0" ? "تشريع النظام للمجلد المُنشئ تُم بشكل قوي، أُحتسب هذا المسار!" : "تم رعاية المُختار لمربوطه الساسي وأرسُل لبر المجمع الآلي المُسبق الحفظ شاشتي!");
            setTimeout(()=>{ S('series'); loadSeries(); }, 500);
        }else al('vmSaveAlert', d.error||'تعذر وصول السرديات للمكتب القُدير', 'e');
    });
}

function vmDel(fn,type){if(!confirm('خطر الازالة: سوف تُنسف الذكريات كاملة عن القرص السحب الثابثة الخاصة بهذا المسير ('+fn+') ؟'))return;api({ajax_action:'delete_video',filename:fn,type}).then(d=>{if(d.success){const c=document.getElementById('vmc-'+fn);if(c){c.style.opacity='0';c.style.transition='all .3s';setTimeout(()=>c.remove(),300);}_vmAll=_vmAll.filter(v=>v.filename!==fn);$('vmCnt').textContent=_vmAll.length+' جِرد مقطعيّ وحيد الان متوفّر بالسيرفر.';}else alert('❌ '+(d.error||'استغاثة لملكية السرفر غير خاضعة.'));});}

// ═══════════════════════════════════════════════════════════════
// نظام البحث المتعدد المصادر (TMDB + AniList + OMDb) v3
// ═══════════════════════════════════════════════════════════════
function switchSource(ctx,source,btn){
    _currentSource[ctx]=source;
    var t=$((ctx==='add'?'add':'edit')+'SrSourceTabs');
    if(!t)return;
    t.querySelectorAll('.source-tab').forEach(function(b){b.classList.remove('active','tmdb-active','anilist-active','omdb-active');});
    btn.classList.add('active',source+'-active');
    var r=$('mediaRes_'+ctx);if(r)r.style.display='none';
}
function mediaAutoSearch(ctx,val){
    clearTimeout(_mediaSearchTimer[ctx]);
    var r=$('mediaRes_'+ctx);
    if(!val||val.length<3){if(r)r.style.display='none';return;}
    _mediaSearchTimer[ctx]=setTimeout(function(){mediaSearch(ctx);},700);
}
function mediaSearch(ctx){
    var nid=ctx==='add'?'srName':'eSrName';
    var val=$(nid).value.trim();
    if(!val||val.length<2)return;
    var src=_currentSource[ctx];
    var r=$('mediaRes_'+ctx);
    r.style.display='block';
    r.innerHTML='<div class="media-result-item"><div class="media-result-info" style="color:var(--t3)"><span class="sp"></span> جارٍ البحث في '+src.toUpperCase()+'…</div></div>';
    if(src==='tmdb')searchTMDB_ms(ctx,val);
    else if(src==='anilist')searchAniList_ms(ctx,val);
    else if(src==='omdb')searchOMDb_ms(ctx,val);
}
async function searchTMDB_ms(ctx,q){
    var key=getTmdbKey(),r=$('mediaRes_'+ctx);
    if(!key){r.innerHTML='<div class="media-result-item"><div class="media-result-info"><span style="color:#ff6b6b"><i class="fas fa-key"></i> مفتاح TMDB مفقود</span></div></div>';return;}
    try{
        var rA=await fetch('https://api.themoviedb.org/3/search/multi?api_key='+encodeURIComponent(key)+'&query='+encodeURIComponent(q)+'&language=ar');
        var rE=await fetch('https://api.themoviedb.org/3/search/multi?api_key='+encodeURIComponent(key)+'&query='+encodeURIComponent(q)+'&language=en-US');
        if(rA.status===401){r.innerHTML='<div class="media-result-item"><div class="media-result-info"><span style="color:#ff6b6b"><i class="fas fa-key"></i> مفتاح TMDB غير صحيح</span></div></div>';return;}
        var dA=await rA.json(),dE=await rE.json();
        var seen=new Set();
        var items=[].concat(dA.results||[],dE.results||[]).filter(function(i){if(seen.has(i.id))return false;seen.add(i.id);return(i.title||i.name);}).slice(0,8);
        if(!items.length){r.innerHTML='<div class="media-result-item"><div class="media-result-info" style="color:var(--t3)"><i class="fas fa-search"></i> لا نتائج</div></div>';return;}
        r.innerHTML=items.map(function(i){
            var t=i.title||i.name||'',y=(i.release_date||i.first_air_date||'').substring(0,4),
                p=i.poster_path?'https://image.tmdb.org/t/p/w92'+i.poster_path:'',
                pf=i.poster_path?'https://image.tmdb.org/t/p/w500'+i.poster_path:'',
                mt=i.media_type||'movie',
                th=mt==='tv'?'<span class="bdg bp" style="font-size:.6rem">مسلسل</span>':'<span class="bdg bc" style="font-size:.6rem">فيلم</span>',
                rt=i.vote_average?'<span style="color:var(--gold);font-size:.65rem"><i class="fas fa-star"></i> '+i.vote_average.toFixed(1)+'</span>':'';
            return '<div class="media-result-item" onclick="mediaPick(\''+ctx+'\',\''+escA(t)+'\',\''+escA(pf)+'\',\''+escA(i.overview||'')+'\')">'+
                (p?'<img src="'+esc(p)+'" onerror="this.style.opacity=\'.2\'">':'<div style="width:36px;height:50px;background:var(--s3);border-radius:4px;display:flex;align-items:center;justify-content:center"><i class="fas fa-film" style="color:var(--t3)"></i></div>')+
                '<div class="media-result-info"><div class="media-result-title">'+esc(t)+'</div><div class="media-result-meta">'+(y||'\u2014')+' '+th+' '+rt+' <span class="source-badge tmdb">TMDB</span></div></div>'+
                '<button type="button" class="tmdb-info-btn" onclick="event.preventDefault();event.stopPropagation();showTmdbInfo('+i.id+',\''+mt+'\')" title="\u062A\u0641\u0627\u0635\u064A\u0644"><i class="fas fa-info"></i></button></div>';
        }).join('');
    }catch(e){r.innerHTML='<div class="media-result-item"><div class="media-result-info" style="color:#ff6b6b"><i class="fas fa-exclamation-triangle"></i> خطأ اتصال TMDB</div></div>';}
}
async function searchAniList_ms(ctx,q){
    var r=$('mediaRes_'+ctx);
    var gql='query($s:String){Page(page:1,perPage:10){media(search:$s,type:ANIME,sort:POPULARITY_DESC){id title{romaji english native}coverImage{medium large}startDate{year}episodes format averageScore description(asHtml:false)}}}';
    try{
        var res=await fetch('https://graphql.anilist.co',{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json'},body:JSON.stringify({query:gql,variables:{s:q}})});
        var data=await res.json();
        var items=(data&&data.data&&data.data.Page&&data.data.Page.media)||[];
        if(!items.length){r.innerHTML='<div class="media-result-item"><div class="media-result-info" style="color:var(--t3)"><i class="fas fa-search"></i> لا نتائج أنمي</div></div>';return;}
        r.innerHTML=items.map(function(i){
            var t=i.title.english||i.title.romaji||i.title.native||'',
                ta=i.title.native||i.title.romaji||'',
                y=i.startDate?i.startDate.year:'',
                p=i.coverImage?i.coverImage.medium:'',pf=i.coverImage?i.coverImage.large:'',
                ep=i.episodes?i.episodes+' حلقة':'',
                sc=i.averageScore?'<span style="color:var(--gold);font-size:.65rem"><i class="fas fa-star"></i> '+(i.averageScore/10).toFixed(1)+'</span>':'',
                fm={TV:'مسلسل',MOVIE:'فيلم',OVA:'OVA',ONA:'ONA',SPECIAL:'خاص'},
                fl=fm[i.format]||i.format||'',
                ds=(i.description||'').replace(/<[^>]+>/g,'').substring(0,200);
            return '<div class="media-result-item" onclick="mediaPick(\''+ctx+'\',\''+escA(t)+'\',\''+escA(pf)+'\',\''+escA(ds)+'\')">'+
                (p?'<img src="'+esc(p)+'" onerror="this.style.opacity=\'.2\'">':'<div style="width:36px;height:50px;background:var(--s3);border-radius:4px;display:flex;align-items:center;justify-content:center"><i class="fas fa-dragon" style="color:var(--t3)"></i></div>')+
                '<div class="media-result-info"><div class="media-result-title">'+esc(t)+(ta&&ta!==t?' <span style="color:var(--t3);font-size:.72rem">('+esc(ta)+')</span>':'')+'</div>'+
                '<div class="media-result-meta">'+(y||'\u2014')+' <span class="bdg" style="background:rgba(76,201,240,.1);color:#4CC9F0;border:1px solid rgba(76,201,240,.2);font-size:.6rem">'+fl+'</span> '+(ep?'<span style="font-size:.65rem">'+ep+'</span> ':'')+sc+' <span class="source-badge anilist">AniList</span></div></div></div>';
        }).join('');
    }catch(e){r.innerHTML='<div class="media-result-item"><div class="media-result-info" style="color:#ff6b6b"><i class="fas fa-exclamation-triangle"></i> خطأ اتصال AniList</div></div>';}
}
async function searchOMDb_ms(ctx,q){
    var r=$('mediaRes_'+ctx),key=getOmdbKey();
    if(!key){r.innerHTML='<div class="media-result-item" onclick="S(\'api-settings\')" style="cursor:pointer"><div class="media-result-info"><span style="color:#ff6b6b"><i class="fas fa-key"></i> مفتاح OMDb مفقود — أضفه في إعدادات API</span></div></div>';return;}
    try{
        var res=await fetch('https://www.omdbapi.com/?apikey='+encodeURIComponent(key)+'&s='+encodeURIComponent(q)+'&page=1');
        var data=await res.json();
        if(data.Response==='False'||!data.Search||!data.Search.length){r.innerHTML='<div class="media-result-item"><div class="media-result-info" style="color:var(--t3)"><i class="fas fa-search"></i> لا نتائج — جرب بالإنجليزية</div></div>';return;}
        r.innerHTML=data.Search.slice(0,8).map(function(i){
            var t=i.Title||'',y=i.Year||'',p=(i.Poster&&i.Poster!=='N/A')?i.Poster:'',id=i.imdbID||'',
                tm={movie:'فيلم',series:'مسلسل',episode:'حلقة',game:'لعبة'},tl=tm[i.Type]||i.Type||'';
            return '<div class="media-result-item" onclick="omdbDetail_ms(\''+ctx+'\',\''+escA(id)+'\',\''+escA(t)+'\',\''+escA(p)+'\')">'+
                (p?'<img src="'+esc(p)+'" onerror="this.style.opacity=\'.2\'">':'<div style="width:36px;height:50px;background:var(--s3);border-radius:4px;display:flex;align-items:center;justify-content:center"><i class="fas fa-database" style="color:var(--t3)"></i></div>')+
                '<div class="media-result-info"><div class="media-result-title">'+esc(t)+'</div><div class="media-result-meta">'+(y||'\u2014')+' <span class="bdg" style="background:rgba(245,166,35,.1);color:var(--gold);border:1px solid rgba(245,166,35,.2);font-size:.6rem">'+tl+'</span> '+(id?'<span style="font-size:.62rem;color:var(--t3)">'+id+'</span> ':'')+'<span class="source-badge omdb">OMDb</span></div></div></div>';
        }).join('');
    }catch(e){r.innerHTML='<div class="media-result-item"><div class="media-result-info" style="color:#ff6b6b"><i class="fas fa-exclamation-triangle"></i> خطأ اتصال OMDb</div></div>';}
}
async function omdbDetail_ms(ctx,imdbId,ft,fp){
    var key=getOmdbKey();
    if(!key||!imdbId){mediaPick(ctx,ft,fp,'');return;}
    try{
        var res=await fetch('https://www.omdbapi.com/?apikey='+encodeURIComponent(key)+'&i='+encodeURIComponent(imdbId)+'&plot=short');
        var d=await res.json();
        if(d.Response==='True')mediaPick(ctx,d.Title||ft,(d.Poster&&d.Poster!=='N/A')?d.Poster:fp,(d.Plot&&d.Plot!=='N/A')?d.Plot:'');
        else mediaPick(ctx,ft,fp,'');
    }catch(e){mediaPick(ctx,ft,fp,'');}
}
function mediaPick(ctx,title,poster,desc){
    var r=$('mediaRes_'+ctx);if(r)r.style.display='none';
    if(ctx==='add'){
        $('srName').value=title;
        if(poster){$('srPoster').value=poster;srPosterPreview('srPosterThumb',poster);}
        if(desc&&$('srDesc'))$('srDesc').value=desc;
    }else{
        $('eSrName').value=title;
        if(poster){$('eSrPoster').value=poster;srPosterPreview('eSrPosterThumb',poster);}
        if(desc&&$('eSrDesc'))$('eSrDesc').value=desc;
    }
}
document.addEventListener('click',function(e){
    if(!e.target.closest('.media-search-wrap'))
        document.querySelectorAll('.media-search-results').forEach(function(r){r.style.display='none';});
});
// ═══ نهاية نظام البحث المتعدد ═══


// ══════════════════════════════════════════════════════════════
// نظام إدارة المستخدمين والصلاحيات v1.0
// ══════════════════════════════════════════════════════════════

const ALL_SECTION_DEFS = [
    {key:'dashboard',    name:'لوحة التحكم',      icon:'fas fa-home'},
    {key:'categories',   name:'الأقسام',           icon:'fas fa-th-large'},
    {key:'channels',     name:'القنوات',           icon:'fas fa-tv'},
    {key:'series',       name:'شاشتي',             icon:'fas fa-film'},
    {key:'vupload',      name:'رفع الأفلام',       icon:'fas fa-cloud-upload-alt'},
    {key:'vmanage',      name:'إدارة الفيديوهات',  icon:'fas fa-photo-video'},
    {key:'api-settings', name:'إعدادات API',       icon:'fas fa-plug'},
    {key:'site-settings',name:'إعدادات الموقع',    icon:'fas fa-cog'},
    {key:'change-password',name:'كلمة المرور',     icon:'fas fa-key'},
    {key:'system-tools', name:'صيانة النظام',      icon:'fas fa-tools'},
    {key:'backup',       name:'النسخ الاحتياطي',   icon:'fas fa-database'},
    {key:'idm-manager',  name:'التحميل الذكي (IDM)', icon:'fas fa-cloud-download-alt'}
];

const ROLE_LABELS = {administrator:'مدير عام',super:'مشرف',normal:'عادي',custom:'مخصص'};
const ROLE_CLASSES = {administrator:'admin',super:'super',normal:'normal',custom:'custom'};

let _usrAll = [];

// ── بناء شبكة الصلاحيات ──
function buildPermsGrid(containerId, selected) {
    var sel = selected || [];
    var html = '';
    ALL_SECTION_DEFS.forEach(function(s) {
        var on = sel.indexOf(s.key) !== -1;
        html += '<div class="perm-item'+(on?' on':'')+'" data-key="'+s.key+'" onclick="togglePerm(this)">';
        html += '<div class="pi-ic"><i class="'+s.icon+'"></i></div>';
        html += '<span class="pi-name">'+s.name+'</span>';
        html += '<div class="pi-chk"><i class="fas fa-check"></i></div>';
        html += '</div>';
    });
    $(containerId).innerHTML = html;
}

function togglePerm(el) {
    el.classList.toggle('on');
}

function getSelectedPerms(containerId) {
    var perms = [];
    $(containerId).querySelectorAll('.perm-item.on').forEach(function(el) {
        perms.push(el.getAttribute('data-key'));
    });
    return perms;
}

// ── إظهار/إخفاء شبكة الصلاحيات بناء على الدور ──
function auRoleChange() {
    var role = $('auRole').value;
    $('auPermsWrap').style.display = (role === 'custom') ? 'block' : 'none';
    if(role === 'custom') buildPermsGrid('auPermsGrid', ['vupload']);
}
function euRoleChange() {
    var role = $('euRole').value;
    $('euPermsWrap').style.display = (role === 'custom') ? 'block' : 'none';
}

// ── تحميل المستخدمين ──
function loadUsers() {
    $('usrGrid').innerHTML = '';
    $('usrEmpty').style.display = 'none';
    $('usrLoading').style.display = 'block';
    api({ajax_action:'get_admin_users'}).then(function(d) {
        $('usrLoading').style.display = 'none';
        if(!d.success) { al('usrGrid', d.error || 'خطأ', 'e'); return; }
        _usrAll = d.data || [];
        usrRender(_usrAll);
    });
}

function usrFilter() {
    var q = ($('usrSearch').value || '').toLowerCase();
    var role = $('usrRoleFilter').value;
    usrRender(_usrAll.filter(function(u) {
        var matchQ = !q || u.username.toLowerCase().indexOf(q) !== -1 || (u.display_name||'').toLowerCase().indexOf(q) !== -1;
        var matchR = role === 'all' || u.role === role;
        return matchQ && matchR;
    }));
}

function usrRender(users) {
    var g = $('usrGrid'), e = $('usrEmpty');
    $('usrCount').textContent = users.length + ' مستخدم';
    if(!users.length) { g.innerHTML = ''; e.style.display = 'block'; return; }
    e.style.display = 'none';
    g.innerHTML = users.map(function(u) {
        var rc = ROLE_CLASSES[u.role] || 'normal';
        var rl = ROLE_LABELS[u.role] || u.role;
        var initial = (u.display_name || u.username || '?').charAt(0).toUpperCase();
        var inactive = u.is_active == 0;
        var lastLogin = u.last_login ? u.last_login.substring(0,16) : 'لم يدخل بعد';
        var sections = [];
        try { sections = JSON.parse(u.allowed_sections || '[]'); } catch(e) {}
        var secText = '';
        if(u.role === 'custom' && sections.length > 0) {
            secText = '<span style="font-size:.68rem;color:var(--gold)"><i class="fas fa-lock-open"></i> ' + sections.length + ' قسم مسموح</span>';
        } else if(u.role === 'normal') {
            secText = '<span style="font-size:.68rem;color:#4CC9F0"><i class="fas fa-cloud-upload-alt"></i> رفع فقط</span>';
        } else if(u.role === 'administrator' || u.role === 'super') {
            secText = '<span style="font-size:.68rem;color:#00D084"><i class="fas fa-globe"></i> كل الأقسام</span>';
        }

        return '<div class="usr-card'+(inactive?' usr-inactive':'')+'">' +
            '<div class="usr-card-hd">' +
                '<div class="usr-avt '+rc+'">'+esc(initial)+'</div>' +
                '<div style="flex:1;min-width:0">' +
                    '<div class="usr-name">'+esc(u.display_name || u.username)+(inactive?' <span style="color:#ff6b6b;font-size:.72rem">⛔ معطّل</span>':'')+'</div>' +
                    '<div class="usr-uname">@'+esc(u.username)+'</div>' +
                '</div>' +
                '<span class="usr-role-bdg '+rc+'"><i class="fas fa-'+(rc==='admin'?'crown':rc==='super'?'shield-alt':rc==='custom'?'sliders-h':'user')+'"></i> '+rl+'</span>' +
            '</div>' +
            '<div class="usr-card-body"><div class="usr-meta">' +
                '<span><i class="fas fa-clock" style="color:var(--t3)"></i> آخر دخول: '+esc(lastLogin)+'</span>' +
                '<span><i class="fas fa-calendar" style="color:var(--t3)"></i> أُنشئ: '+esc((u.created_at||'').substring(0,10))+'</span>' +
                (secText ? '<span>'+secText+'</span>' : '') +
            '</div></div>' +
            '<div class="usr-card-ft">' +
                '<button class="ib ed" onclick=\'openEditUser('+JSON.stringify(u).replace(/'/g,"\\'")+')\'><i class="fas fa-pen"></i></button>' +
                (u.id != ADMIN_USER_ID ? '<button class="ib dl" onclick="deleteUser('+u.id+',\''+escA(u.display_name||u.username)+'\')"><i class="fas fa-trash"></i></button>' : '') +
            '</div>' +
        '</div>';
    }).join('');
}

// ── إضافة مستخدم ──
function addUser() {
    var username = $('auUsername').value.trim();
    var display = $('auDisplay').value.trim();
    var password = $('auPassword').value;
    var role = $('auRole').value;
    var sections = (role === 'custom') ? JSON.stringify(getSelectedPerms('auPermsGrid')) : '[]';

    if(!username) { al('auAlert','أدخل اسم المستخدم','e'); return; }
    if(!password || password.length < 4) { al('auAlert','كلمة المرور يجب أن تكون 4 أحرف على الأقل','e'); return; }

    al('auAlert','<span class="sp"></span> جارٍ الإنشاء...','i');
    api({ajax_action:'add_admin_user', username:username, password:password, display_name:display, role:role, allowed_sections:sections}).then(function(d) {
        if(d.success) {
            CM('addUserM');
            $('auUsername').value = '';
            $('auDisplay').value = '';
            $('auPassword').value = '';
            $('auRole').value = 'normal';
            $('auPermsWrap').style.display = 'none';
            al('auAlert','','');
            loadUsers();
        } else {
            al('auAlert', d.error || 'خطأ', 'e');
        }
    });
}

// ── فتح تعديل مستخدم ──
function openEditUser(u) {
    $('euId').value = u.id;
    $('euUsername').value = u.username;
    $('euDisplay').value = u.display_name || '';
    $('euPassword').value = '';
    $('euRole').value = u.role;
    $('euActive').value = u.is_active;

    var sections = [];
    try { sections = JSON.parse(u.allowed_sections || '[]'); } catch(e) {}

    if(u.role === 'custom') {
        $('euPermsWrap').style.display = 'block';
        buildPermsGrid('euPermsGrid', sections);
    } else {
        $('euPermsWrap').style.display = 'none';
    }

    // Super لا يستطيع اختيار administrator
    if(ADMIN_ROLE === 'super') {
        var opts = $('euRole').options;
        for(var i = 0; i < opts.length; i++) {
            if(opts[i].value === 'administrator') opts[i].disabled = true;
        }
    }

    al('euAlert','','');
    OM('editUserM');
}

// ── حفظ تعديل مستخدم ──
function editUser() {
    var id = $('euId').value;
    var display = $('euDisplay').value.trim();
    var role = $('euRole').value;
    var is_active = $('euActive').value;
    var new_pass = $('euPassword').value;
    var sections = (role === 'custom') ? JSON.stringify(getSelectedPerms('euPermsGrid')) : '[]';

    al('euAlert','<span class="sp"></span> جارٍ الحفظ...','i');
    api({ajax_action:'edit_admin_user', id:id, display_name:display, role:role, allowed_sections:sections, is_active:is_active, new_password:new_pass}).then(function(d) {
        if(d.success) {
            CM('editUserM');
            loadUsers();
        } else {
            al('euAlert', d.error || 'خطأ', 'e');
        }
    });
}

// ── حذف مستخدم ──
function deleteUser(id, name) {
    if(!confirm('حذف المستخدم "' + name + '" نهائياً؟')) return;
    api({ajax_action:'delete_admin_user', id:id}).then(function(d) {
        if(d.success) loadUsers();
        else alert(d.error || 'خطأ');
    });
}

// ══════════════════════════════════════════════════════════════
// فرض الصلاحيات على واجهة المستخدم
// ══════════════════════════════════════════════════════════════
(function enforcePermissions() {
    if(ADMIN_ROLE === 'administrator') return; // المدير العام يرى كل شيء

    var allowed = [];
    if(ADMIN_ROLE === 'super') {
        // المشرف يرى كل شيء + إدارة المستخدمين
        allowed = ALL_SECTION_DEFS.map(function(s){return s.key;});
        allowed.push('users');
    } else if(ADMIN_ROLE === 'normal') {
        allowed = ['vupload'];
    } else if(ADMIN_ROLE === 'custom') {
        allowed = ADMIN_SECTIONS || [];
    }

    // إخفاء أزرار القائمة الجانبية غير المسموحة
    document.querySelectorAll('.si[onclick]').forEach(function(btn) {
        var onclick = btn.getAttribute('onclick') || '';
        var match = onclick.match(/S\('([^']+)'\)/);
        if(match) {
            var sid = match[1];
            if(allowed.indexOf(sid) === -1) {
                btn.style.display = 'none';
            }
        }
    });

    // تعديل دالة S لمنع الوصول للأقسام غير المسموحة
    var _origS = window.S;
    window.S = function(id) {
        if(allowed.indexOf(id) === -1) {
            alert('ليس لديك صلاحية للوصول لهذا القسم');
            return;
        }
        _origS(id);
    };

    // عند تحميل الصفحة، اذهب لأول قسم مسموح
    if(allowed.length > 0 && allowed.indexOf('dashboard') === -1) {
        setTimeout(function() {
            // إزالة on من dashboard
            var ds = document.getElementById('dashboard');
            if(ds) ds.classList.remove('on');
            document.querySelectorAll('.si').forEach(function(b){b.classList.remove('on');});
            _origS(allowed[0]);
            if(allowed[0] === 'vupload') {} // لا حاجة لتحميل إضافي
            else if(allowed[0] === 'series') { if(typeof loadSeries === 'function') loadSeries(); }
            else if(allowed[0] === 'vmanage') { if(typeof vmLoad === 'function') vmLoad(); }
        }, 100);
    }
})();
// ═══ نهاية نظام المستخدمين ═══


// إغلاق قائمة اللغات عند النقر بالخارج
document.addEventListener('click', function() {
    var drop = document.getElementById('langDrop');
    if(drop && drop.classList.contains('op')) { drop.classList.remove('op'); }
});

// ══════════════════════════════════════════════════════════════
// 🎨 THEME SYSTEM — SHASHITY PRO
// ══════════════════════════════════════════════════════════════

const THEME_PRESETS = {
    default: { name: 'الافتراضي', css: '' },
    ultrachromic: {
        name: 'Ultrachromic',
        css: `:root{--red:#b847ff;--redg:rgba(184,71,255,.35);--gold:#e040fb;--s0:#0d0221;--s1:#100828;--s2:#180d35;--s3:#1f1140;--s4:#28174d;--br:rgba(184,71,255,.12);--brh:rgba(184,71,255,.25);--t1:#f0e8ff;--t2:#b89fd8;--t3:#6b5a8a}
.sidebar::after{background:linear-gradient(90deg,#b847ff,#6200ea)}
.si.on{background:rgba(184,71,255,.14)}.si.on::before{background:linear-gradient(180deg,#b847ff,#6200ea)}.si.on .si-ic{color:#b847ff}
.btn-p{background:linear-gradient(135deg,#b847ff,#6200ea);box-shadow:0 4px 14px rgba(184,71,255,.4)}.btn-p:hover{background:linear-gradient(135deg,#c860ff,#7200f4)}
.sbrand-icon{background:linear-gradient(135deg,#b847ff,#6200ea);box-shadow:0 0 20px rgba(184,71,255,.5)}.uavt{background:linear-gradient(135deg,#b847ff,#6200ea)}
.lic-dot{background:#b847ff;box-shadow:0 0 8px #b847ff}.topbar{background:rgba(13,2,33,.95)}
.fi:focus{border-color:#b847ff;box-shadow:0 0 0 3px rgba(184,71,255,.15)}.pw .pb{background:linear-gradient(90deg,#b847ff,#6200ea)}
@keyframes fu{from{opacity:0;transform:translateY(14px) scale(.98)}to{opacity:1;transform:translateY(0) scale(1)}}`
    },
    jellyflix: {
        name: 'JellyFlix',
        css: `:root{--red:#e50914;--redg:rgba(229,9,20,.4);--gold:#f5a623;--s0:#141414;--s1:#181818;--s2:#222;--s3:#2a2a2a;--s4:#333;--br:rgba(255,255,255,.08);--brh:rgba(255,255,255,.16);--t1:#fff;--t2:#b3b3b3;--t3:#6f6f6f}
.sidebar{border-left:none;border-right:3px solid var(--red)}.sbrand{background:linear-gradient(135deg,rgba(229,9,20,.15),transparent)}
.sbrand-icon{background:var(--red);box-shadow:0 0 25px rgba(229,9,20,.6);border-radius:4px}
.si.on{background:rgba(229,9,20,.16)}.si.on::before{background:var(--red);width:4px}
.topbar{background:rgba(20,20,20,.97);border-bottom:1px solid rgba(229,9,20,.2)}
.btn-p{background:var(--red);box-shadow:0 4px 18px rgba(229,9,20,.5);letter-spacing:.03em}
.mbox{border-radius:4px}.fi:focus{border-color:var(--red)}.lic-dot{background:#00b020;box-shadow:0 0 8px #00b020}
.uavt{border-radius:6px}@keyframes fu{from{opacity:0;transform:scale(.97)}to{opacity:1;transform:scale(1)}}`
    },
    dark: {
        name: 'Dark Enhanced',
        css: `:root{--red:#58a6ff;--redg:rgba(88,166,255,.3);--gold:#d29922;--s0:#0d1117;--s1:#161b22;--s2:#1c2128;--s3:#22272e;--s4:#2d333b;--br:rgba(255,255,255,.08);--brh:rgba(88,166,255,.25);--t1:#cdd9e5;--t2:#8b949e;--t3:#4d5562}
.sidebar::after{background:linear-gradient(90deg,#58a6ff,#1f6feb)}
.si.on{background:rgba(88,166,255,.1)}.si.on::before{background:linear-gradient(180deg,#58a6ff,#1f6feb)}.si.on .si-ic{color:#58a6ff}
.btn-p{background:linear-gradient(135deg,#1f6feb,#58a6ff);box-shadow:0 4px 14px rgba(88,166,255,.3)}.btn-p:hover{background:linear-gradient(135deg,#388bfd,#79c0ff)}
.sbrand-icon{background:linear-gradient(135deg,#1f6feb,#58a6ff)}.uavt{background:linear-gradient(135deg,#1f6feb,#58a6ff)}
.lic-dot{background:#3fb950;box-shadow:0 0 8px #3fb950}.fi:focus{border-color:#58a6ff;box-shadow:0 0 0 3px rgba(88,166,255,.12)}
.pw .pb{background:linear-gradient(90deg,#1f6feb,#58a6ff)}`
    },
    neon: {
        name: 'Neon Cyberpunk',
        css: `:root{--red:#00ff88;--redg:rgba(0,255,136,.3);--gold:#ff00ff;--s0:#03000a;--s1:#080016;--s2:#0e0020;--s3:#140030;--s4:#1c0040;--br:rgba(0,255,136,.12);--brh:rgba(0,255,136,.28);--t1:#e0ffe8;--t2:#80ff9a;--t3:#3d7a52}
.sidebar{border-left:1px solid rgba(0,255,136,.2);box-shadow:-4px 0 30px rgba(0,255,136,.08)}.sidebar::after{background:linear-gradient(90deg,#00ff88,#00ccff);height:2px}
.si.on{background:rgba(0,255,136,.1)}.si.on::before{background:linear-gradient(180deg,#00ff88,#00ccff)}.si.on .si-ic{color:#00ff88}
.si:hover{background:rgba(0,255,136,.07);color:#00ff88}
.btn-p{background:linear-gradient(135deg,#00cc70,#00ff88);color:#000;box-shadow:0 0 20px rgba(0,255,136,.4);font-weight:900}.btn-p:hover{box-shadow:0 0 32px rgba(0,255,136,.65)}
.sbrand-icon{background:linear-gradient(135deg,#00cc70,#00ff88);color:#000;box-shadow:0 0 25px rgba(0,255,136,.6)}.uavt{background:linear-gradient(135deg,#00cc70,#00ff88);color:#000}
.lic-dot{background:#ff00ff;box-shadow:0 0 10px #ff00ff}.topbar{background:rgba(3,0,10,.97);border-bottom:1px solid rgba(0,255,136,.15)}
.fi:focus{border-color:#00ff88;box-shadow:0 0 0 3px rgba(0,255,136,.15)}.pw .pb{background:linear-gradient(90deg,#00cc70,#00ff88)}`
    },
    minimal: {
        name: 'Minimal Clean',
        css: `:root{--red:#2563eb;--redg:rgba(37,99,235,.25);--gold:#d97706;--s0:#f8fafc;--s1:#fff;--s2:#f1f5f9;--s3:#e2e8f0;--s4:#cbd5e1;--br:rgba(0,0,0,.08);--brh:rgba(37,99,235,.25);--t1:#0f172a;--t2:#475569;--t3:#94a3b8;--sw:250px}
body{background:var(--s0);color:var(--t1)}.sidebar{background:#fff;border-left:1px solid var(--br);box-shadow:0 0 30px rgba(0,0,0,.06)}
.sidebar::after{background:linear-gradient(90deg,#2563eb,#7c3aed)}.sbrand{background:#fff;border-bottom:1px solid var(--br)}.sbrand-name{color:#0f172a}.sbrand-sub{color:#94a3b8}
.sbrand-icon{background:linear-gradient(135deg,#2563eb,#7c3aed);box-shadow:0 4px 12px rgba(37,99,235,.3)}
.si{color:#475569}.si:hover{background:#f1f5f9;color:#0f172a}.snl{color:#94a3b8}
.si.on{background:#eff6ff;color:#1d4ed8}.si.on::before{background:#2563eb}.si.on .si-ic{color:#2563eb}
.topbar{background:rgba(255,255,255,.97);border-bottom:1px solid var(--br)}.tbtitle{color:#0f172a}.main{background:var(--s0)}
.btn-p{background:linear-gradient(135deg,#2563eb,#7c3aed);box-shadow:0 4px 14px rgba(37,99,235,.3)}.uavt{background:linear-gradient(135deg,#2563eb,#7c3aed)}
.lic-dot{background:#10b981;box-shadow:0 0 8px #10b981}.lic-b{background:#fff;border-color:var(--br);color:#475569}
.card,.tw,.swc,.bkc,.sc{background:#fff;border-color:var(--br)}.sc:hover{border-color:rgba(37,99,235,.3)}
.fi{background:#f8fafc;border-color:var(--br);color:#0f172a}.fi:focus{border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.12)}
.fs{background:#f8fafc;border-color:var(--br);color:#0f172a}.mbox{background:#fff;border-color:var(--br)}.mhd,.mfooter{background:#f8fafc}
.pw .pb{background:linear-gradient(90deg,#2563eb,#7c3aed)}thead tr{background:#f8fafc}th{color:#94a3b8}td{color:#475569}tr:hover td{background:#f8fafc}
.stitle{color:#0f172a}.ib{background:#f1f5f9;border-color:var(--br);color:#475569}.ib:hover{background:#e2e8f0;color:#0f172a}
.al-s{background:#f0fdf4;border-color:#86efac;color:#166534}.al-e{background:#fef2f2;border-color:#fca5a5;color:#991b1b}`
    }
};

let _activeTheme = localStorage.getItem('shashety_theme') || 'default';
let _customCss   = localStorage.getItem('shashety_custom_css') || '';

/* ── Toast notification system ── */
function _adminToast(msg, type) {
    type = type || 's'; // s=success, e=error, i=info
    const icons = {s:'check-circle', e:'exclamation-circle', i:'info-circle'};
    const colors = {s:'#00D084', e:'#ff6b6b', i:'#4CC9F0'};
    let box = document.getElementById('adminToastBox');
    if (!box) {
        box = document.createElement('div');
        box.id = 'adminToastBox';
        box.style.cssText = 'position:fixed;top:24px;left:50%;transform:translateX(-50%);z-index:99999;display:flex;flex-direction:column;gap:10px;pointer-events:none;min-width:260px;max-width:90vw';
        document.body.appendChild(box);
    }
    const t = document.createElement('div');
    t.style.cssText = `background:var(--s1,#181818);color:#fff;border:1.5px solid ${colors[type]};border-radius:12px;padding:14px 20px;font-size:.88rem;font-weight:700;display:flex;align-items:center;gap:10px;box-shadow:0 8px 32px rgba(0,0,0,.5);animation:_toastIn .3s cubic-bezier(.23,1,.32,1);pointer-events:auto;direction:rtl`;
    t.innerHTML = `<i class="fas fa-${icons[type] || 'info-circle'}" style="color:${colors[type]};font-size:1.1rem"></i>${msg}`;
    // Add keyframe once
    if (!document.getElementById('_toastKf')) {
        const s = document.createElement('style');
        s.id = '_toastKf';
        s.textContent = '@keyframes _toastIn{from{opacity:0;transform:translateY(-14px) scale(.92)}to{opacity:1;transform:translateY(0) scale(1)}} @keyframes _toastOut{to{opacity:0;transform:translateY(-10px) scale(.92)}}';
        document.head.appendChild(s);
    }
    box.appendChild(t);
    setTimeout(() => {
        t.style.animation = '_toastOut .25s forwards';
        setTimeout(() => t.remove(), 260);
    }, 3000);
}

/* ── Save full CSS to DB ── */
function _saveThemeToDB(themeKey, fullCss, btnEl) {
    if (btnEl) { btnEl.disabled = true; btnEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري الحفظ...'; }
    api({ ajax_action: 'save_custom_css', custom_css: fullCss, theme_key: themeKey })
        .then(d => {
            if (d.success) {
                _adminToast('\u2705 تم حفظ الثيم بنجاح — سيظهر في index.php تلقائياً', 's');
            } else {
                _adminToast('\u274C خطأ في الحفظ: ' + (d.error || 'غير معروف'), 'e');
            }
        })
        .catch(() => _adminToast('\u274C فشل الاتصال بالخادم', 'e'))
        .finally(() => {
            if (btnEl) { btnEl.disabled = false; btnEl.innerHTML = '<i class="fas fa-check"></i> تطبيق الآن'; }
        });
}

function applyThemePreset(themeKey) {
    _activeTheme = themeKey;
    localStorage.setItem('shashety_theme', themeKey);
    const preset = THEME_PRESETS[themeKey] || THEME_PRESETS.default;
    const userCss = (document.getElementById('customCssInput') || {}).value || _customCss;
    const fullCss = preset.css + '\n' + userCss;
    const styleEl = document.getElementById('customCssThemeStyle');
    if (styleEl) styleEl.textContent = fullCss;
    // Mark active card
    document.querySelectorAll('.theme-card').forEach(c => c.classList.remove('active'));
    const ac = document.getElementById('thc-' + themeKey);
    if (ac) ac.classList.add('active');
    // Show inline status - saving...
    const st = document.getElementById('cssApplyStatus');
    if (st) { st.innerHTML = '<span style="color:#4CC9F0"><i class="fas fa-spinner fa-spin"></i> جاري الحفظ في قاعدة البيانات...</span>'; }
    // Toast
    _adminToast('\uD83C\uDFA8 تم تطبيق ثيم ' + (preset.name || themeKey) + ' محلياً — جاري الحفظ...', 'i');
    // ══ حفظ تلقائي في DB عند اختيار الثيم مباشرة ══
    api({ ajax_action: 'save_custom_css', custom_css: fullCss, theme_key: themeKey })
        .then(function(d) {
            if (d.success) {
                if (st) { st.innerHTML = '<span style="color:#00D084"><i class="fas fa-check-circle"></i> ✅ تم حفظ ثيم ' + (preset.name || themeKey) + ' في قاعدة البيانات — سيظهر في index.php تلقائياً</span>'; }
                _adminToast('✅ تم حفظ ثيم ' + (preset.name || themeKey) + ' وتطبيقه على index.php', 's');
            } else {
                if (st) { st.innerHTML = '<span style="color:#ff6b6b"><i class="fas fa-exclamation-circle"></i> خطأ في الحفظ: ' + (d.error || 'غير معروف') + '</span>'; }
                _adminToast('❌ خطأ في الحفظ: ' + (d.error || 'غير معروف'), 'e');
            }
        })
        .catch(function() {
            if (st) { st.innerHTML = '<span style="color:#ff6b6b"><i class="fas fa-exclamation-circle"></i> فشل الاتصال بالخادم</span>'; }
            _adminToast('❌ فشل الاتصال بالخادم', 'e');
        });
}

function applyCSSFromTextarea() {
    const ta = document.getElementById('customCssInput');
    const btn = document.getElementById('applyThemeBtn');
    if (!ta) return;
    _customCss = ta.value;
    localStorage.setItem('shashety_custom_css', _customCss);
    const preset = THEME_PRESETS[_activeTheme] || THEME_PRESETS.default;
    const fullCss = preset.css + '\n' + _customCss;
    const styleEl = document.getElementById('customCssThemeStyle');
    if (styleEl) styleEl.textContent = fullCss;
    // Save to DB
    _saveThemeToDB(_activeTheme, fullCss, btn);
}

function resetTheme() {
    _activeTheme = 'default'; _customCss = '';
    localStorage.removeItem('shashety_theme');
    localStorage.removeItem('shashety_custom_css');
    const styleEl = document.getElementById('customCssThemeStyle');
    if (styleEl) styleEl.textContent = '';
    const ta = document.getElementById('customCssInput'); if (ta) ta.value = '';
    document.querySelectorAll('.theme-card').forEach(c => c.classList.remove('active'));
    const dc = document.getElementById('thc-default'); if (dc) dc.classList.add('active');
    const st = document.getElementById('cssApplyStatus'); if (st) st.innerHTML = '';
    // Save empty to DB
    _saveThemeToDB('default', '', null);
}

function toggleThemePanel() {
    const panel = document.getElementById('themePanel');
    if (!panel) return;
    panel.classList.toggle('open');
    if (panel.classList.contains('open')) {
        setTimeout(() => document.addEventListener('click', _closePanelOutside), 150);
    } else {
        document.removeEventListener('click', _closePanelOutside);
    }
}
function _closePanelOutside(e) {
    const panel = document.getElementById('themePanel');
    const fab   = document.getElementById('themeFabBtn');
    if (!panel) return;
    if (!panel.contains(e.target) && (!fab || !fab.contains(e.target))) {
        panel.classList.remove('open');
        document.removeEventListener('click', _closePanelOutside);
    }
}

// Init on load
document.addEventListener('DOMContentLoaded', function() {
    const savedTheme = localStorage.getItem('shashety_theme') || 'default';
    const savedCss   = localStorage.getItem('shashety_custom_css') || '';
    const ta = document.getElementById('customCssInput');
    if (ta && savedCss) ta.value = savedCss;
    if (savedTheme && savedTheme !== 'default') {
        // Apply visually but don't re-save (already in DB)
        const preset = THEME_PRESETS[savedTheme] || THEME_PRESETS.default;
        const fullCss = preset.css + '\n' + savedCss;
        const styleEl = document.getElementById('customCssThemeStyle');
        if (styleEl) styleEl.textContent = fullCss;
        document.querySelectorAll('.theme-card').forEach(c => c.classList.remove('active'));
        const ac = document.getElementById('thc-' + savedTheme);
        if (ac) ac.classList.add('active');
        _activeTheme = savedTheme;
    } else if (savedCss) {
        const styleEl = document.getElementById('customCssThemeStyle');
        if (styleEl) styleEl.textContent = savedCss;
        const dc = document.getElementById('thc-default'); if(dc) dc.classList.add('active');
    } else {
        const dc = document.getElementById('thc-default'); if(dc) dc.classList.add('active');
    }
});

// === START IDM ENGINE ===
let idmData = {};
let idmIsDownloading = {}; 
let idmSpeeds = {};
let idmLastBytes = {};

function idmSyncData() {
    api({ajax_action:'idm_list'}).then(r => {
        if(r.success) {
            idmData = r.downloads || {};
            idmRender();
        }
    });
}

function formatBytes(bytes) {
    if(!bytes || bytes === 0) return '0 B';
    const k = 1024, sizes = ['B', 'KB', 'MB', 'GB', 'TB'], i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

function idmRender() {
    const wrap = $('idmListWrap');
    if(!wrap) return;
    const keys = Object.keys(idmData);
    if(keys.length === 0) {
        wrap.innerHTML = '<div class="empty"><i data-lucide="hard-drive-download"></i><p>لا توجد تحميلات نشطة حالياً</p></div>';
        if(typeof lucide !== 'undefined') lucide.createIcons();
        return;
    }
    
    let html = '';
    keys.reverse().forEach(k => {
        const d = idmData[k];
        const pct = d.size > 0 ? ((d.downloaded / d.size) * 100).toFixed(1) : 0;
        const isDling = idmIsDownloading[k] ? true : false;
        
        let statText = "مؤقت/في الانتظار"; let statColor = "var(--t3)"; let btnAction = `<button class="pbtn" onclick="idmStart('${k}')"><i class="fas fa-play" style="color:#00D084;"></i> استئناف المعالجة</button>`;
        if(isDling) { statText = "تنزيل ذكي متزامن..."; statColor = "#4CC9F0"; btnAction = `<button class="pbtn" onclick="idmPause('${k}')"><i class="fas fa-pause" style="color:var(--gold);"></i> إيقاف مؤقت</button>`; }
        if(d.downloaded >= d.size && d.size > 0) { statText = "مكتمل"; statColor = "#00D084"; btnAction = ''; }
        
        const speedText = idmSpeeds[k] && isDling ? formatBytes(idmSpeeds[k]) + '/s' : '';
        
        html += `
        <div class="swc" style="margin-bottom:0; background:var(--s2);">
            <div class="swc-hd" style="display:flex; justify-content:space-between; align-items:center;">
                <div class="swc-title" style="flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; margin-left:15px;"><i class="fas fa-cloud-download-alt" style="color:var(--gold);margin-left:8px"></i><span dir="ltr">${d.name}</span></div>
                <div style="font-size:0.75rem; color:${statColor}; font-weight:bold; flex-shrink:0;">${statText} <span id="idm_spd_${k}" style="margin-right:10px; color:#B36BFF" dir="ltr">${speedText}</span></div>
            </div>
            <div class="swc-body" style="padding:15px 22px;">
                <div style="display:flex; justify-content:space-between; font-size:0.75rem; color:var(--t2); margin-bottom:6px;">
                    <span id="idm_txt_${k}" dir="ltr">${formatBytes(d.downloaded)} / ${formatBytes(d.size)}</span>
                    <span id="idm_pct_${k}" style="font-weight:900; color:var(--t1)">${pct}%</span>
                </div>
                <div style="width:100%; height:10px; background:var(--s3); border-radius:5px; overflow:hidden; margin-bottom:15px;">
                    <div id="idm_bar_${k}" style="width:${pct}%; height:100%; background:linear-gradient(90deg, #E50914, #ff4c4c); transition:width 0.3s ease;"></div>
                </div>
                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                    ${btnAction}
                    <button class="pbtn" style="background:rgba(229,9,20,.1);color:var(--red);border-color:rgba(229,9,20,.2)" onclick="idmDelete('${k}')"><i class="fas fa-trash"></i> مسح الرابط والمعلومات</button>
                    <span style="font-size:0.65rem; color:var(--t3); margin-right:auto; margin-top:5px; direction:ltr;" title="${d.path}">...${d.path.slice(-40)}</span>
                </div>
            </div>
        </div>`;
    });
    wrap.innerHTML = html;
    if(typeof lucide !== 'undefined') lucide.createIcons();
}

function idmInit() {
    const url = $('idm_url').value;
    const name = $('idm_name').value;
    const type = $('idm_type').value;
    const alrt = $('idmAlert');
    
    if(!url || !name) { alrt.innerHTML = '<span style="color:red">الرجاء تحديد الرابط واسم الملف بوضوح!</span>'; return; }
    alrt.innerHTML = '<span style="color:#4CC9F0"><i class="fas fa-spinner fa-spin"></i> يتم التفاوض مع خادم المصدر لفك قيود السرعة وضبط الأحجام...</span>';
    
    api({ajax_action:'idm_init', url:url, name:name, type:type}).then(r => {
        if(r.success) {
            CM('idmNewM');
            alrt.innerHTML = '';
            $('idm_url').value = ''; $('idm_name').value = '';
            idmSyncData();
            setTimeout(() => { idmStart(r.id); }, 500);
        } else {
            alrt.innerHTML = '<span style="color:var(--red)"><i class="fas fa-exclamation-triangle"></i> '+(r.error||'فشل الطلب')+'</span>';
        }
    }).catch(e => alrt.innerHTML = '<span style="color:red"><i class="fas fa-plug"></i> فشل الاتصال بالخادم. تأكد من اتصالك بالنت.</span>');
}

function idmStart(id) {
    if(!idmData[id]) return;
    idmIsDownloading[id] = true;
    
    idmLastBytes[id] = idmData[id].downloaded;
    idmSpeeds[id] = 0;
    
    if(!idmData[id].cursor) idmData[id].cursor = idmData[id].downloaded;
    if(!idmData[id].activeThreads) idmData[id].activeThreads = 0;
    
    if(window[`idmInterval_${id}`]) clearInterval(window[`idmInterval_${id}`]);
    window[`idmInterval_${id}`] = setInterval(() => {
        if(!idmIsDownloading[id]) { clearInterval(window[`idmInterval_${id}`]); return; }
        let current = idmData[id].downloaded;
        let diff = current - idmLastBytes[id];
        idmSpeeds[id] = diff > 0 ? diff : 0;
        idmLastBytes[id] = current;
        
        let spdEl = $('idm_spd_'+id);
        if(spdEl) spdEl.innerText = formatBytes(idmSpeeds[id]) + '/s';
    }, 1000);
    
    idmRender();
    
    // Multi-Threading implementation (4 connections max per file)
    let maxConnections = 4;
    while(idmData[id].activeThreads < maxConnections && idmData[id].cursor < idmData[id].size) {
        _idmProcessChunk(id);
    }
}

function idmPause(id) {
    idmIsDownloading[id] = false;
    api({ajax_action:'idm_sync', id:id, down:idmData[id].downloaded, status:'paused'}).then(()=>idmSyncData());
    idmRender();
}

function idmDelete(id) {
    if(!confirm('هل أنت متأكد من مسح أوامر التنزيل وحذف الملف مؤقتا من الخادم؟')) return;
    idmIsDownloading[id] = false;
    api({ajax_action:'idm_delete', id:id}).then(r => {
        delete idmData[id];
        idmRender();
    });
}

function _idmProcessChunk(id) {
    if(!idmIsDownloading[id]) return;
    let d = idmData[id];
    let chunkSize = 3 * 1024 * 1024; // 3MB Per Thread
    
    if(d.cursor >= d.size) return;
    
    let start = d.cursor;
    let end = start + chunkSize - 1;
    if(end >= d.size) end = d.size - 1;
    
    // Advance cursor for next thread request
    d.cursor = end + 1;
    d.activeThreads = (d.activeThreads || 0) + 1;
    
    let fd = new FormData();
    fd.append('ajax_action', 'idm_chunk');
    fd.append('id', id);
    fd.append('start', start);
    fd.append('end', end);
    
    fetch('', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(r => {
        if(!idmIsDownloading[id]) { d.activeThreads--; return; }
        if(r.success) {
            d.downloaded += parseInt(r.written || 0);
            d.activeThreads--;
            
            let pct = ((d.downloaded / d.size) * 100).toFixed(1);
            let bar = $('idm_bar_'+id); if(bar) bar.style.width = pct+'%';
            let txt = $('idm_txt_'+id); if(txt) txt.innerText = formatBytes(d.downloaded) + ' / ' + formatBytes(d.size);
            let ptxt = $('idm_pct_'+id); if(ptxt) ptxt.innerText = pct+'%';
            
            if(d.downloaded >= d.size) {
                 idmIsDownloading[id] = false;
                 api({ajax_action:'idm_sync', id:id, down:d.size, status:'completed'}).then(()=>idmSyncData());
                 idmRender();
                 alert('✅ اكتمل النقل المتعدد الخيوط للملف: ' + d.name);
                 return;
            }
            
            // Spawn next chunk for this thread
            _idmProcessChunk(id);
        } else {
            d.activeThreads--;
            if (d.cursor > start) d.cursor = start; // Rollback
            idmIsDownloading[id] = false;
            idmRender();
            alert(`خطأ في اتصال المعالجة المتعددة للملف ${d.name} : ${r.error||'انقطع الاتصال'}`);
        }
    }).catch(e => {
        d.activeThreads--;
        if (d.cursor > start) d.cursor = start; // Rollback
        idmIsDownloading[id] = false;
        idmRender();
        alert("انقطع الاتصال اللحظي بجلسة التنزيل. تم الإيقاف المؤقت، يمكنك الاستئناف بأي وقت.");
    });
}
// === END IDM ENGINE ===


document.addEventListener('mouseover', function(e) {
    if(e.target.tagName === 'A' && e.target.href && e.target.href.startsWith(window.location.origin) && e.target.href.indexOf('#') === -1) {
        let l = document.createElement('link');
        l.rel = 'prefetch'; l.href = e.target.href;
        try { document.head.appendChild(l); } catch(err){}
    }
});


// === TAILSCALE DYNAMIC ACTION HANDLER (FIXED & THEMED) ===
let _isTailscaleRunning = false;

function fetchTailscaleStatus() {
    const statusTxt = document.getElementById('ts_display_status');
    const btnBox = document.getElementById('ts_display_btn');
    const btnLbl = document.getElementById('ts_btn_label');
    const btnIcon = btnBox.querySelector('i');
    const ipWrap = document.getElementById('ts_ip_wrap');
    const ipVal = document.getElementById('ts_ip_val');

    api({ ajax_action: 'tailscale_command', ts_action: 'status' }).then(res => {
        // حالة التأكد القاطع بأن النظام قيد العمل في الخلفية
        if(res.success && res.state === 'Running') {
            _isTailscaleRunning = true;
            
            statusTxt.textContent = 'متصل ومحمي ONLINE';
            statusTxt.style.cssText = 'font-size:0.75rem; font-weight:800; padding:3px 10px; border-radius:100px; background: rgba(0,208,132,.15); color: #00D084; border: 1px solid rgba(0,208,132,.3); float:left; transition: 0.3s;';
            
            btnBox.className = 'btn btn-g'; 
            btnBox.style.borderColor = 'rgba(229,9,20, 0.6)';
            btnBox.style.color = '#ff6b6b';
            btnBox.style.background = 'rgba(229,9,20,.1)';
            
            btnLbl.textContent = 'إيقاف الاتصال';
            btnIcon.className = 'fas fa-stop-circle';
            btnBox.style.pointerEvents = 'auto'; // إعادة تشغيل الزر
            
            if(res.ip) {
                ipWrap.style.display = 'block';
                // اضافة الـ IP وعدّاد الاجهزة المتصلة اللي جلبها البايثون الذكي!
                let peerStr = (res.peers_count > 0) ? `   [ 🌐 متصل معك: ${res.peers_count} أجهزة ]` : '   [ 🌐 لا توجد أجهزة متصلة ]';
                ipVal.innerHTML = res.ip + `<span style="color:var(--gold);font-size:0.75rem;">${peerStr}</span>`;
            }
        } else {
            _isTailscaleRunning = false;
            
            statusTxt.textContent = 'مُعطل OFFLINE';
            statusTxt.style.cssText = 'font-size:0.75rem; font-weight:800; padding:3px 10px; border-radius:100px; background: rgba(229,9,20,.15); color: var(--red); border: 1px solid rgba(229,9,20,.3); float:left; transition: 0.3s;';
            
            btnBox.className = 'btn btn-g'; 
            btnBox.style.borderColor = 'rgba(255,255,255,.14)';
            btnBox.style.color = 'var(--t2)';
            btnBox.style.background = 'var(--s3)';

            btnLbl.textContent = 'بدء الاتصال السري';
            btnIcon.className = 'fas fa-power-off';
            btnBox.style.pointerEvents = 'auto';
            ipWrap.style.display = 'none';
        }
    }).catch(err => {
         statusTxt.textContent = 'ERROR / تأكد من الصلاحيات';
         statusTxt.style.color = '#ff9900';
         btnBox.style.pointerEvents = 'auto';
    });
}

function executeTailscaleAction() {
    const targetAction = _isTailscaleRunning ? 'stop' : 'start';
    const btnBox = document.getElementById('ts_display_btn');
    const btnLbl = document.getElementById('ts_btn_label');
    const btnIcon = btnBox.querySelector('i');
    
    // ستايل "الانتظار/التحميل" الجذاب مع قفل الزر لتفادي دبل كليك
    btnBox.style.pointerEvents = 'none';
    btnLbl.textContent = 'جار المعالجة...';
    btnIcon.className = 'fas fa-spinner fa-spin';

    api({ ajax_action: 'tailscale_command', ts_action: targetAction }).then(res => {
        // ننتظر 1.5 ثانية لاعطاء نظام شبكات أوبونتو وقته للاستيعاب، ثم نفحص!
        setTimeout(() => { fetchTailscaleStatus(); }, 1500); 
    }).catch(()=>{
         setTimeout(() => { fetchTailscaleStatus(); }, 1500); 
    });
}

// === START ADMIN MUSIC PLAYER LOGIC (intero.mp3 fixed) ===
const INTERO_URL = '/iptv/intero.mp3';
let adminMusic = new Audio(INTERO_URL);
adminMusic.loop = true;
let isMusicPlaying = false;

function initAdminMusic() {
    let savedPlay = localStorage.getItem('shashety_music_play');
    if(savedPlay === '1') {
        let pp = adminMusic.play();
        if(pp !== undefined) {
            pp.then(() => {
                isMusicPlaying = true;
                updateMusicMini(true);
            }).catch(() => {
                isMusicPlaying = false;
                updateMusicMini(false);
            });
        }
    } else {
        updateMusicMini(false);
    }
}

function playAdminMusic() {
    adminMusic.play().then(() => {
        isMusicPlaying = true;
        localStorage.setItem('shashety_music_play', '1');
        updateMusicMini(true);
    }).catch(e => {
        isMusicPlaying = false;
        localStorage.setItem('shashety_music_play', '0');
        updateMusicMini(false);
    });
}

function pauseAdminMusic() {
    adminMusic.pause();
    isMusicPlaying = false;
    localStorage.setItem('shashety_music_play', '0');
    updateMusicMini(false);
}

function toggleAdminMusic() {
    if(isMusicPlaying) pauseAdminMusic();
    else playAdminMusic();
}

function updateMusicMini(playing) {
    const eq = $('m_eq');
    if(!eq) return;
    if(playing) eq.classList.remove('paused');
    else eq.classList.add('paused');
}

document.addEventListener("DOMContentLoaded", () => {
    initAdminMusic();
    fetchTailscaleStatus();
    if(typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
});

document.querySelectorAll(".si[onclick*='system-tools']").forEach(n => {
    n.addEventListener("click", () => setTimeout(fetchTailscaleStatus, 400));
});
// === END TAILSCALE HANDLER ===