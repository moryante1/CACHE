











<!-- ADMIN MUSIC PLAYER SECTION REMOVED - intero.mp3 fixed -->

</div>
<!-- ══ فوتر النظام (إضافة) ══ -->
<footer class="sys-footer">
  <span class="sf-dot"></span>
  <span>SHA System &copy; 2026 <b>SHASHITY PRO</b></span>
</footer>
</div>

<!-- MODALS -->
<!-- PLAYER -->
<div id="pm">
  <div class="pbox"><div class="phd"><div class="phd-l"><div class="pdot" id="pdot"></div><div class="ptitle" id="ptitle"><?= $t["rv_loading"] ?? "جارٍ التحميل…" ?></div></div><div style="display:flex;align-items:center;gap:8px"><span id="pcodec" style="display:none;font-size:.65rem;font-weight:800;padding:2px 8px;border-radius:4px;margin-left:5px;"></span><span id="pfmt" style="display:none;font-size:.65rem;font-weight:800;padding:2px 8px;border-radius:4px;background:rgba(229,9,20,.15);border:1px solid rgba(229,9,20,.3);color:var(--red)">HLS</span><button class="mclose" onclick="closePlayer()"><i class="fas fa-times"></i></button></div></div>
  <div class="pwrap"><video id="tv" controls playsinline crossorigin="anonymous"></video><div class="pload" id="pload"><div class="pspin"></div><p style="font-size:.83rem;color:var(--t3)"><?= $t["rv_loading_video"] ?? "جارٍ تحميل الفيديو…" ?></p></div><div class="perr" id="perr"><div style="font-size:2.5rem;color:var(--red)"><i class="fas fa-exclamation-triangle"></i></div><h3><?= $t["rv_play_failed"] ?? "تعذّر تشغيل الفيديو" ?></h3><p id="perrMsg" style="color:var(--t3);font-size:.83rem;max-width:360px"><?= $t["rv_check_link"] ?? "تحقق من الرابط أو تنسيق الملف" ?></p><div style="display:flex;gap:8px;margin-top:6px;flex-wrap:wrap;justify-content:center"><button class="btn btn-p" onclick="pRetry()"><i class="fas fa-redo"></i><?= $t["rv_retry"] ?? "إعادة المحاولة" ?></button><button class="btn btn-g" onclick="pOpenNew()"><i class="fas fa-external-link-alt"></i><?= $t["rv_open_tab"] ?? "فتح في تبويب" ?></button></div></div></div>
  <div class="psubbar" id="psubbar" style="display:none"><i class="fas fa-closed-captioning" style="color:#4CC9F0"></i><span id="psubLabel" style="flex:1;font-size:.75rem"><?= $t["rv_sub_active"] ?? "ترجمة نشطة" ?></span><button class="pbtn" onclick="pToggleSub()"><i class="fas fa-toggle-on" id="psubToggleIc"></i><span id="psubToggleTxt"><?= $t["rv_hide"] ?? "إخفاء" ?></span></button></div>
  <div class="pft"><span class="purl" id="purl">—</span><div class="pbtns"><button class="pbtn" onclick="pCopyUrl()"><i class="fas fa-copy"></i><?= $t["rv_copy"] ?? "نسخ" ?></button><button class="pbtn" onclick="pOpenNew()"><i class="fas fa-external-link-alt"></i><?= $t["rv_new"] ?? "جديد" ?></button><button class="pbtn" style="background:rgba(229,9,20,.1);color:var(--red);border-color:rgba(229,9,20,.2)" onclick="closePlayer()"><i class="fas fa-times"></i><?= $t["rv_close"] ?? "إغلاق" ?></button></div></div>
  </div>
</div>

<!-- ADD CATEGORY MODAL -->
<div class="mbd" id="addCatM"><div class="mbox"><div class="mhd"><div class="mhd-title"><i class="fas fa-folder-plus"></i><?= $t["rv_new_category"] ?? "قسم جديد" ?></div><button class="mclose" onclick="CM('addCatM')"><i class="fas fa-times"></i></button></div>
<form method="POST"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"><div class="mbody"><div class="fg"><label class="fl"><?= $t["rv_category_name"] ?? "اسم القسم" ?></label><input type="text" name="category_name" class="fi" required placeholder="<?= htmlspecialchars($t["rv_ph_cat"] ?? "مثال: أفلام عربية") ?>"></div><div class="fg"><label class="fl"><?= $t["rv_parent_opt"] ?? "القسم الأب (اختياري)" ?></label><select name="parent_id" class="fs"><option value=""><?= $t["rv_no_parent"] ?? "بدون — قسم رئيسي" ?></option><?php foreach($categories as $cat): ?><option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option><?php endforeach; ?></select></div><div class="fg"><label class="fl"><?= $t["rv_icon"] ?? "الأيقونة" ?></label><input type="text" name="category_icon" class="fi" value="fas fa-th-large" placeholder="fas fa-film"></div><div class="fg"><label class="fl"><?= $t["rv_desc_opt"] ?? "الوصف (اختياري)" ?></label><input type="text" name="description" class="fi" placeholder="<?= htmlspecialchars($t["rv_ph_desc"] ?? "وصف مختصر") ?>"></div></div>
<div class="mfooter"><button type="button" class="btn btn-g" onclick="CM('addCatM')"><?= $t["rv_cancel"] ?? "إلغاء" ?></button><button type="submit" name="add_category" class="btn btn-p"><i class="fas fa-check"></i><?= $t["rv_add"] ?? "إضافة" ?></button></div></form></div></div>

<!-- EDIT CATEGORY MODAL -->
<div class="mbd" id="editCatM"><div class="mbox"><div class="mhd"><div class="mhd-title"><i class="fas fa-pen"></i><?= $t["rv_edit_category"] ?? "تعديل القسم" ?></div><button class="mclose" onclick="CM('editCatM')"><i class="fas fa-times"></i></button></div>
<form method="POST"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"><div class="mbody"><input type="hidden" name="category_id" id="eCatId"><div class="fg"><label class="fl"><?= $t["rv_category_name"] ?? "اسم القسم" ?></label><input type="text" name="category_name" id="eCatName" class="fi" required></div><div class="fg"><label class="fl"><?= $t["rv_parent"] ?? "القسم الأب" ?></label><select name="parent_id" id="eCatParent" class="fs"><option value=""><?= $t["rv_no_parent"] ?? "بدون — قسم رئيسي" ?></option><?php foreach($categories as $cat): ?><option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option><?php endforeach; ?></select></div><div class="fg"><label class="fl"><?= $t["rv_icon"] ?? "الأيقونة" ?></label><input type="text" name="category_icon" id="eCatIcon" class="fi"></div></div>
<div class="mfooter"><button type="button" class="btn btn-g" onclick="CM('editCatM')"><?= $t["rv_cancel"] ?? "إلغاء" ?></button><button type="submit" name="edit_category" class="btn btn-p"><i class="fas fa-check"></i><?= $t["rv_save"] ?? "حفظ" ?></button></div></form></div></div>

