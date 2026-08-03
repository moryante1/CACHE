<?php
// orig 2899-2929
// ══ جلب دور المستخدم الحالي ══
$_admin_role = 'normal';
$_admin_sections = [];
$_admin_user_id = 0;
$_admin_display = $_SESSION['admin_username'] ?? 'مدير';
try {
    $__au_stmt = $pdo->prepare("SELECT id, role, allowed_sections, display_name FROM admin_users WHERE username = ? AND is_active = 1");
    $__au_stmt->execute([$_SESSION['admin_username'] ?? '']);
    $__au_row = $__au_stmt->fetch(PDO::FETCH_ASSOC);
    if ($__au_row) {
        $_admin_role = $__au_row['role'];
        $_admin_user_id = $__au_row['id'];
        $_admin_display = $__au_row['display_name'] ?: ($_SESSION['admin_username'] ?? '');
        $_admin_sections = json_decode($__au_row['allowed_sections'] ?: '[]', true) ?: [];
        // تحديث وقت آخر دخول
        $pdo->prepare("UPDATE admin_users SET last_login = NOW() WHERE id = ?")->execute([$__au_row['id']]);
    }
    $_SESSION['admin_role'] = $_admin_role;
    $_SESSION['admin_sections'] = $_admin_sections;
    $_SESSION['admin_user_id'] = $_admin_user_id;
} catch(PDOException $e) {}

// قائمة كل المستخدمين المسؤولين (لاستعمالها لاحقاً)
$_all_admin_users = [];
try {
    $_all_admin_users = $pdo->query("SELECT id, username, display_name, role, allowed_sections, is_active, created_at, last_login FROM admin_users ORDER BY FIELD(role,'administrator','super','normal','custom'), id")->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {}


$all_folders_list = $pdo->query("SELECT id, name FROM series ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
