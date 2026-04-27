# Bahçe Otomasyon Projesi Kurulum Kılavuzu

Bu belgede, **Coolify** platformu üzerinde bir bahçe otomasyon projesi oluşturma, GitHub ile entegrasyon, alt alan adı yönlendirme ve başlangıç için basit bir `index.html` dosyası ekleme adımları ayrıntılı olarak anlatılmaktadır.

---

## 1️⃣ Ön Koşullar

- **VPS** üzerinde **Coolify** ve **n8n** zaten kurulu ve `https://emrahakdag.xyz` üzerinden erişilebilir.
- Bir **GitHub** hesabınız ve projenizi barındıracak bir repository (örnek: `github.com/kullaniciadi/bahce-oto`) mevcut.
- **Domain** yönetim panelinizde `bahce.emrahakdag.xyz` alt alan adı (sub‑domain) ekleme yetkiniz var.

---

## 2️⃣ Coolify’da Yeni Proje Oluşturma

1. **Coolify Dashboard** ( `https://emrahakdag.xyz` ) adresine giriş yapın.
2. Sol menüde **Projects** → **Create New Project** seçeneğine tıklayın.
3. **Project Name** kısmına `bahce-otomasyon` yazın ve **Create** butonuna basın.
4. Oluşturulan proje kartında **Deploy Settings** → **Git Repository** kısmına gelin.
5. **Repository URL** alanına GitHub repository'nizin HTTPS adresini girin, örnek:
   ```
   https://github.com/kullaniciadi/bahce-oto.git
   ```
6. **Branch** olarak `main` (veya tercih ettiğiniz dal) seçin ve **Save** edin.
7. **Deploy** butonuna basarak ilk dağıtımı başlatın. Coolify, Docker image’ı oluşturup host üzerinde çalıştıracaktır.

---

## 3️⃣ Alt Alan Adı (Sub‑Domain) Yönlendirme

1. Coolify projesi kartındaki **Settings** sekmesine gidin.
2. **Domain** bölümüne `bahce.emrahakdag.xyz` yazın.
3. **Add Domain** butonuna basın.
4. **DNS** ayarlarınızda (genellikle Cloudflare, GoDaddy vb.) aşağıdaki CNAME kaydını ekleyin:
   - **Name:** `bahce`
   - **Target:** `cname.coolify.io` (VPS’inizde Coolify tarafından sağlanan hedef)
5. DNS yayılmasını (genellikle birkaç dakika) bekleyin ve **Check** butonuyla doğrulayın.

---

## 4️⃣ Deneme Amaçlı `index.html` Dosyası

GitHub repository’nize `public/index.html` (veya doğrudan kök dizine) aşağıdaki içeriği ekleyin ve pushlayın:

```html
<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8" />
  <title>Bahçe Otomasyon – Test Sayfası</title>
  <style>
    body {font-family: Arial, sans-serif; background:#f0f8ff; padding:2rem;}
    h1 {color:#2c7;}
  </style>
</head>
<body>
  <h1>Bahçe Otomasyon Projesine Hoşgeldiniz</h1>
  <p>Bu sayfa, Coolify üzerinden otomatik dağıtımın başarılı olduğunu gösterir.</p>
</body>
</html>
```

Push işleminden sonra Coolify **Auto‑Deploy** ayarınız aktifse, yeni `index.html` otomatik olarak sitenizde (`https://bahce.emrahakdag.xyz`) yayınlanacaktır.

---

## 5️⃣ Proje Konfigürasyon Dosyası (`config.yaml`)

Sensörlerin veri toplama sıklığı, motor kontrol şifresi ve cihaz tanımlamaları tek bir **YAML** dosyasında tutulur. Bu dosya hem Arduino/NodeMCU‑ların hem de Raspberry Pi‑nin okuyabileceği bir format sağlar.