<!-- ADD CHANNEL MODAL -->
<div class="mbd" id="addChM"><div class="mbox w"><div class="mhd"><div class="mhd-title"><i class="fas fa-plus"></i><?= $t["rv_new_channel"] ?? "قناة جديدة" ?></div><button class="mclose" onclick="CM('addChM')"><i class="fas fa-times"></i></button></div>
<form method="POST"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"><div class="mbody"><div class="fg"><label class="fl"><?= $t["rv_category"] ?? "القسم" ?></label><select name="category_id" class="fs" required><option value=""><?= $t["rv_choose_category"] ?? "— اختر القسم —" ?></option><?php foreach($categories as $cat): ?><option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option><?php endforeach; ?></select></div>
<div class="fg fg-rel"><div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:7px"><label class="fl" style="margin:0"><?= $t["rv_channel_name"] ?? "اسم القناة" ?></label></div><input type="text" name="channel_name" id="addChName" class="fi" required placeholder="<?= htmlspecialchars($t["rv_ph_channel"] ?? "مثال: MBC1") ?>"></div>
<div class="fg"><label class="fl"><?= $t["rv_stream_url"] ?? "رابط البث" ?></label><input type="text" name="stream_url" class="fi" required placeholder="https://..."></div>
<div class="fg"><label class="fl"><?= $t["rv_backup_url"] ?? "رابط احتياطي (Backup URL)" ?><span style="color:var(--t3);font-weight:400"><?= $t["rv_optional_dash"] ?? "— اختياري" ?></span></label><input type="text" name="backup_url" class="fi" placeholder="<?= htmlspecialchars($t["rv_ph_backup"] ?? "https://... رابط بديل عند تعطّل الرابط الأساسي") ?>"></div>
<div class="fg"><label class="fl"><?= $t["rv_quality"] ?? "الجودة" ?></label><select name="quality" class="fs">
  <option value="SD 480">SD 480</option>
  <option value="HD 720" selected>HD 720</option>
  <option value="Full HD 1080P">Full HD 1080P</option>
  <option value="4K UHD">4K UHD</option>
</select></div>
<div class="fg"><label class="fl" style="display:flex;align-items:center;justify-content:space-between"><?= $t["rv_status"] ?? "الحالة" ?><label class="fc-switch" style="display:inline-flex"><input type="checkbox" name="is_active" value="1" checked><span class="fc-slider"></span></label></label></div>
<div class="fg"><label class="fl"><?= $t["rv_icon"] ?? "الأيقونة" ?></label><input type="text" name="logo_icon" class="fi" value="fas fa-tv"></div>
<div class="fg">
      <label class="fl"><?= $t["rv_logo_url"] ?? "رابط الشعار" ?></label>
      <div class="image-upload-row">
        <div style="flex:1">
          <input type="text" name="logo_url" id="addChLogo" class="fi" placeholder="https://example.com/logo.png" oninput="previewImage('addPrev',this.value)">
        </div>
        <label class="upload-btn">
          <i class="fas fa-upload"></i><?= $t["rv_upload_image"] ?? "رفع صورة" ?><input type="file" accept="image/png,image/jpeg,image/jpg,image/webp,image/gif" style="display:none" onchange="uploadChannelLogo(this,'addChLogo','addPrev','addLogoStatus')">
        </label>
      </div>
      <div id="addLogoStatus" style="font-size:.75rem;margin-top:4px"></div>
      <div class="image-preview" id="addPrev"><img src="" alt="<?= htmlspecialchars($t["rv_preview"] ?? "معاينة") ?>"></div>
    </div></div>
<div class="mfooter"><button type="button" class="btn btn-g" onclick="CM('addChM')"><?= $t["rv_cancel"] ?? "إلغاء" ?></button><button type="submit" name="add_channel" class="btn btn-p"><i class="fas fa-check"></i><?= $t["rv_add"] ?? "إضافة" ?></button></div></form></div></div>

<!-- EDIT CHANNEL MODAL -->
<div class="mbd" id="editChM"><div class="mbox w"><div class="mhd"><div class="mhd-title"><i class="fas fa-pen"></i><?= $t["rv_edit_channel"] ?? "تعديل القناة" ?></div><button class="mclose" onclick="CM('editChM')"><i class="fas fa-times"></i></button></div>
<form method="POST"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"><div class="mbody"><input type="hidden" name="channel_id" id="eChId">
<div class="fg fg-rel"><div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:7px"><label class="fl" style="margin:0"><?= $t["rv_channel_name"] ?? "اسم القناة" ?></label></div><input type="text" name="channel_name" id="eChName" class="fi" required></div>
<div class="fg"><label class="fl"><?= $t["rv_category"] ?? "القسم" ?></label><select name="category_id" id="eChCat" class="fs" required><option value=""><?= $t["rv_choose_dash"] ?? "— اختر —" ?></option><?php foreach($categories as $cat): ?><option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option><?php endforeach; ?></select></div>
<div class="fg"><label class="fl"><?= $t["rv_stream_url"] ?? "رابط البث" ?></label><input type="text" name="stream_url" id="eChUrl" class="fi" required></div>
<div class="fg"><label class="fl"><?= $t["rv_backup_url"] ?? "رابط احتياطي (Backup URL)" ?><span style="color:var(--t3);font-weight:400"><?= $t["rv_optional_dash"] ?? "— اختياري" ?></span></label><input type="text" name="backup_url" id="eChBackup" class="fi" placeholder="<?= htmlspecialchars($t["rv_ph_backup"] ?? "https://... رابط بديل عند تعطّل الرابط الأساسي") ?>"></div>
<div class="fg"><label class="fl"><?= $t["rv_quality"] ?? "الجودة" ?></label><select name="quality" id="eChQuality" class="fs">
  <option value="SD 480">SD 480</option>
  <option value="HD 720">HD 720</option>
  <option value="Full HD 1080P">Full HD 1080P</option>
  <option value="4K UHD">4K UHD</option>
