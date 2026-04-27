# Coolify ile Deploy Etme Rehberi

Bu rehber, Arduino Monitor sistemini VPS'nizdeki Coolify üzerinde nasıl deploy edeceğinizi açıklar.

## Ön Gereksinimler

- Çalışan bir Coolify kurulumu (VPS'nizde)
- Coolify'a erişim (https://your-coolify-domain.com)
- Git repository (GitHub, GitLab, vs.) veya yerel dosyalar

## Yöntem 1: Git Repository ile Deploy (Önerilen)

### 1. Projeyi Git'e Push Edin

Önce tüm dosyaları bir git repository'sine ekleyin:

```bash
# Proje klasöründe
git init
git add .
git commit -m "Initial commit: Arduino Monitor"
git remote add origin https://github.com/kullanici/arduino-monitor.git
git push -u origin main
```

### 2. Coolify'da Yeni Proje Oluşturun

1. **Coolify Paneli** → **Projects** → **Create Project**
2. Proje adı: `arduino-monitor`
3. **Create**

### 3. MySQL Veritabanı Oluşturun

1. Proje içinde: **Add Resource** → **Database** → **MySQL**
2. Ayarlar:
   - **Name**: `arduino-mysql`
   - **MySQL Version**: `8.0` (veya latest)
   - **Database Name**: `arduino_monitor`
   - **User**: `arduino` (veya istediğiniz)
   - **Password**: Güçlü bir şifre belirleyin
3. **Deploy** butonuna tıklayın
4. Deploy tamamlandıktan sonra **Environment Variables** kısmından bağlantı bilgilerini not edin

### 4. PHP Uygulamasını Deploy Edin

1. Proje içinde: **Add Resource** → **Application** → **Public Repository**
2. Repository URL'sini girin: `https://github.com/kullanici/arduino-monitor.git`
3. **Continue**'ye tıklayın

### 5. Uygulama Ayarlarını Yapın

**General Settings:**
- **Name**: `arduino-app`
- **Build Pack**: `Dockerfile` (Dockerfile'ı repo'da olduğu için)
- **Docker Registry**: `Coolify Hub` (veya kendi registry'niz)

**Environment Variables:**
Coolify otomatik olarak MySQL servisine bağlanmak için şu değişkenleri ekleyin:

```
DB_HOST=arduino-mysql  # MySQL servis adı
DB_NAME=arduino_monitor
DB_USER=arduino
DB_PASS=şifreniz
```

**Port:**
- `80` (Dockerfile'da expose edilen port)

**Domains:**
- **Generate Domain** (Coolify otomatik subdomain verecek)
- Veya kendi domaininizi ekleyin: `monitor.siteniz.com`

### 6. Deploy Edin

1. **Deploy** butonuna tıklayın
2. İşlemi **Deployments** sekmesinden izleyin
3. Deploy başarılı olduğunda domain linkine tıklayın

### 7. Veritabanını Import Edin

Yeni oluşturulan MySQL veritabanına `database.sql` dosyasını import edin:

**Seçenek A: phpMyAdmin ile (Coolify'da ekleyerek)**
1. Proje → **Add Resource** → **Service** → **phpMyAdmin**
2. Bağlanacağı MySQL: `arduino-mysql`
3. Deploy ettikten sonra phpMyAdmin'e giriş yapın
4. `arduino_monitor` veritabanını seçin → **Import** → `database.sql`'i seçin

**Seçenek B: MySQL Command Line ile**
```bash
# Coolify terminal veya SSH ile VPS'ye bağlanın
mysql -h arduino-mysql -u arduino -p arduino_monitor < database.sql
```

### 8. Config Sayfasını Ziyaret Edin

- `https://sizin-domain.coolify.com/config.php`
- Site ayarlarını yapın
- Modülleri ekleyin (Sıcaklık, Nem, vb.)

---

## Yöntem 2: Lokal Dosyaları Yükleyerek (Git Olmadan)

Coolify'a dosyaları manuel olarak yüklemek için:

### 1. Dockerfile ile Private Repository

1. Coolify'da **Add Resource** → **Application** → **Private Repository**
2. Repository kaynağını: **Dockerfile** olarak seçin
3. Dosyaları ZIP olarak yükleyin veya Git repository'si olarak ekleyin

### 2. Dosya Yapısı

Kendi bilgisayarınızda şu yapıyı oluşturun:

```
arduino-monitor/
├── Dockerfile
├── database.sql
├── docker-compose.yml (opsiyonel)
├── .htaccess
├── index.php
├── config.php
├── check.php
├── arduino_example.ino
├── README.md
├── COOLIFY_DEPLOY.md
├── api/
│   ├── post_data.php
│   ├── get_data.php
│   └── modules.php
└── config/
    └── database.php
```

### 3. Coolify Private Volume ile

Eğer dosyaları volume olarak bağlamak isterseniz:

1. **Application Settings** → **Volumes**
2. **Add Volume**:
   - **Container Path**: `/var/www/html`
   - **Host Path**: `/your-host-path/arduino-monitor`

---

## Coolify Özel Ayarları

### SSL/HTTPS

Coolify otomatik olarak Let's Encrypt SSL sertifikası sağlar:

1. **Domains** sekmesi
2. **Enable SSL** → **Let's Encrypt**
3. **Save** ve **Deploy**'u tekrar çalıştırın

### Environment Variables (Önemli)

Coolify'da uygulama ayarlarından **Environment Variables** kısmına ekleyin:

| Key | Value | Açıklama |
|-----|-------|-----------|
| `DB_HOST` | `arduino-mysql` | MySQL servis adı |
| `DB_NAME` | `arduino_monitor` | Veritabanı adı |
| `DB_USER` | `arduino` | MySQL kullanıcısı |
| `DB_PASS` | `güçlü_şifre` | MySQL şifresi |

### Health Check

Uygulamanın çalıştığını kontrol etmek için:

1. **Application Settings** → **Health Check**
2. **Endpoint**: `/check.php`
3. **Port**: `80`
4. **Timeout**: `30`

---

## Sorun Giderme

### Deploy Hatası Alırsanız

1. **Logs** sekmesini kontrol edin
2. Yaygın hatalar:
   - **PDO MySQL extension eksik**: Dockerfile'da `docker-php-ext-install pdo pdo_mysql` var mı kontrol edin
   - **Veritabanı bağlanamıyor**: `DB_HOST` değişkeninin MySQL servis adıyla aynı olduğundan emin olun
   - **403 Forbidden**: Apache mod_rewrite etkin mi kontrol edin

### MySQL Bağlantı Hatası

```bash
# Coolify terminalde test edin
ping arduino-mysql
telnet arduino-mysql 3306
```

### Ardunio Veri Gönderemiyor

1. Config'den API anahtarını kontrol edin
2. `post_data.php`'nin URL'sini tarayıcıda test edin
3. Coolify **Network** sekmesinde uygulama ile MySQL'in aynı network'de olduğundan emin olun

---

## Yapılandırma Örneği (.env)

Lokal geliştirme için `.env` dosyası (Coolify otomatik sağlar):

```env
DB_HOST=mysql
DB_NAME=arduino_monitor
DB_USER=arduino
DB_PASS=şifreniz
```

---

## Sonraki Adımlar

1. ✅ Coolify'da MySQL servisini oluşturun
2. ✅ PHP uygulamasını deploy edin
3. ✅ Environment variables'ları ayarlayın
4. ✅ database.sql'i import edin
5. ✅ SSL sertifikasını etkinleştirin
6. ✅ Domain ayarlarını yapın
7. ✅ Config sayfasından modülleri ekleyin
8. ✅ Arduino kodunu düzenleyip yükleyin

Başarılar! 🚀
