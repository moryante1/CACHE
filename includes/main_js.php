<script>
const _allFoldersGlobal = <?php echo json_encode($all_folders_list ?? []); ?>;

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

/* ══ تبديل الوضع الليلي / النهاري (إضافة) ══ */
function applyDayNight(mode){
  const isLight = mode === 'light';
  document.documentElement.classList.toggle('light-mode', isLight);
  const ic = document.getElementById('modeIcon');
  if(ic){ ic.className = isLight ? 'fas fa-sun' : 'fas fa-moon'; }
}
function toggleDayNight(){
  const next = document.documentElement.classList.contains('light-mode') ? 'dark' : 'light';
  try{ localStorage.setItem('shashety_mode', next); }catch(e){}
  applyDayNight(next);
  if(window.toast) toast(next==='light' ? 'الوضع النهاري ☀️' : 'الوضع الليلي 🌙','i');
}
// تطبيق الوضع المحفوظ فوراً عند تحميل الصفحة
(function(){
  let saved='dark';
  try{ saved = localStorage.getItem('shashety_mode') || 'dark'; }catch(e){}
  applyDayNight(saved);
})();
function api(data){const fd=new FormData();for(const[k,v]of Object.entries(data))fd.append(k,String(v??''));if(window.csrfToken)fd.append('csrf_token',window.csrfToken);return fetch(location.href,{method:'POST',body:fd}).then(r=>r.json()).catch(()=>({success:false,error:'خطأ في الاتصال'}));}

/* ════════════════════════════════════════════════════════════════════
   ⚡ مُسرّع الطلبات + تحسين تجربة المستخدم  (إضافة — لا يُحذف شيء أصلي)
   - كاش ذكي للطلبات القرائية (load/list/get/fetch/status)
   - إلغاء تكرار الطلبات المتزامنة المتطابقة (deduplication)
   - إعادة محاولة تلقائية عند فشل الشبكة (محاولتان)
   - شريط تقدّم علوي خفيف + نظام Toast للإشعارات
   ──────────────────────────────────────────────────────────────────── */
(function(){
  const _origApi = api;                 // الاحتفاظ بالدالة الأصلية كما هي
  const _cache = new Map();             // كاش النتائج القرائية
  const _inflight = new Map();          // الطلبات الجارية حالياً (لمنع التكرار)
  const CACHE_TTL = 8000;               // مدة صلاحية الكاش بالملّي ثانية
  let _activeReqs = 0;

  // الإجراءات القرائية الآمنة للتخزين المؤقت (لا تغيّر بيانات)
  const READ_ONLY = /^(load|list|get|fetch|status|search|count|stats|info|check|tailscale_command)/i;
  // كلمات تدل على إجراء يكتب بيانات → نُبطل الكاش المرتبط بها بعده
  const WRITES = /(add|edit|update|delete|del_|remove|save|set_|create|upload|reorder|toggle|import|restore|merge)/i;

  function _key(d){try{return JSON.stringify(d);}catch(e){return String(Math.random());}}

  // شريط تقدّم علوي خفيف
  function _bar(show){
    let b=document.getElementById('_apiProgBar');
    if(!b){
      b=document.createElement('div');b.id='_apiProgBar';
      b.style.cssText='position:fixed;top:0;left:0;height:3px;width:0;z-index:99999;'+
        'background:linear-gradient(90deg,var(--gold,#e6b800),#00D084);'+
        'box-shadow:0 0 10px rgba(0,208,132,.6);transition:width .25s ease,opacity .4s ease;opacity:0;';
      document.body.appendChild(b);
    }
    if(show){_activeReqs++;b.style.opacity='1';b.style.width=Math.min(85,15+_activeReqs*20)+'%';}
    else{_activeReqs=Math.max(0,_activeReqs-1);
      if(_activeReqs===0){b.style.width='100%';setTimeout(()=>{b.style.opacity='0';b.style.width='0';},300);}}
  }

  // الدالة المحسّنة — تستدعي الأصلية داخلياً
  function fastApi(data, opts){
    opts=opts||{};
    const act=(data&&data.ajax_action)||'';
    const k=_key(data);
    const isRead = !opts.noCache && READ_ONLY.test(act);

    // 1) كاش
    if(isRead && _cache.has(k)){
      const c=_cache.get(k);
      if(Date.now()-c.t < CACHE_TTL) return Promise.resolve(c.v);
      _cache.delete(k);
    }
    // 2) منع تكرار الطلب المتزامن نفسه
    if(_inflight.has(k)) return _inflight.get(k);

    _bar(true);
    const run=(tries)=>_origApi(data).then(res=>{
      // إعادة محاولة عند فشل اتصال الشبكة فقط
      if(res&&res.success===false&&/اتصال|connection|network/i.test(res.error||'')&&tries>0){
        return new Promise(r=>setTimeout(r,400)).then(()=>run(tries-1));
      }
      return res;
    });

    const p=run(2).then(res=>{
      _bar(false);_inflight.delete(k);
      if(isRead && res && res.success!==false) _cache.set(k,{v:res,t:Date.now()});
      // أي عملية كتابة تُبطل الكاش القرائي كله لضمان طزاجة البيانات
      if(WRITES.test(act)) _cache.clear();
      return res;
    }).catch(e=>{_bar(false);_inflight.delete(k);return{success:false,error:'خطأ في الاتصال'};});

    _inflight.set(k,p);
    return p;
  }

  // استبدال المرجع العام دون المساس بالدالة الأصلية المعرّفة أعلاه
  window.api = fastApi;
  try{ api = fastApi; }catch(e){}

  // ── نظام Toast لإشعارات سريعة وأنيقة ──
  window.toast=function(msg,type){
    let host=document.getElementById('_toastHost');
    if(!host){
      host=document.createElement('div');host.id='_toastHost';
      host.style.cssText='position:fixed;bottom:20px;left:50%;transform:translateX(-50%);'+
        'z-index:99999;display:flex;flex-direction:column;gap:8px;align-items:center;pointer-events:none;';
      document.body.appendChild(host);
    }
    const colors={s:'#00D084',e:'#ff5252',i:'#3aa0ff',w:'#e6b800'};
    const icons={s:'check-circle',e:'exclamation-circle',i:'info-circle',w:'exclamation-triangle'};
    const t=document.createElement('div');
    t.style.cssText='background:rgba(20,22,28,.96);color:#fff;padding:11px 18px;border-radius:12px;'+
      'font-size:.86rem;font-weight:600;box-shadow:0 8px 28px rgba(0,0,0,.45);'+
      'border:1px solid '+(colors[type]||colors.i)+';display:flex;align-items:center;gap:9px;'+
      'opacity:0;transform:translateY(12px);transition:.3s;max-width:90vw;direction:rtl;';
    t.innerHTML='<i class="fas fa-'+(icons[type]||icons.i)+'" style="color:'+(colors[type]||colors.i)+'"></i>'+msg;
    host.appendChild(t);
    requestAnimationFrame(()=>{t.style.opacity='1';t.style.transform='translateY(0)';});
    setTimeout(()=>{t.style.opacity='0';t.style.transform='translateY(12px)';setTimeout(()=>t.remove(),320);},3200);
  };

  // أداة مساعدة: إبطال الكاش يدوياً عند الحاجة
  window.apiClearCache=function(){_cache.clear();};
})();

