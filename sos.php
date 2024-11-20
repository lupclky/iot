<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$conn = new mysqli("localhost:3307", "root", "", "iot");
if ($conn->connect_error) {
    die("Kết nối thất bại: " . $conn->connect_error);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $measurementId = $_POST['measurement_id'];
    $updateSql = "UPDATE measurements SET sos = 0 WHERE id = ?";
    $stmt = $conn->prepare($updateSql);
    $stmt->bind_param("i", $measurementId);
    $stmt->execute();
}

$sql = "SELECT id, patient_id, spo2, heart_rate, measurement_time FROM measurements WHERE sos = 1";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SOS</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h1>Lịch sử SOS</h1>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Patient ID</th>
                    <th>SpO2</th>
                    <th>Nhịp tim</th>
                    <th>Thời gian đo</th>
                    <th>Hành động</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if ($result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        echo "<tr>";
                        echo "<td>" . $row['id'] . "</td>";
                        echo "<td>" . $row['patient_id'] . "</td>";
                        echo "<td>" . $row['spo2'] . "</td>";
                        echo "<td>" . $row['heart_rate'] . "</td>";
                        echo "<td>" . $row['measurement_time'] . "</td>";
                        echo "<td>
                            <form method='POST' action=''>
                                <input type='hidden' name='measurement_id' value='" . $row['id'] . "'>
                                <button type='submit'>Xác nhận</button>
                            </form>
                        </td>";
                        echo "</tr>";
                    }
                } else {
                    echo "<tr><td colspan='6'>Không có bản ghi SOS</td></tr>";
                }
                ?>
            </tbody>
        </table>
        <a href="dashboard_1.php" class="back-btn">Quay lại Dashboard</a>
    </div>
</body>
</html>
