<section id="change-password" class="sec">
  <div class="shdr"><h1 class="stitle">كلمة <span>المرور</span></h1></div>
  <?php if(isset($_SESSION['pw_ok'])): ?><div class="al al-s"><i class="fas fa-check-circle"></i><?php echo htmlspecialchars($_SESSION['pw_ok'], ENT_QUOTES, 'UTF-8');unset($_SESSION['pw_ok']); ?></div><?php endif; ?>
  <?php if(isset($_SESSION['pw_err'])): ?><div class="al al-e"><i class="fas fa-exclamation-circle"></i><?php echo htmlspecialchars($_SESSION['pw_err'], ENT_QUOTES, 'UTF-8');unset($_SESSION['pw_err']); ?></div><?php endif; ?>
  <div class="sw-wrap"><div class="swc"><div class="swc-hd"><div class="swc-title">تغيير كلمة المرور</div></div><div class="swc-body">
    <form method="POST"><?= csrfField() ?><div class="fg"><label class="fl">كلمة المرور الحالية</label><input type="password" name="current_password" class="fi" required placeholder="••••••••"></div><div class="fg"><label class="fl">كلمة المرور الجديدة</label><input type="password" name="new_password" class="fi" required minlength="8" placeholder="8 أحرف على الأقل"></div><div class="fg"><label class="fl">تأكيد كلمة المرور</label><input type="password" name="confirm_password" class="fi" required minlength="8" placeholder="أعد الكتابة"></div><button type="submit" name="change_password" class="btn btn-p" style="width:100%;justify-content:center;padding:12px"><i class="fas fa-save"></i>حفظ</button></form>
    <div class="info-b"><div class="info-b-title"><i class="fas fa-shield-alt"></i> نصائح الأمان</div><p style="font-size:.8rem;color:var(--t3)">• 8 أحرف على الأقل<br>• امزج أحرفاً وأرقاماً ورموزاً</p></div>
  </div></div></div>
</section>

<!-- TOOLS -->
