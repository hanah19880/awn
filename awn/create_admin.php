<?php
require_once "db.php";

$full_name = "Admin";
$email = "admin@awn.com";
$phone = "0910000000";
$password = password_hash("Admin@12345", PASSWORD_DEFAULT);
$role = "admin";

$stmt = $conn->prepare(
    "SELECT user_id FROM users WHERE email = ?"
);
$stmt->bind_param("s", $email);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows > 0) {
    die("Admin account already exists.");
}

$stmt = $conn->prepare(
    "INSERT INTO users
    (full_name, email, phone, password_hash, role, account_status)
    VALUES (?, ?, ?, ?, ?, 'active')"
);

$stmt->bind_param(
    "sssss",
    $full_name,
    $email,
    $phone,
    $password,
    $role
);

if ($stmt->execute()) {
    echo "Admin account created successfully!";
} else {
    echo "Error: " . $stmt->error;
}
?>