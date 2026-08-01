#!/usr/bin/env bash
# ══════════════════════════════════════════════════════════════
#  تغيير كلمة مرور قاعدة البيانات بأمان — Shashety IPTV
# ──────────────────────────────────────────────────────────────
#  لماذا سكربت بدل خطوتين يدويتين؟
#
#  الطريقة اليدوية فيها فخّ حقيقي: تُغيّر الكلمة في MySQL أولاً،
#  فيتوقف الموقع فوراً عن العمل، ثم تعدّل .env. أي خطأ مطبعي بين
#  الخطوتين — أو انقطاع اتصالك بالخادم — يترك موقعك معطّلاً بالكامل
#  ولا تعرف السبب.
#
#  هذا السكربت يفعلها بالترتيب الآمن مع تحقق وتراجع تلقائي:
#    ① نسخة احتياطية من .env
#    ② توليد كلمة مرور قوية (أو قبول كلمتك)
#    ③ اختبار الاتصال بالكلمة الحالية قبل أي تغيير
#    ④ تغيير الكلمة في MySQL
#    ⑤ تحديث .env
#    ⑥ التحقق الفعلي من أن PHP يستطيع الاتصال بالكلمة الجديدة
#    ⑦ إن فشل أي شيء → تراجع كامل إلى الحالة السابقة
#
#  الاستخدام:
#     cd /var/www/shashety
#     sudo bash tools/change_db_password.sh
# ══════════════════════════════════════════════════════════════

set -uo pipefail

RED=$'\e[31m'; GRN=$'\e[32m'; YLW=$'\e[33m'; CYN=$'\e[36m'; RST=$'\e[0m'

say()  { printf '%s\n' "$*"; }
ok()   { printf '%s✔%s %s\n' "$GRN" "$RST" "$*"; }
err()  { printf '%s✘%s %s\n' "$RED" "$RST" "$*"; }
warn() { printf '%s⚠%s %s\n' "$YLW" "$RST" "$*"; }
step() { printf '\n%s▸ %s%s\n' "$CYN" "$*" "$RST"; }

# ── تحديد جذر المشروع (مجلد أعلى من tools/) ──
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ENV_FILE="$ROOT/.env"

say "══════════════════════════════════════════════════"
say "  تغيير كلمة مرور قاعدة البيانات — Shashety IPTV"
say "══════════════════════════════════════════════════"
say "المشروع: $ROOT"

# ── ① التحقق من وجود .env ──
step "فحص الملفات"

if [[ ! -f "$ENV_FILE" ]]; then
    err "الملف .env غير موجود في $ROOT"
    say "  أنشئه أولاً:  cp .env.example .env"
    exit 1
fi
ok "الملف .env موجود"

if ! command -v mysql >/dev/null 2>&1; then
    err "الأمر mysql غير متاح على هذا الخادم."
    exit 1
fi
ok "عميل MySQL متاح"

# ── قراءة الإعدادات الحالية من .env ──
getenv() {
    local key="$1"
    grep -E "^${key}=" "$ENV_FILE" 2>/dev/null | tail -1 | cut -d= -f2- | tr -d '"'"'"'' | tr -d '\r'
}

DB_HOST="$(getenv DB_HOST)"; DB_HOST="${DB_HOST:-localhost}"
DB_NAME="$(getenv DB_NAME)"; DB_NAME="${DB_NAME:-iptv_db}"
DB_USER="$(getenv DB_USER)"; DB_USER="${DB_USER:-iptv_user}"
DB_PASS="$(getenv DB_PASS)"

say "  المستخدم : $DB_USER"
say "  القاعدة  : $DB_NAME"
say "  المضيف   : $DB_HOST"

# ── ② اختبار الاتصال بالكلمة الحالية ──
step "اختبار الاتصال الحالي"

if ! MYSQL_PWD="$DB_PASS" mysql -h "$DB_HOST" -u "$DB_USER" -e "SELECT 1" "$DB_NAME" >/dev/null 2>&1; then
    err "تعذّر الاتصال بالكلمة الموجودة في .env"
    say "  تأكد أن DB_PASS في .env يطابق كلمة المرور الفعلية قبل التغيير."
    exit 1
fi
ok "الاتصال الحالي يعمل"

# ── ③ كلمة المرور الجديدة ──
step "كلمة المرور الجديدة"

NEW_PASS="${1:-}"

if [[ -z "$NEW_PASS" ]]; then
    if command -v openssl >/dev/null 2>&1; then
        NEW_PASS="$(openssl rand -base64 24 | tr -d '/+=' | cut -c1-24)"
    else
        NEW_PASS="$(head -c 32 /dev/urandom | od -An -tx1 | tr -d ' \n' | cut -c1-24)"
    fi
    ok "وُلِّدت كلمة مرور قوية تلقائياً"
