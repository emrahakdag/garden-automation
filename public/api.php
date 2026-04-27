<?php
/**
 * Garden-OS API Endpoint
 * Cihazlardan gelen verileri alan ve komut gönderen ana uç nokta
 *
 * Usage:
 *   POST /api.php - Veri göndermek için (sensor_data, motor_control)
 *   GET  /api.php?action=latest - Son değerleri almak için
 *   GET  /api.php?action=history&node_id=node_01&metric=temperature&hours=24 - Tarihsel veri
 */

header('Content-Type: application/json; charset=utf-8');

// CORS (güvenlik ayarları settings.json'dan yüklenir)
$config = json_decode(file_get_contents(__DIR__ . '/../config/settings.json'), true);
if ($config['security']['allow_cors']) {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-API-Key');
}
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ---- Veritabanı Bağlantısı (DB kurulduğunda aktif olur) ----
function getDB() {
    // DB bağlantı bilgileri .env dosyasından okunacak (şimdilik placeholder)
    // $db = new PDO('mysql:host=localhost;dbname=garden_os;charset=utf8mb4', DB_USER, DB_PASS);
    // return $db;
    return null; // TODO: DB bağlantısı kurulduktan sonra aktif edilecek
}

// ---- Yardımcı Fonksiyonlar ----
function jsonResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function verifyApiKey($config) {
    $key = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? null;
    if (!$key) return false;
    // TODO: Güvenlik iyileştirmesi - hash karşılaştırması kullanılmalı
    return $key === $config['security']['api_secret_key'];
}

function logToDB($sql, $params = []) {
    // TODO: DB bağlantısı hazırken aktif edilecek
    return false;
}

// ---- GET Handle ----
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? 'status';

    switch ($action) {
        case 'status':
            // Sistem durumu bilgisi
            jsonResponse([
                'status' => 'ok',
                'system' => $config['system']['name'] . ' v' . $config['system']['version'],
                'php_version' => phpversion(),
                'server_time' => date('Y-m-d H:i:s'),
                'timezone' => date_default_timezone_get()
            ]);
            break;

        case 'latest':
            // Tüm sensörlerin son değerleri
            // TODO: DB hazır olunca sensor_latest tablosundan çekilecek
            jsonResponse([
                'status' => 'ok',
                'data' => [
                    'node_01' => [
                        'location' => 'Sera',
                        'temperature' => ['value' => 24.5, 'unit' => '°C', 'at' => '2026-04-27 15:30:00'],
                        'humidity' => ['value' => 62.0, 'unit' => '%', 'at' => '2026-04-27 15:30:00']
                    ],
                    'node_02' => [
                        'location' => 'Su Deposu',
                        'water_level' => ['value' => 85.0, 'unit' => 'cm', 'at' => '2026-04-27 15:30:00']
                    ],
                    'node_03' => [
                        'location' => 'Bahçe',
                        'temperature' => ['value' => 22.0, 'unit' => '°C', 'at' => '2026-04-27 15:30:00'],
                        'humidity' => ['value' => 55.0, 'unit' => '%', 'at' => '2026-04-27 15:30:00']
                    ]
                ],
                'note' => 'Demo veri - DB bağlantısı henüz kurulmadı'
            ]);
            break;

        case 'history':
            $nodeId = $_GET['node_id'] ?? null;
            $metric = $_GET['metric'] ?? 'temperature';
            $hours = min((int)($_GET['hours'] ?? 24), 168); // Max 7 gün

            // TODO: DB hazır olunca sensor_readings tablosundan çekilecek
            $points = [];
            $now = time();
            for ($i = $hours; $i >= 0; $i -= 2) {
                $base = ['node_01' => 24, 'node_03' => 22][$nodeId] ?? 23;
                $points[] = [
                    'time' => date('H:i', $now - ($i * 3600)),
                    'value' => round(($base ?? 23) + (sin($i * 0.3) * 3), 1)
                ];
            }

            jsonResponse([
                'status' => 'ok',
                'node_id' => $nodeId,
                'metric' => $metric,
                'hours' => $hours,
                'data' => $points,
                'note' => 'Demo veri - DB bağlantısı henüz kurulmadı'
            ]);
            break;

        case 'nodes':
            // Kayıtlı düğümler
            jsonResponse([
                'status' => 'ok',
                'nodes' => $config['nodes']
            ]);
            break;

        default:
            jsonResponse(['error' => 'Bilinmeyen action: ' . $action], 400);
    }
    exit;
}

