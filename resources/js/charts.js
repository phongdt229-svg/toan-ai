/**
 * Entry riêng cho trang có biểu đồ — Chart.js ~200KB, không nạp vào mọi trang.
 * Dùng: @vite('resources/js/charts.js') trong @push('head').
 */
import {
    ArcElement,
    Chart,
    BarController,
    BarElement,
    CategoryScale,
    DoughnutController,
    Legend,
    LinearScale,
    LineController,
    LineElement,
    PointElement,
    Tooltip,
} from 'chart.js';

Chart.register(
    ArcElement, BarController, BarElement, CategoryScale, DoughnutController,
    Legend, LinearScale, LineController, LineElement, PointElement, Tooltip,
);

/**
 * Bọc canvas trong một khung riêng có chiều cao cố định. Chart.js (responsive) lấy cỡ theo
 * phần tử cha — nếu cha là card-body chứa cả tiêu đề/mô tả thì đặt height lên đó sẽ làm
 * biểu đồ tràn ra ngoài card và đè lên chữ bên dưới.
 */
function sizeBox(canvas, height) {
    let box = canvas.parentElement;

    if (!box.classList.contains('chart-box')) {
        box = document.createElement('div');
        box.className = 'chart-box position-relative';
        canvas.before(box);
        box.appendChild(canvas);
    }

    box.style.height = height;
}

/**
 * Biểu đồ cột ngang % đúng theo chủ đề. Đọc dữ liệu từ data-chart (JSON).
 * Màu theo ngưỡng giống MasteryService: <60 yếu, <80 tạm, còn lại tốt.
 */
