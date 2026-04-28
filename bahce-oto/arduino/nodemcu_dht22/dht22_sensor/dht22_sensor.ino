/*
 * NodeMCU (ESP8266) – DHT22 Sensör – Veri Gönderici
 *
 * Bu kod:
 *  1. DHT22'den sıcaklık ve nem okur
 *  2. WiFi'ye bağlanır
 *  3. PHP API'ye POST ile JSON gönderir
 *
 * Pin bağlantısı:
 *   D4 = DHT22 DATA pin
 *   VCC = 3.3V
 *   GND = GND
 *   DHT22'e VCC ve DATA arasına 10kΩ çekme direnci bağlayın!
 */

#include <ESP8266WiFi.h>
#include <DHT.h>
#include <ArduinoJson.h>

#define DHTPIN D4          // DHT22 veri pini
#define DHTTYPE DHT22

const char* ssid     = "WIFI_SSID";
const char* password = "WIFI_PASSWORD";
const char* host     = "bahce.emrahakdag.xyz";
const int   port     = 443;               // HTTPS için 443
const char* path     = "/api/sensor";     // API endpoint
const char* fingerprint[] = { NULL };     // SSL sertifikasını buraya ekleyin

DHT dht(DHTPIN, DHTTYPE);
WiFiClientSecure client;

void setup() {
  Serial.begin(115200);
  delay(1000);
  dht.begin();

  WiFi.begin(ssid, password);
  Serial.println("\nWiFi bağlanıyor");

  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print('.');
  }

  Serial.println("\nWiFi bağlandı – IP: " + WiFi.localIP().toString());
}

void loop() {
  float h = dht.readHumidity();
  float t = dht.readTemperature();            // Celsius

  if (isnan(h) || isnan(t)) {
    Serial.println("DHT okuma hatası!");
    delay(5000);
    return;
  }

  // JSON payload hazırla
  StaticJsonDocument<256> doc;
  doc["device_id"]  = "nodemcu_esp8266_01";
  doc["temperature"] = t;
  doc["humidity"]    = h;

  String payload;
  serializeJson(doc, payload);

  Serial.println("Gönderilecek veri: " + payload);

  // HTTPS bağlantısı
  client.stop();
  if (!client.connect(host, port)) {
    Serial.println("Bağlantı hatası");
    delay(10000);
    return;
  }

  // POST isteği
  String request =  "POST " + String(path) + " HTTP/1.1\r\n" +
                    "Host: " + String(host) + "\r\n" +
                    "User-Agent: NodeMCU-DHT22\r\n" +
                    "Content-Type: application/json\r\n" +
                    "Content-Length: " + String(payload.length()) + "\r\n" +
                    "Connection: close\r\n" +
                    "\r\n" +
                    payload;

  client.println(request);
  delay(100);

  // Yanıtı oku
  while (client.available()) {
    Serial.write(client.read());
  }

  client.stop();
  Serial.println("\n--- Veri gönderildi, 30 saniye bekle ---");

  delay(30000);   // config.yaml'deki poll_interval = 30s
}
