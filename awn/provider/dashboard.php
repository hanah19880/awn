<?php
session_start();

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] != "provider") {
    header("Location: ../login.php");
    exit();
}
?>

<h1>مرحبًا بك في لوحة تحكم مزود الخدمة</h1>
<p>أهلًا <?= htmlspecialchars($_SESSION["full_name"]) ?></p>
<br><br>
<a href="profile.php">استكمال الملف الشخصي وتحديد الخدمات</a>
<br><br>

<a href="../logout.php">تسجيل الخروج</a>