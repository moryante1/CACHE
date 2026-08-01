<style>
/* ════ ROOT VARIABLES ════ */
:root {
  --red:#e50914; --bg:#0f0f0f; --bg2:#181818; --bg3:#202020;
  --surface:rgba(28,28,28,.97); --border:rgba(255,255,255,.1);
  --text:#f0f0f0; --text-dim:#b8b8b8; --text-muted:#707070;
  --accent:<?php echo htmlspecialchars($theme_color); ?>;
  --radius:10px; --radius-lg:16px; --radius-xl:24px;
  --shadow:0 10px 50px rgba(0,0,0,.8);
  --transition:all .35s cubic-bezier(0.25, 1, 0.4, 1);
  --ease-spring:cubic-bezier(0.175, 0.885, 0.32, 1.275);
  --ease-out:cubic-bezier(0.19, 1, 0.22, 1);
}

@media (prefers-reduced-motion: reduce) {
  *,*::before,*::after {
    animation-duration: 0.01ms !important;
    animation-iteration-count: 1 !important;
    transition-duration: 0.01ms !important;
  }
}

/* ════ RESET ════ */
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box;-webkit-tap-highlight-color:transparent}
html{scroll-behavior:smooth;overflow-x:hidden;width:100%}
body{font-family:'Cairo',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;overflow-x:hidden;width:100%;max-width:100vw;-webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale}
a{text-decoration:none;color:inherit}
button{font-family:inherit;cursor:pointer;border:none;background:none}
img{display:block;max-width:100%;height:auto}
::-webkit-scrollbar{width:4px;height:4px}
::-webkit-scrollbar-track{background:transparent}
::-webkit-scrollbar-thumb{background:rgba(255,255,255,.15);border-radius:99px}
::-webkit-scrollbar-thumb:hover{background:var(--red)}
.hidden{display:none!important}

/* ════ KEYFRAMES ════ */
@keyframes shimmer{0%{background-position:-900px 0}100%{background-position:900px 0}}
@keyframes fadeIn{0%{opacity:0;transform:translateY(15px)}100%{opacity:1;transform:translateY(0)}}
@keyframes fadeUp{from{opacity:0;transform:translateY(24px)}to{opacity:1;transform:translateY(0)}}
@keyframes cardIn{0%{opacity:0;transform:translateY(40px) scale(0.9)}100%{opacity:1;transform:translateY(0) scale(1)}}
@keyframes glowPulse{0%,100%{box-shadow:0 0 8px rgba(229,9,20,.25)}50%{box-shadow:0 0 18px rgba(229,9,20,.55)}}
@keyframes iconBounce{0%{transform:scale(1)}30%{transform:scale(1.22) rotate(-7deg)}55%{transform:scale(1.14) rotate(4deg)}100%{transform:scale(1.18) rotate(-6deg)}}
@keyframes spin2{to{transform:rotate(360deg)}}
@keyframes playerSlideIn{from{opacity:0}to{opacity:1}}
@keyframes lockFloat{0%,100%{transform:translateY(0) scale(1)}50%{transform:translateY(-8px) scale(1.04)}}
@keyframes shakeIcon{0%,100%{transform:rotate(0)}8%{transform:rotate(-10deg)}18%{transform:rotate(10deg)}28%{transform:rotate(-7deg)}38%{transform:rotate(7deg)}}
@keyframes ripple{to{transform:scale(5);opacity:0}}
@keyframes toast-in{from{opacity:0;transform:translateX(60px) scale(.85)}to{opacity:1;transform:translateX(0) scale(1)}}
@keyframes toast-out{to{opacity:0;transform:translateX(60px) scale(.85)}}
@keyframes nxKenBurns{0%{transform:scale(1)}100%{transform:scale(1.12)}}
@keyframes nxFloat{0%{transform:rotateY(-5deg) translateY(0)}100%{transform:rotateY(-3deg) translateY(-12px)}}
@keyframes nxBounce{0%,100%{transform:translateX(0)}50%{transform:translateX(8px)}}
@keyframes musicBarAnim{0%{transform:scaleY(0.3)}100%{transform:scaleY(1)}}

