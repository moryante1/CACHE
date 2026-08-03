<?php
/** قسم "المشتركون" — إدارة حسابات مستخدمي الموقع واشتراكاتهم. */
?>
<section id="subscribers" class="sec">

  <div class="shdr">
    <h1 class="stitle">
      <i class="fas fa-user-check" style="color:#4CC9F0"></i>
      <?= $t["subscribers_word"] ?? "إدارة" ?><span><?= $t["subscribers_word2"] ?? "المشتركين" ?></span>
    </h1>
    <button class="btn btn-p" onclick="suNewModal()">
      <i class="fas fa-user-plus"></i><?= $t["new_subscriber"] ?? "مشترك جديد" ?>
    </button>
  </div>

  <div id="suAlert"></div>

  <div class="sb-mini" id="suStatsBox"></div>

  <div class="sb-bar">
    <div class="tsrch" style="max-width:250px;flex:1">
      <i class="fas fa-search"></i>
      <input type="text" id="suSearch" placeholder="<?= htmlspecialchars($t["ph_search_user"] ?? "بحث بالاسم أو البريد…") ?>">
    </div>
    <select class="fs" id="suStatus" style="width:180px" onchange="suLoad(1)">
      <option value="all"><?= $t["all_subscribers"] ?? "كل المشتركين" ?></option>
      <option value="active"><?= $t["st_active"] ?? "اشتراك فعّال" ?></option>
      <option value="expired"><?= $t["st_expired"] ?? "منتهي" ?></option>
      <option value="pending"><?= $t["st_pending"] ?? "بانتظار التفعيل" ?></option>
      <option value="disabled"><?= $t["st_disabled"] ?? "موقوف" ?></option>
    </select>
    <button class="btn btn-g bsm" onclick="suLoad(1)" title="<?= htmlspecialchars($t["refresh"] ?? "تحديث") ?>">
      <i class="fas fa-sync-alt"></i>
    </button>
    <button class="btn btn-g bsm" onclick="suExport()" title="<?= htmlspecialchars($t["export_csv"] ?? "تصدير CSV") ?>">
      <i class="fas fa-download"></i>
    </button>
    <button class="btn btn-g bsm" onclick="suShowLogs(0)" title="<?= htmlspecialchars($t["site_login_logs"] ?? "سجل دخول المشتركين") ?>">
      <i class="fas fa-history"></i>
    </button>
    <span id="suCount" style="font-size:.78rem;color:var(--t3);margin-inline-start:auto"></span>
  </div>

  <div id="suLoading" style="display:none;text-align:center;padding:44px;color:var(--t3)">
    <div class="pspin" style="margin:0 auto 12px"></div>
    <p><?= $t["loading_dots2"] ?? "جارٍ التحميل…" ?></p>
  </div>

  <div class="sb-tbl-wrap" id="suTblWrap">
    <table class="sb-tbl">
      <thead>
        <tr>
          <th><?= $t["su_user"] ?? "المستخدم" ?></th>
          <th><?= $t["su_plan"] ?? "نوع الاشتراك" ?></th>
          <th><?= $t["su_status"] ?? "الحالة" ?></th>
          <th><?= $t["su_start"] ?? "البداية" ?></th>
          <th><?= $t["su_end"] ?? "الانتهاء" ?></th>
          <th><?= $t["su_days_left"] ?? "المتبقي" ?></th>
          <th><?= $t["su_via"] ?? "التفعيل" ?></th>
          <th><?= $t["su_last_login"] ?? "آخر دخول" ?></th>
          <th style="width:160px"></th>
        </tr>
      </thead>
      <tbody id="suBody"></tbody>
    </table>
  </div>

  <div id="suEmpty" class="sb-empty" style="display:none">
    <i class="fas fa-users"></i>
    <p><?= $t["no_subscribers"] ?? "لا يوجد مشتركون مطابقون" ?></p>
  </div>

  <div id="suPager" style="display:flex;gap:8px;justify-content:center;margin-top:18px"></div>
</section>


<!-- ═══════════════════ نوافذ نظام الاشتراكات ═══════════════════ -->

