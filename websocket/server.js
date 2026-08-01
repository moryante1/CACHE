/**
 * ══════════════════════════════════════════════════════════════
 *  Shashety IPTV — خادم التحديثات اللحظية (WebSocket)
 * ══════════════════════════════════════════════════════════════
 *
 *  الأعطال الأمنية التي عولجت في هذه النسخة
 *  ──────────────────────────────────────────────────────────────
 *
 *  ① 🔴 نقطة /broadcast كانت **بلا أي مصادقة**:
 *        app.post('/broadcast', (req,res) => { io.emit(event,data); })
 *     أي شخص يصل إلى المنفذ 3000 كان يستطيع بثّ أي حدث لكل
 *     المتصلين — إشعارات مزيّفة، أو تحديثات محتوى وهمية، أو إغراق
 *     المستخدمين. وإن كان المنفذ مفتوحاً على الإنترنت (خطأ إعداد
 *     شائع) فالهجوم متاح للعالم كله.
 *     الحل: مفتاح سرّي مشترك + الاستماع على 127.0.0.1 افتراضياً.
 *
 *  ② الخادم كان يستمع على كل الواجهات (0.0.0.0) بلا داعٍ.
 *     PHP يتصل به محلياً فقط، فالربط بـ 127.0.0.1 يمنع الوصول
 *     الخارجي على مستوى الشبكة نفسها.
 *
 *  ③ CORS كان "*" — أي موقع في العالم يستطيع فتح اتصال ببثّك
 *     وقراءة كل التحديثات.
 *
 *  ④ لا حدّ لحجم الطلب ⇒ إغراق الذاكرة بحمولة ضخمة.
 *  ⑤ لا حدّ معدل على البثّ.
 *  ⑥ لا معالجة للأخطاء غير الملتقطة ⇒ انهيار صامت للخدمة.
 *  ⑦ لا إيقاف رشيق ⇒ قطع الاتصالات فجأة عند إعادة التشغيل.
 *  ⑧ العميل كان ينضم لأي غرفة بأي اسم بلا تحقق.
 *
 *  ══════════════════════════════════════════════════════════════
 *  متغيرات البيئة
 *  ══════════════════════════════════════════════════════════════
 *    WS_PORT            المنفذ (افتراضي 3000)
 *    WS_HOST            عنوان الاستماع (افتراضي 127.0.0.1)
 *    WS_SECRET          المفتاح المشترك مع PHP — **إلزامي**
 *    WS_ALLOWED_ORIGIN  أصول مسموحة مفصولة بفواصل، أو *
 *    WS_LOG             debug | info | error (افتراضي info)
 */

'use strict';

const express = require('express');
const http    = require('http');
const crypto  = require('crypto');
const { Server } = require('socket.io');

// ── الإعدادات ─────────────────────────────────────────────────
const PORT   = parseInt(process.env.WS_PORT || '3000', 10);
const HOST   = process.env.WS_HOST || '127.0.0.1';
const SECRET = process.env.WS_SECRET || '';
const LOGLVL = (process.env.WS_LOG || 'info').toLowerCase();

const ALLOWED_ORIGINS = (process.env.WS_ALLOWED_ORIGIN || '*')
  .split(',').map(s => s.trim()).filter(Boolean);

// الغرف المسموح بالانضمام إليها — قائمة مغلقة تمنع إنشاء غرف عشوائية
const ALLOWED_ROOMS = ['channels', 'movies', 'series', 'notifications', 'admin'];

// ── تسجيل بمستويات ────────────────────────────────────────────
const LEVELS = { debug: 10, info: 20, error: 30 };
const minLevel = LEVELS[LOGLVL] || LEVELS.info;

function log(level, ...args) {
  if ((LEVELS[level] || 99) < minLevel) return;
  const ts = new Date().toISOString();
  (level === 'error' ? console.error : console.log)(`[${ts}] [${level.toUpperCase()}]`, ...args);
}

if (!SECRET) {
  log('error',
    'WS_SECRET غير مضبوط! نقطة /broadcast مرفوضة بالكامل حتى تضبطه.\n' +
    '   ضع نفس القيمة في ملف .env الخاص بـ PHP وفي بيئة تشغيل Node.\n' +
    '   مقترح: WS_SECRET=' + crypto.randomBytes(24).toString('hex')
  );
}

// ══════════════════════════════════════════════════════════════
const app = express();

app.disable('x-powered-by');
app.set('trust proxy', 1);

// ④ حدّ حجم الحمولة
app.use(express.json({ limit: '64kb' }));

// معالجة أخطاء تحليل JSON بشكل نظيف
app.use((err, req, res, next) => {
  if (err && err.type === 'entity.parse.failed') {
    return res.status(400).json({ success: false, error: 'JSON غير صالح' });
  }
  if (err && err.type === 'entity.too.large') {
    return res.status(413).json({ success: false, error: 'الحمولة كبيرة جداً' });
  }
  return next(err);
});

const server = http.createServer(app);

const io = new Server(server, {
  cors: {
    origin: ALLOWED_ORIGINS.includes('*') ? '*' : ALLOWED_ORIGINS,
    methods: ['GET', 'POST'],
    credentials: false
  },
  pingTimeout: 30000,
  pingInterval: 25000,
  maxHttpBufferSize: 1e5
});

// ── مقارنة ثابتة الزمن (تمنع هجوم التوقيت) ────────────────────
function secretOk(provided) {
  if (!SECRET) return false;
  if (typeof provided !== 'string' || provided.length === 0) return false;

  const a = Buffer.from(provided);
  const b = Buffer.from(SECRET);
  if (a.length !== b.length) return false;
  return crypto.timingSafeEqual(a, b);
}

