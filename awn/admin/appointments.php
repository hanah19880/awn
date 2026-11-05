<?php
session_start();
require_once "../db.php";

// حماية الصفحة
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$adminName = $_SESSION['full_name'] ?? 'Admin';

// تحديث حالة الموعد
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {

    $appointmentId = (int) $_POST['appointment_id'];
    $newStatus = $_POST['appointment_status'];

    $allowedStatuses = ['scheduled', 'completed', 'cancelled'];

    if (in_array($newStatus, $allowedStatuses, true)) {

        $stmt = $conn->prepare("
            UPDATE appointments
            SET appointment_status = ?
            WHERE appointment_id = ?
        ");

        $stmt->bind_param("si", $newStatus, $appointmentId);
        $stmt->execute();
        $stmt->close();
    }

    header("Location: appointments.php");
    exit();
}

// البحث والتصفية
$search = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? '';

$where = [];
$params = [];
$types = '';

if ($search !== '') {
    $where[] = "(u.full_name LIKE ? OR CAST(a.appointment_id AS CHAR) LIKE ? OR CAST(a.request_id AS CHAR) LIKE ?)";

    $searchValue = "%$search%";

    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;

    $types .= "sss";
}

$allowedFilters = ['scheduled', 'completed', 'cancelled'];

if (in_array($statusFilter, $allowedFilters, true)) {
    $where[] = "a.appointment_status = ?";
    $params[] = $statusFilter;
    $types .= "s";
}

$whereSQL = count($where) > 0
    ? "WHERE " . implode(" AND ", $where)
    : "";

// إحصائيات المواعيد
$totalAppointments = $conn->query("
    SELECT COUNT(*) AS total FROM appointments
")->fetch_assoc()['total'];

$scheduledAppointments = $conn->query("
    SELECT COUNT(*) AS total
    FROM appointments
    WHERE appointment_status = 'scheduled'
")->fetch_assoc()['total'];

$completedAppointments = $conn->query("
    SELECT COUNT(*) AS total
    FROM appointments
    WHERE appointment_status = 'completed'
")->fetch_assoc()['total'];

$cancelledAppointments = $conn->query("
    SELECT COUNT(*) AS total
    FROM appointments
    WHERE appointment_status = 'cancelled'
")->fetch_assoc()['total'];

// جلب المواعيد
$sql = "
    SELECT
        a.appointment_id,
        a.request_id,
        a.appointment_date,
        a.appointment_time,
        a.appointment_status,
        u.full_name
    FROM appointments a
    LEFT JOIN service_requests r
        ON a.request_id = r.request_id
    LEFT JOIN users u
        ON r.customer_id = u.user_id
    $whereSQL
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
";

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$appointments = $stmt->get_result();

function appointmentStatusText($status) {
    switch ($status) {
        case 'scheduled':
            return 'مجدول';
        case 'completed':
            return 'مكتمل';
        case 'cancelled':
            return 'ملغي';
        default:
            return $status;
    }
}

function appointmentStatusClass($status) {
    switch ($status) {
        case 'scheduled':
            return 'status-pending';
        case 'completed':
            return 'status-completed';
        case 'cancelled':
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

    <title>إدارة المواعيد | عون</title>

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

            <a href="appointments.php" class="nav-item active">
                <span class="nav-icon">📅</span>
                <span>المواعيد</span>
            </a>

            <a href="reviews.php" class="nav-item">
                <span class="nav-icon">⭐</span>
                <span>التقييمات</span>
            </a>

            <a href="reports.php" class="nav-item">
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
                    إدارة المواعيد
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
                    <span class="welcome-tag">إدارة المواعيد</span>

                    <h1>المواعيد</h1>

                    <p>
                        متابعة مواعيد الخدمات وإدارة حالاتها.
                    </p>
                </div>

                <div class="welcome-decoration">
                    <div class="decoration-circle"></div>
                    <div class="decoration-text">📅</div>
                </div>

            </section>

            <!-- Statistics -->
            <section class="stats-grid">

                <div class="stat-card">
                    <div class="stat-top">
                        <div class="stat-icon purple">📅</div>
                        <span class="stat-label">إجمالي المواعيد</span>
                    </div>

                    <h2><?= $totalAppointments ?></h2>

                    <div class="stat-footer">
                        جميع المواعيد المسجلة
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-top">
                        <div class="stat-icon blue">⏳</div>
                        <span class="stat-label">المواعيد المجدولة</span>
                    </div>

                    <h2><?= $scheduledAppointments ?></h2>

                    <div class="stat-footer">
                        بانتظار تنفيذ الخدمة
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-top">
                        <div class="stat-icon green">✓</div>
                        <span class="stat-label">المواعيد المكتملة</span>
                    </div>

                    <h2><?= $completedAppointments ?></h2>

                    <div class="stat-footer">
                        تم تنفيذها بنجاح
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-top">
                        <div class="stat-icon orange">✕</div>
                        <span class="stat-label">المواعيد الملغاة</span>
                    </div>

                    <h2><?= $cancelledAppointments ?></h2>

                    <div class="stat-footer">
                        المواعيد التي تم إلغاؤها
                    </div>
                </div>

            </section>

            <!-- Appointments Table -->
            <section class="table-section">

                <div class="section-heading">
                    <div>
                        <h2>جميع المواعيد</h2>
                        <p>عرض ومتابعة مواعيد طلبات الخدمات.</p>
                    </div>
                </div>

                <div class="table-card">

                    <div class="table-heading">

                        <form method="GET" style="display:flex; gap:10px; flex-wrap:wrap; width:100%;">

                            <input
                                type="text"
                                name="search"
                                placeholder="ابحث باسم العميل أو رقم الموعد أو الطلب..."
                                value="<?= htmlspecialchars($search) ?>"
                                style="padding:10px 14px; border:1px solid #ddd; border-radius:8px; font-family:inherit; flex:1; min-width:200px;"
                            >

                            <select
                                name="status"
                                style="padding:10px 14px; border:1px solid #ddd; border-radius:8px; font-family:inherit;"
                            >
                                <option value="">جميع الحالات</option>

                                <option value="scheduled" <?= $statusFilter === 'scheduled' ? 'selected' : '' ?>>
                                    مجدول
                                </option>

                                <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>
                                    مكتمل
                                </option>

                                <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>
                                    ملغي
                                </option>
                            </select>

                            <button type="submit" class="nav-item" style="border:none; cursor:pointer; font-family:inherit;">
                                بحث
                            </button>

                            <a href="appointments.php" class="nav-item">
                                إعادة تعيين
                            </a>

                        </form>

                    </div>

                    <div class="table-responsive">

                        <table>

                            <thead>
                                <tr>
                                    <th>رقم الموعد</th>
                                    <th>رقم الطلب</th>
                                    <th>العميل</th>
                                    <th>تاريخ الموعد</th>
                                    <th>وقت الموعد</th>
                                    <th>الحالة</th>
                                    <th>تحديث الحالة</th>
                                </tr>
                            </thead>

                            <tbody>

                            <?php if ($appointments->num_rows > 0): ?>

                                <?php while ($row = $appointments->fetch_assoc()): ?>

                                    <tr>

                                        <td>#<?= $row['appointment_id'] ?></td>

                                        <td>#<?= $row['request_id'] ?></td>

                                        <td>
                                            <?= htmlspecialchars($row['full_name'] ?? 'غير معروف') ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars($row['appointment_date']) ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars(substr($row['appointment_time'], 0, 5)) ?>
                                        </td>

                                        <td>
                                            <span class="status-badge <?= appointmentStatusClass($row['appointment_status']) ?>">
                                                <?= appointmentStatusText($row['appointment_status']) ?>
                                            </span>
                                        </td>

                                        <td>

                                            <form method="POST" style="display:flex; gap:6px; align-items:center;">

                                                <input
                                                    type="hidden"
                                                    name="appointment_id"
                                                    value="<?= $row['appointment_id'] ?>"
                                                >

                                                <select
                                                    name="appointment_status"
                                                    style="padding:7px; border:1px solid #ddd; border-radius:6px; font-family:inherit;"
                                                >

                                                    <option value="scheduled" <?= $row['appointment_status'] === 'scheduled' ? 'selected' : '' ?>>
                                                        مجدول
                                                    </option>

                                                    <option value="completed" <?= $row['appointment_status'] === 'completed' ? 'selected' : '' ?>>
                                                        مكتمل
                                                    </option>

                                                    <option value="cancelled" <?= $row['appointment_status'] === 'cancelled' ? 'selected' : '' ?>>
                                                        ملغي
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
                                    <td colspan="7">
                                        <div class="empty-state">
                                            <div>📅</div>
                                            <h3>لا توجد مواعيد</h3>
                                            <p>لم يتم العثور على مواعيد تطابق البحث.</p>
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