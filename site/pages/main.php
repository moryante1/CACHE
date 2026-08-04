
<div class="panel-overlay" id="panelOverlay" onclick="closeAllPanels()"></div>

<!-- NAVBAR -->
<nav class="navbar" id="navbar">
  <div class="nav-brand">
    <?php if($site_logo): ?>
    <img src="<?php echo htmlspecialchars($site_logo); ?>" alt="Logo" class="nav-logo-img" loading="eager" decoding="async" fetchpriority="high">
    <?php endif; ?>
    <span class="nav-logo-text"><?php echo htmlspecialchars($site_name); ?></span>
  </div>
  <div class="nav-center">
    <?php if(!$hide_search): ?>
    <div class="search-wrap">
      <input type="text" id="searchInput" placeholder="بحث / Search" autocomplete="off" oninput="handleSearch()">
      <span class="lcn si"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg></span>
      <?php /* زرّ مسح البحث: يظهر فقط حين يوجد نصّ، فالحذف بيد المستخدم لا تلقائي */ ?>
      <span class="lcn" id="searchClearBtn" onclick="clearSearchBox()" role="button" title="مسح البحث"
            style="position:absolute;left:44px;top:50%;transform:translateY(-50%);color:var(--text-muted);cursor:pointer;font-size:1rem;display:none;z-index:11;transition:.2s">
        <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </span>
      <span class="lcn" id="voiceSearchBtn" style="position:absolute;left:13px;top:50%;transform:translateY(-50%);color:var(--text-muted);cursor:pointer;font-size:1.1rem;display:none;z-index:10;transition:0.2s" title="بحث صوتي"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2a3 3 0 0 0-3 3v7a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3Z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" x2="12" y1="19" y2="22"/></svg></span>
    </div>
    <?php endif; ?>
  </div>
  <div class="nav-actions">
    <!-- [SHS-CATMENU-BTN-START] زر قائمة الأقسام (إضافة فقط) -->
    <button type="button" class="shs-catmenu-btn" id="shsCatMenuBtn" title="الأقسام" onclick="shsOpenCatMenu()">
      <span class="lcn"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" x2="21" y1="6" y2="6"/><line x1="3" x2="21" y1="12" y2="12"/><line x1="3" x2="21" y1="18" y2="18"/></svg></span>
    </button>
    <!-- [SHS-CATMENU-BTN-END] -->
    <?php if(!$hide_music): ?>
    <button class="nav-btn music-mini-btn" id="musicMiniBtn" title="مشغل الموسيقى" onclick="toggleSiteMusic()">
      <div class="music-eq paused" id="musicEq">
        <div class="music-eq-bar"></div>
        <div class="music-eq-bar"></div>
        <div class="music-eq-bar"></div>
      </div>
    </button>
    <?php endif; ?>
    <?php if(!$hide_admin_btn): ?>
    <a href="admin.php" class="nav-btn" style="background:var(--red);color:#fff;border-color:var(--red)" title="لوحة التحكم"><span class="lcn"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="M12 8v4"/><path d="M12 16h.01"/></svg></span></a>
    <?php endif; ?>
    <?php if(!$hide_notifications): ?>
    <button class="nav-btn" title="الإشعارات" onclick="toggleNotifPanel()">
      <span class="lcn"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg></span>
      <span id="notifBadge" style="display:none"></span>
    </button>
    <?php endif; ?>
    <?php if(!$hide_favorites): ?>
    <button class="nav-btn" title="المفضلة" onclick="toggleFavPanel()"><span class="lcn"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg></span></button>
    <?php endif; ?>
  </div>
</nav>

<!-- CATEGORY QUICK NAV -->
<nav class="cat-navbar" id="catNavbar" style="display:none"></nav>

<!-- [SHS-CATMENU-HTML-START] قائمة الأقسام العمودية المنسدلة (إضافة فقط) -->
<div class="shs-catmenu-overlay" id="shsCatMenuOverlay" onclick="shsCloseCatMenu()"></div>
<aside class="shs-catmenu-panel" id="shsCatMenuPanel" aria-hidden="true">
  <div class="shs-catmenu-head">
    <button type="button" class="shs-catmenu-home" onclick="shsCatMenuGoHome()">الرئيسية</button>
    <div class="shs-catmenu-headwrap">
      <span class="shs-catmenu-title">الأقسام</span>
      <span class="shs-catmenu-sub">تصفّح كل الأقسام</span>
    </div>
    <button type="button" class="shs-catmenu-close" onclick="shsCloseCatMenu()" aria-label="إغلاق">
      <span class="lcn"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg></span>
    </button>
  </div>
  <button type="button" class="shs-catmenu-homerow" onclick="shsCatMenuGoHome()">
    <span>الرئيسية</span>
    <span class="lcn"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></span>
  </button>
  <div class="shs-catmenu-list" id="shsCatMenuList">
    <div class="shs-catmenu-empty">جارٍ التحميل…</div>
  </div>
  <div class="shs-catmenu-count" id="shsCatMenuCount"></div>
