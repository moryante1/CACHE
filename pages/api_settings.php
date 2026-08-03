<section id="api-settings" class="sec">
  <div class="shdr"><h1 class="stitle"><?= $t["api_settings"] ?? "إعدادات <span>API</span>" ?></h1></div>
  <div class="sw-wrap" style="max-width:800px">
    <!-- Add new API -->
    <div style="display:flex;gap:15px;margin-bottom:25px;align-items:center;background:var(--bg2);padding:18px 22px;border-radius:var(--r2);border:1px solid var(--border);box-shadow:var(--shadow)">
      <div style="display:flex;align-items:center;gap:10px;color:var(--t2);font-weight:700;white-space:nowrap;font-size:1.05rem">
        <i class="fas fa-plus-circle" style="color:var(--primary)"></i> <?= $t["add_new_api"] ?? "إضافة اتصال API جديد:" ?>
      </div>
      <select id="apiTypeSelect" class="fi" style="flex:1;max-width:350px;font-size:0.95rem">
        <option value="" disabled selected><?= $t["choose_service_type"] ?? "-- اختر نوع الخدمة --" ?></option>
        <option value="tmdb">TMDB API (<?= $t["tmdb_api_name"] ?? "معلومات الأفلام والمسلسلات" ?>)</option>
        <option value="os">OpenSubtitles API (<?= $t["os_api_name"] ?? "الترجمات" ?>)</option>
        <option value="omdb">OMDb API (<?= $t["omdb_api_name"] ?? "قاعدة بيانات الأفلام" ?>)</option>
      </select>
      <button class="btn btn-p" type="button" onclick="addApiCard()" style="padding:10px 20px;font-weight:600"><i class="fas fa-plus"></i> <?= $t["add"] ?? "إضافة" ?></button>
    </div>

    <!-- TMDB API Card -->
    <div class="swc" id="card_tmdb" style="box-shadow:var(--shadow); <?php echo empty($settings['tmdb_api_key']) ? 'display:none;' : ''; ?>">
      <div class="swc-hd" style="border-bottom:1px solid var(--border);padding:18px 22px">
        <div class="swc-title" style="font-size:1.1rem;font-weight:700"><i class="fas fa-film" style="color:var(--gold);margin-left:10px;font-size:1.2rem"></i>TMDB API</div>
      </div>
      <div class="swc-body" style="padding:22px">
        <p style="color:var(--t3);font-size:0.9rem;margin-bottom:20px;line-height:1.6"><?= $t["tmdb_api_desc"] ?? "استخدم هذا المفتاح لجلب بوسترات ومعلومات الأفلام والمسلسلات تلقائياً من موقع TMDB." ?></p>
        <div class="fg">
          <label class="fl" style="font-weight:600"><?= $t["tmdb_api_key"] ?? "مفتاح TMDB API" ?></label>
          <input type="text" id="api_tmdb_key" class="fi" placeholder="<?= $t["tmdb_api_key_ph"] ?? "أدخل مفتاح TMDB API هنا" ?>" value="<?php echo htmlspecialchars($settings['tmdb_api_key'] ?? ''); ?>" style="direction:ltr;font-size:0.95rem;padding:12px">
        </div>
        <div style="margin-top:15px;font-size:.8rem;color:var(--t3);background:rgba(76,201,240,0.05);padding:12px;border-radius:var(--r1);border:1px solid rgba(76,201,240,0.2)">
          <i class="fas fa-link" style="color:#4CC9F0"></i>
          <?= $t["get_key_link"] ?? "رابط الحصول على المفتاح:" ?> <a href="https://www.themoviedb.org/settings/api" target="_blank" style="color:#4CC9F0;text-decoration:underline;direction:ltr;display:inline-block">https://www.themoviedb.org/settings/api</a>
        </div>
        
        <div style="display:flex;align-items:center;justify-content:space-between;margin-top:25px;padding-top:20px;border-top:1px solid var(--border);">
          <div id="status_tmdb" style="font-size:0.95rem;font-weight:600"></div>
          <div style="display:flex;gap:12px">
            <button class="btn btn-s" type="button" onclick="testApiTmdb()" style="padding:8px 16px"><i class="fas fa-wifi"></i> <?= $t["test_connection"] ?? "فحص الاتصال" ?></button>
            <button class="btn btn-d" type="button" onclick="removeApiCard('tmdb')" style="padding:8px 16px;background:rgba(229,9,20,0.1);color:var(--red);border:1px solid rgba(229,9,20,0.2)"><i class="fas fa-trash-alt"></i> <?= $t["remove"] ?? "إزالة" ?></button>
          </div>
        </div>
      </div>
    </div>

    <!-- OpenSubtitles API Card -->
    <div class="swc" id="card_os" style="box-shadow:var(--shadow); <?php echo (empty($settings['os_username']) && empty($settings['os_api_key'])) ? 'display:none;' : ''; ?>">
      <div class="swc-hd" style="border-bottom:1px solid var(--border);padding:18px 22px">
        <div class="swc-title" style="font-size:1.1rem;font-weight:700"><i class="fas fa-closed-captioning" style="color:#4CC9F0;margin-left:10px;font-size:1.2rem"></i>OpenSubtitles API</div>
      </div>
      <div class="swc-body" style="padding:22px">
        <p style="color:var(--t3);font-size:0.9rem;margin-bottom:20px;line-height:1.6"><?= $t["os_api_desc"] ?? "استخدم هذه الإعدادات لتسجيل الدخول التلقائي والبحث عن الترجمات من موقع OpenSubtitles." ?></p>
        <div class="row2">
            <div class="fg">
              <label class="fl" style="font-weight:600"><?= $t["username"] ?? "اسم المستخدم" ?></label>
              <input type="text" id="api_os_user" class="fi" placeholder="username" value="<?php echo htmlspecialchars($settings['os_username'] ?? ''); ?>" style="direction:ltr;padding:12px">
            </div>
            <div class="fg">
              <label class="fl" style="font-weight:600"><?= $t["password"] ?? "كلمة المرور" ?></label>
              <input type="password" id="api_os_pass" class="fi" placeholder="••••••••" value="<?php echo htmlspecialchars($settings['os_password'] ?? ''); ?>" style="direction:ltr;padding:12px">
            </div>
        </div>
        <div class="fg">
          <label class="fl" style="font-weight:600"><?= $t["api_key"] ?? "مفتاح API" ?></label>
          <input type="text" id="api_os_key" class="fi" placeholder="aBcDeF..." value="<?php echo htmlspecialchars($settings['os_api_key'] ?? ''); ?>" style="direction:ltr;font-size:0.95rem;padding:12px">
        </div>
        <div style="margin-top:15px;font-size:.8rem;color:var(--t3);background:rgba(76,201,240,0.05);padding:12px;border-radius:var(--r1);border:1px solid rgba(76,201,240,0.2)">
          <i class="fas fa-link" style="color:#4CC9F0"></i>
          <?= $t["get_key_link"] ?? "رابط الحصول على المفتاح:" ?> <a href="https://www.opensubtitles.com/en/consumers" target="_blank" style="color:#4CC9F0;text-decoration:underline;direction:ltr;display:inline-block">https://www.opensubtitles.com/en/consumers</a>
        </div>

        <div style="display:flex;align-items:center;justify-content:space-between;margin-top:25px;padding-top:20px;border-top:1px solid var(--border);">
          <div id="status_os" style="font-size:0.95rem;font-weight:600"></div>
          <div style="display:flex;gap:12px">
            <button class="btn btn-s" type="button" onclick="testApiOs()" style="padding:8px 16px"><i class="fas fa-wifi"></i> <?= $t["test_connection"] ?? "فحص الاتصال" ?></button>
            <button class="btn btn-d" type="button" onclick="removeApiCard('os')" style="padding:8px 16px;background:rgba(229,9,20,0.1);color:var(--red);border:1px solid rgba(229,9,20,0.2)"><i class="fas fa-trash-alt"></i> <?= $t["remove"] ?? "إزالة" ?></button>
          </div>
        </div>
      </div>
    </div>

    <!-- OMDb API Card -->
    <div class="swc" id="card_omdb" style="box-shadow:var(--shadow); <?php echo empty($settings['omdb_api_key']) ? 'display:none;' : ''; ?>">
      <div class="swc-hd" style="border-bottom:1px solid var(--border);padding:18px 22px">
        <div class="swc-title" style="font-size:1.1rem;font-weight:700"><i class="fas fa-database" style="color:var(--gold);margin-left:10px;font-size:1.2rem"></i>OMDb API</div>
      </div>
      <div class="swc-body" style="padding:22px">
        <p style="color:var(--t3);font-size:0.9rem;margin-bottom:20px;line-height:1.6"><?= $t["omdb_api_desc"] ?? "مفتاح للبحث عن الأفلام والمسلسلات من OMDb." ?></p>
        <div class="fg">
          <label class="fl" style="font-weight:600"><?= $t["omdb_api_key"] ?? "مفتاح OMDb API" ?></label>
          <input type="text" id="api_omdb_key" class="fi" placeholder="<?= $t["omdb_api_key_ph"] ?? "أدخل مفتاح OMDb API" ?>" value="<?php echo htmlspecialchars($settings['omdb_api_key'] ?? ''); ?>" style="direction:ltr;font-size:0.95rem;padding:12px">
        </div>
        <div style="margin-top:15px;font-size:.8rem;color:var(--t3);background:rgba(76,201,240,0.05);padding:12px;border-radius:var(--r1);border:1px solid rgba(76,201,240,0.2)">
          <i class="fas fa-link" style="color:#4CC9F0"></i>
          <?= $t["get_key_link"] ?? "رابط الحصول على المفتاح:" ?> <a href="https://www.omdbapi.com/apikey.aspx" target="_blank" style="color:#4CC9F0;text-decoration:underline;direction:ltr;display:inline-block">https://www.omdbapi.com/apikey.aspx</a>
        </div>
        
        <div style="display:flex;align-items:center;justify-content:space-between;margin-top:25px;padding-top:20px;border-top:1px solid var(--border);">
          <div id="status_omdb" style="font-size:0.95rem;font-weight:600"></div>
          <div style="display:flex;gap:12px">
            <button class="btn btn-s" type="button" onclick="testApiOmdb()" style="padding:8px 16px"><i class="fas fa-wifi"></i> <?= $t["test_connection"] ?? "فحص الاتصال" ?></button>
            <button class="btn btn-d" type="button" onclick="removeApiCard('omdb')" style="padding:8px 16px;background:rgba(229,9,20,0.1);color:var(--red);border:1px solid rgba(229,9,20,0.2)"><i class="fas fa-trash-alt"></i> <?= $t["remove"] ?? "إزالة" ?></button>
          </div>
        </div>
      </div>
    </div>

    <button class="btn btn-p" type="button" onclick="saveApiSettings()" style="width:100%;justify-content:center;padding:16px;font-size:1.1rem;font-weight:bold;box-shadow:var(--shadow)"><i class="fas fa-save" style="margin-left:8px;font-size:1.2rem"></i> <?= $t["save_all_api"] ?? "حفظ جميع إعدادات API" ?></button>
    <div id="apiSaveAlert" style="margin-top:14px"></div>
  </div>
</section>

<!-- SERIES -->
