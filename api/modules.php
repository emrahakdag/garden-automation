<?php
/**
 * Modül Yönetim API'si
 *
 * GET: Tüm modülleri listele
 * POST: Yeni modül ekle
 * PUT: Modül güncelle
 * DELETE: Modül sil (soft delete - is_active=false)
 *
 * Sadece admin erişimi için korumalı olmalı
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../config/database.php';

$pdo = getDB();
$method = $_SERVER['REQUEST_METHOD'];

// Basit kimlik doğrulama (gerçek projede token/JWT kullanın)
function checkAuth() {
    // Bu fonksiyonu gerekirse geliştirin
    // Örnek: API key veya session kontrolü
    return true; // Şimdilik herkese açık
}

try {
    switch ($method) {
        case 'GET':
            // Modülleri getir
            $active_only = isset($_GET['active_only']) ? (bool)$_GET['active_only'] : false;

            if ($active_only) {
                $stmt = $pdo->query("SELECT * FROM modules WHERE is_active = TRUE ORDER BY id");
            } else {
                $stmt = $pdo->query("SELECT * FROM modules ORDER BY id");
            }

            $modules = $stmt->fetchAll();
            echo json_encode(['success' => true, 'data' => $modules]);
            break;

        case 'POST':
            // Yeni modül ekle
            if (!checkAuth()) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Yetkisiz erişim']);
                exit;
            }

            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) $input = $_POST;

            $required = ['name', 'slug'];
            foreach ($required as $field) {
                if (empty($input[$field])) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => "$field alanı gerekli"]);
                    exit;
                }
            }

            // Slug benzersiz mi kontrol et
            $stmt = $pdo->prepare("SELECT id FROM modules WHERE slug = ?");
            $stmt->execute([$input['slug']]);
            if ($stmt->fetch()) {
                http_response_code(409);
                echo json_encode(['success' => false, 'error' => 'Bu slug zaten kullanımda']);
                exit;
            }

            $stmt = $pdo->prepare("
                INSERT INTO modules (name, slug, unit, icon, color, min_value, max_value, pin, is_active)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $input['name'],
                $input['slug'],
                $input['unit'] ?? '',
                $input['icon'] ?? 'thermometer',
                $input['color'] ?? '#3498db',
                $input['min_value'] ?? null,
                $input['max_value'] ?? null,
                $input['pin'] ?? null,
                $input['is_active'] ?? true
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Modül eklendi',
                'module_id' => $pdo->lastInsertId()
            ]);
            break;

        case 'PUT':
        case 'PATCH':
            // Modül güncelle
            if (!checkAuth()) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Yetkisiz erişim']);
                exit;
            }

            $input = json_decode(file_get_contents('php://input'), true);
            $module_id = $_GET['id'] ?? $input['id'] ?? null;

            if (!$module_id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Modül ID gerekli']);
                exit;
            }

            $fields = [];
            $params = [];

            $allowed_fields = ['name', 'slug', 'unit', 'icon', 'color', 'min_value', 'max_value', 'pin', 'is_active'];
            foreach ($allowed_fields as $field) {
                if (isset($input[$field])) {
                    $fields[] = "$field = ?";
                    $params[] = $input[$field];
                }
            }

            if (empty($fields)) {
                echo json_encode(['success' => false, 'error' => 'Güncellenecek alan yok']);
                exit;
            }

            $params[] = $module_id;
            $sql = "UPDATE modules SET " . implode(', ', $fields) . " WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            echo json_encode(['success' => true, 'message' => 'Modül güncellendi']);
            break;

        case 'DELETE':
            // Modülü pasif yap (silme, sadece pasif et)
            if (!checkAuth()) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Yetkisiz erişim']);
                exit;
            }

            $module_id = $_GET['id'] ?? null;
            if (!$module_id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Modül ID gerekli']);
                exit;
            }

            $stmt = $pdo->prepare("UPDATE modules SET is_active = FALSE WHERE id = ?");
            $stmt->execute([$module_id]);

            echo json_encode(['success' => true, 'message' => 'Modül pasif edildi']);
            break;

        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Metod desteklenmiyor']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
