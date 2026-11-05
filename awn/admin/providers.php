<?php
session_start();
require_once "../db.php";

/* حماية الصفحة */
if (
    !isset($_SESSION["user_id"]) ||
    $_SESSION["role"] !== "admin"
) {
    header("Location: ../login.php");
    exit();
}

/* تحديث حالة اعتماد مقدم الخدمة */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update_approval"])) {

    $user_id = intval($_POST["user_id"]);
    $approval = $_POST["approval_status"];

    $allowed_statuses = ["pending", "approved", "rejected"];

    if (in_array($approval, $allowed_statuses)) {

        $stmt = $conn->prepare("
            UPDATE provider_profiles
            SET approval_status = ?
            WHERE user_id = ?
        ");

        $stmt->bind_param("si", $approval, $user_id);
        $stmt->execute();
        $stmt->close();
    }

    header("Location: providers.php?updated=1");
    exit();
}


/* البحث والتصفية */
$search = trim($_GET["search"] ?? "");
$status_filter = $_GET["status"] ?? "";

$where = ["u.role = 'provider'"];

$params = [];
$types = "";

if ($search !== "") {

    $where[] = "
        (
            u.full_name LIKE ?
            OR u.email LIKE ?
            OR u.phone LIKE ?
        )
    ";

    $value = "%$search%";

    $params[] = $value;
    $params[] = $value;
    $params[] = $value;

    $types .= "sss";
}

if (in_array($status_filter, ["pending", "approved", "rejected"])) {

    $where[] = "p.approval_status = ?";

    $params[] = $status_filter;
    $types .= "s";
}


/* جلب مقدمي الخدمات */
$sql = "
    SELECT
        u.user_id,
        u.full_name,
        u.email,
        u.phone,
        u.account_status,
        p.approval_status

    FROM users u

    INNER JOIN provider_profiles p
    ON u.user_id = p.user_id

    WHERE " . implode(" AND ", $where) . "

    ORDER BY
        CASE p.approval_status
            WHEN 'pending' THEN 1
            WHEN 'approved' THEN 2
            WHEN 'rejected' THEN 3
        END,
        u.user_id DESC
";

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();

$result = $stmt->get_result();