</aside>
<!-- [SHS-CATMENU-HTML-END] -->

<!-- MAIN -->
<main style="padding-top:88px;padding-bottom:60px" id="mainContent">
  <div class="hero-welcome" id="heroWelcome">
    <h1><?php echo htmlspecialchars($welcome_title); ?></h1>
    <p><?php echo htmlspecialchars($welcome_subtitle); ?></p>
  </div>

  <div id="netflixStyleSliders">
    <div style="margin-bottom:40px">
      <div style="padding:0 20px;margin-bottom:10px">
        <div class="skeleton" style="height:22px;width:160px;border-radius:6px;display:inline-block"></div>
      </div>
      <div style="display:flex;gap:10px;padding:0 20px;overflow:hidden">
        <?php for($i=0;$i<8;$i++): ?>
        <div class="skeleton" style="flex:0 0 calc((100vw - 40px - 70px)/8);height:195px;border-radius:10px;flex-shrink:0"></div>
        <?php endfor; ?>
      </div>
    </div>
  </div>

  <div id="categoryViewSection" class="hidden" style="padding:0 20px">
    <button class="back-btn" onclick="closeCategoryView()"><span class="lcn"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg></span> الرئيسية</button>
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px;font-size:1.1rem;font-weight:800;color:#fff">
      <div class="slider-title-icon"><span class="lcn"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="7" height="7" x="3" y="3" rx="1"/><rect width="7" height="7" x="14" y="3" rx="1"/><rect width="7" height="7" x="14" y="14" rx="1"/><rect width="7" height="7" x="3" y="14" rx="1"/></svg></span></div>
      <span id="categoryViewTitle">القسم</span>
      <span class="slider-badge" id="categoryViewCountBadge">0 عنصر</span>
    </div>
    <div class="channels-row" id="categoryViewGrid"></div>
    <div id="categoryViewLoading" class="hidden" style="text-align:center;padding:56px 0">
      <div class="shs-spinner"></div>
      <div class="shs-loading-txt">جارٍ تحميل المحتوى…</div>
    </div>
    <div id="categoryViewEmpty" class="hidden" style="padding:0;color:var(--text-muted)">
      <div class="shs-empty-wrap">
        <div class="shs-empty-ico"><span class="lcn"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M2 8V6a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v2"/><rect width="20" height="14" x="2" y="8" rx="2"/><path d="m9 13 2 2 4-4"/></svg></span></div>
        <div class="shs-empty-title">لا يوجد محتوى بعد</div>
        <p class="shs-empty-sub">هذا القسم فارغ حالياً. جرّب قسماً آخر أو عد لاحقاً بعد إضافة محتوى جديد.</p>
      </div>
    </div>
  </div>

  <div id="searchViewSection" class="hidden" style="padding:0 20px">
    <button class="back-btn" onclick="clearSearchAndGoHome()"><span class="lcn"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg></span> الرئيسية</button>
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px;font-size:1.1rem;font-weight:800;color:#fff">
      <div class="slider-title-icon"><span class="lcn"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg></span></div>
      نتائج البحث
      <span class="slider-badge" id="searchCountBadge">0 نتيجة</span>
    </div>
    <div class="channels-row" id="searchGrid"></div>
    <div id="searchEmpty" class="hidden" style="text-align:center;padding:60px 0;color:var(--text-muted)">
      <span class="lcn" style="font-size:3rem;margin-bottom:16px;display:block;opacity:.3"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg></span>
      <p>لا توجد نتائج مطابقة</p>
    </div>
  </div>

  <div class="hidden" id="epSection" style="padding:0 20px">
    <button class="back-btn" id="epBackBtn" onclick="backFromEpisodesToHome()"><span class="lcn"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg></span> <span id="epBackLabel">رجوع</span></button>
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px;font-size:1.1rem;font-weight:800;color:#fff">
      <div class="slider-title-icon"><span class="lcn"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="20" x="2" y="2" rx="2.18" ry="2.18"/><line x1="7" x2="7" y1="2" y2="22"/><line x1="17" x2="17" y1="2" y2="22"/><line x1="2" x2="22" y1="7" y2="7"/><line x1="2" x2="22" y1="12" y2="12"/><line x1="2" x2="22" y1="17" y2="17"/></svg></span></div>
      <span id="epSectionTitle">الحلقات</span>
    </div>
    <div class="episodes-grid" id="epGrid"></div>
    <div id="epLoading" class="hidden" style="text-align:center;padding:40px 0"><div class="p-buffer-ring" style="margin:0 auto"></div></div>
    <div id="epEmpty" class="hidden" style="text-align:center;padding:60px 0;color:var(--text-muted)"><p>لا تتوفر حلقات</p></div>
  </div>
