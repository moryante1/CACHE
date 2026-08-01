<script>
// API Settings Dynamic Logic
function addApiCard() {
    const sel = document.getElementById('apiTypeSelect');
    const type = sel.value;
    if(!type) return;
    const card = document.getElementById('card_' + type);
    if(card) {
        card.style.display = 'block';
    }
    sel.value = '';
}

function removeApiCard(type) {
    const card = document.getElementById('card_' + type);
    if(card) {
        card.style.display = 'none';
        if(type === 'tmdb') document.getElementById('api_tmdb_key').value = '';
        if(type === 'omdb') document.getElementById('api_omdb_key').value = '';
        if(type === 'os') {
            document.getElementById('api_os_user').value = '';
            document.getElementById('api_os_pass').value = '';
            document.getElementById('api_os_key').value = '';
        }
        document.getElementById('status_' + type).innerHTML = '';
    }
}

async function testApiTmdb() {
    const key = document.getElementById('api_tmdb_key').value.trim();
    const st = document.getElementById('status_tmdb');
    if(!key) {
        st.innerHTML = '<span style="color:var(--red)"><i class="fas fa-times-circle"></i> ' + window.lang.please_enter_key + '</span>';
        return;
    }
    st.innerHTML = '<span style="color:var(--t3)"><i class="fas fa-spinner fa-spin"></i> ' + window.lang.scanning + '</span>';
    try {
        const r = await fetch(`https://api.themoviedb.org/3/configuration?api_key=${encodeURIComponent(key)}`);
        if(r.ok) {
            st.innerHTML = '<span style="color:var(--green)"><i class="fas fa-check-circle"></i> ' + window.lang.connected_successfully + '</span>';
        } else {
            st.innerHTML = '<span style="color:var(--red)"><i class="fas fa-times-circle"></i> ' + window.lang.invalid_key + '</span>';
        }
    } catch(e) {
        st.innerHTML = '<span style="color:var(--red)"><i class="fas fa-times-circle"></i> ' + window.lang.connection_error + '</span>';
    }
}

async function testApiOmdb() {
    const key = document.getElementById('api_omdb_key').value.trim();
    const st = document.getElementById('status_omdb');
    if(!key) {
        st.innerHTML = '<span style="color:var(--red)"><i class="fas fa-times-circle"></i> ' + window.lang.please_enter_key + '</span>';
        return;
    }
    st.innerHTML = '<span style="color:var(--t3)"><i class="fas fa-spinner fa-spin"></i> ' + window.lang.scanning + '</span>';
    try {
        const r = await fetch(`https://www.omdbapi.com/?apikey=${encodeURIComponent(key)}&s=Batman&page=1`);
        const d = await r.json();
        if(d.Response === 'True') {
            st.innerHTML = '<span style="color:var(--green)"><i class="fas fa-check-circle"></i> ' + window.lang.connected_successfully + '</span>';
        } else {
            st.innerHTML = '<span style="color:var(--red)"><i class="fas fa-times-circle"></i> ' + window.lang.invalid_key + '</span>';
        }
    } catch(e) {
        st.innerHTML = '<span style="color:var(--red)"><i class="fas fa-times-circle"></i> ' + window.lang.connection_error + '</span>';
    }
}

function testApiOs() {
    const user = document.getElementById('api_os_user').value.trim();
    const pass = document.getElementById('api_os_pass').value.trim();
    const key  = document.getElementById('api_os_key').value.trim();
    const st   = document.getElementById('status_os');

    if(!key || !user || !pass) {
        st.innerHTML = '<span style="color:var(--red)"><i class="fas fa-times-circle"></i> ' + window.lang.fill_all_fields + '</span>';
        return;
    }
    st.innerHTML = '<span style="color:var(--t3)"><i class="fas fa-spinner fa-spin"></i> ' + window.lang.scanning + '</span>';

    api({ajax_action:'os_login', username:user, password:pass, api_key:key}).then(d => {
        if(d.success){
            st.innerHTML = '<span style="color:var(--green)"><i class="fas fa-check-circle"></i> ' + window.lang.download_balance + (d.allowed||'?') + '</span>';
        } else {
            st.innerHTML = '<span style="color:var(--red)"><i class="fas fa-times-circle"></i> '+(d.error||window.lang.connection_failed)+'</span>';
        }
    }).catch(() => {
        st.innerHTML = '<span style="color:var(--red)"><i class="fas fa-times-circle"></i> ' + window.lang.connection_error + '</span>';
    });
}

function fetchServerStats() {
    const fd = new FormData();
    fd.append('ajax_action', 'get_server_stats');
    if (window.csrfToken) {
        fd.append('csrf_token', window.csrfToken);
    }
    
    // استخدام fetch مباشرة بدلاً من api() لمنع ظهور شريط التحميل العلوي
    fetch(location.href, { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            // CPU
            let cpuP = d.cpu.percent;
            document.getElementById('cpu_percent_text').textContent = cpuP + '%';
            let cpuColor = cpuP > 85 ? 'linear-gradient(90deg, #ff416c, #ff4b2b)' : (cpuP > 60 ? 'linear-gradient(90deg, #fceabb, #f8b500)' : 'linear-gradient(90deg, #B36BFF, #7B2CBF)');
            document.getElementById('cpu_bar').style.width = cpuP + '%';
            document.getElementById('cpu_bar').style.background = cpuColor;
            document.getElementById('cpu_desc').textContent = window.lang.actual_cpu_usage;

            // RAM
            let ramP = d.ram.percent;
            document.getElementById('ram_percent_text').textContent = ramP + '%';
            let ramColor = ramP > 85 ? 'linear-gradient(90deg, #ff416c, #ff4b2b)' : (ramP > 60 ? 'linear-gradient(90deg, #fceabb, #f8b500)' : 'linear-gradient(90deg, #00D084, #009e60)');
            document.getElementById('ram_bar').style.width = ramP + '%';
            document.getElementById('ram_bar').style.background = ramColor;
            document.getElementById('ram_used_text').textContent = window.lang.used + ' ' + d.ram.used;
            document.getElementById('ram_total_text').textContent = window.lang.total + ' ' + d.ram.total;

            // Disk
            let diskP = d.disk.percent;
            document.getElementById('disk_percent_text').textContent = diskP + '%';
            let diskColor = diskP > 85 ? 'linear-gradient(90deg, #ff416c, #ff4b2b)' : (diskP > 60 ? 'linear-gradient(90deg, #fceabb, #f8b500)' : 'linear-gradient(90deg, #4CC9F0, #0096C7)');
            document.getElementById('disk_bar').style.width = diskP + '%';
            document.getElementById('disk_bar').style.background = diskColor;
            document.getElementById('disk_used_text').textContent = window.lang.used + ' ' + d.disk.used;
            document.getElementById('disk_total_text').textContent = window.lang.total + ' ' + d.disk.total;
        }
    }).catch(e => {}); // الصمت عند الخطأ لمنع إزعاج المستخدم
}

document.addEventListener('DOMContentLoaded', () => {
    fetchServerStats();
    // تقليل المدة إلى 3 ثواني ليكون التحسس مستمر ولحظي
    setInterval(fetchServerStats, 3000);
});
</script>
