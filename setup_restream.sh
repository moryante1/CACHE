#!/usr/bin/env bash
# ═══════════════════════════════════════════════════════════════════════════
#  تهيئة وسيط إعادة البثّ — Shashety Pro
# ───────────────────────────────────────────────────────────────────────────
#  يجهّز كل ما يحتاجه الوسيط: ffmpeg، مجلد الذاكرة، وسيط Apache لخدمة
#  المقاطع، مهمة cron للتنظيف، وإعدادات .env.
#
#  آمن للتكرار: تشغيله مرتين لا يضرّ.
#      sudo bash setup_restream.sh
#      sudo bash setup_restream.sh --off      # تعطيل الوسيط
# ═══════════════════════════════════════════════════════════════════════════
set -uo pipefail

R=$'\e[31m'; G=$'\e[32m'; Y=$'\e[33m'; B=$'\e[1m'; N=$'\e[0m'
ok(){ printf '  %s✔%s %s\n' "$G" "$N" "$1"; }
bad(){ printf '  %s✘%s %s\n' "$R" "$N" "$1"; }
warn(){ printf '  %s⚠%s %s\n' "$Y" "$N" "$1"; }
inf(){ printf '  %s•%s %s\n' "$Y" "$N" "$1"; }
head_(){ printf '\n%s══ %s ══%s\n' "$B" "$1" "$N"; }

[[ $EUID -eq 0 ]] || { bad "شغّله بـ sudo:  sudo bash $0"; exit 1; }

APP_DIR="${APP_DIR:-$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)}"
ENV_FILE="$APP_DIR/.env"
HLS_DIR="/dev/shm/shs_hls"
WEBUSER="www-data"; id -u "$WEBUSER" >/dev/null 2>&1 || WEBUSER="apache"

setenv(){
  local k="$1" v="$2"
  [[ -f "$ENV_FILE" ]] || touch "$ENV_FILE"
  if grep -q "^${k}=" "$ENV_FILE"; then
    python3 - "$ENV_FILE" "$k" "$v" <<'PY'
import io,re,sys
p,k,v=sys.argv[1],sys.argv[2],sys.argv[3]
s=io.open(p,encoding='utf-8',errors='surrogateescape').read()
s=re.sub(rf'^{re.escape(k)}=.*$', f'{k}={v}', s, flags=re.M)
io.open(p,'w',encoding='utf-8',errors='surrogateescape').write(s)
PY
  else
    printf '%s=%s\n' "$k" "$v" >> "$ENV_FILE"
  fi
}

