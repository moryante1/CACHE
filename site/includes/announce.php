<?php /* الشريط الإعلاني العلوي — يُتحكم به من الإعدادات العامة */ ?>
<?php if ($gs_announce_enabled && trim($gs_announce_text) !== ''): ?>
<div id="gsAnnounceBar" style="position:relative;z-index:9998;background:linear-gradient(90deg,var(--accent,#e50914),#9a050d);color:#fff;text-align:center;padding:9px 40px;font-size:.9rem;font-weight:600;box-shadow:0 2px 10px rgba(0,0,0,.4)">
  <?php if (trim($gs_announce_link) !== ''): ?>
    <?php
      /* [أمان] نسمح فقط بـ http/https أو رابط نسبي — نمنع javascript: و data: */
      $__al = trim($gs_announce_link);
      if (!preg_match('#^(https?://|/)#i', $__al)) { $__al = 'https://' . ltrim($__al, '/'); }
    ?>
    <a href="<?php echo htmlspecialchars($__al, ENT_QUOTES); ?>" target="_blank" rel="noopener noreferrer" style="color:#fff;text-decoration:none"><?php echo htmlspecialchars($gs_announce_text); ?></a>
  <?php else: ?>
    <?php echo htmlspecialchars($gs_announce_text); ?>
  <?php endif; ?>
  <button onclick="this.parentElement.style.display='none'" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);background:transparent;border:none;color:#fff;font-size:1.2rem;cursor:pointer;line-height:1" aria-label="إغلاق">&times;</button>
</div>
<?php endif; ?>