<!-- خطة اشتراك -->
<div class="mbd" id="planM">
  <div class="mbox w">
    <div class="mhd">
      <div class="mhd-title"><i class="fas fa-crown"></i><span id="planMTitle"><?= $t["new_plan"] ?? "خطة جديدة" ?></span></div>
      <button class="mclose" onclick="CM('planM')"><i class="fas fa-times"></i></button>
    </div>
    <div class="mbody">
      <input type="hidden" id="planId">
      <div class="row2">
        <div class="fg">
          <label class="fl"><?= $t["plan_code"] ?? "المعرّف البرمجي" ?></label>
          <input type="text" id="planCode" class="fi" placeholder="monthly">
          <small style="color:var(--t3);font-size:.68rem"><?= $t["plan_code_hint"] ?? "أحرف إنجليزية صغيرة وأرقام و _ فقط" ?></small>
        </div>
        <div class="fg">
          <label class="fl"><?= $t["plan_days"] ?? "المدة (بالأيام)" ?></label>
          <input type="number" id="planDays" class="fi" min="1" max="36500" value="30">
        </div>
      </div>
      <div class="fg">
        <label class="fl"><?= $t["plan_name_ar"] ?? "الاسم (عربي)" ?></label>
        <input type="text" id="planNameAr" class="fi" placeholder="اشتراك شهري">
      </div>
      <div class="row2">
        <div class="fg">
          <label class="fl"><?= $t["plan_name_en"] ?? "الاسم (إنجليزي)" ?></label>
          <input type="text" id="planNameEn" class="fi" placeholder="Monthly Plan">
        </div>
        <div class="fg">
          <label class="fl"><?= $t["plan_name_tr"] ?? "الاسم (تركي)" ?></label>
          <input type="text" id="planNameTr" class="fi" placeholder="Aylık Abonelik">
        </div>
      </div>
      <div class="row2">
        <div class="fg">
          <label class="fl"><?= $t["plan_price"] ?? "السعر" ?></label>
          <input type="number" id="planPrice" class="fi" min="0" step="0.01" value="0">
        </div>
        <div class="fg">
          <label class="fl"><?= $t["plan_order"] ?? "ترتيب العرض" ?></label>
          <input type="number" id="planOrder" class="fi" value="0">
        </div>
      </div>
      <div class="fg" style="display:flex;align-items:center;gap:12px">
        <label class="sb-sw"><input type="checkbox" id="planActive" checked><span></span></label>
        <span style="font-size:.85rem;color:var(--t2)"><?= $t["plan_is_active"] ?? "الخطة مفعّلة ومتاحة للبيع" ?></span>
      </div>
      <div id="planMAlert"></div>
    </div>
    <div class="mfooter">
      <button class="btn btn-g" onclick="CM('planM')"><?= $t["cancel"] ?? "إلغاء" ?></button>
      <button class="btn btn-p" onclick="planSave()"><i class="fas fa-save"></i><?= $t["save_word"] ?? "حفظ" ?></button>
    </div>
  </div>
</div>

