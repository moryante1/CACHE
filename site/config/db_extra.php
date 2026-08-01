<?php
// index.php lines 387-399
$disabled_category_ids = [];
try {
    $dc = $pdo->query("SELECT id FROM categories WHERE COALESCE(is_active,1) = 0");
    $disabled_category_ids = $dc ? array_map('intval', $dc->fetchAll(PDO::FETCH_COLUMN)) : [];
} catch (PDOException $e) {}

// ══ القنوات المعطّلة (غير نشطة) من الواجهة الأمامية — تُتحكم من admin.php > القنوات ══
$disabled_channel_ids = [];
try {
    $dch = $pdo->query("SELECT id FROM channels WHERE COALESCE(is_active,1) = 0");
    $disabled_channel_ids = $dch ? array_map('intval', $dch->fetchAll(PDO::FETCH_COLUMN)) : [];
} catch (PDOException $e) {}
?>