</select></div>
<div class="fg"><label class="fl" style="display:flex;align-items:center;justify-content:space-between"><?= $t["rv_status"] ?? "الحالة" ?><label class="fc-switch" style="display:inline-flex"><input type="checkbox" name="is_active" id="eChActive" value="1" checked><span class="fc-slider"></span></label></label></div>
<div class="fg"><label class="fl"><?= $t["rv_icon"] ?? "الأيقونة" ?></label><input type="text" name="logo_icon" id="eChIcon" class="fi"></div>
<div class="fg">
      <label class="fl"><?= $t["rv_logo_url"] ?? "رابط الشعار" ?></label>
      <div class="image-upload-row">
        <div style="flex:1">
          <input type="text" name="logo_url" id="eChLogo" class="fi" placeholder="https://..." oninput="previewImage('editPrev',this.value)">
        </div>
        <label class="upload-btn">
          <i class="fas fa-upload"></i><?= $t["rv_upload_image"] ?? "رفع صورة" ?><input type="file" accept="image/png,image/jpeg,image/jpg,image/webp,image/gif" style="display:none" onchange="uploadChannelLogo(this,'eChLogo','editPrev','editLogoStatus')">
        </label>
      </div>
      <div id="editLogoStatus" style="font-size:.75rem;margin-top:4px"></div>
      <div class="image-preview" id="editPrev"><img src="" alt="<?= htmlspecialchars($t["rv_preview"] ?? "معاينة") ?>"></div>
    </div></div>
<div class="mfooter"><button type="button" class="btn btn-g" onclick="CM('editChM')"><?= $t["rv_cancel"] ?? "إلغاء" ?></button><button type="submit" name="edit_channel" class="btn btn-p"><i class="fas fa-check"></i><?= $t["rv_save"] ?? "حفظ" ?></button></div></form></div></div>

<!-- ADD SERIES MODAL -->
<div class="mbd" id="addSeriesM"><div class="mbox"><div class="mhd"><div class="mhd-title"><i class="fas fa-film"></i><?= $t["rv_new_series"] ?? "مسلسل / فيلم جديد" ?></div><button class="mclose" onclick="CM('addSeriesM')"><i class="fas fa-times"></i></button></div>
<div class="mbody"><div class="fg"><label class="fl"><i class="fas fa-globe" style="color:#4CC9F0"></i><?= $t["rv_search_source"] ?? "مصدر البحث" ?></label>
  <div class="source-tabs" id="addSrSourceTabs">
    <button type="button" class="source-tab active tmdb-active" onclick="switchSource('add','tmdb',this)"><i class="fas fa-film"></i> TMDB</button>
    <button type="button" class="source-tab" onclick="switchSource('add','anilist',this)"><i class="fas fa-dragon"></i> AniList</button>
    <button type="button" class="source-tab" onclick="switchSource('add','omdb',this)"><i class="fas fa-database"></i> OMDb</button>
  </div>
</div><div class="fg media-search-wrap"><label class="fl"><?= $t["rv_name"] ?? "الاسم" ?></label><div class="media-search-row"><input type="text" class="fi" id="srName" placeholder="<?= htmlspecialchars($t["rv_ph_search"] ?? "ابحث عن اسم الفيلم أو المسلسل...") ?>" oninput="mediaAutoSearch('add',this.value)" onkeydown="if(event.key==='Enter'){event.preventDefault();mediaSearch('add')}"><button type="button" class="btn btn-g bsm" onclick="mediaSearch('add')"><i class="fas fa-search"></i></button></div><div class="media-search-results" id="mediaRes_add"></div></div><div class="fg"><label class="fl"><?= $t["rv_category"] ?? "القسم" ?></label><select class="fs" id="srCat"><option value=""><?= $t["rv_choose_category"] ?? "— اختر القسم —" ?></option><?php foreach($categories as $cat): ?><option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option><?php endforeach; ?></select></div>
<div class="fg"><label class="fl"><?= $t["rv_poster"] ?? "صورة البوستر" ?></label><div style="display:flex;gap:8px;align-items:flex-start"><div style="flex:1"><input type="text" class="fi" id="srPoster" placeholder="https://example.com/poster.jpg" oninput="srPosterPreview('srPosterThumb',this.value)"></div><label style="flex-shrink:0;display:flex;align-items:center;gap:6px;padding:9px 13px;background:var(--s3);border:1px solid var(--br);border-radius:var(--r1);cursor:pointer;font-size:.78rem;font-weight:700;color:var(--t2);transition:all .15s;white-space:nowrap"><i class="fas fa-upload" style="color:var(--red)"></i><?= $t["rv_upload_image"] ?? "رفع صورة" ?><input type="file" accept="image/png,image/jpeg,image/jpg,image/webp" style="display:none" onchange="srPosterUpload(this,'srPoster','srPosterThumb','srPosterStatus')"></label></div><div id="srPosterStatus" style="margin-top:6px;font-size:.75rem"></div><div id="srPosterThumb" style="margin-top:8px;display:none"><img src="" style="width:80px;height:110px;object-fit:cover;border-radius:var(--r1);border:2px solid var(--br)"></div></div>
<div class="fg"><label class="fl"><?= $t["rv_desc_opt"] ?? "الوصف (اختياري)" ?></label><textarea class="fi" id="srDesc" rows="3" style="resize:vertical" placeholder="<?= htmlspecialchars($t["rv_ph_desc2"] ?? "وصف مختصر…") ?>"></textarea></div><div id="srAddAlert"></div></div>
<div class="mfooter"><button class="btn btn-g" onclick="CM('addSeriesM')"><?= $t["rv_cancel"] ?? "إلغاء" ?></button><button class="btn btn-p" onclick="srAdd()"><i class="fas fa-check"></i><?= $t["rv_add"] ?? "إضافة" ?></button></div></div></div>

