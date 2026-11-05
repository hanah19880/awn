<?php
session_start();
require_once "db.php";

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $email = trim($_POST["email"]);
    $password = $_POST["password"];

    $stmt = $conn->prepare(
        "SELECT user_id, full_name, email, password_hash, role, account_status
         FROM users WHERE email = ?"
    );

    $stmt->bind_param("s", $email);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows == 1) {

        $user = $result->fetch_assoc();

        if (password_verify($password, $user["password_hash"])) {

            if ($user["account_status"] != "active") {

                $message = "حسابك غير نشط حاليًا.";

            } else {

                session_regenerate_id(true);

                $_SESSION["user_id"] = $user["user_id"];
                $_SESSION["full_name"] = $user["full_name"];
                $_SESSION["role"] = $user["role"];

                if ($user["role"] == "customer") {
                    header("Location: customer/dashboard.php");
                } elseif ($user["role"] == "provider") {
                    header("Location: provider/dashboard.php");
                } elseif ($user["role"] == "admin") {
                    header("Location: admin/dashboard.php");
                }

                exit();
            }

        } else {
            $message = "البريد الإلكتروني أو كلمة المرور غير صحيحة.";
        }

    } else {
        $message = "البريد الإلكتروني أو كلمة المرور غير صحيحة.";
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الدخول | عَون</title>
    <link rel="stylesheet" href="css/style.css">

    <style>
        .login-section {
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 30px 15px;
            background: linear-gradient(120deg, #f3edff, #edf9ff);
        }

        .login-box {
            width: 100%;
            max-width: 430px;
            background: white;
            padding: 35px;
            border-radius: 18px;
            box-shadow: 0 10px 35px rgba(0,0,0,0.08);
        }

        .login-box h1 {
            text-align: center;
            color: #7138c8;
            margin-bottom: 10px;
        }

        .login-box p {
            text-align: center;
            color: #888;
            margin-bottom: 25px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            margin-bottom: 7px;
            font-weight: bold;
        }

        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
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

        .message {
            background: #ffe9e9;
            color: #c62828;
            padding: 12px;
            text-align: center;
            border-radius: 8px;
            margin-bottom: 18px;
        }

        .register-link {
            text-align: center;
            margin-top: 20px;
        }

        .register-link a {
            color: #7138c8;
            font-weight: bold;
        }
    </style>
</head>

<body>

<section class="login-section">

    <div class="login-box">

        <h1>مرحبًا بعودتك</h1>
        <p>سجّل الدخول إلى حسابك في عَون.</p>

        <?php if (isset($_GET["registered"])): ?>
            <div style="background:#e6f7ed;color:#16834b;padding:12px;text-align:center;margin-bottom:18px;border-radius:8px;">
                تم إنشاء حسابك بنجاح. يمكنك تسجيل الدخول الآن.
            </div>
        <?php endif; ?>

        <?php if (!empty($message)): ?>
            <div class="message">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <form method="POST">

            <div class="form-group">
                <label>البريد الإلكتروني</label>
                <input type="email" name="email" required>
            </div>

            <div class="form-group">
                <label>كلمة المرور</label>
                <input type="password" name="password" required>
            </div>

            <button type="submit" class="submit-btn">
                تسجيل الدخول
            </button>

        </form>

        <div class="register-link">
            ليس لديك حساب؟
            <a href="register.php">إنشاء حساب جديد</a>
        </div>

    </div>

</section>

</body>
</html>