/* الإحصائيات */
$total_providers = $conn->query("
    SELECT COUNT(*) AS total
    FROM provider_profiles
")->fetch_assoc()["total"];


$total_pending = $conn->query("
    SELECT COUNT(*) AS total
    FROM provider_profiles
    WHERE approval_status = 'pending'
")->fetch_assoc()["total"];


$total_approved = $conn->query("
    SELECT COUNT(*) AS total
    FROM provider_profiles
    WHERE approval_status = 'approved'
")->fetch_assoc()["total"];


$total_rejected = $conn->query("
    SELECT COUNT(*) AS total
    FROM provider_profiles
    WHERE approval_status = 'rejected'
")->fetch_assoc()["total"];


/* أسماء الحالات */
$status_names = [
    "pending" => "قيد المراجعة",
    "approved" => "معتمد",
    "rejected" => "مرفوض"
];

$status_classes = [
    "pending" => "status-pending",
    "approved" => "status-completed",
    "rejected" => "status-cancelled"
];

?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>مقدمو الخدمات | عون</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">

    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="admin.css">

</head>

<body>

<div class="admin-layout">


    <!-- Sidebar -->
    <aside class="sidebar">

        <div class="brand">

            <div class="brand-icon">ع</div>

            <div>
                <h2>عَــــون</h2>
                <span>لوحة الإدارة</span>
            </div>

        </div>


        <nav class="sidebar-nav">

            <a href="dashboard.php" class="nav-item">
                <span class="nav-icon">⌂</span>
                <span>الرئيسية</span>
            </a>

            <a href="users.php" class="nav-item">
                <span class="nav-icon">♙</span>
                <span>إدارة المستخدمين</span>
            </a>

            <a href="providers.php" class="nav-item active">
                <span class="nav-icon">🔧</span>
                <span>مقدمو الخدمات</span>
            </a>

            <a href="requests.php" class="nav-item">
                <span class="nav-icon">▤</span>
                <span>طلبات الخدمات</span>
            </a>

            <a href="appointments.php" class="nav-item">
                <span class="nav-icon">▦</span>
                <span>المواعيد</span>
            </a>

            <a href="reviews.php" class="nav-item">
                <span class="nav-icon">☆</span>
                <span>التقييمات</span>
            </a>

            <a href="reports.php" class="nav-item">
                <span class="nav-icon">⚑</span>
                <span>البلاغات</span>
            </a>

        </nav>


        <div class="sidebar-bottom">

            <div class="admin-profile">

                <div class="profile-avatar">A</div>

                <div class="profile-info">

                    <strong>
                        <?= htmlspecialchars($_SESSION["full_name"]) ?>
                    </strong>

                    <span>مدير النظام</span>

                </div>

            </div>

            <a href="../logout.php" class="logout-link">
                <span>↪</span>
                تسجيل الخروج
            </a>

        </div>

    </aside>



    <!-- Main Content -->
    <main class="main-content">


        <!-- Topbar -->
        <header class="topbar">

            <div class="topbar-right">

                <button class="menu-toggle" onclick="toggleSidebar()">
                    ☰
                </button>

                <div>

                    <h3>مقدمو الخدمات</h3>

                    <p>مراجعة وإدارة مقدمي الخدمات في المنصة</p>

                </div>

            </div>


            <div class="topbar-left">

                <div class="admin-greeting">

                    مرحباً بك

                    <strong>
                        <?= htmlspecialchars($_SESSION["full_name"]) ?>
                    </strong>

                </div>

                <div class="top-avatar">A</div>

            </div>

        </header>



        <!-- Dashboard Content -->
        <section class="dashboard-content">


            <!-- Notification -->
            <?php if (isset($_GET["updated"])): ?>

                <div class="notice success">
                    تم تحديث حالة اعتماد مقدم الخدمة بنجاح.
                </div>

            <?php endif; ?>



            <!-- Welcome Section -->
            <div class="welcome-section">

                <div>

                    <span class="welcome-tag">
                        إدارة مقدمي الخدمات
                    </span>

                    <h1>مقدمو الخدمات</h1>

                    <p>
                        تابع طلبات التسجيل وراجع بيانات مقدمي الخدمات
                        واعتمد الحسابات المناسبة للعمل على منصة عون.
                    </p>

                </div>


                <div class="welcome-decoration">

                    <div class="decoration-circle"></div>

                    <div class="decoration-text">🔧</div>

                </div>

            </div>



            <!-- Statistics -->
            <div class="stats-grid">


                <div class="stat-card purple">

                    <div class="stat-top">

                        <span class="stat-label">
                            إجمالي مقدمي الخدمات
                        </span>

                        <div class="stat-icon">🔧</div>

                    </div>

                    <h2><?= $total_providers ?></h2>

                    <span class="stat-footer">
                        جميع الحسابات المسجلة كمقدمي خدمات
                    </span>

                </div>



                <div class="stat-card orange">

                    <div class="stat-top">

                        <span class="stat-label">
                            قيد المراجعة
                        </span>

                        <div class="stat-icon">⌛</div>

                    </div>

                    <h2><?= $total_pending ?></h2>

                    <span class="stat-footer">
                        حسابات تنتظر موافقة الإدارة
                    </span>

                </div>



                <div class="stat-card green">

                    <div class="stat-top">

                        <span class="stat-label">
                            الحسابات المعتمدة
                        </span>

                        <div class="stat-icon">✓</div>

                    </div>

                    <h2><?= $total_approved ?></h2>

                    <span class="stat-footer">
                        مقدمو الخدمات الذين تمت الموافقة عليهم
                    </span>

                </div>



                <div class="stat-card pink">

                    <div class="stat-top">

                        <span class="stat-label">
                            الحسابات المرفوضة
                        </span>

                        <div class="stat-icon">✕</div>

                    </div>

                    <h2><?= $total_rejected ?></h2>

                    <span class="stat-footer">
                        طلبات التسجيل التي تم رفضها
                    </span>

                </div>


            </div>



            <!-- Search and Filter -->
            <div class="table-card">


                <div class="section-heading" style="padding:22px 22px 0;">

                    <div>

                        <h2>البحث والتصفية</h2>

                        <p>
                            ابحث عن مقدم خدمة أو حدد حالة الاعتماد
                        </p>

                    </div>

                </div>



                <form method="GET"
                      action="providers.php"
                      style="padding:20px 22px; display:flex; gap:13px; flex-wrap:wrap; align-items:end;">


                    <div style="flex:2; min-width:180px;">

                        <label style="display:block; font-size:11px; margin-bottom:8px;">
                            البحث عن مقدم خدمة
                        </label>

                        <input
                            type="text"
                            name="search"
                            placeholder="الاسم أو البريد الإلكتروني أو الهاتف"
                            value="<?= htmlspecialchars($search) ?>"
                            style="width:100%; height:43px; padding:0 13px; border:1px solid #eeeef4; border-radius:9px; font-family:inherit; outline:none;"
                        >

                    </div>



                    <div style="flex:1; min-width:160px;">

                        <label style="display:block; font-size:11px; margin-bottom:8px;">
                            حالة الاعتماد
                        </label>

                        <select name="status"
                                style="width:100%; height:43px; padding:0 10px; border:1px solid #eeeef4; border-radius:9px; font-family:inherit;">

                            <option value="">جميع الحالات</option>

                            <option value="pending"
                                <?= $status_filter === "pending" ? "selected" : "" ?>>
                                قيد المراجعة
                            </option>

                            <option value="approved"
                                <?= $status_filter === "approved" ? "selected" : "" ?>>
                                معتمد
                            </option>

                            <option value="rejected"
                                <?= $status_filter === "rejected" ? "selected" : "" ?>>
                                مرفوض
                            </option>

                        </select>

                    </div>



                    <button type="submit"
                            style="height:43px; padding:0 20px; border:none; border-radius:9px; background:#7055d9; color:white; font-family:inherit; font-size:12px; cursor:pointer;">

                        🔍 بحث

                    </button>



                    <a href="providers.php"
                       style="height:43px; padding:0 17px; display:flex; align-items:center; border-radius:9px; background:#f0edff; color:#7055d9; font-size:12px;">

                        إعادة تعيين

                    </a>


                </form>

            </div>




            <!-- Providers Table -->
            <div class="section-heading table-heading">

                <div>

                    <h2>قائمة مقدمي الخدمات</h2>

                    <p>
                        مراجعة حالة الحسابات والتحكم في اعتمادها
                    </p>

                </div>

            </div>



            <div class="table-card">

                <div class="table-responsive">

                    <table>

                        <thead>

                            <tr>

                                <th>مقدم الخدمة</th>

                                <th>البريد الإلكتروني</th>

                                <th>رقم الهاتف</th>

                                <th>حالة الحساب</th>

                                <th>حالة الاعتماد</th>

                                <th>تحديث الاعتماد</th>

                            </tr>

                        </thead>



                        <tbody>


                        <?php if ($result->num_rows > 0): ?>


                            <?php while ($provider = $result->fetch_assoc()): ?>


                                <tr>


                                    <td>

                                        <strong>
                                            <?= htmlspecialchars($provider["full_name"]) ?>
                                        </strong>

                                    </td>



                                    <td>
                                        <?= htmlspecialchars($provider["email"]) ?>
                                    </td>



                                    <td>
                                        <?= htmlspecialchars($provider["phone"] ?? "—") ?>
                                    </td>



                                    <td>

                                        <?php if ($provider["account_status"] === "active"): ?>

                                            <span class="status-badge status-completed">
                                                نشط
                                            </span>

                                        <?php else: ?>

                                            <span class="status-badge status-cancelled">
                                                غير نشط
                                            </span>

                                        <?php endif; ?>

                                    </td>



                                    <td>

                                        <span class="status-badge <?= $status_classes[$provider["approval_status"]] ?? "" ?>">

                                            <?= $status_names[$provider["approval_status"]] ?? "غير معروف" ?>

                                        </span>

                                    </td>



                                    <td>


                                        <form method="POST"
                                              action="providers.php"
                                              style="display:flex; align-items:center; gap:6px;">

                                            <input
                                                type="hidden"
                                                name="user_id"
                                                value="<?= $provider["user_id"] ?>"
                                            >


                                            <select
                                                name="approval_status"
                                                style="height:32px; border:1px solid #eeeef4; border-radius:7px; font-family:inherit; font-size:10px;"
                                            >

                                                <option value="pending"
                                                    <?= $provider["approval_status"] === "pending" ? "selected" : "" ?>>
                                                    قيد المراجعة
                                                </option>

                                                <option value="approved"
                                                    <?= $provider["approval_status"] === "approved" ? "selected" : "" ?>>
                                                    اعتماد
                                                </option>

                                                <option value="rejected"
                                                    <?= $provider["approval_status"] === "rejected" ? "selected" : "" ?>>
                                                    رفض
                                                </option>

                                            </select>


                                            <button
                                                type="submit"
                                                name="update_approval"
                                                style="height:32px; padding:0 10px; border:none; border-radius:7px; background:#eee9ff; color:#7055d9; font-family:inherit; font-size:10px; cursor:pointer;"
                                            >

                                                حفظ

                                            </button>

                                        </form>


                                    </td>


                                </tr>


                            <?php endwhile; ?>


                        <?php else: ?>


                            <tr>

                                <td colspan="6" class="empty-state">

                                    لا توجد حسابات مطابقة لعملية البحث.

                                </td>

                            </tr>


                        <?php endif; ?>


                        </tbody>

                    </table>

                </div>

            </div>



            <!-- Footer -->
            <footer class="dashboard-footer">

                <span>
                    © 2026 منصة عَــــون - جميع الحقوق محفوظة
                </span>

                <span>
                    لوحة إدارة النظام
                </span>

            </footer>


        </section>

    </main>

</div>



<script>

function toggleSidebar() {

    document.querySelector(".sidebar").classList.toggle("show");

}

</script>


</body>
</html>


<?php

$stmt->close();
$conn->close();

?>