# ── التعطيل ──
if [[ "${1:-}" == "--off" ]]; then
  head_ "تعطيل الوسيط"
  setenv RESTREAM_ENABLED 0
  # القفل الصلب ضروري: مفتاح لوحة الإدارة يُخزَّن في قاعدة البيانات
  # وأولويته أعلى من .env، فلولا هذا السطر لبقي الوسيط مفعّلاً رغم --off.
  setenv RESTREAM_HARD_OFF 1
  pkill -f "$HLS_DIR" 2>/dev/null && ok "أُنهيت عمليات ffmpeg" || inf "لا عمليات تعمل"
  rm -rf "${HLS_DIR:?}"/* 2>/dev/null
  rm -f /etc/cron.d/shashety-restream && ok "أُزيلت مهمة cron"
  ok "الوسيط معطّل — القنوات تعود إلى المصدر مباشرةً"
  exit 0
fi

# ═════════════════════════════════════════════════════════════════
head_ "١) ffmpeg"
# ═════════════════════════════════════════════════════════════════
if command -v ffmpeg >/dev/null 2>&1; then
  ok "$(ffmpeg -version | head -1 | cut -c1-60)"
else
  inf "جارٍ التثبيت…"
  DEBIAN_FRONTEND=noninteractive apt-get update -qq >/dev/null 2>&1
  DEBIAN_FRONTEND=noninteractive apt-get install -y -qq ffmpeg >/dev/null 2>&1
  command -v ffmpeg >/dev/null 2>&1 && ok "ثُبِّت ffmpeg" || { bad "فشل التثبيت"; exit 1; }
fi
# ⚠ لا تكتب هذا الفحص كأنبوب مع grep -q.
#
#    ffmpeg -encoders | grep -q ' aac'
#
# grep -q يخرج فور أول تطابق فيُغلق الأنبوب، فيتلقّى ffmpeg إشارة SIGPIPE
# وينهي بالرمز 141. ومع set -o pipefail في أعلى الملف يصبح الأنبوب كله
# فاشلاً، فيُبلَّغ عن غياب مُرمِّز AAC وهو موجود. أي أن الفحص نفسه يكذب.
# الالتقاط في متغيّر يقرأ الخرج كاملاً فلا أنبوب ولا SIGPIPE.
FF_ENC="$(ffmpeg -hide_banner -encoders 2>/dev/null || true)"
case "$FF_ENC" in
  *" aac "*) ok "مُرمِّز AAC متوفّر" ;;
  *)         bad "مُرمِّز AAC مفقود"
             echo "     تحقّق يدوياً:  ffmpeg -hide_banner -encoders | grep aac"
             exit 1 ;;
esac

# ═════════════════════════════════════════════════════════════════
head_ "٢) مجلد المقاطع في الذاكرة"
# ═════════════════════════════════════════════════════════════════
# /dev/shm ذاكرة لا قرص: البثّ الحيّ يكتب ويحذف آلاف الملفات في الساعة،
# وتركها على SSD يستهلك عمره بلا فائدة — المقاطع مؤقّتة بطبعها.
mkdir -p "$HLS_DIR"
chown "$WEBUSER:$WEBUSER" "$HLS_DIR"
chmod 755 "$HLS_DIR"
SHM_MB=$(df -m /dev/shm | tail -1 | awk '{print $2}')
ok "$HLS_DIR جاهز (متاح ${SHM_MB}MB)"

# ── مجلد الأفلام: على القرص لا في الذاكرة ──
# الفيلم يحتفظ بكل مقاطعه ليستطيع المشاهد الإرجاع. ساعتان بـ2 ميغابت
# = 1.8 غيغابايت، وذلك يملأ /dev/shm بفيلم واحد ويُسقط الخادم.
VOD_DIR="$APP_DIR/storage/vod"
mkdir -p "$VOD_DIR"
chown "$WEBUSER:$WEBUSER" "$VOD_DIR"
chmod 775 "$VOD_DIR"
DISK_MB=$(df -m "$APP_DIR" | tail -1 | awk '{print $4}')
ok "$VOD_DIR جاهز (متاح على القرص ${DISK_MB}MB)"
[[ ${SHM_MB:-0} -lt 512 ]] && warn "المساحة صغيرة — كل قناة تحتاج ~2MB"

# ═════════════════════════════════════════════════════════════════
head_ "٣) وسيط Apache لخدمة المقاطع"
# ═════════════════════════════════════════════════════════════════
# المقاطع تُخدَم كملفات ساكنة عبر Apache لا عبر PHP.
# تمريرها عبر PHP يعني عملية PHP محجوزة لكل مشاهد طوال المشاهدة —
# مستحيل عند مئات المشاهدين. Apache يخدم الملف الساكن بتكلفة تكاد لا تُذكر.
CONF="/etc/apache2/conf-available/shashety-hls.conf"
cat > "$CONF" <<EOF
# Shashety — خدمة مقاطع HLS من الذاكرة
Alias /hls $HLS_DIR
<Directory "$HLS_DIR">
    Options -Indexes -FollowSymLinks
    AllowOverride None
    Require all granted

    # لا نخدم إلا امتدادات HLS. بلا هذا القيد يصبح المجلد قابلاً
    # للتصفّح لأي ملف يُكتب فيه بالخطأ.
    <FilesMatch "\.(m3u8|ts|m4s|mp4)\$">
        Require all granted
    </FilesMatch>
    <FilesMatch "^\.">
        Require all denied
    </FilesMatch>
    <FilesMatch "\.(log|pid|hit|lock)\$">
        Require all denied
    </FilesMatch>

    <IfModule mod_headers.c>
        Header set Access-Control-Allow-Origin "*"
        Header set Cache-Control "no-cache, no-store, must-revalidate"
    </IfModule>
    <IfModule mod_mime.c>
        AddType application/vnd.apple.mpegurl .m3u8
        AddType video/mp2t .ts
    </IfModule>
</Directory>
EOF
cat >> "$CONF" <<EOF

# مقاطع الأفلام (على القرص — تحتفظ بكل المقاطع ليعمل الإرجاع)
Alias /vodhls $VOD_DIR
<Directory "$VOD_DIR">
    Options -Indexes -FollowSymLinks
    AllowOverride None
    <FilesMatch "\.(m3u8|ts)\$">
        Require all granted
    </FilesMatch>
    <FilesMatch "^\.|\.(log|pid|hit|lock)\$">
        Require all denied
    </FilesMatch>
    <IfModule mod_headers.c>
        Header set Access-Control-Allow-Origin "*"
    </IfModule>
    <IfModule mod_mime.c>
        AddType application/vnd.apple.mpegurl .m3u8
        AddType video/mp2t .ts
    </IfModule>
</Directory>
EOF

a2enmod headers  >/dev/null 2>&1
a2enmod mime     >/dev/null 2>&1
a2enconf shashety-hls >/dev/null 2>&1
if apache2ctl configtest >/dev/null 2>&1; then
  systemctl reload apache2 >/dev/null 2>&1
  ok "‎/hls يخدم من الذاكرة عبر Apache"
else
  bad "إعداد Apache غير صالح — أُلغي"
  a2disconf shashety-hls >/dev/null 2>&1
  rm -f "$CONF"
  apache2ctl configtest 2>&1 | head -3
  exit 1
fi

# ═════════════════════════════════════════════════════════════════
head_ "٤) مهمة التنظيف الدورية"
# ═════════════════════════════════════════════════════════════════
# بلا هذه المهمة تبقى عمليات ffmpeg تعمل بعد إغلاق المشاهدين تبويباتهم،
# لأن المتصفح لا يخبر الخادم بالإغلاق. تتراكم حتى تلتهم المعالج والنطاق.
cat > /etc/cron.d/shashety-restream <<EOF
SHELL=/bin/bash
PATH=/usr/local/bin:/usr/bin:/bin
* * * * * $WEBUSER /usr/bin/php $APP_DIR/tools/restream_gc.php >/dev/null 2>&1
EOF
chmod 644 /etc/cron.d/shashety-restream
ok "تنظيف كل دقيقة (يُنهي القنوات المهجورة)"

# ═════════════════════════════════════════════════════════════════
head_ "٥) الإعدادات"
# ═════════════════════════════════════════════════════════════════
CORES=$(nproc)
MAXCH=$(( (CORES - 1) * 16 ))
[[ $MAXCH -lt 4 ]] && MAXCH=4
[[ $MAXCH -gt 60 ]] && MAXCH=60

setenv RESTREAM_ENABLED      1
setenv RESTREAM_HARD_OFF     0
setenv RESTREAM_DIR          "$HLS_DIR"
setenv RESTREAM_PUBLIC       "/hls"
setenv RESTREAM_MAX_CHANNELS "$MAXCH"
setenv RESTREAM_IDLE         60
# سقف عمر صلب لكل عملية: يضمن ألا تبقى عملية يتيمة تسحب من المزوّد
# لو تعطّل cron ولم يرد أي طلب جديد. 12 ساعة = 43200 ثانية.
setenv RESTREAM_MAX_LIFE     43200
setenv RESTREAM_VOD_DIR      "$VOD_DIR"
setenv RESTREAM_VOD_PUBLIC   "/vodhls"
setenv RESTREAM_VOD_IDLE     180
# حصّة الأفلام: ربع المساحة الحرة، بحدّ أدنى 2GB وأقصى 40GB
VODQ=$(( ${DISK_MB:-8192} / 4 ))
[[ $VODQ -lt 2048 ]]  && VODQ=2048
[[ $VODQ -gt 40960 ]] && VODQ=40960
setenv RESTREAM_VOD_QUOTA_MB "$VODQ"
grep -q '^RESTREAM_SECRET=' "$ENV_FILE" || \
  setenv RESTREAM_SECRET "$(LC_ALL=C tr -dc 'a-f0-9' </dev/urandom | head -c 48)"

chown "$WEBUSER:$WEBUSER" "$ENV_FILE" 2>/dev/null && chmod 640 "$ENV_FILE" || chmod 644 "$ENV_FILE"
ok "مفعّل · أقصى $MAXCH قناة ($CORES نواة) · حصّة الأفلام ${VODQ}MB"

# ═════════════════════════════════════════════════════════════════
head_ "٦) اختبار حيّ"
# ═════════════════════════════════════════════════════════════════
TESTDIR="$HLS_DIR/.selftest"
rm -rf "$TESTDIR"; mkdir -p "$TESTDIR"
timeout 25 ffmpeg -hide_banner -loglevel error -nostdin \
  -f lavfi -i "testsrc=size=640x360:rate=25:duration=10" \
  -f lavfi -i "sine=frequency=440:duration=10" \
  -c:v libx264 -preset ultrafast -g 25 -c:a ac3 -f mpegts "$TESTDIR/in.ts" >/dev/null 2>&1

if [[ -s "$TESTDIR/in.ts" ]]; then
  timeout 30 ffmpeg -hide_banner -loglevel error -nostdin -i "$TESTDIR/in.ts" \
    -map 0:v:0 -map 0:a:0 -c:v copy -c:a aac -b:a 128k -ac 2 \
    -f hls -hls_time 4 -hls_init_time 1 -hls_list_size 6 \
    -hls_segment_filename "$TESTDIR/s%05d.ts" "$TESTDIR/index.m3u8" >/dev/null 2>&1
  # ffprobe يُكرّر الأسطر مع قوائم HLS (مسار لكل برنامج) ويُدرج أسطراً
  # فارغة، فالمقارنة المباشرة تفشل حتى عند النجاح. نأخذ أول سطر غير فارغ.
  AC=$(ffprobe -v quiet -select_streams a:0 -show_entries stream=codec_name -of csv=p=0 "$TESTDIR/index.m3u8" 2>/dev/null | grep -m1 -v '^$')
  VC=$(ffprobe -v quiet -select_streams v:0 -show_entries stream=codec_name -of csv=p=0 "$TESTDIR/index.m3u8" 2>/dev/null | grep -m1 -v '^$')
  if [[ "$AC" == "aac" && "$VC" == "h264" ]]; then
    ok "التحويل يعمل: ac3 ← aac والفيديو منسوخ كما هو"
  else
    bad "الاختبار غير متوقّع (فيديو=$VC صوت=$AC)"
  fi
else
  warn "تعذّر إنشاء عيّنة الاختبار"
fi
rm -rf "$TESTDIR"

# ═════════════════════════════════════════════════════════════════
head_ "النتيجة"
# ═════════════════════════════════════════════════════════════════
printf '  %sالوسيط جاهز.%s\n\n' "$B$G" "$N"
echo "  الحالة في أي وقت:"
echo "      php $APP_DIR/tools/restream_gc.php --status"
echo
echo "  اختبار قناة (استبدل 5 برقم قناة حقيقي):"
echo "      curl -s 'http://localhost/restream.php?ch=5' | head -c 300"
echo
printf '  %s⚠ تذكير النطاق الترددي:%s كل مشاهد يسحب الآن من خادمك لا من المزوّد.\n' "$Y" "$N"
echo "     500 مشاهد × 5 ميغابت ≈ 2.5 غيغابت/ث صادرة. راقب الاستهلاك."
echo
echo "  التحكّم اليومي من لوحة الإدارة:"
echo "      الاشتراكات ← خطط الاشتراك ← إصلاح صوت القنوات"
echo
echo "  للإيقاف الصلب من الطرفية (يتجاوز اللوحة):  sudo bash $0 --off"
