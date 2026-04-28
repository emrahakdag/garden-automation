-- Bahçe Otomasyon Veritabanı Şeması

CREATE DATABASE IF NOT EXISTS bahce_oto
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE bahce_oto;

-- Sensör verileri
CREATE TABLE sensor_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    device_id VARCHAR(64) NOT NULL,
    temperature FLOAT,
    humidity FLOAT,
    ts TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_device (device_id),
    INDEX idx_ts (ts)
) ENGINE=InnoDB;

-- Motor durumu kayıtları
CREATE TABLE motor_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    status VARCHAR(32) NOT NULL COMMENT 'on, off, error',
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    stopped_at TIMESTAMP NULL,
    operator VARCHAR(64) DEFAULT 'api'
) ENGINE=InnoDB;

-- Su tankı doluluk kayıtları
CREATE TABLE tank_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    level_percent FLOAT,
    level_liters FLOAT,
    ts TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ts (ts)
) ENGINE=InnoDB;