</main>

<footer style="background:#0d0d0d;border-top:1px solid rgba(255,255,255,.07);direction:rtl">

  <div style="max-width:1000px;margin:0 auto;padding:48px 32px 32px">

    <!-- الأعلى: الشعار والوصف — نفس تصميم القديم -->
    <div style="text-align:center;margin-bottom:40px">
      <div style="font-size:1.6rem;font-weight:900;color:var(--red);letter-spacing:-1px;margin-bottom:8px"><?php echo htmlspecialchars($site_name); ?></div>
      <p style="color:#444;font-size:.82rem;line-height:1.7;margin:0"><?php echo htmlspecialchars($footer_text); ?></p>
    </div>

    <!-- عنوان التواصل -->
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;justify-content:center">
      <div style="flex:1;max-width:120px;height:1px;background:linear-gradient(to right,transparent,rgba(229,9,20,.3))"></div>
      <span style="font-size:.68rem;font-weight:800;color:#444;text-transform:uppercase;letter-spacing:3px">تواصل معنا</span>
      <div style="flex:1;max-width:120px;height:1px;background:linear-gradient(to left,transparent,rgba(229,9,20,.3))"></div>
    </div>

    <!-- أزرار التواصل أفقية -->
    <?php if(!$hide_social): ?>
    <div style="display:flex;flex-wrap:wrap;gap:12px;justify-content:center;margin-bottom:40px">

      <!-- واتساب -->
  <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $settings['contact_whatsapp'] ?? '9647512328848'); ?>" target="_blank" rel="noopener noreferrer"
         style="display:flex;align-items:center;gap:10px;padding:12px 22px;border-radius:50px;background:rgba(37,211,102,.08);border:1px solid rgba(37,211,102,.2);text-decoration:none;color:#e2e8f0;font-size:.85rem;font-weight:700;transition:all .22s;white-space:nowrap"
         onmouseover="this.style.background='rgba(37,211,102,.2)';this.style.borderColor='rgba(37,211,102,.5)';this.style.transform='translateY(-2px)';this.style.boxShadow='0 6px 20px rgba(37,211,102,.2)'"
         onmouseout="this.style.background='rgba(37,211,102,.08)';this.style.borderColor='rgba(37,211,102,.2)';this.style.transform='translateY(0)';this.style.boxShadow='none'">
        <div style="width:30px;height:30px;border-radius:50%;background:#25D366;display:flex;align-items:center;justify-content:center;flex-shrink:0">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="white"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
        </div>
        واتساب
      </a>

      <!-- فيسبوك -->
      <a href="https://<?php echo htmlspecialchars(ltrim($settings['contact_facebook'] ?? 'facebook.com/xxkpq', 'https://')); ?>" target="_blank" rel="noopener"
         style="display:flex;align-items:center;gap:10px;padding:12px 22px;border-radius:50px;background:rgba(24,119,242,.08);border:1px solid rgba(24,119,242,.2);text-decoration:none;color:#e2e8f0;font-size:.85rem;font-weight:700;transition:all .22s;white-space:nowrap"
         onmouseover="this.style.background='rgba(24,119,242,.2)';this.style.borderColor='rgba(24,119,242,.5)';this.style.transform='translateY(-2px)';this.style.boxShadow='0 6px 20px rgba(24,119,242,.2)'"
         onmouseout="this.style.background='rgba(24,119,242,.08)';this.style.borderColor='rgba(24,119,242,.2)';this.style.transform='translateY(0)';this.style.boxShadow='none'">
        <div style="width:30px;height:30px;border-radius:50%;background:#1877F2;display:flex;align-items:center;justify-content:center;flex-shrink:0">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="white"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
        </div>
        فيسبوك
      </a>

      <!-- البريد -->
      <a href="mailto:<?php echo htmlspecialchars($settings['contact_email'] ?? 'info@shashety-pro.com'); ?>"
         style="display:flex;align-items:center;gap:10px;padding:12px 22px;border-radius:50px;background:rgba(229,9,20,.08);border:1px solid rgba(229,9,20,.2);text-decoration:none;color:#e2e8f0;font-size:.85rem;font-weight:700;transition:all .22s;white-space:nowrap"
         onmouseover="this.style.background='rgba(229,9,20,.2)';this.style.borderColor='rgba(229,9,20,.5)';this.style.transform='translateY(-2px)';this.style.boxShadow='0 6px 20px rgba(229,9,20,.2)'"
         onmouseout="this.style.background='rgba(229,9,20,.08)';this.style.borderColor='rgba(229,9,20,.2)';this.style.transform='translateY(0)';this.style.boxShadow='none'">
        <div style="width:30px;height:30px;border-radius:50%;background:#e50914;display:flex;align-items:center;justify-content:center;flex-shrink:0">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
        </div>
        البريد الإلكتروني
      </a>

    </div>
    <?php endif; ?>

    <!-- فاصل + حقوق -->
    <div style="height:1px;background:rgba(255,255,255,.05);margin-bottom:20px"></div>
    <div style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:10px">
      <span style="color:#2e2e2e;font-size:.74rem"><?php echo htmlspecialchars($footer_text); ?></span>
    </div>

  </div>
