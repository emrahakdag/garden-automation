<?php
/**
 * Veritabanı bağlantı ayarları
 * Coolify ortam değişkenlerini kullanır (env vars)
 * Lokalde .env dosyası veya docker-compose ile ayarlanır
 */

// Ortam değişkenlerini al (Coolify otomatik sağlar)
// Fallback: Coolify'da DB_HOST genellikle servis adıdır (mysql)
$db_host = getenv('DB_HOST') ?: (getenv('MYSQL_HOST') ?: 'mysql');
$db_name = getenv('DB_NAME') ?: (getenv('MYSQL_DATABASE') ?: 'arduino_monitor');
$db_user = getenv('DB_USER') ?: (getenv('MYSQL_USER') ?: 'root');
$db_pass = getenv('DB_PASS') ?: (getenv('MYSQL_PASSWORD') ?: '');

define('DB_HOST', $db_host);
define('DB_NAME', $db_name);
define('DB_USER', $db_user);
define('DB_PASS', $db_pass);
define('DB_CHARSET', 'utf8mb4');

// PDO bağlantısı
function getDB() {
    static $pdo = null;

    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            die(json_encode(['error' => 'Veritabanı bağlantı hatası']));
        }
    }

    return $pdo;
}

// Ayarları getir
function getSetting($key, $default = null) {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    return $row ? $row['setting_value'] : $default;
}

// Ayar kaydet/güncelle
function saveSetting($key, $value) {
    $pdo = getDB();
    $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
                           ON DUPLICATE KEY UPDATE setting_value = ?");
    return $stmt->execute([$key, $value, $value]);
}
