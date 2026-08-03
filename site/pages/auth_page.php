<?php
/**
 * صفحة بوابة المشتركين — دخول / إنشاء حساب / تفعيل كوبون / انتهاء الاشتراك.
 * تُعرض من site/gates/subscriber_gate.php وحدها ثم exit، فلا تُحمَّل أي أصول
 * من الموقع الأصلي: لا سكربتات المشغّل ولا الفهارس. صفحة واحدة مكتفية بنفسها.
 *
 * المتغيرات القادمة من البوابة:
 *   $__sg_view  login|register|activate|expired|disabled
 *   $__sg_err $__sg_ok $__sg_csrf $__sg_site $__sg_logo $__sg_wa
 *   $__sg_regOn $__sg_lang $__sg_dir $__sg_user
 */
$T = static function (string $key, string $ar) use ($t): string {
    return htmlspecialchars((string)($t[$key] ?? $ar), ENT_QUOTES, 'UTF-8');
};
$plans = subsPlans(true);
$cur   = subsCurrency();
?><!DOCTYPE html>
<html lang="<?= htmlspecialchars($__sg_lang, ENT_QUOTES, 'UTF-8') ?>" dir="<?= $__sg_dir ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="robots" content="noindex,nofollow">
<title><?= $__sg_site ?> — <?= $T('sg_title', 'الدخول') ?></title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
:root{--red:#e50914;--bg:#0b0b0d;--bg2:#151518;--br:#26262b;--t1:#fff;--t2:#c9c9d1;--t3:#8a8a94;--ok:#00D084;--warn:#F5A623}
html,body{height:100%}
body{
  background:var(--bg);color:var(--t1);
  font-family:'Tajawal','Segoe UI',system-ui,-apple-system,Tahoma,Arial,sans-serif;
  display:flex;align-items:center;justify-content:center;padding:20px;
  min-height:100vh;min-height:100dvh;position:relative;overflow-x:hidden
}
/* توهّج خلفي خفيف — تدرّجان ثابتان، بلا رسوم متحركة تستهلك المعالج */
body::before{
  content:"";position:fixed;inset:0;z-index:0;pointer-events:none;
  background:
    radial-gradient(ellipse 70% 50% at 15% 0%,rgba(229,9,20,.13),transparent 60%),
    radial-gradient(ellipse 60% 50% at 85% 100%,rgba(76,201,240,.08),transparent 60%)
}

/* ══ فيديو الخلفية ══ */
#sgBgVid{
  position:fixed;inset:0;width:100%;height:100%;object-fit:cover;
  z-index:-2;pointer-events:none;opacity:0;transition:opacity 1.2s ease
}
#sgBgVid.on{opacity:1}
/* طبقة تعتيم — تثبّت المزاج ولا تخفي الفيديو.
   ⚠ كانت القيم .78→.88 خطياً فوق .35→.85 شعاعياً، وهما تتراكمان
   ضربياً لا جمعياً: 88% تعتيم في الوسط و98% في الزوايا. أي أن الفيديو
   كان يصل العين بنحو 9 من 255 — أسود عملياً، ونطاق يُدفع بلا مقابل.
   البطاقة نفسها معتمة 96% ولها ظلّ وblur، فالنص فيها مقروء بلا هذا
   الثقل؛ وظيفة الحجاب تهدئة الخلفية لا محوها. */
