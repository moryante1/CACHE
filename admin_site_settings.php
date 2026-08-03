<?php
require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/client_config.php';

/* ══════════════════════════════════════════════════════════════
   🔴 ثغرة تجاوز مصادقة كاملة كانت هنا — أُصلحت
   ──────────────────────────────────────────────────────────────
   لم يكن في هذا الملف **أي** فحص لتسجيل الدخول إطلاقاً. الحماية
   الوحيدة كانت ترويسة Referer:

       $referer_ok = strpos($referer, 'admin.php') !== false;
       if ($referer_ok) { $_SESSION['ss_token'] = bin2hex(...); }

   وترويسة Referer يرسلها المتصفح ويستطيع أي شخص تزويرها بسطر واحد.
   والأسوأ أن الرمز السرّي كان يُولَّد **داخل نفس الطلب المزوَّر**،
   فالهجوم كان:

     ١. المهاجم يرسل GET مع ترويسة:  Referer: https://موقعك/admin.php
     ٢. الخادم يقتنع، ويُنشئ له ss_token، ويعرض له الصفحة كاملة
     ٣. المهاجم يقرأ الرمز من الصفحة ويرسل POST به
     ٤. يعدّل إعدادات موقعك بالكامل — دون أن يسجّل الدخول ولا مرة

   ملاحظة إضافية: ربط الجلسة بـ REMOTE_ADDR كان يقطع الجلسة على
   مستخدمي الجوال عند تنقّل الشبكة (تغيّر IP) — إزعاج بلا فائدة
   أمنية تُذكر ما دامت المصادقة الحقيقية غائبة.

   الحماية الصحيحة: جلسة مدير مسجَّل الدخول + رمز CSRF الموحَّد
   المستخدم في بقية المشروع.
══════════════════════════════════════════════════════════════ */

securityHeaders();

// ① المصادقة الحقيقية — لا شيء قبلها
if (!isAdminLoggedIn()) {
    if (!headers_sent()) {
        header('Location: login.php');
    }
    exit;
}

// ② تحقق CSRF على أي طلب يغيّر البيانات
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $__ok = csrfValidate()                       // الرمز الموحَّد للمشروع
        || csrfValidate((string)($_POST['_csrf_token'] ?? '')); // توافق مع النموذج القديم

    if (!$__ok) {
        http_response_code(403);
        exit('انتهت صلاحية الجلسة. أعد تحميل الصفحة وحاول مجدداً.');
    }
}

// رمز للنموذج (يبقى الاسم القديم _csrf_token حتى لا ينكسر أي حقل قائم)
$_SESSION['ss_token'] = csrfToken();

// ── وصلنا هنا = مدير مسجَّل الدخول وطلبه سليم ──

$stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
$settings = [];
while ($row = $stmt->fetch()) $settings[$row['setting_key']] = $row['setting_value'];

$msg = '';
$msg_type = '';

// معالجة الحفظ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'save_settings') {
        $fields = [
            'site_name', 'site_description', 'site_logo',
            'welcome_title', 'welcome_subtitle', 'footer_text',
            'theme_color', 'custom_css',
            'contact_whatsapp', 'contact_facebook', 'contact_email'
        ];
        try {
            foreach ($fields as $key) {
                $val = $_POST[$key] ?? '';
                // تنظيف custom_css من الـ tags
                if ($key === 'custom_css') $val = strip_tags($val);
                $check = $pdo->prepare("SELECT COUNT(*) FROM settings WHERE setting_key = ?");
                $check->execute([$key]);
                if ($check->fetchColumn() > 0) {
                    $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?")
                        ->execute([$val, $key]);
                } else {
                    $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)")
                        ->execute([$key, $val]);
                }
                $settings[$key] = $val;
            }
            $msg = '✓ تم حفظ جميع الإعدادات بنجاح';
            $msg_type = 'success';
        } catch (Exception $e) {
            $msg = '✕ خطأ في الحفظ: ' . htmlspecialchars($e->getMessage());
            $msg_type = 'error';
        }
    }
}

