# Bahçe Otomasyon Projesi

Bahçe su pompası ve sensörleri üzerinden uzaktan otomasyon sağlayan bir IoT projesi.

## 🏗️ Proje Yapısı

```
bahce-oto/
├── src/
│   ├── config.php          # Veritabanı bağlantısı
│   └── api/
│       ├── sensor.php      # Sensör veri API'si
│       └── motor.php       # Motor kontrol API'si
├── public/
│   ├── index.html          # Ana sayfa
│   └── motor.html          # Motor kontrol arayüzü
├── database/
│   └── schema.sql          # Veritabanı şeması
├── arduino/
│   └── nodemcu_dht22/
│       └── dht22_sensor.ino # Arduino/NodeMCU firmware
├── config.yaml             # Genel konfigürasyon
├── Dockerfile              # Docker konteyner tanımı
├── docker-compose.yml      # Yerel geliştirme için
├── .env                    # Ortam değişkenleri
├── .env.example            # Ortam değişkeni şablonu
└── .gitignore
```

## 🚀 Hızlı Başlangıç

### Yerel Geliştirme

```bash
# Ortam değişkenlerini ayarla
cp .env.example .env
# .env dosyasını düzenle

# Docker ile başlat
docker-compose up -d
```

Siteyi aç: `http://localhost:8080`

### Coolify ile Dağıtım

1. Coolify'da yeni proje oluştur: `bahce-otomasyon`
2. GitHub repository bağlantısını ekle
3. Branch: `main`
4. Domain: `bahce.emrahakdag.xyz`
5. Docker build pack seçeneği ile dağıt

## 📡 API Endpoint'leri

### Sensör API

| Endpoint | Method | Açıklama |
|----------|--------|----------|
| `/api/sensor` | POST | Sensör verisi kaydet |
| `/api/sensor/latest` | GET | Son okunan değerler |
| `/api/sensor/device/{device_id}` | GET | Belirli cihazın son verisi |

**POST /api/sensor** – Gövde:
```json
{
  "device_id": "nodemcu_esp8266_01",
  "temperature": 24.5,
  "humidity": 62.3
}
```

**Yanıt:**
```json
{ "status": "ok", "id": 1 }
```

### Motor API

| Endpoint | Method | Açıklama |
|----------|--------|----------|
| `/api/motor` | POST | Motor başlat (şifreli) |
| `/api/motor/start` | POST | Motor başlat (yukarı ile aynı) |
| `/api/motor/stop` | POST | Motor durdur (şifreli) |
| `/api/motor/status` | GET | Son motor durumu |

**POST /api/motor** – Gövde:
```json
{
  "password": "GUVENLISIFRE123"
}
```

**Yanıt:**
```json
{ "status": "motor_started" }
```

**GET /api/motor/status** – Yanıt:
```json
{
  "current": "on",
  "started_at": "2026-04-28 10:30:00"
}
```

## 🔌 Arduino / NodeMCU

NodeMCU (ESP8266) üzerinde `dht22_sensor.ino` kodunu Arduino IDE ile yükleyin.
Önce `config.yaml` ve `.env` dosyasındaki WiFi bilgilerini güncelleyin.

### Gerekli kütüphaneler

- `DHT sensor library` (Adafruit)
- `ArduinoJson`

## 🗄️ Veritabanı

MySQL 8.0 kullanılır. `database/schema.sql` dosyası, container başlatıldığında otomatik olarak oluşturulur.

Tablolar:
- `sensor_log` – Sensör okuma kayıtları
- `motor_log` – Motor açma/kapama kayıtları
- `tank_log` – Su tankı doluluk kayıtları

## 📋 Yapım Aşamasında Olan Özellikler

- [ ] Su tankı seviyesi okuma (ultrasonic sensör)
- [ ] Cevaplı MQTT broker entegrasyonu
- [ ] HTTP istatistiklerini izleyen loglama
- [ ] E-posta/SMS uyarıları: su seviyesi %10 altında + sensörler çökmüş

## ⚙️ Güvenlik

- Motor şifresi `.env` dosyasında tutulur (asla `config.yaml` içinde değil)
- Coolify ortam değişkenleri üzerinden şifre yönetilir
- HTTPS (Let's Encrypt SSL önerilir)
