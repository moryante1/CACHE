<?php
// HTTP polling is the lightweight default. Enable WebSocket only when its
// optional service is configured on the server.
$__wsEnabled = (string) env('ENABLE_WEBSOCKET', '0') === '1';
$__basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
?>
<?php if ($__wsEnabled): ?>
<script src="<?php echo $__basePath; ?>/socket.io/socket.io.js" defer></script>
<script src="<?php echo $__basePath; ?>/assets/js/websocket_client.js" defer></script>
<?php endif; ?>
</body>
</html>
