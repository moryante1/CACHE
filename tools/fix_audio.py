#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
فحص وإصلاح الصوت في الفيديوهات المرفوعة سابقاً.

═══════════════════════════════════════════════════════════════════════════
 الملفات التي رُفعت قبل إصلاح convert_mp4.py ما تزال تحمل صوت AC3/DTS،
 فتُعرض صورةً بلا صوت. هذه الأداة تجدها وتصلحها.

 الفحص افتراضي والإصلاح يحتاج --fix صراحةً: التحويل يستهلك وقتاً
 ومساحة، ولا يجوز أن يبدأ بالخطأ على مكتبة كاملة.
═══════════════════════════════════════════════════════════════════════════

الاستخدام:
    python3 fix_audio.py /var/www/html/iptv/uploads/videos          # فحص
    python3 fix_audio.py /var/www/html/iptv/uploads/videos --fix    # إصلاح
    python3 fix_audio.py ... --fix --keep      # الاحتفاظ بالأصل
"""

import os
import shutil
import subprocess
import sys
import time

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
try:
    from convert_mp4 import probe, plan, BROWSER_AUDIO, run
except ImportError:
    print('ERROR: convert_mp4.py غير موجود بجانب هذا الملف')
    sys.exit(1)

EXTS = ('.mp4', '.mkv', '.avi', '.mov', '.webm', '.ts', '.flv', '.m4v', '.wmv')

G, R, Y, B, N = '\033[32m', '\033[31m', '\033[33m', '\033[1m', '\033[0m'


def human(n):
    for u in ('B', 'KB', 'MB', 'GB'):
        if n < 1024:
            return f'{n:.0f}{u}'
        n /= 1024
    return f'{n:.1f}TB'


def hhmm(sec):
    sec = int(sec)
    return f'{sec // 3600:02d}:{(sec % 3600) // 60:02d}:{sec % 60:02d}'


def scan(root):
    """يجمع كل ملفات الفيديو ويصنّفها."""
    good, bad, broken, noaudio = [], [], [], []
    files = []
    for dirpath, _, names in os.walk(root):
        for nm in names:
            if nm.lower().endswith(EXTS) and not nm.startswith('.'):
                files.append(os.path.join(dirpath, nm))

    print(f'{B}جارٍ فحص {len(files)} ملفاً…{N}\n')
    for i, f in enumerate(sorted(files), 1):
        sys.stdout.write(f'\r  [{i}/{len(files)}] {os.path.basename(f)[:55]:<55}')
        sys.stdout.flush()
        info = probe(f)
        if info is None:
            broken.append((f, 'تعذّر الفحص'))
            continue
        if not info['audio']:
            noaudio.append((f, 'لا مسار صوت'))
            continue
        bads = [a['codec'] for a in info['audio'] if a['codec'] not in BROWSER_AUDIO]
        if bads:
            bad.append((f, ', '.join(sorted(set(bads)))))
        else:
            good.append((f, info['audio'][0]['codec']))
    sys.stdout.write('\r' + ' ' * 78 + '\r')
    return good, bad, broken, noaudio


def fix_one(path, keep):
    """
    يحوّل ملفاً في مكانه عبر ملف مؤقّت.
    الاستبدال لا يحدث إلا بعد التحقّق من صحّة الناتج — الكتابة فوق الأصل
    مباشرةً تعني أن أي فشل يفقد الملف نهائياً.
    """
    base, _ = os.path.splitext(path)
    tmp = base + '.__fixing__.mp4'
    final = base + '.mp4'

    info = probe(path)
    if info is None:
        return False, 'تعذّر الفحص'

    args, _, _ = plan(info)
    cmd = ['ffmpeg', '-y', '-hide_banner', '-loglevel', 'error', '-i', path]
    if info['video']:
        cmd += ['-map', '0:v:0']
    for i in range(len(info['audio'])):
        cmd += ['-map', f'0:a:{i}']
    cmd += args + ['-movflags', '+faststart', tmp]

    rc, _, err = run(cmd)
    if rc != 0 or not os.path.exists(tmp) or os.path.getsize(tmp) == 0:
        if os.path.exists(tmp):
            os.remove(tmp)
        return False, (err.strip()[:120] or 'فشل ffmpeg')

    chk = probe(tmp)
    if not chk or not chk['audio']:
        os.remove(tmp)
        return False, 'الناتج بلا صوت'
    still = [a['codec'] for a in chk['audio'] if a['codec'] not in BROWSER_AUDIO]
    if still:
        os.remove(tmp)
        return False, 'الصوت ما يزال غير مدعوم: ' + ', '.join(still)

    if keep:
        shutil.move(path, path + '.orig')
    else:
        os.remove(path)
    shutil.move(tmp, final)
    try:
        os.chmod(final, 0o644)
    except OSError:
        pass
    return True, final


def main():
    if len(sys.argv) < 2:
        print(__doc__)
        sys.exit(1)

    root = sys.argv[1]
    do_fix = '--fix' in sys.argv
    keep = '--keep' in sys.argv

    if not os.path.isdir(root):
        print(f'{R}المجلد غير موجود: {root}{N}')
        sys.exit(1)

    if not shutil.which('ffmpeg') or not shutil.which('ffprobe'):
        print(f'{R}ffmpeg غير مثبّت:{N}  sudo apt install ffmpeg')
        sys.exit(1)

    good, bad, broken, noaudio = scan(root)

    print(f'{B}══ النتيجة ══{N}')
    print(f'  {G}✔{N} صوت سليم:            {len(good)}')
    print(f'  {R}✘{N} صوت لا يعمل بالمتصفح: {len(bad)}')
    print(f'  {Y}⚠{N} بلا مسار صوت:        {len(noaudio)}')
    print(f'  {Y}⚠{N} تعذّر فحصها:          {len(broken)}\n')

    if noaudio:
        print(f'{Y}ملفات بلا صوت أصلاً (لا يصلحها التحويل):{N}')
        for f, _ in noaudio[:10]:
            print(f'    {os.path.basename(f)}')
        if len(noaudio) > 10:
            print(f'    … و{len(noaudio) - 10} غيرها')
        print()

    if not bad:
        print(f'{G}لا توجد ملفات تحتاج إصلاحاً.{N}')
        return

    total = sum(os.path.getsize(f) for f, _ in bad)
    print(f'{R}ملفات صوتها لا يعمل في المتصفح ({human(total)}):{N}')
    for f, codecs in bad[:25]:
        print(f'    {codecs:<12} {human(os.path.getsize(f)):>8}  {os.path.basename(f)[:50]}')
    if len(bad) > 25:
        print(f'    … و{len(bad) - 25} غيرها')

    if not do_fix:
        print(f'\n{Y}هذا فحص فقط. للإصلاح أضف --fix:{N}')
        print(f'    python3 {os.path.basename(__file__)} {root} --fix')
        print(f'\n  الفيديو يُنسخ بلا إعادة ترميز، فالتحويل سريع')
        print(f'  (دقائق للمكتبة كلها عادةً، لا ساعات).')
        print(f'  أضف --keep للاحتفاظ بالملفات الأصلية بلاحقة .orig')
        return

    print(f'\n{B}══ بدء الإصلاح ══{N}')
    ok = fail = 0
    t0 = time.time()
    for i, (f, codecs) in enumerate(bad, 1):
        name = os.path.basename(f)[:48]
        sys.stdout.write(f'  [{i}/{len(bad)}] {name:<48} … ')
        sys.stdout.flush()
        success, msg = fix_one(f, keep)
        if success:
            ok += 1
            print(f'{G}✔{N}')
        else:
            fail += 1
            print(f'{R}✘ {msg}{N}')

    print(f'\n{B}══ انتهى ══{N}')
    print(f'  {G}✔{N} أُصلح: {ok}')
    if fail:
        print(f'  {R}✘{N} فشل:  {fail}')
    print(f'  الزمن: {hhmm(time.time() - t0)}')
    if keep and ok:
        print(f'\n  الأصول محفوظة بلاحقة .orig — احذفها بعد التأكد:')
        print(f'      find {root} -name "*.orig" -delete')
    if ok:
        print(f'\n  {Y}ملاحظة:{N} إن تغيّر امتداد ملف من .mkv إلى .mp4 فحدّث رابطه')
        print(f'  في لوحة الإدارة، أو شغّل الاستيراد من جديد.')


if __name__ == '__main__':
    try:
        main()
    except KeyboardInterrupt:
        print('\nأُلغي.')
        sys.exit(130)
