<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

// Lấy tên người dùng từ session
$username = $_SESSION['username'];

// Kết nối tới cơ sở dữ liệu để lấy danh sách bệnh nhân
$conn = new mysqli("localhost:3307", "root", "", "health_monitoring");

// Kiểm tra kết nối
if ($conn->connect_error) {
    die("Kết nối thất bại: " . $conn->connect_error);
}

// Lấy danh sách bệnh nhân từ cơ sở dữ liệu
$sql = "SELECT id, name FROM patients";
$result = $conn->query($sql);

$patients = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $patients[] = $row;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Health Monitoring Dashboard</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script> <!-- Thư viện Chart.js -->
</head>
<body>
    <div class="container">
        <!-- Phần chào mừng người dùng -->
        <div class="welcome">
            <span>Xin chào bác sĩ <strong><?php echo htmlspecialchars($username); ?></strong></span>
            <div class="user-actions">
                <a href="logout.php">Đăng xuất</a>
                <a href="change-password.php">Đổi mật khẩu</a>
            </div>
        </div>

        <!-- Phần chọn bệnh nhân để đo -->
        <div class="select-patient">
            <h2>Chọn bệnh nhân để tiến hành đo</h2>
            <form id="patientForm">
                <label for="patient">Chọn bệnh nhân:</label>
                <select name="patient" id="patient">
                    <?php foreach ($patients as $patient): ?>
                        <option value="<?php echo $patient['id']; ?>"><?php echo $patient['name']; ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="button" onclick="startMeasurement()">Bắt đầu đo</button>
            </form>
        </div>

        <!-- Phần đếm ngược 30 giây -->
        <div class="countdown">
            <h2>Đang đo... <span id="countdown">30</span> giây</h2>
        </div>

        <div class="row">
            <!-- Điện tâm đồ tim -->
            <div class="column">
                <h2>Điện tâm đồ tim</h2>
                <div class="chart">
                    <canvas id="ecgChart"></canvas>
                </div>
            </div>
            <!-- Nồng độ Oxy trong máu -->
            <div class="column">
                <h2>Nồng độ Oxy trong máu</h2>
                <div class="spo2-box">
                    <span id="spo2-value">Đang đo...</span>
                </div>
            </div>
        </div>

        <!-- Thêm bảng hiển thị nhịp tim và logo trái tim -->
        <div class="heart-rate-section">
            <h2>Nhịp tim (bpm)</h2>
            <div class="heart-rate-box">
                <img src="heart.png" alt="Heart Icon" class="heart-icon">
                <span id="heart-rate-value">Đang đo...</span>
            </div>
        </div>

        <!-- Nút lưu kết quả -->
        <div class="footer">
            <button id="save-result" class="save-result" style="display:none;" onclick="saveResult()">Lưu kết quả</button>
            <button class="open-records" onclick="window.location.href='list.php'">Xem danh sách bệnh nhân</button>
            <button class="send-alert" onclick="sendAlert()">Gửi cảnh báo</button>
        </div>
    </div>

    <script src="script.js"></script>
    <script>
        let countdownInterval;
        let countdownTime = 30;

        // Vẽ biểu đồ ECG với Chart.js
        const ctx = document.getElementById('ecgChart').getContext('2d');
        const ecgChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: [],
                datasets: [{
                    label: 'Nhịp tim',
                    data: [],
                    borderColor: '#e74c3c',
                    borderWidth: 2,
                    fill: false
                }]
            },
            options: {
                scales: {
                    x: { display: true, title: { display: true, text: 'Thời gian (giây)' } },
                    y: { display: true, title: { display: true, text: 'Nhịp tim (bpm)' } }
                }
            }
        });

        // Hàm khởi động việc đo từ ESP32
        function startMeasurement() {
            const patientId = document.getElementById('patient').value;
            countdownTime = 30;
            document.getElementById('countdown').textContent = countdownTime;

            countdownInterval = setInterval(() => {
                countdownTime--;
                document.getElementById('countdown').textContent = countdownTime;
                if (countdownTime <= 0) {
                    clearInterval(countdownInterval);
                    document.getElementById('save-result').style.display = 'block'; // Hiển thị nút lưu sau khi đếm ngược
                }
            }, 1000);

            fetch('http://192.168.14.123/data') // Địa chỉ của ESP32
                .then(response => response.json())
                .then(data => {
                    document.getElementById('spo2-value').textContent = data.spo2 + ' %';
                    document.getElementById('heart-rate-value').textContent = data.heart_rate + ' bpm';

                    // Cập nhật biểu đồ ECG với dữ liệu mới
                    ecgChart.data.labels.push(30 - countdownTime); // Thời gian
                    ecgChart.data.datasets[0].data.push(data.heart_rate); // Nhịp tim
                    ecgChart.update();

                    if (countdownTime <= 0) {
                        clearInterval(countdownInterval);
                        document.getElementById('save-result').style.display = 'block'; // Hiển thị nút lưu sau khi hết 30 giây
                    }
                })
                .catch(error => {
                    console.error('Lỗi khi lấy dữ liệu từ ESP32:', error);
                });
        }

        // Hàm lưu kết quả vào cơ sở dữ liệu
        function saveResult() {
            const patientId = document.getElementById('patient').value;
            const heartRate = document.getElementById('heart-rate-value').textContent.replace(' bpm', '');
            const spo2 = document.getElementById('spo2-value').textContent.replace(' %', '');

            fetch('save_measurement.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: `patient_id=${patientId}&heart_rate=${heartRate}&spo2=${spo2}`
            })
            .then(response => response.text())
            .then(data => {
                alert('Kết quả đã được lưu!');
            })
            .catch(error => {
                console.error('Lỗi khi lưu dữ liệu:', error);
            });
        }

        function sendAlert() {
            alert('Cảnh báo đã được gửi!');
        }
    </script>
</body>
</html>
