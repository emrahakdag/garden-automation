<?php
/**
 * Config Sayfası
 * Site ayarları ve modül yönetimi
 */
require_once 'config/database.php';

$pdo = getDB();
$message = '';
$error = '';

// Form gönderimi işle
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'update_settings':
                    // Site ayarlarını güncelle
                    $settings = [
                        'site_title' => $_POST['site_title'] ?? '',
                        'site_description' => $_POST['site_description'] ?? '',
                        'refresh_interval' => (int)($_POST['refresh_interval'] ?? 5000),
                        'chart_points' => (int)($_POST['chart_points'] ?? 20),
                        'api_key' => $_POST['api_key'] ?? '',
                        'timezone' => $_POST['timezone'] ?? 'Europe/Istanbul',
                        'max_readings' => (int)($_POST['max_readings'] ?? 10000)
                    ];

                    foreach ($settings as $key => $value) {
                        saveSetting($key, $value);
                    }

                    $message = 'Ayarlar başarıyla güncellendi.';
                    break;

                case 'add_module':
                    $stmt = $pdo->prepare("
                        INSERT INTO modules (name, slug, unit, icon, color, min_value, max_value, pin)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");

                    $stmt->execute([
                        $_POST['name'],
                        $_POST['slug'],
                        $_POST['unit'] ?? '',
                        $_POST['icon'] ?? 'thermometer',
                        $_POST['color'] ?? '#3498db',
                        !empty($_POST['min_value']) ? $_POST['min_value'] : null,
                        !empty($_POST['max_value']) ? $_POST['max_value'] : null,
                        !empty($_POST['pin']) ? (int)$_POST['pin'] : null
                    ]);

                    $message = 'Modül başarıyla eklendi.';
                    break;

                case 'toggle_module':
                    $module_id = (int)$_POST['module_id'];
                    $stmt = $pdo->prepare("UPDATE modules SET is_active = NOT is_active WHERE id = ?");
                    $stmt->execute([$module_id]);
                    $message = 'Modül durumu güncellendi.';
                    break;

                case 'delete_module':
                    $module_id = (int)$_POST['module_id'];
                    $stmt = $pdo->prepare("DELETE FROM modules WHERE id = ?");
                    $stmt->execute([$module_id]);
                    $message = 'Modül silindi.';
                    break;
            }
        }
    } catch (Exception $e) {
        $error = 'Hata: ' . $e->getMessage();
    }
}

