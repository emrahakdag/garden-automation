<?php
/**
 * public/api/sensor.php – Sensör verisi API'si
 *
 * Endpoint                      | Method | Açıklama
 * ----------------------------- | ------ | ---------------------------------------
 * /api/sensor                   | POST   | Sensör verisi kaydet
 * /api/sensor/latest            | GET    | Son okunan değerleri döndür
 * /api/sensor/device/{device_id}| GET    | Belirli cihazın son verisini döndür
 */

require '../config.php';

header('Content-Type: application/json');

$action = isset($_GET['action']) ? $_GET['action'] : '';
$url = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$parts = explode('/', trim($url, '/'));

// --- GET /api/sensor/latest ---
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'latest') {
    $stmt = $db->query(
        "SELECT device_id, temperature, humidity, ts FROM sensor_log ORDER BY id DESC LIMIT 1"
    );
    $row = $stmt->fetch();
    echo json_encode($row ?: ['message' => 'No data yet']);
    exit;
}

// --- GET /api/sensor/device/{device_id} ---
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($parts[3]) && $parts[1] === 'sensor') {
    $deviceId = $parts[3];
    $stmt = $db->prepare(
        'SELECT device_id, temperature, humidity, ts FROM sensor_log WHERE device_id = ? ORDER BY id DESC LIMIT 1'
    );
    $stmt->execute([$deviceId]);
    $row = $stmt->fetch();
    echo json_encode($row ?: ['message' => 'No data for device: ' . $deviceId]);
    exit;
}

// --- POST /api/sensor – Sensör verisi kaydet ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['device_id'], $data['temperature'], $data['humidity'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid data. Required: device_id, temperature, humidity']);
        exit;
    }

    $stmt = $db->prepare(
        'INSERT INTO sensor_log (device_id, temperature, humidity, ts) VALUES (?, ?, ?, NOW())'
      );
    $stmt->execute([
        $data['device_id'],
        $data['temperature'],
        $data['humidity']
    ]);

    echo json_encode([
        'status' => 'ok',
        'id' => $db->lastInsertId()
    ]);
    exit;
}

// Bilinmeyen istek
http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
?>
