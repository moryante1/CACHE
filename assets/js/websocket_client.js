// Real-time updates client using Socket.io
document.addEventListener('DOMContentLoaded', () => {
    // Check if io is defined (Socket.io script loaded successfully)
    if (typeof io === 'undefined') {
        console.warn('[WebSocket] Socket.io library not loaded. Real-time features disabled.');
        return;
    }

    // Attempt to connect to the WebSocket server via the same domain (Production Ready)
    let connectFailures = 0;
    const socket = io('/', {
        reconnection: true,
        reconnectionAttempts: 5,
        reconnectionDelay: 2000,
        reconnectionDelayMax: 30000,
        randomizationFactor: 0.5,
        timeout: 6000,
    });

    socket.on('connect', () => {
        connectFailures = 0;
        window.isWebSocketActive = true; // Tell main_js to stop polling
    });

    // Listen for general content updates
    socket.on('content_update', (data) => {
        let msg = data && data.title ? `تحديث جديد: ${data.title}` : 'تم تحديث المحتوى للتو!';
        showRealTimeToast(msg);
        
        // Dispatch event so main_js.php can trigger the UI refresh without full page reload
        window.dispatchEvent(new CustomEvent('shashety_ws_update', { detail: data }));
    });

    socket.on('disconnect', () => {
        window.isWebSocketActive = false; // Tell main_js to resume polling
    });

    socket.on('connect_error', () => {
        connectFailures++;
        window.isWebSocketActive = false;
        if (connectFailures >= 5) socket.disconnect();
    });

    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            socket.disconnect();
            window.isWebSocketActive = false;
        } else if (!socket.connected && connectFailures < 5) {
            socket.connect();
        }
    });
});

// Professional Notification Toast Function
function showRealTimeToast(message) {
    let t = document.createElement('div');
    t.style.cssText = `
        position: fixed;
        bottom: 20px;
        right: 20px;
        background: rgba(0, 208, 132, 0.95);
        backdrop-filter: blur(10px);
        color: #fff;
        padding: 15px 25px;
        border-radius: 12px;
        box-shadow: 0 8px 30px rgba(0,208,132,0.3);
        z-index: 999999;
        font-weight: bold;
        font-family: inherit;
        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        opacity: 0;
        transform: translateY(30px) scale(0.9);
        display: flex;
        align-items: center;
        gap: 10px;
    `;
    
    const icon = document.createElement('i');
    icon.className = 'fas fa-bell';
    const text = document.createElement('span');
    text.textContent = String(message || 'تم تحديث المحتوى');
    t.append(icon, text);
    
    document.body.appendChild(t);
    
    // Animate In
    setTimeout(() => {
        t.style.opacity = '1';
        t.style.transform = 'translateY(0) scale(1)';
    }, 50);

    // Animate Out
    setTimeout(() => {
        t.style.opacity = '0';
        t.style.transform = 'translateY(20px) scale(0.9)';
        setTimeout(() => t.remove(), 400);
    }, 5000);
}
