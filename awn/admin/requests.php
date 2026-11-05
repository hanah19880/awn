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

/* تحديث حالة الطلب */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update_status"])) {

    $request_id = intval($_POST["request_id"]);
    $status = $_POST["status"];

    $allowed_statuses = [
        "pending",
        "accepted",
        "in_progress",
        "completed",
        "cancelled"
    ];

    if (in_array($status, $allowed_statuses, true)) {

        $stmt = $conn->prepare("
            UPDATE service_requests
            SET status = ?
            WHERE request_id = ?
        ");

        $stmt->bind_param("si", $status, $request_id);
        $stmt->execute();
        $stmt->close();
    }

    header("Location: requests.php?updated=1");
    exit();
}


/* البحث والتصفية */
$search = trim($_GET["search"] ?? "");
$status_filter = $_GET["status"] ?? "";

$where = [];
$params = [];
$types = "";

if ($search !== "") {

    $where[] = "
        (
            u.full_name LIKE ?
            OR CAST(r.request_id AS CHAR) LIKE ?
        )
    ";

    $value = "%$search%";

    $params[] = $value;
    $params[] = $value;

    $types .= "ss";
}

if (in_array($status_filter, [
    "pending",
    "accepted",
    "in_progress",
    "completed",
    "cancelled"
], true)) {

    $where[] = "r.status = ?";

    $params[] = $status_filter;
    $types .= "s";
}

$where_sql = !empty($where)
    ? "WHERE " . implode(" AND ", $where)
    : "";


/* جلب الطلبات */
$sql = "
    SELECT
        r.request_id,
        r.customer_id,
        r.service_id,
        r.status,
        r.preferred_date,
        r.created_at,
        u.full_name

    FROM service_requests r

    LEFT JOIN users u
    ON r.customer_id = u.user_id

    $where_sql

    ORDER BY r.created_at DESC
";

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();


