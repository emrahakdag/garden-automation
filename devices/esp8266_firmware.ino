/**
 * Garden-OS - ESP8266 Ana Firmware
 * =================================
 * Sensör okumalarını API'ye gönderir + motor kontrolü yapar
 *
 * Donanım: NodeMCU (ESP8266)
 * Bağlanan Sensörler:
 *   - DHT22 (Sıcaklık/Nem) → D2 (GPIO4)
 *   - HC-SR04 (Mesafe)     → Trigger: D5 (GPIO14), Echo: D6 (GPIO12)
 *   - Motor Röle            → D1 (GPIO5)
 *
 * Kullanım:
 * 1. Bu kodu Arduino IDE'ye aç
 * 2. Gerekli kütüphaneleri yükle (DHT, NewPing - Library Manager)
 * 3. WIFI_SSID, WIFI_PASSWORD ve API_URL'yi değiştir
 * 4. YÜKLE
 *
 * ESP8266 Board Package:
 *   Dosya > Tercihler > Ek Board Yöneticisi URL'leri:
 *   https://arduino.esp8266.com/stable/package_esp8266com_index.json
 */

#include <ESP8266WiFi.h>
#include <ESP8266HTTPClient.h>
#include <WiFiClient.h>
#include <DHT.h>
#include <ArduinoJson.h>

// ==========================================
// KURULUM - BUNLARI DEĞİŞTİR!
// ==========================================
const char* WIFI_SSID     = "WiFi_Adi";
const char* WIFI_PASSWORD = "WiFi_Sifre";
const char* API_URL       = "https://bahce.emrahakdag.xyz/api.php";
const char* NODE_ID       = "node_01";  /* node_01, node_02 veya node_03 */
const char* NODE_TYPE     = "DHT22_HCSR04";

#define UPDATE_INTERVAL 60000  // Sensör güncelleme süresi (ms) - 60 sn

// DHT22 Pin
#define DHT_PIN D2
#define DHTTYPE DHT22

// HC-SR04 Pin
#define TRIG_PIN D5
#define ECHO_PIN D6

// Motor Röle Pin
#define MOTOR_RELAY_PIN D1

// ==========================================
// GLOBAL DEĞİŞKENLER
// ==========================================
DHT dht(DHT_PIN, DHTTYPE);
unsigned long lastUpdate = 0;
bool motorRunning = false;
unsigned long motorStartTime = 0;
int motorDuration = 0; // Planlanan süre (ms)

// ==========================================
// SETUP
// ==========================================
void setup() {
    Serial.begin(115200);
    delay(1000);

    Serial.println("\n=== Garden-OS Başlatılıyor ===");
    Serial.print("Node: "); Serial.println(NODE_ID);

    // Pin modları
    pinMode(MOTOR_RELAY_PIN, OUTPUT);
    digitalWrite(MOTOR_RELAY_PIN, LOW); // Röle kapalı (LOW aktif röle ise HIGH)

    // DHT22 Init
    dht.begin();
    Serial.println("DHT22 hazır.");

    // HC-SR04 Pin modları
    pinMode(TRIG_PIN, OUTPUT);
    pinMode(ECHO_PIN, INPUT);
    Serial.println("HC-SR04 hazır.");

    // WiFi Bağlantısı
    WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
    Serial.print("WiFi bağlanıyor");
    int attempts = 0;
    while (WiFi.status() != WL_CONNECTED && attempts < 30) {
        delay(500);
        Serial.print(".");
        attempts++;
    }
    Serial.println();

    if (WiFi.status() == WL_CONNECTED) {
        Serial.print("Bağlandı! IP: ");
        Serial.println(WiFi.localIP());
        Serial.print("RSSI: ");
        Serial.print(WiFi.RSSI());
        Serial.println(" dBm");
    } else {
        Serial.println("WiFi bağlantısı başarısız! Retry bekleniyor...");
    }

    // Motor kontrol pininin başlangıç durumu
    Serial.print("Motor Röle (D1): ");
    Serial.println(digitalRead(MOTOR_RELAY_PIN) == LOW ? "Kapalı" : "Açık");
}

// ==========================================
// HC-SR04 MESAFE ÖLÇÜMÜ
// ==========================================
float readDistance() {
    digitalWrite(TRIG_PIN, LOW);
    delayMicroseconds(2);
    digitalWrite(TRIG_PIN, HIGH);
    delayMicroseconds(10);
    digitalWrite(TRIG_PIN, LOW);

    long duration = pulseIn(ECHO_PIN, HIGH, 30000); // 30ms timeout
    if (duration == 0) return -1; // Timeout - sensör yanıt vermedi

    float distance = duration * 0.034 / 2.0; // cm
    return distance;
}

// ==========================================
// MOTOR KONTROL
// ==========================================
void setMotor(bool on, int durationSeconds = 0) {
    digitalWrite(MOTOR_RELAY_PIN, on ? HIGH : LOW); // Röle HIGH aktif
    motorRunning = on;
    motorStartTime = millis();
    motorDuration = durationSeconds * 1000;

    Serial.print("Motor ");
    Serial.println(on ? "AÇILDI" : "KAPATILDI");

    if (on && durationSeconds > 0) {
        Serial.print("Süre: ");
        Serial.print(durationSeconds);
        Serial.println(" saniye");
    }
}