```yaml
# config.yaml – Bahçe Otomasyon Konfigürasyonu

# Veri toplama periyodu (saniye)
poll_interval: 30   # her 30 saniyede bir sensör verisi gönderilir

# Motor kontrol şifresi (HTTPS endpoint üzerinden doğrulanır)
motor_control:
  password: "GUVENLISIFRE123"   # üretim ortamında env‑variable kullanın!
  endpoint: "/api/motor/start"   # POST isteği bu route’a gönderilir

# Sensör tanımları (her bir Arduino/NodeMCU ayrı bir cihaz ID’si alır)
devices:
  - id: "arduino_nano_01"
    type: "dht22"
    pins:
      data: 2
  - id: "nodemcu_esp8266_01"
    type: "dht22"
    pins:
      data: D4
  - id: "raspberry_pi"
    type: "ultrasonic"
    pins:
      trig: 23
      echo: 24

# Depo doluluk oranı sensörü (örnek olarak bir analog giriş)
water_tank:
  sensor_pin: A0
  capacity_liters: 200
```

**Not:** Gerçek ortamda şifre gibi gizli değerleri `config.yaml` içinde tutmayın. Bunun yerine **environment variables** (`.env`) ve **Docker secrets** kullanın.

---

## 6️⃣ Arduino / NodeMCU Kod Şablonu (PHP‑MySQL API’ye veri gönderme)

```cpp
#include <ESP8266WiFi.h>
#include <DHT.h>
#include <ArduinoJson.h>

#define DHTPIN D4          // DHT22 veri pini (NodeMCU)
#define DHTTYPE DHT22

const char* ssid     = "WIFI_SSID";
const char* password = "WIFI_PASSWORD";
const char* host     = "bahce.emrahakdag.xyz"; // Coolify’da çalışan API

DHT dht(DHTPIN, DHTTYPE);
WiFiClient client;

void setup() {
  Serial.begin(115200);
  dht.begin();
  WiFi.begin(ssid, password);
  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print('.');
  }
  Serial.println("\nWiFi bağlandı");
}

void loop() {
  delay(30000); // config.yaml'deki poll_interval (30s)
  float h = dht.computeHeatIndex(dht.readHumidity(), dht.readTemperature());
  float t = dht.readTemperature();
  if (isnan(h) || isnan(t)) {
    Serial.println("DHT okuma hatası");
    return;
  }
  // JSON payload hazırlama
  DynamicJsonDocument doc(256);
  doc["device_id"] = "nodemcu_esp8266_01";
  doc["temperature"] = t;
  doc["humidity"]    = h;
  String payload;
  serializeJson(doc, payload);

  if (client.connect(host, 80)) {
    client.println("POST /api/sensor/data HTTP/1.1");
    client.println("Host: " + String(host));
    client.println("Content-Type: application/json");
    client.println("Content-Length: " + String(payload.length()));
    client.println();
    client.print(payload);
    client.stop();
    Serial.println("Veri gönderildi");
  } else {
    Serial.println("Bağlantı hatası");
  }
}
```

> **PHP‑MySQL API** kısmı daha sonra ayrı bir bölümde detaylandırılacaktır (veri tablo yapısı, endpoint tanımları vb.).

---

## 7️⃣ Raspberry Pi Üzerinde PHP‑MySQL API (Coolify’da Docker) 

1. **Dockerfile** (repo kökünde):
   ```dockerfile
   FROM php:8.2-apache
   RUN docker-php-ext-install pdo pdo_mysql
   COPY ./src/ /var/www/html/
   ```
2. **src/config.php** – veritabanı bağlantısı ve motor şifresi (çevre değişkeni):
   ```php
   <?php
   $db = new PDO(
       "mysql:host={$_ENV['DB_HOST']};dbname={$_ENV['DB_NAME']};charset=utf8mb4",
       $_ENV['DB_USER'],
       $_ENV['DB_PASS']
   );
   $motorPassword = $_ENV['MOTOR_PASSWORD'];
   ?>
   ```
