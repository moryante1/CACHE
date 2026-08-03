<?php
/** قسم "الكوبونات" — إنشاء وإدارة أكواد التفعيل. */
?>
<section id="coupons" class="sec">

  <div class="shdr">
    <h1 class="stitle">
      <i class="fas fa-ticket-alt" style="color:#00D084"></i>
      <?= $t["coupons_word"] ?? "أكواد" ?><span><?= $t["coupons_word2"] ?? "التفعيل" ?></span>
    </h1>
    <button class="btn btn-p" onclick="cpNewModal()">
      <i class="fas fa-plus"></i><?= $t["generate_coupons"] ?? "توليد كوبونات" ?>
    </button>
  </div>

  <div id="cpAlert"></div>

  <div class="sb-bar">
    <div class="tsrch" style="max-width:250px;flex:1">
      <i class="fas fa-search"></i>
      <input type="text" id="cpSearch" placeholder="<?= htmlspecialchars($t["ph_search_coupon"] ?? "بحث بالكود أو المستخدم…") ?>">
    </div>
    <select class="fs" id="cpStatus" style="width:170px" onchange="cpLoad(1)">
      <option value="all"><?= $t["all_coupons"] ?? "كل الكوبونات" ?></option>
      <option value="active"><?= $t["cp_available"] ?? "متاح" ?></option>
      <option value="used"><?= $t["cp_used_up"] ?? "مستهلك" ?></option>
      <option value="disabled"><?= $t["cp_disabled"] ?? "معطّل" ?></option>
    </select>
    <select class="fs" id="cpPlan" style="width:180px" onchange="cpLoad(1)">
      <option value="0"><?= $t["all_plans"] ?? "كل الخطط" ?></option>
    </select>
    <button class="btn btn-g bsm" onclick="cpLoad(1)" title="<?= htmlspecialchars($t["refresh"] ?? "تحديث") ?>">
      <i class="fas fa-sync-alt"></i>
    </button>
    <button class="btn btn-g bsm" onclick="cpExport()" title="<?= htmlspecialchars($t["export_csv"] ?? "تصدير CSV") ?>">
      <i class="fas fa-download"></i>
    </button>
    <button class="btn btn-g bsm" onclick="cpPurgeUsed()" title="<?= htmlspecialchars($t["delete_used_coupons"] ?? "حذف المستهلكة") ?>"
            style="color:#ff5a63">
      <i class="fas fa-broom"></i>
    </button>
    <span id="cpCount" style="font-size:.78rem;color:var(--t3);margin-inline-start:auto"></span>
  </div>

  <div id="cpLoading" style="display:none;text-align:center;padding:44px;color:var(--t3)">
    <div class="pspin" style="margin:0 auto 12px"></div>
    <p><?= $t["loading_dots2"] ?? "جارٍ التحميل…" ?></p>
  </div>

  <div class="sb-tbl-wrap" id="cpTblWrap">
    <table class="sb-tbl">
      <thead>
        <tr>
          <th><?= $t["cp_code"] ?? "الكود" ?></th>
          <th><?= $t["cp_plan"] ?? "الخطة" ?></th>
          <th><?= $t["cp_duration"] ?? "المدة" ?></th>
          <th><?= $t["cp_uses"] ?? "الاستخدام" ?></th>
          <th><?= $t["cp_status"] ?? "الحالة" ?></th>
          <th><?= $t["cp_user"] ?? "المستخدم" ?></th>
          <th><?= $t["cp_created"] ?? "الإنشاء" ?></th>
          <th><?= $t["cp_used_at"] ?? "الاستخدام" ?></th>
          <th style="width:130px"></th>
        </tr>
      </thead>
      <tbody id="cpBody"></tbody>
    </table>
  </div>

  <div id="cpEmpty" class="sb-empty" style="display:none">
    <i class="fas fa-ticket-alt"></i>
    <p><?= $t["no_coupons"] ?? "لا توجد كوبونات مطابقة" ?></p>
  </div>

  <div id="cpPager" style="display:flex;gap:8px;justify-content:center;margin-top:18px"></div>
</section>
