<section id="categories" class="sec">
  <div class="shdr"><h1 class="stitle"><?= $t["manage_categories"] ?? "إدارة <span>الأقسام</span>" ?></h1><button class="btn btn-p" onclick="OM('addCatM')"><i class="fas fa-plus"></i><?= $t["new_category"] ?? "قسم جديد" ?></button></div>
  <div class="tw">
    <div class="tt"><div class="tsrch"><i class="fas fa-search"></i><input type="text" placeholder="<?= $t["search"] ?? "بحث..." ?>" oninput="FT(this,'catTbl')"></div><span style="font-size:.78rem;color:var(--t3)"><?php echo count($categories); ?> <?= $t["category"] ?? "قسم" ?></span></div>
    <?php if($categories): ?>
    <div id="catBulkBar" style="display:none;align-items:center;gap:12px;padding:10px 14px;margin-bottom:10px;background:rgba(229,9,20,.08);border:1px solid rgba(229,9,20,.25);border-radius:10px">
      <span style="font-size:.82rem;color:var(--t1);font-weight:700"><i class="fas fa-check-square" style="color:var(--red)"></i> <span id="catSelCount">0</span> <?= $t["selected_categories"] ?? "قسم محدد" ?></span>
      <button class="btn btn-g" style="margin-right:auto;padding:6px 14px" onclick="catClearSel()"><i class="fas fa-times"></i> <?= $t["deselect_all"] ?? "إلغاء التحديد" ?></button>
      <button class="btn btn-p" style="padding:6px 14px;background:var(--red)" onclick="catBulkDelete()"><i class="fas fa-trash"></i> <?= $t["delete_selected"] ?? "حذف المحدد" ?></button>
    </div>
    <table id="catTbl"><thead><tr><th style="width:38px"><input type="checkbox" id="catSelAll" onchange="catToggleAll(this)" style="width:16px;height:16px;cursor:pointer;accent-color:var(--red)"></th><th><?= $t["id"] ?? "ID" ?></th><th><?= $t["category"] ?? "القسم" ?></th><th><?= $t["parent_cat"] ?? "القسم الأب" ?></th><th><?= $t["icon"] ?? "الأيقونة" ?></th><th><?= $t["channels"] ?? "القنوات" ?></th><th><?= $t["visibility"] ?? "الظهور بالواجهة" ?></th><th><?= $t["actions"] ?? "إجراءات" ?></th></tr></thead><tbody>
    <?php foreach($categories as $cat): ?>
    <tr><td><input type="checkbox" class="catSelChk" value="<?php echo $cat['id']; ?>" onchange="catSelCtrl()" style="width:16px;height:16px;cursor:pointer;accent-color:var(--red)"></td><td style="color:var(--t3);font-size:.75rem">#<?php echo $cat['id']; ?></td><td><div class="cn"><div class="nic"><i class="<?php echo htmlspecialchars($cat['icon']); ?>"></i></div><strong style="color:var(--t1)"><?php echo htmlspecialchars($cat['name']); ?></strong></div></td>
    <td><?php $pid=$cat['parent_id']??null;if($pid){foreach($categories as $pc){if($pc['id']==$pid){echo '<span class="bdg bc">'.htmlspecialchars($pc['name']).'</span>';break;}}}else echo '<span style="color:var(--t3);font-size:.75rem">—</span>'; ?></td>
    <td><code style="font-size:.72rem;color:var(--t3);background:var(--s3);padding:2px 7px;border-radius:4px"><?php echo htmlspecialchars($cat['icon']); ?></code></td>
    <td><span class="bdg bc"><?php echo $cat['channel_count']; ?></span></td>
    <td><label class="fc-switch" style="display:inline-flex"><input type="checkbox" data-cat-id="<?php echo $cat['id']; ?>" class="catActiveToggle" <?php echo ((int)$cat['is_active']===1)?'checked':''; ?> onchange="toggleCategoryActive(this)"><span class="fc-slider"></span></label></td>
    <td><div class="acts"><button class="ib ed" onclick='editCat(<?php echo json_encode(['id'=>$cat['id'],'name'=>$cat['name'],'icon'=>$cat['icon'],'parent_id'=>$cat['parent_id']??null,'description'=>$cat['description']??'']); ?>)'><i class="fas fa-pen"></i></button><button class="ib dl" onclick="postDelete('delete_category', '<?php echo $cat['id']; ?>')"><i class="fas fa-trash"></i></button></div></td></tr>
    <?php endforeach; ?>
    </tbody></table>
    <?php else: ?><div class="empty"><i class="fas fa-th-large"></i><p><?= $t["no_categories"] ?? "لا توجد أقسام" ?></p></div><?php endif; ?>
  </div>
</section>

<!-- CHANNELS -->
