<section id="vupload" class="sec">
  <div class="shdr"><h1 class="stitle"><?= $t["upload_movie"] ?? "رفع <span>الأفلام</span>" ?></h1></div>
  <div class="vsteps"><div class="vs act" id="vs1"><div class="vs-n">1</div><?= $t["step_1"] ?? "رفع الفيديو" ?></div><div class="vs" id="vs2"><div class="vs-n">2</div><?= $t["step_2"] ?? "الترجمة" ?></div><div class="vs" id="vs3"><div class="vs-n">3</div><?= $t["step_3"] ?? "الحفظ" ?></div></div>
  <div class="vp act" id="vp1">
    <div class="vc"><div class="vchd"><div class="vchd-title"><i class="fas fa-cloud-upload-alt"></i><?= $t["choose_video_file"] ?? "اختر ملف الفيديو" ?></div></div>
      <div class="vcbody">
        
        <div id="vtab-file">
          <div class="uz" id="vidDZ"><input type="file" id="vidFileIn" accept="video/*" onchange="vidUpload(this)"><i class="fas fa-film"></i><h3><?= $t["drag_drop_video"] ?? "اسحب الفيديو هنا أو انقر للاختيار" ?></h3><p>MP4 · MKV · AVI · MOV · WebM</p></div>
        </div>

        <div id="vidProg" style="display:none;margin-top:12px">
            <div style="display:flex;align-items:center;gap:9px;margin-bottom:6px">
                <span class="sp" id="vidProgSp"></span>
                <span id="vidPLabel" style="font-size:.8rem;color:var(--t2);flex:1"><?= $t["uploading"] ?? "جارٍ الرفع…" ?></span>
                <span id="vidPct" style="font-size:.75rem;color:var(--t3)">0%</span>
                <button class="btn btn-g bsm" id="cancelDlBtn" style="display:none;padding:2px 8px;font-size:0.7rem;color:#ff6b6b;border-color:rgba(229,9,20,.3)"><i class="fas fa-times"></i> <?= $t["cancel"] ?? "إلغاء" ?></button>
            </div>
            <div class="pw"><div class="pb" id="vidPBar"></div></div>
        </div>
        <div class="chip" id="vidChip"><div style="width:36px;height:36px;background:rgba(229,9,20,.1);border-radius:7px;display:flex;align-items:center;justify-content:center;color:var(--red);flex-shrink:0"><i class="fas fa-film"></i></div><div style="flex:1;min-width:0"><div id="vidChipName" style="font-weight:700;font-size:.855rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">—</div><div id="vidChipSize" style="font-size:.74rem;color:var(--t3)">—</div></div><div onclick="vidReset()" style="width:26px;height:26px;background:var(--s3);border:1px solid var(--br);border-radius:6px;cursor:pointer;display:flex;align-items:center;justify-content:center;color:var(--t3);flex-shrink:0"><i class="fas fa-times"></i></div></div>
        <div id="v1alert" style="margin-top:10px"></div>
        <div style="margin-top:8px;text-align:left"><button class="btn btn-g bsm" onclick="vidDebug()" style="font-size:.72rem;opacity:.6"><i class="fas fa-bug"></i> <?= $t["check_server_settings"] ?? "فحص إعدادات الخادم" ?></button></div>
        <div id="v1debug" style="display:none;margin-top:8px;background:var(--s2);border:1px solid var(--br);border-radius:var(--r1);padding:12px;font-size:.75rem;font-family:monospace;color:var(--t3)"></div>
      </div>
    </div>
    <div class="vnavb"><span></span><button class="btn btn-p" id="vNext1" disabled onclick="vidGo(2)"><?= $t["next_subtitle"] ?? "التالي: الترجمة" ?> <i class="fas fa-arrow-left"></i></button></div>
  </div>
  <div class="vp" id="vp2">
    <div class="vc"><div class="vchd"><div class="vchd-title"><i class="fas fa-closed-captioning"></i><?= $t["subtitle_options"] ?? "خيارات الترجمة" ?></div></div>
      <div class="vcbody">
        <div class="sub-opts">
          <div class="so sel" id="so-none" onclick="vidSubOpt('none')"><div class="so-ic">🎬</div><div class="so-lbl"><?= $t["no_subtitle"] ?? "بدون ترجمة" ?></div><div class="so-desc"><?= $t["save_without_subtitle"] ?? "حفظ بدون ترجمة" ?></div></div>
          <div class="so" id="so-search" onclick="vidSubOpt('search')"><div class="so-ic">🔍</div><div class="so-lbl"><?= $t["search_opensubtitles"] ?? "بحث OpenSubtitles" ?></div><div class="so-desc"><?= $t["search_by_name"] ?? "ابحث بالاسم" ?></div></div>
          <div class="so" id="so-upload" onclick="vidSubOpt('upload')"><div class="so-ic">📁</div><div class="so-lbl"><?= $t["upload_subtitle"] ?? "رفع ترجمة" ?></div><div class="so-desc"><?= $t["srt_ass_vtt"] ?? "SRT · ASS · VTT" ?></div></div>
        </div>
      </div>
    </div>
    <div class="vc" id="osCard" style="display:none">
      <div class="vchd"><div class="vchd-title"><i class="fas fa-search"></i>OpenSubtitles</div></div>
      <div class="vcbody">
        <div id="osNL" style="display:<?php echo $os_logged?'none':'block'; ?>">
          <div class="os-info"><i class="fas fa-key" style="color:#4CC9F0;margin-left:5px"></i><?= $t["auto_login_api"] ?? "يتم سحب البيانات من قسم (إعدادات API) لتسجيل الدخول التلقائي." ?></div>
          <div class="row2">
            <div><label class="fl"><?= $t["username"] ?? "اسم المستخدم" ?></label><input type="text" class="fi" id="osU" placeholder="username" value="<?php echo htmlspecialchars($settings['os_username'] ?? ''); ?>"></div>
            <div><label class="fl"><?= $t["password"] ?? "كلمة المرور" ?></label><input type="password" class="fi" id="osP" placeholder="••••••••" value="<?php echo htmlspecialchars($settings['os_password'] ?? ''); ?>" onkeydown="if(event.key==='Enter')osLogin()"></div>
          </div>
          <div class="fg" style="margin-top:12px"><label class="fl"><?= $t["api_key"] ?? "مفتاح API" ?></label><input type="text" class="fi" id="osApiKey" placeholder="aBcDeF..." value="<?php echo htmlspecialchars($settings['os_api_key'] ?? ''); ?>"></div>
          <button class="btn btn-p" style="width:100%;justify-content:center;padding:11px" onclick="osLogin()" id="osLBtn"><i class="fas fa-sign-in-alt"></i><?= $t["login"] ?? "تسجيل الدخول" ?></button>
          <div id="osLAlert" style="margin-top:8px"></div>
        </div>
        <div id="osL" style="display:<?php echo $os_logged?'flex':'none'; ?>;align-items:center;gap:9px;margin-bottom:12px;padding:10px 13px;background:rgba(0,208,132,.07);border:1px solid rgba(0,208,132,.22);border-radius:var(--r1)">
          <i class="fas fa-check-circle" style="color:#00D084;font-size:1.1rem"></i>
          <span style="flex:1;font-size:.83rem"><?= $t["logged_in"] ?? "مسجّل:" ?> <strong id="osLUser"><?php echo htmlspecialchars($os_user); ?></strong></span>
          <button class="btn btn-g bsm" onclick="osLogout()"><i class="fas fa-sign-out-alt"></i><?= $t["logout"] ?? "خروج" ?></button>
        </div>
        <label class="fl"><?= $t["movie_name_search"] ?? "اسم الفيلم للبحث" ?></label>
        <div class="srow">
          <div class="sinp"><i class="fas fa-film"></i><input type="text" id="osQ" placeholder="<?= $t["movie_name_placeholder"] ?? "اسم الفيلم…" ?>" onkeydown="if(event.key==='Enter')osSearch()"></div>
          <select class="lsel" id="osLang"><option value="ar"><?= $t["lang_arabic_flag"] ?? "🇸🇦 عربي" ?></option><option value="en">🇬🇧 English</option><option value="fr">🇫🇷 Français</option><option value="es">🇪🇸 Español</option><option value="de">🇩🇪 Deutsch</option><option value="tr">🇹🇷 Türkçe</option></select>
          <button class="btn btn-p" onclick="osSearch()" id="osSearchBtn"><i class="fas fa-search"></i><?= $t["search"] ?? "بحث" ?></button>
        </div>
        <div id="osAl" style="margin-top:8px"></div>
        <div class="sub-rl" id="osRes"></div>
        <div class="sub-chip" id="selSubChip"><i class="fas fa-check-circle"></i><strong id="selSubName">—</strong><button class="btn btn-g bsm" onclick="clearSub()" style="margin-right:auto"><i class="fas fa-times"></i><?= $t["cancel_sub"] ?? "إلغاء" ?></button></div>
      </div>
    </div>
    <div class="vc" id="subUpCard" style="display:none">
      <div class="vchd"><div class="vchd-title"><i class="fas fa-upload"></i><?= $t["upload_subtitle"] ?? "رفع ملف ترجمة" ?></div></div>
      <div class="vcbody">
        <div class="uz" style="padding:26px"><input type="file" id="subFileIn" accept=".srt,.ass,.ssa,.vtt" onchange="subFileUpload(this)"><i class="fas fa-file-alt"></i><h3><?= $t["choose_subtitle_file"] ?? "اختر ملف الترجمة" ?></h3><p><?= $t["srt_ass_vtt"] ?? "SRT · ASS · SSA · VTT" ?></p></div>
        <div class="sub-chip" id="upSubChip" style="margin-top:10px"><i class="fas fa-check-circle"></i><strong id="upSubName">—</strong></div>
        <div id="subAl" style="margin-top:8px"></div>
      </div>
    </div>
    <div class="vnavb"><button class="btn btn-g" onclick="vidGo(1)"><i class="fas fa-arrow-right"></i><?= $t["previous"] ?? "السابق" ?></button><button class="btn btn-p" onclick="vidGo(3)"><?= $t["next"] ?? "التالي" ?> <i class="fas fa-arrow-left"></i></button></div>
  </div>
  <div class="vp" id="vp3">
    <div class="vc"><div class="vchd"><div class="vchd-title"><i class="fas fa-save"></i><?= $t["save_in_series"] ?? "الحفظ في شاشتي" ?></div></div>
      <div class="vcbody">
        <div class="merge-sum"><div class="mr"><div class="ml"><?= $t["video"] ?? "الفيديو" ?></div><div class="mv" id="mSumV">—</div></div><div class="mr"><div class="ml"><?= $t["subtitle"] ?? "الترجمة" ?></div><div class="mv" id="mSumS"><?= $t["no_subtitle"] ?? "بدون ترجمة" ?></div></div></div>
        
        <div class="fg" style="background:rgba(245,166,35,.07);padding:14px;border:1px solid rgba(245,166,35,.2);border-radius:var(--r1)">
            <label class="fl" style="color:var(--gold);margin-bottom:10px;"><i class="fas fa-folder"></i> <?= $t["target_folder"] ?? "تحديد المجلد الوجهة في شاشتي" ?></label>
            <select class="fs" id="vTargetSeries" onchange="vToggleSeriesFields(this.value, 'upload')"></select>
        </div>

        <div class="fg"><label class="fl" id="vNameLabel"><?= $t["new_work_name"] ?? "اسم العمل/المجلد الجديد (مطلوب)" ?> <small style='color:#00D084'></small></label><input type="text" class="fi" id="vChanName" placeholder="<?= $t["new_work_name_placeholder"] ?? "أدخل اسم الفيلم / عنوان الحلقة" ?>"></div>
        <div class="fg" id="vCatDiv"><label class="fl"><?= $t["category_select"] ?? "القسم (التصنيف)" ?></label><select class="fs" id="vChanCat"><option value=""><?= $t["select_category"] ?? "— اختر القسم —" ?></option><?php foreach($categories as $cat): ?><option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option><?php endforeach; ?></select></div>
        <div id="v3alert" style="margin-bottom:10px"></div>
        <div style="display:flex;gap:8px;flex-wrap:wrap"><button class="btn btn-s" style="flex:1;justify-content:center;padding:12px" onclick="vidSave()"><i class="fas fa-check"></i><?= $t["save_now"] ?? "حفظ في شاشتي الآن" ?></button></div>
        
        <div id="vidResult" style="display:none;margin-top:14px;background:rgba(0,208,132,.07);border:1px solid rgba(0,208,132,.25);border-radius:var(--r2);padding:20px;text-align:center"><div style="font-size:2rem;color:#00D084;margin-bottom:8px"><i class="fas fa-check-circle"></i></div><h3 style="margin-bottom:4px"><?= $t["saved_successfully"] ?? "تم الحفظ بنجاح! 🎉" ?></h3><p id="vidResultInfo" style="font-size:.8rem;color:var(--t3);margin-bottom:14px"></p><button class="btn btn-g" onclick="S('series');loadSeries();"><i class="fas fa-film"></i><?= $t["go_to_series"] ?? "انتقل لإدارة شاشتي" ?></button></div>
      </div>
    </div>
    <div class="vnavb"><button class="btn btn-g" onclick="vidGo(2)"><i class="fas fa-arrow-right"></i><?= $t["previous"] ?? "السابق" ?></button><span></span></div>
  </div>
</section>

<!-- VIDEO MANAGE -->