<!-- EDIT SERIES MODAL -->
<div class="mbd" id="editSeriesM"><div class="mbox"><div class="mhd"><div class="mhd-title"><i class="fas fa-pen"></i><?= $t["rv_edit_shashety"] ?? "تعديل شاشتي" ?></div><button class="mclose" onclick="CM('editSeriesM')"><i class="fas fa-times"></i></button></div>
<div class="mbody"><input type="hidden" id="eSrId"><div class="fg"><label class="fl"><i class="fas fa-globe" style="color:#4CC9F0"></i><?= $t["rv_search_source"] ?? "مصدر البحث" ?></label>
  <div class="source-tabs" id="editSrSourceTabs">
    <button type="button" class="source-tab active tmdb-active" onclick="switchSource('edit','tmdb',this)"><i class="fas fa-film"></i> TMDB</button>
    <button type="button" class="source-tab" onclick="switchSource('edit','anilist',this)"><i class="fas fa-dragon"></i> AniList</button>
    <button type="button" class="source-tab" onclick="switchSource('edit','omdb',this)"><i class="fas fa-database"></i> OMDb</button>
  </div>
</div><div class="fg media-search-wrap"><label class="fl"><?= $t["rv_name"] ?? "الاسم" ?></label><div class="media-search-row"><input type="text" class="fi" id="eSrName"  oninput="mediaAutoSearch('edit',this.value)" onkeydown="if(event.key==='Enter'){event.preventDefault();mediaSearch('edit')}"><button type="button" class="btn btn-g bsm" onclick="mediaSearch('edit')"><i class="fas fa-search"></i></button></div><div class="media-search-results" id="mediaRes_edit"></div></div><div class="fg"><label class="fl"><?= $t["rv_category"] ?? "القسم" ?></label><select class="fs" id="eSrCat"><option value=""><?= $t["rv_choose_dash"] ?? "— اختر —" ?></option><?php foreach($categories as $cat): ?><option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option><?php endforeach; ?></select></div>
<div class="fg"><label class="fl"><?= $t["rv_poster"] ?? "صورة البوستر" ?></label><div style="display:flex;gap:8px;align-items:flex-start"><div style="flex:1"><input type="text" class="fi" id="eSrPoster" placeholder="https://..." oninput="srPosterPreview('eSrPosterThumb',this.value)"></div><label style="flex-shrink:0;display:flex;align-items:center;gap:6px;padding:9px 13px;background:var(--s3);border:1px solid var(--br);border-radius:var(--r1);cursor:pointer;font-size:.78rem;font-weight:700;color:var(--t2);transition:all .15s;white-space:nowrap"><i class="fas fa-upload" style="color:var(--red)"></i><?= $t["rv_upload_image"] ?? "رفع صورة" ?><input type="file" accept="image/png,image/jpeg,image/jpg,image/webp" style="display:none" onchange="srPosterUpload(this,'eSrPoster','eSrPosterThumb','eSrPosterStatus')"></label></div><div id="eSrPosterStatus" style="margin-top:6px;font-size:.75rem"></div><div id="eSrPosterThumb" style="margin-top:8px;display:none"><img src="" style="width:80px;height:110px;object-fit:cover;border-radius:var(--r1);border:2px solid var(--br)"></div></div>
<div class="fg"><label class="fl"><?= $t["rv_description"] ?? "الوصف" ?></label><textarea class="fi" id="eSrDesc" rows="3" style="resize:vertical"></textarea></div><div id="eSrAlert"></div></div>
<div class="mfooter"><button class="btn btn-g" onclick="CM('editSeriesM')"><?= $t["rv_cancel"] ?? "إلغاء" ?></button><button class="btn btn-p" onclick="srEditSave()"><i class="fas fa-check"></i><?= $t["rv_save"] ?? "حفظ" ?></button></div></div></div>

<!-- ADD EPISODE MODAL -->
<div class="mbd" id="addEpM"><div class="mbox w"><div class="mhd"><div class="mhd-title"><i class="fas fa-plus"></i><?= $t["rv_add_episode"] ?? "إضافة فيديو/حلقة" ?></div><button class="mclose" onclick="CM('addEpM')"><i class="fas fa-times"></i></button></div>
<div class="mbody"><div class="row2"><div class="fg"><label class="fl"><?= $t["rv_episode_num"] ?? "رقم الحلقة" ?></label><input type="number" class="fi" id="epNum" value="1" min="1"></div><div class="fg"><label class="fl"><?= $t["rv_title"] ?? "العنوان" ?></label><input type="text" class="fi" id="epTitle" placeholder="<?= htmlspecialchars($t["rv_ph_ep"] ?? "الحلقة 1 أو اسم الفيلم") ?>"></div></div>
<div class="etabs"><button class="etab on" onclick="etab('url')"><?= $t["rv_direct_link"] ?? "رابط مباشر" ?></button><button class="etab" onclick="etab('file')"><?= $t["rv_upload_file"] ?? "رفع ملف" ?></button></div>
<div id="etab-url"><div class="fg"><label class="fl"><?= $t["rv_video_url"] ?? "رابط الفيديو" ?></label><input type="text" class="fi" id="epUrl" placeholder="https://..."></div></div>
<div id="etab-file" style="display:none"><div class="fg"><label class="fl"><?= $t["rv_upload_video"] ?? "رفع ملف الفيديو" ?></label><div class="uz" style="padding:22px"><input type="file" accept="video/*" onchange="epFileUpload(this)"><i class="fas fa-video"></i><h3>اختر ملف الفيديو</h3><p>MP4 · MKV · AVI</p></div><div id="epFileChip" style="display:none;margin-top:8px;padding:9px 12px;background:rgba(0,208,132,.07);border:1px solid rgba(0,208,132,.22);border-radius:var(--r1);font-size:.8rem;align-items:center;gap:8px"><i class="fas fa-check-circle" style="color:#00D084"></i><span id="epFileChipName">—</span></div><div id="epFileProgress" style="display:none;margin-top:8px"><div class="pw"><div class="pb" id="epFilePBar"></div></div></div><input type="hidden" id="epUploadedUrl"></div></div>
<div class="fg"><label class="fl">رابط الترجمة (اختياري)</label><input type="text" class="fi" id="epSubUrl" placeholder="https://... (SRT أو VTT)"></div>
<div class="orsep">أو</div>
<div class="fg"><label class="fl">رفع ملف ترجمة</label><div class="uz" style="padding:18px"><input type="file" accept=".srt,.ass,.vtt,.ssa" onchange="epSubUpload(this)"><i class="fas fa-file-alt"></i><h3>اختر ملف الترجمة</h3><p>SRT · VTT · ASS</p></div><div id="epSubChip" style="display:none;margin-top:8px;padding:9px 12px;background:rgba(76,201,240,.07);border:1px solid rgba(76,201,240,.22);border-radius:var(--r1);font-size:.8rem;align-items:center;gap:8px"><i class="fas fa-closed-captioning" style="color:#4CC9F0"></i><span id="epSubChipName">—</span></div></div>
<div class="fg"><label class="fl">المدة (اختياري)</label><input type="text" class="fi" id="epDur" placeholder="45:00"></div><div id="addEpAlert"></div></div>
<div class="mfooter"><button class="btn btn-g" onclick="CM('addEpM')"><?= $t["rv_cancel"] ?? "إلغاء" ?></button><button class="btn btn-p" onclick="epAdd()"><i class="fas fa-check"></i><?= $t["rv_add"] ?? "إضافة" ?></button></div></div></div>

