<?php
// orig 2717-2767

// ══ Categories Handlers (إدارة الأقسام) ══
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])){
    // CSRF Check
    $postedToken = $_POST['csrf_token'] ?? '';
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $postedToken)) {
        $_SESSION['error'] = 'انتهت صلاحية الجلسة، يرجى إعادة المحاولة.';
        header('Location: admin.php#categories');
        exit;
    }

    try {
        $name = htmlspecialchars(strip_tags($_POST['category_name']));
        $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
        $icon = htmlspecialchars(strip_tags($_POST['category_icon'] ?? 'fas fa-th-large'));
        $desc = htmlspecialchars(strip_tags($_POST['description'] ?? ''));
        
        $slug_new = "cat-".time()."-".rand(100,999);
        $pdo->prepare("INSERT INTO categories (name, parent_id, icon, description, slug) VALUES (?, ?, ?, ?, ?)")->execute([$name, $parent_id, $icon, $desc, $slug_new]);
        $_SESSION['success'] = '✅ تم إضافة القسم بنجاح.'; 
    } catch(PDOException $e) {
        error_log('[shashety] DB error: ' . $e->getMessage());
        $_SESSION['error'] = 'حدث خطأ في قاعدة البيانات، يرجى المحاولة مرة أخرى.';
    }
    header('Location: admin.php#categories'); 
    exit;
}

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_category'])){
    // CSRF Check
    $postedToken = $_POST['csrf_token'] ?? '';
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $postedToken)) {
        $_SESSION['error'] = 'انتهت صلاحية الجلسة، يرجى إعادة المحاولة.';
        header('Location: admin.php#categories');
        exit;
    }

    try {
        $id = (int)$_POST['category_id'];
        $name = htmlspecialchars(strip_tags($_POST['category_name']));
        $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
        $icon = htmlspecialchars(strip_tags($_POST['category_icon'] ?? 'fas fa-th-large'));
        
        $pdo->prepare("UPDATE categories SET name=?, parent_id=?, icon=? WHERE id=?")->execute([$name, $parent_id, $icon, $id]);
        $_SESSION['success'] = '✅ تم تعديل القسم بنجاح.'; 
    } catch(PDOException $e) {
        error_log('[shashety] DB error: ' . $e->getMessage());
        $_SESSION['error'] = 'حدث خطأ في قاعدة البيانات، يرجى المحاولة مرة أخرى.';
    }
    header('Location: admin.php#categories'); 
    exit;
}

if(isset($_POST['delete_category']) && $_SERVER['REQUEST_METHOD'] === 'POST'){
    // CSRF Check
    $postedToken = $_POST['csrf_token'] ?? '';
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $postedToken)) {
        $_SESSION['error'] = 'انتهت صلاحية الجلسة، يرجى إعادة المحاولة.';
        header('Location: admin.php#categories');
        exit;
    }

    try {
        $id = (int)$_POST['delete_category'];
        // حذف القسم
        $pdo->prepare("DELETE FROM categories WHERE id=?")->execute([$id]);
        $_SESSION['success'] = '✅ تم حذف القسم بنجاح.'; 
    } catch(PDOException $e) {
        $_SESSION['error'] = 'لا يمكن الحذف (قد يكون هناك قنوات مرتبطة بهذا القسم).';
    }
    header('Location: admin.php#categories'); 
    exit;
}

// ══ Channels Handlers ══
