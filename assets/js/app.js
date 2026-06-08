/* ============================================================
   EcoSalva Admin - JavaScript global
   ============================================================ */

'use strict';

// ── DataTables por defecto ────────────────────────────────
function initDataTable(selector, options = {}) {
    const defaults = {
        language: {
            url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json'
        },
        pageLength: 25,
        responsive: true,
        dom: '<"d-flex justify-content-between align-items-center mb-2"lf>rt<"d-flex justify-content-between align-items-center mt-2"ip>',
    };
    return $(selector).DataTable({ ...defaults, ...options });
}

// ── Confirmar acciones destructivas ──────────────────────
function confirmAction(message, callback) {
    if (confirm(message)) callback();
}

// ── Mostrar/ocultar spinner de carga ─────────────────────
function showLoading(containerId) {
    const el = document.getElementById(containerId);
    if (el) el.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-success"></div></div>';
}

// ── Fetch JSON con manejo de errores ─────────────────────
async function fetchJSON(url, options = {}) {
    try {
        const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, ...options });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        return await res.json();
    } catch (err) {
        console.error('fetchJSON error:', err);
        return null;
    }
}

// ── Colores para Chart.js ─────────────────────────────────
const chartColors = {
    green:   'rgba(26, 124, 62, .8)',
    greenBg: 'rgba(26, 124, 62, .15)',
    blue:    'rgba(54, 162, 235, .8)',
    blueBg:  'rgba(54, 162, 235, .15)',
    orange:  'rgba(255, 159, 64, .8)',
    red:     'rgba(220, 53, 69, .8)',
    purple:  'rgba(153, 102, 255, .8)',
    palette: [
        '#1a7c3e','#36a2eb','#ff9f40','#ff6384',
        '#9966ff','#4bc0c0','#ffcd56','#c9cbcf',
    ]
};

// ── Inicializar tooltips Bootstrap ───────────────────────
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
        new bootstrap.Tooltip(el);
    });
});

// ── Formato de moneda ────────────────────────────────────
function formatCurrency(amount) {
    return 'S/ ' + parseFloat(amount).toLocaleString('es-PE', {
        minimumFractionDigits: 2, maximumFractionDigits: 2
    });
}

// ── Crear línea de tendencia ──────────────────────────────
function createLineChart(canvasId, labels, datasets, title = '') {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return null;

    return new Chart(ctx, {
        type: 'line',
        data: { labels, datasets },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'top' },
                title: { display: !!title, text: title }
            },
            scales: {
                y: { beginAtZero: true, ticks: { callback: v => formatCurrency(v) } }
            }
        }
    });
}

// ── Crear gráfico de barras ───────────────────────────────
function createBarChart(canvasId, labels, data, label = '', color = chartColors.green) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return null;

    return new Chart(ctx, {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                label,
                data,
                backgroundColor: color,
                borderRadius: 6,
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true } }
        }
    });
}

// ── Crear gráfico de dona ─────────────────────────────────
function createDoughnutChart(canvasId, labels, data) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return null;

    return new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels,
            datasets: [{ data, backgroundColor: chartColors.palette, hoverOffset: 4 }]
        },
        options: {
            responsive: true,
            plugins: { legend: { position: 'right' } }
        }
    });
}
