<script>
window.clearLoginLogs = function(){
    if(confirm("هل أنت متأكد من حذف جميع سجلات الدخول؟ لا يمكن التراجع عن هذا الإجراء.")){
        api({ajax_action:'clear_login_logs'}).then(d=>{ if(d.success) loadLoginLogs(); else al('alContainer',d.error,'e'); });
    }
};
window.exportLoginLogs = function(){
    api({ajax_action:'get_login_logs'}).then(d => {
        if(d.success && d.logs && d.logs.length > 0){
            let csvContent = "data:text/csv;charset=utf-8,\uFEFF";
            csvContent += "ID,IP Address,Username,Status,Blocked,Time\n";
            d.logs.forEach(l => {
                let row = [l.id, l.ip_address, l.username, l.status, (l.is_blocked==1?'Yes':'No'), l.attempt_time].join(",");
                csvContent += row + "\r\n";
            });
            let encodedUri = encodeURI(csvContent);
            let link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", "login_logs_" + new Date().toISOString().slice(0,10) + ".csv");
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        } else {
            al('alContainer', 'لا يوجد بيانات لتصديرها', 'e');
        }
    });
};
function loadLoginLogs(){
    $('llTbody').innerHTML = '<tr><td colspan="5" style="text-align:center"><span class="sp"></span> جاري التحميل...</td></tr>';
    api({ajax_action:'get_login_logs'}).then(d => {
        if(d.success){
            let h = '';
            if(!d.logs || d.logs.length === 0){
                h = '<tr><td colspan="5" style="text-align:center;color:var(--t3)">لا يوجد سجلات</td></tr>';
            } else {
                d.logs.forEach(l => {
                    let st = l.status === 'success' ? '<span style="color:#00D084;font-weight:bold">ناجح</span>' : '<span style="color:#E50914;font-weight:bold">فشل</span>';
                    h += `<tr>
                        <td>${l.id}</td>
                        <td dir="ltr" style="text-align:right">${esc(l.ip_address)}</td>
                        <td>${esc(l.username||'-')}</td>
                        <td>${st}</td>
                        <td dir="ltr" style="text-align:right">${esc(l.attempt_time)}</td>
                    </tr>`;
                });
            }
            $('llTbody').innerHTML = h;
            if(window.lucide) lucide.createIcons();
        } else {
            al('alContainer', d.error || 'حدث خطأ', 'e');
            $('llTbody').innerHTML = '<tr><td colspan="5" style="text-align:center;color:red">خطأ في التحميل</td></tr>';
        }
    }).catch(e => {
        $('llTbody').innerHTML = '<tr><td colspan="5" style="text-align:center;color:red">فشل الاتصال</td></tr>';
    });
}
</script>
