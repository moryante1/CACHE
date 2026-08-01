<script>
  window.addEventListener('load', function(){
    setTimeout(function(){
      var l = document.getElementById('nfx-loader');
      if(l){ l.style.opacity='0'; l.style.visibility='hidden'; setTimeout(function(){ l.remove(); },650); }
    }, 900);
  });
</script>
