<?php
session_start();
require_once "../db.php";

// التحقق من صلاحية المستخدم
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] != "provider") {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION["user_id"];
$message = "";
$success = false;

// جلب بيانات مقدم الخدمة
$stmt = $conn->prepare(
    "SELECT * FROM provider_profiles WHERE user_id = ?"
);
$stmt->bind_param("i", $user_id);
$stmt->execute();

$profile = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$profile) {
    die("لم يتم العثور على ملف مقدم الخدمة.");
}

$provider_id = $profile["provider_id"];

// جلب جميع الخدمات
$services = $conn->query(
    "SELECT service_id, service_name FROM services ORDER BY service_name"
);

// جلب الخدمات المختارة سابقًا
$selected_services = [];

$stmt = $conn->prepare(
    "SELECT service_id FROM provider_services WHERE provider_id = ?"
);
$stmt->bind_param("i", $provider_id);
$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $selected_services[] = $row["service_id"];
}
$stmt->close();

// جلب المناطق السابقة
$areas = [];

$stmt = $conn->prepare(
    "SELECT area_name FROM provider_areas WHERE provider_id = ?"
);
$stmt->bind_param("i", $provider_id);
$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $areas[] = $row["area_name"];
}
$stmt->close();

// معالجة النموذج
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $bio = trim($_POST["bio"] ?? "");
    $experience = (int)($_POST["experience_years"] ?? 0);

    $service_ids = $_POST["service_ids"] ?? [];
    $area_text = trim($_POST["areas"] ?? "");

    // التحقق من البيانات
    if ($experience < 0 || $experience > 70) {
        $message = "يرجى إدخال عدد سنوات خبرة صحيح.";
    } elseif (empty($service_ids)) {
        $message = "يرجى اختيار خدمة واحدة على الأقل.";
    } elseif (empty($area_text)) {
        $message = "يرجى إدخال منطقة عمل واحدة على الأقل.";
    } else {

        // تحويل المناطق إلى قائمة
        $area_list = preg_split('/[,،\n]+/u', $area_text);

        $area_list = array_unique(
            array_filter(array_map('trim', $area_list))
        );

        // التحقق من أن الخدمات المختارة موجودة فعلًا
        $valid_services = [];

        $check = $conn->prepare(
            "SELECT service_id FROM services WHERE service_id = ?"
        );

        foreach ($service_ids as $id) {
            $id = (int)$id;

            $check->bind_param("i", $id);
            $check->execute();

            if ($check->get_result()->num_rows > 0) {
                $valid_services[] = $id;
            }
        }

        $check->close();

        if (empty($valid_services) || empty($area_list)) {
            $message = "يرجى التأكد من اختيار خدمات ومناطق صحيحة.";
        } else {

            try {

                $conn->begin_transaction();

                // تحديث الملف الشخصي
                $stmt = $conn->prepare(
                    "UPDATE provider_profiles
                     SET bio = ?, experience_years = ?
                     WHERE provider_id = ?"
                );

                $stmt->bind_param(
                    "sii",
                    $bio,
                    $experience,
                    $provider_id
                );

                $stmt->execute();
                $stmt->close();

                // حذف الخدمات القديمة وإضافة المختارة
                $stmt = $conn->prepare(
                    "DELETE FROM provider_services WHERE provider_id = ?"
                );

                $stmt->bind_param("i", $provider_id);
                $stmt->execute();
                $stmt->close();

                $stmt = $conn->prepare(
                    "INSERT INTO provider_services
                     (provider_id, service_id) VALUES (?, ?)"
                );

                foreach ($valid_services as $service_id) {
                    $stmt->bind_param(
                        "ii",
                        $provider_id,
                        $service_id
                    );
                    $stmt->execute();
                }

                $stmt->close();

                // حذف المناطق القديمة وإضافة الجديدة
                $stmt = $conn->prepare(
                    "DELETE FROM provider_areas WHERE provider_id = ?"
                );

                $stmt->bind_param("i", $provider_id);
                $stmt->execute();
                $stmt->close();

                $stmt = $conn->prepare(
                    "INSERT INTO provider_areas
                     (provider_id, area_name) VALUES (?, ?)"
                );

                foreach ($area_list as $area) {
                    $stmt->bind_param(
                        "is",
                        $provider_id,
                        $area
                    );
                    $stmt->execute();
                }

                $stmt->close();

                $conn->commit();

                $message = "تم حفظ بيانات ملفك الشخصي بنجاح.";
                $success = true;

                $profile["bio"] = $bio;
                $profile["experience_years"] = $experience;
                $selected_services = $valid_services;
                $areas = $area_list;

            } catch (Exception $e) {

                $conn->rollback();
                $message = "حدث خطأ أثناء حفظ البيانات.";

            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>الملف الشخصي لمقدم الخدمة | عَون</title>

    <link rel="stylesheet" href="../css/style.css">

    <style>
        .profile-container {
            max-width: 700px;
            margin: 40px auto;
            padding: 30px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.08);
        }

        .profile-container h1 {
            color: #7137a8;
            text-align: center;
        }

        .profile-container label {
            display: block;
            margin: 18px 0 8px;
            font-weight: bold;
        }

        .profile-container input,
        .profile-container textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            box-sizing: border-box;
            font-family: inherit;
        }

        .profile-container textarea {
            height: 100px;
        }

        .service-option {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 12px 0;
        }

        .service-option input {
            width: auto;
        }

        .profile-container button {
            width: 100%;
            padding: 13px;
            margin-top: 25px;
            background: #7137a8;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
        }

        .message {
            padding: 12px;
            background: #f0eafa;
            color: #542580;
            border-radius: 8px;
            text-align: center;
            margin: 15px 0;
        }
    </style>
</head>

<body>

<div class="profile-container">

    <h1>استكمال الملف الشخصي</h1>

    <p>أهلًا <?= htmlspecialchars($_SESSION["full_name"]) ?></p>

    <?php if ($message): ?>
        <div class="message">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <form method="POST">

        <label>نبذة عنك وخبراتك:</label>

        <textarea name="bio"
            placeholder="اكتب نبذة مختصرة عن خبرتك ومهاراتك..."
        ><?= htmlspecialchars($profile["bio"] ?? "") ?></textarea>

        <label>سنوات الخبرة:</label>

        <input type="number"
            name="experience_years"
            min="0"
            max="70"
            value="<?= (int)$profile["experience_years"] ?>"
            required>

        <label>الخدمات التي تقدمها:</label>

        <?php while ($service = $services->fetch_assoc()): ?>

            <div class="service-option">

                <input type="checkbox"
                    name="service_ids[]"
                    value="<?= (int)$service["service_id"] ?>"
                    <?= in_array($service["service_id"], $selected_services) ? "checked" : "" ?>>

                <span>
                    <?= htmlspecialchars($service["service_name"]) ?>
                </span>

            </div>

        <?php endwhile; ?>

        <label>مناطق العمل:</label>

        <textarea name="areas"
            placeholder="مثال: جنزور، طرابلس، السراج"
            required><?= htmlspecialchars(implode("، ", $areas)) ?></textarea>

        <small>افصل بين المناطق باستخدام الفاصلة.</small>

        <button type="submit">حفظ البيانات</button>

    </form>

    <br>

    <a href="dashboard.php">العودة إلى لوحة التحكم</a>

</div>

</body>
</html>