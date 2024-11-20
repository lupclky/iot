<?php
session_start();
if (!isset($_SESSION['username']) ){
    header("Location: login.php");
    exit();
}

$conn = new mysqli("localhost:3307", "root", "", "iot");
if ($conn->connect_error) {
    die("Kết nối thất bại: " . $conn->connect_error);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $name = $_POST['name'];
    $phone = $_POST['phone'];
    $birthdate = $_POST['birthdate'];
    $password = $_POST['password'];

    $sql = "INSERT INTO users (username, name, phone, birthdate, password, role) VALUES (?, ?, ?, ?, ?, 1)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssss", $username, $name, $phone, $birthdate, $password);

    if ($stmt->execute()) {
        header("Location: dashboard_1.php");
        exit();
    } else {
        echo "Lỗi: " . $stmt->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tạo mới bệnh nhân</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <form method="POST" action="">
        <h1>Tạo mới bệnh nhân</h1>
        <label>Username: <input type="text" name="username" required></label>
        <label>Họ tên: <input type="text" name="name" required></label>
        <label>Số điện thoại: <input type="text" name="phone" required></label>
        <label>Ngày sinh: <input type="date" name="birthdate" required></label>
        <label>Mật khẩu: <input type="password" name="password" required></label>
        <button type="submit">Tạo mới</button>
        <a href="dashboard_1.php" class="back-btn">Quay lại Dashboard</a>
    </form>
</body>
</html>
