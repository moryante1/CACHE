<section id="xtream" class="sec">
  <div class="shdr" style="margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
    <h1 class="stitle" style="font-size: 1.8rem; font-weight: 700; color: var(--t1); margin: 0;">إدارة حسابات <span style="color: var(--primary);">Xtream IPTV</span></h1>
    <button type="button" class="btn btn-p" onclick="document.getElementById('xtAddForm').style.display = document.getElementById('xtAddForm').style.display === 'none' ? 'block' : 'none'" style="padding: 10px 20px; font-weight: 600; border-radius: 8px; box-shadow: 0 4px 10px rgba(59, 130, 246, 0.3);">
      <i class="fas fa-plus"></i> إضافة حساب جديد
    </button>
  </div>

  <div id="xtAddForm" style="display:none; animation: fadeInDown 0.3s ease;">
  <div class="bkc" style="margin-bottom:30px; background: var(--bg2); border: 1px solid var(--border); border-radius: 16px; box-shadow: 0 8px 24px rgba(0,0,0,0.06); padding: 25px; transition: transform 0.3s ease;">
    <div class="bkc-title" style="font-size: 1.3rem; font-weight: 600; border-bottom: 2px solid var(--border); padding-bottom: 15px; margin-bottom: 20px; color: var(--t1); display: flex; align-items: center; gap: 10px;">
      <div style="background: rgba(255, 60, 60, 0.1); width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; border-radius: 50%;"><i class="fas fa-satellite-dish" style="color:var(--red); font-size: 1.2rem;"></i></div>
      تسجيل الدخول وإضافة حساب
    </div>
    
    <div class="row2" style="display:grid;grid-template-columns:repeat(auto-fit, minmax(300px, 1fr));gap:20px; margin-bottom: 25px;">
      <div class="fg" style="background: var(--bg1); padding: 15px; border-radius: 12px; border: 1px solid var(--border); margin-bottom: 0;">
        <label class="fl" style="font-weight: 600; color: var(--t2); margin-bottom: 10px; display: block;"><i class="fas fa-tag" style="margin-left: 8px; color: var(--primary);"></i>اسم الحساب <span style="font-size: 0.8em; color: var(--t3); font-weight: normal;">(اختياري)</span></label>
        <input type="text" id="xtName" class="fi" placeholder="مثال: سيرفري الرئيسي" style="border: 1px solid var(--br); border-radius: 8px; padding: 12px; width: 100%; transition: all 0.2s;">
      </div>
      <div class="fg" style="background: var(--bg1); padding: 15px; border-radius: 12px; border: 1px solid var(--border); margin-bottom: 0;">
        <label class="fl" style="font-weight: 600; color: var(--t2); margin-bottom: 10px; display: block;"><i class="fas fa-server" style="margin-left: 8px; color: var(--primary);"></i>العنوان <span style="font-size: 0.8em; color: var(--t3); font-weight: normal;">(Host / DNS)</span></label>
        <input type="text" id="xtHost" class="fi" placeholder="http://example.com:8080" style="direction:ltr;text-align:left; border: 1px solid var(--br); border-radius: 8px; padding: 12px; width: 100%; font-family: monospace;">
      </div>
      <div class="fg" style="background: var(--bg1); padding: 15px; border-radius: 12px; border: 1px solid var(--border); margin-bottom: 0;">
        <label class="fl" style="font-weight: 600; color: var(--t2); margin-bottom: 10px; display: block;"><i class="fas fa-user" style="margin-left: 8px; color: var(--primary);"></i>اسم المستخدم</label>
        <input type="text" id="xtUser" class="fi" placeholder="username" style="direction:ltr;text-align:left; border: 1px solid var(--br); border-radius: 8px; padding: 12px; width: 100%; font-family: monospace;">
      </div>
      <div class="fg" style="background: var(--bg1); padding: 15px; border-radius: 12px; border: 1px solid var(--border); margin-bottom: 0;">
        <label class="fl" style="font-weight: 600; color: var(--t2); margin-bottom: 10px; display: block;"><i class="fas fa-key" style="margin-left: 8px; color: var(--primary);"></i>كلمة المرور</label>
        <input type="password" id="xtPass" class="fi" placeholder="password" style="direction:ltr;text-align:left; border: 1px solid var(--br); border-radius: 8px; padding: 12px; width: 100%; font-family: monospace;">
      </div>
    </div>

    <button type="button" class="btn btn-g" id="xtLoginBtn" style="width:100%; justify-content:center; padding: 16px; font-size: 1.1rem; font-weight: bold; border-radius: 12px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; border: none; box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3); transition: all 0.3s; margin-top: 6px;" onclick="xtreamLogin()">
      <i class="fas fa-plug" style="margin-left: 10px;"></i> تسجيل الدخول والتحقق من الاتصال
    </button>
    <div id="xtLoginStatus" style="margin-top:15px; font-size:0.95rem; text-align: center; font-weight: 500;"></div>

    <!-- نتيجة الفحص + خيارات الاستيراد -->
    <div id="xtImportBox" style="display:none; margin-top:25px; border-top:2px dashed var(--br); padding-top:25px;">
      <div id="xtInfo" style="font-size:0.95rem; color:var(--t2); margin-bottom:20px; background: rgba(59, 130, 246, 0.1); padding: 15px; border-radius: 10px; border-right: 4px solid #3b82f6;"></div>
      
      <div class="fl" style="margin-bottom:15px; font-size: 1.1rem; font-weight: 600;">حدد المحتوى المراد استيراده:</div>
      
      <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap:15px; margin-bottom:25px;">
        <label class="xt-chk" style="display:flex; flex-direction: column; align-items:center; gap:12px; cursor:pointer; background: var(--bg1); padding: 20px; border-radius: 12px; border: 2px solid transparent; box-shadow: 0 4px 10px rgba(0,0,0,0.05); transition: all 0.2s;" onmouseover="this.style.borderColor='var(--primary)'" onmouseout="this.style.borderColor='transparent'">
          <input type="checkbox" id="xtImpLive" checked style="transform: scale(1.3);"> 
          <div style="display: flex; flex-direction: column; align-items: center; gap: 8px;">
            <i class="fas fa-tv" style="font-size: 1.8rem; color: #3b82f6;"></i>
            <span id="xtLblLive" style="font-weight: 600; font-size: 1.1rem;">القنوات</span>
            <span style="font-size:.72rem; color:var(--t3); text-align:center;">← إدارة القنوات<br><small style="opacity:.75;">بث مباشر ts / m3u8</small></span>
          </div>
        </label>
        
        <label class="xt-chk" style="display:flex; flex-direction: column; align-items:center; gap:12px; cursor:pointer; background: var(--bg1); padding: 20px; border-radius: 12px; border: 2px solid transparent; box-shadow: 0 4px 10px rgba(0,0,0,0.05); transition: all 0.2s;" onmouseover="this.style.borderColor='var(--primary)'" onmouseout="this.style.borderColor='transparent'">
          <input type="checkbox" id="xtImpVod" checked style="transform: scale(1.3);"> 
          <div style="display: flex; flex-direction: column; align-items: center; gap: 8px;">
            <i class="fas fa-film" style="font-size: 1.8rem; color: #ef4444;"></i>
            <span id="xtLblVod" style="font-weight: 600; font-size: 1.1rem;">الأفلام</span>
            <span style="font-size:.72rem; color:var(--t3); text-align:center;">← إدارة شاشتي<br><small style="opacity:.75;">ملفات mp4 / mkv</small></span>
          </div>
        </label>
        
        <label class="xt-chk" style="display:flex; flex-direction: column; align-items:center; gap:12px; cursor:pointer; background: var(--bg1); padding: 20px; border-radius: 12px; border: 2px solid transparent; box-shadow: 0 4px 10px rgba(0,0,0,0.05); transition: all 0.2s;" onmouseover="this.style.borderColor='var(--primary)'" onmouseout="this.style.borderColor='transparent'">
          <input type="checkbox" id="xtImpSeries" checked style="transform: scale(1.3);"> 
          <div style="display: flex; flex-direction: column; align-items: center; gap: 8px;">
            <i class="fas fa-layer-group" style="font-size: 1.8rem; color: #f59e0b;"></i>
            <span id="xtLblSeries" style="font-weight: 600; font-size: 1.1rem;">المسلسلات</span>
            <span style="font-size:.72rem; color:var(--t3); text-align:center;">← إدارة شاشتي<br><small style="opacity:.75;">حلقات mp4 / mkv</small></span>
          </div>
        </label>
      </div>
      
      <!-- ══ لوحة تقدّم الاستيراد الحيّة ══ -->
      <div id="xtProgBox" style="display:none; margin-bottom:14px; background:var(--bg1); border:1px solid var(--br); border-radius:14px; padding:18px;">
        <div style="display:flex; justify-content:space-between; align-items:center; gap:10px; margin-bottom:12px; flex-wrap:wrap;">
          <span id="xtProgLabel" style="font-weight:700; color:var(--t1); font-size:.95rem; display:flex; align-items:center; gap:9px;">
            <span class="sp" style="width:15px;height:15px;border-width:2px;"></span>جارٍ التحضير...
          </span>
          <span id="xtProgPct" style="font-weight:800; color:#3b82f6; font-size:1.15rem; font-variant-numeric:tabular-nums;">0%</span>
        </div>

        <div style="height:11px; background:var(--bg2); border-radius:99px; overflow:hidden; position:relative;">
          <div id="xtProgFill" style="height:100%; width:0%; border-radius:99px; background:linear-gradient(90deg,#3b82f6,#8b5cf6,#3b82f6); background-size:200% 100%; animation:xtFlow 1.6s linear infinite; transition:width .45s cubic-bezier(.4,0,.2,1);"></div>
        </div>

        <div id="xtProgCount" style="margin-top:10px; font-size:.85rem; color:var(--t2); font-variant-numeric:tabular-nums;">—</div>

        <div style="display:flex; gap:8px; flex-wrap:wrap; margin-top:12px;">
          <span class="xtchip"><i class="fas fa-tv"></i> قنوات: <b id="xtCntLive">0</b></span>
          <span class="xtchip"><i class="fas fa-film"></i> أفلام: <b id="xtCntVod">0</b></span>
          <span class="xtchip"><i class="fas fa-clapperboard"></i> مسلسلات: <b id="xtCntSer">0</b></span>
          <span class="xtchip" id="xtCntSkipWrap" style="display:none;"><i class="fas fa-forward"></i> متخطّى: <b id="xtCntSkip">0</b></span>
        </div>

        <div style="display:flex; justify-content:space-between; margin-top:12px; padding-top:11px; border-top:1px solid var(--br); font-size:.82rem; color:var(--t3); font-variant-numeric:tabular-nums;">
          <span><i class="far fa-clock" style="margin-left:5px;"></i>مضى: <b id="xtElapsed" style="color:var(--t2);">00:00</b></span>
          <span><i class="fas fa-hourglass-half" style="margin-left:5px;"></i>المتبقي تقريباً: <b id="xtEta" style="color:#10b981;">— يُحسب...</b></span>
        </div>
      </div>

      <button type="button" id="xtStopBtn" onclick="xtreamAbortImport()"
        style="display:none; width:100%; justify-content:center; align-items:center; padding:14px; margin-bottom:12px; font-size:1rem; font-weight:700; border-radius:12px; background:#ef4444; color:#fff; border:none; box-shadow:0 4px 15px rgba(239,68,68,.3); cursor:pointer; transition:all .25s; font-family:'Tajawal',sans-serif;">
        <i class="fas fa-hand" style="margin-left:8px;"></i>إيقاف الاستيراد إجبارياً
      </button>

      <button type="button" class="btn btn-p" id="xtImportBtn" style="width:100%; justify-content:center; padding: 16px; font-size: 1.1rem; font-weight: bold; border-radius: 12px; background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); color: white; border: none; box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3); transition: all 0.3s;" onclick="xtreamImport()">
        <i class="fas fa-cloud-download-alt" style="margin-left: 10px;"></i> استيراد وإضافة المحتوى
      </button>
      <div id="xtImportStatus" style="margin-top:15px; font-size:0.95rem; text-align: center; font-weight: 500;"></div>
    </div>
  </div>
  </div>

  <div class="tw" style="background: var(--bg2); border: 1px solid var(--border); border-radius: 16px; box-shadow: 0 8px 24px rgba(0,0,0,0.06); padding: 25px; overflow: hidden;">
    <div class="chdr" style="margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid var(--border);">
      <span class="ctitle" style="font-size: 1.2rem; font-weight: 600; display: flex; align-items: center; gap: 10px;">
        <div style="background: rgba(59, 130, 246, 0.1); width: 35px; height: 35px; display: flex; align-items: center; justify-content: center; border-radius: 8px;"><i class="fas fa-list" style="color: #3b82f6;"></i></div>
        الحسابات المضافة
      </span>
    </div>
    
    <div id="xtLoading" style="padding:40px;text-align:center;color:var(--t3); font-size: 1.1rem;"><span class="sp" style="width: 30px; height: 30px; border-width: 3px; margin-bottom: 15px; border-top-color: var(--primary);"></span><br>جاري التحميل...</div>
    
    <div id="xtEmpty" class="empty" style="display:none; padding: 50px 20px; background: var(--bg1); border-radius: 12px; border: 1px dashed var(--br);">
      <div style="background: rgba(156, 163, 175, 0.1); width: 80px; height: 80px; display: flex; align-items: center; justify-content: center; border-radius: 50%; margin: 0 auto 20px auto;">
        <i class="fas fa-satellite-dish" style="font-size: 2.5rem; color: var(--t3);"></i>
      </div>
      <p style="font-size: 1.2rem; color: var(--t2); font-weight: 600; margin-bottom: 5px;">لا توجد حسابات مضافة بعد</p>
    </div>
    
    <div style="overflow-x: auto;">
      <table id="xtTbl" style="display:none; width: 100%; border-collapse: separate; border-spacing: 0 8px; white-space: nowrap;">
        <thead>
          <tr style="background: var(--bg1);">
            <th style="padding: 15px; border-radius: 0 8px 8px 0; font-weight: 600; color: var(--t2);">الاسم</th>
            <th style="padding: 15px; font-weight: 600; color: var(--t2);">العنوان / المستخدم</th>
            <th style="padding: 15px; font-weight: 600; color: var(--t2); text-align: center;"><i class="fas fa-network-wired" style="color: #8b5cf6; margin-left: 5px;"></i> اتصالات</th>
            <th style="padding: 15px; font-weight: 600; color: var(--t2); text-align: center;"><i class="fas fa-tv" style="color: #3b82f6; margin-left: 5px;"></i> محتوى</th>
            <th style="padding: 15px; font-weight: 600; color: var(--t2);"><i class="fas fa-calendar-alt" style="color: #10b981; margin-left: 5px;"></i> الانتهاء</th>
            <th style="padding: 15px; font-weight: 600; color: var(--t2);">المزامنة</th>
            <th style="padding: 15px; border-radius: 8px 0 0 8px; font-weight: 600; color: var(--t2); text-align: center;">إجراءات</th>
          </tr>
        </thead>
        <tbody id="xtBody"></tbody>
      </table>
    </div>
  </div>

  <!-- ══════════ تسريع: فهارس قاعدة البيانات ══════════ -->
  <div class="tw" style="margin-top:25px; background: var(--bg2); border: 1px solid rgba(16,185,129,.35); border-radius: 16px; padding: 22px;">
    <div style="display:flex; align-items:center; gap:10px; margin-bottom:12px; color:#10b981; font-weight:700; font-size:1.05rem;">
      <div style="background:rgba(16,185,129,.12); width:34px; height:34px; display:flex; align-items:center; justify-content:center; border-radius:8px;"><i class="fas fa-bolt"></i></div>
      تسريع قاعدة البيانات
    </div>
    <p style="color:var(--t2); font-size:.9rem; line-height:1.85; margin:0 0 16px;">
      بدون فهارس، كل استعلام يمسح الجدول <strong>كاملاً</strong> — ومع عشرات آلاف الصفوف بعد استيراد Xtream
      يصبح هذا أبطأ جزء في الموقع. هذا الزر يضيف الفهارس الناقصة لجداول القنوات والمسلسلات والحلقات.
      <br><span style="color:var(--t3); font-size:.85rem;">آمن تماماً — لا يحذف أي بيانات. يكفي تشغيله مرة واحدة.</span>
    </p>
    <button id="xtOptBtn" class="btn" onclick="xtreamOptimizeDb()"
      style="background:#10b981; color:#fff; border:none; padding:11px 22px; border-radius:10px; font-family:'Tajawal',sans-serif; font-weight:700; font-size:.92rem; cursor:pointer;">
      <i class="fas fa-bolt" style="margin-left:8px;"></i> تطبيق الفهارس وتسريع الموقع
    </button>
    <div id="xtOptStatus" style="margin-top:13px; font-size:.9rem; font-weight:500;"></div>
  </div>

  <!-- ══════════ إصلاح: نقل الأفلام القديمة من القنوات إلى شاشتي ══════════ -->
  <div class="tw" style="margin-top:25px; background: var(--bg2); border: 1px solid rgba(245,158,11,.35); border-radius: 16px; padding: 22px;">
    <div style="display:flex; align-items:center; gap:10px; margin-bottom:12px; color:#f59e0b; font-weight:700; font-size:1.05rem;">
      <div style="background:rgba(245,158,11,.12); width:34px; height:34px; display:flex; align-items:center; justify-content:center; border-radius:8px;"><i class="fas fa-wand-magic-sparkles"></i></div>
      إصلاح: نقل الأفلام إلى «شاشتي»
    </div>
    <p style="color:var(--t2); font-size:.9rem; line-height:1.85; margin:0 0 16px;">
      في الإصدارات السابقة كانت الأفلام تُستورد خطأً إلى <strong>إدارة القنوات</strong>.
      يبحث هذا الزر عن أي فيلم عالق هناك <span style="color:var(--t3);">(رابطه <code style="direction:ltr; display:inline-block;">/movie/</code>)</span>
      وينقله إلى <strong style="color:#10b981;">إدارة شاشتي</strong> مع صورته واسمه — بلا إعادة استيراد.
      <br><span style="color:var(--t3); font-size:.85rem;">القنوات المباشرة (ts / m3u8) لن تتأثر.</span>
    </p>
    <button id="xtFixVodBtn" class="btn" onclick="xtreamFixVod()"
      style="background:#f59e0b; color:#fff; border:none; padding:11px 22px; border-radius:10px; font-family:'Tajawal',sans-serif; font-weight:700; font-size:.92rem; cursor:pointer;">
      <i class="fas fa-arrows-turn-right" style="margin-left:8px;"></i> نقل الأفلام إلى شاشتي الآن
    </button>
    <div id="xtFixVodStatus" style="margin-top:13px; font-size:.9rem; font-weight:500;"></div>
  </div>

  <!-- ══════════ منطقة الخطر: مسح إجباري يدوي لكل ما يخص Xtream ══════════ -->
  <div class="tw" style="margin-top:25px; background: var(--bg2); border: 1px solid rgba(239,68,68,.35); border-radius: 16px; box-shadow: 0 8px 24px rgba(239,68,68,0.07); padding: 25px; overflow: hidden;">
    <div class="chdr" style="margin-bottom: 18px; padding-bottom: 15px; border-bottom: 1px solid rgba(239,68,68,.2);">
      <span class="ctitle" style="font-size: 1.2rem; font-weight: 600; display: flex; align-items: center; gap: 10px; color:#ef4444;">
        <div style="background: rgba(239, 68, 68, 0.12); width: 35px; height: 35px; display: flex; align-items: center; justify-content: center; border-radius: 8px;"><i class="fas fa-triangle-exclamation" style="color: #ef4444;"></i></div>
        منطقة الخطر — مسح إجباري
      </span>
    </div>

    <!-- سطر تعريفي موجز -->
    <p style="color:var(--t2); font-size:.94rem; line-height:1.8; margin:0 0 20px;">
      عملية <strong style="color:#ef4444;">نهائية</strong> تمسح كل ما يخص Xtream من قاعدة البيانات دفعة واحدة.
      خصّصناها للحالات الاستثنائية فقط.
    </p>

    <!-- جدول: ما سيُحذف مقابل ما سيبقى -->
    <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(255px,1fr)); gap:14px; margin-bottom:20px;">

      <div style="background:var(--bg1); border:1px solid rgba(239,68,68,.22); border-radius:12px; padding:16px;">
        <div style="display:flex; align-items:center; gap:8px; color:#ef4444; font-weight:700; font-size:.9rem; margin-bottom:12px; padding-bottom:10px; border-bottom:1px solid rgba(239,68,68,.15);">
          <i class="fas fa-trash-can"></i><span>سيُحذف نهائياً</span>
        </div>
        <ul style="list-style:none; padding:0; margin:0; color:var(--t2); font-size:.87rem; line-height:2.1;">
          <li><i class="fas fa-xmark" style="color:#ef4444; margin-left:8px; font-size:.75rem;"></i>كل حسابات Xtream المضافة</li>
          <li><i class="fas fa-xmark" style="color:#ef4444; margin-left:8px; font-size:.75rem;"></i>كل القنوات المستوردة</li>
          <li><i class="fas fa-xmark" style="color:#ef4444; margin-left:8px; font-size:.75rem;"></i>كل الأفلام المستوردة</li>
          <li><i class="fas fa-xmark" style="color:#ef4444; margin-left:8px; font-size:.75rem;"></i>كل المسلسلات وحلقاتها</li>
          <li><i class="fas fa-xmark" style="color:#ef4444; margin-left:8px; font-size:.75rem;"></i>أقسام Xtream الفارغة</li>
          <li><i class="fas fa-xmark" style="color:#ef4444; margin-left:8px; font-size:.75rem;"></i>الكاش والمفضلة المرتبطة</li>
        </ul>
      </div>

      <div style="background:var(--bg1); border:1px solid rgba(16,185,129,.22); border-radius:12px; padding:16px;">
        <div style="display:flex; align-items:center; gap:8px; color:#10b981; font-weight:700; font-size:.9rem; margin-bottom:12px; padding-bottom:10px; border-bottom:1px solid rgba(16,185,129,.15);">
          <i class="fas fa-shield-halved"></i><span>لن يتأثر</span>
        </div>
        <ul style="list-style:none; padding:0; margin:0; color:var(--t2); font-size:.87rem; line-height:2.1;">
          <li><i class="fas fa-check" style="color:#10b981; margin-left:8px; font-size:.75rem;"></i>المحتوى المُضاف يدوياً</li>
          <li><i class="fas fa-check" style="color:#10b981; margin-left:8px; font-size:.75rem;"></i>قوائم M3U ومحتواها</li>
          <li><i class="fas fa-check" style="color:#10b981; margin-left:8px; font-size:.75rem;"></i>الفيديوهات المرفوعة</li>
          <li><i class="fas fa-check" style="color:#10b981; margin-left:8px; font-size:.75rem;"></i>الأقسام المشتركة مع مصادر أخرى</li>
          <li><i class="fas fa-check" style="color:#10b981; margin-left:8px; font-size:.75rem;"></i>الإعدادات وحسابات الأدمن</li>
        </ul>
      </div>

    </div>

    <!-- متى تستخدمه -->
    <div style="background:rgba(245,158,11,.07); border-right:3px solid #f59e0b; border-radius:8px; padding:13px 15px; margin-bottom:20px;">
      <div style="color:#f59e0b; font-weight:700; font-size:.85rem; margin-bottom:6px;">
        <i class="fas fa-lightbulb" style="margin-left:6px;"></i>متى تستخدمه؟
      </div>
      <div style="color:var(--t3); font-size:.84rem; line-height:1.9;">
        عند بقاء بيانات معلّقة أو أقسام فارغة بعد حذف حساب، أو عند تعطّل استيراد في منتصفه، أو إذا أردت البدء من الصفر.
      </div>
    </div>

    <!-- شريط التنفيذ -->
    <div style="background:var(--bg1); border:1px solid rgba(239,68,68,.25); border-radius:12px; padding:18px;">
      <div style="display:flex; align-items:flex-start; gap:9px; color:#ef4444; font-size:.87rem; font-weight:700; margin-bottom:14px;">
        <i class="fas fa-circle-exclamation" style="margin-top:3px;"></i>
        <span>لا يمكن التراجع عن هذه العملية بعد تنفيذها. تأكّد من وجود نسخة احتياطية إن كان المحتوى مهماً.</span>
      </div>
      <div style="display:flex; align-items:center; gap:16px; flex-wrap:wrap; padding-top:14px; border-top:1px solid rgba(239,68,68,.12);">
        <label style="display:flex; align-items:center; gap:9px; cursor:pointer; user-select:none; color:var(--t2); font-size:.89rem; font-weight:500;">
          <input type="checkbox" id="xtPurgeConfirm" onchange="document.getElementById('xtPurgeBtn').disabled = !this.checked;" style="width:17px; height:17px; cursor:pointer; accent-color:#ef4444;">
          أفهم العواقب وأرغب في المتابعة
        </label>
        <button id="xtPurgeBtn" class="btn" disabled onclick="xtreamPurgeAll()"
          style="background:#ef4444; color:#fff; border:none; padding:11px 22px; border-radius:10px; font-family:'Tajawal',sans-serif; font-weight:700; font-size:.92rem; cursor:pointer; transition:.18s; margin-right:auto;">
          <i class="fas fa-bomb" style="margin-left:8px;"></i>مسح كل محتوى Xtream
        </button>
      </div>
    </div>
    <div id="xtPurgeStatus" style="margin-top:14px; font-size:.93rem; font-weight:500;"></div>
  </div>
  <style>
    #xtPurgeBtn:disabled{opacity:.45; cursor:not-allowed;}
    #xtPurgeBtn:not(:disabled):hover{background:#dc2626; transform:translateY(-1px); box-shadow:0 6px 16px rgba(239,68,68,.3);}
    #xtStopBtn:not(:disabled):hover{background:#dc2626; transform:translateY(-1px); box-shadow:0 6px 18px rgba(239,68,68,.4);}
    #xtStopBtn:disabled{opacity:.6; cursor:wait;}
    #xtStopBtn{animation:xtPulse 2s ease-in-out infinite;}
    @keyframes xtPulse{
      0%,100%{box-shadow:0 4px 15px rgba(239,68,68,.3);}
      50%{box-shadow:0 4px 22px rgba(239,68,68,.55);}
    }
    /* شريط التقدّم الحيّ */
    @keyframes xtFlow{0%{background-position:200% 0;}100%{background-position:-200% 0;}}
    .xtchip{display:inline-flex; align-items:center; gap:6px; background:var(--bg2); border:1px solid var(--br); border-radius:99px; padding:5px 12px; font-size:.8rem; color:var(--t2); font-variant-numeric:tabular-nums;}
    .xtchip b{color:var(--t1); font-weight:800;}
    .xtchip i{font-size:.72rem; opacity:.75;}
  </style>
</section>
<!-- [XTREAM-SECTION-END] -->

<!-- API SETTINGS -->
