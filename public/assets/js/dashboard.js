/**
 * Garden-OS Dashboard JavaScript
 * API ile iletişim, kart render, grafik ve motor kontrol
 */

// ---- GLOBAL STATE ----
let mainChart = null;
let autoRefreshTimer = null;
let motorState = { active: false, lastAction: null };

// ---- Sensör Yerleri ----
const nodeLocations = {
    node_01: 'Sera',
    node_02: 'Su Deposu',
    node_03: 'Bahçe'
};

const metricLabels = {
    temperature: { label: 'Sıcaklık', icon: '🌡️', unit: '°C', color: '#f97316', threshold: { min: 5, max: 40 }},
    humidity: { label: 'Nem', icon: '💧', unit: '%', color: '#3b82f6', threshold: { min: 30, max: 85 }},
    water_level: { label: 'Su Seviyesi', icon: '🚰', unit: 'cm', color: '#06b6d4', threshold: { min: 15, max: 150 }},
    soil_moisture: { label: 'Toprak Nemi', icon: '🌱', unit: '%', color: '#22c55e', threshold: { min: 20, max: 90 }}
};

// ---- API İLETİŞİM ----
async function apiFetch(endpoint, params = {}) {
    const url = new URL('api.php', window.location.href);
    Object.entries(params).forEach(([k, v]) => url.searchParams.set(k, v));
    const res = await fetch(url.toString());
    return res.json();
}

async function apiPost(body) {
    const res = await fetch('api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body)
    });
    return res.json();
}

// ---- VERİ YÜKLEME ----
async function loadSensorData() {
    try {
        const data = await apiFetch('latest');
        if (data.status !== 'ok') return;

        renderSensorCards(data.data);
        checkAlerts(data.data);
        updateStatusIndicator(true);
    } catch (e) {
        console.error('Veri yüklenemedi:', e);
        updateStatusIndicator(false);
    }
}

async function loadHistory() {
    const [nodeId, metric] = document.getElementById('chart-select').value.split('_');
    const nodeIdFull = Object.keys(nodeLocations).includes(nodeId) ? nodeId : 'node_01';
    const metricKey = nodeIdFull === nodeId ? metric : metric;

    try {
        const data = await apiFetch('history', {
            node_id: nodeIdFull,
            metric: metricKey,
            hours: 24
        });
        if (data.status !== 'ok') return;
        renderChart(data.data, metricKey);
    } catch (e) {
        console.error('Geçmiş veri yüklenemedi:', e);
    }
}

// ---- KARTLARI RENDER ----
function renderSensorCards(data) {
    const grid = document.getElementById('sensor-grid');
    let html = '';

    for (const [nodeId, info] of Object.entries(data)) {
        const location = info.location;
        const metrics = [];

        for (const [metric, valInfo] of Object.entries(info)) {
            if (metric === 'location') continue;
            const m = metricLabels[metric] || { label: metric, icon: '📊', unit: '', color: '#6b7280' };
            const isAlert = isOutsideThreshold(metric, valInfo.value);
            const cardClass = isAlert ? 'card-glow-alert border-amber-200 bg-amber-50' : 'card-glow border-gray-100';

            metrics.push(`
                <div class="flex items-center justify-between py-2 ${isAlert ? 'text-red-600' : ''}">
                    <span class="flex items-center gap-2 text-sm text-gray-600">
                        ${m.icon} ${m.label}
                    </span>
                    <span class="text-lg font-bold">${valInfo.value} ${m.unit}</span>
                </div>
            `);
        }

        html += `
            <div class="bg-white rounded-2xl p-5 shadow-sm border ${cardClass || 'border-gray-100'} transition hover:shadow-md">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="font-semibold text-gray-800">${location}</h3>
                    <span class="w-2 h-2 bg-green-400 rounded-full"></span>
                </div>
                <div class="space-y-1">
                    ${metrics.join('')}
                </div>
                <div class="mt-2 text-xs text-gray-400 text-right">${info[Object.keys(info).find(m => m !== 'location')]?.at || ''}</div>
            </div>
        `;
    }

    grid.innerHTML = html;
}

function isOutsideThreshold(metric, value) {
    const t = metricLabels[metric]?.threshold;
    if (!t) return false;
    return value < t.min || value > t.max;
}

