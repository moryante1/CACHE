<div class="sb-overlay" id="sbOverlay" onclick="closeSidebar()"></div>
<aside class="sidebar" id="sidebar">
  <div class="sbrand" style="display:flex; align-items:center; gap:12px;">
    <div class="sbrand-icon" style="width: 38px; height: 38px; background: linear-gradient(135deg, #E50914, #9a050d); color: #fff; border-radius: 10px; margin:0; box-shadow: 0 4px 15px rgba(229,9,20,0.4); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
        <i data-lucide="layout-dashboard" style="width:1.2rem; height:1.2rem; stroke-width: 2.5;"></i>
    </div>
    <div class="sbrand-text" style="flex:1; display:flex; flex-direction:column; justify-content:center; text-align:left;">
        <div class="sbrand-name" style="font-family: 'Inter', 'Tajawal', sans-serif; font-size: 1.1rem; font-weight: 800; letter-spacing: 1.5px; color: var(--t1); line-height: 1.1;">DASHBOARD</div>
        <div style="font-size: 0.65rem; color: #E50914; font-weight: 800; letter-spacing: 2px; margin-top: 3px;">SH PRO V2.0</div>
    </div>
    <button class="desktop-toggle-btn" onclick="toggleDesktopSidebar()" title="<?= htmlspecialchars($t["tip_collapse"] ?? "طي / توسيع القائمة") ?>">
      <i data-lucide="chevron-right" id="dtoggle-icon"></i>
    </button>
  </div>
  <nav class="snav">
    <div class="snl"><?= $t["nav_main"] ?? "الرئيسية" ?></div>
    <button class="si on" onclick="S('dashboard');closeSidebar();addRipple(event,this)"><span class="si-ic"><i data-lucide="home"></i></span><?= $t["dashboard"] ?? "لوحة التحكم" ?></button>
    <div class="snl"><?= $t["nav_content"] ?? "المحتوى" ?></div>
    <button class="si" onclick="S('categories');closeSidebar();addRipple(event,this)"><span class="si-ic"><i data-lucide="layout-grid"></i></span><?= $t["categories"] ?? "الأقسام" ?></button>
    <button class="si" onclick="S('channels');closeSidebar();addRipple(event,this)"><span class="si-ic"><i data-lucide="tv"></i></span><?= $t["channels"] ?? "القنوات" ?></button>
    <button class="si" onclick="S('m3u-import');m3uLoadPlaylists();closeSidebar();addRipple(event,this)"><span class="si-ic"><i data-lucide="file-up"></i></span><?= $t["m3u_import"] ?? "استيراد M3U" ?></button>
    <!-- [XTREAM-NAV-START] -->
    <button class="si" onclick="S('xtream');xtreamLoadAccounts();closeSidebar();addRipple(event,this)"><span class="si-ic"><i data-lucide="satellite-dish" style="color:#F5A623"></i></span><?= $t["xtream_account"] ?? "حساب Xtream" ?></button>
    <!-- [XTREAM-NAV-END] -->
    <button class="si" onclick="S('series');loadSeries();closeSidebar();addRipple(event,this)"><span class="si-ic"><i data-lucide="film"></i></span><?= $t["series"] ?? "شاشتي" ?></button>
    <button class="si" onclick="S('vupload');closeSidebar();addRipple(event,this)"><span class="si-ic"><i data-lucide="upload-cloud"></i></span><?= $t["upload"] ?? "رفع الأفلام" ?></button>
    <button class="si" onclick="S('vmanage');vmLoad();closeSidebar();addRipple(event,this)"><span class="si-ic"><i data-lucide="video"></i></span><?= $t["manage"] ?? "إدارة الفيديوهات" ?></button>
    <!-- [SUBS-NAV-START] نظام الاشتراكات -->
    <div class="snl"><?= $t["nav_subscriptions"] ?? "الاشتراكات" ?></div>
    <button class="si" onclick="S('subscriptions');loadSubscriptions();closeSidebar();addRipple(event,this)"><span class="si-ic"><i data-lucide="crown" style="color:#F5A623"></i></span><?= $t["nav_plans"] ?? "خطط الاشتراك" ?></button>
    <button class="si" onclick="S('coupons');loadCoupons();closeSidebar();addRipple(event,this)"><span class="si-ic"><i data-lucide="ticket" style="color:#00D084"></i></span><?= $t["nav_coupons"] ?? "أكواد التفعيل" ?></button>
    <button class="si" onclick="S('subscribers');loadSubscribers();closeSidebar();addRipple(event,this)"><span class="si-ic"><i data-lucide="user-check" style="color:#4CC9F0"></i></span><?= $t["nav_subscribers"] ?? "المشتركون" ?></button>
    <!-- [SUBS-NAV-END] -->
    <div class="snl"><?= $t["nav_management"] ?? "الإدارة" ?></div>
    <button class="si" onclick="S('api-settings');closeSidebar();addRipple(event,this)"><span class="si-ic"><i data-lucide="plug"></i></span><?= $t["api_settings"] ?? "إعدادات API" ?></button>
    <button class="si" onclick="S('site-settings');closeSidebar();addRipple(event,this)"><span class="si-ic"><i data-lucide="settings"></i></span><?= $t["settings"] ?? "إعدادات الموقع" ?></button>
    <button class="si" onclick="S('change-password');closeSidebar();addRipple(event,this)"><span class="si-ic"><i data-lucide="key"></i></span><?= $t["password"] ?? "كلمة المرور" ?></button>
    <button class="si" onclick="S('system-tools');closeSidebar();addRipple(event,this)"><span class="si-ic"><i data-lucide="wrench"></i></span><?= $t["tools"] ?? "صيانة النظام" ?></button>
    <button class="si" onclick="S('backup');closeSidebar();addRipple(event,this)"><span class="si-ic"><i data-lucide="database"></i></span><?= $t["backup"] ?? "النسخ الاحتياطي" ?></button>
    <button class="si" onclick="S('users');loadUsers();closeSidebar();addRipple(event,this)"><span class="si-ic"><i data-lucide="users"></i></span><?= $t["users"] ?? "إدارة المستخدمين" ?></button>
    <button class="si" onclick="S('login-logs');loadLoginLogs();closeSidebar();addRipple(event,this)"><span class="si-ic"><i data-lucide="shield"></i></span><?= $t["login_logs"] ?? "سجل الدخول" ?></button>
        <!-- Theme Button in Sidebar -->
    <div class="snl"><?= $t["customization"] ?? "التخصيص" ?></div>
    <button class="si" onclick="S('general-settings');loadGeneralSettings();closeSidebar();addRipple(event,this)"><span class="si-ic"><i data-lucide="sliders-horizontal" style="color:#F5A623"></i></span>⚙️ <?= $t["general_settings"] ?? "الإعدادات العامة" ?></button>
    <button class="si" onclick="toggleThemePanel();closeSidebar();addRipple(event,this)"><span class="si-ic"><i data-lucide="palette" style="color:#B36BFF"></i></span>🎨 <?= $t["themes_colors"] ?? "الثيمات والألوان" ?></button>
    <button class="si" onclick="S('frontend-control');loadFrontendToggles();closeSidebar();addRipple(event,this)"><span class="si-ic"><i data-lucide="layout-dashboard" style="color:#00D084"></i></span><?= $t["frontend_control"] ?? "التحكم بالواجهة الأمامية" ?></button>
    <div class="snl"><?= $t["about_system"] ?? "حول النظام" ?></div>
    <button class="si" onclick="S('company-info');closeSidebar();addRipple(event,this)"><span class="si-ic"><i data-lucide="info" style="color:#4CC9F0"></i></span><?= $t["company_info"] ?? "حول الشركة" ?></button>
  </nav>

