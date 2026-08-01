#!/usr/bin/env bash
# ══════════════════════════════════════════════════════════════
#  Shashety IPTV — سكربت إكمال النواقص
#  Ubuntu 22.04 · Apache · PHP 8.1 · MySQL
# ──────────────────────────────────────────────────────────────
#  يُكمل ما ينقص سكربت التثبيت الأصلي فقط — لا يعيد تثبيت شيء.
#
#  آمن للتشغيل المتكرر: كل خطوة تفحص حالتها أولاً وتتخطّاها إن
#  كانت منفَّذة. لا يعطّل الموقع — كل عملية حسّاسة معها تحقق وتراجع.
#
#  الاستخدام:
#      sudo bash auto.sh              # الخطوات الآمنة
#      sudo bash auto.sh --ssh        # + تقوية SSH (اقرأ التحذير)
#      sudo bash auto.sh --ssl=موقعك.com
#      sudo bash auto.sh --all --ssl=موقعك.com
# ══════════════════════════════════════════════════════════════

set -uo pipefail

APP_DIR="${APP_DIR:-/var/www/html/iptv}"
ENV_FILE="$APP_DIR/.env"
VHOST="/etc/apache2/sites-available/000-default.conf"

DO_SSH=0
SSL_DOMAIN=""

for arg in "$@"; do
    case "$arg" in
        --ssh)     DO_SSH=1 ;;
        --all)     DO_SSH=1 ;;
        --ssl=*)   SSL_DOMAIN="${arg#*=}" ;;
    esac
done

R=$'\e[31m'; G=$'\e[32m'; Y=$'\e[33m'; C=$'\e[36m'; B=$'\e[1m'; N=$'\e[0m'
ok()   { printf '  %s✔%s %s\n' "$G" "$N" "$*"; }
skip() { printf '  %s•%s %s\n' "$C" "$N" "$*"; }
warn() { printf '  %s⚠%s %s\n' "$Y" "$N" "$*"; }
bad()  { printf '  %s✘%s %s\n' "$R" "$N" "$*"; }
head_() { printf '\n%s%s══ %s ══%s\n' "$B" "$C" "$*" "$N"; }

FAILED=0

# ── فحوص أولية ────────────────────────────────────────────────
[[ $EUID -eq 0 ]] || { bad "شغّله بـ sudo"; exit 1; }
[[ -d "$APP_DIR" ]] || { bad "المجلد غير موجود: $APP_DIR"; exit 1; }

printf '%s%s\n' "$B" "═══════════════════════════════════════════════"
printf '  Shashety IPTV — إكمال النواقص\n'
printf '═══════════════════════════════════════════════%s\n' "$N"
printf '  المسار: %s\n' "$APP_DIR"

getenv() {
    [[ -f "$ENV_FILE" ]] || return 1
    grep -E "^$1=" "$ENV_FILE" 2>/dev/null | tail -1 | cut -d= -f2- | tr -d '\r'
}

setenv() {
    local k="$1" v="$2"
    if grep -qE "^${k}=" "$ENV_FILE" 2>/dev/null; then
        # نستخدم awk لتفادي مشاكل الرموز الخاصة في sed
        awk -v k="$k" -v v="$v" 'BEGIN{FS=OFS="="} $1==k{print k "=" v; next} {print}' \
            "$ENV_FILE" > "$ENV_FILE.tmp" && mv "$ENV_FILE.tmp" "$ENV_FILE"
    else
        printf '%s=%s\n' "$k" "$v" >> "$ENV_FILE"
    fi
}


# ══════════════════════════════════════════════════════════════
head_ "١) ملف .env"
# ══════════════════════════════════════════════════════════════
if [[ -f "$ENV_FILE" ]]; then
    skip "موجود — لن يُستبدل"
else
    cat > "$ENV_FILE" <<EOF