<!-- توليد كوبونات -->
<div class="mbd" id="cpM">
  <div class="mbox w">
    <div class="mhd">
      <div class="mhd-title"><i class="fas fa-ticket-alt"></i><?= $t["generate_coupons"] ?? "توليد كوبونات" ?></div>
      <button class="mclose" onclick="CM('cpM')"><i class="fas fa-times"></i></button>
    </div>
    <div class="mbody">
      <div id="cpForm">
        <div class="fg">
          <label class="fl"><?= $t["cp_choose_plan"] ?? "نوع الاشتراك" ?></label>
          <select class="fs" id="cpMPlan" style="width:100%"></select>
        </div>
        <div class="row2">
          <div class="fg">
            <label class="fl"><?= $t["cp_count"] ?? "عدد الكوبونات" ?></label>
            <input type="number" id="cpMCount" class="fi" min="1" max="500" value="10">
          </div>
          <div class="fg">
            <label class="fl"><?= $t["cp_max_uses"] ?? "عدد مرات الاستخدام لكل كوبون" ?></label>
            <input type="number" id="cpMUses" class="fi" min="1" value="1">
          </div>
        </div>
        <div class="row2">
          <div class="fg">
            <label class="fl"><?= $t["cp_expires"] ?? "صلاحية الكوبون نفسه (اختياري)" ?></label>
            <input type="date" id="cpMExp" class="fi">
          </div>
          <div class="fg">
            <label class="fl"><?= $t["cp_note"] ?? "ملاحظة" ?></label>
            <input type="text" id="cpMNote" class="fi" maxlength="255"
                   placeholder="<?= htmlspecialchars($t["cp_note_ph"] ?? "دفعة وكيل بغداد") ?>">
          </div>
        </div>
        <div id="cpMAlert"></div>
      </div>

      <div id="cpResult" style="display:none">
        <p style="color:#00D084;font-weight:700;margin-bottom:10px">
          <i class="fas fa-check-circle"></i>
          <span id="cpResultMsg"></span>
        </p>
        <div class="sb-codes-out" id="cpCodesOut"></div>
        <div style="display:flex;gap:8px;margin-top:12px">
          <button class="btn btn-g" onclick="cpCopyCodes()"><i class="fas fa-copy"></i><?= $t["copy_all"] ?? "نسخ الكل" ?></button>
          <button class="btn btn-g" onclick="cpDownloadCodes()"><i class="fas fa-download"></i><?= $t["download_txt"] ?? "تحميل ملف" ?></button>
        </div>
      </div>
    </div>
    <div class="mfooter">
      <button class="btn btn-g" onclick="CM('cpM')"><?= $t["close"] ?? "إغلاق" ?></button>
      <button class="btn btn-p" id="cpMSubmit" onclick="cpGenerate()"><i class="fas fa-bolt"></i><?= $t["generate"] ?? "توليد" ?></button>
    </div>
  </div>
</div>

<!-- مشترك: إنشاء / تعديل -->
<div class="mbd" id="suM">
  <div class="mbox w">
    <div class="mhd">
      <div class="mhd-title"><i class="fas fa-user-plus"></i><span id="suMTitle"><?= $t["new_subscriber"] ?? "مشترك جديد" ?></span></div>
      <button class="mclose" onclick="CM('suM')"><i class="fas fa-times"></i></button>
    </div>
    <div class="mbody">
      <input type="hidden" id="suId">
      <div class="row2">
        <div class="fg">
          <label class="fl"><?= $t["su_username"] ?? "اسم المستخدم" ?></label>
          <input type="text" id="suUsername" class="fi" autocomplete="off">
          <small style="color:var(--t3);font-size:.68rem"><?= $t["su_username_hint"] ?? "3–50 حرفاً: أحرف إنجليزية وأرقام و _ . -" ?></small>
        </div>
        <div class="fg">
          <label class="fl"><?= $t["su_email"] ?? "البريد (اختياري)" ?></label>
          <input type="email" id="suEmail" class="fi" autocomplete="off">
        </div>
      </div>
      <div class="fg">
        <label class="fl"><span id="suPwLabel"><?= $t["su_password"] ?? "كلمة المرور" ?></span></label>
        <input type="password" id="suPassword" class="fi" autocomplete="new-password"
               placeholder="<?= htmlspecialchars($t["ph_8_chars"] ?? "8 أحرف على الأقل") ?>">
      </div>
      <div class="fg">
        <label class="fl"><?= $t["su_notes"] ?? "ملاحظات" ?></label>
        <textarea id="suNotes" class="fi" rows="2" style="resize:vertical"></textarea>
      </div>

      <div id="suActivateBox" style="border-top:1px solid var(--br,#2a2a2a);padding-top:14px;margin-top:6px">
        <div class="fg" style="display:flex;align-items:center;gap:12px">
          <label class="sb-sw"><input type="checkbox" id="suActivateNow" onchange="suToggleActivate()"><span></span></label>
          <span style="font-size:.85rem;color:var(--t2)"><?= $t["su_activate_now"] ?? "تفعيل الاشتراك فوراً" ?></span>
        </div>
        <div class="row2" id="suActivateFields" style="display:none">
          <div class="fg">
            <label class="fl"><?= $t["su_plan_pick"] ?? "الخطة" ?></label>
            <select class="fs" id="suPlan" style="width:100%" onchange="suPlanChanged()"></select>
          </div>
          <div class="fg">
            <label class="fl"><?= $t["su_days"] ?? "عدد الأيام (0 = بلا نهاية)" ?></label>
            <input type="number" id="suDays" class="fi" min="0" max="36500" value="30">
          </div>
        </div>
      </div>
      <div id="suMAlert"></div>
    </div>
    <div class="mfooter">
      <button class="btn btn-g" onclick="CM('suM')"><?= $t["cancel"] ?? "إلغاء" ?></button>
      <button class="btn btn-p" onclick="suSave()"><i class="fas fa-save"></i><?= $t["save_word"] ?? "حفظ" ?></button>
    </div>
  </div>
