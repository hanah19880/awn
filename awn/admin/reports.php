<?php
session_start();
require_once "../db.php";

// حماية الصفحة
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$adminName = $_SESSION['full_name'] ?? 'Admin';

// تحديث حالة البلاغ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {

    $reportId = (int) $_POST['report_id'];
    $newStatus = $_POST['report_status'];

    $allowedStatuses = ['pending', 'reviewed', 'resolved', 'rejected'];

    if (in_array($newStatus, $allowedStatuses, true)) {

        $stmtUpdate = $conn->prepare("
            UPDATE reports
            SET report_status = ?
            WHERE report_id = ?
        ");

        $stmtUpdate->bind_param("si", $newStatus, $reportId);
        $stmtUpdate->execute();
        $stmtUpdate->close();
    }

    header("Location: reports.php");
    exit();
}

// البحث والتصفية
$search = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? '';

$where = [];
$params = [];
$types = '';

if ($search !== '') {

    $where[] = "(
        reporter.full_name LIKE ?
        OR reported.full_name LIKE ?
        OR CAST(r.report_id AS CHAR) LIKE ?
        OR CAST(r.request_id AS CHAR) LIKE ?
        OR r.report_reason LIKE ?
    )";

    $searchValue = "%$search%";

    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;

    $types .= "sssss";
}

$allowedFilters = ['pending', 'reviewed', 'resolved', 'rejected'];

if (in_array($statusFilter, $allowedFilters, true)) {

    $where[] = "r.report_status = ?";

    $params[] = $statusFilter;
    $types .= "s";
}

$whereSQL = !empty($where)
    ? "WHERE " . implode(" AND ", $where)
    : "";

// إحصائيات البلاغات
$stats = $conn->query("
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN report_status = 'pending' THEN 1 ELSE 0 END) AS pending,
        SUM(CASE WHEN report_status = 'reviewed' THEN 1 ELSE 0 END) AS reviewed,
        SUM(CASE WHEN report_status = 'resolved' THEN 1 ELSE 0 END) AS resolved,
        SUM(CASE WHEN report_status = 'rejected' THEN 1 ELSE 0 END) AS rejected
    FROM reports
")->fetch_assoc();

$totalReports = $stats['total'];
$pendingReports = $stats['pending'];
$reviewedReports = $stats['reviewed'];
$resolvedReports = $stats['resolved'];
$rejectedReports = $stats['rejected'];

// جلب البلاغات
$sql = "
    SELECT
        r.report_id,
        r.reporter_id,
        r.reported_user_id,
        r.request_id,
        r.report_reason,
        r.report_status,
        r.created_at,

        reporter.full_name AS reporter_name,
        reported.full_name AS reported_name

    FROM reports r

    LEFT JOIN users reporter
        ON r.reporter_id = reporter.user_id

    LEFT JOIN users reported
        ON r.reported_user_id = reported.user_id

    $whereSQL

    ORDER BY r.created_at DESC
";

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$reports = $stmt->get_result();

// ترجمة حالة البلاغ
function reportStatusText($status) {

    switch ($status) {

        case 'pending':
            return 'قيد الانتظار';

        case 'reviewed':
            return 'تمت المراجعة';

        case 'resolved':
            return 'تم الحل';

        case 'rejected':
            return 'مرفوض';

        default:
            return $status;
    }
}

// كلاس الحالة
function reportStatusClass($status) {

    switch ($status) {

        case 'pending':
            return 'status-pending';

        case 'reviewed':
            return 'status-accepted';

        case 'resolved':
            return 'status-completed';

        case 'rejected':
            return 'status-cancelled';

        default:
            return '';
    }
}