# أُنشئ بواسطة auto.sh — $(date '+%Y-%m-%d %H:%M')
DB_HOST=localhost
DB_NAME=iptv_db
DB_USER=iptv_user
DB_PASS=123456
DB_CHARSET=utf8mb4
APP_KEY=

# حماية روابط البث — يمنع تسريب بيانات اشتراك Xtream في api.php
PROTECT_STREAM_URLS=1
STREAM_TOKEN_TTL=43200
STREAM_URL_STYLE=path

# التحديث اللحظي
WS_SECRET=
WS_HOST=127.0.0.1
WS_PORT=3000
WS_ENABLED=1
WS_ALLOWED_ORIGIN=*
ENABLE_WEBSOCKET=0

HEALTH_KEY=
XTREAM_VERIFY_SSL=0
ALLOW_PRIVATE_FETCH=1
EOF
    ok "أُنشئ .env"
fi

# توليد المفاتيح الفارغة فقط
if [[ -z "$(getenv WS_SECRET)" ]]; then
    setenv WS_SECRET "$(openssl rand -hex 24)"
    ok "وُلِّد WS_SECRET"
else
    skip "WS_SECRET موجود"
fi

if [[ -z "$(getenv HEALTH_KEY)" ]]; then
    setenv HEALTH_KEY "$(openssl rand -hex 16)"
    ok "وُلِّد HEALTH_KEY"
else
    skip "HEALTH_KEY موجود"
fi

chown www-data:www-data "$ENV_FILE" 2>/dev/null
chmod 600 "$ENV_FILE"
ok "الصلاحيات 600 · المالك www-data"


# ══════════════════════════════════════════════════════════════
head_ "٢) كلمة مرور قاعدة البيانات"
# ══════════════════════════════════════════════════════════════
DB_USER="$(getenv DB_USER)"; DB_USER="${DB_USER:-iptv_user}"
DB_NAME="$(getenv DB_NAME)"; DB_NAME="${DB_NAME:-iptv_db}"
CUR_PASS="$(getenv DB_PASS)"

