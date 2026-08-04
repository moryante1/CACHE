#!/usr/bin/env bash
# ═══════════════════════════════════════════════════════════════════════════
#  إعادة مزامنة كلمة مرور قاعدة البيانات — Shashety Pro
# ───────────────────────────────────────────────────────────────────────────
#  المشكلة التي يحلّها: MySQL و.env يحملان كلمتين مختلفتين، فيفشل الاتصال.
#
#  لماذا حدث ذلك: تغيير كلمة مرور موزّع على ثلاثة أماكن (MySQL، .env، ملفات
#  المشاريع الأخرى) بلا معاملة تجمعها. نجاح جزئي = عطل كامل. هذا السكربت
#  يعامل الثلاثة كوحدة واحدة: إما تنجح كلها أو تُستعاد كلها.
#
#  الاستخدام:
#      sudo bash fix_db_password.sh              # يولّد كلمة قوية جديدة
#      sudo bash fix_db_password.sh 'كلمتي'      # يستخدم كلمة تحدّدها أنت
#
#  آمن للتكرار: تشغيله مرتين لا يضرّ.
# ═══════════════════════════════════════════════════════════════════════════
set -uo pipefail

R=$'\e[31m'; G=$'\e[32m'; Y=$'\e[33m'; B=$'\e[1m'; N=$'\e[0m'
ok(){   printf '  %s✔%s %s\n' "$G" "$N" "$1"; }
bad(){  printf '  %s✘%s %s\n' "$R" "$N" "$1"; }
warn(){ printf '  %s⚠%s %s\n' "$Y" "$N" "$1"; }
inf(){  printf '  %s•%s %s\n' "$Y" "$N" "$1"; }
head_(){ printf '\n%s══ %s ══%s\n' "$B" "$1" "$N"; }

[[ $EUID -eq 0 ]] || { bad "شغّله بـ sudo:  sudo bash $0"; exit 1; }

APP_DIR="${APP_DIR:-$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)}"

# ══════════════════════════════════════════════════════════════
#  الملف المؤثّر فعلاً — لا الذي اعتدنا عليه.
#  بعد install_env.sh تعيش الأسرار في /etc/shashety/env وهو أعلى
#  أولويةً عند قراءة الكود. ولو ظلّ هذا السكربت يكتب في ‎.env‎ لأعلن
#  نجاحه بينما التطبيق يقرأ كلمة مرور أخرى — أي «أصلحتُ العطل» وهو
#  قائم. الأسوأ أنه سيقارن كلمة MySQL الجديدة بملف لا يُقرأ أصلاً.
# ══════════════════════════════════════════════════════════════
ENV_ETC="/etc/shashety/env"
if [[ -f "$ENV_ETC" ]]; then
    ENV_FILE="$ENV_ETC"
else
    ENV_FILE="$APP_DIR/.env"
fi

WEBROOT="$(dirname "$APP_DIR")"
STAMP="$(date +%Y%m%d-%H%M%S)"

command -v mysql >/dev/null || { bad "mysql غير مثبّت"; exit 1; }

# ═════════════════════════════════════════════════════════════════
head_ "١) قراءة الإعدادات الحالية"
# ═════════════════════════════════════════════════════════════════
[[ -f "$ENV_FILE" ]] || { bad "لا يوجد $ENV_FILE"; exit 1; }

getenv(){ grep -m1 "^$1=" "$ENV_FILE" 2>/dev/null | cut -d= -f2- | sed 's/^["'\'']//;s/["'\'']$//'; }

DB_USER="$(getenv DB_USER)"; DB_USER="${DB_USER:-iptv_user}"
DB_NAME="$(getenv DB_NAME)"; DB_NAME="${DB_NAME:-iptv_db}"
DB_HOST="$(getenv DB_HOST)"; DB_HOST="${DB_HOST:-localhost}"
ENV_PASS="$(getenv DB_PASS)"

ok "المستخدم: $DB_USER   القاعدة: $DB_NAME"
inf "كلمة .env الحالية: ${#ENV_PASS} حرفاً"

# هل صلاحيات root على MySQL متاحة؟
if ! mysql -e "SELECT 1" >/dev/null 2>&1; then
    bad "تعذّر الدخول إلى MySQL كـ root"
    echo "     جرّب:  sudo mysql -u root -p -e 'SELECT 1'"
    echo "     ثم شغّل هذا السكربت مجدداً."
    exit 1
