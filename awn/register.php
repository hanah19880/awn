<?php
require_once "db.php";

$message = "";
$message_type = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $full_name = trim($_POST["full_name"]);
    $email = trim($_POST["email"]);
    $phone = trim($_POST["phone"]);
    $password = $_POST["password"];
    $confirm_password = $_POST["confirm_password"];
    $role = $_POST["role"];

    if (
        empty($full_name) ||
        empty($email) ||
        empty($phone) ||
        empty($password)
    ) {
        $message = "يرجى تعبئة جميع الحقول.";
        $message_type = "error";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "البريد الإلكتروني غير صحيح.";
        $message_type = "error";

    } elseif (strlen($password) < 8) {
        $message = "كلمة المرور يجب أن تكون 8 أحرف على الأقل.";
        $message_type = "error";

    } elseif ($password !== $confirm_password) {
        $message = "كلمتا المرور غير متطابقتين.";
        $message_type = "error";

    } elseif (!in_array($role, ["customer", "provider"])) {
        $message = "نوع الحساب غير صحيح.";
        $message_type = "error";

    } else {

        $check = $conn->prepare(
            "SELECT user_id FROM users WHERE email = ? OR phone = ?"
        );

        $check->bind_param("ss", $email, $phone);
        $check->execute();

        $result = $check->get_result();

        if ($result->num_rows > 0) {

            $message = "البريد الإلكتروني أو رقم الهاتف مستخدم بالفعل.";
            $message_type = "error";

        } else {

            $hashed_password = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            try {

                $conn->begin_transaction();

                $sql = "INSERT INTO users
                        (full_name, email, phone, password_hash, role)
                        VALUES (?, ?, ?, ?, ?)";

                $stmt = $conn->prepare($sql);

                $stmt->bind_param(
                    "sssss",
                    $full_name,
                    $email,
                    $phone,
                    $hashed_password,
                    $role
                );

                $stmt->execute();

                $user_id = $conn->insert_id;

                if ($role == "provider") {

                    $provider = $conn->prepare(
                        "INSERT INTO provider_profiles (user_id)
                         VALUES (?)"
                    );

                    $provider->bind_param("i", $user_id);
                    $provider->execute();
                }

                $conn->commit();

                header("Location: login.php?registered=1");
                exit();

            } catch (Exception $e) {

                $conn->rollback();

                $message = "حدث خطأ أثناء إنشاء الحساب. حاول مرة أخرى.";
                $message_type = "error";
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

    <title>إنشاء حساب | عَون</title>

    <link rel="stylesheet" href="css/style.css">

    <style>
        .register-section {
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 40px 15px;
            background: linear-gradient(120deg, #f3edff, #edf9ff);
        }

        .register-box {
            width: 100%;
            max-width: 480px;
            background: white;
            padding: 35px;
            border-radius: 18px;
            box-shadow: 0 10px 35px rgba(0,0,0,0.08);
        }

        .register-box h1 {
            text-align: center;
            color: #7138c8;
            margin-bottom: 8px;
        }

        .register-box .description {
            text-align: center;
            color: #888;
            margin-bottom: 25px;
        }

        .form-group {
            margin-bottom: 17px;
        }

        .form-group label {
            display: block;
            margin-bottom: 7px;
            font-weight: bold;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 15px;
            outline: none;
        }

        .form-group input:focus,
        .form-group select:focus {
            border-color: #7138c8;
        }

        .submit-btn {
            width: 100%;
            padding: 13px;
            border: none;
            border-radius: 8px;
            background: #7138c8;
            color: white;
            font-size: 17px;
            cursor: pointer;
        }

        .submit-btn:hover {
            background: #5726a5;
        }

        .form-message {
            padding: 12px;
            margin-bottom: 18px;
            border-radius: 8px;
            text-align: center;
        }

        .error {
            background: #ffe9e9;
            color: #c62828;
        }

        .login-link {
            text-align: center;
            margin-top: 20px;
        }

        .login-link a {
            color: #7138c8;
            font-weight: bold;
        }

        .back-home {
            display: block;
            text-align: center;
            margin-top: 15px;
            color: #777;
        }
    </style>

</head>

<body>

<section class="register-section">

    <div class="register-box">

        <h1>انضم إلى عَون</h1>

        <p class="description">
            أنشئ حسابك وابدأ باستخدام خدمات المنصة.
        </p>

        <?php if (!empty($message)): ?>

            <div class="form-message <?= $message_type ?>">
                <?= htmlspecialchars($message) ?>
            </div>

        <?php endif; ?>

        <form method="POST" action="">

            <div class="form-group">
                <label>الاسم الكامل</label>
                <input type="text" name="full_name" required>
            </div>

            <div class="form-group">
                <label>البريد الإلكتروني</label>
                <input type="email" name="email" required>
            </div>

            <div class="form-group">
                <label>رقم الهاتف</label>
                <input type="tel" name="phone" required>
            </div>

            <div class="form-group">
                <label>كلمة المرور</label>
                <input type="password" name="password"
                       minlength="8" required>
            </div>

            <div class="form-group">
                <label>تأكيد كلمة المرور</label>
                <input type="password" name="confirm_password"
                       minlength="8" required>
            </div>

            <div class="form-group">
                <label>نوع الحساب</label>

                <select name="role" required>
                    <option value="">اختر نوع الحساب</option>
                    <option value="customer">عميل</option>
                    <option value="provider">مقدم خدمة</option>
                </select>
            </div>

            <button type="submit" class="submit-btn">
                إنشاء الحساب
            </button>

        </form>

        <div class="login-link">
            لديك حساب بالفعل؟
            <a href="login.php">تسجيل الدخول</a>
        </div>

        <a href="index.php" class="back-home">
            العودة إلى الصفحة الرئيسية
        </a>

    </div>

</section>

</body>
</html>