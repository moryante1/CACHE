#!/usr/bin/env bash
# ═══════════════════════════════════════════════════════════════════════════
#  فحص جاهزية الإصدار — Shashety Pro
# ───────────────────────────────────────────────────────────────────────────
#  يفحص كل ما يجب أن يكون صحيحاً قبل اعتماد النسخة نهائية، ويحذف ما لا
#  ينبغي بقاؤه على خادم إنتاج.
#
#      sudo bash release_check.sh          فحص فقط (لا يحذف شيئاً)
#      sudo bash release_check.sh --fix    يحذف الملفات الخطرة ويصلح الصلاحيات
#
#  لماذا موجود: أدوات التشخيص والتثبيت تكشف عمداً ما نُخفيه عادةً. كتابة
#  «احذفه بعد الانتهاء» في رأس كل ملف أمنيةٌ لا ضابط — الملفات تُنسى،
#  وخصوصاً بعد أن تُحلّ المشكلة فينصرف الذهن عنها.
# ═══════════════════════════════════════════════════════════════════════════
set -uo pipefail

R=$'\e[31m'; G=$'\e[32m'; Y=$'\e[33m'; C=$'\e[36m'; B=$'\e[1m'; N=$'\e[0m'
ok(){   printf '  %s✔%s %s\n' "$G" "$N" "$*"; }
bad(){  printf '  %s✘%s %s\n' "$R" "$N" "$*"; ((FAIL++)); }
warn(){ printf '  %s⚠%s %s\n' "$Y" "$N" "$*"; ((WARN++)); }
inf(){  printf '  %s•%s %s\n' "$C" "$N" "$*"; }
head_(){ printf '\n%s%s══ %s ══%s\n' "$B" "$C" "$*" "$N"; }

APP_DIR="${APP_DIR:-$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)}"
FIX=0; [[ "${1:-}" == "--fix" ]] && FIX=1
FAIL=0; WARN=0
cd "$APP_DIR" || { echo "المجلد غير موجود"; exit 1; }

# ═════════════════════════════════════════════════════════════════
head_ "١) ملفات يجب ألا تبقى على خادم إنتاج"
# ═════════════════════════════════════════════════════════════════
# أدوات تشخيص أنشأناها أثناء حلّ المشاكل — تكشف بنية النظام وبيانات
# الاتصال وحالة الجلسات. لها حارس يمنع الوصول من الإنترنت، لكن أفضل
# حارس هو ألا يكون الملف موجوداً.
DIAG=(db_check.php subs_check.php gate_check.php capacity_check.php stream_audio_check.php)

# ملفات تثبيت خطرة بطبيعتها: تنشئ حسابات أو تنفّذ SQL أو تعيد ضبط النظام.
DANGER=(setup.php loader.php fix_db.php install.php migration.php)

for f in "${DIAG[@]}"; do
  if [[ -f "$f" ]]; then
    if [[ $FIX -eq 1 ]]; then rm -f "$f" && ok "حُذفت أداة التشخيص $f"
    else warn "أداة تشخيص باقية: $f"; fi
  fi
done

for f in "${DANGER[@]}"; do
  if [[ -f "$f" ]]; then
    if [[ $FIX -eq 1 ]]; then
      mv "$f" "$f.disabled" && ok "عُطِّل $f (أُعيدت تسميته)"
    else bad "ملف تثبيت خطر: $f"; fi
  fi
done

[[ -f health.php ]] && inf "health.php موجود — احتفظ به إن كنت تراقب، وإلا احذفه"

