<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

// Kết nối cơ sở dữ liệu
$host = "localhost:3307";
$dbUsername = "root";
$dbPassword = "";
$dbName = "health_monitoring";

$conn = new mysqli($host, $dbUsername, $dbPassword, $dbName);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Truy vấn danh sách bệnh nhân
$sql = "SELECT id, name, birthdate, address, heart_rate, spo2, status FROM patients";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Danh sách bệnh nhân</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="container">
        <h1>Danh sách bệnh nhân</h1>

        <!-- Nút trở về -->
        <button onclick="window.location.href='dashboard_1.php'" class="back-button">Trở về Dashboard</button>

        <table class="patient-table">
            <thead>
                <tr>
                    <th>ID Bệnh nhân</th>
                    <th>Tên bệnh nhân</th>
                    <th>Ngày tháng năm sinh</th>
                    <th>Địa chỉ</th>
                    <th>Chỉ số nhịp tim (bpm)</th>
                    <th>Chỉ số SpO2 (%)</th>
                    <th>Tình trạng bệnh nhân</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // Hiển thị dữ liệu từ cơ sở dữ liệu
                if ($result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        echo "<tr>";
                        echo "<td>" . $row['id'] . "</td>";
                        echo "<td>" . $row['name'] . "</td>";
                        echo "<td>" . date('d/m/Y', strtotime($row['birthdate'])) . "</td>";
                        echo "<td>" . $row['address'] . "</td>";
                        echo "<td>" . $row['heart_rate'] . "</td>";
                        echo "<td>" . $row['spo2'] . "</td>";
                        echo "<td><span class='status " . strtolower($row['status']) . "'>" . $row['status'] . "</span></td>";
                        echo "</tr>";
                    }
                } else {
                    echo "<tr><td colspan='7'>Không có dữ liệu</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>

    <style>
        .back-button {
            margin-bottom: 20px;
            padding: 10px 20px;
            background-color: #007bff;
            color: white;
            border: none;
            cursor: pointer;
            border-radius: 5px;
        }
        .back-button:hover {
            background-color: #0056b3;
        }
        .patient-table {
            width: 100%;
            border-collapse: collapse;
        }
        .patient-table th, .patient-table td {
            padding: 10px;
            text-align: left;
            border: 1px solid #ddd;
        }
        .patient-table th {
            background-color: #f2f2f2;
        }
        .status.stable {
            color: green;
        }
        .status.normal {
            color: orange;
        }
        .status.critical {
            color: red;
        }
    </style>
</body>
</html>

<?php
$conn->close();
?>