// قيم افتراضية
$s = [
    'site_name'        => $settings['site_name']        ?? 'Shashety',
    'site_description' => $settings['site_description'] ?? 'نظام IPTV احترافي',
    'site_logo'        => $settings['site_logo']        ?? '',
    'welcome_title'    => $settings['welcome_title']    ?? 'مرحباً بك في عالم البث المباشر',
    'welcome_subtitle' => $settings['welcome_subtitle'] ?? 'شاهد آلاف القنوات من جميع أنحاء العالم',
    'footer_text'      => $settings['footer_text']      ?? 'جميع الحقوق محفوظة © 2024 Shashety',
    'theme_color'      => $settings['theme_color']      ?? '#e50914',
    'custom_css'       => $settings['custom_css']       ?? '',
    'contact_whatsapp' => $settings['contact_whatsapp'] ?? '9647512328848',
    'contact_facebook' => $settings['contact_facebook'] ?? 'facebook.com/xxkpq',
    'contact_email'    => $settings['contact_email']    ?? 'info@shashety-pro.com',
];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>إعدادات الموقع — <?php echo htmlspecialchars($s['site_name']); ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;900&display=swap" rel="stylesheet">
<style>
:root{
  --red:#e50914;--red-dim:rgba(229,9,20,.15);--red-border:rgba(229,9,20,.3);
  --bg:#0f0f0f;--bg2:#181818;--bg3:#202020;--bg4:#252525;
  --surface:rgba(28,28,28,.97);
  --border:rgba(255,255,255,.08);--border-h:rgba(255,255,255,.15);
  --text:#f0f0f0;--text-dim:#999;--text-muted:#555;
  --radius:10px;--radius-lg:16px;--radius-xl:24px;
  --shadow:0 10px 40px rgba(0,0,0,.7);
  --ease:cubic-bezier(0.25,1,0.4,1);
  --theme:<?php echo htmlspecialchars($s['theme_color']); ?>;
}
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
html{scroll-behavior:smooth}
body{
  font-family:'Cairo',sans-serif;
  background:var(--bg);
  color:var(--text);
  min-height:100vh;
  -webkit-font-smoothing:antialiased;
}

/* ═══ SCROLLBAR ═══ */
::-webkit-scrollbar{width:4px}
::-webkit-scrollbar-track{background:transparent}
::-webkit-scrollbar-thumb{background:rgba(255,255,255,.1);border-radius:99px}
::-webkit-scrollbar-thumb:hover{background:var(--red)}

/* ═══ KEYFRAMES ═══ */
@keyframes fadeUp{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}
@keyframes spin{to{transform:rotate(360deg)}}
@keyframes pulse{0%,100%{box-shadow:0 0 0 0 rgba(229,9,20,.4)}70%{box-shadow:0 0 0 8px rgba(229,9,20,0)}}
@keyframes slideIn{from{opacity:0;transform:translateX(40px) scale(.95)}to{opacity:1;transform:translateX(0) scale(1)}}

/* ═══ NAVBAR ═══ */
.navbar{
  position:sticky;top:0;z-index:100;
  background:rgba(10,10,10,.9);
  backdrop-filter:blur(20px);
  border-bottom:1px solid var(--border);
  padding:0 32px;
  height:62px;
  display:flex;align-items:center;justify-content:space-between;
}
.navbar-brand{font-size:1.2rem;font-weight:900;color:var(--red);letter-spacing:-0.5px}
.navbar-brand span{color:var(--text-dim);font-weight:400;font-size:.85rem;margin-right:12px}
.nav-back{
  display:flex;align-items:center;gap:8px;
  padding:8px 18px;border-radius:99px;
  background:rgba(255,255,255,.05);
  border:1px solid var(--border);
  color:var(--text-dim);font-size:.85rem;font-weight:600;
  text-decoration:none;transition:all .2s;
}
.nav-back:hover{background:var(--red-dim);border-color:var(--red-border);color:var(--red)}

/* ═══ LAYOUT ═══ */
.page-wrap{
  max-width:860px;
  margin:0 auto;
  padding:40px 24px 80px;
  animation:fadeUp .4s var(--ease);
}

/* ═══ PAGE HEADER ═══ */
.page-header{margin-bottom:40px;display:flex;align-items:center;gap:16px}
.page-header-icon{
  width:52px;height:52px;border-radius:14px;
  background:var(--red-dim);border:1px solid var(--red-border);
  display:flex;align-items:center;justify-content:center;
  font-size:1.4rem;flex-shrink:0;
}
.page-header h1{font-size:1.5rem;font-weight:900;margin-bottom:4px}
.page-header p{color:var(--text-muted);font-size:.85rem}