else
    if [[ ${#NEW_PASS} -lt 12 ]]; then
        err "الكلمة قصيرة (${#NEW_PASS} حرفاً). الحد الأدنى 12."
        exit 1
    fi
    ok "استُخدمت الكلمة التي أدخلتها"
fi

if [[ "$NEW_PASS" == "$DB_PASS" ]]; then
    err "الكلمة الجديدة مطابقة للحالية."
    exit 1
fi

# ── ④ نسخة احتياطية من .env ──
step "نسخة احتياطية"

BACKUP="$ENV_FILE.bak.$(date +%Y%m%d_%H%M%S)"
cp -p "$ENV_FILE" "$BACKUP" || { err "تعذّر إنشاء نسخة احتياطية"; exit 1; }
chmod 600 "$BACKUP" 2>/dev/null
ok "حُفظت: $(basename "$BACKUP")"

# ── دالة التراجع ──
rollback() {
    warn "جارٍ التراجع..."
    cp -p "$BACKUP" "$ENV_FILE" 2>/dev/null && ok "أُعيد .env إلى حالته السابقة"
    MYSQL_PWD="$NEW_PASS" mysql -h "$DB_HOST" -u "$DB_USER" \
        -e "ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS//\'/\'\'}'; FLUSH PRIVILEGES;" \
        >/dev/null 2>&1 && ok "أُعيدت كلمة المرور في MySQL"
    say ""
    err "لم يتغيّر شيء. موقعك يعمل كما كان."
    exit 1
}

# ── ⑤ تغيير الكلمة في MySQL ──
step "تغيير الكلمة في MySQL"

ESC_NEW="${NEW_PASS//\'/\'\'}"
ALTERED=0

for HOSTPART in localhost '127.0.0.1' '%'; do
    if MYSQL_PWD="$DB_PASS" mysql -h "$DB_HOST" -u "$DB_USER" \
         -e "ALTER USER '${DB_USER}'@'${HOSTPART}' IDENTIFIED BY '${ESC_NEW}';" >/dev/null 2>&1; then
        ok "حُدِّثت لـ ${DB_USER}@${HOSTPART}"
        ALTERED=1
    fi
done

if [[ $ALTERED -eq 0 ]]; then
    err "تعذّر تغيير كلمة المرور — قد يحتاج المستخدم صلاحية أعلى."
    say "  جرّب يدوياً بحساب root:"
    say "    mysql -u root -p -e \"ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY 'كلمتك';\""
    rm -f "$BACKUP"
    exit 1
fi

MYSQL_PWD="$NEW_PASS" mysql -h "$DB_HOST" -u "$DB_USER" -e "FLUSH PRIVILEGES;" >/dev/null 2>&1

# ── ⑥ تحديث .env ──
step "تحديث ملف .env"

TMP="$ENV_FILE.tmp.$$"
if grep -qE '^DB_PASS=' "$ENV_FILE"; then
    awk -v p="$NEW_PASS" '/^DB_PASS=/{print "DB_PASS=" p; next} {print}' "$ENV_FILE" > "$TMP"
else
    cp "$ENV_FILE" "$TMP"
    printf 'DB_PASS=%s\n' "$NEW_PASS" >> "$TMP"
fi

if [[ ! -s "$TMP" ]]; then
    rm -f "$TMP"
    rollback
fi

mv "$TMP" "$ENV_FILE"
chmod 600 "$ENV_FILE" 2>/dev/null
ok "حُدِّث DB_PASS في .env"

# ── ⑦ التحقق النهائي ──
step "التحقق من أن الموقع سيعمل"

if ! MYSQL_PWD="$NEW_PASS" mysql -h "$DB_HOST" -u "$DB_USER" -e "SELECT 1" "$DB_NAME" >/dev/null 2>&1; then
    err "فشل الاتصال بالكلمة الجديدة!"
    rollback
fi
ok "الاتصال بالكلمة الجديدة يعمل"

# تحقق عبر PHP نفسه — أدقّ اختبار ممكن
if command -v php >/dev/null 2>&1; then
    if php -r '
        require "'"$ROOT"'/core/config.php";
        try { db()->query("SELECT 1"); echo "PHP_OK"; }
        catch (Throwable $e) { echo "PHP_FAIL"; }
    ' 2>/dev/null | grep -q PHP_OK; then
        ok "PHP يتصل بقاعدة البيانات بنجاح"
    else
        err "PHP لا يستطيع الاتصال — تراجع فوري."
        rollback
    fi
else
    warn "PHP CLI غير متاح — تحقق يدوياً بفتح health.php"
fi

# ── تنظيف الكاش ──
rm -f "$ROOT/storage/cache/.schema_ok" 2>/dev/null

# ══════════════════════════════════════════════════════════════
say ""
say "══════════════════════════════════════════════════"
ok  "تمت العملية بنجاح"
say "══════════════════════════════════════════════════"
say ""
say "  كلمة المرور الجديدة:"
printf '  %s%s%s\n' "$GRN" "$NEW_PASS" "$RST"
say ""
warn "احفظها في مكان آمن الآن — لن تُعرض مرة أخرى."
say ""
say "  النسخة الاحتياطية: $(basename "$BACKUP")"
say "  احذفها بعد التأكد:  rm '$BACKUP'"
say ""
say "  الخطوة التالية: افتح health.php وتأكد من اختفاء التحذير."
say ""
