<?php
/**
 * قسم "الاشتراكات" — خطط الاشتراك + إعدادات حماية الصفحة الرئيسية.
 * كل الأنماط محلية داخل هذا القسم (بادئة .sb-) حتى لا تعتمد على
 * أصناف CSS قد تتغيّر في الثيمات، ولا تتسرّب إلى بقية اللوحة.
 */
?>
<section id="subscriptions" class="sec">

  <style>
  /* ── أنماط نظام الاشتراكات (معزولة ببادئة sb-) ── */
  .sb-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px}
  .sb-card{background:var(--bg2,#181818);border:1px solid var(--br,#2a2a2a);border-radius:14px;padding:18px;position:relative;transition:.2s;overflow:hidden}
  .sb-card:hover{border-color:var(--red,#e50914);transform:translateY(-2px)}
  .sb-card.off{opacity:.55}
  .sb-card .sb-ribbon{position:absolute;top:0;inset-inline-end:0;padding:3px 12px;font-size:.62rem;font-weight:800;border-bottom-left-radius:10px;letter-spacing:.5px}
  .sb-rb-on{background:rgba(0,208,132,.18);color:#00D084}
  .sb-rb-off{background:rgba(229,9,20,.15);color:#e50914}
  .sb-plan-name{font-size:1.05rem;font-weight:800;color:var(--t1,#fff);margin:4px 0 2px}
  .sb-plan-code{font-size:.68rem;color:var(--t3,#888);font-family:ui-monospace,monospace;letter-spacing:.5px}
  .sb-price{font-size:1.9rem;font-weight:900;color:var(--red,#e50914);margin:12px 0 2px;line-height:1}
  .sb-dur{font-size:.78rem;color:var(--t3,#888);margin-bottom:14px}
  .sb-acts{display:flex;gap:7px;flex-wrap:wrap}
  .sb-btn{border:1px solid var(--br,#2a2a2a);background:transparent;color:var(--t2,#ccc);padding:6px 11px;border-radius:8px;font-size:.74rem;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:5px;transition:.18s;font-family:inherit}
  .sb-btn:hover{border-color:var(--red,#e50914);color:var(--red,#e50914)}
  .sb-btn.ok:hover{border-color:#00D084;color:#00D084}
  .sb-btn.dg:hover{border-color:#e50914;color:#e50914;background:rgba(229,9,20,.08)}
  .sb-tbl{width:100%;border-collapse:collapse;font-size:.8rem}
  .sb-tbl th{text-align:start;padding:11px 10px;background:var(--bg3,#1f1f1f);color:var(--t3,#888);font-weight:700;font-size:.72rem;white-space:nowrap;border-bottom:1px solid var(--br,#2a2a2a)}
  .sb-tbl td{padding:10px;border-bottom:1px solid var(--br,#242424);color:var(--t2,#ccc);vertical-align:middle}
  .sb-tbl tbody tr:hover{background:rgba(255,255,255,.025)}
  .sb-tbl-wrap{overflow-x:auto;border:1px solid var(--br,#2a2a2a);border-radius:12px;background:var(--bg2,#181818)}
  .sb-pill{display:inline-block;padding:3px 10px;border-radius:20px;font-size:.68rem;font-weight:800;white-space:nowrap}
  .sb-p-active{background:rgba(0,208,132,.15);color:#00D084}
  .sb-p-expired{background:rgba(229,9,20,.15);color:#ff5a63}
  .sb-p-pending{background:rgba(245,166,35,.15);color:#F5A623}
  .sb-p-disabled{background:rgba(150,150,150,.15);color:#999}
  .sb-code{font-family:ui-monospace,'Courier New',monospace;font-weight:800;letter-spacing:1.2px;font-size:.82rem;color:var(--t1,#fff)}
  .sb-mini{display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:12px;margin-bottom:20px}
  .sb-mini-c{background:var(--bg2,#181818);border:1px solid var(--br,#2a2a2a);border-radius:12px;padding:14px;text-align:center}
  .sb-mini-v{font-size:1.5rem;font-weight:900;color:var(--t1,#fff);line-height:1.1}
  .sb-mini-l{font-size:.7rem;color:var(--t3,#888);margin-top:4px}
  .sb-bar{display:flex;gap:9px;align-items:center;margin-bottom:16px;flex-wrap:wrap}
  .sb-empty{text-align:center;padding:46px 20px;color:var(--t3,#888)}
  .sb-empty i{font-size:2.4rem;opacity:.35;display:block;margin-bottom:12px}
  .sb-codes-out{background:#0d0d0d;border:1px solid var(--br,#2a2a2a);border-radius:10px;padding:14px;max-height:260px;overflow:auto;font-family:ui-monospace,monospace;font-size:.85rem;line-height:2;letter-spacing:1px;color:#00D084;user-select:all}
  .sb-sw{position:relative;display:inline-block;width:48px;height:26px;flex-shrink:0}
  .sb-sw input{opacity:0;width:0;height:0}
  .sb-sw span{position:absolute;inset:0;background:#3a3a3a;border-radius:26px;cursor:pointer;transition:.25s}
  .sb-sw span:before{content:"";position:absolute;height:20px;width:20px;left:3px;top:3px;background:#fff;border-radius:50%;transition:.25s}
  .sb-sw input:checked+span{background:#00D084}
  .sb-sw input:checked+span:before{transform:translateX(22px)}
  .sb-row{display:flex;align-items:center;gap:14px;padding:15px 0;border-bottom:1px solid var(--br,#242424)}
  .sb-row:last-child{border-bottom:none}
  .sb-row-t{font-weight:700;color:var(--t1,#fff);font-size:.9rem}
  .sb-row-d{font-size:.75rem;color:var(--t3,#888);margin-top:3px;line-height:1.5}
  </style>

  <div class="shdr">
    <h1 class="stitle">
      <i class="fas fa-crown" style="color:#F5A623"></i>
      <?= $t["subs_word"] ?? "خطط" ?><span><?= $t["subs_word2"] ?? "الاشتراك" ?></span>
    </h1>
    <button class="btn btn-p" onclick="planNew()">
      <i class="fas fa-plus"></i><?= $t["new_plan"] ?? "خطة جديدة" ?>
    </button>
  </div>

  <div id="planAlert"></div>

  <!-- إحصاءات سريعة -->
  <div class="sb-mini" id="subsStatsBox"></div>

  <!-- بطاقات الخطط -->
  <div id="plansGrid" class="sb-grid"></div>
  <div id="plansEmpty" class="sb-empty" style="display:none">
    <i class="fas fa-crown"></i>
    <p><?= $t["no_plans"] ?? "لا توجد خطط بعد — أنشئ خطة جديدة" ?></p>
  </div>

  <!-- ══════ إعدادات الحماية والوصول ══════ -->
  <div style="margin-top:34px">
    <h2 style="font-size:1.05rem;font-weight:800;color:var(--t1,#fff);margin-bottom:14px">
      <i class="fas fa-shield-alt" style="color:#4CC9F0;margin-inline-end:8px"></i>
      <?= $t["access_protection"] ?? "الحماية والوصول" ?>
    </h2>

    <div style="background:var(--bg2,#181818);border:1px solid var(--br,#2a2a2a);border-radius:14px;padding:6px 20px">

      <div class="sb-row">
        <label class="sb-sw">
          <input type="checkbox" id="setIndexProtection" onchange="subsSaveSettings()"><span></span>
        </label>
        <div style="flex:1">
          <div class="sb-row-t"><?= $t["protect_index"] ?? "حماية الصفحة الرئيسية" ?></div>
          <div class="sb-row-d">
            <?= $t["protect_index_desc"] ?? "عند الإيقاف: يدخل أي زائر مباشرةً بلا اسم مستخدم أو كلمة مرور. عند التفعيل: تظهر صفحة تسجيل دخول، ولا يُعرض أي محتوى قبل الدخول باشتراك فعّال." ?>
          </div>
        </div>
      </div>

      <div class="sb-row">
        <label class="sb-sw">
          <input type="checkbox" id="setAllowReg" onchange="subsSaveSettings()"><span></span>
        </label>
        <div style="flex:1">
          <div class="sb-row-t"><?= $t["allow_registration"] ?? "السماح بإنشاء حسابات جديدة" ?></div>
          <div class="sb-row-d">
            <?= $t["allow_registration_desc"] ?? "عند الإيقاف يختفي زر «إنشاء حساب» ولا تُقبل أي تسجيلات — تُنشئ الحسابات من لوحة الإدارة فقط." ?>
          </div>
        </div>
      </div>

      <div class="sb-row">
        <div style="width:48px;text-align:center;color:#F5A623;font-size:1.3rem"><i class="fas fa-coins"></i></div>
        <div style="flex:1">
          <div class="sb-row-t"><?= $t["currency"] ?? "العملة" ?></div>
          <div class="sb-row-d"><?= $t["currency_desc"] ?? "الرمز يظهر بجانب كل سعر في اللوحة وصفحات الاشتراك." ?></div>
        </div>
        <input type="text" id="setCurSymbol" class="fi" maxlength="8" style="width:90px;text-align:center"
               placeholder="$" onchange="subsSaveSettings()">
        <input type="text" id="setCurCode" class="fi" maxlength="8" style="width:100px;text-align:center"
               placeholder="USD" onchange="subsSaveSettings()">
      </div>

    </div>
    <div id="subsSetAlert" style="margin-top:10px"></div>
  </div>

  <!-- ══════ وسيط إعادة البثّ ══════ -->
  <div style="margin-top:34px">
    <h2 style="font-size:1.05rem;font-weight:800;color:var(--t1,#fff);margin-bottom:6px">
      <i class="fas fa-headphones" style="color:#B36BFF;margin-inline-end:8px"></i>
      <?= $t["restream_title"] ?? "إصلاح صوت القنوات (AC3)" ?>
    </h2>
    <p style="font-size:.79rem;color:var(--t3,#888);margin-bottom:14px;line-height:1.7;max-width:760px">
      <?= $t["restream_desc"] ?? "بعض قنوات Xtream تبثّ صوتاً بترميز AC3 لا يفكّه أي متصفح، فتظهر الصورة بلا صوت. عند التفعيل يحوّل الخادم الصوت إلى AAC تلقائياً وينسخ الفيديو كما هو. عملية واحدة لكل قناة تخدم كل مشاهديها." ?>
    </p>

    <div style="background:var(--bg2,#181818);border:1px solid var(--br,#2a2a2a);border-radius:14px;padding:6px 20px">

      <div class="sb-row">
        <label class="sb-sw">
          <input type="checkbox" id="setRestream" onchange="rsToggle()"><span></span>
        </label>
        <div style="flex:1">
          <div class="sb-row-t"><?= $t["restream_enable"] ?? "تفعيل الوسيط" ?></div>
          <div class="sb-row-d" id="rsStateNote"><?= $t["restream_loading"] ?? "جارٍ قراءة الحالة…" ?></div>
        </div>
        <button class="sb-btn dg" onclick="rsStopAll()" id="rsStopBtn" style="display:none">
          <i class="fas fa-stop"></i><?= $t["restream_stop_all"] ?? "إنهاء الكل" ?>
        </button>
        <button class="sb-btn" onclick="rsLoadStatus()" title="<?= htmlspecialchars($t["refresh"] ?? "تحديث") ?>">
          <i class="fas fa-sync-alt"></i>
        </button>
      </div>

      <?php /* حدّ القنوات المتزامنة — يُضبط من هنا بلا طرفية ولا sudo */ ?>
      <div class="sb-row" style="align-items:center;gap:10px">
        <div style="width:48px;text-align:center;color:#4CC9F0;font-size:1.15rem"><i class="fas fa-sliders-h"></i></div>
        <div style="flex:1">
          <div class="sb-row-t"><?= $t["restream_limit_title"] ?? "حدّ القنوات المتزامنة" ?></div>
          <div class="sb-row-d" style="color:var(--t3,#8a8a94)">
            <?= $t["restream_limit_hint"] ?? "كل قناة ≈ ٥ ميغابت/ث من نطاقك. اختر بحسب نطاقك لا المعالج." ?>
          </div>
        </div>
        <input type="number" id="rsLimitInput" min="1" max="500" step="1"
               style="width:80px;text-align:center;padding:8px;border-radius:8px;border:1px solid var(--br,#2a2a2a);background:var(--bg,#0f0f10);color:var(--t1,#fff);font-weight:700">
        <button class="sb-btn" onclick="rsSaveLimit()" id="rsLimitSaveBtn">
          <i class="fas fa-check"></i><?= $t["save"] ?? "حفظ" ?>
        </button>
      </div>

      <div class="sb-row" id="rsLiveRow" style="display:none">
        <div style="width:48px;text-align:center;color:#00D084;font-size:1.3rem"><i class="fas fa-broadcast-tower"></i></div>
        <div style="flex:1">
          <div class="sb-row-t"><?= $t["restream_live"] ?? "القنوات العاملة الآن" ?></div>
          <div class="sb-row-d" id="rsLiveList">—</div>
        </div>
      </div>

    </div>

    <div class="sb-mini" id="rsStatsBox" style="margin-top:14px"></div>
    <div id="rsAlert" style="margin-top:10px"></div>

    <p style="font-size:.75rem;color:var(--t3,#888);margin-top:12px;line-height:1.8;
              background:rgba(245,166,35,.07);border:1px solid rgba(245,166,35,.25);
              border-radius:10px;padding:12px 15px;max-width:760px">
      <b style="color:#F5A623"><?= $t["restream_warn_title"] ?? "تنبيه النطاق الترددي" ?></b><br>
      <?= $t["restream_warn"] ?? "عند التفعيل يسحب كل مشاهد البثّ من خادمك بدل مزوّد Xtream. ٥٠٠ مشاهد يحتاجون نحو ٢٫٥ غيغابت/ثانية صادرة. راقب استهلاك الشبكة بعد التفعيل." ?>
    </p>
  </div>
</section>
