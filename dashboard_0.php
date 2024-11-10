<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

// Lấy tên người dùng từ session
$username = $_SESSION['username'];

// Kết nối tới cơ sở dữ liệu
$conn = new mysqli("localhost:3307", "root", "", "health_monitoring");

// Kiểm tra kết nối
if ($conn->connect_error) {
    die("Kết nối thất bại: " . $conn->connect_error);
}

// Lấy lịch sử đo của bệnh nhân từ cơ sở dữ liệu dựa trên tên tài khoản
$sql = "SELECT date, heart_rate, spo2 FROM measurements WHERE patient_id = (SELECT id FROM patients WHERE username = ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

$measurements = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $measurements[] = $row;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Dashboard - Health Monitoring</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script> <!-- Thư viện Chart.js -->
</head>
<body>
    <div class="container">
        <!-- Phần chào mừng người dùng -->
        <div class="welcome">
            <span>Xin chào, bệnh nhân <strong><?php echo htmlspecialchars($username); ?></strong></span>
            <div class="user-actions">
                <a href="logout.php">Đăng xuất</a>
                <a href="change-password.php">Đổi mật khẩu</a>
            </div>
        </div>
        <button type="button" onclick="startMeasurement()">Bắt đầu đo</button>
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
                    <span id="spo2-status" class="status-text"></span> <!-- Thẻ hiển thị trạng thái SpO2 -->
                </div>
            </div>
        </div>

        <!-- Thêm bảng hiển thị nhịp tim và logo trái tim -->
        <div class="heart-rate-section">
            <h2>Nhịp tim (bpm)</h2>
            <div class="heart-rate-box">
                <img src="heart.png" alt="Heart Icon" class="heart-icon">
                <span id="heart-rate-value">Đang đo...</span>
                <span id="heart-rate-status" class="status-text"></span> <!-- Thẻ hiển thị trạng thái nhịp tim -->
            </div>
        </div>

        <!-- Nút lưu kết quả và xem lịch sử -->
        <div class="footer">
            <button id="save-result" class="save-result" style="display:none;" onclick="saveResult()">Lưu kết quả</button>
            <button class="open-records" onclick="window.location.href='patient_history.php'">Xem lịch sử đo</button> <!-- Thêm nút xem lịch sử -->
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
        function saveResult() {
            const patientId = document.getElementById('patient').value;
            const spo2 = document.getElementById('spo2-value').textContent;
            const heartRate = document.getElementById('heart-rate-value').textContent;

            fetch('save_measurement.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    patient_id: patientId,
                    spo2: spo2.replace(' %', ''), // Loại bỏ ký tự '%' trước khi gửi
                    heart_rate: heartRate.replace(' bpm', '') // Loại bỏ ký tự 'bpm' trước khi gửi
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Kết quả đo đã được lưu thành công!');
                } else {
                    alert('Lưu kết quả đo thất bại: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Lỗi khi lưu kết quả đo:', error);
                alert('Đã xảy ra lỗi, không thể lưu kết quả đo.');
            });
        }

        // Hàm khởi động việc đo từ ESP32
        function startMeasurement() {
            countdownTime = 30;
            document.getElementById('countdown').textContent = countdownTime;

            // Ẩn nút Lưu kết quả khi bắt đầu đo
            document.getElementById('save-result').style.display = 'none'; 

            // Xóa dữ liệu của biểu đồ ECG
            ecgChart.data.labels = [];
            ecgChart.data.datasets[0].data = [];
            ecgChart.update();

            // Reset các giá trị Spo2 và nhịp tim
            document.getElementById('spo2-value').textContent = '0';
            document.getElementById('heart-rate-value').textContent = '0';
            document.getElementById('spo2-status').textContent = '';
            document.getElementById('heart-rate-status').textContent = '';

            // Khởi động đếm ngược
            countdownInterval = setInterval(() => {
                countdownTime--;
                document.getElementById('countdown').textContent = countdownTime;

                if (countdownTime <= 0) {
                    clearInterval(countdownInterval);
                    document.getElementById('save-result').style.display = 'block'; // Hiện lại nút Lưu kết quả khi đo xong
                }
            }, 1000);

            const measureInterval = setInterval(() => {
                if (countdownTime > 0) {
                    fetch('http://192.168.123.86/data') // Endpoint root của ESP32
                        .then(response => response.json())
                        .then(data => {
                            const spo2 = data.spo2;
                            const heartRate = data.heart_rate;

                            // Hiển thị nhịp tim và SpO2
                            document.getElementById('spo2-value').textContent = spo2 + ' %';
                            document.getElementById('heart-rate-value').textContent = heartRate + ' bpm';

                            // Cập nhật màu sắc và trạng thái cho nhịp tim dựa trên giá trị
                            let heartRateStatus = '';
                            if (heartRate > 120 || heartRate < 60) {
                                document.getElementById('heart-rate-value').style.color = 'red'; // Nguy hiểm
                                heartRateStatus = 'Nguy hiểm';
                            } else if (heartRate >= 100 && heartRate <= 120) {
                                document.getElementById('heart-rate-value').style.color = 'yellow'; // Bất ổn
                                heartRateStatus = 'Bất ổn';
                            } else {
                                document.getElementById('heart-rate-value').style.color = 'green'; // Bình thường
                                heartRateStatus = 'Bình thường';
                            }
                            document.getElementById('heart-rate-status').textContent = heartRateStatus;

                            // Cập nhật màu sắc và trạng thái cho SpO2 dựa trên giá trị
                            let spo2Status = '';
                            if (spo2 < 90) {
                                document.getElementById('spo2-value').style.color = 'red'; // Thiếu oxy nặng
                                spo2Status = 'Thiếu oxy nặng';
                            } else if (spo2 >= 90 && spo2 < 95) {
                                document.getElementById('spo2-value').style.color = 'yellow'; // Hypoxemia nhẹ
                                spo2Status = 'Hypoxemia nhẹ';
                            } else {
                                document.getElementById('spo2-value').style.color = 'green'; // Bình thường
                                spo2Status = 'Bình thường';
                            }
                            document.getElementById('spo2-status').textContent = spo2Status;

                            // Cập nhật dữ liệu vào biểu đồ ECG và màu sắc biểu đồ
                            ecgChart.data.labels.push(30 - countdownTime);
                            ecgChart.data.datasets[0].data.push(heartRate);
                            
                            // Thay đổi màu sắc của biểu đồ dựa vào nhịp tim
                            if (heartRate > 120 || heartRate < 60) {
                                ecgChart.data.datasets[0].borderColor = 'red'; // Màu nguy hiểm
                            } else if (heartRate >= 100 && heartRate <= 120) {
                                ecgChart.data.datasets[0].borderColor = 'yellow'; // Màu bất ổn
                            } else {
                                ecgChart.data.datasets[0].borderColor = 'green'; // Màu bình thường
                            }

                            ecgChart.update();
                        });
                } else {
                    clearInterval(measureInterval);
                }
            }, 1000);
        }

        // Gọi hàm bắt đầu đo ngay khi tải trang
       
    </script>
</body>
</html>