function renderTopicBars(canvas) {
    const rows = JSON.parse(canvas.dataset.chart || '[]');
    if (!rows.length) return;

    const colorFor = (p) => (p < 60 ? '#dc2626' : p < 80 ? '#f59e0b' : '#16a34a');

    // Chiều cao theo số dòng để nhãn không bị ép trên điện thoại.
    sizeBox(canvas, `${Math.max(140, rows.length * 44)}px`);

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
                tooltip: {
                    callbacks: {
                        // Trang admin truyền kèm số học sinh mỗi chủ đề; trang khác chỉ có %.
                        label: (ctx) => {
                            const row = rows[ctx.dataIndex];
                            const suffix = row.students != null ? ` · ${row.students} học sinh` : '';

                            return ` ${ctx.parsed.x}% đúng${suffix}`;
                        },
                    },
                },
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

    sizeBox(canvas, '220px');

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

/**
 * Dashboard admin: cột = học sinh hoạt động, đường = người dùng mới (trục trái),
 * đường doanh thu dùng trục phải vì khác đơn vị (₫).
 */
function renderAdminDaily(canvas) {
    const rows = JSON.parse(canvas.dataset.chart || '[]');
    if (!rows.length) return;

    sizeBox(canvas, '280px');
    const vnd = (v) => `${Number(v).toLocaleString('vi-VN')}₫`;

    new Chart(canvas, {
        // Bắt buộc có type ở cấp ngoài: thiếu nó Chart.js coi trục x là trục số,
        // nhãn ngày "20/08" thành NaN và mọi cột dồn về mép trái.
        type: 'bar',
        data: {
            labels: rows.map((r) => r.label),
            datasets: [
                { type: 'bar', label: 'Học sinh hoạt động', data: rows.map((r) => r.active), backgroundColor: '#93c5fd', borderRadius: 4, yAxisID: 'y' },
                { type: 'line', label: 'Người dùng mới', data: rows.map((r) => r.signups), borderColor: '#16a34a', backgroundColor: '#16a34a', tension: 0.3, pointRadius: 2, yAxisID: 'y' },
                { type: 'line', label: 'Doanh thu', data: rows.map((r) => r.revenue), borderColor: '#a50064', backgroundColor: '#a50064', tension: 0.3, pointRadius: 2, yAxisID: 'money' },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            scales: {
                x: { type: 'category', ticks: { maxTicksLimit: 10 } },
                y: { beginAtZero: true, ticks: { precision: 0 } },
                money: { position: 'right', beginAtZero: true, grid: { drawOnChartArea: false }, ticks: { callback: (v) => (v >= 1000 ? `${v / 1000}k` : v) } },
            },
            plugins: {
                legend: { position: 'bottom' },
                tooltip: { callbacks: { label: (ctx) => ` ${ctx.dataset.label}: ${ctx.dataset.yAxisID === 'money' ? vnd(ctx.parsed.y) : ctx.parsed.y}` } },
            },
        },
    });
}

/**
 * Trang AI usage: cột = số lượt gọi AI, đường = chi phí ước tính (USD, trục phải —
 * khác đơn vị với số lượt).
 */
function renderAiUsageDaily(canvas) {
    const rows = JSON.parse(canvas.dataset.chart || '[]');
    if (!rows.length) return;

    sizeBox(canvas, '240px');
    const usd = (v) => `$${Number(v).toFixed(2)}`;

    new Chart(canvas, {
        type: 'bar',
        data: {
            labels: rows.map((r) => r.label),
            datasets: [
                { type: 'bar', label: 'Lượt gọi', data: rows.map((r) => r.requests), backgroundColor: '#93c5fd', borderRadius: 4, yAxisID: 'y' },
                { type: 'line', label: 'Chi phí ước tính', data: rows.map((r) => r.cost), borderColor: '#a50064', backgroundColor: '#a50064', tension: 0.3, pointRadius: 2, yAxisID: 'money' },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            scales: {
                x: { type: 'category', ticks: { maxTicksLimit: 10 } },
                y: { beginAtZero: true, ticks: { precision: 0 } },
                money: { position: 'right', beginAtZero: true, grid: { drawOnChartArea: false }, ticks: { callback: usd } },
            },
            plugins: {
                legend: { position: 'bottom' },
                tooltip: { callbacks: { label: (ctx) => ` ${ctx.dataset.label}: ${ctx.dataset.yAxisID === 'money' ? usd(ctx.parsed.y) : ctx.parsed.y}` } },
            },
        },
    });
}

/**
 * Phân bố học sinh theo gói (Free/Pro/Premium) — donut để thấy tỉ trọng, không cần trục.
 */
function renderSubscriptionDonut(canvas) {
    const rows = JSON.parse(canvas.dataset.chart || '[]');
    if (!rows.every((r) => r.count === 0)) {
        sizeBox(canvas, '220px');

        new Chart(canvas, {
            type: 'doughnut',
            data: {
                labels: rows.map((r) => r.label),
                datasets: [{
                    data: rows.map((r) => r.count),
                    backgroundColor: rows.map((r) => r.color),
                    borderWidth: 0,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom' } },
            },
        });
    }
}

/**
 * Cột đơn giản {label, count} theo ngày — dùng chung cho vài trang quản trị (yêu cầu hỗ
 * trợ, tài khoản đăng ký mới…), chỉ khác nhãn chú thích.
 */
function renderCountDaily(canvas, seriesLabel) {
    const rows = JSON.parse(canvas.dataset.chart || '[]');
    if (!rows.length) return;

    sizeBox(canvas, '220px');

    new Chart(canvas, {
        type: 'bar',
        data: {
            labels: rows.map((r) => r.label),
            datasets: [{ label: seriesLabel, data: rows.map((r) => r.count), backgroundColor: '#93c5fd', borderRadius: 4, maxBarThickness: 28 }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: { ticks: { maxTicksLimit: 10 } },
                y: { beginAtZero: true, ticks: { precision: 0 } },
            },
            plugins: { legend: { display: false } },
        },
    });
}

/** Trang AI usage: số lượt gọi theo từng tính năng (30 ngày) — cam nếu có lượt lỗi, xanh nếu không. */
function renderFeatureBars(canvas) {
    const rows = JSON.parse(canvas.dataset.chart || '[]');
    if (!rows.length) return;

    sizeBox(canvas, `${Math.max(140, rows.length * 40)}px`);

    new Chart(canvas, {
        type: 'bar',
        data: {
            labels: rows.map((r) => r.feature),
            datasets: [{
                data: rows.map((r) => r.requests),
                backgroundColor: rows.map((r) => (r.failed > 0 ? '#f59e0b' : '#93c5fd')),
                borderRadius: 6,
                maxBarThickness: 28,
            }],
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: { beginAtZero: true, ticks: { precision: 0 } },
                y: { ticks: { autoSkip: false } },
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: (ctx) => {
                            const row = rows[ctx.dataIndex];

                            return row.failed > 0 ? ` ${row.requests} lượt · ${row.failed} lỗi` : ` ${row.requests} lượt`;
                        },
                    },
                },
            },
        },
    });
}

/** Cột ngang đơn giản {label, count} — dùng cho các bảng phân loại (vd đăng ký theo từng gói). */
function renderCountBars(canvas) {
    const rows = JSON.parse(canvas.dataset.chart || '[]');
    if (!rows.length) return;

    sizeBox(canvas, `${Math.max(140, rows.length * 40)}px`);

    new Chart(canvas, {
        type: 'bar',
        data: {
            labels: rows.map((r) => r.label),
            datasets: [{ data: rows.map((r) => r.count), backgroundColor: '#3b82f6', borderRadius: 6, maxBarThickness: 28 }],
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: { beginAtZero: true, ticks: { precision: 0 } },
                y: { ticks: { autoSkip: false } },
            },
            plugins: { legend: { display: false } },
        },
    });
}

/** Trang Giao dịch: số giao dịch thành công (cột) + doanh thu ₫ (đường, trục phải). */
function renderPaymentsDaily(canvas) {
    const rows = JSON.parse(canvas.dataset.chart || '[]');
    if (!rows.length) return;

    sizeBox(canvas, '240px');
    const vnd = (v) => `${Number(v).toLocaleString('vi-VN')}₫`;

    new Chart(canvas, {
        type: 'bar',
        data: {
            labels: rows.map((r) => r.label),
            datasets: [
                { type: 'bar', label: 'Giao dịch thành công', data: rows.map((r) => r.count), backgroundColor: '#93c5fd', borderRadius: 4, yAxisID: 'y' },
                { type: 'line', label: 'Doanh thu', data: rows.map((r) => r.revenue), borderColor: '#a50064', backgroundColor: '#a50064', tension: 0.3, pointRadius: 2, yAxisID: 'money' },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            scales: {
                x: { type: 'category', ticks: { maxTicksLimit: 10 } },
                y: { beginAtZero: true, ticks: { precision: 0 } },
                money: { position: 'right', beginAtZero: true, grid: { drawOnChartArea: false }, ticks: { callback: (v) => (v >= 1000 ? `${v / 1000}k` : v) } },
            },
            plugins: {
                legend: { position: 'bottom' },
                tooltip: { callbacks: { label: (ctx) => ` ${ctx.dataset.label}: ${ctx.dataset.yAxisID === 'money' ? vnd(ctx.parsed.y) : ctx.parsed.y}` } },
            },
        },
    });
}

/** Trang Mã giảm giá: lượt dùng mỗi ngày (cột) + tiền đã giảm ₫ (đường, trục phải). */
function renderVouchersDaily(canvas) {
    const rows = JSON.parse(canvas.dataset.chart || '[]');
    if (!rows.length) return;

    sizeBox(canvas, '260px');
    const vnd = (v) => `${Number(v).toLocaleString('vi-VN')}₫`;

    new Chart(canvas, {
        type: 'bar',
        data: {
            labels: rows.map((r) => r.label),
            datasets: [
                { type: 'bar', label: 'Lượt dùng', data: rows.map((r) => r.count), backgroundColor: '#93c5fd', borderRadius: 4, yAxisID: 'y' },
                { type: 'line', label: 'Tiền đã giảm', data: rows.map((r) => r.discount), borderColor: '#dc2626', backgroundColor: '#dc2626', tension: 0.3, pointRadius: 2, yAxisID: 'money' },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            scales: {
                x: { type: 'category', ticks: { maxTicksLimit: 10 } },
                y: { beginAtZero: true, ticks: { precision: 0 } },
                money: { position: 'right', beginAtZero: true, grid: { drawOnChartArea: false }, ticks: { callback: (v) => (v >= 1000 ? `${v / 1000}k` : v) } },
            },
            plugins: {
                legend: { position: 'bottom' },
                tooltip: { callbacks: { label: (ctx) => ` ${ctx.dataset.label}: ${ctx.dataset.yAxisID === 'money' ? vnd(ctx.parsed.y) : ctx.parsed.y}` } },
            },
        },
    });
}

/** Top mã: cột ngang theo số lượt dùng, tooltip kèm tiền đã giảm. */
function renderVoucherTop(canvas) {
    const rows = JSON.parse(canvas.dataset.chart || '[]');
    if (!rows.length) return;

    sizeBox(canvas, `${Math.max(140, rows.length * 40)}px`);

    new Chart(canvas, {
        type: 'bar',
        data: {
            labels: rows.map((r) => r.label),
            datasets: [{ data: rows.map((r) => r.count), backgroundColor: '#3b82f6', borderRadius: 6, maxBarThickness: 28 }],
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: { beginAtZero: true, ticks: { precision: 0 } },
                y: { ticks: { autoSkip: false } },
            },
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: { label: (ctx) => ` ${ctx.parsed.x} lượt · giảm ${Number(rows[ctx.dataIndex].discount).toLocaleString('vi-VN')}₫` } },
            },
        },
    });
}

