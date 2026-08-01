<?php
/**
 * API للتطبيق - Shashety IPTV
 * نقطة النهاية الرئيسية للبيانات
 * -------------------------------------------------------------
 * جميع الـ Endpoints والأسماء والحقول المُرجَعة محفوظة 100%:
 *   categories, channels, channel, search, featured, stats,
 *   increment_view, all_content, series, episodes, content_version
 *
 * التحسينات:
 *   • إزالة CREATE TABLE من الـAPI (انتقلت إلى install.php/migration.php)
 *   • Rate Limiting لكل نقطة
 *   • كاش + ETag + Compression + Cache-Control
 *   • Prepared Statements في كل مكان + تحقق من المدخلات
 *   • Pagination بحدود آمنة
 *   • تقليل الاستعلامات المكررة (COUNT عبر window function مع بديل)
 *   • رؤوس أمان كاملة
 *
 * @package Shashety\Api
 */

declare(strict_types=1);

require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/functions/stream_token.php';

// ══════════════════════════════════════════════════════════════
// رؤوس CORS ورؤوس الأمان
// ══════════════════════════════════════════════════════════════
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token, If-None-Match');
header('Access-Control-Expose-Headers: ETag, X-Content-Version, X-Cache');
header('Access-Control-Max-Age: 86400');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
if (IS_HTTPS) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

// التعامل مع طلبات OPTIONS
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit();
}

// ══════════════════════════════════════════════════════════════
// ضغط الاستجابة (Compression) — يقلل زمن النقل بشكل كبير
// ══════════════════════════════════════════════════════════════
if (!ob_get_level()
    && extension_loaded('zlib')
    && !ini_get('zlib.output_compression')
    && strpos(strtolower((string) ($_SERVER['HTTP_ACCEPT_ENCODING'] ?? '')), 'gzip') !== false
) {
    @ob_start('ob_gzhandler');
} elseif (!ob_get_level()) {
    @ob_start();
}

// ══════════════════════════════════════════════════════════════
// حدّ المعدل العام — حماية الـAPI من الإساءة
// ══════════════════════════════════════════════════════════════
if (!rateLimit('api:global:v2', 300, 60)) {
    header('Retry-After: 60');
    jsonResponse([
        'success' => false,
        'error'   => 'تم تجاوز الحد المسموح من الطلبات. حاول بعد قليل.',
    ], 429);
}

// ══════════════════════════════════════════════════════════════
// الحصول على الإجراء المطلوب
// ══════════════════════════════════════════════════════════════
$action = sanitizeInput($_GET['action'] ?? '');

// تحديد الإجراءات المسموح بها
// ── أُضيفت: all_content, series, episodes ──
$allowedActions = [
    'categories',
    'channels',
    'channel',
    'search',
    'featured',
    'stats',
    'increment_view',
    'all_content',
    'notification_state',
    'series',
    'episodes',
    'content_version',
];

if (!in_array($action, $allowedActions, true)) {
    jsonResponse([
        'success' => false,
        'error'   => 'إجراء غير صالح',
    ], 400);
}

// ══════════════════════════════════════════════════════════════
// دوال البنية التحتية للاستجابة (كاش + ETag)
// ══════════════════════════════════════════════════════════════

/**
 * استبدال كل روابط البث في الاستجابة بروابط موقَّعة منتهية الصلاحية.
 *
 * يمرّ على البنية كاملةً مهما كان عمقها، فيغطّي:
 *   channels[], series[], episodes[], channel{}, results[], featured[] …
 *
 * @param mixed $node عقدة من بنية الاستجابة.
 * @return mixed العقدة بعد المعالجة.
 */
function apiProtectStreamUrls($node)
{
    static $enabled = null;
    static $base    = null;

    if ($enabled === null) {
        $enabled = function_exists('streamProtectionEnabled') && streamProtectionEnabled();
        // مسار القاعدة حتى يعمل الموقع داخل مجلد فرعي مثل /iptv
        $base = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? ''))), '/');
    }

    if (!$enabled || !is_array($node)) {
        return $node;
    }

    foreach ($node as $k => $v) {
        if (is_array($v)) {
            $node[$k] = apiProtectStreamUrls($v);
        } elseif (is_string($v) && $v !== ''
                  && ($k === 'stream_url' || $k === 'backup_url' || $k === 'url')) {
            $node[$k] = streamPublicUrl($v, (string) $base);
        }
    }

    return $node;
}

/**
 * إرسال استجابة JSON قابلة للتخزين المؤقت مع ETag.
 * إن تطابق ETag مع ما لدى المتصفح تُرجَع 304 بلا جسم (توفير كبير).
 *
 * @param array $data البيانات.
 * @param int   $ttl  مدة الكاش بالثواني (0 = بلا كاش عام).
 * @return never
 */