/* الإحصائيات */
$total_requests = $conn->query("
    SELECT COUNT(*) AS total
    FROM service_requests
")->fetch_assoc()["total"];

$total_pending = $conn->query("
    SELECT COUNT(*) AS total
    FROM service_requests
    WHERE status = 'pending'
")->fetch_assoc()["total"];

$total_progress = $conn->query("
    SELECT COUNT(*) AS total
    FROM service_requests
    WHERE status = 'in_progress'
")->fetch_assoc()["total"];

$total_completed = $conn->query("
    SELECT COUNT(*) AS total
    FROM service_requests
    WHERE status = 'completed'
")->fetch_assoc()["total"];


/* أسماء الحالات */
$status_names = [
    "pending" => "قيد الانتظار",
    "accepted" => "مقبول",
    "in_progress" => "قيد التنفيذ",
    "completed" => "مكتمل",
    "cancelled" => "ملغى"
];

$status_classes = [
    "pending" => "status-pending",
    "accepted" => "status-accepted",
    "in_progress" => "status-in_progress",
    "completed" => "status-completed",
    "cancelled" => "status-cancelled"
];

?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>طلبات الخدمات | عون</title>

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

            <a href="providers.php" class="nav-item">
                <span class="nav-icon">🔧</span>
                <span>مقدمو الخدمات</span>
            </a>

            <a href="requests.php" class="nav-item active">
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
                    <h3>طلبات الخدمات</h3>
                    <p>متابعة وإدارة طلبات الخدمات المنزلية</p>
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
                    تم تحديث حالة الطلب بنجاح.
                </div>

            <?php endif; ?>


            <!-- Welcome Section -->
            <div class="welcome-section">

                <div>

                    <span class="welcome-tag">
                        إدارة الطلبات
                    </span>

                    <h1>طلبات الخدمات</h1>

                    <p>
                        تابع طلبات العملاء للخدمات المنزلية،
                        وراقب مراحل تنفيذها وتحكم في حالة كل طلب
                        من خلال لوحة الإدارة.
                    </p>

                </div>

                <div class="welcome-decoration">

                    <div class="decoration-circle"></div>

                    <div class="decoration-text">📋</div>

                </div>

            </div>


            <!-- Statistics -->
            <div class="stats-grid">


                <div class="stat-card purple">

                    <div class="stat-top">

                        <span class="stat-label">
                            إجمالي الطلبات
                        </span>

                        <div class="stat-icon">📋</div>

                    </div>

                    <h2><?= $total_requests ?></h2>

                    <span class="stat-footer">
                        جميع طلبات الخدمات المسجلة
                    </span>

                </div>


                <div class="stat-card orange">

                    <div class="stat-top">

                        <span class="stat-label">
                            قيد الانتظار
                        </span>

                        <div class="stat-icon">⌛</div>

                    </div>

                    <h2><?= $total_pending ?></h2>

                    <span class="stat-footer">
                        طلبات تنتظر المعالجة
                    </span>

                </div>


                <div class="stat-card blue">

                    <div class="stat-top">

                        <span class="stat-label">
                            قيد التنفيذ
                        </span>

                        <div class="stat-icon">⚙</div>

                    </div>

                    <h2><?= $total_progress ?></h2>

                    <span class="stat-footer">
                        الطلبات الجاري تنفيذها
                    </span>

                </div>


                <div class="stat-card green">

                    <div class="stat-top">

                        <span class="stat-label">
                            الطلبات المكتملة
                        </span>

                        <div class="stat-icon">✓</div>

                    </div>

                    <h2><?= $total_completed ?></h2>

                    <span class="stat-footer">
                        الطلبات التي تم إنجازها
                    </span>

                </div>


            </div>


            <!-- Search and Filter -->
            <div class="table-card">

                <div class="section-heading" style="padding:22px 22px 0;">

                    <div>
                        <h2>البحث والتصفية</h2>
                        <p>ابحث عن طلب أو حدد حالته</p>
                    </div>

                </div>


                <form method="GET"
                      action="requests.php"
                      style="padding:20px 22px; display:flex; gap:13px; flex-wrap:wrap; align-items:end;">


                    <div style="flex:2; min-width:180px;">

                        <label style="display:block; font-size:11px; margin-bottom:8px;">
                            البحث عن طلب
                        </label>

                        <input
                            type="text"
                            name="search"
                            placeholder="اسم العميل أو رقم الطلب"
                            value="<?= htmlspecialchars($search) ?>"
                            style="width:100%; height:43px; padding:0 13px; border:1px solid #eeeef4; border-radius:9px; font-family:inherit; outline:none;"
                        >

                    </div>


                    <div style="flex:1; min-width:160px;">

                        <label style="display:block; font-size:11px; margin-bottom:8px;">
                            حالة الطلب
                        </label>

                        <select name="status"
                                style="width:100%; height:43px; padding:0 10px; border:1px solid #eeeef4; border-radius:9px; font-family:inherit;">

                            <option value="">جميع الحالات</option>

                            <option value="pending"
                                <?= $status_filter === "pending" ? "selected" : "" ?>>
                                قيد الانتظار
                            </option>

                            <option value="accepted"
                                <?= $status_filter === "accepted" ? "selected" : "" ?>>
                                مقبول
                            </option>

                            <option value="in_progress"
                                <?= $status_filter === "in_progress" ? "selected" : "" ?>>
                                قيد التنفيذ
                            </option>

                            <option value="completed"
                                <?= $status_filter === "completed" ? "selected" : "" ?>>
                                مكتمل
                            </option>

                            <option value="cancelled"
                                <?= $status_filter === "cancelled" ? "selected" : "" ?>>
                                ملغى
                            </option>

                        </select>

                    </div>


                    <button type="submit"
                            style="height:43px; padding:0 20px; border:none; border-radius:9px; background:#7055d9; color:white; font-family:inherit; font-size:12px; cursor:pointer;">
                        🔍 بحث
                    </button>


                    <a href="requests.php"
                       style="height:43px; padding:0 17px; display:flex; align-items:center; border-radius:9px; background:#f0edff; color:#7055d9; font-size:12px;">
                        إعادة تعيين
                    </a>

                </form>

            </div>


            <!-- Requests Table -->
            <div class="section-heading table-heading">

                <div>

                    <h2>قائمة طلبات الخدمات</h2>

                    <p>
                        عرض ومتابعة جميع الطلبات المسجلة في المنصة
                    </p>

                </div>

            </div>


            <div class="table-card">

                <div class="table-responsive">

                    <table>

                        <thead>

                            <tr>
                                <th>رقم الطلب</th>
                                <th>العميل</th>
                                <th>رقم الخدمة</th>
                                <th>تاريخ الطلب</th>
                                <th>الموعد المفضل</th>
                                <th>الحالة</th>
                                <th>تحديث الحالة</th>
                            </tr>

                        </thead>

                        <tbody>

                        <?php if ($result->num_rows > 0): ?>

                            <?php while ($request = $result->fetch_assoc()): ?>

                                <tr>

                                    <td>
                                        <strong>
                                            #<?= $request["request_id"] ?>
                                        </strong>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($request["full_name"] ?? "مستخدم غير معروف") ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($request["service_id"]) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($request["created_at"] ?? "—") ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($request["preferred_date"] ?? "—") ?>
                                    </td>

                                    <td>

                                        <span class="status-badge <?= $status_classes[$request["status"]] ?? "" ?>">
                                            <?= $status_names[$request["status"]] ?? "غير معروف" ?>
                                        </span>

                                    </td>

                                    <td>

                                        <form method="POST"
                                              action="requests.php"
                                              style="display:flex; align-items:center; gap:6px;">

                                            <input
                                                type="hidden"
                                                name="request_id"
                                                value="<?= $request["request_id"] ?>"
                                            >

                                            <select
                                                name="status"
                                                style="height:32px; border:1px solid #eeeef4; border-radius:7px; font-family:inherit; font-size:10px;"
                                            >

                                                <?php foreach ($status_names as $value => $label): ?>

                                                    <option value="<?= $value ?>"
                                                        <?= $request["status"] === $value ? "selected" : "" ?>>

                                                        <?= $label ?>

                                                    </option>

                                                <?php endforeach; ?>

                                            </select>

                                            <button
                                                type="submit"
                                                name="update_status"
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

                                <td colspan="7" class="empty-state">
                                    لا توجد طلبات مطابقة لعملية البحث.
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