// ---- GRAFİK ----
function renderChart(data, metric) {
    const m = metricLabels[metric] || { label: metric, unit: '', color: '#22c55e' };
    const ctx = document.getElementById('mainChart').getContext('2d');

    if (mainChart) mainChart.destroy();

    mainChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: data.map(d => d.time),
            datasets: [{
                label: `${m.label} (${m.unit})`,
                data: data.map(d => d.value),
                borderColor: m.color,
                backgroundColor: m.color + '20',
                fill: true,
                tension: 0.3,
                pointRadius: 2,
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#1f2937',
                    titleColor: '#fff',
                    bodyColor: '#fff',
                    cornerRadius: 8
                }
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { maxTicksLimit: 8, font: { size: 11 } }
                },
                y: {
                    grid: { color: '#f3f4f6' },
                    ticks: { font: { size: 11 } }
                }
            }
        }
    });
}

// ---- MOTOR KONTROL ----
async function toggleMotor(action) {
    const password = document.getElementById('motor-password').value;
    const duration = document.getElementById('motor-duration').value;

    if (!password) {
        showMotorResponse('Lütfen güvenlik şifresini girin.', 'error');
        return;
    }

    try {
        const result = await apiPost({
            action: 'motor_control',
            password: password,
            action_value: action,
            duration: parseInt(duration)
        });

        if (result.status === 'ok') {
            motorState.active = (action === 'ON');
            motorState.lastAction = action;
            updateMotorVisual(action === 'ON');
            showMotorResponse(`Motor ${action === 'ON' ? 'açıldı' : 'kapatıldı'}! (${result.planned_duration})`, 'success');
        } else {
            showMotorResponse(result.error || 'Bilinmeyen hata', 'error');
        }
    } catch (e) {
        showMotorResponse('Bağlantı hatası: ' + e.message, 'error');
    }

    document.getElementById('motor-password').value = '';
}

function updateMotorVisual(isOn) {
    const box = document.getElementById('motor-status-box');
    const text = document.getElementById('motor-text');
    box.className = 'rounded-xl p-4 text-center text-white mb-4 transition-all ' + (isOn ? 'motor-on' : 'motor-off');
    text.textContent = isOn ? 'Motor Açık' : 'Motor Kapalı';
}

function showMotorResponse(message, type) {
    const el = document.getElementById('motor-response');
    el.className = `mt-4 p-3 rounded-lg text-sm ${
        type === 'success' ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200'
    }`;
    el.textContent = message;
    el.classList.remove('hidden');
    setTimeout(() => el.classList.add('hidden'), 4000);
}

// ---- UYARI KONTROL ----
function checkAlerts(data) {
    const alerts = [];
    for (const [nodeId, info] of Object.entries(data)) {
        for (const [metric, valInfo] of Object.entries(info)) {
            if (metric === 'location') continue;
            if (isOutsideThreshold(metric, valInfo.value)) {
                const m = metricLabels[metric];
                alerts.push(`${nodeLocations[nodeId]}: ${m?.label || metric} = ${valInfo.value}${m?.unit || ''} (sınır dışında!)`);
            }
        }
    }

    const banner = document.getElementById('alert-banner');
    const alertText = document.getElementById('alert-text');
    if (alerts.length > 0) {
        banner.classList.remove('hidden');
        alertText.textContent = alerts.join(' • ');
    } else {
        banner.classList.add('hidden');
    }
}

// ---- DURUM GÖSTERGESİ ----
function updateStatusIndicator(connected) {
    const indicator = document.getElementById('status-indicator');
    const dot = indicator.querySelector('span');
    dot.className = connected ? 'w-2 h-2 bg-green-400 rounded-full animate-pulse' : 'w-2 h-2 bg-red-400 rounded-full';
    indicator.lastChild.textContent = connected ? ' Sistem Aktif' : ' Bağlantı Kesildi';
}

// ---- YENİLEME ----
function refreshData() {
    loadSensorData();
    loadHistory();
}

// ---- OTOMATİK YENİLEME (60 sn) ----
function startAutoRefresh(interval = 60000) {
    if (autoRefreshTimer) clearInterval(autoRefreshTimer);
    autoRefreshTimer = setInterval(() => loadSensorData(), interval);
}

// ---- BAŞLAT ----
document.addEventListener('DOMContentLoaded', () => {
    loadSensorData();
    loadHistory();
    startAutoRefresh();
});
