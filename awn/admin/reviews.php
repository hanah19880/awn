<?php
session_start();
require_once "../db.php";

// حماية الصفحة
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$adminName = $_SESSION['full_name'] ?? 'Admin';

// البحث والتصفية
$search = trim($_GET['search'] ?? '');
$ratingFilter = $_GET['rating'] ?? '';

$where = [];
$params = [];
$types = '';

if ($search !== '') {
    $where[] = "(
        c.full_name LIKE ?
        OR p.full_name LIKE ?
        OR CAST(rv.review_id AS CHAR) LIKE ?
        OR CAST(rv.request_id AS CHAR) LIKE ?
    )";

    $searchValue = "%$search%";

    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;

    $types .= "ssss";
}

if (in_array($ratingFilter, ['1', '2', '3', '4', '5'], true)) {
    $where[] = "rv.rating = ?";
    $params[] = (int)$ratingFilter;
    $types .= "i";
}

$whereSQL = !empty($where)
    ? "WHERE " . implode(" AND ", $where)
    : "";

// إحصائيات التقييمات
$stats = $conn->query("
    SELECT
        COUNT(*) AS total,
        AVG(rating) AS average_rating,
        SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) AS five_stars,
        SUM(CASE WHEN rating <= 2 THEN 1 ELSE 0 END) AS low_ratings
    FROM reviews
")->fetch_assoc();

$totalReviews = $stats['total'];
$averageRating = $stats['average_rating'] !== null
    ? number_format($stats['average_rating'], 1)
    : '0.0';

$fiveStars = $stats['five_stars'];
$lowRatings = $stats['low_ratings'];

// جلب التقييمات
$sql = "
    SELECT
        rv.review_id,
        rv.request_id,
        rv.customer_id,
        rv.provider_id,
        rv.rating,
        rv.comment,
        rv.created_at,
        c.full_name AS customer_name,
        p.full_name AS provider_name
    FROM reviews rv
    LEFT JOIN users c
        ON rv.customer_id = c.user_id
    LEFT JOIN users p
        ON rv.provider_id = p.user_id
    $whereSQL
    ORDER BY rv.created_at DESC
";

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$reviews = $stmt->get_result();

?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>إدارة التقييمات | عون</title>

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

            <a href="reviews.php" class="nav-item active">
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
                    إدارة التقييمات
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

                    <span class="welcome-tag">إدارة التقييمات</span>

                    <h1>تقييمات المستخدمين</h1>

                    <p>
                        متابعة آراء العملاء وتقييماتهم لمقدمي الخدمات.
                    </p>

                </div>

                <div class="welcome-decoration">
                    <div class="decoration-circle"></div>
                    <div class="decoration-text">⭐</div>
                </div>

            </section>

            <!-- Statistics -->
            <section class="stats-grid">

                <div class="stat-card">

                    <div class="stat-top">
                        <div class="stat-icon purple">⭐</div>
                        <span class="stat-label">إجمالي التقييمات</span>
                    </div>

                    <h2><?= $totalReviews ?></h2>

                    <div class="stat-footer">
                        جميع التقييمات المسجلة
                    </div>

                </div>

                <div class="stat-card">

                    <div class="stat-top">
                        <div class="stat-icon blue">★</div>
                        <span class="stat-label">متوسط التقييم</span>
                    </div>

                    <h2><?= $averageRating ?> / 5</h2>

                    <div class="stat-footer">
                        متوسط تقييمات مقدمي الخدمات
                    </div>

                </div>

                <div class="stat-card">

                    <div class="stat-top">
                        <div class="stat-icon green">🌟</div>
                        <span class="stat-label">تقييمات خمس نجوم</span>
                    </div>

                    <h2><?= $fiveStars ?></h2>

                    <div class="stat-footer">
                        التقييمات الأعلى
                    </div>

                </div>

                <div class="stat-card">

                    <div class="stat-top">
                        <div class="stat-icon orange">⚠️</div>
                        <span class="stat-label">التقييمات المنخفضة</span>
                    </div>

                    <h2><?= $lowRatings ?></h2>

                    <div class="stat-footer">
                        تقييمات بنجمتين أو أقل
                    </div>

                </div>

            </section>

            <!-- Reviews Table -->
            <section class="table-section">

                <div class="section-heading">

                    <div>
                        <h2>جميع التقييمات</h2>
                        <p>عرض تقييمات العملاء لمقدمي الخدمات.</p>
                    </div>

                </div>

                <div class="table-card">

                    <!-- Search and Filter -->
                    <div class="table-heading">

                        <form method="GET" style="display:flex; gap:10px; flex-wrap:wrap; width:100%;">

                            <input
                                type="text"
                                name="search"
                                placeholder="ابحث باسم العميل أو مقدم الخدمة أو رقم التقييم..."
                                value="<?= htmlspecialchars($search) ?>"
                                style="padding:10px 14px; border:1px solid #ddd; border-radius:8px; font-family:inherit; flex:1; min-width:200px;"
                            >

                            <select
                                name="rating"
                                style="padding:10px 14px; border:1px solid #ddd; border-radius:8px; font-family:inherit;"
                            >

                                <option value="">جميع التقييمات</option>

                                <?php for ($i = 5; $i >= 1; $i--): ?>

                                    <option
                                        value="<?= $i ?>"
                                        <?= $ratingFilter === (string)$i ? 'selected' : '' ?>
                                    >
                                        <?= $i ?> نجوم
                                    </option>

                                <?php endfor; ?>

                            </select>

                            <button
                                type="submit"
                                class="nav-item"
                                style="border:none; cursor:pointer; font-family:inherit;"
                            >
                                بحث
                            </button>

                            <a href="reviews.php" class="nav-item">
                                إعادة تعيين
                            </a>

                        </form>

                    </div>

                    <!-- Table -->
                    <div class="table-responsive">

                        <table>

                            <thead>

                                <tr>
                                    <th>رقم التقييم</th>
                                    <th>رقم الطلب</th>
                                    <th>العميل</th>
                                    <th>مقدم الخدمة</th>
                                    <th>التقييم</th>
                                    <th>التعليق</th>
                                    <th>تاريخ التقييم</th>
                                </tr>

                            </thead>

                            <tbody>

                            <?php if ($reviews->num_rows > 0): ?>

                                <?php while ($row = $reviews->fetch_assoc()): ?>

                                    <tr>

                                        <td>
                                            #<?= $row['review_id'] ?>
                                        </td>

                                        <td>
                                            #<?= $row['request_id'] ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars($row['customer_name'] ?? 'غير معروف') ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars($row['provider_name'] ?? 'غير معروف') ?>
                                        </td>

                                        <td>

                                            <span style="color:#F5A623; white-space:nowrap;">
                                                <?= str_repeat('★', (int)$row['rating']) ?><?= str_repeat('☆', 5 - (int)$row['rating']) ?>
                                            </span>

                                            <span>
                                                (<?= (int)$row['rating'] ?>/5)
                                            </span>

                                        </td>

                                        <td>
                                            <?= htmlspecialchars($row['comment'] ?: 'لا يوجد تعليق') ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars($row['created_at']) ?>
                                        </td>

                                    </tr>

                                <?php endwhile; ?>

                            <?php else: ?>

                                <tr>

                                    <td colspan="7">

                                        <div class="empty-state">

                                            <div>⭐</div>

                                            <h3>لا توجد تقييمات</h3>

                                            <p>
                                                لم يتم العثور على تقييمات تطابق البحث.
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