// Mevcut ayarları al
$settings = [];
$stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
foreach ($stmt->fetchAll() as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Modülleri al
$stmt = $pdo->query("SELECT * FROM modules ORDER BY id");
$modules = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Config - Arduino Monitor</title>
    <style>
        * { margin:0; padding:0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f6fa; color: #2c3e50; }
        .container { max-width: 1200px; margin:0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 10px; margin-bottom: 30px; }
        .header h1 { font-size: 2em; margin-bottom: 10px; }
        .card { background: white; border-radius: 10px; padding: 25px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
        .card h2 { margin-bottom: 20px; color: #667eea; border-bottom: 2px solid #f0f0f0; padding-bottom: 10px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 600; color: #555; }
        .form-group input, .form-group select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 1em; }
        .form-group input:focus, .form-group select:focus { outline: none; border-color: #667eea; }
        .btn { padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 1em; transition: background 0.3s; }
        .btn-primary { background: #667eea; color: white; }
        .btn-primary:hover { background: #764ba2; }
        .btn-danger { background: #e74c3c; color: white; }
        .btn-danger:hover { background: #c0392b; }
        .btn-secondary { background: #95a5a6; color: white; }
        .alert { padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .module-list { display: grid; gap: 15px; }
        .module-item { display: flex; justify-content: space-between; align-items: center; padding: 15px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #667eea; }
        .module-info { flex: 1; }
        .module-name { font-weight: 600; font-size: 1.1em; }
        .module-details { color: #7f8c8d; font-size: 0.9em; margin-top: 5px; }
        .module-actions { display: flex; gap: 10px; }
        .status-badge { padding: 5px 10px; border-radius: 20px; font-size: 0.85em; font-weight: 600; }
        .status-active { background: #d4edda; color: #155724; }
        .status-inactive { background: #f8d7da; color: #721c24; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        @media (max-width: 768px) { .grid-2 { grid-template-columns: 1fr; } }
        .back-link { display: inline-block; margin-bottom: 20px; color: white; text-decoration: none; }
    </style>
</head>
<body>
    <div class="container">
        <a href="index.php" class="back-link">← Ana Sayfaya Dön</a>

        <div class="header">
            <h1>⚙️ Config Sayfası</h1>
            <p>Site ayarları ve modül yönetimi</p>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="grid-2">
            <!-- Site Ayarları -->
            <div class="card">
                <h2>📝 Site Ayarları</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="update_settings">

                    <div class="form-group">
                        <label>Site Başlığı</label>
                        <input type="text" name="site_title" value="<?php echo htmlspecialchars($settings['site_title'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Site Açıklaması</label>
                        <input type="text" name="site_description" value="<?php echo htmlspecialchars($settings['site_description'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label>Yenileme Aralığı (ms)</label>
                        <input type="number" name="refresh_interval" value="<?php echo htmlspecialchars($settings['refresh_interval'] ?? '5000'); ?>" min="1000" step="500">
                    </div>

                    <div class="form-group">
                        <label>Grafikteki Veri Sayısı</label>
                        <input type="number" name="chart_points" value="<?php echo htmlspecialchars($settings['chart_points'] ?? '20'); ?>" min="5" max="100">
                    </div>

                    <div class="form-group">
                        <label>API Anahtarı (Arduino için)</label>
                        <input type="text" name="api_key" value="<?php echo htmlspecialchars($settings['api_key'] ?? ''); ?>" placeholder="Boş bırakılırsa kontrolsüz">
                    </div>

                    <div class="form-group">
                        <label>Maksimum Kayıt Sayısı</label>
                        <input type="number" name="max_readings" value="<?php echo htmlspecialchars($settings['max_readings'] ?? '10000'); ?>" min="100">
                    </div>

                    <button type="submit" class="btn btn-primary">💾 Ayarları Kaydet</button>
                </form>
            </div>

            <!-- Yeni Modül Ekle -->
            <div class="card">
                <h2>➕ Yeni Modül Ekle</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="add_module">

                    <div class="form-group">
                        <label>Modül Adı</label>
                        <input type="text" name="name" placeholder="Örn: Sıcaklık" required>
                    </div>

                    <div class="form-group">
                        <label>Slug (Kısa Ad)</label>
                        <input type="text" name="slug" placeholder="Örn: sicaklik" required>
                        <small style="color: #7f8c8d;">URL dostu, benzersiz tanımlayıcı</small>
                    </div>

                    <div class="form-group">
                        <label>Birim</label>
                        <input type="text" name="unit" placeholder="Örn: °C, %, hPa">
                    </div>

                    <div class="form-group">
                        <label>İkon</label>
                        <select name="icon">
                            <option value="thermometer">🌡️ Termometre</option>
                            <option value="droplet">💧 Damla</option>
                            <option value="gauge">⏲️ Gösterge</option>
                            <option value="lightbulb">💡 Ampul</option>
                            <option value="wind">🌬️ Rüzgar</option>
                            <option value="sun">☀️ Güneş</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Renk</label>
                        <input type="color" name="color" value="#3498db" style="height: 40px;">
                    </div>

                    <div class="form-group">
                        <label>Arduino Pin</label>
                        <input type="number" name="pin" placeholder="Örn: 0" min="0" max="53">
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <div class="form-group">
                            <label>Min Değer (Normal)</label>
                            <input type="number" step="0.01" name="min_value" placeholder="Örn: 15">
                        </div>
                        <div class="form-group">
                            <label>Max Değer (Normal)</label>
                            <input type="number" step="0.01" name="max_value" placeholder="Örn: 35">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">➕ Modül Ekle</button>
                </form>
            </div>
        </div>

        <!-- Modül Listesi -->
        <div class="card">
            <h2>📦 Mevcut Modüller</h2>
            <div class="module-list">
                <?php if (empty($modules)): ?>
                    <p style="text-align: center; color: #7f8c8d; padding: 20px;">Henüz modül eklenmemiş.</p>
                <?php else: ?>
                    <?php foreach ($modules as $module): ?>
                        <div class="module-item">
                            <div class="module-info">
                                <div class="module-name">
                                    <?php echo htmlspecialchars($module['name']); ?>
                                    <span class="status-badge <?php echo $module['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                        <?php echo $module['is_active'] ? 'Aktif' : 'Pasif'; ?>
                                    </span>
                                </div>
                                <div class="module-details">
                                    Slug: <?php echo htmlspecialchars($module['slug']); ?> |
                                    Birim: <?php echo htmlspecialchars($module['unit'] ?: '-'); ?> |
                                    Pin: <?php echo $module['pin'] ?: '-'; ?> |
                                    Renk: <span style="color: <?php echo $module['color']; ?>;">■</span>
                                </div>
                            </div>
                            <div class="module-actions">
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="toggle_module">
                                    <input type="hidden" name="module_id" value="<?php echo $module['id']; ?>">
                                    <button type="submit" class="btn <?php echo $module['is_active'] ? 'btn-secondary' : 'btn-primary'; ?>">
                                        <?php echo $module['is_active'] ? 'Pasif Yap' : 'Aktif Yap'; ?>
                                    </button>
                                </form>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Bu modülü silmek istediğinizden emin misiniz?');">
                                    <input type="hidden" name="action" value="delete_module">
                                    <input type="hidden" name="module_id" value="<?php echo $module['id']; ?>">
                                    <button type="submit" class="btn btn-danger">Sil</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- API Bilgileri -->
        <div class="card">
            <h2>🔌 API Kullanım Bilgileri</h2>
            <h3>Arduino Veri Gönderme</h3>
            <pre style="background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto;"><?php echo htmlspecialchars("POST " . ($_SERVER['REQUEST_SCHEME'] ?? 'http') . "://" . $_SERVER['HTTP_HOST'] . "/api/post_data.php

Content-Type: application/json
{
    \"module_slug\": \"sicaklik\",
    \"value\": 25.5,
    \"api_key\": \"" . ($settings['api_key'] ?? '') . "\"
}"); ?>
            </pre>

            <h3>Veri Okuma</h3>
            <pre style="background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto;"><?php echo htmlspecialchars("GET " . ($_SERVER['REQUEST_SCHEME'] ?? 'http') . "://" . $_SERVER['HTTP_HOST'] . "/api/get_data.php?module=sicaklik&limit=20"); ?>
            </pre>
        </div>
    </div>
</body>
</html>
