<section id="login-logs" class="sec">
    <div class="sc">
        <div class="srow">
            <div class="sc-ic"><i data-lucide="shield"></i></div>
            <div class="sc-tit">
                <div class="sc-v"><?= $t["login_logs_title"] ?? "سجل محاولات الدخول" ?></div>
                <div class="sc-l"><?= $t["login_logs_desc"] ?? "مراقبة محاولات تسجيل الدخول وعناوين IP" ?></div>
            </div>
            <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
                <button class="btn" style="background:var(--card-bg);border:1px solid var(--br);color:var(--text);transition:all 0.3s;" onclick="loadLoginLogs()" onmouseover="this.style.borderColor='var(--red)';this.style.color='var(--red)'" onmouseout="this.style.borderColor='var(--br)';this.style.color='var(--text)'">
                    <i data-lucide="refresh-cw"></i><?= $t["refresh_list"] ?? "تحديث القائمة" ?></button>
                <button class="btn" style="background:rgba(0,208,132,0.1);color:#00D084;border:1px solid rgba(0,208,132,0.2);transition:all 0.3s;" onclick="exportLoginLogs()" onmouseover="this.style.background='#00D084';this.style.color='#fff'" onmouseout="this.style.background='rgba(0,208,132,0.1)';this.style.color='#00D084'">
                    <i data-lucide="download"></i><?= $t["export_log_csv"] ?? "تصدير السجل (CSV)" ?></button>
                <button class="btn" style="background:rgba(229,9,20,0.1);color:var(--red);border:1px solid rgba(229,9,20,0.2);transition:all 0.3s;" onclick="clearLoginLogs()" onmouseover="this.style.background='var(--red)';this.style.color='#fff'" onmouseout="this.style.background='rgba(229,9,20,0.1)';this.style.color='var(--red)'">
                    <i data-lucide="trash-2"></i><?= $t["clear_log_all"] ?? "تفريغ السجل بالكامل" ?></button>
            </div>
        </div>
    </div>
    <div class="tc">
        <div class="table-responsive">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th><?= $t["col_ip"] ?? "الآي بي (IP)" ?></th>
                        <th><?= $t["col_username"] ?? "اسم المستخدم" ?></th>
                        <th><?= $t["col_status"] ?? "الحالة" ?></th>
                        <th><?= $t["col_time"] ?? "الوقت" ?></th>
                    </tr>
                </thead>
                <tbody id="llTbody">
                </tbody>
            </table>
        </div>
    </div>
</section>
