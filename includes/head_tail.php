</style>
</head>
<body>
<script>if(localStorage.getItem('shashety_sidebar')==='collapsed'){document.body.classList.add('sidebar-collapsed');}
if(sessionStorage.getItem('shashety_loaded')){ document.write('<style>#nfx-loader { display: none !important; }</style>'); } else { sessionStorage.setItem('shashety_loaded', '1'); }
</script>
<!-- Netflix-Style Loading Screen -->
<div id="nfx-loader" style="position:fixed;inset:0;background:#0a0a0a;z-index:99999;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:0;transition:opacity .6s ease,visibility .6s ease">
  <div style="font-family:'Tajawal',sans-serif;font-size:2.8rem;font-weight:900;color:#E50914;letter-spacing:.05em;text-shadow:0 0 40px rgba(229,9,20,.6);animation:nfxpulse 1.2s ease-in-out infinite">SHASHITY PRO</div>
  <div style="margin-top:48px;display:flex;gap:7px;align-items:center">
    <span style="width:6px;height:6px;background:#E50914;border-radius:50%;animation:nfxdot 1.4s ease-in-out infinite .0s"></span>
    <span style="width:6px;height:6px;background:#E50914;border-radius:50%;animation:nfxdot 1.4s ease-in-out infinite .2s"></span>
    <span style="width:6px;height:6px;background:#E50914;border-radius:50%;animation:nfxdot 1.4s ease-in-out infinite .4s"></span>
  </div>