function apiCachedResponse(array $data, int $ttl = 60)
{
    /* 🔴 حماية روابط البث — تُطبَّق هنا عمداً في نقطة واحدة.
       سبب اختيار هذا الموضع: الاستجابات تخرج من تسع نقاط مختلفة في
       هذا الملف، وتعديل كل واحدة يدوياً يعني نسيان واحدة يوماً ما
       فتتسرّب بيانات الاشتراك من نقطة واحدة منسيّة. المعالجة في
       نقطة الخروج الموحّدة تضمن تغطية كل النقاط الحالية وأي نقطة
       تُضاف مستقبلاً. راجع functions/stream_token.php للتفاصيل. */
    $data = apiProtectStreamUrls($data);

    $body = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    $etag = '"' . md5((string) $body) . '"';

    if (!headers_sent()) {
        header('ETag: ' . $etag);
        if ($ttl > 0) {
            header('Cache-Control: public, max-age=' . $ttl . ', stale-while-revalidate=30');
        } else {
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        }
        header('Vary: Accept-Encoding');
    }

    $clientEtag = trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''));
    if ($clientEtag !== '' && $clientEtag === $etag) {
        http_response_code(304);
        // إفراغ أي مخرجات لضمان جسم فارغ فعلاً
        while (ob_get_level()) {
            ob_end_clean();
        }
        exit();
    }

    http_response_code(200);
    echo $body;
    exit();
}

/**
 * تنفيذ استعلام مع كاش على مستوى النتيجة (Query Cache).
 *
 * @param string   $key      مفتاح الكاش.
 * @param int      $ttl      مدة الصلاحية.
 * @param callable $producer دالة تُنتج البيانات عند غياب الكاش.
 * @return mixed
 */
function apiRemember(string $key, int $ttl, callable $producer)
{
    $cached = cacheGet($key);
    if ($cached !== null) {
        if (!headers_sent()) {
            header('X-Cache: HIT');
        }
        return $cached;
    }

    if (!headers_sent()) {
        header('X-Cache: MISS');
    }

    $value = $producer();
    cacheSet($key, $value, $ttl);
    return $value;
}

/**
 * بصمة المحتوى الحالية — تُستخدم كجزء من مفاتيح الكاش
 * حتى يبطل الكاش تلقائياً فور أي تعديل من لوحة الإدارة.
 *
 * @return string
 */
function apiContentStamp(): string
{
    $stamp = cacheGet('content_stamp');
    if (is_string($stamp) && $stamp !== '') {
        return $stamp;
    }

    $stamp = computeContentVersion();
    cacheSet('content_stamp', $stamp, 10); // نافذة قصيرة جداً
    return $stamp;
}

/**
 * حساب بصمة المحتوى فعلياً من قاعدة البيانات.
 *
 * COUNT(*) + MAX(id) معاً يكشفان:
 *   • الإضافة → يزيدان
 *   • الحذف   → COUNT ينقص
 *   • إضافة+حذف بنفس العدد → MAX(id) يكشفها
 *
 * @return string بصمة من 16 محرفاً.
 */
function computeContentVersion(): string
{
    $pdo   = db();
    $parts = [];

    // القنوات والمسلسلات: النشطة فقط + أعلى معرّف
    foreach (['channels' => 'is_active = 1', 'series' => 'is_active = 1'] as $tbl => $cond) {
        try {
            $row = $pdo->query("SELECT COUNT(*) AS c, COALESCE(MAX(id),0) AS m FROM `$tbl` WHERE $cond")
                       ->fetch(PDO::FETCH_ASSOC);
            $parts[] = $tbl . ':' . (int) $row['c'] . ':' . (int) $row['m'];
        } catch (PDOException $e) {
            // إن لم يوجد عمود is_active نعيد المحاولة بلا شرط
            try {
                $row = $pdo->query("SELECT COUNT(*) AS c, COALESCE(MAX(id),0) AS m FROM `$tbl`")
                           ->fetch(PDO::FETCH_ASSOC);
                $parts[] = $tbl . ':' . (int) $row['c'] . ':' . (int) $row['m'];
            } catch (PDOException $e2) {
                $parts[] = $tbl . ':0:0';
            }
        }
    }

    // الحلقات والأقسام: بلا شرط (الحلقات ليس فيها is_active)
    foreach (['episodes', 'categories'] as $tbl) {
        try {
            $row = $pdo->query("SELECT COUNT(*) AS c, COALESCE(MAX(id),0) AS m FROM `$tbl`")
                       ->fetch(PDO::FETCH_ASSOC);
            $parts[] = $tbl . ':' . (int) $row['c'] . ':' . (int) $row['m'];
        } catch (PDOException $e) {
            $parts[] = $tbl . ':0:0';
        }
    }

    // بصمة قصيرة للمقارنة فقط (ليست لغرض أمني)
    return substr(md5(implode('|', $parts)), 0, 16);
}

