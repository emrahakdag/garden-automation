/**
 * Garden-OS - Motor Kontrol Birimi (ESP8266)
 * ===========================================
 * Sadece motor röle kontrolüne adamış özel firmware
 * API'den komut alır ve röleyi yönetir
 *
 * Donanım: ESP8266 + 1 Kanal Röle Modülü
 * Röle Pin: D1 (GPIO5)
 */

#include <ESP8266WiFi.h>
#include <ESP8266HTTPClient.h>

// ---- AYARLAR ----
const char* WIFI_SSID     = "WiFi_Adi";
const char* WIFI_PASSWORD = "WiFi_Sifre";
const char* API_URL       = "https://bahce.emrahakdag.xyz/api.php";
const char* MOTOR_PASSWORD = "guclu_bir_sifre";  // settings.json ile aynı olmalı
const char* DEVICE_ID     = "motor_ctrl_01";

#define MOTOR_RELAY_PIN D1
#define POLL_INTERVAL 5000  // Durum kontrol aralığı (ms)

// Motor durumu
bool motorState = false;
unsigned long motorStartTs = 0;
int motorDurationSec = 0;

// ---- SETUP ----
void setup() {
    Serial.begin(115200);
    pinMode(MOTOR_RELAY_PIN, OUTPUT);
    digitalWrite(MOTOR_RELAY_PIN, LOW);

    Serial.println("\n=== Motor Kontrol Birimi ===");

    WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
    int attempts = 0;
    while (WiFi.status() != WL_CONNECTED && attempts++ < 30) delay(500);

    if (WiFi.status() == WL_CONNECTED) {
        Serial.print("WiFi: "); Serial.println(WiFi.localIP());
    }
}

// ---- HTTP POST: Komut gönder ----
String apiRequest(String action, String password, String command) {
    if (WiFi.status() != WL_CONNECTED) return "";

    WiFiClient client;
    HTTPClient http;
    String payload = "{\"action\":\"" + action +
                     "\",\"password\":\"" + password +
                     "\",\"action_value\":\"" + command + "\"}";

    http.begin(client, API_URL);
    http.addHeader("Content-Type", "application/json");
    int code = http.POST(payload);
    String resp = (code > 0) ? http.getString() : "";
    http.end();
    return resp;
}

// ---- LOOP ----
void loop() {
    // Eğer planlı süre dolmuşsa motoru kapat
    if (motorState && motorDurationSec > 0) {
        if ((millis() - motorStartTs) >= (unsigned long)(motorDurationSec * 1000)) {
            motorState = false;
            motorDurationSec = 0;
            digitalWrite(MOTOR_RELAY_PIN, LOW);
            Serial.println("Motor otomatik kapatıldı (süre doldu)");
        }
    }

    delay(POLL_INTERVAL);
}
