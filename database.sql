-- Arduino Monitoring System Database Schema
-- MySQL veritabanı yapısı

-- Veritabanını oluştur (gerekirse)
-- CREATE DATABASE arduino_monitor;
-- USE arduino_monitor;

-- Modüller tablosu: Farklı sensör tiplerini tanımlar
CREATE TABLE IF NOT EXISTS modules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL COMMENT 'Modül adı (örn: Sıcaklık, Nem, Basınç)',
    slug VARCHAR(100) NOT NULL UNIQUE COMMENT 'URL dostu kısa ad',
    unit VARCHAR(20) DEFAULT '' COMMENT 'Birim (°C, %, hPa vb.)',
    icon VARCHAR(50) DEFAULT 'thermometer' COMMENT 'İkon adı',
    color VARCHAR(20) DEFAULT '#3498db' COMMENT 'Grafik/renk kodu',
    min_value DECIMAL(10,2) DEFAULT NULL COMMENT 'Normal minimum değer',
    max_value DECIMAL(10,2) DEFAULT NULL COMMENT 'Normal maximum değer',
    pin INT DEFAULT NULL COMMENT 'Arduino pin numarası',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_slug (slug),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Okumalar tablosu: Arduino'dan gelen veriler
CREATE TABLE IF NOT EXISTS readings (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    module_id INT NOT NULL,
    value DECIMAL(10,4) NOT NULL COMMENT 'Sensör değeri',
    raw_data TEXT COMMENT 'Ham veri (JSON)',
    ip_address VARCHAR(45) COMMENT 'Gönderen IP',
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE CASCADE,
    INDEX idx_module_time (module_id, recorded_at),
    INDEX idx_recorded (recorded_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ayarlar tablosu: Sistem konfigürasyonu
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    setting_type ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
    description VARCHAR(255),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Kullanıcılar tablosu (opsiyonel - giriş için)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    email VARCHAR(100),
    role ENUM('admin', 'viewer') DEFAULT 'viewer',
    is_active BOOLEAN DEFAULT TRUE,
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Varsayılan ayarları ekle
INSERT INTO settings (setting_key, setting_value, setting_type, description) VALUES
('site_title', 'Arduino Monitor', 'string', 'Site başlığı'),
('site_description', 'Arduino sensör verilerini izleme sistemi', 'string', 'Site açıklaması'),
('refresh_interval', '5000', 'number', 'Veri yenileme aralığı (ms)'),
('chart_points', '20', 'number', 'Grafikte gösterilecek son veri sayısı'),
('api_key', '', 'string', 'Arduino API anahtarı (boş ise kontrolsüz)'),
('timezone', 'Europe/Istanbul', 'string', 'Zaman dilimi'),
('date_format', 'd.m.Y H:i:s', 'string', 'Tarih formatı'),
('max_readings', '10000', 'number', 'Saklanacak maksimum kayıt sayısı')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- Örnek modüller ekle
INSERT INTO modules (name, slug, unit, icon, color, min_value, max_value, pin) VALUES
('Sıcaklık', 'sicaklik', '°C', 'thermometer', '#e74c3c', 15, 35, 0),
('Nem', 'nem', '%', 'droplet', '#3498db', 30, 80, 1),
('Basınç', 'basinc', 'hPa', 'gauge', '#9b59b6', 980, 1050, 2)
ON DUPLICATE KEY UPDATE name = VALUES(name);
