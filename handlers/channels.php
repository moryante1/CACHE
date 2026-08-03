<?php
// orig 2768-2898

// تم نقل CREATE TABLE و ALTER TABLE إلى ملف update.php لتحسين الأداء
// -------------------------------------------------------------------


// كود: إضافة قناة جديدة
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_channel'])){
    // CSRF Check
    $postedToken = $_POST['csrf_token'] ?? '';
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $postedToken)) {
        $_SESSION['error'] = 'انتهت صلاحية الجلسة، يرجى إعادة المحاولة.';
        header('Location: admin.php#channels');
        exit;
    }

    try {
        $cat_id = (int)$_POST['category_id'];
        $name = htmlspecialchars(strip_tags($_POST['channel_name']));
        $url = $_POST['stream_url'] ?? '';
        $icon = htmlspecialchars(strip_tags($_POST['logo_icon'] ?? 'fas fa-tv'));
        $logo = $_POST['logo_url'] ?? '';
        $backup_url = trim($_POST['backup_url'] ?? '');
        $allowed_quality = ['SD 480','HD 720','Full HD 1080P','4K UHD'];
        $quality = $_POST['quality'] ?? 'HD 720';
        if (!in_array($quality, $allowed_quality, true)) $quality = 'HD 720';
        $is_active = (isset($_POST['is_active']) && $_POST['is_active'] === '1') ? 1 : 0;
        
        $pdo->prepare("INSERT INTO channels (category_id, name, stream_url, logo_icon, logo_url, backup_url, quality, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")->execute([$cat_id, $name, $url, $icon, $logo, $backup_url, $quality, $is_active]);
        $_SESSION['success'] = '✅ تم إضافة القناة بنجاح.'; 
    } catch(PDOException $e) {
        error_log('[shashety] DB error: ' . $e->getMessage());
        $_SESSION['error'] = 'حدث خطأ في قاعدة البيانات، يرجى المحاولة مرة أخرى.';
    }
    header('Location: admin.php#channels'); 
    exit;
}

// كود: تعديل قناة موجودة
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_channel'])){
    // CSRF Check
    $postedToken = $_POST['csrf_token'] ?? '';
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $postedToken)) {
        $_SESSION['error'] = 'انتهت صلاحية الجلسة، يرجى إعادة المحاولة.';
        header('Location: admin.php#channels');
        exit;
    }

    try {
        $id = (int)$_POST['channel_id'];
        $cat_id = (int)$_POST['category_id'];
        $name = htmlspecialchars(strip_tags($_POST['channel_name']));
        $url = $_POST['stream_url'] ?? '';
        $icon = htmlspecialchars(strip_tags($_POST['logo_icon'] ?? 'fas fa-tv'));
        $logo = $_POST['logo_url'] ?? '';
        $backup_url = trim($_POST['backup_url'] ?? '');
        $allowed_quality = ['SD 480','HD 720','Full HD 1080P','4K UHD'];
        $quality = $_POST['quality'] ?? 'HD 720';
        if (!in_array($quality, $allowed_quality, true)) $quality = 'HD 720';
        $is_active = (isset($_POST['is_active']) && $_POST['is_active'] === '1') ? 1 : 0;
        
        $pdo->prepare("UPDATE channels SET category_id=?, name=?, stream_url=?, logo_icon=?, logo_url=?, backup_url=?, quality=?, is_active=? WHERE id=?")->execute([$cat_id, $name, $url, $icon, $logo, $backup_url, $quality, $is_active, $id]);
        $_SESSION['success'] = '✅ تم تعديل القناة بنجاح.'; 
    } catch(PDOException $e) {
        error_log('[shashety] DB error: ' . $e->getMessage());
        $_SESSION['error'] = 'حدث خطأ في قاعدة البيانات، يرجى المحاولة مرة أخرى.';
    }
    header('Location: admin.php#channels'); 
    exit;
}

// كود: حذف القناة
if(isset($_POST['delete_channel']) && $_SERVER['REQUEST_METHOD'] === 'POST'){
    // CSRF Check
    $postedToken = $_POST['csrf_token'] ?? '';
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $postedToken)) {
        $_SESSION['error'] = 'انتهت صلاحية الجلسة، يرجى إعادة المحاولة.';
        header('Location: admin.php#channels');
        exit;
    }

    try {
        $id = (int)$_POST['delete_channel'];
        $pdo->prepare("DELETE FROM channels WHERE id=?")->execute([$id]);
        $_SESSION['success'] = '✅ تم حذف القناة بنجاح.'; 
    } catch(PDOException $e) {
        $_SESSION['error'] = 'حدث خطأ أثناء الحذف.';
    }
    header('Location: admin.php#channels'); 
    exit;
}

// تم نقل تحديث جدول القنوات إلى ملف update.php
$stats=[];
$stats['cats']=$pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
$stats['channels']=$pdo->query("SELECT COUNT(*) FROM channels")->fetchColumn();
$stats['views']=$pdo->query("SELECT COALESCE(SUM(views_count),0) FROM channels")->fetchColumn();
try{$stats['series']=$pdo->query("SELECT COUNT(*) FROM series")->fetchColumn();}catch(PDOException $e){$stats['series']=0;}
try{$stats['users']=$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();}catch(PDOException $e){$stats['users']=1;}

$categories=$pdo->query("SELECT c.id,c.name,c.parent_id,c.icon,c.description,COALESCE(c.display_order,0) as display_order,COALESCE(c.is_active,1) as is_active,COUNT(ch.id) as channel_count FROM categories c LEFT JOIN channels ch ON c.id=ch.category_id GROUP BY c.id,c.name,c.parent_id,c.icon,c.description,c.display_order,c.is_active ORDER BY COALESCE(c.display_order,0),c.id")->fetchAll(PDO::FETCH_ASSOC);
$channels=$pdo->query("SELECT ch.*,c.name as cat_name FROM channels ch LEFT JOIN categories c ON ch.category_id=c.id ORDER BY ch.category_id,ch.display_order,ch.id")->fetchAll(PDO::FETCH_ASSOC);

$os_logged=!empty($_SESSION['os_token']);
$os_user=$_SESSION['os_username']??'';