</footer>


<div class="toasts" id="toastContainer"></div>

<!-- TMDB Modal -->
<div class="tmdb-modal-overlay" id="tmdbInfoM">
  <div class="tmdb-modal-box">
    <div class="tmdb-modal-head">
      <div style="font-size:1.05rem;font-weight:800;display:flex;align-items:center;gap:10px">
        <span class="lcn" style="color:var(--red)"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg></span> تفاصيل العمل
      </div>
      <button class="tmdb-modal-close" onclick="closeTmdbModal()"><span class="lcn"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg></span></button>
    </div>
    <div class="tmdb-modal-body" id="tmdbInfoBody"></div>
  </div>
</div>

<!-- Panels -->
<div class="ep-panel" id="epPanel">
  <div class="ep-panel-head">
    <div style="font-size:1rem;font-weight:800;color:#fff;display:flex;align-items:center;gap:10px"><span class="lcn" style="color:#B36BFF"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="20" x="2" y="2" rx="2.18" ry="2.18"/><line x1="7" x2="7" y1="2" y2="22"/><line x1="17" x2="17" y1="2" y2="22"/><line x1="2" x2="22" y1="7" y2="7"/><line x1="2" x2="22" y1="12" y2="12"/><line x1="2" x2="22" y1="17" y2="17"/></svg></span><span id="epPanelTitle">الحلقات</span></div>
    <button class="ep-panel-close" onclick="toggleEpPanel()"><span class="lcn"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg></span></button>
  </div>
  <div style="flex:1;overflow-y:auto;padding:14px" id="epPanelBody"></div>
</div>

<div class="fp-panel" id="favPanel">
  <div class="ep-panel-head">
    <div style="font-size:1rem;font-weight:800;color:#fff;display:flex;align-items:center;gap:10px"><span class="lcn" style="color:#ff4d57"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg></span>مفضلتي</div>
    <button class="ep-panel-close" onclick="toggleFavPanel()"><span class="lcn"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg></span></button>
  </div>
  <div style="flex:1;overflow-y:auto;padding:14px" id="favPanelBody"></div>
</div>

<div class="m3u-panel" id="m3uPanel">
  <div class="ep-panel-head">
    <div style="font-size:1rem;font-weight:800;color:#fff;display:flex;align-items:center;gap:10px"><span class="lcn" style="color:#ff4d57"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="10" x2="21" y1="6" y2="6"/><line x1="10" x2="21" y1="12" y2="12"/><line x1="10" x2="21" y1="18" y2="18"/><path d="M4 6h1v4"/><path d="M4 10h2"/><path d="M6 18H4c0-1 2-2 2-3s-1-1.5-2-1"/></svg></span><span id="m3uPanelHead">قائمة التشغيل</span></div>
    <button class="ep-panel-close" onclick="toggleM3UPanel()"><span class="lcn"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg></span></button>
  </div>
  <div style="flex:1;overflow-y:auto;padding:14px" id="m3uPanelBody"></div>
</div>

