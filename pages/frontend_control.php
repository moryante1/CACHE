<section id="frontend-control" class="sec">
  <div class="shdr">
    <h1 class="stitle"><i class="fas fa-sliders-h" style="color:#00D084"></i><?= $t["control_word"] ?? "التحكم" ?><span><?= $t["frontend_word"] ?? "بالواجهة الأمامية" ?></span></h1>
  </div>
  <p style="color:var(--t3);font-size:.86rem;margin-bottom:22px;line-height:1.7"><?= $t["frontend_desc"] ?? "تحكّم في إظهار أو إخفاء عناصر الصفحة الرئيسية (index.php) لجميع الزوار. التغييرات تُحفظ في قاعدة البيانات وتُطبَّق فوراً على الموقع." ?></p>
  <div id="fcAlert"></div>

  <div class="fc-grid">
    <label class="fc-card" for="fc_search">
      <div class="fc-ic" style="background:rgba(88,166,255,.12);color:#58a6ff"><i class="fas fa-search"></i></div>
      <div class="fc-info"><b><?= $t["fc_search_bar"] ?? "شريط البحث" ?></b><small><?= $t["fc_search_desc"] ?? "إخفاء حقل البحث من الأعلى" ?></small></div>
      <span class="fc-switch"><input type="checkbox" id="fc_search" data-key="hide_search"><span class="fc-slider"></span></span>
    </label>

    <label class="fc-card" for="fc_notif">
      <div class="fc-ic" style="background:rgba(245,166,35,.12);color:#f5a623"><i class="fas fa-bell"></i></div>
      <div class="fc-info"><b><?= $t["fc_notifications"] ?? "الإشعارات" ?></b><small><?= $t["fc_notif_desc"] ?? "إخفاء زر ولوحة الإشعارات" ?></small></div>
      <span class="fc-switch"><input type="checkbox" id="fc_notif" data-key="hide_notifications"><span class="fc-slider"></span></span>
    </label>

    <label class="fc-card" for="fc_fav">
      <div class="fc-ic" style="background:rgba(255,77,87,.12);color:#ff4d57"><i class="fas fa-heart"></i></div>
      <div class="fc-info"><b><?= $t["fc_favorites"] ?? "المفضلة" ?></b><small><?= $t["fc_fav_desc"] ?? "إخفاء زر ولوحة المفضلة" ?></small></div>
      <span class="fc-switch"><input type="checkbox" id="fc_fav" data-key="hide_favorites"><span class="fc-slider"></span></span>
    </label>

    <label class="fc-card" for="fc_music">
      <div class="fc-ic" style="background:rgba(224,64,251,.12);color:#e040fb"><i class="fas fa-music"></i></div>
      <div class="fc-info"><b><?= $t["fc_music"] ?? "مشغل الموسيقى" ?></b><small><?= $t["fc_music_desc"] ?? "إخفاء زر موسيقى الخلفية" ?></small></div>
      <span class="fc-switch"><input type="checkbox" id="fc_music" data-key="hide_music"><span class="fc-slider"></span></span>
    </label>

    <label class="fc-card" for="fc_admin">
      <div class="fc-ic" style="background:rgba(229,9,20,.12);color:var(--red)"><i class="fas fa-shield-alt"></i></div>
      <div class="fc-info"><b><?= $t["fc_admin_btn"] ?? "زر لوحة التحكم" ?></b><small><?= $t["fc_admin_desc"] ?? "إخفاء رابط الدخول للوحة الإدارة" ?></small></div>
      <span class="fc-switch"><input type="checkbox" id="fc_admin" data-key="hide_admin_btn"><span class="fc-slider"></span></span>
    </label>

    <label class="fc-card" for="fc_social">
      <div class="fc-ic" style="background:rgba(37,211,102,.12);color:#25D366"><i class="fas fa-share-alt"></i></div>
      <div class="fc-info"><b><?= $t["fc_social"] ?? "أزرار التواصل" ?></b><small><?= $t["fc_social_desc"] ?? "إخفاء واتساب وفيسبوك والبريد" ?></small></div>
      <span class="fc-switch"><input type="checkbox" id="fc_social" data-key="hide_social"><span class="fc-slider"></span></span>
    </label>

    <label class="fc-card" for="fc_download">
      <div class="fc-ic" style="background:rgba(0,208,132,.12);color:#00D084"><i class="fas fa-download"></i></div>
      <div class="fc-info"><b><?= $t["fc_download"] ?? "زر التحميل" ?></b><small><?= $t["fc_download_desc"] ?? "إخفاء زر تحميل الفيديو في المشغل" ?></small></div>
      <span class="fc-switch"><input type="checkbox" id="fc_download" data-key="hide_download"><span class="fc-slider"></span></span>
    </label>

    <label class="fc-card" for="fc_cast">
      <div class="fc-ic" style="background:rgba(76,201,240,.12);color:#4CC9F0"><i class="fas fa-tv"></i></div>
      <div class="fc-info"><b><?= $t["fc_cast"] ?? "البث على التلفاز" ?></b><small><?= $t["fc_cast_desc"] ?? "منع البث للشاشات الذكية (Cast)" ?></small></div>
      <span class="fc-switch"><input type="checkbox" id="fc_cast" data-key="hide_cast"><span class="fc-slider"></span></span>
    </label>

    <label class="fc-card" for="fc_most_watched">
      <div class="fc-ic" style="background:rgba(255,159,28,.12);color:#ff9f1c"><i class="fas fa-fire"></i></div>
      <div class="fc-info"><b><?= $t["fc_most_watched"] ?? "الأكثر مشاهدة" ?></b><small><?= $t["fc_most_desc"] ?? "إخفاء شريط الأكثر مشاهدة من الرئيسية" ?></small></div>
      <span class="fc-switch"><input type="checkbox" id="fc_most_watched" data-key="hide_most_watched"><span class="fc-slider"></span></span>
    </label>

    <label class="fc-card" for="fc_suggestions">
      <div class="fc-ic" style="background:rgba(88,166,255,.12);color:#58a6ff"><i class="fas fa-magic"></i></div>
      <div class="fc-info"><b><?= $t["fc_suggestions"] ?? "مقترحات قد تعجبك" ?></b><small><?= $t["fc_sugg_desc"] ?? "إخفاء شريط المقترحات من الرئيسية" ?></small></div>
      <span class="fc-switch"><input type="checkbox" id="fc_suggestions" data-key="hide_suggestions"><span class="fc-slider"></span></span>
    </label>

    <label class="fc-card" for="fc_screensaver">
      <div class="fc-ic" style="background:rgba(70,211,105,.12);color:#46d369"><i class="fas fa-desktop"></i></div>
      <div class="fc-info"><b><?= $t["fc_screensaver"] ?? "شاشة التوقف" ?></b><small><?= $t["fc_screensaver_desc"] ?? "إيقاف شاشة التوقف (Screensaver) عن كل الزوار" ?></small></div>
      <span class="fc-switch"><input type="checkbox" id="fc_screensaver" data-key="hide_screensaver"><span class="fc-slider"></span></span>
    </label>
  </div>

  <div style="margin-top:24px;display:flex;gap:10px;flex-wrap:wrap">
    <button class="btn btn-p" onclick="saveFrontendToggles()"><i class="fas fa-save"></i><?= $t["save_changes"] ?? "حفظ التغييرات" ?></button>
    <button class="btn btn-g" onclick="loadFrontendToggles()"><i class="fas fa-sync-alt"></i><?= $t["restore_saved2"] ?? "استرجاع المحفوظ" ?></button>
  </div>
</section>
