#!/usr/bin/env bash
# ═══════════════════════════════════════════════════════════════════════════
#  تشخيص تعذّر الخروج إلى الإنترنت — Shashety Pro
# ───────────────────────────────────────────────────────────────────────────
#      sudo bash tools/net_diag.sh
#
#  يفحص الطبقات بالترتيب — الشبكة، ثم DNS، ثم TCP، ثم هوية المنفِّذ —
#  لأن كل طبقة تفشل بطريقة تشبه فشل التي فوقها. «Connection refused»
#  و«Resolving timed out» و«Couldn't resolve host» أعطاض متشابهة
#  لأسباب مختلفة تماماً، والخلط بينها يرسل البحث إلى المكان الخطأ.
# ═══════════════════════════════════════════════════════════════════════════
set -uo pipefail
G=$'\e[32m'; R=$'\e[31m'; Y=$'\e[33m'; C=$'\e[36m'; B=$'\e[1m'; N=$'\e[0m'
ok(){ printf '  %s✔%s %s\n' "$G" "$N" "$*"; }
no(){ printf '  %s✘%s %s\n' "$R" "$N" "$*"; }
wr(){ printf '  %s⚠%s %s\n' "$Y" "$N" "$*"; }
inf(){ printf '  %s•%s %s\n' "$C" "$N" "$*"; }
hd(){ printf '\n%s%s══ %s ══%s\n' "$B" "$C" "$*" "$N"; }

H=api.opensubtitles.com
VERDICT=""

# ═════════════════════════════════════════════════════════════════
hd "١) هل الشبكة نفسها تعمل؟ (بلا DNS إطلاقاً)"
# ═════════════════════════════════════════════════════════════════
# نتصل بعنوان رقمي مباشرةً. نجاحُه يعزل المشكلة في DNS وحده،
# وفشلُه يعني أن العطل أعمق ولا فائدة من فحص DNS بعده.
if curl -sS -o /dev/null --max-time 8 https://1.1.1.1 2>/dev/null; then
    ok "الخروج إلى الإنترنت يعمل (اتصال برقم بلا اسم)"
    NET_OK=1
elif ping -c1 -W3 1.1.1.1 >/dev/null 2>&1; then
    wr "ping يمرّ لكن HTTPS لا — حظر على المنفذ 443 الصادر"
    NET_OK=0; VERDICT="حظر المنفذ 443 الصادر"
else
    no "لا خروج إلى الإنترنت إطلاقاً"
    NET_OK=0; VERDICT="لا اتصال بالإنترنت من الخادم"
fi

# ═════════════════════════════════════════════════════════════════
hd "٢) إعداد DNS الحالي"
# ═════════════════════════════════════════════════════════════════
NS="$(grep -E '^nameserver' /etc/resolv.conf 2>/dev/null | awk '{print $2}' | tr '\n' ' ')"
if [[ -z "$NS" ]]; then
    no "لا يوجد أي nameserver في /etc/resolv.conf"
    VERDICT="${VERDICT:-/etc/resolv.conf بلا خادم أسماء}"
else
    inf "الخوادم المُعلَنة: $NS"
fi
if [[ "$NS" == *"127.0.0.53"* ]]; then
    if systemctl is-active --quiet systemd-resolved 2>/dev/null; then
        ok "systemd-resolved يعمل (الوسيط المحلي 127.0.0.53)"
    else
        no "resolv.conf يشير إلى 127.0.0.53 لكن systemd-resolved متوقّف"
        VERDICT="${VERDICT:-systemd-resolved متوقّف}"
    fi
    command -v resolvectl >/dev/null 2>&1 && \
        resolvectl status 2>/dev/null | grep -E 'DNS Servers|Current DNS' | head -3 | sed 's/^/       /'
fi

# ═════════════════════════════════════════════════════════════════
hd "٣) هل يستجيب DNS فعلاً؟"
# ═════════════════════════════════════════════════════════════════
# نفرّق بين ثلاث حالات يخلط بينها الناس:
#   • يردّ بعنوان        → DNS سليم
#   • يردّ NXDOMAIN      → الاسم خاطئ (ليس حالتنا)
#   • لا يردّ إطلاقاً     → الخادم غير قابل للوصول أو المنفذ 53 محظور
# ⚠ لا يكفي أن ينجح الأمر — يجب أن تكون الإجابة عنواناً صالحاً.
# النسخة الأولى اكتفت برمز الخروج، فاعتبرت 0.0.0.0 نجاحاً وأعلنت
# «ترجمة النظام تعمل» بينما هي بالضبط سبب العطل. إجابةٌ مسمومة تبدو
# إجابة، وهذا أخطر من غياب الإجابة لأنه يوقف البحث في مكانه.
valid_ip(){
    local ip="$1"
    [[ "$ip" =~ ^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$ ]] || return 1
    case "$ip" in
        0.0.0.0|0.*|127.*|255.255.255.255) return 1 ;;
    esac
    return 0
}

