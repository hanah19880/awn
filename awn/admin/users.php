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

/* تحديث حالة المستخدم */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update_status"])) {

    $user_id = intval($_POST["user_id"]);
    $status = $_POST["account_status"];

    $allowed_statuses = ["active", "inactive", "suspended"];

    if (
        $user_id !== intval($_SESSION["user_id"]) &&
        in_array($status, $allowed_statuses)
    ) {
        $stmt = $conn->prepare("
            UPDATE users
            SET account_status = ?
            WHERE user_id = ?
            AND role != 'admin'
        ");

        $stmt->bind_param("si", $status, $user_id);
        $stmt->execute();
        $stmt->close();
    }

    header("Location: users.php?updated=1");
    exit();
}

/* البحث والتصفية */
$search = trim($_GET["search"] ?? "");
$role_filter = $_GET["role"] ?? "";
$status_filter = $_GET["status"] ?? "";

$where = ["role IN ('customer', 'provider')"];
$params = [];
$types = "";

if ($search !== "") {
    $where[] = "(full_name LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $value = "%$search%";

    $params[] = $value;
    $params[] = $value;
    $params[] = $value;

    $types .= "sss";
}

if (in_array($role_filter, ["customer", "provider"])) {
    $where[] = "role = ?";
    $params[] = $role_filter;
    $types .= "s";
}