/* ═══ SECTION ═══ */
.section{
  background:var(--bg2);
  border:1px solid var(--border);
  border-radius:var(--radius-lg);
  margin-bottom:20px;
  overflow:hidden;
  transition:border-color .2s;
}
.section:hover{border-color:var(--border-h)}
.section-header{
  padding:20px 24px 16px;
  display:flex;align-items:center;gap:12px;
  border-bottom:1px solid var(--border);
  cursor:pointer;
  user-select:none;
}
.section-header:hover .section-toggle{color:var(--red)}
.section-icon{
  width:36px;height:36px;border-radius:10px;
  background:var(--red-dim);border:1px solid var(--red-border);
  display:flex;align-items:center;justify-content:center;
  font-size:1rem;flex-shrink:0;
  color:var(--red);
}
.section-icon svg{width:18px;height:18px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.section-title{font-size:.95rem;font-weight:800;flex:1}
.section-desc{font-size:.75rem;color:var(--text-muted);margin-top:2px}
.section-toggle{
  color:var(--text-muted);transition:transform .3s,color .2s;
  display:flex;align-items:center;
}
.section-toggle svg{width:18px;height:18px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.section-toggle.open{transform:rotate(180deg)}
.section-body{padding:24px;display:flex;flex-direction:column;gap:20px}
.section-body.collapsed{display:none}

/* ═══ FORM FIELDS ═══ */
.field{display:flex;flex-direction:column;gap:7px}
.field-row{display:grid;grid-template-columns:1fr 1fr;gap:16px}
@media(max-width:600px){.field-row{grid-template-columns:1fr}}
label{
  font-size:.78rem;font-weight:700;color:var(--text-dim);
  display:flex;align-items:center;gap:6px;
}
label .badge{
  font-size:.62rem;padding:1px 7px;border-radius:99px;
  background:var(--red-dim);color:var(--red);
  border:1px solid var(--red-border);font-weight:800;
}
input[type="text"],
input[type="email"],
input[type="url"],
input[type="color"],
input[type="password"],
textarea,select{
  width:100%;
  background:var(--bg3);
  border:1.5px solid var(--border);
  border-radius:var(--radius);
  color:var(--text);
  font-family:'Cairo',sans-serif;
  font-size:.88rem;
  padding:11px 14px;
  transition:border-color .2s,background .2s,box-shadow .2s;
  outline:none;
}
input[type="text"]:focus,
input[type="email"]:focus,
input[type="url"]:focus,
input[type="password"]:focus,
textarea:focus,select:focus{
  border-color:var(--red);
  background:var(--bg4);
  box-shadow:0 0 0 3px rgba(229,9,20,.12);
}
textarea{resize:vertical;min-height:100px;line-height:1.6}
input[type="color"]{
  padding:4px 8px;height:44px;cursor:pointer;
}
.field-hint{font-size:.72rem;color:var(--text-muted);line-height:1.5;margin-top:2px}

/* ═══ LOGO PREVIEW ═══ */
.logo-wrap{display:flex;align-items:center;gap:16px;flex-wrap:wrap}
.logo-preview{
  width:64px;height:64px;border-radius:12px;
  background:var(--bg4);border:1px solid var(--border);
  display:flex;align-items:center;justify-content:center;
  overflow:hidden;flex-shrink:0;
}
.logo-preview img{width:100%;height:100%;object-fit:contain}
.logo-preview .no-img{font-size:1.6rem;color:var(--text-muted)}
.logo-input-wrap{flex:1;min-width:200px}

/* ═══ COLOR PICKER ═══ */
.color-wrap{display:flex;align-items:center;gap:12px}
.color-swatch{
  width:44px;height:44px;border-radius:10px;
  border:2px solid rgba(255,255,255,.1);
  cursor:pointer;transition:transform .2s;flex-shrink:0;
}
.color-swatch:hover{transform:scale(1.08)}
.color-presets{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px}
.color-preset{
  width:30px;height:30px;border-radius:8px;
  cursor:pointer;border:2px solid transparent;
  transition:all .2s;flex-shrink:0;
}
.color-preset:hover,.color-preset.active{
  border-color:#fff;transform:scale(1.15);
  box-shadow:0 0 10px rgba(255,255,255,.3);
}

/* ═══ CONTACT ICONS ═══ */
.contact-field{display:flex;align-items:center;gap:12px}
.contact-icon{
  width:42px;height:42px;border-radius:10px;
  display:flex;align-items:center;justify-content:center;
  flex-shrink:0;
}
.contact-icon svg{width:20px;height:20px}

/* ═══ CSS EDITOR ═══ */
.css-editor{
  font-family:'Courier New',monospace;
  font-size:.8rem;
  min-height:160px;
  line-height:1.6;
  background:#0a0a0a;
  color:#e2e8f0;
  border-color:rgba(255,255,255,.1);
}
.css-editor:focus{border-color:rgba(229,9,20,.4)}

/* ═══ PREVIEW BOX ═══ */
.preview-box{
  background:var(--bg3);border:1px solid var(--border);
  border-radius:var(--radius);padding:20px;
  position:relative;overflow:hidden;
}
.preview-box::before{
  content:'معاينة';position:absolute;top:8px;left:12px;
  font-size:.62rem;color:var(--text-muted);font-weight:700;
  text-transform:uppercase;letter-spacing:2px;
}
.preview-hero-title{
  font-size:clamp(1.2rem,3vw,1.8rem);
  font-weight:900;color:#fff;margin-bottom:6px;margin-top:16px;
}
.preview-hero-sub{color:#888;font-size:.85rem}
.preview-footer{
  margin-top:16px;padding-top:16px;
  border-top:1px solid rgba(255,255,255,.06);
  text-align:center;color:#333;font-size:.75rem;
}
.preview-site-name{font-size:1.1rem;font-weight:900;margin-bottom:4px}

/* ═══ SAVE BUTTON ═══ */
.save-bar{
  position:sticky;bottom:0;z-index:50;
  background:rgba(10,10,10,.95);
  backdrop-filter:blur(20px);
  border-top:1px solid var(--border);
  padding:16px 24px;
  display:flex;align-items:center;justify-content:space-between;gap:16px;
  margin:0 -24px;
}
.btn-save{
  display:flex;align-items:center;gap:10px;
  background:var(--red);color:#fff;
  border:none;border-radius:99px;
  padding:13px 32px;
  font-family:'Cairo',sans-serif;
  font-size:.92rem;font-weight:800;
  cursor:pointer;
  transition:all .22s;
  box-shadow:0 4px 20px rgba(229,9,20,.35);
}
.btn-save:hover{
  background:#ff1a27;transform:translateY(-2px);
  box-shadow:0 8px 28px rgba(229,9,20,.5);
}
.btn-save:active{transform:translateY(0)}
.btn-save svg{width:18px;height:18px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.btn-save.loading svg.spin-icon{animation:spin .8s linear infinite}
.save-hint{font-size:.78rem;color:var(--text-muted)}

/* ═══ TOAST ═══ */
.toast-wrap{
  position:fixed;bottom:90px;left:24px;z-index:9999;
  display:flex;flex-direction:column;gap:10px;
}
.toast{
  background:var(--bg2);
  border:1px solid var(--border);
  border-radius:var(--radius);
  padding:13px 18px;
  font-size:.85rem;font-weight:600;
  display:flex;align-items:center;gap:10px;
  box-shadow:var(--shadow);
  animation:slideIn .35s var(--ease);
  min-width:260px;
}
.toast.success{border-right:3px solid #25D366;color:#fff}
.toast.error{border-right:3px solid var(--red);color:#fff}
.toast-icon{font-size:1.1rem;flex-shrink:0}
</style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar">
  <div class="navbar-brand">
    <?php echo htmlspecialchars($s['site_name']); ?>
    <span>/ إعدادات الموقع</span>
  </div>
  <a href="index.php" class="nav-back">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
    الصفحة الرئيسية
  </a>
</nav>

<div class="page-wrap">

  <!-- PAGE HEADER -->
  <div class="page-header">
    <div class="page-header-icon">⚙️</div>
    <div>
      <h1>إعدادات الموقع الرئيسية</h1>
      <p>تحكم بجميع نصوص وألوان وبيانات التواصل الظاهرة للزوار</p>
    </div>
  </div>

  <form method="POST" id="settingsForm">
    <input type="hidden" name="action" value="save_settings">
    <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($_SESSION['ss_token'] ?? ''); ?>">

  <!-- ══════════════════════════════
       القسم 1: هوية الموقع
  ══════════════════════════════ -->
  <div class="section">
    <div class="section-header" onclick="toggleSection(this)">
      <div class="section-icon">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/><path d="M2 12h20"/></svg>
      </div>
      <div>
        <div class="section-title">هوية الموقع</div>
        <div class="section-desc">الاسم · الشعار · الوصف · لون الثيم</div>
      </div>
      <div class="section-toggle open">
        <svg viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
      </div>
    </div>
    <div class="section-body">

      <!-- اسم الموقع + لون الثيم -->
      <div class="field-row">
        <div class="field">
          <label>اسم الموقع <span class="badge">مطلوب</span></label>
          <input type="text" name="site_name" value="<?php echo htmlspecialchars($s['site_name']); ?>"
                 placeholder="Shashety IPTV" oninput="liveUpdate('site_name',this.value)">
        </div>
        <div class="field">
          <label>لون الثيم الرئيسي</label>
          <div class="color-wrap">
            <input type="color" name="theme_color" id="themeColor"
                   value="<?php echo htmlspecialchars($s['theme_color']); ?>"
                   oninput="updateThemeColor(this.value)"
                   style="flex:1;height:44px;padding:4px 8px">
          </div>
          <div class="color-presets">
            <?php foreach(['#e50914','#ff6b35','#f59e0b','#10b981','#3b82f6','#8b5cf6','#ec4899','#06b6d4'] as $c): ?>
            <div class="color-preset <?php echo $s['theme_color']===$c?'active':''; ?>"
                 style="background:<?php echo $c; ?>"
                 onclick="setColor('<?php echo $c; ?>')"
                 title="<?php echo $c; ?>"></div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <!-- وصف الموقع -->
      <div class="field">
        <label>وصف الموقع <span style="color:var(--text-muted);font-weight:400">(SEO)</span></label>
        <input type="text" name="site_description" value="<?php echo htmlspecialchars($s['site_description']); ?>"
               placeholder="نظام IPTV احترافي">
      </div>

      <!-- الشعار -->
      <div class="field">
        <label>رابط شعار الموقع (Logo URL)</label>
        <div class="logo-wrap">
          <div class="logo-preview" id="logoPreview">
            <?php if($s['site_logo']): ?>
            <img src="<?php echo htmlspecialchars($s['site_logo']); ?>" id="logoImg" alt="logo">
            <?php else: ?>
            <span class="no-img" id="logoImg">📺</span>
            <?php endif; ?>
          </div>
          <div class="logo-input-wrap">
            <input type="url" name="site_logo" id="logoUrl"
                   value="<?php echo htmlspecialchars($s['site_logo']); ?>"
                   placeholder="https://example.com/logo.png"
                   oninput="previewLogo(this.value)">
            <div class="field-hint">ادخل رابط الصورة أو اتركه فارغاً لإظهار اسم الموقع فقط</div>
          </div>
        </div>
      </div>

    </div>
  </div>

  <!-- ══════════════════════════════
       القسم 2: الصفحة الرئيسية
  ══════════════════════════════ -->
  <div class="section">
    <div class="section-header" onclick="toggleSection(this)">
      <div class="section-icon">
        <svg viewBox="0 0 24 24"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
      </div>
      <div>
        <div class="section-title">نصوص الصفحة الرئيسية</div>
        <div class="section-desc">العنوان الترحيبي · الوصف · نص الفوتر</div>
      </div>
      <div class="section-toggle open">
        <svg viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
      </div>
    </div>
    <div class="section-body">

      <!-- معاينة حية -->
      <div class="preview-box">
        <div class="preview-hero-title" id="prevTitle"><?php echo htmlspecialchars($s['welcome_title']); ?></div>
        <div class="preview-hero-sub" id="prevSub"><?php echo htmlspecialchars($s['welcome_subtitle']); ?></div>
        <div class="preview-footer">
          <div class="preview-site-name" id="prevSiteName" style="color:var(--red)"><?php echo htmlspecialchars($s['site_name']); ?></div>
          <div id="prevFooter"><?php echo htmlspecialchars($s['footer_text']); ?></div>
        </div>
      </div>

      <div class="field">
        <label>عنوان الترحيب <span class="badge">يظهر في الأعلى</span></label>
        <input type="text" name="welcome_title"
               value="<?php echo htmlspecialchars($s['welcome_title']); ?>"
               placeholder="مرحباً بك في عالم البث المباشر"
               oninput="liveUpdate('welcome_title',this.value)">
      </div>

      <div class="field">
        <label>وصف الترحيب</label>
        <input type="text" name="welcome_subtitle"
               value="<?php echo htmlspecialchars($s['welcome_subtitle']); ?>"
               placeholder="شاهد آلاف القنوات من جميع أنحاء العالم"
               oninput="liveUpdate('welcome_subtitle',this.value)">
      </div>

      <div class="field">
        <label>نص حقوق الملكية (Footer)</label>
        <input type="text" name="footer_text"
               value="<?php echo htmlspecialchars($s['footer_text']); ?>"
               placeholder="جميع الحقوق محفوظة © 2024 Shashety"
               oninput="liveUpdate('footer_text',this.value)">
      </div>

    </div>
  </div>

  <!-- ══════════════════════════════
       القسم 3: معلومات التواصل
  ══════════════════════════════ -->
  <div class="section">
    <div class="section-header" onclick="toggleSection(this)">
      <div class="section-icon">
        <svg viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.79 19.79 0 0 1 1.61 3.41 2 2 0 0 1 3.6 1.22h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 8.77a16 16 0 0 0 6.29 6.29l.87-.87a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
      </div>
      <div>
        <div class="section-title">معلومات التواصل</div>
        <div class="section-desc">واتساب · فيسبوك · البريد الإلكتروني</div>
      </div>
      <div class="section-toggle open">
        <svg viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
      </div>
    </div>
    <div class="section-body">

      <!-- واتساب -->
      <div class="field">
        <label>
          <div class="contact-icon" style="background:rgba(37,211,102,.12);border-radius:8px;padding:4px;display:inline-flex">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="#25D366"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
          </div>
          رقم واتساب
        </label>
        <input type="text" name="contact_whatsapp"
               value="<?php echo htmlspecialchars($s['contact_whatsapp']); ?>"
               placeholder="9647512328848"
               style="direction:ltr">
        <div class="field-hint">أدخل الرقم مع رمز الدولة بدون + أو مسافات (مثال: 9647512328848)</div>
      </div>

      <!-- فيسبوك -->
      <div class="field">
        <label>
          <div class="contact-icon" style="background:rgba(24,119,242,.12);border-radius:8px;padding:4px;display:inline-flex">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="#1877F2"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
          </div>
          صفحة فيسبوك
        </label>
        <input type="text" name="contact_facebook"
               value="<?php echo htmlspecialchars($s['contact_facebook']); ?>"
               placeholder="facebook.com/pagename"
               style="direction:ltr">
        <div class="field-hint">يمكن كتابة الرابط الكامل أو اسم الصفحة فقط</div>
      </div>

      <!-- البريد -->
      <div class="field">
        <label>
          <div class="contact-icon" style="background:rgba(229,9,20,.12);border-radius:8px;padding:4px;display:inline-flex">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#e50914" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
          </div>
          البريد الإلكتروني
        </label>
        <input type="email" name="contact_email"
               value="<?php echo htmlspecialchars($s['contact_email']); ?>"
               placeholder="info@yoursite.com"
               style="direction:ltr">
      </div>

    </div>
  </div>

  <!-- ══════════════════════════════
       القسم 4: CSS مخصص
  ══════════════════════════════ -->
  <div class="section">
    <div class="section-header" onclick="toggleSection(this)">
      <div class="section-icon">
        <svg viewBox="0 0 24 24"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
      </div>
      <div>
        <div class="section-title">CSS مخصص</div>
        <div class="section-desc">أكواد تنسيق إضافية تُطبَّق على الموقع</div>
      </div>
      <div class="section-toggle">
        <svg viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
      </div>
    </div>
    <div class="section-body collapsed">
      <div class="field">
        <label>كود CSS مخصص</label>
        <textarea name="custom_css" class="css-editor" placeholder="/* أكواد CSS إضافية هنا */
.ch-card { border-radius: 20px; }"><?php echo htmlspecialchars($s['custom_css']); ?></textarea>
        <div class="field-hint">⚠️ هذا الكود يُطبَّق على الموقع مباشرة — تأكد من صحته قبل الحفظ</div>
      </div>
    </div>
  </div>

  <!-- SAVE BAR -->
  <div class="save-bar">
    <span class="save-hint" id="saveHint">أي تغيير يحتاج حفظ يدوي</span>
    <button type="submit" class="btn-save" id="saveBtn">
      <svg viewBox="0 0 24 24" class="spin-icon" id="saveIcon"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
      حفظ جميع الإعدادات
    </button>
  </div>

  </form>
</div>

<!-- TOAST -->
<div class="toast-wrap" id="toastWrap"></div>

<?php if($msg): ?>
<script>
document.addEventListener('DOMContentLoaded',function(){
  showToast(<?php echo json_encode($msg); ?>, <?php echo json_encode($msg_type); ?>);
});
</script>
<?php endif; ?>

<script>
/* ═══ SECTION TOGGLE ═══ */
function toggleSection(header){
  const body = header.nextElementSibling;
  const icon = header.querySelector('.section-toggle');
  const isOpen = !body.classList.contains('collapsed');
  body.classList.toggle('collapsed', isOpen);
  icon.classList.toggle('open', !isOpen);
}

/* ═══ LIVE PREVIEW ═══ */
function liveUpdate(key, val){
  const map = {
    'welcome_title':  'prevTitle',
    'welcome_subtitle': 'prevSub',
    'footer_text':    'prevFooter',
    'site_name':      'prevSiteName',
  };
  if(map[key]){
    const el = document.getElementById(map[key]);
    if(el) el.textContent = val || ' ';
  }
  markChanged();
}

/* ═══ THEME COLOR ═══ */
function updateThemeColor(val){
  document.documentElement.style.setProperty('--red', val);
  document.documentElement.style.setProperty('--red-dim', val + '26');
  document.documentElement.style.setProperty('--red-border', val + '4d');
  // تحديث الـ presets
  document.querySelectorAll('.color-preset').forEach(p=>{
    p.classList.toggle('active', p.style.background === val ||
      p.getAttribute('onclick')?.includes(val));
  });
  markChanged();
}

function setColor(val){
  document.getElementById('themeColor').value = val;
  updateThemeColor(val);
}

/* ═══ LOGO PREVIEW ═══ */
function previewLogo(url){
  const wrap = document.getElementById('logoPreview');
  if(!url){
    wrap.innerHTML = '<span class="no-img">📺</span>';
    return;
  }
  const img = document.createElement('img');
  img.src = url;
  img.style.cssText = 'width:100%;height:100%;object-fit:contain';
  img.onerror = () => wrap.innerHTML = '<span class="no-img" style="color:#555">✕</span>';
  wrap.innerHTML = '';
  wrap.appendChild(img);
  markChanged();
}

/* ═══ SAVE INDICATOR ═══ */
let _changed = false;
function markChanged(){
  if(_changed) return;
  _changed = true;
  const hint = document.getElementById('saveHint');
  if(hint){
    hint.textContent = '● يوجد تغييرات غير محفوظة';
    hint.style.color = '#f59e0b';
  }
}

// تتبع أي تغيير في أي حقل
document.querySelectorAll('input,textarea,select').forEach(el=>{
  el.addEventListener('input', markChanged);
  el.addEventListener('change', markChanged);
});

/* ═══ FORM SUBMIT ═══ */
document.getElementById('settingsForm').addEventListener('submit', function(e){
  const btn = document.getElementById('saveBtn');
  const icon = document.getElementById('saveIcon');
  btn.disabled = true;
  btn.style.opacity = '.7';
  // تغيير الأيقونة لـ spinner
  icon.innerHTML = '<path d="M21 12a9 9 0 1 1-9-9c2.52 0 4.93 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/>';
  icon.classList.add('spin-icon');
  icon.style.animation = 'spin .8s linear infinite';
});

/* ═══ TOAST ═══ */
function showToast(msg, type='success'){
  const wrap = document.getElementById('toastWrap');
  const t = document.createElement('div');
  t.className = 'toast ' + type;
  const icon = type === 'success' ? '✓' : '✕';
  t.innerHTML = `<span class="toast-icon">${icon}</span>${msg}`;
  wrap.appendChild(t);
  setTimeout(()=>{
    t.style.transition = 'opacity .3s,transform .3s';
    t.style.opacity = '0';
    t.style.transform = 'translateX(40px)';
    setTimeout(()=>t.remove(), 350);
  }, 3500);
}

/* ═══ WARN ON LEAVE ═══ */
window.addEventListener('beforeunload', function(e){
  if(_changed){
    e.preventDefault();
    e.returnValue = '';
  }
});

// إعادة تعيين _changed بعد الحفظ
<?php if($msg_type === 'success'): ?>
_changed = false;
<?php endif; ?>
</script>
</body>
</html>
