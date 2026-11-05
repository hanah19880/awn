<?php
session_start();
require_once "../db.php";

// التحقق من أن المستخدم أدمن
if (
    !isset($_SESSION["user_id"]) ||
    $_SESSION["role"] !== "admin"
) {
    header("Location: ../login.php");
    exit();
}

// دالة آمنة لعرض البيانات
function e($value) {
    return htmlspecialchars((string)($value ?? ""), ENT_QUOTES, "UTF-8");
}

// دالة لجلب رقم من قاعدة البيانات
function getCount($conn, $sql) {
    $result = $conn->query($sql);

    if ($result && $row = $result->fetch_assoc()) {
        return (int)$row["total"];
    }

    return 0;
}

// الإحصائيات
$totalUsers = getCount($conn,
    "SELECT COUNT(*) AS total FROM users"
);

$totalCustomers = getCount($conn,
    "SELECT COUNT(*) AS total FROM users WHERE role = 'customer'"
);

$totalProviders = getCount($conn,
    "SELECT COUNT(*) AS total FROM users WHERE role = 'provider'"
);

$activeUsers = getCount($conn,
    "SELECT COUNT(*) AS total FROM users WHERE account_status = 'active'"
);

$pendingProviders = getCount($conn,
    "SELECT COUNT(*) AS total FROM provider_profiles WHERE approval_status = 'pending'"
);

$approvedProviders = getCount($conn,
    "SELECT COUNT(*) AS total FROM provider_profiles WHERE approval_status = 'approved'"
);

$totalRequests = getCount($conn,
    "SELECT COUNT(*) AS total FROM service_requests"
);

$pendingRequests = getCount($conn,
    "SELECT COUNT(*) AS total FROM service_requests WHERE status = 'pending'"
);

$totalAppointments = getCount($conn,
    "SELECT COUNT(*) AS total FROM appointments"
);

$totalReviews = getCount($conn,
    "SELECT COUNT(*) AS total FROM reviews"
);

$pendingReports = getCount($conn,
    "SELECT COUNT(*) AS total FROM reports WHERE report_status = 'pending'"
);

