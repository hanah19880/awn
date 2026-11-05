<?php
require_once "db.php";

$sql = "SELECT * FROM service_categories";
$result = $conn->query($sql);

$categories = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>عَون | خدمات منزلك بين يديك</title>

    <link rel="stylesheet" href="css/style.css">
</head>

<body>

<header>
    <div class="container navbar">

        <a href="index.php" class="logo">
            عَون<span>.</span>
        </a>

        <nav>
            <a href="#home">الرئيسية</a>
            <a href="#services">الخدمات</a>
            <a href="#about">من نحن</a>
        </nav>

        <div class="nav-buttons">
            <a href="login.php" class="login-btn">تسجيل الدخول</a>
            <a href="register.php" class="register-btn">إنشاء حساب</a>
        </div>

    </div>
</header>

<main>

    <section class="hero" id="home">

        <div class="container hero-content">

            <div class="hero-text">

                <span class="subtitle">
                    خدمات منزلية أسهل وأسرع
                </span>

                <h1>
                    كل ما يحتاجه منزلك،
                    <span>تجده في عَون.</span>
                </h1>

                <p>
                    منصة تجمعك بمقدمي الخدمات المنزلية،
                    لتجد المساعدة المناسبة وتطلب الخدمة
                    بكل سهولة وراحة.
                </p>

                <a href="#services" class="main-btn">
                    اكتشف خدماتنا
                </a>

            </div>

            <div class="hero-image">
                <div class="hero-card">
                    <span>🏠</span>
                    <h3>منزلك في أيدٍ أمينة</h3>
                    <p>نساعدك في الوصول إلى الخدمة المناسبة.</p>
                </div>
            </div>

        </div>

    </section>

    <section class="services" id="services">

        <div class="container">

            <div class="section-heading">
                <span>ماذا تحتاج اليوم؟</span>
                <h2>اكتشف خدماتنا</h2>
                <p>اختر التصنيف المناسب لاحتياجات منزلك.</p>
            </div>

            <div class="services-grid">

                <?php foreach ($categories as $category): ?>

                    <div class="service-card">

                        <div class="service-icon">✦</div>

                        <h3>
                            <?= htmlspecialchars($category['category_name']) ?>
                        </h3>

                        <p>
                            <?= htmlspecialchars($category['description'] ?? '') ?>
                        </p>

                    </div>

                <?php endforeach; ?>

            </div>

        </div>

    </section>

    <section class="about" id="about">

        <div class="container">

            <div class="section-heading">
                <span>عن المنصة</span>
                <h2>كيف يعمل عَون؟</h2>
            </div>

            <div class="steps-grid">

                <div class="step-card">
                    <span>01</span>
                    <h3>اختر الخدمة</h3>
                    <p>حدد نوع الخدمة المنزلية التي تحتاج إليها.</p>
                </div>

                <div class="step-card">
                    <span>02</span>
                    <h3>أرسل طلبك</h3>
                    <p>أدخل وصف المشكلة والموقع والموعد المناسب.</p>
                </div>

                <div class="step-card">
                    <span>03</span>
                    <h3>تابع طلبك</h3>
                    <p>تابع حالة طلبك وتواصل مع مقدم الخدمة.</p>
                </div>

            </div>

        </div>

    </section>

</main>

<footer>
    <div class="container">
        <h2>عَون.</h2>
        <p>عَون، لأن راحة منزلك تهمنا.</p>
        <p>جميع الحقوق محفوظة © 2026</p>
    </div>
</footer>

<script src="js/script.js"></script>

</body>
</html>