#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
تحويل الفيديو إلى MP4 صالح للتشغيل في المتصفح.

═══════════════════════════════════════════════════════════════════════════
 لماذا أُعيدت كتابة هذا الملف
───────────────────────────────────────────────────────────────────────────
 النسخة السابقة كانت تستخدم:  ffmpeg -i in -c copy out

 و -c copy ينسخ الفيديو **والصوت** كما هما. فملف MKV بصوت AC3 يصبح MP4
 بصوت AC3: الحاوية صارت مدعومة في المتصفح لكن ترميز الصوت لم يتغيّر.
 النتيجة صورة تعمل بلا صوت — وهو عطل صامت لا يُبلّغ عن نفسه، لأن
 التحويل "نجح" من ناحية ffmpeg.

 كروم وفايرفوكس لا يفكّان AC3/EAC3/DTS/TrueHD، ولا يمكن إضافة هذه
 الترميزات إلى المتصفح من جهة الخادم — الحل الوحيد تحويل الصوت إلى AAC.

 المنهج هنا: نفحص الملف أولاً ثم نقرّر لكل مسار على حدة.
   • فيديو H.264/H.265 → نسخ بلا إعادة ترميز (سريع، بلا فقد جودة)
   • فيديو غير ذلك     → تحويل إلى H.264
   • صوت AAC/MP3       → نسخ
   • صوت AC3/DTS/…     → تحويل إلى AAC ستيريو
 فلا نعيد ترميز ما لا يحتاج، ولا نترك ما يحتاج.
═══════════════════════════════════════════════════════════════════════════

الاستخدام:
    python3 convert_mp4.py <input> <output>
    python3 convert_mp4.py --probe <input>      # فحص فقط، يطبع JSON