# ═════════════════════════════════════════════════════════════════
head_ "٢) ملفات لا ينبغي أن تكون في جذر الويب"
# ═════════════════════════════════════════════════════════════════
shopt -s nullglob
JUNK=( *.rar *.zip *.tar.gz *.sql *.bak *.bak.* *.orig *.disabled~ )
shopt -u nullglob
if [[ ${#JUNK[@]} -gt 0 ]]; then
  for f in "${JUNK[@]}"; do
    SZ=$(du -h "$f" 2>/dev/null | cut -f1)
    if [[ $FIX -eq 1 ]]; then
      mkdir -p "$APP_DIR/_archive" && mv "$f" "$APP_DIR/_archive/" && ok "نُقل $f ($SZ) إلى _archive"
    else warn "أرشيف في جذر الويب: $f ($SZ)"; fi
  done
else ok "لا أرشيفات في الجذر"; fi

for d in Xp xp _backup_before_fix server; do
  [[ -d "$d" ]] && warn "مجلد نسخة كاملة: $d ($(du -sh "$d" 2>/dev/null | cut -f1)) — انقله خارج جذر الويب"
done

# ═════════════════════════════════════════════════════════════════
head_ "٣) ملف .env"
# ═════════════════════════════════════════════════════════════════
if [[ ! -f .env ]]; then
  bad ".env مفقود — سيستخدم النظام كلمة المرور الافتراضية"
else
  WEBUSER="www-data"; id -u "$WEBUSER" >/dev/null 2>&1 || WEBUSER="apache"
  PERM=$(stat -c%a .env)
  OWNER=$(stat -c%U .env)

  # الملف يجب أن يقرأه الويب ولا يقرأه غيره.
  # ⚠ 600 مملوك لـ root عطّل الموقع سابقاً: Apache لا يقرأه فترتدّ
  #   الإعدادات إلى القيم الافتراضية بصمت.
  if [[ "$OWNER" != "$WEBUSER" ]]; then
    if [[ $FIX -eq 1 ]]; then
      chown "$WEBUSER:$WEBUSER" .env && chmod 640 .env && ok "ضُبطت ملكية .env"
    else bad ".env مملوك لـ $OWNER لا $WEBUSER — الويب قد لا يقرأه"; fi
  elif [[ "$PERM" != "640" && "$PERM" != "600" ]]; then
    if [[ $FIX -eq 1 ]]; then chmod 640 .env && ok "ضُبطت صلاحية .env إلى 640"
    else warn ".env بصلاحية $PERM — الأفضل 640"; fi
  else ok ".env: $OWNER بصلاحية $PERM"; fi

  # كلمة مرور افتراضية
  if grep -q '^DB_PASS=123456$' .env 2>/dev/null; then
    bad "DB_PASS ما زالت 123456 — شغّل fix_db_password.sh"
  else ok "كلمة مرور قاعدة البيانات ليست الافتراضية"; fi

  for k in APP_KEY WS_SECRET HEALTH_KEY; do
    V=$(grep -m1 "^$k=" .env 2>/dev/null | cut -d= -f2-)
    [[ -z "$V" ]] && warn "$k فارغ في .env"
  done
fi

# ═════════════════════════════════════════════════════════════════
head_ "٤) صحّة صياغة كل ملفات PHP"
# ═════════════════════════════════════════════════════════════════
if command -v php >/dev/null 2>&1; then
  ERRS=0; TOTAL=0
  while IFS= read -r f; do
    ((TOTAL++))
    OUT="$(php -l "$f" 2>&1)" || { bad "خطأ صياغة: $f"; echo "      ${OUT}" | head -2; ((ERRS++)); }
  done < <(find . -name '*.php' \
             -not -path './Xp/*' -not -path './_backup_before_fix/*' \
             -not -path './server/*' -not -path './_archive/*' \
             -not -path './node_modules/*' -not -path './vendor/*')
  [[ $ERRS -eq 0 ]] && ok "$TOTAL ملف PHP سليم"
else
  warn "php غير متاح في PATH — تُخطّي فحص الصياغة"
fi

# ═════════════════════════════════════════════════════════════════
head_ "٥) صلاحيات المجلدات"
# ═════════════════════════════════════════════════════════════════
WEBUSER="www-data"; id -u "$WEBUSER" >/dev/null 2>&1 || WEBUSER="apache"
for d in storage uploads cache storage/vod; do
  [[ -d "$d" ]] || { [[ $FIX -eq 1 ]] && mkdir -p "$d"; }
  if [[ -d "$d" ]]; then
    if [[ -w "$d" ]] || sudo -u "$WEBUSER" test -w "$d" 2>/dev/null; then
      ok "$d قابل للكتابة"
    else
      if [[ $FIX -eq 1 ]]; then
        chown -R "$WEBUSER:$WEBUSER" "$d" && chmod -R 775 "$d" && ok "أُصلحت صلاحيات $d"
      else bad "$d غير قابل للكتابة لـ $WEBUSER"; fi
    fi
  fi
done

# ═════════════════════════════════════════════════════════════════
head_ "٦) الحماية عبر HTTP"
# ═════════════════════════════════════════════════════════════════
if [[ -f .htaccess ]]; then
  ok ".htaccess موجود"
  grep -q 'FilesMatch' .htaccess && ok "قواعد حظر الملفات الحسّاسة مفعّلة" \
    || warn ".htaccess بلا قواعد FilesMatch"
else bad ".htaccess مفقود — ملفات .env و.sql قد تكون قابلة للتنزيل"; fi

# اختبار حيّ إن كان الموقع يعمل محلياً
if command -v curl >/dev/null 2>&1; then
  for p in .env storage/.appkey database.sql; do
    CODE=$(curl -s -o /dev/null -w '%{http_code}' --max-time 4 "http://localhost/$(basename "$APP_DIR")/$p" 2>/dev/null || echo 000)
    case "$CODE" in
      200) bad "‼ $p قابل للتنزيل عبر HTTP (رمز 200)" ;;
      403|404) ok "$p محجوب ($CODE)" ;;
      *) inf "$p — تعذّر الفحص ($CODE)" ;;
    esac
  done