SYS_OK=0; POISONED=0
SYS_IPS="$(timeout 6 getent ahostsv4 "$H" 2>/dev/null | awk '{print $1}' | sort -u | tr '\n' ' ')"
if [[ -z "$SYS_IPS" ]]; then
    no "ترجمة النظام لا تردّ (انتهت المهلة أو رُفضت)"
else
    GOODC=0
    for ip in $SYS_IPS; do valid_ip "$ip" && GOODC=$((GOODC+1)); done
    if [[ $GOODC -gt 0 ]]; then
        ok "ترجمة النظام تعمل: $SYS_IPS"
        SYS_OK=1
    else
        no "ترجمة النظام تعيد عنواناً غير صالح: $SYS_IPS"
        echo "       0.0.0.0 تعني «لا وجهة» — الاتصال بها يرتدّ فوراً."
        POISONED=1
    fi
fi

for S in 1.1.1.1 8.8.8.8; do
    R1=""
    if command -v dig >/dev/null 2>&1; then
        R1="$(timeout 6 dig +short +time=3 +tries=1 "$H" @"$S" 2>/dev/null | head -1)"
    elif command -v nslookup >/dev/null 2>&1; then
        R1="$(timeout 6 nslookup "$H" "$S" 2>/dev/null | awk '/^Address: /{print $2; exit}')"
    fi
    # dig يطبع رسائل الخطأ على المخرج القياسي أيضاً، فـ«غير فارغ»
    # ليس دليل نجاح. النسخة الأولى طبعت
    # «الاستعلام ينجح → ;; communications error … timed out» — أي
    # أعلنت نجاحاً ونصُّ الفشل ملتصقٌ بالإعلان نفسه.
    if valid_ip "$R1"; then
        ok "الاستعلام المباشر من $S → $R1"
        DIRECT_OK=1
    elif [[ -n "$R1" ]]; then
        no "الاستعلام المباشر من $S يعيد إجابة غير صالحة: $R1"
        [[ "$R1" == 0.0.0.0 ]] && POISONED=1
        DIRECT_OK=${DIRECT_OK:-0}
    else
        no "الاستعلام المباشر من $S لا يردّ إطلاقاً"
        DIRECT_OK=${DIRECT_OK:-0}
    fi
done
DIRECT_OK=${DIRECT_OK:-0}

# ═════════════════════════════════════════════════════════════════
hd "٤) إدخالات hosts"
# ═════════════════════════════════════════════════════════════════
if grep -qi "opensubtitles" /etc/hosts 2>/dev/null; then
    wr "يوجد إدخال يدوي — يتجاوز DNS ويُنتج نتائج مضلّلة:"
    grep -i opensubtitles /etc/hosts | sed 's/^/       /'
else
    ok "لا إدخال يدوي"
fi

# ═════════════════════════════════════════════════════════════════
hd "٥) الجدار الناري"
# ═════════════════════════════════════════════════════════════════
ufw status verbose 2>/dev/null | head -4 | sed 's/^/  /'
RJ="$(iptables -S OUTPUT 2>/dev/null | grep -cE 'REJECT|DROP' || true)"
[[ "${RJ:-0}" -gt 0 ]] && wr "توجد ${RJ} قاعدة حظر على الصادر" || ok "لا حظر على الصادر في iptables"

# ═════════════════════════════════════════════════════════════════
hd "٦) الاتصال بهوية خادم الويب و PHP"
# ═════════════════════════════════════════════════════════════════
W=www-data; id -u "$W" >/dev/null 2>&1 || W=apache
OW="$(sudo -u "$W" curl -sS -o /dev/null --max-time 12 \
     -w 'رمز:%{http_code} وجهة:%{remote_ip}' "https://$H/api/v1/infos/formats" 2>&1)"
if [[ "$OW" == *"رمز:2"* || "$OW" == *"رمز:4"* ]]; then
    ok "$W: $OW"; APP_OK=1