// ── ⑤ حدّ معدل في الذاكرة ─────────────────────────────────────
const rate = new Map();

function rateLimited(ip, max = 120, windowMs = 60000) {
  const now = Date.now();
  const rec = rate.get(ip);

  if (!rec || now > rec.resetAt) {
    rate.set(ip, { count: 1, resetAt: now + windowMs });
    return false;
  }
  if (rec.count >= max) return true;
  rec.count++;
  return false;
}

// تنظيف دوري يمنع نمو الذاكرة
const rateCleaner = setInterval(() => {
  const now = Date.now();
  for (const [ip, rec] of rate) if (now > rec.resetAt) rate.delete(ip);
}, 120000);
rateCleaner.unref();

// ══════════════════════════════════════════════════════════════
//  نقطة البثّ — يستدعيها PHP فقط
// ══════════════════════════════════════════════════════════════
app.post('/broadcast', (req, res) => {
  const ip = req.ip || (req.socket && req.socket.remoteAddress) || 'unknown';

  if (rateLimited(ip)) {
    return res.status(429).json({ success: false, error: 'تجاوز حد الطلبات' });
  }

  // ① المصادقة
  const provided = req.get('X-WS-Secret') || (req.body && req.body.secret) || '';
  if (!secretOk(provided)) {
    log('error', `رفض بثّ غير مصرّح به من ${ip}`);
    return res.status(403).json({ success: false, error: 'غير مصرّح' });
  }

  const { event, data, room } = req.body || {};

  // ② التحقق من المدخلات
  if (typeof event !== 'string' || !/^[a-z0-9_:-]{1,64}$/i.test(event)) {
    return res.status(400).json({ success: false, error: 'اسم حدث غير صالح' });
  }
  if (room !== undefined && room !== null && !ALLOWED_ROOMS.includes(room)) {
    return res.status(400).json({ success: false, error: 'غرفة غير معروفة' });
  }

  try {
    if (room) io.to(room).emit(event, data);
    else      io.emit(event, data);

    log('debug', `بثّ: ${event} → ${room || 'الجميع'}`);
    return res.json({ success: true, clients: io.engine.clientsCount });

  } catch (e) {
    log('error', 'فشل البثّ:', e.message);
    return res.status(500).json({ success: false, error: 'فشل البثّ' });
  }
});

// ══════════════════════════════════════════════════════════════
//  فحص الصحة — للمراقبة
// ══════════════════════════════════════════════════════════════
app.get('/health', (req, res) => {
  res.json({
    status:  'ok',
    uptime:  Math.round(process.uptime()),
    clients: io.engine.clientsCount,
    memory:  Math.round(process.memoryUsage().heapUsed / 1048576) + 'MB',
    secured: Boolean(SECRET)
  });
});

app.use((req, res) => res.status(404).json({ success: false, error: 'غير موجود' }));

app.use((err, req, res, _next) => {
  log('error', 'خطأ غير متوقع:', err && err.message);
  res.status(500).json({ success: false, error: 'خطأ داخلي' });
});

// ══════════════════════════════════════════════════════════════
//  اتصالات العملاء
// ══════════════════════════════════════════════════════════════
io.on('connection', (socket) => {
  log('debug', 'اتصال جديد:', socket.id);

  socket.on('join_room', (room) => {
    // ⑧ قائمة مغلقة بدل قبول أي اسم
    if (typeof room !== 'string' || !ALLOWED_ROOMS.includes(room)) {
      socket.emit('error_msg', 'غرفة غير مسموحة');
      return;
    }
    socket.join(room);
    log('debug', `${socket.id} انضم إلى ${room}`);
  });

  socket.on('leave_room', (room) => {
    if (typeof room === 'string' && ALLOWED_ROOMS.includes(room)) socket.leave(room);
  });

  socket.on('error', (e) => log('error', 'خطأ socket:', e && e.message));
  socket.on('disconnect', (reason) => log('debug', 'انقطاع:', socket.id, reason));
});

// ══════════════════════════════════════════════════════════════
//  التشغيل والإيقاف الرشيق
// ══════════════════════════════════════════════════════════════
server.listen(PORT, HOST, () => {
  log('info', `خادم WebSocket يعمل على ${HOST}:${PORT}`);
  log('info', `الحماية: ${SECRET ? 'مفعّلة ✔' : 'معطّلة ✘ (اضبط WS_SECRET)'}`);
  log('info', `الأصول المسموحة: ${ALLOWED_ORIGINS.join(', ')}`);
});

server.on('error', (e) => {
  if (e.code === 'EADDRINUSE') {
    log('error', `المنفذ ${PORT} مستخدم بالفعل.`);
    process.exit(1);
  }
  log('error', 'خطأ في الخادم:', e.message);
});

// ⑥ منع الانهيار الصامت
process.on('uncaughtException',  (e) => log('error', 'استثناء غير ملتقط:', e && e.stack));
process.on('unhandledRejection', (e) => log('error', 'وعد مرفوض:', e));

// ⑦ إيقاف رشيق
let shuttingDown = false;
function shutdown(signal) {
  if (shuttingDown) return;
  shuttingDown = true;
  log('info', `${signal} — إيقاف رشيق...`);

  io.close(() => log('info', 'أُغلقت اتصالات WebSocket'));
  server.close(() => { log('info', 'أُغلق الخادم'); process.exit(0); });

  setTimeout(() => { log('error', 'انتهت مهلة الإيقاف — إنهاء إجباري'); process.exit(1); }, 10000).unref();
}
process.on('SIGTERM', () => shutdown('SIGTERM'));
process.on('SIGINT',  () => shutdown('SIGINT'));
