<?php
/**
 * جسر البثّ اللحظي بين PHP وخادم WebSocket (Node.js).
 *
 * ══════════════════════════════════════════════════════════════
 *  ما عولج في هذه النسخة
 * ══════════════════════════════════════════════════════════════
 *  ① نقطة /broadcast في خادم Node أصبحت تتطلب مفتاحاً سرّياً،
 *     فأُضيفت ترويسة X-WS-Secret هنا. اضبط WS_SECRET في ملف .env
 *     بنفس القيمة المستخدمة في بيئة تشغيل Node.
 *
 *  ② كانت الدالة تتجاهل نتيجة الاتصال تماماً: لا فحص لرمز الحالة
 *     ولا تسجيل عند الفشل. فإن توقّف خادم WebSocket تتوقف التحديثات
 *     اللحظية بصمت تام بلا أي أثر يدلّ على السبب.
 *
 *  ③ العنوان كان مكتوباً داخل الكود (localhost:3000) فلا يمكن نقل
 *     الخدمة إلى منفذ أو خادم آخر دون تعديل الكود.
 *
 *  ④ لم يكن هناك تعطيل اختياري: عند عدم تشغيل خادم WebSocket كانت
 *     كل عملية بثّ تنتظر مهلة الاتصال وتُبطئ الطلب بلا فائدة.
 */

if (!function_exists('broadcast_ws_event')) {

    /**
     * إرسال حدث لحظي إلى العملاء المتصلين.
     *
     * @param string      $event اسم الحدث (أحرف وأرقام و _ : - فقط).
     * @param array       $data  الحمولة.
     * @param string|null $room  الغرفة، أو null للبثّ للجميع.
     * @return bool نجح الإرسال أم لا.
     */
    function broadcast_ws_event($event, $data = [], $room = null): bool
    {
        // ④ تعطيل سريع دون تعديل الكود
        $enabled = function_exists('env') ? (string) env('WS_ENABLED', '1') : '1';
        if ($enabled === '0' || strtolower($enabled) === 'false') {
            return false;
        }

        $secret = function_exists('env') ? (string) env('WS_SECRET', '') : '';
        if ($secret === '') {
            // بلا مفتاح سيرفض خادم Node الطلب — لا نضيّع مهلة الاتصال
            static $warned = false;
            if (!$warned) {
                $warned = true;
                error_log('websocket: WS_SECRET غير مضبوط في .env — التحديثات اللحظية معطّلة.');
            }
            return false;
        }

        // ③ العنوان قابل للضبط
        $host = function_exists('env') ? (string) env('WS_HOST', '127.0.0.1') : '127.0.0.1';
        $port = function_exists('env') ? (int)    env('WS_PORT', 3000)        : 3000;
        $url  = 'http://' . $host . ':' . $port . '/broadcast';

        // تحقق من اسم الحدث قبل الإرسال (نفس قاعدة خادم Node)
        if (!is_string($event) || !preg_match('/^[a-z0-9_:-]{1,64}$/i', $event)) {
            error_log('websocket: اسم حدث غير صالح: ' . var_export($event, true));
            return false;
        }

        $payload = json_encode([
            'event' => $event,
            'data'  => $data,
            'room'  => $room,
        ], JSON_UNESCAPED_UNICODE);

        if ($payload === false) {
            error_log('websocket: تعذّر ترميز الحمولة إلى JSON.');
            return false;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($payload),
                'X-WS-Secret: ' . $secret,     // ① المصادقة
            ],
            // مهل قصيرة: التحديث اللحظي ميزة مساعدة ولا يجوز أن يُبطئ الصفحة
            CURLOPT_TIMEOUT        => 2,
            CURLOPT_CONNECTTIMEOUT => 1,
        ]);

        $result = curl_exec($ch);
        $code   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err    = curl_error($ch);
        curl_close($ch);

        // ② تسجيل واضح للفشل بدل الصمت التام
        if ($result === false) {
            error_log("websocket: تعذّر الاتصال بخادم البثّ ({$url}) — {$err}");
            return false;
        }
        if ($code === 403) {
            error_log('websocket: رُفض البثّ — WS_SECRET في .env لا يطابق المستخدم في خادم Node.');
            return false;
        }
        if ($code !== 200) {
            error_log("websocket: استجابة غير متوقعة HTTP {$code} — {$result}");
            return false;
        }

        return true;
    }
}
