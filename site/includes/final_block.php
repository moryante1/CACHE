<?php if ($gs_disable_download): ?>
<style>
/* منع تحميل الفيديوهات: إخفاء أي زر تنزيل + منع قائمة الفيديو */
.download-btn,[data-action="download"],.video-download,.dl-btn,#tdmDownloadBtn{display:none !important;}
video{pointer-events:auto;}
</style>
<script>
document.addEventListener('DOMContentLoaded',function(){
  document.querySelectorAll('video').forEach(function(v){
    v.setAttribute('controlsList','nodownload noremoteplayback');
    v.setAttribute('disablePictureInPicture','');
    v.addEventListener('contextmenu',function(e){e.preventDefault();});
  });
});
</script>
<?php endif; ?>

<?php /* كود مخصص يُحقن قبل نهاية body (شات/ودجت/سكربت) — من الإعدادات العامة */ ?>
<?php if (!empty($gs_custom_body_code)): ?>
<?php echo $gs_custom_body_code; ?>
<?php endif; ?>

