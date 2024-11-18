<?php
session_start();

// Kiểm tra xem người dùng đã đăng nhập chưa
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

// Kết nối đến cơ sở dữ liệu
$host = "localhost:3307";
$dbUsername = "root";
$dbPassword = "";
$dbName = "iot";
$conn = new mysqli($host, $dbUsername, $dbPassword, $dbName);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Lấy patient_id từ session
$patientId = $_SESSION['id'];

// Lấy lịch sử đo của bệnh nhân từ cơ sở dữ liệu
$sql = "SELECT * FROM measurements WHERE patient_id = ? ORDER BY measurement_time DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $patientId);
$stmt->execute();
$result = $stmt->get_result();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lịch sử đo của bệnh nhân</title>
    <link rel="stylesheet" href="style.css">
    <script>
        // Hàm để mở popup hiển thị lời khuyên
        function showAdvice(advice) {
            alert("Lời khuyên từ bác sĩ: " + advice);
        }
    </script>
</head>
<body>
    <div class="container">
        <div class="history">
            <h2>Lịch sử đo</h2>
            <table>
                <thead>
                    <tr>
                        <th>Thời gian</th>
                        <th>Nhịp tim (bpm)</th>
                        <th>SpO2 (%)</th>
                        <th>Lời khuyên</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Kiểm tra xem có lịch sử đo không
                    if ($result->num_rows > 0) {
                        while ($row = $result->fetch_assoc()) {
                            echo "<tr>
                                    <td>" . $row['measurement_time'] . "</td>
                                    <td>" . $row['heart_rate'] . " bpm</td>
                                    <td>" . $row['spo2'] . " %</td>
                                    <td><button onclick='showAdvice(\"" . addslashes($row['advice']) . "\")'>Xem lời khuyên</button></td>
                                  </tr>";
                        }
                    } else {
                        echo "<tr><td colspan='4'>Không có lịch sử đo.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
        <div class="footer">
            <a href="dashboard_0.php" class="btn">Quay lại Dashboard</a>
        </div>
    </div>
</body>
</html>