else
    no "$W: $OW"; APP_OK=0
fi

if command -v php >/dev/null 2>&1; then
    sudo -u "$W" php -r '
      $ch=curl_init("https://api.opensubtitles.com/api/v1/infos/formats");
      curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>1,CURLOPT_TIMEOUT=>12,
                             CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_IPRESOLVE=>CURL_IPRESOLVE_V4]);
      curl_exec($ch);
      printf("  • PHP → رمز:%s وجهة:%s خطأ:%s\n",
        curl_getinfo($ch,CURLINFO_HTTP_CODE),
        curl_getinfo($ch,CURLINFO_PRIMARY_IP) ?: "-",
        curl_error($ch) ?: "لا شيء");
    ' 2>&1
fi

# ═════════════════════════════════════════════════════════════════
hd "الحكم"
# ═════════════════════════════════════════════════════════════════
# ⚠ الحكم يعتمد على الاختبار الفعلي (القسم ٦) لا على الطبقات وحدها.
#   النسخة الأولى استنتجت السلامة من نجاح DNS وتجاهلت أن الاتصال
#   الحقيقي فشل في السطر الذي يسبقها مباشرةً — فأعلنت «كل شيء سليم»
#   فوق نصّ الفشل. الاختبار المباشر يعلو على أي استنتاج.
APP_OK=${APP_OK:-0}
POISONED=${POISONED:-0}

if [[ $APP_OK -eq 1 ]]; then
    ok "الاتصال بالخدمة يعمل — أعد المحاولة من اللوحة."

elif [[ $POISONED -eq 1 ]]; then
    no "الاسم $H يُترجَم إلى 0.0.0.0 — أي حجبٌ على مستوى DNS."
    echo
    echo "     ما معنى ذلك: خادم الأسماء يردّ بعنوان «لا وجهة» بدل العنوان"
    echo "     الحقيقي، فيتصل الخادم بنفسه ويرتدّ الاتصال خلال أجزاء من"
    echo "     الثانية. وليس عطلاً في خادمك: شبكته أو مزوّده يحجب الاسم."
    echo
    echo "     ولاحظ أن الاستعلام من 8.8.8.8 أعاد 0.0.0.0 أيضاً — أي أن"
    echo "     طلبات المنفذ 53 تُعترَض في الطريق ولا تصل إلى Google أصلاً."
    echo "     لذلك تغيير خادم الأسماء وحده لا يكفي."
    echo
    echo "     الحلّ: DNS مشفَّر (DoT) يتجاوز الاعتراض لأنه لا يمرّ على 53:"
    echo
    echo "       sudo mkdir -p /etc/systemd/resolved.conf.d"
    echo "       printf '[Resolve]\\nDNS=1.1.1.1#cloudflare-dns.com\\nDNSOverTLS=yes\\n' \\"
    echo "         | sudo tee /etc/systemd/resolved.conf.d/dot.conf"
    echo "       sudo systemctl restart systemd-resolved"
    echo "       getent ahostsv4 $H     # يجب أن يظهر عنوان حقيقي"
    echo
    echo "     إن بقي 0.0.0.0 فالحجب أوسع من DNS، ولن يفتحه إعداد على"
    echo "     الخادم. عندها تبقى هذه الميزة معطّلة — وهي اختيارية"
    echo "     بالكامل ولا تمسّ الاشتراكات ولا البثّ ولا رفع الأفلام."

elif [[ $NET_OK -eq 0 ]]; then
    no "${VERDICT}"
    echo "     ابدأ من هنا — لا فائدة من إصلاح DNS قبل عودة الاتصال."

elif [[ $SYS_OK -eq 0 && ${DIRECT_OK:-0} -eq 1 ]]; then
    no "الاستعلام المباشر يعمل وترجمة النظام لا — العطل في إعداد الخادم:"
    echo "       sudo systemctl restart systemd-resolved"
    echo "       sudo ln -sf /run/systemd/resolve/stub-resolv.conf /etc/resolv.conf"

elif [[ $SYS_OK -eq 0 ]]; then
    no "لا ترجمة النظام تعمل ولا الاستعلام المباشر — المنفذ 53 محظور."
    echo "       sudo ufw allow out 53"

else
    no "الترجمة سليمة لكن الاتصال يفشل — راجع القسم ٦ أعلاه."
fi
echo
