<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $conn = new mysqli("localhost:3307", "root", "", "iot");
    if ($conn->connect_error) {
        die("Kết nối thất bại: " . $conn->connect_error);
    }

    $patientId = $_POST['patient_id'];
    $spo2 = $_POST['spo2'];
    $heartRate = $_POST['heart_rate'];
    $sos = isset($_POST['sos']) ? intval($_POST['sos']) : 0; // Nhận giá trị SOS (mặc định là 0)

    $sql = "INSERT INTO measurements (patient_id, spo2, heart_rate, sos, measurement_time) 
            VALUES (?, ?, ?, ?, NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("idii", $patientId, $spo2, $heartRate, $sos);

    if ($stmt->execute()) {
        echo "Dữ liệu đã được lưu thành công.";
    } else {
        echo "Lỗi: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();
}
?>
