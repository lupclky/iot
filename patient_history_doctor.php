<?php
session_start();
if (!isset($_SESSION['username']) ) {
    header("Location: login.php");
    exit();
}

$conn = new mysqli("localhost:3307", "root", "", "iot");
if ($conn->connect_error) {
    die("Kết nối thất bại: " . $conn->connect_error);
}

// Lấy ID bệnh nhân từ URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("ID bệnh nhân không hợp lệ.");
}
$patientId = intval($_GET['id']);

// Lấy thông tin bệnh nhân
$patientSql = "SELECT name FROM users WHERE id = ?";
$stmt = $conn->prepare($patientSql);
$stmt->bind_param("i", $patientId);
$stmt->execute();
$patientResult = $stmt->get_result();
if ($patientResult->num_rows == 0) {
    die("Bệnh nhân không tồn tại.");
}
$patientName = $patientResult->fetch_assoc()['name'];

// Lấy lịch sử đo
$historySql = "SELECT id, spo2, heart_rate, measurement_time, advice FROM measurements WHERE patient_id = ? ORDER BY measurement_time DESC";
$stmt = $conn->prepare($historySql);
$stmt->bind_param("i", $patientId);
$stmt->execute();
$historyResult = $stmt->get_result();

// Xử lý gửi lời khuyên
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $measurementId = intval($_POST['measurement_id']);
    $advice = $_POST['advice'];

    $adviceSql = "UPDATE measurements SET advice = ? WHERE id = ?";
    $stmt = $conn->prepare($adviceSql);
    $stmt->bind_param("si", $advice, $measurementId);
    if ($stmt->execute()) {
        header("Location: patient_history_doctor.php?id=$patientId");
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
    <title>Lịch sử đo - <?php echo htmlspecialchars($patientName); ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
    <div class ="history">
    <h1>Lịch sử đo của bệnh nhân: <?php echo htmlspecialchars($patientName); ?></h1>
    <a href="dashboard_1.php" class="btn">Quay lại Dashboard</a>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>SpO2</th>
                <th>Nhịp tim</th>
                <th>Thời gian đo</th>
                <th>Lời khuyên</th>
                <th>Hành động</th>
            </tr>
        </thead>
        <tbody>
            <?php
            if ($historyResult->num_rows > 0) {
                while ($row = $historyResult->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($row['id']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['spo2']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['heart_rate']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['measurement_time']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['advice'] ?: "Chưa có lời khuyên") . "</td>";
                    echo "<td>
                        <form method='POST' action=''>
                            <input type='hidden' name='measurement_id' value='" . $row['id'] . "'>
                            <input type='text' name='advice' placeholder='Nhập lời khuyên' required>
                            <button type='submit' class='btn'>Gửi lời khuyên</button>
                        </form>
                    </td>";
                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='6'>Chưa có lịch sử đo nào</td></tr>";
            }
            ?>
        </tbody>
    </table>
        </div>
        </div>
</body>
</html>
