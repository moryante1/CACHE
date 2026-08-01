<section id="series" class="sec">
  <div class="shdr"><h1 class="stitle"><?= $t["manage_series"] ?? "إدارة <span>شاشتي</span>" ?></h1>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <button class="btn btn-g" id="srBackBtn" style="display:none" onclick="srBack()"><i class="fas fa-arrow-right"></i><?= $t["back"] ?? "رجوع" ?></button>
      <button class="btn btn-v" id="srBulkBtn" style="display:none" onclick="OM('bulkM')"><i class="fas fa-folder-open"></i><?= $t["bulk_upload"] ?? "رفع مجلد كامل" ?></button>
      <button class="btn btn-p" id="srAddBtn" onclick="OM('addSeriesM')"><i class="fas fa-plus"></i><?= $t["new_movie"] ?? "مسلسل / فيلم جديد" ?></button>
    </div>
  </div>
  <div id="srBreadcrumb" style="display:none;align-items:center;gap:8px;margin-bottom:18px;font-size:.855rem;color:var(--t3)"><span style="cursor:pointer;color:#4CC9F0" onclick="srBack()"><?= $t["series"] ?? "شاشتي" ?></span><i class="fas fa-chevron-left" style="font-size:.62rem"></i><strong id="srBCName" style="color:var(--t1)"></strong><span class="bdg bp" id="srBCCount" style="margin-right:6px"></span></div>
  <div id="srFilterBar" style="display:flex;gap:8px;align-items:center;margin-bottom:18px;flex-wrap:wrap">
    <select class="fs" id="srCatFilter" style="max-width:200px" onchange="loadSeries()"><option value=""><?= $t["all_categories"] ?? "كل الأقسام" ?></option><?php foreach($categories as $cat): ?><option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option><?php endforeach; ?></select>
    <div class="tsrch" style="max-width:230px;flex:1"><i class="fas fa-search"></i><input type="text" id="srSearch" placeholder="<?= $t["search_movie"] ?? "بحث عن فيلم/مسلسل..." ?>" oninput="srFilter()"></div>
    <span id="srCount" style="font-size:.78rem;color:var(--t3);margin-right:auto"></span>
  </div>
  <div id="srLoading" style="display:none;text-align:center;padding:50px;color:var(--t3)"><div class="pspin" style="margin:0 auto 12px"></div><p><?= $t["loading"] ?? "جارٍ التحميل…" ?></p></div>
  <div id="srGrid" class="srgrid"></div>
  <div id="srEmpty" style="display:none" class="empty"><i class="fas fa-film"></i><p><?= $t["no_data"] ?? "لا توجد بيانات بعد" ?></p></div>
  <div id="epsPanel" style="display:none">
    <div class="tw">
      <div class="tt" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; background:var(--s1)">
          <span style="font-weight:900;font-size:1.05rem;"><i class="fas fa-list-ul" style="color:var(--red); margin-left:8px"></i> <?= $t["manage_episodes"] ?? "إدارة عناصر ومقاطع العمل" ?></span>
          
          <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
              <span style="font-size:0.72rem; color:var(--gold); border:1px solid rgba(245, 166, 35, 0.2); background:rgba(245, 166, 35, 0.1); padding:4px 8px; border-radius:4px;"><i class="fas fa-mouse-pointer"></i> <?= $t["drag_drop_support"] ?? "ادعمنا بـ السحب والافلات للترتيب (يُحفظ تلقائياً)" ?></span>
              
              <select class="fs" id="epSortAZ" onchange="_sortAndSaveEps()" style="width:auto; padding:6px; font-size:0.75rem; font-weight:bold; cursor:pointer">
                  <option value="def"><?= $t["sort_default"] ?? "📅 ترتيب: حسب الإضافة للخادم" ?></option>
                  <option value="az"><?= $t["sort_asc"] ?? "✨ الفرز: تصاعدي الشامل (A-Z)" ?></option>
                  <option value="za"><?= $t["sort_desc"] ?? "✨ الفرز: تنازلي الشامل (Z-A)" ?></option>
                  <option value="manual" disabled style="background:#000;color:var(--t3)"><?= $t["sort_manual"] ?? "🤚 مُهندس يدويا (إفلات وماوس)" ?></option>
              </select>
              
              <button class="btn btn-p bsm" id="delBulkBtn" style="display:none; background:#ff4d57; color:#fff" onclick="deleteCheckedEps()">
                <i class="fas fa-trash-alt"></i> <?= $t["delete_selected_eps"] ?? "نسف التحديد" ?>
              </button>
                       <button class="btn btn-p bsm" id="convertMp4Btn" style="display:none; background:rgba(179,107,255,1); color:#fff; margin-right:8px; box-shadow:0 4px 14px rgba(179,107,255,.3);" onclick="convertCheckedEpsToMp4()">
   <i class="fas fa-magic"></i> <?= $t["convert_mp4"] ?? "التحويل السريع لـ MP4" ?>
</button>
              
          </div>
      </div>
      <table><thead><tr><th style="width:30px;"><input type="checkbox" id="chkEpsMaster" onclick="toggleChkEps(this)" style="cursor:pointer; width:16px;height:16px; accent-color:var(--red);"></th><th><?= $t["show"] ?? "العرض" ?></th><th><?= $t["work_name"] ?? "اسم العمل" ?></th><th><?= $t["host_ext"] ?? "امتداد الاستضافة" ?></th><th><?= $t["lang_enc"] ?? "تشفير لغة" ?></th><th><?= $t["duration"] ?? "المدة" ?></th><th><?= $t["features"] ?? "مـزايـــا" ?></th></tr></thead><tbody id="epsTbody"></tbody></table>
      <div id="epsEmpty" style="display:none" class="empty"><i class="fas fa-film"></i><p><?= $t["no_episodes"] ?? "لا توجد حلقات/فيديوهات" ?></p></div>
    </div>
  </div>
</section>

<!-- VIDEO UPLOAD -->
