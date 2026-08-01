import sys
import subprocess
import json

# مفتاح التفعيل الخاص بك
AUTH_KEY = "tskey-auth-k95Y25hRt111CNTRL-kQ2aYL99sC1ZKo7NG6FdC1qfEvuaFyxY"

def run_command(cmd):
    try:
        # تحديد مهلة (Timeout) 12 ثانية كحد أقصى حتى لا تتجمد صفحة الموقع
        result = subprocess.run(cmd, capture_output=True, text=True, timeout=12)
        return True, result.stdout, result.stderr
    except subprocess.TimeoutExpired:
        return False, "", "نفذ الوقت (Timeout)، الأمر يستغرق وقتاً طويلاً."
    except Exception as e:
        return False, "", str(e)

def get_status():
    # استخدام sudo لأن أوبونتو يتطلب صلاحيات الروت للتحكم في Tailscale
    success, stdout, stderr = run_command(["sudo", "tailscale", "status", "--json"])
    
    if success and stdout.strip():
        try:
            data = json.loads(stdout)
            state = data.get("BackendState", "Offline")
            
            # جلب الآيبي الداخلي للسيرفر على شبكة تيلسكيل
            ip_list = data.get("Self", {}).get("TailscaleIPs", [])
            ip_address = ip_list[0] if ip_list else ""
            
            # جلب وعد الأجهزة الأخرى المتصلة في الشبكة
            peers = data.get("Peer", {}) 
            peers_count = len(peers.keys()) if isinstance(peers, dict) else 0
            
            return {
                "success": True, 
                "state": state, 
                "ip": ip_address,
                "peers_count": peers_count
            }
        except json.JSONDecodeError:
            pass
            
    return {"success": False, "state": "Offline", "error": stderr}

def start_tailscale():
    # بدء الاتصال باستخدام المفتاح المدمج
    success, stdout, stderr = run_command(["sudo", "tailscale", "up", f"--authkey={AUTH_KEY}", "--reset"])
    return get_status()

def stop_tailscale():
    # إيقاف الاتصال
    success, stdout, stderr = run_command(["sudo", "tailscale", "down"])
    return get_status()

if __name__ == "__main__":
    # استلام الأوامر القادمة من ملف admin.php
    action = sys.argv[1] if len(sys.argv) > 1 else "status"

    if action == "status":
        print(json.dumps(get_status()))
    elif action == "start":
        print(json.dumps(start_tailscale()))
    elif action == "stop":
        print(json.dumps(stop_tailscale()))