fi
ok "الوصول إلى MySQL كـ root متاح"

# ═════════════════════════════════════════════════════════════════
head_ "٢) تشخيص الوضع"
# ═════════════════════════════════════════════════════════════════
ENV_WORKS=0
if MYSQL_PWD="$ENV_PASS" mysql -u "$DB_USER" -h "$DB_HOST" -e "SELECT 1" "$DB_NAME" >/dev/null 2>&1; then
    ENV_WORKS=1
    ok "كلمة .env تعمل أصلاً — لا يوجد عطل في المزامنة"
else
    bad "كلمة .env مرفوضة من MySQL — هذا سبب تعطّل الموقع"
fi

# ═════════════════════════════════════════════════════════════════
head_ "٣) البحث عن المشاريع التي تشارك نفس المستخدم"
# ═════════════════════════════════════════════════════════════════
# نبحث قبل أي تغيير لا بعده. الدرس من عطل سابق: غُيّرت كلمة مستخدم
# مشترك وحُدّث .env الخاص بمشروع واحد فقط، فتوقّف مشروع /act بصمت.
# ⚠ نبحث باسم المستخدم لا بكلمة المرور.
#
# النسخة الأولى بحثت عن الكلمة الموجودة في .env، فأخفقت في اكتشاف /act:
# كان قد استقبل كلمةً من دورة تغيير سابقة ثم انفصل، فصار يحمل قيمة
# ثالثة لا هي القديمة ولا الجديدة. البحث بقيمة متغيّرة يجد فقط ما لم
# ينفصل بعد — أي أنه يعمى تحديداً عن الملفات التي تحتاج الإصلاح.
# اسم المستخدم ثابت لا يتغيّر مع التدوير، فهو المرساة الصحيحة.
declare -a LINKED=()
while IFS= read -r f; do [[ -n "$f" ]] && LINKED+=("$f"); done < <(
    {
        [[ -n "$ENV_PASS" ]] && grep -rlF "$ENV_PASS" "$WEBROOT" --include='*.php' 2>/dev/null
        grep -rlE "DB_PASS|DB_PASSWORD|db_pass" "$WEBROOT" --include='*.php' 2>/dev/null \
          | xargs -r grep -lF "$DB_USER" 2>/dev/null
    } \
      | grep -v "^${APP_DIR}/" \
      | grep -vE '/(node_modules|_backup_before_fix|Xp|xp|vendor)/' \
      | grep -vE '\.bak(\.|$)' \
      | sort -u
)

