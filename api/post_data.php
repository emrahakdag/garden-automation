<?php
/**
 * Arduino Veri Gönderme API'si
 *
 * Arduino bu endpoint'e HTTP POST isteği gönderir
 *
 * Örnek istek:
 * POST /api/post_data.php
 * Content-Type: application/json
 * {
 *   "api_key": "your_key_here",  // eğer ayarlarda tanımlıysa
 *   "module_slug": "sicaklik",
 *   "value": 25.5,
 *   "raw": {"temp": 25.5, "hum": 60}  // opsiyonel
 * }
 *
 * Veya basit form verisi:
 * POST /api/post_data.php?slug=sicaklik&value=25.5
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../config/database.php';

// API key kontrolü (eğer ayarlarda tanımlıysa)
$api_key = getSetting('api_key', '');
if (!empty($api_key)) {
    $headers = getallheaders();
    $provided_key = $_POST['api_key'] ?? $headers['X-API-Key'] ?? '';

    // JSON body kontrolü
    $input = json_decode(file_get_contents('php://input'), true);
    if ($input && isset($input['api_key'])) {
        $provided_key = $input['api_key'];
    }

    if ($provided_key !== $api_key) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Geçersiz API anahtarı']);
        exit;
    }
}

// Veriyi al (JSON veya POST)
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

// GET isteği ile basit test
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $input = $_GET;
}

// Gerekli alanları kontrol et
$module_slug = $input['module_slug'] ?? $input['slug'] ?? null;
$value = $input['value'] ?? null;

if (!$module_slug || $value === null) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'module_slug ve value alanları gerekli']);
    exit;
}

try {
    $pdo = getDB();

    // Modülü bul
    $stmt = $pdo->prepare("SELECT id FROM modules WHERE slug = ? AND is_active = TRUE");
    $stmt->execute([$module_slug]);
    $module = $stmt->fetch();

    if (!$module) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Modül bulunamadı: ' . $module_slug]);
        exit;
    }

    // Veriyi kaydet
    $raw_data = isset($input['raw']) ? json_encode($input['raw']) : null;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;

    $stmt = $pdo->prepare("INSERT INTO readings (module_id, value, raw_data, ip_address) VALUES (?, ?, ?, ?)");
    $stmt->execute([$module['id'], $value, $raw_data, $ip_address]);

    $reading_id = $pdo->lastInsertId();

    // Maksimum kayıt sayısını kontrol et ve gerekiyorsa eski kayıtları sil
    $max_readings = (int)getSetting('max_readings', 10000);
    $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM readings");
    $stmt->execute();
    $count = $stmt->fetch()['cnt'];

    if ($count > $max_readings) {
        $to_delete = $count - $max_readings;
        $pdo->exec("DELETE FROM readings ORDER BY recorded_at ASC LIMIT $to_delete");
    }

    echo json_encode([
        'success' => true,
        'message' => 'Veri kaydedildi',
        'reading_id' => $reading_id,
        'timestamp' => date('Y-m-d H:i:s')
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Sunucu hatası: ' . $e->getMessage()]);
}