fi

# ═════════════════════════════════════════════════════════════════
head_ "٧) HTTPS"
# ═════════════════════════════════════════════════════════════════
if ls /etc/letsencrypt/live/*/fullchain.pem >/dev/null 2>&1; then
  ok "شهادة Let's Encrypt موجودة"
else
  warn "لا شهادة HTTPS — كلمات مرور المشتركين تُرسَل بلا تشفير"
  echo "      sudo certbot --apache -d نطاقك.com"
fi

# ═════════════════════════════════════════════════════════════════
head_ "٨) المهام الدورية"
# ═════════════════════════════════════════════════════════════════
[[ -f /etc/cron.d/shashety-restream ]] \
  && ok "مهمة تنظيف إعادة البثّ مجدولة" \
  || warn "مهمة التنظيف غير مجدولة — شغّل setup_restream.sh"
systemctl is-active --quiet cron 2>/dev/null && ok "خدمة cron تعمل" || warn "cron متوقفة"

# ═════════════════════════════════════════════════════════════════
head_ "النتيجة"
# ═════════════════════════════════════════════════════════════════
printf '  أخطاء: %s%d%s   تحذيرات: %s%d%s\n\n' \
  "$([[ $FAIL -gt 0 ]] && echo "$R" || echo "$G")" "$FAIL" "$N" \
  "$([[ $WARN -gt 0 ]] && echo "$Y" || echo "$G")" "$WARN" "$N"

if [[ $FAIL -eq 0 && $WARN -eq 0 ]]; then
  printf '  %s%sالنسخة جاهزة للإنتاج.%s\n' "$B" "$G" "$N"
elif [[ $FAIL -eq 0 ]]; then
  printf '  %sلا أخطاء حرجة — راجع التحذيرات أعلاه.%s\n' "$Y" "$N"
else
  printf '  %sيوجد ما يجب إصلاحه قبل الاعتماد.%s\n' "$R" "$N"
  [[ $FIX -eq 0 ]] && echo "  جرّب:  sudo bash $0 --fix"
fi
echo
exit $(( FAIL > 0 ? 1 : 0 ))
