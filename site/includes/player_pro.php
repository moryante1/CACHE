<?php
/**
 * ══════════════════════════════════════════════════════════════
 *  Shashety IPTV — الحزمة الاحترافية لمشغّل الصفحة الرئيسية
 * ══════════════════════════════════════════════════════════════
 *
 *  وحدة إضافية (Additive) تعمل *فوق* المشغّل الحالي دون تعديل
 *  سطر واحد من site/includes/main_js.php.
 *
 *  ► للتعطيل الكامل والعودة للسلوك السابق: احذف سطر التضمين
 *    الخاص بهذا الملف من index.php فقط. لا شيء آخر يتأثر.
 *
 *  ──────────────────────────────────────────────────────────────
 *  ما تضيفه هذه الحزمة
 *  ──────────────────────────────────────────────────────────────
 *   ①  اختيار مسار الصوت (تعدد اللغات) — الأهم في IPTV
 *   ②  اختيار الجودة يدوياً (1080p/720p/…) أو تلقائي
 *   ③  اختيار ترجمات المانيفست المدمجة
 *   ④  سرعة التشغيل (0.5× … 2×)
 *   ⑤  منع إطفاء الشاشة أثناء المشاهدة (Screen Wake Lock)
 *   ⑥  أزرار التحكم على شاشة القفل والسماعات (Media Session)
 *   ⑦  التشغيل في نافذة عائمة (Picture-in-Picture)
 *   ⑧  اختصارات لوحة المفاتيح (لم تكن موجودة إطلاقاً)
 *   ⑨  لوحة إحصاءات فنية للتشخيص
 *   ⑩  إظهار الجزء المُحمَّل مسبقاً على شريط التقدّم
 *   ⑪  حفظ مستوى الصوت بين الجلسات
 *   ⑫  نقر مزدوج على جانبي الشاشة للتقديم/الإرجاع (جوال)
 *
 *  ──────────────────────────────────────────────────────────────
 *  ملاحظات تقنية مهمة
 *  ──────────────────────────────────────────────────────────────
 *   • دالة initStream تُنشئ عنصر <video> جديداً في كل مرة وتستبدل
 *     القديم. لذلك لا يصحّ ربط المستمعات مرة واحدة عند التحميل —
 *     نستخدم MutationObserver يعيد الربط تلقائياً عند كل استبدال.
 *   • كائن PL و hls يتغيّران مع كل قناة، لذا كل القوائم تقرأ
 *     الحالة لحظياً عند الفتح لا عند الإنشاء.
 *   • لا نلمس مفاتيح الأسهم إطلاقاً: المشغّل يستخدمها للتنقّل بين
 *     الأزرار بريموت التلفاز. اخترنا J/K/L وSpace وF وM (معيار
 *     يوتيوب) حتى لا نكسر تجربة التلفاز.
 */