// ══════════════════════════════════════════════════════════════
// توجيه الطلبات حسب الإجراء
// ══════════════════════════════════════════════════════════════
switch ($action) {
    case 'categories':
        getCategories();
        break;

    case 'channels':
        getChannels();
        break;

    case 'channel':
        getChannel();
        break;

    case 'search':
        searchChannels();
        break;

    case 'featured':
        getFeaturedChannels();
        break;

    case 'stats':
        getStatistics();
        break;

    case 'increment_view':
        incrementViewCount();
        break;

    // ── جديد ──
    case 'all_content':
        getAllContent();
        break;

    case 'notification_state':
        getNotificationState();
        break;

    case 'series':
        getSeries();
        break;

    case 'episodes':
        getEpisodes();
        break;

    // ── بصمة المحتوى: للتحديث اللحظي بلا إعادة تحميل ──
    case 'content_version':
        getContentVersion();
        break;

    default:
        jsonResponse([
            'success' => false,
            'error'   => 'إجراء غير معروف',
        ], 400);
}

/**
 * الحصول على جميع الأقسام مع عدد القنوات.
 *
 * الاستجابة: success, count, categories
 *
 * @return never
 */
function getCategories()
{
    try {
        $categories = apiRemember('cat:' . apiContentStamp(), 120, static function (): array {
            $stmt = db()->prepare("
                SELECT 
                    c.id,
                    c.name,
                    c.slug,
                    c.icon,
                    c.description,
                    COUNT(ch.id) as channel_count
                FROM categories c
                LEFT JOIN channels ch ON c.id = ch.category_id AND ch.is_active = 1
                WHERE c.is_active = 1
                GROUP BY c.id
                ORDER BY c.display_order ASC, c.name ASC
            ");
            $stmt->execute();
            return $stmt->fetchAll();
        });

        apiCachedResponse([
            'success'    => true,
            'count'      => count($categories),
            'categories' => $categories,
        ], 120);

    } catch (PDOException $e) {
        error_log('API Error - getCategories: ' . $e->getMessage());
        jsonResponse([
            'success' => false,
            'error'   => 'حدث خطأ في جلب الأقسام',
        ], 500);
    }
}

/**
 * الحصول على القنوات حسب القسم.
 *
 * الاستجابة: success, count, total, limit, offset, channels
 *
 * @return never
 */
function getChannels()
{
    try {
        $category_id = isset($_GET['category_id']) ? (int) $_GET['category_id'] : 0;
        $limit       = safeInt($_GET['limit']  ?? null, 1, 500,     100);
        $offset      = safeInt($_GET['offset'] ?? null, 0, 1000000, 0);
        $after_id    = safeInt($_GET['after_id'] ?? null, 0, 2147483647, 0);

        if ($category_id <= 0) {
            jsonResponse([
                'success' => false,
                'error'   => 'معرف القسم غير صالح',
            ], 400);
        }

        $key = "chs:{$category_id}:{$limit}:{$offset}:{$after_id}:" . apiContentStamp();

        $payload = apiRemember($key, 90, static function () use ($category_id, $limit, $offset, $after_id): array {
            $pdo = db();

            if ($after_id > 0) {
                $stmt = $pdo->prepare("
                    SELECT ch.*, c.name as category_name, c.icon as category_icon
                    FROM channels ch
                    JOIN categories c ON ch.category_id = c.id
                    WHERE ch.category_id = ? AND ch.is_active = 1 AND ch.id > ?
                    ORDER BY ch.id DESC
                    LIMIT ?
                ");
                $stmt->execute([$category_id, $after_id, $limit]);
                $rows = $stmt->fetchAll();
                return ['rows' => $rows, 'total' => count($rows)];
            }

            // استعلام واحد يجلب الصفوف والعدد الكلي معاً (MySQL 8+)
            // بدلاً من استعلامين منفصلين.
            try {
                $stmt = $pdo->prepare("
                    SELECT 
                        ch.*,
                        c.name as category_name,
                        c.icon as category_icon,
                        COUNT(*) OVER() as __total
                    FROM channels ch
                    JOIN categories c ON ch.category_id = c.id
                    WHERE ch.category_id = ? AND ch.is_active = 1
                    ORDER BY ch.display_order ASC, ch.name ASC
                    LIMIT ? OFFSET ?
                ");
                $stmt->execute([$category_id, $limit, $offset]);
                $rows = $stmt->fetchAll();

                $total = 0;
                if ($rows) {
                    $total = (int) $rows[0]['__total'];
                    foreach ($rows as &$r) {
                        unset($r['__total']);
                    }
                    unset($r);
                } else {
                    // لا صفوف في هذه الصفحة → نحتاج العدد الكلي
                    $c = $pdo->prepare(
                        'SELECT COUNT(*) FROM channels WHERE category_id = ? AND is_active = 1'
                    );
                    $c->execute([$category_id]);
                    $total = (int) $c->fetchColumn();
                }

                return ['rows' => $rows, 'total' => $total];

            } catch (PDOException $e) {
                // بديل متوافق مع MySQL 5.7 — نفس السلوك الأصلي تماماً
                $stmt = $pdo->prepare("
                    SELECT 
                        ch.*,
                        c.name as category_name,
                        c.icon as category_icon
                    FROM channels ch
                    JOIN categories c ON ch.category_id = c.id
                    WHERE ch.category_id = ? AND ch.is_active = 1
                    ORDER BY ch.display_order ASC, ch.name ASC
                    LIMIT ? OFFSET ?
                ");
                $stmt->execute([$category_id, $limit, $offset]);
                $rows = $stmt->fetchAll();

                $countStmt = $pdo->prepare("
                    SELECT COUNT(*) as total 
                    FROM channels 
                    WHERE category_id = ? AND is_active = 1
                ");
                $countStmt->execute([$category_id]);

                return ['rows' => $rows, 'total' => (int) $countStmt->fetch()['total']];
            }
        });

        apiCachedResponse([
            'success'  => true,
            'count'    => count($payload['rows']),
            'total'    => $payload['total'],
            'limit'    => $limit,
            'offset'   => $offset,
            'channels' => $payload['rows'],
        ], 90);

    } catch (PDOException $e) {
        error_log('API Error - getChannels: ' . $e->getMessage());
        jsonResponse([
            'success' => false,
            'error'   => 'حدث خطأ في جلب القنوات',
        ], 500);
    }
}

/**
 * الحصول على قناة واحدة.
 *
 * الاستجابة: success, channel
 *
 * @return never
 */
function getChannel()
{
    try {
        $channel_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

        if ($channel_id <= 0) {
            jsonResponse([
                'success' => false,
                'error'   => 'معرف القناة غير صالح',
            ], 400);
        }

        $channel = apiRemember(
            "ch:{$channel_id}:" . apiContentStamp(),
            120,
            static function () use ($channel_id) {
                $stmt = db()->prepare("
                    SELECT 
                        ch.*,
                        c.name as category_name,
                        c.icon as category_icon
                    FROM channels ch
                    JOIN categories c ON ch.category_id = c.id
                    WHERE ch.id = ? AND ch.is_active = 1
                    LIMIT 1
                ");
                $stmt->execute([$channel_id]);
                return $stmt->fetch() ?: false;
            }
        );

        if (!$channel) {
            jsonResponse([
                'success' => false,
                'error'   => 'القناة غير موجودة',
            ], 404);
        }

        apiCachedResponse([
            'success' => true,
            'channel' => $channel,
        ], 120);

    } catch (PDOException $e) {
        error_log('API Error - getChannel: ' . $e->getMessage());
        jsonResponse([
            'success' => false,
            'error'   => 'حدث خطأ في جلب القناة',
        ], 500);
    }
}

/**
 * البحث في القنوات.
 * ── مُحسَّن: يبحث أيضاً في المسلسلات ──
 *
 * الاستجابة: success, query, count, channels, series
 *
 * @return never
 */
function searchChannels()
{
    try {
        // حدّ معدل أخصّ للبحث (الأثقل على قاعدة البيانات)
        if (!rateLimit('api:search', 60, 60)) {
            header('Retry-After: 30');
            jsonResponse([
                'success' => false,
                'error'   => 'طلبات بحث كثيرة جداً. حاول بعد قليل.',
            ], 429);
        }

        $query = sanitizeInput($_GET['q'] ?? '');
        $limit = safeInt($_GET['limit'] ?? null, 1, 200, 50);

        if ($query === '' || mb_strlen($query) < 2) {
            jsonResponse([
                'success' => false,
                'error'   => 'يجب إدخال حرفين على الأقل للبحث',
            ], 400);
        }

        // تهريب محارف LIKE الخاصة لمنع أنماط مكلفة (ReDoS-like) وسلوك غير متوقع
        $escaped    = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $query);
        $searchTerm = '%' . $escaped . '%';

        $key = 'srch:' . md5($query . '|' . $limit) . ':' . apiContentStamp();

        $payload = apiRemember($key, 60, static function () use ($searchTerm, $limit): array {
            $pdo = db();

            // البحث في القنوات (الكود الأصلي)
            $stmt = $pdo->prepare("
                SELECT 
                    ch.*,
                    c.name as category_name,
                    c.icon as category_icon
                FROM channels ch
                JOIN categories c ON ch.category_id = c.id
                WHERE ch.is_active = 1 
                AND (ch.name LIKE ? OR ch.description LIKE ?)
                ORDER BY ch.views_count DESC, ch.name ASC
                LIMIT ?
            ");
            $stmt->execute([$searchTerm, $searchTerm, $limit]);
            $channels = $stmt->fetchAll();

            // البحث في المسلسلات (جديد)
            $series = [];
            try {
                $srStmt = $pdo->prepare("
                    SELECT s.*, c.name as cat_name, COUNT(e.id) as ep_count
                    FROM series s
                    LEFT JOIN categories c ON s.category_id = c.id
                    LEFT JOIN episodes e ON e.series_id = s.id
                    WHERE s.is_active = 1 AND s.name LIKE ?
                    GROUP BY s.id
                    ORDER BY s.name ASC
                    LIMIT ?
                ");
                $srStmt->execute([$searchTerm, (int) ceil($limit / 2)]);
                $series = $srStmt->fetchAll();
            } catch (PDOException $e) {
                // الجدول غير موجود — تجاهل
            }

            return ['channels' => $channels, 'series' => $series];
        });

        apiCachedResponse([
            'success'  => true,
            'query'    => $query,
            'count'    => count($payload['channels']),
            'channels' => $payload['channels'],
            'series'   => $payload['series'],
        ], 60);

    } catch (PDOException $e) {
        error_log('API Error - searchChannels: ' . $e->getMessage());
        jsonResponse([
            'success' => false,
            'error'   => 'حدث خطأ في البحث',
        ], 500);
    }
}

/**
 * الحصول على القنوات المميزة.
 *
 * الاستجابة: success, count, channels
 *
 * @return never
 */
function getFeaturedChannels()
{
    try {
        $limit = safeInt($_GET['limit'] ?? null, 1, 100, 10);

        $channels = apiRemember(
            "feat:{$limit}:" . apiContentStamp(),
            180,
            static function () use ($limit): array {
                $stmt = db()->prepare("
                    SELECT 
                        ch.*,
                        c.name as category_name,
                        c.icon as category_icon
                    FROM channels ch
                    JOIN categories c ON ch.category_id = c.id
                    WHERE ch.is_active = 1 AND ch.is_featured = 1
                    ORDER BY ch.display_order ASC, ch.views_count DESC
                    LIMIT ?
                ");
                $stmt->execute([$limit]);
                return $stmt->fetchAll();
            }
        );

        apiCachedResponse([
            'success'  => true,
            'count'    => count($channels),
            'channels' => $channels,
        ], 180);

    } catch (PDOException $e) {
        error_log('API Error - getFeaturedChannels: ' . $e->getMessage());
        jsonResponse([
            'success' => false,
            'error'   => 'حدث خطأ في جلب القنوات المميزة',
        ], 500);
    }
}

/**
 * الحصول على الإحصائيات العامة.
 * ── مُحسَّن: يشمل إحصائيات المسلسلات والحلقات ──
 *
 * جميع الحقول الأصلية والمختصرة محفوظة كما هي.
 *
 * @return never
 */
function getStatistics()
{
    try {
        $stats = apiRemember('stats:' . apiContentStamp(), 120, static function (): array {
            $pdo = db();

            // استعلام واحد بدل ثلاثة للقنوات والأقسام والمشاهدات
            $row = $pdo->query("
                SELECT
                    (SELECT COUNT(*)          FROM channels   WHERE is_active = 1) AS total_channels,
                    (SELECT COUNT(*)          FROM categories WHERE is_active = 1) AS total_categories,
                    (SELECT COALESCE(SUM(views_count),0) FROM channels)            AS total_views
            ")->fetch(PDO::FETCH_ASSOC);

            $totalChannels   = (int) ($row['total_channels'] ?? 0);
            $totalCategories = (int) ($row['total_categories'] ?? 0);
            $totalViews      = (int) ($row['total_views'] ?? 0);

            // إحصائيات المسلسلات والحلقات (جديد)
            $totalSeries   = 0;
            $totalEpisodes = 0;
            try {
                $r2 = $pdo->query("
                    SELECT
                        (SELECT COUNT(*) FROM series WHERE is_active = 1) AS s,
                        (SELECT COUNT(*) FROM episodes)                   AS e
                ")->fetch(PDO::FETCH_ASSOC);
                $totalSeries   = (int) ($r2['s'] ?? 0);
                $totalEpisodes = (int) ($r2['e'] ?? 0);
            } catch (PDOException $e) {
                // الجدول غير موجود — تجاهل
            }

            return [
                // الحقول الأصلية — محفوظة
                'total_channels'   => $totalChannels,
                'total_categories' => $totalCategories,
                'total_views'      => $totalViews,
                'online'           => true,
                // الحقول الجديدة
                'total_series'     => $totalSeries,
                'total_episodes'   => $totalEpisodes,
                // أسماء مختصرة يستخدمها index.php
                'channels'         => $totalChannels,
                'categories'       => $totalCategories,
                'series'           => $totalSeries,
                'episodes'         => $totalEpisodes,
            ];
        });

        apiCachedResponse([
            'success' => true,
            'stats'   => $stats,
        ], 60);

    } catch (PDOException $e) {
        error_log('API Error - getStatistics: ' . $e->getMessage());
        jsonResponse([
            'success' => false,
            'error'   => 'حدث خطأ في جلب الإحصائيات',
        ], 500);
    }
}

/**
 * زيادة عداد المشاهدات.
 * ── مُحسَّن: يدعم ?type=channel|series|episode ──
 *
 * @return never
 */
function incrementViewCount()
{
    try {
        // حماية من الإساءة على عمليات الكتابة
        if (!rateLimit('api:view', 120, 60)) {
            header('Retry-After: 30');
            jsonResponse([
                'success' => false,
                'error'   => 'طلبات كثيرة جداً. حاول بعد قليل.',
            ], 429);
        }

        // لا كاش على عمليات الكتابة
        if (!headers_sent()) {
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        }

        $pdo = db();

        // قبول GET أو POST
        $channel_id = isset($_GET['id'])
            ? (int) $_GET['id']
            : (isset($_POST['channel_id']) ? (int) $_POST['channel_id'] : 0);
        $type = sanitizeInput($_GET['type'] ?? 'channel');

        if ($channel_id <= 0) {
            jsonResponse([
                'success' => false,
                'error'   => 'معرف القناة غير صالح',
            ], 400);
            return;
        }

        // مسلسل (جديد)
        if ($type === 'series') {
            try {
                $pdo->prepare('UPDATE series SET views_count = views_count + 1 WHERE id = ?')
                    ->execute([$channel_id]);
            } catch (PDOException $e) {
                error_log('API Error - incrementViewCount(series): ' . $e->getMessage());
            }
            jsonResponse([
                'success'   => true,
                'message'   => 'تم تسجيل المشاهدة',
                'series_id' => $channel_id,
            ]);
            return;
        }

        // حلقة (جديد)
        if ($type === 'episode') {
            try {
                $pdo->prepare('UPDATE episodes SET views_count = views_count + 1 WHERE id = ?')
                    ->execute([$channel_id]);
            } catch (PDOException $e) {
                error_log('API Error - incrementViewCount(episode): ' . $e->getMessage());
            }
            jsonResponse([
                'success'    => true,
                'message'    => 'تم تسجيل المشاهدة',
                'episode_id' => $channel_id,
            ]);
            return;
        }

        // قناة — الكود الأصلي
        // التحقق من وجود القناة
        $checkStmt = $pdo->prepare('SELECT id FROM channels WHERE id = ? AND is_active = 1');
        $checkStmt->execute([$channel_id]);

        if (!$checkStmt->fetch()) {
            jsonResponse([
                'success' => false,
                'error'   => 'القناة غير موجودة',
            ], 404);
            return;
        }

        // زيادة عداد المشاهدات - هذا الأهم!
        $stmt    = $pdo->prepare('UPDATE channels SET views_count = views_count + 1 WHERE id = ?');
        $success = $stmt->execute([$channel_id]);

        if (!$success) {
            jsonResponse([
                'success' => false,
                'error'   => 'فشل تحديث العداد',
            ], 500);
            return;
        }

        // تسجيل المشاهدة (اختياري - فقط إذا كان الجدول موجود)
        try {
            $ip         = clientIp();
            $user_agent = mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown'), 0, 255);

            $viewStmt = $pdo->prepare("
                INSERT INTO view_stats (channel_id, ip_address, user_agent, viewed_at) 
                VALUES (?, ?, ?, NOW())
            ");
            $viewStmt->execute([$channel_id, $ip, $user_agent]);
        } catch (PDOException $e) {
            // الجدول غير موجود - تجاهل الخطأ
        }

        jsonResponse([
            'success'    => true,
            'message'    => 'تم تسجيل المشاهدة بنجاح',
            'channel_id' => $channel_id,
        ]);

    } catch (PDOException $e) {
        error_log('API Error - incrementViewCount: ' . $e->getMessage());
        jsonResponse([
            'success' => false,
            'error'   => 'حدث خطأ في تسجيل المشاهدة',
        ], 500);
    }
}

/* ══════════════════════════════════════════════════════════════
   الدوال الجديدة — المسلسلات والحلقات
══════════════════════════════════════════════════════════════ */

/**
 * all_content
 * يعيد الأقسام مع عدد القنوات والمسلسلات معاً.
 * يستخدمه index.php لعرض شارة "X مسلسل" على بطاقة القسم.
 *
 * @return never
 */
function getAllContent()
{
    try {
        $categories = apiRemember('allc:' . apiContentStamp(), 120, static function (): array {
            $pdo = db();

            try {
                return $pdo->query("
                    SELECT
                        c.id,
                        c.name,
                        c.slug,
                        c.icon,
                        c.description,
                        c.display_order,
                        COUNT(DISTINCT ch.id) as channel_count,
                        COUNT(DISTINCT s.id)  as series_count,
                        MAX(ch.id) as max_ch_id,
                        MAX(s.id)  as max_sr_id
                    FROM categories c
                    LEFT JOIN channels ch ON ch.category_id = c.id AND ch.is_active = 1
                    LEFT JOIN series   s  ON s.category_id  = c.id AND s.is_active  = 1
                    WHERE c.is_active = 1
                    GROUP BY c.id
                    ORDER BY c.display_order ASC, c.name ASC
                ")->fetchAll();

            } catch (PDOException $e) {
                // احتياط: إذا فشل JOIN مع series نرجع للأقسام بدون series_count
                return $pdo->query("
                    SELECT c.id, c.name, c.slug, c.icon, c.description, c.display_order,
                           COUNT(ch.id) as channel_count, 0 as series_count,
                           MAX(ch.id) as max_ch_id, 0 as max_sr_id
                    FROM categories c
                    LEFT JOIN channels ch ON ch.category_id = c.id AND ch.is_active = 1
                    WHERE c.is_active = 1
                    GROUP BY c.id
                    ORDER BY c.display_order ASC, c.name ASC
                ")->fetchAll();
            }
        });

        apiCachedResponse([
            'success'    => true,
            'count'      => count($categories),
            'categories' => $categories,
        ], 120);

    } catch (PDOException $e) {
        error_log('API Error - getAllContent: ' . $e->getMessage());
        jsonResponse(['success' => false, 'error' => 'حدث خطأ في جلب المحتوى'], 500);
    }
}

/**
 * A deliberately small, uncached summary for the browser notification check.
 * It avoids relying on a potentially stale full-catalogue response and never
 * transfers the films, series, or channels themselves.
 *
 * @return never
 */
function getNotificationState()
{
    try {
        if (!headers_sent()) {
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
        }

        $pdo = db();
        try {
            $categories = $pdo->query("
                SELECT c.id, c.name,
                       COUNT(DISTINCT ch.id) AS channel_count,
                       COUNT(DISTINCT s.id)  AS series_count,
                       COALESCE(MAX(ch.id), 0) AS max_ch_id,
                       COALESCE(MAX(s.id), 0)  AS max_sr_id
                FROM categories c
                LEFT JOIN channels ch ON ch.category_id = c.id AND ch.is_active = 1
                LEFT JOIN series s ON s.category_id = c.id AND s.is_active = 1
                WHERE c.is_active = 1
                GROUP BY c.id
                ORDER BY c.display_order ASC, c.name ASC
            ")->fetchAll();
        } catch (PDOException $e) {
            $categories = $pdo->query("
                SELECT c.id, c.name,
                       COUNT(ch.id) AS channel_count,
                       0 AS series_count,
                       COALESCE(MAX(ch.id), 0) AS max_ch_id,
                       0 AS max_sr_id
                FROM categories c
                LEFT JOIN channels ch ON ch.category_id = c.id AND ch.is_active = 1
                WHERE c.is_active = 1
                GROUP BY c.id
                ORDER BY c.display_order ASC, c.name ASC
            ")->fetchAll();
        }

        jsonResponse([
            'success' => true,
            'categories' => $categories,
        ]);
    } catch (PDOException $e) {
        error_log('API Error - getNotificationState: ' . $e->getMessage());
        jsonResponse(['success' => false, 'error' => 'Unable to load notification state'], 500);
    }
}

/**
 * series
 * جلب المسلسلات — كل المسلسلات أو حسب قسم محدد عبر ?category_id=X
 *
 * الاستجابة: success, count, series
 *
 * @return never
 */
function getSeries()
{
    try {
        $category_id = isset($_GET['category_id']) ? (int) $_GET['category_id'] : 0;
        $limit       = safeInt($_GET['limit']  ?? null, 1, 500,     200);
        $offset      = safeInt($_GET['offset'] ?? null, 0, 1000000, 0);
        $after_id    = safeInt($_GET['after_id'] ?? null, 0, 2147483647, 0);

        $key = "srs:{$category_id}:{$limit}:{$offset}:{$after_id}:" . apiContentStamp();

        $series = apiRemember(
            $key,
            120,
            static function () use ($category_id, $limit, $offset, $after_id): array {
                $pdo = db();

                if ($after_id > 0) {
                    $stmt = $pdo->prepare("
                        SELECT s.*, c.name as cat_name, c.icon as cat_icon, COUNT(e.id) as ep_count
                        FROM series s
                        LEFT JOIN categories c ON s.category_id = c.id
                        LEFT JOIN episodes   e ON e.series_id   = s.id
                        WHERE s.category_id = ? AND s.is_active = 1 AND s.id > ?
                        GROUP BY s.id
                        ORDER BY s.id DESC
                        LIMIT ?
                    ");
                    $stmt->execute([$category_id, $after_id, $limit]);
                    return $stmt->fetchAll();
                }

                if ($category_id > 0) {
                    $stmt = $pdo->prepare("
                        SELECT s.*, c.name as cat_name, c.icon as cat_icon, COUNT(e.id) as ep_count
                        FROM series s
                        LEFT JOIN categories c ON s.category_id = c.id
                        LEFT JOIN episodes   e ON e.series_id   = s.id
                        WHERE s.category_id = ? AND s.is_active = 1
                        GROUP BY s.id
                        ORDER BY s.display_order ASC, s.id DESC
                        LIMIT ? OFFSET ?
                    ");
                    $stmt->execute([$category_id, $limit, $offset]);
                } else {
                    $stmt = $pdo->prepare("
                        SELECT s.*, c.name as cat_name, c.icon as cat_icon, COUNT(e.id) as ep_count
                        FROM series s
                        LEFT JOIN categories c ON s.category_id = c.id
                        LEFT JOIN episodes   e ON e.series_id   = s.id
                        WHERE s.is_active = 1
                        GROUP BY s.id
                        ORDER BY s.display_order ASC, s.id DESC
                        LIMIT ? OFFSET ?
                    ");
                    $stmt->execute([$limit, $offset]);
                }

                return $stmt->fetchAll();
            }
        );

        apiCachedResponse([
            'success' => true,
            'count'   => count($series),
            'series'  => $series,
        ], 120);

    } catch (PDOException $e) {
        error_log('API Error - getSeries: ' . $e->getMessage());
        jsonResponse(['success' => false, 'error' => 'حدث خطأ في جلب المسلسلات'], 500);
    }
}

/**
 * episodes
 * جلب حلقات مسلسل محدد عبر ?series_id=X
 *
 * الاستجابة: success, series_id, series_name, count, episodes
 *
 * @return never
 */
function getEpisodes()
{
    try {
        $series_id = isset($_GET['series_id']) ? (int) $_GET['series_id'] : 0;

        if ($series_id <= 0) {
            jsonResponse(['success' => false, 'error' => 'معرّف المسلسل غير صالح'], 400);
            return;
        }

        /* ⚠️ عطل «أفتح البوستر فلا يظهر محتوى، وبعد تحديث الصفحة يظهر»:
           كانت نتيجة «المسلسل غير موجود» (false) تُخزَّن في الكاش
           لمدة 120 ثانية مثل أي نتيجة ناجحة. فإذا صادف الطلب لحظةً
           كان فيها الصفّ قيد الاستيراد من Xtream (أو لم تُضبط قيمة
           is_active بعد)، بقي الرد «لا يوجد محتوى» ثابتاً دقيقتين
           كاملتين لكل من يفتح ذلك العمل — حتى لو اكتملت البيانات
           بعدها بثانية. وهذا يفسّر تماماً الطابع المتقطّع للمشكلة
           وأن إعادة التحميل بعد قليل «تُصلحها».
           الحل: لا نخزّن النتائج السلبية إلا لثوانٍ قليلة جداً. */
        $cacheKey = "eps:{$series_id}:" . apiContentStamp();
        $payload  = cacheGet($cacheKey);

        if ($payload === null) {
            $payload = (static function () use ($series_id) {
                $pdo = db();

                /* is_active قد لا يكون موجوداً في تركيبات أُنشئ فيها
                   جدول series من functions/helpers.php (تعريفه هناك
                   لا يحتوي العمود إطلاقاً، بخلاف database.sql).
                   لذلك نتحقق من وجود العمود قبل استخدامه بدل أن يفشل
                   الاستعلام كله ويظهر «تعذر تحميل الحلقات». */
                $hasActive = false;
                try {
                    $hasActive = (bool) $pdo->query("SHOW COLUMNS FROM `series` LIKE 'is_active'")->fetch();
                } catch (PDOException $e) {
                    $hasActive = false;
                }

                $sql   = 'SELECT id, name FROM series WHERE id = ?'
                       . ($hasActive ? ' AND is_active = 1' : '');
                $check = $pdo->prepare($sql);
                $check->execute([$series_id]);
                $sr = $check->fetch();

                if (!$sr) {
                    return false;
                }

                $stmt = $pdo->prepare("
                    SELECT *
                    FROM episodes
                    WHERE series_id = ?
                    ORDER BY episode_number ASC, display_order ASC, id ASC
                ");
                $stmt->execute([$series_id]);

                return ['name' => $sr['name'], 'episodes' => $stmt->fetchAll()];
            })();

            /* نتيجة ناجحة → كاش عادي (دقيقتان).
               نتيجة سلبية أو قائمة حلقات فارغة → 5 ثوانٍ فقط، حتى
               لا تُثبَّت حالة «لا يوجد محتوى» على المستخدمين. */
            $isNegative = ($payload === false) || empty($payload['episodes']);
            cacheSet($cacheKey, $payload, $isNegative ? 5 : 120);

            if (!headers_sent()) {
                header('X-Cache: MISS');
            }
        } elseif (!headers_sent()) {
            header('X-Cache: HIT');
        }

        if ($payload === false) {
            jsonResponse(['success' => false, 'error' => 'المسلسل غير موجود'], 404);
            return;
        }

        apiCachedResponse([
            'success'     => true,
            'series_id'   => $series_id,
            'series_name' => $payload['name'],
            'count'       => count($payload['episodes']),
            'episodes'    => $payload['episodes'],
        ], 120);

    } catch (PDOException $e) {
        error_log('API Error - getEpisodes: ' . $e->getMessage());
        jsonResponse(['success' => false, 'error' => 'حدث خطأ في جلب الحلقات'], 500);
    }
}

/**
 * content_version
 * بصمة صغيرة تتغيّر عند أي إضافة/حذف في المحتوى.
 * تستعملها index.php للتحديث اللحظي بلا إعادة تحميل الصفحة.
 *
 * مهم: نحسب القنوات/المسلسلات النشطة فقط (is_active = 1) ليتطابق
 * ما نراقبه مع ما تعرضه بقية نقاط الـAPI فعلاً؛ فتفعيل قناة أو
 * تعطيلها من لوحة التحكم يُعتبر تغييراً أيضاً.
 *
 * @return never
 */
function getContentVersion()
{
    // لا كاش إطلاقاً على هذه النقطة، وإلا لن يصل التغيير للمتصفح
    if (!headers_sent()) {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
    }

    $version = computeContentVersion();

    // تحديث بصمة الكاش الداخلية فوراً حتى تبطل النتائج القديمة
    cacheSet('content_stamp', $version, 10);

    if (!headers_sent()) {
        header('X-Content-Version: ' . $version);
    }

    jsonResponse([
        'success' => true,
        'version' => $version,
        'ts'      => time(),
    ]);
}