/** Trang Google Analytics: người dùng (đường) + lượt xem trang (cột), cùng một trục vì cùng là số đếm. */
function renderGaDaily(canvas) {
    const rows = JSON.parse(canvas.dataset.chart || '[]');
    if (!rows.length) return;

    sizeBox(canvas, '260px');

    new Chart(canvas, {
        type: 'bar',
        data: {
            labels: rows.map((r) => r.label),
            datasets: [
                { type: 'bar', label: 'Lượt xem trang', data: rows.map((r) => r.views), backgroundColor: '#93c5fd', borderRadius: 4 },
                { type: 'line', label: 'Người dùng', data: rows.map((r) => r.users), borderColor: '#16a34a', backgroundColor: '#16a34a', tension: 0.3, pointRadius: 2 },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            scales: {
                x: { type: 'category', ticks: { maxTicksLimit: 10 } },
                y: { beginAtZero: true, ticks: { precision: 0 } },
            },
            plugins: { legend: { position: 'bottom' } },
        },
    });
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('canvas[data-chart-type="admin-daily"]').forEach(renderAdminDaily);
    document.querySelectorAll('canvas[data-chart-type="topic-bars"]').forEach(renderTopicBars);
    document.querySelectorAll('canvas[data-chart-type="daily-activity"]').forEach(renderDailyActivity);
    document.querySelectorAll('canvas[data-chart-type="ai-usage-daily"]').forEach(renderAiUsageDaily);
    document.querySelectorAll('canvas[data-chart-type="subscription-donut"]').forEach(renderSubscriptionDonut);
    document.querySelectorAll('canvas[data-chart-type="tickets-daily"]').forEach((c) => renderCountDaily(c, 'Yêu cầu mới'));
    document.querySelectorAll('canvas[data-chart-type="signups-daily"]').forEach((c) => renderCountDaily(c, 'Tài khoản mới'));
    document.querySelectorAll('canvas[data-chart-type="blog-daily"]').forEach((c) => renderCountDaily(c, 'Bài xuất bản'));
    document.querySelectorAll('canvas[data-chart-type="feature-bars"]').forEach(renderFeatureBars);
    document.querySelectorAll('canvas[data-chart-type="count-bars"]').forEach(renderCountBars);
    document.querySelectorAll('canvas[data-chart-type="payments-daily"]').forEach(renderPaymentsDaily);
    document.querySelectorAll('canvas[data-chart-type="vouchers-daily"]').forEach(renderVouchersDaily);
    document.querySelectorAll('canvas[data-chart-type="ga-daily"]').forEach(renderGaDaily);
    document.querySelectorAll('canvas[data-chart-type="voucher-top"]').forEach(renderVoucherTop);
});
