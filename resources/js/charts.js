/**
 * Entry riêng cho trang có biểu đồ — Chart.js ~200KB, không nạp vào mọi trang.
 * Dùng: @vite('resources/js/charts.js') trong @push('head').
 */
import {
    Chart,
    BarController,
    BarElement,
    CategoryScale,
    Legend,
    LinearScale,
    Tooltip,
} from 'chart.js';

Chart.register(BarController, BarElement, CategoryScale, Legend, LinearScale, Tooltip);

/**
 * Biểu đồ cột ngang % đúng theo chủ đề. Đọc dữ liệu từ data-chart (JSON).
 * Màu theo ngưỡng giống MasteryService: <60 yếu, <80 tạm, còn lại tốt.
 */
function renderTopicBars(canvas) {
    const rows = JSON.parse(canvas.dataset.chart || '[]');
    if (!rows.length) return;

    const colorFor = (p) => (p < 60 ? '#dc2626' : p < 80 ? '#f59e0b' : '#16a34a');

    // Chiều cao theo số dòng để nhãn không bị ép trên điện thoại.
    canvas.parentElement.style.height = `${Math.max(140, rows.length * 44)}px`;

    new Chart(canvas, {
        type: 'bar',
        data: {
            labels: rows.map((r) => r.topic),
            datasets: [{
                data: rows.map((r) => r.percent),
                backgroundColor: rows.map((r) => colorFor(r.percent)),
                borderRadius: 6,
                maxBarThickness: 28,
            }],
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: { min: 0, max: 100, ticks: { callback: (v) => `${v}%` } },
                y: { ticks: { autoSkip: false } },
            },
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: { label: (ctx) => ` ${ctx.parsed.x}% đúng` } },
            },
        },
    });
}

/**
 * Số câu làm mỗi ngày (§14 phụ huynh). Cột xếp chồng: phần đúng + phần sai,
 * để phụ huynh thấy cả "con có học không" lẫn "học có hiệu quả không".
 */
function renderDailyActivity(canvas) {
    const rows = JSON.parse(canvas.dataset.chart || '[]');
    if (!rows.length) return;

    canvas.parentElement.style.height = '220px';

    new Chart(canvas, {
        type: 'bar',
        data: {
            labels: rows.map((r) => r.label),
            datasets: [
                {
                    label: 'Đúng',
                    data: rows.map((r) => r.correct),
                    backgroundColor: '#16a34a',
                    borderRadius: 4,
                    stack: 'answers',
                },
                {
                    label: 'Chưa đúng',
                    data: rows.map((r) => r.answered - r.correct),
                    backgroundColor: '#fca5a5',
                    borderRadius: 4,
                    stack: 'answers',
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: { stacked: true },
                y: { stacked: true, beginAtZero: true, ticks: { precision: 0 } },
            },
            plugins: {
                legend: { position: 'bottom' },
                tooltip: { mode: 'index', intersect: false },
            },
        },
    });
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('canvas[data-chart-type="topic-bars"]').forEach(renderTopicBars);
    document.querySelectorAll('canvas[data-chart-type="daily-activity"]').forEach(renderDailyActivity);
});
