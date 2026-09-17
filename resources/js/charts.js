/**
 * Entry riêng cho trang có biểu đồ — Chart.js ~200KB, không nạp vào mọi trang.
 * Dùng: @vite('resources/js/charts.js') trong @push('head').
 */
import {
    Chart,
    BarController,
    BarElement,
    CategoryScale,
    LinearScale,
    Tooltip,
} from 'chart.js';

Chart.register(BarController, BarElement, CategoryScale, LinearScale, Tooltip);

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

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('canvas[data-chart-type="topic-bars"]').forEach(renderTopicBars);
});