if [[ "$CUR_PASS" != "123456" && ${#CUR_PASS} -ge 12 ]]; then
    skip "الكلمة قوية بالفعل — لا تغيير"
elif ! MYSQL_PWD="$CUR_PASS" mysql -u "$DB_USER" -e "SELECT 1" "$DB_NAME" >/dev/null 2>&1; then
    bad "تعذّر الاتصال بالكلمة الموجودة في .env — تخطّي"
    warn "صحّح DB_PASS في .env ثم أعد التشغيل"
    FAILED=1
else
    NEWP="$(openssl rand -base64 24 | tr -d '/+=' | cut -c1-24)"
    cp -p "$ENV_FILE" "$ENV_FILE.bak.$(date +%s)"

    ALTERED=0
    for H in localhost 127.0.0.1 '%'; do
        MYSQL_PWD="$CUR_PASS" mysql -u "$DB_USER" \
            -e "ALTER USER '${DB_USER}'@'${H}' IDENTIFIED BY '${NEWP}';" >/dev/null 2>&1 && ALTERED=1
    done

    if [[ $ALTERED -eq 0 ]]; then
        bad "لا صلاحية لتغيير الكلمة — نفّذها بحساب root:"
        printf "      mysql -u root -e \"ALTER USER '%s'@'localhost' IDENTIFIED BY 'كلمتك';\"\n" "$DB_USER"
        FAILED=1
    else
        MYSQL_PWD="$NEWP" mysql -u "$DB_USER" -e "FLUSH PRIVILEGES;" >/dev/null 2>&1
        setenv DB_PASS "$NEWP"
        chmod 600 "$ENV_FILE"

        if MYSQL_PWD="$NEWP" mysql -u "$DB_USER" -e "SELECT 1" "$DB_NAME" >/dev/null 2>&1; then
            ok "غُيِّرت وتحقّقت"
            printf '\n  %s%s كلمة المرور الجديدة — احفظها الآن:%s\n' "$B" "$G" "$N"
            printf '  %s%s%s\n\n' "$G" "$NEWP" "$N"
        else
            # تراجع كامل
            for H in localhost 127.0.0.1 '%'; do
                MYSQL_PWD="$NEWP" mysql -u "$DB_USER" \
                    -e "ALTER USER '${DB_USER}'@'${H}' IDENTIFIED BY '${CUR_PASS}';" >/dev/null 2>&1
            done
            setenv DB_PASS "$CUR_PASS"
            bad "فشل التحقق — تم التراجع. الموقع يعمل كما كان."
            FAILED=1
        fi
    fi
fi


# ══════════════════════════════════════════════════════════════
head_ "٣) تقييد صلاحيات MySQL"
# ══════════════════════════════════════════════════════════════
DBP="$(getenv DB_PASS)"
if mysql -u root -e "SELECT 1" >/dev/null 2>&1; then
    GRANTS="$(mysql -u root -N -e "SHOW GRANTS FOR '${DB_USER}'@'localhost';" 2>/dev/null)"
    if grep -q "ON \*\.\*" <<<"$GRANTS"; then
        mysql -u root >/dev/null 2>&1 <<SQL
REVOKE ALL PRIVILEGES, GRANT OPTION FROM '${DB_USER}'@'localhost';
GRANT ALL ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
GRANT ALL ON \`license_server\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL
        if MYSQL_PWD="$DBP" mysql -u "$DB_USER" -e "SELECT 1" "$DB_NAME" >/dev/null 2>&1; then
            ok "قُيِّدت على iptv_db و license_server"
        else
            mysql -u root -e "GRANT ALL ON *.* TO '${DB_USER}'@'localhost' WITH GRANT OPTION; FLUSH PRIVILEGES;" 2>/dev/null
            bad "فشل التحقق — أُعيدت الصلاحيات السابقة"
            FAILED=1
        fi
    else
        skip "مقيَّدة بالفعل"
    fi
else
    warn "لا وصول لحساب root — تخطّي (نفّذها يدوياً)"
fi


# ══════════════════════════════════════════════════════════════
head_ "٤) إضافة APCu ووحدات Apache"
# ══════════════════════════════════════════════════════════════
if php -m 2>/dev/null | grep -qi '^apcu$'; then
    skip "APCu مثبّتة"
else
    DEBIAN_FRONTEND=noninteractive apt-get install -y php8.1-apcu >/dev/null 2>&1 \
        && ok "ثُبِّتت APCu" || { warn "تعذّر تثبيت APCu"; }
fi

NEED_RELOAD=0
for M in rewrite headers deflate expires proxy proxy_http proxy_wstunnel; do
    if a2query -m "$M" >/dev/null 2>&1; then
        skip "mod_$M مفعّلة"
    else
        a2enmod "$M" >/dev/null 2>&1 && { ok "فُعِّلت mod_$M"; NEED_RELOAD=1; }
    fi
done


# ══════════════════════════════════════════════════════════════
head_ "٥) فهارس الأداء"
# ══════════════════════════════════════════════════════════════
DBP="$(getenv DB_PASS)"
if MYSQL_PWD="$DBP" mysql -u "$DB_USER" -N \
     -e "SHOW INDEX FROM episodes WHERE Column_name='series_id';" "$DB_NAME" 2>/dev/null | grep -q .; then
    skip "فهرس episodes.series_id موجود"
elif [[ -f "$APP_DIR/sql/performance_indexes.sql" ]]; then
    MYSQL_PWD="$DBP" mysql -u "$DB_USER" "$DB_NAME" < "$APP_DIR/sql/performance_indexes.sql" >/dev/null 2>&1
    ok "طُبِّقت فهارس الأداء"
else
    # الفهارس الأساسية مباشرة إن لم يوجد الملف
    MYSQL_PWD="$DBP" mysql -u "$DB_USER" "$DB_NAME" >/dev/null 2>&1 <<'SQL'
ALTER TABLE episodes ADD INDEX idx_series (series_id);
ALTER TABLE episodes ADD INDEX idx_series_order (series_id, episode_number, display_order, id);
ALTER TABLE series   ADD INDEX idx_category (category_id);
ALTER TABLE channels ADD INDEX idx_cat_order (category_id, display_order, id);
SQL
    ok "أُضيفت الفهارس الأساسية"
fi

rm -f "$APP_DIR/storage/cache/.schema_ok" 2>/dev/null
ok "أُبطلت علامة المخطط (ستُعاد التهيئة تلقائياً)"


# ══════════════════════════════════════════════════════════════
head_ "٦) خادم WebSocket"
# ══════════════════════════════════════════════════════════════
WS_SRV="$APP_DIR/websocket/server.js"

if [[ ! -f "$WS_SRV" ]]; then
    warn "لا يوجد websocket/server.js — تخطّي"
elif ! grep -q "WS_SECRET" "$WS_SRV"; then
    warn "نسخة server.js قديمة (لا تقرأ WS_SECRET) — ارفع النسخة الجديدة أولاً"
    setenv ENABLE_WEBSOCKET 0
    skip "أُبقيت الميزة معطّلة"
elif ! command -v pm2 >/dev/null 2>&1; then
    warn "pm2 غير مثبّت — تخطّي"
else
    WS="$(getenv WS_SECRET)"
    pm2 delete server      >/dev/null 2>&1
    pm2 delete shashety-ws >/dev/null 2>&1

    cd "$APP_DIR/websocket" || exit 1
    WS_SECRET="$WS" WS_HOST=127.0.0.1 WS_PORT=3000 \
        pm2 start server.js --name shashety-ws --update-env >/dev/null 2>&1
    pm2 save >/dev/null 2>&1
    pm2 startup systemd -u root --hp /root 2>/dev/null | tail -1 | bash >/dev/null 2>&1

    sleep 2
    if curl -s --max-time 3 http://127.0.0.1:3000/health | grep -q '"status":"ok"'; then
        ok "يعمل ومحمي بمفتاح"
        setenv ENABLE_WEBSOCKET 1
    else
        warn "لا يستجيب — الميزة اختيارية، أُبقيت معطّلة"
        setenv ENABLE_WEBSOCKET 0
    fi
    cd "$APP_DIR" || exit 1
fi


# ══════════════════════════════════════════════════════════════
head_ "٧) إغلاق /broadcast من الإنترنت"
# ══════════════════════════════════════════════════════════════
if [[ -f "$VHOST" ]] && grep -q "ProxyPass */broadcast" "$VHOST"; then
    cp -p "$VHOST" "$VHOST.bak.$(date +%s)"
    sed -i '/ProxyPass  *\/broadcast/d; /ProxyPassReverse  *\/broadcast/d' "$VHOST"
    if apache2ctl configtest >/dev/null 2>&1; then
        ok "أُزيل ProxyPass /broadcast"
        NEED_RELOAD=1
    else
        cp -p "$VHOST.bak."* "$VHOST" 2>/dev/null
        bad "خطأ في الإعداد — تم التراجع"
        FAILED=1
    fi
else
    skip "غير موجود أصلاً"
fi

if [[ $NEED_RELOAD -eq 1 ]]; then
    systemctl reload apache2 >/dev/null 2>&1 && ok "أُعيد تحميل Apache"
fi


# ══════════════════════════════════════════════════════════════
head_ "٨) جدار ناري"
# ══════════════════════════════════════════════════════════════
if ! command -v ufw >/dev/null 2>&1; then
    DEBIAN_FRONTEND=noninteractive apt-get install -y ufw >/dev/null 2>&1
fi

if ufw status 2>/dev/null | grep -q "Status: active"; then
    skip "مفعّل بالفعل"
else
    ufw allow 22/tcp  >/dev/null 2>&1
    ufw allow 80/tcp  >/dev/null 2>&1
    ufw allow 443/tcp >/dev/null 2>&1
    ufw --force enable >/dev/null 2>&1 && ok "فُعِّل — المنافذ 22/80/443 فقط"
fi
ufw status numbered 2>/dev/null | grep -qE '3000' && warn "المنفذ 3000 مفتوح — أغلقه: ufw delete allow 3000"


# ══════════════════════════════════════════════════════════════
head_ "٩) حذف الملفات الخطرة وضبط الصلاحيات"
# ══════════════════════════════════════════════════════════════
cd "$APP_DIR" || exit 1
BK="/root/iptv_backups"

# لا ننقل شيئاً قبل التأكد من وجود وجهة صالحة — وإلا تبقى الملفات
# الخطرة في مكانها بصمت ويظن المستخدم أنها نُقلت.
if ! mkdir -p "$BK" 2>/dev/null || [[ ! -w "$BK" ]]; then
    BK="$APP_DIR/../iptv_backups"
    mkdir -p "$BK" 2>/dev/null
fi

if [[ -d "$BK" && -w "$BK" ]]; then
    MOVED=0
    for F in setup.php loader.php change_db_password.php database.sql *.rar *.zip; do
        [[ -e "$F" ]] || continue
        if mv "$F" "$BK/" 2>/dev/null; then ok "نُقل $F"; MOVED=1; else bad "تعذّر نقل $F"; FAILED=1; fi
    done
    for D in Xp xp _backup_before_fix server; do
        [[ -d "$D" ]] || continue
        if mv "$D" "$BK/" 2>/dev/null; then ok "نُقل مجلد $D/"; MOVED=1; else bad "تعذّر نقل $D/"; FAILED=1; fi
    done
    [[ $MOVED -eq 0 ]] && skip "لا ملفات خطرة"
    [[ $MOVED -eq 1 ]] && ok "الوجهة: $BK"
else
    bad "تعذّر إنشاء مجلد النسخ — الملفات الخطرة ما زالت في مكانها!"
    warn "انقلها يدوياً: mv setup.php loader.php database.sql /root/"
    FAILED=1
fi

chown -R www-data:www-data "$APP_DIR" 2>/dev/null
find "$APP_DIR" -type d -exec chmod 755 {} \; 2>/dev/null
find "$APP_DIR" -type f -exec chmod 644 {} \; 2>/dev/null
[[ -d storage ]] && chmod -R 775 storage
[[ -d uploads ]] && chmod -R 775 uploads
chmod 600 "$ENV_FILE"
[[ -f tools/convert_mp4.py ]]      && chmod +x tools/convert_mp4.py
[[ -f tools/tailscale_control.py ]] && chmod +x tools/tailscale_control.py
ok "الصلاحيات مضبوطة (755/644 · storage+uploads 775 · .env 600)"


# ══════════════════════════════════════════════════════════════
head_ "١٠) تقوية SSH"
# ══════════════════════════════════════════════════════════════
if [[ $DO_SSH -eq 1 ]]; then
    if [[ -s /root/.ssh/authorized_keys ]]; then
        cp -p /etc/ssh/sshd_config "/etc/ssh/sshd_config.bak.$(date +%s)"
        NEWROOT="$(openssl rand -base64 18)"
        echo "root:${NEWROOT}" | chpasswd
        sed -i 's/^[#[:space:]]*PermitRootLogin.*/PermitRootLogin prohibit-password/' /etc/ssh/sshd_config
        if sshd -t 2>/dev/null; then
            systemctl restart ssh
            ok "الدخول بالمفتاح فقط · كلمة root الجديدة: $NEWROOT"
        else
            cp -p /etc/ssh/sshd_config.bak.* /etc/ssh/sshd_config 2>/dev/null
            bad "خطأ في إعداد SSH — تم التراجع"
        fi
    else
        bad "لا يوجد مفتاح SSH في /root/.ssh/authorized_keys"
        warn "التقوية ستقطع وصولك — تخطّي. أضف مفتاحك أولاً:"
        printf '      ssh-copy-id root@%s\n' "$(hostname -I | awk '{print $1}')"
    fi
else
    skip "تخطّي (شغّل بـ --ssh لتفعيلها)"
    warn "كلمة root الحالية 001122 — ضعيفة جداً"
fi


# ══════════════════════════════════════════════════════════════
head_ "١١) HTTPS"
# ══════════════════════════════════════════════════════════════
if [[ -n "$SSL_DOMAIN" ]]; then
    command -v certbot >/dev/null 2>&1 || \
        DEBIAN_FRONTEND=noninteractive apt-get install -y certbot python3-certbot-apache >/dev/null 2>&1
    certbot --apache -d "$SSL_DOMAIN" --non-interactive --agree-tos \
            --register-unsafely-without-email --redirect >/dev/null 2>&1 \
        && ok "شهادة SSL مثبّتة لـ $SSL_DOMAIN" \
        || { bad "فشل certbot — تأكد أن النطاق يشير لهذا الخادم"; FAILED=1; }
else
    skip "تخطّي (شغّل بـ --ssl=نطاقك.com)"
fi


# ══════════════════════════════════════════════════════════════
head_ "١٢) صيانة دورية"
# ══════════════════════════════════════════════════════════════
CRON="/etc/cron.d/shashety"
if [[ -f "$CRON" ]]; then
    skip "موجودة"
else
    DBP="$(getenv DB_PASS)"
    mkdir -p /root/iptv_backups
    cat > "$CRON" <<EOF
SHELL=/bin/bash
PATH=/usr/local/sbin:/usr/local/bin:/sbin:/bin:/usr/sbin:/usr/bin

# نسخة احتياطية يومية 3 فجراً (يُحتفظ بـ 7 أيام)
0 3 * * * root MYSQL_PWD='${DBP}' mysqldump -u ${DB_USER} ${DB_NAME} | gzip > /root/iptv_backups/db_\$(date +\%F).sql.gz 2>/dev/null; find /root/iptv_backups -name 'db_*.sql.gz' -mtime +7 -delete

# تنظيف السجلات أسبوعياً
0 4 * * 0 root find ${APP_DIR}/storage/logs -name '*.log' -mtime +30 -delete 2>/dev/null

# تقليم سجل الدخول شهرياً
0 5 1 * * root MYSQL_PWD='${DBP}' mysql -u ${DB_USER} ${DB_NAME} -e "DELETE FROM login_logs WHERE attempt_time < DATE_SUB(NOW(), INTERVAL 90 DAY);" 2>/dev/null
EOF
    chmod 600 "$CRON"
    ok "نسخ احتياطي يومي + تنظيف تلقائي"
fi


# ══════════════════════════════════════════════════════════════
head_ "النتيجة"
# ══════════════════════════════════════════════════════════════
systemctl reload apache2 >/dev/null 2>&1

HK="$(getenv HEALTH_KEY)"
printf '\n'
if [[ $FAILED -eq 0 ]]; then
    printf '  %s%s✔ اكتملت جميع الخطوات%s\n' "$B" "$G" "$N"
else
    printf '  %s%s⚠ اكتملت مع تنبيهات — راجع الأسطر الحمراء أعلاه%s\n' "$B" "$Y" "$N"
fi

printf '\n  للتحقق:\n'
printf '    %shttp://%s/iptv/health.php%s\n' "$C" "$(hostname -I | awk '{print $1}')" "$N"
printf '\n  أو من الطرفية:\n'
printf '    curl -s "http://localhost/iptv/health.php?format=json&key=%s" | head -30\n' "$HK"
printf '\n  الملفات المنقولة محفوظة في: /root/iptv_backups\n\n'
