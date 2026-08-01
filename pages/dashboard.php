<section id="dashboard" class="sec on">
  <div class="shdr"><h1 class="stitle"><?= $t["overview"] ?? "نظرة <span>عامة</span>" ?></h1></div>
  <div class="sgrid">
    <div class="sc r"><div class="sc-ic"><i class="fas fa-th-large"></i></div><div class="sc-v"><?php echo $stats['cats']; ?></div><div class="sc-l"><?= $t["categories"] ?? "الأقسام" ?></div></div>
    <div class="sc g"><div class="sc-ic"><i class="fas fa-tv"></i></div><div class="sc-v"><?php echo $stats['channels']; ?></div><div class="sc-l"><?= $t["channels"] ?? "القنوات" ?></div></div>
    <div class="sc p"><div class="sc-ic"><i class="fas fa-film"></i></div><div class="sc-v"><?php echo $stats['series']; ?></div><div class="sc-l"><?= $t["series"] ?? "شاشتي" ?></div></div>
    <div class="sc go"><div class="sc-ic"><i class="fas fa-eye"></i></div><div class="sc-v"><?php echo number_format($stats['views']); ?></div><div class="sc-l"><?= $t["views"] ?? "المشاهدات" ?></div></div>
    <div class="sc b"><div class="sc-ic"><i class="fas fa-users"></i></div><div class="sc-v"><?php echo $stats['users']; ?></div><div class="sc-l"><?= $t["users"] ?? "المستخدمين" ?></div></div>
  </div>

  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:20px;margin-bottom:25px;">
    <!-- CPU Card -->
    <div style="background:var(--bg2);padding:20px;border-radius:var(--r2);border:1px solid var(--border);box-shadow:var(--shadow);position:relative;overflow:hidden">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:15px">
        <div style="font-size:1.1rem;font-weight:700;color:var(--t2)"><i class="fas fa-microchip" style="color:#B36BFF;margin-left:8px"></i><?= $t["cpu_processor"] ?? "المعالج (CPU)" ?></div>
        <div id="cpu_percent_text" style="font-size:1.4rem;font-weight:800;color:var(--t1)">--%</div>
      </div>
      <div style="height:6px;background:var(--s2);border-radius:10px;overflow:hidden">
        <div id="cpu_bar" style="height:100%;width:0%;background:linear-gradient(90deg,#B36BFF,#7B2CBF);transition:width 0.5s ease, background 0.5s ease"></div>
      </div>
      <div style="margin-top:12px;font-size:0.8rem;color:var(--t3);text-align:left" id="cpu_desc"><?= $t["scanning"] ?? "جاري الفحص..." ?></div>
    </div>
    
    <!-- RAM Card -->
    <div style="background:var(--bg2);padding:20px;border-radius:var(--r2);border:1px solid var(--border);box-shadow:var(--shadow);position:relative;overflow:hidden">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:15px">
        <div style="font-size:1.1rem;font-weight:700;color:var(--t2)"><i class="fas fa-memory" style="color:#00D084;margin-left:8px"></i><?= $t["memory_ram"] ?? "الذاكرة (RAM)" ?></div>
        <div id="ram_percent_text" style="font-size:1.4rem;font-weight:800;color:var(--t1)">--%</div>
      </div>
      <div style="height:6px;background:var(--s2);border-radius:10px;overflow:hidden">
        <div id="ram_bar" style="height:100%;width:0%;background:linear-gradient(90deg,#00D084,#009e60);transition:width 0.5s ease, background 0.5s ease"></div>
      </div>
      <div style="margin-top:12px;font-size:0.85rem;color:var(--t3);text-align:left;display:flex;justify-content:space-between;font-weight:600">
        <span id="ram_used_text">--</span> <span id="ram_total_text">--</span>
      </div>
    </div>

    <!-- Disk Card -->
    <div style="background:var(--bg2);padding:20px;border-radius:var(--r2);border:1px solid var(--border);box-shadow:var(--shadow);position:relative;overflow:hidden">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:15px">
        <div style="font-size:1.1rem;font-weight:700;color:var(--t2)"><i class="fas fa-hdd" style="color:#4CC9F0;margin-left:8px"></i><?= $t["storage_disk"] ?? "التخزين (Disk)" ?></div>
        <div id="disk_percent_text" style="font-size:1.4rem;font-weight:800;color:var(--t1)">--%</div>
      </div>
      <div style="height:6px;background:var(--s2);border-radius:10px;overflow:hidden">
        <div id="disk_bar" style="height:100%;width:0%;background:linear-gradient(90deg,#4CC9F0,#0096C7);transition:width 0.5s ease, background 0.5s ease"></div>
      </div>
      <div style="margin-top:12px;font-size:0.85rem;color:var(--t3);text-align:left;display:flex;justify-content:space-between;font-weight:600">
        <span id="disk_used_text">--</span> <span id="disk_total_text">--</span>
      </div>
    </div>
  </div>

  <div class="dgrid">
    <div class="card">
      <div class="chdr"><span class="ctitle"><i class="fas fa-tv" style="color:var(--red);margin-left:7px"></i><?= $t["latest_channels"] ?? "آخر القنوات" ?></span><button class="btn btn-g bsm" onclick="S('channels')"><?= $t["all"] ?? "الكل" ?></button></div>
      <div class="cbody">
        <?php $rc=array_slice($channels,0,6);if($rc):foreach($rc as $ch): ?>
        <div class="ri"><div class="ri-ic"><i class="<?php echo htmlspecialchars($ch['logo_icon']); ?>"></i></div><div style="flex:1;min-width:0"><div class="ri-name"><?php echo htmlspecialchars($ch['name']); ?></div><div class="ri-meta"><?php echo htmlspecialchars($ch['cat_name']); ?></div></div><span style="font-size:.75rem;color:var(--t3)"><i class="fas fa-eye"></i> <?php echo $ch['views_count']; ?></span></div>
        <?php endforeach;else: ?><div class="empty"><i class="fas fa-tv"></i><p><?= $t["no_channels"] ?? "لا توجد قنوات" ?></p></div><?php endif; ?>
      </div>
    </div>
    <div class="card">
      <div class="chdr"><span class="ctitle"><i class="fas fa-bolt" style="color:var(--gold);margin-left:7px"></i><?= $t["quick_actions"] ?? "إجراءات سريعة" ?></span></div>
      <div class="cbody">
        <div class="qa-list">
          <a class="qa r" onclick="S('channels');setTimeout(()=>OM('addChM'),200)"><div class="qa-ic"><i class="fas fa-plus"></i></div><?= $t["add_channel"] ?? "إضافة قناة" ?></a>
          <a class="qa b" onclick="S('categories');setTimeout(()=>OM('addCatM'),200)"><div class="qa-ic"><i class="fas fa-folder-plus"></i></div><?= $t["add_category"] ?? "إضافة قسم" ?></a>
          <a class="qa p" onclick="S('series');loadSeries();setTimeout(()=>OM('addSeriesM'),300)"><div class="qa-ic"><i class="fas fa-film"></i></div><?= $t["add_series"] ?? "إضافة مسلسل" ?></a>
          <a class="qa go" onclick="S('vupload')"><div class="qa-ic"><i class="fas fa-cloud-upload-alt"></i></div><?= $t["upload_movie"] ?? "رفع فيلم" ?></a>
          <a class="qa g" href="backup_system.php?action=export_full"><div class="qa-ic"><i class="fas fa-download"></i></div><?= $t["export_backup"] ?? "تصدير نسخة احتياطية" ?></a>
          <a class="qa b" onclick="S('m3u-import');m3uLoadPlaylists()"><div class="qa-ic"><i class="fas fa-file-import"></i></div><?= $t["m3u_import"] ?? "استيراد M3U" ?></a>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- CATEGORIES -->
