# Arduino Monitor - Web Izleme Sistemi

PHP + MySQL kullanarak Arduino sensör verilerini web üzerinden izleyebileceğiniz, modüler ve yapılandırılabilir bir sistem.

## Özellikler

- **Modüler Yapı**: Farklı sensör tiplerini (sıcaklık, nem, basınç vb.) modül olarak ekleyebilirsiniz
- **Gerçek Zamanlı İzleme**: Belirlenen aralıklarla verileri otomatik yeniler
- **Grafiksel Gösterim**: Chart.js ile sensör verilerini grafik üzerinde görüntüleme
- **Config Sayfası**: Web arayüzünden ayarları ve modülleri yönetme
- **API Desteği**: RESTful API ile Arduino'dan veri alma
- **HTTP REST API**: Arduino ESP8266/ESP32 ile kolay entegrasyon

## Proje Yapısı

```
arduino-monitor/
├── api/
│   ├── post_data.php      # Arduino'nun veri gönderdiği endpoint
│   ├── get_data.php      # Verileri okumak için API
│   └── modules.php       # Modül yönetim API'si
├── config/
│   └── database.php      # Veritabanı bağlantı ayarları
├── index.php             # Ana dashboard sayfası
├── config.php            # Ayarlar ve modül yönetim sayfası
├── database.sql          # Veritabanı şeması
├── arduino_example.ino   # Arduino kod örneği
└── README.md
```

## Kurulum

### 1. Web Sunucusu Hazırlığı

- PHP 7.4+ ve MySQL 5.7+ (veya MariaDB) desteklenen bir hosting hesabınız olmalı
- Apache veya Nginx web sunucusu
- PDO MySQL eklentisinin aktif olduğundan emin olun

### 2. Dosyaları Yükleyin

Tüm dosyaları web sunucunuzun `public_html` veya `www` klasörüne yükleyin.

### 3. Veritabanı Kurulumu

1. MySQL veritabanı oluşturun (örn: `arduino_monitor`)
2. `database.sql` dosyasını phpMyAdmin veya MySQL komut satırından import edin:

```bash
mysql -u kullanici_adi -p arduino_monitor < database.sql
```

3. Veya phpMyAdmin üzerinden:
   - Veritabanını seçin
   - "Import" sekmesine tıklayın
   - `database.sql` dosyasını seçin ve çalıştırın

### 4. Veritabanı Bağlantı Ayarları

`config/database.php` dosyasını düzenleyin:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'arduino_monitor');  // Veritabanı adı
define('DB_USER', 'kullanici_adi');    // MySQL kullanıcısı
define('DB_PASS', 'sifre');            // MySQL şifresi
```

### 5. Web Sitesine Erişim

- Ana sayfa: `http://siteniz.com/`
- Config sayfası: `http://siteniz.com/config.php`

## Config Sayfası Kullanımı

Config sayfasından şunları yapabilirsiniz:

### Site Ayarları
- **Site Başlığı**: Tarayıcı sekmesinde görünen başlık
- **Yenileme Aralığı**: Dashboard'un kaç milisaniye aralıkla yenileneceği (5000 = 5 saniye)
- **Grafikteki Veri Sayısı**: Her modül için gösterilecek son kaç kayıt
- **API Anahtarı**: Arduino'nun veri gönderirken kullanacağı güvenlik anahtarı (boş bırakılırsa kontrolsüz)
- **Maksimum Kayıt Sayısı**: Veritabanında saklanacak maksimum kayıt (eski kayıtlar otomatik silinir)

### Modül Ekleme
1. "Yeni Modül Ekle" bölümünü doldurun:
   - **Modül Adı**: Örn "Sıcaklık Sensörü"
   - **Slug**: URL dostu kısa ad (örn: "sicaklik") - benzersiz olmalı
   - **Birim**: Örn "°C", "%", "hPa"
   - **İkon**: Gösterilecek ikon seçimi
   - **Renk**: Grafik rengi
   - **Arduino Pin**: Sensörün bağlı olduğu pin
   - **Min/Max Değerler**: Normal çalışma aralığı (isteğe bağlı)

2. "Modül Ekle" butonuna tıklayın

### Modül Yönetimi
- **Aktif/Pasif**: Modülü geçici olarak devre dışı bırakabilirsiniz
- **Sil**: Modülü tamamen silebilirsiniz (dikkat: kayıtlar da silinir)

## Arduino Kurulumu

### Gereksinimler
- ESP8266 (NodeMCU) veya ESP32 kartı
- Sıcaklık sensörü (LM35, DHT22 vb.)
- Arduino IDE

### Arduino Kodunu Yükleme

1. `arduino_example.ino` dosyasını Arduino IDE ile açın
2. Aşağıdaki ayarları düzenleyin:

```cpp
// WiFi ayarları
const char* ssid = "WIFI_ADINIZ";
const char* password = "WIFI_SIFRENIZ";

// Sunucu ayarları
const char* serverUrl = "http://siteniz.com/api/post_data.php";
const char* apiKey = "";  // Config'den aldığınız anahtar

// Modül slug'ı
const char* moduleSlug = "sicaklik";
```

3. Kart seçimi: Araçlar > Kart > ESP8266 Boards (veya ESP32)
4. Doğru portu seçin
5. "Yükle" butonuna tıklayın
6. Serial Monitör'ü açın (115200 baud) ve çıktıyı izleyin

### Sensör Bağlantıları

#### LM35 Sıcaklık Sensörü
```
LM35    →  ESP8266/ESP32
VCC     →  3.3V
GND     →  GND
OUT     →  A0
```

#### DHT22 Sıcaklık/Nem Sensörü
```
DHT22   →  ESP8266/ESP32
VCC     →  3.3V
GND     →  GND
DATA    →  Pin 2 (veya istediğiniz dijital pin)
```

## API Kullanımı

### Veri Gönderme (Arduino → Sunucu)

**Endpoint**: `POST /api/post_data.php`

**JSON Formatı**:
```json
{
  "module_slug": "sicaklik",
  "value": 25.50,
  "api_key": "sizin_anahtariniz",
  "raw": {
    "extra_data": "opsiyonel"
  }
}
```

**Form Verisi ile**:
```
POST /api/post_data.php?slug=sicaklik&value=25.50&api_key=xxx
```

### Veri Okuma (Sunucu → Tarayıcı)

**Endpoint**: `GET /api/get_data.php`

**Parametreler**:
- `module`: Modül slug'ı (örn: "sicaklik")
- `limit`: Kaç kayıt getirilsin (varsayılan: 20)
- `from`: Başlangıç tarihi (Y-m-d H:i:s)
- `to`: Bitiş tarihi

**Örnek İstek**:
```
GET /api/get_data.php?module=sicaklik&limit=50
```

**Yanıt**:
```json
{
  "success": true,
  "data": [
    {
      "module": {
        "id": 1,
        "name": "Sıcaklık",
        "slug": "sicaklik",
        "unit": "°C",
        "color": "#e74c3c"
      },
      "readings": [
        {"value": 25.5, "time": "2024-01-15 14:30:00"},
        ...
      ],
      "latest": {"value": 25.5, "time": "2024-01-15 14:30:00"}
    }
  ]
}
```

## Sorun Giderme

### Arduino veri gönderemiyor
1. WiFi bağlantısını kontrol edin (Serial Monitör'de IP adresini görün)
2. `serverUrl` doğru mu kontrol edin
3. API anahtarını kontrol edin (config.php'den kopyalayın)
4. Sunucuda `api/post_data.php` erişilebilir mi test edin

### Dashboard verileri göstermiyor
1. Tarayıcı konsolunu açın (F12) ve hataları kontrol edin
2. `api/get_data.php`'yi tarayıcıda açıp sonucu kontrol edin
3. Veritabanında kayıt var mı kontrol edin (phpMyAdmin)

### Veritabanı hatası
1. `config/database.php` ayarlarının doğru olduğunu kontrol edin
2. MySQL servisinin çalıştığından emin olun
3. Kullanıcının veritabanına erişim izni var mı kontrol edin

## Güvenlik Önerileri

1. **API Anahtarı Kullanın**: Config'den güçlü bir API anahtarı belirleyin
2. **HTTPS Kullanın**: Hosting sağlayıcınızdan SSL sertifikası alın
3. **Admin Şifresi**: Config sayfasına giriş sistemi ekleyin (ileride eklenebilir)
4. **Rate Limiting**: Sunucuda API istek sınırlaması yapın
5. **Düzenli Yedekleme**: Veritabanını düzenli olarak yedekleyin

## Geliştirme Önerileri

- [ ] Kullanıcı giriş sistemi (JWT veya Session)
- [ ] E-posta bildirimleri (sınır aşımı durumunda)
- [ ] Daha fazla grafik türü (bar, pie, vs.)
- [ ] Veri dışa aktarma (CSV, Excel)
- [ ] Mobil uyumlu tema
- [ ] Çoklu dil desteği
- [ ] Alarm sistemi (min/max değerler aşıldığında)

## Lisans

Bu proje MIT lisansı ile sunulmaktadır.

## İletişim

Sorularınız için issue açabilir veya yorum yapabilirsiniz.

---

**NOT**: Bu sistem eğitim ve prototipleme amaçlıdır. Üretim ortamında kullanmadan önce güvenlik önlemlerini almanız önerilir.