</aside>

<div class="main">
<header class="topbar">
  <button class="mob-menu-btn" id="hamburgerBtn" onclick="toggleSidebar()" aria-label="القائمة">
    <div class="ham-icon">
      <span></span><span></span><span></span>
    </div>
  </button>
  <span class="tbtitle" id="tbTitle"><?= $t["dashboard_word"] ?? "لوحة التحكم" ?></span>
  <div class="tbr">

    <!-- Music Mini Player -->
    <div class="music-p-mini" onclick="toggleAdminMusic()" title="<?= htmlspecialchars($t["tip_music"] ?? "إيقاف / تشغيل موسيقى الخلفية") ?>">
        <i class="fas fa-music" style="color:var(--t3); font-size:0.8rem;"></i>
        <div class="m-eq paused" id="m_eq"><span></span><span></span><span></span></div>
    </div>

    <!-- Day/Night Mode Toggle (إضافة) -->
    <button class="mode-toggle" id="modeToggle" onclick="toggleDayNight()" title="<?= htmlspecialchars($t["tip_darkmode"] ?? "تبديل الوضع الليلي / النهاري") ?>" aria-label="تبديل الوضع">
      <i class="fas fa-moon" id="modeIcon"></i>
    </button>

    <!-- Language Switcher -->
    <div class="lang-sw">
      <button class="lang-btn" onclick="document.getElementById('langDrop').classList.toggle('op'); event.stopPropagation();">
        <i class="fas fa-globe" style="color:var(--t3)"></i> <span><?= strtoupper($__cur_lang) ?></span>
      </button>
      <div class="lang-drop" id="langDrop">
        <a class="lang-opt" href="?lang=ar">
            <span class="lang-flag">🇸🇦</span><span><?= $t["lang_arabic"] ?? "العربية" ?></span>
        </a>
        <a class="lang-opt" href="?lang=en">
            <span class="lang-flag">🇬🇧</span><span>English</span>
        </a>
        <a class="lang-opt" href="?lang=tr">
            <span class="lang-flag">🇹🇷</span><span>Türkçe</span>
        </a>
      </div>
    </div>
    <!-- End Language Switcher -->
    
    <div class="lic-b"><span class="lic-dot"></span><?php $lt=htmlspecialchars($_SESSION['license_info']['license_type_name']??'نشطة');$dl=$_SESSION['license_days_left']??0;$ds=($dl==='unlimited'||$dl>9999)?'∞':"$dl يوم";echo "$lt · $ds"; ?></div>

    <!-- ══ قائمة البروفايل المنسدلة (مع صورة + تسجيل خروج) ══ -->
    <div class="prof-sw" id="profSw">
      <button class="prof-btn" onclick="document.getElementById('profSw').classList.toggle('op'); event.stopPropagation();" aria-label="حساب المستخدم">
        <img class="prof-avt-img" src="assets/22.png" alt="" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
        <div class="uavt" style="display:none"><?php echo strtoupper(substr($_admin_display ?? ($_SESSION['admin_username']??'A'),0,1)); ?></div>
        <span class="prof-name"><?php echo htmlspecialchars($_admin_display ?? ($_SESSION['admin_username']??'المدير')); ?></span>
        <i class="fas fa-chevron-down prof-caret"></i>
      </button>
      <div class="prof-drop">
        <div class="prof-head">
          <img class="prof-avt-img" src="assets/22.png" alt="" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
          <div class="uavt" style="display:none"><?php echo strtoupper(substr($_admin_display ?? ($_SESSION['admin_username']??'A'),0,1)); ?></div>
          <div class="prof-head-info">
            <b><?php echo htmlspecialchars($_admin_display ?? ($_SESSION['admin_username']??'المدير')); ?></b>
            <small><?php echo htmlspecialchars($_SESSION['admin_username']??''); ?></small>
          </div>
        </div>
        <button class="prof-logout" onclick="if(confirm('تسجيل الخروج؟'))location.href='logout.php'">
          <i data-lucide="log-out"></i><span><?= $t["logout"] ?? "تسجيل الخروج" ?></span>
        </button>
      </div>
    </div>
  </div>
</header>
<div class="pcont">