<div class="np-panel" id="notifPanel">
  <div class="ep-panel-head">
    <div style="font-size:1rem;font-weight:800;color:#fff;display:flex;align-items:center;gap:10px"><span class="lcn" style="color:#ffb020"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg></span>المحتوى المُضاف حديثاً</div>
    <button class="ep-panel-close" onclick="toggleNotifPanel()"><span class="lcn"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg></span></button>
  </div>
  <div style="flex:1;overflow-y:auto;padding:14px" id="notifPanelBody"></div>
</div>

<!-- PLAYER -->
<div class="player-overlay" id="playerOverlay" tabindex="-1">
  <div class="pv-wrap" id="pvWrap" tabindex="-1">
    <video id="html5Player" playsinline preload="auto"></video>
    <div class="p-buffer" id="pBuffer"><div class="p-buffer-ring"></div></div>
    <div class="p-flash"><div class="p-flash-icon" id="pFlash"><span class="lcn"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="6 3 20 12 6 21 6 3"/></svg></span></div></div>
  </div>
  <div class="p-vignette-top"></div>
  <div class="p-vignette-bot"></div>
  <!-- شعارات دعم الجهاز: صوت + صورة + هرتزية -->
  <div id="deviceBadgesWrap"></div>
  <div class="p-top" id="pTop">
    <button type="button" class="p-back" onclick="closePlayer()"><span class="lcn"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg></span></button>
    <div class="p-title-block">
      <div class="p-channel-name" id="pChannelName">—</div>
      <div class="p-title-sub">
        <span class="p-live-badge" id="pBadgeLabel">LIVE</span>
        <span class="p-fmt-tag" id="pFmtTag">HLS</span>
      </div>
    </div>
    <div class="p-top-right">
      <div class="p-ep-nav" id="pEpNav" style="display:none">
        <button type="button" class="p-icon-btn" onclick="navEpisode(-1)" id="pPrevEp"><span class="lcn"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="19 20 9 12 19 4 19 20"/><line x1="5" x2="5" y1="19" y2="5"/></svg></span></button>
        <span id="pEpLabel" class="p-ep-label">الحلقة 1</span>
        <button type="button" class="p-icon-btn" onclick="navEpisode(1)" id="pNextEp"><span class="lcn"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="5 4 15 12 5 20 5 4"/><line x1="19" x2="19" y1="5" y2="19"/></svg></span></button>
      </div>
      <?php if(!$hide_cast): ?>
      <button type="button" class="p-icon-btn" onclick="castToSmartWvc()"><span class="lcn"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 8V6a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-6"/><path d="M2 12a9 9 0 0 1 8 8"/><path d="M2 16a5 5 0 0 1 4 4"/><line x1="2" x2="2.01" y1="20" y2="20"/></svg></span></button>
      <?php endif; ?>
      <?php if(!$hide_download): ?>
      <button type="button" class="p-icon-btn" id="tdmDownloadBtn" onclick="downloadWithTdm()" style="display:none" title="تحميل الفيديو"><span class="lcn"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/></svg></span></button>
      <?php endif; ?>
    </div>
  </div>
  <div class="p-center" id="pCenter">
    <button type="button" class="p-seek-btn" onclick="skip(-10)">
      <span class="lcn"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg></span><span class="p-seek-n">10</span>
    </button>
    <button type="button" class="p-play-btn" id="playBtn" onclick="togglePlay()">
      <span class="lcn"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="4" height="16" x="6" y="4"/><rect width="4" height="16" x="14" y="4"/></svg></span>
    </button>
    <button type="button" class="p-seek-btn" onclick="skip(10)">
      <span class="lcn"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-9-9c2.52 0 4.93 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/></svg></span><span class="p-seek-n">10</span>
    </button>
  </div>
  <div class="p-bottom" id="pBottom">
    <div class="p-prog-row">
      <span class="p-tc" id="pTimeCur">00:00</span>
      <div class="p-prog-wrap" id="pProgress" onclick="seekTo(event)">
        <div class="p-prog-track"><div class="p-prog-fill" id="pFill"><div class="p-prog-dot"></div></div></div>
      </div>
      <span class="p-tc" id="pTimeTotal">00:00</span>
    </div>
    <div class="p-tools">
      <!-- اليمين: ترجمة + تحسين + قوائم (p-tools-l أول في DOM = يمين في RTL) -->
      <div class="p-tools-l">
        <button type="button" class="p-btn" onclick="toggleSubtitle()" id="subBtn"><span class="lcn"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="14" x="3" y="5" rx="2" ry="2"/><path d="M7 15h4m4 0h2M7 11h2m4 0h4"/></svg></span></button>
        <button type="button" class="p-btn p-enh" onclick="toggleEnhancements()" id="enhanceBtn">
          <span class="lcn"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.64 3.64-1.28-1.28a1.21 1.21 0 0 0-1.72 0L2.36 18.64a1.21 1.21 0 0 0 0 1.72l1.28 1.28a1.2 1.2 0 0 0 1.72 0L21.64 5.36a1.2 1.2 0 0 0 0-1.72Z"/><path d="m14 7 3 3"/><path d="M5 6v4"/><path d="M19 14v4"/><path d="M10 2v2"/><path d="M7 8H3"/><path d="M21 16h-4"/><path d="M11 3H9"/></svg></span><span id="enhLabel" class="p-enh-lbl">HD</span>
        </button>
        <button type="button" class="p-btn" onclick="toggleEpPanel()" id="epPanelBtn" style="display:none"><span class="lcn"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" x2="21" y1="6" y2="6"/><line x1="8" x2="21" y1="12" y2="12"/><line x1="8" x2="21" y1="18" y2="18"/><line x1="3" x2="3.01" y1="6" y2="6"/><line x1="3" x2="3.01" y1="12" y2="12"/><line x1="3" x2="3.01" y1="18" y2="18"/></svg></span></button>
        <button type="button" class="p-btn" onclick="toggleM3UPanel()" id="m3uPanelBtn" style="display:none"><span class="lcn"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="10" x2="21" y1="6" y2="6"/><line x1="10" x2="21" y1="12" y2="12"/><line x1="10" x2="21" y1="18" y2="18"/><path d="M4 6h1v4"/><path d="M4 10h2"/><path d="M6 18H4c0-1 2-2 2-3s-1-1.5-2-1"/></svg></span></button>
      </div>
      <!-- اليسار: صوت + وقت + fullscreen (p-tools-r ثاني في DOM = يسار في RTL) -->
      <div class="p-tools-r">
        <button type="button" class="p-btn p-fs-btn" onclick="toggleFullscreen()">
          <span class="lcn" id="fsIcon"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21 21-6-6m6 6v-4.8m0 4.8h-4.8"/><path d="M3 16.2V21m0 0h4.8M3 21l6-6"/><path d="M21 7.8V3m0 0h-4.8M21 3l-6 6"/><path d="M3 7.8V3m0 0h4.8M3 3l6 6"/></svg></span>
        </button>
        <span class="p-time-txt" id="pTime">00:00 / 00:00</span>
        <div class="p-vol-wrap" id="volWrap">
          <button type="button" class="p-btn p-vol-icon" id="muteIcon" onclick="toggleMute()">
            <span class="lcn"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><path d="M15.54 8.46a5 5 0 0 1 0 7.07"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14"/></svg></span>
          </button>
          <div class="p-vol-slider-wrap">
            <div class="p-vol-track" onclick="setVolume(event)">
              <div class="p-vol-fill" id="volFill"></div>
              <div class="p-vol-thumb" id="volThumb"></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- SCREENSAVER -->