<!-- OS SEARCH MODAL FOR EDIT -->
<div class="mbd" id="eEpOsM" style="z-index:3000;">
  <div class="mbox w">
    <div class="mhd"><div class="mhd-title"><i class="fas fa-closed-captioning" style="color:#4CC9F0"></i> جلب ترجمة من OpenSubtitles</div><button class="mclose" onclick="CM('eEpOsM')"><i class="fas fa-times"></i></button></div>
    <div class="mbody">
      <div class="srow">
        <div class="sinp"><i class="fas fa-film"></i><input type="text" id="eEpOsQ" placeholder="اسم الفيلم..." onkeydown="if(event.key==='Enter')eEpOsSearch()"></div>
        <select class="lsel" id="eEpOsLang" style="width:auto;"><option value="ar">🇸🇦 عربي</option><option value="en">🇬🇧 English</option></select>
        <button class="btn btn-p" onclick="eEpOsSearch()" id="eEpOsSearchBtn"><i class="fas fa-search"></i> بحث</button>
      </div>
      <div id="eEpOsAl" style="margin-top:8px;font-size:0.8rem;"></div>
      <div class="sub-rl" id="eEpOsRes" style="margin-top:10px; max-height:250px; overflow-y:auto; border:1px solid var(--br); border-radius:var(--r1);"></div>
    </div>
  </div>
</div>

<!-- EDIT EPISODE MODAL -->
<div class="mbd" id="editEpM"><div class="mbox w"><div class="mhd"><div class="mhd-title"><i class="fas fa-pen"></i>تعديل ونقل الفيديو</div><button class="mclose" onclick="CM('editEpM')"><i class="fas fa-times"></i></button></div>
<div class="mbody">
    <input type="hidden" id="eEpId">
    
    <div style="background:rgba(245,166,35,.07);border:1px solid rgba(245,166,35,.18);border-radius:var(--r1);padding:11px 14px;margin-bottom:16px;">
       <label class="fl" style="color:var(--gold)"><i class="fas fa-folder-open"></i> المجلد الوجهة بداخل إدارة شاشتي</label>
       <select class="fs" id="eEpSeriesId"></select>
    </div>

    <div class="row2">
        <div class="fg"><label class="fl"><?= $t["rv_episode_num"] ?? "رقم الحلقة" ?></label><input type="number" class="fi" id="eEpNum" min="1"></div>
        <div class="fg"><label class="fl"><?= $t["rv_title"] ?? "العنوان" ?></label><input type="text" class="fi" id="eEpTitle"></div>
    </div>
    <div class="fg"><label class="fl"><?= $t["rv_video_url"] ?? "رابط الفيديو" ?></label><input type="text" class="fi" id="eEpUrl"></div>
    <div class="fg">
    <label class="fl">رابط الترجمة (أضف يدوياً أو اجلب تلقائياً)</label>
    <div style="display:flex; gap:8px; align-items:center;">
        <input type="text" class="fi" id="eEpSub" placeholder="https://..." style="flex:1;">
        <label class="btn btn-s bsm" style="cursor:pointer; margin:0; padding:8px 12px; white-space:nowrap;">
            <i class="fas fa-upload"></i><?= $t["rv_upload_file"] ?? "رفع ملف" ?><input type="file" accept=".srt,.ass,.vtt,.ssa" style="display:none;" onchange="eEpSubUpload(this)">
        </label>
        <button type="button" class="btn btn-b bsm" style="padding:8px 12px; white-space:nowrap;" onclick="eEpOpenOS()">
            <i class="fas fa-search"></i> OpenSubtitles
        </button>
    </div>
    <div id="eEpSubStatus" style="margin-top:5px; font-size:0.75rem;"></div>
</div>
    <div class="fg"><label class="fl">المدة</label><input type="text" class="fi" id="eEpDur" placeholder="45:00"></div>
    <div id="eEpAlert"></div>
</div>
<div class="mfooter"><button class="btn btn-g" onclick="CM('editEpM')"><?= $t["rv_cancel"] ?? "إلغاء" ?></button><button class="btn btn-p" onclick="epEditSave()"><i class="fas fa-save"></i>حفظ التعديلات</button></div></div></div>

