<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Lấy dữ liệu từ form
    $patient_id = $_POST['patient_id'];
    $spo2 = $_POST['spo2'];
    $heart_rate = $_POST['heart_rate'];

    // Kết nối cơ sở dữ liệu
    $conn = new mysqli("localhost:3307", "root", "", "health_monitoring");

    // Kiểm tra kết nối
    if ($conn->connect_error) {
        die("Kết nối thất bại: " . $conn->connect_error);
    }

    // Lưu kết quả đo vào bảng measurements
    $sql = "INSERT INTO measurements (patient_id, spo2, heart_rate, timestamp) VALUES (?, ?, ?, NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("idd", $patient_id, $spo2, $heart_rate);

    if ($stmt->execute()) {
        echo "Kết quả đo đã được lưu thành công.";
    } else {
        echo "Lỗi: " . $stmt->error;
    }

    // Đóng kết nối
    $stmt->close();
    $conn->close();
}
?>
