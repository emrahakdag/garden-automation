<?php
/**
 * Sistem Kontrol Sayfası
 * Kurulumun doğru yapıldığını kontrol eder
 */
header('Content-Type: text/html; charset=UTF-8');

$checks = [];
$all_ok = true;

// PHP versiyonu
$checks['PHP Version'] = [
    'status' => version_compare(PHP_VERSION, '7.4.0') >= 0,
    'message' => 'PHP ' . PHP_VERSION . (version_compare(PHP_VERSION, '7.4.0') >= 0 ? ' ✓' : ' ✗ (7.4+ gerekli)')
];

// PDO MySQL
$checks['PDO MySQL'] = [
    'status' => extension_loaded('pdo_mysql'),
    'message' => extension_loaded('pdo_mysql') ? 'Yüklü ✓' : 'Yüklü değil ✗'
];

// Veritabanı bağlantısı
try {
    require_once 'config/database.php';
    $pdo = getDB();
    $checks['Veritabanı Bağlantısı'] = [
        'status' => true,
        'message' => 'Bağlandı ✓'
    ];

    // Tabloları kontrol et
    $tables = ['modules', 'readings', 'settings', 'users'];
    foreach ($tables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        $checks["Tablo: $table"] = [
            'status' => $stmt->rowCount() > 0,
            'message' => $stmt->rowCount() > 0 ? 'Var ✓' : 'Yok ✗ (database.sql import edin)'
        ];
        if ($stmt->rowCount() == 0) $all_ok = false;
    }

    // Modül sayısı
    $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM modules");
    $cnt = $stmt->fetch()['cnt'];
    $checks['Aktif Modüller'] = [
        'status' => $cnt > 0,
        'message' => "$cnt modül bulundu" . ($cnt > 0 ? ' ✓' : ' ⚠ (config.php\'den ekleyin)')
    ];

} catch (Exception $e) {
    $checks['Veritabanı Bağlantısı'] = [
        'status' => false,
        'message' => 'Hata: ' . $e->getMessage() . ' ✗'
    ];
    $all_ok = false;
}

// API endpointleri
$api_files = ['api/post_data.php', 'api/get_data.php', 'api/modules.php'];
foreach ($api_files as $file) {
    $checks["API: $file"] = [
        'status' => file_exists($file),
        'message' => file_exists($file) ? 'Var ✓' : 'Yok ✗'
    ];
    if (!file_exists($file)) $all_ok = false;
}

// Yazma izinleri
$writable_dirs = ['', 'api', 'config'];
foreach ($writable_dirs as $dir) {
    $checks["Yazma İzni: /$dir"] = [
        'status' => is_writable($dir ?: '.'),
        'message' => is_writable($dir ?: '.') ? 'OK ✓' : 'Yazılabilir değil ✗'
    ];
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Sistem Kontrolü - Arduino Monitor</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f6fa; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #667eea; }
        .status { margin: 20px 0; }
        .check-item { padding: 10px; margin: 5px 0; border-radius: 5px; background: #f8f9fa; }
        .check-ok { border-left: 4px solid #2ecc71; }
        .check-fail { border-left: 4px solid #e74c3c; }
        .check-warn { border-left: 4px solid #f39c12; }
        .summary { padding: 20px; border-radius: 5px; margin-top: 20px; text-align: center; font-size: 1.2em; }
        .summary-ok { background: #d4edda; color: #155724; }
        .summary-fail { background: #f8d7da; color: #721c24; }
        a { color: #667eea; text-decoration: none; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Sistem Kontrolü</h1>
        <p>Arduino Monitor kurulumunun durumunu kontrol edin.</p>

        <div class="status">
            <?php foreach ($checks as $name => $check): ?>
                <div class="check-item <?php echo $check['status'] ? 'check-ok' : (strpos($name, '⚠') !== false ? 'check-warn' : 'check-fail'); ?>">
                    <strong><?php echo htmlspecialchars($name); ?>:</strong>
                    <?php echo $check['message']; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="summary <?php echo $all_ok ? 'summary-ok' : 'summary-fail'; ?>">
            <?php if ($all_ok): ?>
                ✅ Tüm kontroller başarılı! Sistem kullanıma hazır.
            <?php else: ?>
                ⚠️ Bazı kontroller başarısız. Lütfen yukarıdaki hataları düzeltin.
            <?php endif; ?>
        </div>

        <div style="margin-top: 30px; text-align: center;">
            <a href="index.php">← Ana Sayfa</a> |
            <a href="config.php">⚙️ Ayarlar</a> |
            <a href="check.php">🔄 Yenile</a>
        </div>

        <div style="margin-top: 20px; padding: 15px; background: #fff3cd; border-radius: 5px; font-size: 0.9em;">
            <strong>⚠️ Güvenlik Uyarısı:</strong> Kurulum tamamlandıktan sonra bu dosyayı (check.php) silmeyi unutmayın!
        </div>
    </div>
</body>
</html>
