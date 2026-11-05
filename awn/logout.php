<?php
session_start();

// حذف جميع بيانات الجلسة
$_SESSION = array();

// إنهاء الجلسة
session_destroy();

// العودة إلى صفحة تسجيل الدخول
header("Location: login.php");
exit();
?>