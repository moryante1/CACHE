
<!-- SVG FILTERS -->
<svg style="display:none" xmlns="http://www.w3.org/2000/svg">
  <filter id="enh-deblock" x="0" y="0" width="100%" height="100%" color-interpolation-filters="sRGB">
    <feGaussianBlur stdDeviation="0.45" result="blurred"/>
    <feComposite in="SourceGraphic" in2="blurred" operator="arithmetic" k1="0" k2="1.6" k3="-0.6" k4="0" result="unsharp"/>
    <feBlend in="unsharp" in2="blurred" mode="normal"/>
  </filter>
  <filter id="enh-hdr" x="0" y="0" width="100%" height="100%" color-interpolation-filters="sRGB">
    <feColorMatrix type="saturate" values="1.1"/>
    <feComponentTransfer>
      <feFuncR type="table" tableValues="0.00 0.05 0.18 0.38 0.60 0.80 0.93 1.00"/>
      <feFuncG type="table" tableValues="0.00 0.05 0.18 0.38 0.60 0.80 0.93 1.00"/>
      <feFuncB type="table" tableValues="0.00 0.04 0.15 0.34 0.57 0.78 0.92 1.00"/>
    </feComponentTransfer>
  </filter>
  <filter id="enh-frame" x="0" y="0" width="100%" height="100%" color-interpolation-filters="sRGB">
    <feConvolveMatrix order="3" preserveAlpha="true" kernelMatrix="-0.1 -0.15 -0.1 -0.15 2.1 -0.15 -0.1 -0.15 -0.1"/>
  </filter>
  <filter id="enh-full" x="0" y="0" width="100%" height="100%" color-interpolation-filters="sRGB">
    <feGaussianBlur stdDeviation="0.35" result="soft"/>
    <feComposite in="SourceGraphic" in2="soft" operator="arithmetic" k1="0" k2="1.5" k3="-0.5" k4="0" result="deblocked"/>
    <feColorMatrix in="deblocked" type="saturate" values="1.08" result="sat"/>
    <feComponentTransfer in="sat" result="hdr">
      <feFuncR type="table" tableValues="0.00 0.05 0.18 0.38 0.60 0.80 0.93 1.00"/>
      <feFuncG type="table" tableValues="0.00 0.05 0.18 0.38 0.60 0.80 0.93 1.00"/>
      <feFuncB type="table" tableValues="0.00 0.04 0.15 0.34 0.57 0.78 0.92 1.00"/>
    </feComponentTransfer>
    <feConvolveMatrix in="hdr" order="3" preserveAlpha="true" kernelMatrix="-0.08 -0.12 -0.08 -0.12 1.8 -0.12 -0.08 -0.12 -0.08"/>
  </filter>
</svg>

<!-- DEVTOOLS OVERLAY -->
<?php if ($gs_block_devtools): ?>
<div class="devtools-overlay" id="devtoolsOverlay">
  <div class="devtools-box">
    <div class="devtools-lock-icon" id="lockIcon"><span class="lcn"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="#ff4d57" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="M12 8v4"/><path d="M12 16h.01"/></svg></span></div>
    <div class="devtools-title">السيرفر محمي</div>
    <div style="width:60px;height:2px;background:linear-gradient(90deg,transparent,var(--red),transparent);margin:12px auto 18px"></div>
    <div class="devtools-badge"><span class="lcn" style="font-size:.75rem"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></span> حماية متقدمة مفعّلة</div>
    <div class="devtools-sub">هذا النظام محمي بالكامل.<br>لا يُسمح بالوصول إلى أدوات المطور.</div>
  </div>
</div>
<?php endif; ?>

