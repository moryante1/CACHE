<section id="m3u-import" class="sec">
  <div class="shdr"><h1 class="stitle">استيراد <span>قوائم M3U</span></h1></div>

  <div class="bkgrid" style="margin-bottom:24px">
    <div class="bkc">
      <div class="bkc-title"><i class="fas fa-file-import" style="color:var(--red)"></i>رفع قائمة M3U</div>
      <div class="uz" id="m3uDropZone">
        <input type="file" id="m3uFileIn" accept=".m3u,.m3u8" onchange="m3uFileSelected(this)">
        <i class="fas fa-folder-open"></i>
        <h3>اسحب وأفلت ملف M3U هنا، أو انقر للاختيار</h3>
        <p>يدعم: .m3u, .m3u8</p>
      </div>
      <div id="m3uFileStatus" style="margin-top:10px;font-size:.8rem"></div>
    </div>

    <div class="bkc">
      <div class="bkc-title"><i class="fas fa-link" style="color:var(--red)"></i>رابط M3U</div>
      <div class="fg" style="margin-bottom:0">
        <label class="fl">رابط M3U</label>
        <input type="text" id="m3uUrlIn" class="fi" placeholder="https://yourserver.com/playlist.m3u" style="direction:ltr;text-align:left" onkeydown="if(event.key==='Enter'){event.preventDefault();m3uImportFromUrl()}">
      </div>
      <button type="button" class="btn btn-p" id="m3uUrlBtn" style="width:100%;justify-content:center;margin-top:14px" onclick="m3uImportFromUrl()"><i class="fas fa-arrow-down"></i>استيراد</button>
      <div id="m3uUrlStatus" style="margin-top:10px;font-size:.8rem"></div>
    </div>
  </div>

  <div class="tw">
    <div class="chdr"><span class="ctitle"><i class="fas fa-list" style="color:var(--red);margin-left:7px"></i>القوائم المستوردة</span></div>
    <div id="m3uPlaylistsLoading" style="padding:30px;text-align:center;color:var(--t3)"><span class="sp"></span> جارٍ التحميل...</div>
    <div id="m3uPlaylistsEmpty" class="empty" style="display:none"><i class="fas fa-file-import"></i><p>لا توجد قوائم مستوردة بعد</p></div>
    <table id="m3uPlaylistsTbl" style="display:none"><thead><tr><th>المصدر</th><th>النوع</th><th>عدد القنوات</th><th>تاريخ الاستيراد</th><th>إجراءات</th></tr></thead><tbody id="m3uPlaylistsBody"></tbody></table>
  </div>
</section>

<!-- [XTREAM-SECTION-START] قسم حساب Xtream IPTV -->
