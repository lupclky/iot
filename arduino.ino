#include <WiFi.h>
#include <WebServer.h>
#include <Wire.h>
#include "MAX30105.h"
#include "heartRate.h"
#define WIFISSID ""
#define PASSWORD ""

#define TOKEN "BBUS-FEktgGDiYSJsrXibDYV6A8wIsjICVw"
#define MQTT_CLIENT_NAME "alexnewton"

#define SPO2_PIN 34 // Giả định rằng pin 34 là nơi đọc dữ liệu SpO2
#define HEART_RATE_PIN 35 // Giả định rằng pin 35 là nơi đọc dữ liệu nhịp tim

WebServer server(80); // Tạo một server trên cổng 80

void handleData() {
  // Biến lưu trữ nhịp tim và SpO2
  float spo2Value = 0;
  

  // Đọc giá trị từ cảm biến MAX30102
  float heartRateValue = 0;

  // Tính SpO2 từ dữ liệu thu được (tạm thời dùng giá trị giả lập)
  spo2Value = AnalogRead(SPO2_PIN); // Tạm thời giả lập SpO2 trong khoảng 90-100%
  heartRateValue = AnalogRead(HEART_RATE_PIN);
  // Tạo chuỗi JSON trả về
  server.sendHeader("Access-Control-Allow-Origin", "*");
  String jsonResponse = "{\"spo2\": " + String(spo2Value, 2) + ", \"heart_rate\": " + String(heartRateValue) + "}";
  server.send(200, "application/json", jsonResponse);
}
void handleStatus() {
  String jsonResponse = "{\"status\": \"connected\"}";
  server.send(200, "application/json", jsonResponse);
}


void setup() {
  Serial.begin(115200);
  WiFi.begin(WIFISSID, PASSWORD);

  Serial.print("Waiting for WiFi...");
  while (WiFi.status() != WL_CONNECTED) {
    Serial.print(".");
    delay(500);
  }
  
  Serial.println("");
  Serial.println("WiFi Connected");
  Serial.println("IP address: ");
  Serial.println(WiFi.localIP());
  
  // Cấu hình server và route
  server.on("/data", handleData); // Định nghĩa route để xử lý yêu cầu HTTP GET
  server.on("/status", handleStatus); 
  server.begin(); // Bắt đầu server
  Serial.println("HTTP server started");

}

void loop() {
  server.handleClient(); // Xử lý các yêu cầu từ client
}
