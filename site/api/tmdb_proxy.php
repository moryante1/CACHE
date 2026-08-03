<?php
/* ══ وكيل TMDB الآمن مع كاش وحماية معدّل ══ */

final class TmdbCache {
    private $dir;
    public function __construct(string $dir) {
        $this->dir = $dir;
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
    }
    public function get(string $key, int $ttl): ?string {
        $f = $this->path($key);
        if (is_file($f) && (time() - filemtime($f) < $ttl)) {
            $raw = @file_get_contents($f);
            if ($raw !== false && $raw !== '') return $raw;
        }
        return null;
    }
    public function stale(string $key): ?string {
        $f = $this->path($key);
        if (is_file($f)) { $raw = @file_get_contents($f); return $raw !== false ? $raw : null; }
        return null;
    }
    public function put(string $key, string $data): void {
        $f = $this->path($key);
        $tmp = $f . '.' . getmypid() . '.tmp';
        if (@file_put_contents($tmp, $data, LOCK_EX) !== false) @rename($tmp, $f);
    }
    /* تنظيف تلقائي: يحذف الملفات الأقدم من 30 يوم، باحتمال 1% لكل طلب */
    public function gc(int $maxAge = 2592000): void {
        if (mt_rand(1, 100) !== 1) return;
        foreach (glob($this->dir . '/*.json') ?: [] as $f) {
            if (is_file($f) && (time() - filemtime($f) > $maxAge)) @unlink($f);
        }
    }
    private function path(string $key): string {
        return $this->dir . '/tmdb_' . md5($key) . '.json';
    }
}

if (isset($_GET['tmdb_proxy'])) {
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');

    /* ⚠️ تعليق الملف يقول «مع كاش وحماية معدّل» لكن حماية المعدّل
       لم تكن موجودة إطلاقاً. وهذه نقطة **عامة** بلا مصادقة تستخدم
       مفتاح TMDB الخاص بك. أي شخص يستطيع إرسال آلاف الطلبات بكلمات
       بحث مختلفة (فيتخطّى الكاش في كل مرة) فيستنزف حصّتك اليومية
       ويُوقف TMDB مفتاحك، فتتوقف صور الأفلام عن الظهور لمستخدميك.
       60 طلباً في الدقيقة لكل عنوان IP أكثر من كافٍ للتصفّح الطبيعي. */
    if (function_exists('rateLimit') && !rateLimit('tmdb:proxy', 60, 60)) {
        http_response_code(429);
        header('Retry-After: 60');
        echo json_encode(['error' => 'rate_limit']);
        exit;
    }

    $__key = trim($settings['tmdb_api_key'] ?? '');
    if ($__key === '') { echo json_encode(['error' => 'disabled']); exit; }

    $__mode = $_GET['tmdb_proxy'];
    $__lang = (($_GET['lang'] ?? 'ar') === 'en') ? 'en-US' : 'ar';

    if ($__mode === 'search') {
        $__q = trim((string)($_GET['q'] ?? ''));
        if ($__q === '' || mb_strlen($__q) > 200) { echo json_encode(['results' => []]); exit; }
        $__url = 'https://api.themoviedb.org/3/search/multi?api_key=' . urlencode($__key)
               . '&query=' . urlencode($__q) . '&language=' . urlencode($__lang);
        $__ttl = 3600;
    } elseif ($__mode === 'detail') {
        $__type = ($_GET['type'] ?? '') === 'movie' ? 'movie' : 'tv';
        $__id   = (int)($_GET['id'] ?? 0);
        if ($__id <= 0) { echo json_encode(['error' => 'bad id']); exit; }
        $__url = 'https://api.themoviedb.org/3/' . $__type . '/' . $__id
               . '?api_key=' . urlencode($__key) . '&language=' . urlencode($__lang);
        $__ttl = 604800;
    } else {
        echo json_encode(['error' => 'bad mode']); exit;
    }

    $__cache = new TmdbCache(__DIR__ . '/../../cache/tmdb');
    $__cache->gc();

    /* 1) كاش صالح → أسرع مسار، بدون لمس TMDB */
    $__hit = $__cache->get($__url, $__ttl);
    if ($__hit !== null) {
        header('Cache-Control: public, max-age=' . $__ttl);
        echo $__hit; exit;
    }

    /* 2) نداء TMDB */
    $__ch = curl_init($__url);
    curl_setopt_array($__ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 12,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_USERAGENT      => 'ShashetyIPTV',
    ]);
    $__res  = curl_exec($__ch);
    $__code = (int)curl_getinfo($__ch, CURLINFO_HTTP_CODE);
    curl_close($__ch);

    /* 3) فشل الشبكة أو الحد → اخدم آخر نسخة ناجحة بدل الفراغ */
    if ($__res === false || $__code === 429 || $__code === 409 || $__code >= 500) {
        $__old = $__cache->stale($__url);
        if ($__old !== null) { header('Cache-Control: public, max-age=300'); echo $__old; exit; }
        echo json_encode(['error' => 'rate_limit']); exit;
    }
    if ($__code === 401) { echo json_encode(['error' => 'bad key']); exit; }
    if ($__code !== 200) { echo json_encode(['error' => 'upstream']); exit; }

    /* 4) نجاح → خزّن واخدم */
    $__cache->put($__url, $__res);
    header('Cache-Control: public, max-age=' . $__ttl);
    echo $__res;
    exit;
}
