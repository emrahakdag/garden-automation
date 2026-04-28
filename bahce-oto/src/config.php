<?php
/**
 * src/config.php – Veritabanı bağlantısı ve motor şifresi
 *
 * Ortam değişkenleri (.env dosyasından) okunur.
 * Coolify ortamında environment variables otomatik olarak yüklenir.
 */

$db = new PDO(
    "mysql:host={$_ENV['DB_HOST']};dbname={$_ENV['DB_NAME']};charset=utf8mb4",
    $_ENV['DB_USER'],
    $_ENV['DB_PASS']
);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$motorPassword = $_ENV['MOTOR_PASSWORD'];
?>
