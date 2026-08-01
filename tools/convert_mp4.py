import sys
import subprocess
import os

def convert_video(input_path, output_path):
    # التحقق من وجود الملف الأصلي
    if not os.path.exists(input_path):
        print(f"ERROR: Input file not found: {input_path}")
        sys.exit(1)

    # استخدام FFmpeg لتحويل الصيغة (بسرعة فائقة دون إعادة إنتاج الترميز)
    command = [
        'ffmpeg',
        '-y',               # الموافقة التلقائية على الاستبدال
        '-i', input_path,   # مسار الملف الأصلي
        '-c', 'copy',       # نسخ الفيديو والصوت كما هو (سريع جداً)
        output_path         # مسار الملف الجديد
    ]

    try:
        # تشغيل أمر التحويل
        process = subprocess.run(command, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True)
        
        # التحقق من نجاح التحويل ووجود الملف الجديد
        if process.returncode == 0 and os.path.exists(output_path):
            # هذه الكلمة ضرورية جداً لأن كود الـ PHP يبحث عنها ليعرف أن العملية نجحت
            print("SUCCESS")
        else:
            print(f"FFmpeg Error: {process.stderr}")
            
    except FileNotFoundError:
        print("ERROR: FFmpeg is not installed on the server.")
    except Exception as e:
        print(f"ERROR: {str(e)}")

if __name__ == "__main__":
    if len(sys.argv) < 3:
        print("Usage: python convert_mp4.py <input_file> <output_file>")
        sys.exit(1)
        
    in_file = sys.argv[1]
    out_file = sys.argv[2]
    
    convert_video(in_file, out_file)