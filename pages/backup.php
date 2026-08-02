<section id="backup" class="sec">
  <div class="shdr"><h1 class="stitle"><?= $t["backup_word"] ?? "النسخ" ?><span><?= $t["backup_word2"] ?? "الاحتياطي" ?></span></h1></div>
  <div class="bkgrid">
    <div class="bkc"><div class="bkc-title"><i class="fas fa-upload"></i><?= $t["restore_backup"] ?? "استعادة نسخة احتياطية" ?></div><p style="color:var(--t3);font-size:.83rem;margin-bottom:18px"><?= $t["restore_backup_desc"] ?? "اختر ملف SQL لاسترجاع كافة البيانات." ?></p><form action="backup_system.php?action=import" method="POST" enctype="multipart/form-data" style="border:2px dashed var(--br);padding:20px;border-radius:10px;text-align:center"><?= csrfField() ?><input type="file" name="sql_file" accept=".sql" required style="margin-bottom:10px;display:block;width:100%"><button type="submit" class="btn btn-p" style="width:100%;justify-content:center"><i class="fas fa-upload"></i><?= $t["start_import_now"] ?? "بدء الاستيراد الآن" ?></button></form></div>
    <div class="bkc"><div class="bkc-title"><i class="fas fa-download"></i><?= $t["export_new_backup"] ?? "تصدير نسخة جديدة" ?></div><p style="color:var(--t3);font-size:.83rem;margin-bottom:18px"><?= $t["export_backup_desc"] ?? "للحفاظ على بياناتك، قم بتحميل نسخة SQL دورياً." ?></p><a href="backup_system.php?action=export_full" class="btn btn-g" style="width:100%;justify-content:center;padding:12px"><i class="fas fa-download"></i><?= $t["download_backup"] ?? "تحميل النسخة الاحتياطية" ?></a></div>
  </div>
</section>


<!-- USERS MANAGEMENT -->
