</style>
<?php if (!empty($custom_css_db)): ?>
<style id="siteCustomTheme"><?php echo strip_tags($custom_css_db); ?></style>
<?php endif; ?>
<?php /* كود مخصص يُحقن داخل head (تحليلات/بكسل/سكربت) — من الإعدادات العامة */ ?>
<?php if (!empty($gs_custom_head_code)): ?>
<?php echo $gs_custom_head_code; ?>
<?php endif; ?>
</head>
<body>
<!-- INIT LOADER -->
<div id="nxInitLoader"><div class="nx-loader-circle"></div></div>