3. **src/api/sensor.php** – sensör verisini kaydet:
   ```php
   <?php
   require '../config.php';
   $data = json_decode(file_get_contents('php://input'), true);
   $stmt = $db->prepare('INSERT INTO sensor_log (device_id, temperature, humidity, ts) VALUES (?, ?, ?, NOW())');
   $stmt->execute([$data['device_id'], $data['temperature'], $data['humidity']]);
   echo json_encode(['status' => 'ok']);
   ?>
   ```
4. **src/api/motor.php** – motoru başlatma (POST, şifre kontrolü):
   ```php
   <?php
   require '../config.php';
   $payload = json_decode(file_get_contents('php://input'), true);
   if ($payload['password'] !== $motorPassword) {
       http_response_code(403);
       exit(json_encode(['error' => 'Unauthorized'])));
   }
   // burada gerçek motor kontrolü (GPIO, MQTT vs.) yapılır
   echo json_encode(['status' => 'motor_started']);
   ?>
   ```
5. **database schema** (MySQL):
   ```sql
   CREATE TABLE sensor_log (
       id BIGINT AUTO_INCREMENT PRIMARY KEY,
       device_id VARCHAR(64),
       temperature FLOAT,
       humidity FLOAT,
       ts TIMESTAMP DEFAULT CURRENT_TIMESTAMP
   );
   ```
6. **Coolify’da ortam değişkenleri** (Settings → Environment):
   - `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`
   - `MOTOR_PASSWORD` (güvenlik için **secret** olarak ekleyin)

---

## 8️⃣ Motor Kontrolü – Web Arayüzü

`public/motor.html` dosyasına basit bir form ekleyerek kullanıcıdan şifre alıp POST isteği gönderebiliriz:

```html
<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8" />
  <title>Motor Çalıştır</title>
</head>
<body>
  <h2>Kuyu Motorunu Çalıştır</h2>
  <form id="motorForm">
    <label>Şifre: <input type="password" name="password" required /></label>
    <button type="submit">Başlat</button>
  </form>
  <pre id="result"></pre>
  <script>
    document.getElementById('motorForm').addEventListener('submit', async e => {
      e.preventDefault();
      const pwd = e.target.password.value;
      const resp = await fetch('/api/motor', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({password: pwd})
      });
      const txt = await resp.text();
      document.getElementById('result').textContent = txt;
    });
  </script>
</body>
</html>
```

Bu sayfa `https://bahce.emrahakdag.xyz/motor.html` üzerinden erişilebilir.

---

## 9️⃣ İleride Ek Arduino/Raspberry Pi Birimleri Eklemek

1. **Yeni cihaz ekleyin:** `config.yaml` dosyasındaki `devices` listesine bir yeni öğe ekleyin (örnek: `arduino_uno_02`).
2. **Kod şablonunu çoğaltın:** Mevcut Arduino/NodeMCU örnek kodunu cihaz ID’si ve pin tanımlarını güncelleyerek yeni bir `.ino` dosyası oluşturun.
3. **Git‑push** yapın → Coolify otomatik dağıtım **devam eder**; `config.yaml` değiştirilirse PHP API’nin `config.yaml`’ı okuyarak yeni cihazları hemen tanıyacaktır.

---

## 📌 Özet
1. Coolify’da `bahce-otomasyon` projesi ve GitHub bağlantısı oluşturuldu.  
2. `bahce.emrahakdag.xyz` sub‑domain’i DNS‑CNAME ile yönlendirildi.  
3. Basit `index.html` ile dağıtım doğrulandı.  
4. `config.yaml` sensör/ motor konfigürasyonunu topladı.  
5. Arduino/NodeMCU kod örnekleri ve PHP‑MySQL API tasarımı sağlandı.  
6. Motor kontrolü için güvenli bir web formu eklendi.  
7. Yeni cihaz ekleme prosedürü belgelendi.

Bu dosyayı `/home/emrah/ardugit/bahce_oto_kurulum.md` içinde oluşturduk. Bundan sonraki adımlarda **Arduino/Nano**, **NodeMCU**, **Raspberry Pi** tarafı kodlarını ve PHP‑MySQL veri tabanı kurulumunu detaylandırabiliriz.