?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>إدارة البلاغات | عون</title>

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
                <span class="nav-icon">📊</span>
                <span>لوحة التحكم</span>
            </a>

            <a href="users.php" class="nav-item">
                <span class="nav-icon">👥</span>
                <span>المستخدمون</span>
            </a>

            <a href="providers.php" class="nav-item">
                <span class="nav-icon">🛠️</span>
                <span>مقدمو الخدمات</span>
            </a>

            <a href="requests.php" class="nav-item">
                <span class="nav-icon">📋</span>
                <span>طلبات الخدمات</span>
            </a>

            <a href="appointments.php" class="nav-item">
                <span class="nav-icon">📅</span>
                <span>المواعيد</span>
            </a>

            <a href="reviews.php" class="nav-item">
                <span class="nav-icon">⭐</span>
                <span>التقييمات</span>
            </a>

            <a href="reports.php" class="nav-item active">
                <span class="nav-icon">🚩</span>
                <span>البلاغات</span>
            </a>

        </nav>

        <div class="sidebar-bottom">

            <div class="admin-profile">

                <div class="profile-avatar">A</div>

                <div class="profile-info">
                    <strong><?= htmlspecialchars($adminName) ?></strong>
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

            <div class="topbar-left">

                <button class="menu-toggle" type="button">☰</button>

                <div class="admin-greeting">
                    إدارة البلاغات
                </div>

            </div>

            <div class="topbar-right">
                <div class="top-avatar">A</div>
            </div>

        </header>

        <!-- Dashboard Content -->
        <div class="dashboard-content">

            <!-- Welcome Section -->
            <section class="welcome-section">

                <div>

                    <span class="welcome-tag">إدارة البلاغات</span>

                    <h1>البلاغات والشكاوى</h1>

                    <p>
                        متابعة بلاغات المستخدمين ومراجعتها وإدارة حالاتها.
                    </p>

                </div>

                <div class="welcome-decoration">
                    <div class="decoration-circle"></div>
                    <div class="decoration-text">🚩</div>
                </div>

            </section>

            <!-- Statistics -->
            <section class="stats-grid">

                <div class="stat-card">

                    <div class="stat-top">
                        <div class="stat-icon purple">🚩</div>
                        <span class="stat-label">إجمالي البلاغات</span>
                    </div>

                    <h2><?= $totalReports ?></h2>

                    <div class="stat-footer">
                        جميع البلاغات المسجلة
                    </div>

                </div>

                <div class="stat-card">

                    <div class="stat-top">
                        <div class="stat-icon orange">⏳</div>
                        <span class="stat-label">قيد الانتظار</span>
                    </div>

                    <h2><?= $pendingReports ?></h2>

                    <div class="stat-footer">
                        تحتاج إلى مراجعة
                    </div>

                </div>

                <div class="stat-card">

                    <div class="stat-top">
                        <div class="stat-icon blue">🔍</div>
                        <span class="stat-label">تمت المراجعة</span>
                    </div>

                    <h2><?= $reviewedReports ?></h2>

                    <div class="stat-footer">
                        البلاغات التي تمت مراجعتها
                    </div>

                </div>

                <div class="stat-card">

                    <div class="stat-top">
                        <div class="stat-icon green">✓</div>
                        <span class="stat-label">تم حلها</span>
                    </div>

                    <h2><?= $resolvedReports ?></h2>

                    <div class="stat-footer">
                        البلاغات التي تم حلها
                    </div>

                </div>

            </section>

            <!-- Reports Table -->
            <section class="table-section">

                <div class="section-heading">

                    <div>
                        <h2>جميع البلاغات</h2>
                        <p>عرض ومتابعة بلاغات المستخدمين.</p>
                    </div>

                </div>

                <div class="table-card">

                    <!-- Search and Filter -->
                    <div class="table-heading">

                        <form method="GET" style="display:flex; gap:10px; flex-wrap:wrap; width:100%;">

                            <input
                                type="text"
                                name="search"
                                placeholder="ابحث باسم المستخدم أو رقم البلاغ أو السبب..."
                                value="<?= htmlspecialchars($search) ?>"
                                style="padding:10px 14px; border:1px solid #ddd; border-radius:8px; font-family:inherit; flex:1; min-width:200px;"
                            >

                            <select
                                name="status"
                                style="padding:10px 14px; border:1px solid #ddd; border-radius:8px; font-family:inherit;"
                            >

                                <option value="">جميع الحالات</option>

                                <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>
                                    قيد الانتظار
                                </option>

                                <option value="reviewed" <?= $statusFilter === 'reviewed' ? 'selected' : '' ?>>
                                    تمت المراجعة
                                </option>

                                <option value="resolved" <?= $statusFilter === 'resolved' ? 'selected' : '' ?>>
                                    تم الحل
                                </option>

                                <option value="rejected" <?= $statusFilter === 'rejected' ? 'selected' : '' ?>>
                                    مرفوض
                                </option>

                            </select>

                            <button
                                type="submit"
                                class="nav-item"
                                style="border:none; cursor:pointer; font-family:inherit;"
                            >
                                بحث
                            </button>

                            <a href="reports.php" class="nav-item">
                                إعادة تعيين
                            </a>

                        </form>

                    </div>

                    <!-- Table -->
                    <div class="table-responsive">

                        <table>

                            <thead>

                                <tr>
                                    <th>رقم البلاغ</th>
                                    <th>مقدم البلاغ</th>
                                    <th>المستخدم المُبلّغ عنه</th>
                                    <th>رقم الطلب</th>
                                    <th>سبب البلاغ</th>
                                    <th>تاريخ البلاغ</th>
                                    <th>الحالة</th>
                                    <th>تحديث الحالة</th>
                                </tr>

                            </thead>

                            <tbody>

                            <?php if ($reports->num_rows > 0): ?>

                                <?php while ($row = $reports->fetch_assoc()): ?>

                                    <tr>

                                        <td>
                                            #<?= $row['report_id'] ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars($row['reporter_name'] ?? 'غير معروف') ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars($row['reported_name'] ?? 'غير محدد') ?>
                                        </td>

                                        <td>
                                            <?= $row['request_id'] !== null
                                                ? '#' . (int)$row['request_id']
                                                : 'غير مرتبط بطلب' ?>
                                        </td>

                                        <td style="max-width:220px; white-space:normal;">
                                            <?= nl2br(htmlspecialchars($row['report_reason'])) ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars($row['created_at']) ?>
                                        </td>

                                        <td>

                                            <span class="status-badge <?= reportStatusClass($row['report_status']) ?>">
                                                <?= reportStatusText($row['report_status']) ?>
                                            </span>

                                        </td>

                                        <td>

                                            <form method="POST" style="display:flex; gap:6px; align-items:center;">

                                                <input
                                                    type="hidden"
                                                    name="report_id"
                                                    value="<?= $row['report_id'] ?>"
                                                >

                                                <select
                                                    name="report_status"
                                                    style="padding:7px; border:1px solid #ddd; border-radius:6px; font-family:inherit;"
                                                >

                                                    <option value="pending" <?= $row['report_status'] === 'pending' ? 'selected' : '' ?>>
                                                        قيد الانتظار
                                                    </option>

                                                    <option value="reviewed" <?= $row['report_status'] === 'reviewed' ? 'selected' : '' ?>>
                                                        تمت المراجعة
                                                    </option>

                                                    <option value="resolved" <?= $row['report_status'] === 'resolved' ? 'selected' : '' ?>>
                                                        تم الحل
                                                    </option>

                                                    <option value="rejected" <?= $row['report_status'] === 'rejected' ? 'selected' : '' ?>>
                                                        مرفوض
                                                    </option>

                                                </select>

                                                <button
                                                    type="submit"
                                                    name="update_status"
                                                    value="1"
                                                    class="nav-item"
                                                    style="border:none; cursor:pointer; font-family:inherit;"
                                                >
                                                    حفظ
                                                </button>

                                            </form>

                                        </td>

                                    </tr>

                                <?php endwhile; ?>

                            <?php else: ?>

                                <tr>

                                    <td colspan="8">

                                        <div class="empty-state">

                                            <div>🚩</div>

                                            <h3>لا توجد بلاغات</h3>

                                            <p>
                                                لم يتم العثور على بلاغات تطابق البحث.
                                            </p>

                                        </div>

                                    </td>

                                </tr>

                            <?php endif; ?>

                            </tbody>

                        </table>

                    </div>

                </div>

            </section>

        </div>

    </main>

</div>

<script>

const menuToggle = document.querySelector('.menu-toggle');
const sidebar = document.querySelector('.sidebar');

if (menuToggle && sidebar) {

    menuToggle.addEventListener('click', function () {
        sidebar.classList.toggle('active');
    });

}

</script>

</body>
</html>

<?php
$stmt->close();
$conn->close();
?>