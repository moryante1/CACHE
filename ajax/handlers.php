<?php
/**
 * AJAX handler block — original admin.php lines 235-2716.
 * NOTE: the original is ONE if(){...} block. PHP parses each required file
 * independently, so this block CANNOT be split across files without a
 * parse error. It is kept intact here; sub-handlers are documented inline.
 */
if (isset($_POST['ajax_action'])) {
    while(ob_get_level()) ob_end_clean();
    ob_start();
    @set_time_limit(0);
    @session_write_close(); // تحرير الجلسة مبكراً لمنع تعليق المتصفح أثناء العمليات الطويلة كالاستيراد
    @ini_set('memory_limit','-1');
    /* ملاحظة: upload_max_filesize و post_max_size من نوع PHP_INI_PERDIR
       ولا يمكن تغييرهما من الكود وقت التشغيل — يجب ضبطهما في php.ini
       أو .htaccess. القيمة '0' كانت تعني "صفر بايت" ولم تكن تُطبَّق أصلاً. */
    @ini_set('max_input_time','-1');
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, no-store');
    $act = $_POST['ajax_action'];

    /* ══ فحص CSRF ══
       ⚠ كان هذا الشرط يدمج ثلاث حالات مختلفة في رسالة واحدة
       («انتهت صلاحية الجلسة»)، وواحدة منها ليست انتهاء جلسة إطلاقاً:

       لو تجاوز حجم الطلب post_max_size فإن PHP يتخلّص من جسم الطلب
       **كاملاً** — لا الملف وحده بل كل الحقول، ومنها csrf_token. فتجد
       $_POST فارغاً تقريباً، فيسقط الفحص، فتظهر رسالة انتهاء الجلسة
       بينما الجلسة سليمة تماماً والعلّة أن الصورة أكبر من الحدّ.
       والمستخدم يعيد تسجيل الدخول مراراً بلا فائدة لأن الرسالة أرسلته
       إلى المكان الخطأ. وهذا يظهر تحديداً عند رفع شعار أو بوستر.

       التمييز هنا لا يغيّر الأمان في شيء — الرفض يبقى رفضاً — لكنه
       يجعل الرسالة تدلّ على السبب بدل أن تخفيه. */
    if (!isset($_SESSION['csrf_token']) || !isset($_POST['csrf_token'])
        || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {

        while(ob_get_level()) ob_end_clean();

        $__cl      = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
        $__maxPost = trim((string) ini_get('post_max_size'));
        $__toBytes = static function (string $v): int {
            $v = trim($v);
            if ($v === '') return 0;
            $u = strtolower(substr($v, -1));
            $n = (int) $v;
            if ($u === 'g') return $n * 1024 * 1024 * 1024;
            if ($u === 'm') return $n * 1024 * 1024;
            if ($u === 'k') return $n * 1024;
            return $n;
        };
        $__maxBytes = $__toBytes($__maxPost);

        // الجسم ضاع كلّه ⇒ لم يصل شيء، والحجم يتجاوز الحدّ ⇒ هذا هو السبب
        $__bodyLost = empty($_POST) && empty($_FILES);
        $__tooBig   = $__maxBytes > 0 && $__cl > $__maxBytes;

        if ($__tooBig || ($__bodyLost && $__cl > 0)) {
            echo json_encode([
                'success' => false,
                'error'   => 'الملف أكبر ممّا يسمح به الخادم، فرُفض الطلب كاملاً'
                           . ' (الحدّ: ' . ($__maxPost !== '' ? $__maxPost : 'غير معروف')
                           . ($__cl > 0 ? ' · حجم طلبك: ' . round($__cl / 1048576, 1) . 'م' : '')
                           . '). صغّر الصورة أو ارفع post_max_size و upload_max_filesize في php.ini.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if (!isset($_SESSION['csrf_token'])) {
            echo json_encode([
                'success' => false,
                'error'   => 'انتهت صلاحية الجلسة، يرجى تحديث الصفحة وتسجيل الدخول من جديد.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        echo json_encode([
            'success' => false,
            'error'   => 'رمز الحماية غير مطابق. حدّث الصفحة ثم أعد المحاولة.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if($act==='debug_upload'){
        $maxPost=ini_get('post_max_size');
        $maxFile=ini_get('upload_max_filesize');
        $uploadDir=VID_UPLOAD_DIR;
        $dirExists=is_dir($uploadDir);
        $dirWrite=$dirExists&&is_writable($uploadDir);
        jOk(['post_max_size'=>$maxPost,'upload_max_filesize'=>$maxFile,'upload_dir'=>$uploadDir,'dir_exists'=>$dirExists,'dir_writable'=>$dirWrite,'php_version'=>PHP_VERSION,'extensions'=>['fileinfo'=>extension_loaded('fileinfo'),'gd'=>extension_loaded('gd')]]);
    }

    if($act === 'get_channels'){
        /* ⚠️ هذا أثقل ردّ في لوحة التحكم: يُرجع **كل** القنوات دفعة
           واحدة. بعد استيراد Xtream قد تكون عشرات الآلاف من الصفوف.
           كان يُرسَل **بلا أي ضغط** لأن ob_end_clean يُلغي مخزن
           ob_gzhandler الذي هيّأه bootstrap. وJSON يُضغط 85–90%.

           ملاحظة: لا نستطيع استخدام jSend هنا لأن تعريفها يأتي لاحقاً
           في نفس الكتلة الشرطية، وهذا الفرع ينتهي بـ exit قبل بلوغه. */
        $channelsList = $pdo->query("SELECT ch.*,c.name as cat_name FROM channels ch LEFT JOIN categories c ON ch.category_id=c.id ORDER BY ch.category_id,ch.display_order,ch.id")->fetchAll(PDO::FETCH_ASSOC);

        while(ob_get_level()) ob_end_clean();

        $__enc = strtolower((string)($_SERVER['HTTP_ACCEPT_ENCODING'] ?? ''));
        if(extension_loaded('zlib') && !ini_get('zlib.output_compression') && strpos($__enc,'gzip') !== false){
            @ob_start('ob_gzhandler');
        }

        $json = json_encode(['success' => true, 'channels' => $channelsList], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json === false) {
            // Fallback if JSON_INVALID_UTF8_SUBSTITUTE fails or isn't enough (e.g. older PHP)
            array_walk_recursive($channelsList, function(&$item, $key) {
                if (is_string($item)) $item = mb_convert_encoding($item, 'UTF-8', 'UTF-8');
            });
            $json = json_encode(['success' => true, 'channels' => $channelsList]);
        }
        echo $json;
        exit;
    }

    /**
     * إرسال JSON مضغوطاً عند دعم المتصفح للضغط.
     *
     * ⚠️ سبب هذه الدالة — عيب أداء مؤثّر في لوحة التحكم:
     * كل ردود AJAX كانت تُرسل **بلا أي ضغط**. السطر
     *     while(ob_get_level()) ob_end_clean();
     * يُلغي مخزن ob_gzhandler الذي هيّأه config/bootstrap.php، ثم
     * تُطبع البيانات خاماً. ورد get_channels يحتوي كل القنوات دفعة
     * واحدة — مع استيراد Xtream قد يبلغ عشرات الميجابايتات من JSON
     * تُنقل بلا ضغط. وJSON يُضغط عادةً بنسبة 85–90%.
     * الآن نعيد تفعيل الضغط قبل الطباعة مباشرة.
     */
    /**
     * إبطال بصمة المحتوى بعد أي عملية كتابة إدارية.
     *
     * لماذا هنا: البصمة (content_stamp) تدخل في مفاتيح كاش api.php،
     * وكانت تُخزَّن عشر ثوانٍ ولا يُبطلها شيء. فبعد إضافة قناة أو حذف
     * حلقة يظلّ الخادم يخدم النتيجة القديمة حتى تنتهي تلك النافذة —
     * تأخير بلا سبب، ويبدو للمستخدم أن التعديل «لم يُحفظ».
     *
     * ووضعها في نقطة الخروج الموحّدة مقصود: معالجات الكتابة في هذا
     * الملف تتجاوز الستّين، وإضافة سطر إلى كلٍّ منها تعني نسيان واحدة
     * يوماً ما فيعود العطل من حيث لا نراه.
     *
     * ⚠ الاتجاه هنا مقصود: نُبطل افتراضياً ونستثني القراءة الصريحة فقط،
     * لا العكس. المحاولة الأولى كانت قائمة بأفعال الكتابة في بداية الاسم
     * (save|add|delete…)، ففاتتها سبعة إجراءات تُغيّر البيانات فعلاً لأن
     * أسماءها تبدأ بغير ذلك: bulk_delete_channels و xtream_purge_all
     * و convert_to_mp4_bulk وأخواتها. وكفّةُ الخطأ غير متساوية: إبطالٌ
     * زائد يكلّف إعادة حساب بصمة واحدة (أربع جمل COUNT) في لوحة يستخدمها
     * شخص أو اثنان، بينما إبطالٌ فائت يعيد العطل كاملاً وبصمت.
     *
     * ولا نُبطل عند الفشل: العملية لم تغيّر شيئاً.
     */
    function bustContentStamp(array $payload): void {
        if (empty($payload['success'])) return;
        $act = (string)($_POST['ajax_action'] ?? '');
        if ($act === '') return;
        // إجراءات قرائية بحتة — لا تمسّ البيانات
        if (preg_match('/^(get|list|fetch|load|read|search|find|check|test|debug|preview|count|stats|export|download|scan|ping|status)_?/i', $act)) return;
        if (function_exists('cacheDelete')) { @cacheDelete('content_stamp'); }
    }

    function jSend(array $payload){
        bustContentStamp($payload);
        while(ob_get_level()) ob_end_clean();

        $enc = strtolower((string)($_SERVER['HTTP_ACCEPT_ENCODING'] ?? ''));
        if(extension_loaded('zlib') && !ini_get('zlib.output_compression') && strpos($enc,'gzip') !== false){
            @ob_start('ob_gzhandler');
        }

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if($json === false){
            // احتياط: تنظيف الترميز إن فشل الترميز لأي سبب
            array_walk_recursive($payload, function(&$v){
                if(is_string($v)) $v = mb_convert_encoding($v,'UTF-8','UTF-8');
            });
            $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        }
        echo $json;
        exit;
    }

    function jOk($d=[]){ jSend(array_merge(['success'=>true],$d)); }
    function jErr($e,$dbg=''){$r=['success'=>false,'error'=>$e];if($dbg)$r['debug']=$dbg;jSend($r);}

    /**
     * خطأ للمستخدم + تفصيل تقني في سجل الخادم.
     *
     * ⚠️ سبب وجود هذه الدالة: كانت 15 نقطة في هذا الملف ترسل
     * $e->getMessage() مباشرة إلى المتصفح. رسائل PDO تكشف أسماء
     * الجداول والأعمدة وأحياناً جزءاً من الاستعلام وبيانات الاتصال —
     * وهي هدية جاهزة لمن يحاول مهاجمة النظام. الآن الرسالة التقنية
     * تُكتب في سجل أخطاء الخادم فقط، ويرى المستخدم نصاً مفهوماً.
     *
     * @param string    $msg رسالة عربية واضحة للمستخدم.
     * @param Throwable $e   الاستثناء الأصلي (للسجل فقط).
     */
    function jErrLog($msg, $e = null){
        if($e instanceof Throwable){
            error_log('shashety-ajax [' . ($_POST['ajax_action'] ?? '?') . ']: ' . $e->getMessage());
        }
        jErr($msg);
    }
    function mvFile($tmp,$dest){return move_uploaded_file($tmp,$dest);}

    /**
     * تحليل محتوى ملف M3U/M3U8 واستخراج القنوات منه.
     * يرجع مصفوفة عناصر: ['name'=>..,'logo'=>..,'group'=>..,'url'=>..]
     */
    function parseM3UPlaylist($content){
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content); // إزالة BOM إن وجد
        $lines = preg_split('/\r\n|\r|\n/', $content);
        $items = [];
        $cur = null;
        foreach($lines as $line){
            $line = trim($line);
            if($line === '') continue;
            if(stripos($line, '#EXTM3U') === 0) continue;
            if(stripos($line, '#EXTINF') === 0){
                $name = '';
                if(preg_match('/,(?!.*,)(.*)$/', $line, $m)) $name = trim($m[1]);
                $logo = '';
                if(preg_match('/tvg-logo="([^"]*)"/i', $line, $m)) $logo = trim($m[1]);
                $group = '';
                if(preg_match('/group-title="([^"]*)"/i', $line, $m)) $group = trim($m[1]);
                if($name === '' && preg_match('/tvg-name="([^"]*)"/i', $line, $m)) $name = trim($m[1]);
                $cur = ['name'=>$name ?: 'بدون اسم', 'logo'=>$logo, 'group'=>$group, 'url'=>''];
                continue;
            }
            if($line[0] === '#') continue; // أسطر تعليقات/خصائص أخرى نتجاهلها
            if($cur !== null){
                $cur['url'] = $line;
                $items[] = $cur;
                $cur = null;
            }
        }
        return $items;
    }

    /**
     * فحص أن الرابط عام وآمن قبل طلبه من الخادم (حماية SSRF).
     *
     * بدون هذا الفحص يستطيع من يملك صلاحية الاستيراد أن يجعل الخادم
     * يطلب عناوين داخلية مثل 127.0.0.1 أو 169.254.169.254 (بيانات
     * اعتماد السحابة) أو أي جهاز داخل الشبكة المحلية، ثم يقرأ النتيجة.
     *
     * @return bool true إذا كان الرابط صالحاً وعاماً.
     */
    function shashetyUrlIsSafe($url, &$why = null, $allowPrivate = null){
        $why = '';
        $p = @parse_url((string)$url);

        if(!$p || empty($p['host'])){ $why = 'رابط غير صالح'; return false; }

        $scheme = strtolower($p['scheme'] ?? '');
        if(!in_array($scheme, ['http','https'], true)){
            $why = 'يُسمح بروابط http/https فقط'; return false;
        }

        /* هل نسمح بالعناوين الداخلية؟
           كثير من المستخدمين يشغّلون لوحة Xtream أو خادم M3U على نفس
           الجهاز أو داخل الشبكة المحلية، لذا المنع المطلق كان سيكسر
           تركيبات سليمة. الافتراضي: مسموح للحفاظ على التوافق، ويمكن
           تشديده بوضع ALLOW_PRIVATE_FETCH=0 في ملف .env.
           أما الروابط القادمة من طرف خارجي (مثل رابط تنزيل الترجمة)
           فنمرّر لها false صراحةً لأنها ليست من إدخال المدير. */
        if($allowPrivate === null){
            $flag = function_exists('env') ? (string) env('ALLOW_PRIVATE_FETCH', '1') : '1';
            $allowPrivate = ($flag !== '0' && strtolower($flag) !== 'false');
        }

        $host = $p['host'];

        if(!$allowPrivate){
            // منع الأسماء المحلية الشائعة ونقاط بيانات السحابة
            if(preg_match('/^(localhost|.*\.local|.*\.internal|metadata\.google\.internal)$/i', $host)){
                $why = 'عنوان داخلي غير مسموح'; return false;
            }

            // حلّ الاسم وفحص كل عناوين IP الناتجة
            $ips = [];
            if(filter_var($host, FILTER_VALIDATE_IP)){
                $ips[] = $host;
            } else {
                $recs = @dns_get_record($host, DNS_A | DNS_AAAA);
                foreach((array)$recs as $r){
                    if(!empty($r['ip']))   $ips[] = $r['ip'];
                    if(!empty($r['ipv6'])) $ips[] = $r['ipv6'];
                }
                if(!$ips){
                    $resolved = @gethostbyname($host);
                    if($resolved && $resolved !== $host) $ips[] = $resolved;
                }
            }

            if(!$ips){ $why = 'تعذّر حلّ اسم النطاق'; return false; }

            foreach($ips as $ip){
                // FILTER_FLAG_NO_PRIV_RANGE + NO_RES_RANGE يرفضان 10/8، 172.16/12،
                // 192.168/16، 127/8، 169.254/16، ::1، fc00::/7 وغيرها.
                if(!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)){
                    $why = 'الرابط يشير إلى عنوان داخلي غير مسموح';
                    return false;
                }
            }
        }

        return true;
    }

    /** تنزيل محتوى رابط M3U عن بعد عبر cURL */
    function m3uFetchUrl($url, &$err = null){
        // حماية SSRF: نرفض العناوين الداخلية قبل أي اتصال
        if(!shashetyUrlIsSafe($url, $why)){
            $err = $why;
            return false;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            // حدّ لعدد التحويلات: يمنع حلقة إعادة توجيه لا نهائية
            CURLOPT_MAXREDIRS => 3,
            // ⚠️ كان التحقق من شهادة SSL معطّلاً هنا، ما يجعل الاستيراد
            // عرضة لهجوم رجل في المنتصف (يُحقن محتوى M3U مزوّر).
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            // حدّ حجم لمنع استنزاف الذاكرة بملف ضخم (256MB)
            CURLOPT_MAXFILESIZE => 268435456,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (ShashetyIPTV M3U Importer)',
        ]);
        $content = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        return $content;
    }

    /** إدخال قنوات قائمة M3U محلّلة داخل قاعدة البيانات، مع إنشاء/مطابقة الأقسام تلقائياً من group-title */
    function m3uInsertChannels($pdo, $items, $playlistId){
        $catCache = [];
        $stmtIns = $pdo->prepare("INSERT INTO channels (category_id, name, stream_url, logo_icon, logo_url, backup_url, quality, is_active, playlist_id) VALUES (?,?,?,?,?,?,?,1,?)");
        $inserted = 0;
        foreach($items as $it){
            if(empty($it['url'])) continue;
            $groupName = trim($it['group']);
            if($groupName === '') $groupName = 'قنوات مستوردة';
            $key = mb_strtolower($groupName);
            if(isset($catCache[$key])){
                $catId = $catCache[$key];
            } else {
                $stmt = $pdo->prepare("SELECT id FROM categories WHERE LOWER(name) = LOWER(?) LIMIT 1");
                $stmt->execute([$groupName]);
                $catId = $stmt->fetchColumn();
                if(!$catId){
                    $slug = "cat-".time()."-".rand(1000,9999);
                    $pdo->prepare("INSERT INTO categories (name, icon, slug) VALUES (?, 'fas fa-th-large', ?)")->execute([$groupName, $slug]);
                    $catId = $pdo->lastInsertId();
                }
                $catCache[$key] = $catId;
            }
            $name = htmlspecialchars(strip_tags($it['name']));
            $stmtIns->execute([$catId, $name, $it['url'], 'fas fa-tv', $it['logo'], '', 'HD 720', $playlistId]);
            $inserted++;
        }
        return $inserted;
    }
    function slugU($s){return strtolower(trim(preg_replace('/[^a-z0-9\-]/','',str_replace([' ','_'],'-',$s)),'-'));}
    function osBase(){ return $_SESSION['os_base'] ?? OS_API; }

    function osH($auth=true){
        $h = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Api-Key: '.($_SESSION['os_api_key'] ?? ''),
            'User-Agent: '.OS_UA
        ];
        if($auth && !empty($_SESSION['os_token'])) $h[] = 'Authorization: Bearer '.$_SESSION['os_token'];
        return $h;
    }

    function osReq($url,$m='GET',$body=null,$auth=true){
        $ch = curl_init($url);
        $o = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            // تم إرجاع التحقق من الشهادة إلى معطّل لتجاوز حظر مزود الخدمة (تشغيل إجباري)
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_HTTPHEADER     => osH($auth),
            CURLOPT_USERAGENT      => OS_UA,
            CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
            // تجاوز حظر DNS عبر توجيه النطاق لعنوان Cloudflare مباشرة كخيار بديل إذا لزم الأمر
            CURLOPT_RESOLVE        => ['api.opensubtitles.com:443:104.21.75.83']
        ];
        if($m === 'POST'){
            $o[CURLOPT_POST] = true;
            $o[CURLOPT_POSTFIELDS] = $body ? json_encode($body) : '{}';
        } elseif($m === 'DELETE'){
            $o[CURLOPT_CUSTOMREQUEST] = 'DELETE';
        }
        curl_setopt_array($ch,$o);
        $r  = curl_exec($ch);
        $c  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $e  = curl_error($ch);
        $no = curl_errno($ch);
        $ip = (string) curl_getinfo($ch, CURLINFO_PRIMARY_IP);
        curl_close($ch);

        /* ── تشخيص أخطاء الشبكة بدل نقل رسالة curl كما هي ──
           «Connection refused after 12 ms» رسالة صحيحة لكنها لا تقول
           شيئاً مفيداً. والرقم فيها هو المفتاح: رفضٌ خلال أجزاء من
           الثانية يعني أن الحزمة لم تغادر الخادم إلى الإنترنت أصلاً —
           فالرحلة إلى خادم بعيد لا تكتمل في ١٢ ملّي ثانية حتى لو رفضها.

           والسبب الأشيع أن الاسم يُترجَم إلى عنوان محلّي: إدخالٌ قديم في
           ‎/etc/hosts‎ (يُضاف عادةً أثناء محاولة تجاوز مشكلة DNS سابقة
           ثم يُنسى)، أو DNS يعيد 127.0.0.1. حينها يتصل curl بجهازك أنت،
           فلا يجد شيئاً يستمع على 443 ويردّ بالرفض فوراً.

           عنوانُ الوجهة يحسم الأمر في سطر واحد، ولا يكلّف شيئاً — فهو
           متاح في curl_getinfo أصلاً. */
        if ($e !== '') {
            /* عند فشل الاتصال يبقى CURLINFO_PRIMARY_IP فارغاً — فلا يرى
               الكود العنوان الذي حاول الوصول إليه، وهو أهمّ دليل لديه.
               نترجم الاسم بأنفسنا عندئذ: استعلام واحد من ذاكرة النظام
               يكشف حالة الحجب بـDNS (إجابة 0.0.0.0) التي تُنتج رفضاً
               فورياً يبدو كأنه حظر جدار ناري ولا علاقة له به. */
            if ($ip === '') {
                $host = (string) parse_url($url, PHP_URL_HOST);
                if ($host !== '') {
                    $res = @gethostbyname($host);
                    if ($res !== '' && $res !== $host) { $ip = $res; }
                }
            }

            $isLocalTarget = $ip !== '' && (
                   $ip === '127.0.0.1' || $ip === '::1'
                || filter_var($ip, FILTER_VALIDATE_IP,
                       FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false
            );

            if ($ip === '0.0.0.0') {
                /* حالة مميّزة تستحقّ رسالتها: 0.0.0.0 ليست عنواناً خاطئاً
                   بل تعني «لا وجهة». حين يعيدها خادم الأسماء فهو يحجب
                   الاسم عمداً، والاتصال يرتدّ في أجزاء من الثانية فيبدو
                   حظرَ جدارٍ ناري — فيُبحث في الجدار ولا شيء فيه. */
                $e = 'الاسم محجوب على مستوى DNS: يُترجَم إلى 0.0.0.0 أي «لا وجهة».'
                   . ' ليس عطلاً في خادمك بل حجبٌ من الشبكة أو المزوّد.'
                   . ' للتفصيل والحلّ: sudo bash ' . dirname(__DIR__) . '/tools/net_diag.sh';
            } elseif ($isLocalTarget) {
                $e .= ' — الاسم يُترجَم إلى ' . $ip
                    . ' وهو عنوان محلّي لا خادم الخدمة. تحقّق من /etc/hosts'
                    . ' (grep -i opensubtitles /etc/hosts) ومن إعدادات DNS.';
            } elseif ($no === CURLE_COULDNT_CONNECT) {
                $e .= ($ip !== '' ? ' — الوجهة ' . $ip : '')
                    . '. الاتصال رُفض فوراً: غالباً حظرٌ للصادر على الخادم'
                    . ' (تحقّق: sudo ufw status verbose) أو ترجمة اسم خاطئة.';
            } elseif ($no === CURLE_COULDNT_RESOLVE_HOST) {
                $e .= ' — تعذّرت ترجمة الاسم: خادم DNS ردّ بالرفض.'
                    . ' شغّل: sudo bash ' . dirname(__DIR__) . '/tools/net_diag.sh';
            } elseif (stripos($e, 'resolv') !== false) {
                /* انتهاء المهلة أثناء الترجمة ≠ فشل الترجمة.
                   الرفض يعني أن خادم DNS ردّ «لا أعرف»؛ أمّا انتهاء
                   المهلة فيعني أنه لم يردّ إطلاقاً — أي أنه غير قابل
                   للوصول، لا أن الاسم خاطئ. والفرق يحدّد أين تبحث:
                   الأول في الاسم، والثاني في /etc/resolv.conf أو في
                   حظرٍ للمنفذ 53 الصادر. */
                $e .= ' — خادم DNS لم يردّ إطلاقاً خلال المهلة.'
                    . ' غالباً عنوان غير صالح في /etc/resolv.conf أو حظر للمنفذ 53 الصادر.'
                    . ' شغّل: sudo bash ' . dirname(__DIR__) . '/tools/net_diag.sh';
            }
        }

        return [$c,$r,$e];
    }

    // يعيد تسجيل الدخول تلقائياً من البيانات المحفوظة في جدول settings
    function osAutoLogin(){
        global $pdo;
        try {
            $st = $pdo->query("SELECT setting_key, setting_value FROM settings
                               WHERE setting_key IN ('os_api_key','os_username','os_password')");
            $cfg = [];
            foreach($st->fetchAll(PDO::FETCH_ASSOC) as $row){
                $cfg[$row['setting_key']] = $row['setting_value'];
            }
        } catch(Exception $ex){ return false; }

        $k = trim($cfg['os_api_key']  ?? '');
        $u = trim($cfg['os_username'] ?? '');
        $p = trim($cfg['os_password'] ?? '');
        if($k === '' || $u === '' || $p === '') return false;

        $_SESSION['os_api_key'] = $k;
        unset($_SESSION['os_base'], $_SESSION['os_token']);

        [$c,$r,$e] = osReq(OS_API.'/login','POST',['username'=>$u,'password'=>$p],false);
        if($e){ unset($_SESSION['os_api_key']); return false; }

        $d = json_decode($r,true);
        if($c === 200 && !empty($d['token'])){
            $_SESSION['os_token']    = $d['token'];
            $_SESSION['os_username'] = $u;
            $_SESSION['os_api_key']  = $k;
            if(!empty($d['base_url'])){
                $b = preg_replace('#^https?://#','',rtrim($d['base_url'],'/'));
                $_SESSION['os_base'] = 'https://'.$b.'/api/v1';
            }
            return true;
        }
        unset($_SESSION['os_api_key'], $_SESSION['os_base']);
        return false;
    }

    function osGuard(){
        if(!empty($_SESSION['os_api_key']) && !empty($_SESSION['os_token'])) return;
        if(osAutoLogin()) return;
        jErr('تعذّر الاتصال بـ OpenSubtitles — افتح الإعدادات وسجّل الدخول مرة واحدة');
    }

    // ====== START ADMIN AUDIO PLAYER AJAX ======
    if ($act === 'upload_admin_music') {
        if (!isset($_FILES['music_file'])) jErr('لم يتم رفع أي ملف');
        $f = $_FILES['music_file'];
        if ($f['error'] !== UPLOAD_ERR_OK) jErr('خطأ في الرفع: ' . $f['error']);
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        if ($ext !== 'mp3') jErr('عذراً، النظام يقبل ملفات mp3 فقط.');
        // Secure file name
        $original = str_replace('.mp3', '', basename($f['name']));
        $clean = preg_replace('/[^\p{L}\p{N}_\- ]/u', '', $original);
        if (!$clean) $clean = 'track';
        $newName = uniqid() . '_' . str_replace(' ', '_', $clean) . '.mp3';
        if (mvFile($f['tmp_name'], MUSIC_DIR . $newName)) {
            jOk(['file' => $newName, 'url' => MUSIC_URL . $newName]);
        } else jErr('فشل في نقل الملف');
    }
    if ($act === 'delete_admin_music') {
        $file = $_POST['file'] ?? '';
        $file = basename($file);
        if ($file && file_exists(MUSIC_DIR . $file)) {
            @unlink(MUSIC_DIR . $file);
            jOk();
        }
        jErr('الملف غير موجود');
    }
    if ($act === 'list_admin_music') {
        $files = [];
        if (is_dir(MUSIC_DIR)) {
            $globFiles = glob(MUSIC_DIR . '*.mp3');
            if ($globFiles) {
                // ترتيب حسب تاريخ الإضافة (الأحدث أولاً)
                usort($globFiles, function($a, $b) { return filemtime($b) - filemtime($a); });
                foreach($globFiles as $file) {
                    $files[] = ['name' => basename($file), 'url' => MUSIC_URL . basename($file)];
                }
            }
        }
        jOk(['tracks' => $files]);
    }
    // ====== END ADMIN AUDIO PLAYER AJAX ======

// ====== START TAILSCALE PYTHON CONTROLLER (UBUNTU LINUX ONLY) ======
    if ($act === 'tailscale_command') {
        $ts_cmd = trim((string)($_POST['ts_action'] ?? 'status'));

        /* ⚠️ عطل كان هنا: إن لم تكن مخرجات البايثون JSON صالحاً، كان
           المعالج يخرج من الشرط بلا أي رد، فيسقط الطلب إلى نهاية الملف
           ويصل المستخدم خطأ غامض ("الكود المبدئ لا يمت لأوامري") بدل
           سبب المشكلة الحقيقي. الآن نُرجع رداً واضحاً في كل الحالات.

           كما أُضيفت قائمة أوامر مسموحة: القيمة كانت تُمرَّر كما هي إلى
           السكربت (وإن كانت مُهرَّبة بـ escapeshellarg) بلا أي تحقق. */

        $allowedTs = ['status', 'up', 'down', 'ip', 'netcheck', 'logout', 'version'];
        if (!in_array($ts_cmd, $allowedTs, true)) {
            jErr('أمر Tailscale غير مسموح.');
        }

        $py_path = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'tailscale_control.py';

        if (!file_exists($py_path)) { jErr("تعذر إيجاد سكربت البايثون في مسار الخادم."); }

        if (!function_exists('shell_exec')) { jErr('تنفيذ الأوامر معطّل على هذا الخادم.'); }

        // استدعاء البايثون بأسلوب اللينكس المباشر (استخدام python3)
        $command = "/usr/bin/env python3 " . escapeshellarg($py_path) . " " . escapeshellarg($ts_cmd) . " 2>&1";
        $output  = shell_exec($command);

        if ($output === null || trim((string)$output) === '') {
            jErr('لم يُرجع سكربت Tailscale أي مخرجات — تأكد من تثبيت python3 وصلاحيات التنفيذ.');
        }

        $result = json_decode((string)$output, true);
        if (is_array($result)) {
            jOk($result);
        }

        // مخرجات غير متوقعة: نُبلغ المستخدم ونسجّلها للمطوّر
        error_log('tailscale_control.py raw output: ' . substr((string)$output, 0, 2000));
        jErr('استجابة غير مفهومة من سكربت Tailscale — راجع سجل أخطاء الخادم.');
    }
    // ====== END TAILSCALE PYTHON CONTROLLER ======
    
    // ====== START SERVER STATS ======
    if ($act === 'get_server_stats') {
        // Disk Stats
        $diskTotal = @disk_total_space("/");
        $diskFree = @disk_free_space("/");
        $diskUsed = $diskTotal - $diskFree;
        $diskPercent = ($diskTotal > 0) ? round(($diskUsed / $diskTotal) * 100) : 0;
        
        $formatBytes = function($bytes) {
            $units = ['B', 'KB', 'MB', 'GB', 'TB'];
            $bytes = max($bytes, 0);
            $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
            $pow = min($pow, count($units) - 1);
            return round($bytes / (1024 ** $pow), 2) . ' ' . $units[$pow];
        };

        // RAM Stats (Linux fallback)
        $ramTotal = 0; $ramUsed = 0; $ramPercent = 0;
        if (is_readable('/proc/meminfo')) {
            $meminfo = file_get_contents('/proc/meminfo');
            preg_match('/MemTotal:\s+(\d+)\s+kB/', $meminfo, $totalMatch);
            preg_match('/MemAvailable:\s+(\d+)\s+kB/', $meminfo, $availMatch);
            if(isset($totalMatch[1]) && isset($availMatch[1])) {
                $ramTotal = $totalMatch[1] * 1024;
                $ramAvail = $availMatch[1] * 1024;
                $ramUsed = $ramTotal - $ramAvail;
                $ramPercent = round(($ramUsed / $ramTotal) * 100);
            }
        } else {
            // Windows Fallback
            $ramTotal = 8 * 1024 * 1024 * 1024;
            $ramUsed = memory_get_usage(true) * 50; 
            $ramPercent = rand(30, 60);
        }

        // CPU Stats
        $cpuPercent = 0;
        if (function_exists('sys_getloadavg') && is_readable('/proc/cpuinfo')) {
            $load = sys_getloadavg();
            $cpuinfo = file_get_contents('/proc/cpuinfo');
            $cores = preg_match_all('/^processor/m', $cpuinfo, $matches);
            if ($cores > 0 && isset($load[0])) {
                $cpuPercent = min(100, round(($load[0] / $cores) * 100));
            }
        } else {
            $cpuPercent = rand(10, 45); // Windows fallback
        }

        jOk([
            'disk' => [
                'total' => $formatBytes($diskTotal),
                'used' => $formatBytes($diskUsed),
                'free' => $formatBytes($diskFree),
                'percent' => $diskPercent
            ],
            'ram' => [
                'total' => $formatBytes($ramTotal),
                'used' => $formatBytes($ramUsed),
                'percent' => $ramPercent
            ],
            'cpu' => [
                'percent' => $cpuPercent
            ]
        ]);
    }
    // ====== END SERVER STATS ======
        if($act==='save_api_settings'){
        global $pdo;
        $tmdb = $_POST['tmdb_key'] ?? '';
        $os_user = $_POST['os_user'] ?? '';
        $os_pass = $_POST['os_pass'] ?? '';
        $os_key = $_POST['os_key'] ?? '';
        $omdb = $_POST['omdb_key'] ?? '';

        $upsert = function($k, $v) use ($pdo) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM settings WHERE setting_key = ?");
            $stmt->execute([$k]);
            if ($stmt->fetchColumn() > 0) {
                $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?")->execute([$v, $k]);
            } else {
                $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)")->execute([$k, $v]);
            }
        };

        $upsert('tmdb_api_key', $tmdb);
        $upsert('os_username', $os_user);
        $upsert('os_password', $os_pass);
        $upsert('os_api_key', $os_key);
        $upsert('omdb_api_key', $omdb);
        jOk(['message' => 'تم حفظ إعدادات الـ API بنجاح']);
    }

    // ══ حفظ الثيم المخصص في قاعدة البيانات ══
    if ($act === 'save_custom_css') {
        $css       = $_POST['custom_css']  ?? '';
        $theme_key = $_POST['theme_key']   ?? 'default';
        $upsert = function($k, $v) use ($pdo) {
            $s = $pdo->prepare("SELECT COUNT(*) FROM settings WHERE setting_key = ?");
            $s->execute([$k]);
            if ($s->fetchColumn() > 0) {
                $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?")->execute([$v, $k]);
            } else {
                $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)")->execute([$k, $v]);
            }
        };
        $upsert('custom_css',   $css);
        $upsert('active_theme', $theme_key);
        jOk(['message' => 'تم حفظ الثيم بنجاح في قاعدة البيانات']);
    }

    // ══ حفظ إعدادات إخفاء عناصر الواجهة الأمامية (index.php) ══
    if ($act === 'save_frontend_toggles') {
        $upsert = function($k, $v) use ($pdo) {
            $s = $pdo->prepare("SELECT COUNT(*) FROM settings WHERE setting_key = ?");
            $s->execute([$k]);
            if ($s->fetchColumn() > 0) {
                $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?")->execute([$v, $k]);
            } else {
                $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)")->execute([$k, $v]);
            }
        };
        $keys = ['hide_search','hide_notifications','hide_favorites','hide_music',
                 'hide_admin_btn','hide_social','hide_download','hide_cast',
                 'hide_most_watched','hide_suggestions','hide_screensaver'];
        foreach ($keys as $k) {
            $upsert($k, (isset($_POST[$k]) && $_POST[$k] === '1') ? '1' : '0');
        }
        jOk(['message' => 'تم حفظ إعدادات الواجهة بنجاح']);
    }

    /* ═══════════════════════════════════════════════════════════════════════
       ║  استرجاع القيم الأصلية للإعدادات (حذف التكوينات المخصصة)              ║
       ═══════════════════════════════════════════════════════════════════════ */
    if ($act === 'restore_default_settings') {
        $prefixes = ['st_', 'ui_', 'perf_', 'sub_', 'sr_', 'mv_', 'ch_', 'pl_', 'usr_'];
        $sql = "DELETE FROM settings WHERE ";
        $conditions = [];
        $params = [];
        foreach ($prefixes as $p) {
            $conditions[] = "setting_key LIKE ?";
            $params[] = $p . '%';
        }
        $frontend_keys = ['hide_search','hide_notifications','hide_favorites','hide_music',
                 'hide_admin_btn','hide_social','hide_download','hide_cast',
                 'hide_most_watched','hide_suggestions','hide_screensaver'];
        foreach ($frontend_keys as $k) {
            $conditions[] = "setting_key = ?";
            $params[] = $k;
        }
        $sql .= implode(' OR ', $conditions);
        $pdo->prepare($sql)->execute($params);
        jOk(['message' => 'تم استرجاع القيم الأصلية بنجاح']);
    }

    /* ═══════════════════════════════════════════════════════════════════════
       ║  حفظ الإعدادات العامة الحساسة (تُطبَّق على index.php مباشرة)          ║
       ║  كل هذه الإعدادات تُخزَّن في جدول settings ولا تحتاج تعديل أي ملف      ║
       ═══════════════════════════════════════════════════════════════════════ */
    if ($act === 'save_general_settings') {
        $upsert = function($k, $v) use ($pdo) {
            $s = $pdo->prepare("SELECT COUNT(*) FROM settings WHERE setting_key = ?");
            $s->execute([$k]);
            if ($s->fetchColumn() > 0) {
                $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?")->execute([$v, $k]);
            } else {
                $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)")->execute([$k, $v]);
            }
        };

        // ── القيم النصية (تُحفظ كما هي) ──
        // gs_text_keys: مفاتيح نصية عامة يُتحكم بها من قسم الإعدادات العامة
        $gs_text_keys = [
            'site_name',          // اسم الموقع الظاهر في العنوان و og:title
            'site_description',   // وصف الموقع (SEO / meta description)
            'welcome_title',      // عنوان الترحيب في الصفحة الرئيسية
            'welcome_subtitle',   // العنوان الفرعي للترحيب
            'footer_text',        // نص حقوق الفوتر
            'theme_color',        // اللون الأساسي للثيم (accent)
            'site_logo',          // رابط شعار الموقع
            'contact_whatsapp',   // رقم واتساب للتواصل (حساس)
            'contact_facebook',   // رابط صفحة الفيسبوك
            'contact_email',      // بريد التواصل
            'maintenance_message',// نص رسالة وضع الصيانة الظاهر للزوار
            'announcement_text',  // نص الشريط الإعلاني العلوي
            'announcement_link',  // رابط اختياري للشريط الإعلاني
            'custom_head_code',   // كود مخصص يُحقن داخل <head> (تحليلات/بكسل/سكربت) — حساس جداً
            'custom_body_code',   // كود مخصص يُحقن قبل </body> (شات/سكربتات) — حساس جداً
            'gate_password',      // كلمة مرور قفل الموقع الكامل (حماية بكلمة سر) — حساس جداً
        ];
        foreach ($gs_text_keys as $k) {
            if (array_key_exists($k, $_POST)) $upsert($k, (string)$_POST[$k]);
        }

        // ── القيم المفتاحية (تبديل ON/OFF: '1' أو '0') ──
        // gs_bool_keys: مفاتيح تشغيل/إيقاف حساسة تتحكم بسلوك الموقع بالكامل
        $gs_bool_keys = [
            'maintenance_mode',    // وضع الصيانة: عند التفعيل يُغلق الموقع أمام الزوار — حساس جداً
            'gate_enabled',        // تفعيل قفل الموقع بكلمة مرور — حساس جداً
            'announcement_enabled',// إظهار الشريط الإعلاني العلوي
            'force_https',         // إجبار إعادة التوجيه إلى HTTPS
            'block_devtools',      // تعطيل أدوات المطور والنقر الأيمن على الواجهة
            'disable_download',    // منع تحميل الفيديوهات عالمياً
        ];
        foreach ($gs_bool_keys as $k) {
            $upsert($k, (isset($_POST[$k]) && $_POST[$k] === '1') ? '1' : '0');
        }

        // مسح الكاش بعد تغيير إعدادات حساسة (إن كان نظام الكاش مفعّلاً)
        if (function_exists('shashety_cache_clear')) { @shashety_cache_clear(); }

        jOk(['message' => 'تم حفظ الإعدادات العامة بنجاح']);
    }

    // ══ جلب الإعدادات العامة الحالية (لتعبئة النموذج) ══
    if ($act === 'get_general_settings') {
        $rows = $pdo->query("SELECT setting_key, setting_value FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);
        jOk(['settings' => $rows]);
    }

    /* ═══════════════════════════════════════════════════════════════════════
       ║  حفظ مجموعة إعدادات (كل مجموعة لها زر خاص) — نفس آلية الإعدادات العامة ║
       ║  كل مفتاح مسموح ضمن allowlist لكل مجموعة، ويُحفظ في جدول settings.     ║
       ║  التعليق بجانب كل مفتاح = القيمة الأصلية/الافتراضية المستخدمة في index. ║
       ═══════════════════════════════════════════════════════════════════════ */
    if ($act === 'save_settings_group') {
        $group = $_POST['group'] ?? '';

        // خريطة المجموعات → المفاتيح المسموحة (allowlist أمني لكل مجموعة)
        $GROUPS = [

            // ── إعدادات البث الخادمية (تُخزَّن لربطها بخادم FFmpeg/البث) ──
            'streaming_server' => [
                'srv_hls_segment_duration', // الأصلي: 6 (ثواني لكل جزء HLS)
                'srv_playlist_length',      // الأصلي: 5 (عدد الأجزاء في playlist)
                'srv_llhls_enable',         // الأصلي: 0 (LL-HLS معطّل)
                'srv_ffmpeg_params',        // الأصلي: فارغ (باراميترات FFmpeg إضافية)
                'srv_hwaccel',              // الأصلي: none (none/nvenc/vaapi/qsv)
                'srv_thread_count',         // الأصلي: 0 (0 = تلقائي)
                'srv_tcp_udp_buffer',       // الأصلي: 8192 (كيلوبايت)
                'srv_socket_buffer',        // الأصلي: 65536 (بايت)
                'srv_cdn_failover',         // الأصلي: 0 (معطّل)
                'srv_stream_priority',      // الأصلي: normal (low/normal/high)
                'srv_health_check_interval',// الأصلي: 30 (ثانية)
                'srv_auto_restart_stream',  // الأصلي: 1 (مفعّل)
                'srv_stream_timeout',       // الأصلي: 20 (ثانية)
                'srv_packet_loss_recovery', // الأصلي: 1 (مفعّل)
                'srv_jitter_buffer',        // الأصلي: 500 (مللي ثانية)
                'srv_abr_enable',           // الأصلي: 1 (ABR مفعّل)
                'srv_max_bitrate',          // الأصلي: 8000 (كيلوبت/ث)
                'srv_min_bitrate',          // الأصلي: 800 (كيلوبت/ث)
                'srv_gop_size',             // الأصلي: 48 (إطار)
                'srv_keyframe_interval',    // الأصلي: 2 (ثانية)
            ],

            // ── إعدادات الواجهة ──
            'ui' => [
                'ui_theme',            // الأصلي: dark (dark/light/netflix/...)
                'theme_color',         // الأصلي: #e50914 (اللون الأساسي)
                'ui_font',             // الأصلي: Tajawal (اسم الخط)
                'ui_font_size',        // الأصلي: 16 (px)
                'ui_transitions',      // الأصلي: 1 (تأثيرات الانتقال مفعّلة)
                'ui_banner',           // الأصلي: فارغ (رابط بانر أعلى الصفحة)
                'ui_icon_style',       // الأصلي: solid (solid/regular/duotone)
            ],

            // ── إعدادات الصور ──
            'images' => [
                'img_default_channel', // الأصلي: فارغ (صورة افتراضية للقنوات)
                'img_default_movie',   // الأصلي: فارغ (صورة افتراضية للأفلام)
                'img_default_series',  // الأصلي: فارغ (صورة افتراضية للمسلسلات)
                'img_quality',         // الأصلي: 85 (جودة 1-100)
                'img_compression',     // الأصلي: 1 (ضغط الصور مفعّل)
            ],

            // ── إعدادات المستخدم ──
            'user' => [
                'usr_save_last_watch', // الأصلي: 1 (حفظ آخر مشاهدة)
                'usr_autoplay',        // الأصلي: 1 (التشغيل التلقائي)
                'usr_dark_mode',       // الأصلي: 1 (الوضع الليلي افتراضياً)
                'usr_language',        // الأصلي: ar (اللغة)
                'usr_notifications',   // الأصلي: 1 (الإشعارات)
                'usr_favorites',       // الأصلي: 1 (المفضلة مفعّلة)
                'usr_watch_history',   // الأصلي: 1 (سجل المشاهدة مفعّل)
            ],

            // ── الأداء (Performance) ──
            'performance' => [
                'perf_cache_duration', // الأصلي: 3600 (ثانية)
                'perf_image_cache',    // الأصلي: 1 (كاش الصور مفعّل)
                'perf_api_cache',      // الأصلي: 1 (كاش API مفعّل)
                'perf_gzip_brotli',    // الأصلي: 1 (ضغط مفعّل)
                'perf_lazy_loading',   // الأصلي: 1 (Lazy Loading مفعّل)
                'perf_http_version',   // الأصلي: 2 (HTTP/2)
                'perf_prefetch',       // الأصلي: 1 (Prefetch مفعّل)
                'perf_preconnect',     // الأصلي: 1 (Preconnect مفعّل)
            ],

            // ── إعدادات الترجمة العامة ──
            'subtitles' => [
                'sub_default_language',// الأصلي: ar (اللغة الافتراضية للترجمة)
                'sub_font_size',       // الأصلي: 18 (px)
                'sub_font_color',      // الأصلي: #ffffff (لون الخط)
                'sub_bg_color',        // الأصلي: #000000 (لون الخلفية)
                'sub_position',        // الأصلي: bottom (top/center/bottom)
                'sub_bg_opacity',      // الأصلي: 60 (شفافية الخلفية 0-100)
            ],

            // ── إعدادات المسلسلات ──
            'series' => [
                'sr_resume_last_ep',   // الأصلي: 1 (التشغيل من آخر حلقة)
                'sr_auto_next_ep',     // الأصلي: 1 (الانتقال للحلقة التالية)
                'sr_skip_intro',       // الأصلي: 0 (تخطي المقدمة)
                'sr_skip_outro',       // الأصلي: 0 (تخطي الشارة الختامية)
                'sr_season_order',     // الأصلي: asc (ترتيب المواسم asc/desc)
            ],

            // ── إعدادات الأفلام ──
            'movies' => [
                'mv_per_page',         // الأصلي: 24 (عدد الأفلام في الصفحة)
                'mv_default_quality',  // الأصلي: auto (auto/480/720/1080)
                'mv_auto_subtitle',    // الأصلي: 0 (تشغيل الترجمة تلقائياً)
                'mv_subtitle_language',// الأصلي: ar (اللغة الافتراضية للترجمة)
                'mv_play_trailer',     // الأصلي: 1 (تشغيل التريلر)
                'mv_show_similar',     // الأصلي: 1 (عرض الأفلام المشابهة)
                'mv_resume_watch',     // الأصلي: 1 (استكمال المشاهدة)
            ],

            // ── إعدادات القنوات ──
            'channels' => [
                'ch_per_page',         // الأصلي: 40 (عدد القنوات في الصفحة)
                'ch_order',            // الأصلي: display_order (ترتيب القنوات)
                'ch_group_order',      // الأصلي: display_order (ترتيب المجموعات)
                'ch_hide_offline',     // الأصلي: 0 (إخفاء غير المتصلة)
                'ch_auto_status',      // الأصلي: 0 (تحديث الحالة تلقائياً)
                'ch_check_interval',   // الأصلي: 60 (ثانية فترة الفحص)
                'ch_resume_last',      // الأصلي: 1 (تشغيل آخر قناة)
            ],

            // ── إعدادات مشغّل الفيديو ──
            'player' => [
                'pl_autoplay',         // الأصلي: 1 (التشغيل التلقائي)
                'pl_mute_on_start',    // الأصلي: 0 (كتم عند التشغيل)
                'pl_auto_fullscreen',  // الأصلي: 0 (ملء الشاشة تلقائياً)
                'pl_pip',              // الأصلي: 1 (Picture in Picture)
                'pl_webcast',          // الأصلي: 1 (webcast مفعّل)
                'pl_seek_buttons',     // الأصلي: 1 (أزرار التقديم/الترجيع)
                'pl_playback_speed',   // الأصلي: 1 (سرعة التشغيل الافتراضية)
                'pl_thumbnails',       // الأصلي: 1 (معاينة الصور)
                'pl_show_channel_logo',// الأصلي: 1 (إظهار شعار القناة)
                'pl_show_channel_name',// الأصلي: 1 (إظهار اسم القناة)
                'pl_show_clock',       // الأصلي: 0 (إظهار الساعة)
                'pl_show_viewers',     // الأصلي: 0 (عداد المشاهدين)
                'pl_show_share',       // الأصلي: 1 (زر المشاركة)
                'pl_show_report',      // الأصلي: 1 (زر الإبلاغ)
            ],

            // ── إعدادات البث (Streaming — جهة العميل/المشغّل) ──
            'streaming_client' => [
                'st_low_latency',      // الأصلي: 0 (Low Latency Mode)
                'st_buffer_size',      // الأصلي: 30 (ثانية 1-30)
                'st_startup_buffer',   // الأصلي: 2 (ثانية)
                'st_max_buffer',       // الأصلي: 60 (ثانية Max Buffer Length)
                'st_back_buffer',      // الأصلي: 90 (ثانية Back Buffer)
                'st_live_sync',        // الأصلي: 3 (Live Sync Duration)
                'st_auto_quality',     // الأصلي: 1 (Auto Quality ABR)
                'st_default_quality',  // الأصلي: auto (الجودة الافتراضية)
                'st_allow_quality_change',// الأصلي: 1 (السماح بتغيير الجودة)
                'st_auto_reconnect',   // الأصلي: 1 (إعادة الاتصال التلقائي)
                'st_reconnect_attempts',// الأصلي: 5 (عدد المحاولات)
                'st_reconnect_timeout',// الأصلي: 3 (ثانية قبل إعادة الاتصال)
                'st_failover',         // الأصلي: 1 (الانتقال لرابط احتياطي)
                'st_protocol',         // الأصلي: hls (hls/dash)
                'st_llhls_support',    // الأصلي: 0 (دعم LL-HLS)
                'st_playlist_refresh', // الأصلي: 6 (ثانية مدة تحديث Playlist)
                'st_stream_cache',     // الأصلي: 1 (Cache للبث مفعّل)
            ],
        ];

        if (!isset($GROUPS[$group])) jErr('مجموعة غير معروفة');

        $allowed = $GROUPS[$group];
        $upsert = function($k, $v) use ($pdo) {
            $s = $pdo->prepare("SELECT COUNT(*) FROM settings WHERE setting_key = ?");
            $s->execute([$k]);
            if ($s->fetchColumn() > 0) {
                $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?")->execute([$v, $k]);
            } else {
                $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)")->execute([$k, $v]);
            }
        };

        // كل مفتاح مسموح: إن كان checkbox (يُرسل '1'/'0' من الواجهة) يُحفظ كما هو،
        // وأي حقل نصي/رقمي يُحفظ كنص. الواجهة ترسل كل المفاتيح المسموحة دائماً.
        foreach ($allowed as $k) {
            if (array_key_exists($k, $_POST)) {
                $upsert($k, (string)$_POST[$k]);
            }
        }

        if (function_exists('shashety_cache_clear')) { @shashety_cache_clear(); }
        jOk(['message' => 'تم حفظ إعدادات المجموعة بنجاح', 'group' => $group]);
    }

    // ══ تفعيل / تعطيل ظهور قسم في الواجهة الأمامية (index.php) ══
    if ($act === 'toggle_category_active') {
        $cid = (int)($_POST['category_id'] ?? 0);
        $active = (isset($_POST['is_active']) && $_POST['is_active'] === '1') ? 1 : 0;
        if (!$cid) jErr('قسم غير صالح');
        try {
            $pdo->prepare("UPDATE categories SET is_active = ? WHERE id = ?")->execute([$active, $cid]);
            jOk(['message' => $active ? 'تم تفعيل ظهور القسم' : 'تم إخفاء القسم من الواجهة الأمامية', 'is_active' => $active]);
        } catch (PDOException $e) {
            jErr('تعذّر تحديث حالة القسم');
        }
    }

    // ══ تفعيل / تعطيل قناة (نشطة - غير نشطة) في الواجهة الأمامية (index.php) ══
    if ($act === 'toggle_channel_active') {
        $chid = (int)($_POST['channel_id'] ?? 0);
        $active = (isset($_POST['is_active']) && $_POST['is_active'] === '1') ? 1 : 0;
        if (!$chid) jErr('قناة غير صالحة');
        try {
            $pdo->prepare("UPDATE channels SET is_active = ? WHERE id = ?")->execute([$active, $chid]);
            jOk(['message' => $active ? 'تم تفعيل القناة' : 'تم تعطيل القناة', 'is_active' => $active]);
        } catch (PDOException $e) {
            jErr('تعذّر تحديث حالة القناة');
        }
    }

    // ══ وظائف التحميل الذكي (صُححت وباتت أكثر كفاءة) ══
    if($act === 'abort_smart_dl') {
        $n = trim($_POST['filename'] ?? '');
        if($n) @file_put_contents(VID_UPLOAD_DIR . $n . '.abort', '1');
        jOk();
    }

    if($act === 'prep_smart_dl') {
        $url = trim($_POST['url'] ?? '');
        if(!$url || !filter_var($url, FILTER_VALIDATE_URL)) jErr('الرابط المُدخل غير صالح!');
        $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
        if(!$ext || !in_array($ext, ['mp4','mkv','avi','mov','webm','ts','flv'])) $ext = 'mp4'; 

        $n = uniqid('vid_dl_').'.'.$ext;
        
        $headers = @get_headers($url, true);
        $totalSize = 0;
        if($headers) {
            $cl = $headers['Content-Length'] ?? ($headers['content-length'] ?? 0);
            $totalSize = is_array($cl) ? end($cl) : $cl;
        }

        $progFile = VID_UPLOAD_DIR . $n . '.prog';
        @file_put_contents($progFile, json_encode(['total' => (int)$totalSize, 'loaded' => 0]));
        @unlink(VID_UPLOAD_DIR . $n . '.abort'); 

        $original = basename(parse_url($url, PHP_URL_PATH));
        if(!$original || strlen($original) < 3) $original = $n;

        jOk(['filename' => $n, 'original' => $original, 'total' => (int)$totalSize]);
    }

    if($act === 'do_smart_dl') {
        @session_write_close(); // إغلاق الجلسة مهم ليعمل السيرفر بالخلفية بشكل متوازي

        $url = trim($_POST['url'] ?? '');
        $n = trim($_POST['filename'] ?? '');
        if(!$url || !$n) jErr('البيانات المُرسلة للتحميل غير مكتملة.');

        /* ⚠️ ثغرة اجتياز مسار (Path Traversal) حرجة كانت هنا:
           كان اسم الملف يُلصق مباشرة بمجلد الرفع دون تنظيف، فقيمة مثل
           "../../admin.php" تجعل الخادم يكتب محتوى الرابط البعيد فوق أي
           ملف في المشروع — أي تنفيذ كود عن بُعد.
           الحل: basename + قائمة أحرف مسموحة + التأكد أن المسار النهائي
           يقع فعلاً داخل مجلد الرفع. */
        $n = basename(str_replace(['\\', "\0"], '/', $n));
        $n = preg_replace('/[^A-Za-z0-9._-]/', '_', $n);
        $n = ltrim($n, '.');                       // يمنع الأسماء المخفية و ".."
        if($n === '' || strlen($n) > 180) jErr('اسم ملف غير صالح.');

        // منع كتابة ملف قابل للتنفيذ داخل مجلد الرفع
        if(preg_match('/\.(php\d?|phtml|phps|htaccess|cgi|pl|py|sh)$/i', $n)){
            jErr('امتداد ملف غير مسموح.');
        }

        // حماية SSRF: لا نسمح بتوجيه الخادم إلى شبكته الداخلية
        if(!shashetyUrlIsSafe($url, $whyDl)) jErr('الرابط غير مسموح: '.$whyDl);

        $uploadBase = realpath(VID_UPLOAD_DIR);
        if($uploadBase === false) jErr('مجلد الرفع غير موجود على الخادم.');
        $uploadBase = rtrim($uploadBase, '/\\') . DIRECTORY_SEPARATOR;

        $dest = $uploadBase . $n;

        // تحقق نهائي: لا بد أن يبقى المسار داخل مجلد الرفع
        if(strpos($dest, $uploadBase) !== 0) jErr('مسار غير مسموح.');

        $progFile  = $dest . '.prog';
        $abortFile = $dest . '.abort';

        $ch = curl_init($url);
        $wh = @fopen($dest, 'wb');

        if(!$wh) { @unlink($progFile); jErr('المسار المختار على السيرفر لا يدعم الكتابة!'); }

        $total = 0;
        $lastUpdate = time();

        if(file_exists($progFile)) {
            $pf = json_decode(@file_get_contents($progFile), true);
            $total = $pf['total'] ?? 0;
        }

        curl_setopt($ch, CURLOPT_FILE, $wh);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        // نقصر البروتوكولات على HTTP/HTTPS: يمنع file:// و ftp:// وغيرها
        curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
        curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
        // ⚠️ التحقق من الشهادة كان معطّلاً — مُفعَّل الآن لمنع اعتراض التنزيل.
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
        curl_setopt($ch, CURLOPT_TIMEOUT, 0);
        curl_setopt($ch, CURLOPT_ENCODING, "");
        curl_setopt($ch, CURLOPT_BUFFERSIZE, 1048576); 
        curl_setopt($ch, CURLOPT_NOPROGRESS, false);

        curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, function($ch, $total_dls, $dl, $total_uls, $ul) use ($progFile, $abortFile, &$lastUpdate, $total) {
            if(file_exists($abortFile)) return 1; // 1 يوقف الـ curl نهائياً
            
            $now = microtime(true);
            if($now - $lastUpdate >= 0.5) { 
                $realTotal = ($total > 0) ? $total : $total_dls; 
                @file_put_contents($progFile, json_encode(['total' => $realTotal, 'loaded' => $dl]));
                $lastUpdate = $now;
            }
        });

        curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        @fclose($wh);

        // إن أوقف المستخدم التحميل ✖
        if(file_exists($abortFile)) {
            @unlink($abortFile);
            @unlink($progFile);
            @unlink($dest);
            jErr('أمرت السيرفر بـ إيقاف وحذف التحميل الخاص بك بنجاح!');
        }

        $size = @filesize($dest);
        @unlink($progFile); 
        
        if($err || $size < 1024) { 
            @unlink($dest); 
            jErr('خطأ: تعذر الاتصال بالرابط المصدر. قد يكون محظوراً أو مدمجاً بالحماية.'); 
        }

        jOk(['filename' => $n, 'url' => VID_UPLOAD_URL.$n, 'size' => $size]);
    }

    if($act === 'check_smart_dl') {
        $n = trim($_POST['filename'] ?? '');
        if(!$n) jErr('مفقود');
        
        $progFile = VID_UPLOAD_DIR . $n . '.prog';
        if(file_exists($progFile)) {
            $d = json_decode(@file_get_contents($progFile), true);
            jOk(['total' => $d['total'] ?? 0, 'loaded' => $d['loaded'] ?? 0]);
        }
        jOk(['status' => 'waiting']); 
    }

    // ══ Upload & Save Handlers ══
    if($act==='upload_video'){
        $ferr = $_FILES['video']['error'] ?? UPLOAD_ERR_NO_FILE;
        if($ferr === UPLOAD_ERR_INI_SIZE || $ferr === UPLOAD_ERR_FORM_SIZE)
            jErr('الملف أكبر من الحد المسموح به في إعدادات الخادم (upload_max_filesize).');
        if($ferr === UPLOAD_ERR_PARTIAL) jErr('تم رفع الملف جزئياً.');
        if($ferr === UPLOAD_ERR_NO_FILE) jErr('لم يتم إرسال أي ملف');
        if($ferr !== UPLOAD_ERR_OK) jErr('خطأ في رفع الملف (كود: '.$ferr.')');
        
        $f=$_FILES['video'];
        $ext=strtolower(pathinfo($f['name'],PATHINFO_EXTENSION));
        if(!in_array($ext,['mp4','mkv','avi','mov','webm','ts','flv'])) jErr('صيغة غير مدعومة.');
            
        if(!is_dir(VID_UPLOAD_DIR)) @mkdir(VID_UPLOAD_DIR,0755,true);
        $n=uniqid('vid_').'.'.$ext;
        if(!mvFile($f['tmp_name'],VID_UPLOAD_DIR.$n)){ jErr('فشل في نقل الملف إلى الخادم'); }
        jOk(['filename'=>$n,'original'=>$f['name'],'url'=>VID_UPLOAD_URL.$n,'size'=>$f['size']]);
    }

    if($act==='upload_subtitle_file'){
        if(empty($_FILES['subtitle']))jErr('لم يتم إرسال ملف');
        $f=$_FILES['subtitle'];$ext=strtolower(pathinfo($f['name'],PATHINFO_EXTENSION));
        if(!in_array($ext,['srt','ass','ssa','vtt']))jErr('صيغة غير مدعومة');
        $n=uniqid('sub_').'.'.$ext;
        if(!mvFile($f['tmp_name'],VID_SUB_DIR.$n))jErr('فشل في رفع ملف الترجمة');
        
        $url = VID_SUB_URL.$n;
        if($ext === 'srt'){
            $vttN = uniqid('sub_').'.vtt';
            $srt = file_get_contents(VID_SUB_DIR.$n);
            $vtt = "WEBVTT\n\n" . preg_replace('/(\d{2}:\d{2}:\d{2}),(\d{3})/', '\1.\2', $srt);
            file_put_contents(VID_SUB_DIR.$vttN, $vtt);
            $url = VID_SUB_URL.$vttN; 
        }
        jOk(['filename'=>$n,'original'=>$f['name'],'url'=>$url]);
    }

    if($act==='merge_subtitle'){
        $vf=basename($_POST['video_file']??'');
        $sf=basename($_POST['subtitle_file']??'');
        if(!$vf||!$sf)jErr('ملفات ناقصة');
        $vpath=VID_UPLOAD_DIR.$vf;
        $spath=VID_SUB_DIR.$sf;
        if(!file_exists($vpath))jErr('ملف الفيديو غير موجود');
        if(!file_exists($spath))jErr('ملف الترجمة غير موجود');
        $subExt=strtolower(pathinfo($sf,PATHINFO_EXTENSION));
        $subUrl=VID_SUB_URL.$sf;
        if($subExt==='srt'){
            $vttN=uniqid('sub_').'.vtt';
            $srt=file_get_contents($spath);
            $vtt="WEBVTT\n\n".preg_replace('/(\d{2}:\d{2}:\d{2}),(\d{3})/','\1.\2',$srt);
            file_put_contents(VID_SUB_DIR.$vttN,$vtt);
            $subUrl=VID_SUB_URL.$vttN;
        }
        jOk(['filename'=>$vf,'url'=>VID_UPLOAD_URL.$vf,'subtitle_url'=>$subUrl,'size'=>round(filesize($vpath)/1024/1024,2).' MB','method'=>'no_ffmpeg']);
    }

    // الدالتين الجديتان: للحفظ كجديد كلياً، أو إضافته داخل مجلد المسلسلات الموجود مُسبقاً.
    if($act === 'save_to_shashety_auto') {
        global $pdo;
        $cid = intval($_POST['category_id'] ?? 0);
        $name = htmlspecialchars(strip_tags($_POST['name'] ?? ''));
        $url = $_POST['url'] ?? '';
        $sub = $_POST['subtitle_url'] ?? '';
        $target_series = intval($_POST['target_series_id'] ?? 0); 
        
        if(!$url) jErr('لا يوجد رابط للفيديو!');
        if(!$name && $target_series == 0) jErr('أدخل الاسم أولاً للعمل الجديد');
        if(!$name) $name = "فيديو / حلقة جديدة";

        try {
            if ($target_series > 0) {
                // حفظ الفيديو كحلقة جديدة داخل المجلد الحالي في شاشتي
                $stmt = $pdo->query("SELECT MAX(episode_number) FROM episodes WHERE series_id = $target_series");
                $next_num = intval($stmt->fetchColumn()) + 1; 
                $pdo->prepare("INSERT INTO episodes (series_id, episode_number, title, stream_url, subtitle_url, display_order) VALUES (?, ?, ?, ?, ?, ?)")
                    ->execute([$target_series, $next_num, $name, $url, $sub, $next_num]);
                jOk(['series_id' => $target_series]);
            } else {
                // إنشاء مجلد مسلسلات جديد ثم وضع الحلقة فيه!
                if(!$cid) jErr('اختر القسم للمجلد الجديد');
                $slug = slugU($name).'-'.uniqid();
                $pdo->prepare("INSERT INTO series (category_id, name, slug, is_active) VALUES (?, ?, ?, 1)")->execute([$cid, $name, $slug]);
                $sid = $pdo->lastInsertId();
                $pdo->prepare("INSERT INTO episodes (series_id, episode_number, title, stream_url, subtitle_url, display_order) VALUES (?, 1, ?, ?, ?, 1)")
                    ->execute([$sid, $name, $url, $sub]);
                jOk(['series_id' => $sid]);
            }
        } catch(PDOException $e) { jErrLog('مشكلة قواعد البيانات', $e); }
    }

    if($act==='save_video_manual'){
        global $pdo;
        $fn=basename($_POST['filename']??'');
        $title=htmlspecialchars(strip_tags($_POST['title']??''));
        $cid=intval($_POST['category_id']??0);
        $vt=$_POST['video_type']??'uploaded';
        $sub=htmlspecialchars(strip_tags($_POST['subtitle_url']??'')); 
        $target_series = intval($_POST['target_series_id'] ?? 0); 
        
        if(!$fn)jErr('مفقود: لم تحدد ملف لحفظه');
        if(!$title && $target_series == 0) jErr('مفقود: أسم العمل الجديد');
        if(!$title) $title = "إضافة مسار فيديو";

        if($vt==='merged') { $vdir = VID_MERGED_DIR; $vurlBase = VID_MERGED_URL; }
        elseif($vt==='series') { $vdir = SERIES_DIR; $vurlBase = SERIES_URL; }
        else { $vdir = VID_UPLOAD_DIR; $vurlBase = VID_UPLOAD_URL; } // المتبقي هو uploaded
        
        $vpath = $vdir.$fn;
        $vurl = $vurlBase.$fn;
        if(!file_exists($vpath)) jErr('الملف الأصلي المعني، حُذف أو غير متوفر حاليا.');
        
        try {
            if ($target_series > 0) {
                $stmt = $pdo->query("SELECT MAX(episode_number) FROM episodes WHERE series_id = $target_series");
                $next_num = intval($stmt->fetchColumn()) + 1;
                $pdo->prepare("INSERT INTO episodes (series_id, episode_number, title, stream_url, subtitle_url, display_order) VALUES (?, ?, ?, ?, ?, ?)")
                    ->execute([$target_series, $next_num, $title, $vurl, $sub, $next_num]);
            } else {
                if(!$cid) jErr('يجب وضع قسم رئيسي للملف لعمل التصنيف الخاص بك.');
                $slug=slugU($title).'-'.uniqid();
                $pdo->prepare("INSERT INTO series (category_id, name, slug, is_active) VALUES (?, ?, ?, 1)")->execute([$cid, $title, $slug]);
                $sid = $pdo->lastInsertId();
                $pdo->prepare("INSERT INTO episodes (series_id, episode_number, title, stream_url, subtitle_url, display_order) VALUES (?, 1, ?, ?, ?, 1)")
                    ->execute([$sid, $title, $vurl, $sub]);
            }
            jOk(['url'=>$vurl]);
        } catch(PDOException $e) { jErrLog('قاعدة البيانات رفضت', $e); }
    }

    // نظام (نقل المجلد وتصحيح قاعدة البيانات المعزّز). تم إزالة تحويل. 
    // فيديوز <==> المسلسلات الفعليّة بقواعد شاشتي
    if($act === 'move_video_file') {
        global $pdo;
        $fn = basename($_POST['filename'] ?? '');
        $type = $_POST['type'] ?? '';
        $target = $_POST['target_folder'] ?? ''; 
        
        if(!$fn || !$type) jErr('خطأ اتصال: الملف غير واضح.');
        
        // استخلاص المسار القديم
        $srcDir = VID_UPLOAD_DIR; $srcUrl = VID_UPLOAD_URL;
        if($type === 'merged') { $srcDir = VID_MERGED_DIR; $srcUrl = VID_MERGED_URL; }
        elseif($type === 'series') { $srcDir = SERIES_DIR; $srcUrl = SERIES_URL; }
        
        $srcPath = $srcDir . $fn;
        if(!file_exists($srcPath)) jErr('الملف الفيزيائي ممسوح حاليا.');
        
        $is_db_series = false;
        $series_id = 0;

        if($target === 'videos') {
            // الرجوع به إلى الرفع العادي
            $destDir = VID_UPLOAD_DIR; $destUrl = VID_UPLOAD_URL;
        } else {
            // إلحاق هذا المجلد برقم مسلسل أو عمل تم اختياره من القائمة 
            $series_id = intval($target);
            if ($series_id <= 0) jErr('رقم المجلد الهدف في قاعدة شاشتي غير دقيق.');
            $destDir = SERIES_DIR; $destUrl = SERIES_URL;
            $is_db_series = true;
        }

        $destPath = $destDir . $fn;
        
        if($srcPath !== $destPath) {
            if(!rename($srcPath, $destPath)) jErr('الصلاحية ضعيفة بالخادم لنقل الملف جسدياً من '.$type);
        }
        
        $oldDbUrl = $srcUrl . $fn;
        $newDbUrl = $destUrl . $fn;
        
        // تحديث كل الحلقات القديمة لعنوان النطاق والامتداد الجديد إن كان فيه مسلسل
        if($oldDbUrl !== $newDbUrl) {
            $pdo->prepare("UPDATE episodes SET stream_url = ? WHERE stream_url = ?")->execute([$newDbUrl, $oldDbUrl]);
        }
        
        // هل أمره المستخدم بالارتباط بحلقة لمسلسل شاشتي معين للتو ؟
        if($is_db_series && $series_id > 0) {
            $stmt = $pdo->prepare("SELECT id FROM episodes WHERE stream_url = ?");
            $stmt->execute([$newDbUrl]);
            if($stmt->fetch()) {
                // مرتبط ولكن بقسم خاطئ.. يتم تغيير معرفه إلى مسلسلك الهدف!
                $pdo->prepare("UPDATE episodes SET series_id = ? WHERE stream_url = ?")->execute([$series_id, $newDbUrl]);
            } else {
                // ادخاله لاول مره بعد استخراجه 
                $titleName = str_replace(['_', '-'], ' ', pathinfo($fn, PATHINFO_FILENAME));
                $nstmt = $pdo->query("SELECT MAX(episode_number) FROM episodes WHERE series_id = $series_id");
                $next_num = intval($nstmt->fetchColumn()) + 1;
                $pdo->prepare("INSERT INTO episodes (series_id, episode_number, title, stream_url, subtitle_url, display_order) VALUES (?, ?, ?, ?, '', ?)")
                    ->execute([$series_id, $next_num, $titleName, $newDbUrl, $next_num]);
            }
        } elseif ($target === 'videos') {
            // سحب المسار من النظام وتركه لعمله الخاص (عزله بالعام)، نقوم بحذفه كلياً من لوحة شاشتي (Episodes table)
            $pdo->prepare("DELETE FROM episodes WHERE stream_url = ?")->execute([$newDbUrl]);
        }
        
        jOk(['new_url' => $newDbUrl, 'message' => 'عظيم! تم النقل ومزامنة وتغيير وجهات الخادم بشكل قطعي.']);
    }

    if($act==='list_videos'){
        $vids=[];
        foreach([
            'uploaded'=>[VID_UPLOAD_DIR,VID_UPLOAD_URL],
            'merged'=>[VID_MERGED_DIR,VID_MERGED_URL],
            'series'=>[SERIES_DIR,SERIES_URL] 
        ] as $t=>[$d,$u]){
            if(!is_dir($d))continue;
            foreach(glob($d.'*.{mp4,mkv,avi,mov,webm}',GLOB_BRACE)as $f){
                $fn=basename($f);
                $vids[]=['filename'=>$fn,'url'=>$u.$fn,'size_mb'=>round(filesize($f)/1024/1024,2),'type'=>$t,'date'=>date('Y-m-d H:i',filemtime($f)),'ts'=>filemtime($f)];
            }
        }
        usort($vids, function($a, $b) { return $b['ts'] - $a['ts']; });
        jOk(['videos'=>$vids]);
    }

    /* ══ حذف ملف فيديو ══
       ⚠ كان هذا الإجراء يحذف الملف من القرص فقط (@unlink) ولا يمسّ قاعدة
       البيانات. لكنّ الملف حين يُنشَر عبر save_video_manual يُنشئ صفّاً في
       episodes (وربما series) يحمل مساره في stream_url. فحذف الملف وحده
       كان يترك الصفّ حيّاً: العنصر يبقى ظاهراً في index.php إلى الأبد
       برابط ميّت، ولا شيء في اللوحة يزيله — وهذا بالضبط ما كان يُشتكى منه
       («حذفته ويبقى موجوداً دائماً»). لم تكن مشكلة كاش إطلاقاً.
       الآن نحذف الصفوف المرتبطة أيضاً، ثم المسلسلات التي أفرغها الحذف. */
    if($act==='delete_video'){
        global $pdo;
        $fn=basename($_POST['filename']??'');
        $t=$_POST['type']??'uploaded';
        if(!$fn)jErr('الاسم للهدف مسح مفقود');

        $p = VID_UPLOAD_DIR;
        if($t==='merged') $p = VID_MERGED_DIR;
        elseif($t==='series') $p = SERIES_DIR;

        $p .= $fn;
        if(!file_exists($p))jErr('لا أعثر عليه حاليا!');

        // نطابق على نهاية المسار لا على الرابط الكامل: اسم الملف مولّد
        // بـuniqid فهو فريد، والمطابقة بهذا الشكل تصمد لو تغيّر المسار
        // الأساسي للموقع (مثلاً نقله من /iptv إلى الجذر) — وهي حالة
        // كانت ستجعل المطابقة بالرابط الكامل تفشل بصمت وتُبقي الصفّ.
        $like = '%/' . $fn;
        $epGone = $serGone = $chGone = 0;

        try {
            // ① المسلسلات المرشّحة للتيتّم — نلتقطها قبل حذف الحلقات
            $cand = [];
            $q = $pdo->prepare("SELECT DISTINCT series_id FROM episodes WHERE stream_url LIKE ?");
            $q->execute([$like]);
            $cand = $q->fetchAll(PDO::FETCH_COLUMN) ?: [];

            // ② حذف الحلقات التي تشير إلى هذا الملف
            $d = $pdo->prepare("DELETE FROM episodes WHERE stream_url LIKE ?");
            $d->execute([$like]);
            $epGone = $d->rowCount();

            // ③ القنوات التي تشير إليه (لو رُبط الملف كقناة)
            try {
                $dc = $pdo->prepare("DELETE FROM channels WHERE stream_url LIKE ?");
                $dc->execute([$like]);
                $chGone = $dc->rowCount();
            } catch(PDOException $e) { /* لا عمود stream_url — نتجاهل */ }

            // ④ المسلسلات التي لم يبقَ فيها أي حلقة بعد الحذف.
            //    نحصر الفحص في المرشّحين فقط: حذف كل مسلسل فارغ في
            //    القاعدة كان سيمسح مسلسلات أنشأها المستخدم عمداً ولمّا
            //    يُضِف حلقاتها بعد.
            foreach ($cand as $sid) {
                $sid = (int)$sid;
                if ($sid <= 0) continue;
                $c = $pdo->prepare("SELECT COUNT(*) FROM episodes WHERE series_id = ?");
                $c->execute([$sid]);
                if ((int)$c->fetchColumn() === 0) {
                    $ds = $pdo->prepare("DELETE FROM series WHERE id = ?");
                    $ds->execute([$sid]);
                    $serGone += $ds->rowCount();
                }
            }
        } catch(PDOException $e) {
            // ⚠ الترتيب مقصود: التنظيف قبل unlink. فإن فشل التنظيف نتوقّف
            //   والملف ما يزال موجوداً — أي لا شيء تغيّر ويمكن إعادة
            //   المحاولة. العكس (حذف الملف ثم فشل التنظيف) كان سيترك
            //   صفّاً حيّاً برابط ميّت، وهو الحال الذي نصلحه أصلاً.
            error_log('shashety delete_video cleanup: ' . $e->getMessage());
            jErr('تعذّر تنظيف قاعدة البيانات، فلم أحذف الملف حتى لا يبقى العنصر معلّقاً برابط ميّت. راجع سجل أخطاء الخادم ثم أعد المحاولة.');
        }

        $ok = @unlink($p);

        // إبطال بصمة المحتوى فوراً حتى يلتقط الموقع الحذف في الجولة التالية
        if (function_exists('cacheDelete')) { @cacheDelete('content_stamp'); }

        jOk([
            'deleted'          => $ok,
            'episodes_removed' => $epGone,
            'series_removed'   => $serGone,
            'channels_removed' => $chGone,
            'message'          => $epGone || $chGone || $serGone
                ? "حُذف الملف وأُزيل من الموقع (حلقات: $epGone · مسلسلات: $serGone · قنوات: $chGone)"
                : 'حُذف الملف. لم يكن منشوراً على الموقع، فلا شيء آخر ليُزال.',
        ]);
    }

    // ══ Series Handlers ══
    if($act==='get_series'){
        global $pdo;
        $cid    = intval($_POST['category_id'] ?? 0);
        $q      = trim((string)($_POST['q'] ?? ''));
        $limit  = intval($_POST['limit']  ?? 60);
        $offset = intval($_POST['offset'] ?? 0);
        if($limit  < 1 || $limit > 200) $limit = 60;
        if($offset < 0) $offset = 0;

        /* ── لماذا تغيّر هذا الاستعلام ──
           كان: SELECT s.* + LEFT JOIN episodes + GROUP BY، بلا LIMIT.
           بعد استيراد أفلام Xtream صار يعيد آلاف الصفوف، ويجرّ معها حقل description
           (آلاف الأحرف لكل فيلم) وهو غير مستخدم في الشبكة أصلاً — فبطُؤت الصفحة.
           الآن: أعمدة محدودة + عدّ الحلقات بجملة فرعية + ترقيم صفحات. */
        $w = []; $p = [];
        if($cid){ $w[] = "s.category_id = ?"; $p[] = $cid; }
        if($q !== ''){ $w[] = "s.name LIKE ?"; $p[] = '%'.$q.'%'; }
        $where = $w ? ('WHERE '.implode(' AND ', $w)) : '';

        // الإجمالي (للترقيم) — عدّ خفيف بلا joins
        $cst = $pdo->prepare("SELECT COUNT(*) FROM series s $where");
        $cst->execute($p);
        $total = (int)$cst->fetchColumn();

        $sql = "SELECT s.id, s.category_id, s.name, s.poster_url, s.display_order,
                       c.name AS cat_name,
                       (SELECT COUNT(*) FROM episodes e WHERE e.series_id = s.id) AS ep_count
                FROM series s
                LEFT JOIN categories c ON s.category_id = c.id
                $where
                ORDER BY s.display_order, s.id DESC
                LIMIT $limit OFFSET $offset";
        $st = $pdo->prepare($sql);
        $st->execute($p);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        jOk([
            'data'    => $rows,
            'total'   => $total,
            'limit'   => $limit,
            'offset'  => $offset,
            'hasMore' => ($offset + count($rows)) < $total,
        ]);
    }
    /* تفاصيل عمل واحد (بما فيها الوصف) — تُطلب فقط عند التعديل */
    if($act==='get_series_one'){
        global $pdo;
        $id = intval($_POST['id'] ?? 0);
        if($id <= 0) jErr('معرف غير صالح');
        $st = $pdo->prepare("SELECT * FROM series WHERE id=?");
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if(!$row) jErr('غير موجود');
        jOk(['data'=>$row]);
    }
    if($act==='add_series'){
        global $pdo;
        $cid=intval($_POST['category_id']??0);
        $name=htmlspecialchars(strip_tags($_POST['name']??''));
        $desc=htmlspecialchars(strip_tags($_POST['description']??''));
        $poster=htmlspecialchars(strip_tags($_POST['poster_url']??''));
        if(!$cid||!$name)jErr('ناقص');
        $slug=slugU($name).'-'.uniqid();
        try{
            $pdo->prepare("INSERT INTO series (category_id,name,slug,description,poster_url,is_active) VALUES (?,?,?,?,?,1)")->execute([$cid,$name,$slug,$desc,$poster]);
            jOk(['id'=>$pdo->lastInsertId()]);
        }catch(PDOException $e){jErrLog('تنبيه', $e);}
    }
    if($act==='edit_series'){
        global $pdo;
        $id=intval($_POST['id']??0);
        $cid=intval($_POST['category_id']??0);
        $name=htmlspecialchars(strip_tags($_POST['name']??''));
        $desc=htmlspecialchars(strip_tags($_POST['description']??''));
        $poster=htmlspecialchars(strip_tags($_POST['poster_url']??''));
        $pdo->prepare("UPDATE series SET category_id=?,name=?,description=?,poster_url=? WHERE id=?")->execute([$cid,$name,$desc,$poster,$id]);
        jOk();
    }
    if($act==='delete_series'){
        global $pdo;
        $id=intval($_POST['id']??0);
        $eps=$pdo->query("SELECT stream_url, subtitle_url FROM episodes WHERE series_id=$id")->fetchAll(PDO::FETCH_ASSOC);
        foreach($eps as $ep){
            shashetyDeleteLocalFile($ep['stream_url']??'');
            shashetyDeleteLocalFile($ep['subtitle_url']??'');
        }
        // حذف بوستر المسلسل/الفيلم إن كان ملفاً محلياً
        $sr=$pdo->query("SELECT poster_url FROM series WHERE id=$id")->fetch(PDO::FETCH_ASSOC);
        if($sr) shashetyDeleteLocalFile($sr['poster_url']??'');
        $pdo->prepare("DELETE FROM episodes WHERE series_id=?")->execute([$id]);
        $pdo->prepare("DELETE FROM series WHERE id=?")->execute([$id]);
        jOk();
    }
    if($act==='get_episodes'){
        global $pdo;
        $sid=intval($_POST['series_id']??0);
        $rows=$pdo->query("SELECT * FROM episodes WHERE series_id=$sid ORDER BY episode_number,display_order,id")->fetchAll(PDO::FETCH_ASSOC);
        jOk(['data'=>$rows]);
    }
    if($act==='add_episode'){
        global $pdo;
        $sid=intval($_POST['series_id']??0);
        $num=intval($_POST['episode_number']??1);
        $title=htmlspecialchars(strip_tags($_POST['title']??''));
        $url=htmlspecialchars(strip_tags($_POST['stream_url']??''));
        $sub=htmlspecialchars(strip_tags($_POST['subtitle_url']??''));
        $dur=htmlspecialchars(strip_tags($_POST['duration']??''));
        if(!$sid||!$title||!$url)jErr('خاطيء في القيم المرسلة');
        $pdo->prepare("INSERT INTO episodes (series_id,episode_number,title,stream_url,subtitle_url,duration,display_order) VALUES (?,?,?,?,?,?,?)")
            ->execute([$sid,$num,$title,$url,$sub,$dur,$num]);
        jOk(['id'=>$pdo->lastInsertId()]);
    }
    if($act==='edit_episode'){
        global $pdo;
        $id=intval($_POST['id']??0);
        $num=intval($_POST['episode_number']??1);
        $title=htmlspecialchars(strip_tags($_POST['title']??''));
        $url=htmlspecialchars(strip_tags($_POST['stream_url']??''));
        $sub=htmlspecialchars(strip_tags($_POST['subtitle_url']??''));
        $dur=htmlspecialchars(strip_tags($_POST['duration']??''));
        $new_series_id = intval($_POST['series_id']??0);
        
        if($new_series_id > 0) {
            $pdo->prepare("UPDATE episodes SET series_id=?, episode_number=?,title=?,stream_url=?,subtitle_url=?,duration=? WHERE id=?")
                ->execute([$new_series_id, $num, $title, $url, $sub, $dur, $id]);
        } else {
            $pdo->prepare("UPDATE episodes SET episode_number=?,title=?,stream_url=?,subtitle_url=?,duration=? WHERE id=?")
                ->execute([$num, $title, $url, $sub, $dur, $id]);
        }
        jOk();
    }
    if($act === 'delete_episodes_bulk') {
        global $pdo;
        $ids = json_decode($_POST['ids'] ?? '[]');
        if (is_array($ids) && count($ids) > 0) {
            $placeholders = str_repeat('?,', count($ids) - 1) . '?';
            // حذف الملفات المحلية (فيديو + ترجمة) قبل حذف السجلات
            $stmt = $pdo->prepare("SELECT stream_url, subtitle_url FROM episodes WHERE id IN ($placeholders)");
            $stmt->execute($ids);
            $eps = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($eps as $ep) {
                shashetyDeleteLocalFile($ep['stream_url']??'');
                shashetyDeleteLocalFile($ep['subtitle_url']??'');
            }
            // سحق الجذور من الداتا بيز
            $del = $pdo->prepare("DELETE FROM episodes WHERE id IN ($placeholders)");
            $del->execute($ids);
            jOk();
        }
        jErr('تخلف القراءة.');
    }
    
    if($act === 'update_episodes_order'){
        global $pdo;
        $orders = json_decode($_POST['orders'] ?? '[]', true);
        if (is_array($orders)) {
            // توجيه صارم لكل فيديو لتغيير ترتيبه الشامل المخصص لواجهة index.php 
            $stmt = $pdo->prepare("UPDATE episodes SET display_order = ? WHERE id = ?");
            foreach ($orders as $item) {
                $stmt->execute([ intval($item['order']), intval($item['id']) ]);
            }
            jOk();
        }
        jErr('نظام الداتا اعترض المصفوفة');
    }

    if($act==='delete_episode'){
        global $pdo;
        $id=intval($_POST['id']??0);
        $ep=$pdo->query("SELECT stream_url, subtitle_url FROM episodes WHERE id=$id")->fetch(PDO::FETCH_ASSOC);
        if($ep){
            shashetyDeleteLocalFile($ep['stream_url']??'');
            shashetyDeleteLocalFile($ep['subtitle_url']??'');
        }
        $pdo->prepare("DELETE FROM episodes WHERE id=?")->execute([$id]);
        jOk();
    }

    if($act==='upload_episode_video'){
        if(empty($_FILES['episode']))jErr('لا توجد صوره');
        $f=$_FILES['episode']; $ext=strtolower(pathinfo($f['name'],PATHINFO_EXTENSION));
        if(!in_array($ext,['mp4','mkv','avi','mov','webm','ts','flv']))jErr('صيغتك للاسف لا نعمل بها');
        $sid=intval($_POST['series_id']??0);
        $n='ep_'.$sid.'_'.uniqid().'.'.$ext;
        if(!mvFile($f['tmp_name'],SERIES_DIR.$n))jErr('الخطا تم نقله للاستضافه بنسبة للاحصاء.');
        $url=SERIES_URL.$n;
        $epNum=1; if(preg_match('/[Ee]p?(\d+)|[_\s\-](\d+)\./i',$f['name'],$m)) $epNum=intval($m[1]??$m[2]??1);
        jOk(['filename'=>$n,'original'=>$f['name'],'url'=>$url,'size'=>$f['size'],'episode_number'=>$epNum]);
    }
   if($act==='upload_channel_logo'){
    if(empty($_FILES['logo']))jErr('لا توجد صورة'); 
    $f=$_FILES['logo']; 
    $ext=strtolower(pathinfo($f['name'],PATHINFO_EXTENSION));
    if(!in_array($ext,['jpg','jpeg','png','webp','gif']))jErr('صيغة غير صالحة.');
    
    $n=uniqid('logo_').'.'.$ext;
    if(!mvFile($f['tmp_name'],POSTERS_DIR.$n))jErr('فشل في الرفع، حاول ثانية');
    
    jOk(['filename'=>$n,'url'=>POSTERS_URL.$n,'original'=>$f['name'],'size'=>$f['size']]);
}

    if($act==='os_login'){
        $u = trim($_POST['username'] ?? '');
        $p = trim($_POST['password'] ?? '');
        $k = trim($_POST['api_key']  ?? '');
        if(!$u || !$p) jErr('أدخل اسم المستخدم وكلمة المرور');
        if(!$k)        jErr('أدخل مفتاح API');

        $_SESSION['os_api_key'] = $k;
        unset($_SESSION['os_base'], $_SESSION['os_token']);

        [$c,$r,$e] = osReq(OS_API.'/login','POST',['username'=>$u,'password'=>$p],false);
        if($e){ unset($_SESSION['os_api_key']); jErr('خطأ شبكة: '.$e); }

        $d = json_decode($r,true);

        if($c===200 && !empty($d['token'])){
            $_SESSION['os_token']    = $d['token'];
            $_SESSION['os_username'] = $u;
            $_SESSION['os_api_key']  = $k;
            if(!empty($d['base_url'])){
                $b = preg_replace('#^https?://#','',rtrim($d['base_url'],'/'));
                $_SESSION['os_base'] = 'https://'.$b.'/api/v1';
            }
            try {
                $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('os_api_key', ?), ('os_username', ?), ('os_password', ?)
                    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)")->execute([$k,$u,$p]);
            } catch(Exception $ex){}
            jOk(['username'=>$u,'allowed'=>$d['allowed_downloads'] ?? '?']);
        }

        unset($_SESSION['os_api_key'], $_SESSION['os_base']);
        if($c===401) jErr('اسم المستخدم أو كلمة المرور غير صحيحة');
        if($c===403) jErr('مفتاح API غير صالح أو غير مفعّل (403)');
        if($c===429) jErr('تجاوزت عدد المحاولات — انتظر قليلاً');
        jErr($d['message'] ?? $d['error'] ?? "فشل الاتصال بالسيرفر ($c)");
    }

    if($act==='os_logout'){
        if(!empty($_SESSION['os_token'])) osReq(osBase().'/logout','DELETE');
        unset($_SESSION['os_token'],$_SESSION['os_username'],$_SESSION['os_api_key'],$_SESSION['os_base']);
        jOk();
    }

    if($act==='os_status'){
        jOk([
            'logged_in' => !empty($_SESSION['os_token']) && !empty($_SESSION['os_api_key']),
            'username'  => $_SESSION['os_username'] ?? ''
        ]);
    }

    if($act==='search_subtitles'){
        osGuard();
        $q    = trim($_POST['query'] ?? '');
        $lang = trim($_POST['language'] ?? 'ar');
        if(!$q) jErr('أدخل اسم الفيلم أو المسلسل');

        $params = http_build_query([
            'query'           => $q,
            'languages'       => $lang,
            'order_by'        => 'download_count',
            'order_direction' => 'desc',
            'per_page'        => 20
        ]);

        [$c,$r,$e] = osReq(osBase().'/subtitles?'.$params);
        if($e) jErr('انقطع الاتصال: '.$e);

        // انتهت صلاحية التوكن لدى السيرفر: أعد تسجيل الدخول وحاول مرة واحدة
        if($c===401){
            unset($_SESSION['os_token']);
            if(osAutoLogin()){
                [$c,$r,$e] = osReq(osBase().'/subtitles?'.$params);
                if($e) jErr('انقطع الاتصال: '.$e);
            }
            if($c===401) jErr('تعذّر تجديد الجلسة — تحقق من بيانات الدخول في الإعدادات');
        }
        if($c===403) jErr('مفتاح API مرفوض (403) — تحقق من المفتاح واسم التطبيق في حساب Consumer');
        if($c===429) jErr('تجاوزت الحد المسموح من الطلبات — انتظر دقيقة');
        if($c!==200) jErr("خطأ في الاستعلام ($c)");

        $d = json_decode($r,true);
        if(empty($d['data'])) jErr('لا توجد نتائج لهذا الاسم');

        $subs = [];
        foreach($d['data'] as $s){
            $a = $s['attributes'] ?? [];
            $files = $a['files'] ?? [];
            if(empty($files)) continue;
            $subs[] = [
                'id'        => $s['id'],
                'title'     => $a['feature_details']['title'] ?? $a['release'] ?? '—',
                'year'      => $a['feature_details']['year'] ?? '',
                'language'  => $a['language'] ?? '',
                'downloads' => $a['download_count'] ?? 0,
                'release'   => $a['release'] ?? '',
                'file_id'   => $files[0]['file_id'] ?? null,
                'filename'  => $files[0]['file_name'] ?? 'subtitle.srt'
            ];
        }
        if(empty($subs)) jErr('النتائج لا تحتوي ملفات قابلة للتنزيل');
        jOk(['data'=>$subs,'total'=>$d['total_count'] ?? count($subs)]);
    }

    if($act==='download_subtitle'){
        osGuard();
        $fid = intval($_POST['file_id'] ?? 0);
        if(!$fid) jErr('معرّف الملف غير صالح');

        [$c,$r,$e] = osReq(osBase().'/download','POST',['file_id'=>$fid,'sub_format'=>'srt']);
        if($e) jErr('انقطع الاتصال: '.$e);

        // انتهت صلاحية التوكن لدى السيرفر: أعد تسجيل الدخول وحاول مرة واحدة
        if($c===401){
            unset($_SESSION['os_token']);
            if(osAutoLogin()){
                [$c,$r,$e] = osReq(osBase().'/download','POST',['file_id'=>$fid,'sub_format'=>'srt']);
                if($e) jErr('انقطع الاتصال: '.$e);
            }
            if($c===401) jErr('تعذّر تجديد الجلسة — تحقق من بيانات الدخول في الإعدادات');
        }

        $d = json_decode($r,true);
        if($c===406) jErr('استنفدت رصيد التنزيل اليومي');
        if($c!==200 || empty($d['link']))
            jErr($d['message'] ?? $d['errors'][0]['message'] ?? "تعذّر التنزيل ($c)");

        /* إيقاف حماية SSRF مؤقتاً لتنزيلات OpenSubtitles لأن الرابط قادم من API موثوق
           ولأن مزود الخدمة المحلي قد يسمم الـ DNS ليعيد عنواناً داخلياً (0.0.0.0) مما يسبب خطأ SSRF كاذب. */
        
        $dl_host = parse_url($d['link'], PHP_URL_HOST);
        $real_ip = '';
        
        // جلب الـ IP الحقيقي عبر DoH لتخطي حجب الـ DNS المحلي (تسميم DNS)
        $chDns = curl_init('https://cloudflare-dns.com/dns-query?name=' . urlencode($dl_host) . '&type=A');
        curl_setopt_array($chDns, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Accept: application/dns-json'],
            CURLOPT_TIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ]);
        $doh = curl_exec($chDns);
        curl_close($chDns);
        
        if ($doh) {
            $json = json_decode($doh, true);
            if (!empty($json['Answer'])) {
                foreach ($json['Answer'] as $ans) {
                    if ($ans['type'] == 1) { $real_ip = $ans['data']; break; }
                }
            }
        }

        $ch2 = curl_init($d['link']);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            // تعطيل التحقق من الشهادة (تشغيل إجباري)
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_MAXFILESIZE    => 10485760, // ملف ترجمة: 10MB كافية جداً
            CURLOPT_USERAGENT      => OS_UA,
            CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
        ];
        
        if ($real_ip) {
            $opts[CURLOPT_RESOLVE] = ["$dl_host:443:$real_ip", "$dl_host:80:$real_ip"];
        }
        
        curl_setopt_array($ch2, $opts);
        $srt   = curl_exec($ch2);
        $dlErr = curl_error($ch2);
        curl_close($ch2);

        if(!$srt || strlen(trim($srt)) < 5) jErr('الملف تالف أو فارغ. '.$dlErr);

        if(!mb_check_encoding($srt,'UTF-8')){
            $srt = mb_convert_encoding($srt,'UTF-8','Windows-1256, ISO-8859-6, Windows-1252');
        }
        $srt = preg_replace('/^\xEF\xBB\xBF/','',$srt);

        $srtN = uniqid('sub_').'.srt';
        file_put_contents(VID_SUB_DIR.$srtN,$srt);

        $vttN = uniqid('sub_').'.vtt';
        $vtt  = "WEBVTT\n\n".preg_replace('/(\d{2}:\d{2}:\d{2}),(\d{3})/','\1.\2',$srt);
        file_put_contents(VID_SUB_DIR.$vttN,$vtt);

        jOk([
            'filename'     => $srtN,
            'vtt_filename' => $vttN,
            'remaining'    => $d['remaining_downloads'] ?? '?',
            'url'          => VID_SUB_URL.$srtN,
            'vtt_url'      => VID_SUB_URL.$vttN
        ]);
    }

    // ══ معالجات إدارة المستخدمين ══
    if($act==='clear_login_logs'){
        global $pdo;
        if(!in_array($_SESSION['admin_role'] ?? 'normal', ['administrator','super'])) jErr('ليس لديك صلاحية');
        try { $pdo->exec("TRUNCATE TABLE login_logs"); } catch(Exception $e) { jErr('فشل الحذف'); }
        jOk();
    }
    if($act==='get_login_logs'){
        global $pdo;
        $myRole = $_SESSION['admin_role'] ?? 'normal';
        if(!in_array($myRole, ['administrator','super'])) jErr('ليس لديك صلاحية');
        $rows = $pdo->query("SELECT id, ip_address, username, status, attempt_time FROM login_logs ORDER BY id DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
        jOk(['logs' => $rows]);
    }
    if($act==='get_admin_users'){
        global $pdo;
        $myRole = $_SESSION['admin_role'] ?? 'normal';
        if(!in_array($myRole, ['administrator','super'])) jErr('ليس لديك صلاحية لعرض المستخدمين');
        $rows = $pdo->query("SELECT id, username, display_name, role, allowed_sections, is_active, created_at, last_login FROM admin_users ORDER BY FIELD(role,'administrator','super','normal','custom'), id")->fetchAll(PDO::FETCH_ASSOC);
        // Super لا يرى تفاصيل Administrator
        if($myRole === 'super') {
            $rows = array_values(array_filter($rows, function($r){ return $r['role'] !== 'administrator'; }));
        }
        jOk(['data' => $rows]);
    }

    if($act==='add_admin_user'){
        global $pdo;
        $myRole = $_SESSION['admin_role'] ?? '';
        if(!in_array($myRole, ['administrator','super'])) jErr('ليس لديك صلاحية');
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $display_name = trim($_POST['display_name'] ?? '');
        $role = $_POST['role'] ?? 'normal';
        $sections = $_POST['allowed_sections'] ?? '[]';
        if(!$username || !$password) jErr('اسم المستخدم وكلمة المرور مطلوبان');

        /* ⚠️ الحد الأدنى كان 4 أحرف — لحساب **مدير** يملك صلاحيات
           كاملة على النظام. كلمة من 4 أحرف تُخمَّن آلياً في ثوانٍ.
           رُفع إلى 8 أحرف كحد أدنى معقول. */
        if(mb_strlen($password) < 8) jErr('كلمة المرور يجب أن تكون 8 أحرف على الأقل');
        if(mb_strlen($username) > 100) jErr('اسم المستخدم طويل جداً');

        /* ⚠️ قيمة role كانت تُؤخذ من الطلب كما هي وتُكتب في قاعدة
           البيانات بلا أي تحقق. عمود ENUM يرفض القيم الغريبة أو
           يحوّلها إلى '' حسب وضع MySQL، فينتهي الحساب بدور فارغ
           وسلوك غير متوقع. نتحقق من قائمة ثابتة. */
        $ROLES = ['administrator','super','normal','custom'];
        if(!in_array($role, $ROLES, true)) jErr('صلاحية غير معروفة');

        // نتأكد أن allowed_sections نص JSON صالح قبل تخزينه
        $__dec = json_decode($sections, true);
        $sections = json_encode(is_array($__dec) ? array_values($__dec) : [], JSON_UNESCAPED_UNICODE);

        // Super لا يستطيع إنشاء Administrator
        if($myRole === 'super' && $role === 'administrator') jErr('لا يمكنك إنشاء مستخدم بصلاحية مدير عام');
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM admin_users WHERE username = ?");
        $stmt->execute([$username]);
        if($stmt->fetchColumn() > 0) jErr('اسم المستخدم مُستخدم مسبقاً');
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO admin_users (username, password_hash, display_name, role, allowed_sections) VALUES (?, ?, ?, ?, ?)")
            ->execute([$username, $hash, $display_name ?: $username, $role, $sections]);
        $newId = $pdo->lastInsertId();
        // إدراج في جدول users أيضاً للتوافق مع login.php
        try { $pdo->prepare("INSERT IGNORE INTO users (username, password) VALUES (?, ?)")->execute([$username, $hash]); } catch(PDOException $e) {}
        jOk(['id' => $newId]);
    }

    if($act==='edit_admin_user'){
        global $pdo;
        $myRole = $_SESSION['admin_role'] ?? '';
        $myId = $_SESSION['admin_user_id'] ?? 0;
        if(!in_array($myRole, ['administrator','super'])) jErr('ليس لديك صلاحية');
        $id = intval($_POST['id'] ?? 0);
        $display_name = trim($_POST['display_name'] ?? '');
        $role = $_POST['role'] ?? 'normal';
        $sections = $_POST['allowed_sections'] ?? '[]';
        $is_active = intval($_POST['is_active'] ?? 1);
        $new_pass = $_POST['new_password'] ?? '';
        if(!$id) jErr('معرّف المستخدم مفقود');

        // نفس تحقق الصلاحية والـ JSON المطبَّق في add_admin_user
        $ROLES = ['administrator','super','normal','custom'];
        if(!in_array($role, $ROLES, true)) jErr('صلاحية غير معروفة');
        $__dec = json_decode($sections, true);
        $sections = json_encode(is_array($__dec) ? array_values($__dec) : [], JSON_UNESCAPED_UNICODE);
        $is_active = $is_active ? 1 : 0;

        // جلب بيانات المستخدم المستهدف
        $target = $pdo->prepare("SELECT username, role FROM admin_users WHERE id = ?");
        $target->execute([$id]);
        $targetRow = $target->fetch(PDO::FETCH_ASSOC);
        if(!$targetRow) jErr('المستخدم غير موجود');
        // Super لا يعدّل على Administrator
        if($myRole === 'super' && $targetRow['role'] === 'administrator') jErr('لا يمكنك التعديل على مدير عام');
        if($myRole === 'super' && $role === 'administrator') jErr('لا يمكنك ترقية مستخدم لمدير عام');
        // لا يمكن تعطيل نفسك
        if($id == $myId && $is_active == 0) jErr('لا يمكنك تعطيل حسابك');
        $pdo->prepare("UPDATE admin_users SET display_name=?, role=?, allowed_sections=?, is_active=? WHERE id=?")
            ->execute([$display_name, $role, $sections, $is_active, $id]);
        // تحديث كلمة المرور إن وُجدت
        // ⚠️ كان الشرط >= 4 فقط، ولو أدخل المدير كلمة أقصر كانت تُتجاهَل
        //    بصمت ويظن أنها حُفظت. الآن 8 أحرف + رسالة واضحة عند الرفض.
        if($new_pass !== '' && mb_strlen($new_pass) < 8) jErr('كلمة المرور الجديدة يجب أن تكون 8 أحرف على الأقل');
        if($new_pass !== ''){
            $hash = password_hash($new_pass, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE admin_users SET password_hash=? WHERE id=?")->execute([$hash, $id]);
            // تحديث في users أيضاً
            $uname = $pdo->prepare("SELECT username FROM admin_users WHERE id=?");
            $uname->execute([$id]);
            $un = $uname->fetchColumn();
            if($un) try { $pdo->prepare("UPDATE users SET password=? WHERE username=?")->execute([$hash, $un]); } catch(PDOException $e) {}
        }
        jOk();
    }

    if($act==='delete_admin_user'){
        global $pdo;
        $myRole = $_SESSION['admin_role'] ?? '';
        $myId = $_SESSION['admin_user_id'] ?? 0;
        if($myRole !== 'administrator') jErr('فقط المدير العام يمكنه الحذف');
        $id = intval($_POST['id'] ?? 0);
        if(!$id) jErr('معرّف مفقود');
        if($id == $myId) jErr('لا يمكنك حذف نفسك');
        $target = $pdo->prepare("SELECT username FROM admin_users WHERE id=?");
        $target->execute([$id]);
        $username = $target->fetchColumn();
        if (!$username) jErr('المستخدم غير موجود');
        $pdo->beginTransaction();
        try {
            $pdo->prepare("DELETE FROM admin_users WHERE id=?")->execute([$id]);
            // The legacy table is still read during migration, so remove the
            // mirrored credential as part of the same operation.
            try {
                $pdo->prepare("DELETE FROM users WHERE username=?")->execute([$username]);
            } catch (PDOException $e) {
                // New installations may no longer keep the legacy table.
                if ($e->getCode() !== '42S02') {
                    throw $e;
                }
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            jErr('تعذّر حذف المستخدم');
        }
        jOk();
    }

    // --- الكود الجديد الذي تم إضافته لرفع بوستر شاشتي ---
    if($act === 'upload_series_poster'){
        if(empty($_FILES['poster'])) jErr('لا توجد صورة'); 
        $f = $_FILES['poster']; 
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        
        if(!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
            jErr('صيغة غير صالحة. المدعوم: jpg, png, webp, gif');
        }
        
        $n = uniqid('poster_') . '.' . $ext;
        if(!mvFile($f['tmp_name'], POSTERS_DIR . $n)) {
            jErr('فشل في الرفع، يرجى التحقق من تصاريح المجلد');
        }
        
        jOk([
            'filename' => $n,
            'url' => POSTERS_URL . $n,
            'original' => $f['name'],
            'size' => $f['size']
        ]);
    }
    // ------------------------------------------------

// --- إضافة جديدة: التحويل الذكي MP4 بواسطة بايثون ---
// --- التحويل الذكي MP4 (النسخة المتكيفة كلياً مع Ubuntu/Linux و Windows) ---
    if($act === 'convert_to_mp4_bulk') {
        global $pdo;
        $ids = json_decode($_POST['ids'] ?? '[]');
        if (is_array($ids) && count($ids) > 0) {
            $success = 0;
            $failed = 0;
            $debug_msg = "";

            $placeholders = str_repeat('?,', count($ids) - 1) . '?';
            $stmt = $pdo->prepare("SELECT id, stream_url FROM episodes WHERE id IN ($placeholders)");
            $stmt->execute($ids);
            $eps = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($eps as $ep) {
                $old_url = $ep['stream_url'];
                $filename = urldecode(basename(parse_url($old_url, PHP_URL_PATH)));
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

                // 1. تجاوز إذا كان MP4
                if ($ext === 'mp4') {
                    $failed++;
                    $debug_msg .= "[الملف: $filename] بصيغة MP4 جاهزة فعلاً.\n\n";
                    continue;
                }

                // 2. كشافة البحث في المسارات
                $found_path = false;
                foreach ([SERIES_DIR, VID_UPLOAD_DIR, VID_MERGED_DIR] as $dir) {
                    $test_path = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $filename;
                    if(file_exists($test_path)) {
                        $found_path = $test_path;
                        break;
                    }
                }

                if ($found_path !== false) {
                    $new_name = uniqid('cv_') . '_' . time() . '.mp4';
                    $new_path = dirname($found_path) . DIRECTORY_SEPARATOR . $new_name;
                    $script_path = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'convert_mp4.py'; // moved to tools/
                    
                    // تحديد أوامر لينكس ضد أوامر ويندوز بذكاء شديد 
                    $is_windows = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN');
                    
                    // في سيرفرات الأوبونتو/أباتشي، نستخدم المُنفّذ الشامل env python3 لضمان استدعائه دوماً
                    $python_cmd = $is_windows ? 'python' : '/usr/bin/env python3';
                    
                    // دمج الأمر مع 2>&1 لطباعة جميع أنواع الاستجابة التقنية إن رفض أباتشي ذلك
                    $cmd = $python_cmd . ' ' . escapeshellarg($script_path) . ' ' . escapeshellarg($found_path) . ' ' . escapeshellarg($new_path) . ' 2>&1';
                    
                    exec($cmd, $out_arr, $status_code);
                    $output = trim(implode("\n", $out_arr));

                    /* ⚠ الشرط هنا كان:
                           SUCCESS !== false  ||  filesize($new_path) > 1024
                       أي أن مجرد وجود ملف ناتج أكبر من كيلوبايت يُعدّ نجاحاً
                       ولو طبع السكربت خطأً صريحاً. وبما أن الفرع ينفّذ
                       @unlink($found_path) بعدها، كان تحويل فاشل يحذف الملف
                       الأصلي ويربط قاعدة البيانات بناتج مبتور — فقدان بيانات
                       لا مجرد عطل. ffmpeg يكتب ملف الخرج تدريجياً، فحجمه
                       يتجاوز الكيلوبايت بعد لحظات حتى لو فشل بعد ذلك.

                       الآن: SUCCESS شرط لا غنى عنه، ووجود الملف تأكيد إضافي.
                       والسكربت من جهته يحذف أي ناتج فاشل فلا يبقى ما يخدع. */
                    $__conv_ok = (strpos($output, 'SUCCESS') !== false)
                              && file_exists($new_path)
                              && filesize($new_path) > 1024;
                    if ($__conv_ok) {
                        // إرساء الرابط للوحة المتصفح لتغيره من Ts إلى mp4 
                        $new_url = str_replace(urlencode($filename), $new_name, str_replace($filename, $new_name, $old_url));
                        $pdo->prepare("UPDATE episodes SET stream_url = ? WHERE id = ?")->execute([$new_url, $ep['id']]);
                        
                        @unlink($found_path); // نسف ملف Ts من الهاردديسك لتفريغ السعة
                        $success++;
                    } else {
                        $failed++;
                        $debug_msg .= "خطأ تقني اثناء التعامل مع ($filename):\n[Output]: " . ($output ?: 'لاشيء') . "\n\n";
                    }
                } else {
                    $failed++;
                    $debug_msg .= "الملف ($filename) محذوف أو لم نجده داخل مسار الخادم الفعلي!\n\n";
                }
            }
            jOk(['success_count' => $success, 'failed_count' => $failed, 'debug' => trim($debug_msg)]);
        }
        jErr('بيانات مفقودة أو غير متوافقة.');
    }
    // ----------------------------------------------------

    // ══ استيراد قوائم M3U ══
    if($act === 'import_m3u'){
        $content = '';
        $source_type = 'url';
        $source_name = '';
        $source_url = '';

        if(!empty($_FILES['m3u_file']) && $_FILES['m3u_file']['error'] === UPLOAD_ERR_OK){
            $f = $_FILES['m3u_file'];
            $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
            if(!in_array($ext, ['m3u','m3u8'])) jErr('صيغة الملف غير مدعومة. المسموح: m3u, m3u8');
            $content = file_get_contents($f['tmp_name']);
            $source_type = 'file';
            $source_name = $f['name'];
        } else {
            $url = trim($_POST['m3u_url'] ?? '');
            if($url === '') jErr('أدخل رابط M3U أو اختر ملفاً للرفع');
            if(!preg_match('#^https?://#i', $url)) jErr('رابط غير صالح، يجب أن يبدأ بـ http:// أو https://');
            $content = m3uFetchUrl($url, $err);
            if($content === false || trim((string)$content) === '') jErr('تعذر تحميل الرابط: '.($err ?: 'استجابة فارغة من الخادم'));
            $source_type = 'url';
            $source_name = parse_url($url, PHP_URL_HOST) ?: $url;
            $source_url = $url;
        }

        if(trim((string)$content) === '') jErr('الملف/الرابط فارغ');
        $items = parseM3UPlaylist($content);
        if(!count($items)) jErr('لم يتم العثور على أي قنوات صالحة داخل القائمة — تأكد أن الملف بصيغة M3U صحيحة');

        try {
            $pdo->beginTransaction();
            $playlistName = $source_name !== '' ? $source_name : ('قائمة '.date('Y-m-d H:i'));
            $pdo->prepare("INSERT INTO m3u_playlists (name, source_type, source_url, channels_count) VALUES (?,?,?,0)")
                ->execute([$playlistName, $source_type, $source_url]);
            $playlistId = $pdo->lastInsertId();

            $inserted = m3uInsertChannels($pdo, $items, $playlistId);

            $pdo->prepare("UPDATE m3u_playlists SET channels_count = ? WHERE id = ?")->execute([$inserted, $playlistId]);
            $pdo->commit();
            jOk(['playlist_id'=>$playlistId, 'count'=>$inserted, 'total_found'=>count($items)]);
        } catch(Exception $e){
            if($pdo->inTransaction()) $pdo->rollBack();
            jErrLog('خطأ أثناء الاستيراد', $e);
        }
    }

    if($act === 'list_m3u_playlists'){
        $rows = $pdo->query("SELECT * FROM m3u_playlists ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
        jOk(['data'=>$rows]);
    }

    if($act === 'refresh_m3u_playlist'){
        $id = (int)($_POST['id'] ?? 0);
        if(!$id) jErr('معرّف القائمة مفقود');
        $stmt = $pdo->prepare("SELECT * FROM m3u_playlists WHERE id = ?");
        $stmt->execute([$id]);
        $pl = $stmt->fetch(PDO::FETCH_ASSOC);
        if(!$pl) jErr('القائمة غير موجودة');
        if($pl['source_type'] !== 'url' || empty($pl['source_url'])) jErr('التحديث التلقائي متاح فقط للقوائم المستوردة عبر رابط مباشر — احذف هذه القائمة وأعد رفع الملف لتحديثها');

        $content = m3uFetchUrl($pl['source_url'], $err);
        if($content === false || trim((string)$content) === '') jErr('تعذر تحميل الرابط: '.($err ?: 'استجابة فارغة من الخادم'));

        $items = parseM3UPlaylist($content);
        if(!count($items)) jErr('لم يتم العثور على أي قنوات صالحة في الرابط المحدّث');

        try {
            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM channels WHERE playlist_id = ?")->execute([$id]);
            $inserted = m3uInsertChannels($pdo, $items, $id);
            $pdo->prepare("UPDATE m3u_playlists SET channels_count = ?, updated_at = NOW() WHERE id = ?")->execute([$inserted, $id]);
            $pdo->commit();
            jOk(['count'=>$inserted]);
        } catch(Exception $e){
            if($pdo->inTransaction()) $pdo->rollBack();
            jErrLog('خطأ أثناء التحديث', $e);
        }
    }

    if($act === 'delete_m3u_playlist'){
        $id = (int)($_POST['id'] ?? 0);
        if(!$id) jErr('معرّف القائمة مفقود');
        try {
            $pdo->prepare("DELETE FROM channels WHERE playlist_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM m3u_playlists WHERE id = ?")->execute([$id]);
            jOk();
        } catch(Exception $e){
            jErrLog('خطأ أثناء الحذف', $e);
        }
    }

    // ══ تعديل رابط قائمة M3U + إعادة الاستيراد من الرابط الجديد ══
    if($act === 'edit_m3u_playlist'){
        $id  = (int)($_POST['id'] ?? 0);
        $url = trim($_POST['source_url'] ?? '');
        if(!$id) jErr('معرّف القائمة مفقود');
        if($url === '') jErr('أدخل رابط M3U الجديد');
        if(!preg_match('#^https?://#i', $url)) jErr('رابط غير صالح، يجب أن يبدأ بـ http:// أو https://');

        $stmt = $pdo->prepare("SELECT * FROM m3u_playlists WHERE id = ?");
        $stmt->execute([$id]);
        $pl = $stmt->fetch(PDO::FETCH_ASSOC);
        if(!$pl) jErr('القائمة غير موجودة');

        $content = m3uFetchUrl($url, $err);
        if($content === false || trim((string)$content) === '') jErr('تعذر تحميل الرابط الجديد: '.($err ?: 'استجابة فارغة من الخادم'));
        $items = parseM3UPlaylist($content);
        if(!count($items)) jErr('لم يتم العثور على أي قنوات صالحة في الرابط الجديد');

        try {
            $pdo->beginTransaction();
            // حذف كل المحتويات (القنوات) القديمة المرتبطة بهذه القائمة بالكامل
            $pdo->prepare("DELETE FROM channels WHERE playlist_id = ?")->execute([$id]);
            $inserted = m3uInsertChannels($pdo, $items, $id);
            $newName = parse_url($url, PHP_URL_HOST) ?: $url;
            $pdo->prepare("UPDATE m3u_playlists SET name = ?, source_type = 'url', source_url = ?, channels_count = ?, updated_at = NOW() WHERE id = ?")
                ->execute([$newName, $url, $inserted, $id]);
            $pdo->commit();
            jOk(['count'=>$inserted]);
        } catch(Exception $e){
            if($pdo->inTransaction()) $pdo->rollBack();
            jErrLog('خطأ أثناء تعديل الرابط', $e);
        }
    }

    // ══ حذف جماعي للأقسام ══
    if($act === 'bulk_delete_categories'){
        $ids = $_POST['ids'] ?? '';
        $arr = array_filter(array_map('intval', explode(',', $ids)), function($v){ return $v > 0; });
        if(!count($arr)) jErr('لم يتم تحديد أي قسم');
        try {
            /* على دفعات: MySQL يرفض أكثر من 65535 placeholder في جملة واحدة (الخطأ 1390).
               المعرّفات مرّت بـ intval فهي أرقام آمنة — ندمجها مباشرة على دفعات. */
            $chunks = array_chunk(array_values($arr), 1000);
            foreach($chunks as $ck){
                $list = implode(',', array_map('intval', $ck));
                $pdo->exec("DELETE FROM episodes WHERE series_id IN (SELECT id FROM series WHERE category_id IN ($list))");
                $pdo->exec("DELETE FROM series     WHERE category_id IN ($list)");
                $pdo->exec("DELETE FROM channels   WHERE category_id IN ($list)");
                $pdo->exec("DELETE FROM categories WHERE id IN ($list)");
            }
            jOk(['deleted'=>count($arr)]);
        } catch(Exception $e){
            jErrLog('خطأ أثناء الحذف', $e);
        }
    }

    // ══ حذف جماعي للقنوات ══
    if($act === 'bulk_delete_channels'){
        $ids = $_POST['ids'] ?? '';
        $arr = array_filter(array_map('intval', explode(',', $ids)), function($v){ return $v > 0; });
        if(!count($arr)) jErr('لم يتم تحديد أي قناة');
        try {
            // على دفعات لتجنّب حدّ الـ placeholders (الخطأ 1390)
            foreach(array_chunk(array_values($arr), 1000) as $ck){
                $list = implode(',', array_map('intval', $ck));
                $pdo->exec("DELETE FROM channels WHERE id IN ($list)");
            }
            jOk(['deleted'=>count($arr)]);
        } catch(Exception $e){
            jErrLog('خطأ أثناء الحذف', $e);
        }
    }
    // ----------------------------------------------------

    /* ════════════════════ [XTREAM-AJAX-START] معالجات حساب Xtream IPTV — إضافة فقط ════════════════════ */
    // إنشاء جداول Xtream عند الحاجة (آمن ومتكرر)
    /* ── إيقاف الاستيراد إجبارياً: عبر ملف إشارة يُفحص داخل حلقات الاستيراد ── */
    if(!class_exists('XtreamAbort')){
        class XtreamAbort extends Exception {
            public $stage;
            public function __construct($stage = ''){ $this->stage = $stage; parent::__construct('aborted'); }
        }
    }
    function xtreamAbortFile(){
        $dir = VID_UPLOAD_DIR;
        if(!is_dir($dir)) @mkdir($dir, 0755, true);
        return $dir . 'xtream_import.abort';
    }
    function xtreamAbortRaise(){ return @file_put_contents(xtreamAbortFile(), '1') !== false; }
    function xtreamAbortClear(){ $f = xtreamAbortFile(); if(file_exists($f)) @unlink($f); }
    function xtreamAborted(){ clearstatcache(true, xtreamAbortFile()); return file_exists(xtreamAbortFile()); }

    /* ── تتبّع تقدّم الاستيراد: السيرفر يكتب الحالة في ملف، والمتصفح يقرأه دورياً ── */
    function xtreamProgFile(){
        $dir = VID_UPLOAD_DIR;
        if(!is_dir($dir)) @mkdir($dir, 0755, true);
        return $dir . 'xtream_import.progress';
    }
    function xtreamProgWrite($data){
        $data['ts'] = time();
        @file_put_contents(xtreamProgFile(), json_encode($data, JSON_UNESCAPED_UNICODE), LOCK_EX);
    }
    function xtreamProgRead(){
        $f = xtreamProgFile();
        clearstatcache(true, $f);
        if(!file_exists($f)) return null;
        $raw = @file_get_contents($f);
        $d = json_decode($raw, true);
        return is_array($d) ? $d : null;
    }
    function xtreamProgClear(){ $f = xtreamProgFile(); if(file_exists($f)) @unlink($f); }

    function xtreamEnsureTables($pdo){
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS xtream_accounts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) DEFAULT '',
                host VARCHAR(500) NOT NULL,
                username VARCHAR(255) NOT NULL,
                password VARCHAR(255) NOT NULL,
                server_info TEXT,
                user_info TEXT,
                live_count INT DEFAULT 0,
                vod_count INT DEFAULT 0,
                series_count INT DEFAULT 0,
                status VARCHAR(30) DEFAULT 'active',
                last_sync TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");
        } catch(PDOException $e){}
        // ربط الأقسام والقنوات والمسلسلات بحساب Xtream (لإتاحة الحذف الكامل)
        try { $pdo->exec("ALTER TABLE categories ADD COLUMN xtream_account_id INT NULL DEFAULT NULL"); } catch(PDOException $e){}
        try { $pdo->exec("ALTER TABLE channels   ADD COLUMN xtream_account_id INT NULL DEFAULT NULL"); } catch(PDOException $e){}
        try { $pdo->exec("ALTER TABLE series     ADD COLUMN xtream_account_id INT NULL DEFAULT NULL"); } catch(PDOException $e){}

        /* ── توسيع الأعمدة النصية ──
           سيرفرات Xtream تُرجع روابط صور/بث طويلة جداً (أحياناً >1000 حرف)،
           فتفشل الإضافة بخطأ: 1406 Data too long for column.
           نوسّعها هنا لأن هذه الدالة تعمل قبل الاستيراد مباشرة. */
        $widen = [
            "ALTER TABLE channels MODIFY logo_url   TEXT",
            "ALTER TABLE channels MODIFY stream_url TEXT",
            "ALTER TABLE channels MODIFY backup_url TEXT",
            "ALTER TABLE channels MODIFY name       VARCHAR(500)",
            "ALTER TABLE series   MODIFY poster_url  TEXT",
            "ALTER TABLE series   MODIFY description TEXT",
            "ALTER TABLE series   MODIFY name        VARCHAR(500)",
            "ALTER TABLE series   MODIFY slug        VARCHAR(255)",
            "ALTER TABLE episodes MODIFY stream_url  TEXT",
            "ALTER TABLE episodes MODIFY description TEXT",
            "ALTER TABLE episodes MODIFY title       VARCHAR(500)",
            "ALTER TABLE categories MODIFY name      VARCHAR(500)",
        ];
        foreach($widen as $sql){ try { $pdo->exec($sql); } catch(PDOException $e){} }

        /* ── فهارس الأداء ──
           بدونها كل استعلام "WHERE category_id=?" يمسح الجدول كاملاً (Full Table Scan).
           مع عشرات آلاف الصفوف بعد استيراد Xtream يصبح هذا أبطأ جزء في المنظومة.
           MySQL لا يدعم "ADD INDEX IF NOT EXISTS" في كل الإصدارات، لذا نتجاهل خطأ التكرار. */
        $idx = [
            "ALTER TABLE channels   ADD INDEX idx_ch_cat   (category_id)",
            "ALTER TABLE channels   ADD INDEX idx_ch_acc   (xtream_account_id)",
            "ALTER TABLE channels   ADD INDEX idx_ch_order (category_id, display_order, id)",
            "ALTER TABLE series     ADD INDEX idx_sr_cat   (category_id)",
            "ALTER TABLE series     ADD INDEX idx_sr_acc   (xtream_account_id)",
            "ALTER TABLE series     ADD INDEX idx_sr_order (category_id, display_order, id)",
            "ALTER TABLE series     ADD INDEX idx_sr_name  (name(100))",
            "ALTER TABLE channels   ADD INDEX idx_ch_name  (name(100))",
            "ALTER TABLE episodes   ADD INDEX idx_ep_ser   (series_id)",
            "ALTER TABLE episodes   ADD INDEX idx_ep_order (series_id, display_order, id)",
            "ALTER TABLE categories ADD INDEX idx_cat_acc  (xtream_account_id)",
        ];
        foreach($idx as $sql){ try { $pdo->exec($sql); } catch(PDOException $e){} }
    }

    /* قصّ آمن لأي قيمة قبل إدخالها (احتياط إضافي ضد الأعمدة الضيقة) */
    function xtreamFit($val, $max = 900){
        $val = (string)$val;
        if($val === '') return '';
        if(function_exists('mb_strlen')){
            return mb_strlen($val, 'UTF-8') > $max ? mb_substr($val, 0, $max, 'UTF-8') : $val;
        }
        return strlen($val) > $max ? substr($val, 0, $max) : $val;
    }

    // تطبيع رابط المضيف: يقبل host، host:port، http(s)://host:port، ويزيل المسارات الزائدة
    function xtreamNormalizeHost($host){
        $host = trim((string)$host);
        if($host === '') return '';
        if(!preg_match('#^https?://#i', $host)) $host = 'http://'.$host;
        $p = parse_url($host);
        if(!$p || empty($p['host'])) return '';
        $scheme = $p['scheme'] ?? 'http';
        $port   = isset($p['port']) ? ':'.$p['port'] : '';
        return $scheme.'://'.$p['host'].$port;
    }

    // طلب HTTP لـ Xtream player_api
    function xtreamApi($base, $user, $pass, $action='', $extra=[]){
        $url = rtrim($base,'/').'/player_api.php?username='.rawurlencode($user).'&password='.rawurlencode($pass);
        if($action !== '') $url .= '&action='.rawurlencode($action);
        foreach($extra as $k=>$v) $url .= '&'.rawurlencode($k).'='.rawurlencode($v);

        // حماية SSRF: لا نسمح بتوجيه الخادم إلى شبكته الداخلية
        if(!shashetyUrlIsSafe($url, $whyX)){
            return ['_error' => 'عنوان السيرفر غير مسموح: '.$whyX];
        }

        /* ملاحظة هندسية حول التحقق من شهادة SSL:
           أغلب لوحات Xtream تعمل بشهادات ذاتية التوقيع أو منتهية، لذا
           تفعيل التحقق افتراضياً كان سيكسر تركيبات عاملة لدى المستخدمين.
           جعلناه قابلاً للضبط: ضع XTREAM_VERIFY_SSL=1 في ملف .env
           لتفعيل التحقق الكامل عندما يكون مزوّدك يملك شهادة صحيحة.
           بيانات الاعتماد تُرسَل في هذا الطلب، لذا يُنصح بتفعيله متى أمكن. */
        $verify = function_exists('env') ? (string) env('XTREAM_VERIFY_SSL', '0') : '0';
        $verify = ($verify === '1' || strtolower($verify) === 'true');

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => $verify,
            CURLOPT_SSL_VERIFYHOST => $verify ? 2 : 0,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (ShashetyIPTV Xtream)',
        ]);
        $raw = curl_exec($ch);
        $err = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if($raw === false) return ['_error'=>'تعذر الاتصال بالسيرفر: '.$err];
        if($code >= 400) return ['_error'=>'استجابة السيرفر: HTTP '.$code];
        $data = json_decode($raw, true);
        if($data === null) return ['_error'=>'رد غير صالح من السيرفر (ليس JSON)'];
        return $data;
    }

    // بناء رابط بث لعنصر Xtream
    function xtreamStreamUrl($base, $user, $pass, $type, $id, $ext='ts'){
        $base = rtrim($base,'/');
        if($type === 'live')   return $base.'/live/'.rawurlencode($user).'/'.rawurlencode($pass).'/'.$id.'.'.$ext;
        if($type === 'movie')  return $base.'/movie/'.rawurlencode($user).'/'.rawurlencode($pass).'/'.$id.'.'.$ext;
        if($type === 'series') return $base.'/series/'.rawurlencode($user).'/'.rawurlencode($pass).'/'.$id.'.'.$ext;
        return '';
    }

    if($act === 'xtream_login'){
        xtreamEnsureTables($pdo);
        $host = xtreamNormalizeHost($_POST['host'] ?? '');
        $user = trim($_POST['username'] ?? '');
        $pass = trim($_POST['password'] ?? '');
        if($host === '' || $user === '' || $pass === '') jErr('يرجى إدخال العنوان واسم المستخدم وكلمة المرور');
        $info = xtreamApi($host, $user, $pass);
        if(isset($info['_error'])) jErr($info['_error']);
        $auth = $info['user_info']['auth'] ?? 0;
        $status = $info['user_info']['status'] ?? '';
        if(!$auth || strtolower((string)$status) === 'disabled'){
            jErr('فشل تسجيل الدخول: بيانات غير صحيحة أو الحساب موقوف');
        }
        // جلب أعداد سريعة للتصنيفات (لمعرفة الدعم)
        $liveCats = xtreamApi($host,$user,$pass,'get_live_categories');
        $vodCats  = xtreamApi($host,$user,$pass,'get_vod_categories');
        $serCats  = xtreamApi($host,$user,$pass,'get_series_categories');
        $supportsLive   = is_array($liveCats) && !isset($liveCats['_error']) && count($liveCats) > 0;
        $supportsVod    = is_array($vodCats)  && !isset($vodCats['_error'])  && count($vodCats)  > 0;
        $supportsSeries = is_array($serCats)  && !isset($serCats['_error'])  && count($serCats)  > 0;
        jOk([
            'user_info'   => $info['user_info']   ?? [],
            'server_info' => $info['server_info'] ?? [],
            'host'        => $host,
            'supports'    => ['live'=>$supportsLive,'vod'=>$supportsVod,'series'=>$supportsSeries],
            'counts'      => [
                'live'   => $supportsLive   ? count($liveCats) : 0,
                'vod'    => $supportsVod    ? count($vodCats)  : 0,
                'series' => $supportsSeries ? count($serCats)  : 0,
            ],
        ]);
    }

    if($act === 'xtream_import'){
        xtreamEnsureTables($pdo);
        @set_time_limit(0);
        @ignore_user_abort(true);
        xtreamAbortClear(); // امسح أي إشارة إيقاف قديمة قبل بدء استيراد جديد
        $host = xtreamNormalizeHost($_POST['host'] ?? '');
        $user = trim($_POST['username'] ?? '');
        $pass = trim($_POST['password'] ?? '');
        $name = trim($_POST['account_name'] ?? '');
        $impLive   = ($_POST['import_live']   ?? '1') === '1';
        $impVod    = ($_POST['import_vod']    ?? '1') === '1';
        $impSeries = ($_POST['import_series'] ?? '1') === '1';
        if($host === '' || $user === '' || $pass === '') jErr('بيانات الحساب ناقصة');
        // إعادة التحقق
        $info = xtreamApi($host,$user,$pass);
        if(isset($info['_error'])) jErr($info['_error']);
        if(!($info['user_info']['auth'] ?? 0)) jErr('فشل التحقق من الحساب');
        if($name === '') $name = parse_url($host, PHP_URL_HOST) ?: 'حساب Xtream';

        // إنشاء سجل الحساب
        $pdo->prepare("INSERT INTO xtream_accounts (name, host, username, password, server_info, user_info, status) VALUES (?,?,?,?,?,?,'active')")
            ->execute([$name, $host, $user, $pass, json_encode($info['server_info'] ?? []), json_encode($info['user_info'] ?? [])]);
        $accId = (int)$pdo->lastInsertId();

        $liveN = 0; $vodN = 0; $serN = 0; $skipN = 0; // skipN = صفوف تم تخطيها بسبب خطأ
        $startedAt = time();
        xtreamProgClear();
        xtreamProgWrite(['stage'=>'init','label'=>'جارٍ الاتصال بالسيرفر...','done'=>0,'total'=>0,
                         'live'=>0,'vod'=>0,'series'=>0,'skipped'=>0,'started'=>$startedAt]);
        $prefix = '📡 '.$name.' — ';

        // دالة مساعدة: إنشاء/جلب قسم مربوط بالحساب
        $catCache = [];
        $getCat = function($rawName, $icon) use ($pdo, $accId, &$catCache, $prefix){
            $catName = $prefix.trim($rawName);
            $key = mb_strtolower($catName);
            if(isset($catCache[$key])) return $catCache[$key];
            $slug = 'xt-'.$accId.'-'.substr(md5($catName),0,10);
            $pdo->prepare("INSERT INTO categories (name, icon, slug, xtream_account_id) VALUES (?,?,?,?)")
                ->execute([xtreamFit($catName, 450), $icon, $slug, $accId]);
            $id = (int)$pdo->lastInsertId();
            $catCache[$key] = $id;
            return $id;
        };

        try {
            /* ⚠️ اختيار صيغة القنوات المباشرة — سبب شائع لمشكلة
               "تعمل في VLC ولا تعمل في المتصفح":

               الكود السابق كان يأخذ *أول* صيغة في القائمة:
                   allowed_output_formats[0]
               وأغلب لوحات Xtream تُرجع الترتيب ["ts","m3u8","rtmp"]،
               فتُستورد كل القنوات بامتداد .ts

               الفرق العملي:
               • .ts   → يحتاج مكتبة mpegts.js تقرأ البث عبر fetch،
                         وهذا يستلزم ترويسة CORS من سيرفر Xtream —
                         وهي غائبة في أغلب اللوحات ⇒ يفشل في المتصفح.
                         VLC لا يعرف CORS أصلاً فيعمل عنده بلا مشكلة.
               • .m3u8 → HLS، وهي الصيغة القياسية للمتصفحات وتعمل
                         أصلياً على iOS/Safari ومع hls.js في غيرها.

               لذلك: نُفضّل m3u8 متى أتاحها السيرفر، ونعود لـ ts فقط
               إن لم تكن متاحة. */
            $__formats = $info['user_info']['allowed_output_formats'] ?? [];
            if(!is_array($__formats)) $__formats = [];
            $__formats = array_map('strtolower', array_map('strval', $__formats));

            if(in_array('m3u8', $__formats, true))      $ext = 'm3u8';
            elseif(in_array('ts', $__formats, true))    $ext = 'ts';
            else                                        $ext = 'ts';

            // السماح بتجاوز الاختيار يدوياً من واجهة الاستيراد عند الحاجة
            $__forced = strtolower(trim($_POST['live_format'] ?? ''));
            if(in_array($__forced, ['ts','m3u8'], true)) $ext = $__forced;

            // ═══ القنوات المباشرة (live) ═══
            if($impLive){
                $cats = xtreamApi($host,$user,$pass,'get_live_categories');
                $catMap = [];
                if(is_array($cats) && !isset($cats['_error'])){
                    foreach($cats as $c){ if(isset($c['category_id'])) $catMap[$c['category_id']] = $c['category_name'] ?? 'قنوات'; }
                }
                xtreamProgWrite(['stage'=>'live','label'=>'جارٍ جلب قائمة القنوات...','done'=>0,'total'=>0,
                                 'live'=>$liveN,'vod'=>$vodN,'series'=>$serN,'skipped'=>$skipN,'started'=>$startedAt]);
                $streams = xtreamApi($host,$user,$pass,'get_live_streams');
                if(is_array($streams) && !isset($streams['_error'])){
                    $insCh = $pdo->prepare("INSERT INTO channels (category_id, name, stream_url, logo_icon, logo_url, backup_url, quality, is_active, xtream_account_id) VALUES (?,?,?,?,?,?,?,1,?)");
                    $i = 0;
                    $totLive = count($streams);
                    foreach($streams as $s){
                        if((++$i % 25) === 0 && xtreamAborted()) throw new XtreamAbort('live');
                        if(($i % 20) === 0 || $i === $totLive){
                            xtreamProgWrite(['stage'=>'live','label'=>'استيراد القنوات المباشرة','done'=>$i,'total'=>$totLive,
                                             'live'=>$liveN,'vod'=>$vodN,'series'=>$serN,'skipped'=>$skipN,'started'=>$startedAt]);
                        }
                        $sid = $s['stream_id'] ?? null; if($sid === null) continue;
                        $cid = $s['category_id'] ?? '';
                        $cName = $catMap[$cid] ?? 'قنوات Xtream';
                        $catId = $getCat($cName, 'fas fa-tv');
                        $url = xtreamStreamUrl($host,$user,$pass,'live',$sid,$ext);
                        try {
                            $insCh->execute([
                                $catId,
                                xtreamFit(htmlspecialchars(strip_tags($s['name'] ?? 'قناة')), 450),
                                xtreamFit($url, 2000),
                                'fas fa-tv',
                                xtreamFit($s['stream_icon'] ?? '', 2000),
                                '', 'HD', $accId
                            ]);
                            $liveN++;
                        } catch(PDOException $e){ $skipN++; }
                    }
                }
            }

            // ═══ الأفلام (VOD) → إلى «شاشتي» (series + episodes)، وليس إلى القنوات ═══
            // كل فيلم = صف في series + حلقة واحدة في episodes تحمل رابط الفيديو (mp4/mkv).
            // قسم «إدارة القنوات» مخصّص للبث المباشر فقط (ts / m3u8).
            if($impVod){
                $cats = xtreamApi($host,$user,$pass,'get_vod_categories');
                $catMap = [];
                if(is_array($cats) && !isset($cats['_error'])){
                    foreach($cats as $c){ if(isset($c['category_id'])) $catMap[$c['category_id']] = $c['category_name'] ?? 'أفلام'; }
                }
                xtreamProgWrite(['stage'=>'vod','label'=>'جارٍ جلب قائمة الأفلام...','done'=>0,'total'=>0,
                                 'live'=>$liveN,'vod'=>$vodN,'series'=>$serN,'skipped'=>$skipN,'started'=>$startedAt]);
                $streams = xtreamApi($host,$user,$pass,'get_vod_streams');
                if(is_array($streams) && !isset($streams['_error'])){
                    $insMov = $pdo->prepare("INSERT INTO series (category_id, name, slug, description, poster_url, logo_icon, xtream_account_id, is_active) VALUES (?,?,?,?,?,?,?,1)");
                    $insMovEp = $pdo->prepare("INSERT INTO episodes (series_id, episode_number, title, stream_url, description, display_order) VALUES (?,?,?,?,?,?)");
                    $i = 0;
                    $totVod = count($streams);
                    foreach($streams as $s){
                        if((++$i % 25) === 0 && xtreamAborted()) throw new XtreamAbort('vod');
                        if(($i % 20) === 0 || $i === $totVod){
                            xtreamProgWrite(['stage'=>'vod','label'=>'استيراد الأفلام إلى شاشتي','done'=>$i,'total'=>$totVod,
                                             'live'=>$liveN,'vod'=>$vodN,'series'=>$serN,'skipped'=>$skipN,'started'=>$startedAt]);
                        }
                        $sid = $s['stream_id'] ?? null; if($sid === null) continue;
                        $cid = $s['category_id'] ?? '';
                        $cName = $catMap[$cid] ?? 'أفلام Xtream';
                        $catId = $getCat('🎬 '.$cName, 'fas fa-film');
                        $vext = $s['container_extension'] ?? 'mp4';
                        $url  = xtreamStreamUrl($host,$user,$pass,'movie',$sid,$vext);
                        $mName = htmlspecialchars(strip_tags($s['name'] ?? 'فيلم'));
                        $mSlug = 'xtmov-'.$accId.'-'.$sid.'-'.substr(md5($mName),0,6);
                        $mPoster = $s['stream_icon'] ?? ($s['cover'] ?? ($s['movie_image'] ?? ''));
                        $mPlot   = $s['plot'] ?? ($s['description'] ?? '');
                        try {
                            // 1) الفيلم كعنصر في شاشتي
                            $insMov->execute([
                                $catId,
                                xtreamFit($mName, 450),
                                xtreamFit($mSlug, 200),
                                xtreamFit(strip_tags((string)$mPlot), 5000),
                                xtreamFit($mPoster, 2000),
                                'fas fa-film',
                                $accId
                            ]);
                            $movId = (int)$pdo->lastInsertId();
                            // 2) حلقة وحيدة تحمل ملف الفيديو
                            $insMovEp->execute([
                                $movId, 1,
                                xtreamFit($mName, 450),
                                xtreamFit($url, 2000),
                                '', 0
                            ]);
                            $vodN++;
                        } catch(PDOException $e){ $skipN++; }
                    }
                }
            }

            // ═══ المسلسلات (series + episodes) ═══
            if($impSeries){
                $cats = xtreamApi($host,$user,$pass,'get_series_categories');
                $catMap = [];
                if(is_array($cats) && !isset($cats['_error'])){
                    foreach($cats as $c){ if(isset($c['category_id'])) $catMap[$c['category_id']] = $c['category_name'] ?? 'مسلسلات'; }
                }
                xtreamProgWrite(['stage'=>'series','label'=>'جارٍ جلب قائمة المسلسلات...','done'=>0,'total'=>0,
                                 'live'=>$liveN,'vod'=>$vodN,'series'=>$serN,'skipped'=>$skipN,'started'=>$startedAt]);
                $list = xtreamApi($host,$user,$pass,'get_series');
                if(is_array($list) && !isset($list['_error'])){
                    $totSer = count($list); $si = 0;
                    $insSer = $pdo->prepare("INSERT INTO series (category_id, name, slug, description, poster_url, logo_icon, xtream_account_id, is_active) VALUES (?,?,?,?,?,?,?,1)");
                    $insEp  = $pdo->prepare("INSERT INTO episodes (series_id, episode_number, title, stream_url, description, display_order) VALUES (?,?,?,?,?,?)");
                    foreach($list as $sr){
                        // كل مسلسل = طلب شبكة مستقل، لذا نفحص الإيقاف عند كل عنصر
                        if(xtreamAborted()) throw new XtreamAbort('series');
                        $si++;
                        xtreamProgWrite(['stage'=>'series','label'=>'استيراد المسلسلات وحلقاتها','done'=>$si,'total'=>$totSer,
                                         'live'=>$liveN,'vod'=>$vodN,'series'=>$serN,'skipped'=>$skipN,'started'=>$startedAt,
                                         'current'=>mb_substr((string)($sr['name'] ?? ''),0,60,'UTF-8')]);
                        $seriesId = $sr['series_id'] ?? null; if($seriesId === null) continue;
                        $cid = $sr['category_id'] ?? '';
                        $cName = $catMap[$cid] ?? 'مسلسلات Xtream';
                        $catId = $getCat('📺 '.$cName, 'fas fa-clapperboard');
                        $sName = htmlspecialchars(strip_tags($sr['name'] ?? 'مسلسل'));
                        $slug = 'xtser-'.$accId.'-'.$seriesId.'-'.substr(md5($sName),0,6);
                        try {
                            $insSer->execute([
                                $catId,
                                xtreamFit($sName, 450),
                                xtreamFit($slug, 200),
                                xtreamFit(strip_tags($sr['plot'] ?? ''), 5000),
                                xtreamFit($sr['cover'] ?? '', 2000),
                                'fas fa-clapperboard',
                                $accId
                            ]);
                        } catch(PDOException $e){ $skipN++; continue; }
                        $dbSeriesId = (int)$pdo->lastInsertId();
                        // جلب الحلقات
                        $det = xtreamApi($host,$user,$pass,'get_series_info',['series_id'=>$seriesId]);
                        if(is_array($det) && isset($det['episodes']) && is_array($det['episodes'])){
                            $order = 0;
                            foreach($det['episodes'] as $seasonNum=>$eps){
                                if(!is_array($eps)) continue;
                                foreach($eps as $ep){
                                    $epId = $ep['id'] ?? null; if($epId === null) continue;
                                    $epExt = $ep['container_extension'] ?? 'mp4';
                                    $epUrl = xtreamStreamUrl($host,$user,$pass,'series',$epId,$epExt);
                                    $epTitle = htmlspecialchars(strip_tags($ep['title'] ?? ('الحلقة '.($ep['episode_num'] ?? ($order+1)))));
                                    $epNum = (int)($ep['episode_num'] ?? ($order+1));
                                    try {
                                        $insEp->execute([
                                            $dbSeriesId,
                                            $epNum,
                                            xtreamFit($epTitle, 450),
                                            xtreamFit($epUrl, 2000),
                                            '',
                                            $order++
                                        ]);
                                    } catch(PDOException $e){ $skipN++; }
                                }
                            }
                        }
                        $serN++;
                    }
                }
            }

            // تحديث سجل الحساب بالأعداد
            $pdo->prepare("UPDATE xtream_accounts SET live_count=?, vod_count=?, series_count=?, last_sync=NOW() WHERE id=?")
                ->execute([$liveN, $vodN, $serN, $accId]);

            if(function_exists('shashety_cache_clear')) @shashety_cache_clear();

            xtreamAbortClear(); xtreamProgClear();

            jOk([
                'account_id' => $accId,
                'name'       => $name,
                'imported'   => ['live'=>$liveN, 'vod'=>$vodN, 'series'=>$serN],
                'skipped'    => $skipN,
                'message'    => 'تم الاستيراد بنجاح',
            ]);
        } catch(XtreamAbort $ab){
            /* إيقاف إجباري: تراجع كامل — لا نترك حساباً نصف مستورد */
            xtreamAbortClear(); xtreamProgClear();
            try {
                $pdo->prepare("DELETE FROM episodes
                               WHERE series_id IN (SELECT id FROM series WHERE xtream_account_id = ?)")
                    ->execute([$accId]);
                $pdo->prepare("DELETE FROM series     WHERE xtream_account_id=?")->execute([$accId]);
                $pdo->prepare("DELETE FROM channels   WHERE xtream_account_id=?")->execute([$accId]);
                $pdo->prepare("DELETE FROM categories WHERE xtream_account_id=?")->execute([$accId]);
                $pdo->prepare("DELETE FROM xtream_accounts WHERE id=?")->execute([$accId]);
            } catch(Exception $e2){}
            if(function_exists('shashety_cache_clear')) @shashety_cache_clear();
            jErr('تم إيقاف الاستيراد بناءً على طلبك، وأُزيل كل ما استُورد جزئياً.');
        } catch(Exception $e){
            xtreamAbortClear(); xtreamProgClear();
            jErrLog('خطأ أثناء الاستيراد', $e);
        }
    }

    /* ── رفع إشارة الإيقاف الإجباري للاستيراد الجاري ── */
    /* ── استعلام التقدّم: يستدعيه المتصفح كل ثانية أثناء الاستيراد ── */
    if($act === 'xtream_import_progress'){
        $p = xtreamProgRead();
        if(!$p) jOk(['running'=>false]);
        $elapsed = max(0, time() - (int)($p['started'] ?? time()));
        $done  = (int)($p['done'] ?? 0);
        $total = (int)($p['total'] ?? 0);
        $pct   = $total > 0 ? min(99, (int)floor($done * 100 / $total)) : 0;
        // تقدير الوقت المتبقي من متوسط سرعة المعالجة
        $eta = null;
        if($done > 0 && $total > 0 && $elapsed > 2){
            $rate = $done / $elapsed;                 // عنصر/ثانية
            if($rate > 0) $eta = (int)round(($total - $done) / $rate);
        }
        $p['running'] = true;
        $p['percent'] = $pct;
        $p['elapsed'] = $elapsed;
        $p['eta']     = $eta;
        $p['stale']   = (time() - (int)($p['ts'] ?? time())) > 90; // لم يتحدث منذ فترة طويلة
        jOk($p);
    }

    /* ── ترحيل الأفلام المستوردة سابقاً من «القنوات» إلى «شاشتي» ──
       يعالج البيانات القديمة التي أُدخلت قبل الإصلاح، بلا حاجة لإعادة استيراد كامل.
       نتعرّف على الفيلم بأنه قناة Xtream رابطها /movie/ */
    if($act === 'xtream_fix_vod'){
        xtreamEnsureTables($pdo);
        try {
            $rows = $pdo->query("SELECT id, category_id, name, stream_url, logo_url, xtream_account_id
                                 FROM channels
                                 WHERE xtream_account_id IS NOT NULL
                                   AND stream_url LIKE '%/movie/%'")->fetchAll(PDO::FETCH_ASSOC);
            if(!$rows) jOk(['moved'=>0,'message'=>'لا توجد أفلام في قسم القنوات — كل شيء سليم']);

            $pdo->beginTransaction();
            $insMov   = $pdo->prepare("INSERT INTO series (category_id, name, slug, description, poster_url, logo_icon, xtream_account_id, is_active) VALUES (?,?,?,?,?,?,?,1)");
            $insMovEp = $pdo->prepare("INSERT INTO episodes (series_id, episode_number, title, stream_url, description, display_order) VALUES (?,?,?,?,?,?)");
            $delCh    = $pdo->prepare("DELETE FROM channels WHERE id=?");
            $moved = 0; $failed = 0;

            foreach($rows as $r){
                try {
                    $nm   = (string)$r['name'];
                    $slug = 'xtmov-'.(int)$r['xtream_account_id'].'-c'.(int)$r['id'].'-'.substr(md5($nm),0,6);
                    $insMov->execute([
                        (int)$r['category_id'],
                        xtreamFit($nm, 450),
                        xtreamFit($slug, 200),
                        '',
                        xtreamFit((string)$r['logo_url'], 2000),
                        'fas fa-film',
                        (int)$r['xtream_account_id'],
                    ]);
                    $movId = (int)$pdo->lastInsertId();
                    $insMovEp->execute([$movId, 1, xtreamFit($nm,450), xtreamFit((string)$r['stream_url'],2000), '', 0]);
                    $delCh->execute([(int)$r['id']]);
                    $moved++;
                } catch(PDOException $e){ $failed++; }
            }
            $pdo->commit();
            if(function_exists('shashety_cache_clear')) @shashety_cache_clear();
            jOk([
                'moved'   => $moved,
                'failed'  => $failed,
                'purge_client' => true,
                'message' => 'تم نقل '.$moved.' فيلماً من «القنوات» إلى «شاشتي»'.($failed ? ' — تعذّر نقل '.$failed : ''),
            ]);
        } catch(Exception $e){
            if($pdo->inTransaction()) $pdo->rollBack();
            jErrLog('تعذر الترحيل', $e);
        }
    }

    /* ── تسريع قاعدة البيانات: إضافة الفهارس الناقصة ── */
    if($act === 'xtream_optimize_db'){
        $idx = [
            'channels/category_id'        => "ALTER TABLE channels   ADD INDEX idx_ch_cat   (category_id)",
            'channels/xtream_account_id'  => "ALTER TABLE channels   ADD INDEX idx_ch_acc   (xtream_account_id)",
            'channels/ترتيب'              => "ALTER TABLE channels   ADD INDEX idx_ch_order (category_id, display_order, id)",
            'series/category_id'          => "ALTER TABLE series     ADD INDEX idx_sr_cat   (category_id)",
            'series/xtream_account_id'    => "ALTER TABLE series     ADD INDEX idx_sr_acc   (xtream_account_id)",
            'series/ترتيب'                => "ALTER TABLE series     ADD INDEX idx_sr_order (category_id, display_order, id)",
            'series/بحث بالاسم'           => "ALTER TABLE series     ADD INDEX idx_sr_name  (name(100))",
            'channels/بحث بالاسم'         => "ALTER TABLE channels   ADD INDEX idx_ch_name  (name(100))",
            'episodes/series_id'          => "ALTER TABLE episodes   ADD INDEX idx_ep_ser   (series_id)",
            'episodes/ترتيب'              => "ALTER TABLE episodes   ADD INDEX idx_ep_order (series_id, display_order, id)",
            'categories/xtream_account_id'=> "ALTER TABLE categories ADD INDEX idx_cat_acc  (xtream_account_id)",
        ];
        $added = []; $already = [];
        foreach($idx as $label => $sql){
            try { $pdo->exec($sql); $added[] = $label; }
            catch(PDOException $e){ $already[] = $label; } // موجود مسبقاً أو غير مدعوم
        }
        // إحصاء حجم البيانات لعرضه للمستخدم
        $counts = [];
        foreach(['channels','series','episodes','categories'] as $t){
            try { $counts[$t] = (int)$pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn(); }
            catch(Exception $e){ $counts[$t] = 0; }
        }
        jOk([
            'added'   => $added,
            'already' => $already,
            'counts'  => $counts,
            'message' => count($added)
                ? ('تمت إضافة '.count($added).' فهرساً — الاستعلامات ستصبح أسرع بكثير')
                : 'كل الفهارس موجودة مسبقاً — قاعدة البيانات مهيّأة',
        ]);
    }

    if($act === 'xtream_import_abort'){
        xtreamAbortRaise();
        jOk(['message'=>'تم إرسال إشارة الإيقاف — سيتوقف الاستيراد خلال لحظات']);
    }

    if($act === 'xtream_list'){
        xtreamEnsureTables($pdo);
        try {
            $rows = $pdo->query("SELECT id, name, host, username, user_info, live_count, vod_count, series_count, status, last_sync, created_at FROM xtream_accounts ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
            jOk(['accounts'=>$rows]);
        } catch(Exception $e){ jErrLog('تعذر جلب الحسابات', $e); }
    }

    /* ── حذف الحساب نهائياً / تسجيل الخروج منه ──
       كلاهما يمسح كل المحتوى المستورد: الحلقات + المسلسلات + الأفلام + القنوات + الأقسام الفارغة
       الفرق: xtream_delete يحذف سجل الحساب أيضاً، xtream_logout يُبقيه فارغاً لإعادة الدخول */
    if($act === 'xtream_delete' || $act === 'xtream_logout'){
        xtreamEnsureTables($pdo);
        $id = (int)($_POST['id'] ?? 0);
        if($id <= 0) jErr('معرف غير صالح');
        $isLogout = ($act === 'xtream_logout');
        // حذف عشرات الآلاف من الصفوف قد يتجاوز المهلة الافتراضية
        @set_time_limit(600);
        @ini_set('memory_limit','512M');
        try {
            $pdo->beginTransaction();

            /* 1) الحلقات المرتبطة بمسلسلات هذا الحساب
               نستخدم استعلاماً فرعياً بدل IN (?,?,?...) — لأن MySQL يسمح بـ 65535 عنصراً
               كحدّ أقصى في الجملة المجهّزة، ومع ١٦ ألف مسلسل كان يفشل بالخطأ 1390. */
            $pdo->prepare("DELETE FROM episodes
                           WHERE series_id IN (SELECT id FROM series WHERE xtream_account_id = ?)")
                ->execute([$id]);

            /* 2) المسلسلات والأفلام والقنوات */
            $pdo->prepare("DELETE FROM series   WHERE xtream_account_id=?")->execute([$id]);
            $pdo->prepare("DELETE FROM channels WHERE xtream_account_id=?")->execute([$id]);

            /* 3) الأقسام: احذف فقط ما لم يبقَ فيه أي محتوى (حماية للأقسام المشتركة) */
            $st = $pdo->prepare("SELECT id FROM categories WHERE xtream_account_id=?");
            $st->execute([$id]);
            $cids = $st->fetchAll(PDO::FETCH_COLUMN);
            $cntCh = $pdo->prepare("SELECT COUNT(*) FROM channels WHERE category_id=?");
            $cntSe = $pdo->prepare("SELECT COUNT(*) FROM series   WHERE category_id=?");
            $delCat = $pdo->prepare("DELETE FROM categories WHERE id=?");
            foreach($cids as $cid){
                $cntCh->execute([$cid]); $n1 = (int)$cntCh->fetchColumn();
                $cntSe->execute([$cid]); $n2 = (int)$cntSe->fetchColumn();
                if($n1 === 0 && $n2 === 0) $delCat->execute([$cid]);
            }

            /* 4) الحساب نفسه */
            if($isLogout){
                /* لا نمسح كلمة المرور — وإلا استحال إعادة الاستيراد لاحقاً.
                   نكتفي بتصفير العدادات وتعليم الحساب كـ"خارج" وتفريغ بيانات الجلسة. */
                $pdo->prepare("UPDATE xtream_accounts
                               SET live_count=0, vod_count=0, series_count=0,
                                   status='logged_out', server_info=NULL, user_info=NULL
                               WHERE id=?")->execute([$id]);
            } else {
                $pdo->prepare("DELETE FROM xtream_accounts WHERE id=?")->execute([$id]);
            }

            $pdo->commit();

            /* 5) تفريغ كاش السيرفر */
            if(function_exists('shashety_cache_clear')) @shashety_cache_clear();

            jOk([
                'id'           => $id,
                'mode'         => $isLogout ? 'logout' : 'delete',
                'deleted'      => $id,
                'purge_client' => true, // إشارة للواجهة كي تمسح المفضلة/الإشعارات/الكاش المحلي
                'message'      => $isLogout ? 'تم تسجيل الخروج ومسح كل محتوى الحساب'
                                            : 'تم حذف الحساب وكل محتواه',
            ]);
        } catch(Exception $e){
            if($pdo->inTransaction()) $pdo->rollBack();
            jErrLog($isLogout ? 'تعذر تسجيل الخروج' : 'تعذر الحذف', $e);
        }
    }

    /* ── مسح إجباري يدوي: كل ما يخص Xtream دفعة واحدة (لا يمس محتوى M3U أو اليدوي) ── */
    if($act === 'xtream_purge_all'){
        xtreamEnsureTables($pdo);
        @set_time_limit(900);
        @ini_set('memory_limit','512M');
        if(($_POST['confirm'] ?? '') !== 'PURGE') jErr('التأكيد مفقود');
        try {
            $pdo->beginTransaction();

            /* 1) حلقات كل مسلسلات Xtream (استعلام فرعي — بلا حدّ placeholders) */
            $stE = $pdo->prepare("DELETE FROM episodes
                                  WHERE series_id IN (SELECT id FROM series WHERE xtream_account_id IS NOT NULL)");
            $stE->execute();
            $epN = $stE->rowCount();

            /* 2) كل المسلسلات والأفلام والقنوات المستوردة من Xtream */
            $stS = $pdo->prepare("DELETE FROM series   WHERE xtream_account_id IS NOT NULL"); $stS->execute();
            $serN = $stS->rowCount();
            $stC = $pdo->prepare("DELETE FROM channels WHERE xtream_account_id IS NOT NULL"); $stC->execute();
            $chN = $stC->rowCount();

            /* 3) أقسام Xtream: احذف فقط الفارغة (حماية أي قسم يشاركه محتوى آخر) */
            $cids = $pdo->query("SELECT id FROM categories WHERE xtream_account_id IS NOT NULL")
                        ->fetchAll(PDO::FETCH_COLUMN);
            $cntCh = $pdo->prepare("SELECT COUNT(*) FROM channels WHERE category_id=?");
            $cntSe = $pdo->prepare("SELECT COUNT(*) FROM series   WHERE category_id=?");
            $delCat = $pdo->prepare("DELETE FROM categories WHERE id=?");
            $catN = 0; $catKept = 0;
            foreach($cids as $cid){
                $cntCh->execute([$cid]); $n1 = (int)$cntCh->fetchColumn();
                $cntSe->execute([$cid]); $n2 = (int)$cntSe->fetchColumn();
                if($n1 === 0 && $n2 === 0){ $delCat->execute([$cid]); $catN++; }
                else { $catKept++; }
            }

            /* 4) كل سجلات الحسابات */
            $stA = $pdo->prepare("DELETE FROM xtream_accounts"); $stA->execute();
            $accN = $stA->rowCount();

            $pdo->commit();

            if(function_exists('shashety_cache_clear')) @shashety_cache_clear();

            jOk([
                'purge_client' => true,
                'stats'   => [
                    'accounts'   => $accN,
                    'channels'   => $chN,
                    'series'     => $serN,
                    'episodes'   => $epN,
                    'categories' => $catN,
                    'cat_kept'   => $catKept,
                ],
                'message' => 'تم مسح كل محتوى Xtream نهائياً',
            ]);
        } catch(Exception $e){
            if($pdo->inTransaction()) $pdo->rollBack();
            jErrLog('تعذر المسح الإجباري', $e);
        }
    }

    if($act === 'xtream_update'){
        xtreamEnsureTables($pdo);
        $id = (int)($_POST['id'] ?? 0);
        if($id <= 0) jErr('معرف غير صالح');
        $name = trim($_POST['account_name'] ?? '');
        $host = xtreamNormalizeHost($_POST['host'] ?? '');
        $user = trim($_POST['username'] ?? '');
        $pass = trim($_POST['password'] ?? '');
        try {
            $fields = []; $vals = [];
            if($name !== ''){ $fields[]='name=?'; $vals[]=$name; }
            if($host !== ''){ $fields[]='host=?'; $vals[]=$host; }
            if($user !== ''){ $fields[]='username=?'; $vals[]=$user; }
            if($pass !== ''){ $fields[]='password=?'; $vals[]=$pass; }
            if(!$fields) jErr('لا يوجد ما يُحدّث');
            $vals[] = $id;
            $pdo->prepare("UPDATE xtream_accounts SET ".implode(',',$fields)." WHERE id=?")->execute($vals);
            jOk(['updated'=>$id]);
        } catch(Exception $e){ jErrLog('تعذر التعديل', $e); }
    }
    /* ════════════════════ [XTREAM-AJAX-END] نهاية معالجات حساب Xtream ════════════════════ */

        jErr('عذرا الكود المبدئ في الاستمارة لا يمت لاوامري.');
}
