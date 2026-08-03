<section id="channels" class="sec">
  <div class="shdr"><h1 class="stitle"><?= $t["manage_channels"] ?? "إدارة <span>القنوات</span>" ?></h1><button class="btn btn-p" onclick="OM('addChM')"><i class="fas fa-plus"></i><?= $t["new_channel"] ?? "قناة جديدة" ?></button></div>
  <div class="tw">
    <div class="tt"><div class="tsrch"><i class="fas fa-search"></i><input type="text" id="chSearchInput" placeholder="<?= $t["search"] ?? "بحث..." ?>" oninput="chSearch(this.value)"></div><span id="chTotalCount" style="font-size:.78rem;color:var(--t3)"><?php echo count($channels); ?> <?= $t["channel"] ?? "قناة" ?></span></div>
    
    <div id="chBulkBar" style="display:none;align-items:center;gap:12px;padding:10px 14px;margin-bottom:10px;background:rgba(229,9,20,.08);border:1px solid rgba(229,9,20,.25);border-radius:10px">
      <span style="font-size:.82rem;color:var(--t1);font-weight:700"><i class="fas fa-check-square" style="color:var(--red)"></i> <span id="chSelCount">0</span> <?= $t["selected_channels"] ?? "قناة محددة" ?></span>
      <button class="btn btn-g" style="margin-right:auto;padding:6px 14px" onclick="chClearSel()"><i class="fas fa-times"></i> <?= $t["deselect_all"] ?? "إلغاء التحديد" ?></button>
      <button class="btn btn-p" style="padding:6px 14px;background:var(--red)" onclick="chBulkDelete()"><i class="fas fa-trash"></i> <?= $t["delete_selected"] ?? "حذف المحدد" ?></button>
    </div>

    <div id="chLoading" style="display:none;text-align:center;padding:50px;color:var(--t3)"><div class="pspin" style="margin:0 auto 12px"></div><p><?= $t["loading"] ?? "جارٍ تحميل القنوات…" ?></p></div>
    
    <table id="chTbl" style="display:none"><thead><tr><th style="width:38px"><input type="checkbox" id="chSelAll" onchange="chToggleAll(this)" style="width:16px;height:16px;cursor:pointer;accent-color:var(--red)"></th><th><?= $t["id"] ?? "ID" ?></th><th><?= $t["channel"] ?? "القناة" ?></th><th><?= $t["category"] ?? "القسم" ?></th><th><?= $t["quality"] ?? "الجودة" ?></th><th><?= $t["backup_link"] ?? "رابط احتياطي" ?></th><th><?= $t["has_subtitle"] ?? "ترجمة؟" ?></th><th><?= $t["views"] ?? "المشاهدات" ?></th><th><?= $t["status"] ?? "الحالة" ?></th><th><?= $t["actions"] ?? "إجراءات" ?></th></tr></thead><tbody id="chTblBody">
    </tbody></table>
    
    <div id="chEmpty" class="empty" style="display:none"><i class="fas fa-tv"></i><p><?= $t["no_channels"] ?? "لا توجد قنوات" ?></p></div>
    
    <div id="chPagination" style="display:none;justify-content:center;align-items:center;gap:15px;margin-top:20px;padding:10px;">
        <button class="btn btn-g bsm" id="chPrevBtn" onclick="chChangePage(-1)"><i class="fas fa-chevron-right"></i> <?= $t["previous"] ?? "السابق" ?></button>
        <span id="chPageInfo" style="font-size:0.9rem;font-weight:bold;color:var(--t1)"><?= $t["page_x_of_y"] ?? "صفحة 1 من 1" ?></span>
        <button class="btn btn-g bsm" id="chNextBtn" onclick="chChangePage(1)"><?= $t["next"] ?? "التالي" ?> <i class="fas fa-chevron-left"></i></button>
    </div>
  </div>
</section>

<!-- M3U IMPORT -->
