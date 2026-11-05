<?php
session_start();
require_once "../db.php";

// التأكد من تسجيل دخول العميل
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] != "customer") {
    header("Location: ../login.php");
    exit();
}

$message = "";
$success = false;

// الحصول على رقم الخدمة
$service_id = isset($_GET["service_id"])
    ? (int) $_GET["service_id"]
    : (int) ($_POST["service_id"] ?? 0);

// التأكد من وجود الخدمة
$stmt = $conn->prepare(
    "SELECT service_id, service_name FROM services WHERE service_id = ?"
);

$stmt->bind_param("i", $service_id);
$stmt->execute();

$service = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$service) {
    die("الخدمة غير موجودة.");
}

// معالجة إرسال الطلب
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $problem = trim($_POST["problem_description"] ?? "");
    $address = trim($_POST["address"] ?? "");
    $preferred_date = $_POST["preferred_date"] ?? "";
    $preferred_time = $_POST["preferred_time"] ?? "";

    if (
        empty($problem) ||
        empty($address) ||
        empty($preferred_date) ||
        empty($preferred_time)
    ) {
        $message = "يرجى تعبئة جميع الحقول.";
    } elseif (
        !DateTime::createFromFormat('Y-m-d', $preferred_date) ||
        $preferred_date < date("Y-m-d")
    ) {
        $message = "يرجى اختيار تاريخ صحيح وغير سابق لليوم.";
    } elseif (
        !DateTime::createFromFormat('H:i', $preferred_time)
    ) {
        $message = "يرجى اختيار وقت صحيح.";
    } else {

        $customer_id = $_SESSION["user_id"];

        $sql = "INSERT INTO service_requests
                (customer_id, service_id, problem_description,
                 address, preferred_date, preferred_time)
                VALUES (?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);

        $stmt->bind_param(
            "iissss",
            $customer_id,
            $service_id,
            $problem,
            $address,
            $preferred_date,
            $preferred_time
        );

        if ($stmt->execute()) {
            $success = true;
            $message = "تم إرسال طلبك بنجاح.";
        } else {
            $message = "حدث خطأ أثناء إرسال الطلب.";
        }

        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>طلب خدمة | عَون</title>

    <link rel="stylesheet" href="../css/style.css">

    <style>
        .request-container {
            max-width: 650px;
            margin: 40px auto;
            padding: 30px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.08);
        }

        .request-container h1 {
            color: #7137a8;
            text-align: center;
        }

        .request-container label {
            display: block;
            margin: 15px 0 7px;
            font-weight: bold;
        }

        .request-container input,
        .request-container textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-family: inherit;
            box-sizing: border-box;
        }

        .request-container textarea {
            height: 120px;
            resize: vertical;
        }

        .request-container button {
            width: 100%;
            margin-top: 20px;
            padding: 13px;
            border: none;
            border-radius: 8px;
            background: #7137a8;
            color: white;
            font-size: 16px;
            cursor: pointer;
        }

        .request-container button:hover {
            background: #542580;
        }

        .message {
            text-align: center;
            padding: 12px;
            margin: 15px 0;
            border-radius: 8px;
            background: #f0eafa;
            color: #542580;
        }

        .service-name {
            background: #f8f6fc;
            padding: 12px;
            border-radius: 8px;
            margin: 20px 0;
        }
    </style>
</head>

<body>

<div class="request-container">

    <h1>طلب خدمة منزلية</h1>

    <div class="service-name">
        الخدمة المختارة:
        <strong><?= htmlspecialchars($service["service_name"]) ?></strong>
    </div>

    <?php if ($message): ?>
        <div class="message">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <?php if (!$success): ?>

    <form method="POST" action="request.php">

        <input type="hidden" name="service_id"
               value="<?= (int)$service_id ?>">

        <label>وصف المشكلة:</label>

        <textarea
            name="problem_description"
            placeholder="اشرح المشكلة التي تواجهها..."
            required><?= htmlspecialchars($_POST["problem_description"] ?? "") ?></textarea>

        <label>العنوان:</label>

        <input
            type="text"
            name="address"
            placeholder="أدخل عنوان منزلك بالتفصيل"
            value="<?= htmlspecialchars($_POST["address"] ?? "") ?>"
            required>

        <label>التاريخ المناسب:</label>

        <input
            type="date"
            name="preferred_date"
            min="<?= date("Y-m-d") ?>"
            value="<?= htmlspecialchars($_POST["preferred_date"] ?? "") ?>"
            required>

        <label>الوقت المناسب:</label>

        <input
            type="time"
            name="preferred_time"
            value="<?= htmlspecialchars($_POST["preferred_time"] ?? "") ?>"
            required>

        <button type="submit">إرسال الطلب</button>

    </form>

    <?php else: ?>

        <p style="text-align:center;">
            يمكنك الآن العودة إلى لوحة التحكم.
        </p>

        <a href="dashboard.php">العودة إلى لوحة التحكم</a>

    <?php endif; ?>

</div>

</body>
</html>