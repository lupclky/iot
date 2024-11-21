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

$sql = "SELECT MAX(id) AS max_id FROM measurements WHERE patient_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$maxMeasurementId = $row['max_id'] ?? 0; ;
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="container">
        <div class="welcome">
            <span>Xin chào, bệnh nhân <strong><?php echo htmlspecialchars($username); ?></strong></span>
            <div class="user-actions">
                <a href="logout.php">Đăng xuất</a>
                <a href="change-password.php">Đổi mật khẩu</a>
            </div>
        </div>

        <button type="button" onclick="startMeasurement()">Bắt đầu đo</button>

        <div class="countdown">
            <h2>Đang đo... <span id="countdown">30</span> giây</h2>
        </div>

        <input type="hidden" name="patient_id" id="patient_id" value="<?php echo $_SESSION['id'] ?? ''; ?>">

        <div class="row">
            <div class="column">
                <h2>Điện tâm đồ tim</h2>
                <div class="chart">
                    <canvas id="ecgChart" width="full" height ="400"></canvas>
                </div>
            </div>
        </div>

        <div class="heart-rate-section">
            <div class="heart-rate-container">
                <div class="heart-rate-box">
                    <img src="heart.png" alt="Heart Icon" class="heart-icon">
                    <span id="heart-rate-value">Đang đo...</span>
                    <span id="heart-rate-status" class="status-text"></span>
                </div>

                <div class="spo2-box">
                    <h2>Nồng độ Oxy trong máu</h2>
                    <span id="spo2-value">Đang đo...</span>
                    <span id="spo2-status" class="status-text"></span>
                </div>
            </div>
        </div>

        <div class="footer">
            <button id="save-result" class="save-result">Lưu kết quả</button>
            <button class="open-records" onclick="window.location.href='patient_history.php'">Xem lịch sử đo</button>
        </div>

        <div class="sos-section" id="sos-section" style="display:none;">
            <button id="sos-button" class="sos-button" onclick="handleSOSClick()">SOS</button>
        </div>
    </div>
    <form action="save_measurement.php" method="POST">
            <input type="hidden" name="patient_id" id="patient_id" value="<?php echo $_SESSION['patient_id'] ?? ''; ?>">
            <input type="hidden" name="spo2" id="spo2_input" value="">
            <input type="hidden" name="heart_rate" id="heart_rate_input" value="">
            <button type="submit" id="save-result" class="save-result" style="display:none;">Lưu kết quả</button>
        </form>
        
    <script>
        let countdownTime = 30;
        let countdownInterval, measureInterval;
        let hasSaved = false; // Biến kiểm soát lưu dữ liệu

        const ecgChart = new Chart(document.getElementById('ecgChart').getContext('2d'), {
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
                responsive: true, // Biểu đồ tự động thay đổi kích thước
                maintainAspectRatio: false, 
                scales: {
                    x: { title: { display: true, text: 'Thời gian (giây)' } },
                    y: { title: { display: true, text: 'Nhịp tim (bpm)' } }
                }
            }
        });

        function startMeasurement() {
            countdownTime =30;
            hasSaved = false;
            document.getElementById('countdown').textContent = countdownTime;
            document.getElementById('save-result').style.display = 'none';

            ecgChart.data.labels = [];
            ecgChart.data.datasets[0].data = [];
        //    ecgChart.update();

            document.getElementById('spo2-value').textContent = '0';
            document.getElementById('heart-rate-value').textContent = '0';
            document.getElementById('spo2-status').textContent = '';
            document.getElementById('heart-rate-status').textContent = '';

            countdownInterval = setInterval(() => {
                countdownTime--;
                document.getElementById('countdown').textContent = countdownTime;

                if (countdownTime <= 0) {
                    clearInterval(countdownInterval);
                    document.getElementById('save-result').style.display = 'block';
                }
            }, 1000);

            measureInterval = setInterval(fetchDataFromSensor, 500);
        }

        let measureCount = 0; // Biến đếm số lần đo
        let lastECGValue = 0; // Biến lưu giá trị ECG trước đó
      
        function fetchDataFromSensor() {
            if (countdownTime <= 0) {
                clearInterval(measureInterval);
                return;
            }

            fetch('http://192.168.1.67/data')
                .then(response => response.json())
                .then(data => {
                    if (data.spo2 !== undefined && data.heart_rate !== undefined) {
                 //       document.getElementById('spo2-value').textContent = `${data.spo2} %`;
                 //       document.getElementById('heart-rate-value').textContent = `${data.heart_rate} bpm`;
                        let ecg= data.heart_rate;
                        updateECGChart(data.heart_rate);
                        
                           // Tính sự chênh lệch giữa lần đo hiện tại và trước đó
            let heartRate;
            let difference = Math.abs(ecg - lastECGValue);


            if (difference > 1) {
                // Nếu sự thay đổi > 1, nhịp tim dao động từ 80-100
                heartRate = Math.floor(Math.random() * (100 - 80 + 1)) + 80;
            } else {
                // Nếu sự thay đổi <= 1
                const probability = Math.random();
                if (probability <= 0.1) {
                    // 5% nhịp tim từ 120-130
                    heartRate = Math.floor(Math.random() * (130 - 120 + 1)) + 120;
                } else {
                    // 95% nhịp tim từ 100-120
                    heartRate = Math.floor(Math.random() * (120 - 100 + 1)) + 100;
                }
            }


            // Lưu giá trị ECG hiện tại để so sánh lần đo sau
            lastECGValue = ecg;


            // Tính giá trị SpO2 tương ứng (giả lập)
            const a = 100; // Giá trị SpO2 tại nhịp tim 80
            const b = 0.1111; // Hệ số ảnh hưởng của nhịp tim
            const c = 80; // Ngưỡng nhịp tim cơ bản
            const randomSpo2 = a - b * (heartRate - c);


            // Cập nhật dữ liệu lên biểu đồ ECG
            updateECGChart(ecg);


            // Cập nhật giá trị ngẫu nhiên lên giao diện
            document.getElementById('spo2-value').textContent = `${randomSpo2.toFixed(1)} %`;
            document.getElementById('heart-rate-value').textContent = `${heartRate.toFixed(2)} bpm`;

                        checkDangerousHeartRate(heartRate, randomSpo2);
                        console.log(heartRate);
                        console.log(randomSpo2);
                    }
                })
                .catch(error => console.error('Error fetching sensor data:', error));
        }
        

        // Du ohong ///////////////////////////////////
        ///
        //
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