?>
<style>
/* ══ قوائم الإعدادات الاحترافية ══ */
.pp-menu{
  position:absolute; bottom:104px; inset-inline-start:16px; z-index:60;
  background:rgba(14,14,16,.97); border:1px solid rgba(255,255,255,.14);
  border-radius:14px; padding:8px; min-width:230px; max-height:52vh; overflow-y:auto;
  box-shadow:0 18px 50px rgba(0,0,0,.75); backdrop-filter:blur(14px);
  display:none; font-size:.9rem;
}
.pp-menu.open{display:block; animation:ppIn .16s ease}
@keyframes ppIn{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}
.pp-menu-title{
  color:#8b94a7; font-size:.72rem; font-weight:700; padding:8px 12px 6px;
  letter-spacing:.4px; text-transform:uppercase;
}
.pp-item{
  display:flex; align-items:center; gap:10px; width:100%;
  padding:10px 12px; border:0; border-radius:9px; background:transparent;
  color:#e8ecf3; cursor:pointer; text-align:start; font-size:.88rem;
  font-family:inherit; transition:background .15s;
}
.pp-item:hover,.pp-item:focus-visible{background:rgba(255,255,255,.09); outline:none}
.pp-item.active{background:rgba(229,9,20,.18); color:#fff; font-weight:700}
.pp-item .pp-check{width:16px; flex-shrink:0; opacity:0}
.pp-item.active .pp-check{opacity:1}
.pp-item .pp-sub{margin-inline-start:auto; color:#8b94a7; font-size:.75rem}
.pp-sep{height:1px; background:rgba(255,255,255,.1); margin:6px 4px}

/* ══ لوحة الإحصاءات ══ */
.pp-stats{
  position:absolute; top:84px; inset-inline-start:16px; z-index:60;
  background:rgba(0,0,0,.82); border:1px solid rgba(255,255,255,.16);
  border-radius:12px; padding:12px 14px; display:none;
  font-family:ui-monospace,Menlo,Consolas,monospace; font-size:.72rem;
  color:#c9d3e3; line-height:1.85; direction:ltr; text-align:left;
  min-width:220px; backdrop-filter:blur(10px); pointer-events:none;
}
.pp-stats.open{display:block}
.pp-stats b{color:#fff; font-weight:700}
.pp-stats .k{color:#8b94a7; display:inline-block; min-width:104px}

/* ══ شريط التحميل المسبق داخل شريط التقدّم ══ */
.pp-buffered{
  position:absolute; inset-block:0; inset-inline-start:0;
  background:rgba(255,255,255,.26); border-radius:inherit;
  width:0; pointer-events:none; z-index:0;
}
.p-prog-track{position:relative}
.p-prog-fill{position:relative; z-index:1}

/* ══ مؤشر التقديم بالنقر المزدوج ══ */
.pp-skip-hint{
  position:absolute; top:50%; transform:translateY(-50%);
  background:rgba(0,0,0,.62); color:#fff; border-radius:50%;
  width:104px; height:104px; display:flex; align-items:center; justify-content:center;
  flex-direction:column; gap:3px; font-size:.78rem; font-weight:800;
  opacity:0; pointer-events:none; transition:opacity .22s; z-index:35;
}
.pp-skip-hint.show{opacity:1}
.pp-skip-hint.l{inset-inline-start:9%}
.pp-skip-hint.r{inset-inline-end:9%}

/* شارة صغيرة تحت زر الإعدادات تُظهر الجودة الحالية */
.pp-badge{
  position:absolute; bottom:-3px; inset-inline-end:-3px;
  background:var(--red,#e50914); color:#fff; font-size:.55rem; font-weight:800;
  padding:1px 4px; border-radius:5px; line-height:1.3; pointer-events:none;
}
.pp-btn-wrap{position:relative}
@media(max-width:600px){
  .pp-menu{min-width:200px; bottom:96px; max-height:46vh}
  .pp-stats{font-size:.66rem; top:74px; min-width:190px}
  .pp-skip-hint{width:84px;height:84px;font-size:.7rem}
}
</style>

<script>
/* ══════════════════════════════════════════════════════════════
   الحزمة الاحترافية للمشغّل — كل شيء داخل نطاق مغلق
   ══════════════════════════════════════════════════════════════ */
(function () {
  'use strict';

  /* ── أدوات مساعدة آمنة ──
     كل استدعاء لدالة من المشغّل الأصلي محمي بـ typeof، فإن غيّرت
     اسم دالة لاحقاً لن تنهار الحزمة بل تتجاهل تلك الميزة فقط. */
  var $  = function (id) { return document.getElementById(id); };
  var has = function (fn) { return typeof window[fn] === 'function'; };
  var call = function (fn, a, b) { if (has(fn)) { try { return window[fn](a, b); } catch (e) {} } };

  function vid()     { return $('html5Player'); }
  function overlay() { return $('playerOverlay'); }
  function isOpen()  { var o = overlay(); return !!(o && o.classList.contains('active')); }
  function hls()     { try { return (typeof PL !== 'undefined' && PL && PL.hls) ? PL.hls : null; } catch (e) { return null; } }
  function say(m)    { if (has('toast')) window.toast(m); }

  var LS = {
    get: function (k, d) { try { var v = localStorage.getItem('shs_pp_' + k); return v === null ? d : v; } catch (e) { return d; } },
    set: function (k, v) { try { localStorage.setItem('shs_pp_' + k, v); } catch (e) {} }
  };

  /* ════════════════════════════════════════════════════════════
     1) بناء أزرار الشريط: إعدادات + نافذة عائمة
     ════════════════════════════════════════════════════════════ */

  var ICON = {
    gear: '<svg viewBox="0 0 24 24" width="1em" height="1em" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.6a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>',
    pip:  '<svg viewBox="0 0 24 24" width="1em" height="1em" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 10V5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2h-6"/><rect x="2" y="13" width="10" height="8" rx="2"/></svg>',
    check:'<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>'
  };

  function mkBtn(id, title, svg) {
    var w = document.createElement('div');
    w.className = 'pp-btn-wrap';
    var b = document.createElement('button');
    b.type = 'button';
    b.className = 'p-btn';
    b.id = id;
    b.title = title;
    b.setAttribute('aria-label', title);
    b.innerHTML = '<span class="lcn">' + svg + '</span>';
    w.appendChild(b);
    return { wrap: w, btn: b };
  }

  var settingsMenu, statsBox, qualityBadge;

  function buildUI() {
    var tools = document.querySelector('#pBottom .p-tools-l');
    if (!tools || $('ppSettingsBtn')) return;

    // زر الإعدادات (جودة / صوت / ترجمة / سرعة)
    var gear = mkBtn('ppSettingsBtn', 'الإعدادات — الجودة والصوت والسرعة', ICON.gear);
    qualityBadge = document.createElement('span');
    qualityBadge.className = 'pp-badge';
    qualityBadge.id = 'ppQualityBadge';
    qualityBadge.textContent = 'AUTO';
    gear.wrap.appendChild(qualityBadge);
    gear.btn.addEventListener('click', function (e) { e.stopPropagation(); toggleSettings(); });
    tools.appendChild(gear.wrap);

    // زر النافذة العائمة — يظهر فقط إن كان المتصفح يدعمه
    if (document.pictureInPictureEnabled) {
      var pip = mkBtn('ppPipBtn', 'نافذة عائمة (P)', ICON.pip);
      pip.btn.addEventListener('click', function (e) { e.stopPropagation(); togglePip(); });
      tools.appendChild(pip.wrap);
    }

    var wrap = $('pvWrap') || overlay();

    settingsMenu = document.createElement('div');
    settingsMenu.className = 'pp-menu';
    settingsMenu.id = 'ppSettingsMenu';
    settingsMenu.addEventListener('click', function (e) { e.stopPropagation(); });
    (overlay() || wrap).appendChild(settingsMenu);

    statsBox = document.createElement('div');
    statsBox.className = 'pp-stats';
    statsBox.id = 'ppStats';
    (overlay() || wrap).appendChild(statsBox);

    // شريط التحميل المسبق
    var track = document.querySelector('#pProgress .p-prog-track');
    if (track && !track.querySelector('.pp-buffered')) {
      var bf = document.createElement('div');
      bf.className = 'pp-buffered';
      bf.id = 'ppBuffered';
      track.insertBefore(bf, track.firstChild);
    }

    // مؤشرات النقر المزدوج
    if (wrap && !$('ppSkipL')) {
      ['l', 'r'].forEach(function (side) {
        var h = document.createElement('div');
        h.className = 'pp-skip-hint ' + side;
        h.id = 'ppSkip' + side.toUpperCase();
        h.innerHTML = '<div>' + (side === 'l' ? '«' : '»') + '</div><div>10 ثوانٍ</div>';
        wrap.appendChild(h);
      });
    }

    document.addEventListener('click', function () { closeSettings(); });
  }

  /* ════════════════════════════════════════════════════════════
     2) قائمة الإعدادات — تُبنى لحظياً من حالة البث الحالية
     ════════════════════════════════════════════════════════════ */

  var menuView = 'root';

  function row(label, opts) {
    opts = opts || {};
    var b = document.createElement('button');
    b.type = 'button';
    b.className = 'pp-item' + (opts.active ? ' active' : '');
    b.innerHTML = '<span class="pp-check">' + ICON.check + '</span><span>' + label + '</span>' +
                  (opts.hint ? '<span class="pp-sub">' + opts.hint + '</span>' : '');
    if (opts.onClick) b.addEventListener('click', opts.onClick);
    return b;
  }

  function title(t) {
    var d = document.createElement('div');
    d.className = 'pp-menu-title';
    d.textContent = t;
    return d;
  }

  function sep() { var d = document.createElement('div'); d.className = 'pp-sep'; return d; }

  function renderSettings() {
    if (!settingsMenu) return;
    settingsMenu.innerHTML = '';
    var H = hls(), v = vid();

    if (menuView === 'root') {
      settingsMenu.appendChild(title('الإعدادات'));

      // الجودة
      var qLabel = 'تلقائي';
      if (H && H.levels && H.levels.length) {
        var qi = manualLevel >= 0 ? manualLevel : H.currentLevel;
        if (isManual() && H.levels[qi]) {
          qLabel = (H.levels[qi].height || '?') + 'p';
        } else if (qi >= 0 && H.levels[qi]) {
          qLabel = 'تلقائي (' + (H.levels[qi].height || '?') + 'p)';
        }
      } else {
        qLabel = 'غير متاح';
      }
      settingsMenu.appendChild(row('الجودة', {
        hint: qLabel,
        onClick: function () { if (H && H.levels && H.levels.length) { menuView = 'quality'; renderSettings(); } else say('هذا البث لا يوفّر جودات متعددة'); }
      }));

      // الصوت
      var aCount = (H && H.audioTracks) ? H.audioTracks.length : 0;
      settingsMenu.appendChild(row('مسار الصوت', {
        hint: aCount > 1 ? (aCount + ' مسارات') : (aCount === 1 ? 'مسار واحد' : 'غير متاح'),
        onClick: function () { if (aCount > 0) { menuView = 'audio'; renderSettings(); } else say('هذا البث لا يوفّر مسارات صوت متعددة'); }
      }));

      // ترجمات المانيفست
      var sCount = (H && H.subtitleTracks) ? H.subtitleTracks.length : 0;
      if (sCount > 0) {
        settingsMenu.appendChild(row('ترجمات البث', {
          hint: sCount + ' مسار',
          onClick: function () { menuView = 'subs'; renderSettings(); }
        }));
      }

      // السرعة
      settingsMenu.appendChild(row('سرعة التشغيل', {
        hint: (v ? (v.playbackRate || 1) : 1) + '×',
        onClick: function () { menuView = 'speed'; renderSettings(); }
      }));

      settingsMenu.appendChild(sep());
      settingsMenu.appendChild(row('الإحصاءات الفنية', {
        active: statsOn,
        hint: 'I',
        onClick: function () { toggleStats(); renderSettings(); }
      }));
      settingsMenu.appendChild(row('اختصارات لوحة المفاتيح', {
        hint: '?',
        onClick: function () { showShortcuts(); closeSettings(); }
      }));
      return;
    }

    // زر الرجوع لأي قائمة فرعية
    settingsMenu.appendChild(row('‹ رجوع', { onClick: function () { menuView = 'root'; renderSettings(); } }));
    settingsMenu.appendChild(sep());

    if (menuView === 'quality' && H) {
      settingsMenu.appendChild(title('الجودة'));
      settingsMenu.appendChild(row('تلقائي', {
        active: !isManual(),
        hint: 'موصى به',
        onClick: function () {
          manualLevel = -1; H.currentLevel = -1;
          updateBadge(); menuView = 'root'; renderSettings(); say('الجودة: تلقائي');
        }
      }));
      // الأعلى أولاً — أقرب لتوقّع المستخدم
      H.levels.map(function (l, i) { return { l: l, i: i }; })
        .sort(function (a, b) { return (b.l.height || 0) - (a.l.height || 0); })
        .forEach(function (o) {
          var mb = o.l.bitrate ? (Math.round(o.l.bitrate / 1000) + ' kbps') : '';
          settingsMenu.appendChild(row((o.l.height ? o.l.height + 'p' : 'مستوى ' + (o.i + 1)), {
            active: isManual() && (manualLevel >= 0 ? manualLevel : H.currentLevel) === o.i,
            hint: mb,
            onClick: function () {
              manualLevel = o.i; H.currentLevel = o.i;
              updateBadge(); menuView = 'root'; renderSettings();
              say('الجودة: ' + (o.l.height ? o.l.height + 'p' : 'مستوى ' + (o.i + 1)));
            }
          }));
        });
      return;
    }

    if (menuView === 'audio' && H) {
      settingsMenu.appendChild(title('مسار الصوت'));
      H.audioTracks.forEach(function (t, i) {
        var nm = t.name || t.lang || ('مسار ' + (i + 1));
        settingsMenu.appendChild(row(nm, {
          active: H.audioTrack === i,
          hint: (t.lang || '').toUpperCase(),
          onClick: function () { H.audioTrack = i; menuView = 'root'; renderSettings(); say('الصوت: ' + nm); }
        }));
      });
      return;
    }

    if (menuView === 'subs' && H) {
      settingsMenu.appendChild(title('ترجمات البث'));
      settingsMenu.appendChild(row('إيقاف', {
        active: H.subtitleTrack === -1,
        onClick: function () { H.subtitleTrack = -1; menuView = 'root'; renderSettings(); }
      }));
      H.subtitleTracks.forEach(function (t, i) {
        var nm = t.name || t.lang || ('ترجمة ' + (i + 1));
        settingsMenu.appendChild(row(nm, {
          active: H.subtitleTrack === i,
          hint: (t.lang || '').toUpperCase(),
          onClick: function () { H.subtitleDisplay = true; H.subtitleTrack = i; menuView = 'root'; renderSettings(); say('الترجمة: ' + nm); }
        }));
      });
      return;
    }

    if (menuView === 'speed') {
      settingsMenu.appendChild(title('سرعة التشغيل'));
      [0.5, 0.75, 1, 1.25, 1.5, 1.75, 2].forEach(function (r) {
        settingsMenu.appendChild(row(r === 1 ? 'عادية (1×)' : r + '×', {
          active: v && Math.abs((v.playbackRate || 1) - r) < 0.01,
          onClick: function () { setRate(r); menuView = 'root'; renderSettings(); }
        }));
      });
    }
  }

  function setRate(r) {
    var v = vid(); if (!v) return;
    // البث الحيّ لا يقبل تغيير السرعة — يسبّب انقطاعاً وتشويهاً للصوت
    if (isLive() && r !== 1) { say('لا يمكن تغيير السرعة في البث المباشر'); return; }
    v.playbackRate = r;
    LS.set('rate', r);
    say('السرعة: ' + r + '×');
  }

  function isLive() {
    var v = vid();
    if (!v) return false;
    if (!isFinite(v.duration) || v.duration === 0) return true;
    var H = hls();
    if (H && H.levels && H.currentLevel >= 0 && H.levels[H.currentLevel] &&
        H.levels[H.currentLevel].details && H.levels[H.currentLevel].details.live) return true;
    return false;
  }

  /* هل اختار المستخدم الجودة يدوياً؟
     نتتبّعها بأنفسنا بدل الاعتماد الكامل على H.autoLevelEnabled، لأن
     ذلك الخاصية getter داخلي في hls.js وقد يختلف سلوكه بين الإصدارات.
     تُصفَّر تلقائياً عند كل بث جديد (كل قناة تبدأ بالوضع التلقائي). */
  var manualLevel = -1;

  function isManual() {
    var H = hls();
    if (manualLevel >= 0) return true;
    return !!(H && H.autoLevelEnabled === false);
  }

  function updateBadge() {
    if (!qualityBadge) return;
    var H = hls();
    if (!H || !H.levels || !H.levels.length) { qualityBadge.textContent = 'HD'; return; }

    var idx = manualLevel >= 0 ? manualLevel : H.currentLevel;
    var lvl = H.levels[idx];

    if (isManual() && lvl) {
      qualityBadge.textContent = (lvl.height || '?') + 'p';
    } else {
      qualityBadge.textContent = 'AUTO';
    }
  }

  function toggleSettings() {
    if (!settingsMenu) return;
    if (settingsMenu.classList.contains('open')) { closeSettings(); return; }
    menuView = 'root';
    renderSettings();
    settingsMenu.classList.add('open');
    call('showControls');
  }
  function closeSettings() { if (settingsMenu) settingsMenu.classList.remove('open'); }

  /* ════════════════════════════════════════════════════════════
     3) لوحة الإحصاءات
     ════════════════════════════════════════════════════════════ */

  var statsOn = false, statsTimer = null;

  function toggleStats() {
    statsOn = !statsOn;
    if (!statsBox) return;
    statsBox.classList.toggle('open', statsOn);
    if (statsOn) { drawStats(); statsTimer = setInterval(drawStats, 1000); }
    else { clearInterval(statsTimer); statsTimer = null; }
  }

  function drawStats() {
    if (!statsBox || !statsOn) return;
    var v = vid(), H = hls();
    if (!v) return;

    var lines = [];
    function put(k, val) { lines.push('<span class="k">' + k + '</span><b>' + val + '</b>'); }

    put('Resolution', (v.videoWidth || 0) + ' × ' + (v.videoHeight || 0));

    if (H && H.levels && H.levels[H.currentLevel]) {
      var L = H.levels[H.currentLevel];
      put('Level', (H.autoLevelEnabled === false ? 'manual ' : 'auto ') + (H.currentLevel + 1) + '/' + H.levels.length);
      if (L.bitrate) put('Bitrate', Math.round(L.bitrate / 1000) + ' kbps');
      if (L.videoCodec) put('Codec', L.videoCodec);
    }

    // تقدير عرض النطاق من hls.js
    if (H && H.bandwidthEstimate) put('Bandwidth', Math.round(H.bandwidthEstimate / 1000) + ' kbps');

    // المخزن المتبقي أمام رأس التشغيل
    var ahead = 0;
    try {
      for (var i = 0; i < v.buffered.length; i++) {
        if (v.currentTime >= v.buffered.start(i) && v.currentTime <= v.buffered.end(i)) {
          ahead = v.buffered.end(i) - v.currentTime; break;
        }
      }
    } catch (e) {}
    put('Buffer', ahead.toFixed(1) + ' s');

    var q = (v.getVideoPlaybackQuality && v.getVideoPlaybackQuality()) || null;
    if (q) {
      put('Dropped', (q.droppedVideoFrames || 0) + ' / ' + (q.totalVideoFrames || 0));
    } else if (typeof v.webkitDroppedFrameCount === 'number') {
      put('Dropped', v.webkitDroppedFrameCount);
    }

    put('Live', isLive() ? 'yes' : 'no');
    put('Rate', (v.playbackRate || 1) + '×');

    statsBox.innerHTML = lines.join('<br>');
  }

  /* ════════════════════════════════════════════════════════════
     4) منع إطفاء الشاشة (Screen Wake Lock)
     ════════════════════════════════════════════════════════════ */

  var wakeLock = null;

  async function acquireWake() {
    if (!('wakeLock' in navigator)) return;
    if (wakeLock) return;
    try {
      wakeLock = await navigator.wakeLock.request('screen');
      wakeLock.addEventListener('release', function () { wakeLock = null; });
    } catch (e) { wakeLock = null; }
  }
  function releaseWake() {
    if (!wakeLock) return;
    try { wakeLock.release(); } catch (e) {}
    wakeLock = null;
  }
  // النظام يسحب القفل تلقائياً عند تبديل التبويب — نعيد طلبه عند العودة
  document.addEventListener('visibilitychange', function () {
    if (document.visibilityState === 'visible' && isOpen()) {
      var v = vid(); if (v && !v.paused) acquireWake();
    }
  });

  /* ════════════════════════════════════════════════════════════
     5) Media Session — أزرار شاشة القفل والسماعات
     ════════════════════════════════════════════════════════════ */

  function updateMediaSession() {
    if (!('mediaSession' in navigator)) return;
    var nameEl = $('pChannelName');
    var name = nameEl ? (nameEl.textContent || '').trim() : '';
    try {
      navigator.mediaSession.metadata = new window.MediaMetadata({
        title: name || 'Shashety IPTV',
        artist: isLive() ? 'بث مباشر' : 'Shashety IPTV',
        album: 'Shashety',
        artwork: [{ src: (window.SITE_LOGO || '/assets/22.png'), sizes: '512x512', type: 'image/png' }]
      });
    } catch (e) {}

    var acts = [
      ['play',  function () { var v = vid(); if (v) v.play().catch(function () {}); }],
      ['pause', function () { var v = vid(); if (v) v.pause(); }],
      ['seekbackward', function () { call('skip', -10); }],
      ['seekforward',  function () { call('skip', 10); }]
    ];
    // التنقّل بين الحلقات إن كنا في مسلسل
    try {
      if (typeof App !== 'undefined' && App && App.currentType === 'episode') {
        acts.push(['previoustrack', function () { call('navEpisode', -1); }]);
        acts.push(['nexttrack',     function () { call('navEpisode', 1); }]);
      }
    } catch (e) {}

    acts.forEach(function (a) {
      try { navigator.mediaSession.setActionHandler(a[0], a[1]); } catch (e) {}
    });
  }

  function clearMediaSession() {
    if (!('mediaSession' in navigator)) return;
    try { navigator.mediaSession.metadata = null; } catch (e) {}
    ['play', 'pause', 'seekbackward', 'seekforward', 'previoustrack', 'nexttrack'].forEach(function (a) {
      try { navigator.mediaSession.setActionHandler(a, null); } catch (e) {}
    });
  }

  /* ════════════════════════════════════════════════════════════
     6) النافذة العائمة (PiP)
     ════════════════════════════════════════════════════════════ */

  function togglePip() {
    var v = vid();
    if (!v || !document.pictureInPictureEnabled) { say('المتصفح لا يدعم النافذة العائمة'); return; }
    if (document.pictureInPictureElement) {
      document.exitPictureInPicture().catch(function () {});
    } else {
      v.requestPictureInPicture().catch(function () { say('تعذّر فتح النافذة العائمة'); });
    }
  }

  /* ════════════════════════════════════════════════════════════
     7) شريط التحميل المسبق
     ════════════════════════════════════════════════════════════ */

  function updateBuffered() {
    var bar = $('ppBuffered'), v = vid();
    if (!bar || !v) return;
    var d = v.duration;
    if (!isFinite(d) || d <= 0) { bar.style.width = '0'; return; }
    try {
      var end = 0;
      for (var i = 0; i < v.buffered.length; i++) {
        if (v.currentTime >= v.buffered.start(i) && v.currentTime <= v.buffered.end(i)) { end = v.buffered.end(i); break; }
      }
      bar.style.width = Math.min(100, (end / d) * 100) + '%';
    } catch (e) {}
  }

  /* ════════════════════════════════════════════════════════════
     8) اختصارات لوحة المفاتيح
     ────────────────────────────────────────────────────────────
     ⚠️ لا نلمس مفاتيح الأسهم: المشغّل يستخدمها للتنقّل بين أزرار
     التحكم بريموت التلفاز. اعتمدنا معيار يوتيوب (J/K/L) الذي لا
     يتعارض معها.
     ════════════════════════════════════════════════════════════ */

  function showShortcuts() {
    say('مسافة/K تشغيل · J تأخير 10 · L تقديم 10 · F ملء الشاشة · M كتم · P عائمة · C ترجمة · I إحصاءات · 0-9 قفز');
  }

  document.addEventListener('keydown', function (e) {
    if (!isOpen()) return;
    if (e.ctrlKey || e.altKey || e.metaKey) return;

    var t = e.target;
    if (t && (t.tagName === 'INPUT' || t.tagName === 'TEXTAREA' || t.isContentEditable)) return;

    var k = (e.key || '').toLowerCase();
    var v = vid();
    if (!v) return;

    // أرقام 0-9 → قفز نسبي (للمحتوى المسجَّل فقط)
    if (k >= '0' && k <= '9' && !isLive() && isFinite(v.duration) && v.duration > 0) {
      e.preventDefault();
      v.currentTime = (parseInt(k, 10) / 10) * v.duration;
      call('showControls');
      return;
    }

    switch (k) {
      case ' ':
      case 'k':
        e.preventDefault(); call('togglePlay'); call('showControls'); break;
      case 'j':
        e.preventDefault(); call('skip', -10); flashSkip('l'); break;
      case 'l':
        e.preventDefault(); call('skip', 10); flashSkip('r'); break;
      case 'f':
        e.preventDefault(); call('toggleFullscreen'); break;
      case 'm':
        e.preventDefault(); call('toggleMute'); call('showControls'); break;
      case 'p':
        e.preventDefault(); togglePip(); break;
      case 'c':
        e.preventDefault(); call('toggleSubtitle'); break;
      case 'i':
        e.preventDefault(); toggleStats(); break;
      case '?':
        e.preventDefault(); showShortcuts(); break;
      case '>':
      case '.':
        e.preventDefault(); bumpRate(1); break;
      case '<':
      case ',':
        e.preventDefault(); bumpRate(-1); break;
      default:
        break;
    }
  });

  var RATES = [0.5, 0.75, 1, 1.25, 1.5, 1.75, 2];
  function bumpRate(dir) {
    var v = vid(); if (!v) return;
    var cur = v.playbackRate || 1;
    var i = RATES.indexOf(cur);
    if (i === -1) i = RATES.indexOf(1);
    i = Math.max(0, Math.min(RATES.length - 1, i + dir));
    setRate(RATES[i]);
  }

  function flashSkip(side) {
    var el = $('ppSkip' + side.toUpperCase());
    if (!el) return;
    el.classList.add('show');
    clearTimeout(el._t);
    el._t = setTimeout(function () { el.classList.remove('show'); }, 480);
  }

  /* ════════════════════════════════════════════════════════════
     9) نقر مزدوج على الجانبين للتقديم/الإرجاع (جوال)
     ════════════════════════════════════════════════════════════ */

  var lastTap = 0, lastX = 0;
  function bindDoubleTap() {
    var wrap = $('pvWrap');
    if (!wrap || wrap._ppTap) return;
    wrap._ppTap = true;
    wrap.addEventListener('touchend', function (e) {
      if (!isOpen()) return;
      var now = Date.now();
      var x = (e.changedTouches && e.changedTouches[0]) ? e.changedTouches[0].clientX : 0;
      if (now - lastTap < 320 && Math.abs(x - lastX) < 90) {
        var r = wrap.getBoundingClientRect();
        var isLeft = (x - r.left) < r.width / 2;
        // في التخطيط العربي (RTL) يبقى الاتجاه الفيزيائي كما هو:
        // اليسار = إرجاع، اليمين = تقديم
        if (isLeft) { call('skip', -10); flashSkip('l'); }
        else        { call('skip', 10);  flashSkip('r'); }
        e.preventDefault();
        lastTap = 0;
      } else {
        lastTap = now; lastX = x;
      }
    }, { passive: false });
  }

  /* ════════════════════════════════════════════════════════════
     10) الربط بعنصر الفيديو — يُعاد عند كل استبدال
     ════════════════════════════════════════════════════════════ */

  function bindVideo() {
    var v = vid();
    if (!v || v._ppBound) return;
    v._ppBound = true;

    /* عنصر فيديو جديد = بث جديد ← نعود للوضع التلقائي.
       اختيار المستخدم لـ720p في قناة لا يجب أن يُفرض على القناة التالية
       التي قد لا تملك هذا المستوى أصلاً. */
    manualLevel = -1;

    // استعادة الصوت المحفوظ
    var sv = parseFloat(LS.get('vol', ''));
    if (!isNaN(sv) && sv >= 0 && sv <= 1) {
      v.volume = sv;
      try { if (typeof PL !== 'undefined' && PL) PL.vol = sv; } catch (e) {}
      call('_syncVolUI');
    }

    v.addEventListener('volumechange', function () {
      if (!v.muted) LS.set('vol', v.volume);
    });

    v.addEventListener('playing', function () {
      acquireWake();
      updateMediaSession();
      if ('mediaSession' in navigator) { try { navigator.mediaSession.playbackState = 'playing'; } catch (e) {} }
      updateBadge();
    });

    v.addEventListener('pause', function () {
      releaseWake();
      if ('mediaSession' in navigator) { try { navigator.mediaSession.playbackState = 'paused'; } catch (e) {} }
    });

    v.addEventListener('progress',    updateBuffered);
    v.addEventListener('timeupdate',  updateBuffered);
    v.addEventListener('loadedmetadata', function () {
      updateBadge();
      updateMediaSession();
      // إعادة تطبيق السرعة المحفوظة على المحتوى المسجَّل فقط
      var r = parseFloat(LS.get('rate', '1'));
      if (!isNaN(r) && r !== 1 && !isLive()) v.playbackRate = r;
    });

    // تحديث الشارة عند تغيّر مستوى الجودة تلقائياً
    var H = hls();
    if (H && H.on && !H._ppHooked) {
      H._ppHooked = true;
      try {
        H.on(window.Hls.Events.LEVEL_SWITCHED, function () { updateBadge(); if (statsOn) drawStats(); });
        H.on(window.Hls.Events.AUDIO_TRACK_SWITCHED, function () { if (settingsMenu && settingsMenu.classList.contains('open')) renderSettings(); });
      } catch (e) {}
    }
  }

  /* ════════════════════════════════════════════════════════════
     11) دورة حياة المشغّل
     ════════════════════════════════════════════════════════════ */

  function onPlayerOpen() {
    buildUI();
    bindVideo();
    bindDoubleTap();
    updateBadge();
    updateMediaSession();
  }

  function onPlayerClose() {
    releaseWake();
    clearMediaSession();
    closeSettings();
    if (statsOn) toggleStats();
    if (document.pictureInPictureElement) {
      document.exitPictureInPicture().catch(function () {});
    }
  }

  // عنصر الفيديو يُستبدل في كل initStream → نعيد الربط تلقائياً
  function watchPlayer() {
    var wrap = $('pvWrap');
    if (wrap && window.MutationObserver) {
      new MutationObserver(function () {
        bindVideo();
        updateBadge();
      }).observe(wrap, { childList: true });
    }

    var ov = overlay();
    if (ov && window.MutationObserver) {
      var wasOpen = false;
      new MutationObserver(function () {
        var now = isOpen();
        if (now && !wasOpen) onPlayerOpen();
        if (!now && wasOpen) onPlayerClose();
        wasOpen = now;
      }).observe(ov, { attributes: true, attributeFilter: ['class'] });
    }
  }

  function boot() {
    if (!$('playerOverlay')) return;
    watchPlayer();
    if (isOpen()) onPlayerOpen();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }

  // واجهة صغيرة للتشخيص من الـ console
  window.ShashetyPlayerPro = {
    stats: toggleStats,
    pip: togglePip,
    settings: toggleSettings,
    rate: setRate,
    version: '1.0'
  };
})();
</script>