if [[ ${#LINKED[@]} -gt 0 ]]; then
    warn "مشاريع أخرى تستخدم '$DB_USER' — ستُحدَّث معاً:"
    for f in "${LINKED[@]}"; do
        # نعرض السطر الحالي ليرى المستخدم ما سيتغيّر قبل أن يتغيّر
        cur="$(grep -oE "(DB_PASS|DB_PASSWORD|db_pass)['\"]?\s*(,|=>|=)\s*['\"][^'\"]*" "$f" 2>/dev/null | head -1 | grep -oE "['\"][^'\"]*$" | tr -d "'\"")"
        if [[ -n "$cur" ]]; then
            printf '        %s  (الكلمة الحالية: %s حرفاً)\n' "$f" "${#cur}"
        else
            printf '        %s\n' "$f"
        fi
    done
else
    inf "لا مشاريع أخرى تستخدم هذا المستخدم"
fi

# ═════════════════════════════════════════════════════════════════
head_ "٤) تحديد الكلمة الجديدة"
# ═════════════════════════════════════════════════════════════════
if [[ $# -ge 1 && -n "${1:-}" ]]; then
    NEWP="$1"
    if [[ ${#NEWP} -lt 8 ]]; then bad "الكلمة قصيرة — ٨ محارف على الأقل"; exit 1; fi
    inf "استخدام الكلمة التي حدّدتها (${#NEWP} حرفاً)"
elif [[ $ENV_WORKS -eq 1 && ${#ENV_PASS} -ge 12 ]]; then
    ok "كل شيء متزامن وقوي — لا حاجة للتغيير"
    NEWP=""
else
    # أبجدية بلا رموز خاصة: الكلمة تمرّ عبر sed وPHP وMySQL وملفات .env،
    # وكل واحد منها يفسّر رموزاً مختلفة. حروف وأرقام فقط = صفر مفاجآت.
    NEWP="$(LC_ALL=C tr -dc 'A-Za-z0-9' </dev/urandom | head -c 24)"
    inf "وُلّدت كلمة قوية جديدة (24 حرفاً)"
fi

if [[ -z "$NEWP" ]]; then
    head_ "النتيجة"
    ok "لا شيء لتغييره. إن كان الموقع معطّلاً فالسبب في مكان آخر."
    exit 0
fi

# ═════════════════════════════════════════════════════════════════
head_ "٥) النسخ الاحتياطي"
# ═════════════════════════════════════════════════════════════════
cp -p "$ENV_FILE" "${ENV_FILE}.bak.${STAMP}" && ok "نُسخ $(basename "$ENV_FILE")"
for f in "${LINKED[@]}"; do
    cp -p "$f" "${f}.bak.${STAMP}" && ok "نُسخ $(basename "$(dirname "$f")")/$(basename "$f")"
done

# استعادة كل شيء إلى ما كان — تُستدعى عند أي فشل
rollback(){
    warn "جارٍ التراجع…"
    mysql -e "ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${ENV_PASS}';" >/dev/null 2>&1
    mysql -e "ALTER USER '${DB_USER}'@'127.0.0.1' IDENTIFIED BY '${ENV_PASS}';" >/dev/null 2>&1
    mysql -e "ALTER USER '${DB_USER}'@'%' IDENTIFIED BY '${ENV_PASS}';"         >/dev/null 2>&1
    mysql -e "FLUSH PRIVILEGES;" >/dev/null 2>&1
    cp -p "${ENV_FILE}.bak.${STAMP}" "$ENV_FILE" 2>/dev/null
    for f in "${LINKED[@]}"; do cp -p "${f}.bak.${STAMP}" "$f" 2>/dev/null; done
    bad "تم التراجع — كل شيء كما كان قبل التشغيل"
}

# ═════════════════════════════════════════════════════════════════
head_ "٦) تطبيق الكلمة على MySQL"
# ═════════════════════════════════════════════════════════════════
# نضمن وجود المستخدم أولاً: إن كان محذوفاً فـ ALTER يفشل بينما CREATE ينجح
mysql -e "CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${NEWP}';" >/dev/null 2>&1
mysql -e "CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" >/dev/null 2>&1

CHANGED=0
for H in localhost 127.0.0.1 '%'; do
    # ملتقَط في متغيّر لا أنبوب: grep -q يُغلق الأنبوب فيتلقّى mysql
    # إشارة SIGPIPE، ومع pipefail يصبح الفحص فاشلاً فتُتخطّى حسابات
    # موجودة فعلاً — أي تتغيّر كلمة بعضها ويبقى بعضها على القديمة.
    HOST_ROW="$(mysql -N -e "SELECT 1 FROM mysql.user WHERE user='${DB_USER}' AND host='${H}'" 2>/dev/null || true)"
    if [[ -n "$(printf '%s' "$HOST_ROW" | tr -d '[:space:]')" ]]; then
        if mysql -e "ALTER USER '${DB_USER}'@'${H}' IDENTIFIED BY '${NEWP}';" 2>/dev/null; then
            ok "غُيّرت لـ ${DB_USER}@${H}"
            CHANGED=1
        else
            bad "فشل التغيير لـ ${DB_USER}@${H}"
        fi
    fi
done

mysql -e "GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';" >/dev/null 2>&1
mysql -e "FLUSH PRIVILEGES;" >/dev/null 2>&1

[[ $CHANGED -eq 1 ]] || { bad "لم يُغيَّر أي حساب"; rollback; exit 1; }

# ═════════════════════════════════════════════════════════════════
head_ "٧) التحقق قبل لمس أي ملف"
# ═════════════════════════════════════════════════════════════════
# نتحقق أولاً ثم نكتب. العكس يترك ملفات بكلمة لا تعمل.
if ! MYSQL_PWD="$NEWP" mysql -u "$DB_USER" -h "$DB_HOST" -e "SELECT 1" "$DB_NAME" >/dev/null 2>&1; then
    bad "MySQL لا يقبل الكلمة الجديدة"
    rollback
    exit 1
fi
ok "MySQL يقبل الكلمة الجديدة"

# ═════════════════════════════════════════════════════════════════
head_ "٨) تحديث الملفات"
# ═════════════════════════════════════════════════════════════════
# python بدل sed: الكلمة قد تحوي رموزاً تكسر sed بصمت وتُفسد الملف
setenv_py(){
python3 - "$ENV_FILE" "$1" "$2" <<'PY'
import io, re, sys
path, key, val = sys.argv[1], sys.argv[2], sys.argv[3]
s = io.open(path, encoding='utf-8', errors='surrogateescape').read()
line = f'{key}={val}'
if re.search(rf'^{re.escape(key)}=', s, re.M):
    s = re.sub(rf'^{re.escape(key)}=.*$', line.replace('\\', '\\\\'), s, flags=re.M)
else:
    if s and not s.endswith('\n'): s += '\n'
    s += line + '\n'
io.open(path, 'w', encoding='utf-8', errors='surrogateescape').write(s)
PY
}

if setenv_py DB_PASS "$NEWP"; then ok "حُدّث $ENV_FILE"; else bad "فشل تحديث .env"; rollback; exit 1; fi

UPD_FAIL=0
for f in "${LINKED[@]}"; do
    if python3 - "$f" "$ENV_PASS" "$NEWP" <<'PY'
# يستبدل قيمة كلمة مرور قاعدة البيانات أياً كانت، لا نصاً بعينه.
#
# الاستبدال الحرفي للكلمة القديمة يفشل حين يكون الملف قد انفصل سابقاً
# ويحمل قيمة ثالثة — وهذه بالضبط حالة /act التي عطّلت الموقع مرتين.
# هنا نستهدف الإسناد نفسه: define('DB_PASS', '…') و $db_pass = '…'
# و 'DB_PASS' => '…' — فيصحّ أياً كانت القيمة الحالية.
import io, re, sys
path, old, new = sys.argv[1], sys.argv[2], sys.argv[3]
try:
    s = io.open(path, encoding='utf-8', errors='surrogateescape').read()
    orig = s
    q = lambda m: m.group(1) + new + m.group(3)

    pats = [
        # define('DB_PASS', '...')  /  define("DB_PASSWORD", "...")
        r"""(define\s*\(\s*['"](?:DB_PASS|DB_PASSWORD)['"]\s*,\s*(['"]))([^'"]*)(\2)""",
        # $db_pass = '...'  /  $dbPassword = "..."
        r"""((?:\$db_?pass(?:word)?)\s*=\s*(['"]))([^'"]*)(\2)""",
        # 'DB_PASS' => '...'
        r"""(['"](?:DB_PASS|DB_PASSWORD|db_pass)['"]\s*=>\s*(['"]))([^'"]*)(\2)""",
    ]
    for p in pats:
        s = re.sub(p, lambda m: m.group(1) + new + m.group(len(m.groups())), s, flags=re.I)

    # ارتداد حرفي — مشروط.
    # لا نستبدل نصاً حرفياً إلا إذا كان الملف يحوي فعلاً إسناد كلمة مرور
    # قاعدة بيانات. بدون هذا الشرط يُستبدل أي ورود لـ "123456" في الملف:
    # رقم منفذ، معرّف، قيمة افتراضية في تعليق. كلمة مرور ضعيفة وشائعة
    # كهذه تظهر في أماكن لا علاقة لها بقاعدة البيانات، والاستبدال الأعمى
    # يُفسدها بصمت.
    if s == orig and old and old in s:
        if re.search(r'DB_PASS|DB_PASSWORD|db_pass', orig, re.I):
            s = s.replace(old, new)
        else:
            sys.exit(3)   # لا يبدو ملف إعدادات قاعدة بيانات — لا نلمسه

    if s == orig:
        sys.exit(2)   # لم يتغيّر شيء
    io.open(path, 'w', encoding='utf-8', errors='surrogateescape').write(s)
except Exception as e:
    sys.stderr.write(str(e))
    sys.exit(1)
PY
    then ok "حُدّث $(basename "$(dirname "$f")")/$(basename "$f")"
    else
        rc=$?
        case $rc in
            2) warn "لم أجد إسناد كلمة مرور في $f — راجعه يدوياً"; UPD_FAIL=1 ;;
            3) inf "تُخطّي $f (لا يحوي إعدادات قاعدة بيانات)" ;;   # ليس خطأ
            *) bad "تعذّر تحديث $f"; UPD_FAIL=1 ;;
        esac
    fi
done

# ═════════════════════════════════════════════════════════════════
head_ "٩) صلاحيات .env"
# ═════════════════════════════════════════════════════════════════
# 0777 يعني أن أي مستخدم على الخادم يقرأ بيانات الاعتماد ويكتبها.
# 640 مع ملكية www-data: يقرؤه الويب وحده.
WEBUSER="www-data"
id -u "$WEBUSER" >/dev/null 2>&1 || WEBUSER="apache"
id -u "$WEBUSER" >/dev/null 2>&1 || WEBUSER=""

if [[ -n "$WEBUSER" ]]; then
    if chown "${WEBUSER}:${WEBUSER}" "$ENV_FILE"; then
        chmod 640 "$ENV_FILE"
        ok "الملكية ${WEBUSER}:${WEBUSER} والصلاحية 640"
    else
        # لا نضيّق الصلاحيات إن فشل تغيير الملكية — هذا بالضبط ما يجعل
        # الملف غير مقروء لخادم الويب فيرتدّ إلى الكلمة الافتراضية.
        warn "تعذّر تغيير الملكية — أبقينا الصلاحية 644 كي يبقى مقروءاً"
        chmod 644 "$ENV_FILE"
    fi
else
    warn "لم أتعرّف على مستخدم خادم الويب — راجع الصلاحيات يدوياً"
fi

# ═════════════════════════════════════════════════════════════════
head_ "١٠) التحقق النهائي"
# ═════════════════════════════════════════════════════════════════
FINAL_OK=1

if MYSQL_PWD="$(getenv DB_PASS)" mysql -u "$DB_USER" -h "$DB_HOST" -e "SELECT 1" "$DB_NAME" >/dev/null 2>&1; then
    ok "الاتصال بالكلمة الموجودة في $ENV_FILE ناجح"
else
    bad "فشل الاتصال بالكلمة الموجودة في $ENV_FILE"; FINAL_OK=0
fi

# هل يقرأ خادم الويب الملف فعلاً؟ نختبر بهويته لا بهوية root
if [[ -n "$WEBUSER" ]]; then
    if sudo -u "$WEBUSER" test -r "$ENV_FILE" 2>/dev/null; then
        ok "خادم الويب ($WEBUSER) يستطيع قراءة $ENV_FILE"
    else
        bad "خادم الويب لا يستطيع قراءة $ENV_FILE"; FINAL_OK=0
    fi
fi

[[ $UPD_FAIL -eq 0 ]] || { warn "بعض المشاريع لم تُحدَّث — قد تتوقف"; FINAL_OK=0; }

systemctl reload apache2 >/dev/null 2>&1 && ok "أُعيد تحميل Apache"

# ═════════════════════════════════════════════════════════════════
head_ "النتيجة"
# ═════════════════════════════════════════════════════════════════
if [[ $FINAL_OK -eq 1 ]]; then
    printf '  %s%s كل شيء متزامن. كلمة المرور الجديدة — احفظها:%s\n' "$B" "$G" "$N"
    printf '  %s%s%s\n\n' "$G" "$NEWP" "$N"
    # سطر بلا ألوان ولا زخرفة — يلتقطه auto.sh بدقّة. الاعتماد على
    # تحليل نصّ ملوّن هشّ: أي تغيير في الصياغة يكسر الالتقاط بصمت.
    printf '__NEWPASS__=%s\n' "$NEWP"
    echo "  افتح الموقع للتأكّد."
    echo "  النسخ الاحتياطية محفوظة بلاحقة .bak.${STAMP}"
else
    bad "بقيت مشكلة — راجع الرسائل أعلاه"
    echo "  للاستعادة اليدوية:"
    echo "      sudo cp ${ENV_FILE}.bak.${STAMP} $ENV_FILE"
    exit 1
fi
