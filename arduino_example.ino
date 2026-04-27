/*
 * Arduino (ESP8266/ESP32) Sensör Verisi Gönderme Örneği
 *
 * Bu kod, sıcaklık ve nem sensöründen okunan verileri
 * web sunucunuza HTTP POST ile gönderir.
 *
 * Gereksinimler:
 * - ESP8266 veya ESP32 kartı
 * - DHT22 sıcaklık/nem sensörü (opsiyonel)
 * - WiFi bağlantısı
 *
 * Kütüphaneler (ESP8266 için):
 * - ESP8266WiFi.h
 * - ESP8266HTTPClient.h
 * - WiFiClient.h
 *
 * Kütüphaneler (ESP32 için):
 * - WiFi.h
 * - HTTPClient.h
 */

// === AYARLAR - BURAYI DÜZENLEYİN ===

// WiFi ayarları
const char* ssid = "WIFI_ADINIZ";           // WiFi adı
const char* password = "WIFI_SIFRENIZ";      // WiFi şifresi

// Sunucu ayarları
const char* serverUrl = "http://192.168.1.100/api/post_data.php";  // Sunucu adresi
const char* apiKey = "";                       // Config'den aldığınız API anahtarı (boş olabilir)

// Modül ayarları (config.php'den eklediğiniz modül slug'ı)
const char* moduleSlug = "sicaklik";          // Modül kısa adı

// Zamanlama
const unsigned long sendInterval = 5000;      // 5 saniyede bir gönder (milisaniye)

// === PIN AYARLARI ===
const int sensorPin = A0;                     // Analog sensör pini (LM35 için)
// const int dhtPin = 2;                     // DHT sensör pini (DHT kullanıyorsanız)

// DHT sensörü kullanıyorsanız bu satırı açın:
// #include <DHT.h>
// #define DHTTYPE DHT22
// DHT dht(dhtPin, DHTTYPE);

// === KÜTÜPHANELER ===
#if defined(ESP8266)
  #include <ESP8266WiFi.h>
  #include <ESP8266HTTPClient.h>
  #include <WiFiClient.h>
#elif defined(ESP32)
  #include <WiFi.h>
  #include <HTTPClient.h>
#else
  #error "Bu kod sadece ESP8266 veya ESP32 ile çalışır!"
#endif

#include <ArduinoJson.h>

// Değişkenler
unsigned long lastSendTime = 0;
int readingCount = 0;

void setup() {
  Serial.begin(115200);
  delay(1000);

  Serial.println("\n============================");
  Serial.println("Arduino Monitor - Veri Gönderici");
  Serial.println("============================\n");

  // Pin modlarını ayarla
  pinMode(sensorPin, INPUT);

  // WiFi bağlantısı
  connectToWiFi();

  // DHT sensörünü başlat (kullanıyorsanız)
  // dht.begin();

  Serial.println("\nKurulum tamamlandı! Veriler gönderilmeye hazır.\n");
}

void loop() {
  // WiFi bağlantısını kontrol et
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("WiFi bağlantısı kesildi! Yeniden bağlanılıyor...");
    connectToWiFi();
  }

  // Belirlenen aralıkta veri gönder
  unsigned long currentTime = millis();
  if (currentTime - lastSendTime >= sendInterval) {
    sendSensorData();
    lastSendTime = currentTime;
  }

  delay(100);
}

void connectToWiFi() {
  Serial.print("WiFi'ye bağlanılıyor: ");
  Serial.println(ssid);

  WiFi.begin(ssid, password);

  int attempts = 0;
  while (WiFi.status() != WL_CONNECTED && attempts < 20) {
    delay(500);
    Serial.print(".");
    attempts++;
  }

  if (WiFi.status() == WL_CONNECTED) {
    Serial.println("\n✓ WiFi bağlandı!");
    Serial.print("IP Adresi: ");
    Serial.println(WiFi.localIP());
  } else {
    Serial.println("\n✗ WiFi bağlantısı başarısız!");
  }
}

void sendSensorData() {
  // Sensör verisini oku
  float value = readSensor();

  if (value < -999) {
    Serial.println("Hata: Sensör verisi okunamadı!");
    return;
  }

  readingCount++;

  // JSON verisini oluştur
  DynamicJsonDocument doc(200);
  doc["module_slug"] = moduleSlug;
  doc["value"] = value;

  if (strlen(apiKey) > 0) {
    doc["api_key"] = apiKey;
  }

  // İsteğe bağlı: Ham veri ekle
  JsonObject raw = doc.createNestedObject("raw");
  raw["value"] = value;
  raw["reading"] = readingCount;
  raw["uptime"] = millis() / 1000;

  String jsonString;
  serializeJson(doc, jsonString);

  // HTTP isteği gönder
  Serial.printf("\n[%d] Veri gönderiliyor: %.2f\n", readingCount, value);

#if defined(ESP8266)
  WiFiClient client;
  HTTPClient http;
#elif defined(ESP32)
  HTTPClient http;
#endif

  http.begin(serverUrl);
  http.addHeader("Content-Type", "application/json");

  int httpCode = http.POST(jsonString);

  if (httpCode > 0) {
    String response = http.getString();
    Serial.printf("HTTP Kodu: %d\n", httpCode);
    Serial.printf("Yanıt: %s\n", response.c_str());
  } else {
    Serial.printf("HTTP Hatası: %s\n", http.errorToString(httpCode).c_str());
  }

  http.end();
}

float readSensor() {
  // === SEÇENEK 1: LM35 Analog Sıcaklık Sensörü ===
  int sensorValue = analogRead(sensorPin);
  float voltage = sensorValue * (3.3 / 1023.0);  // ESP8266: 1023, ESP32: 4095
  float temperature = voltage * 100.0;  // LM35: 10mV/°C
  return temperature;

  // === SEÇENEK 2: DHT22 Sıcaklık/Nem Sensörü ===
  // float temperature = dht.readTemperature();
  // if (isnan(temperature)) {
  //   return -999; // Hata değeri
  // }
  // return temperature;

  // === SEÇENEK 3: Rastgele Veri (Test için) ===
  // return random(200, 300) / 10.0;
}

/*
 * KURULUM TALİMATLARI:
 *
 * 1. Gerekli kütüphaneleri yükleyin:
 *    - ESP8266 için: Arduino IDE > Tercihler > Additional Boards Manager URLs:
 *      http://arduino.esp8266.com/stable/package_esp8266com_index.json
 *    - Araçlar > Kart > Kart Yöneticisi > ESP8266'yı yükleyin
 *
 * 2. Kod içindeki ayarları düzenleyin:
 *    - ssid: WiFi ağ adı
 *    - password: WiFi şifresi
 *    - serverUrl: Web sunucunuzun adresi (config.php'deki API URL'si)
 *    - moduleSlug: Config'den eklediğiniz modülün slug'ı
 *    - apiKey: Config'de ayarladığınız API anahtarı
 *
 * 3. Sensör bağlantılarını yapın:
 *    - LM35: VCC→3.3V, GND→GND, OUT→A0
 *    - DHT22: VCC→3.3V, GND→GND, DATA→Pin 2
 *
 * 4. Kodu yükleyin ve Serial Monitör'ü açın (115200 baud)
 *
 * API'YE GÖNDERILEN JSON ÖRNEĞI:
 * {
 *   "module_slug": "sicaklik",
 *   "value": 25.50,
 *   "api_key": "sizin_api_anahtariniz",
 *   "raw": {
 *     "value": 25.50,
 *     "reading": 1,
 *     "uptime": 5
 *   }
 * }
 */
