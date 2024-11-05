<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $patient_id = $_POST['patient_id'];
    $heart_rate = $_POST['heart_rate'];
    $spo2 = $_POST['spo2'];

    // Kết nối tới cơ sở dữ liệu
    $conn = new mysqli("localhost:3307", "root", "", "health_monitoring");

    // Kiểm tra kết nối
    if ($conn->connect_error) {
        die("Kết nối thất bại: " . $conn->connect_error);
    }

    // Lưu dữ liệu đo vào cơ sở dữ liệu
    $sql = "INSERT INTO patients_data (patient_id, heart_rate, spo2) VALUES ('$patient_id', '$heart_rate', '$spo2')";

    if ($conn->query($sql) === TRUE) {
        echo "Dữ liệu đã được lưu thành công!";
    } else {
        echo "Lỗi: " . $sql . "<br>" . $conn->error;
    }

    $conn->close();
}