// ---- POST Handle ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        jsonResponse(['error' => 'Geçersiz JSON'], 400);
    }

    $action = $input['action'] ?? '';

    // Cihaz gönderimleri API key gerektirmez, dış API için gerekli
    // (Motor kontrolü gibi kritik komutlar için key kontrolü: verifyApiKey($config))

    switch ($action) {
        // ---- SENSÖR VERİSİ GÖNDER ----
        case 'sensor_data':
            $nodeId = $input['node_id'] ?? null;
            $data = $input['data'] ?? null; // ['temperature' => 25.5, 'humidity' => 60]
            $rssi = $input['rssi'] ?? null;

            if (!$nodeId || !$data) {
                jsonResponse(['error' => 'node_id ve data gerekli'], 400);
            }

            // Eşik kontrolü ve log
            $node = null;
            foreach ($config['nodes'] as $n) {
                if ($n['id'] === $nodeId) { $node = $n; break; }
            }

            $alerts = [];
            if ($node && isset($node['alert_thresholds'])) {
                foreach ($data as $metric => $value) {
                    if (isset($node['alert_thresholds'][$metric])) {
                        $t = $node['alert_thresholds'][$metric];
                        if ($value < $t['min'] || $value > $t['max']) {
                            $severity = ($value < ($t['min'] * 0.5) || $value > ($t['max'] * 1.3)) ? 'critical' : 'warning';
                            $alerts[] = [
                                'metric' => $metric,
                                'value' => $value,
                                'threshold' => $t,
                                'severity' => $severity
                            ];
                        }
                    }
                }
            }

            // TODO: DB'ye yaz - sensor_readings + sensor_latest güncelle
            // logToDB("INSERT INTO sensor_readings ...", [...]);
            // logToDB("UPDATE sensor_latest SET ...", [...]);

            jsonResponse([
                'status' => 'ok',
                'message' => 'Veri alındı',
                'node_id' => $nodeId,
                'metrics' => array_keys($data),
                'received_at' => date('Y-m-d H:i:s'),
                'alerts' => $alerts
            ]);
            break;

        // ---- MOTOR / EKİPMAN KONTROL ----
        case 'motor_control':
            $password = $input['password'] ?? '';
            $motorAction = $input['action_value'] ?? null; // 'ON', 'OFF', 'TOGGLE'
            $duration = $input['duration'] ?? $config['modules']['motor_control']['default_run_duration'];

            if ($password !== $config['security']['motor_password']) {
                jsonResponse(['error' => 'Geçersiz şifre'], 401);
            }

            if (!in_array($motorAction, ['ON', 'OFF', 'TOGGLE'])) {
                jsonResponse(['error' => 'Geçersiz aksiyon: ON, OFF veya TOGGLE olmalı'], 400);
            }

            $effectiveAction = ($motorAction === 'TOGGLE') ? 'ON' : $motorAction;

            // TODO:
            // 1. control_log tablosuna kaydet
            // 2. MQTT/webhook ile cihazlara bildir (veya NodeMCU poll edebilir)
            // 3. Eğer duration varsa, cron veya background job ile OTOMATIK KAPATMA planla

            jsonResponse([
                'status' => 'ok',
                'message' => 'Motor komutu gönderildi',
                'action' => $effectiveAction,
                'planned_duration' => $duration . ' saniye',
                'executed_at' => date('Y-m-d H:i:s')
            ]);
            break;

        // ---- Cihaz Kayıt / Kalibrasyon ----
        case 'device_register':
            $nodeId = $input['node_id'] ?? null;
            $hwVersion = $input['hardware_version'] ?? null;
            $fwVersion = $input['firmware_version'] ?? null;

            if (!$nodeId) {
                jsonResponse(['error' => 'node_id gerekli'], 400);
            }

            // TODO: nodes tablosunda güncelle
            // logToDB("UPDATE nodes SET last_seen_at=NOW(), firmware_version=? WHERE id=?", [$fwVersion, $nodeId]);

            jsonResponse([
                'status' => 'ok',
                'message' => 'Cihaz kaydedildi',
                'node_id' => $nodeId,
                'config' => $config,
                'server_time' => date('Y-m-d H:i:s')
            ]);
            break;

        default:
            jsonResponse([
                'error' => 'Bilinmeyen POST action: ' . $action,
                'available_actions' => ['sensor_data', 'motor_control', 'device_register']
            ], 400);
    }
    exit;
}

jsonResponse(['error' => 'Yalnızca GET ve POST desteklenir'], 405);
