<section id="users" class="sec">
  <div class="shdr">
    <h1 class="stitle"><i class="fas fa-users-cog" style="color:var(--red)"></i><?= $t["manage_word"] ?? "إدارة" ?><span><?= $t["users_word"] ?? "المستخدمين" ?></span></h1>
    <button class="btn btn-p" onclick="OM('addUserM')"><i class="fas fa-user-plus"></i><?= $t["new_user"] ?? "مستخدم جديد" ?></button>
  </div>
  <div style="display:flex;gap:9px;align-items:center;margin-bottom:18px;flex-wrap:wrap">
    <div class="tsrch" style="max-width:250px;flex:1"><i class="fas fa-search"></i><input type="text" id="usrSearch" placeholder="<?= htmlspecialchars($t["ph_search"] ?? "بحث...") ?>" oninput="usrFilter()"></div>
    <select class="fs" id="usrRoleFilter" style="width:160px" onchange="usrFilter()">
      <option value="all"><?= $t["all_roles"] ?? "كل الأدوار" ?></option>
      <option value="administrator"><?= $t["role_admin"] ?? "مدير عام" ?></option>
      <option value="super"><?= $t["role_super"] ?? "مشرف" ?></option>
      <option value="normal"><?= $t["role_normal"] ?? "عادي" ?></option>
      <option value="custom"><?= $t["role_custom"] ?? "مخصص" ?></option>
    </select>
    <button class="btn btn-g bsm" onclick="loadUsers()"><i class="fas fa-sync-alt"></i></button>
    <span id="usrCount" style="font-size:.78rem;color:var(--t3);margin-right:auto"></span>
  </div>
  <div id="usrLoading" style="display:none;text-align:center;padding:50px;color:var(--t3)"><div class="pspin" style="margin:0 auto 12px"></div><p><?= $t["loading_dots2"] ?? "جارٍ التحميل…" ?></p></div>
  <div id="usrGrid" class="usr-grid"></div>
  <div id="usrEmpty" style="display:none" class="empty"><i class="fas fa-users"></i><p><?= $t["no_users"] ?? "لا يوجد مستخدمون" ?></p></div>
</section>

<!-- COMPANY INFO -->