$sql = "SELECT MAX(id) AS max_id FROM measurements WHERE patient_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$maxMeasurementId = $row['max_id'] ?? 0;;
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>


<body>
    <div class="container">
        <div class="welcome">
            <span>Xin chào, bệnh nhân <strong><?php echo htmlspecialchars($username); ?></strong></span>
            <div class="user-actions">
                <a href="logout.php">Đăng xuất</a>
                <a href="change-password.php">Đổi mật khẩu</a>
            </div>
        </div>


        <button type="button" onclick="startMeasurement()">Bắt đầu đo</button>


        <div class="countdown">
            <h2>Đang đo... <span id="countdown">30</span> giây</h2>
        </div>


        <input type="hidden" name="patient_id" id="patient_id" value="<?php echo $_SESSION['id'] ?? ''; ?>">


        <div class="row">
            <div class="column">
                <h2>Điện tâm đồ tim</h2>
                <div class="chart">
                    <canvas id="ecgChart"></canvas>
                </div>
            </div>
        </div>


        <div class="heart-rate-section">
            <div class="heart-rate-container">
                <div class="heart-rate-box">
                    <img src="heart.png" alt="Heart Icon" class="heart-icon">
                    <span id="heart-rate-value">Đang đo...</span>
                    <span id="heart-rate-status" class="status-text"></span>
                </div>


                <div class="spo2-box">
                    <h2>Nồng độ Oxy trong máu</h2>
                    <span id="spo2-value">Đang đo...</span>
                    <span id="spo2-status" class="status-text"></span>
                </div>
            </div>
        </div>


        <div class="footer">
            <button id="save-result" class="save-result">Lưu kết quả</button>
            <button class="open-records" onclick="window.location.href='patient_history.php'">Xem lịch sử đo</button>
        </div>


        <div class="sos-section" id="sos-section" style="display:none;">
            <button id="sos-button" class="sos-button" onclick="handleSOSClick()">SOS</button>
            <p id="sos-warning" style="color: red"> Phát hiện sức khỏe bất thường </p>
        </div>


    </div>


    <script>
        let countdownTime = 30;
        let countdownInterval, measureInterval;
        let hasSaved = false; // Biến kiểm soát lưu dữ liệu


        const ecgChart = new Chart(document.getElementById('ecgChart').getContext('2d'), {
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
                responsive: true, // Biểu đồ tự động thay đổi kích thước
                maintainAspectRatio: false, // Tắt để không giữ tỉ lệ cố định
                scales: {
                    x: {
                        title: {
                            display: true,
                            text: 'Thời gian (giây)'
                        }
                    },
                    y: {
                        title: {
                            display: true,
                            text: 'Nhịp tim (bpm)'
                        }
                    }
                }
            }
        });


        function startMeasurement() {
            countdownTime = 15;
            hasSaved = false;
            document.getElementById('countdown').textContent = countdownTime;
            document.getElementById('save-result').style.display = 'none';


            ecgChart.data.labels = [];
            ecgChart.data.datasets[0].data = [];
            ecgChart.update();


            document.getElementById('spo2-value').textContent = '0';
            document.getElementById('heart-rate-value').textContent = '0';
            document.getElementById('spo2-status').textContent = '';
            document.getElementById('heart-rate-status').textContent = '';


            countdownInterval = setInterval(() => {
                countdownTime--;
                document.getElementById('countdown').textContent = countdownTime;


                if (countdownTime <= 0) {
                    clearInterval(countdownInterval);
                    document.getElementById('save-result').style.display = 'block';
                }
            }, 1000);


            measureInterval = setInterval(fetchDataFromSensor, 500);
        }


        // function fetchDataFromSensor() {
        //     if (countdownTime <= 0) {
        //         clearInterval(measureInterval);
        //         return;
        //     }


        //     fetch('http://172.20.10.13/data')
        //         .then(response => response.json())
        //         .then(data => {
        //             if (data.spo2 !== undefined && data.heart_rate !== undefined) {
        //                 document.getElementById('spo2-value').textContent = `${data.spo2} %`;
        //                 document.getElementById('heart-rate-value').textContent = `${data.heart_rate} bpm`;
        //                 updateECGChart(data.heart_rate);
        //                 checkDangerousHeartRate(data.heart_rate, data.spo2);
        //             }
        //         })
        //         .catch(error => console.error('Error fetching sensor data:', error));








        // }




        let measureCount = 0; // Biến đếm số lần đo
        let lastECGValue = 0; // Biến lưu giá trị ECG trước đó


        // function fetchDataFromSensor() {
        //     if (countdownTime <= 0) {
        //         clearInterval(measureInterval);
        //         return;
        //     }


        //     // Tăng biến đếm mỗi lần đo
        //     measureCount++;


        //     // Tạo giá trị điện tim (ECG) ngẫu nhiên dựa trên lần đo chẵn hoặc lẻ
        //     let randomECGValue;
        //     if (measureCount % 2 === 0) {
        //         // Lần đo chẵn (số lần đo chia hết cho 2)
        //         randomECGValue = (Math.random() * (0.3 - 0.1)) + 0.1; // Tạo giá trị ngẫu nhiên từ 0.1 đến 0.3
        //     } else {
        //         // Lần đo lẻ (số lần đo không chia hết cho 2)
        //         randomECGValue = (Math.random() * (1.8 - 1.5)) + 1.5; // Tạo giá trị ngẫu nhiên từ 1.5 đến 1.8
        //     }


        //     // Tính sự chênh lệch giữa lần đo hiện tại và trước đó
        //     let heartRate;
        //     let difference = Math.abs(randomECGValue - lastECGValue);


        //     if (difference > 1) {
        //         // Nếu sự thay đổi > 1, nhịp tim dao động từ 80-100
        //         heartRate = Math.floor(Math.random() * (100 - 80 + 1)) + 80;
        //     } else {
        //         // Nếu sự thay đổi <= 1
        //         const probability = Math.random();
        //         if (probability <= 0.05) {
        //             // 5% nhịp tim từ 120-130
        //             heartRate = Math.floor(Math.random() * (130 - 120 + 1)) + 120;
        //         } else {
        //             // 95% nhịp tim từ 100-120
        //             heartRate = Math.floor(Math.random() * (120 - 100 + 1)) + 100;
        //         }
        //     }


        //     // Lưu giá trị ECG hiện tại để so sánh lần đo sau
        //     lastECGValue = randomECGValue;


        //     // Tính giá trị SpO2 tương ứng (giả lập)
        //     const a = 100; // Giá trị SpO2 tại nhịp tim 80
        //     const b = 0.1111; // Hệ số ảnh hưởng của nhịp tim
        //     const c = 80; // Ngưỡng nhịp tim cơ bản
        //     const randomSpo2 = a - b * (heartRate - c); // Giả lập SpO2 dựa trên ECG (tạm thời)


        //     // Cập nhật dữ liệu lên biểu đồ ECG
        //     updateECGChart(randomECGValue);


        //     // Cập nhật giá trị ngẫu nhiên lên giao diện
        //     document.getElementById('spo2-value').textContent = `${randomSpo2.toFixed(1)} %`;
        //     document.getElementById('heart-rate-value').textContent = `${heartRate.toFixed(2)} bpm`;


        //     // Kiểm tra tình trạng SpO2 và nhịp tim
        //     checkDangerousHeartRate(heartRate, randomSpo2);
        // }




        function fetchDataFromSensor() {
            if (countdownTime <= 0) {
                clearInterval(measureInterval);
                return;
            }


            // Tăng biến đếm mỗi lần đo
            measureCount++;


            // Xác suất tạo giá trị ECG ngẫu nhiên
            let randomECGValue;
            const probability = Math.random(); // Xác suất ngẫu nhiên từ 0 đến 1
            if (probability <= 0.5) {
                // 50% từ 0.2-0.4
                randomECGValue = (Math.random() * (0.4 - 0.2)) + 0.2;
            } else {
                // 50% từ 1.5-1.9
                randomECGValue = (Math.random() * (1.9 - 1.5)) + 1.5;
            }


            // Tính sự chênh lệch giữa lần đo hiện tại và trước đó
            let heartRate;
            let difference = Math.abs(randomECGValue - lastECGValue);


            if (difference > 1) {
                // Nếu sự thay đổi > 1, nhịp tim dao động từ 80-100
                heartRate = Math.floor(Math.random() * (100 - 80 + 1)) + 80;
            } else {
                // Nếu sự thay đổi <= 1
                const probability = Math.random();
                if (probability <= 0.1) {
                    // 5% nhịp tim từ 120-130
                    heartRate = Math.floor(Math.random() * (130 - 120 + 1)) + 120;
                } else {
                    // 95% nhịp tim từ 100-120
                    heartRate = Math.floor(Math.random() * (120 - 100 + 1)) + 100;
                }
            }


            // Lưu giá trị ECG hiện tại để so sánh lần đo sau
            lastECGValue = randomECGValue;


            // Tính giá trị SpO2 tương ứng (giả lập)
            const a = 100; // Giá trị SpO2 tại nhịp tim 80
            const b = 0.1111; // Hệ số ảnh hưởng của nhịp tim
            const c = 80; // Ngưỡng nhịp tim cơ bản
            const randomSpo2 = a - b * (heartRate - c);


            // Cập nhật dữ liệu lên biểu đồ ECG
            updateECGChart(randomECGValue);


            // Cập nhật giá trị ngẫu nhiên lên giao diện
            document.getElementById('spo2-value').textContent = `${randomSpo2.toFixed(1)} %`;
            document.getElementById('heart-rate-value').textContent = `${heartRate.toFixed(2)} bpm`;


            // Kiểm tra tình trạng SpO2 và nhịp tim
            checkDangerousHeartRate(heartRate, randomSpo2);
        }








        function updateECGChart(heartRate) {
            const now = new Date().toLocaleTimeString();
            ecgChart.data.labels.push(now);
            ecgChart.data.datasets[0].data.push(heartRate);
            ecgChart.update();
        }


        function checkDangerousHeartRate(heartRate, spo2) {
            const spo2Status = checkSpo2Status(spo2);
            document.getElementById('spo2-status').textContent = spo2Status.status;
            document.getElementById('spo2-status').style.color = spo2Status.color;


            const heartRateStatus = checkHeartRateStatus(heartRate);
            document.getElementById('heart-rate-status').textContent = heartRateStatus.status;
            document.getElementById('heart-rate-status').style.color = heartRateStatus.color;


            if (!hasSaved && (heartRateStatus.isDangerous || spo2Status.isDangerous)) {
                hasSaved = true;
                saveMeasurement(true); // Lưu với SOS = 1 khi phát hiện nguy hiểm
                document.getElementById('sos-section').style.display = 'block'; // Hiện nút SOS
            }
        }


        function checkSpo2Status(spo2) {
            if (spo2 < 90) {
                return {
                    status: 'Nguy hiểm',
                    color: 'red',
                    isDangerous: true
                };
            } else if (spo2 < 95) {
                return {
                    status: 'Bất ổn',
                    color: 'orange',
                    isDangerous: false
                };
            } else {
                return {
                    status: 'Bình thường',
                    color: 'green',
                    isDangerous: false
                };
            }
        }


        function checkHeartRateStatus(heartRate) {
            if (heartRate < 60 || heartRate > 120) {
                return {
                    status: 'Nguy hiểm',
                    color: 'red',
                    isDangerous: true
                };
            } else if (heartRate < 80 || heartRate > 100) {
                return {
                    status: 'Bất ổn',
                    color: 'orange',
                    isDangerous: false
                };
            } else {
                return {
                    status: 'Bình thường',
                    color: 'green',
                    isDangerous: false
                };
            }
        }


        function handleSOSClick() {
            fetch('update_sos.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        sos_status: 1
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('SOS đã được kích hoạt!');
                    } else {
                        alert('Có lỗi khi kích hoạt SOS.');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Có lỗi xảy ra.');
                });
        }


        function saveMeasurement(isDangerous = false) {
            const patientId = document.getElementById('patient_id').value;
            const spo2 = document.getElementById('spo2-value').textContent.replace(' %', '');
            const heartRate = document.getElementById('heart-rate-value').textContent.replace(' bpm', '');
            const sos = isDangerous ? 1 : 0; // SOS = 1 nếu nguy hiểm, ngược lại = 0


            // Gửi dữ liệu đến save_measurement.php qua AJAX
            fetch('save_measurement.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: new URLSearchParams({
                        patient_id: patientId,
                        spo2: spo2,
                        heart_rate: heartRate,
                        sos: sos // Truyền giá trị SOS
                    })
                })
                .then(response => response.text())
                .then(data => {
                    console.log('Kết quả đo đã được lưu:', data);


                })
                .catch(error => {
                    console.error('Lỗi khi lưu kết quả:', error);


                });
        }


        // Khi bấm nút lưu, giá trị sos = 0
        document.getElementById('save-result').addEventListener('click', () => {
            saveMeasurement(false); // Gọi lưu kết quả bình thường với SOS = 0
        });
    </script>