if (in_array($status_filter, ["active", "inactive", "suspended"])) {
    $where[] = "account_status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

$sql = "
    SELECT user_id, full_name, email, phone, role, account_status
    FROM users
    WHERE " . implode(" AND ", $where) . "
    ORDER BY user_id DESC
";

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

/* الإحصائيات */
$total_customers = $conn->query("
    SELECT COUNT(*) AS total
    FROM users
    WHERE role = 'customer'
")->fetch_assoc()["total"];

$total_providers = $conn->query("
    SELECT COUNT(*) AS total
    FROM users
    WHERE role = 'provider'
")->fetch_assoc()["total"];

$total_active = $conn->query("
    SELECT COUNT(*) AS total
    FROM users
    WHERE role IN ('customer', 'provider')
    AND account_status = 'active'
")->fetch_assoc()["total"];

$total_suspended = $conn->query("
    SELECT COUNT(*) AS total
    FROM users
    WHERE role IN ('customer', 'provider')
    AND account_status = 'suspended'
")->fetch_assoc()["total"];

?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>إدارة المستخدمين | عون</title>

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

            <a href="users.php" class="nav-item active">
                <span class="nav-icon">♙</span>
                <span>إدارة المستخدمين</span>
            </a>

            <a href="providers.php" class="nav-item">
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
                    <h3>إدارة المستخدمين</h3>
                    <p>إدارة حسابات العملاء ومقدمي الخدمات</p>
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

            <?php if (isset($_GET["updated"])): ?>

                <div class="notice success">
                    تم تحديث حالة الحساب بنجاح.
                </div>

            <?php endif; ?>


            <!-- Welcome Section -->
            <div class="welcome-section">

                <div>

                    <span class="welcome-tag">
                        إدارة النظام
                    </span>

                    <h1>إدارة المستخدمين</h1>

                    <p>
                        يمكنك متابعة حسابات المستخدمين والتحكم في
                        حالة الحسابات وإدارة صلاحيات الوصول إلى المنصة.
                    </p>

                </div>

                <div class="welcome-decoration">

                    <div class="decoration-circle"></div>

                    <div class="decoration-text">♙</div>

                </div>

            </div>


            <!-- Statistics -->
            <div class="stats-grid">

                <div class="stat-card purple">

                    <div class="stat-top">
                        <span class="stat-label">إجمالي العملاء</span>
                        <div class="stat-icon">♙</div>
                    </div>

                    <h2><?= $total_customers ?></h2>

                    <span class="stat-footer">
                        عدد حسابات العملاء المسجلة
                    </span>

                </div>


                <div class="stat-card blue">

                    <div class="stat-top">
                        <span class="stat-label">مقدمو الخدمات</span>
                        <div class="stat-icon">🔧</div>
                    </div>

                    <h2><?= $total_providers ?></h2>

                    <span class="stat-footer">
                        عدد مقدمي الخدمات المسجلين
                    </span>

                </div>


                <div class="stat-card green">

                    <div class="stat-top">
                        <span class="stat-label">الحسابات النشطة</span>
                        <div class="stat-icon">✓</div>
                    </div>

                    <h2><?= $total_active ?></h2>

                    <span class="stat-footer">
                        الحسابات المسموح لها بالدخول
                    </span>

                </div>


                <div class="stat-card orange">

                    <div class="stat-top">
                        <span class="stat-label">الحسابات الموقوفة</span>
                        <div class="stat-icon">!</div>
                    </div>

                    <h2><?= $total_suspended ?></h2>

                    <span class="stat-footer">
                        الحسابات المعلقة حالياً
                    </span>

                </div>

            </div>


            <!-- Search and Filters -->
            <div class="table-card">

                <div class="section-heading" style="padding:22px 22px 0;">

                    <div>
                        <h2>البحث والتصفية</h2>
                        <p>ابحث عن مستخدم أو حدد نوع الحساب وحالته</p>
                    </div>

                </div>

                <form method="GET" action="users.php"
                      style="padding:20px 22px; display:flex; gap:13px; flex-wrap:wrap; align-items:end;">

                    <div style="flex:2; min-width:180px;">

                        <label style="display:block; font-size:11px; margin-bottom:8px;">
                            البحث عن مستخدم
                        </label>

                        <input
                            type="text"
                            name="search"
                            placeholder="الاسم أو البريد الإلكتروني أو الهاتف"
                            value="<?= htmlspecialchars($search) ?>"
                            style="width:100%; height:43px; padding:0 13px; border:1px solid #eeeef4; border-radius:9px; font-family:inherit; outline:none;"
                        >

                    </div>


                    <div style="flex:1; min-width:140px;">

                        <label style="display:block; font-size:11px; margin-bottom:8px;">
                            نوع الحساب
                        </label>

                        <select name="role"
                                style="width:100%; height:43px; padding:0 10px; border:1px solid #eeeef4; border-radius:9px; font-family:inherit;">

                            <option value="">جميع الحسابات</option>

                            <option value="customer"
                                <?= $role_filter === "customer" ? "selected" : "" ?>>
                                عميل
                            </option>

                            <option value="provider"
                                <?= $role_filter === "provider" ? "selected" : "" ?>>
                                مقدم خدمة
                            </option>

                        </select>

                    </div>


                    <div style="flex:1; min-width:140px;">

                        <label style="display:block; font-size:11px; margin-bottom:8px;">
                            حالة الحساب
                        </label>

                        <select name="status"
                                style="width:100%; height:43px; padding:0 10px; border:1px solid #eeeef4; border-radius:9px; font-family:inherit;">

                            <option value="">جميع الحالات</option>

                            <option value="active"
                                <?= $status_filter === "active" ? "selected" : "" ?>>
                                نشط
                            </option>

                            <option value="inactive"
                                <?= $status_filter === "inactive" ? "selected" : "" ?>>
                                غير نشط
                            </option>

                            <option value="suspended"
                                <?= $status_filter === "suspended" ? "selected" : "" ?>>
                                موقوف
                            </option>

                        </select>

                    </div>


                    <button type="submit"
                            style="height:43px; padding:0 20px; border:none; border-radius:9px; background:#7055d9; color:white; font-family:inherit; font-size:12px; cursor:pointer;">
                        🔍 بحث
                    </button>

                    <a href="users.php"
                       style="height:43px; padding:0 17px; display:flex; align-items:center; border-radius:9px; background:#f0edff; color:#7055d9; font-size:12px;">
                        إعادة تعيين
                    </a>

                </form>

            </div>


            <!-- Users Table -->
            <div class="section-heading table-heading">

                <div>
                    <h2>قائمة المستخدمين</h2>
                    <p>عرض وإدارة الحسابات المسجلة في المنصة</p>
                </div>

            </div>


            <div class="table-card">

                <div class="table-responsive">

                    <table>

                        <thead>

                            <tr>
                                <th>المستخدم</th>
                                <th>البريد الإلكتروني</th>
                                <th>رقم الهاتف</th>
                                <th>نوع الحساب</th>
                                <th>الحالة</th>
                                <th>تعديل الحالة</th>
                            </tr>

                        </thead>

                        <tbody>

                        <?php if ($result->num_rows > 0): ?>

                            <?php while ($user = $result->fetch_assoc()): ?>

                                <tr>

                                    <td>
                                        <strong>
                                            <?= htmlspecialchars($user["full_name"]) ?>
                                        </strong>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($user["email"]) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($user["phone"] ?? "—") ?>
                                    </td>

                                    <td>

                                        <?php if ($user["role"] === "customer"): ?>

                                            <span class="status-badge status-accepted">
                                                عميل
                                            </span>

                                        <?php else: ?>

                                            <span class="status-badge status-in_progress">
                                                مقدم خدمة
                                            </span>

                                        <?php endif; ?>

                                    </td>

                                    <td>

                                        <?php
                                        $status_class = [
                                            "active" => "status-completed",
                                            "inactive" => "status-cancelled",
                                            "suspended" => "status-cancelled"
                                        ];

                                        $status_names = [
                                            "active" => "نشط",
                                            "inactive" => "غير نشط",
                                            "suspended" => "موقوف"
                                        ];
                                        ?>

                                        <span class="status-badge <?= $status_class[$user["account_status"]] ?? "" ?>">
                                            <?= $status_names[$user["account_status"]] ?? "غير معروف" ?>
                                        </span>

                                    </td>

                                    <td>

                                        <?php if ($user["user_id"] != $_SESSION["user_id"]): ?>

                                            <form method="POST"
                                                  action="users.php"
                                                  style="display:flex; align-items:center; gap:6px;">

                                                <input type="hidden"
                                                       name="user_id"
                                                       value="<?= $user["user_id"] ?>">

                                                <select name="account_status"
                                                        style="height:32px; border:1px solid #eeeef4; border-radius:7px; font-family:inherit; font-size:10px;">

                                                    <option value="active"
                                                        <?= $user["account_status"] === "active" ? "selected" : "" ?>>
                                                        نشط
                                                    </option>

                                                    <option value="inactive"
                                                        <?= $user["account_status"] === "inactive" ? "selected" : "" ?>>
                                                        غير نشط
                                                    </option>

                                                    <option value="suspended"
                                                        <?= $user["account_status"] === "suspended" ? "selected" : "" ?>>
                                                        إيقاف
                                                    </option>

                                                </select>

                                                <button type="submit"
                                                        name="update_status"
                                                        style="height:32px; padding:0 10px; border:none; border-radius:7px; background:#eee9ff; color:#7055d9; font-family:inherit; font-size:10px; cursor:pointer;">
                                                    حفظ
                                                </button>

                                            </form>

                                        <?php else: ?>

                                            <span style="color:#999; font-size:10px;">
                                                حسابك الحالي
                                            </span>

                                        <?php endif; ?>

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

                <span>© 2026 منصة عَــــون - جميع الحقوق محفوظة</span>

                <span>لوحة إدارة النظام</span>

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