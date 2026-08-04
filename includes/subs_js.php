<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 *  واجهة جافاسكربت لنظام الاشتراكات
 * ───────────────────────────────────────────────────────────────────────────
 *  ملف مستقل عن main_js.php عمداً: ذاك الملف يقارب 2100 سطر ويمرّ منه كل
 *  شيء في اللوحة، وخطأ صياغة واحد فيه يُطفئ اللوحة بالكامل. هنا العزل
 *  يعني أن أي خلل في الاشتراكات يبقى داخل الاشتراكات.
 *
 *  كل النصوص تمرّ عبر $t[...] مع ارتداد عربي — نفس نمط بقية اللوحة.
 * ═══════════════════════════════════════════════════════════════════════════
 */
?>
<script>
(function(){
'use strict';

/* ── نصوص مترجمة ── */
const T = {
  err_net:      <?= json_encode($t["js_subs_neterr"]   ?? "تعذّر الاتصال بالخادم", JSON_UNESCAPED_UNICODE) ?>,
  saved:        <?= json_encode($t["js_subs_saved"]    ?? "تم الحفظ بنجاح", JSON_UNESCAPED_UNICODE) ?>,
  deleted:      <?= json_encode($t["js_subs_deleted"]  ?? "تم الحذف", JSON_UNESCAPED_UNICODE) ?>,
  confirm_del:  <?= json_encode($t["js_subs_confdel"]  ?? "تأكيد الحذف؟ لا يمكن التراجع.", JSON_UNESCAPED_UNICODE) ?>,
  never:        <?= json_encode($t["js_subs_never"]    ?? "—", JSON_UNESCAPED_UNICODE) ?>,
  unlimited:    <?= json_encode($t["js_subs_unlimited"]?? "بلا نهاية", JSON_UNESCAPED_UNICODE) ?>,
  day:          <?= json_encode($t["js_subs_day"]      ?? "يوم", JSON_UNESCAPED_UNICODE) ?>,
  days:         <?= json_encode($t["js_subs_days"]     ?? "يوم", JSON_UNESCAPED_UNICODE) ?>,
  st_active:    <?= json_encode($t["js_st_active"]     ?? "فعّال", JSON_UNESCAPED_UNICODE) ?>,
  st_expired:   <?= json_encode($t["js_st_expired"]    ?? "منتهٍ", JSON_UNESCAPED_UNICODE) ?>,
  st_pending:   <?= json_encode($t["js_st_pending"]    ?? "بانتظار التفعيل", JSON_UNESCAPED_UNICODE) ?>,
  st_disabled:  <?= json_encode($t["js_st_disabled"]   ?? "موقوف", JSON_UNESCAPED_UNICODE) ?>,
  cp_available: <?= json_encode($t["js_cp_available"]  ?? "متاح", JSON_UNESCAPED_UNICODE) ?>,
  cp_usedup:    <?= json_encode($t["js_cp_usedup"]     ?? "مستهلك", JSON_UNESCAPED_UNICODE) ?>,
  cp_disabled:  <?= json_encode($t["js_cp_disabled"]   ?? "معطّل", JSON_UNESCAPED_UNICODE) ?>,
  cp_expired:   <?= json_encode($t["js_cp_expired"]    ?? "منتهي الصلاحية", JSON_UNESCAPED_UNICODE) ?>,
  via_coupon:   <?= json_encode($t["js_via_coupon"]    ?? "كوبون", JSON_UNESCAPED_UNICODE) ?>,
  via_admin:    <?= json_encode($t["js_via_admin"]     ?? "إداري", JSON_UNESCAPED_UNICODE) ?>,
  via_none:     <?= json_encode($t["js_via_none"]      ?? "—", JSON_UNESCAPED_UNICODE) ?>,
  gen_ok:       <?= json_encode($t["js_gen_ok"]        ?? "تم توليد {n} كوبون", JSON_UNESCAPED_UNICODE) ?>,
  copied:       <?= json_encode($t["js_copied"]        ?? "تم النسخ", JSON_UNESCAPED_UNICODE) ?>,
  purge_confirm:<?= json_encode($t["js_purge_confirm"] ?? "حذف كل الكوبونات المستهلكة بالكامل؟", JSON_UNESCAPED_UNICODE) ?>,
  purged:       <?= json_encode($t["js_purged"]        ?? "حُذف {n} كوبون", JSON_UNESCAPED_UNICODE) ?>,
  no_rows:      <?= json_encode($t["js_no_rows"]       ?? "لا توجد سجلات", JSON_UNESCAPED_UNICODE) ?>,
  activated:    <?= json_encode($t["js_activated"]     ?? "تم التفعيل", JSON_UNESCAPED_UNICODE) ?>,
  deactivated:  <?= json_encode($t["js_deactivated"]   ?? "تم الإيقاف", JSON_UNESCAPED_UNICODE) ?>,
  m_users:      <?= json_encode($t["js_m_users"]       ?? "مشترك", JSON_UNESCAPED_UNICODE) ?>,
  m_active:     <?= json_encode($t["js_m_active"]      ?? "فعّال", JSON_UNESCAPED_UNICODE) ?>,
  m_expired:    <?= json_encode($t["js_m_expired"]     ?? "منتهٍ", JSON_UNESCAPED_UNICODE) ?>,
  m_pending:    <?= json_encode($t["js_m_pending"]     ?? "بالانتظار", JSON_UNESCAPED_UNICODE) ?>,
  m_coupons:    <?= json_encode($t["js_m_coupons"]     ?? "كوبون متاح", JSON_UNESCAPED_UNICODE) ?>,
  m_expiring:   <?= json_encode($t["js_m_expiring"]    ?? "ينتهي خلال ٧ أيام", JSON_UNESCAPED_UNICODE) ?>,
  edit:         <?= json_encode($t["js_edit"]          ?? "تعديل", JSON_UNESCAPED_UNICODE) ?>,
  del:          <?= json_encode($t["js_del"]           ?? "حذف", JSON_UNESCAPED_UNICODE) ?>,
  history:      <?= json_encode($t["js_history"]       ?? "السجل", JSON_UNESCAPED_UNICODE) ?>,
  manage:       <?= json_encode($t["js_manage"]        ?? "الاشتراك", JSON_UNESCAPED_UNICODE) ?>,
  stop:         <?= json_encode($t["js_stop"]          ?? "إيقاف", JSON_UNESCAPED_UNICODE) ?>,
  plan_in_use:  <?= json_encode($t["js_plan_in_use"]   ?? "لا يمكن حذف الخطة: مرتبطة بـ {u} مشترك و {c} كوبون فعّال", JSON_UNESCAPED_UNICODE) ?>
};

/* رسائل أخطاء الخادم — مفتاح مختصر من subs_api.php */
const ERRS = {
  invalid_username:  <?= json_encode($t["js_e_username"] ?? "اسم مستخدم غير صالح (٣–٥٠ حرفاً إنجليزياً/رقماً)", JSON_UNESCAPED_UNICODE) ?>,
  weak_password:     <?= json_encode($t["js_e_weakpw"]   ?? "كلمة المرور قصيرة — ٨ أحرف على الأقل", JSON_UNESCAPED_UNICODE) ?>,
  invalid_email:     <?= json_encode($t["js_e_email"]    ?? "بريد إلكتروني غير صالح", JSON_UNESCAPED_UNICODE) ?>,
  username_taken:    <?= json_encode($t["js_e_taken"]    ?? "اسم المستخدم محجوز", JSON_UNESCAPED_UNICODE) ?>,
  bad_code:          <?= json_encode($t["js_e_badcode"]  ?? "المعرّف البرمجي غير صالح", JSON_UNESCAPED_UNICODE) ?>,
  bad_duration:      <?= json_encode($t["js_e_baddur"]   ?? "المدة غير صالحة", JSON_UNESCAPED_UNICODE) ?>,
  bad_name:          <?= json_encode($t["js_e_badname"]  ?? "الاسم العربي مطلوب", JSON_UNESCAPED_UNICODE) ?>,
  bad_date:          <?= json_encode($t["js_e_baddate"]  ?? "تاريخ غير صالح", JSON_UNESCAPED_UNICODE) ?>,
  plan_not_found:    <?= json_encode($t["js_e_noplan"]   ?? "الخطة غير موجودة", JSON_UNESCAPED_UNICODE) ?>,
  user_not_found:    <?= json_encode($t["js_e_nouser"]   ?? "المشترك غير موجود", JSON_UNESCAPED_UNICODE) ?>,
  unauthorized:      <?= json_encode($t["js_e_auth"]     ?? "انتهت الجلسة — أعد تحميل الصفحة", JSON_UNESCAPED_UNICODE) ?>,
  csrf:              <?= json_encode($t["js_e_csrf"]     ?? "انتهت صلاحية الجلسة — أعد تحميل الصفحة", JSON_UNESCAPED_UNICODE) ?>,
  schema_failed:     <?= json_encode($t["js_e_schema"]   ?? "تعذّر إنشاء جداول النظام — راجع صلاحيات قاعدة البيانات", JSON_UNESCAPED_UNICODE) ?>,
  server_error:      <?= json_encode($t["js_e_server"]   ?? "خطأ في الخادم", JSON_UNESCAPED_UNICODE) ?>
};

/* نصوص وسيط إعادة البثّ */
const RS = {
  on:         <?= json_encode($t["js_rs_on"]        ?? "مفعّل", JSON_UNESCAPED_UNICODE) ?>,
  off:        <?= json_encode($t["js_rs_off"]       ?? "موقوف — القنوات تعمل من المصدر مباشرةً", JSON_UNESCAPED_UNICODE) ?>,
  no_ffmpeg:  <?= json_encode($t["js_rs_noffmpeg"]  ?? "ffmpeg غير مثبّت على الخادم", JSON_UNESCAPED_UNICODE) ?>,
  no_exec:    <?= json_encode($t["js_rs_noexec"]    ?? "shell_exec معطّلة في php.ini — أزِلها من disable_functions ثم أعد تشغيل Apache", JSON_UNESCAPED_UNICODE) ?>,
  limit:      <?= json_encode($t["js_rs_limit"]     ?? "حدّ {n} قناة", JSON_UNESCAPED_UNICODE) ?>,
  idle:       <?= json_encode($t["js_rs_idle"]      ?? "إنهاء بعد {n}ث خمول", JSON_UNESCAPED_UNICODE) ?>,
  idle_s:     <?= json_encode($t["js_rs_idles"]     ?? "خمول {n}ث", JSON_UNESCAPED_UNICODE) ?>,
  segs:       <?= json_encode($t["js_rs_segs"]      ?? "مقطع", JSON_UNESCAPED_UNICODE) ?>,
  m_channels: <?= json_encode($t["js_rs_mch"]       ?? "قناة تعمل", JSON_UNESCAPED_UNICODE) ?>,
  m_cpu:      <?= json_encode($t["js_rs_mcpu"]      ?? "معالج · أنوية", JSON_UNESCAPED_UNICODE) ?>,
  m_free:     <?= json_encode($t["js_rs_mfree"]     ?? "مساحة متاحة", JSON_UNESCAPED_UNICODE) ?>,
  turned_on:  <?= json_encode($t["js_rs_onmsg"]     ?? "فُعِّل الوسيط — القنوات الصامتة ستُصلَح تلقائياً", JSON_UNESCAPED_UNICODE) ?>,
  turned_off: <?= json_encode($t["js_rs_offmsg"]    ?? "أُوقف الوسيط وأُنهيت كل العمليات", JSON_UNESCAPED_UNICODE) ?>,
  confirm_stop:<?= json_encode($t["js_rs_confstop"] ?? "إنهاء كل عمليات إعادة البثّ؟ سينقطع البثّ عن المشاهدين الحاليين لحظياً.", JSON_UNESCAPED_UNICODE) ?>,
  stopped:    <?= json_encode($t["js_rs_stopped"]   ?? "أُنهيت {n} قناة", JSON_UNESCAPED_UNICODE) ?>
};

const $$ = id => document.getElementById(id);
const E  = s => String(s==null?'':s).replace(/&/g,'&amp;').replace(/</g,'&lt;')
                 .replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');

/* حالة محلية */
let PLANS = [], CUR = '$', cpPage = 1, suPage = 1, lastCodes = [];

/* ── نداء الواجهة ──
   نقطة نهاية مستقلة (subs_api.php) لا location.href، لأن اللوحة
   تُرجع HTML كاملاً لأي POST غير معروف وسيفشل تحليل JSON بصمت. */
function API(act, data){
  const fd = new FormData();
  fd.append('act', act);
  for (const k in (data||{})) fd.append(k, data[k]==null ? '' : String(data[k]));
  if (window.csrfToken) fd.append('csrf_token', window.csrfToken);
  return fetch('subs_api.php', {method:'POST', body:fd, credentials:'same-origin'})
    .then(r => r.json().catch(() => ({success:false, error:'server_error'})))
    .catch(() => ({success:false, error:'__net'}));
}

/** يحوّل رمز الخطأ إلى نص مفهوم. */
function emsg(d){
  if (!d) return T.err_net;
  if (d.error === '__net') return T.err_net;
  return ERRS[d.error] || d.error || T.err_net;
}

/** تنبيه داخل حاوية. */
function say(id, msg, type){
  const el = $$(id); if (!el) return;
  if (!msg) { el.innerHTML=''; return; }
  const ic = {s:'check-circle', e:'exclamation-circle', i:'info-circle'}[type] || 'info-circle';
  const cl = {s:'al-s', e:'al-e', i:'al-i'}[type] || 'al-i';
  el.innerHTML = '<div class="al '+cl+'" style="margin:0 0 10px"><i class="fas fa-'+ic+'"></i> '+E(msg)+'</div>';
  if (type === 's') setTimeout(() => { if ($$(id)) $$(id).innerHTML=''; }, 4000);
}

/** تاريخ مختصر بالتقويم الميلادي وأرقام لاتينية مهما كانت لغة المتصفح. */
function fdate(s){
  if (!s) return T.never;
  const d = new Date(String(s).replace(' ','T'));
  if (isNaN(d)) return T.never;
  const p = n => String(n).padStart(2,'0');
  return d.getFullYear()+'-'+p(d.getMonth()+1)+'-'+p(d.getDate())+' '+p(d.getHours())+':'+p(d.getMinutes());
}
function fdateShort(s){
  if (!s) return T.never;
  const d = new Date(String(s).replace(' ','T'));
  if (isNaN(d)) return T.never;
  const p = n => String(n).padStart(2,'0');
  return d.getFullYear()+'-'+p(d.getMonth()+1)+'-'+p(d.getDate());
}

// ═══════════════════════════════════════════════════════════════════
//  الخطط
// ═══════════════════════════════════════════════════════════════════

function loadPlans(){
  return API('plans_list').then(d => {
    if (!d.success) { say('planAlert', emsg(d), 'e'); return; }
    PLANS = d.plans || [];
    CUR   = d.currency || '$';
    renderPlans();
    fillPlanSelects();
  });
}

function renderPlans(){
  const g = $$('plansGrid'), em = $$('plansEmpty');
  if (!g) return;
  if (!PLANS.length) { g.innerHTML=''; if(em) em.style.display='block'; return; }
  if (em) em.style.display='none';

  g.innerHTML = PLANS.map(p => {
    const on = Number(p.is_active) === 1;
    return '<div class="sb-card'+(on?'':' off')+'">'
      + '<div class="sb-ribbon '+(on?'sb-rb-on':'sb-rb-off')+'">'+(on?T.st_active:T.st_disabled)+'</div>'
      + '<div class="sb-plan-code">'+E(p.code)+'</div>'
      + '<div class="sb-plan-name">'+E(p.name_ar)+'</div>'
      + '<div class="sb-price">'+E(CUR)+Number(p.price).toFixed(2)+'</div>'
      + '<div class="sb-dur"><i class="fas fa-clock"></i> '+Number(p.duration_days)+' '+T.days+'</div>'
      + '<div class="sb-acts">'
      +   '<button class="sb-btn" onclick="planEdit('+Number(p.id)+')"><i class="fas fa-pen"></i>'+T.edit+'</button>'
      +   '<button class="sb-btn ok" onclick="planToggle('+Number(p.id)+')"><i class="fas fa-power-off"></i>'+(on?T.stop:T.st_active)+'</button>'
      +   '<button class="sb-btn dg" onclick="planDelete('+Number(p.id)+')"><i class="fas fa-trash"></i></button>'
      + '</div></div>';
  }).join('');
}

/** يملأ كل قوائم اختيار الخطة في الصفحة. */
function fillPlanSelects(){
  const opts = PLANS.filter(p => Number(p.is_active) === 1)
    .map(p => '<option value="'+Number(p.id)+'" data-days="'+Number(p.duration_days)+'">'
              + E(p.name_ar) + ' — ' + Number(p.duration_days) + ' ' + T.days + '</option>').join('');
  ['cpMPlan','suPlan','suActPlan'].forEach(id => { const s=$$(id); if(s) s.innerHTML=opts; });

  const f = $$('cpPlan');
  if (f) {
    const cur = f.value;
    f.innerHTML = '<option value="0">'+E(f.dataset.allLabel || f.options[0]?.text || '—')+'</option>'
                + PLANS.map(p=>'<option value="'+Number(p.id)+'">'+E(p.name_ar)+'</option>').join('');
    f.value = cur || '0';
  }
}

window.planNew = function(){
  $$('planId').value=''; $$('planCode').value=''; $$('planNameAr').value='';
  $$('planNameEn').value=''; $$('planNameTr').value='';
  $$('planDays').value=30; $$('planPrice').value=0; $$('planOrder').value=0;
  $$('planActive').checked = true;
  say('planMAlert','');
  const ttl=$$('planMTitle'); if(ttl) ttl.textContent = <?= json_encode($t["new_plan"] ?? "خطة جديدة", JSON_UNESCAPED_UNICODE) ?>;
  OM('planM');
};

window.planEdit = function(id){
  const p = PLANS.find(x => Number(x.id) === Number(id));
  if (!p) return;
  $$('planId').value = p.id;
  $$('planCode').value = p.code;
  $$('planNameAr').value = p.name_ar || '';
  $$('planNameEn').value = p.name_en || '';
  $$('planNameTr').value = p.name_tr || '';
  $$('planDays').value = p.duration_days;
  $$('planPrice').value = p.price;
  $$('planOrder').value = p.sort_order;
  $$('planActive').checked = Number(p.is_active) === 1;
  say('planMAlert','');
  const ttl=$$('planMTitle'); if(ttl) ttl.textContent = p.name_ar || '';
  OM('planM');
};

window.planSave = function(){
  const body = {
    id: $$('planId').value || 0,
    code: $$('planCode').value.trim().toLowerCase(),
    name_ar: $$('planNameAr').value.trim(),
    name_en: $$('planNameEn').value.trim(),
    name_tr: $$('planNameTr').value.trim(),
    duration_days: $$('planDays').value,
    price: $$('planPrice').value,
    sort_order: $$('planOrder').value,
    is_active: $$('planActive').checked ? 1 : ''
  };
  API('plan_save', body).then(d => {
    if (!d.success) { say('planMAlert', emsg(d), 'e'); return; }
    CM('planM'); say('planAlert', T.saved, 's'); loadPlans();
  });
};

window.planToggle = function(id){
  API('plan_toggle', {id:id}).then(d => {
    if (!d.success) { say('planAlert', emsg(d), 'e'); return; }
    loadPlans();
  });
};

window.planDelete = function(id){
  if (!confirm(T.confirm_del)) return;
  API('plan_delete', {id:id}).then(d => {
    if (!d.success) {
      // الخادم يمنع حذف خطة مستخدمة ويعيد العدد — نعرضه بدل رسالة عامة
      if (d.error === 'plan_in_use') {
        say('planAlert', T.plan_in_use.replace('{u}', d.users).replace('{c}', d.coupons), 'e');
      } else say('planAlert', emsg(d), 'e');
      return;
    }
    say('planAlert', T.deleted, 's'); loadPlans();
  });
};

// ═══════════════════════════════════════════════════════════════════
//  الإعدادات
// ═══════════════════════════════════════════════════════════════════

function loadSubsSettings(){
  return API('settings_get').then(d => {
    if (!d.success) return;
    const s = d.settings || {};
    if ($$('setIndexProtection')) $$('setIndexProtection').checked = s.index_protection === '1';
    if ($$('setAllowReg'))        $$('setAllowReg').checked        = s.allow_registration === '1';
    if ($$('setCurSymbol'))       $$('setCurSymbol').value         = s.currency_symbol || '$';
    if ($$('setCurCode'))         $$('setCurCode').value           = s.currency_code || 'USD';
  });
}

window.subsSaveSettings = function(){
  API('settings_save', {
    index_protection:   $$('setIndexProtection').checked ? 1 : '',
    allow_registration: $$('setAllowReg').checked ? 1 : '',
    currency_symbol:    $$('setCurSymbol').value,
    currency_code:      $$('setCurCode').value
  }).then(d => {
    if (!d.success) { say('subsSetAlert', emsg(d), 'e'); return; }
    say('subsSetAlert', T.saved, 's');
    loadPlans();  // رمز العملة قد تغيّر
  });
};

function renderStats(box, s){
  const el = $$(box); if (!el || !s) return;
  const cards = [
    [s.users,        T.m_users,    '#4CC9F0'],
    [s.active,       T.m_active,   '#00D084'],
    [s.expired,      T.m_expired,  '#ff5a63'],
    [s.pending,      T.m_pending,  '#F5A623'],
    [s.coupons_free, T.m_coupons,  '#B36BFF'],
    [s.expiring_7d,  T.m_expiring, '#F5A623']
  ];
  el.innerHTML = cards.map(c =>
    '<div class="sb-mini-c"><div class="sb-mini-v" style="color:'+c[2]+'">'+Number(c[0]||0)+'</div>'
    + '<div class="sb-mini-l">'+E(c[1])+'</div></div>').join('');
}

// ═══════════════════════════════════════════════════════════════════
//  الكوبونات
// ═══════════════════════════════════════════════════════════════════

window.cpLoad = function(page){
  cpPage = page || 1;
  const ld=$$('cpLoading'), wr=$$('cpTblWrap'), em=$$('cpEmpty');
  if(ld) ld.style.display='block'; if(wr) wr.style.display='none'; if(em) em.style.display='none';

  API('coupons_list', {
    q: ($$('cpSearch')||{}).value || '',
    status: ($$('cpStatus')||{}).value || 'all',
    plan_id: ($$('cpPlan')||{}).value || 0,
    page: cpPage
  }).then(d => {
    if(ld) ld.style.display='none';
    if (!d.success) { say('cpAlert', emsg(d), 'e'); return; }
    const rows = d.coupons || [];
    if (!rows.length) { if(em) em.style.display='block'; return; }
    if(wr) wr.style.display='';

    $$('cpBody').innerHTML = rows.map(c => {
      const used = Number(c.used_count), max = Number(c.max_uses);
      const expd = c.expires_at && new Date(String(c.expires_at).replace(' ','T')) < new Date();
      let pill, cls;
      if (used >= max)                  { pill=T.cp_usedup;   cls='sb-p-disabled'; }
      else if (Number(c.is_active)!==1) { pill=T.cp_disabled; cls='sb-p-expired'; }
      else if (expd)                    { pill=T.cp_expired;  cls='sb-p-expired'; }
      else                              { pill=T.cp_available;cls='sb-p-active'; }

      return '<tr>'
        + '<td><span class="sb-code">'+E(c.code)+'</span>'
        +   ' <i class="fas fa-copy" style="cursor:pointer;opacity:.5;margin-inline-start:6px" '
        +   'onclick="cpCopyOne(\''+E(c.code)+'\')" title="'+E(T.copied)+'"></i></td>'
        + '<td>'+E(c.plan_name || '—')+'</td>'
        + '<td>'+Number(c.duration_days)+' '+E(T.days)+'</td>'
        + '<td>'+used+' / '+max+'</td>'
        + '<td><span class="sb-pill '+cls+'">'+E(pill)+'</span></td>'
        + '<td>'+E(c.last_used_by || '—')+'</td>'
        + '<td style="white-space:nowrap;font-size:.72rem;color:var(--t3)">'+E(fdateShort(c.created_at))+'</td>'
        + '<td style="white-space:nowrap;font-size:.72rem;color:var(--t3)">'+E(c.last_used_at?fdate(c.last_used_at):'—')+'</td>'
        + '<td style="white-space:nowrap">'
        +   '<button class="sb-btn" onclick="cpHistory('+Number(c.id)+')" title="'+E(T.history)+'"><i class="fas fa-history"></i></button> '
        +   '<button class="sb-btn ok" onclick="cpToggle('+Number(c.id)+')" title="'+E(T.stop)+'"><i class="fas fa-power-off"></i></button> '
        +   '<button class="sb-btn dg" onclick="cpDelete('+Number(c.id)+')" title="'+E(T.del)+'"><i class="fas fa-trash"></i></button>'
        + '</td></tr>';
    }).join('');

    const cnt=$$('cpCount'); if(cnt) cnt.textContent = d.total + ' — ' + T.m_coupons;
    pager('cpPager', d.total, d.per, d.page, 'cpLoad');
  });
};

function pager(box, total, per, page, fn){
  const el=$$(box); if(!el) return;
  const pages = Math.ceil(total / per);
  if (pages <= 1) { el.innerHTML=''; return; }
  let h='';
  const from = Math.max(1, page-2), to = Math.min(pages, page+2);
  if (from > 1) h += '<button class="sb-btn" onclick="'+fn+'(1)">1</button>';
  if (from > 2) h += '<span style="color:var(--t3);padding:0 4px">…</span>';
  for (let i=from;i<=to;i++)
    h += '<button class="sb-btn'+(i===page?' ok':'')+'" style="'+(i===page?'border-color:#00D084;color:#00D084':'')+'" onclick="'+fn+'('+i+')">'+i+'</button>';
  if (to < pages-1) h += '<span style="color:var(--t3);padding:0 4px">…</span>';
  if (to < pages) h += '<button class="sb-btn" onclick="'+fn+'('+pages+')">'+pages+'</button>';
  el.innerHTML = h;
}

window.cpNewModal = function(){
  $$('cpForm').style.display='';
  $$('cpResult').style.display='none';
  $$('cpMSubmit').style.display='';
  say('cpMAlert','');
  if (!PLANS.length) loadPlans().then(()=>OM('cpM')); else OM('cpM');
};

window.cpGenerate = function(){
  const btn = $$('cpMSubmit');
  btn.disabled = true;
  API('coupons_create', {
    plan_id: $$('cpMPlan').value,
    count: $$('cpMCount').value,
    max_uses: $$('cpMUses').value,
    expires_at: $$('cpMExp').value,
    note: $$('cpMNote').value
  }).then(d => {
    btn.disabled = false;
    if (!d.success) { say('cpMAlert', emsg(d), 'e'); return; }
    lastCodes = d.codes || [];
    $$('cpForm').style.display='none';
    $$('cpMSubmit').style.display='none';
    $$('cpResult').style.display='';
    $$('cpResultMsg').textContent = T.gen_ok.replace('{n}', d.count);
    $$('cpCodesOut').textContent = lastCodes.join('\n');
    cpLoad(1);
  });
};

window.cpCopyCodes = function(){
  copyText(lastCodes.join('\n'));
  say('cpAlert', T.copied, 's');
};
window.cpCopyOne = function(code){ copyText(code); say('cpAlert', T.copied + ': ' + code, 's'); };

/** نسخ يعمل أيضاً على http:// حيث clipboard API غير متاح. */
function copyText(txt){
  if (navigator.clipboard && window.isSecureContext) {
    navigator.clipboard.writeText(txt).catch(()=>fallbackCopy(txt));
  } else fallbackCopy(txt);
}
function fallbackCopy(txt){
  const ta = document.createElement('textarea');
  ta.value = txt;
  ta.style.cssText = 'position:fixed;top:-1000px;opacity:0';
  document.body.appendChild(ta); ta.select();
  try { document.execCommand('copy'); } catch(e){}
  document.body.removeChild(ta);
}

window.cpDownloadCodes = function(){
  downloadFile('coupons-'+new Date().toISOString().slice(0,10)+'.txt', lastCodes.join('\r\n'));
};

function downloadFile(name, content, mime){
  // BOM لكي يفتح Excel العربية والرموز بترميز صحيح
  const blob = new Blob(['﻿'+content], {type: mime || 'text/plain;charset=utf-8'});
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob); a.download = name;
  document.body.appendChild(a); a.click();
  setTimeout(()=>{ URL.revokeObjectURL(a.href); a.remove(); }, 100);
}

window.cpToggle = function(id){ API('coupon_toggle',{id:id}).then(d=>{ if(!d.success) say('cpAlert',emsg(d),'e'); else cpLoad(cpPage); }); };
window.cpDelete = function(id){
  if(!confirm(T.confirm_del)) return;
  API('coupon_delete',{id:id}).then(d=>{ if(!d.success) say('cpAlert',emsg(d),'e'); else { say('cpAlert',T.deleted,'s'); cpLoad(cpPage); } });
};
window.cpPurgeUsed = function(){
  if(!confirm(T.purge_confirm)) return;
  API('coupons_delete_used').then(d=>{
    if(!d.success){ say('cpAlert',emsg(d),'e'); return; }
    say('cpAlert', T.purged.replace('{n}', d.deleted), 's'); cpLoad(1);
  });
};

window.cpHistory = function(id){
  API('coupon_history',{id:id}).then(d=>{
    if(!d.success){ say('cpAlert',emsg(d),'e'); return; }
    const rows = d.rows||[];
    $$('subsLogBody').innerHTML = rows.length
      ? rows.map(r=>'<tr><td>'+E(r.uname||r.username||'—')+'</td><td>'+Number(r.days_added)+' '+E(T.days)
          +'</td><td>'+E(fdate(r.redeemed_at))+'</td><td style="color:var(--t3);font-size:.72rem">'+E(r.ip||'')+'</td></tr>').join('')
      : '<tr><td style="text-align:center;padding:30px;color:var(--t3)">'+E(T.no_rows)+'</td></tr>';
    $$('subsLogTitle').textContent = T.history;
    OM('subsLogM');
  });
};

window.cpExport = function(){
  API('coupons_list', {
    q: ($$('cpSearch')||{}).value||'', status:($$('cpStatus')||{}).value||'all',
    plan_id:($$('cpPlan')||{}).value||0, page:1
  }).then(d=>{
    if(!d.success) return;
    const head = ['code','plan','days','used','max','active','last_user','created','used_at'];
    const body = (d.coupons||[]).map(c=>[c.code,c.plan_name||'',c.duration_days,c.used_count,
                                        c.max_uses,c.is_active,c.last_used_by||'',c.created_at,c.last_used_at||'']);
    downloadFile('coupons.csv', toCsv(head, body), 'text/csv;charset=utf-8');
  });
};

/** يبني CSV مع تهريب صحيح — الملاحظات قد تحوي فواصل أو اقتباسات. */
function toCsv(head, rows){
  const q = v => '"' + String(v==null?'':v).replace(/"/g,'""') + '"';
  return [head.map(q).join(',')].concat(rows.map(r => r.map(q).join(','))).join('\r\n');
}

// ═══════════════════════════════════════════════════════════════════
//  المشتركون
// ═══════════════════════════════════════════════════════════════════

const STATE_PILL = {
  active:   ['sb-p-active',   () => T.st_active],
  expired:  ['sb-p-expired',  () => T.st_expired],
  pending:  ['sb-p-pending',  () => T.st_pending],
  disabled: ['sb-p-disabled', () => T.st_disabled]
};
const VIA = { coupon: () => T.via_coupon, admin: () => T.via_admin, none: () => T.via_none };

window.suLoad = function(page){
  suPage = page || 1;
  const ld=$$('suLoading'), wr=$$('suTblWrap'), em=$$('suEmpty');
  if(ld) ld.style.display='block'; if(wr) wr.style.display='none'; if(em) em.style.display='none';

  API('subs_list', {
    q: ($$('suSearch')||{}).value||'',
    status: ($$('suStatus')||{}).value||'all',
    page: suPage
  }).then(d=>{
    if(ld) ld.style.display='none';
    if(!d.success){ say('suAlert', emsg(d), 'e'); return; }
    renderStats('suStatsBox', d.stats);
    renderStats('subsStatsBox', d.stats);

    const rows = d.users||[];
    if(!rows.length){ if(em) em.style.display='block'; return; }
    if(wr) wr.style.display='';

    $$('suBody').innerHTML = rows.map(u=>{
      const st  = STATE_PILL[u.state] || STATE_PILL.pending;
      const dl  = Number(u.days_left);
      const dtx = dl < 0 ? T.unlimited : (dl > 0 ? dl + ' ' + T.days : '0');
      const dcol = dl < 0 ? '#4CC9F0' : (dl === 0 ? '#ff5a63' : (dl <= 7 ? '#F5A623' : '#00D084'));
      const on  = Number(u.is_active) === 1;
      return '<tr>'
        + '<td><b style="color:var(--t1)">'+E(u.username)+'</b>'
        +   (u.email?'<div style="font-size:.7rem;color:var(--t3)">'+E(u.email)+'</div>':'')+'</td>'
        + '<td>'+E(u.plan_name||'—')+'</td>'
        + '<td><span class="sb-pill '+st[0]+'">'+E(st[1]())+'</span></td>'
        + '<td style="white-space:nowrap;font-size:.72rem;color:var(--t3)">'+E(fdateShort(u.sub_start))+'</td>'
        + '<td style="white-space:nowrap;font-size:.72rem;color:var(--t3)">'+E(fdateShort(u.sub_end))+'</td>'
        + '<td style="font-weight:800;color:'+dcol+';white-space:nowrap">'+E(dtx)+'</td>'
        + '<td style="font-size:.72rem">'+E((VIA[u.activated_via]||VIA.none)())+'</td>'
        + '<td style="white-space:nowrap;font-size:.72rem;color:var(--t3)">'+E(u.last_login?fdate(u.last_login):'—')+'</td>'
        + '<td style="white-space:nowrap">'
        +   '<button class="sb-btn ok" onclick="suManage('+Number(u.id)+')" title="'+E(T.manage)+'"><i class="fas fa-bolt"></i></button> '
        +   '<button class="sb-btn" onclick="suEdit('+Number(u.id)+')" title="'+E(T.edit)+'"><i class="fas fa-pen"></i></button> '
        +   (on ? '<button class="sb-btn dg" onclick="suDeactivate('+Number(u.id)+')" title="'+E(T.stop)+'"><i class="fas fa-ban"></i></button> ' : '')
        +   '<button class="sb-btn" onclick="suShowLogs('+Number(u.id)+')" title="'+E(T.history)+'"><i class="fas fa-history"></i></button> '
        +   '<button class="sb-btn dg" onclick="suDelete('+Number(u.id)+')" title="'+E(T.del)+'"><i class="fas fa-trash"></i></button>'
        + '</td></tr>';
    }).join('');

    const cnt=$$('suCount'); if(cnt) cnt.textContent = d.total + ' — ' + T.m_users;
    pager('suPager', d.total, d.per, d.page, 'suLoad');
    window.__suRows = rows;
  });
};

window.suNewModal = function(){
  $$('suId').value=''; $$('suUsername').value=''; $$('suEmail').value='';
  $$('suPassword').value=''; $$('suNotes').value='';
  $$('suUsername').disabled = false;
  $$('suActivateNow').checked = false;
  $$('suActivateFields').style.display='none';
  $$('suActivateBox').style.display='';
  $$('suPwLabel').textContent = <?= json_encode($t["su_password"] ?? "كلمة المرور", JSON_UNESCAPED_UNICODE) ?>;
  say('suMAlert','');
  if(!PLANS.length) loadPlans().then(()=>OM('suM')); else OM('suM');
};

window.suEdit = function(id){
  const u = (window.__suRows||[]).find(x => Number(x.id) === Number(id));
  if(!u) return;
  $$('suId').value = u.id;
  $$('suUsername').value = u.username;
  // اسم المستخدم غير قابل للتعديل: هو المفتاح الذي يعرفه المشترك ويسجّل به،
  // وتغييره من اللوحة يقطعه عن حسابه بلا إشعار.
  $$('suUsername').disabled = true;
  $$('suEmail').value = u.email || '';
  $$('suPassword').value = '';
  $$('suNotes').value = u.notes || '';
  $$('suActivateBox').style.display = 'none';
  $$('suPwLabel').textContent = <?= json_encode($t["su_password_opt"] ?? "كلمة مرور جديدة (اتركها فارغة لعدم التغيير)", JSON_UNESCAPED_UNICODE) ?>;
  say('suMAlert','');
  OM('suM');
};

window.suToggleActivate = function(){
  $$('suActivateFields').style.display = $$('suActivateNow').checked ? '' : 'none';
};
window.suPlanChanged = function(){
  const o = $$('suPlan').selectedOptions[0];
  if (o && o.dataset.days) $$('suDays').value = o.dataset.days;
};
window.suActPlanChanged = function(){
  const o = $$('suActPlan').selectedOptions[0];
  if (o && o.dataset.days) $$('suActDays').value = o.dataset.days;
};

window.suSave = function(){
  const id = $$('suId').value;
  if (id) {
    API('sub_update', {
      id: id, email: $$('suEmail').value.trim(),
      notes: $$('suNotes').value, password: $$('suPassword').value
    }).then(d=>{
      if(!d.success){ say('suMAlert', emsg(d), 'e'); return; }
      CM('suM'); say('suAlert', T.saved, 's'); suLoad(suPage);
    });
  } else {
    API('sub_create', {
      username: $$('suUsername').value.trim(),
      password: $$('suPassword').value,
      email: $$('suEmail').value.trim(),
      activate: $$('suActivateNow').checked ? 1 : '',
      plan_id: $$('suPlan').value || 0,
      days: $$('suDays').value || 0
    }).then(d=>{
      if(!d.success){ say('suMAlert', emsg(d), 'e'); return; }
      CM('suM'); say('suAlert', T.saved, 's'); suLoad(1);
    });
  }
};

window.suManage = function(id){
  const u = (window.__suRows||[]).find(x=>Number(x.id)===Number(id));
  $$('suActId').value = id;
  $$('suActName').textContent = u ? u.username : '';
  if (u && u.plan_id) $$('suActPlan').value = u.plan_id;
  suActPlanChanged();
  say('suActAlert','');
  if(!PLANS.length) loadPlans().then(()=>OM('suActM')); else OM('suActM');
};

window.suActivateSave = function(){
  API('sub_activate', {
    id: $$('suActId').value,
    plan_id: $$('suActPlan').value || 0,
    days: $$('suActDays').value
  }).then(d=>{
    if(!d.success){ say('suActAlert', emsg(d), 'e'); return; }
    CM('suActM'); say('suAlert', T.activated, 's'); suLoad(suPage);
  });
};

window.suExtend = function(days){
  API('sub_extend', {id: $$('suActId').value, days: days}).then(d=>{
    if(!d.success){ say('suActAlert', emsg(d), 'e'); return; }
    say('suActAlert', T.saved + ' — ' + fdate(d.sub_end), 's');
    suLoad(suPage);
  });
};

window.suDeactivate = function(id){
  API('sub_deactivate',{id:id}).then(d=>{
    if(!d.success){ say('suAlert', emsg(d), 'e'); return; }
    say('suAlert', T.deactivated, 's'); suLoad(suPage);
  });
};

window.suDelete = function(id){
  if(!confirm(T.confirm_del)) return;
  API('sub_delete',{id:id}).then(d=>{
    if(!d.success){ say('suAlert', emsg(d), 'e'); return; }
    say('suAlert', T.deleted, 's'); suLoad(suPage);
  });
};

window.suShowLogs = function(id){
  API('sub_logs', {id: id||0}).then(d=>{
    if(!d.success){ say('suAlert', emsg(d), 'e'); return; }
    const rows = d.rows||[];
    const col = {success:'#00D084', fail:'#ff5a63', blocked:'#F5A623', register:'#4CC9F0', expired:'#999'};
    $$('subsLogBody').innerHTML = rows.length
      ? rows.map(r=>'<tr><td><b>'+E(r.username||'—')+'</b></td>'
          +'<td style="color:'+(col[r.status]||'#999')+';font-weight:700;font-size:.72rem">'+E(r.status)+'</td>'
          +'<td style="font-size:.72rem;color:var(--t3)">'+E(r.ip||'')+'</td>'
          +'<td style="white-space:nowrap;font-size:.72rem;color:var(--t3)">'+E(fdate(r.created_at))+'</td></tr>').join('')
      : '<tr><td style="text-align:center;padding:30px;color:var(--t3)">'+E(T.no_rows)+'</td></tr>';
    $$('subsLogTitle').textContent = <?= json_encode($t["site_login_logs"] ?? "سجل دخول المشتركين", JSON_UNESCAPED_UNICODE) ?>;
    OM('subsLogM');
  });
};

window.suExport = function(){
  API('subs_list',{q:($$('suSearch')||{}).value||'',status:($$('suStatus')||{}).value||'all',page:1}).then(d=>{
    if(!d.success) return;
    const head=['username','email','plan','state','days_left','start','end','via','last_login'];
    const body=(d.users||[]).map(u=>[u.username,u.email||'',u.plan_name||'',u.state,u.days_left,
                                    u.sub_start||'',u.sub_end||'',u.activated_via,u.last_login||'']);
    downloadFile('subscribers.csv', toCsv(head, body), 'text/csv;charset=utf-8');
  });
};

// ═══════════════════════════════════════════════════════════════════
//  التهيئة
// ═══════════════════════════════════════════════════════════════════

/* بحث مؤجَّل: بلا هذا يُطلق كل حرف طلباً للخادم، فتصل عشرة طلبات
   متسابقة وقد يصل ردّ الأقدم أخيراً فيعرض نتائج لا تطابق ما كُتب. */
function debounce(fn, ms){
  let tm; return function(){ clearTimeout(tm); tm = setTimeout(fn, ms||350); };
}

// ═══════════════════════════════════════════════════════════════════
//  وسيط إعادة البثّ
// ═══════════════════════════════════════════════════════════════════

let rsTimer = null;

window.rsLoadStatus = function(){
  return API('restream_status').then(d => {
    if (!d.success) { say('rsAlert', emsg(d), 'e'); return; }
    const r = d.restream || {};
    const sw = $$('setRestream');
    if (sw) {
      sw.checked = !!r.enabled;
      // قفل صلب من الطرفية: نعطّل المفتاح ونشرح، فلا يبدو معطّلاً بصمت
      sw.disabled = !!r.hard_off;
    }

    // نملأ حقل الحدّ بالقيمة الحالية — ما لم يكن المستخدم يكتب فيه الآن
    const li = $$('rsLimitInput');
    if (li && document.activeElement !== li && r.max) li.value = r.max;

    const note = $$('rsStateNote');
    if (note) {
      if (r.hard_off) {
        // ليس عطلاً: إيقاف صلب مقصود من الطرفية، ولا يُلغى إلا منها
        note.innerHTML = '<span style="color:#F5A623">■ '
          + E(RS.hard_off || 'موقوف إيقافاً صلباً من الطرفية — الزرّ معطّل عمداً.')
          + '</span><br><span style="color:#8a8a94;font-size:.8em">'
          + E(RS.hard_off_fix || 'لإلغائه: sudo bash /var/www/html/iptv/setup_restream.sh')
          + '</span>';
      } else if (r.can_exec === false) {
        note.innerHTML = '<span style="color:#e50914">'
          + E(RS.no_exec) + '</span>';
      } else if (!r.ffmpeg) {
        note.innerHTML = '<span style="color:#e50914">'
          + E(RS.no_ffmpeg) + '</span> <code style="background:#0a0a0c;padding:2px 6px;border-radius:4px">sudo apt install -y ffmpeg</code>';
      } else if (r.enabled) {
        note.innerHTML = '<span style="color:#00D084">● ' + E(RS.on) + '</span> — '
          + E(RS.limit.replace('{n}', r.max)) + ' · ' + E(RS.idle.replace('{n}', r.idle));
      } else {
        note.textContent = RS.off;
      }
    }

    // القنوات العاملة
    const live = (r.channels || []).filter(c => c.alive);
    const row = $$('rsLiveRow'), list = $$('rsLiveList'), stop = $$('rsStopBtn');
    if (row) row.style.display = live.length ? '' : 'none';
    if (stop) stop.style.display = live.length ? '' : 'none';
    if (list && live.length) {
      list.innerHTML = live.map(c =>
        '<span style="display:inline-block;margin:3px 0 3px 8px;padding:3px 10px;border-radius:20px;'
        + 'background:rgba(0,208,132,.12);color:#00D084;font-size:.72rem;font-weight:700">'
        + E(c.segments) + ' ' + E(RS.segs) + ' · ' + E(RS.idle_s.replace('{n}', c.idle_s))
        + '</span>').join('');
    }

    // إحصاءات
    const box = $$('rsStatsBox');
    if (box) {
      if (!r.enabled && !live.length) { box.innerHTML = ''; }
      else {
        const cards = [
          [live.length + ' / ' + r.max, RS.m_channels, '#00D084'],
          [(r.cpu || 0) + '%',          RS.m_cpu + ' (' + (r.cores||1) + ')', (r.cpu > (r.cores||1)*70) ? '#e50914' : '#4CC9F0'],
          [(r.free_mb || 0) + 'MB',     RS.m_free, '#B36BFF']
        ];
        box.innerHTML = cards.map(c =>
          '<div class="sb-mini-c"><div class="sb-mini-v" style="color:' + c[2] + ';font-size:1.15rem">'
          + E(c[0]) + '</div><div class="sb-mini-l">' + E(c[1]) + '</div></div>').join('');
      }
    }
  });
};

window.rsToggle = function(){
  const on = $$('setRestream').checked;
  API('restream_toggle', { on: on ? 1 : '' }).then(d => {
    if (!d.success) {
      $$('setRestream').checked = !on;   // نُعيد المفتاح لموضعه الحقيقي
      say('rsAlert', d.error === 'ffmpeg_missing' ? RS.no_ffmpeg : emsg(d), 'e');
      return;
    }
    say('rsAlert', on ? RS.turned_on : RS.turned_off, 's');
    rsLoadStatus();
  });
};

window.rsStopAll = function(){
  if (!confirm(RS.confirm_stop)) return;
  API('restream_stop_all').then(d => {
    if (!d.success) { say('rsAlert', emsg(d), 'e'); return; }
    say('rsAlert', RS.stopped.replace('{n}', d.stopped), 's');
    rsLoadStatus();
  });
};

/* حفظ حدّ القنوات من اللوحة — بلا طرفية ولا sudo.
   يُطبَّق فوراً لأن rsMaxChannels يقرأ من قاعدة البيانات. */
window.rsSaveLimit = function(){
  const li = $$('rsLimitInput'); if (!li) return;
  const n = parseInt(li.value, 10);
  if (!(n >= 1 && n <= 500)) { say('rsAlert', RS.limit_bad || 'أدخل رقماً بين 1 و 500', 'e'); return; }
  const btn = $$('rsLimitSaveBtn'); if (btn) btn.disabled = true;
  API('restream_set_limit', { limit: n }).then(d => {
    if (btn) btn.disabled = false;
    if (!d.success) { say('rsAlert', emsg(d), 'e'); return; }
    if (d.max) li.value = d.max;
    say('rsAlert', (RS.limit_saved || 'حُفظ الحدّ: {n} قناة').replace('{n}', d.max), 's');
    rsLoadStatus();
  });
};

/* تحديث دوري أثناء فتح القسم فقط.
   مؤقّت يعمل دائماً يُرسل طلباً كل 10 ثوانٍ إلى الأبد حتى وأنت في قسم
   آخر — نوقفه عند مغادرة القسم. */
function rsStartPolling(){
  rsStopPolling();
  rsTimer = setInterval(function(){
    const sec = document.getElementById('subscriptions');
    if (!sec || !sec.classList.contains('on')) { rsStopPolling(); return; }
    rsLoadStatus();
  }, 10000);
}
function rsStopPolling(){ if (rsTimer) { clearInterval(rsTimer); rsTimer = null; } }

window.loadSubscriptions = function(){
  loadPlans(); loadSubsSettings();
  API('stats').then(d=>{ if(d.success) renderStats('subsStatsBox', d.stats); });
  rsLoadStatus(); rsStartPolling();
};
window.loadCoupons      = function(){ if(!PLANS.length) loadPlans().then(()=>cpLoad(1)); else cpLoad(1); };
window.loadSubscribers  = function(){ if(!PLANS.length) loadPlans().then(()=>suLoad(1)); else suLoad(1); };

document.addEventListener('DOMContentLoaded', function(){
  const cs=$$('cpSearch'); if(cs) cs.addEventListener('input', debounce(()=>cpLoad(1)));
  const ss=$$('suSearch'); if(ss) ss.addEventListener('input', debounce(()=>suLoad(1)));
  const cf=$$('cpPlan');   if(cf) cf.dataset.allLabel = cf.options[0] ? cf.options[0].text : '—';
});

})();
</script>