<!-- BULK UPLOAD MODAL -->
<div class="mbd" id="bulkM"><div class="mbox w"><div class="mhd"><div class="mhd-title"><i class="fas fa-folder-open"></i>رفع مجلد مسلسل كامل</div><button class="mclose" onclick="CM('bulkM')"><i class="fas fa-times"></i></button></div>
<div class="mbody"><div style="background:rgba(76,201,240,.06);border:1px solid rgba(76,201,240,.18);border-radius:var(--r1);padding:11px 14px;margin-bottom:16px;font-size:.8rem;color:var(--t2)"><i class="fas fa-info-circle" style="color:#4CC9F0;margin-left:5px"></i>اختر جميع ملفات حلقات المسلسل دفعة واحدة.</div>
<div class="fg"><label class="fl">اختر ملفات الحلقات (متعددة)</label><div class="uz" id="bulkDZ"><input type="file" id="bulkFiles" accept="video/*" multiple onchange="bulkPreview(this.files)"><i class="fas fa-folder-open"></i><h3>اختر ملفات الحلقات</h3><p>اضغط لاختيار أكثر من ملف</p></div></div>
<div id="bulkPreviewList" style="display:none;margin-bottom:14px"><div style="font-size:.78rem;font-weight:700;color:var(--t2);margin-bottom:8px;display:flex;align-items:center;justify-content:space-between"><span id="bulkPreviewTitle"></span><span id="bulkTotalSize" style="color:var(--t3)"></span></div><div id="bulkItems" style="max-height:280px;overflow-y:auto;display:flex;flex-direction:column;gap:5px"></div></div>
<div id="bulkProgress" style="display:none;margin-bottom:14px"><div style="display:flex;justify-content:space-between;font-size:.78rem;margin-bottom:6px"><span id="bulkProgLabel" style="color:var(--t2)">رفع الحلقات…</span><span id="bulkProgPct" style="color:var(--t3)">0%</span></div><div class="pw"><div class="pb" id="bulkPBar"></div></div><div id="bulkCurFile" style="font-size:.72rem;color:var(--t3);margin-top:5px"></div></div>
<div id="bulkResult" style="display:none"></div><div id="bulkAlert"></div></div>
<div class="mfooter"><button class="btn btn-g" onclick="CM('bulkM')"><?= $t["rv_close"] ?? "إغلاق" ?></button><button class="btn btn-p" id="bulkStartBtn" style="display:none" onclick="bulkUpload()"><i class="fas fa-upload"></i>ابدأ الرفع</button></div></div></div>

<!-- VM SAVE MODAL -->
<div class="mbd" id="vmSaveM"><div class="mbox"><div class="mhd"><div class="mhd-title"><i class="fas fa-save"></i>حفظ الفيديو في شاشتي</div><button class="mclose" onclick="CM('vmSaveM')"><i class="fas fa-times"></i></button></div>
<div class="mbody">
  <p id="vmSaveFile" style="font-size:.78rem;color:var(--t3);margin-bottom:8px"></p>
  <div id="vmSaveSub" style="display:none;font-size:.78rem;color:#00D084;margin-bottom:16px;background:rgba(0,208,132,.07);padding:8px 12px;border-radius:4px;border:1px solid rgba(0,208,132,.2)"><i class="fas fa-check-circle"></i> تم إرفاق ملف ترجمة بنجاح</div>
  <input type="hidden" id="vmSaveSubUrl">
  
  <div class="fg" style="background:rgba(245,166,35,.07);padding:14px;border:1px dashed rgba(245,166,35,.3);border-radius:var(--r1)">
     <label class="fl" style="color:var(--gold)"><i class="fas fa-folder"></i> إرسال هذا الملف إلى:</label>
     <select class="fs" id="vmSaveTargetSeries" onchange="vToggleSeriesFields(this.value, 'manage')"></select>
  </div>

  <div class="fg"><label class="fl" id="vmNameLabel">الاسم أو العنوان</label><input type="text" class="fi" id="vmSaveTitle" placeholder="اسم الفيلم / أو عنوان الحلقة المضافة"></div>
  <div class="fg" id="vmCatDiv"><label class="fl">قسم العمل (مطلوب)</label><select class="fs" id="vmSaveCat"><option value=""><?= $t["rv_choose_category"] ?? "— اختر القسم —" ?></option><?php foreach($categories as $cat): ?><option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option><?php endforeach; ?></select></div>
  <div id="vmSaveAlert"></div>
</div>
<div class="mfooter"><button class="btn btn-g" onclick="CM('vmSaveM')"><?= $t["rv_cancel"] ?? "إلغاء" ?></button><button class="btn btn-p" onclick="vmDoSave()"><i class="fas fa-check"></i>حفظ في شاشتي</button></div></div></div>

<!-- VM MOVE MODAL -->
<div class="mbd" id="vmMoveM"><div class="mbox"><div class="mhd"><div class="mhd-title"><i class="fas fa-folder-open"></i>نقل مسار الفيديو</div><button class="mclose" onclick="CM('vmMoveM')"><i class="fas fa-times"></i></button></div>
<div class="mbody">
  <div class="info-b" style="margin-top:0;margin-bottom:16px"><div class="info-b-title"><i class="fas fa-info-circle"></i> تنبيه هام</div><p style="font-size:.8rem;color:var(--t3)">أنت تقوم الآن بإعادة تعيين هذا الفيديو (التحكم بالملف في الخادم والواجهة الأمامية في نفس الوقت). نقل الملف لن يكسر الروابط!</p></div>
  <p id="vmMoveFile" style="font-size:.8rem;color:var(--t1);margin-bottom:16px;font-weight:bold"></p>
  <div class="fg">
    <label class="fl">إلى أين تريد نقله؟</label>
    <select class="fs" id="vmMoveTarget">
        <!-- ستتم تعبئة الخيارات من الـ JS بالأسفل -->
    </select>
  </div>
  <div id="vmMoveAlert"></div>
</div>
<div class="mfooter"><button class="btn btn-g" onclick="CM('vmMoveM')"><?= $t["rv_cancel"] ?? "إلغاء" ?></button><button class="btn btn-p" onclick="vmDoMove()"><i class="fas fa-exchange-alt"></i>نقل الفيديو الآن</button></div></div></div>

<!-- TMDB INFO MODAL -->
<div class="mbd" id="tmdbInfoM" style="z-index: 2000;">
  <div class="mbox w">
    <div class="mhd">
      <div class="mhd-title"><i class="fas fa-info-circle" style="color:#4CC9F0"></i> تفاصيل العمل</div>
      <button class="mclose" onclick="CM('tmdbInfoM')"><i class="fas fa-times"></i></button>
    </div>
    <div class="mbody" id="tmdbInfoBody"></div>
  </div>
</div>


