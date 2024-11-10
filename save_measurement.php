<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Lấy dữ liệu từ yêu cầu POST
    $input = json_decode(file_get_contents('php://input'), true);

    // Kiểm tra dữ liệu
    if (!isset($input['patient_id']) || !isset($input['heart_rate']) || !isset($input['spo2'])) {
        echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ.']);
        exit();
    }

    $patient_id = intval($input['patient_id']);
    $heart_rate = intval($input['heart_rate']);
    $spo2 = intval($input['spo2']);

    // Kết nối tới cơ sở dữ liệu
    $conn = new mysqli("localhost:3307", "root", "", "health_monitoring");

    // Kiểm tra kết nối
    if ($conn->connect_error) {
        echo json_encode(['success' => false, 'message' => 'Kết nối cơ sở dữ liệu thất bại.']);
        exit();
    }

    // Lưu kết quả đo vào bảng measurements
    $stmt = $conn->prepare("INSERT INTO measurements (patient_id, heart_rate, spo2) VALUES (?, ?, ?)");
    $stmt->bind_param("iii", $patient_id, $heart_rate, $spo2);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Kết quả đo đã được lưu thành công.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Lưu kết quả đo thất bại.']);
    }

    // Đóng kết nối
    $stmt->close();
    $conn->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Yêu cầu không hợp lệ.']);
}
?>
