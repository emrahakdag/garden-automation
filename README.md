# 🌿 Garden-OS (Akıllı Bahçe Otomasyon Sistemi)

[![PHP](https://img.shields.io/badge/PHP-8.x-777BB4.svg?style=flat&logo=php)]()
[![MySQL](https://img.shields.io/badge/MySQL-MariaDB-4479A1.svg?style=flat&logo=mysql)]()
[![Tailwind](https://img.shields.io/badge/CSS-Tailwind-06B6D4.svg?style=flat&logo=tailwindcss)]()
[![ESP8266](https://img.shields.io/badge/HW-ESP8266-2CA5E0.svg?style=flat)]()

> Bahçe verilerinin izlenmesi ve kritik donanımın (kuyu motoru vb.) güvenli uzaktan kontrolünü sağlayan uçtan uca IoT sistemi.

🌐 **URL**: https://bahce.emrahakdag.xyz

---

## 📁 Proje Yapısı

```
garden-automation/
├── public/
│   ├── index.php              # Ana dashboard (Tailwind CSS)
│   ├── api.php                # REST API endpoint
│   └── assets/
│       └── js/
│           └── dashboard.js   # Dashboard JavaScript
├── config/
│   └── settings.json          # Dinamik sistem konfigürasyonu
├── data/
│   └── db_schema.sql          # Veritabanı şeması (zaman serisi + son değer)
├── devices/
│   ├── esp8266_firmware.ino   # NodeMCU ana firmware (DHT22 + HC-SR04)
│   └── motor_control_unit.ino # Motor kontrol birimi
├── README.md
├── .gitignore
└── .gitattributes
```

---

## 🛠 Teknik Yapı

| Katman       | Teknoloji                              |
| ------------ | -------------------------------------- |
| Sunucu       | Coolify (VPS), PHP 8.x, Nginx/Apache  |
| Veritabanı   | MySQL / MariaDB                        |
| Frontend     | HTML5, Tailwind CSS, JavaScript        |
| Donanım      | NodeMCU (ESP8266), Arduino, DHT22, HC-SR04 |
| Protokol     | HTTP/HTTPS (REST API + JSON)           |

---

## 🚀 Kurulum

### 1. Veritabanı

```bash
mysql -u root -p < data/db_schema.sql
```

### 2. API Konfigürasyonu

`public/api.php` dosyasındaki `getDB()` fonksiyonunu gerçek veritabanı bilgilerinizle güncelleyin:

```php
function getDB() {
    $db = new PDO(
        'mysql:host=localhost;dbname=garden_os;charset=utf8mb4',
        'db_user',
        'db_password'
    );
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $db;
}
```

### 3. Coolify Deployment

1. GitHub reposunu Coolify'e bağlayın
2. Domain olarak `bahce.emrahakdag.xyz` tanımlayın
3. Build root olarak `public/` klasörünü ayarlayın
4. Otomatik deployment aktif!

### 4. ESP8266 Kurulumu

1. Arduino IDE'yi aç
2. Board Management'dan ESP8266 paketini yükle: `https://arduino.esp8266.com/stable/package_esp8266com_index.json`
3. `devices/esp8266_firmware.ino` dosyasını aç
4. WiFi ve API ayarlarını değiştir
5. Yükle

---

## 📡 API Endpoints

### GET Endpoints

| Endpoint | Açıklama |
| -------- | -------- |
| `?action=status` | Sistem bilgisi |
| `?action=latest` | Tüm sensörlerin son değerleri |
| `?action=history&node_id=X&metric=Y&hours=24` | Tarihsel sensör verisi |
| `?action=nodes` | Kayıtlı düğümler |

### POST Endpoints

| action | Açıklama |
| ------ | -------- |
| `sensor_data` | Sensör okuması gönder |
| `motor_control` | Motoru aç/kapat (şifreli) |
| `device_register` | Cihaz kayıt/kalibrasyon |

### Örnek İstekler

**Sensör verisi gönderme:**
```bash
curl -X POST https://bahce.emrahakdag.xyz/api.php \
  -H "Content-Type: application/json" \
  -d '{
    "action": "sensor_data",
    "node_id": "node_01",
    "data": { "temperature": 25.5, "humidity": 60.0 },
    "rssi": -45
  }'
```

**Motor kontrol:**
```bash
curl -X POST https://bahce.emrahakdag.xyz/api.php \
  -H "Content-Type: application/json" \
  -d '{
    "action": "motor_control",
    "password": "guclu_bir_sifre",
    "action_value": "ON",
    "duration": 3600
  }'
```

---

## 🔐 Güvenlik

- **Motor komutları:** Şifre korumalı (settings.json'dan okunur)
- API key koruması için `config/settings.json` -> `security.api_secret_key` değiştir
- `.env` dosyaları `.gitignore` ile dışlandı
- CORS ayarları konfigürasyondan yönetilir

---

## 📦 Donanım Pini Tanımları

| Bileşen    | ESP8266 Pin | Görev               |
| ---------- | ----------- | ------------------- |
| DHT22      | D2 (GPIO4)  | Sensör veri         |
| HC-SR04 TR | D5 (GPIO14) | Ultrasonik trigger  |
| HC-SR04 EC | D6 (GPIO12) | Ultrasonik echo     |
| Motor Röle | D1 (GPIO5)  | Dijital çıkış       |

---

## 🗺 Yol Haritası

- [x] Altyapı kurulumu
- [x] API + Dashboard
- [x] Veritabanı şeması
- [x] ESP8266 firmware
- [x] Konfigürasyon sistemi
- [ ] Veritabanı bağlantısı aktif etme
- [ ] MQTT / WebSocket gerçek zamanlı
- [ ] Zamanlayıcı motor kontrol cron-job
- [ ] Mobil uyum iyileştirme
- [ ] Telegram / WhatsApp bildirim entegrasyonu

---

## 📜 Lisans

Garden-OS — Akıllı Bahçe Otomasyon Sistemi