// أحدث طلبات الخدمات
$recentRequests = $conn->query("
    SELECT
        sr.request_id,
        sr.problem_description,
        sr.address,
        sr.status,
        sr.created_at,
        u.full_name AS customer_name
    FROM service_requests sr
    LEFT JOIN users u ON sr.customer_id = u.user_id
    ORDER BY sr.created_at DESC
    LIMIT 6
");

// أحدث مقدمي الخدمات قيد المراجعة
$recentProviders = $conn->query("
    SELECT
        pp.provider_id,
        pp.experience_years,
        pp.approval_status,
        u.full_name,
        u.email,
        u.phone
    FROM provider_profiles pp
    INNER JOIN users u ON pp.user_id = u.user_id
    WHERE pp.approval_status = 'pending'
    ORDER BY pp.provider_id DESC
    LIMIT 5
");

// تنسيق حالة الطلب
function requestStatus($status) {
    $statuses = [
        "pending" => "قيد الانتظار",
        "accepted" => "مقبول",
        "in_progress" => "قيد التنفيذ",
        "completed" => "مكتمل",
        "cancelled" => "ملغي"
    ];

    return $statuses[$status] ?? $status;
}

?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>لوحة الإدارة | عَون</title>

    <link rel="stylesheet" href="admin.css">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>

<body>

<div class="admin-layout">

    <!-- القائمة الجانبية -->
    <aside class="sidebar" id="sidebar">

        <div class="brand">
            <div class="brand-icon">ع</div>
            <div>
                <h2>عَــــون</h2>
                <span>لوحة الإدارة</span>
            </div>
        </div>


        <nav class="sidebar-nav">

            <a href="dashboard.php" class="nav-item active">
                <span class="nav-icon">⌂</span>
                <span>الرئيسية</span>
            </a>

            <a href="users.php" class="nav-item">
                <span class="nav-icon">♙</span>
                <span>إدارة المستخدمين</span>
            </a>

            <a href="providers.php" class="nav-item">
                <span class="nav-icon">♧</span>
                <span>مقدمو الخدمات</span>

                <?php if ($pendingProviders > 0): ?>
                    <span class="nav-badge">
                        <?= $pendingProviders ?>
                    </span>
                <?php endif; ?>
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

                <?php if ($pendingReports > 0): ?>
                    <span class="nav-badge">
                        <?= $pendingReports ?>
                    </span>
                <?php endif; ?>
            </a>

        </nav>

        <div class="sidebar-bottom">

            <div class="admin-profile">
                <div class="profile-avatar">A</div>

                <div class="profile-info">
                    <strong><?= e($_SESSION["full_name"]) ?></strong>
                    <span>مدير النظام</span>
                </div>
            </div>

            <a href="../logout.php" class="logout-link">
                <span>↪</span>
                تسجيل الخروج
            </a>

        </div>

    </aside>


    <!-- المحتوى الرئيسي -->
    <main class="main-content">

        <!-- الشريط العلوي -->
        <header class="topbar">

            <div class="topbar-right">

                <button class="menu-toggle" id="menuToggle">☰</button>

                <div>
                    <h3>لوحة التحكم</h3>
                    <p>إدارة ومتابعة منصة عَون</p>
                </div>

            </div>

            <div class="topbar-left">

                <div class="admin-greeting">
                    <span>مرحباً،</span>
                    <strong><?= e($_SESSION["full_name"]) ?></strong>
                </div>

                <div class="top-avatar">A</div>

            </div>

        </header>


        <!-- محتوى الصفحة -->
        <section class="dashboard-content">

            <div class="welcome-section">

                <div>
                    <span class="welcome-tag">نظرة عامة</span>

                    <h1>أهلاً بك في لوحة الإدارة 👋</h1>

                    <p>
                        من هنا يمكنك متابعة نشاط المنصة وإدارة المستخدمين
                        ومقدمي الخدمات وطلبات الخدمات.
                    </p>
                </div>

                <div class="welcome-decoration">
                    <div class="decoration-circle"></div>
                    <div class="decoration-text">عَون</div>
                </div>

            </div>


            <!-- الإحصائيات -->
            <div class="section-heading">
                <div>
                    <h2>إحصائيات المنصة</h2>
                    <p>ملخص شامل لنشاط المنصة</p>
                </div>
            </div>


            <div class="stats-grid">

                <div class="stat-card purple">
                    <div class="stat-top">
                        <div class="stat-icon">♙</div>
                        <span class="stat-label">إجمالي المستخدمين</span>
                    </div>

                    <h2><?= number_format($totalUsers) ?></h2>

                    <div class="stat-footer">
                        <span>جميع الحسابات المسجلة</span>
                    </div>
                </div>


                <div class="stat-card blue">
                    <div class="stat-top">
                        <div class="stat-icon">♧</div>
                        <span class="stat-label">مقدمو الخدمات</span>
                    </div>

                    <h2><?= number_format($totalProviders) ?></h2>

                    <div class="stat-footer">
                        <span>المسجلون في المنصة</span>
                    </div>
                </div>


                <div class="stat-card green">
                    <div class="stat-top">
                        <div class="stat-icon">♙</div>
                        <span class="stat-label">العملاء</span>
                    </div>

                    <h2><?= number_format($totalCustomers) ?></h2>

                    <div class="stat-footer">
                        <span>إجمالي حسابات العملاء</span>
                    </div>
                </div>


                <div class="stat-card orange">
                    <div class="stat-top">
                        <div class="stat-icon">▤</div>
                        <span class="stat-label">طلبات الخدمات</span>
                    </div>

                    <h2><?= number_format($totalRequests) ?></h2>

                    <div class="stat-footer">
                        <span><?= number_format($pendingRequests) ?> طلب قيد الانتظار</span>
                    </div>
                </div>


                <div class="stat-card pink">
                    <div class="stat-top">
                        <div class="stat-icon">◷</div>
                        <span class="stat-label">مقدمو الخدمات بانتظار الاعتماد</span>
                    </div>

                    <h2><?= number_format($pendingProviders) ?></h2>

                    <div class="stat-footer">
                        <span>طلبات التسجيل الجديدة</span>
                    </div>
                </div>


                <div class="stat-card teal">
                    <div class="stat-top">
                        <div class="stat-icon">✓</div>
                        <span class="stat-label">مقدمو الخدمات المعتمدون</span>
                    </div>

                    <h2><?= number_format($approvedProviders) ?></h2>

                    <div class="stat-footer">
                        <span>الحسابات التي تمت الموافقة عليها</span>
                    </div>
                </div>


                <div class="stat-card dark">
                    <div class="stat-top">
                        <div class="stat-icon">▦</div>
                        <span class="stat-label">المواعيد</span>
                    </div>

                    <h2><?= number_format($totalAppointments) ?></h2>

                    <div class="stat-footer">
                        <span>إجمالي المواعيد المسجلة</span>
                    </div>
                </div>


                <div class="stat-card rose">
                    <div class="stat-top">
                        <div class="stat-icon">⚑</div>
                        <span class="stat-label">البلاغات المعلقة</span>
                    </div>

                    <h2><?= number_format($pendingReports) ?></h2>

                    <div class="stat-footer">
                        <span>تحتاج إلى مراجعة الإدارة</span>
                    </div>
                </div>

            </div>


            <!-- أحدث الطلبات -->
            <div class="section-heading table-heading">

                <div>
                    <h2>أحدث طلبات الخدمات</h2>
                    <p>آخر الطلبات التي تم تسجيلها في المنصة</p>
                </div>

                <a href="requests.php" class="view-all">
                    عرض جميع الطلبات ←
                </a>

            </div>


            <div class="table-card">

                <div class="table-responsive">

                    <table>

                        <thead>
                            <tr>
                                <th>رقم الطلب</th>
                                <th>العميل</th>
                                <th>وصف المشكلة</th>
                                <th>العنوان</th>
                                <th>الحالة</th>
                                <th>تاريخ الطلب</th>
                            </tr>
                        </thead>

                        <tbody>

                        <?php if ($recentRequests && $recentRequests->num_rows > 0): ?>

                            <?php while ($request = $recentRequests->fetch_assoc()): ?>

                                <tr>

                                    <td>
                                        <strong>#<?= e($request["request_id"]) ?></strong>
                                    </td>

                                    <td><?= e($request["customer_name"]) ?></td>

                                    <td class="description-cell">
                                        <?= e($request["problem_description"]) ?>
                                    </td>

                                    <td><?= e($request["address"]) ?></td>

                                    <td>
                                        <span class="status-badge status-<?= e($request["status"]) ?>">
                                            <?= e(requestStatus($request["status"])) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <?= e($request["created_at"]) ?>
                                    </td>

                                </tr>

                            <?php endwhile; ?>

                        <?php else: ?>

                            <tr>
                                <td colspan="6" class="empty-state">
                                    لا توجد طلبات مسجلة حتى الآن.
                                </td>
                            </tr>

                        <?php endif; ?>

                        </tbody>

                    </table>

                </div>

            </div>


            <!-- مقدمو الخدمات -->
            <div class="section-heading table-heading">

                <div>
                    <h2>طلبات اعتماد مقدمي الخدمات</h2>
                    <p>مقدمو الخدمات الذين ينتظرون مراجعة الإدارة</p>
                </div>

                <a href="providers.php" class="view-all">
                    مراجعة جميع الطلبات ←
                </a>

            </div>


            <div class="providers-grid">

                <?php if ($recentProviders && $recentProviders->num_rows > 0): ?>

                    <?php while ($provider = $recentProviders->fetch_assoc()): ?>

                        <div class="provider-card">

                            <div class="provider-header">

                                <div class="provider-avatar">
                                    <?= e(mb_substr($provider["full_name"], 0, 1, "UTF-8")) ?>
                                </div>

                                <div class="provider-details">
                                    <h3><?= e($provider["full_name"]) ?></h3>
                                    <span>مقدم خدمة جديد</span>
                                </div>

                                <span class="pending-dot"></span>

                            </div>

                            <div class="provider-info">
                                <p>
                                    <span>البريد الإلكتروني</span>
                                    <?= e($provider["email"]) ?>
                                </p>

                                <p>
                                    <span>رقم الهاتف</span>
                                    <?= e($provider["phone"]) ?>
                                </p>

                                <p>
                                    <span>سنوات الخبرة</span>
                                    <?= e($provider["experience_years"]) ?> سنوات
                                </p>
                            </div>

                            <a href="providers.php" class="review-button">
                                مراجعة طلب التسجيل
                                <span>←</span>
                            </a>

                        </div>

                    <?php endwhile; ?>

                <?php else: ?>

                    <div class="no-providers">
                        <span>✓</span>
                        <h3>لا توجد طلبات اعتماد معلقة</h3>
                        <p>جميع طلبات مقدمي الخدمات تمت مراجعتها.</p>
                    </div>

                <?php endif; ?>

            </div>


            <!-- ملخص إضافي -->
            <div class="bottom-summary">

                <div class="summary-card">
                    <div class="summary-icon">✓</div>
                    <div>
                        <span>الحسابات النشطة</span>
                        <h3><?= number_format($activeUsers) ?></h3>
                    </div>
                </div>

                <div class="summary-card">
                    <div class="summary-icon">☆</div>
                    <div>
                        <span>تقييمات العملاء</span>
                        <h3><?= number_format($totalReviews) ?></h3>
                    </div>
                </div>

            </div>


            <footer class="dashboard-footer">
                <p>© <?= date("Y") ?> منصة عَون - جميع الحقوق محفوظة.</p>
                <span>لوحة إدارة المنصة</span>
            </footer>

        </section>

    </main>

</div>


<script>
const menuToggle = document.getElementById("menuToggle");
const sidebar = document.getElementById("sidebar");

menuToggle.addEventListener("click", function () {
    sidebar.classList.toggle("show");
});

document.addEventListener("click", function(event) {
    if (
        window.innerWidth <= 900 &&
        !sidebar.contains(event.target) &&
        !menuToggle.contains(event.target)
    ) {
        sidebar.classList.remove("show");
    }
});
</script>

</body>
</html>