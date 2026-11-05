<?php
session_start();
require_once "../db.php";

// التأكد من تسجيل دخول العميل
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] != "customer") {
    header("Location: ../login.php");
    exit();
}

// جلب التصنيفات والخدمات المرتبطة بها
$sql = "SELECT 
            c.category_id,
            c.category_name,
            c.description AS category_description,
            s.service_id,
            s.service_name,
            s.description AS service_description
        FROM service_categories c
        LEFT JOIN services s 
        ON c.category_id = s.category_id
        ORDER BY c.category_id, s.service_id";

$result = $conn->query($sql);

$categories = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $id = $row["category_id"];

        if (!isset($categories[$id])) {
            $categories[$id] = [
                "name" => $row["category_name"],
                "description" => $row["category_description"],
                "services" => []
            ];
        }

        if ($row["service_id"] !== null) {
            $categories[$id]["services"][] = [
                "id" => $row["service_id"],
                "name" => $row["service_name"],
                "description" => $row["service_description"]
            ];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>الخدمات المتاحة | عَون</title>

    <link rel="stylesheet" href="../css/style.css">

    <style>
        .services-page {
            max-width: 1100px;
            margin: 40px auto;
            padding: 20px;
        }

        .services-heading {
            text-align: center;
            margin-bottom: 35px;
        }

        .category-box {
            background: white;
            padding: 25px;
            margin-bottom: 25px;
            border-radius: 15px;
            box-shadow: 0 3px 12px rgba(0,0,0,0.08);
        }

        .category-box h2 {
            color: #7137a8;
            margin-bottom: 10px;
        }

        .category-description {
            color: #777;
            margin-bottom: 20px;
        }

        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 15px;
        }

        .service-card {
            background: #f8f6fc;
            padding: 20px;
            border-radius: 12px;
            border: 1px solid #eee;
        }

        .service-card h3 {
            color: #333;
            margin-bottom: 10px;
        }

        .service-card p {
            color: #666;
            font-size: 14px;
            margin-bottom: 15px;
        }

        .request-btn {
            display: inline-block;
            background: #7137a8;
            color: white;
            padding: 9px 18px;
            border-radius: 8px;
            text-decoration: none;
        }

        .request-btn:hover {
            background: #542580;
        }

        .no-services {
            color: #999;
        }
    </style>
</head>

<body>

    <main class="services-page">

        <div class="services-heading">
            <h1>الخدمات المتاحة في عَون</h1>
            <p>
                مرحبًا <?= htmlspecialchars($_SESSION["full_name"]) ?>،
                اختر التصنيف والخدمة التي تحتاج إليها.
            </p>
        </div>

        <?php if (empty($categories)): ?>

            <p>لا توجد خدمات متاحة حاليًا.</p>

        <?php else: ?>

            <?php foreach ($categories as $category): ?>

                <section class="category-box">

                    <h2>
                        <?= htmlspecialchars($category["name"]) ?>
                    </h2>

                    <p class="category-description">
                        <?= htmlspecialchars($category["description"]) ?>
                    </p>

                    <?php if (empty($category["services"])): ?>

                        <p class="no-services">
                            لا توجد خدمات ضمن هذا التصنيف حاليًا.
                        </p>

                    <?php else: ?>

                        <div class="services-grid">

                            <?php foreach ($category["services"] as $service): ?>

                                <div class="service-card">

                                    <h3>
                                        <?= htmlspecialchars($service["name"]) ?>
                                    </h3>

                                    <p>
                                        <?= htmlspecialchars($service["description"]) ?>
                                    </p>

                                    <a class="request-btn"
                                       href="request.php?service_id=<?= (int)$service["id"] ?>">
                                        طلب الخدمة
                                    </a>

                                </div>

                            <?php endforeach; ?>

                        </div>

                    <?php endif; ?>

                </section>

            <?php endforeach; ?>

        <?php endif; ?>

        <a href="dashboard.php">العودة إلى لوحة التحكم</a>

    </main>

</body>
</html>