<!-- ADD USER MODAL -->
<div class="mbd" id="addUserM"><div class="mbox w"><div class="mhd"><div class="mhd-title"><i class="fas fa-user-plus"></i>مستخدم جديد</div><button class="mclose" onclick="CM('addUserM')"><i class="fas fa-times"></i></button></div>
<div class="mbody">
  <div class="row2">
    <div class="fg"><label class="fl">اسم المستخدم (للدخول)</label><input type="text" class="fi" id="auUsername" placeholder="username" style="direction:ltr"></div>
    <div class="fg"><label class="fl">الاسم المعروض</label><input type="text" class="fi" id="auDisplay" placeholder="أحمد محمد"></div>
  </div>
  <div class="row2">
    <div class="fg"><label class="fl">كلمة المرور</label><input type="password" class="fi" id="auPassword" placeholder="••••••••"></div>
    <div class="fg"><label class="fl">الدور / الصلاحية</label>
      <select class="fs" id="auRole" onchange="auRoleChange()">
        <option value="normal">عادي (رفع فقط)</option>
        <option value="custom">مخصص (اختر الأقسام)</option>
        <option value="super">مشرف (كل شيء عدا إدارة المدراء)</option>
        <option value="administrator">مدير عام (تحكم كامل)</option>
      </select>
    </div>
  </div>
  <div id="auPermsWrap" style="display:none">
    <label class="fl" style="margin-top:6px"><i class="fas fa-shield-alt" style="color:#00D084"></i> الأقسام المسموحة</label>
    <div class="perm-grid" id="auPermsGrid"></div>
  </div>
  <div id="auAlert" style="margin-top:12px"></div>
</div>
<div class="mfooter"><button class="btn btn-g" onclick="CM('addUserM')"><?= $t["rv_cancel"] ?? "إلغاء" ?></button><button class="btn btn-p" onclick="addUser()"><i class="fas fa-check"></i>إنشاء المستخدم</button></div></div></div>

<!-- EDIT USER MODAL -->
<div class="mbd" id="editUserM"><div class="mbox w"><div class="mhd"><div class="mhd-title"><i class="fas fa-user-edit"></i>تعديل المستخدم</div><button class="mclose" onclick="CM('editUserM')"><i class="fas fa-times"></i></button></div>
<div class="mbody">
  <input type="hidden" id="euId">
  <div class="row2">
    <div class="fg"><label class="fl">اسم المستخدم</label><input type="text" class="fi" id="euUsername" disabled style="direction:ltr;opacity:.6"></div>
    <div class="fg"><label class="fl">الاسم المعروض</label><input type="text" class="fi" id="euDisplay"></div>
  </div>
  <div class="row2">
    <div class="fg"><label class="fl">كلمة مرور جديدة <small style="color:var(--t3)">(اتركها فارغة للإبقاء)</small></label><input type="password" class="fi" id="euPassword" placeholder="••••••••"></div>
    <div class="fg"><label class="fl">الدور / الصلاحية</label>
      <select class="fs" id="euRole" onchange="euRoleChange()">
        <option value="normal">عادي (رفع فقط)</option>
        <option value="custom">مخصص (اختر الأقسام)</option>
        <option value="super">مشرف</option>
        <option value="administrator">مدير عام</option>
      </select>
    </div>
  </div>
  <div class="fg">
    <label class="fl"><?= $t["rv_status"] ?? "الحالة" ?></label>
    <select class="fs" id="euActive">
      <option value="1">نشط ✅</option>
      <option value="0">معطّل ⛔</option>
    </select>
  </div>
  <div id="euPermsWrap" style="display:none">
    <label class="fl" style="margin-top:6px"><i class="fas fa-shield-alt" style="color:#00D084"></i> الأقسام المسموحة</label>
    <div class="perm-grid" id="euPermsGrid"></div>
  </div>
  <div id="euAlert" style="margin-top:12px"></div>
</div>
<div class="mfooter"><button class="btn btn-g" onclick="CM('editUserM')"><?= $t["rv_cancel"] ?? "إلغاء" ?></button><button class="btn btn-p" onclick="editUser()"><i class="fas fa-save"></i>حفظ التعديلات</button></div></div></div>

<!-- ══ THEME PANEL ══ -->
<button class="theme-fab" id="themeFabBtn" onclick="toggleThemePanel()" title="تغيير الثيم" style="z-index:500">
  <i class="fas fa-palette"></i>
</button>

