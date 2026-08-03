<section id="m3u-import" class="sec">
  <div class="shdr"><h1 class="stitle"><?= $t["m3u_import"] ?? "استيراد" ?><span><?= $t["m3u_lists"] ?? "قوائم M3U" ?></span></h1></div>

  <div class="bkgrid" style="margin-bottom:24px">
    <div class="bkc">
      <div class="bkc-title"><i class="fas fa-file-import" style="color:var(--red)"></i><?= $t["m3u_upload"] ?? "رفع قائمة M3U" ?></div>
      <div class="uz" id="m3uDropZone">
        <input type="file" id="m3uFileIn" accept=".m3u,.m3u8" onchange="m3uFileSelected(this)">
        <i class="fas fa-folder-open"></i>
        <h3><?= $t["m3u_drag"] ?? "اسحب وأفلت ملف M3U هنا، أو انقر للاختيار" ?></h3>
        <p><?= $t["m3u_supports"] ?? "يدعم: .m3u, .m3u8" ?></p>
      </div>
      <div id="m3uFileStatus" style="margin-top:10px;font-size:.8rem"></div>
    </div>

    <div class="bkc">
      <div class="bkc-title"><i class="fas fa-link" style="color:var(--red)"></i><?= $t["m3u_url"] ?? "رابط M3U" ?></div>
      <div class="fg" style="margin-bottom:0">
        <label class="fl"><?= $t["m3u_url"] ?? "رابط M3U" ?></label>
        <input type="text" id="m3uUrlIn" class="fi" placeholder="https://yourserver.com/playlist.m3u" style="direction:ltr;text-align:left" onkeydown="if(event.key==='Enter'){event.preventDefault();m3uImportFromUrl()}">
      </div>
      <button type="button" class="btn btn-p" id="m3uUrlBtn" style="width:100%;justify-content:center;margin-top:14px" onclick="m3uImportFromUrl()"><i class="fas fa-arrow-down"></i><?= $t["m3u_import"] ?? "استيراد" ?></button>
      <div id="m3uUrlStatus" style="margin-top:10px;font-size:.8rem"></div>
    </div>
  </div>

  <div class="tw">
    <div class="chdr"><span class="ctitle"><i class="fas fa-list" style="color:var(--red);margin-left:7px"></i><?= $t["m3u_imported"] ?? "القوائم المستوردة" ?></span></div>
    <div id="m3uPlaylistsLoading" style="padding:30px;text-align:center;color:var(--t3)"><span class="sp"></span><?= $t["loading_dots"] ?? "جارٍ التحميل..." ?></div>
    <div id="m3uPlaylistsEmpty" class="empty" style="display:none"><i class="fas fa-file-import"></i><p><?= $t["m3u_none"] ?? "لا توجد قوائم مستوردة بعد" ?></p></div>
    <table id="m3uPlaylistsTbl" style="display:none"><thead><tr><th><?= $t["col_source"] ?? "المصدر" ?></th><th><?= $t["col_type"] ?? "النوع" ?></th><th><?= $t["col_channels_count"] ?? "عدد القنوات" ?></th><th><?= $t["col_import_date"] ?? "تاريخ الاستيراد" ?></th><th><?= $t["col_actions"] ?? "إجراءات" ?></th></tr></thead><tbody id="m3uPlaylistsBody"></tbody></table>
  </div>
</section>

<!-- [XTREAM-SECTION-START] قسم حساب Xtream IPTV -->