#sgBgVeil{
  position:fixed;inset:0;z-index:-1;pointer-events:none;
  background:
    linear-gradient(180deg,rgba(11,11,13,.30),rgba(11,11,13,.46)),
    radial-gradient(ellipse 78% 70% at 50% 48%,rgba(11,11,13,0),rgba(11,11,13,.52))
}
/* من يفضّل تقليل الحركة لا يُعرض له فيديو متحرك إطلاقاً */
@media (prefers-reduced-motion: reduce){ #sgBgVid{display:none} }
.wrap{position:relative;z-index:1;width:100%;max-width:430px}
.card{
  background:linear-gradient(180deg,rgba(30,30,34,.96),rgba(18,18,21,.98));
  border:1px solid var(--br);border-radius:20px;padding:36px 30px;
  box-shadow:0 24px 70px rgba(0,0,0,.6);backdrop-filter:blur(8px)
}
.logo{width:76px;height:76px;margin:0 auto 16px;border-radius:18px;
  background:linear-gradient(135deg,var(--red),#8c060e);display:flex;
  align-items:center;justify-content:center;font-size:2.1rem;
  box-shadow:0 8px 26px rgba(229,9,20,.4);overflow:hidden}
/* مع شعار حقيقي نُلغي التدرّج الأحمر: خلفية محايدة تُظهر الشعار بألوانه
   الصحيحة، والأحمر خلف شعار ملوّن يشوّهه. */
.logo.has-img{background:#0e0e11;border:1px solid var(--br);
  box-shadow:0 8px 26px rgba(0,0,0,.45);padding:6px}
.logo img{width:100%;height:100%;object-fit:contain;display:block}
h1{font-size:1.35rem;text-align:center;font-weight:800;margin-bottom:5px;letter-spacing:-.3px}
.sub{text-align:center;color:var(--t3);font-size:.85rem;margin-bottom:24px;line-height:1.6}
label{display:block;font-size:.79rem;color:var(--t2);margin-bottom:6px;font-weight:600}
.f{margin-bottom:15px}
input[type=text],input[type=password],input[type=email]{
  width:100%;padding:13px 15px;border-radius:11px;border:1px solid var(--br);
  background:#0e0e11;color:var(--t1);font-size:.95rem;font-family:inherit;
  transition:border-color .18s,box-shadow .18s;outline:none
}
input:focus{border-color:var(--red);box-shadow:0 0 0 3px rgba(229,9,20,.13)}
input::placeholder{color:#5a5a63}
.btn{
  width:100%;padding:14px;border:none;border-radius:11px;font-size:.97rem;
  font-weight:800;cursor:pointer;font-family:inherit;transition:.18s;
  background:linear-gradient(135deg,var(--red),#b4070f);color:#fff;
  box-shadow:0 6px 20px rgba(229,9,20,.32)
}
.btn:hover{transform:translateY(-1px);box-shadow:0 9px 26px rgba(229,9,20,.44)}
.btn:active{transform:translateY(0)}
.btn:disabled{opacity:.6;cursor:not-allowed;transform:none}
.btn-o{background:transparent;border:1px solid var(--br);color:var(--t2);box-shadow:none}
.btn-o:hover{border-color:var(--red);color:var(--red);box-shadow:none}
.msg{padding:12px 14px;border-radius:11px;font-size:.83rem;margin-bottom:16px;
  display:flex;align-items:flex-start;gap:9px;line-height:1.6}
.msg-e{background:rgba(229,9,20,.11);border:1px solid rgba(229,9,20,.3);color:#ff7a80}
.msg-s{background:rgba(0,208,132,.11);border:1px solid rgba(0,208,132,.3);color:var(--ok)}
.msg-w{background:rgba(245,166,35,.11);border:1px solid rgba(245,166,35,.3);color:var(--warn)}
.alt{text-align:center;margin-top:18px;font-size:.84rem;color:var(--t3)}
.alt a{color:var(--red);text-decoration:none;font-weight:700}
.alt a:hover{text-decoration:underline}
.cpin{
  text-align:center;font-family:ui-monospace,'Courier New',monospace;
  font-size:1.25rem;letter-spacing:3px;font-weight:800;text-transform:uppercase
}
.plans{display:grid;gap:9px;margin:18px 0 6px}
.plan{display:flex;align-items:center;justify-content:space-between;gap:10px;
  padding:12px 15px;border:1px solid var(--br);border-radius:11px;background:#0e0e11}
.plan b{font-size:.88rem}
.plan small{color:var(--t3);font-size:.72rem;display:block;margin-top:2px}
.plan .pr{color:var(--red);font-weight:900;font-size:1.05rem;white-space:nowrap}
.ic{font-size:3rem;text-align:center;margin-bottom:12px;line-height:1}
.uinfo{display:flex;align-items:center;justify-content:space-between;gap:10px;
  background:#0e0e11;border:1px solid var(--br);border-radius:11px;
  padding:11px 15px;margin-bottom:18px;font-size:.83rem}
.uinfo span{color:var(--t3)}
.uinfo b{color:var(--t1)}
.uinfo a{color:var(--t3);text-decoration:none;font-size:.78rem}
.uinfo a:hover{color:var(--red)}
.wa{display:flex;align-items:center;justify-content:center;gap:8px;
  margin-top:16px;padding:12px;border-radius:11px;text-decoration:none;
  background:rgba(37,211,102,.1);border:1px solid rgba(37,211,102,.25);
  color:#25D366;font-weight:700;font-size:.85rem;transition:.18s}
.wa:hover{background:rgba(37,211,102,.18)}
/* ⚠ كان اللون ‎#4a4a52‎ وهو يعتمد على أن الخلفية سوداء تماماً — تباينه
   عليها 2.3:1 أصلاً، ولمّا ظهر الفيديو خلفه هبط إلى 1.2:1 فاختفى.
   هذا السطر وحده خارج البطاقة، فيجب أن يقرأ فوق أي لقطة تمرّ تحته:
   لون أفتح (5.1:1) وظلّ يثبّت الحافة مهما كان المشهد. */
.foot{
  text-align:center;margin-top:22px;font-size:.72rem;color:#b6b6c2;
  text-shadow:0 1px 3px rgba(0,0,0,.8)
}
@media(max-width:480px){.card{padding:28px 20px;border-radius:16px}h1{font-size:1.2rem}}
</style>
</head>
<body>
<?php /* ⚠ حُذف من هنا عنصر <video src="sha.mp4"> ثابت.
        الملف غير موجود في المشروع إطلاقاً، ولم يكن يُذكر في أي مكان آخر،
        فكان كل زائر يدفع طلباً يرتدّ 404 مقابل لا شيء. وكان يغطّي الشاشة
        كاملةً بلا preload="none"، أي أنه يلتهم النطاق قبل أن يظهر نموذج
        الدخول — وهو نقيض ما بُني منطق الخلفية أدناه لأجله. */ ?>
<?php if (!empty($__sg_bg['sources'])): ?>
  <?php /* preload="none" ومحمّل بجافاسكربت: الفيديو زينة، ولا يجوز أن
           يؤخّر ظهور نموذج الدخول أو يستهلك باقة زائر على الجوال قبل
           أن يقرّر المتصفح إن كان سيشغّله أصلاً. */ ?>
  <video id="sgBgVid" autoplay muted loop playsinline preload="none"
         <?= $__sg_bg['poster'] !== '' ? 'poster="' . htmlspecialchars($__sg_bg['poster'], ENT_QUOTES, 'UTF-8') . '"' : '' ?>
         data-srcs="<?= htmlspecialchars(json_encode($__sg_bg['sources'], JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8') ?>"
         aria-hidden="true" tabindex="-1"></video>
  <div id="sgBgVeil"></div>
<?php endif; ?>

<div class="wrap">
  <div class="card">

    <div class="logo<?= $__sg_logo !== '' ? ' has-img' : '' ?>">
      <?php if ($__sg_logo !== ''): ?>
        <?php /* onerror: لو تعذّر تحميل الصورة نُظهر الرمز النصّي بدل مربّع مكسور */ ?>
        <img src="<?= htmlspecialchars($__sg_logo, ENT_QUOTES, 'UTF-8') ?>"
             alt="<?= $__sg_site ?>"
             onerror="this.remove();this.parentNode.classList.remove('has-img');this.parentNode.textContent='▶';">
      <?php else: ?>▶<?php endif; ?>
    </div>

    <?php if ($__sg_err !== ''): ?>
      <div class="msg msg-e"><span>⚠</span><span><?= htmlspecialchars($__sg_err, ENT_QUOTES, 'UTF-8') ?></span></div>
    <?php endif; ?>
    <?php if ($__sg_ok !== ''): ?>
      <div class="msg msg-s"><span>✔</span><span><?= htmlspecialchars($__sg_ok, ENT_QUOTES, 'UTF-8') ?></span></div>
    <?php endif; ?>


    <?php /* ══════════════════ تسجيل الدخول ══════════════════ */ ?>
    <?php if ($__sg_view === 'login'): ?>
      <h1><?= $__sg_site ?></h1>
      <p class="sub"><?= $T('sg_login_sub', 'سجّل الدخول للوصول إلى المحتوى') ?></p>

      <form method="POST" autocomplete="on">
        <input type="hidden" name="sg_csrf" value="<?= htmlspecialchars($__sg_csrf, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="sg_action" value="login">
        <div class="f">
          <label for="u"><?= $T('sg_username', 'اسم المستخدم') ?></label>
          <input id="u" type="text" name="username" required autofocus autocomplete="username"
                 maxlength="50" value="<?= htmlspecialchars((string)($_POST['username'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="f">
          <label for="p"><?= $T('sg_password', 'كلمة المرور') ?></label>
          <input id="p" type="password" name="password" required autocomplete="current-password" placeholder="••••••••">
        </div>
        <button class="btn" type="submit"><?= $T('sg_login_btn', 'دخول') ?></button>
      </form>

      <?php if ($__sg_regOn): ?>
        <p class="alt"><?= $T('sg_no_account', 'ليس لديك حساب؟') ?>
          <a href="?sg=register"><?= $T('sg_create', 'أنشئ حساباً') ?></a></p>
      <?php endif; ?>


    <?php /* ══════════════════ إنشاء حساب ══════════════════ */ ?>
    <?php elseif ($__sg_view === 'register'): ?>
      <h1><?= $T('sg_create_title', 'إنشاء حساب جديد') ?></h1>
      <p class="sub"><?= $T('sg_create_sub', 'بعد إنشاء الحساب ستحتاج كوبوناً لتفعيل الاشتراك') ?></p>

      <form method="POST" autocomplete="on">
        <input type="hidden" name="sg_csrf" value="<?= htmlspecialchars($__sg_csrf, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="sg_action" value="register">
        <div class="f">
          <label for="ru"><?= $T('sg_username', 'اسم المستخدم') ?></label>
          <input id="ru" type="text" name="username" required autofocus autocomplete="username" maxlength="50"
                 pattern="[A-Za-z0-9_.\-]{3,50}"
                 title="<?= $T('sg_username_rule', '٣–٥٠ حرفاً إنجليزياً أو رقماً أو _ . -') ?>"
                 value="<?= htmlspecialchars((string)($_POST['username'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="f">
          <label for="re"><?= $T('sg_email_opt', 'البريد الإلكتروني (اختياري)') ?></label>
          <input id="re" type="email" name="email" autocomplete="email" maxlength="100"
                 value="<?= htmlspecialchars((string)($_POST['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="f">
          <label for="rp"><?= $T('sg_password', 'كلمة المرور') ?></label>
          <input id="rp" type="password" name="password" required minlength="8"
                 autocomplete="new-password" placeholder="<?= $T('sg_min8', '٨ أحرف على الأقل') ?>">
        </div>
        <div class="f">
          <label for="rp2"><?= $T('sg_password2', 'تأكيد كلمة المرور') ?></label>
          <input id="rp2" type="password" name="password2" required minlength="8"
                 autocomplete="new-password" placeholder="••••••••">
        </div>
        <button class="btn" type="submit"><?= $T('sg_create_btn', 'إنشاء الحساب') ?></button>
      </form>

      <p class="alt"><?= $T('sg_have_account', 'لديك حساب؟') ?>
        <a href="?sg=login"><?= $T('sg_login_btn', 'دخول') ?></a></p>


    <?php /* ══════════════════ تفعيل / انتهاء / إيقاف ══════════════════ */ ?>
    <?php else: ?>
      <?php
        $isExpired  = ($__sg_view === 'expired');
        $isDisabled = ($__sg_view === 'disabled');
      ?>
      <div class="ic"><?= $isExpired ? '⏳' : ($isDisabled ? '🚫' : '🎟️') ?></div>

      <h1><?php
        if ($isExpired)       echo $T('sg_expired_title',  'انتهت صلاحية اشتراكك');
        elseif ($isDisabled)  echo $T('sg_disabled_title', 'حسابك موقوف');
        else                  echo $T('sg_activate_title', 'تفعيل الاشتراك');
      ?></h1>

      <p class="sub"><?php
        if ($isExpired)       echo $T('sg_expired_sub',  'انتهت صلاحية اشتراكك، يرجى شراء كوبون جديد لتجديد الاشتراك.');
        elseif ($isDisabled)  echo $T('sg_disabled_sub', 'أوقفت الإدارة هذا الحساب. تواصل معنا للمزيد.');
        else                  echo $T('sg_activate_sub', 'أدخل كود الكوبون لتفعيل حسابك والبدء بالمشاهدة.');
      ?></p>

      <div class="uinfo">
        <div>
          <span><?= $T('sg_account', 'الحساب') ?>:</span>
          <b><?= htmlspecialchars((string)($__sg_user['username'] ?? ''), ENT_QUOTES, 'UTF-8') ?></b>
          <?php if ($isExpired && !empty($__sg_user['sub_end'])): ?>
            <div style="color:#ff7a80;font-size:.74rem;margin-top:3px">
              <?= $T('sg_ended_on', 'انتهى في') ?>:
              <?= htmlspecialchars(date('Y-m-d', strtotime((string)$__sg_user['sub_end'])), ENT_QUOTES, 'UTF-8') ?>
            </div>
          <?php endif; ?>
        </div>
        <a href="?__su_logout=1"><?= $T('sg_logout', 'خروج') ?></a>
      </div>

      <?php if (!$isDisabled): ?>
        <form method="POST" autocomplete="off">
          <input type="hidden" name="sg_csrf" value="<?= htmlspecialchars($__sg_csrf, ENT_QUOTES, 'UTF-8') ?>">
          <input type="hidden" name="sg_action" value="redeem">
          <div class="f">
            <label for="cp"><?= $T('sg_coupon', 'كود الكوبون') ?></label>
            <input id="cp" class="cpin" type="text" name="coupon" required autofocus
                   maxlength="32" placeholder="XXXX-XXXX-XXXX" autocapitalize="characters" spellcheck="false">
          </div>
          <button class="btn" type="submit"><?= $T('sg_redeem_btn', 'تفعيل الاشتراك') ?></button>
        </form>
      <?php endif; ?>

      <?php if ($plans && !$isDisabled): ?>
        <div style="margin-top:24px;border-top:1px solid var(--br);padding-top:18px">
          <p style="font-size:.8rem;color:var(--t3);text-align:center;margin-bottom:4px">
            <?= $T('sg_plans_title', 'باقات الاشتراك المتاحة') ?>
          </p>
          <div class="plans">
            <?php foreach ($plans as $p): ?>
              <div class="plan">
                <div>
                  <b><?= htmlspecialchars(subsPlanName($p, $__sg_lang), ENT_QUOTES, 'UTF-8') ?></b>
                  <small><?= (int)$p['duration_days'] ?> <?= $T('sg_days', 'يوم') ?></small>
                </div>
                <div class="pr"><?= htmlspecialchars($cur, ENT_QUOTES, 'UTF-8') ?><?= number_format((float)$p['price'], 2) ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

      <?php if ($__sg_wa !== ''): ?>
        <?php $__wa = preg_replace('/[^0-9]/', '', $__sg_wa); ?>
        <?php if ($__wa !== ''): ?>
          <a class="wa" href="https://wa.me/<?= $__wa ?>" target="_blank" rel="noopener noreferrer">
            <span>💬</span><span><?= $T('sg_buy_coupon', 'شراء كوبون عبر واتساب') ?></span>
          </a>
        <?php endif; ?>
      <?php endif; ?>

    <?php endif; ?>

  </div>
  <p class="foot">© <?= date('Y') ?> <?= $__sg_site ?></p>
</div>

<script>
/* ── تشغيل فيديو الخلفية ──
   لا نحمّله إلا بعد اكتمال الصفحة وبشروط: نموذج الدخول أهمّ من الزينة.

   نتخطّاه كلياً عند: تفضيل تقليل الحركة، أو وضع توفير البيانات، أو
   اتصال بطيء (2g/3g). زائر على باقة محدودة لا يجوز أن يدفع ميغابايتات
   ثمن خلفية متحركة لن ينظر إليها وهو يكتب كلمة مروره. */
(function () {
  var v = document.getElementById('sgBgVid');
  if (!v) return;

  var mq = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)');
  if (mq && mq.matches) { v.remove(); return; }

  var c = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
  if (c && (c.saveData || /^(slow-)?2g$/.test(c.effectiveType || ''))) { v.remove(); return; }

  function start() {
    /* نضيف كل الصيغ المتاحة ويختار المتصفح أوّل ما يفهمه.
       تقديم webm وحده كان يحرم Safari الأقدم (لا يفكّ VP9) من الخلفية،
       والبديل mp4 موجود على القرص بلا فائدة. */
    var srcs = [];
    try { srcs = JSON.parse(v.getAttribute('data-srcs') || '[]'); } catch (e) { return; }
    if (!srcs.length) return;
    for (var i = 0; i < srcs.length; i++) {
      var s = document.createElement('source');
      s.src  = srcs[i][0];
      s.type = srcs[i][1] || 'video/mp4';
      v.appendChild(s);
    }
    v.load();
    var p = v.play();
    if (p && p.catch) p.catch(function () { /* منع التشغيل التلقائي — تبقى صورة الملصق */ });
    v.addEventListener('playing', function () { v.classList.add('on'); }, { once: true });
    // لو تعذّر التحميل نزيله بدل ترك عنصر أسود فوق التدرّج
    v.addEventListener('error', function () { v.remove(); }, { once: true });
  }

  if (document.readyState === 'complete') start();
  else window.addEventListener('load', start, { once: true });

  // إيقافه حين تكون الصفحة مخفية — لا معنى لفكّ ترميز فيديو في تبويب مطويّ
  document.addEventListener('visibilitychange', function () {
    if (document.hidden) { try { v.pause(); } catch (e) {} }
    else { try { v.play().catch(function () {}); } catch (e) {} }
  });
})();

// تنسيق حقل الكوبون أثناء الكتابة: أحرف كبيرة وشرطة كل 4 خانات.
// نُعيد بناء القيمة من الأحرف الصالحة فقط بدل الاعتماد على موضع المؤشر،
// وهو ما ينكسر عند اللصق أو الحذف من المنتصف.
(function(){
  var el = document.getElementById('cp');
  if (!el) return;
  el.addEventListener('input', function(){
    var raw = el.value.toUpperCase().replace(/[^A-Z0-9]/g,'').slice(0,12);
    var out = raw.match(/.{1,4}/g);
    el.value = out ? out.join('-') : '';
  });
})();

// تبديل عرض الدخول/التسجيل بلا رحلة إلى الخادم عند وجود ?sg=
(function(){
  var p = new URLSearchParams(location.search).get('sg');
  if (!p) return;
  history.replaceState(null, '', location.pathname);
})();
</script>
</body>
</html>
