#!/usr/bin/env bash
# ═══════════════════════════════════════════════════════════════════════════
#  إصلاح الصلاحيات تلقائياً — Shashety Pro
# ───────────────────────────────────────────────────────────────────────────
#  يُشغَّل من cron كل خمس دقائق، ولا يحتاج منك شيئاً.
#
#  لماذا موجود:
#  ترفع الملفات من ويندوز عبر FTP/SMB، فتصل أحياناً بمالك خاطئ أو نمط
#  خاطئ. النتيجة أخطاء "permission denied" متفرّقة يصعب ربطها بسببها،
#  وتظهر بعد كل رفع فتبدو عشوائية.
#
#  الحلّ الشائع الخاطئ: chmod 777 على كل شيء. وهو يجعل أي ثغرة صغيرة
#  في أي سكربت سيطرةً كاملة — من يكتب ملفاً يكتب باباً خلفياً.
#
#  الحلّ هنا: نصحّح الملكية والأنماط دورياً. المالك الصحيح يُنهي كل
#  أخطاء الصلاحيات، والأنماط تبقى ضيقة.
#
#  ⚡ رخيص جداً: لا يعمل إلا إن تغيّر شيء فعلاً (يقارن بصمة الحالة).
# ═══════════════════════════════════════════════════════════════════════════
set -uo pipefail

APP_DIR="${APP_DIR:-/var/www/html/iptv}"
[[ -d "$APP_DIR" ]] || exit 0

WEBUSER="www-data"
id -u "$WEBUSER" >/dev/null 2>&1 || WEBUSER="apache"
id -u "$WEBUSER" >/dev/null 2>&1 || exit 0

QUIET=0
[[ "${1:-}" == "--quiet" ]] && QUIET=1
say(){ [[ $QUIET -eq 1 ]] || printf '%s\n' "$*"; }

# مجلدات نتخطّاها: ضخمة ولا يخدمها الويب
PRUNE=( -path "$APP_DIR/Xp" -o -path "$APP_DIR/node_modules"
        -o -path "$APP_DIR/_backup_before_fix" -o -path "$APP_DIR/_archive"
        -o -path "$APP_DIR/.git" )

# ── هل يحتاج الأمر عملاً أصلاً؟ ──
# نبحث عن أول ملف بمالك خاطئ. إن لم نجد شيئاً نخرج فوراً بلا أي تعديل،
# فلا يستهلك cron شيئاً في الحالة الطبيعية (وهي الغالبة).
WRONG="$(find "$APP_DIR" \( "${PRUNE[@]}" \) -prune -o \
         ! -user "$WEBUSER" -print -quit 2>/dev/null)"

if [[ -z "$WRONG" ]]; then
    # الملكية سليمة — نتأكد فقط من مجلدات الكتابة و.env
    NEEDW=0
    for D in storage storage/logs storage/cache storage/vod uploads cache; do
        [[ -d "$APP_DIR/$D" ]] || { NEEDW=1; break; }
        [[ -w "$APP_DIR/$D" ]] || { NEEDW=1; break; }
    done
    if [[ $NEEDW -eq 0 ]]; then
        say "الصلاحيات سليمة — لا تغيير"
        exit 0
    fi
fi

say "تصحيح الصلاحيات…"

# ── ① الملكية: هي أصل المشكلة ──
chown -R "$WEBUSER:$WEBUSER" "$APP_DIR" 2>/dev/null

# ── ② الأنماط ──
find "$APP_DIR" \( "${PRUNE[@]}" \) -prune -o -type d -exec chmod 755 {} + 2>/dev/null
find "$APP_DIR" \( "${PRUNE[@]}" \) -prune -o -type f -exec chmod 644 {} + 2>/dev/null

# ── ③ مجلدات يكتب فيها الويب ──
for D in storage storage/logs storage/cache storage/vod uploads cache; do
    mkdir -p "$APP_DIR/$D" 2>/dev/null
    chown -R "$WEBUSER:$WEBUSER" "$APP_DIR/$D" 2>/dev/null
    chmod -R 775 "$APP_DIR/$D" 2>/dev/null
done

# ── ④ سكربتات قابلة للتنفيذ ──
for S in "$APP_DIR"/tools/*.py "$APP_DIR"/tools/*.sh "$APP_DIR"/*.sh; do
    [[ -f "$S" ]] && chmod 755 "$S" 2>/dev/null
done

# ── ⑤ ‎.env: آخر شيء، وبحذر ──
# لا نضيّق الصلاحية إلا بعد التأكد من نجاح تغيير الملكية. الترتيب
# المعكوس (chmod ثم chown مكتوم الخطأ) هو ما عطّل الموقع سابقاً:
# ملف مملوك لـ root بصلاحية 600 لا يقرأه Apache، فترتدّ الإعدادات
# بصمت إلى القيم الافتراضية.
ENV_FILE="$APP_DIR/.env"
if [[ -f "$ENV_FILE" ]]; then
    if chown "$WEBUSER:$WEBUSER" "$ENV_FILE" 2>/dev/null; then
        chmod 640 "$ENV_FILE"
    else
        chmod 644 "$ENV_FILE"
    fi
fi

# ── ⑥ تحقّق فعلي بهوية خادم الويب ──
FAILED=0
for D in storage uploads cache storage/vod; do
    sudo -u "$WEBUSER" test -w "$APP_DIR/$D" 2>/dev/null || FAILED=1
done
sudo -u "$WEBUSER" test -r "$ENV_FILE" 2>/dev/null || FAILED=1

if [[ $FAILED -eq 0 ]]; then
    say "✔ الصلاحيات مضبوطة (المالك $WEBUSER · الكود 644 · الكتابة 775 · .env 640)"
    exit 0
fi

say "⚠ بقيت مشكلة — راجع: sudo bash $APP_DIR/release_check.sh"
exit 1