// ==========================================
// API'YE VERİ GÖNDERME
// ==========================================
void sendDataToAPI(float temp, float humidity, float distance) {
    if (WiFi.status() != WL_CONNECTED) {
        Serial.println("WiFi bağlantısı yok, veri gönderilemez");
        return;
    }

    WiFiClient client;
    HTTPClient http;

    // JSON payload oluştur
    DynamicJsonDocument doc(1024);
    doc["action"] = "sensor_data";
    doc["node_id"] = NODE_ID;
    doc["rssi"] = WiFi.RSSI();

    JsonObject dataObj = doc.createNestedObject("data");

    bool hasData = false;

    if (!isnan(temp)) {
        dataObj["temperature"] = temp;
        hasData = true;
    }
    if (!isnan(humidity)) {
        dataObj["humidity"] = humidity;
        hasData = true;
    }
    if (distance >= 0) {
        dataObj["water_level"] = distance;
        hasData = true;
    }

    if (!hasData) {
        Serial.println("Gönderilecek veri yok");
        return;
    }

    String json;
    serializeJson(doc, json);

    http.begin(client, API_URL);
    http.addHeader("Content-Type", "application/json");

    int httpCode = http.POST(json);
    Serial.printf("API POST: %d\n", httpCode);

    if (httpCode > 0) {
        String response = http.getString();
        Serial.println("Yanıt: " + response);

        // Yanıtta alert var mı kontrol et
        DynamicJsonDocument respDoc(512);
        DeserializationError error = deserializeJson(respDoc, response);
        if (!error) {
            JsonArray alerts = respDoc["alerts"].as<JsonArray>();
            if (alerts.size() > 0) {
                Serial.println("UYARI!");
                for (JsonObject alert : alerts) {
                    Serial.print("  ");
                    Serial.print(alert["metric"].as<String>());
                    Serial.print(" -> ");
                    Serial.print(alert["value"].as<float>());
                    Serial.print(" [");
                    Serial.print(alert["severity"].as<String>());
                    Serial.println("]");
                }
            }
        }
    } else {
        Serial.printf("API Hatası: %s\n", http.errorToString(httpCode).c_str());
    }

    http.end();
}

// ==========================================
// MOTOR KONTROL - API'DEN KONTROL
// ==========================================
void checkMotorStatus() {
    // Manuel olarak açılmış motor süresi dolduysa kapat
    if (motorRunning && motorDuration > 0) {
        unsigned long elapsed = millis() - motorStartTime;
        if (elapsed >= motorDuration) {
            setMotor(false);
            Serial.println("Motor süresi doldu, otomatik kapatıldı");
        }
    }
}

// ==========================================
// CİHAZ KAYDI (ilk açılışta)
// ==========================================
void registerDevice(String fwVersion = "0.1.0") {
    if (WiFi.status() != WL_CONNECTED) return;

    WiFiClient client;
    HTTPClient http;

    DynamicJsonDocument doc(256);
    doc["action"] = "device_register";
    doc["node_id"] = NODE_ID;
    doc["hardware_version"] = "ESP8266";
    doc["firmware_version"] = fwVersion;

    String json;
    serializeJson(doc, json);

    http.begin(client, API_URL);
    http.addHeader("Content-Type", "application/json");

    int httpCode = http.POST(json);
    Serial.printf("Cihaz kaydı: %d\n", httpCode);
    http.end();
}

// ==========================================
// LOOP
// ==========================================
void loop() {
    // WiFi kontrolü
    if (WiFi.status() != WL_CONNECTED) {
        Serial.println("Bağlantı kayboldu, yeniden bağlanıyor...");
        WiFi.reconnect();
        delay(2000);
        return;
    }

    // Motor kontrol (süre dolanları kapat)
    checkMotorStatus();

    // UPDATE_INTERVAL geçti mi?
    if (millis() - lastUpdate < UPDATE_INTERVAL) {
        delay(100);
        return;
    }
    lastUpdate = millis();

    // ---- SENSÖR OKUMALARI ----
    Serial.println("\n--- Sensör Okuma ---");

    // DHT22
    float temp = dht.readTemperature();
    float humidity = dht.readHumidity();

    if (isnan(temp) || isnan(humidity)) {
        Serial.println("DHT22 okuma hatası!");
        temp = NAN;
        humidity = NAN;
    } else {
        Serial.printf("Sıcaklık: %.1f°C, Nem: %.1f%%\n", temp, humidity);
    }

    // HC-SR04
    float distance = readDistance();
    if (distance < 0) {
        Serial.println("HC-SR04 okuma hatası (timeout)!");
    } else {
        Serial.printf("Mesafe: %.1f cm\n", distance);
    }

    // API'ye gönder
    sendDataToAPI(temp, humidity, distance);

    Serial.println("Bekleniyor... (" + String(UPDATE_INTERVAL / 1000) + "sn)\n");
}