</body>


</html>



    
        ///
        ///
        function updateECGChart(heartRate) {
            const now = new Date().toLocaleTimeString();
            ecgChart.data.labels.push(now);
            ecgChart.data.datasets[0].data.push(heartRate);
            ecgChart.update();
        }

        function checkDangerousHeartRate(heartRate, spo2) {
            const spo2Status = checkSpo2Status(spo2);
            document.getElementById('spo2-status').textContent = spo2Status.status;
            document.getElementById('spo2-status').style.color = spo2Status.color;

            const heartRateStatus = checkHeartRateStatus(heartRate);
            document.getElementById('heart-rate-status').textContent = heartRateStatus.status;
            document.getElementById('heart-rate-status').style.color = heartRateStatus.color;

            if (!hasSaved && (heartRateStatus.isDangerous || spo2Status.isDangerous)) {
                hasSaved = true;
                saveMeasurement(true); // Lưu với SOS = 1 khi phát hiện nguy hiểm
                document.getElementById('sos-section').style.display = 'block'; // Hiện nút SOS
            }
        }
        function checkSpo2Status(spo2) {
            if (spo2 < 90) {
                return { status: 'Nguy hiểm', color: 'red', isDangerous: true };
            } else if (spo2 < 95) {
                return { status: 'Bất ổn', color: 'orange', isDangerous: false };
            } else {
                return { status: 'Bình thường', color: 'green', isDangerous: false };
            }
        }

        function checkHeartRateStatus(heartRate) {
            if (heartRate < 60|| heartRate > 120) {
                return { status: 'Nguy hiểm', color: 'red', isDangerous: true };
            } else if (heartRate < 80 || heartRate > 100) {
                return { status: 'Bất ổn', color: 'orange', isDangerous: false };
            } else {
                return { status: 'Bình thường', color: 'green', isDangerous: false };
            }
        }
        function handleSOSClick() {
            fetch('update_sos.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({sos_status: 1 })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('SOS đã được kích hoạt!');
                } else {
                    alert('Có lỗi khi kích hoạt SOS.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Có lỗi xảy ra.');
            });
        }
        function saveMeasurement(isDangerous = false) {
            const patientId = document.getElementById('patient_id').value;
            const spo2 = document.getElementById('spo2-value').textContent.replace(' %', '');
            const heartRate = document.getElementById('heart-rate-value').textContent.replace(' bpm', '');
            const sos = isDangerous ? 1 : 0; // SOS = 1 nếu nguy hiểm, ngược lại = 0

            // Gửi dữ liệu đến save_measurement.php qua AJAX
            fetch('save_measurement.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    patient_id: patientId,
                    spo2: spo2,
                    heart_rate: heartRate,
                    sos: sos // Truyền giá trị SOS
                })
            })
            .then(response => response.text())
            .then(data => {
                console.log('Kết quả đo đã được lưu:', data);
            
            })
            .catch(error => {
                console.error('Lỗi khi lưu kết quả:', error);
                
            });
        }

        // Khi bấm nút lưu, giá trị sos = 0
        document.getElementById('save-result').addEventListener('click', () => {
            saveMeasurement(false); // Gọi lưu kết quả bình thường với SOS = 0
        });


    </script>
</body>
</html>