</div>

<!-- تفعيل / تمديد اشتراك -->
<div class="mbd" id="suActM">
  <div class="mbox">
    <div class="mhd">
      <div class="mhd-title"><i class="fas fa-bolt"></i><?= $t["su_manage_sub"] ?? "إدارة الاشتراك" ?></div>
      <button class="mclose" onclick="CM('suActM')"><i class="fas fa-times"></i></button>
    </div>
    <div class="mbody">
      <input type="hidden" id="suActId">
      <p style="color:var(--t3);font-size:.82rem;margin-bottom:14px">
        <?= $t["su_manage_for"] ?? "المشترك" ?>: <b id="suActName" style="color:var(--t1)"></b>
      </p>
      <div class="fg">
        <label class="fl"><?= $t["su_plan_pick"] ?? "الخطة" ?></label>
        <select class="fs" id="suActPlan" style="width:100%" onchange="suActPlanChanged()"></select>
      </div>
      <div class="fg">
        <label class="fl"><?= $t["su_days"] ?? "عدد الأيام (0 = بلا نهاية)" ?></label>
        <input type="number" id="suActDays" class="fi" min="0" max="36500" value="30">
      </div>
      <div id="suActAlert"></div>
      <div style="border-top:1px solid var(--br,#2a2a2a);margin-top:14px;padding-top:14px">
        <label class="fl" style="margin-bottom:8px;display:block"><?= $t["su_quick_extend"] ?? "تمديد سريع (يُضاف للمتبقي)" ?></label>
        <div style="display:flex;gap:7px;flex-wrap:wrap">
          <button class="sb-btn ok" onclick="suExtend(7)">+7</button>
          <button class="sb-btn ok" onclick="suExtend(30)">+30</button>
          <button class="sb-btn ok" onclick="suExtend(90)">+90</button>
          <button class="sb-btn ok" onclick="suExtend(365)">+365</button>
          <button class="sb-btn dg" onclick="suExtend(-7)">−7</button>
          <button class="sb-btn dg" onclick="suExtend(-30)">−30</button>
        </div>
      </div>
    </div>
    <div class="mfooter">
      <button class="btn btn-g" onclick="CM('suActM')"><?= $t["cancel"] ?? "إلغاء" ?></button>
      <button class="btn btn-p" onclick="suActivateSave()"><i class="fas fa-check"></i><?= $t["su_activate"] ?? "تفعيل" ?></button>
    </div>
  </div>
</div>

<!-- سجل / تاريخ -->
<div class="mbd" id="subsLogM">
  <div class="mbox w">
    <div class="mhd">
      <div class="mhd-title"><i class="fas fa-history"></i><span id="subsLogTitle"><?= $t["site_login_logs"] ?? "سجل دخول المشتركين" ?></span></div>
      <button class="mclose" onclick="CM('subsLogM')"><i class="fas fa-times"></i></button>
    </div>
    <div class="mbody">
      <div class="sb-tbl-wrap"><table class="sb-tbl"><tbody id="subsLogBody"></tbody></table></div>
    </div>
    <div class="mfooter">
      <button class="btn btn-g" onclick="CM('subsLogM')"><?= $t["close"] ?? "إغلاق" ?></button>
    </div>
  </div>
</div>