"""

import json
import os
import subprocess
import sys

# ترميزات يشغّلها المتصفح مباشرةً داخل حاوية MP4
BROWSER_AUDIO = {'aac', 'mp3', 'opus', 'flac'}
BROWSER_VIDEO = {'h264', 'hevc', 'vp9', 'av1'}

# ترميزات صوت شائعة في ملفات BluRay/DVD لا يفكّها أي متصفح
NEEDS_TRANSCODE = {'ac3', 'eac3', 'dts', 'truehd', 'mlp', 'pcm_s16le',
                   'pcm_s24le', 'pcm_bluray', 'pcm_dvd', 'wmav2', 'wmapro'}


def run(cmd, timeout=None):
    """ينفّذ أمراً ويُرجع (returncode, stdout, stderr)."""
    p = subprocess.run(cmd, stdout=subprocess.PIPE, stderr=subprocess.PIPE,
                       text=True, errors='replace', timeout=timeout)
    return p.returncode, p.stdout, p.stderr


def probe(path):
    """
    يفحص الملف ويُرجع وصف مساراته.
    @return dict|None
    """
    rc, out, err = run([
        'ffprobe', '-v', 'quiet', '-print_format', 'json',
        '-show_streams', '-show_format', path
    ], timeout=120)
    if rc != 0:
        return None
    try:
        data = json.loads(out)
    except ValueError:
        return None

    info = {'video': [], 'audio': [], 'subtitle': [], 'duration': 0.0}
    try:
        info['duration'] = float(data.get('format', {}).get('duration', 0) or 0)
    except (TypeError, ValueError):
        pass

    for s in data.get('streams', []):
        t = s.get('codec_type')
        if t not in ('video', 'audio', 'subtitle'):
            continue
        # الصور المضمّنة (غلاف الملف) تُعدّ مسار فيديو وتُفسد التحويل
        if t == 'video' and (s.get('disposition') or {}).get('attached_pic'):
            continue
        info[t].append({
            'index':    s.get('index'),
            'codec':    (s.get('codec_name') or '').lower(),
            'channels': s.get('channels'),
            'lang':     (s.get('tags') or {}).get('language', ''),
            'title':    (s.get('tags') or {}).get('title', ''),
        })
    return info


def plan(info):
    """
    يبني قرار التحويل ويشرحه.
    @return (args:list, notes:list, will_reencode_video:bool)
    """
    args, notes = [], []

    # ── الفيديو ──
    if not info['video']:
        notes.append('لا يوجد مسار فيديو')
        vcopy = True
    else:
        vc = info['video'][0]['codec']
        vcopy = vc in BROWSER_VIDEO
        if vcopy:
            args += ['-c:v', 'copy']
            notes.append(f'فيديو {vc} → نسخ بلا إعادة ترميز')
        else:
            args += ['-c:v', 'libx264', '-preset', 'veryfast', '-crf', '20',
                     '-pix_fmt', 'yuv420p']
            notes.append(f'فيديو {vc} غير مدعوم → تحويل إلى H.264 (سيستغرق وقتاً)')

    # ── الصوت ──
    if not info['audio']:
        notes.append('⚠ لا يوجد مسار صوت في الملف الأصلي')
    else:
        any_transcode = False
        for i, a in enumerate(info['audio']):
            c = a['codec']
            if c in BROWSER_AUDIO:
                args += [f'-c:a:{i}', 'copy']
                notes.append(f'صوت #{i} ({c}) → نسخ')
            else:
                # ستيريو: كثير من الأجهزة لا تُخرج 5.1، والخلط يمنع اختفاء
                # الحوار الذي يوضع عادةً في القناة الوسطى.
                args += [f'-c:a:{i}', 'aac', f'-b:a:{i}', '192k', f'-ac:a:{i}', '2']
                any_transcode = True
                reason = 'لا يدعمه المتصفح' if c in NEEDS_TRANSCODE else 'ترميز غير مألوف'
                notes.append(f'صوت #{i} ({c}) {reason} → تحويل إلى AAC ستيريو')
        if any_transcode:
            notes.append('★ هذا هو سبب غياب الصوت — التحويل يعالجه')

    return args, notes, (not vcopy)


def convert(src, dst):
    info = probe(src)
    if info is None:
        print('ERROR: تعذّر فحص الملف (ffprobe)')
        return False

    args, notes, heavy = plan(info)
    for n in notes:
        print('  · ' + n)

    cmd = ['ffmpeg', '-y', '-hide_banner', '-loglevel', 'error', '-i', src]

    # نأخذ مسار الفيديو وكل مسارات الصوت. الترجمات تُستثنى عمداً: صيغ
    # SRT/ASS لا تدخل MP4 إلا بتحويل، وفشلها يُسقط العملية كلها — والمشروع
    # يتعامل مع الترجمات كملفات VTT منفصلة أصلاً.
    if info['video']:
        cmd += ['-map', '0:v:0']
    for i in range(len(info['audio'])):
        cmd += ['-map', f'0:a:{i}']

    cmd += args
    # faststart ينقل فهرس الملف إلى بدايته ليبدأ التشغيل قبل اكتمال التنزيل
    cmd += ['-movflags', '+faststart', dst]

    def discard(msg):
        """
        يحذف الناتج الفاشل ثم يُبلّغ.

        ⚠ الحذف ضروري لا تجميلي. المستدعي في ajax/handlers.php يقول:
            if (strpos($output,'SUCCESS') !== false || filesize($new_path) > 1024)
        أي أنه يعتبر مجرد وجود ملف كبير نجاحاً — ثم ينفّذ unlink على الملف
        الأصلي. فلو تركنا ناتجاً فاشلاً على القرص لحُذف المصدر السليم
        واستُبدل بملف معطوب. لا نترك أثراً عند الفشل.
        """
        try:
            if os.path.exists(dst):
                os.remove(dst)
        except OSError:
            pass
        print('ERROR: ' + msg)
        return False

    rc, out, err = run(cmd)
    if rc != 0 or not os.path.exists(dst) or os.path.getsize(dst) == 0:
        return discard('FFmpeg Error: ' + (err.strip()[:500] or 'فشل غير معروف'))

    # ── تحقّق فعلي من الناتج ──
    # "نجح ffmpeg" لا يعني "يوجد صوت مسموع". هذا الفارق بالضبط هو ما جعل
    # النسخة السابقة تُبلّغ SUCCESS عن ملفات صامتة.
    chk = probe(dst)
    if chk:
        if info['audio'] and not chk['audio']:
            return discard('فُقد مسار الصوت أثناء التحويل')
        bad = [a['codec'] for a in chk['audio'] if a['codec'] not in BROWSER_AUDIO]
        if bad:
            return discard('الناتج ما يزال يحمل صوتاً غير مدعوم: ' + ', '.join(bad))
        if info['video'] and not chk['video']:
            return discard('فُقد مسار الفيديو أثناء التحويل')

    print('SUCCESS')   # PHP يبحث عن هذه الكلمة
    return True


def main():
    if len(sys.argv) >= 3 and sys.argv[1] == '--probe':
        info = probe(sys.argv[2])
        if info is None:
            print(json.dumps({'ok': False, 'error': 'probe_failed'}, ensure_ascii=False))
            sys.exit(1)
        _, notes, _ = plan(info)
        bad = [a['codec'] for a in info['audio'] if a['codec'] not in BROWSER_AUDIO]
        print(json.dumps({
            'ok': True,
            'video': info['video'],
            'audio': info['audio'],
            'duration': round(info['duration'], 1),
            'audio_playable': not bad,
            'bad_audio': bad,
            'notes': notes,
        }, ensure_ascii=False, indent=2))
        sys.exit(0)

    if len(sys.argv) < 3:
        print('Usage: python3 convert_mp4.py <input_file> <output_file>')
        print('       python3 convert_mp4.py --probe <input_file>')
        sys.exit(1)

    src, dst = sys.argv[1], sys.argv[2]
    if not os.path.exists(src):
        print(f'ERROR: Input file not found: {src}')
        sys.exit(1)

    ok = convert(src, dst)
    sys.exit(0 if ok else 1)


if __name__ == '__main__':
    try:
        main()
    except FileNotFoundError:
        print('ERROR: FFmpeg is not installed on the server.')
        sys.exit(1)
    except KeyboardInterrupt:
        sys.exit(130)
    except Exception as e:
        print(f'ERROR: {e}')
        sys.exit(1)
