<?php
/**
 * Garden-OS Dashboard
 * Ana kontrol paneli
 */
$config = json_decode(file_get_contents(__DIR__ . '/../config/settings.json'), true);
$system = $config['system'];
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $system['name'] ?> — Dashboard</title>
    <!-- Tailwind Play CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.5.0/dist/chart.umd.min.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        garden: {
                            50: '#f0fdf4',
                            100: '#dcfce7',
                            200: '#bbf7d0',
                            300: '#86efac',
                            400: '#4ade80',
                            500: '#22c55e',
                            600: '#16a34a',
                            700: '#15803d',
                            800: '#166534',
                            900: '#14532d',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        /* Dashboard-specific styles */
        .card-glow { box-shadow: 0 0 15px rgba(34, 197, 94, 0.1); }
        .card-glow-warning { box-shadow: 0 0 15px rgba(245, 158, 11, 0.2); }
        .card-glow-alert { box-shadow: 0 0 15px rgba(239, 68, 68, 0.2); }
        .fade-in { animation: fadeIn 0.5s ease-in; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }
        .motor-on { background: linear-gradient(135deg, #22c55e, #16a34a) !important; }
        .motor-off { background: linear-gradient(135deg, #6b7280, #4b5563) !important; }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">

    <!-- ===== ÜST BAR ===== -->
    <header class="bg-gradient-to-r from-garden-700 to-garden-600 text-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 py-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 3a7 7 0 00-7 7c0 5.25 7 11 7 11s7-5.75 7-11a7 7 0 00-7-7z"/>
                </svg>
                <div>
                    <h1 class="text-xl font-bold"><?= $system['name'] ?></h1>
                    <p class="text-xs text-garden-100">v<?= $system['version'] ?> • <?= date('d.m.Y H:i') ?></p>
                </div>
            </div>
            <div class="flex items-center gap-4">
                <span id="status-indicator" class="flex items-center gap-2 text-sm">
                    <span class="w-2 h-2 bg-green-400 rounded-full animate-pulse"></span>
                    Sistem Aktif
                </span>
                <button onclick="refreshData()" class="bg-white/20 hover:bg-white/30 px-3 py-1.5 rounded-lg text-sm transition">
                    <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    Yenile
                </button>
            </div>
        </div>
    </header>

    <!-- ===== ANA İÇERİK ===== -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 py-6 fade-in">

        <!-- Uyarı Banner -->
        <div id="alert-banner" class="hidden mb-4 p-4 bg-amber-50 border border-amber-200 rounded-xl">
            <div class="flex items-center gap-3">
                <svg class="w-5 h-5 text-amber-500" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                </svg>
                <p id="alert-text" class="text-sm text-amber-800 font-medium"></p>
            </div>
        </div>

        <!-- ===== SENSÖR KARTLARI ===== -->
        <section id="sensor-grid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
            <!-- JS ile doldurulacak -->
            <div class="text-center py-8 text-gray-400">Veriler yükleniyor...</div>
        </section>

        <!-- ===== GRAFİK + MOTOR KONTROL ===== -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            <!-- Grafik -->
            <div class="lg:col-span-2 bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-gray-800">📈 24 Saatlik Grafik</h2>
                    <select id="chart-select" onchange="loadHistory()"
                            class="text-sm border border-gray-200 rounded-lg px-3 py-1.5 focus:ring-2 focus:ring-garden-400">
                        <option value="node_01_temperature">Sera - Sıcaklık</option>
                        <option value="node_01_humidity">Sera - Nem</option>
                        <option value="node_02_water_level">Su Deposu - Seviye</option>
                        <option value="node_03_temperature">Bahçe - Sıcaklık</option>
                        <option value="node_03_humidity">Bahçe - Nem</option>
                    </select>
                </div>
                <div class="relative h-64">
                    <canvas id="mainChart"></canvas>
                </div>
            </div>

            <!-- Motor Kontrol -->
            <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
                <h2 class="text-lg font-semibold text-gray-800 mb-4">⚙️ Motor Kontrol</h2>
                <div id="motor-status-box" class="rounded-xl p-4 text-center text-white mb-4 motor-off transition-all">
                    <div class="text-3xl mb-2">⚡</div>
                    <div id="motor-text" class="text-lg font-bold">Motor Kapalı</div>
                    <div class="text-xs opacity-75 mt-1">Son durum: kontrol edilemedi</div>
                </div>

                <!-- Şifre girişi -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Güvenlik Şifresi</label>
                    <input type="password" id="motor-password" placeholder="Şifreyi girin"
                           class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-garden-400 focus:border-transparent">
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <button onclick="toggleMotor('ON')"
                            class="bg-green-500 hover:bg-green-600 text-white py-2.5 rounded-lg font-medium transition flex items-center justify-center gap-2">
                        <span>▶</span> Aç
                    </button>
                    <button onclick="toggleMotor('OFF')"
                            class="bg-red-500 hover:bg-red-600 text-white py-2.5 rounded-lg font-medium transition flex items-center justify-center gap-2">
                        <span>⏹</span> Kapat
                    </button>
                </div>

                <div class="mt-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Süre (saniye)</label>
                    <input type="number" id="motor-duration"
                           value="<?= $config['modules']['motor_control']['default_run_duration'] ?>"
                           min="<?= $config['modules']['motor_control']['min_interval'] ?>"
                           max="<?= $config['modules']['motor_control']['max_run_duration'] ?>"
                           class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-garden-400">
                </div>

                <div id="motor-response" class="mt-4 hidden p-3 rounded-lg text-sm"></div>
            </div>
        </div>

        <!-- ===== ALT BİLGİ ===== -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
            <!-- Cihaz Bilgileri -->
            <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
                <h2 class="text-lg font-semibold text-gray-800 mb-3">📱 Cihaz Durumu</h2>
                <div class="divide-y divide-gray-50">
                    <?php foreach ($config['nodes'] as $node): ?>
                    <div class="py-2 flex items-center justify-between">
                        <div>
                            <span class="font-medium text-gray-800"><?= $node['location'] ?></span>
                            <span class="text-xs text-gray-400 ml-2"><?= $node['id'] ?></span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full"><?= $node['type'] ?></span>
                            <span class="w-2 h-2 bg-green-400 rounded-full" title="Aktif (demo)"></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Sistem Bilgi -->
            <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
                <h2 class="text-lg font-semibold text-gray-800 mb-3">ℹ️ Sistem</h2>
                <div class="space-y-2 text-sm text-gray-600">
                    <div class="flex justify-between"><span>PHP Sürümü</span><span class="font-mono"><?= phpversion() ?></span></div>
                    <div class="flex justify-between"><span>Zaman Dilimi</span><span class="font-mono"><?= date_default_timezone_get() ?></span></div>
                    <div class="flex justify-between"><span>Etkin Modüller</span><span class="font-mono"><?= count($config['nodes']) ?> düğüm</span></div>
                    <div class="flex justify-between"><span>Son Kontrol</span><span class="font-mono text-xs"><?= date('H:i:s') ?></span></div>
                </div>
            </div>
        </div>

    </main>

    <!-- Footer -->
    <footer class="text-center py-6 text-gray-400 text-sm">
        Garden-OS © <?= date('Y') ?> • bahce.emrahakdag.xyz
    </footer>

    <!-- Dashboard JS -->
    <script src="assets/js/dashboard.js"></script>
</body>
</html>
