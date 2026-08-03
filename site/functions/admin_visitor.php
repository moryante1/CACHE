<?php
// index.php lines 275-279
/* [إصلاح] كانت هذه السطر تقرأ $_SESSION قبل بدء الجلسة، فتكون فارغة دائماً
   ولا يُتعرَّف على المدير إطلاقاً. نبدأ الجلسة أولاً. */
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
$__is_admin_visitor = !empty($_SESSION['admin_logged_in']) || !empty($_SESSION['admin_username']) || !empty($_SESSION['is_admin']);

