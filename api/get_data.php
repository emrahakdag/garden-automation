<?php
/**
 * Veri Okuma API'si
 *
 * Dashboard ve grafikler için veri getirir
 *
 * Parametreler:
 * - module: modül slug'ı (opsiyonel, yoksa tüm modüller)
 * - limit: kaç kayıt getirilsin (varsayılan: 20)
 * - from: başlangıç tarihi (Y-m-d H:i:s)
 * - to: bitiş tarihi
 * - interval: gruplama aralığı (minute, hour, day)
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../config/database.php';

$pdo = getDB();

// Parametreleri al
$module_slug = $_GET['module'] ?? null;
$limit = min((int)($_GET['limit'] ?? 20), 1000); // Maksimum 1000
$from = $_GET['from'] ?? null;
$to = $_GET['to'] ?? null;
$interval = $_GET['interval'] ?? null;

try {
    // Modülleri getir
    if ($module_slug) {
        $stmt = $pdo->prepare("SELECT * FROM modules WHERE slug = ? AND is_active = TRUE");
        $stmt->execute([$module_slug]);
        $modules = [$stmt->fetch()];
    } else {
        $stmt = $pdo->query("SELECT * FROM modules WHERE is_active = TRUE ORDER BY id");
        $modules = $stmt->fetchAll();
    }

    $result = [];

    foreach ($modules as $module) {
        if (!$module) continue;

        // Okumaları getir
        $sql = "SELECT r.value, r.recorded_at
                FROM readings r
                WHERE r.module_id = ?
                AND 1=1";
        $params = [$module['id']];

        if ($from) {
            $sql .= " AND r.recorded_at >= ?";
            $params[] = $from;
        }
        if ($to) {
            $sql .= " AND r.recorded_at <= ?";
            $params[] = $to;
        }

        $sql .= " ORDER BY r.recorded_at DESC LIMIT ?";
        $params[] = $limit;

        $stmt = $pdo->prepare($sql);
        foreach ($params as $i => $param) {
            $stmt->bindValue($i + 1, $param, is_int($param) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        $readings = $stmt->fetchAll();

        // Ters çevir (eski->yeni sıralama için)
        $readings = array_reverse($readings);

        $result[] = [
            'module' => [
                'id' => $module['id'],
                'name' => $module['name'],
                'slug' => $module['slug'],
                'unit' => $module['unit'],
                'icon' => $module['icon'],
                'color' => $module['color'],
                'min_value' => $module['min_value'],
                'max_value' => $module['max_value']
            ],
            'readings' => array_map(function($r) {
                return [
                    'value' => (float)$r['value'],
                    'time' => $r['recorded_at']
                ];
            }, $readings),
            'latest' => !empty($readings) ? [
                'value' => (float)end($readings)['value'],
                'time' => end($readings)['time']
            ] : null
        ];
    }

    // İstatistikler (varsa)
    if (isset($_GET['stats']) && $module_slug) {
        $stmt = $pdo->prepare("
            SELECT
                MIN(value) as min_val,
                MAX(value) as max_val,
                AVG(value) as avg_val,
                COUNT(*) as total
            FROM readings
            WHERE module_id = (SELECT id FROM modules WHERE slug = ?)
            AND recorded_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        $stmt->execute([$module_slug]);
        $stats = $stmt->fetch();
        $result['stats'] = $stats;
    }

    echo json_encode([
        'success' => true,
        'data' => $result,
        'server_time' => date('Y-m-d H:i:s')
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