<div class="theme-panel" id="themePanel">
  <div class="theme-panel-hd">
    <div class="theme-panel-title"><i class="fas fa-palette"></i> 🎨 مركز الثيمات</div>
    <button class="theme-panel-close" onclick="toggleThemePanel()"><i class="fas fa-times"></i></button>
  </div>
  <div class="theme-panel-body">

    <div class="theme-section-title">✨ الثيمات الجاهزة</div>
    <div class="theme-presets">

      <div class="theme-card" id="thc-default" onclick="applyThemePreset('default')">
        <div class="theme-card-preview" style="background:linear-gradient(135deg,#111,#1a1a1a);color:#E50914;border:1px solid rgba(229,9,20,.3)">SHASHITY</div>
        <div class="theme-card-name">الافتراضي</div>
        <div class="theme-card-desc">الثيم الأصلي للوحة</div>
      </div>

      <div class="theme-card" id="thc-ultrachromic" onclick="applyThemePreset('ultrachromic')">
        <div class="theme-card-preview" style="background:linear-gradient(135deg,#0d0221,#190c38);color:#b847ff;border:1px solid rgba(184,71,255,.4)">🌌 Ultra</div>
        <div class="theme-card-name">Ultrachromic</div>
        <div class="theme-card-desc">بنفسجي متدرج عصري</div>
      </div>

      <div class="theme-card" id="thc-jellyflix" onclick="applyThemePreset('jellyflix')">
        <div class="theme-card-preview" style="background:linear-gradient(135deg,#141414,#1c1c1c);color:#e50914;border:1px solid rgba(229,9,20,.5)">🎬 Flix</div>
        <div class="theme-card-name">JellyFlix</div>
        <div class="theme-card-desc">نمط Netflix احترافي</div>
      </div>

      <div class="theme-card" id="thc-dark" onclick="applyThemePreset('dark')">
        <div class="theme-card-preview" style="background:linear-gradient(135deg,#0d1117,#161b22);color:#58a6ff;border:1px solid rgba(88,166,255,.3)">🌑 Dark</div>
        <div class="theme-card-name">Dark Enhanced</div>
        <div class="theme-card-desc">داكن محسّن للعيون</div>
      </div>

      <div class="theme-card" id="thc-neon" onclick="applyThemePreset('neon')">
        <div class="theme-card-preview" style="background:linear-gradient(135deg,#0a0a1a,#12122a);color:#00ff88;border:1px solid rgba(0,255,136,.4)">🎮 Neon</div>
        <div class="theme-card-name">Neon Cyberpunk</div>
        <div class="theme-card-desc">جيمينج نيون ملوّن</div>
      </div>

      <div class="theme-card" id="thc-minimal" onclick="applyThemePreset('minimal')">
        <div class="theme-card-preview" style="background:linear-gradient(135deg,#fafafa,#f0f0f0);color:#222;border:1px solid rgba(0,0,0,.1)">🧊 Min</div>
        <div class="theme-card-name">Minimal Clean</div>
        <div class="theme-card-desc">بسيط صافي فاتح</div>
      </div>

      <div class="theme-card" id="thc-midnight" onclick="applyThemePreset('midnight')">
        <div class="theme-card-preview" style="background:linear-gradient(135deg,#0b1437,#1a2456);color:#7aa2ff;border:1px solid rgba(122,162,255,.4)">🌃 Midnight</div>
        <div class="theme-card-name">Midnight Blue</div>
        <div class="theme-card-desc">أزرق ليلي ملكي فاخر</div>
      </div>

      <div class="theme-card" id="thc-emerald" onclick="applyThemePreset('emerald')">
        <div class="theme-card-preview" style="background:linear-gradient(135deg,#04130d,#0a2a1c);color:#10d98a;border:1px solid rgba(16,217,138,.4)">💎 Emerald</div>
        <div class="theme-card-name">Emerald Luxe</div>
        <div class="theme-card-desc">زمردي أنيق ودافئ</div>
      </div>

      <div class="theme-card" id="thc-sunset" onclick="applyThemePreset('sunset')">
        <div class="theme-card-preview" style="background:linear-gradient(135deg,#1a0a14,#2e0f1f);color:#ff7849;border:1px solid rgba(255,120,73,.4)">🌅 Sunset</div>
        <div class="theme-card-name">Sunset Glow</div>
        <div class="theme-card-desc">غروب برتقالي وردي</div>
      </div>

      <div class="theme-card" id="thc-royalgold" onclick="applyThemePreset('royalgold')">
        <div class="theme-card-preview" style="background:linear-gradient(135deg,#0f0c00,#1f1a05);color:#f5c542;border:1px solid rgba(245,197,66,.45)">👑 Gold</div>
        <div class="theme-card-name">Royal Gold</div>
        <div class="theme-card-desc">ذهبي ملكي على أسود</div>
      </div>

      <div class="theme-card" id="thc-crimson" onclick="applyThemePreset('crimson')">
        <div class="theme-card-preview" style="background:linear-gradient(135deg,#16050a,#2c0a12);color:#ff3355;border:1px solid rgba(255,51,85,.45)">🔥 Crimson</div>
        <div class="theme-card-name">Crimson Noir</div>
        <div class="theme-card-desc">أحمر قرمزي درامي</div>
      </div>

      <div class="theme-card" id="thc-arctic" onclick="applyThemePreset('arctic')">
        <div class="theme-card-preview" style="background:linear-gradient(135deg,#eef4f8,#dde8f0);color:#0e7490;border:1px solid rgba(14,116,144,.25)">❄️ Arctic</div>
        <div class="theme-card-name">Arctic Frost</div>
        <div class="theme-card-desc">فاتح بارد أنيق</div>
      </div>

      <div class="theme-card" id="thc-win11" onclick="applyThemePreset('win11')">
        <div class="theme-card-preview" style="background:linear-gradient(135deg,#2b2b2b,#3a4358);color:#4cc2ff;border:1px solid rgba(76,194,255,.35)">🪟 Win 11</div>
        <div class="theme-card-name">Windows 11</div>
        <div class="theme-card-desc">Mica زجاجي رمادي</div>
      </div>

      <div class="theme-card" id="thc-sierra" onclick="applyThemePreset('sierra')">
        <div class="theme-card-preview" style="background:linear-gradient(160deg,#6ea8dc,#b79ccb 55%,#f3c39b);color:#fff;border:1px solid rgba(255,255,255,.5);text-shadow:0 1px 4px rgba(0,0,0,.3)">🍎 Sierra</div>
        <div class="theme-card-name">macOS Sierra</div>
        <div class="theme-card-desc">فاتح شفّاف أنيق</div>
      </div>

      <div class="theme-card" id="thc-ps5" onclick="applyThemePreset('ps5')">
        <div class="theme-card-preview" style="background:linear-gradient(135deg,#0a0e17,#1b2b52);color:#2e6ff2;border:1px solid rgba(46,111,242,.45);box-shadow:inset 0 0 18px rgba(46,111,242,.25)">🎮 PS5</div>
        <div class="theme-card-name">PlayStation 5</div>
        <div class="theme-card-desc">أزرق متوهّج حاد</div>
      </div>

      <div class="theme-card" id="thc-glass" onclick="applyThemePreset('glass')">
        <div class="theme-card-preview" style="background:linear-gradient(135deg,#0d121d,#16203a);color:#6ea8fe;border:1px solid rgba(110,168,254,.35);box-shadow:inset 0 1px 0 rgba(255,255,255,.08)">🧊 Glass</div>
        <div class="theme-card-name">Glass Blur</div>
        <div class="theme-card-desc">زجاجي شفاف واضح</div>
      </div>

    </div>

    <div class="theme-section-title" style="margin-top:16px">🖌️ CSS مخصص</div>
    <div class="custom-css-wrap">
      <textarea class="custom-css-textarea" id="customCssInput" placeholder=":root {
  --red: #E50914;
  --s0: #0a0a0a;
  --s1: #111;
}

/* أضف CSS هنا */
.sidebar { /* تعديل الشريط الجانبي */ }"></textarea>
    </div>
    <div id="cssApplyStatus" style="margin-top:8px;font-size:.75rem"></div>

  </div>
  <div class="theme-panel-footer">
    <button class="theme-reset-btn" onclick="resetTheme()" style="flex:1;justify-content:center"><i class="fas fa-undo"></i><?= $t["rv_reset"] ?? "إعادة الضبط" ?></button>
    <button id="applyThemeBtn" class="btn btn-p" onclick="applyCSSFromTextarea()" style="flex:2;justify-content:center;padding:9px"><i class="fas fa-check"></i><?= $t["rv_apply_save"] ?? "تطبيق و حفظ" ?></button>
  </div>
</div>


