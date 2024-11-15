<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['username'];
$id = $_SESSION['id'];  // ID của bệnh nhân (sử dụng để lấy max_id)

// Kết nối đến cơ sở dữ liệu
$conn = new mysqli("localhost:3307", "root", "", "iot");
if ($conn->connect_error) {
    die("Kết nối thất bại: " . $conn->connect_error);
}

// Lấy max_id của bệnh nhân sau khi bấm SOS
$sql = "SELECT MAX(id) AS max_id FROM measurements WHERE patient_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$measurement_id = $row['max_id'] ?? 0;  // Nếu không có kết quả, gán 0 làm mặc định

// Cập nhật tình trạng SOS nếu có
$data = json_decode(file_get_contents('php://input'), true);
$sos_status = $data['sos_status'] ?? 0;  // Giá trị SOS từ client

// Thực hiện update SOS nếu cần
if ($measurement_id && $sos_status !== null) {
    $sql = "INSERT INTO SOS(measurement_id ,SOS_status) VALUES(?,?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $measurement_id, $sos_status);

    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Không thể cập nhật']);
    }

    $stmt->close();
} else {
    echo json_encode(['success' => false, 'error' => 'Dữ liệu không hợp lệ']);
}

$conn->close();
?>


