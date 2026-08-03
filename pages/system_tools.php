<section id="system-tools" class="sec">
  <div class="shdr"><h1 class="stitle"><?= $t["tools"] ?? "صيانة <span>النظام</span>" ?></h1></div>
<!-- START: TAILSCALE UI (NETFLIX PREMIUM UI) -->
<div class="card" style="margin-bottom: 25px; border-color: rgba(229, 9, 20, 0.4); box-shadow: 0 0 20px rgba(229, 9, 20, 0.1);">
   <div class="chdr" style="background: rgba(229, 9, 20, 0.05); border-bottom: 1px solid rgba(229, 9, 20, 0.2);">
       <span class="ctitle"><i class="fas fa-satellite-dish" style="color:var(--red);margin-left:8px"></i><?= $t["tailscale_vpn"] ?? "قناة الإتصال الخاصة (Tailscale VPN)" ?></span>
       <span id="ts_display_status" style="font-size:0.75rem; font-weight:800; padding:3px 10px; border-radius:100px; background: rgba(229,9,20,.15); color: var(--red); border: 1px solid rgba(229,9,20,.3); float:left;"><?= $t["offline"] ?? "غير متصل OFFLINE" ?></span>
   </div>
   <div class="cbody" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 15px;">
       <div style="flex:1;">
           <div style="color: var(--t1); font-size: 1.1rem; font-weight:700; margin-bottom: 5px;"><?= $t["remote_access_tunnel"] ?? "Remote Access Tunnel" ?></div>
           <div style="color: var(--t3); font-size: 0.85rem;"><?= $t["tailscale_desc"] ?? "فتح مسار اتصال محمي لربط السيرفر وتجاوز قيود الجدار الناري بكل أمان." ?></div>
           <div id="ts_ip_wrap" style="display:none; margin-top:10px;">
               <span style="font-family:'Courier New', monospace; font-size:0.8rem; background:rgba(0,208,132,.1); border:1px dashed rgba(0,208,132,.4); color:#00D084; padding:5px 12px; border-radius:var(--r1);">
                   <i class="fas fa-wifi"></i> <?= $t["hidden_server_ip"] ?? "آيبي السيرفر المخفي:" ?> <b id="ts_ip_val">...</b>
               </span>
           </div>
       </div>
       
       <div>
           <button id="ts_display_btn" onclick="executeTailscaleAction()" class="btn btn-g" style="padding: 10px 24px; font-size: 1rem; display:flex; align-items:center; gap:8px; border-width: 2px;">
               <span id="ts_btn_label"><?= $t["turn_on_connection"] ?? "تشغيل الاتصال" ?></span> <i class="fas fa-power-off"></i>
           </button>
       </div>
   </div>
</div>
<!-- END: TAILSCALE UI -->

  <div class="tgrid">
    <div class="tc b" onclick="location.href='update.php'"><div class="tc-ic"><i class="fas fa-sync-alt"></i></div><div class="tc-name"><?= $t["system_update"] ?? "تحديث النظام" ?></div><div class="tc-desc"><?= $t["upgrade_to_latest"] ?? "الترقية إلى أحدث إصدار متاح" ?></div></div>
    <div class="tc g" onclick="location.href='backup_system.php?action=export_full'"><div class="tc-ic"><i class="fas fa-database"></i></div><div class="tc-name"><?= $t["backup"] ?? "نسخ احتياطي" ?></div><div class="tc-desc"><?= $t["export_db"] ?? "تصدير كامل لقاعدة البيانات" ?></div></div>
    <div class="tc p" onclick="S('backup')"><div class="tc-ic"><i class="fas fa-upload"></i></div><div class="tc-name"><?= $t["import_backup"] ?? "استيراد نسخة" ?></div><div class="tc-desc"><?= $t["restore_from_sql"] ?? "استعادة البيانات من ملف SQL" ?></div></div>
    <div class="tc r" onclick="location.href='activate.php'"><div class="tc-ic"><i class="fas fa-key"></i></div><div class="tc-name"><?= $t["license"] ?? "الترخيص" ?></div><div class="tc-desc"><?= $t["view_renew_license"] ?? "عرض وتجديد الترخيص" ?></div></div>
  
    <!-- الإضافات الجديدة المضافة بواسطة التحديث الآلي -->
    <div class="tc g" onclick="location.href='/act/index.php'"><div class="tc-ic"><i class="fas fa-certificate"></i></div><div class="tc-name"><?= $t["license_registration"] ?? "تسجيل الترخيص" ?></div><div class="tc-desc"><?= $t["add_follow_activation"] ?? "إضافة ومتابعة بيانات التفعيل" ?></div></div>
    
    <div class="tc p" onclick="location.href='storage_manager.php'"><div class="tc-ic"><i class="fas fa-hdd"></i></div><div class="tc-name"><?= $t["disk_management"] ?? "إدارة الهارد دسك" ?></div><div class="tc-desc"><?= $t["storage_monitoring"] ?? "لوحة دمج ومراقبة المساحة (Storage)" ?></div></div>
    
  </div>
</section>

<!-- BACKUP -->
