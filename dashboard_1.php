<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['username'];
$id = $_SESSION['id'];

$conn = new mysqli("localhost:3307", "root", "", "iot");
if ($conn->connect_error) {
    die("Kết nối thất bại: " . $conn->connect_error);
}

// Lấy danh sách bệnh nhân có role = 0
$sql = "SELECT id, username, name, phone, birthdate FROM users WHERE role = 1";
$result = $conn->query($sql);

// Đếm số SOS = 1
$sosCountSql = "SELECT COUNT(*) AS sos_count FROM measurements WHERE sos = 1";
$sosCountResult = $conn->query($sosCountSql);
$sosCount = $sosCountResult->fetch_assoc()['sos_count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
    <div class="welcome">
        <span>Xin chào, Doctor1</span>
        <div class="user-actions">
            <a href="change_password.php">Đổi mật khẩu</a>
            <a href="logout.php">Đăng xuất</a>
        </div>
    </div>
    <div class="history">
    <h1>Danh sách bệnh nhân</h1>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Username</th>
                <th>Họ tên</th>
                <th>Ngày sinh</th>
                <th>Số điện thoại</th>
            </tr>
        </thead>
        <tbody>
            <?php
              if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    echo "<tr onclick=\"viewPatientHistory(" . $row['id'] . ")\">";
                    echo "<td>" . htmlspecialchars($row['id']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['username']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['name']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['birthdate']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['phone']) . "</td>";
                    echo "<td><button class='btn' onclick='viewPatientHistory(" . $row['id'] . ")'>Xem lịch sử</button></td>";
                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='6'>Không có bệnh nhân nào</td></tr>";
            }
            ?>
        </tbody>
    </table>
        </div>
        <button id="sos-button" onclick="viewSOS()">
            SOS (<?php echo $sosCount; ?>)
        </button>
    <div class="footer">
        <a href="create_patient.php" class="btn">Tạo mới bệnh nhân</a>
        

    </div>
</div>



    <script>
        function viewPatientHistory(patientId) {
            window.location.href = `patient_history_doctor.php?id=${patientId}`;
        }

        function viewSOS() {
            window.location.href = "sos.php";
        }
    </script>
</body>
</html>
