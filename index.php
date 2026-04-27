<?php
/**
 * Ana Dashboard Sayfası
 * Arduino sensör verilerini grafikler ve kartlar halinde gösterir
 */
require_once 'config/database.php';

// Site ayarlarını al
$site_title = getSetting('site_title', 'Arduino Monitor');
$site_description = getSetting('site_description', 'Arduino sensör verilerini izleme sistemi');
$refresh_interval = (int)getSetting('refresh_interval', 5000);
$chart_points = (int)getSetting('chart_points', 20);

// Modülleri getir
$pdo = getDB();
$stmt = $pdo->query("SELECT * FROM modules WHERE is_active = TRUE ORDER BY id");
$modules = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($site_title); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f6fa; color: #2c3e50; }
        .container { max-width: 1400px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 10px; margin-bottom: 30px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .header h1 { font-size: 2.5em; margin-bottom: 10px; }
        .header p { opacity: 0.9; font-size: 1.1em; }
        .modules-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .module-card { background: white; border-radius: 10px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); transition: transform 0.2s; }
        .module-card:hover { transform: translateY(-5px); box-shadow: 0 4px 15px rgba(0,0,0,0.15); }
        .module-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        .module-name { font-size: 1.3em; font-weight: 600; }
        .module-value { font-size: 3em; font-weight: bold; margin: 20px 0; text-align: center; }
        .module-unit { font-size: 0.4em; opacity: 0.7; }
        .module-time { color: #7f8c8d; font-size: 0.9em; text-align: center; }
        .module-chart { height: 150px; margin-top: 15px; }
        .status-bar { background: white; padding: 15px 20px; border-radius: 10px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
        .status-indicator { display: flex; align-items: center; gap: 10px; }
        .status-dot { width: 10px; height: 10px; border-radius: 50%; background: #2ecc71; animation: pulse 2s infinite; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
        .btn { padding: 10px 20px; background: #667eea; color: white; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn:hover { background: #764ba2; }
        .loading { text-align: center; padding: 20px; color: #7f8c8d; }
        canvas { max-width: 100%; }
        .error-message { background: #e74c3c; color: white; padding: 15px; border-radius: 5px; margin: 10px 0; }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.5.0/dist/chart.umd.js" integrity="sha384-iU8HYtnGQ8Cy4zl7gbNMOhsDTTKX02BTXptVP/vqAWIaTfM7isw76iyZCsjL2eVi" crossorigin="anonymous"></script>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📊 <?php echo htmlspecialchars($site_title); ?></h1>
            <p><?php echo htmlspecialchars($site_description); ?></p>
        </div>

        <div class="status-bar">
            <div class="status-indicator">
                <div class="status-dot"></div>
                <span id="status-text">Bağlı - Veriler alınıyor...</span>
            </div>
            <div>
                <span id="last-update">Son güncelleme: -</span>
                <a href="config.php" class="btn" style="margin-left: 15px;">⚙️ Ayarlar</a>
            </div>
        </div>

        <div class="modules-grid" id="modules-grid">
            <div class="loading">Modüller yükleniyor...</div>
        </div>
    </div>

    <script>
        const refreshInterval = <?php echo $refresh_interval; ?>;
        const chartPoints = <?php echo $chart_points; ?>;
        let charts = {};

        // Verileri getir ve göster
        async function fetchData() {
            try {
                const response = await fetch('api/get_data.php?limit=' + chartPoints);
                const result = await response.json();

                if (!result.success) {
                    throw new Error(result.error || 'Veri alınamadı');
                }

                renderModules(result.data);
                document.getElementById('last-update').textContent =
                    'Son güncelleme: ' + new Date().toLocaleTimeString('tr-TR');
                document.getElementById('status-text').textContent = 'Bağlı - Veriler alınıyor...';

            } catch (error) {
                console.error('Hata:', error);
                document.getElementById('status-text').textContent = 'Hata: ' + error.message;
                document.querySelector('.status-dot').style.background = '#e74c3c';
            }
        }

        // Modülleri render et
        function renderModules(modules) {
            const grid = document.getElementById('modules-grid');

            if (!modules || modules.length === 0) {
                grid.innerHTML = '<div class="loading">Henüz veri yok. Arduino veri göndermeye başladığında burada görünecek.</div>';
                return;
            }

            grid.innerHTML = modules.map(module => {
                const mod = module.module;
                const latest = module.latest;
                const readings = module.readings || [];

                // Chart oluştur (veya güncelle)
                const chartId = 'chart-' + mod.slug;

                setTimeout(() => {
                    const ctx = document.getElementById(chartId);
                    if (!ctx) return;

                    const labels = readings.map(r => new Date(r.time).toLocaleTimeString('tr-TR'));
                    const data = readings.map(r => r.value);

                    if (charts[mod.slug]) {
                        charts[mod.slug].data.labels = labels;
                        charts[mod.slug].data.datasets[0].data = data;
                        charts[mod.slug].update();
                    } else {
                        charts[mod.slug] = new Chart(ctx, {
                            type: 'line',
                            data: {
                                labels: labels,
                                datasets: [{
                                    label: mod.name,
                                    data: data,
                                    borderColor: mod.color,
                                    backgroundColor: mod.color + '20',
                                    borderWidth: 2,
                                    fill: true,
                                    tension: 0.4
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: { legend: { display: false } },
                                scales: {
                                    y: { beginAtZero: false },
                                    x: { display: false }
                                }
                            }
                        });
                    }
                }, 100);

                return `
                    <div class="module-card" style="border-top: 4px solid ${mod.color}">
                        <div class="module-header">
                            <div class="module-name">${mod.name}</div>
                            <div style="font-size: 1.5em;">${getIcon(mod.icon)}</div>
                        </div>
                        <div class="module-value" style="color: ${mod.color}">
                            ${latest ? latest.value.toFixed(2) : '--'}
                            <span class="module-unit">${mod.unit}</span>
                        </div>
                        <div class="module-time">
                            ${latest ? 'Son veri: ' + new Date(latest.time).toLocaleString('tr-TR') : 'Henüz veri yok'}
                        </div>
                        <div class="module-chart">
                            <canvas id="${chartId}"></canvas>
                        </div>
                    </div>
                `;
            }).join('');
        }

        // İkon mapi
        function getIcon(iconName) {
            const icons = {
                'thermometer': '🌡️',
                'droplet': '💧',
                'gauge': '⏲️',
                'lightbulb': '💡',
                'wind': '🌬️',
                'sun': '☀️'
            };
            return icons[iconName] || '📊';
        }

        // Sayfa yüklendiğinde ve periyodik olarak veri çek
        fetchData();
        setInterval(fetchData, refreshInterval);
    </script>
</body>
</html>