<div id="nxScreensaver">
  <div class="nx-bg" id="nxBg"></div>
  <div class="nx-vignette"></div>
  <div class="nx-container" id="nxWrap">
    <div class="nx-info-box">
      <div class="nx-top-badge"><span class="lcn" style="font-size:.7rem"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="6 3 20 12 6 21 6 3"/></svg></span> متاح للمشاهدة</div>
      <h1 class="nx-title" id="nxTitle">—</h1>
      <div class="nx-meta-row">
        <span class="nx-match" id="nxMatchBadge">المطابقة 98%</span>
        <span id="nxYear">2024</span>
        <span class="nx-tag">HD</span>
      </div>
    </div>
    <div><img class="nx-poster" id="nxImg" src="" alt=""></div>
  </div>
  <div class="nx-footer"><span class="nx-bounce-arrow"><span class="lcn"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg></span></span> المس للعودة</div>
</div>

<link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
<link rel="dns-prefetch" href="https://cdn.jsdelivr.net">
<!-- إصدار مثبّت بدل @latest: تخزين مؤقت أقوى وبلا جولة تحديد إصدار.
     preload يبدأ التنزيل فوراً، وasync ينفّذه دون حجب رسم الصفحة. -->
<link rel="preload" as="script" href="https://cdn.jsdelivr.net/npm/hls.js@1.5.17/dist/hls.min.js">
