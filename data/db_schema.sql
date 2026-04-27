-- ============================================
-- Garden-OS Veritabanı Şeması
-- Bahçe Otomasyon Sistemi
-- ============================================

CREATE DATABASE IF NOT EXISTS `garden_os`
    DEFAULT CHARACTER SET utf8mb4
    DEFAULT COLLATE utf8mb4_unicode_ci;

USE `garden_os`;

-- ------------------------------------------
-- SENSÖR DÜĞÜM TABLOSU
-- Fiziksel cihazların tanımlandığı tablo
-- ------------------------------------------
CREATE TABLE `nodes` (
    `id` VARCHAR(20) PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `type` VARCHAR(50) NOT NULL COMMENT 'DHT22, HC-SR04, DHT22_HCSR04',
    `location` VARCHAR(100) NOT NULL COMMENT 'Sera, Su Deposu, Bahçe',
    `description` TEXT DEFAULT NULL,
    `firmware_version` VARCHAR(20) DEFAULT '0.0.0',
    `is_active` TINYINT(1) DEFAULT 1,
    `last_seen_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_location (`location`),
    INDEX idx_active (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------
-- SENSÖR OKUMALARI (Zaman Serisi)
-- Her sensör okuması bu tabloda saklanır
-- ------------------------------------------
CREATE TABLE `sensor_readings` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `node_id` VARCHAR(20) NOT NULL,
    `metric` VARCHAR(50) NOT NULL COMMENT 'temperature, humidity, water_level, soil_moisture',
    `value` DECIMAL(10,2) NOT NULL,
    `unit` VARCHAR(20) NOT NULL COMMENT '°C, %, cm',
    `raw_value` DECIMAL(10,2) DEFAULT NULL COMMENT 'Sensörden gelen ham değer',
    `signal_strength` INT DEFAULT NULL COMMENT 'RSSI (Wi-Fi sinyal gücü)',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_node_metric (`node_id`, `metric`),
    INDEX idx_created_at (`created_at`),
    INDEX idx_node_time (`node_id`, `created_at`),
    FOREIGN KEY (`node_id`) REFERENCES `nodes`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------
-- SON DEĞER ÖZET TABLOSU
-- Hızlı sorgular için güncel değerler
-- ------------------------------------------
CREATE TABLE `sensor_latest` (
    `node_id` VARCHAR(20) NOT NULL,
    `metric` VARCHAR(50) NOT NULL,
    `value` DECIMAL(10,2) NOT NULL,
    `unit` VARCHAR(20) NOT NULL,
    `read_at` DATETIME NOT NULL COMMENT 'Okumanın yapıldığı zaman',
    `is_alert` TINYINT(1) DEFAULT 0,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`node_id`, `metric`),
    INDEX idx_alert (`is_alert`),
    FOREIGN KEY (`node_id`) REFERENCES `nodes`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------
-- MOTOR / EKİPMAN KONTROL LOGU
-- Aç/Kapat komutlarının kayıtları
-- ------------------------------------------
CREATE TABLE `control_log` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `node_id` VARCHAR(20) DEFAULT NULL COMMENT 'İlgili düğüm (NULL: genel)',
    `component` VARCHAR(50) NOT NULL COMMENT 'motor_main, valve_1, valve_2, light',
    `action` ENUM('ON', 'OFF', 'RESTART') NOT NULL,
    `triggered_by` VARCHAR(50) NOT NULL DEFAULT 'manual' COMMENT 'manual, scheduled, automation',
    `duration_planned` INT DEFAULT NULL COMMENT 'Planlanan süre (saniye)',
    `duration_actual` INT DEFAULT NULL COMMENT 'Gerçek süre (saniye)',
    `status` ENUM('success', 'failed', 'timeout') DEFAULT 'success',
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_component (`component`),
    INDEX idx_created_at (`created_at`),
    FOREIGN KEY (`node_id`) REFERENCES `nodes`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------
-- UYARI / ALARM TABLOSU
-- Eşik değer ihlalleri
-- ------------------------------------------
CREATE TABLE `alerts` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `node_id` VARCHAR(20) NOT NULL,
    `metric` VARCHAR(50) NOT NULL,
    `value` DECIMAL(10,2) NOT NULL,
    `threshold_min` DECIMAL(10,2) DEFAULT NULL,
    `threshold_max` DECIMAL(10,2) DEFAULT NULL,
    `severity` ENUM('warning', 'critical') DEFAULT 'warning',
    `message` TEXT NOT NULL,
    `is_acknowledged` TINYINT(1) DEFAULT 0,
    `acknowledged_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_node (`node_id`),
    INDEX idx_severity (`severity`),
    INDEX idx_unack (`is_acknowledged`),
    FOREIGN KEY (`node_id`) REFERENCES `nodes`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------
-- ZAMANLAYICI / SCHEDULE TABLOSU
-- Otomatik aç/kapat planları
-- ------------------------------------------
CREATE TABLE `schedules` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `component` VARCHAR(50) NOT NULL,
    `action` ENUM('ON', 'OFF') NOT NULL,
    `cron_expression` VARCHAR(50) DEFAULT NULL COMMENT 'Linux cron formatı',
    `start_time` TIME DEFAULT NULL COMMENT 'HH:MM formatı',
    `end_time` TIME DEFAULT NULL COMMENT 'HH:MM formatı (opsiyonel)',
    `days` VARCHAR(50) DEFAULT '1,2,3,4,5,6,0' COMMENT '1=Pzt ... 0=Paz',
    `is_active` TINYINT(1) DEFAULT 1,
    `duration` INT DEFAULT NULL COMMENT 'Otomatik kapama süresi (saniye)',
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------
-- DEMO VERİLERİ
-- ------------------------------------------
INSERT INTO `nodes` (`id`, `name`, `type`, `location`, `description`) VALUES
    ('node_01', 'Sera Sensörü', 'DHT22', 'Sera', 'Sera içinde sıcaklık ve nem takibi'),
    ('node_02', 'Su Deposu Sensörü', 'HC-SR04', 'Su Deposu', 'Depo su seviyesi ölçümü'),
    ('node_03', 'Bahçe Sensörü', 'DHT22_HCSR04', 'Bahçe', 'Bahçe sıcaklık, nem ve toprak nem ölçümü');

-- Her düğüm için örnek son değerler
INSERT INTO `sensor_latest` (`node_id`, `metric`, `value`, `unit`, `read_at`) VALUES
    ('node_01', 'temperature', 24.50, '°C', NOW()),
    ('node_01', 'humidity', 62.00, '%', NOW()),
    ('node_02', 'water_level', 85.00, 'cm', NOW()),
    ('node_03', 'temperature', 22.00, '°C', NOW()),
    ('node_03', 'humidity', 55.00, '%', NOW());

-- Örnek zamanlayıcı: Her gün sabah 06:00''da motoru aç
INSERT INTO `schedules` (`name`, `component`, `action`, `start_time`, `days`, `duration`) VALUES
    ('Sabah Sulama', 'motor_main', 'ON', '06:00:00', '1,2,3,4,5,6,0', 3600);