/* ════ SKELETON ════ */
.skeleton{background:linear-gradient(110deg,#181818 20%,#2c2c2c 40%,#3d3d3d 50%,#2c2c2c 60%,#181818 80%);background-size:1200px 100%;animation:shimmer 1.5s ease-in infinite;border-radius:var(--radius)}

/* ════ DEVTOOLS OVERLAY ════ */
.devtools-overlay{display:none;position:fixed;inset:0;z-index:999999;background:rgba(0,0,0,.97);backdrop-filter:blur(20px);align-items:center;justify-content:center;flex-direction:column;animation:fadeIn .3s ease}
.devtools-overlay.show{display:flex}
.devtools-box{background:linear-gradient(160deg,#1a0a0a,#140000);border:1px solid rgba(229,9,20,.35);border-radius:var(--radius-xl);padding:52px 56px;text-align:center;max-width:440px;width:90%;box-shadow:0 0 80px rgba(229,9,20,.25),0 30px 80px rgba(0,0,0,.9)}
.devtools-lock-icon{font-size:4.5rem;margin-bottom:24px;display:inline-block;animation:lockFloat 3.5s ease-in-out infinite}
.devtools-lock-icon.shake{animation:shakeIcon .7s ease,lockFloat 3.5s ease-in-out .7s infinite}
.devtools-title{font-size:1.7rem;font-weight:900;color:#fff;margin-bottom:10px}
.devtools-sub{font-size:1rem;color:#707070;line-height:1.6;margin-bottom:28px}
.devtools-badge{display:inline-flex;align-items:center;gap:8px;background:rgba(229,9,20,.12);border:1px solid rgba(229,9,20,.3);padding:8px 20px;border-radius:99px;font-size:.85rem;font-weight:700;color:#ff6060}

/* ════ LICENSE BANNER ════ */
.license-banner{background:linear-gradient(135deg,#9a0000,#c00,#b71c1c);padding:14px 20px;display:flex;align-items:center;justify-content:center;gap:16px;font-weight:700;font-size:.9rem;box-shadow:0 4px 25px rgba(183,28,28,.6)}
.lic-renew{background:rgba(255,255,255,.2);color:#fff;padding:7px 18px;border-radius:99px;font-weight:800;transition:var(--transition);border:1px solid rgba(255,255,255,.3)}

/* ════ NAVBAR ════ */
.navbar{
  position:fixed;top:0;left:0;right:0;z-index:900;
  padding: max(10px, env(safe-area-inset-top)) max(15px, env(safe-area-inset-right)) 10px max(15px, env(safe-area-inset-left));
  display:flex;align-items:center;gap:12px;
  background:rgba(12,12,12,.7);backdrop-filter:blur(24px) saturate(180%);
  -webkit-backdrop-filter:blur(24px) saturate(180%);
  border-bottom:1px solid rgba(255,255,255,.05);transition:.4s var(--ease-out);
}
.navbar.scrolled{box-shadow:0 4px 20px rgba(0,0,0,.5)}
.nav-actions{display:flex;align-items:center;gap:7px;flex-shrink:0;order:1}
.nav-center{flex:1;order:2}
.nav-brand{flex-shrink:0;order:3}
.nav-logo-img{width:32px;height:32px;border-radius:5px;object-fit:cover}
.nav-logo-text{font-size:1.3rem;font-weight:900;letter-spacing:-1px;color:var(--red)}
.search-wrap{position:relative}
.search-wrap input{
  width:100%;padding:9px 38px 9px 14px;
  background:rgba(255,255,255,.06);
  border:1.5px solid rgba(255,255,255,.15);
  border-radius:99px;color:var(--text);
  font-family:inherit;font-size:.9rem;direction:rtl;
  transition:border-color .2s,background .2s;
}
.search-wrap input:focus{outline:none;background:rgba(255,255,255,.1);border-color:var(--red)}
.search-wrap .si{position:absolute;right:13px;top:50%;transform:translateY(-50%);color:var(--text-muted);pointer-events:none;font-size:.85rem}
.nav-btn{width:40px;height:40px;border-radius:50%;background:rgba(255,255,255,.08);border:1.5px solid rgba(255,255,255,.1);color:#ccc;display:flex;align-items:center;justify-content:center;font-size:.9rem;transition:var(--transition);position:relative;cursor:pointer}
.nav-btn:hover{background:var(--red);border-color:var(--red);color:#fff;transform:scale(1.08)}
#notifBadge{position:absolute;top:3px;right:3px;width:8px;height:8px;border-radius:50%;background:red;box-shadow:0 0 8px #ff3040}
/* nav responsive في كتلة الـ responsive الموحّدة أدناه */

/* ════ CATEGORY QUICK NAV (شريط اختصارات الأقسام) ════ */
.cat-navbar{
  position:fixed;left:0;right:0;z-index:880;
  top:var(--navbar-h, 68px);
  display:flex;align-items:center;gap:8px;
  padding:10px max(15px, env(safe-area-inset-right)) 10px max(15px, env(safe-area-inset-left));
  background:rgba(12,12,12,.55);backdrop-filter:blur(18px) saturate(160%);
  -webkit-backdrop-filter:blur(18px) saturate(160%);
  border-bottom:1px solid rgba(255,255,255,.05);
  overflow-x:auto;overflow-y:hidden;scrollbar-width:none;-ms-overflow-style:none;
  white-space:nowrap;
}
.cat-navbar::-webkit-scrollbar{display:none}
.cat-nav-btn{
  flex-shrink:0;display:inline-flex;align-items:center;gap:6px;
  padding:7px 16px;border-radius:99px;cursor:pointer;
  background:rgba(255,255,255,.06);border:1.5px solid rgba(255,255,255,.1);
  color:#ccc;font-size:.82rem;font-weight:700;
  transition:var(--transition);font-family:inherit;
}
.cat-nav-btn:hover{background:rgba(255,255,255,.12);border-color:rgba(255,255,255,.22);color:#fff}
.cat-nav-btn.active{background:var(--red);border-color:var(--red);color:#fff;box-shadow:0 4px 14px rgba(229,9,20,.4)}
.cat-nav-btn i,.cat-nav-btn .lcn{font-size:.8rem}
/* [SHS-ICON-FIX] ضمان وراثة اللون والحجم لأيقونات SVG داخل شريط الأقسام */
.cat-nav-btn .lcn{display:inline-flex;align-items:center;justify-content:center;line-height:0;flex-shrink:0}
.cat-nav-btn svg,.cat-nav-btn .lcn svg{
  width:1em;height:1em;flex-shrink:0;
  color:inherit;stroke:currentColor;fill:none;
  vertical-align:middle;pointer-events:none;
}
.cat-nav-btn.active svg,.cat-nav-btn.active .lcn svg{color:inherit;stroke:currentColor}

/* ════ HERO WELCOME ════ */
.hero-welcome{padding:0 20px;margin-bottom:28px}
.hero-welcome h1{font-size:clamp(1.5rem,2.5vw,2.4rem);font-weight:900;margin-bottom:8px;animation:fadeUp .6s ease both}
.hero-welcome p{color:#aaa;font-size:.95rem;animation:fadeUp .6s .1s ease both}

/* ════ SECTION ROW ════ */
.netflix-slider-row{position:relative;margin-bottom:32px}
/* تسريع الرسم: تخطّي رسم الصفوف خارج الشاشة حتى الاقتراب منها (إضافة) */
.netflix-slider-row{content-visibility:auto;contain-intrinsic-size:auto 260px}
.slider-header{display:flex;align-items:center;justify-content:space-between;padding:0 12px;margin-bottom:10px;border-right:3px solid var(--red)}
.slider-title{display:flex;align-items:center;gap:8px;font-size:1rem;font-weight:800;color:#fff;padding:0;margin:0;flex:1;min-width:0}
.slider-title-icon{width:26px;height:26px;border-radius:5px;background:rgba(229,9,20,.15);border:1px solid rgba(229,9,20,.3);display:flex;align-items:center;justify-content:center;color:#ff4d57;font-size:.75rem;flex-shrink:0}
.slider-badge{font-size:.68rem;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);padding:2px 7px;border-radius:99px;color:var(--text-muted);white-space:nowrap}
.slider-nav-btns{display:none}
.snav-btn{display:none}
.slider-scroll-mask{position:relative}
.slider-footer{display:none}

/* [SHS-CATMENU-STYLE-START] قائمة الأقسام العمودية المنسدلة (إضافة فقط) */
.shs-catmenu-btn{
  width:40px;height:40px;border-radius:50%;background:rgba(255,255,255,.08);
  border:1.5px solid rgba(255,255,255,.1);color:#ccc;display:flex;align-items:center;
  justify-content:center;font-size:.9rem;transition:var(--transition);cursor:pointer;position:relative;
}
.shs-catmenu-btn:hover{background:var(--red);border-color:var(--red);color:#fff;transform:scale(1.08)}
.shs-catmenu-overlay{
  position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:1400;opacity:0;visibility:hidden;
  transition:opacity .25s ease,visibility .25s ease;
}
.shs-catmenu-overlay.open{opacity:1;visibility:visible}
.shs-catmenu-panel{
  position:fixed;top:0;right:0;height:100%;width:min(300px,82vw);
  background:#141414;border-left:1px solid rgba(255,255,255,.08);
  box-shadow:-8px 0 40px rgba(0,0,0,.6);z-index:1401;
  transform:translateX(100%);transition:transform .3s cubic-bezier(.4,0,.2,1);
  display:flex;flex-direction:column;overflow-y:auto;
}
.shs-catmenu-panel.open{transform:translateX(0)}
.shs-catmenu-panel::-webkit-scrollbar{width:6px}
.shs-catmenu-panel::-webkit-scrollbar-thumb{background:rgba(255,255,255,.15);border-radius:3px}
.shs-catmenu-head{
  display:flex;align-items:center;justify-content:space-between;gap:16px;
  padding:18px 20px;border-bottom:1px solid rgba(255,255,255,.08);
  position:sticky;top:0;background:#141414;z-index:2;
}
.shs-catmenu-head a,.shs-catmenu-head .shs-catmenu-home{
  background:none;border:none;color:#eaeaea;font-size:.95rem;font-weight:700;
  cursor:pointer;font-family:inherit;padding:4px 2px;transition:color .2s;
}
.shs-catmenu-head a:hover,.shs-catmenu-head .shs-catmenu-home:hover{color:var(--red)}
.shs-catmenu-close{
  background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);
  color:#ccc;width:30px;height:30px;border-radius:50%;display:flex;
  align-items:center;justify-content:center;cursor:pointer;transition:.2s;flex-shrink:0;
}
.shs-catmenu-close:hover{background:var(--red);border-color:var(--red);color:#fff}
.shs-catmenu-list{display:flex;flex-direction:column;padding:6px 0}
.shs-catmenu-item{
  background:none;border:none;color:#dcdcdc;font-family:inherit;
  text-align:right;font-size:.95rem;padding:16px 22px;cursor:pointer;
  border-bottom:1px solid rgba(255,255,255,.06);transition:background .2s,color .2s;
  display:flex;align-items:center;justify-content:flex-end;gap:10px;
}
.shs-catmenu-item:hover{background:rgba(255,255,255,.05);color:#fff}
.shs-catmenu-item.active{color:var(--red);font-weight:700}
.shs-catmenu-item .shs-catmenu-arrow{color:var(--text-muted);font-size:.8rem;order:-1}
.shs-catmenu-empty{padding:24px 22px;color:var(--text-muted);font-size:.85rem;text-align:center}
@media(max-width:600px){
  .shs-catmenu-item{padding:15px 18px;font-size:.9rem}
  .shs-catmenu-head{padding:16px 18px}
}
/* [SHS-CATMENU-STYLE-END] */

/* [SHS-CATMENU-PRO-START] إخفاء الشريط الأفقي + تصميم احترافي للقائمة العمودية (إضافة فقط) */
/* إخفاء شريط الأقسام الأفقي (بدون حذف الكود) */
#catNavbar{display:none !important}

/* لوحة أعرض بخلفية متدرجة وحواف ناعمة */
.shs-catmenu-panel{
  width:min(330px,86vw);
  background:linear-gradient(180deg,#181818 0%,#101010 100%);
  border-left:1px solid rgba(255,255,255,.06);
  box-shadow:-14px 0 50px rgba(0,0,0,.7);
}
.shs-catmenu-overlay{background:rgba(0,0,0,.62);backdrop-filter:blur(3px);-webkit-backdrop-filter:blur(3px)}

/* رأس احترافي: عنوان بارز + خط سفلي بلون الهوية */
.shs-catmenu-head{
  flex-direction:row-reverse;justify-content:space-between;align-items:center;
  padding:20px 22px 16px;border-bottom:1px solid rgba(255,255,255,.06);
  background:transparent;
}
.shs-catmenu-head .shs-catmenu-home{display:none}
.shs-catmenu-headwrap{display:flex;flex-direction:column;gap:2px;align-items:flex-start}
.shs-catmenu-title{
  font-size:1.15rem;font-weight:900;color:#fff;letter-spacing:-.3px;
  display:flex;align-items:center;gap:9px;
}
.shs-catmenu-title::before{
  content:"";width:4px;height:20px;border-radius:99px;
  background:linear-gradient(180deg,var(--red),#ff5b64);display:inline-block;
}
.shs-catmenu-sub{font-size:.72rem;color:var(--text-muted);padding-right:13px}

/* زر الرئيسية كصف مميّز أعلى القائمة */
.shs-catmenu-homerow{
  display:flex;align-items:center;justify-content:flex-end;gap:10px;
  margin:12px 16px 6px;padding:13px 16px;cursor:pointer;
  background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.07);
  border-radius:12px;color:#eaeaea;font-family:inherit;font-size:.92rem;font-weight:700;
  transition:background .2s,border-color .2s,transform .15s;
}
.shs-catmenu-homerow:hover{background:rgba(229,9,20,.14);border-color:rgba(229,9,20,.4);color:#fff;transform:translateX(-3px)}
.shs-catmenu-homerow .lcn{color:var(--red);font-size:1rem}

/* عناصر الأقسام: بطاقات ناعمة بدل خطوط فاصلة صريحة */
.shs-catmenu-list{padding:6px 12px 20px;gap:2px}
.shs-catmenu-item{
  border-bottom:none;border-radius:11px;padding:14px 16px;margin:1px 0;
  position:relative;overflow:hidden;
}
.shs-catmenu-item::after{content:none}
.shs-catmenu-item:hover{background:rgba(255,255,255,.06);transform:translateX(-3px)}
.shs-catmenu-item.active{
  background:linear-gradient(90deg,rgba(229,9,20,.18),rgba(229,9,20,.04));
  color:#fff;
}
.shs-catmenu-item.active::before{
  content:"";position:absolute;right:0;top:18%;height:64%;width:3px;
  border-radius:99px;background:var(--red);
}
.shs-catmenu-item .shs-catmenu-idx{
  font-size:.7rem;color:var(--text-muted);background:rgba(255,255,255,.05);
  border:1px solid rgba(255,255,255,.08);border-radius:7px;min-width:24px;height:24px;
  display:flex;align-items:center;justify-content:center;order:-2;flex-shrink:0;transition:.2s;
}
.shs-catmenu-item:hover .shs-catmenu-idx,.shs-catmenu-item.active .shs-catmenu-idx{
  background:rgba(229,9,20,.18);border-color:rgba(229,9,20,.35);color:#ff8a90;
}
.shs-catmenu-item .shs-catmenu-name{flex:1;text-align:right}
.shs-catmenu-item .shs-catmenu-arrow{opacity:0;transform:translateX(4px);transition:.2s}
.shs-catmenu-item:hover .shs-catmenu-arrow{opacity:1;transform:translateX(0)}

/* شارة عدد الأقسام في الأسفل */
.shs-catmenu-count{
  margin-top:auto;padding:14px 22px;border-top:1px solid rgba(255,255,255,.06);
  font-size:.72rem;color:var(--text-muted);text-align:center;
}
/* [SHS-CATMENU-PRO-END] */

/* [SHS-SEARCHBAR-START] ترتيب وتصميم شريط البحث ليطابق الصورة (إضافة فقط) */
/* الترتيب: الشعار أقصى اليمين — البحث يتمدّد — الأزرار أقصى اليسار */
.navbar .nav-brand{order:3 !important}
.navbar .nav-center{order:2 !important;flex:1 !important;min-width:0}
.navbar .nav-actions{order:1 !important}

/* كبسولة البحث: خلفية داكنة زرقاء + العدسة يساراً + نص يبدأ من اليسار */
.search-wrap{position:relative;max-width:640px;margin:0 auto}
.search-wrap input{
  width:100% !important;
  padding:11px 16px 11px 44px !important;   /* مساحة للعدسة على اليسار */
  background:linear-gradient(180deg,#1e2a4a 0%,#182238 100%) !important;
  border:1.5px solid rgba(120,150,220,.22) !important;
  border-radius:999px !important;
  color:#e8ecf6 !important;
  font-size:.92rem !important;
  direction:ltr !important;                 /* النص والعدسة على اليسار كما بالصورة */
  text-align:left !important;
  box-shadow:0 2px 10px rgba(0,0,0,.28) inset,0 1px 0 rgba(255,255,255,.03);
}
.search-wrap input::placeholder{color:#9fb0d4 !important;opacity:.9}
.search-wrap input:focus{
  background:linear-gradient(180deg,#22315a 0%,#1b2743 100%) !important;
  border-color:rgba(120,150,220,.5) !important;
  box-shadow:0 0 0 3px rgba(90,120,200,.18),0 2px 10px rgba(0,0,0,.3) inset !important;
}
/* أيقونة العدسة على اليسار */
.search-wrap .si{
  right:auto !important;left:15px !important;
  color:#9fb0d4 !important;font-size:.9rem !important;
}
/* زر البحث الصوتي ينتقل إلى اليمين لتفادي تداخله مع العدسة */
#voiceSearchBtn{left:auto !important;right:14px !important;color:#9fb0d4 !important}

/* الشعار على أقصى اليمين بحجم أوضح */
.navbar .nav-logo-img{width:34px;height:34px;border-radius:8px}
/* [SHS-SEARCHBAR-END] */

/* [SHS-CATVIEW-STYLE-START] عرض احترافي داخل الأقسام + هياكل تحميل (إضافة فقط) */
/* بانر عنوان القسم */
.shs-catview-banner{
  display:flex;align-items:center;gap:14px;margin:6px 0 20px;padding:16px 18px;
  border-radius:16px;position:relative;overflow:hidden;
  background:linear-gradient(120deg,rgba(229,9,20,.14),rgba(30,42,74,.35) 60%,rgba(255,255,255,.02));
  border:1px solid rgba(255,255,255,.07);
}
.shs-catview-banner::before{
  content:"";position:absolute;inset:0;z-index:0;
  background:radial-gradient(120% 140% at 100% 0,rgba(229,9,20,.22),transparent 55%);
  pointer-events:none;
}
.shs-catview-banner>*{position:relative;z-index:1}
.shs-catview-ico{
  width:46px;height:46px;flex-shrink:0;border-radius:13px;display:flex;align-items:center;justify-content:center;
  background:linear-gradient(180deg,rgba(229,9,20,.28),rgba(229,9,20,.12));
  border:1px solid rgba(229,9,20,.35);color:#ff6169;font-size:1.15rem;
  box-shadow:0 4px 14px rgba(229,9,20,.25);
}
.shs-catview-meta{display:flex;flex-direction:column;gap:4px;min-width:0;flex:1}
.shs-catview-name{font-size:1.25rem;font-weight:900;color:#fff;letter-spacing:-.4px;line-height:1.1}
.shs-catview-chips{display:flex;flex-wrap:wrap;gap:6px}
.shs-catview-chip{
  font-size:.68rem;font-weight:700;padding:3px 10px;border-radius:99px;
  background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);color:var(--text-dim);
  display:inline-flex;align-items:center;gap:5px;
}
.shs-catview-chip.total{background:rgba(229,9,20,.16);border-color:rgba(229,9,20,.32);color:#ff8a90}
.shs-catview-chip .dot{width:6px;height:6px;border-radius:50%;background:currentColor;opacity:.8}

/* فاصل بين القنوات والمسلسلات */
.shs-catview-sep{
  grid-column:1/-1;display:flex;align-items:center;gap:12px;margin:14px 0 4px;
  color:var(--text-muted);font-size:.78rem;font-weight:700;
}
.shs-catview-sep::before,.shs-catview-sep::after{content:"";height:1px;flex:1;background:rgba(255,255,255,.08)}

/* هياكل تحميل (Skeleton) بدل السبنر */
.shs-skel-grid{display:none}
.shs-skel-card{
  border-radius:12px;overflow:hidden;background:rgba(255,255,255,.04);
  border:1px solid rgba(255,255,255,.05);aspect-ratio:2/3;position:relative;
}
.shs-skel-card::after{
  content:"";position:absolute;inset:0;
  background:linear-gradient(100deg,transparent 20%,rgba(255,255,255,.07) 45%,transparent 70%);
  transform:translateX(-100%);animation:shsShimmer 1.25s infinite;
}
@keyframes shsShimmer{100%{transform:translateX(100%)}}

/* ظهور ناعم لشبكة العناصر */
.shs-fadein{animation:shsFadeIn .35s ease both}
@keyframes shsFadeIn{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:none}}

/* إخفاء صف العنوان القديم داخل قسم العرض (البانر يحلّ محله) — بلا حذف من الـ HTML */
#categoryViewSection > div:has(> #categoryViewTitle){display:none !important}
/* [SHS-CATVIEW-STYLE-END] */

/* [SHS-POLISH-START] لمسات احترافية: شريط أفقي + عرض القسم + القائمة الجانبية (إضافة فقط) */

/* ═══ (1) شريط الأقسام الأفقي — Pills زجاجية + حركات دقيقة + تدرّج للنشط ═══ */
.cat-navbar{
  background:linear-gradient(180deg,rgba(18,18,22,.72),rgba(12,12,14,.55)) !important;
  backdrop-filter:blur(22px) saturate(180%) !important;
  -webkit-backdrop-filter:blur(22px) saturate(180%) !important;
  gap:9px !important;
}
.cat-nav-btn{
  padding:8px 18px !important;
  background:rgba(255,255,255,.05) !important;
  border:1px solid rgba(255,255,255,.09) !important;
  -webkit-backdrop-filter:blur(8px);backdrop-filter:blur(8px);
  color:#d4d4d4 !important;font-weight:700 !important;
  transition:transform .18s cubic-bezier(.34,1.56,.64,1),background .2s,border-color .2s,box-shadow .2s,color .2s !important;
  will-change:transform;
}
.cat-nav-btn:hover{
  background:rgba(255,255,255,.1) !important;border-color:rgba(255,255,255,.2) !important;
  color:#fff !important;transform:translateY(-2px) scale(1.03);
  box-shadow:0 6px 18px rgba(0,0,0,.35);
}
.cat-nav-btn:active{transform:translateY(0) scale(.97)}
.cat-nav-btn.active{
  background:linear-gradient(135deg,#ff2b36,#e50914 55%,#b00610) !important;
  border-color:rgba(255,90,98,.6) !important;color:#fff !important;
  box-shadow:0 6px 20px rgba(229,9,20,.45),0 0 0 1px rgba(255,255,255,.06) inset !important;
  transform:translateY(-1px);
}

/* ═══ (2) ترويسة القسم — أفخم وأوضح ═══ */
.shs-catview-banner{
  gap:16px;padding:20px 22px;border-radius:20px;margin:8px 0 22px;
  animation:shsBannerIn .5s cubic-bezier(.22,1,.36,1) both;
}
@keyframes shsBannerIn{from{opacity:0;transform:translateY(-8px) scale(.98)}to{opacity:1;transform:none}}
.shs-catview-ico{width:54px;height:54px;border-radius:16px;font-size:1.35rem}
.shs-catview-name{font-size:1.5rem;letter-spacing:-.6px}
.shs-catview-chip{font-size:.72rem;padding:4px 12px}

/* زر العودة أنعم داخل عرض القسم */
#categoryViewSection .back-btn{
  transition:transform .18s,background .2s,color .2s;border-radius:99px;
}
#categoryViewSection .back-btn:hover{transform:translateX(3px)}

/* دخول ناعم Slide-up لكامل قسم العرض */
#categoryViewSection:not(.hidden){animation:shsSectionIn .45s cubic-bezier(.22,1,.36,1) both}
@keyframes shsSectionIn{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:none}}

/* ظهور تدريجي متدرّج لبطاقات الشبكة */
#categoryViewGrid.shs-stagger > .ch-card,
#categoryViewGrid.shs-stagger > .sr-card{animation:shsCardIn .4s ease both}
@keyframes shsCardIn{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:none}}

/* ═══ (2ب) حالة التحميل — سبنر مزدوج أنيق ═══ */
.shs-spinner{
  width:54px;height:54px;margin:0 auto;position:relative;
}
.shs-spinner::before,.shs-spinner::after{
  content:"";position:absolute;inset:0;border-radius:50%;
  border:3px solid transparent;
}
.shs-spinner::before{border-top-color:#e50914;border-right-color:#e50914;animation:spin2 .8s linear infinite}
.shs-spinner::after{inset:8px;border-bottom-color:rgba(255,90,98,.6);border-left-color:rgba(255,90,98,.6);animation:spin2 1.1s linear infinite reverse}
.shs-loading-txt{margin-top:16px;color:var(--text-muted);font-size:.82rem;font-weight:600}

/* ═══ (2ج) حالة الفراغ — أيقونية جميلة ═══ */
.shs-empty-wrap{display:flex;flex-direction:column;align-items:center;gap:14px;padding:56px 20px}
.shs-empty-ico{
  width:84px;height:84px;border-radius:24px;display:flex;align-items:center;justify-content:center;
  background:radial-gradient(120% 120% at 30% 20%,rgba(229,9,20,.16),rgba(255,255,255,.03));
  border:1px solid rgba(255,255,255,.08);color:#ff6169;font-size:2rem;
  box-shadow:0 10px 30px rgba(0,0,0,.35);animation:shsFloat 3s ease-in-out infinite;
}
@keyframes shsFloat{0%,100%{transform:translateY(0)}50%{transform:translateY(-7px)}}
.shs-empty-title{font-size:1.05rem;font-weight:800;color:#e8e8e8}
.shs-empty-sub{font-size:.82rem;color:var(--text-muted);max-width:280px;text-align:center;line-height:1.6}

/* ═══ (3) القائمة الجانبية — زجاج داكن + مسافات + انزلاق + سكرول أنيق ═══ */
.shs-catmenu-panel{
  background:linear-gradient(180deg,rgba(20,22,30,.92),rgba(10,11,16,.94)) !important;
  -webkit-backdrop-filter:blur(26px) saturate(160%);backdrop-filter:blur(26px) saturate(160%);
  border-left:1px solid rgba(255,255,255,.08) !important;
}
.shs-catmenu-list{padding:10px 12px 20px;gap:5px}
.shs-catmenu-item{
  padding:15px 16px;margin:2px 0;
  transition:background .22s,color .22s,transform .2s cubic-bezier(.34,1.56,.64,1) !important;
}
.shs-catmenu-item:hover{transform:translateX(-6px)}
.shs-catmenu-homerow{transition:background .2s,border-color .2s,transform .2s cubic-bezier(.34,1.56,.64,1)}
.shs-catmenu-homerow:hover{transform:translateX(-6px)}

/* شريط تمرير أنيق يظهر عند الحاجة فقط */
.shs-catmenu-panel{scrollbar-width:thin;scrollbar-color:transparent transparent}
.shs-catmenu-panel:hover{scrollbar-color:rgba(255,255,255,.2) transparent}
.shs-catmenu-panel::-webkit-scrollbar{width:8px}
.shs-catmenu-panel::-webkit-scrollbar-track{background:transparent}
.shs-catmenu-panel::-webkit-scrollbar-thumb{
  background:transparent;border-radius:99px;border:2px solid transparent;background-clip:padding-box;
  transition:background .3s;
}
.shs-catmenu-panel:hover::-webkit-scrollbar-thumb{background:rgba(255,255,255,.18);background-clip:padding-box}
.shs-catmenu-panel::-webkit-scrollbar-thumb:hover{background:rgba(255,255,255,.32);background-clip:padding-box}

/* شارة أيقونة القسم في القائمة الجانبية (بديل شارة الرقم) */
.shs-catmenu-ico{
  order:-2;flex-shrink:0;width:38px;height:38px;border-radius:11px;
  display:flex;align-items:center;justify-content:center;
  background:linear-gradient(180deg,rgba(255,255,255,.07),rgba(255,255,255,.02));
  border:1px solid rgba(255,255,255,.08);color:#cfd4de;font-size:1.05rem;
  transition:background .22s,border-color .22s,color .22s,transform .22s;
}
.shs-catmenu-ico svg{width:20px;height:20px}
.shs-catmenu-item:hover .shs-catmenu-ico{
  background:linear-gradient(180deg,rgba(229,9,20,.22),rgba(229,9,20,.08));
  border-color:rgba(229,9,20,.4);color:#ff8a90;transform:scale(1.06);
}
.shs-catmenu-item.active .shs-catmenu-ico{
  background:linear-gradient(135deg,#ff2b36,#e50914);border-color:rgba(255,90,98,.55);
  color:#fff;box-shadow:0 4px 12px rgba(229,9,20,.35);
}
/* أيقونة بانر القسم أوضح */
.shs-catview-ico svg{width:26px;height:26px}
/* [SHS-POLISH-END] */

/* ════ GRID CONTAINER ════ */
.slider-cards-wrapper {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(clamp(100px, 14vw, 175px), 1fr));
  gap: clamp(8px, 1.5vw, 15px);
  padding: 0 12px;
  overflow: visible;
  /* FIX: direction:ltr للـ grid حتى تمتلئ الكروت من اليسار لليمين */
  direction: ltr;
}
.slider-cards-wrapper .skeleton{height:0;padding-bottom:150%;border-radius:8px}
@media(max-width:1200px){.slider-cards-wrapper{grid-template-columns:repeat(5,1fr)}}
@media(max-width:900px){.slider-cards-wrapper{grid-template-columns:repeat(4,1fr)}}
@media(max-width:768px){.slider-cards-wrapper,.channels-row{grid-template-columns:repeat(3,1fr)!important}}

/* ════ CARDS ════ */
.ch-card,.sr-card{
  position:relative;overflow:hidden;
  background:linear-gradient(145deg,rgba(30,30,30,1) 0%,rgba(18,18,18,1) 100%);
  border:1px solid rgba(255,255,255,.05);
  border-radius:clamp(8px,1.2vw,14px);
  cursor:pointer;width:100%;
  box-shadow:0 4px 15px rgba(0,0,0,.4);
  transition:transform .35s var(--ease-spring),border-color .3s,box-shadow .3s;
  /* FIX: لا will-change دائم — يُفعّل فقط عند hover عبر JS */
}
.ch-card:hover,.sr-card:hover{transform:translateY(-8px) scale(1.05);border-color:var(--red);box-shadow:0 15px 40px rgba(0,0,0,.85),0 0 25px rgba(229,9,20,.4);z-index:10}
.ch-thumb{position:relative;width:100%;aspect-ratio:16/9;background:#111;overflow:hidden;display:flex;align-items:center;justify-content:center}
.sr-poster{position:relative;width:100%;aspect-ratio:2/3;background:#111;overflow:hidden;display:flex;align-items:center;justify-content:center}
.ch-thumb img{position:absolute;inset:0;width:100%;height:100%;object-fit:contain;padding:10%;transition:transform .25s var(--ease-spring)}
.sr-poster img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;padding:0;transition:transform .25s var(--ease-spring)}
.ch-card:hover .ch-thumb img,.sr-card:hover .sr-poster img{transform:scale(1.06)}
.ch-thumb::after,.sr-poster::after{content:'';position:absolute;inset:0;background:transparent;transition:background .2s;z-index:2;pointer-events:none}
.ch-card:hover .ch-thumb::after,.sr-card:hover .sr-poster::after{background:rgba(0,0,0,.3)}
.ch-thumb .ch-icon,.sr-poster .sr-icon{font-size:1.8rem;color:#2e2e2e;position:relative;z-index:1;transition:color .2s,transform .22s}
.ch-card:hover .ch-thumb .ch-icon,.sr-card:hover .sr-poster .sr-icon{color:#ff4d57;transform:scale(1.12)}
.ch-play-btn{position:absolute;z-index:4;top:50%;left:50%;transform:translate(-50%,-50%);width:36px;height:36px;border-radius:50%;background:rgba(229,9,20,.9);color:#fff;font-size:.85rem;display:flex;align-items:center;justify-content:center;opacity:0;transition:opacity .2s;pointer-events:none}
.ch-card:hover .ch-play-btn{opacity:1}
.ch-live-badge{position:absolute;top:5px;right:5px;z-index:5;background:#e50914;color:#fff;font-size:.55rem;font-weight:800;padding:2px 6px;border-radius:3px;animation:glowPulse 3s ease-in-out infinite}
.ch-fmt-badge{position:absolute;top:5px;left:5px;z-index:5;background:rgba(0,0,0,.7);color:#999;font-size:.52rem;font-weight:800;padding:1px 4px;border-radius:3px;text-transform:uppercase;border:1px solid rgba(255,255,255,.1)}
.ch-quality-badge{position:absolute;bottom:5px;right:5px;z-index:5;background:rgba(0,0,0,.72);color:#fff;font-size:.52rem;font-weight:800;padding:1px 5px;border-radius:3px;border:1px solid rgba(255,255,255,.15)}
.ch-info,.sr-info{padding:6px 7px 8px;background:#161616;direction:rtl}
.ch-name,.sr-name{font-size:clamp(0.68rem,2.2vw,0.85rem);font-weight:700;color:#eeeeee;line-height:1.4;height:2.8em;margin-bottom:6px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;text-overflow:ellipsis;white-space:normal;transition:color .2s var(--ease-spring)}
.ch-card:hover .ch-name,.sr-card:hover .sr-name{color:#fff}
.ch-meta,.sr-meta{font-size:.62rem;color:var(--text-muted);display:flex;align-items:center;gap:3px}
@media(max-width:600px){.ch-info,.sr-info{padding:5px 6px 6px}}

/* ════ INFO ACTION BUTTONS ════ */
.info-action-btn{background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.12);color:#bbb;width:25px;height:25px;border-radius:5px;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:all .18s;flex-shrink:0;font-size:.68rem}
.info-action-btn:hover{background:var(--red);color:#fff;border-color:var(--red);transform:scale(1.08)}
.info-action-btn.active-fav{color:var(--red)}

/* ════ EPISODES GRID ════ */
.channels-row{display:grid;grid-template-columns:repeat(auto-fill,minmax(clamp(100px,14vw,175px),1fr));gap:clamp(8px,1.5vw,14px);direction:ltr}
.episodes-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(clamp(145px,20vw,240px),1fr));gap:clamp(10px,2vw,18px);direction:ltr}/* episodes responsive في كتلة الـ responsive الموحّدة */
.ep-card{display:flex;flex-direction:column;background:#181818;border:1px solid rgba(255,255,255,.08);border-radius:var(--radius);overflow:hidden;cursor:pointer;transition:var(--transition)}
.ep-card:hover{transform:translateY(-8px) scale(1.04);border-color:var(--red);box-shadow:0 15px 35px rgba(0,0,0,.7),0 0 20px rgba(229,9,20,.35);z-index:5}
.ep-thumb-area{position:relative;width:100%;aspect-ratio:16/9;background:linear-gradient(135deg,#252525,#1a1a1a);display:flex;align-items:center;justify-content:center;overflow:hidden}
.ep-thumb-video{width:100%;height:100%;object-fit:cover;position:absolute;inset:0;z-index:0;pointer-events:none}
/* [SHS-EPPOSTER] عند استخدام بوستر المسلسل كخلفية: تعتيم خفيف ليبرز زر التشغيل */
.ep-thumb-fallback{filter:brightness(.62) saturate(1.05)}
.ep-thumb-area:has(.ep-thumb-fallback)::before{content:"";position:absolute;inset:0;z-index:1;background:linear-gradient(180deg,rgba(0,0,0,.15),rgba(0,0,0,.55));pointer-events:none}
.ep-card:hover .ep-thumb-fallback{filter:brightness(.78) saturate(1.1)}
.ep-thumb-icon{font-size:2.4rem;color:rgba(255,255,255,.6);transition:.3s;z-index:2;position:relative}
.ep-card:hover .ep-thumb-icon{color:var(--red);transform:scale(1.15)}
.ep-num-badge{position:absolute;top:8px;right:8px;background:var(--red);color:#fff;padding:2px 9px;border-radius:4px;font-size:.72rem;font-weight:bold;z-index:3}
.ep-info-box{padding:10px;text-align:center;border-top:1px solid rgba(255,255,255,.05);background:#151515}
.ep-date-text{font-size:.82rem;color:var(--text-muted);display:flex;align-items:center;justify-content:center;gap:6px;font-weight:600}

/* ════ SEARCH GRID ════ */
.channels-row{display:grid;grid-template-columns:repeat(auto-fill,minmax(clamp(100px,14vw,175px),1fr));gap:clamp(8px,1.5vw,14px)}
@media(max-width:1024px){.channels-row{grid-template-columns:repeat(5,1fr)}}

/* ════ BACK BUTTON ════ */
.back-btn{display:inline-flex;align-items:center;gap:10px;padding:9px 20px;background:rgba(255,255,255,.07);border:1.5px solid rgba(255,255,255,.14);border-radius:99px;color:var(--text);margin-bottom:24px;cursor:pointer;font-weight:700;font-size:.9rem;transition:var(--transition)}
.back-btn:hover{background:rgba(229,9,20,.12);border-color:rgba(229,9,20,.5);color:#ff4d57}

/* ════ PANELS ════ */
.fp-panel,.np-panel,.m3u-panel,.ep-panel{position:fixed;top:0;height:100%;z-index:9996;width:min(100vw,420px);background:var(--surface);backdrop-filter:blur(30px) saturate(1.5);display:flex;flex-direction:column;transition:all .45s var(--ease-spring);box-shadow:0 0 50px rgba(0,0,0,.85)}
.fp-panel{right:-420px;border-left:1px solid rgba(255,255,255,.1)}.fp-panel.open{right:0}
.np-panel{right:-420px;border-left:1px solid rgba(255,255,255,.1)}.np-panel.open{right:0}
.m3u-panel{right:-420px;border-left:1px solid rgba(255,255,255,.1)}.m3u-panel.open{right:0}
.ep-panel{left:-420px;border-right:1px solid rgba(255,255,255,.1)}.ep-panel.open{left:0}
.panel-overlay{position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9995;display:none;backdrop-filter:blur(2px)}.panel-overlay.show{display:block}
/* panels responsive في كتلة الـ responsive الموحّدة */
.ep-panel-head{padding:22px 20px;background:linear-gradient(180deg,rgba(229,9,20,.08),transparent);border-bottom:1px solid rgba(255,255,255,.08);display:flex;align-items:center;justify-content:space-between;flex-shrink:0}
.ep-panel-close{width:32px;height:32px;border-radius:50%;background:rgba(255,255,255,.07);color:#fff;border:1px solid rgba(255,255,255,.1);cursor:pointer;transition:background .2s;display:flex;align-items:center;justify-content:center}
.ep-panel-close:hover{background:rgba(229,9,20,.3)}
.m3u-item{display:flex;align-items:center;gap:12px;padding:10px 14px;border-radius:10px;cursor:pointer;margin-bottom:5px;border:1px solid rgba(255,255,255,.05);background:rgba(255,255,255,.03);transition:all .2s}
.m3u-item:hover{background:rgba(229,9,20,.1);border-color:rgba(229,9,20,.35)}
.m3u-item.playing{background:rgba(229,9,20,.15);border-color:rgba(229,9,20,.5)}
.m3u-item-logo{width:36px;height:36px;border-radius:8px;object-fit:contain;background:rgba(255,255,255,.07);flex-shrink:0}
.m3u-item-name{font-size:.86rem;font-weight:700;color:#f0f0f0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.m3u-item-group{font-size:.7rem;color:#666}
.ep-item{display:flex;align-items:center;gap:12px;padding:12px 14px;border-radius:10px;cursor:pointer;margin-bottom:6px;border:1.5px solid rgba(255,255,255,.05);background:rgba(255,255,255,.03);transition:all .22s}
.ep-item:hover{background:rgba(229,9,20,.1);border-color:rgba(229,9,20,.35)}
.ep-item.playing{background:rgba(229,9,20,.15);border-color:rgba(229,9,20,.5)}
.ep-item-num{width:36px;height:36px;border-radius:50%;background:rgba(229,9,20,.12);border:1.5px solid rgba(229,9,20,.3);display:flex;align-items:center;justify-content:center;font-size:.76rem;font-weight:900;color:#ff4d57;flex-shrink:0}
.ep-item.playing .ep-item-num{background:var(--red);color:#fff}
.ep-item-info{flex:1;min-width:0}
.ep-item-title{font-size:.87rem;font-weight:700;color:#f0f0f0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:2px}
.ep-item-play{width:28px;height:28px;border-radius:50%;background:rgba(229,9,20,.15);border:1px solid rgba(229,9,20,.3);color:#ff4d57;display:flex;align-items:center;justify-content:center;font-size:.7rem;flex-shrink:0;opacity:0;transition:.2s}
.ep-item:hover .ep-item-play,.ep-item.playing .ep-item-play{opacity:1}

/* ════ TOAST ════ */
.toasts{position:fixed;bottom:24px;left:24px;z-index:99999;display:flex;flex-direction:column;gap:10px;direction:rtl}
.toast{background:rgba(24,24,24,.97);color:var(--text);border:1px solid rgba(255,255,255,.1);border-right:3px solid var(--red);padding:12px 18px;border-radius:var(--radius);font-size:.86rem;font-weight:600;box-shadow:var(--shadow);display:flex;align-items:center;gap:10px;animation:toast-in .35s var(--ease-spring)}
.toast.out{animation:toast-out .28s forwards}

/* ════ TMDB MODAL ════ */
.tmdb-modal-overlay{position:fixed;inset:0;z-index:99999;background:rgba(0,0,0,.85);backdrop-filter:blur(8px);display:none;align-items:center;justify-content:center;padding:20px}
.tmdb-modal-overlay.open{display:flex}
.tmdb-modal-box{background:var(--bg2);border:1px solid var(--border);border-radius:var(--radius-lg);width:100%;max-width:600px;max-height:90vh;display:flex;flex-direction:column;box-shadow:var(--shadow);animation:cardIn .3s var(--ease-out)}
.tmdb-modal-head{padding:18px 22px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between}
.tmdb-modal-close{width:32px;height:32px;border-radius:50%;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);color:#ccc;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:.2s}
.tmdb-modal-close:hover{background:var(--red);color:#fff;border-color:var(--red)}
.tmdb-modal-body{padding:22px;overflow-y:auto}
.tmdb-info-wrap{display:flex;gap:18px;flex-wrap:wrap;direction:rtl;text-align:right}
.tmdb-info-poster{width:140px;border-radius:var(--radius);flex-shrink:0;border:1px solid var(--border);object-fit:cover;background:var(--bg3)}
.tmdb-info-details{flex:1;min-width:200px}
.tmdb-info-title{font-size:1.3rem;font-weight:800;color:#fff;margin-bottom:8px;line-height:1.2}
.tmdb-info-meta{font-size:.85rem;color:var(--text-muted);margin-bottom:14px;display:flex;gap:12px;flex-wrap:wrap;align-items:center}
.tmdb-genre-badge{background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);padding:3px 10px;border-radius:99px;font-size:.75rem;font-weight:600;color:#ccc}
.tmdb-info-overview{font-size:.9rem;color:#ddd;line-height:1.7;background:rgba(0,0,0,.3);padding:14px;border-radius:var(--radius);border:1px solid rgba(255,255,255,.05)}

/* ══════════════════════════════════════════
   FIX CRITICAL: عزل شامل للمشغل
   منع أي filter أو transform أو opacity
   من الصفحة الخارجية أن تؤثر على الفيديو
══════════════════════════════════════════ */
#playerOverlay{
  /* عزل المشغل تماماً عن أي stacking context خارجي */
  isolation:isolate;
  /* لا transform على الـ overlay نفسه */
  transform:none !important;
}
/* FIX: الأزرار الشفافة لا تخلق compositing layer */
#playerOverlay .p-back:hover{background:rgba(229,9,20,.85);transform:scale(1.06)}
#playerOverlay .p-btn:hover{background:rgba(229,9,20,.85);transform:scale(1.06)}
#playerOverlay .p-seek-btn:hover{background:rgba(229,9,20,.8);transform:scale(1.08)}
#playerOverlay .p-play-btn:hover{background:rgba(229,9,20,1);transform:scale(1.1)}
#playerOverlay .p-icon-btn:hover{background:rgba(229,9,20,.85);transform:scale(1.06)}
/* FIX: p-vol-track مُعرَّف مرة واحدة فقط */


.cat-card:focus,.ch-card:focus,.sr-card:focus,.ep-card:focus{outline:none!important;transform:translateY(-8px) scale(1.05)!important;border-color:rgba(229,9,20,.8)!important;box-shadow:0 22px 55px rgba(229,9,20,.5),0 0 0 4px #fff!important;z-index:10}

/* ════ PLAYER ════ */
.player-overlay{
  position:fixed;inset:0;z-index:9990;background:#000;
  display:none;flex-direction:column;
  width:100vw;height:100vh;height:100dvh;overflow:hidden;
  /* FIX: لا contain:strict — يمنع requestFullscreen() في Chrome/Firefox */
}
#playerOverlay:focus,#pvWrap:focus,video#html5Player:focus{outline:none}
.player-overlay.p-native-fs{position:fixed!important;inset:0!important;width:100%!important;height:100%!important;z-index:2147483647!important;margin:0!important;padding:0!important;display:flex!important}
#playerOverlay:fullscreen,#playerOverlay:-webkit-full-screen,#playerOverlay:-moz-full-screen{position:fixed!important;inset:0!important;width:100vw!important;height:100vh!important;z-index:2147483647!important;display:flex!important;flex-direction:column!important;background:#000!important}
#playerOverlay:fullscreen .pv-wrap,#playerOverlay:-webkit-full-screen .pv-wrap{position:absolute!important;inset:0!important;width:100%!important;height:100%!important}
#playerOverlay:fullscreen video,#playerOverlay:-webkit-full-screen video{width:100%!important;height:100%!important;object-fit:contain!important}
/* FIX: لا animation بـ scale على المشغل — يُفسد الجودة */
.player-overlay.active{display:flex;animation:playerSlideIn .25s ease}
.player-overlay.idle *{cursor:none!important}

/* ══ VIDEO LAYER — نقي بدون أي تأثير ══ */
.pv-wrap{
  position:absolute;inset:0;
  display:flex;align-items:center;justify-content:center;
  background:#000;
  /* FIX: isolation يمنع أي stacking context خارجي من التأثير على الفيديو */
  isolation:isolate;
}
video#html5Player{
  width:100%;
  height:100%;
  /* الجودة الأصلية الكاملة — contain يحافظ على النسبة */
  object-fit:contain;
  /* لا أي تأثير CSS يلمس الفيديو */
  transform:none !important;
  filter:none;
  opacity:1 !important;
  will-change:auto;
  /* أعلى جودة rendering ممكنة */
  image-rendering:auto; /* تم تعديله لـ auto لتجنب تهنيج شاشات TCL */
}
/* تحسينات الجودة فقط عند الطلب الصريح */
video#html5Player.enh-deblock{filter:url(#enh-deblock) !important}
video#html5Player.enh-hdr{filter:url(#enh-hdr) !important}
video#html5Player.enh-frame{filter:url(#enh-frame) !important}
video#html5Player.enh-full{filter:url(#enh-full) !important}

.p-buffer{position:absolute;inset:0;display:none;align-items:center;justify-content:center;pointer-events:none;z-index:15}
.p-buffer.show{display:flex}
.p-buffer-ring{width:56px;height:56px;border:4px solid rgba(255,255,255,.12);border-top-color:#e50914;border-radius:50%;animation:spin2 .8s linear infinite}
.p-flash{position:absolute;inset:0;pointer-events:none;display:flex;align-items:center;justify-content:center;z-index:20}
.p-flash-icon{width:74px;height:74px;border-radius:50%;background:rgba(0,0,0,.55);display:flex;align-items:center;justify-content:center;font-size:1.9rem;color:#fff;opacity:0;transition:opacity .28s;pointer-events:none}
.p-flash-icon.show{opacity:1}
/* FIX: vignette خفيف جداً — يظهر فقط عند ظهور الكنترولز */
.p-vignette-top{position:absolute;top:0;left:0;right:0;height:30%;background:linear-gradient(180deg,rgba(0,0,0,.55) 0%,rgba(0,0,0,.2) 60%,transparent 100%);pointer-events:none;z-index:10;opacity:0;transition:opacity .3s}
.p-vignette-bot{position:absolute;bottom:0;left:0;right:0;height:35%;background:linear-gradient(0deg,rgba(0,0,0,.65) 0%,rgba(0,0,0,.25) 60%,transparent 100%);pointer-events:none;z-index:10;opacity:0;transition:opacity .3s}
/* يظهر الـ vignette فقط عند ظهور الكنترولز */
.player-overlay:not(.idle) .p-vignette-top,
.player-overlay:not(.idle) .p-vignette-bot{opacity:1}

/* ── TOP ── */
.p-top{position:absolute;top:0;left:0;right:0;z-index:30;padding:max(24px,env(safe-area-inset-top)) 24px 0;display:flex;align-items:center;gap:18px;transition:opacity .3s cubic-bezier(0.25,1,0.4,1);direction:rtl}
.p-top.hide{opacity:0;pointer-events:none}
/* FIX: حذف backdrop-filter من أزرار المشغل — كانت تخلق stacking context يُدمر جودة الفيديو */
.p-back{width:48px;height:48px;border-radius:50%;background:rgba(0,0,0,.5);border:1px solid rgba(255,255,255,.2);color:#fff;font-size:1.15rem;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:background .2s,transform .2s;box-shadow:0 4px 15px rgba(0,0,0,.5)}
.p-back:hover{background:rgba(229,9,20,.8);border-color:var(--red);transform:scale(1.08)}
.p-title-block{flex:1;min-width:0;position:relative}
.p-channel-name{font-size:1.2rem;font-weight:900;color:#fff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;text-shadow:0 2px 10px rgba(0,0,0,1)}
.p-title-sub{display:flex;align-items:center;gap:8px;margin-top:5px;flex-wrap:wrap}
.p-live-badge{background:var(--red);color:#fff;padding:3px 10px;border-radius:4px;font-size:.65rem;font-weight:900;letter-spacing:1px;box-shadow:0 0 10px rgba(229,9,20,.6);animation:glowPulse 2s infinite alternate;white-space:nowrap}
.p-fmt-tag{font-size:.65rem;font-weight:800;color:#eee;background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.25);padding:2px 8px;border-radius:4px;text-transform:uppercase;white-space:nowrap}
.p-top-right{display:flex;align-items:center;gap:10px;flex-shrink:0}
.p-ep-nav{display:flex;align-items:center;gap:12px}
.p-ep-label{font-size:.9rem;font-weight:800;color:#fff;white-space:nowrap;text-shadow:0 2px 8px rgba(0,0,0,.8);max-width:180px;overflow:hidden;text-overflow:ellipsis}
.p-icon-btn{width:44px;height:44px;border-radius:50%;background:rgba(0,0,0,.5);border:1px solid rgba(255,255,255,.2);color:#fff;font-size:1.1rem;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:background .2s,transform .2s}
.p-icon-btn:hover{background:rgba(229,9,20,.8);transform:scale(1.08)}
.p-icon-btn:disabled{opacity:0.4;cursor:not-allowed;transform:none;background:rgba(255,255,255,.05)}

/* ── CENTER ── */
.p-center{position:absolute;inset:0;z-index:25;display:flex;align-items:center;justify-content:center;gap:45px;pointer-events:none;transition:opacity .3s cubic-bezier(0.25,1,0.4,1)}
.p-center.hide{opacity:0;pointer-events:none}
.p-seek-btn{width:76px;height:76px;max-width:80px;max-height:80px;border-radius:50%;background:rgba(0,0,0,.55);border:2px solid rgba(255,255,255,.15);color:#fff;font-size:1.8rem;cursor:pointer;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:2px;pointer-events:auto;transition:background .2s,transform .2s;box-shadow:0 10px 30px rgba(0,0,0,.5);box-sizing:border-box;flex:0 0 auto}
.p-seek-btn:hover,.p-seek-btn:active{background:rgba(229,9,20,.8);border-color:var(--red);transform:scale(1.12)}
.p-seek-n{font-size:.65rem;font-weight:900;color:rgba(255,255,255,.9);line-height:1}
.p-play-btn{width:96px;height:96px;max-width:104px;max-height:104px;border-radius:50%;background:rgba(229,9,20,.85);border:3px solid rgba(255,255,255,.2);color:#fff;font-size:2.8rem;cursor:pointer;display:flex;align-items:center;justify-content:center;pointer-events:auto;transition:background .25s,transform .25s;box-shadow:0 15px 40px rgba(229,9,20,.4);box-sizing:border-box;flex:0 0 auto}
.p-play-btn:hover,.p-play-btn:active{background:rgba(229,9,20,1);border-color:#fff;transform:scale(1.15) translateY(-5px);box-shadow:0 20px 50px rgba(229,9,20,.6)}

/* ── BOTTOM ── */
.p-bottom{position:absolute;bottom:0;left:0;right:0;z-index:30;padding:40px 24px max(24px,env(safe-area-inset-bottom));transition:opacity .3s cubic-bezier(0.25,1,0.4,1)}
.p-bottom.hide{opacity:0;pointer-events:none}
.p-prog-row{display:flex;align-items:center;gap:16px;margin-bottom:18px;direction:ltr}
.p-tc{font-size:.85rem;font-weight:800;color:rgba(255,255,255,.9);font-family:monospace;white-space:nowrap;min-width:44px;text-align:center;text-shadow:0 1px 5px rgba(0,0,0,.8)}
.p-prog-wrap{flex:1;padding:12px 0;cursor:pointer}
.p-prog-track{position:relative;height:6px;background:rgba(255,255,255,.25);border-radius:10px;transition:height .2s}
.p-prog-wrap:hover .p-prog-track{height:10px}
.p-prog-fill{position:absolute;left:0;top:0;height:100%;background:var(--red);border-radius:10px;width:0;transition:width .2s linear}
.p-prog-dot{position:absolute;right:-9px;top:50%;transform:translateY(-50%);width:18px;height:18px;background:#fff;border-radius:50%;box-shadow:0 0 8px rgba(0,0,0,.6);opacity:0;transition:opacity .2s}
.p-prog-wrap:hover .p-prog-dot{opacity:1}
/* شريط الأدوات — RTL للعربية، اليمين = ترجمة+تحسين، اليسار = صوت+fullscreen */
.p-tools{display:flex;align-items:center;justify-content:space-between;gap:12px;direction:rtl}
.p-tools-l,.p-tools-r{display:flex;align-items:center;gap:10px}
.p-btn{width:48px;height:48px;border-radius:12px;background:rgba(0,0,0,.5);border:1px solid rgba(255,255,255,.15);color:#fff;font-size:1.2rem;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:background .2s,transform .2s;flex-shrink:0}
.p-btn:hover{background:rgba(229,9,20,.8);border-color:var(--red);transform:scale(1.1)}
.p-btn.active-magic{color:#ff4d57;background:rgba(229,9,20,.2);border-color:var(--red);box-shadow:0 0 15px rgba(229,9,20,.5)}
.p-enh{flex-direction:column;gap:3px;font-size:1.05rem;width:58px;height:48px}
.p-enh-lbl{font-size:.5rem;font-weight:900;color:rgba(255,255,255,.8);line-height:1}
.p-vol-wrap{display:flex;align-items:center;gap:0;position:relative;direction:ltr}
.p-vol-wrap:hover .p-vol-slider-wrap{width:100px;opacity:1;margin-right:8px}
.p-vol-icon{background:none!important;border:none!important;box-shadow:none!important}
.p-vol-slider-wrap{width:0;opacity:0;overflow:hidden;transition:all .3s cubic-bezier(0.25,1,0.4,1);display:flex;align-items:center}
.p-vol-track{position:relative;width:100%;height:6px;background:rgba(255,255,255,.2);border-radius:10px;cursor:pointer;transition:height .2s}
.p-vol-track:hover{height:8px}
.p-vol-fill{height:100%;background:#fff;border-radius:10px;width:100%;pointer-events:none}
.p-vol-thumb{position:absolute;right:0;top:50%;transform:translate(50%,-50%);width:16px;height:16px;background:#fff;border-radius:50%;box-shadow:0 2px 5px rgba(0,0,0,.6);pointer-events:none;opacity:0;transition:opacity .2s}
.p-vol-track:hover .p-vol-thumb{opacity:1}
.p-time-txt{font-size:.9rem;font-weight:800;color:rgba(255,255,255,.9);font-family:monospace;white-space:nowrap;padding:0 8px}
@media(max-width:1024px){.p-vol-slider-wrap{width:80px!important;opacity:1!important;margin-right:6px!important}.p-back{width:44px;height:44px}.p-icon-btn{width:40px;height:40px}.p-btn{width:44px;height:44px}}
/* player responsive في كتلة الـ responsive الموحّدة */

/* ════ SCREENSAVER ════ */
#nxScreensaver{position:fixed;top:0;left:0;width:100vw;height:100vh;z-index:99998;background:#000;opacity:0;pointer-events:none;visibility:hidden;transition:opacity 1s,visibility 1s;overflow:hidden;font-family:'Cairo',sans-serif}
#nxScreensaver.nx-active{opacity:1;pointer-events:auto;visibility:visible}
.nx-bg{position:absolute;inset:-15%;background-size:cover;background-position:center 20%;filter:blur(55px) saturate(1.5) brightness(.25);z-index:1;animation:nxKenBurns 20s infinite alternate linear}
.nx-vignette{position:absolute;inset:0;z-index:2;background:radial-gradient(circle at center,transparent 30%,rgba(0,0,0,.85) 100%),linear-gradient(0deg,#000 0%,rgba(0,0,0,0) 40%)}
.nx-container{position:absolute;inset:0;z-index:3;display:flex;align-items:center;justify-content:flex-start;padding:0 8vw;gap:5vw;direction:rtl;opacity:1;transition:opacity .8s}
.nx-container.nx-faded{opacity:0}
.nx-poster{width:clamp(240px,22vw,360px);aspect-ratio:2/3;border-radius:12px;object-fit:cover;box-shadow:0 30px 80px rgba(0,0,0,.9),0 0 0 1px rgba(255,255,255,.08);animation:nxFloat 6s ease-in-out infinite alternate}
.nx-info-box{display:flex;flex-direction:column;max-width:700px;align-items:flex-start;text-align:right}
.nx-top-badge{display:inline-flex;align-items:center;gap:8px;margin-bottom:18px;background:#E50914;color:#fff;font-size:.88rem;font-weight:800;padding:4px 14px;border-radius:4px;letter-spacing:.5px}
.nx-title{font-size:clamp(2.5rem,5vw,4.5rem);font-weight:900;color:#fff;line-height:1.1;margin-bottom:14px;text-shadow:2px 4px 15px rgba(0,0,0,.8);display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.nx-meta-row{display:flex;gap:12px;align-items:center;color:#a3a3a3;font-size:1rem;font-weight:700;margin-bottom:22px;flex-wrap:wrap}
.nx-match{color:#46d369;font-weight:900}
.nx-tag{border:1px solid rgba(255,255,255,.3);padding:1px 8px;border-radius:4px;font-size:.88rem;font-family:monospace;color:#ddd}
.nx-footer{position:absolute;bottom:50px;left:0;right:0;text-align:center;z-index:3;font-size:.9rem;color:rgba(255,255,255,.4);display:flex;justify-content:center;align-items:center;gap:10px}
.nx-bounce-arrow{animation:nxBounce 2s infinite ease-in-out;display:inline-block}
/* screensaver responsive في كتلة الـ responsive الموحّدة */

/* ════ MUSIC PLAYER ════ */
.music-mini-btn{width:40px;height:40px;border-radius:50%;background:rgba(255,255,255,.08);border:1.5px solid rgba(255,255,255,.1);color:#ccc;display:flex;align-items:center;justify-content:center;font-size:.9rem;transition:var(--transition);position:relative;cursor:pointer}
.music-mini-btn:hover{background:rgba(156,39,176,.2);border-color:rgba(156,39,176,.5);color:#e040fb;transform:scale(1.08)}
.music-mini-btn.playing{background:rgba(156,39,176,.2);border-color:rgba(156,39,176,.5);color:#e040fb}
.music-mini-btn .music-eq{display:flex;align-items:flex-end;gap:2px;height:14px}
.music-mini-btn .music-eq-bar{width:3px;background:currentColor;border-radius:2px;animation:musicBarAnim 0.5s ease infinite alternate}
.music-mini-btn .music-eq-bar:nth-child(1){height:40%;animation-delay:0s}
.music-mini-btn .music-eq-bar:nth-child(2){height:70%;animation-delay:0.15s}
.music-mini-btn .music-eq-bar:nth-child(3){height:50%;animation-delay:0.3s}
.music-mini-btn .music-eq.paused .music-eq-bar{animation:none;height:30%!important}
/* music responsive في كتلة الـ responsive الموحّدة */

/* ════ INIT LOADER ════ */
#nxInitLoader{position:fixed;inset:0;z-index:9999999;background:#000;display:flex;align-items:center;justify-content:center;transition:opacity 0.4s var(--ease-out),visibility 0.4s}
#nxInitLoader.loaded{opacity:0;visibility:hidden;pointer-events:none}
.nx-loader-circle{width:70px;height:70px;border:4px solid rgba(229,9,20,.12);border-top-color:var(--red);border-radius:50%;animation:spin2 0.8s linear infinite}

/* ════ DEVICE CAPABILITY BADGES ════ */
#deviceBadgesWrap{
  position:absolute;top:90px;right:20px;z-index:40;
  display:flex;flex-direction:column;gap:7px;
  pointer-events:none;direction:rtl;
}
.dev-badge{
  display:inline-flex;align-items:center;gap:7px;
  background:rgba(0,0,0,.78);
  border:1px solid rgba(255,255,255,.15);
  border-radius:6px;
  padding:5px 12px;
  font-size:.72rem;font-weight:800;
  color:#fff;
  opacity:0;
  transform:translateX(20px);
  transition:opacity .4s ease, transform .4s ease;
  white-space:nowrap;
}
.dev-badge.visible{opacity:1;transform:translateX(0)}
.dev-badge .db-icon{font-size:.85rem;flex-shrink:0}
/* ألوان حسب النوع */
.dev-badge.audio-dolby{border-color:rgba(0,163,255,.5);color:#7dd4fc}
.dev-badge.audio-dts{border-color:rgba(255,136,0,.5);color:#fdba74}
.dev-badge.audio-std{border-color:rgba(255,255,255,.2);color:#d1d5db}
.dev-badge.video-hdr{border-color:rgba(255,204,0,.5);color:#fde047}
.dev-badge.video-4k{border-color:rgba(168,85,247,.5);color:#d8b4fe}
.dev-badge.video-std{border-color:rgba(255,255,255,.2);color:#d1d5db}
.dev-badge.display-hz{border-color:rgba(52,211,153,.5);color:#6ee7b7}

video#html5Player::cue{
  background:rgba(0,0,0,.78);
  color:#fff;
  font-family:'Cairo',sans-serif;
  font-size:1.05em;
  font-weight:600;
  line-height:1.5;
  text-shadow:0 1px 3px rgba(0,0,0,.9);
  border-radius:4px;
  padding:2px 6px;
}
video#html5Player::cue(b){font-weight:900}
video#html5Player::cue(i){font-style:italic;color:#ffe066}
#subBtn.sub-active{color:#ff4d57;background:rgba(229,9,20,.2);border-color:var(--red)}

/* ════ RESPONSIVE — كتلة موحّدة ════ */
@media(max-width:768px){
  /* grid */
  .slider-cards-wrapper,.channels-row{grid-template-columns:repeat(3,1fr)!important}
  .channels-row{gap:8px}
  .episodes-grid{grid-template-columns:repeat(2,1fr);gap:10px}
  /* panels */
  .fp-panel,.np-panel,.m3u-panel{width:100%;right:-100%}
  .ep-panel{width:100%;left:-100%}
  /* player */
  .p-seek-btn{width:64px;height:64px;font-size:1.5rem}
  .p-play-btn{width:82px;height:82px;font-size:2.4rem}
  .p-center{gap:30px}
  .p-btn{width:44px;height:44px;font-size:1.1rem}
  .p-top{padding:max(16px,env(safe-area-inset-top)) 16px 0;gap:12px;flex-wrap:wrap}
  .p-back{order:1}.p-top-right{order:2;margin-right:auto}
  .p-title-block{order:3;width:100%;flex:none;margin-top:2px;padding-right:6px}
  .p-channel-name{font-size:1.1rem;white-space:normal;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical}
  .p-title-sub{position:relative;top:auto;right:auto;margin-top:6px;width:100%}
  .p-bottom{padding:24px 16px max(16px,env(safe-area-inset-bottom))}
  /* screensaver */
  .nx-container{flex-direction:column-reverse;justify-content:center;padding:0 5vw;gap:18px}
  .nx-info-box{align-items:center!important;text-align:center!important}
  .nx-poster{width:clamp(130px,40vw,220px)!important}
  .nx-title{font-size:clamp(1.7rem,7vw,2.3rem)!important}
  .nx-footer{bottom:max(28px,env(safe-area-inset-bottom))!important}
}
@media(max-width:480px){
  /* nav */
  .nav-logo-text{display:none}
  .nav-btn{width:36px;height:36px;font-size:.82rem}
  .music-mini-btn{width:36px;height:36px;font-size:.82rem}
  /* category quick nav */
  .cat-navbar{padding:8px 12px;gap:6px}
  .cat-nav-btn{padding:6px 13px;font-size:.76rem}
  /* player */
  .p-seek-btn{width:56px;height:56px;font-size:1.3rem}
  .p-play-btn{width:72px;height:72px;font-size:2rem}
  .p-center{gap:20px}
  .p-btn{width:40px;height:40px;font-size:1rem;border-radius:10px}
  .p-enh{width:48px;height:40px}
  .p-time-txt{display:none}
  .p-icon-btn,.p-back{width:36px;height:36px;font-size:.9rem}
  .p-top-right{gap:6px}
  .p-ep-nav{gap:6px}
}

/* ════ TV FIX — إصلاح تضخم أزرار المشغل على التلفاز فقط ════
   متصفحات التلفاز (شاشة عريضة جداً + كثافة بكسل منخفضة) تُظهر أزرار
   التحكم عملاقة. نثبّت أحجاماً معقولة لها دون المساس بالموبايل/الأندرويد. */
@media screen and (min-width:1280px){
  /* أزرار التقديم/التأخير والإيقاف في وسط الفيديو */
  #playerOverlay .p-center{gap:60px}
  #playerOverlay .p-seek-btn{width:72px!important;height:72px!important;font-size:1.7rem!important}
  #playerOverlay .p-play-btn{width:92px!important;height:92px!important;font-size:2.6rem!important}
  #playerOverlay .p-seek-n{font-size:.62rem!important}
  /* منع أي تكبير عند hover/active يجعلها تقفز على التلفاز */
  #playerOverlay .p-seek-btn:hover,#playerOverlay .p-seek-btn:active{transform:none!important}
  #playerOverlay .p-play-btn:hover,#playerOverlay .p-play-btn:active{transform:none!important}
}
/* أجهزة التلفاز عبر pointer الخشن + شاشة كبيرة (تأكيد إضافي) */
@media screen and (min-width:1280px) and (pointer:coarse){
  #playerOverlay .p-seek-btn{width:68px!important;height:68px!important}
  #playerOverlay .p-play-btn{width:88px!important;height:88px!important}
}
/* شاشات 4K/التلفاز فائق العرض — نحافظ على نفس الحجم النسبي المعقول */
@media screen and (min-width:1920px){
  #playerOverlay .p-seek-btn{width:80px!important;height:80px!important;font-size:1.9rem!important}
  #playerOverlay .p-play-btn{width:104px!important;height:104px!important;font-size:3rem!important}
  #playerOverlay .p-center{gap:72px}
}

/* ════ LUCIDE COMPAT ════ */
.lcn{display:inline-flex;align-items:center;justify-content:center;vertical-align:middle;line-height:1}
.lcn svg{width:1em;height:1em;stroke:currentColor;fill:none}

/* ════ PERF BOOST — تحسينات أداء إضافية ════ */
/* تمرير أنعم على كامل الصفحة */
html{scroll-behavior:smooth}
@media (prefers-reduced-motion: reduce){html{scroll-behavior:auto}}
/* عزل رسم كل صف لتقليل إعادة التخطيط عند التمرير */
.netflix-slider-row{contain:layout style}
/* الصور: انتقال ظهور لطيف + تسريع فك الترميز */
img{content-visibility:auto}
img.perf-img{opacity:0;transition:opacity .35s ease}
img.perf-img.perf-loaded{opacity:1}
/* تسريع اللمس وإزالة تأخير 300ms على الجوال */
a,button,.netflix-card,.nx-card,[onclick]{touch-action:manipulation}
/* تلميح للمتصفح بأن العناصر القابلة للتمرير ستتحرك */
.netflix-slider,.nx-row,.ep-list{will-change:scroll-position}

/* ══════════════════════════════════════════════════════════════════
   ✨ GLASS THEME — تحسين تأثير الزجاج (Blur أعمق + إضاءة داخلية)
   إضافة فقط — لا يحذف أي شيء. يعزّز وضوح ونعومة الأسطح الزجاجية.
   ══════════════════════════════════════════════════════════════════ */
@supports ((backdrop-filter:blur(1px)) or (-webkit-backdrop-filter:blur(1px))){

  /* شريط التنقّل العلوي — زجاج أعمق وأنقى */
  .navbar{
    background:linear-gradient(180deg,rgba(14,14,16,.62),rgba(10,10,12,.5)) !important;
    -webkit-backdrop-filter:blur(30px) saturate(200%) contrast(105%) !important;
    backdrop-filter:blur(30px) saturate(200%) contrast(105%) !important;
    box-shadow:inset 0 1px 0 rgba(255,255,255,.06) !important;
  }
  .navbar.scrolled{
    background:linear-gradient(180deg,rgba(12,12,14,.78),rgba(9,9,11,.66)) !important;
    box-shadow:0 8px 30px rgba(0,0,0,.55),inset 0 1px 0 rgba(255,255,255,.07) !important;
  }

  /* شريط الأقسام الأفقي — زجاج غنيّ */
  .cat-navbar{
    -webkit-backdrop-filter:blur(28px) saturate(190%) contrast(104%) !important;
    backdrop-filter:blur(28px) saturate(190%) contrast(104%) !important;
    box-shadow:inset 0 1px 0 rgba(255,255,255,.05) !important;
  }
  .cat-nav-btn{
    -webkit-backdrop-filter:blur(14px) saturate(140%) !important;
    backdrop-filter:blur(14px) saturate(140%) !important;
  }

  /* اللوحات الجانبية (فلاتر/إشعارات/M3U/حلقات) — زجاج فاخر */
  .fp-panel,.np-panel,.m3u-panel,.ep-panel{
    background:linear-gradient(180deg,rgba(26,26,30,.82),rgba(16,16,20,.86)) !important;
    -webkit-backdrop-filter:blur(38px) saturate(180%) contrast(103%) !important;
    backdrop-filter:blur(38px) saturate(180%) contrast(103%) !important;
    border-left:1px solid rgba(255,255,255,.09);
    box-shadow:0 0 60px rgba(0,0,0,.85),inset 1px 0 0 rgba(255,255,255,.05) !important;
  }
  .panel-overlay{
    -webkit-backdrop-filter:blur(6px) saturate(120%);
    backdrop-filter:blur(6px) saturate(120%);
  }

  /* القائمة الجانبية للأقسام — زجاج معتّم أنيق */
  .shs-catmenu-panel{
    -webkit-backdrop-filter:blur(34px) saturate(175%) contrast(103%) !important;
    backdrop-filter:blur(34px) saturate(175%) contrast(103%) !important;
    box-shadow:inset 1px 0 0 rgba(255,255,255,.05),0 0 50px rgba(0,0,0,.6) !important;
  }
  .shs-catmenu-overlay{
    -webkit-backdrop-filter:blur(6px) saturate(120%) !important;
    backdrop-filter:blur(6px) saturate(120%) !important;
  }

  /* نافذة TMDB المنبثقة — زجاج ناعم */
  .tmdb-modal-overlay{
    -webkit-backdrop-filter:blur(14px) saturate(150%);
    backdrop-filter:blur(14px) saturate(150%);
  }
}

/* على الأجهزة الضعيفة: نُخفّف حِدّة الـblur للحفاظ على الأداء */
@media (max-width:640px){
  @supports ((backdrop-filter:blur(1px)) or (-webkit-backdrop-filter:blur(1px))){
    .navbar,.navbar.scrolled{
      -webkit-backdrop-filter:blur(22px) saturate(180%) !important;
      backdrop-filter:blur(22px) saturate(180%) !important;
    }
    .cat-navbar{
      -webkit-backdrop-filter:blur(20px) saturate(170%) !important;
      backdrop-filter:blur(20px) saturate(170%) !important;
    }
    .fp-panel,.np-panel,.m3u-panel,.ep-panel,.shs-catmenu-panel{
      -webkit-backdrop-filter:blur(26px) saturate(165%) !important;
      backdrop-filter:blur(26px) saturate(165%) !important;
    }
  }
}
