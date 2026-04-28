<?php
/**
 * src/api/motor.php – Motor kontrol API'si
 *
 * Endpoint                   | Method | Açıklama
 * -------------------------- | ------ | ----------------------------------
 * /api/motor                 | POST   | Motoru başlatır (şifreli)
 * /api/motor/start           | POST   | Motoru başlatır (yukarı ile aynı)
 * /api/motor/stop            | POST   | Motoru durdurur (şifreli)
 * /api/motor/status          | GET    | Son motor durumunu döndürür
 */

require '../config.php';

header('Content-Type: application/json');

$action = isset($_GET['action']) ? $_GET['action'] : '';
$isApiEndpoint = ($action === '' && $_SERVER['REQUEST_METHOD'] === 'POST');
if ($isApiEndpoint) $action = 'start'; // POST /api/motor -> start

// --- durum sorgusu: GET /api/motor/status ---
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'status') {
    $stmt = $db->query("SELECT status, started_at FROM motor_log ORDER BY id DESC LIMIT 1");
    $row = $stmt->fetch();
    if ($row) {
        echo json_encode([
            'current' => $row['status'],
            'started_at' => $row['started_at']
        ]);
    } else {
        echo json_encode(['current' => 'never']);
    }
    exit;
}

// --- Motor başlat / durdur: POST /api/motor ?action=start|stop ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($action === 'start' || $action === 'stop')) {
    $payload = json_decode(file_get_contents('php://input'), true);

    if (!isset($payload['password'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Password required']);
        exit;
    }

    if ($payload['password'] !== $motorPassword) {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }

    $now = date('Y-m-d H:i:s');
    $status = ($action === 'start') ? 'on' : 'off';

    if ($action === 'start') {
        $stmt = $db->prepare(
            'INSERT INTO motor_log (status, started_at, operator) VALUES (?, ?, ?)'
        );
        $stmt->execute([$status, $now, 'api']);
        echo json_encode(['status' => 'motor_started']);
    } else {
        // Son açık motor kaydını bul ve durdurma zamanını ekle
        $stmt = $db->prepare(
            'UPDATE motor_log SET stopped_at = ?, status = ? WHERE status = "on" ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([$now, 'off']);
        echo json_encode(['status' => 'motor_stopped']);
    }

    exit;
}

// Bilinmeyen istek
http_response_code(405);
echo json_encode(['error' => 'Method not allowed or unknown action']);
?>