function esc(s){return String(s==null?'':s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;')}
function escA(s){return String(s==null?'':s).replace(/\\/g,'\\\\').replace(/'/g,"\\'").replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\n/g,'\\n').replace(/\r/g,'\\r')}
function fmtSz(b){if(b>=1073741824)return(b/1073741824).toFixed(1)+' GB';if(b>=1048576)return(b/1048576).toFixed(1)+' MB';if(b>=1024)return(b/1024).toFixed(0)+' KB';return b+' B'}
function al(id,msg,type){const icons={s:'check-circle',e:'exclamation-circle',i:'info-circle'};const cls={s:'al-s',e:'al-e',i:'al-i'};const el=$(id);if(!el)return;if(!msg){el.innerHTML='';return;}el.innerHTML=`<div class="al ${cls[type]||'al-i'}" style="margin:0"><i class="fas fa-${icons[type]||'info-circle'}"></i> ${msg}</div>`;}
const titles={dashboard:'<?= $t["dashboard"] ?? "لوحة التحكم" ?>',categories:'<?= $t["categories"] ?? "الأقسام" ?>',channels:'<?= $t["channels"] ?? "القنوات" ?>','m3u-import':'استيراد M3U',xtream:'حساب Xtream',series:'<?= $t["series"] ?? "شاشتي" ?>',vupload:'<?= $t["upload"] ?? "رفع الأفلام" ?>',vmanage:'<?= $t["manage"] ?? "إدارة الفيديوهات" ?>','api-settings':'<?= $t["api_settings"] ?? "إعدادات API" ?>','site-settings':'<?= $t["settings"] ?? "إعدادات الموقع" ?>','change-password':'<?= $t["password"] ?? "كلمة المرور" ?>','system-tools':'<?= $t["tools"] ?? "صيانة النظام" ?>',backup:'<?= $t["backup"] ?? "النسخ الاحتياطي" ?>',users:'<?= $t["users"] ?? "إدارة المستخدمين" ?>','frontend-control':'التحكم بالواجهة الأمامية','general-settings':'الإعدادات العامة'};
function S(id){document.querySelectorAll('.sec').forEach(s=>{s.classList.remove('on')});document.querySelectorAll('.si').forEach(s=>{s.classList.remove('on')});const sec=$(id);if(sec)sec.classList.add('on');$('tbTitle').textContent=titles[id]||'';document.querySelectorAll('.si').forEach(b=>{if(b.getAttribute('onclick')&&b.getAttribute('onclick').includes(`'${id}'`))b.classList.add('on')});
sessionStorage.setItem('active_sec', id);

// Load each admin section only when it is opened. Previously only Channels
// was initialized here, leaving the other pages on their default loading state.
const sectionLoaders = {
  channels: 'chLoad',
  'm3u-import': 'm3uLoadPlaylists',
  xtream: 'xtreamLoadAccounts',
  series: 'loadSeries',
  vmanage: 'vmLoad'
};
const loaderName = sectionLoaders[id];
if (loaderName && typeof window[loaderName] === 'function') {
  try { window[loaderName](); } catch (error) { console.error('Section loader failed:', id, error); }
}
}

/* ══ التحكم بالواجهة الأمامية (إضافة) ══ */
const _FC_INITIAL = {
  hide_search:        '<?= ($settings['hide_search']        ?? '0') ?>',
  hide_notifications: '<?= ($settings['hide_notifications'] ?? '0') ?>',
  hide_favorites:     '<?= ($settings['hide_favorites']     ?? '0') ?>',
  hide_music:         '<?= ($settings['hide_music']         ?? '0') ?>',
  hide_admin_btn:     '<?= ($settings['hide_admin_btn']     ?? '0') ?>',
  hide_social:        '<?= ($settings['hide_social']        ?? '0') ?>',
  hide_download:      '<?= ($settings['hide_download']      ?? '0') ?>',
  hide_cast:          '<?= ($settings['hide_cast']          ?? '0') ?>',
  hide_most_watched:  '<?= ($settings['hide_most_watched']  ?? '0') ?>',
  hide_suggestions:   '<?= ($settings['hide_suggestions']   ?? '0') ?>',
  hide_screensaver:   '<?= ($settings['hide_screensaver']   ?? '0') ?>'
};
function loadFrontendToggles(){
  document.querySelectorAll('#frontend-control input[data-key]').forEach(inp=>{
    const k = inp.getAttribute('data-key');
    inp.checked = (_FC_INITIAL[k] === '1');
  });
  al('fcAlert','',null);
}
function saveFrontendToggles(){
  const data = { ajax_action:'save_frontend_toggles' };
  document.querySelectorAll('#frontend-control input[data-key]').forEach(inp=>{
    data[inp.getAttribute('data-key')] = inp.checked ? '1' : '0';
  });
  al('fcAlert','<span class="sp"></span> جارٍ الحفظ…','i');
  api(data, {noCache:true}).then(d=>{
    if(d.success){
      // تحديث الحالة الأولية محلياً حتى يبقى "استرجاع المحفوظ" متسقاً
      Object.keys(_FC_INITIAL).forEach(k=>{ if(k in data) _FC_INITIAL[k]=data[k]; });
      al('fcAlert','✅ '+(d.message||'تم الحفظ بنجاح')+' — حدّث صفحة الموقع لرؤية التغييرات','s');
      if(window.toast) toast('تم حفظ إعدادات الواجهة','s');
    } else {
      al('fcAlert','❌ '+(d.error||'تعذّر الحفظ'),'e');
    }
  });
}
/* ═══════════ الإعدادات العامة الحساسة (general-settings) ═══════════ */
// جلب الإعدادات المحفوظة من قاعدة البيانات وتعبئة الحقول
// يجلب حقول الإعدادات العامة الأصلية فقط (يستثني حقول المجموعات داخل .gs-acc)
function gsGeneralInputs(){
  return Array.prototype.filter.call(
    document.querySelectorAll('#general-settings [data-key]'),
    function(inp){ return !inp.closest('.gs-acc'); }
  );
}
function loadGeneralSettings(){
  al('gsAlert','<span class="sp"></span> جارٍ تحميل الإعدادات…','i');
  api({ ajax_action:'get_general_settings' }).then(d=>{
    if(!d.success){ al('gsAlert','❌ '+(d.error||'تعذّر التحميل'),'e'); return; }
    const s = d.settings || {};
    gsGeneralInputs().forEach(inp=>{
      const k = inp.getAttribute('data-key');
      const type = inp.getAttribute('data-type');
      const val = (k in s) ? s[k] : '';
      if(type === 'bool'){
        inp.checked = (val === '1');
      } else {
        inp.value = val ?? '';
        // مزامنة منتقي اللون مع الحقل النصي
        if(k === 'theme_color' && val){ try{ document.getElementById('gs_theme_color_pick').value = val; }catch(e){} }
      }
    });
    al('gsAlert','',null);
  });
}

// استرجاع كل القيم الأصلية (يعيد التعيين للوضع الافتراضي)
function restoreDefaultSettings(){
  if(!confirm('هل أنت متأكد من استرجاع جميع قيم الإعدادات للوضع الافتراضي؟ هذا الإجراء سيحذف كل تعديلاتك على إعدادات الواجهة والمشغّل.')) return;
  api({ajax_action:'restore_default_settings'}).then(d=>{
    if(d.success){
      alert(d.message || 'تم الاسترجاع بنجاح');
      location.reload();
    } else {
      alert(d.message || 'حدث خطأ أثناء الاسترجاع');
    }
  });
}
// حفظ كل الإعدادات العامة دفعة واحدة (لا يشمل المجموعات؛ لكل مجموعة زرها)
function saveGeneralSettings(){
  const data = { ajax_action:'save_general_settings' };
  gsGeneralInputs().forEach(inp=>{
    const k = inp.getAttribute('data-key');
    const type = inp.getAttribute('data-type');
    data[k] = (type === 'bool') ? (inp.checked ? '1' : '0') : (inp.value ?? '');
  });
  al('gsAlert','<span class="sp"></span> جارٍ حفظ الإعدادات…','i');
  api(data).then(d=>{
    if(d.success){
      al('gsAlert','✅ '+(d.message||'تم الحفظ')+' — حدّث صفحة الموقع لرؤية التغييرات','s');
      if(window.toast) toast('تم حفظ الإعدادات العامة','s');
    } else {
      al('gsAlert','❌ '+(d.error||'تعذّر الحفظ'),'e');
    }
  });
}


/* ═══════════ مجموعات الإعدادات المتقدمة (كل مجموعة زر خاص) ═══════════ */
// القيم الافتراضية الأصلية لكل مفتاح (تُعرض كـ placeholder عند عدم وجود قيمة محفوظة)
const GS_DEFAULTS = {"srv_hls_segment_duration": "6", "srv_playlist_length": "5", "srv_llhls_enable": "0", "srv_ffmpeg_params": "", "srv_hwaccel": "none", "srv_thread_count": "0", "srv_tcp_udp_buffer": "8192", "srv_socket_buffer": "65536", "srv_cdn_failover": "0", "srv_stream_priority": "normal", "srv_health_check_interval": "30", "srv_auto_restart_stream": "1", "srv_stream_timeout": "20", "srv_packet_loss_recovery": "1", "srv_jitter_buffer": "500", "srv_abr_enable": "1", "srv_max_bitrate": "8000", "srv_min_bitrate": "800", "srv_gop_size": "48", "srv_keyframe_interval": "2", "ui_theme": "dark", "theme_color": "#e50914", "ui_font": "Tajawal", "ui_font_size": "16", "ui_transitions": "1", "ui_banner": "", "ui_icon_style": "solid", "img_default_channel": "", "img_default_movie": "", "img_default_series": "", "img_quality": "85", "img_compression": "1", "usr_save_last_watch": "1", "usr_autoplay": "1", "usr_dark_mode": "1", "usr_language": "ar", "usr_notifications": "1", "usr_favorites": "1", "usr_watch_history": "1", "perf_cache_duration": "3600", "perf_image_cache": "1", "perf_api_cache": "1", "perf_gzip_brotli": "1", "perf_lazy_loading": "1", "perf_http_version": "2", "perf_prefetch": "1", "perf_preconnect": "1", "sub_default_language": "ar", "sub_font_size": "18", "sub_font_color": "#ffffff", "sub_bg_color": "#000000", "sub_position": "bottom", "sub_bg_opacity": "60", "sr_resume_last_ep": "1", "sr_auto_next_ep": "1", "sr_skip_intro": "0", "sr_skip_outro": "0", "sr_season_order": "asc", "mv_per_page": "24", "mv_default_quality": "auto", "mv_auto_subtitle": "0", "mv_subtitle_language": "ar", "mv_play_trailer": "1", "mv_show_similar": "1", "mv_resume_watch": "1", "ch_per_page": "40", "ch_order": "display_order", "ch_group_order": "display_order", "ch_hide_offline": "0", "ch_auto_status": "0", "ch_check_interval": "60", "ch_resume_last": "1", "pl_autoplay": "1", "pl_mute_on_start": "0", "pl_auto_fullscreen": "0", "pl_pip": "1", "pl_webcast": "1", "pl_seek_buttons": "1", "pl_playback_speed": "1", "pl_thumbnails": "1", "pl_show_channel_logo": "1", "pl_show_channel_name": "1", "pl_show_clock": "0", "pl_show_viewers": "0", "pl_show_share": "1", "pl_show_report": "1", "st_low_latency": "0", "st_buffer_size": "30", "st_startup_buffer": "2", "st_max_buffer": "60", "st_back_buffer": "90", "st_live_sync": "3", "st_auto_quality": "1", "st_default_quality": "auto", "st_allow_quality_change": "1", "st_auto_reconnect": "1", "st_reconnect_attempts": "5", "st_reconnect_timeout": "3", "st_failover": "1", "st_protocol": "hls", "st_llhls_support": "0", "st_playlist_refresh": "6", "st_stream_cache": "1"};

// تحميل قيم مجموعة معيّنة من قاعدة البيانات وتعبئة حقولها
function loadGroupSettings(group){
  const scope = document.querySelector('.gs-acc[data-group="'+group+'"]') || document;
  const alId = 'ga_' + group;
  al(alId,'<span class="sp"></span> جارٍ التحميل…','i');
  api({ ajax_action:'get_general_settings' }).then(d=>{
    if(!d.success){ al(alId,'❌ '+(d.error||'تعذّر التحميل'),'e'); return; }
    const s = d.settings || {};
    scope.querySelectorAll('[data-key]').forEach(inp=>{
      const k = inp.getAttribute('data-key');
      const type = inp.getAttribute('data-type');
      const has = (k in s) && s[k] !== null && s[k] !== '';
      const val = has ? s[k] : (GS_DEFAULTS[k] ?? '');
      if(type === 'bool'){
        // للمفاتيح المنطقية: إن لم تُحفظ بعد نستخدم الافتراضي الأصلي
        inp.checked = has ? (s[k] === '1') : ((GS_DEFAULTS[k] ?? '0') === '1');
      } else if(inp.tagName === 'SELECT'){
        inp.value = val;
      } else {
        // للحقول النصية/الرقمية: نملأ القيمة المحفوظة فقط، ويبقى الافتراضي كـ placeholder
        inp.value = has ? s[k] : '';
        // مزامنة منتقي الألوان إن وُجد
        const pick = document.getElementById(inp.id + '_pick');
        if(pick && val){ try{ pick.value = val; }catch(e){} }
      }
    });
    al(alId,'',null);
  });
}

// حفظ مجموعة معيّنة (يرسل كل مفاتيح المجموعة)
function saveGroupSettings(group){
  const scope = document.querySelector('.gs-acc[data-group="'+group+'"]') || document;
  const alId = 'ga_' + group;
  const data = { ajax_action:'save_settings_group', group:group };
  scope.querySelectorAll('[data-key]').forEach(inp=>{
    const k = inp.getAttribute('data-key');
    const type = inp.getAttribute('data-type');
    if(type === 'bool'){
      data[k] = inp.checked ? '1' : '0';
    } else {
      // إن ترك المستخدم الحقل فارغاً، نحفظ القيمة الأصلية الافتراضية
      let v = (inp.value ?? '').trim();
      if(v === '' && (GS_DEFAULTS[k] !== undefined)) v = GS_DEFAULTS[k];
      data[k] = v;
    }
  });
  al(alId,'<span class="sp"></span> جارٍ الحفظ…','i');
  api(data).then(d=>{
    if(d.success){
      al(alId,'✅ '+(d.message||'تم الحفظ')+' — حدّث صفحة الموقع لرؤية التغييرات','s');
      if(window.toast) toast('تم حفظ إعدادات المجموعة','s');
    } else {
      al(alId,'❌ '+(d.error||'تعذّر الحفظ'),'e');
    }
  });
}

// طي/فتح بطاقة مجموعة داخل الإعدادات العامة
function gsToggleAcc(btn){
  var body = btn.nextElementSibling;
  var arrow = btn.querySelector('.gs-acc-arrow');
  var open = body.style.display !== 'none';
  if(open){
    body.style.display = 'none';
    if(arrow) arrow.style.transform = '';
  } else {
    body.style.display = 'block';
    if(arrow) arrow.style.transform = 'rotate(180deg)';
    // تحميل قيم المجموعة عند أول فتح
    var acc = btn.closest('.gs-acc');
    if(acc && !acc.dataset.loaded){
      acc.dataset.loaded = '1';
      var g = acc.getAttribute('data-group');
      if(g && window.loadGroupSettings) loadGroupSettings(g);
    }
  }
}

function toggleCategoryActive(checkbox){
  const cid = checkbox.getAttribute('data-cat-id');
  const newState = checkbox.checked ? '1' : '0';
  checkbox.disabled = true;
  api({ ajax_action:'toggle_category_active', category_id:cid, is_active:newState }).then(d=>{
    checkbox.disabled = false;
    if(d.success){
      if(window.toast) toast(d.message||'تم التحديث','s');
    } else {
      checkbox.checked = !checkbox.checked; // تراجع عند الفشل
      if(window.toast) toast(d.error||'تعذّر تحديث حالة القسم','e');
      else alert(d.error||'تعذّر تحديث حالة القسم');
    }
  }).catch(()=>{
    checkbox.disabled = false;
    checkbox.checked = !checkbox.checked;
    if(window.toast) toast('خطأ في الاتصال','e');
  });
}
function OM(id){const m=$(id);if(m){m.classList.add('op');document.body.style.overflow='hidden'}}
function CM(id){const m=$(id);if(m){m.classList.remove('op');document.body.style.overflow=''}}
document.addEventListener('keydown',e=>{if(e.key==='Escape'){document.querySelectorAll('.mbd.op').forEach(m=>{m.classList.remove('op')});document.body.style.overflow='';closePlayer()}});
document.querySelectorAll('.mbd').forEach(m=>m.addEventListener('click',e=>{if(e.target===m){m.classList.remove('op');document.body.style.overflow=''}}));
function FT(inp,tblId){const q=inp.value.toLowerCase();document.querySelectorAll('#'+tblId+' tbody tr').forEach(r=>{r.style.display=r.textContent.toLowerCase().includes(q)?'':'none';});}

let _allChannels = [];
let _filteredChannels = [];
let _chCurrentPage = 1;
const _chPerPage = 100;
let _chLoaded = false;

function chLoad(){
    if(_chLoaded) {
        $('chTbl').style.display='table';
        if(_filteredChannels.length === 0) {
            $('chTbl').style.display='none';
            $('chEmpty').style.display='block';
            $('chPagination').style.display='none';
        } else {
            $('chEmpty').style.display='none';
            $('chPagination').style.display='flex';
        }
        return;
    }
    $('chLoading').style.display='block';
    $('chTbl').style.display='none';
    $('chPagination').style.display='none';
    $('chEmpty').style.display='none';
    
    api({ajax_action:'get_channels'}).then(d=>{
        $('chLoading').style.display='none';
        if(d.success){
            _allChannels = d.channels || [];
            _filteredChannels = [..._allChannels];
            _chLoaded = true;
            $('chTotalCount').textContent = _allChannels.length + ' قناة';
            chRender(1);
        } else {
            al('alContainer', d.error || 'فشل جلب القنوات', 'e');
        }
    }).catch(e=>{
        $('chLoading').style.display='none';
        al('alContainer', 'خطأ في الاتصال', 'e');
    });
}

function chSearch(q){
    q = q.toLowerCase().trim();
    if(!q){
        _filteredChannels = [..._allChannels];
    } else {
        _filteredChannels = _allChannels.filter(c => {
            return (c.name && c.name.toLowerCase().includes(q)) || 
                   (c.cat_name && c.cat_name.toLowerCase().includes(q));
        });
    }
    chRender(1);
}

function chChangePage(dir){
    const maxPage = Math.ceil(_filteredChannels.length / _chPerPage) || 1;
    let newPage = _chCurrentPage + dir;
    if(newPage < 1) newPage = 1;
    if(newPage > maxPage) newPage = maxPage;
    chRender(newPage);
}

function escQ(str) { return (str||'').replace(/'/g, "\\'").replace(/"/g, '&quot;'); }

function chRender(page){
    _chCurrentPage = page;
    const maxPage = Math.ceil(_filteredChannels.length / _chPerPage) || 1;
    if(_chCurrentPage > maxPage) _chCurrentPage = maxPage;
    
    const tbody = $('chTblBody');
    tbody.innerHTML = '';
    
    if(_filteredChannels.length === 0){
        $('chTbl').style.display='none';
        $('chEmpty').style.display='block';
        $('chPagination').style.display='none';
        return;
    }
    
    $('chTbl').style.display='table';
    $('chEmpty').style.display='none';
    $('chPagination').style.display='flex';
    
    const start = (_chCurrentPage - 1) * _chPerPage;
    const end = Math.min(start + _chPerPage, _filteredChannels.length);
    
    let html = '';
    for(let i=start; i<end; i++){
        const ch = _filteredChannels[i];
        
        let logoHtml = ch.logo_url 
            ? `<img src="${encodeURI(ch.logo_url)}" style="width:34px;height:34px;object-fit:cover;border-radius:7px" onerror="this.style.display='none'">`
            : `<div class="nic"><i class="${ch.logo_icon || 'fas fa-tv'}"></i></div>`;
            
        let backupHtml = ch.backup_url ? `<span class="bdg bg"><i class="fas fa-link"></i> متوفر</span>` : `<span style="color:var(--t3);font-size:.75rem">—</span>`;
        let subHtml = ch.subtitle_url ? `<span class="bdg bg"><i class="fas fa-closed-captioning"></i> نعم</span>` : `<span style="color:var(--t3);font-size:.75rem">—</span>`;
        let activeChecked = parseInt(ch.is_active) === 1 ? 'checked' : '';
        let quality = ch.quality || 'HD 720';
        let views = ch.views_count || 0;
        
        let editData = JSON.stringify({
            id: ch.id,
            category_id: ch.category_id,
            name: ch.name,
            stream_url: ch.stream_url,
            logo_icon: ch.logo_icon,
            logo_url: ch.logo_url,
            backup_url: ch.backup_url || '',
            quality: quality,
            is_active: parseInt(ch.is_active || 1)
        }).replace(/"/g, '&quot;');
        
        html += `<tr>
            <td><input type="checkbox" class="chSelChk" value="${ch.id}" onchange="chSelCtrl()" style="width:16px;height:16px;cursor:pointer;accent-color:var(--red)"></td>
            <td style="color:var(--t3);font-size:.75rem">#${ch.id}</td>
            <td><div class="cn">${logoHtml}<strong style="color:var(--t1)">${ch.name}</strong></div></td>
            <td><span class="bdg bc">${ch.cat_name || ''}</span></td>
            <td><span class="bdg bp">${quality}</span></td>
            <td>${backupHtml}</td>
            <td>${subHtml}</td>
            <td><span style="font-size:.75rem;color:var(--t3)"><i class="fas fa-eye"></i> ${views}</span></td>
            <td><label class="fc-switch" style="display:inline-flex"><input type="checkbox" data-ch-id="${ch.id}" class="chActiveToggle" ${activeChecked} onchange="toggleChannelActive(this)"><span class="fc-slider"></span></label></td>
            <td>
                <div class="acts">
                    <button class="ib pl" onclick="testChannel('${escQ(ch.stream_url)}','${escQ(ch.name)}','${escQ(ch.subtitle_url)}','${escQ(ch.backup_url)}')"><i class="fas fa-play"></i></button>
                    <button class="ib ed" onclick="editCh(${editData})"><i class="fas fa-pen"></i></button>
                    <button class="ib dl" onclick="postDelete('delete_channel', ${ch.id})"><i class="fas fa-trash"></i></button>
                </div>
            </td>
        </tr>`;
    }
    tbody.innerHTML = html;
    
    $('chPageInfo').textContent = `صفحة ${_chCurrentPage} من ${maxPage}`;
    $('chPrevBtn').disabled = _chCurrentPage === 1;
    $('chNextBtn').disabled = _chCurrentPage === maxPage;
}
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
const SERVER_TMDB_KEY = "<?php echo addslashes($settings['tmdb_api_key'] ?? ''); ?>";
function getTmdbKey(){ return SERVER_TMDB_KEY; }
const SERVER_OMDB_KEY = "<?php echo addslashes($settings['omdb_api_key'] ?? ''); ?>";
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

/* ════ PLAYER STATE — FIXED v4 (MPEG-TS + HLS + MP4) ════ */
let _hls = null, _playerTS = null, _pUrl = '', _pSub = '';
let _watchdogTimer = null;
let _lastTime = -1;
let _frozenCount = 0;
let _pName = '';            // اسم القناة الحالية
let _pPrimary = '';         // الرابط الأساسي
let _pBackup = '';          // الرابط الاحتياطي
let _pUsingBackup = false;  // هل نشغّل حالياً الرابط الاحتياطي؟
let _pTriedBackup = false;  // هل جرّبنا الاحتياطي في هذه الجلسة؟
let _pConnectTimer = null;  // مؤقّت أولي: إن لم يبدأ التشغيل → تبديل للاحتياطي
let _tsRetries = 0;         // عدّاد إعادة محاولة TS داخلياً

/* اكتشاف صيغة الرابط بذكاء — يميّز روابط TS المباشرة عن HLS */
function detectFmt(url) {
    if (!url) return 'hls';
    const u = url.toLowerCase();
    const clean = u.split('?')[0].split('#')[0];
    // روابط TS الخام المباشرة أو روابط لوحات IPTV (live بدون امتداد m3u8)
    if (clean.endsWith('.ts') || clean.endsWith('.mts')) return 'ts';
    if (u.includes('type=m3u')) return 'hls';                 // قوائم Xtream غالباً m3u8
    if (clean.endsWith('.m3u8') || clean.endsWith('.m3u')) return 'hls';
    if (clean.includes('m3u8') || clean.includes('/hls/'))  return 'hls';
    // روابط /live/ بدون امتداد: جرّبها كـ TS مباشر (نمط Xtream للقنوات الحية)
    if (clean.includes('/live/'))                            return 'ts';
    if (clean.endsWith('.mp4') || clean.endsWith('.m4v'))   return 'mp4';
    if (clean.endsWith('.mkv') || clean.endsWith('.webm'))  return 'mp4';
    return 'hls';
}

/* تدمير كامل للبلاير السابق (HLS + TS + عنصر الفيديو) */
function _destroyAll() {
    if (_watchdogTimer) { clearInterval(_watchdogTimer); _watchdogTimer = null; }
    if (_pConnectTimer) { clearTimeout(_pConnectTimer); _pConnectTimer = null; }
    _lastTime = -1; _frozenCount = 0;

    if (_hls) { try { _hls.destroy(); } catch(e) {} _hls = null; }
    if (_playerTS) {
        // ترتيب مهم لمنع بقاء buffers معلّقة عند تبديل القنوات
        try { _playerTS.pause(); } catch(e) {}
        try { _playerTS.unload(); } catch(e) {}
        try { _playerTS.detachMediaElement(); } catch(e) {}
        try { _playerTS.destroy(); } catch(e) {}
        _playerTS = null;
    }

    const vid = $('tv');
    if (!vid) return;
    vid.oncanplay = null; vid.onwaiting = null; vid.onplaying = null;
    vid.onstalled = null; vid.onerror = null;
    try {
        vid.pause();
        while (vid.firstChild) vid.removeChild(vid.firstChild);
        vid.removeAttribute('src');
        vid.load();
    } catch(e) {}
}

/* التبديل الذكي للرابط الاحتياطي عند فشل/تجمّد المصدر الحالي */
function _smartFallback(reason) {
    if (_pBackup && _pBackup.trim() && !_pTriedBackup && !_pUsingBackup) {
        _pTriedBackup = true;
        _pUsingBackup = true;
        if (window.toast) toast('الرابط الأساسي لا يعمل — التبديل للرابط الاحتياطي تلقائياً…', 'i');
        $('pload').classList.remove('hid');
        $('perr').classList.remove('sh');
        $('pdot').className = 'pdot';
        _playSource(_pBackup);
        return true;
    }
    return false;
}

/* watchdog: يراقب التجمّد ويُعيد التشغيل/يبدّل للاحتياطي */
function _startWatchdog() {
    if (_watchdogTimer) clearInterval(_watchdogTimer);
    _lastTime = -1; _frozenCount = 0;
    _watchdogTimer = setInterval(function() {
        const vid = $('tv');
        if (!vid || vid.paused || vid.ended) return;
        const ct = vid.currentTime;
        if (ct === _lastTime) {
            _frozenCount++;
            if (_frozenCount >= 5) { // ~10 ثوانٍ تجمّد
                _frozenCount = 0;
                console.warn('Watchdog: stream frozen.');
                if (!_smartFallback('frozen')) {
                    _playSource(_pUsingBackup ? _pBackup : _pPrimary);
                }
            }
        } else {
            _frozenCount = 0;
            _lastTime = ct;
        }
    }, 2000);
}

function testChannel(url, name, subUrl, backupUrl) {
    _pName        = name || url || '';
    _pSub         = subUrl || '';
    _pPrimary     = url || '';
    _pBackup      = backupUrl || '';
    _pUsingBackup = false;
    _pTriedBackup = false;
    _tsRetries    = 0;
    _pUrl         = _pPrimary;

    $('ptitle').textContent = _pName;
    $('purl').textContent = _pPrimary;
    $('pm').classList.add('op');
    document.body.style.overflow = 'hidden';
    $('pload').classList.remove('hid');
    $('perr').classList.remove('sh');
    $('pdot').className = 'pdot';
    $('pfmt').style.display = 'none';

    _destroyAll();

    const vid = $('tv');
    vid.setAttribute('playsinline', '');
    vid.setAttribute('webkit-playsinline', '');
    vid.removeAttribute('crossorigin');

    // الترجمة
    if (_pSub && _pSub.trim()) {
        $('psubbar').style.display = 'flex';
        $('psubLabel').textContent = 'ترجمة: ' + _pSub.split('/').pop();
        const tr = document.createElement('track');
        tr.kind = 'subtitles'; tr.srclang = 'ar'; tr.label = 'عربي';
        tr.src = _pSub; tr.default = true;
        vid.appendChild(tr);
        setTimeout(function() { if (vid.textTracks[0]) vid.textTracks[0].mode = 'showing'; }, 800);
        if ($('psubToggleIc')) $('psubToggleIc').className = 'fas fa-toggle-on';
        if ($('psubToggleTxt')) $('psubToggleTxt').textContent = 'إخفاء';
    } else {
        $('psubbar').style.display = 'none';
    }

    _playSource(_pPrimary);
}

/* المحرك التنفيذي — يوجّه لكل صيغة لمحركها الصحيح */
function _playSource(url) {
    _pUrl = url || '';
    _destroyAll();

    const vid = $('tv');
    if (!vid) return;

    if ($('purl')) $('purl').textContent = url + (_pUsingBackup ? '  (رابط احتياطي)' : '');

    // مؤقّت اتصال: إن لم يبدأ التشغيل خلال 15 ثانية → بدّل للاحتياطي
    _pConnectTimer = setTimeout(function() {
        const v = $('tv');
        if (v && v.currentTime > 0 && !v.paused) return;
        if (!_smartFallback('timeout')) pShowErr('انتهت مهلة الاتصال — رابط القناة لا يستجيب');
    }, 15000);

    const fmt = detectFmt(url);
    const pfmt = $('pfmt');
    if (pfmt) pfmt.style.display = 'inline';

    // ══════════════ MPEG-TS (بث مباشر خام) ══════════════
    if (fmt === 'ts') {
        if (pfmt) {
            pfmt.textContent = 'MPEG-TS';
            pfmt.style.cssText = 'display:inline;font-size:.65rem;font-weight:800;padding:2px 8px;border-radius:4px;background:rgba(0,183,255,0.15);border:1px solid rgba(0,183,255,0.3);color:#00b7ff';
        }
        _playTS(url, vid);
    }
    // ══════════════ HLS (m3u8) ══════════════
    else if (fmt === 'hls') {
        if (pfmt) {
            pfmt.textContent = 'HLS';
            pfmt.style.cssText = 'display:inline;font-size:.65rem;font-weight:800;padding:2px 8px;border-radius:4px;background:rgba(229,9,20,.15);border:1px solid rgba(229,9,20,.3);color:var(--red)';
        }
        /* المكتبة تُحمَّل الآن بـ defer (لئلا تحجب اللوحة إن كان الـ CDN
           بطيئاً)، لذا قد لا تكون جاهزة إن ضغط المستخدم على المعاينة
           فوراً بعد فتح الصفحة. ننتظر جاهزيتها بدل إظهار رسالة
           "المتصفح لا يدعم بث HLS" الخاطئة.
           نفس النمط المستخدم في واجهة الموقع (site/includes/main_js.php). */
        if (typeof Hls === 'undefined'
            && !vid.canPlayType('application/vnd.apple.mpegurl')
            && window.__hlsReady && typeof window.__hlsReady.then === 'function') {
            var _pendingUrl = url;
            window.__hlsReady.then(function () {
                // نعيد المحاولة فقط إن كان المستخدم ما زال على نفس الرابط
                if (_pUrl === _pendingUrl) _playSource(_pendingUrl);
            });
            return;
        }

        if (typeof Hls !== 'undefined' && Hls.isSupported()) {
            _hls = new Hls({
                enableWorker: true,
                lowLatencyMode: false,
                backBufferLength: 90,
                maxBufferLength: 60,
                liveSyncDurationCount: 5,
                fragLoadingMaxRetry: 15,
                manifestLoadingMaxRetry: 10,
                levelLoadingMaxRetry: 10,
                fragLoadingRetryDelay: 500,
                manifestLoadingRetryDelay: 500
            });
            _hls.loadSource(url);
            _hls.attachMedia(vid);
            _hls.on(Hls.Events.MANIFEST_PARSED, function() {
                vid.play().catch(function() {});
                _startWatchdog();
            });
            _hls.on(Hls.Events.ERROR, function(event, data) {
                if (!data.fatal) return;
                switch (data.type) {
                    case Hls.ErrorTypes.NETWORK_ERROR:
                        if (!_smartFallback('hls-network')) {
                            setTimeout(function(){ if (_hls) { try { _hls.startLoad(); } catch(e){} } }, 1500);
                        }
                        break;
                    case Hls.ErrorTypes.MEDIA_ERROR:
                        try { _hls.recoverMediaError(); } catch(e) {}
                        break;
                    default:
                        if (!_smartFallback('hls-fatal')) pShowErr('خطأ في تشغيل قناة HLS');
                }
            });
        } else if (vid.canPlayType('application/vnd.apple.mpegurl')) {
            // Safari / iOS — HLS أصلي
            vid.src = url; vid.play().catch(function(){}); _startWatchdog();
        } else {
            if (!_smartFallback('hls-nosupport')) pShowErr('المتصفح لا يدعم بث HLS');
        }
    }
    // ══════════════ MP4 / Direct ══════════════
    else {
        if (pfmt) {
            pfmt.textContent = fmt.toUpperCase();
            pfmt.style.cssText = 'display:inline;font-size:.65rem;font-weight:800;padding:2px 8px;border-radius:4px;background:rgba(0,208,132,.15);border:1px solid rgba(0,208,132,.3);color:#00D084';
        }
        vid.preload = 'auto';
        vid.src = url;
        vid.play().catch(function() {});
        _startWatchdog();
    }

    // ═══ أحداث الفيديو المشتركة ═══
    vid.oncanplay = function() {
        if (_pConnectTimer) { clearTimeout(_pConnectTimer); _pConnectTimer = null; }
        $('pload').classList.add('hid');
        $('pdot').className = 'pdot ok';
    };
    vid.onwaiting = function() { $('pload').classList.remove('hid'); };
    vid.onplaying = function() {
        if (_pConnectTimer) { clearTimeout(_pConnectTimer); _pConnectTimer = null; }
        $('pload').classList.add('hid');
        $('pdot').className = 'pdot ok';
        _tsRetries = 0;
    };
    vid.onstalled = function() {
        setTimeout(function() { if (vid.paused && _pUrl) vid.play().catch(function(){}); }, 3000);
    };
    vid.onerror = function() {
        if (!_smartFallback('video-error')) pShowErr('تعذّر تشغيل هذا الرابط');
    };
}

/* تحميل مكتبة mpegts.js تلقائياً من الـ CDN عند أول حاجة (بدون تعديل HTML) */
let _mpegtsLoading = null;
function _ensureMpegts() {
    if (typeof mpegts !== 'undefined') return Promise.resolve(true);
    if (_mpegtsLoading) return _mpegtsLoading;
    _mpegtsLoading = new Promise(function(resolve) {
        // مصادر بديلة: إن فشل الأول جرّب الثاني تلقائياً
        var srcs = [
            'https://cdn.jsdelivr.net/npm/mpegts.js@latest/dist/mpegts.js',
            'https://unpkg.com/mpegts.js@latest/dist/mpegts.js'
        ];
        var i = 0;
        function tryNext() {
            if (i >= srcs.length) { resolve(false); return; }
            var s = document.createElement('script');
            s.src = srcs[i++];
            s.async = true;
            s.onload = function() { resolve(typeof mpegts !== 'undefined'); };
            s.onerror = function() { tryNext(); };
            document.head.appendChild(s);
        }
        tryNext();
    });
    return _mpegtsLoading;
}

/* تشغيل MPEG-TS عبر mpegts.js مع معالجة أخطاء وإعادة محاولة */
function _playTS(url, vid) {
    if (typeof mpegts === 'undefined') {
        // حاول تحميل المكتبة تلقائياً ثم أعد التشغيل
        if ($('pload')) $('pload').classList.remove('hid');
        _ensureMpegts().then(function(ok) {
            if (ok) { _playTS(url, vid); }
            else if (!_smartFallback('ts-nolib')) pShowErr('تعذّر تحميل مكتبة MPEG-TS — تحقق من الإنترنت');
        });
        return;
    }
    if (!mpegts.getFeatureList().mseLivePlayback) {
        if (!_smartFallback('ts-nomse')) pShowErr('المتصفح لا يدعم بث MPEG-TS');
        return;
    }
    try {
        _playerTS = mpegts.createPlayer(
            { type: 'mse', isLive: true, url: url, cors: true },
            {
                enableWorker: true,
                liveBufferLatencyChasing: true,      // تقليل التأخير في البث الحيّ
                liveBufferLatencyMaxLatency: 5.0,
                liveBufferLatencyMinRemain: 1.0,
                lazyLoad: false,
                autoCleanupSourceBuffer: true        // منع امتلاء الذاكرة في البث الطويل
            }
        );
        _playerTS.attachMediaElement(vid);

        _playerTS.on(mpegts.Events.ERROR, function(type, detail) {
            console.warn('MPEG-TS Error:', type, detail);
            // إعادة محاولة داخلية مرتين قبل الانتقال للاحتياطي
            if (_tsRetries < 2) {
                _tsRetries++;
                try { _playerTS.unload(); _playerTS.load(); _playerTS.play().catch(function(){}); } catch(e) {}
                return;
            }
            if (!_smartFallback('ts-err')) pShowErr('خطأ في تشغيل قناة TS');
        });

        _playerTS.load();
        _playerTS.play().catch(function(){});
        _startWatchdog();
    } catch(e) {
        if (!_smartFallback('ts-exception')) pShowErr('تعذّر تهيئة مشغّل TS');
    }
}

function pShowErr(msg) {
    $('pload').classList.add('hid');
    $('perr').classList.add('sh');
    $('pdot').className = 'pdot err';
    var em = document.getElementById('perrMsg');
    if (em) em.textContent = msg || 'تعذر تشغيل الفيديو';
}
function pRetry() {
    testChannel(_pPrimary || _pUrl, _pName, _pSub, _pBackup);
}
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
    if ($('psubToggleIc')) $('psubToggleIc').className = on ? 'fas fa-toggle-off' : 'fas fa-toggle-on';
    if ($('psubToggleTxt')) $('psubToggleTxt').textContent = on ? 'إظهار' : 'إخفاء';
}
function closePlayer() {
    $('pm').classList.remove('op');
    document.body.style.overflow = '';
    _destroyAll();
}

function editCat(d){$('eCatId').value=d.id;$('eCatName').value=d.name;$('eCatIcon').value=d.icon||'fas fa-th-large';const sel=$('eCatParent');for(let o of sel.options)o.selected=(o.value===(d.parent_id||'').toString());OM('editCatM');}
function editCh(d){$('eChId').value=d.id;$('eChName').value=d.name;$('eChUrl').value=d.stream_url;$('eChBackup').value=d.backup_url||'';$('eChQuality').value=d.quality||'HD 720';$('eChActive').checked=(parseInt(d.is_active)!==0);$('eChIcon').value=d.logo_icon||'fas fa-tv';$('eChLogo').value=d.logo_url||'';const sel=$('eChCat');for(let o of sel.options)o.selected=(o.value===d.category_id.toString());if(d.logo_url)previewImage('editPrev',d.logo_url);else $('editPrev').style.display='none';OM('editChM');}
function toggleChannelActive(checkbox){
  const chid = checkbox.getAttribute('data-ch-id');
  const newState = checkbox.checked ? '1' : '0';
  checkbox.disabled = true;
  api({ ajax_action:'toggle_channel_active', channel_id:chid, is_active:newState }).then(d=>{
    checkbox.disabled = false;
    if(d.success){
      if(window.toast) toast(d.message||'تم التحديث','s');
    } else {
      checkbox.checked = !checkbox.checked; // تراجع عند الفشل
      if(window.toast) toast(d.error||'تعذّر تحديث حالة القناة','e');
      else alert(d.error||'تعذّر تحديث حالة القناة');
    }
  }).catch(()=>{
    checkbox.disabled = false;
    checkbox.checked = !checkbox.checked;
    if(window.toast) toast('خطأ في الاتصال','e');
  });
}

/* ════ تحديد وحذف جماعي — الأقسام ════ */
function catSelCtrl(){
  const chks = document.querySelectorAll('.catSelChk');
  const sel  = document.querySelectorAll('.catSelChk:checked');
  const bar  = $('catBulkBar');
  if(sel.length){ bar.style.display='flex'; $('catSelCount').textContent = sel.length; }
  else { bar.style.display='none'; }
  const all = $('catSelAll');
  if(all){ all.checked = (chks.length>0 && sel.length===chks.length); all.indeterminate = (sel.length>0 && sel.length<chks.length); }
}
function catToggleAll(master){
  document.querySelectorAll('#catTbl tbody tr').forEach(tr=>{
    if(tr.style.display==='none') return; // تجاهل المخفي بالبحث
    const c = tr.querySelector('.catSelChk'); if(c) c.checked = master.checked;
  });
  catSelCtrl();
}
function catClearSel(){
  document.querySelectorAll('.catSelChk').forEach(c=>c.checked=false);
  const all=$('catSelAll'); if(all){ all.checked=false; all.indeterminate=false; }
  catSelCtrl();
}
function catBulkDelete(){
  const ids = [...document.querySelectorAll('.catSelChk:checked')].map(c=>c.value);
  if(!ids.length) return;
  if(!confirm('سيتم حذف '+ids.length+' قسم بالكامل مع كل القنوات المرتبطة بها. متابعة؟')) return;
  api({ajax_action:'bulk_delete_categories', ids:ids.join(',')}).then(d=>{
    if(d.success){
      if(window.toast) toast('تم حذف '+d.deleted+' قسم','s');
      setTimeout(()=>location.reload(), 900);
    } else { if(window.toast) toast(d.error||'فشل الحذف','e'); else alert(d.error||'فشل الحذف'); }
  });
}

/* ════ تحديد وحذف جماعي — القنوات ════ */
function chSelCtrl(){
  const chks = document.querySelectorAll('.chSelChk');
  const sel  = document.querySelectorAll('.chSelChk:checked');
  const bar  = $('chBulkBar');
  if(sel.length){ bar.style.display='flex'; $('chSelCount').textContent = sel.length; }
  else { bar.style.display='none'; }
  const all = $('chSelAll');
  if(all){ all.checked = (chks.length>0 && sel.length===chks.length); all.indeterminate = (sel.length>0 && sel.length<chks.length); }
}
function chToggleAll(master){
  document.querySelectorAll('#chTbl tbody tr').forEach(tr=>{
    if(tr.style.display==='none') return;
    const c = tr.querySelector('.chSelChk'); if(c) c.checked = master.checked;
  });
  chSelCtrl();
}
function chClearSel(){
  document.querySelectorAll('.chSelChk').forEach(c=>c.checked=false);
  const all=$('chSelAll'); if(all){ all.checked=false; all.indeterminate=false; }
  chSelCtrl();
}
function chBulkDelete(){
  const ids = [...document.querySelectorAll('.chSelChk:checked')].map(c=>c.value);
  if(!ids.length) return;
  if(!confirm('سيتم حذف '+ids.length+' قناة. متابعة؟')) return;
  api({ajax_action:'bulk_delete_channels', ids:ids.join(',')}).then(d=>{
    if(d.success){
      if(window.toast) toast('تم حذف '+d.deleted+' قناة','s');
      setTimeout(()=>location.reload(), 900);
    } else { if(window.toast) toast(d.error||'فشل الحذف','e'); else alert(d.error||'فشل الحذف'); }
  });
}

/* ════ استيراد قوائم M3U ════ */
function m3uFileSelected(inp){
  const f = inp.files[0];
  if(!f) return;
  const ext = (f.name.split('.').pop()||'').toLowerCase();
  if(ext!=='m3u' && ext!=='m3u8'){
    $('m3uFileStatus').innerHTML='<span style="color:#ff6b6b"><i class="fas fa-exclamation-triangle"></i> صيغة غير مدعومة، استخدم m3u أو m3u8</span>';
    inp.value='';
    return;
  }
  const fd = new FormData();
  if(window.csrfToken)fd.append('csrf_token',window.csrfToken);
  fd.append('ajax_action','import_m3u');
  fd.append('m3u_file', f);
  $('m3uFileStatus').innerHTML='<span class="sp"></span> جارٍ رفع وتحليل الملف…';
  fetch(location.href,{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
    inp.value='';
    if(d.success){
      $('m3uFileStatus').innerHTML='<span style="color:#00D084"><i class="fas fa-check-circle"></i> تم استيراد '+d.count+' قناة بنجاح</span>';
      if(window.toast) toast('تم استيراد '+d.count+' قناة من الملف','s');
      setTimeout(()=>location.reload(), 1300);
    } else {
      $('m3uFileStatus').innerHTML='<span style="color:#ff6b6b"><i class="fas fa-exclamation-triangle"></i> '+esc(d.error||'فشل الاستيراد')+'</span>';
    }
  }).catch(()=>{
    inp.value='';
    $('m3uFileStatus').innerHTML='<span style="color:#ff6b6b"><i class="fas fa-exclamation-triangle"></i> انقطع الاتصال بالخادم</span>';
  });
}

function m3uImportFromUrl(){
  const url = $('m3uUrlIn').value.trim();
  if(!url){ $('m3uUrlStatus').innerHTML='<span style="color:#ff6b6b">أدخل رابط M3U صالح</span>'; return; }
  const btn = $('m3uUrlBtn');
  btn.disabled = true;
  const origHtml = btn.innerHTML;
  btn.innerHTML = '<span class="sp"></span> جارٍ الاستيراد…';
  $('m3uUrlStatus').innerHTML='';
  api({ajax_action:'import_m3u', m3u_url:url}).then(d=>{
    btn.disabled = false;
    btn.innerHTML = origHtml;
    if(d.success){
      $('m3uUrlStatus').innerHTML='<span style="color:#00D084"><i class="fas fa-check-circle"></i> تم استيراد '+d.count+' قناة بنجاح</span>';
      if(window.toast) toast('تم استيراد '+d.count+' قناة من الرابط','s');
      setTimeout(()=>location.reload(), 1300);
    } else {
      $('m3uUrlStatus').innerHTML='<span style="color:#ff6b6b"><i class="fas fa-exclamation-triangle"></i> '+esc(d.error||'فشل الاستيراد')+'</span>';
    }
  }).catch(()=>{
    btn.disabled = false;
    btn.innerHTML = origHtml;
    $('m3uUrlStatus').innerHTML='<span style="color:#ff6b6b">انقطع الاتصال بالخادم</span>';
  });
}

let _m3uPlaylists = [];
function m3uLoadPlaylists(){
  $('m3uPlaylistsTbl').style.display='none';
  $('m3uPlaylistsEmpty').style.display='none';
  $('m3uPlaylistsLoading').style.display='block';
  api({ajax_action:'list_m3u_playlists'}).then(d=>{
    $('m3uPlaylistsLoading').style.display='none';
    if(!d.success){ $('m3uPlaylistsEmpty').style.display='block'; return; }
    _m3uPlaylists = d.data||[];
    if(!_m3uPlaylists.length){ $('m3uPlaylistsEmpty').style.display='block'; return; }
    m3uRenderPlaylists();
  }).catch(()=>{ $('m3uPlaylistsLoading').style.display='none'; $('m3uPlaylistsEmpty').style.display='block'; });
}

function m3uRenderPlaylists(){
  const tbody = $('m3uPlaylistsBody');
  tbody.innerHTML = _m3uPlaylists.map(p=>{
    const isUrl = p.source_type === 'url';
    const srcLabel = isUrl ? (p.source_url||p.name) : p.name;
    const typeBdg = isUrl ? '<span class="bdg bp"><i class="fas fa-link"></i> رابط</span>' : '<span class="bdg bc"><i class="fas fa-file"></i> ملف</span>';
    const refreshBtn = isUrl ? `<button class="ib ed" title="تحديث من نفس الرابط" onclick="m3uRefreshPlaylist(${p.id})"><i class="fas fa-sync"></i></button>` : '';
    const editBtn = isUrl ? `<button class="ib ed" title="تعديل الرابط (يحذف كل القنوات القديمة ويستورد من الرابط الجديد)" onclick="m3uEditPlaylist(${p.id},'${escA(p.source_url||'')}')"><i class="fas fa-pen"></i></button>` : '';
    return `<tr><td><strong style="color:var(--t1)">${esc(srcLabel)}</strong></td><td>${typeBdg}</td><td><span class="bdg bg">${p.channels_count||0} قناة</span></td><td style="font-size:.75rem;color:var(--t3)">${esc(p.created_at||'')}</td><td><div class="acts">${refreshBtn}${editBtn}<button class="ib dl" title="حذف القائمة بالكامل مع كل قنواتها" onclick="m3uDeletePlaylist(${p.id},'${escA(srcLabel)}')"><i class="fas fa-trash"></i></button></div></td></tr>`;
  }).join('');
  $('m3uPlaylistsTbl').style.display='table';
}

function m3uEditPlaylist(id, currentUrl){
  const newUrl = prompt('أدخل رابط M3U الجديد.\nسيتم حذف كل القنوات القديمة المرتبطة بهذه القائمة بالكامل واستيرادها من جديد من الرابط:', currentUrl||'');
  if(newUrl===null) return;
  const u = newUrl.trim();
  if(!u){ if(window.toast) toast('الرابط فارغ','e'); return; }
  if(!/^https?:\/\//i.test(u)){ if(window.toast) toast('رابط غير صالح، يجب أن يبدأ بـ http:// أو https://','e'); else alert('رابط غير صالح'); return; }
  if(window.toast) toast('جارٍ تعديل الرابط وإعادة الاستيراد…','i');
  api({ajax_action:'edit_m3u_playlist', id:id, source_url:u}).then(d=>{
    if(d.success){
      if(window.toast) toast('تم تعديل الرابط — '+d.count+' قناة','s');
      setTimeout(()=>location.reload(), 1200);
    } else {
      if(window.toast) toast(d.error||'فشل التعديل','e'); else alert(d.error||'فشل التعديل');
    }
  });
}

function m3uRefreshPlaylist(id){
  if(!confirm('سيتم حذف كل قنوات هذه القائمة واستيرادها من جديد من نفس الرابط، متابعة؟')) return;
  api({ajax_action:'refresh_m3u_playlist', id:id}).then(d=>{
    if(d.success){
      if(window.toast) toast('تم تحديث القائمة — '+d.count+' قناة','s');
      setTimeout(()=>location.reload(), 1200);
    } else {
      if(window.toast) toast(d.error||'فشل التحديث','e'); else alert(d.error||'فشل التحديث');
    }
  });
}

function m3uDeletePlaylist(id,name){
  if(!confirm('حذف القائمة "'+name+'" بالكامل مع كل القنوات التابعة لها؟')) return;
  api({ajax_action:'delete_m3u_playlist', id:id}).then(d=>{
    if(d.success){
      if(window.toast) toast('تم حذف القائمة','s');
      setTimeout(()=>location.reload(), 1000);
    } else {
      if(window.toast) toast(d.error||'فشل الحذف','e'); else alert(d.error||'فشل الحذف');
    }
  });
}

/* ════════════════════ [XTREAM-JS-START] وظائف حساب Xtream — إضافة فقط ════════════════════ */
let _xtVerified = null; // بيانات الحساب بعد التحقق الناجح

function xtreamSetStatus(elId, msg, type){
  const el = $(elId); if(!el) return;
  const colors = {s:'#2ecc71', e:'#e74c3c', i:'var(--t2)'};
  el.innerHTML = '<span style="color:'+(colors[type]||colors.i)+'">'+msg+'</span>';
}

function xtreamLogin(){
  const host = ($('xtHost').value||'').trim();
  const user = ($('xtUser').value||'').trim();
  const pass = ($('xtPass').value||'').trim();
  const name = ($('xtName').value||'').trim();
  if(!host || !user || !pass){ xtreamSetStatus('xtLoginStatus','⚠️ يرجى ملء العنوان واسم المستخدم وكلمة المرور','e'); return; }
  const btn = $('xtLoginBtn'); btn.disabled = true; btn.innerHTML = '<span class="sp"></span> جارٍ التحقق...';
  $('xtImportBox').style.display = 'none';
  xtreamSetStatus('xtLoginStatus','⏳ جارٍ الاتصال بالسيرفر...','i');
  api({ajax_action:'xtream_login', host:host, username:user, password:pass, account_name:name}).then(d=>{
    btn.disabled = false; btn.innerHTML = '<i class="fas fa-right-to-bracket"></i>تسجيل الدخول والتحقق';
    if(!d.success){ xtreamSetStatus('xtLoginStatus','❌ '+(d.error||'فشل تسجيل الدخول'),'e'); return; }
    _xtVerified = {host:d.host, username:user, password:pass, name:name};
    const ui = d.user_info||{}, si = d.server_info||{}, sup = d.supports||{}, cnt = d.counts||{};
    const exp = ui.exp_date ? new Date(ui.exp_date*1000).toLocaleDateString('ar') : 'غير محدد';
    const conns = (ui.active_cons||'0')+' / '+(ui.max_connections||'∞');
    xtreamSetStatus('xtLoginStatus','✅ تم تسجيل الدخول بنجاح','s');
    $('xtInfo').innerHTML =
      '<div style="display:flex;flex-wrap:wrap;gap:14px">'+
      '<span>👤 <b>'+esc(user)+'</b></span>'+
      '<span>📅 ينتهي: <b>'+esc(exp)+'</b></span>'+
      '<span>🔌 الاتصالات: <b>'+esc(conns)+'</b></span>'+
      '<span>🖥️ '+esc(si.url||'')+'</span>'+
      '</div>';
    // ضبط خيارات الاستيراد حسب الدعم
    const setOpt = (chk,lbl,supported,label,c)=>{
      const box=$(chk), span=$(lbl);
      if(supported){ box.disabled=false; box.checked=true; span.style.opacity='1'; span.innerHTML=label+' <span style="color:var(--t3)">('+c+' قسم)</span>'; }
      else { box.disabled=true; box.checked=false; span.style.opacity='.45'; span.innerHTML=label+' <span style="color:var(--t3)">(غير مدعوم)</span>'; }
    };
    setOpt('xtImpLive','xtLblLive',sup.live,'📡 القنوات',cnt.live);
    setOpt('xtImpVod','xtLblVod',sup.vod,'🎬 الأفلام',cnt.vod);
    setOpt('xtImpSeries','xtLblSeries',sup.series,'📺 المسلسلات',cnt.series);
    $('xtImportBox').style.display = 'block';
  }).catch(()=>{
    btn.disabled=false; btn.innerHTML='<i class="fas fa-right-to-bracket"></i>تسجيل الدخول والتحقق';
    xtreamSetStatus('xtLoginStatus','❌ خطأ في الاتصال','e');
  });
}

/* ══ محرّك تتبّع تقدّم الاستيراد الحيّ ══ */
let _xtPollTimer = null;

function xtFmtTime(sec){
  sec = Math.max(0, parseInt(sec)||0);
  const h = Math.floor(sec/3600), m = Math.floor((sec%3600)/60), s = sec%60;
  const p = n => String(n).padStart(2,'0');
  return h > 0 ? (h+':'+p(m)+':'+p(s)) : (p(m)+':'+p(s));
}

function xtProgShow(on){
  const box = $('xtProgBox');
  if(box) box.style.display = on ? 'block' : 'none';
}

function xtProgReset(){
  const set = (id,v)=>{ const e=$(id); if(e) e.textContent=v; };
  const fill = $('xtProgFill'); if(fill) fill.style.width='0%';
  set('xtProgPct','0%'); set('xtProgCount','—');
  set('xtCntLive','0'); set('xtCntVod','0'); set('xtCntSer','0'); set('xtCntSkip','0');
  set('xtElapsed','00:00'); set('xtEta','— يُحسب...');
  const sw = $('xtCntSkipWrap'); if(sw) sw.style.display='none';
  const lbl = $('xtProgLabel');
  if(lbl) lbl.innerHTML = '<span class="sp" style="width:15px;height:15px;border-width:2px;"></span>جارٍ التحضير...';
}

function xtProgApply(p){
  const set = (id,v)=>{ const e=$(id); if(e) e.textContent=v; };
  const pct = Math.max(0, Math.min(100, parseInt(p.percent)||0));

  const fill = $('xtProgFill'); if(fill) fill.style.width = pct+'%';
  set('xtProgPct', pct+'%');

  const lbl = $('xtProgLabel');
  if(lbl){
    const icon = '<span class="sp" style="width:15px;height:15px;border-width:2px;"></span>';
    let txt = p.label || 'جارٍ الاستيراد...';
    if(p.current) txt += ' — <span style="color:var(--t3); font-weight:500;">'+esc(p.current)+'</span>';
    lbl.innerHTML = icon + txt;
  }

  const done = parseInt(p.done)||0, total = parseInt(p.total)||0;
  set('xtProgCount', total > 0
      ? (done.toLocaleString('en-US')+' من '+total.toLocaleString('en-US')+' — المتبقي '+Math.max(0,total-done).toLocaleString('en-US'))
      : 'جارٍ جلب البيانات من السيرفر...');

  set('xtCntLive', (parseInt(p.live)||0).toLocaleString('en-US'));
  set('xtCntVod',  (parseInt(p.vod)||0).toLocaleString('en-US'));
  set('xtCntSer',  (parseInt(p.series)||0).toLocaleString('en-US'));

  const sk = parseInt(p.skipped)||0, sw = $('xtCntSkipWrap');
  if(sw){ sw.style.display = sk > 0 ? 'inline-flex' : 'none'; set('xtCntSkip', sk.toLocaleString('en-US')); }

  set('xtElapsed', xtFmtTime(p.elapsed));
  const eta = $('xtEta');
  if(eta){
    if(p.eta === null || p.eta === undefined){ eta.textContent = '— يُحسب...'; eta.style.color = 'var(--t3)'; }
    else { eta.textContent = xtFmtTime(p.eta); eta.style.color = '#10b981'; }
  }
}

function xtPollStart(){
  xtPollStop();
  _xtPollTimer = setInterval(()=>{
    api({ajax_action:'xtream_import_progress'})
      .then(d=>{ if(d && d.success && d.running) xtProgApply(d); })
      .catch(()=>{}); // فشل استعلام واحد لا يوقف المتابعة
  }, 1000);
}

function xtPollStop(){
  if(_xtPollTimer){ clearInterval(_xtPollTimer); _xtPollTimer = null; }
}

function xtreamImport(){
  if(!_xtVerified){ xtreamSetStatus('xtImportStatus','⚠️ سجّل الدخول أولاً','e'); return; }
  const impLive = $('xtImpLive').checked ? '1':'0';
  const impVod = $('xtImpVod').checked ? '1':'0';
  const impSeries = $('xtImpSeries').checked ? '1':'0';
  if(impLive==='0' && impVod==='0' && impSeries==='0'){ xtreamSetStatus('xtImportStatus','⚠️ اختر نوعاً واحداً على الأقل','e'); return; }
  const btn = $('xtImportBtn'), stopBtn = $('xtStopBtn');
  const restore = ()=>{
    xtPollStop(); xtProgShow(false);
    btn.disabled=false; btn.innerHTML='<i class="fas fa-download"></i>إضافة المحتوى المختار';
    if(stopBtn){ stopBtn.style.display='none'; stopBtn.disabled=false;
      stopBtn.innerHTML='<i class="fas fa-hand" style="margin-left:8px;"></i>إيقاف الاستيراد إجبارياً'; }
  };

  btn.disabled=true; btn.innerHTML='<span class="sp"></span> جارٍ الاستيراد... قد يستغرق وقتاً';
  if(stopBtn) stopBtn.style.display='inline-flex'; // يظهر زر الإيقاف أثناء العملية فقط
  xtProgReset(); xtProgShow(true); xtPollStart();   // شريط التقدّم الحيّ
  xtreamSetStatus('xtImportStatus','⏳ جارٍ جلب المحتوى وإضافته... لا تغلق الصفحة','i');

  api({
    ajax_action:'xtream_import',
    host:_xtVerified.host, username:_xtVerified.username, password:_xtVerified.password,
    account_name:_xtVerified.name,
    import_live:impLive, import_vod:impVod, import_series:impSeries
  }).then(d=>{
    restore();
    if(!d.success){ xtreamSetStatus('xtImportStatus','❌ '+(d.error||'فشل الاستيراد'),'e'); return; }
    const im = d.imported||{};
    const skipMsg = (d.skipped>0) ? ' — تم تخطي '+d.skipped+' عنصراً لبيانات غير صالحة' : '';
    xtreamSetStatus('xtImportStatus','✅ تمت الإضافة: '+(im.live||0)+' قناة، '+(im.vod||0)+' فيلم، '+(im.series||0)+' مسلسل'+skipMsg, skipMsg ? 'i' : 's');
    if(window.toast) toast('تمت إضافة حساب Xtream بنجاح','s');
    $('xtHost').value=''; $('xtUser').value=''; $('xtPass').value=''; $('xtName').value='';
    $('xtImportBox').style.display='none'; _xtVerified=null;
    xtreamLoadAccounts();
  }).catch(()=>{
    restore();
    xtreamSetStatus('xtImportStatus','❌ خطأ في الاتصال أثناء الاستيراد','e');
  });
}

/* إيقاف إجباري للاستيراد الجاري — يرفع إشارة يلتقطها السيرفر داخل حلقة الاستيراد */
function xtreamAbortImport(){
  if(!confirm('إيقاف الاستيراد الجاري إجبارياً؟\n\nسيتم التراجع عن كل ما استُورد جزئياً حتى الآن،\nولن يُضاف الحساب إلى القائمة.')) return;
  const stopBtn = $('xtStopBtn');
  if(stopBtn){
    stopBtn.disabled = true;
    stopBtn.innerHTML = '<span class="sp" style="width:14px;height:14px;border-width:2px;margin-left:8px;vertical-align:middle;"></span> جارٍ الإيقاف...';
  }
  xtreamSetStatus('xtImportStatus','🛑 تم إرسال إشارة الإيقاف — بانتظار توقف السيرفر...','e');
  api({ajax_action:'xtream_import_abort'}).then(()=>{
    if(window.toast) toast('تم إرسال إشارة الإيقاف','i');
  }).catch(()=>{
    if(window.toast) toast('تعذر إرسال إشارة الإيقاف','e');
  });
}

function xtreamLoadAccounts(){
  $('xtTbl').style.display='none';
  $('xtEmpty').style.display='none';
  $('xtLoading').style.display='block';
  api({ajax_action:'xtream_list'}).then(d=>{
    $('xtLoading').style.display='none';
    if(!d.success){ $('xtEmpty').style.display='block'; return; }
    const rows = d.accounts||[];
    if(!rows.length){ $('xtEmpty').style.display='block'; return; }
    $('xtBody').innerHTML = rows.map(a=>{
      const sync = a.last_sync ? esc(a.last_sync) : '—';
      
      let maxCons = '?', actCons = '?';
      let expDateStr = 'غير محدد';
      let uStatus = 'غير معروف';
      
      try {
        if(a.user_info) {
          const uInfo = typeof a.user_info === 'string' ? JSON.parse(a.user_info) : a.user_info;
          maxCons = uInfo.max_connections || 'غير محدود';
          actCons = uInfo.active_cons || 0;
          uStatus = uInfo.status || 'Active';
          if(uInfo.exp_date && uInfo.exp_date !== "null" && uInfo.exp_date !== "") {
            const exp = parseInt(uInfo.exp_date) * 1000;
            if(!isNaN(exp)) expDateStr = new Date(exp).toLocaleDateString('ar-EG', {year:'numeric', month:'short', day:'numeric'});
          }
        }
      }catch(e){}
      
      let statusColor = uStatus.toLowerCase() === 'active' ? '#10b981' : '#ef4444';

      return '<tr>'+
        '<td>'+
          '<div style="display:flex; align-items:center; gap:10px;">'+
             '<div style="width:10px; height:10px; border-radius:50%; background:'+statusColor+'; box-shadow:0 0 5px '+statusColor+'" title="'+esc(uStatus)+'"></div>'+
             '<strong style="color:var(--t1); font-size:1.05rem;">'+esc(a.name||'—')+'</strong>'+
          '</div>'+
        '</td>'+
        '<td style="direction:ltr; text-align:right;">'+
           '<div style="font-size:0.85rem; color:var(--t2); font-family:monospace; margin-bottom:4px;">'+esc(a.host||'')+'</div>'+
           '<div style="font-size:0.8rem; color:var(--primary); font-family:monospace;"><i class="fas fa-user" style="margin-right:5px; font-size:0.75rem;"></i>'+esc(a.username||'')+'</div>'+
        '</td>'+
        '<td style="text-align:center;">'+
           '<div style="display:inline-flex; align-items:center; background:var(--bg2); padding:5px 12px; border-radius:20px; border:1px solid var(--border);">'+
              '<span style="color:#ef4444; font-weight:bold; margin-left:5px;" title="المستخدم حالياً">'+actCons+'</span>'+
              '<span style="color:var(--t3); margin:0 5px;">/</span>'+
              '<span style="color:#8b5cf6; font-weight:bold;" title="الحد الأقصى">'+maxCons+'</span>'+
           '</div>'+
        '</td>'+
        '<td style="text-align:center;">'+
           '<div style="display:flex; gap:6px; justify-content:center;">'+
             '<span class="bdg bg" title="القنوات"><i class="fas fa-tv" style="margin-left:4px; font-size:0.7rem;"></i>'+(a.live_count||0)+'</span>'+
             '<span class="bdg bp" title="الأفلام"><i class="fas fa-film" style="margin-left:4px; font-size:0.7rem;"></i>'+(a.vod_count||0)+'</span>'+
             '<span class="bdg bc" title="المسلسلات"><i class="fas fa-layer-group" style="margin-left:4px; font-size:0.7rem;"></i>'+(a.series_count||0)+'</span>'+
           '</div>'+
        '</td>'+
        '<td><div style="font-size:0.9rem; font-weight:500; color:var(--t2);"><i class="far fa-clock" style="margin-left:6px; color:#10b981;"></i>'+expDateStr+'</div></td>'+
        '<td><div style="font-size:0.8rem; color:var(--t3);">'+sync+'</div></td>'+
        '<td style="text-align:center;"><div class="acts" style="justify-content:center;">'+
          '<button class="ib ed" title="تعديل بيانات الحساب" onclick="xtreamEdit('+a.id+',\''+escA(a.name||'')+'\',\''+escA(a.host||'')+'\',\''+escA(a.username||'')+'\')"><i class="fas fa-pen"></i></button>'+
          '<button class="ib" style="color:#f59e0b" title="تسجيل الخروج ومسح كل المحتوى المستورد" onclick="xtreamLogout('+a.id+',\''+escA(a.name||'')+'\')"><i class="fas fa-sign-out-alt"></i></button>'+
          '<button class="ib dl" title="حذف الحساب نهائياً وكل محتواه المستورد" onclick="xtreamDelete('+a.id+',\''+escA(a.name||'')+'\')"><i class="fas fa-trash"></i></button>'+
        '</div></td>'+
      '</tr>';
    }).join('');
    $('xtTbl').style.display='table';
  }).catch(()=>{ $('xtLoading').style.display='none'; $('xtEmpty').style.display='block'; });
}

/* مسح كل الأثر المحلي المرتبط بمحتوى الحساب: المفضلة + الإشعارات + الكاش */
function xtreamPurgeClient(){
  try{
    ['shashety_favs_v2','shashety_notifs_pending'].forEach(k=>{ try{localStorage.removeItem(k);}catch(e){} });
    try{
      Object.keys(localStorage).forEach(k=>{
        if(/^(shashety_|shs_|sc_)/i.test(k)) localStorage.removeItem(k);
      });
    }catch(e){}
    try{
      Object.keys(sessionStorage).forEach(k=>{
        if(/^(shs_|sc_)/i.test(k) || /api\.php/i.test(k)) sessionStorage.removeItem(k);
      });
    }catch(e){}
    if(window.scInvalidate) window.scInvalidate();
    if(window.caches && caches.keys) caches.keys().then(ks=>ks.forEach(k=>caches.delete(k))).catch(()=>{});
  }catch(e){}
}

function xtreamDelete(id, name){
  if(!confirm('حذف حساب "'+name+'" نهائياً وكل ما استُورد منه (القنوات + الأفلام + المسلسلات + الحلقات + أقسامها)؟\n\nلا يمكن التراجع عن هذه العملية.')) return;
  api({ajax_action:'xtream_delete', id:id}).then(d=>{
    if(d && d.success){
      if(d.purge_client) xtreamPurgeClient();
      if(window.toast) toast(d.message||'تم حذف الحساب وكل محتواه','s');
      xtreamLoadAccounts();
    } else {
      const msg = (d && d.error) ? d.error : 'فشل الحذف (رد غير متوقع من السيرفر)';
      if(window.toast) toast(msg,'e'); else alert(msg);
      console.error('xtream_delete failed:', d);
    }
  }).catch(err=>{
    if(window.toast) toast('خطأ في الاتصال أثناء الحذف — راجع Console','e');
    else alert('خطأ في الاتصال أثناء الحذف');
    console.error('xtream_delete error:', err);
  });
}

function xtreamLogout(id, name){
  if(!confirm('تسجيل الخروج من حساب "'+name+'"؟\n\nسيتم حذف كل القنوات والأفلام والمسلسلات والحلقات المستوردة منه،\nمع الإبقاء على بيانات الحساب لإعادة الاستيراد لاحقاً.')) return;
  if(window.toast) toast('جارٍ تسجيل الخروج ومسح المحتوى...','i');
  api({ajax_action:'xtream_logout', id:id}).then(d=>{
    if(d && d.success){
      if(d.purge_client) xtreamPurgeClient();
      if(window.toast) toast(d.message||'تم تسجيل الخروج ومسح كل محتوى الحساب','s');
      xtreamLoadAccounts();
    } else {
      const msg = (d && d.error) ? d.error : 'فشل تسجيل الخروج (رد غير متوقع من السيرفر)';
      if(window.toast) toast(msg,'e'); else alert(msg);
      console.error('xtream_logout failed:', d);
    }
  }).catch(err=>{
    // بدون هذا الـ catch كان الزر يفشل بصمت تماماً
    if(window.toast) toast('خطأ في الاتصال أثناء تسجيل الخروج — راجع Console','e');
    else alert('خطأ في الاتصال أثناء تسجيل الخروج');
    console.error('xtream_logout error:', err);
  });
}

/* تطبيق فهارس قاعدة البيانات */
function xtreamOptimizeDb(){
  const btn = $('xtOptBtn'), st = $('xtOptStatus');
  btn.disabled = true;
  btn.innerHTML = '<span class="sp" style="width:14px;height:14px;border-width:2px;margin-left:8px;vertical-align:middle;"></span> جارٍ التطبيق...';
  st.innerHTML = '<span style="color:var(--t3);">جارٍ فحص الجداول وإضافة الفهارس...</span>';

  api({ajax_action:'xtream_optimize_db'}).then(d=>{
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-bolt" style="margin-left:8px;"></i> تطبيق الفهارس وتسريع الموقع';
    if(d && d.success){
      const c = d.counts || {};
      const rows = (parseInt(c.channels)||0)+(parseInt(c.series)||0)+(parseInt(c.episodes)||0);
      const added = (d.added||[]).length;
      st.innerHTML =
        '<div style="color:'+(added?'#10b981':'var(--t3)')+'; font-weight:700;">'+
          '<i class="fas fa-'+(added?'circle-check':'circle-info')+'" style="margin-left:6px;"></i>'+esc(d.message||'')+
        '</div>'+
        '<div style="margin-top:8px; font-size:.82rem; color:var(--t3); font-variant-numeric:tabular-nums;">'+
          'القنوات: <b>'+(parseInt(c.channels)||0).toLocaleString('en-US')+'</b> · '+
          'شاشتي: <b>'+(parseInt(c.series)||0).toLocaleString('en-US')+'</b> · '+
          'الحلقات: <b>'+(parseInt(c.episodes)||0).toLocaleString('en-US')+'</b> · '+
          'الإجمالي: <b>'+rows.toLocaleString('en-US')+'</b> صف'+
        '</div>';
      if(window.toast) toast(d.message||'تم','s');
    } else {
      const msg = (d && d.error) ? d.error : 'فشل التطبيق';
      st.innerHTML = '<span style="color:#ef4444; font-weight:600;">'+esc(msg)+'</span>';
      if(window.toast) toast(msg,'e');
      console.error('xtream_optimize_db failed:', d);
    }
  }).catch(err=>{
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-bolt" style="margin-left:8px;"></i> تطبيق الفهارس وتسريع الموقع';
    st.innerHTML = '<span style="color:#ef4444; font-weight:600;">خطأ في الاتصال — راجع Console</span>';
    console.error('xtream_optimize_db error:', err);
  });
}

/* نقل الأفلام العالقة في «القنوات» إلى «شاشتي» */
function xtreamFixVod(){
  if(!confirm('نقل كل الأفلام المستوردة من قسم «إدارة القنوات» إلى «إدارة شاشتي»؟\n\nالقنوات المباشرة (ts / m3u8) لن تتأثر.')) return;
  const btn = $('xtFixVodBtn'), st = $('xtFixVodStatus');
  btn.disabled = true;
  btn.innerHTML = '<span class="sp" style="width:14px;height:14px;border-width:2px;margin-left:8px;vertical-align:middle;"></span> جارٍ النقل...';
  st.innerHTML = '<span style="color:var(--t3);">جارٍ فحص القنوات ونقل الأفلام...</span>';

  api({ajax_action:'xtream_fix_vod'}).then(d=>{
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-arrows-turn-right" style="margin-left:8px;"></i> نقل الأفلام إلى شاشتي الآن';
    if(d && d.success){
      if(d.purge_client) xtreamPurgeClient();
      const moved = parseInt(d.moved)||0;
      st.innerHTML = moved > 0
        ? '<span style="color:#10b981; font-weight:700;"><i class="fas fa-circle-check" style="margin-left:6px;"></i>'+esc(d.message||'')+'</span>'
        : '<span style="color:var(--t3);"><i class="fas fa-circle-info" style="margin-left:6px;"></i>'+esc(d.message||'لا توجد أفلام تحتاج نقلاً')+'</span>';
      if(window.toast) toast(d.message||'تم','s');
      if(typeof loadChannels==='function') loadChannels();
      if(typeof loadSeries==='function') loadSeries();
    } else {
      const msg = (d && d.error) ? d.error : 'فشل النقل';
      st.innerHTML = '<span style="color:#ef4444; font-weight:600;">'+esc(msg)+'</span>';
      if(window.toast) toast(msg,'e');
      console.error('xtream_fix_vod failed:', d);
    }
  }).catch(err=>{
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-arrows-turn-right" style="margin-left:8px;"></i> نقل الأفلام إلى شاشتي الآن';
    st.innerHTML = '<span style="color:#ef4444; font-weight:600;">خطأ في الاتصال — راجع Console</span>';
    console.error('xtream_fix_vod error:', err);
  });
}

/* ── مسح إجباري يدوي لكل ما يخص Xtream (منطقة الخطر) ── */
function xtreamPurgeAll(){
  const btn = $('xtPurgeBtn'), st = $('xtPurgeStatus'), cb = $('xtPurgeConfirm');

  // تأكيد أول
  if(!confirm('⚠️ تحذير أخير\n\nسيتم حذف كل ما يخص Xtream نهائياً من قاعدة البيانات:\n\n• كل الحسابات المضافة\n• كل القنوات المستوردة\n• كل الأفلام\n• كل المسلسلات والحلقات\n• كل الأقسام المستوردة\n\nلا يمكن التراجع عن هذه العملية إطلاقاً.\n\nهل تريد المتابعة؟')) return;

  // تأكيد ثانٍ بالكتابة
  const typed = prompt('للتأكيد النهائي، اكتب الكلمة التالية بالضبط:\n\nمسح', '');
  if(typed === null) return;
  if(typed.trim() !== 'مسح'){
    if(window.toast) toast('لم تتم كتابة كلمة التأكيد بشكل صحيح — تم الإلغاء','e');
    else alert('لم تتم كتابة كلمة التأكيد بشكل صحيح — تم الإلغاء');
    return;
  }

  btn.disabled = true;
  btn.innerHTML = '<span class="sp" style="width:15px;height:15px;border-width:2px;margin-left:8px;vertical-align:middle;"></span> جاري المسح...';
  st.innerHTML = '<span style="color:var(--t3);">جاري حذف كل محتوى Xtream، الرجاء الانتظار...</span>';

  api({ajax_action:'xtream_purge_all', confirm:'PURGE'}).then(d=>{
    btn.innerHTML = '<i class="fas fa-bomb" style="margin-left:8px;"></i> مسح كل محتوى Xtream إجبارياً';
    if(d.success){
      if(d.purge_client && window.xtreamPurgeClient) xtreamPurgeClient();
      const s = d.stats || {};
      st.innerHTML = '<div style="color:#10b981; font-weight:700; margin-bottom:8px;">'+
          '<i class="fas fa-circle-check" style="margin-left:6px;"></i>تم مسح كل محتوى Xtream بنجاح</div>'+
        '<div style="color:var(--t3); font-size:.86rem; line-height:1.9;">'+
          'الحسابات: <strong>'+(s.accounts||0)+'</strong> • '+
          'القنوات/الأفلام: <strong>'+(s.channels||0)+'</strong> • '+
          'المسلسلات: <strong>'+(s.series||0)+'</strong> • '+
          'الحلقات: <strong>'+(s.episodes||0)+'</strong> • '+
          'الأقسام: <strong>'+(s.categories||0)+'</strong>'+
          (s.cat_kept ? ' <br><span style="color:#f59e0b;">تم الإبقاء على '+s.cat_kept+' قسم لاحتوائه محتوى من مصادر أخرى</span>' : '')+
        '</div>';
      if(window.toast) toast('تم مسح كل محتوى Xtream نهائياً','s');
      cb.checked = false; btn.disabled = true;
      xtreamLoadAccounts();
    } else {
      btn.disabled = !cb.checked;
      st.innerHTML = '<span style="color:#ef4444; font-weight:600;"><i class="fas fa-circle-xmark" style="margin-left:6px;"></i>'+(d.error||'فشل المسح')+'</span>';
      if(window.toast) toast(d.error||'فشل المسح','e');
    }
  }).catch(()=>{
    btn.innerHTML = '<i class="fas fa-bomb" style="margin-left:8px;"></i> مسح كل محتوى Xtream إجبارياً';
    btn.disabled = !cb.checked;
    st.innerHTML = '<span style="color:#ef4444; font-weight:600;">خطأ في الاتصال بالسيرفر</span>';
  });
}

function xtreamEdit(id, name, host, user){
  const newName = prompt('اسم الحساب:', name||'');
  if(newName===null) return;
  const newHost = prompt('العنوان (Host):', host||'');
  if(newHost===null) return;
  const newUser = prompt('اسم المستخدم:', user||'');
  if(newUser===null) return;
  const newPass = prompt('كلمة المرور الجديدة (اتركها فارغة للإبقاء على الحالية):', '');
  if(newPass===null) return;
  api({ajax_action:'xtream_update', id:id, account_name:newName.trim(), host:newHost.trim(), username:newUser.trim(), password:newPass.trim()}).then(d=>{
    if(d && d.success){ if(window.toast) toast('تم تحديث بيانات الحساب','s'); xtreamLoadAccounts(); }
    else {
      const msg = (d && d.error) ? d.error : 'فشل التعديل (رد غير متوقع من السيرفر)';
      if(window.toast) toast(msg,'e'); else alert(msg);
      console.error('xtream_update failed:', d);
    }
  }).catch(err=>{
    if(window.toast) toast('خطأ في الاتصال أثناء التعديل — راجع Console','e');
    else alert('خطأ في الاتصال أثناء التعديل');
    console.error('xtream_update error:', err);
  });
}
/* ════════════════════ [XTREAM-JS-END] نهاية وظائف حساب Xtream ════════════════════ */

let _srAll=[],_srCurId=0,_srCurName='';
/* ── تحميل «شاشتي» على صفحات ──
   سابقاً: طلب واحد يجلب كل الأعمال (آلاف بعد استيراد أفلام Xtream) ويبني بطاقة لكل واحد.
   الآن: ٦٠ عملاً لكل دفعة + بحث على السيرفر + زر «تحميل المزيد». */
const SR_PAGE = 60;
let _srOffset = 0, _srTotal = 0, _srBusy = false, _srQ = '';

function loadSeries(reset){
  if(reset !== false) reset = true;
  if(reset){ _srOffset = 0; _srAll = []; _srTotal = 0; }
  $('epsPanel').style.display='none';
  $('srBackBtn').style.display='none';
  $('srBulkBtn').style.display='none';
  $('srBreadcrumb').style.display='none';
  $('srAddBtn').innerHTML='<i class="fas fa-plus"></i>مسلسل / فيلم جديد';
  $('srAddBtn').setAttribute('onclick',"OM('addSeriesM')");
  if(reset){ $('srGrid').style.display='none'; $('srEmpty').style.display='none'; $('srLoading').style.display='block'; }

  if(_srBusy) return;
  _srBusy = true;
  const cid = $('srCatFilter').value;
  api({ajax_action:'get_series', category_id:cid, q:_srQ, limit:SR_PAGE, offset:_srOffset})
    .then(d=>{
      _srBusy = false;
      $('srLoading').style.display='none';
      if(!d || !d.success) return;
      _srTotal  = parseInt(d.total)||0;
      _srAll    = _srAll.concat(d.data||[]);
      _srOffset = _srAll.length;
      srRender(_srAll);
    })
    .catch(err=>{
      _srBusy = false;
      $('srLoading').style.display='none';
      console.error('get_series error:', err);
      if(window.toast) toast('تعذر تحميل شاشتي','e');
    });
}

/* البحث يتم على السيرفر (لا يمكن الفلترة محلياً لأننا لا نحمّل كل شيء) */
let _srSearchTimer = null;
function srFilter(){
  clearTimeout(_srSearchTimer);
  _srSearchTimer = setTimeout(()=>{
    _srQ = $('srSearch').value.trim();
    loadSeries(true);
  }, 300);
}

function srLoadMore(){ if(!_srBusy && _srAll.length < _srTotal) loadSeries(false); }
function srRender(arr){
  const g=$('srGrid'), e=$('srEmpty');
  $('srCount').textContent = _srTotal
    ? (arr.length < _srTotal ? ('عرض '+arr.length+' من '+_srTotal.toLocaleString('en-US')) : (_srTotal.toLocaleString('en-US')+' مسلسلات/أفلام'))
    : (arr.length+' مسلسلات/أفلام');
  if(!arr.length){ g.style.display='none'; e.style.display='block'; srMoreBtn(false); return; }
  e.style.display='none'; g.style.display='grid';
  // لا نمرّر الكائن كاملاً في onclick — نمرّر المعرّف ونجلب التفاصيل عند التعديل
  g.innerHTML = arr.map(s=>`<div class="src" id="sr-${s.id}"><div class="src-poster" onclick="srOpen(${s.id},'${escA(s.name)}')">${s.poster_url?`<img src="${esc(s.poster_url)}" loading="lazy" decoding="async" onerror="this.parentElement.innerHTML='<i class=\\'fas fa-film\\'></i>'">`:'<i class="fas fa-film"></i>'}</div><div class="src-body" onclick="srOpen(${s.id},'${escA(s.name)}')"><div class="src-name">${esc(s.name)}</div><div class="src-meta"><span class="bdg bc">${esc(s.cat_name||'—')}</span><span class="bdg bp">${s.ep_count||0} فيديو</span></div></div><div class="src-acts"><button class="ib ed" onclick="srEdit(${s.id})"><i class="fas fa-pen"></i></button><button class="ib dl" onclick="srDel(${s.id},'${escA(s.name)}')"><i class="fas fa-trash"></i></button></div></div>`).join('');
  srMoreBtn(arr.length < _srTotal);
}

/* زر «تحميل المزيد» أسفل الشبكة */
function srMoreBtn(show){
  let b = $('srMoreWrap');
  if(!show){ if(b) b.style.display='none'; return; }
  if(!b){
    b = document.createElement('div');
    b.id = 'srMoreWrap';
    b.style.cssText = 'text-align:center; padding:22px 0 6px;';
    b.innerHTML = '<button class="btn btn-p" id="srMoreBtn" onclick="srLoadMore()" style="padding:11px 26px; border-radius:10px; font-weight:700;"><i class="fas fa-chevron-down" style="margin-left:8px;"></i>تحميل المزيد</button>';
    const g = $('srGrid');
    if(g && g.parentNode) g.parentNode.insertBefore(b, g.nextSibling);
  }
  b.style.display = 'block';
  const btn = $('srMoreBtn');
  if(btn){
    btn.disabled = _srBusy;
    btn.innerHTML = _srBusy
      ? '<span class="sp" style="width:14px;height:14px;border-width:2px;margin-left:8px;vertical-align:middle;"></span> جارٍ التحميل...'
      : ('<i class="fas fa-chevron-down" style="margin-left:8px;"></i>تحميل المزيد ('+(_srTotal-_srAll.length).toLocaleString('en-US')+' متبقٍ)');
  }
}
function srOpen(id,name){_srCurId=id;_srCurName=name;$('srGrid').style.display='none';$('srEmpty').style.display='none';$('srFilterBar').style.display='none';$('epsPanel').style.display='block';$('srBackBtn').style.display='';$('srBulkBtn').style.display='';$('srBreadcrumb').style.display='flex';$('srBCName').textContent=name;$('srAddBtn').style.display='none';loadEps();}
function srBack(){$('epsPanel').style.display='none';$('srBackBtn').style.display='none';$('srBulkBtn').style.display='none';$('srBreadcrumb').style.display='none';$('srFilterBar').style.display='flex';$('srAddBtn').style.display='';$('srAddBtn').innerHTML='<i class="fas fa-plus"></i>مسلسل / فيلم جديد';$('srAddBtn').setAttribute('onclick',"OM('addSeriesM')");loadSeries();}
function srAdd(){const n=$('srName').value.trim(),cid=$('srCat').value,desc=$('srDesc').value.trim(),poster=$('srPoster').value.trim();if(!n||!cid){al('srAddAlert','أدخل الاسم واختر القسم','e');return;}api({ajax_action:'add_series',name:n,category_id:cid,description:desc,poster_url:poster}).then(d=>{if(d.success){CM('addSeriesM');loadSeries();$('srName').value='';$('srCat').value='';$('srDesc').value='';$('srPoster').value='';$('srPosterThumb').style.display='none';$('srPosterStatus').innerHTML='';}else al('srAddAlert',d.error||'خطأ','e');});}
/* يستقبل الآن المعرّف فقط ويجلب التفاصيل الكاملة (الوصف غير موجود في قائمة الشبكة) */
function srEdit(idOrObj){
  const fill = (s)=>{
    $('eSrId').value=s.id; $('eSrName').value=s.name;
    $('eSrDesc').value=s.description||''; $('eSrPoster').value=s.poster_url||'';
    const sel=$('eSrCat');
    for(let o of sel.options) o.selected=(o.value===String(s.category_id));
    const thumbEl=$('eSrPosterThumb'), statusEl=$('eSrPosterStatus');
    if(s.poster_url){ thumbEl.style.display='block'; thumbEl.querySelector('img').src=s.poster_url; statusEl.innerHTML=''; }
    else { thumbEl.style.display='none'; statusEl.innerHTML=''; }
    OM('editSeriesM');
  };
  if(idOrObj && typeof idOrObj==='object'){ fill(idOrObj); return; }
  const id = parseInt(idOrObj)||0;
  if(!id) return;
  api({ajax_action:'get_series_one', id:id}).then(d=>{
    if(d && d.success && d.data) fill(d.data);
    else if(window.toast) toast((d&&d.error)||'تعذر جلب البيانات','e');
  }).catch(err=>{
    console.error('get_series_one error:', err);
    if(window.toast) toast('خطأ في الاتصال','e');
  });
}
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
                    <td><div onclick='testChannel("${escA(e.stream_url)}","${escA(e.title)}","${escA(e.subtitle_url||'')}")' style="color:var(--red);font-size:1.35rem;padding-left:4px;cursor:pointer" title="تشغيل الفيديو"><i class="fas fa-play-circle"></i></div></td>
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
function epFileUpload(inp){const f=inp.files[0];if(!f)return;const fd=new FormData();if(window.csrfToken)fd.append('csrf_token',window.csrfToken);fd.append('ajax_action','upload_episode_video');fd.append('episode',f);fd.append('series_id',_srCurId);$('epFilePBar').style.width='0%';$('epFileProgress').style.display='block';$('epFileChip').style.display='none';const xhr=new XMLHttpRequest();xhr.upload.onprogress=e=>{if(e.lengthComputable){const p=Math.round(e.loaded/e.total*100);$('epFilePBar').style.width=p+'%';}};xhr.onload=()=>{$('epFileProgress').style.display='none';try{const d=JSON.parse(xhr.responseText);if(d.success){_epFileUpUrl=d.url;$('epUploadedUrl').value=d.url;$('epFileChip').style.display='flex';$('epFileChipName').textContent=d.original;$('epNum').value=d.episode_number||1;if(!$('epTitle').value.trim())$('epTitle').value=(d.original).replace(/\.[^.]+$/,'');}else al('addEpAlert',d.error||'خطأ في الرفع','e');}catch(e){al('addEpAlert','خطأ في الاستجابة','e');}};xhr.onerror=()=>{$('epFileProgress').style.display='none';al('addEpAlert','انقطع الاتصال','e');};xhr.open('POST',location.href);xhr.send(fd);}
function epSubUpload(inp){const f=inp.files[0];if(!f)return;const fd=new FormData();if(window.csrfToken)fd.append('csrf_token',window.csrfToken);fd.append('ajax_action','upload_episode_subtitle');fd.append('subtitle',f);fd.append('series_id',_srCurId);fetch(location.href,{method:'POST',body:fd}).then(r=>r.json()).then(d=>{if(d.success){_epSubUpUrl=d.url;$('epSubUrl').value=d.url;$('epSubChip').style.display='flex';$('epSubChipName').textContent=f.name;}else al('addEpAlert',d.error||'خطأ','e');});}
function epAdd(){const num=parseInt($('epNum').value)||1;const title=$('epTitle').value.trim()||'الحلقة '+num;const urlTab=$('etab-url').style.display!=='none';let url=urlTab?$('epUrl').value.trim():($('epUploadedUrl').value.trim());const sub=$('epSubUrl').value.trim()||_epSubUpUrl;const dur=$('epDur').value.trim();if(!url){al('addEpAlert','أدخل رابط الفيديو أو ارفع ملفاً','e');return;}api({ajax_action:'add_episode',series_id:_srCurId,episode_number:num,title,stream_url:url,subtitle_url:sub,duration:dur}).then(d=>{if(d.success){CM('addEpM');loadEps();$('epNum').value=parseInt($('epNum').value)+1;$('epTitle').value='';$('epUrl').value='';$('epSubUrl').value='';$('epDur').value='';$('epUploadedUrl').value='';$('epFileChip').style.display='none';$('epSubChip').style.display='none';_epSubUpUrl='';_epFileUpUrl='';}else al('addEpAlert',d.error||'خطأ','e');});}

function eEpSubUpload(inp){const f=inp.files[0];if(!f)return;const fd=new FormData();if(window.csrfToken)fd.append('csrf_token',window.csrfToken);fd.append('ajax_action','upload_subtitle_file');fd.append('subtitle',f);$('eEpSubStatus').innerHTML='<span style="color:var(--gold)"><i class="fas fa-spinner fa-spin"></i> جارٍ الرفع...</span>';fetch(location.href,{method:'POST',body:fd}).then(r=>r.json()).then(d=>{if(d.success){$('eEpSub').value=d.vtt_url||d.url;$('eEpSubStatus').innerHTML='<span style="color:#00D084"><i class="fas fa-check-circle"></i> تم رفع الترجمة بنجاح</span>';}else{$('eEpSubStatus').innerHTML='<span style="color:#ff6b6b"><i class="fas fa-exclamation-triangle"></i> '+d.error+'</span>';}}).catch(()=>{if(window.al)al('eEpAlert','خطأ في الاتصال','e');});}
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
async function bulkUpload(){if(!_bulkFiles.length||!_srCurId){al('bulkAlert','تأكد من اختيار الملفات','e');return;}$('bulkStartBtn').disabled=true;$('bulkProgress').style.display='block';let done=0,errs=0;for(let i=0;i<_bulkFiles.length;i++){const f=_bulkFiles[i];$('bstat-'+i).textContent='في العملية..';$('bstat-'+i).className='ep-stat up';let vidName=(f.name).replace(/\.[^.]+$/,'');$('bulkCurFile').innerHTML=`<b style="color:var(--t1)">${esc(vidName)}</b>`;const fd=new FormData();if(window.csrfToken)fd.append('csrf_token',window.csrfToken);fd.append('ajax_action','upload_episode_video');fd.append('episode',f);fd.append('series_id',_srCurId);try{const d=await new Promise((res,rej)=>{const x=new XMLHttpRequest();let t0=Date.now(), l0=0; x.upload.onprogress=(e)=>{if(e.lengthComputable){const p=Math.round((e.loaded/e.total)*100);$('bulkPBar').style.width=p+'%';let t1=Date.now(),dt=(t1-t0)/1000;let sp='جاري التوزيع الشبكي..';if(dt>=0.65){sp=fmtSz((e.loaded-l0)/dt)+'/ث';t0=t1;l0=e.loaded;} $('bulkProgPct').innerHTML=`<span style="color:#00D084" dir="ltr">⏳ ${sp}</span> &nbsp; <b>${p}%</b>`;}};x.onload=()=>{try{res(JSON.parse(x.responseText));}catch(err){rej();}};x.onerror=()=>rej();x.open('POST',location.href);x.send(fd);});if(d.success){const title=(d.original||f.name).replace(/\.[^.]+$/,'');const d2=await api({ajax_action:'add_episode',series_id:_srCurId,episode_number:(i+1),title:title,stream_url:d.url,subtitle_url:'',duration:''});if(d2.success){done++;$('bstat-'+i).textContent='✅ دُمج واكتمل';$('bstat-'+i).className='ep-stat ok';}else{errs++;$('bstat-'+i).textContent='❌ صُد بخادم الداتا';$('bstat-'+i).className='ep-stat err';}}else{errs++;$('bstat-'+i).textContent='❌ رُفض الرفع كلياً';$('bstat-'+i).className='ep-stat err';}}catch(e){errs++;$('bstat-'+i).textContent='❌ فُصل من العوامل';$('bstat-'+i).className='ep-stat err';}}$('bulkPBar').style.width='100%';$('bulkProgPct').textContent='100%';$('bulkCurFile').textContent='';$('bulkResult').style.display='block';$('bulkResult').innerHTML=`<div class="al ${errs?'al-e':'al-s'}" style="margin:0"><i class="fas fa-${errs?'exclamation-circle':'check-circle'}"></i> خلاصة النتائج الحسابية: تم تأمين وتسجيل ( ${done} ) ملفات خاضعة للاستدامة، وسُقط (${errs}).</div>`;$('bulkStartBtn').disabled=false;_bulkFiles=[];if(_srCurId)loadEps();}

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

function vidUpload(inp){const f=inp.files[0];if(!f)return;const fd=new FormData();if(window.csrfToken)fd.append('csrf_token',window.csrfToken);fd.append('ajax_action','upload_video');fd.append('video',f);$('vidProg').style.display='block';$('cancelDlBtn').style.display='none';$('vidProgSp').style.display='inline-block';$('vidPBar').style.animation='none';$('vidChip').style.display='none';const xhr=new XMLHttpRequest();xhr.upload.onprogress=e=>{if(e.lengthComputable){const p=Math.round(e.loaded/e.total*100);$('vidPBar').style.width=p+'%';$('vidPct').textContent=p+'%';$('vidPLabel').textContent=p<100?'رفع '+fmtSz(e.loaded)+' / '+fmtSz(f.size):'معالجة…';}};xhr.onload=()=>{$('vidProg').style.display='none';const raw=xhr.responseText.trim();if(!raw){al('v1alert','الخادم لم يُرجع رداً — تحقق من إعدادات PHP','e');return;}let d;try{d=JSON.parse(raw);}catch(ex){const preview=raw.replace(/<[^>]+>/g,'').substring(0,300);al('v1alert','خطأ في الاستجابة: '+preview,'e');return;}if(d.success){VID.filename=d.filename;VID.url=d.url;VID.file=f;$('vidChip').style.display='flex';$('vidChipName').textContent=d.original;$('vidChipSize').textContent=fmtSz(f.size);$('vNext1').disabled=false;const title=d.original.replace(/\.[^.]+$/,'').replace(/[._\-]/g,' ').replace(/\b(720p|1080p|4k|bdrip|web|hdtv|bluray)\b/gi,'').trim();$('osQ').value=title;$('vChanName').value=title;al('v1alert','✅ تم رفع الفيديو بنجاح','s');}else{let msg=d.error||'خطأ غير معروف';if(d.debug)msg+=' — '+d.debug;al('v1alert',msg,'e');}};xhr.onerror=()=>{$('vidProg').style.display='none';al('v1alert','انقطع الاتصال بالخادم','e');};xhr.open('POST',location.href);xhr.send(fd);}
function vidDebug(){api({ajax_action:'debug_upload'}).then(d=>{const dbg=$('v1debug');dbg.style.display='block';if(d.success){const ok='✅',no='❌';dbg.innerHTML=`<strong>إعدادات PHP:</strong><br>upload_max_filesize: <b>${d.upload_max_filesize}</b><br>post_max_size: <b>${d.post_max_size}</b><br>مجلد الرفع: <b>${d.upload_dir}</b><br>المجلد موجود: ${d.dir_exists?ok:no}<br>قابل للكتابة: ${d.dir_writable?ok:no}<br>PHP: ${d.php_version}<br><br><small style="color:var(--t3)">إذا كانت القيم 8M أو أقل، أضف للـ .htaccess:<br>php_value upload_max_filesize 2048M<br>php_value post_max_size 2048M</small>`;}else dbg.innerHTML='خطأ: '+d.error;});}
function vidReset(){VID={file:null,filename:'',url:'',subFile:'',subUrl:'',subVttUrl:'',opt:'none'};$('vidChip').style.display='none';$('vidFileIn').value='';$('vNext1').disabled=true;al('v1alert','','');}
function vidGo(step){if(step===3){$('mSumV').textContent=VID.filename||'—';$('mSumS').textContent=VID.subFile?(VID.subFile+' ✅'):'بدون ترجمة';}document.querySelectorAll('.vp').forEach(p=>p.classList.remove('act'));document.querySelectorAll('.vs').forEach(v=>v.classList.remove('act'));$('vp'+step).classList.add('act');$('vs'+step).classList.add('act');for(let i=1;i<step;i++)$('vs'+i).classList.add('done');}
function vidSubOpt(opt){VID.opt=opt;document.querySelectorAll('.so').forEach(s=>s.classList.remove('sel'));$('so-'+opt).classList.add('sel');$('osCard').style.display=opt==='search'?'block':'none';$('subUpCard').style.display=opt==='upload'?'block':'none';}
function subFileUpload(inp){const f=inp.files[0];if(!f)return;const fd=new FormData();if(window.csrfToken)fd.append('csrf_token',window.csrfToken);fd.append('ajax_action','upload_subtitle_file');fd.append('subtitle',f);al('subAl','<span class="sp"></span> جارٍ الرفع…','i');fetch(location.href,{method:'POST',body:fd}).then(r=>r.json()).then(d=>{if(d.success){VID.subFile=d.filename;VID.subUrl=d.url;$('upSubChip').style.display='flex';$('upSubName').textContent=f.name;al('subAl','✅ تم','s');}else al('subAl',d.error||'خطأ','e');});}

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
function srPosterUpload(inp,urlInputId,thumbId,statusId){const f=inp.files[0];if(!f)return;const statusEl=$(statusId),thumbEl=$(thumbId);statusEl.innerHTML='<span class="sp"></span> <span style="color:var(--t2)">جارٍ رفع الصورة…</span>';const fd=new FormData();if(window.csrfToken)fd.append('csrf_token',window.csrfToken);fd.append('ajax_action','upload_series_poster');fd.append('poster',f);const xhr=new XMLHttpRequest();xhr.upload.onprogress=e=>{if(e.lengthComputable){const p=Math.round(e.loaded/e.total*100);statusEl.innerHTML=`<span class="sp"></span> <span style="color:var(--gold)">${p}%</span>`;}};xhr.onload=()=>{try{const d=JSON.parse(xhr.responseText);if(d.success){$(urlInputId).value=d.url;statusEl.innerHTML=`<span style="color:#00D084"><i class="fas fa-check-circle"></i> تم رفع الصورة بنجاح — ${fmtSz(d.size)}</span>`;thumbEl.style.display='block';thumbEl.querySelector('img').src=d.url;thumbEl.querySelector('img').style.borderColor='#00D084';}else statusEl.innerHTML=`<span style="color:#ff6b6b"><i class="fas fa-exclamation-circle"></i> ${d.error||'خطأ في الرفع'}</span>`;}catch(e){statusEl.innerHTML=`<span style="color:#ff6b6b"><i class="fas fa-exclamation-circle"></i> خطأ في الاستجابة</span>`;}inp.value='';};xhr.onerror=()=>{statusEl.innerHTML=`<span style="color:#ff6b6b"><i class="fas fa-exclamation-circle"></i> انقطع الاتصال</span>`;};xhr.open('POST',location.href);xhr.send(fd);}
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
    const fd=new FormData(); if(window.csrfToken)fd.append('csrf_token',window.csrfToken); fd.append('ajax_action','upload_subtitle_file'); fd.append('subtitle',f);
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
const ADMIN_ROLE = "<?php echo $_admin_role; ?>";
const ADMIN_SECTIONS = <?php echo json_encode($_admin_sections); ?>;
const ADMIN_USER_ID = <?php echo $_admin_user_id; ?>;

const ALL_SECTION_DEFS = [
    {key:'dashboard',    name:'لوحة التحكم',      icon:'fas fa-home'},
    {key:'categories',   name:'الأقسام',           icon:'fas fa-th-large'},
    {key:'channels',     name:'القنوات',           icon:'fas fa-tv'},
    {key:'m3u-import',   name:'استيراد M3U',       icon:'fas fa-file-import'},
    {key:'xtream',       name:'حساب Xtream',       icon:'fas fa-satellite-dish'},
    {key:'series',       name:'شاشتي',             icon:'fas fa-film'},
    {key:'vupload',      name:'رفع الأفلام',       icon:'fas fa-cloud-upload-alt'},
    {key:'vmanage',      name:'إدارة الفيديوهات',  icon:'fas fa-photo-video'},
    {key:'api-settings', name:'إعدادات API',       icon:'fas fa-plug'},
    {key:'site-settings',name:'إعدادات الموقع',    icon:'fas fa-cog'},
    {key:'change-password',name:'كلمة المرور',     icon:'fas fa-key'},
    {key:'system-tools', name:'صيانة النظام',      icon:'fas fa-tools'},
    {key:'backup',       name:'النسخ الاحتياطي',   icon:'fas fa-database'},
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
            else if(allowed[0] === 'm3u-import') { if(typeof m3uLoadPlaylists === 'function') m3uLoadPlaylists(); }
            else if(allowed[0] === 'xtream') { if(typeof xtreamLoadAccounts === 'function') xtreamLoadAccounts(); }
        }, 100);
    }
})();
// ═══ نهاية نظام المستخدمين ═══


// إغلاق قائمة اللغات عند النقر بالخارج
document.addEventListener('click', function() {
    var drop = document.getElementById('langDrop');
    if(drop && drop.classList.contains('op')) { drop.classList.remove('op'); }
    var psw = document.getElementById('profSw');
    if(psw && psw.classList.contains('op')) { psw.classList.remove('op'); }
});

// ══════════════════════════════════════════════════════════════
// 🎨 THEME SYSTEM — SHASHITY PRO
// ══════════════════════════════════════════════════════════════

const THEME_PRESETS = {
    default: { name: 'الافتراضي', css: '' },
    ultrachromic: {
        name: 'Ultrachromic',
        css: `:root{--red:#b847ff;--redg:rgba(184,71,255,0.35);--gold:#6200ea;--s0:#0d0221;--s1:#100828;--s2:#180d35;--s3:#1f1140;--s4:#28174d;--br:rgba(255,255,255,.09);--brh:rgba(255,255,255,.2);--t1:#f0e8ff;--t2:#b89fd8;--t3:#6b5a8a;--r1:12px}

/* خلفية */
html{background:#0d0221}
body{background:#0d0221 !important;color:#f0e8ff;position:relative;min-height:100vh}
body::before{content:"";position:fixed;inset:0;z-index:-2;pointer-events:none;background:
  radial-gradient(60% 50% at 18% 10%,rgba(184,71,255,0.16),transparent 62%),
  radial-gradient(60% 50% at 85% 20%,rgba(98,0,234,0.14),transparent 62%),
  radial-gradient(60% 50% at 70% 90%,rgba(224,64,251,0.1),transparent 62%);
  animation:thDrift 30s ease-in-out infinite alternate}
@keyframes thDrift{0%{transform:scale(1)}100%{transform:scale(1.08)}}
body,.si,.fi,.fs,td,th,p{-webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale}
.stitle,.tbtitle,.sbrand-name{color:#f0e8ff;font-weight:750;letter-spacing:-.01em}
.card p,.sc p{color:#b89fd8;line-height:1.75}
.snl{color:#6b5a8a;letter-spacing:.08em;font-weight:600}

/* ══ لوحة التحكم ══ */
.sidebar{background:rgba(16,8,40,0.82) !important;-webkit-backdrop-filter:blur(22px) saturate(150%);backdrop-filter:blur(22px) saturate(150%);border-left:1px solid rgba(255,255,255,.09) !important;box-shadow:-1px 0 40px rgba(0,0,0,.4)}
.sidebar::after{background:linear-gradient(180deg,rgba(184,71,255,0.6),transparent)}
.sbrand{background:rgba(255,255,255,.03);border-bottom:1px solid rgba(255,255,255,.09)}
.sbrand-icon{background:linear-gradient(135deg,#6200ea,#b847ff);color:#ffffff;box-shadow:0 4px 16px rgba(184,71,255,0.3)}
.sbrand-sub{color:#6b5a8a}
.si{color:#b89fd8;font-weight:600}
.si:hover{background:rgba(255,255,255,.06);color:#f0e8ff}
.si.on{background:rgba(184,71,255,0.12);color:#f0e8ff}
.si.on::before{background:#b847ff}
.si.on .si-ic{color:#b847ff}
.topbar{background:rgba(16,8,40,0.72) !important;-webkit-backdrop-filter:blur(22px) saturate(150%);backdrop-filter:blur(22px) saturate(150%);border-bottom:1px solid rgba(255,255,255,.09) !important}
.main{background:transparent !important}
.card,.tw,.swc,.bkc,.sc,.mbox,.pcard,.theme-panel{background:rgba(255,255,255,.05) !important;-webkit-backdrop-filter:blur(16px) saturate(140%) !important;backdrop-filter:blur(16px) saturate(140%) !important;border:1px solid rgba(255,255,255,.09) !important;border-radius:14px !important;box-shadow:0 8px 30px rgba(0,0,0,.4),inset 0 1px 0 rgba(255,255,255,.06) !important}
.sc:hover{background:rgba(255,255,255,.08) !important;border-color:rgba(255,255,255,.2) !important;box-shadow:0 12px 38px rgba(0,0,0,.55),inset 0 1px 0 rgba(255,255,255,.06) !important}
.fi,.fs{background:rgba(255,255,255,.05) !important;border:1px solid rgba(255,255,255,.09) !important;color:#f0e8ff !important;border-radius:10px}
.fi::placeholder{color:#6b5a8a}
.fi:focus,.fs:focus{border-color:#b847ff !important;background:rgba(255,255,255,.08) !important;box-shadow:0 0 0 3px rgba(184,71,255,0.18) !important}
.btn-p{background:linear-gradient(135deg,#6200ea,#b847ff) !important;border:none !important;color:#ffffff !important;font-weight:700;border-radius:10px;box-shadow:0 4px 18px rgba(184,71,255,0.32)}
.btn-p:hover{filter:brightness(1.1);box-shadow:0 6px 24px rgba(184,71,255,0.45)}
.ib{background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.09);color:#b89fd8;border-radius:9px}
.ib:hover{background:rgba(255,255,255,.12);color:#f0e8ff;border-color:rgba(255,255,255,.2)}
.uavt{background:linear-gradient(135deg,#6200ea,#b847ff);color:#ffffff}
.lic-dot{background:#b847ff;box-shadow:0 0 8px rgba(184,71,255,0.7)}
.lic-b{background:rgba(255,255,255,.05);border-color:rgba(255,255,255,.09);color:#b89fd8}
.mhd,.mfooter{background:rgba(255,255,255,.035);border-color:rgba(255,255,255,.09)}
thead tr{background:rgba(255,255,255,.05)}
th{color:#6b5a8a;font-weight:700;letter-spacing:.04em}
td{color:#f0e8ff;border-color:rgba(255,255,255,.06)}
tr:hover td{background:rgba(255,255,255,.045)}
.pw .pb{background:linear-gradient(90deg,#6200ea,#b847ff)}
.mbd,#pm{background:rgba(0,0,0,.8) !important;-webkit-backdrop-filter:blur(14px) !important;backdrop-filter:blur(14px) !important}
.al-s{background:rgba(184,71,255,0.12);border-color:rgba(184,71,255,0.4);color:#b847ff}
@keyframes fu{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}

/* ══ الموقع العام index.php ══ */
.navbar{background:rgba(16,8,40,0.72) !important;-webkit-backdrop-filter:blur(22px) saturate(155%) !important;backdrop-filter:blur(22px) saturate(155%) !important;border-bottom:1px solid rgba(255,255,255,.09) !important}
.nav-logo-text{color:#b847ff !important}
.nav-btn{background:rgba(255,255,255,.07) !important;border:1px solid rgba(255,255,255,.09) !important;color:#b89fd8 !important}
.nav-btn:hover{background:rgba(184,71,255,0.22) !important;border-color:#b847ff !important;color:#f0e8ff !important}
.search-wrap input{background:rgba(255,255,255,.05) !important;border:1px solid rgba(255,255,255,.09) !important;color:#f0e8ff !important}
.search-wrap input::placeholder{color:#6b5a8a}
.search-wrap input:focus{background:rgba(255,255,255,.09) !important;border-color:#b847ff !important}
.cat-navbar{background:rgba(16,8,40,0.72) !important;-webkit-backdrop-filter:blur(20px) saturate(150%) !important;backdrop-filter:blur(20px) saturate(150%) !important;border-bottom:1px solid rgba(255,255,255,.09) !important}
.cat-nav-btn{background:rgba(255,255,255,.055) !important;border:1px solid rgba(255,255,255,.09) !important;color:#b89fd8 !important;font-weight:650 !important}
.cat-nav-btn:hover{background:rgba(255,255,255,.11) !important;border-color:rgba(255,255,255,.2) !important;color:#f0e8ff !important}
.cat-nav-btn.active{background:linear-gradient(135deg,#6200ea,#b847ff) !important;border-color:transparent !important;color:#ffffff !important;box-shadow:0 4px 16px rgba(184,71,255,0.38) !important}
.fp-panel,.np-panel,.m3u-panel,.ep-panel,.shs-catmenu-panel{background:rgba(16,8,40,0.82) !important;-webkit-backdrop-filter:blur(28px) saturate(155%) !important;backdrop-filter:blur(28px) saturate(155%) !important;border-left:1px solid rgba(255,255,255,.09) !important;box-shadow:0 0 50px rgba(0,0,0,.55) !important}
.panel-overlay,.shs-catmenu-overlay,.tmdb-modal-overlay{background:rgba(0,0,0,.65) !important;-webkit-backdrop-filter:blur(8px) !important;backdrop-filter:blur(8px) !important}
.netflix-card,.nx-card,.ch-card,.sr-card{background:rgba(255,255,255,.05) !important;border:1px solid rgba(255,255,255,.09) !important;border-radius:12px !important;box-shadow:0 6px 24px rgba(0,0,0,.4) !important}
.netflix-card:hover,.nx-card:hover{border-color:rgba(184,71,255,0.5) !important;box-shadow:0 12px 36px rgba(0,0,0,.55),0 0 0 1px rgba(184,71,255,0.25) !important}
.shs-catview-banner{background:rgba(255,255,255,.05) !important;border:1px solid rgba(255,255,255,.09) !important;-webkit-backdrop-filter:blur(16px) saturate(140%);backdrop-filter:blur(16px) saturate(140%)}
.shs-catmenu-item:hover{background:rgba(255,255,255,.07)}
.shs-empty-ico{background:radial-gradient(120% 120% at 30% 20%,rgba(184,71,255,0.16),rgba(255,255,255,.03));color:#b847ff}
.shs-spinner::before{border-top-color:#b847ff;border-right-color:#b847ff}
.hero-btn-play,.btn-play{background:linear-gradient(135deg,#6200ea,#b847ff) !important;color:#ffffff !important}
/* ══ إصلاح أيقونات شريط الأقسام ══ */
.cat-nav-btn i,.cat-nav-btn svg,.cat-nav-btn .lcn,.cat-nav-btn .lcn svg{
 color:#b89fd8 !important;stroke:currentColor !important;fill:none !important;
 width:1em;height:1em;flex-shrink:0;opacity:.9;transition:color .2s}
.cat-nav-btn:hover i,.cat-nav-btn:hover svg,.cat-nav-btn:hover .lcn,.cat-nav-btn:hover .lcn svg{
 color:#f0e8ff !important;opacity:1}
.cat-nav-btn.active i,.cat-nav-btn.active svg,.cat-nav-btn.active .lcn,.cat-nav-btn.active .lcn svg{
 color:#ffffff !important;opacity:1}
.cat-nav-btn .lcn{display:inline-flex;align-items:center;justify-content:center;line-height:0}
/* أيقونات القائمة الجانبية للأقسام */
.shs-catmenu-item i,.shs-catmenu-item svg,.shs-catmenu-item .lcn svg{
 color:#b847ff !important;stroke:currentColor !important;fill:none !important}
/* أيقونات أزرار التنقّل العلوية */
.nav-btn i,.nav-btn svg,.nav-btn .lcn svg{stroke:currentColor !important;fill:none !important}`
    },
    jellyflix: {
        name: 'JellyFlix',
        css: `:root{--red:#e50914;--redg:rgba(229,9,20,0.4);--gold:#b00610;--s0:#0b0b0b;--s1:#141414;--s2:#1c1c1c;--s3:#242424;--s4:#2e2e2e;--br:rgba(255,255,255,.09);--brh:rgba(255,255,255,.2);--t1:#ffffff;--t2:#b3b3b3;--t3:#6f6f6f;--r1:6px}

/* خلفية */
html{background:#0b0b0b}
body{background:#0b0b0b !important;color:#ffffff;position:relative;min-height:100vh}
body::before{content:"";position:fixed;inset:0;z-index:-2;pointer-events:none;background:
  radial-gradient(60% 50% at 15% 8%,rgba(229,9,20,0.12),transparent 62%),
  radial-gradient(60% 50% at 88% 15%,rgba(255,43,54,0.08),transparent 62%);
  animation:thDrift 30s ease-in-out infinite alternate}
@keyframes thDrift{0%{transform:scale(1)}100%{transform:scale(1.08)}}
body,.si,.fi,.fs,td,th,p{-webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale}
.stitle,.tbtitle,.sbrand-name{color:#ffffff;font-weight:750;letter-spacing:-.01em}
.card p,.sc p{color:#b3b3b3;line-height:1.75}
.snl{color:#6f6f6f;letter-spacing:.08em;font-weight:600}

/* ══ لوحة التحكم ══ */
.sidebar{background:rgba(20,20,20,0.82) !important;-webkit-backdrop-filter:blur(22px) saturate(150%);backdrop-filter:blur(22px) saturate(150%);border-left:1px solid rgba(255,255,255,.09) !important;box-shadow:-1px 0 40px rgba(0,0,0,.4)}
.sidebar::after{background:linear-gradient(180deg,rgba(229,9,20,0.6),transparent)}
.sbrand{background:rgba(255,255,255,.03);border-bottom:1px solid rgba(255,255,255,.09)}
.sbrand-icon{background:linear-gradient(135deg,#b00610,#e50914);color:#ffffff;box-shadow:0 4px 16px rgba(229,9,20,0.3)}
.sbrand-sub{color:#6f6f6f}
.si{color:#b3b3b3;font-weight:600}
.si:hover{background:rgba(255,255,255,.06);color:#ffffff}
.si.on{background:rgba(229,9,20,0.12);color:#ffffff}
.si.on::before{background:#e50914}
.si.on .si-ic{color:#e50914}
.topbar{background:rgba(20,20,20,0.72) !important;-webkit-backdrop-filter:blur(22px) saturate(150%);backdrop-filter:blur(22px) saturate(150%);border-bottom:1px solid rgba(255,255,255,.09) !important}
.main{background:transparent !important}
.card,.tw,.swc,.bkc,.sc,.mbox,.pcard,.theme-panel{background:rgba(255,255,255,.05) !important;-webkit-backdrop-filter:blur(16px) saturate(140%) !important;backdrop-filter:blur(16px) saturate(140%) !important;border:1px solid rgba(255,255,255,.09) !important;border-radius:8px !important;box-shadow:0 8px 30px rgba(0,0,0,.4),inset 0 1px 0 rgba(255,255,255,.06) !important}
.sc:hover{background:rgba(255,255,255,.08) !important;border-color:rgba(255,255,255,.2) !important;box-shadow:0 12px 38px rgba(0,0,0,.55),inset 0 1px 0 rgba(255,255,255,.06) !important}
.fi,.fs{background:rgba(255,255,255,.05) !important;border:1px solid rgba(255,255,255,.09) !important;color:#ffffff !important;border-radius:4px}
.fi::placeholder{color:#6f6f6f}
.fi:focus,.fs:focus{border-color:#e50914 !important;background:rgba(255,255,255,.08) !important;box-shadow:0 0 0 3px rgba(229,9,20,0.18) !important}
.btn-p{background:linear-gradient(135deg,#b00610,#e50914) !important;border:none !important;color:#ffffff !important;font-weight:700;border-radius:4px;box-shadow:0 4px 18px rgba(229,9,20,0.32)}
.btn-p:hover{filter:brightness(1.1);box-shadow:0 6px 24px rgba(229,9,20,0.45)}
.ib{background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.09);color:#b3b3b3;border-radius:3px}
.ib:hover{background:rgba(255,255,255,.12);color:#ffffff;border-color:rgba(255,255,255,.2)}
.uavt{background:linear-gradient(135deg,#b00610,#e50914);color:#ffffff}
.lic-dot{background:#00b020;box-shadow:0 0 8px rgba(0,176,32,0.7)}
.lic-b{background:rgba(255,255,255,.05);border-color:rgba(255,255,255,.09);color:#b3b3b3}
.mhd,.mfooter{background:rgba(255,255,255,.035);border-color:rgba(255,255,255,.09)}
thead tr{background:rgba(255,255,255,.05)}
th{color:#6f6f6f;font-weight:700;letter-spacing:.04em}
td{color:#ffffff;border-color:rgba(255,255,255,.06)}
tr:hover td{background:rgba(255,255,255,.045)}
.pw .pb{background:linear-gradient(90deg,#b00610,#e50914)}
.mbd,#pm{background:rgba(0,0,0,.8) !important;-webkit-backdrop-filter:blur(14px) !important;backdrop-filter:blur(14px) !important}
.al-s{background:rgba(0,176,32,0.12);border-color:rgba(0,176,32,0.4);color:#00b020}
@keyframes fu{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}

/* ══ الموقع العام index.php ══ */
.navbar{background:rgba(20,20,20,0.72) !important;-webkit-backdrop-filter:blur(22px) saturate(155%) !important;backdrop-filter:blur(22px) saturate(155%) !important;border-bottom:1px solid rgba(255,255,255,.09) !important}
.nav-logo-text{color:#e50914 !important}
.nav-btn{background:rgba(255,255,255,.07) !important;border:1px solid rgba(255,255,255,.09) !important;color:#b3b3b3 !important}
.nav-btn:hover{background:rgba(229,9,20,0.22) !important;border-color:#e50914 !important;color:#ffffff !important}
.search-wrap input{background:rgba(255,255,255,.05) !important;border:1px solid rgba(255,255,255,.09) !important;color:#ffffff !important}
.search-wrap input::placeholder{color:#6f6f6f}
.search-wrap input:focus{background:rgba(255,255,255,.09) !important;border-color:#e50914 !important}
.cat-navbar{background:rgba(20,20,20,0.72) !important;-webkit-backdrop-filter:blur(20px) saturate(150%) !important;backdrop-filter:blur(20px) saturate(150%) !important;border-bottom:1px solid rgba(255,255,255,.09) !important}
.cat-nav-btn{background:rgba(255,255,255,.055) !important;border:1px solid rgba(255,255,255,.09) !important;color:#b3b3b3 !important;font-weight:650 !important}
.cat-nav-btn:hover{background:rgba(255,255,255,.11) !important;border-color:rgba(255,255,255,.2) !important;color:#ffffff !important}
.cat-nav-btn.active{background:linear-gradient(135deg,#b00610,#e50914) !important;border-color:transparent !important;color:#ffffff !important;box-shadow:0 4px 16px rgba(229,9,20,0.38) !important}
.fp-panel,.np-panel,.m3u-panel,.ep-panel,.shs-catmenu-panel{background:rgba(20,20,20,0.82) !important;-webkit-backdrop-filter:blur(28px) saturate(155%) !important;backdrop-filter:blur(28px) saturate(155%) !important;border-left:1px solid rgba(255,255,255,.09) !important;box-shadow:0 0 50px rgba(0,0,0,.55) !important}
.panel-overlay,.shs-catmenu-overlay,.tmdb-modal-overlay{background:rgba(0,0,0,.65) !important;-webkit-backdrop-filter:blur(8px) !important;backdrop-filter:blur(8px) !important}
.netflix-card,.nx-card,.ch-card,.sr-card{background:rgba(255,255,255,.05) !important;border:1px solid rgba(255,255,255,.09) !important;border-radius:6px !important;box-shadow:0 6px 24px rgba(0,0,0,.4) !important}
.netflix-card:hover,.nx-card:hover{border-color:rgba(229,9,20,0.5) !important;box-shadow:0 12px 36px rgba(0,0,0,.55),0 0 0 1px rgba(229,9,20,0.25) !important}
.shs-catview-banner{background:rgba(255,255,255,.05) !important;border:1px solid rgba(255,255,255,.09) !important;-webkit-backdrop-filter:blur(16px) saturate(140%);backdrop-filter:blur(16px) saturate(140%)}
.shs-catmenu-item:hover{background:rgba(255,255,255,.07)}
.shs-empty-ico{background:radial-gradient(120% 120% at 30% 20%,rgba(229,9,20,0.16),rgba(255,255,255,.03));color:#e50914}
.shs-spinner::before{border-top-color:#e50914;border-right-color:#e50914}
.hero-btn-play,.btn-play{background:linear-gradient(135deg,#b00610,#e50914) !important;color:#ffffff !important}
/* ══ إصلاح أيقونات شريط الأقسام ══ */
.cat-nav-btn i,.cat-nav-btn svg,.cat-nav-btn .lcn,.cat-nav-btn .lcn svg{
 color:#b3b3b3 !important;stroke:currentColor !important;fill:none !important;
 width:1em;height:1em;flex-shrink:0;opacity:.9;transition:color .2s}
.cat-nav-btn:hover i,.cat-nav-btn:hover svg,.cat-nav-btn:hover .lcn,.cat-nav-btn:hover .lcn svg{
 color:#ffffff !important;opacity:1}
.cat-nav-btn.active i,.cat-nav-btn.active svg,.cat-nav-btn.active .lcn,.cat-nav-btn.active .lcn svg{
 color:#ffffff !important;opacity:1}
.cat-nav-btn .lcn{display:inline-flex;align-items:center;justify-content:center;line-height:0}
/* أيقونات القائمة الجانبية للأقسام */
.shs-catmenu-item i,.shs-catmenu-item svg,.shs-catmenu-item .lcn svg{
 color:#e50914 !important;stroke:currentColor !important;fill:none !important}
/* أيقونات أزرار التنقّل العلوية */
.nav-btn i,.nav-btn svg,.nav-btn .lcn svg{stroke:currentColor !important;fill:none !important}`
    },
    dark: {
        name: 'Dark Enhanced',
        css: `:root{--red:#58a6ff;--redg:rgba(88,166,255,0.3);--gold:#1f6feb;--s0:#0d1117;--s1:#161b22;--s2:#1c2128;--s3:#22272e;--s4:#2d333b;--br:rgba(255,255,255,.09);--brh:rgba(255,255,255,.2);--t1:#e6edf3;--t2:#9aa5b1;--t3:#6e7681;--r1:10px}

/* خلفية */
html{background:#0d1117}
body{background:#0d1117 !important;color:#e6edf3;position:relative;min-height:100vh}
body::before{content:"";position:fixed;inset:0;z-index:-2;pointer-events:none;background:
  radial-gradient(60% 50% at 20% 10%,rgba(88,166,255,0.1),transparent 62%),
  radial-gradient(60% 50% at 85% 85%,rgba(31,111,235,0.08),transparent 62%);
  animation:thDrift 30s ease-in-out infinite alternate}
@keyframes thDrift{0%{transform:scale(1)}100%{transform:scale(1.08)}}
body,.si,.fi,.fs,td,th,p{-webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale}
.stitle,.tbtitle,.sbrand-name{color:#e6edf3;font-weight:750;letter-spacing:-.01em}
.card p,.sc p{color:#9aa5b1;line-height:1.75}
.snl{color:#6e7681;letter-spacing:.08em;font-weight:600}

/* ══ لوحة التحكم ══ */
.sidebar{background:rgba(22,27,34,0.82) !important;-webkit-backdrop-filter:blur(22px) saturate(150%);backdrop-filter:blur(22px) saturate(150%);border-left:1px solid rgba(255,255,255,.09) !important;box-shadow:-1px 0 40px rgba(0,0,0,.4)}
.sidebar::after{background:linear-gradient(180deg,rgba(88,166,255,0.6),transparent)}
.sbrand{background:rgba(255,255,255,.03);border-bottom:1px solid rgba(255,255,255,.09)}
.sbrand-icon{background:linear-gradient(135deg,#1f6feb,#58a6ff);color:#ffffff;box-shadow:0 4px 16px rgba(88,166,255,0.3)}
.sbrand-sub{color:#6e7681}
.si{color:#9aa5b1;font-weight:600}
.si:hover{background:rgba(255,255,255,.06);color:#e6edf3}
.si.on{background:rgba(88,166,255,0.12);color:#e6edf3}
.si.on::before{background:#58a6ff}
.si.on .si-ic{color:#58a6ff}
.topbar{background:rgba(22,27,34,0.72) !important;-webkit-backdrop-filter:blur(22px) saturate(150%);backdrop-filter:blur(22px) saturate(150%);border-bottom:1px solid rgba(255,255,255,.09) !important}
.main{background:transparent !important}
.card,.tw,.swc,.bkc,.sc,.mbox,.pcard,.theme-panel{background:rgba(255,255,255,.05) !important;-webkit-backdrop-filter:blur(16px) saturate(140%) !important;backdrop-filter:blur(16px) saturate(140%) !important;border:1px solid rgba(255,255,255,.09) !important;border-radius:12px !important;box-shadow:0 8px 30px rgba(0,0,0,.4),inset 0 1px 0 rgba(255,255,255,.06) !important}
.sc:hover{background:rgba(255,255,255,.08) !important;border-color:rgba(255,255,255,.2) !important;box-shadow:0 12px 38px rgba(0,0,0,.55),inset 0 1px 0 rgba(255,255,255,.06) !important}
.fi,.fs{background:rgba(255,255,255,.05) !important;border:1px solid rgba(255,255,255,.09) !important;color:#e6edf3 !important;border-radius:8px}
.fi::placeholder{color:#6e7681}
.fi:focus,.fs:focus{border-color:#58a6ff !important;background:rgba(255,255,255,.08) !important;box-shadow:0 0 0 3px rgba(88,166,255,0.18) !important}
.btn-p{background:linear-gradient(135deg,#1f6feb,#58a6ff) !important;border:none !important;color:#ffffff !important;font-weight:700;border-radius:8px;box-shadow:0 4px 18px rgba(88,166,255,0.32)}
.btn-p:hover{filter:brightness(1.1);box-shadow:0 6px 24px rgba(88,166,255,0.45)}
.ib{background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.09);color:#9aa5b1;border-radius:7px}
.ib:hover{background:rgba(255,255,255,.12);color:#e6edf3;border-color:rgba(255,255,255,.2)}
.uavt{background:linear-gradient(135deg,#1f6feb,#58a6ff);color:#ffffff}
.lic-dot{background:#3fb950;box-shadow:0 0 8px rgba(63,185,80,0.7)}
.lic-b{background:rgba(255,255,255,.05);border-color:rgba(255,255,255,.09);color:#9aa5b1}
.mhd,.mfooter{background:rgba(255,255,255,.035);border-color:rgba(255,255,255,.09)}
thead tr{background:rgba(255,255,255,.05)}
th{color:#6e7681;font-weight:700;letter-spacing:.04em}
td{color:#e6edf3;border-color:rgba(255,255,255,.06)}
tr:hover td{background:rgba(255,255,255,.045)}
.pw .pb{background:linear-gradient(90deg,#1f6feb,#58a6ff)}
.mbd,#pm{background:rgba(0,0,0,.8) !important;-webkit-backdrop-filter:blur(14px) !important;backdrop-filter:blur(14px) !important}
.al-s{background:rgba(63,185,80,0.12);border-color:rgba(63,185,80,0.4);color:#3fb950}
@keyframes fu{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}

/* ══ الموقع العام index.php ══ */
.navbar{background:rgba(22,27,34,0.72) !important;-webkit-backdrop-filter:blur(22px) saturate(155%) !important;backdrop-filter:blur(22px) saturate(155%) !important;border-bottom:1px solid rgba(255,255,255,.09) !important}
.nav-logo-text{color:#58a6ff !important}
.nav-btn{background:rgba(255,255,255,.07) !important;border:1px solid rgba(255,255,255,.09) !important;color:#9aa5b1 !important}
.nav-btn:hover{background:rgba(88,166,255,0.22) !important;border-color:#58a6ff !important;color:#e6edf3 !important}
.search-wrap input{background:rgba(255,255,255,.05) !important;border:1px solid rgba(255,255,255,.09) !important;color:#e6edf3 !important}
.search-wrap input::placeholder{color:#6e7681}
.search-wrap input:focus{background:rgba(255,255,255,.09) !important;border-color:#58a6ff !important}
.cat-navbar{background:rgba(22,27,34,0.72) !important;-webkit-backdrop-filter:blur(20px) saturate(150%) !important;backdrop-filter:blur(20px) saturate(150%) !important;border-bottom:1px solid rgba(255,255,255,.09) !important}
.cat-nav-btn{background:rgba(255,255,255,.055) !important;border:1px solid rgba(255,255,255,.09) !important;color:#9aa5b1 !important;font-weight:650 !important}
.cat-nav-btn:hover{background:rgba(255,255,255,.11) !important;border-color:rgba(255,255,255,.2) !important;color:#e6edf3 !important}
.cat-nav-btn.active{background:linear-gradient(135deg,#1f6feb,#58a6ff) !important;border-color:transparent !important;color:#ffffff !important;box-shadow:0 4px 16px rgba(88,166,255,0.38) !important}
.fp-panel,.np-panel,.m3u-panel,.ep-panel,.shs-catmenu-panel{background:rgba(22,27,34,0.82) !important;-webkit-backdrop-filter:blur(28px) saturate(155%) !important;backdrop-filter:blur(28px) saturate(155%) !important;border-left:1px solid rgba(255,255,255,.09) !important;box-shadow:0 0 50px rgba(0,0,0,.55) !important}
.panel-overlay,.shs-catmenu-overlay,.tmdb-modal-overlay{background:rgba(0,0,0,.65) !important;-webkit-backdrop-filter:blur(8px) !important;backdrop-filter:blur(8px) !important}
.netflix-card,.nx-card,.ch-card,.sr-card{background:rgba(255,255,255,.05) !important;border:1px solid rgba(255,255,255,.09) !important;border-radius:10px !important;box-shadow:0 6px 24px rgba(0,0,0,.4) !important}
.netflix-card:hover,.nx-card:hover{border-color:rgba(88,166,255,0.5) !important;box-shadow:0 12px 36px rgba(0,0,0,.55),0 0 0 1px rgba(88,166,255,0.25) !important}
.shs-catview-banner{background:rgba(255,255,255,.05) !important;border:1px solid rgba(255,255,255,.09) !important;-webkit-backdrop-filter:blur(16px) saturate(140%);backdrop-filter:blur(16px) saturate(140%)}
.shs-catmenu-item:hover{background:rgba(255,255,255,.07)}
.shs-empty-ico{background:radial-gradient(120% 120% at 30% 20%,rgba(88,166,255,0.16),rgba(255,255,255,.03));color:#58a6ff}
.shs-spinner::before{border-top-color:#58a6ff;border-right-color:#58a6ff}
.hero-btn-play,.btn-play{background:linear-gradient(135deg,#1f6feb,#58a6ff) !important;color:#ffffff !important}
/* ══ إصلاح أيقونات شريط الأقسام ══ */
.cat-nav-btn i,.cat-nav-btn svg,.cat-nav-btn .lcn,.cat-nav-btn .lcn svg{
 color:#9aa5b1 !important;stroke:currentColor !important;fill:none !important;
 width:1em;height:1em;flex-shrink:0;opacity:.9;transition:color .2s}
.cat-nav-btn:hover i,.cat-nav-btn:hover svg,.cat-nav-btn:hover .lcn,.cat-nav-btn:hover .lcn svg{
 color:#e6edf3 !important;opacity:1}
.cat-nav-btn.active i,.cat-nav-btn.active svg,.cat-nav-btn.active .lcn,.cat-nav-btn.active .lcn svg{
 color:#ffffff !important;opacity:1}
.cat-nav-btn .lcn{display:inline-flex;align-items:center;justify-content:center;line-height:0}
/* أيقونات القائمة الجانبية للأقسام */
.shs-catmenu-item i,.shs-catmenu-item svg,.shs-catmenu-item .lcn svg{
 color:#58a6ff !important;stroke:currentColor !important;fill:none !important}
/* أيقونات أزرار التنقّل العلوية */
.nav-btn i,.nav-btn svg,.nav-btn .lcn svg{stroke:currentColor !important;fill:none !important}`
    },
    neon: {
        name: 'Neon Cyberpunk',
        css: `:root{--red:#00ff88;--redg:rgba(0,255,136,0.5);--gold:#00ccff;--s0:#03000a;--s1:#0a0018;--s2:#100024;--s3:#160030;--s4:#1e003e;--br:rgba(255,255,255,.09);--brh:rgba(255,255,255,.2);--t1:#e8fff0;--t2:#8affb0;--t3:#4a8a68;--r1:8px}

/* خلفية */
html{background:#03000a}
body{background:#03000a !important;color:#e8fff0;position:relative;min-height:100vh}
body::before{content:"";position:fixed;inset:0;z-index:-2;pointer-events:none;background:
  radial-gradient(60% 50% at 15% 12%,rgba(0,255,136,0.14),transparent 62%),
  radial-gradient(60% 50% at 85% 18%,rgba(255,0,255,0.12),transparent 62%),
  radial-gradient(60% 50% at 75% 88%,rgba(0,204,255,0.1),transparent 62%);
  animation:thDrift 30s ease-in-out infinite alternate}
@keyframes thDrift{0%{transform:scale(1)}100%{transform:scale(1.08)}}
body,.si,.fi,.fs,td,th,p{-webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale}
.stitle,.tbtitle,.sbrand-name{color:#e8fff0;font-weight:750;letter-spacing:-.01em}
.card p,.sc p{color:#8affb0;line-height:1.75}
.snl{color:#4a8a68;letter-spacing:.08em;font-weight:600}

/* ══ لوحة التحكم ══ */
.sidebar{background:rgba(10,0,24,0.82) !important;-webkit-backdrop-filter:blur(22px) saturate(150%);backdrop-filter:blur(22px) saturate(150%);border-left:1px solid rgba(255,255,255,.09) !important;box-shadow:-1px 0 40px rgba(0,0,0,.4)}
.sidebar::after{background:linear-gradient(180deg,rgba(0,255,136,0.6),transparent)}
.sbrand{background:rgba(255,255,255,.03);border-bottom:1px solid rgba(255,255,255,.09)}
.sbrand-icon{background:linear-gradient(135deg,#00ccff,#00ff88);color:#ffffff;box-shadow:0 4px 16px rgba(0,255,136,0.3)}
.sbrand-sub{color:#4a8a68}
.si{color:#8affb0;font-weight:600}
.si:hover{background:rgba(255,255,255,.06);color:#e8fff0}
.si.on{background:rgba(0,255,136,0.12);color:#e8fff0}
.si.on::before{background:#00ff88}
.si.on .si-ic{color:#00ff88}
.topbar{background:rgba(10,0,24,0.72) !important;-webkit-backdrop-filter:blur(22px) saturate(150%);backdrop-filter:blur(22px) saturate(150%);border-bottom:1px solid rgba(255,255,255,.09) !important}
.main{background:transparent !important}
.card,.tw,.swc,.bkc,.sc,.mbox,.pcard,.theme-panel{background:rgba(255,255,255,.05) !important;-webkit-backdrop-filter:blur(16px) saturate(140%) !important;backdrop-filter:blur(16px) saturate(140%) !important;border:1px solid rgba(255,255,255,.09) !important;border-radius:10px !important;box-shadow:0 8px 30px rgba(0,0,0,.4),inset 0 1px 0 rgba(255,255,255,.06) !important}
.sc:hover{background:rgba(255,255,255,.08) !important;border-color:rgba(255,255,255,.2) !important;box-shadow:0 12px 38px rgba(0,0,0,.55),inset 0 1px 0 rgba(255,255,255,.06) !important}
.fi,.fs{background:rgba(255,255,255,.05) !important;border:1px solid rgba(255,255,255,.09) !important;color:#e8fff0 !important;border-radius:6px}
.fi::placeholder{color:#4a8a68}
.fi:focus,.fs:focus{border-color:#00ff88 !important;background:rgba(255,255,255,.08) !important;box-shadow:0 0 0 3px rgba(0,255,136,0.18) !important}
.btn-p{background:linear-gradient(135deg,#00ccff,#00ff88) !important;border:none !important;color:#ffffff !important;font-weight:700;border-radius:6px;box-shadow:0 4px 18px rgba(0,255,136,0.32)}
.btn-p:hover{filter:brightness(1.1);box-shadow:0 6px 24px rgba(0,255,136,0.45)}
.ib{background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.09);color:#8affb0;border-radius:5px}
.ib:hover{background:rgba(255,255,255,.12);color:#e8fff0;border-color:rgba(255,255,255,.2)}
.uavt{background:linear-gradient(135deg,#00ccff,#00ff88);color:#ffffff}
.lic-dot{background:#00ff88;box-shadow:0 0 8px rgba(0,255,136,0.7)}
.lic-b{background:rgba(255,255,255,.05);border-color:rgba(255,255,255,.09);color:#8affb0}
.mhd,.mfooter{background:rgba(255,255,255,.035);border-color:rgba(255,255,255,.09)}
thead tr{background:rgba(255,255,255,.05)}
th{color:#4a8a68;font-weight:700;letter-spacing:.04em}
td{color:#e8fff0;border-color:rgba(255,255,255,.06)}
tr:hover td{background:rgba(255,255,255,.045)}
.pw .pb{background:linear-gradient(90deg,#00ccff,#00ff88)}
.mbd,#pm{background:rgba(0,0,0,.8) !important;-webkit-backdrop-filter:blur(14px) !important;backdrop-filter:blur(14px) !important}
.al-s{background:rgba(0,255,136,0.12);border-color:rgba(0,255,136,0.4);color:#00ff88}
@keyframes fu{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}

/* ══ الموقع العام index.php ══ */
.navbar{background:rgba(10,0,24,0.72) !important;-webkit-backdrop-filter:blur(22px) saturate(155%) !important;backdrop-filter:blur(22px) saturate(155%) !important;border-bottom:1px solid rgba(255,255,255,.09) !important}
.nav-logo-text{color:#00ff88 !important}
.nav-btn{background:rgba(255,255,255,.07) !important;border:1px solid rgba(255,255,255,.09) !important;color:#8affb0 !important}
.nav-btn:hover{background:rgba(0,255,136,0.22) !important;border-color:#00ff88 !important;color:#e8fff0 !important}
.search-wrap input{background:rgba(255,255,255,.05) !important;border:1px solid rgba(255,255,255,.09) !important;color:#e8fff0 !important}
.search-wrap input::placeholder{color:#4a8a68}
.search-wrap input:focus{background:rgba(255,255,255,.09) !important;border-color:#00ff88 !important}
.cat-navbar{background:rgba(10,0,24,0.72) !important;-webkit-backdrop-filter:blur(20px) saturate(150%) !important;backdrop-filter:blur(20px) saturate(150%) !important;border-bottom:1px solid rgba(255,255,255,.09) !important}
.cat-nav-btn{background:rgba(255,255,255,.055) !important;border:1px solid rgba(255,255,255,.09) !important;color:#8affb0 !important;font-weight:650 !important}
.cat-nav-btn:hover{background:rgba(255,255,255,.11) !important;border-color:rgba(255,255,255,.2) !important;color:#e8fff0 !important}
.cat-nav-btn.active{background:linear-gradient(135deg,#00ccff,#00ff88) !important;border-color:transparent !important;color:#ffffff !important;box-shadow:0 4px 16px rgba(0,255,136,0.38) !important}
.fp-panel,.np-panel,.m3u-panel,.ep-panel,.shs-catmenu-panel{background:rgba(10,0,24,0.82) !important;-webkit-backdrop-filter:blur(28px) saturate(155%) !important;backdrop-filter:blur(28px) saturate(155%) !important;border-left:1px solid rgba(255,255,255,.09) !important;box-shadow:0 0 50px rgba(0,0,0,.55) !important}
.panel-overlay,.shs-catmenu-overlay,.tmdb-modal-overlay{background:rgba(0,0,0,.65) !important;-webkit-backdrop-filter:blur(8px) !important;backdrop-filter:blur(8px) !important}
.netflix-card,.nx-card,.ch-card,.sr-card{background:rgba(255,255,255,.05) !important;border:1px solid rgba(255,255,255,.09) !important;border-radius:8px !important;box-shadow:0 6px 24px rgba(0,0,0,.4) !important}
.netflix-card:hover,.nx-card:hover{border-color:rgba(0,255,136,0.5) !important;box-shadow:0 12px 36px rgba(0,0,0,.55),0 0 0 1px rgba(0,255,136,0.25) !important}
.shs-catview-banner{background:rgba(255,255,255,.05) !important;border:1px solid rgba(255,255,255,.09) !important;-webkit-backdrop-filter:blur(16px) saturate(140%);backdrop-filter:blur(16px) saturate(140%)}
.shs-catmenu-item:hover{background:rgba(255,255,255,.07)}
.shs-empty-ico{background:radial-gradient(120% 120% at 30% 20%,rgba(0,255,136,0.16),rgba(255,255,255,.03));color:#00ff88}
.shs-spinner::before{border-top-color:#00ff88;border-right-color:#00ff88}
.hero-btn-play,.btn-play{background:linear-gradient(135deg,#00ccff,#00ff88) !important;color:#ffffff !important}
/* ══ إصلاح أيقونات شريط الأقسام ══ */
.cat-nav-btn i,.cat-nav-btn svg,.cat-nav-btn .lcn,.cat-nav-btn .lcn svg{
 color:#8affb0 !important;stroke:currentColor !important;fill:none !important;
 width:1em;height:1em;flex-shrink:0;opacity:.9;transition:color .2s}
.cat-nav-btn:hover i,.cat-nav-btn:hover svg,.cat-nav-btn:hover .lcn,.cat-nav-btn:hover .lcn svg{
 color:#e8fff0 !important;opacity:1}
.cat-nav-btn.active i,.cat-nav-btn.active svg,.cat-nav-btn.active .lcn,.cat-nav-btn.active .lcn svg{
 color:#ffffff !important;opacity:1}
.cat-nav-btn .lcn{display:inline-flex;align-items:center;justify-content:center;line-height:0}
/* أيقونات القائمة الجانبية للأقسام */
.shs-catmenu-item i,.shs-catmenu-item svg,.shs-catmenu-item .lcn svg{
 color:#00ff88 !important;stroke:currentColor !important;fill:none !important}
/* أيقونات أزرار التنقّل العلوية */
.nav-btn i,.nav-btn svg,.nav-btn .lcn svg{stroke:currentColor !important;fill:none !important}`
    },
    minimal: {
        name: 'Minimal Clean',
        css: `:root{--red:#2563eb;--redg:rgba(37,99,235,0.22);--gold:#1d4ed8;--s0:#f8fafc;--s1:#ffffff;--s2:#f1f5f9;--s3:#e2e8f0;--s4:#cbd5e1;--br:rgba(0,0,0,.1);--brh:rgba(0,0,0,.2);--t1:#0f172a;--t2:#475569;--t3:#94a3b8;--r1:10px;--sw:250px}

/* خلفية */
html{background:#f8fafc}
body{background:#f8fafc !important;color:#0f172a;position:relative;min-height:100vh}
body::before{content:"";position:fixed;inset:0;z-index:-2;pointer-events:none;background:
  radial-gradient(60% 50% at 20% 10%,rgba(37,99,235,0.05),transparent 62%),
  radial-gradient(60% 50% at 85% 80%,rgba(139,92,246,0.04),transparent 62%);
  animation:thDrift 30s ease-in-out infinite alternate}
@keyframes thDrift{0%{transform:scale(1)}100%{transform:scale(1.08)}}
body,.si,.fi,.fs,td,th,p{-webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale}
.stitle,.tbtitle,.sbrand-name{color:#0f172a;font-weight:750;letter-spacing:-.01em}
.card p,.sc p{color:#475569;line-height:1.75}
.snl{color:#94a3b8;letter-spacing:.08em;font-weight:600}

/* ══ لوحة التحكم ══ */
.sidebar{background:rgba(255,255,255,.85) !important;-webkit-backdrop-filter:blur(22px) saturate(150%);backdrop-filter:blur(22px) saturate(150%);border-left:1px solid rgba(0,0,0,.1) !important;box-shadow:-1px 0 40px rgba(15,23,42,.1)}
.sidebar::after{background:linear-gradient(180deg,rgba(37,99,235,0.6),transparent)}
.sbrand{background:rgba(0,0,0,.03);border-bottom:1px solid rgba(0,0,0,.1)}
.sbrand-icon{background:linear-gradient(135deg,#1d4ed8,#2563eb);color:#ffffff;box-shadow:0 4px 16px rgba(37,99,235,0.3)}
.sbrand-sub{color:#94a3b8}
.si{color:#475569;font-weight:600}
.si:hover{background:rgba(0,0,0,.06);color:#0f172a}
.si.on{background:rgba(37,99,235,0.12);color:#0f172a}
.si.on::before{background:#2563eb}
.si.on .si-ic{color:#2563eb}
.topbar{background:rgba(255,255,255,.72) !important;-webkit-backdrop-filter:blur(22px) saturate(150%);backdrop-filter:blur(22px) saturate(150%);border-bottom:1px solid rgba(0,0,0,.1) !important}
.main{background:transparent !important}
.card,.tw,.swc,.bkc,.sc,.mbox,.pcard,.theme-panel{background:rgba(255,255,255,.75) !important;-webkit-backdrop-filter:blur(16px) saturate(140%) !important;backdrop-filter:blur(16px) saturate(140%) !important;border:1px solid rgba(0,0,0,.1) !important;border-radius:12px !important;box-shadow:0 8px 30px rgba(15,23,42,.1),inset 0 1px 0 rgba(255,255,255,.9) !important}
.sc:hover{background:rgba(255,255,255,.9) !important;border-color:rgba(0,0,0,.2) !important;box-shadow:0 12px 38px rgba(15,23,42,.16),inset 0 1px 0 rgba(255,255,255,.9) !important}
.fi,.fs{background:rgba(0,0,0,.03) !important;border:1px solid rgba(0,0,0,.1) !important;color:#0f172a !important;border-radius:8px}
.fi::placeholder{color:#94a3b8}
.fi:focus,.fs:focus{border-color:#2563eb !important;background:rgba(0,0,0,.05) !important;box-shadow:0 0 0 3px rgba(37,99,235,0.18) !important}
.btn-p{background:linear-gradient(135deg,#1d4ed8,#2563eb) !important;border:none !important;color:#ffffff !important;font-weight:700;border-radius:8px;box-shadow:0 4px 18px rgba(37,99,235,0.32)}
.btn-p:hover{filter:brightness(1.1);box-shadow:0 6px 24px rgba(37,99,235,0.45)}
.ib{background:rgba(0,0,0,.06);border:1px solid rgba(0,0,0,.1);color:#475569;border-radius:7px}
.ib:hover{background:rgba(0,0,0,.12);color:#0f172a;border-color:rgba(0,0,0,.2)}
.uavt{background:linear-gradient(135deg,#1d4ed8,#2563eb);color:#ffffff}
.lic-dot{background:#16a34a;box-shadow:0 0 8px rgba(22,163,74,0.7)}
.lic-b{background:rgba(0,0,0,.05);border-color:rgba(0,0,0,.1);color:#475569}
.mhd,.mfooter{background:rgba(0,0,0,.035);border-color:rgba(0,0,0,.1)}
thead tr{background:rgba(0,0,0,.05)}
th{color:#94a3b8;font-weight:700;letter-spacing:.04em}
td{color:#0f172a;border-color:rgba(0,0,0,.06)}
tr:hover td{background:rgba(0,0,0,.045)}
.pw .pb{background:linear-gradient(90deg,#1d4ed8,#2563eb)}
.mbd,#pm{background:rgba(255,255,255,.5) !important;-webkit-backdrop-filter:blur(14px) !important;backdrop-filter:blur(14px) !important}
.al-s{background:rgba(22,163,74,0.12);border-color:rgba(22,163,74,0.4);color:#16a34a}
@keyframes fu{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}

/* ══ الموقع العام index.php ══ */
.navbar{background:rgba(255,255,255,.72) !important;-webkit-backdrop-filter:blur(22px) saturate(155%) !important;backdrop-filter:blur(22px) saturate(155%) !important;border-bottom:1px solid rgba(0,0,0,.1) !important}
.nav-logo-text{color:#2563eb !important}
.nav-btn{background:rgba(0,0,0,.07) !important;border:1px solid rgba(0,0,0,.1) !important;color:#475569 !important}
.nav-btn:hover{background:rgba(37,99,235,0.22) !important;border-color:#2563eb !important;color:#0f172a !important}
.search-wrap input{background:rgba(0,0,0,.05) !important;border:1px solid rgba(0,0,0,.1) !important;color:#0f172a !important}
.search-wrap input::placeholder{color:#94a3b8}
.search-wrap input:focus{background:rgba(0,0,0,.09) !important;border-color:#2563eb !important}
.cat-navbar{background:rgba(255,255,255,.72) !important;-webkit-backdrop-filter:blur(20px) saturate(150%) !important;backdrop-filter:blur(20px) saturate(150%) !important;border-bottom:1px solid rgba(0,0,0,.1) !important}
.cat-nav-btn{background:rgba(0,0,0,.055) !important;border:1px solid rgba(0,0,0,.1) !important;color:#475569 !important;font-weight:650 !important}
.cat-nav-btn:hover{background:rgba(0,0,0,.11) !important;border-color:rgba(0,0,0,.2) !important;color:#0f172a !important}
.cat-nav-btn.active{background:linear-gradient(135deg,#1d4ed8,#2563eb) !important;border-color:transparent !important;color:#ffffff !important;box-shadow:0 4px 16px rgba(37,99,235,0.38) !important}
.fp-panel,.np-panel,.m3u-panel,.ep-panel,.shs-catmenu-panel{background:rgba(255,255,255,.85) !important;-webkit-backdrop-filter:blur(28px) saturate(155%) !important;backdrop-filter:blur(28px) saturate(155%) !important;border-left:1px solid rgba(0,0,0,.1) !important;box-shadow:0 0 50px rgba(15,23,42,.16) !important}
.panel-overlay,.shs-catmenu-overlay,.tmdb-modal-overlay{background:rgba(255,255,255,.35) !important;-webkit-backdrop-filter:blur(8px) !important;backdrop-filter:blur(8px) !important}
.netflix-card,.nx-card,.ch-card,.sr-card{background:rgba(255,255,255,.75) !important;border:1px solid rgba(0,0,0,.1) !important;border-radius:10px !important;box-shadow:0 6px 24px rgba(15,23,42,.1) !important}
.netflix-card:hover,.nx-card:hover{border-color:rgba(37,99,235,0.5) !important;box-shadow:0 12px 36px rgba(15,23,42,.16),0 0 0 1px rgba(37,99,235,0.25) !important}
.shs-catview-banner{background:rgba(255,255,255,.75) !important;border:1px solid rgba(0,0,0,.1) !important;-webkit-backdrop-filter:blur(16px) saturate(140%);backdrop-filter:blur(16px) saturate(140%)}
.shs-catmenu-item:hover{background:rgba(0,0,0,.07)}
.shs-empty-ico{background:radial-gradient(120% 120% at 30% 20%,rgba(37,99,235,0.16),rgba(0,0,0,.03));color:#2563eb}
.shs-spinner::before{border-top-color:#2563eb;border-right-color:#2563eb}
.hero-btn-play,.btn-play{background:linear-gradient(135deg,#1d4ed8,#2563eb) !important;color:#ffffff !important}
/* ══ إصلاح أيقونات شريط الأقسام ══ */
.cat-nav-btn i,.cat-nav-btn svg,.cat-nav-btn .lcn,.cat-nav-btn .lcn svg{
 color:#475569 !important;stroke:currentColor !important;fill:none !important;
 width:1em;height:1em;flex-shrink:0;opacity:.9;transition:color .2s}
.cat-nav-btn:hover i,.cat-nav-btn:hover svg,.cat-nav-btn:hover .lcn,.cat-nav-btn:hover .lcn svg{
 color:#0f172a !important;opacity:1}
.cat-nav-btn.active i,.cat-nav-btn.active svg,.cat-nav-btn.active .lcn,.cat-nav-btn.active .lcn svg{
 color:#ffffff !important;opacity:1}
.cat-nav-btn .lcn{display:inline-flex;align-items:center;justify-content:center;line-height:0}
/* أيقونات القائمة الجانبية للأقسام */
.shs-catmenu-item i,.shs-catmenu-item svg,.shs-catmenu-item .lcn svg{
 color:#2563eb !important;stroke:currentColor !important;fill:none !important}
/* أيقونات أزرار التنقّل العلوية */
.nav-btn i,.nav-btn svg,.nav-btn .lcn svg{stroke:currentColor !important;fill:none !important}`
    },
    midnight: {
        name: 'Midnight Blue',
        css: `:root{--red:#7aa2ff;--redg:rgba(122,162,255,0.35);--gold:#3b5bdb;--s0:#070d24;--s1:#0b1437;--s2:#111c47;--s3:#172456;--s4:#1f2f6b;--br:rgba(255,255,255,.09);--brh:rgba(255,255,255,.2);--t1:#e8eeff;--t2:#9fb3e0;--t3:#5a6a99;--r1:12px}

/* خلفية */
html{background:#070d24}
body{background:#070d24 !important;color:#e8eeff;position:relative;min-height:100vh}
body::before{content:"";position:fixed;inset:0;z-index:-2;pointer-events:none;background:
  radial-gradient(60% 50% at 18% 10%,rgba(122,162,255,0.14),transparent 62%),
  radial-gradient(60% 50% at 85% 85%,rgba(59,91,219,0.12),transparent 62%),
  radial-gradient(60% 50% at 75% 15%,rgba(255,209,102,0.05),transparent 62%);
  animation:thDrift 30s ease-in-out infinite alternate}
@keyframes thDrift{0%{transform:scale(1)}100%{transform:scale(1.08)}}
body,.si,.fi,.fs,td,th,p{-webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale}
.stitle,.tbtitle,.sbrand-name{color:#e8eeff;font-weight:750;letter-spacing:-.01em}
.card p,.sc p{color:#9fb3e0;line-height:1.75}
.snl{color:#5a6a99;letter-spacing:.08em;font-weight:600}

/* ══ لوحة التحكم ══ */
.sidebar{background:rgba(11,20,55,0.82) !important;-webkit-backdrop-filter:blur(22px) saturate(150%);backdrop-filter:blur(22px) saturate(150%);border-left:1px solid rgba(255,255,255,.09) !important;box-shadow:-1px 0 40px rgba(0,0,0,.4)}
.sidebar::after{background:linear-gradient(180deg,rgba(122,162,255,0.6),transparent)}
.sbrand{background:rgba(255,255,255,.03);border-bottom:1px solid rgba(255,255,255,.09)}
.sbrand-icon{background:linear-gradient(135deg,#3b5bdb,#7aa2ff);color:#ffffff;box-shadow:0 4px 16px rgba(122,162,255,0.3)}
.sbrand-sub{color:#5a6a99}
.si{color:#9fb3e0;font-weight:600}
.si:hover{background:rgba(255,255,255,.06);color:#e8eeff}
.si.on{background:rgba(122,162,255,0.12);color:#e8eeff}
.si.on::before{background:#7aa2ff}
.si.on .si-ic{color:#7aa2ff}
.topbar{background:rgba(11,20,55,0.72) !important;-webkit-backdrop-filter:blur(22px) saturate(150%);backdrop-filter:blur(22px) saturate(150%);border-bottom:1px solid rgba(255,255,255,.09) !important}
.main{background:transparent !important}
.card,.tw,.swc,.bkc,.sc,.mbox,.pcard,.theme-panel{background:rgba(255,255,255,.05) !important;-webkit-backdrop-filter:blur(16px) saturate(140%) !important;backdrop-filter:blur(16px) saturate(140%) !important;border:1px solid rgba(255,255,255,.09) !important;border-radius:14px !important;box-shadow:0 8px 30px rgba(0,0,0,.4),inset 0 1px 0 rgba(255,255,255,.06) !important}
.sc:hover{background:rgba(255,255,255,.08) !important;border-color:rgba(255,255,255,.2) !important;box-shadow:0 12px 38px rgba(0,0,0,.55),inset 0 1px 0 rgba(255,255,255,.06) !important}
.fi,.fs{background:rgba(255,255,255,.05) !important;border:1px solid rgba(255,255,255,.09) !important;color:#e8eeff !important;border-radius:10px}
.fi::placeholder{color:#5a6a99}
.fi:focus,.fs:focus{border-color:#7aa2ff !important;background:rgba(255,255,255,.08) !important;box-shadow:0 0 0 3px rgba(122,162,255,0.18) !important}
.btn-p{background:linear-gradient(135deg,#3b5bdb,#7aa2ff) !important;border:none !important;color:#ffffff !important;font-weight:700;border-radius:10px;box-shadow:0 4px 18px rgba(122,162,255,0.32)}
.btn-p:hover{filter:brightness(1.1);box-shadow:0 6px 24px rgba(122,162,255,0.45)}
.ib{background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.09);color:#9fb3e0;border-radius:9px}
.ib:hover{background:rgba(255,255,255,.12);color:#e8eeff;border-color:rgba(255,255,255,.2)}
.uavt{background:linear-gradient(135deg,#3b5bdb,#7aa2ff);color:#ffffff}
.lic-dot{background:#4ade80;box-shadow:0 0 8px rgba(74,222,128,0.7)}
.lic-b{background:rgba(255,255,255,.05);border-color:rgba(255,255,255,.09);color:#9fb3e0}
.mhd,.mfooter{background:rgba(255,255,255,.035);border-color:rgba(255,255,255,.09)}
thead tr{background:rgba(255,255,255,.05)}
th{color:#5a6a99;font-weight:700;letter-spacing:.04em}
td{color:#e8eeff;border-color:rgba(255,255,255,.06)}
tr:hover td{background:rgba(255,255,255,.045)}
.pw .pb{background:linear-gradient(90deg,#3b5bdb,#7aa2ff)}
.mbd,#pm{background:rgba(0,0,0,.8) !important;-webkit-backdrop-filter:blur(14px) !important;backdrop-filter:blur(14px) !important}
.al-s{background:rgba(74,222,128,0.12);border-color:rgba(74,222,128,0.4);color:#4ade80}
@keyframes fu{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}

/* ══ الموقع العام index.php ══ */
.navbar{background:rgba(11,20,55,0.72) !important;-webkit-backdrop-filter:blur(22px) saturate(155%) !important;backdrop-filter:blur(22px) saturate(155%) !important;border-bottom:1px solid rgba(255,255,255,.09) !important}
.nav-logo-text{color:#7aa2ff !important}
.nav-btn{background:rgba(255,255,255,.07) !important;border:1px solid rgba(255,255,255,.09) !important;color:#9fb3e0 !important}
.nav-btn:hover{background:rgba(122,162,255,0.22) !important;border-color:#7aa2ff !important;color:#e8eeff !important}
.search-wrap input{background:rgba(255,255,255,.05) !important;border:1px solid rgba(255,255,255,.09) !important;color:#e8eeff !important}
.search-wrap input::placeholder{color:#5a6a99}
.search-wrap input:focus{background:rgba(255,255,255,.09) !important;border-color:#7aa2ff !important}
.cat-navbar{background:rgba(11,20,55,0.72) !important;-webkit-backdrop-filter:blur(20px) saturate(150%) !important;backdrop-filter:blur(20px) saturate(150%) !important;border-bottom:1px solid rgba(255,255,255,.09) !important}
.cat-nav-btn{background:rgba(255,255,255,.055) !important;border:1px solid rgba(255,255,255,.09) !important;color:#9fb3e0 !important;font-weight:650 !important}
.cat-nav-btn:hover{background:rgba(255,255,255,.11) !important;border-color:rgba(255,255,255,.2) !important;color:#e8eeff !important}
.cat-nav-btn.active{background:linear-gradient(135deg,#3b5bdb,#7aa2ff) !important;border-color:transparent !important;color:#ffffff !important;box-shadow:0 4px 16px rgba(122,162,255,0.38) !important}
.fp-panel,.np-panel,.m3u-panel,.ep-panel,.shs-catmenu-panel{background:rgba(11,20,55,0.82) !important;-webkit-backdrop-filter:blur(28px) saturate(155%) !important;backdrop-filter:blur(28px) saturate(155%) !important;border-left:1px solid rgba(255,255,255,.09) !important;box-shadow:0 0 50px rgba(0,0,0,.55) !important}
.panel-overlay,.shs-catmenu-overlay,.tmdb-modal-overlay{background:rgba(0,0,0,.65) !important;-webkit-backdrop-filter:blur(8px) !important;backdrop-filter:blur(8px) !important}
.netflix-card,.nx-card,.ch-card,.sr-card{background:rgba(255,255,255,.05) !important;border:1px solid rgba(255,255,255,.09) !important;border-radius:12px !important;box-shadow:0 6px 24px rgba(0,0,0,.4) !important}
.netflix-card:hover,.nx-card:hover{border-color:rgba(122,162,255,0.5) !important;box-shadow:0 12px 36px rgba(0,0,0,.55),0 0 0 1px rgba(122,162,255,0.25) !important}
.shs-catview-banner{background:rgba(255,255,255,.05) !important;border:1px solid rgba(255,255,255,.09) !important;-webkit-backdrop-filter:blur(16px) saturate(140%);backdrop-filter:blur(16px) saturate(140%)}
.shs-catmenu-item:hover{background:rgba(255,255,255,.07)}
.shs-empty-ico{background:radial-gradient(120% 120% at 30% 20%,rgba(122,162,255,0.16),rgba(255,255,255,.03));color:#7aa2ff}
.shs-spinner::before{border-top-color:#7aa2ff;border-right-color:#7aa2ff}
.hero-btn-play,.btn-play{background:linear-gradient(135deg,#3b5bdb,#7aa2ff) !important;color:#ffffff !important}
/* ══ إصلاح أيقونات شريط الأقسام ══ */
.cat-nav-btn i,.cat-nav-btn svg,.cat-nav-btn .lcn,.cat-nav-btn .lcn svg{
 color:#9fb3e0 !important;stroke:currentColor !important;fill:none !important;
 width:1em;height:1em;flex-shrink:0;opacity:.9;transition:color .2s}
.cat-nav-btn:hover i,.cat-nav-btn:hover svg,.cat-nav-btn:hover .lcn,.cat-nav-btn:hover .lcn svg{
 color:#e8eeff !important;opacity:1}
.cat-nav-btn.active i,.cat-nav-btn.active svg,.cat-nav-btn.active .lcn,.cat-nav-btn.active .lcn svg{
 color:#ffffff !important;opacity:1}
.cat-nav-btn .lcn{display:inline-flex;align-items:center;justify-content:center;line-height:0}
/* أيقونات القائمة الجانبية للأقسام */
.shs-catmenu-item i,.shs-catmenu-item svg,.shs-catmenu-item .lcn svg{
 color:#7aa2ff !important;stroke:currentColor !important;fill:none !important}
/* أيقونات أزرار التنقّل العلوية */
.nav-btn i,.nav-btn svg,.nav-btn .lcn svg{stroke:currentColor !important;fill:none !important}`
    },
    emerald: {
        name: 'Emerald Luxe',
        css: `:root{--red:#10d98a;--redg:rgba(16,217,138,0.32);--gold:#059669;--s0:#03100b;--s1:#061a12;--s2:#0a2419;--s3:#0e2f20;--s4:#143d2a;--br:rgba(255,255,255,.09);--brh:rgba(255,255,255,.2);--t1:#e6fff4;--t2:#93dbbd;--t3:#4f7f69;--r1:12px}

/* خلفية */
html{background:#03100b}
body{background:#03100b !important;color:#e6fff4;position:relative;min-height:100vh}
body::before{content:"";position:fixed;inset:0;z-index:-2;pointer-events:none;background:
  radial-gradient(60% 50% at 18% 12%,rgba(16,217,138,0.13),transparent 62%),
  radial-gradient(60% 50% at 82% 85%,rgba(5,150,105,0.1),transparent 62%),
  radial-gradient(60% 50% at 80% 15%,rgba(255,217,125,0.05),transparent 62%);
  animation:thDrift 30s ease-in-out infinite alternate}
@keyframes thDrift{0%{transform:scale(1)}100%{transform:scale(1.08)}}
body,.si,.fi,.fs,td,th,p{-webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale}
.stitle,.tbtitle,.sbrand-name{color:#e6fff4;font-weight:750;letter-spacing:-.01em}
.card p,.sc p{color:#93dbbd;line-height:1.75}
.snl{color:#4f7f69;letter-spacing:.08em;font-weight:600}

/* ══ لوحة التحكم ══ */
.sidebar{background:rgba(6,26,18,0.82) !important;-webkit-backdrop-filter:blur(22px) saturate(150%);backdrop-filter:blur(22px) saturate(150%);border-left:1px solid rgba(255,255,255,.09) !important;box-shadow:-1px 0 40px rgba(0,0,0,.4)}
.sidebar::after{background:linear-gradient(180deg,rgba(16,217,138,0.6),transparent)}
.sbrand{background:rgba(255,255,255,.03);border-bottom:1px solid rgba(255,255,255,.09)}
.sbrand-icon{background:linear-gradient(135deg,#059669,#10d98a);color:#ffffff;box-shadow:0 4px 16px rgba(16,217,138,0.3)}
.sbrand-sub{color:#4f7f69}
.si{color:#93dbbd;font-weight:600}
.si:hover{background:rgba(255,255,255,.06);color:#e6fff4}
.si.on{background:rgba(16,217,138,0.12);color:#e6fff4}
.si.on::before{background:#10d98a}
.si.on .si-ic{color:#10d98a}
.topbar{background:rgba(6,26,18,0.72) !important;-webkit-backdrop-filter:blur(22px) saturate(150%);backdrop-filter:blur(22px) saturate(150%);border-bottom:1px solid rgba(255,255,255,.09) !important}
.main{background:transparent !important}
.card,.tw,.swc,.bkc,.sc,.mbox,.pcard,.theme-panel{background:rgba(255,255,255,.05) !important;-webkit-backdrop-filter:blur(16px) saturate(140%) !important;backdrop-filter:blur(16px) saturate(140%) !important;border:1px solid rgba(255,255,255,.09) !important;border-radius:14px !important;box-shadow:0 8px 30px rgba(0,0,0,.4),inset 0 1px 0 rgba(255,255,255,.06) !important}
.sc:hover{background:rgba(255,255,255,.08) !important;border-color:rgba(255,255,255,.2) !important;box-shadow:0 12px 38px rgba(0,0,0,.55),inset 0 1px 0 rgba(255,255,255,.06) !important}
.fi,.fs{background:rgba(255,255,255,.05) !important;border:1px solid rgba(255,255,255,.09) !important;color:#e6fff4 !important;border-radius:10px}
.fi::placeholder{color:#4f7f69}
.fi:focus,.fs:focus{border-color:#10d98a !important;background:rgba(255,255,255,.08) !important;box-shadow:0 0 0 3px rgba(16,217,138,0.18) !important}
.btn-p{background:linear-gradient(135deg,#059669,#10d98a) !important;border:none !important;color:#ffffff !important;font-weight:700;border-radius:10px;box-shadow:0 4px 18px rgba(16,217,138,0.32)}
.btn-p:hover{filter:brightness(1.1);box-shadow:0 6px 24px rgba(16,217,138,0.45)}
.ib{background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.09);color:#93dbbd;border-radius:9px}
.ib:hover{background:rgba(255,255,255,.12);color:#e6fff4;border-color:rgba(255,255,255,.2)}
.uavt{background:linear-gradient(135deg,#059669,#10d98a);color:#ffffff}
.lic-dot{background:#10d98a;box-shadow:0 0 8px rgba(16,217,138,0.7)}
.lic-b{background:rgba(255,255,255,.05);border-color:rgba(255,255,255,.09);color:#93dbbd}
.mhd,.mfooter{background:rgba(255,255,255,.035);border-color:rgba(255,255,255,.09)}
thead tr{background:rgba(255,255,255,.05)}
th{color:#4f7f69;font-weight:700;letter-spacing:.04em}
td{color:#e6fff4;border-color:rgba(255,255,255,.06)}
tr:hover td{background:rgba(255,255,255,.045)}
.pw .pb{background:linear-gradient(90deg,#059669,#10d98a)}
.mbd,#pm{background:rgba(0,0,0,.8) !important;-webkit-backdrop-filter:blur(14px) !important;backdrop-filter:blur(14px) !important}
.al-s{background:rgba(16,217,138,0.12);border-color:rgba(16,217,138,0.4);color:#10d98a}
@keyframes fu{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}

/* ══ الموقع العام index.php ══ */
.navbar{background:rgba(6,26,18,0.72) !important;-webkit-backdrop-filter:blur(22px) saturate(155%) !important;backdrop-filter:blur(22px) saturate(155%) !important;border-bottom:1px solid rgba(255,255,255,.09) !important}
.nav-logo-text{color:#10d98a !important}
.nav-btn{background:rgba(255,255,255,.07) !important;border:1px solid rgba(255,255,255,.09) !important;color:#93dbbd !important}
.nav-btn:hover{background:rgba(16,217,138,0.22) !important;border-color:#10d98a !important;color:#e6fff4 !important}
.search-wrap input{background:rgba(255,255,255,.05) !important;border:1px solid rgba(255,255,255,.09) !important;color:#e6fff4 !important}
.search-wrap input::placeholder{color:#4f7f69}
.search-wrap input:focus{background:rgba(255,255,255,.09) !important;border-color:#10d98a !important}
.cat-navbar{background:rgba(6,26,18,0.72) !important;-webkit-backdrop-filter:blur(20px) saturate(150%) !important;backdrop-filter:blur(20px) saturate(150%) !important;border-bottom:1px solid rgba(255,255,255,.09) !important}
.cat-nav-btn{background:rgba(255,255,255,.055) !important;border:1px solid rgba(255,255,255,.09) !important;color:#93dbbd !important;font-weight:650 !important}
.cat-nav-btn:hover{background:rgba(255,255,255,.11) !important;border-color:rgba(255,255,255,.2) !important;color:#e6fff4 !important}
.cat-nav-btn.active{background:linear-gradient(135deg,#059669,#10d98a) !important;border-color:transparent !important;color:#ffffff !important;box-shadow:0 4px 16px rgba(16,217,138,0.38) !important}
.fp-panel,.np-panel,.m3u-panel,.ep-panel,.shs-catmenu-panel{background:rgba(6,26,18,0.82) !important;-webkit-backdrop-filter:blur(28px) saturate(155%) !important;backdrop-filter:blur(28px) saturate(155%) !important;border-left:1px solid rgba(255,255,255,.09) !important;box-shadow:0 0 50px rgba(0,0,0,.55) !important}
.panel-overlay,.shs-catmenu-overlay,.tmdb-modal-overlay{background:rgba(0,0,0,.65) !important;-webkit-backdrop-filter:blur(8px) !important;backdrop-filter:blur(8px) !important}
.netflix-card,.nx-card,.ch-card,.sr-card{background:rgba(255,255,255,.05) !important;border:1px solid rgba(255,255,255,.09) !important;border-radius:12px !important;box-shadow:0 6px 24px rgba(0,0,0,.4) !important}
.netflix-card:hover,.nx-card:hover{border-color:rgba(16,217,138,0.5) !important;box-shadow:0 12px 36px rgba(0,0,0,.55),0 0 0 1px rgba(16,217,138,0.25) !important}
.shs-catview-banner{background:rgba(255,255,255,.05) !important;border:1px solid rgba(255,255,255,.09) !important;-webkit-backdrop-filter:blur(16px) saturate(140%);backdrop-filter:blur(16px) saturate(140%)}
.shs-catmenu-item:hover{background:rgba(255,255,255,.07)}
.shs-empty-ico{background:radial-gradient(120% 120% at 30% 20%,rgba(16,217,138,0.16),rgba(255,255,255,.03));color:#10d98a}
.shs-spinner::before{border-top-color:#10d98a;border-right-color:#10d98a}
.hero-btn-play,.btn-play{background:linear-gradient(135deg,#059669,#10d98a) !important;color:#ffffff !important}
/* ══ إصلاح أيقونات شريط الأقسام ══ */
.cat-nav-btn i,.cat-nav-btn svg,.cat-nav-btn .lcn,.cat-nav-btn .lcn svg{
 color:#93dbbd !important;stroke:currentColor !important;fill:none !important;
 width:1em;height:1em;flex-shrink:0;opacity:.9;transition:color .2s}
.cat-nav-btn:hover i,.cat-nav-btn:hover svg,.cat-nav-btn:hover .lcn,.cat-nav-btn:hover .lcn svg{
 color:#e6fff4 !important;opacity:1}
.cat-nav-btn.active i,.cat-nav-btn.active svg,.cat-nav-btn.active .lcn,.cat-nav-btn.active .lcn svg{
 color:#ffffff !important;opacity:1}
.cat-nav-btn .lcn{display:inline-flex;align-items:center;justify-content:center;line-height:0}
/* أيقونات القائمة الجانبية للأقسام */
.shs-catmenu-item i,.shs-catmenu-item svg,.shs-catmenu-item .lcn svg{
 color:#10d98a !important;stroke:currentColor !important;fill:none !important}
/* أيقونات أزرار التنقّل العلوية */
.nav-btn i,.nav-btn svg,.nav-btn .lcn svg{stroke:currentColor !important;fill:none !important}`
    },
    sunset: {
        name: 'Sunset Glow',
        css: `:root{--red:#ff7849;--redg:rgba(255,120,73,0.34);--gold:#ff4d8d;--s0:#140710;--s1:#1c0a16;--s2:#26101e;--s3:#311526;--s4:#3e1b30;--br:rgba(255,255,255,.09);--brh:rgba(255,255,255,.2);--t1:#fff0e8;--t2:#e3ad96;--t3:#8f5f4d;--r1:12px}

/* خلفية */
html{background:#140710}
body{background:#140710 !important;color:#fff0e8;position:relative;min-height:100vh}
body::before{content:"";position:fixed;inset:0;z-index:-2;pointer-events:none;background:
  radial-gradient(60% 50% at 18% 12%,rgba(255,120,73,0.14),transparent 62%),
  radial-gradient(60% 50% at 85% 18%,rgba(255,77,141,0.12),transparent 62%),
  radial-gradient(60% 50% at 75% 88%,rgba(255,179,71,0.08),transparent 62%);
  animation:thDrift 30s ease-in-out infinite alternate}
@keyframes thDrift{0%{transform:scale(1)}100%{transform:scale(1.08)}}
body,.si,.fi,.fs,td,th,p{-webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale}
.stitle,.tbtitle,.sbrand-name{color:#fff0e8;font-weight:750;letter-spacing:-.01em}
.card p,.sc p{color:#e3ad96;line-height:1.75}
.snl{color:#8f5f4d;letter-spacing:.08em;font-weight:600}

/* ══ لوحة التحكم ══ */
.sidebar{background:rgba(28,10,22,0.82) !important;-webkit-backdrop-filter:blur(22px) saturate(150%);backdrop-filter:blur(22px) saturate(150%);border-left:1px solid rgba(255,255,255,.09) !important;box-shadow:-1px 0 40px rgba(0,0,0,.4)}
.sidebar::after{background:linear-gradient(180deg,rgba(255,120,73,0.6),transparent)}
.sbrand{background:rgba(255,255,255,.03);border-bottom:1px solid rgba(255,255,255,.09)}
.sbrand-icon{background:linear-gradient(135deg,#ff4d8d,#ff7849);color:#ffffff;box-shadow:0 4px 16px rgba(255,120,73,0.3)}
.sbrand-sub{color:#8f5f4d}
.si{color:#e3ad96;font-weight:600}
.si:hover{background:rgba(255,255,255,.06);color:#fff0e8}
.si.on{background:rgba(255,120,73,0.12);color:#fff0e8}
.si.on::before{background:#ff7849}
.si.on .si-ic{color:#ff7849}
.topbar{background:rgba(28,10,22,0.72) !important;-webkit-backdrop-filter:blur(22px) saturate(150%);backdrop-filter:blur(22px) saturate(150%);border-bottom:1px solid rgba(255,255,255,.09) !important}
.main{background:transparent !important}
.card,.tw,.swc,.bkc,.sc,.mbox,.pcard,.theme-panel{background:rgba(255,255,255,.05) !important;-webkit-backdrop-filter:blur(16px) saturate(140%) !important;backdrop-filter:blur(16px) saturate(140%) !important;border:1px solid rgba(255,255,255,.09) !important;border-radius:14px !important;box-shadow:0 8px 30px rgba(0,0,0,.4),inset 0 1px 0 rgba(255,255,255,.06) !important}
.sc:hover{background:rgba(255,255,255,.08) !important;border-color:rgba(255,255,255,.2) !important;box-shadow:0 12px 38px rgba(0,0,0,.55),inset 0 1px 0 rgba(255,255,255,.06) !important}
.fi,.fs{background:rgba(255,255,255,.05) !important;border:1px solid rgba(255,255,255,.09) !important;color:#fff0e8 !important;border-radius:10px}
.fi::placeholder{color:#8f5f4d}
.fi:focus,.fs:focus{border-color:#ff7849 !important;background:rgba(255,255,255,.08) !important;box-shadow:0 0 0 3px rgba(255,120,73,0.18) !important}
.btn-p{background:linear-gradient(135deg,#ff4d8d,#ff7849) !important;border:none !important;color:#ffffff !important;font-weight:700;border-radius:10px;box-shadow:0 4px 18px rgba(255,120,73,0.32)}
.btn-p:hover{filter:brightness(1.1);box-shadow:0 6px 24px rgba(255,120,73,0.45)}
.ib{background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.09);color:#e3ad96;border-radius:9px}
.ib:hover{background:rgba(255,255,255,.12);color:#fff0e8;border-color:rgba(255,255,255,.2)}
.uavt{background:linear-gradient(135deg,#ff4d8d,#ff7849);color:#ffffff}
.lic-dot{background:#ffb347;box-shadow:0 0 8px rgba(255,179,71,0.7)}
.lic-b{background:rgba(255,255,255,.05);border-color:rgba(255,255,255,.09);color:#e3ad96}
.mhd,.mfooter{background:rgba(255,255,255,.035);border-color:rgba(255,255,255,.09)}
thead tr{background:rgba(255,255,255,.05)}
th{color:#8f5f4d;font-weight:700;letter-spacing:.04em}
td{color:#fff0e8;border-color:rgba(255,255,255,.06)}
tr:hover td{background:rgba(255,255,255,.045)}
.pw .pb{background:linear-gradient(90deg,#ff4d8d,#ff7849)}
.mbd,#pm{background:rgba(0,0,0,.8) !important;-webkit-backdrop-filter:blur(14px) !important;backdrop-filter:blur(14px) !important}
.al-s{background:rgba(255,179,71,0.12);border-color:rgba(255,179,71,0.4);color:#ffb347}
@keyframes fu{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}

/* ══ الموقع العام index.php ══ */
.navbar{background:rgba(28,10,22,0.72) !important;-webkit-backdrop-filter:blur(22px) saturate(155%) !important;backdrop-filter:blur(22px) saturate(155%) !important;border-bottom:1px solid rgba(255,255,255,.09) !important}
.nav-logo-text{color:#ff7849 !important}
.nav-btn{background:rgba(255,255,255,.07) !important;border:1px solid rgba(255,255,255,.09) !important;color:#e3ad96 !important}
.nav-btn:hover{background:rgba(255,120,73,0.22) !important;border-color:#ff7849 !important;color:#fff0e8 !important}
.search-wrap input{background:rgba(255,255,255,.05) !important;border:1px solid rgba(255,255,255,.09) !important;color:#fff0e8 !important}
.search-wrap input::placeholder{color:#8f5f4d}
.search-wrap input:focus{background:rgba(255,255,255,.09) !important;border-color:#ff7849 !important}
.cat-navbar{background:rgba(28,10,22,0.72) !important;-webkit-backdrop-filter:blur(20px) saturate(150%) !important;backdrop-filter:blur(20px) saturate(150%) !important;border-bottom:1px solid rgba(255,255,255,.09) !important}
.cat-nav-btn{background:rgba(255,255,255,.055) !important;border:1px solid rgba(255,255,255,.09) !important;color:#e3ad96 !important;font-weight:650 !important}
.cat-nav-btn:hover{background:rgba(255,255,255,.11) !important;border-color:rgba(255,255,255,.2) !important;color:#fff0e8 !important}
.cat-nav-btn.active{background:linear-gradient(135deg,#ff4d8d,#ff7849) !important;border-color:transparent !important;color:#ffffff !important;box-shadow:0 4px 16px rgba(255,120,73,0.38) !important}
.fp-panel,.np-panel,.m3u-panel,.ep-panel,.shs-catmenu-panel{background:rgba(28,10,22,0.82) !important;-webkit-backdrop-filter:blur(28px) saturate(155%) !important;backdrop-filter:blur(28px) saturate(155%) !important;border-left:1px solid rgba(255,255,255,.09) !important;box-shadow:0 0 50px rgba(0,0,0,.55) !important}
.panel-overlay,.shs-catmenu-overlay,.tmdb-modal-overlay{background:rgba(0,0,0,.65) !important;-webkit-backdrop-filter:blur(8px) !important;backdrop-filter:blur(8px) !important}
.netflix-card,.nx-card,.ch-card,.sr-card{background:rgba(255,255,255,.05) !important;border:1px solid rgba(255,255,255,.09) !important;border-radius:12px !important;box-shadow:0 6px 24px rgba(0,0,0,.4) !important}
.netflix-card:hover,.nx-card:hover{border-color:rgba(255,120,73,0.5) !important;box-shadow:0 12px 36px rgba(0,0,0,.55),0 0 0 1px rgba(255,120,73,0.25) !important}
.shs-catview-banner{background:rgba(255,255,255,.05) !important;border:1px solid rgba(255,255,255,.09) !important;-webkit-backdrop-filter:blur(16px) saturate(140%);backdrop-filter:blur(16px) saturate(140%)}
.shs-catmenu-item:hover{background:rgba(255,255,255,.07)}
.shs-empty-ico{background:radial-gradient(120% 120% at 30% 20%,rgba(255,120,73,0.16),rgba(255,255,255,.03));color:#ff7849}
.shs-spinner::before{border-top-color:#ff7849;border-right-color:#ff7849}
.hero-btn-play,.btn-play{background:linear-gradient(135deg,#ff4d8d,#ff7849) !important;color:#ffffff !important}
/* ══ إصلاح أيقونات شريط الأقسام ══ */
.cat-nav-btn i,.cat-nav-btn svg,.cat-nav-btn .lcn,.cat-nav-btn .lcn svg{
 color:#e3ad96 !important;stroke:currentColor !important;fill:none !important;
 width:1em;height:1em;flex-shrink:0;opacity:.9;transition:color .2s}
.cat-nav-btn:hover i,.cat-nav-btn:hover svg,.cat-nav-btn:hover .lcn,.cat-nav-btn:hover .lcn svg{
 color:#fff0e8 !important;opacity:1}
.cat-nav-btn.active i,.cat-nav-btn.active svg,.cat-nav-btn.active .lcn,.cat-nav-btn.active .lcn svg{
 color:#ffffff !important;opacity:1}
.cat-nav-btn .lcn{display:inline-flex;align-items:center;justify-content:center;line-height:0}
/* أيقونات القائمة الجانبية للأقسام */
.shs-catmenu-item i,.shs-catmenu-item svg,.shs-catmenu-item .lcn svg{
 color:#ff7849 !important;stroke:currentColor !important;fill:none !important}
/* أيقونات أزرار التنقّل العلوية */
.nav-btn i,.nav-btn svg,.nav-btn .lcn svg{stroke:currentColor !important;fill:none !important}`
    },
    royalgold: {
        name: 'Royal Gold',
        css: `:root{--red:#f5c542;--redg:rgba(245,197,66,0.3);--gold:#b8941f;--s0:#0a0800;--s1:#100d02;--s2:#181305;--s3:#211a08;--s4:#2c220b;--br:rgba(255,255,255,.09);--brh:rgba(255,255,255,.2);--t1:#fdf6e3;--t2:#d1bc80;--t3:#7f734a;--r1:10px}

/* خلفية */
html{background:#0a0800}
body{background:#0a0800 !important;color:#fdf6e3;position:relative;min-height:100vh}
body::before{content:"";position:fixed;inset:0;z-index:-2;pointer-events:none;background:
  radial-gradient(60% 50% at 18% 10%,rgba(245,197,66,0.11),transparent 62%),
  radial-gradient(60% 50% at 85% 85%,rgba(184,148,31,0.09),transparent 62%);
  animation:thDrift 30s ease-in-out infinite alternate}
@keyframes thDrift{0%{transform:scale(1)}100%{transform:scale(1.08)}}
body,.si,.fi,.fs,td,th,p{-webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale}
.stitle,.tbtitle,.sbrand-name{color:#fdf6e3;font-weight:750;letter-spacing:-.01em}
.card p,.sc p{color:#d1bc80;line-height:1.75}
.snl{color:#7f734a;letter-spacing:.08em;font-weight:600}

/* ══ لوحة التحكم ══ */
.sidebar{background:rgba(16,13,2,0.82) !important;-webkit-backdrop-filter:blur(22px) saturate(150%);backdrop-filter:blur(22px) saturate(150%);border-left:1px solid rgba(255,255,255,.09) !important;box-shadow:-1px 0 40px rgba(0,0,0,.4)}
.sidebar::after{background:linear-gradient(180deg,rgba(245,197,66,0.6),transparent)}
.sbrand{background:rgba(255,255,255,.03);border-bottom:1px solid rgba(255,255,255,.09)}
.sbrand-icon{background:linear-gradient(135deg,#b8941f,#f5c542);color:#1a1400;box-shadow:0 4px 16px rgba(245,197,66,0.3)}
.sbrand-sub{color:#7f734a}
.si{color:#d1bc80;font-weight:600}
.si:hover{background:rgba(255,255,255,.06);color:#fdf6e3}
.si.on{background:rgba(245,197,66,0.12);color:#fdf6e3}
.si.on::before{background:#f5c542}
.si.on .si-ic{color:#f5c542}
.topbar{background:rgba(16,13,2,0.72) !important;-webkit-backdrop-filter:blur(22px) saturate(150%);backdrop-filter:blur(22px) saturate(150%);border-bottom:1px solid rgba(255,255,255,.09) !important}
.main{background:transparent !important}
.card,.tw,.swc,.bkc,.sc,.mbox,.pcard,.theme-panel{background:rgba(255,255,255,.05) !important;-webkit-backdrop-filter:blur(16px) saturate(140%) !important;backdrop-filter:blur(16px) saturate(140%) !important;border:1px solid rgba(255,255,255,.09) !important;border-radius:12px !important;box-shadow:0 8px 30px rgba(0,0,0,.4),inset 0 1px 0 rgba(255,255,255,.06) !important}
.sc:hover{background:rgba(255,255,255,.08) !important;border-color:rgba(255,255,255,.2) !important;box-shadow:0 12px 38px rgba(0,0,0,.55),inset 0 1px 0 rgba(255,255,255,.06) !important}
.fi,.fs{background:rgba(255,255,255,.05) !important;border:1px solid rgba(255,255,255,.09) !important;color:#fdf6e3 !important;border-radius:8px}
.fi::placeholder{color:#7f734a}
.fi:focus,.fs:focus{border-color:#f5c542 !important;background:rgba(255,255,255,.08) !important;box-shadow:0 0 0 3px rgba(245,197,66,0.18) !important}
.btn-p{background:linear-gradient(135deg,#b8941f,#f5c542) !important;border:none !important;color:#1a1400 !important;font-weight:700;border-radius:8px;box-shadow:0 4px 18px rgba(245,197,66,0.32)}
.btn-p:hover{filter:brightness(1.1);box-shadow:0 6px 24px rgba(245,197,66,0.45)}
.ib{background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.09);color:#d1bc80;border-radius:7px}
.ib:hover{background:rgba(255,255,255,.12);color:#fdf6e3;border-color:rgba(255,255,255,.2)}
.uavt{background:linear-gradient(135deg,#b8941f,#f5c542);color:#1a1400}
.lic-dot{background:#f5c542;box-shadow:0 0 8px rgba(245,197,66,0.7)}
.lic-b{background:rgba(255,255,255,.05);border-color:rgba(255,255,255,.09);color:#d1bc80}
.mhd,.mfooter{background:rgba(255,255,255,.035);border-color:rgba(255,255,255,.09)}
thead tr{background:rgba(255,255,255,.05)}
th{color:#7f734a;font-weight:700;letter-spacing:.04em}
td{color:#fdf6e3;border-color:rgba(255,255,255,.06)}
tr:hover td{background:rgba(255,255,255,.045)}
.pw .pb{background:linear-gradient(90deg,#b8941f,#f5c542)}
.mbd,#pm{background:rgba(0,0,0,.8) !important;-webkit-backdrop-filter:blur(14px) !important;backdrop-filter:blur(14px) !important}
.al-s{background:rgba(245,197,66,0.12);border-color:rgba(245,197,66,0.4);color:#f5c542}
@keyframes fu{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}

/* ══ الموقع العام index.php ══ */
.navbar{background:rgba(16,13,2,0.72) !important;-webkit-backdrop-filter:blur(22px) saturate(155%) !important;backdrop-filter:blur(22px) saturate(155%) !important;border-bottom:1px solid rgba(255,255,255,.09) !important}
.nav-logo-text{color:#f5c542 !important}
.nav-btn{background:rgba(255,255,255,.07) !important;border:1px solid rgba(255,255,255,.09) !important;color:#d1bc80 !important}
.nav-btn:hover{background:rgba(245,197,66,0.22) !important;border-color:#f5c542 !important;color:#fdf6e3 !important}
.search-wrap input{background:rgba(255,255,255,.05) !important;border:1px solid rgba(255,255,255,.09) !important;color:#fdf6e3 !important}
.search-wrap input::placeholder{color:#7f734a}
.search-wrap input:focus{background:rgba(255,255,255,.09) !important;border-color:#f5c542 !important}
.cat-navbar{background:rgba(16,13,2,0.72) !important;-webkit-backdrop-filter:blur(20px) saturate(150%) !important;backdrop-filter:blur(20px) saturate(150%) !important;border-bottom:1px solid rgba(255,255,255,.09) !important}
.cat-nav-btn{background:rgba(255,255,255,.055) !important;border:1px solid rgba(255,255,255,.09) !important;color:#d1bc80 !important;font-weight:650 !important}
.cat-nav-btn:hover{background:rgba(255,255,255,.11) !important;border-color:rgba(255,255,255,.2) !important;color:#fdf6e3 !important}
.cat-nav-btn.active{background:linear-gradient(135deg,#b8941f,#f5c542) !important;border-color:transparent !important;color:#1a1400 !important;box-shadow:0 4px 16px rgba(245,197,66,0.38) !important}
.fp-panel,.np-panel,.m3u-panel,.ep-panel,.shs-catmenu-panel{background:rgba(16,13,2,0.82) !important;-webkit-backdrop-filter:blur(28px) saturate(155%) !important;backdrop-filter:blur(28px) saturate(155%) !important;border-left:1px solid rgba(255,255,255,.09) !important;box-shadow:0 0 50px rgba(0,0,0,.55) !important}
.panel-overlay,.shs-catmenu-overlay,.tmdb-modal-overlay{background:rgba(0,0,0,.65) !important;-webkit-backdrop-filter:blur(8px) !important;backdrop-filter:blur(8px) !important}
.netflix-card,.nx-card,.ch-card,.sr-card{background:rgba(255,255,255,.05) !important;border:1px solid rgba(255,255,255,.09) !important;border-radius:10px !important;box-shadow:0 6px 24px rgba(0,0,0,.4) !important}
.netflix-card:hover,.nx-card:hover{border-color:rgba(245,197,66,0.5) !important;box-shadow:0 12px 36px rgba(0,0,0,.55),0 0 0 1px rgba(245,197,66,0.25) !important}
.shs-catview-banner{background:rgba(255,255,255,.05) !important;border:1px solid rgba(255,255,255,.09) !important;-webkit-backdrop-filter:blur(16px) saturate(140%);backdrop-filter:blur(16px) saturate(140%)}
.shs-catmenu-item:hover{background:rgba(255,255,255,.07)}
.shs-empty-ico{background:radial-gradient(120% 120% at 30% 20%,rgba(245,197,66,0.16),rgba(255,255,255,.03));color:#f5c542}
.shs-spinner::before{border-top-color:#f5c542;border-right-color:#f5c542}
.hero-btn-play,.btn-play{background:linear-gradient(135deg,#b8941f,#f5c542) !important;color:#1a1400 !important}
/* ══ إصلاح أيقونات شريط الأقسام ══ */
.cat-nav-btn i,.cat-nav-btn svg,.cat-nav-btn .lcn,.cat-nav-btn .lcn svg{
 color:#d1bc80 !important;stroke:currentColor !important;fill:none !important;
 width:1em;height:1em;flex-shrink:0;opacity:.9;transition:color .2s}
.cat-nav-btn:hover i,.cat-nav-btn:hover svg,.cat-nav-btn:hover .lcn,.cat-nav-btn:hover .lcn svg{
 color:#fdf6e3 !important;opacity:1}
.cat-nav-btn.active i,.cat-nav-btn.active svg,.cat-nav-btn.active .lcn,.cat-nav-btn.active .lcn svg{
 color:#1a1400 !important;opacity:1}
.cat-nav-btn .lcn{display:inline-flex;align-items:center;justify-content:center;line-height:0}
/* أيقونات القائمة الجانبية للأقسام */
.shs-catmenu-item i,.shs-catmenu-item svg,.shs-catmenu-item .lcn svg{
 color:#f5c542 !important;stroke:currentColor !important;fill:none !important}
/* أيقونات أزرار التنقّل العلوية */
.nav-btn i,.nav-btn svg,.nav-btn .lcn svg{stroke:currentColor !important;fill:none !important}`
    },
    crimson: {
        name: 'Crimson Noir',
        css: `:root{--red:#ff3355;--redg:rgba(255,51,85,0.35);--gold:#b81d3c;--s0:#100308;--s1:#16050a;--s2:#1f0810;--s3:#290b16;--s4:#36101d;--br:rgba(255,255,255,.09);--brh:rgba(255,255,255,.2);--t1:#ffe8ed;--t2:#dda5b1;--t3:#90505e;--r1:12px}

/* خلفية */
html{background:#100308}
body{background:#100308 !important;color:#ffe8ed;position:relative;min-height:100vh}
body::before{content:"";position:fixed;inset:0;z-index:-2;pointer-events:none;background:
  radial-gradient(60% 50% at 18% 12%,rgba(255,51,85,0.13),transparent 62%),
  radial-gradient(60% 50% at 85% 85%,rgba(184,29,60,0.11),transparent 62%);
  animation:thDrift 30s ease-in-out infinite alternate}
@keyframes thDrift{0%{transform:scale(1)}100%{transform:scale(1.08)}}
body,.si,.fi,.fs,td,th,p{-webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale}
.stitle,.tbtitle,.sbrand-name{color:#ffe8ed;font-weight:750;letter-spacing:-.01em}
.card p,.sc p{color:#dda5b1;line-height:1.75}
.snl{color:#90505e;letter-spacing:.08em;font-weight:600}

/* ══ لوحة التحكم ══ */
.sidebar{background:rgba(22,5,10,0.82) !important;-webkit-backdrop-filter:blur(22px) saturate(150%);backdrop-filter:blur(22px) saturate(150%);border-left:1px solid rgba(255,255,255,.09) !important;box-shadow:-1px 0 40px rgba(0,0,0,.4)}
.sidebar::after{background:linear-gradient(180deg,rgba(255,51,85,0.6),transparent)}
.sbrand{background:rgba(255,255,255,.03);border-bottom:1px solid rgba(255,255,255,.09)}
.sbrand-icon{background:linear-gradient(135deg,#b81d3c,#ff3355);color:#ffffff;box-shadow:0 4px 16px rgba(255,51,85,0.3)}
.sbrand-sub{color:#90505e}
.si{color:#dda5b1;font-weight:600}
.si:hover{background:rgba(255,255,255,.06);color:#ffe8ed}
.si.on{background:rgba(255,51,85,0.12);color:#ffe8ed}
.si.on::before{background:#ff3355}
.si.on .si-ic{color:#ff3355}
.topbar{background:rgba(22,5,10,0.72) !important;-webkit-backdrop-filter:blur(22px) saturate(150%);backdrop-filter:blur(22px) saturate(150%);border-bottom:1px solid rgba(255,255,255,.09) !important}
.main{background:transparent !important}
.card,.tw,.swc,.bkc,.sc,.mbox,.pcard,.theme-panel{background:rgba(255,255,255,.05) !important;-webkit-backdrop-filter:blur(16px) saturate(140%) !important;backdrop-filter:blur(16px) saturate(140%) !important;border:1px solid rgba(255,255,255,.09) !important;border-radius:14px !important;box-shadow:0 8px 30px rgba(0,0,0,.4),inset 0 1px 0 rgba(255,255,255,.06) !important}
.sc:hover{background:rgba(255,255,255,.08) !important;border-color:rgba(255,255,255,.2) !important;box-shadow:0 12px 38px rgba(0,0,0,.55),inset 0 1px 0 rgba(255,255,255,.06) !important}
.fi,.fs{background:rgba(255,255,255,.05) !important;border:1px solid rgba(255,255,255,.09) !important;color:#ffe8ed !important;border-radius:10px}
.fi::placeholder{color:#90505e}
.fi:focus,.fs:focus{border-color:#ff3355 !important;background:rgba(255,255,255,.08) !important;box-shadow:0 0 0 3px rgba(255,51,85,0.18) !important}
.btn-p{background:linear-gradient(135deg,#b81d3c,#ff3355) !important;border:none !important;color:#ffffff !important;font-weight:700;border-radius:10px;box-shadow:0 4px 18px rgba(255,51,85,0.32)}
.btn-p:hover{filter:brightness(1.1);box-shadow:0 6px 24px rgba(255,51,85,0.45)}
.ib{background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.09);color:#dda5b1;border-radius:9px}
.ib:hover{background:rgba(255,255,255,.12);color:#ffe8ed;border-color:rgba(255,255,255,.2)}
.uavt{background:linear-gradient(135deg,#b81d3c,#ff3355);color:#ffffff}
.lic-dot{background:#ff9eb5;box-shadow:0 0 8px rgba(255,158,181,0.7)}
.lic-b{background:rgba(255,255,255,.05);border-color:rgba(255,255,255,.09);color:#dda5b1}
.mhd,.mfooter{background:rgba(255,255,255,.035);border-color:rgba(255,255,255,.09)}
thead tr{background:rgba(255,255,255,.05)}
th{color:#90505e;font-weight:700;letter-spacing:.04em}
td{color:#ffe8ed;border-color:rgba(255,255,255,.06)}
tr:hover td{background:rgba(255,255,255,.045)}
.pw .pb{background:linear-gradient(90deg,#b81d3c,#ff3355)}
.mbd,#pm{background:rgba(0,0,0,.8) !important;-webkit-backdrop-filter:blur(14px) !important;backdrop-filter:blur(14px) !important}
.al-s{background:rgba(255,158,181,0.12);border-color:rgba(255,158,181,0.4);color:#ff9eb5}
@keyframes fu{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}

/* ══ الموقع العام index.php ══ */
.navbar{background:rgba(22,5,10,0.72) !important;-webkit-backdrop-filter:blur(22px) saturate(155%) !important;backdrop-filter:blur(22px) saturate(155%) !important;border-bottom:1px solid rgba(255,255,255,.09) !important}
.nav-logo-text{color:#ff3355 !important}
.nav-btn{background:rgba(255,255,255,.07) !important;border:1px solid rgba(255,255,255,.09) !important;color:#dda5b1 !important}
.nav-btn:hover{background:rgba(255,51,85,0.22) !important;border-color:#ff3355 !important;color:#ffe8ed !important}
.search-wrap input{background:rgba(255,255,255,.05) !important;border:1px solid rgba(255,255,255,.09) !important;color:#ffe8ed !important}
.search-wrap input::placeholder{color:#90505e}
.search-wrap input:focus{background:rgba(255,255,255,.09) !important;border-color:#ff3355 !important}
.cat-navbar{background:rgba(22,5,10,0.72) !important;-webkit-backdrop-filter:blur(20px) saturate(150%) !important;backdrop-filter:blur(20px) saturate(150%) !important;border-bottom:1px solid rgba(255,255,255,.09) !important}
.cat-nav-btn{background:rgba(255,255,255,.055) !important;border:1px solid rgba(255,255,255,.09) !important;color:#dda5b1 !important;font-weight:650 !important}
.cat-nav-btn:hover{background:rgba(255,255,255,.11) !important;border-color:rgba(255,255,255,.2) !important;color:#ffe8ed !important}
.cat-nav-btn.active{background:linear-gradient(135deg,#b81d3c,#ff3355) !important;border-color:transparent !important;color:#ffffff !important;box-shadow:0 4px 16px rgba(255,51,85,0.38) !important}
.fp-panel,.np-panel,.m3u-panel,.ep-panel,.shs-catmenu-panel{background:rgba(22,5,10,0.82) !important;-webkit-backdrop-filter:blur(28px) saturate(155%) !important;backdrop-filter:blur(28px) saturate(155%) !important;border-left:1px solid rgba(255,255,255,.09) !important;box-shadow:0 0 50px rgba(0,0,0,.55) !important}
.panel-overlay,.shs-catmenu-overlay,.tmdb-modal-overlay{background:rgba(0,0,0,.65) !important;-webkit-backdrop-filter:blur(8px) !important;backdrop-filter:blur(8px) !important}
.netflix-card,.nx-card,.ch-card,.sr-card{background:rgba(255,255,255,.05) !important;border:1px solid rgba(255,255,255,.09) !important;border-radius:12px !important;box-shadow:0 6px 24px rgba(0,0,0,.4) !important}
.netflix-card:hover,.nx-card:hover{border-color:rgba(255,51,85,0.5) !important;box-shadow:0 12px 36px rgba(0,0,0,.55),0 0 0 1px rgba(255,51,85,0.25) !important}
.shs-catview-banner{background:rgba(255,255,255,.05) !important;border:1px solid rgba(255,255,255,.09) !important;-webkit-backdrop-filter:blur(16px) saturate(140%);backdrop-filter:blur(16px) saturate(140%)}
.shs-catmenu-item:hover{background:rgba(255,255,255,.07)}
.shs-empty-ico{background:radial-gradient(120% 120% at 30% 20%,rgba(255,51,85,0.16),rgba(255,255,255,.03));color:#ff3355}
.shs-spinner::before{border-top-color:#ff3355;border-right-color:#ff3355}
.hero-btn-play,.btn-play{background:linear-gradient(135deg,#b81d3c,#ff3355) !important;color:#ffffff !important}
/* ══ إصلاح أيقونات شريط الأقسام ══ */
.cat-nav-btn i,.cat-nav-btn svg,.cat-nav-btn .lcn,.cat-nav-btn .lcn svg{
 color:#dda5b1 !important;stroke:currentColor !important;fill:none !important;
 width:1em;height:1em;flex-shrink:0;opacity:.9;transition:color .2s}
.cat-nav-btn:hover i,.cat-nav-btn:hover svg,.cat-nav-btn:hover .lcn,.cat-nav-btn:hover .lcn svg{
 color:#ffe8ed !important;opacity:1}
.cat-nav-btn.active i,.cat-nav-btn.active svg,.cat-nav-btn.active .lcn,.cat-nav-btn.active .lcn svg{
 color:#ffffff !important;opacity:1}
.cat-nav-btn .lcn{display:inline-flex;align-items:center;justify-content:center;line-height:0}
/* أيقونات القائمة الجانبية للأقسام */
.shs-catmenu-item i,.shs-catmenu-item svg,.shs-catmenu-item .lcn svg{
 color:#ff3355 !important;stroke:currentColor !important;fill:none !important}
/* أيقونات أزرار التنقّل العلوية */
.nav-btn i,.nav-btn svg,.nav-btn .lcn svg{stroke:currentColor !important;fill:none !important}`
    },
    arctic: {
        name: 'Arctic Frost',
        css: `:root{--red:#0e7490;--redg:rgba(14,116,144,0.25);--gold:#06b6d4;--s0:#eef4f8;--s1:#ffffff;--s2:#e6eef4;--s3:#d6e3ec;--s4:#c2d4e0;--br:rgba(0,0,0,.1);--brh:rgba(0,0,0,.2);--t1:#0c2733;--t2:#3d5a66;--t3:#8aa3ad;--r1:12px;--sw:250px}

/* خلفية */
html{background:#eef4f8}
body{background:#eef4f8 !important;color:#0c2733;position:relative;min-height:100vh}
body::before{content:"";position:fixed;inset:0;z-index:-2;pointer-events:none;background:
  radial-gradient(60% 50% at 20% 10%,rgba(14,116,144,0.06),transparent 62%),
  radial-gradient(60% 50% at 85% 82%,rgba(6,182,212,0.05),transparent 62%);
  animation:thDrift 30s ease-in-out infinite alternate}
@keyframes thDrift{0%{transform:scale(1)}100%{transform:scale(1.08)}}
body,.si,.fi,.fs,td,th,p{-webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale}
.stitle,.tbtitle,.sbrand-name{color:#0c2733;font-weight:750;letter-spacing:-.01em}
.card p,.sc p{color:#3d5a66;line-height:1.75}
.snl{color:#8aa3ad;letter-spacing:.08em;font-weight:600}

/* ══ لوحة التحكم ══ */
.sidebar{background:rgba(255,255,255,.85) !important;-webkit-backdrop-filter:blur(22px) saturate(150%);backdrop-filter:blur(22px) saturate(150%);border-left:1px solid rgba(0,0,0,.1) !important;box-shadow:-1px 0 40px rgba(15,23,42,.1)}
.sidebar::after{background:linear-gradient(180deg,rgba(14,116,144,0.6),transparent)}
.sbrand{background:rgba(0,0,0,.03);border-bottom:1px solid rgba(0,0,0,.1)}
.sbrand-icon{background:linear-gradient(135deg,#06b6d4,#0e7490);color:#ffffff;box-shadow:0 4px 16px rgba(14,116,144,0.3)}
.sbrand-sub{color:#8aa3ad}
.si{color:#3d5a66;font-weight:600}
.si:hover{background:rgba(0,0,0,.06);color:#0c2733}
.si.on{background:rgba(14,116,144,0.12);color:#0c2733}
.si.on::before{background:#0e7490}
.si.on .si-ic{color:#0e7490}
.topbar{background:rgba(255,255,255,.72) !important;-webkit-backdrop-filter:blur(22px) saturate(150%);backdrop-filter:blur(22px) saturate(150%);border-bottom:1px solid rgba(0,0,0,.1) !important}
.main{background:transparent !important}
.card,.tw,.swc,.bkc,.sc,.mbox,.pcard,.theme-panel{background:rgba(255,255,255,.75) !important;-webkit-backdrop-filter:blur(16px) saturate(140%) !important;backdrop-filter:blur(16px) saturate(140%) !important;border:1px solid rgba(0,0,0,.1) !important;border-radius:14px !important;box-shadow:0 8px 30px rgba(15,23,42,.1),inset 0 1px 0 rgba(255,255,255,.9) !important}
.sc:hover{background:rgba(255,255,255,.9) !important;border-color:rgba(0,0,0,.2) !important;box-shadow:0 12px 38px rgba(15,23,42,.16),inset 0 1px 0 rgba(255,255,255,.9) !important}
.fi,.fs{background:rgba(0,0,0,.03) !important;border:1px solid rgba(0,0,0,.1) !important;color:#0c2733 !important;border-radius:10px}
.fi::placeholder{color:#8aa3ad}
.fi:focus,.fs:focus{border-color:#0e7490 !important;background:rgba(0,0,0,.05) !important;box-shadow:0 0 0 3px rgba(14,116,144,0.18) !important}
.btn-p{background:linear-gradient(135deg,#06b6d4,#0e7490) !important;border:none !important;color:#ffffff !important;font-weight:700;border-radius:10px;box-shadow:0 4px 18px rgba(14,116,144,0.32)}
.btn-p:hover{filter:brightness(1.1);box-shadow:0 6px 24px rgba(14,116,144,0.45)}
.ib{background:rgba(0,0,0,.06);border:1px solid rgba(0,0,0,.1);color:#3d5a66;border-radius:9px}
.ib:hover{background:rgba(0,0,0,.12);color:#0c2733;border-color:rgba(0,0,0,.2)}
.uavt{background:linear-gradient(135deg,#06b6d4,#0e7490);color:#ffffff}
.lic-dot{background:#06b6d4;box-shadow:0 0 8px rgba(6,182,212,0.7)}
.lic-b{background:rgba(0,0,0,.05);border-color:rgba(0,0,0,.1);color:#3d5a66}
.mhd,.mfooter{background:rgba(0,0,0,.035);border-color:rgba(0,0,0,.1)}
thead tr{background:rgba(0,0,0,.05)}
th{color:#8aa3ad;font-weight:700;letter-spacing:.04em}
td{color:#0c2733;border-color:rgba(0,0,0,.06)}
tr:hover td{background:rgba(0,0,0,.045)}
.pw .pb{background:linear-gradient(90deg,#06b6d4,#0e7490)}
.mbd,#pm{background:rgba(255,255,255,.5) !important;-webkit-backdrop-filter:blur(14px) !important;backdrop-filter:blur(14px) !important}
.al-s{background:rgba(6,182,212,0.12);border-color:rgba(6,182,212,0.4);color:#06b6d4}
@keyframes fu{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}

/* ══ الموقع العام index.php ══ */
.navbar{background:rgba(255,255,255,.72) !important;-webkit-backdrop-filter:blur(22px) saturate(155%) !important;backdrop-filter:blur(22px) saturate(155%) !important;border-bottom:1px solid rgba(0,0,0,.1) !important}
.nav-logo-text{color:#0e7490 !important}
.nav-btn{background:rgba(0,0,0,.07) !important;border:1px solid rgba(0,0,0,.1) !important;color:#3d5a66 !important}
.nav-btn:hover{background:rgba(14,116,144,0.22) !important;border-color:#0e7490 !important;color:#0c2733 !important}
.search-wrap input{background:rgba(0,0,0,.05) !important;border:1px solid rgba(0,0,0,.1) !important;color:#0c2733 !important}
.search-wrap input::placeholder{color:#8aa3ad}
.search-wrap input:focus{background:rgba(0,0,0,.09) !important;border-color:#0e7490 !important}
.cat-navbar{background:rgba(255,255,255,.72) !important;-webkit-backdrop-filter:blur(20px) saturate(150%) !important;backdrop-filter:blur(20px) saturate(150%) !important;border-bottom:1px solid rgba(0,0,0,.1) !important}
.cat-nav-btn{background:rgba(0,0,0,.055) !important;border:1px solid rgba(0,0,0,.1) !important;color:#3d5a66 !important;font-weight:650 !important}
.cat-nav-btn:hover{background:rgba(0,0,0,.11) !important;border-color:rgba(0,0,0,.2) !important;color:#0c2733 !important}
.cat-nav-btn.active{background:linear-gradient(135deg,#06b6d4,#0e7490) !important;border-color:transparent !important;color:#ffffff !important;box-shadow:0 4px 16px rgba(14,116,144,0.38) !important}
.fp-panel,.np-panel,.m3u-panel,.ep-panel,.shs-catmenu-panel{background:rgba(255,255,255,.85) !important;-webkit-backdrop-filter:blur(28px) saturate(155%) !important;backdrop-filter:blur(28px) saturate(155%) !important;border-left:1px solid rgba(0,0,0,.1) !important;box-shadow:0 0 50px rgba(15,23,42,.16) !important}
.panel-overlay,.shs-catmenu-overlay,.tmdb-modal-overlay{background:rgba(255,255,255,.35) !important;-webkit-backdrop-filter:blur(8px) !important;backdrop-filter:blur(8px) !important}
.netflix-card,.nx-card,.ch-card,.sr-card{background:rgba(255,255,255,.75) !important;border:1px solid rgba(0,0,0,.1) !important;border-radius:12px !important;box-shadow:0 6px 24px rgba(15,23,42,.1) !important}
.netflix-card:hover,.nx-card:hover{border-color:rgba(14,116,144,0.5) !important;box-shadow:0 12px 36px rgba(15,23,42,.16),0 0 0 1px rgba(14,116,144,0.25) !important}
.shs-catview-banner{background:rgba(255,255,255,.75) !important;border:1px solid rgba(0,0,0,.1) !important;-webkit-backdrop-filter:blur(16px) saturate(140%);backdrop-filter:blur(16px) saturate(140%)}
.shs-catmenu-item:hover{background:rgba(0,0,0,.07)}
.shs-empty-ico{background:radial-gradient(120% 120% at 30% 20%,rgba(14,116,144,0.16),rgba(0,0,0,.03));color:#0e7490}
.shs-spinner::before{border-top-color:#0e7490;border-right-color:#0e7490}
.hero-btn-play,.btn-play{background:linear-gradient(135deg,#06b6d4,#0e7490) !important;color:#ffffff !important}
/* ══ إصلاح أيقونات شريط الأقسام ══ */
.cat-nav-btn i,.cat-nav-btn svg,.cat-nav-btn .lcn,.cat-nav-btn .lcn svg{
 color:#3d5a66 !important;stroke:currentColor !important;fill:none !important;
 width:1em;height:1em;flex-shrink:0;opacity:.9;transition:color .2s}
.cat-nav-btn:hover i,.cat-nav-btn:hover svg,.cat-nav-btn:hover .lcn,.cat-nav-btn:hover .lcn svg{
 color:#0c2733 !important;opacity:1}
.cat-nav-btn.active i,.cat-nav-btn.active svg,.cat-nav-btn.active .lcn,.cat-nav-btn.active .lcn svg{
 color:#ffffff !important;opacity:1}
.cat-nav-btn .lcn{display:inline-flex;align-items:center;justify-content:center;line-height:0}
/* أيقونات القائمة الجانبية للأقسام */
.shs-catmenu-item i,.shs-catmenu-item svg,.shs-catmenu-item .lcn svg{
 color:#0e7490 !important;stroke:currentColor !important;fill:none !important}
/* أيقونات أزرار التنقّل العلوية */
.nav-btn i,.nav-btn svg,.nav-btn .lcn svg{stroke:currentColor !important;fill:none !important}`
    },
    glass: {
        name: 'Glass Blur',
        css: `:root{--red:#6ea8fe;--redg:rgba(110,168,254,.28);--gold:#8fb8ff;--s0:#080b12;--s1:rgba(255,255,255,.045);--s2:rgba(255,255,255,.06);--s3:rgba(255,255,255,.08);--s4:rgba(255,255,255,.11);--br:rgba(255,255,255,.1);--brh:rgba(255,255,255,.2);--t1:#f4f7fc;--t2:#aeb9cc;--t3:#71809a;--r1:12px}

/* ═══ الخلفية: أسود مزرق هادئ + توهّجات خافتة ═══ */
html{background:#080b12}
body{background:#080b12 !important;position:relative;min-height:100vh;color:var(--t1)}
body::before{content:"";position:fixed;inset:0;z-index:-2;pointer-events:none;
 background:
  radial-gradient(70% 55% at 18% 8%,rgba(78,132,255,.16),transparent 62%),
  radial-gradient(60% 50% at 88% 18%,rgba(126,95,255,.13),transparent 62%),
  radial-gradient(65% 55% at 78% 92%,rgba(56,150,220,.11),transparent 64%),
  radial-gradient(55% 45% at 10% 88%,rgba(96,110,190,.1),transparent 62%);
 animation:glassDrift 30s ease-in-out infinite alternate}
@keyframes glassDrift{0%{transform:scale(1)}100%{transform:scale(1.1)}}

/* ═══ الطباعة: واضحة واحترافية ═══ */
body,.si,.fi,.fs,td,th,p{-webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale}
.stitle,.tbtitle,.sbrand-name{color:#fff;font-weight:750;letter-spacing:-.01em}
.card p,.sc p{color:var(--t2);line-height:1.75;font-size:.86rem}
.snl{color:var(--t3);letter-spacing:.08em;font-weight:600}

/* ═══ الأسطح الزجاجية ═══ */
.sidebar{background:rgba(14,18,28,.72) !important;-webkit-backdrop-filter:blur(24px) saturate(140%);backdrop-filter:blur(24px) saturate(140%);border-left:1px solid rgba(255,255,255,.08) !important;box-shadow:-1px 0 40px rgba(0,0,0,.5)}
.sidebar::after{background:linear-gradient(180deg,rgba(110,168,254,.5),transparent)}
.sbrand{background:rgba(255,255,255,.03);border-bottom:1px solid rgba(255,255,255,.07)}
.sbrand-icon{background:linear-gradient(135deg,#4d7fe0,#6ea8fe);color:#fff;box-shadow:0 4px 16px rgba(110,168,254,.28)}
.sbrand-sub{color:var(--t3)}
.si{color:var(--t2);font-weight:600}
.si:hover{background:rgba(255,255,255,.055);color:#fff}
.si.on{background:rgba(110,168,254,.11);color:#fff}
.si.on::before{background:#6ea8fe}
.si.on .si-ic{color:#6ea8fe}

.topbar{background:rgba(12,16,25,.7) !important;-webkit-backdrop-filter:blur(22px) saturate(140%);backdrop-filter:blur(22px) saturate(140%);border-bottom:1px solid rgba(255,255,255,.08) !important}
.main{background:transparent !important}

/* البطاقات — زجاج داكن هادئ */
.card,.tw,.swc,.bkc,.sc,.mbox,.pcard,.theme-panel{
 background:rgba(255,255,255,.045) !important;
 -webkit-backdrop-filter:blur(18px) saturate(140%) !important;backdrop-filter:blur(18px) saturate(140%) !important;
 border:1px solid rgba(255,255,255,.09) !important;border-radius:14px !important;
 box-shadow:0 8px 30px rgba(0,0,0,.35),inset 0 1px 0 rgba(255,255,255,.07) !important}
.sc:hover{background:rgba(255,255,255,.07) !important;border-color:rgba(255,255,255,.18) !important;box-shadow:0 12px 38px rgba(0,0,0,.42),inset 0 1px 0 rgba(255,255,255,.1) !important}

/* الحقول */
.fi,.fs{background:rgba(255,255,255,.05) !important;border:1px solid rgba(255,255,255,.11) !important;color:#f4f7fc !important;border-radius:10px}
.fi::placeholder{color:#66748c}
.fi:focus,.fs:focus{border-color:#6ea8fe !important;background:rgba(255,255,255,.08) !important;box-shadow:0 0 0 3px rgba(110,168,254,.16) !important}

/* الأزرار */
.btn-p{background:linear-gradient(135deg,#4d7fe0,#6ea8fe) !important;border:none !important;color:#fff !important;font-weight:700;border-radius:10px;box-shadow:0 4px 18px rgba(110,168,254,.3)}
.btn-p:hover{background:linear-gradient(135deg,#5b8dee,#82b6ff) !important;box-shadow:0 6px 24px rgba(110,168,254,.42)}
.ib{background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);color:var(--t2);border-radius:9px}
.ib:hover{background:rgba(255,255,255,.12);color:#fff;border-color:rgba(255,255,255,.22)}

.uavt{background:linear-gradient(135deg,#4d7fe0,#6ea8fe);color:#fff}
.lic-dot{background:#4ade80;box-shadow:0 0 8px rgba(74,222,128,.7)}
.lic-b{background:rgba(255,255,255,.05);border-color:rgba(255,255,255,.1);color:var(--t2)}
.mhd,.mfooter{background:rgba(255,255,255,.035);border-color:rgba(255,255,255,.08)}
thead tr{background:rgba(255,255,255,.05)}
th{color:var(--t3);font-weight:700;letter-spacing:.04em}
td{color:var(--t1);border-color:rgba(255,255,255,.06)}
tr:hover td{background:rgba(255,255,255,.045)}
.pw .pb{background:linear-gradient(90deg,#4d7fe0,#6ea8fe)}
.mbd,#pm{background:rgba(6,9,15,.82) !important;-webkit-backdrop-filter:blur(14px) !important;backdrop-filter:blur(14px) !important}
@keyframes fu{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}

/* ═══ الواجهة العامة index.php ═══ */
.navbar{background:rgba(12,16,25,.68) !important;-webkit-backdrop-filter:blur(24px) saturate(150%) !important;backdrop-filter:blur(24px) saturate(150%) !important;border-bottom:1px solid rgba(255,255,255,.08) !important}
.nav-logo-text{color:#fff !important}
.nav-btn{background:rgba(255,255,255,.07) !important;border:1px solid rgba(255,255,255,.11) !important;color:#e2e8f0 !important}
.nav-btn:hover{background:rgba(110,168,254,.2) !important;border-color:#6ea8fe !important;color:#fff !important}
.search-wrap input{background:rgba(255,255,255,.055) !important;border:1px solid rgba(255,255,255,.11) !important;color:#f4f7fc !important}
.search-wrap input::placeholder{color:#66748c}
.search-wrap input:focus{background:rgba(255,255,255,.09) !important;border-color:#6ea8fe !important}

.cat-navbar{background:rgba(12,16,25,.6) !important;-webkit-backdrop-filter:blur(20px) saturate(145%) !important;backdrop-filter:blur(20px) saturate(145%) !important;border-bottom:1px solid rgba(255,255,255,.07) !important}
.cat-nav-btn{background:rgba(255,255,255,.055) !important;border:1px solid rgba(255,255,255,.1) !important;color:#cbd5e1 !important;font-weight:650 !important}
.cat-nav-btn:hover{background:rgba(255,255,255,.11) !important;border-color:rgba(255,255,255,.2) !important;color:#fff !important}
.cat-nav-btn.active{background:linear-gradient(135deg,#4d7fe0,#6ea8fe) !important;border-color:transparent !important;color:#fff !important;box-shadow:0 4px 16px rgba(110,168,254,.35) !important}

.fp-panel,.np-panel,.m3u-panel,.ep-panel,.shs-catmenu-panel{background:rgba(14,18,28,.8) !important;-webkit-backdrop-filter:blur(28px) saturate(150%) !important;backdrop-filter:blur(28px) saturate(150%) !important;border-left:1px solid rgba(255,255,255,.09) !important;box-shadow:0 0 50px rgba(0,0,0,.6) !important}
.panel-overlay,.shs-catmenu-overlay{background:rgba(6,9,15,.6) !important;-webkit-backdrop-filter:blur(6px) !important;backdrop-filter:blur(6px) !important}

.netflix-card,.nx-card,.ch-card,.sr-card{background:rgba(255,255,255,.04) !important;border:1px solid rgba(255,255,255,.08) !important;border-radius:12px !important;box-shadow:0 6px 24px rgba(0,0,0,.35) !important}
.netflix-card:hover,.nx-card:hover{border-color:rgba(110,168,254,.45) !important;box-shadow:0 12px 36px rgba(0,0,0,.5),0 0 0 1px rgba(110,168,254,.2) !important}
.shs-catview-banner{background:rgba(255,255,255,.045) !important;border:1px solid rgba(255,255,255,.09) !important;-webkit-backdrop-filter:blur(18px) saturate(140%);backdrop-filter:blur(18px) saturate(140%)}
.tmdb-modal-overlay{background:rgba(6,9,15,.8) !important;-webkit-backdrop-filter:blur(12px) !important;backdrop-filter:blur(12px) !important}
/* ══ إصلاح أيقونات شريط الأقسام ══ */
.cat-nav-btn i,.cat-nav-btn svg,.cat-nav-btn .lcn,.cat-nav-btn .lcn svg{
 color:#aeb9cc !important;stroke:currentColor !important;fill:none !important;
 width:1em;height:1em;flex-shrink:0;opacity:.9;transition:color .2s}
.cat-nav-btn:hover i,.cat-nav-btn:hover svg,.cat-nav-btn:hover .lcn,.cat-nav-btn:hover .lcn svg{
 color:#f4f7fc !important;opacity:1}
.cat-nav-btn.active i,.cat-nav-btn.active svg,.cat-nav-btn.active .lcn,.cat-nav-btn.active .lcn svg{
 color:#ffffff !important;opacity:1}
.cat-nav-btn .lcn{display:inline-flex;align-items:center;justify-content:center;line-height:0}
/* أيقونات القائمة الجانبية للأقسام */
.shs-catmenu-item i,.shs-catmenu-item svg,.shs-catmenu-item .lcn svg{
 color:#6ea8fe !important;stroke:currentColor !important;fill:none !important}
/* أيقونات أزرار التنقّل العلوية */
.nav-btn i,.nav-btn svg,.nav-btn .lcn svg{stroke:currentColor !important;fill:none !important}`
    },
    win11: {
        name: 'Windows 11',
        css: `:root{--red:#4cc2ff;--redg:rgba(76,194,255,0.28);--gold:#0078d4;--s0:#202020;--s1:#2b2b2b;--s2:#323232;--s3:#3a3a3a;--s4:#454545;--br:rgba(255,255,255,.09);--brh:rgba(255,255,255,.2);--t1:#ffffff;--t2:#c5c5c5;--t3:#8a8a8a;--r1:8px}

/* خلفية */
html{background:#202020}
body{background:#202020 !important;color:#ffffff;position:relative;min-height:100vh}
body::before{content:"";position:fixed;inset:0;z-index:-2;pointer-events:none;background:
  radial-gradient(60% 50% at 12% 8%,rgba(0,120,212,0.13),transparent 62%),
  radial-gradient(60% 50% at 88% 12%,rgba(76,194,255,0.09),transparent 62%),
  radial-gradient(60% 50% at 70% 90%,rgba(142,140,216,0.08),transparent 62%),
  radial-gradient(60% 50% at 20% 88%,rgba(194,57,179,0.05),transparent 62%);
  animation:thDrift 30s ease-in-out infinite alternate}
@keyframes thDrift{0%{transform:scale(1)}100%{transform:scale(1.08)}}
body,.si,.fi,.fs,td,th,p{-webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale}
.stitle,.tbtitle,.sbrand-name{color:#ffffff;font-weight:750;letter-spacing:-.01em}
.card p,.sc p{color:#c5c5c5;line-height:1.75}
.snl{color:#8a8a8a;letter-spacing:.08em;font-weight:600}

/* ══ لوحة التحكم ══ */
.sidebar{background:rgba(43,43,43,0.82) !important;-webkit-backdrop-filter:blur(26px) saturate(150%);backdrop-filter:blur(26px) saturate(150%);border-left:1px solid rgba(255,255,255,.09) !important;box-shadow:-1px 0 40px rgba(0,0,0,.4)}
.sidebar::after{background:linear-gradient(180deg,rgba(76,194,255,0.6),transparent)}
.sbrand{background:rgba(255,255,255,.03);border-bottom:1px solid rgba(255,255,255,.09)}
.sbrand-icon{background:linear-gradient(135deg,#0078d4,#4cc2ff);color:#ffffff;box-shadow:0 4px 16px rgba(76,194,255,0.3)}
.sbrand-sub{color:#8a8a8a}
.si{color:#c5c5c5;font-weight:600}
.si:hover{background:rgba(255,255,255,.06);color:#ffffff}
.si.on{background:rgba(76,194,255,0.12);color:#ffffff}
.si.on::before{background:#4cc2ff}
.si.on .si-ic{color:#4cc2ff}
.topbar{background:rgba(43,43,43,0.72) !important;-webkit-backdrop-filter:blur(26px) saturate(150%);backdrop-filter:blur(26px) saturate(150%);border-bottom:1px solid rgba(255,255,255,.09) !important}
.main{background:transparent !important}
.card,.tw,.swc,.bkc,.sc,.mbox,.pcard,.theme-panel{background:rgba(255,255,255,.05) !important;-webkit-backdrop-filter:blur(20px) saturate(140%) !important;backdrop-filter:blur(20px) saturate(140%) !important;border:1px solid rgba(255,255,255,.09) !important;border-radius:10px !important;box-shadow:0 8px 30px rgba(0,0,0,.4),inset 0 1px 0 rgba(255,255,255,.06) !important}
.sc:hover{background:rgba(255,255,255,.08) !important;border-color:rgba(255,255,255,.2) !important;box-shadow:0 12px 38px rgba(0,0,0,.55),inset 0 1px 0 rgba(255,255,255,.06) !important}
.fi,.fs{background:rgba(255,255,255,.05) !important;border:1px solid rgba(255,255,255,.09) !important;color:#ffffff !important;border-radius:6px}
.fi::placeholder{color:#8a8a8a}
.fi:focus,.fs:focus{border-color:#4cc2ff !important;background:rgba(255,255,255,.08) !important;box-shadow:0 0 0 3px rgba(76,194,255,0.18) !important}
.btn-p{background:linear-gradient(135deg,#0078d4,#4cc2ff) !important;border:none !important;color:#ffffff !important;font-weight:700;border-radius:6px;box-shadow:0 4px 18px rgba(76,194,255,0.32)}
.btn-p:hover{filter:brightness(1.1);box-shadow:0 6px 24px rgba(76,194,255,0.45)}
.ib{background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.09);color:#c5c5c5;border-radius:5px}
.ib:hover{background:rgba(255,255,255,.12);color:#ffffff;border-color:rgba(255,255,255,.2)}
.uavt{background:linear-gradient(135deg,#0078d4,#4cc2ff);color:#ffffff}
.lic-dot{background:#6ccb5f;box-shadow:0 0 8px rgba(108,203,95,0.7)}
.lic-b{background:rgba(255,255,255,.05);border-color:rgba(255,255,255,.09);color:#c5c5c5}
.mhd,.mfooter{background:rgba(255,255,255,.035);border-color:rgba(255,255,255,.09)}
thead tr{background:rgba(255,255,255,.05)}
th{color:#8a8a8a;font-weight:700;letter-spacing:.04em}
td{color:#ffffff;border-color:rgba(255,255,255,.06)}
tr:hover td{background:rgba(255,255,255,.045)}
.pw .pb{background:linear-gradient(90deg,#0078d4,#4cc2ff)}
.mbd,#pm{background:rgba(0,0,0,.8) !important;-webkit-backdrop-filter:blur(14px) !important;backdrop-filter:blur(14px) !important}
.al-s{background:rgba(108,203,95,0.12);border-color:rgba(108,203,95,0.4);color:#6ccb5f}
@keyframes fu{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}

/* ══ الموقع العام index.php ══ */
.navbar{background:rgba(43,43,43,0.72) !important;-webkit-backdrop-filter:blur(26px) saturate(155%) !important;backdrop-filter:blur(26px) saturate(155%) !important;border-bottom:1px solid rgba(255,255,255,.09) !important}
.nav-logo-text{color:#4cc2ff !important}
.nav-btn{background:rgba(255,255,255,.07) !important;border:1px solid rgba(255,255,255,.09) !important;color:#c5c5c5 !important}
.nav-btn:hover{background:rgba(76,194,255,0.22) !important;border-color:#4cc2ff !important;color:#ffffff !important}
.search-wrap input{background:rgba(255,255,255,.05) !important;border:1px solid rgba(255,255,255,.09) !important;color:#ffffff !important}
.search-wrap input::placeholder{color:#8a8a8a}
.search-wrap input:focus{background:rgba(255,255,255,.09) !important;border-color:#4cc2ff !important}
.cat-navbar{background:rgba(43,43,43,0.72) !important;-webkit-backdrop-filter:blur(24px) saturate(150%) !important;backdrop-filter:blur(24px) saturate(150%) !important;border-bottom:1px solid rgba(255,255,255,.09) !important}
.cat-nav-btn{background:rgba(255,255,255,.055) !important;border:1px solid rgba(255,255,255,.09) !important;color:#c5c5c5 !important;font-weight:650 !important}
.cat-nav-btn:hover{background:rgba(255,255,255,.11) !important;border-color:rgba(255,255,255,.2) !important;color:#ffffff !important}
.cat-nav-btn.active{background:linear-gradient(135deg,#0078d4,#4cc2ff) !important;border-color:transparent !important;color:#ffffff !important;box-shadow:0 4px 16px rgba(76,194,255,0.38) !important}
.fp-panel,.np-panel,.m3u-panel,.ep-panel,.shs-catmenu-panel{background:rgba(43,43,43,0.82) !important;-webkit-backdrop-filter:blur(32px) saturate(155%) !important;backdrop-filter:blur(32px) saturate(155%) !important;border-left:1px solid rgba(255,255,255,.09) !important;box-shadow:0 0 50px rgba(0,0,0,.55) !important}
.panel-overlay,.shs-catmenu-overlay,.tmdb-modal-overlay{background:rgba(0,0,0,.65) !important;-webkit-backdrop-filter:blur(8px) !important;backdrop-filter:blur(8px) !important}
.netflix-card,.nx-card,.ch-card,.sr-card{background:rgba(255,255,255,.05) !important;border:1px solid rgba(255,255,255,.09) !important;border-radius:8px !important;box-shadow:0 6px 24px rgba(0,0,0,.4) !important}
.netflix-card:hover,.nx-card:hover{border-color:rgba(76,194,255,0.5) !important;box-shadow:0 12px 36px rgba(0,0,0,.55),0 0 0 1px rgba(76,194,255,0.25) !important}
.shs-catview-banner{background:rgba(255,255,255,.05) !important;border:1px solid rgba(255,255,255,.09) !important;-webkit-backdrop-filter:blur(20px) saturate(140%);backdrop-filter:blur(20px) saturate(140%)}
.shs-catmenu-item:hover{background:rgba(255,255,255,.07)}
.shs-empty-ico{background:radial-gradient(120% 120% at 30% 20%,rgba(76,194,255,0.16),rgba(255,255,255,.03));color:#4cc2ff}
.shs-spinner::before{border-top-color:#4cc2ff;border-right-color:#4cc2ff}
.hero-btn-play,.btn-play{background:linear-gradient(135deg,#0078d4,#4cc2ff) !important;color:#ffffff !important}
/* Windows 11 — Mica + زوايا ناعمة */
.card,.tw,.swc,.bkc,.sc,.mbox,.pcard,.netflix-card,.nx-card{border-radius:8px !important}
.btn-p{border-radius:4px !important;font-weight:600}
.fi,.fs{border-radius:4px}
.si{border-radius:5px;margin:2px 6px}
.si.on::before{width:3px;border-radius:3px;top:22%;height:56%}
.nav-btn,.ib{border-radius:5px}
.cat-nav-btn{border-radius:5px !important}
/* ══ إصلاح أيقونات شريط الأقسام ══ */
.cat-nav-btn i,.cat-nav-btn svg,.cat-nav-btn .lcn,.cat-nav-btn .lcn svg{
 color:#c5c5c5 !important;stroke:currentColor !important;fill:none !important;
 width:1em;height:1em;flex-shrink:0;opacity:.9;transition:color .2s}
.cat-nav-btn:hover i,.cat-nav-btn:hover svg,.cat-nav-btn:hover .lcn,.cat-nav-btn:hover .lcn svg{
 color:#ffffff !important;opacity:1}
.cat-nav-btn.active i,.cat-nav-btn.active svg,.cat-nav-btn.active .lcn,.cat-nav-btn.active .lcn svg{
 color:#ffffff !important;opacity:1}
.cat-nav-btn .lcn{display:inline-flex;align-items:center;justify-content:center;line-height:0}
/* أيقونات القائمة الجانبية للأقسام */
.shs-catmenu-item i,.shs-catmenu-item svg,.shs-catmenu-item .lcn svg{
 color:#4cc2ff !important;stroke:currentColor !important;fill:none !important}
/* أيقونات أزرار التنقّل العلوية */
.nav-btn i,.nav-btn svg,.nav-btn .lcn svg{stroke:currentColor !important;fill:none !important}`
    },
    sierra: {
        name: 'macOS Sierra',
        css: `:root{--red:#007aff;--redg:rgba(0,122,255,.28);--gold:#5856d6;--s0:#e8ecf3;--s1:rgba(255,255,255,.62);--s2:rgba(255,255,255,.5);--s3:rgba(255,255,255,.42);--s4:rgba(0,0,0,.06);--br:rgba(0,0,0,.09);--brh:rgba(0,0,0,.16);--t1:#1d1d1f;--t2:#57606e;--t3:#8e949e;--r1:8px;--sw:250px}

/* خط النظام San Francisco */
body,.si,.fi,.fs,.btn-p,td,th,p,input,select,textarea,button{
 font-family:-apple-system,BlinkMacSystemFont,"SF Pro Text","SF Pro Display","Helvetica Neue","Segoe UI",system-ui,sans-serif !important;
 -webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale}

/* خلفية سطح المكتب المتدرّجة — تظهر خلف الزجاج */
html{background:#7b8fb5}
body{background:linear-gradient(160deg,#6ea8dc 0%,#8f9fd4 26%,#b79ccb 50%,#e0a9b4 72%,#f3c39b 100%) !important;
 background-attachment:fixed !important;color:var(--t1);position:relative;min-height:100vh}
body::before{content:"";position:fixed;inset:-8%;z-index:-2;pointer-events:none;background:
 radial-gradient(48% 42% at 14% 12%,rgba(90,200,250,.5),transparent 66%),
 radial-gradient(46% 40% at 86% 16%,rgba(175,142,222,.42),transparent 66%),
 radial-gradient(52% 46% at 78% 88%,rgba(255,159,90,.34),transparent 68%),
 radial-gradient(46% 42% at 16% 86%,rgba(88,86,214,.3),transparent 66%);
 filter:blur(18px) saturate(120%);
 animation:macAurora 34s ease-in-out infinite alternate}
@keyframes macAurora{0%{transform:scale(1) translate(0,0)}100%{transform:scale(1.1) translate(-1.5%,-1.5%)}}

/* الطباعة */
.stitle,.tbtitle,.sbrand-name{color:var(--t1);font-weight:600;letter-spacing:-.015em}
.stitle{font-size:1.02rem}
.card p,.sc p{color:var(--t2);line-height:1.6;font-size:.85rem}
.snl{color:var(--t3);font-size:.68rem;font-weight:600;letter-spacing:.04em;text-transform:uppercase}

/* ══ الشريط الجانبي — Vibrancy رمادي مائل (توقيع macOS) ══ */
.sidebar{background:rgba(238,241,246,.68) !important;
 -webkit-backdrop-filter:blur(45px) saturate(190%);backdrop-filter:blur(45px) saturate(190%);
 border-left:1px solid rgba(0,0,0,.08) !important;box-shadow:none}
.sidebar::after{display:none}
.sbrand{background:transparent;border-bottom:1px solid rgba(0,0,0,.07);padding:14px 16px}
.sbrand-icon{background:linear-gradient(180deg,#3b9dff,#007aff);color:#fff;border-radius:7px;
 box-shadow:0 1px 3px rgba(0,0,0,.18),inset 0 1px 0 rgba(255,255,255,.35)}
.sbrand-name{font-size:.9rem}.sbrand-sub{color:var(--t3);font-size:.68rem}
.si{color:#2b3138;font-weight:500;font-size:.85rem;border-radius:6px;margin:1px 8px;padding:6px 10px;transition:none}
.si:hover{background:rgba(0,0,0,.06);color:#000}
.si.on{background:linear-gradient(180deg,#3b9dff,#0a6fe0) !important;color:#fff !important;
 box-shadow:0 1px 2px rgba(0,0,0,.18)}
.si.on .si-ic{color:#fff !important}
.si.on::before{display:none}
.si-ic{opacity:.85}

/* ══ الشريط العلوي — نافذة macOS ══ */
.topbar{background:rgba(246,248,251,.66) !important;
 -webkit-backdrop-filter:blur(45px) saturate(190%);backdrop-filter:blur(45px) saturate(190%);
 border-bottom:1px solid rgba(0,0,0,.09) !important;box-shadow:0 .5px 0 rgba(255,255,255,.7) inset}
.tbtitle{font-size:.88rem;font-weight:600}
.main{background:transparent !important}

/* ══ البطاقات — لوح زجاجي بحواف Apple ══ */
.card,.tw,.swc,.bkc,.sc,.mbox,.pcard,.theme-panel{
 background:rgba(255,255,255,.6) !important;
 -webkit-backdrop-filter:blur(30px) saturate(180%) !important;backdrop-filter:blur(30px) saturate(180%) !important;
 border:.5px solid rgba(0,0,0,.1) !important;border-radius:12px !important;
 box-shadow:0 .5px 1px rgba(0,0,0,.06),0 8px 24px rgba(30,40,70,.12),inset 0 1px 0 rgba(255,255,255,.75) !important}
.sc:hover{background:rgba(255,255,255,.75) !important;
 box-shadow:0 .5px 1px rgba(0,0,0,.07),0 12px 32px rgba(30,40,70,.16),inset 0 1px 0 rgba(255,255,255,.85) !important;
 transform:translateY(-1px)}

/* ══ الحقول — Apple text field ══ */
.fi,.fs{background:rgba(255,255,255,.78) !important;border:.5px solid rgba(0,0,0,.16) !important;
 color:var(--t1) !important;border-radius:6px;font-size:.85rem;padding:6px 10px;
 box-shadow:inset 0 1px 2px rgba(0,0,0,.05)}
.fi::placeholder{color:#a3a9b3}
.fi:focus,.fs:focus{border-color:#007aff !important;background:#fff !important;
 box-shadow:0 0 0 3.5px rgba(0,122,255,.28) !important;outline:none}

/* ══ الأزرار — Apple push button ══ */
.btn-p{background:linear-gradient(180deg,#3b9dff,#007aff) !important;border:.5px solid rgba(0,80,180,.5) !important;
 color:#fff !important;font-weight:500 !important;font-size:.82rem;border-radius:6px;padding:6px 14px;
 box-shadow:0 1px 2px rgba(0,0,0,.14),inset 0 1px 0 rgba(255,255,255,.3) !important;letter-spacing:0}
.btn-p:hover{background:linear-gradient(180deg,#4aa6ff,#0a84ff) !important;filter:none}
.btn-p:active{background:linear-gradient(180deg,#0071ee,#0062d6) !important;box-shadow:inset 0 1px 3px rgba(0,0,0,.2) !important}
.ib{background:linear-gradient(180deg,#fff,#f2f4f7);border:.5px solid rgba(0,0,0,.16);color:#2b3138;
 border-radius:6px;font-size:.8rem;box-shadow:0 1px 1.5px rgba(0,0,0,.07)}
.ib:hover{background:linear-gradient(180deg,#fff,#e9ecf1);color:#000}

.uavt{background:linear-gradient(180deg,#3b9dff,#007aff);color:#fff;box-shadow:0 1px 3px rgba(0,0,0,.18)}
.lic-dot{background:#34c759;box-shadow:0 0 6px rgba(52,199,89,.6)}
.lic-b{background:rgba(255,255,255,.7);border-color:rgba(0,0,0,.1);color:var(--t2)}
.mhd,.mfooter{background:rgba(248,250,252,.7);border-color:rgba(0,0,0,.08)}
.mbox{border-radius:12px !important}
thead tr{background:rgba(0,0,0,.035)}
th{color:var(--t3);font-weight:600;font-size:.72rem;letter-spacing:.03em}
td{color:var(--t1);border-color:rgba(0,0,0,.06);font-size:.84rem}
tr:hover td{background:rgba(0,122,255,.07)}
.pw .pb{background:linear-gradient(90deg,#0a84ff,#5ac8fa);border-radius:99px}
.mbd,#pm{background:rgba(190,200,215,.45) !important;
 -webkit-backdrop-filter:blur(22px) saturate(150%) !important;backdrop-filter:blur(22px) saturate(150%) !important}
.al-s{background:rgba(52,199,89,.14);border-color:rgba(52,199,89,.4);color:#1a7f37}
.al-e{background:rgba(255,59,48,.12);border-color:rgba(255,59,48,.4);color:#c0392b}
@keyframes fu{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}

/* ══ الموقع العام index.php ══ */
.navbar{background:rgba(246,248,251,.62) !important;
 -webkit-backdrop-filter:blur(45px) saturate(190%) !important;backdrop-filter:blur(45px) saturate(190%) !important;
 border-bottom:1px solid rgba(0,0,0,.09) !important}
.nav-logo-text{color:#007aff !important;font-weight:600;letter-spacing:-.02em}
.nav-btn{background:linear-gradient(180deg,#fff,#f2f4f7) !important;border:.5px solid rgba(0,0,0,.16) !important;
 color:#2b3138 !important;box-shadow:0 1px 1.5px rgba(0,0,0,.07) !important}
.nav-btn:hover{background:linear-gradient(180deg,#3b9dff,#007aff) !important;border-color:rgba(0,80,180,.5) !important;color:#fff !important;transform:none}
.search-wrap input{background:rgba(255,255,255,.8) !important;border:.5px solid rgba(0,0,0,.16) !important;
 color:var(--t1) !important;border-radius:99px;box-shadow:inset 0 1px 2px rgba(0,0,0,.05)}
.search-wrap input::placeholder{color:#a3a9b3}
.search-wrap input:focus{background:#fff !important;border-color:#007aff !important;box-shadow:0 0 0 3.5px rgba(0,122,255,.28) !important}
.search-wrap .si{color:#a3a9b3}

.cat-navbar{background:rgba(246,248,251,.55) !important;
 -webkit-backdrop-filter:blur(40px) saturate(180%) !important;backdrop-filter:blur(40px) saturate(180%) !important;
 border-bottom:1px solid rgba(0,0,0,.08) !important}
.cat-nav-btn{background:rgba(255,255,255,.72) !important;border:.5px solid rgba(0,0,0,.14) !important;
 color:#2b3138 !important;font-weight:500 !important;border-radius:6px !important;font-size:.82rem !important;
 box-shadow:0 1px 1.5px rgba(0,0,0,.06) !important}
.cat-nav-btn:hover{background:#fff !important;color:#000 !important;transform:none !important;box-shadow:0 1px 3px rgba(0,0,0,.1) !important}
.cat-nav-btn.active{background:linear-gradient(180deg,#3b9dff,#007aff) !important;
 border-color:rgba(0,80,180,.5) !important;color:#fff !important;
 box-shadow:0 1px 3px rgba(0,0,0,.18),inset 0 1px 0 rgba(255,255,255,.3) !important;transform:none !important}

.fp-panel,.np-panel,.m3u-panel,.ep-panel,.shs-catmenu-panel{
 background:rgba(240,243,248,.72) !important;
 -webkit-backdrop-filter:blur(50px) saturate(190%) !important;backdrop-filter:blur(50px) saturate(190%) !important;
 border-left:.5px solid rgba(0,0,0,.12) !important;box-shadow:-2px 0 30px rgba(30,40,70,.14) !important}
.panel-overlay,.shs-catmenu-overlay,.tmdb-modal-overlay{background:rgba(190,200,215,.4) !important;
 -webkit-backdrop-filter:blur(14px) !important;backdrop-filter:blur(14px) !important}
.shs-catmenu-item{color:#2b3138;border-radius:6px}
.shs-catmenu-item:hover{background:rgba(0,0,0,.06)}

.netflix-card,.nx-card,.ch-card,.sr-card{background:rgba(255,255,255,.62) !important;
 border:.5px solid rgba(0,0,0,.1) !important;border-radius:11px !important;
 box-shadow:0 .5px 1px rgba(0,0,0,.06),0 6px 18px rgba(30,40,70,.12) !important}
.netflix-card:hover,.nx-card:hover{background:rgba(255,255,255,.8) !important;
 box-shadow:0 .5px 1px rgba(0,0,0,.07),0 14px 34px rgba(30,40,70,.2) !important}
.shs-catview-banner{background:rgba(255,255,255,.6) !important;border:.5px solid rgba(0,0,0,.1) !important;
 border-radius:12px !important;-webkit-backdrop-filter:blur(30px) saturate(180%);backdrop-filter:blur(30px) saturate(180%)}
.shs-catview-name{color:var(--t1)}
.shs-empty-ico{background:rgba(0,122,255,.1);color:#007aff;box-shadow:none}
.shs-empty-title{color:var(--t1)}
.shs-spinner::before{border-top-color:#007aff;border-right-color:#007aff}
.hero-btn-play,.btn-play{background:linear-gradient(180deg,#3b9dff,#007aff) !important;color:#fff !important;border-radius:6px !important}
/* ══ إصلاح أيقونات شريط الأقسام ══ */
.cat-nav-btn i,.cat-nav-btn svg,.cat-nav-btn .lcn,.cat-nav-btn .lcn svg{
 color:#57606e !important;stroke:currentColor !important;fill:none !important;
 width:1em;height:1em;flex-shrink:0;opacity:.9;transition:color .2s}
.cat-nav-btn:hover i,.cat-nav-btn:hover svg,.cat-nav-btn:hover .lcn,.cat-nav-btn:hover .lcn svg{
 color:#1d1d1f !important;opacity:1}
.cat-nav-btn.active i,.cat-nav-btn.active svg,.cat-nav-btn.active .lcn,.cat-nav-btn.active .lcn svg{
 color:#ffffff !important;opacity:1}
.cat-nav-btn .lcn{display:inline-flex;align-items:center;justify-content:center;line-height:0}
/* أيقونات القائمة الجانبية للأقسام */
.shs-catmenu-item i,.shs-catmenu-item svg,.shs-catmenu-item .lcn svg{
 color:#007aff !important;stroke:currentColor !important;fill:none !important}
/* أيقونات أزرار التنقّل العلوية */
.nav-btn i,.nav-btn svg,.nav-btn .lcn svg{stroke:currentColor !important;fill:none !important}`
    },
    ps5: {
        name: 'PlayStation 5',
        css: `:root{--red:#2e6ff2;--redg:rgba(46,111,242,0.42);--gold:#0b3fbf;--s0:#0a0e17;--s1:#111726;--s2:#182033;--s3:#1f2941;--s4:#28344f;--br:rgba(255,255,255,.09);--brh:rgba(255,255,255,.2);--t1:#ffffff;--t2:#b4c0d6;--t3:#71809a;--r1:6px}

/* خلفية */
html{background:#0a0e17}
body{background:#0a0e17 !important;color:#ffffff;position:relative;min-height:100vh}
body::before{content:"";position:fixed;inset:0;z-index:-2;pointer-events:none;background:
  radial-gradient(60% 50% at 15% 10%,rgba(46,111,242,0.16),transparent 62%),
  radial-gradient(60% 50% at 88% 15%,rgba(0,209,255,0.1),transparent 62%),
  radial-gradient(60% 50% at 75% 90%,rgba(123,47,247,0.08),transparent 62%);
  animation:thDrift 30s ease-in-out infinite alternate}
@keyframes thDrift{0%{transform:scale(1)}100%{transform:scale(1.08)}}
body,.si,.fi,.fs,td,th,p{-webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale}
.stitle,.tbtitle,.sbrand-name{color:#ffffff;font-weight:750;letter-spacing:-.01em}
.card p,.sc p{color:#b4c0d6;line-height:1.75}
.snl{color:#71809a;letter-spacing:.08em;font-weight:600}

/* ══ لوحة التحكم ══ */
.sidebar{background:rgba(17,23,38,0.82) !important;-webkit-backdrop-filter:blur(18px) saturate(150%);backdrop-filter:blur(18px) saturate(150%);border-left:1px solid rgba(255,255,255,.09) !important;box-shadow:-1px 0 40px rgba(0,0,0,.4)}
.sidebar::after{background:linear-gradient(180deg,rgba(46,111,242,0.6),transparent)}
.sbrand{background:rgba(255,255,255,.03);border-bottom:1px solid rgba(255,255,255,.09)}
.sbrand-icon{background:linear-gradient(135deg,#0b3fbf,#2e6ff2);color:#ffffff;box-shadow:0 4px 16px rgba(46,111,242,0.3)}
.sbrand-sub{color:#71809a}
.si{color:#b4c0d6;font-weight:600}
.si:hover{background:rgba(255,255,255,.06);color:#ffffff}
.si.on{background:rgba(46,111,242,0.12);color:#ffffff}
.si.on::before{background:#2e6ff2}
.si.on .si-ic{color:#2e6ff2}
.topbar{background:rgba(17,23,38,0.72) !important;-webkit-backdrop-filter:blur(18px) saturate(150%);backdrop-filter:blur(18px) saturate(150%);border-bottom:1px solid rgba(255,255,255,.09) !important}
.main{background:transparent !important}
.card,.tw,.swc,.bkc,.sc,.mbox,.pcard,.theme-panel{background:rgba(255,255,255,.05) !important;-webkit-backdrop-filter:blur(16px) saturate(140%) !important;backdrop-filter:blur(16px) saturate(140%) !important;border:1px solid rgba(255,255,255,.09) !important;border-radius:8px !important;box-shadow:0 8px 30px rgba(0,0,0,.4),inset 0 1px 0 rgba(255,255,255,.06) !important}
.sc:hover{background:rgba(255,255,255,.08) !important;border-color:rgba(255,255,255,.2) !important;box-shadow:0 12px 38px rgba(0,0,0,.55),inset 0 1px 0 rgba(255,255,255,.06) !important}
.fi,.fs{background:rgba(255,255,255,.05) !important;border:1px solid rgba(255,255,255,.09) !important;color:#ffffff !important;border-radius:4px}
.fi::placeholder{color:#71809a}
.fi:focus,.fs:focus{border-color:#2e6ff2 !important;background:rgba(255,255,255,.08) !important;box-shadow:0 0 0 3px rgba(46,111,242,0.18) !important}
.btn-p{background:linear-gradient(135deg,#0b3fbf,#2e6ff2) !important;border:none !important;color:#ffffff !important;font-weight:700;border-radius:4px;box-shadow:0 4px 18px rgba(46,111,242,0.32)}
.btn-p:hover{filter:brightness(1.1);box-shadow:0 6px 24px rgba(46,111,242,0.45)}
.ib{background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.09);color:#b4c0d6;border-radius:3px}
.ib:hover{background:rgba(255,255,255,.12);color:#ffffff;border-color:rgba(255,255,255,.2)}
.uavt{background:linear-gradient(135deg,#0b3fbf,#2e6ff2);color:#ffffff}
.lic-dot{background:#2e6ff2;box-shadow:0 0 8px rgba(46,111,242,0.7)}
.lic-b{background:rgba(255,255,255,.05);border-color:rgba(255,255,255,.09);color:#b4c0d6}
.mhd,.mfooter{background:rgba(255,255,255,.035);border-color:rgba(255,255,255,.09)}
thead tr{background:rgba(255,255,255,.05)}
th{color:#71809a;font-weight:700;letter-spacing:.04em}
td{color:#ffffff;border-color:rgba(255,255,255,.06)}
tr:hover td{background:rgba(255,255,255,.045)}
.pw .pb{background:linear-gradient(90deg,#0b3fbf,#2e6ff2)}
.mbd,#pm{background:rgba(0,0,0,.8) !important;-webkit-backdrop-filter:blur(14px) !important;backdrop-filter:blur(14px) !important}
.al-s{background:rgba(46,111,242,0.12);border-color:rgba(46,111,242,0.4);color:#2e6ff2}
@keyframes fu{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}

/* ══ الموقع العام index.php ══ */
.navbar{background:rgba(17,23,38,0.72) !important;-webkit-backdrop-filter:blur(18px) saturate(155%) !important;backdrop-filter:blur(18px) saturate(155%) !important;border-bottom:1px solid rgba(255,255,255,.09) !important}
.nav-logo-text{color:#2e6ff2 !important}
.nav-btn{background:rgba(255,255,255,.07) !important;border:1px solid rgba(255,255,255,.09) !important;color:#b4c0d6 !important}
.nav-btn:hover{background:rgba(46,111,242,0.22) !important;border-color:#2e6ff2 !important;color:#ffffff !important}
.search-wrap input{background:rgba(255,255,255,.05) !important;border:1px solid rgba(255,255,255,.09) !important;color:#ffffff !important}
.search-wrap input::placeholder{color:#71809a}
.search-wrap input:focus{background:rgba(255,255,255,.09) !important;border-color:#2e6ff2 !important}
.cat-navbar{background:rgba(17,23,38,0.72) !important;-webkit-backdrop-filter:blur(16px) saturate(150%) !important;backdrop-filter:blur(16px) saturate(150%) !important;border-bottom:1px solid rgba(255,255,255,.09) !important}
.cat-nav-btn{background:rgba(255,255,255,.055) !important;border:1px solid rgba(255,255,255,.09) !important;color:#b4c0d6 !important;font-weight:650 !important}
.cat-nav-btn:hover{background:rgba(255,255,255,.11) !important;border-color:rgba(255,255,255,.2) !important;color:#ffffff !important}
.cat-nav-btn.active{background:linear-gradient(135deg,#0b3fbf,#2e6ff2) !important;border-color:transparent !important;color:#ffffff !important;box-shadow:0 4px 16px rgba(46,111,242,0.38) !important}
.fp-panel,.np-panel,.m3u-panel,.ep-panel,.shs-catmenu-panel{background:rgba(17,23,38,0.82) !important;-webkit-backdrop-filter:blur(24px) saturate(155%) !important;backdrop-filter:blur(24px) saturate(155%) !important;border-left:1px solid rgba(255,255,255,.09) !important;box-shadow:0 0 50px rgba(0,0,0,.55) !important}
.panel-overlay,.shs-catmenu-overlay,.tmdb-modal-overlay{background:rgba(0,0,0,.65) !important;-webkit-backdrop-filter:blur(8px) !important;backdrop-filter:blur(8px) !important}
.netflix-card,.nx-card,.ch-card,.sr-card{background:rgba(255,255,255,.05) !important;border:1px solid rgba(255,255,255,.09) !important;border-radius:6px !important;box-shadow:0 6px 24px rgba(0,0,0,.4) !important}
.netflix-card:hover,.nx-card:hover{border-color:rgba(46,111,242,0.5) !important;box-shadow:0 12px 36px rgba(0,0,0,.55),0 0 0 1px rgba(46,111,242,0.25) !important}
.shs-catview-banner{background:rgba(255,255,255,.05) !important;border:1px solid rgba(255,255,255,.09) !important;-webkit-backdrop-filter:blur(16px) saturate(140%);backdrop-filter:blur(16px) saturate(140%)}
.shs-catmenu-item:hover{background:rgba(255,255,255,.07)}
.shs-empty-ico{background:radial-gradient(120% 120% at 30% 20%,rgba(46,111,242,0.16),rgba(255,255,255,.03));color:#2e6ff2}
.shs-spinner::before{border-top-color:#2e6ff2;border-right-color:#2e6ff2}
.hero-btn-play,.btn-play{background:linear-gradient(135deg,#0b3fbf,#2e6ff2) !important;color:#ffffff !important}
/* PlayStation 5 — حواف حادة + توهّج أزرق */
.card,.tw,.swc,.bkc,.sc,.mbox,.pcard{border-radius:6px !important;border-top:1px solid rgba(46,111,242,.35) !important}
.netflix-card,.nx-card{border-radius:4px !important}
.btn-p{border-radius:999px !important;font-weight:700;letter-spacing:.02em;text-transform:uppercase;font-size:.8rem}
.fi,.fs{border-radius:4px}
.sbrand-icon{border-radius:6px;box-shadow:0 0 24px rgba(46,111,242,.6)}
.si{border-radius:0;border-right:2px solid transparent}
.si.on{border-right-color:#2e6ff2;background:linear-gradient(90deg,rgba(46,111,242,.2),transparent) !important}
.si.on::before{display:none}
.topbar,.navbar{border-bottom:1px solid rgba(46,111,242,.28) !important}
.cat-nav-btn{border-radius:999px !important}
.cat-nav-btn.active{box-shadow:0 0 22px rgba(46,111,242,.6) !important}
.netflix-card:hover,.nx-card:hover{transform:translateY(-4px);box-shadow:0 0 0 2px #2e6ff2,0 16px 40px rgba(46,111,242,.35) !important}
.stitle{text-transform:uppercase;letter-spacing:.04em}
/* ══ إصلاح أيقونات شريط الأقسام ══ */
.cat-nav-btn i,.cat-nav-btn svg,.cat-nav-btn .lcn,.cat-nav-btn .lcn svg{
 color:#b4c0d6 !important;stroke:currentColor !important;fill:none !important;
 width:1em;height:1em;flex-shrink:0;opacity:.9;transition:color .2s}
.cat-nav-btn:hover i,.cat-nav-btn:hover svg,.cat-nav-btn:hover .lcn,.cat-nav-btn:hover .lcn svg{
 color:#ffffff !important;opacity:1}
.cat-nav-btn.active i,.cat-nav-btn.active svg,.cat-nav-btn.active .lcn,.cat-nav-btn.active .lcn svg{
 color:#ffffff !important;opacity:1}
.cat-nav-btn .lcn{display:inline-flex;align-items:center;justify-content:center;line-height:0}
/* أيقونات القائمة الجانبية للأقسام */
.shs-catmenu-item i,.shs-catmenu-item svg,.shs-catmenu-item .lcn svg{
 color:#2e6ff2 !important;stroke:currentColor !important;fill:none !important}
/* أيقونات أزرار التنقّل العلوية */
.nav-btn i,.nav-btn svg,.nav-btn .lcn svg{stroke:currentColor !important;fill:none !important}`
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
