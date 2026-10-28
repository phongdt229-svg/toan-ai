import './bootstrap';

import * as bootstrap from 'bootstrap';
import { initAiTutor } from './ai-tutor';
import { initActivityTracker } from './activity-tracker';
import { initOnboarding } from './onboarding-tour';
import './confirm-dialog';
import { renderGeometry } from './geometry-embed';

window.bootstrap = bootstrap;

/**
 * KaTeX (~260 KB JS + CSS + font) chỉ tải khi trang thật sự có công thức — trước đây nằm trong
 * bundle chung nên trang chủ, đăng nhập… cũng phải tải, kéo điểm PageSpeed mobile xuống.
 */
let katexLoader;
function loadKatex() {
    katexLoader ??= Promise.all([
        import('katex/contrib/auto-render'),
        import('katex/dist/katex.min.css'),
    ]).then(([mod]) => mod.default);

    return katexLoader;
}

/**
 * Render công thức Toán trong mọi phần tử [data-math].
 * Dùng cho nội dung lesson/câu hỏi (Phase 2 trở đi). Trả về Promise — ai cần đo kích thước
 * sau khi render thì await.
 */
async function renderMath(root = document.body) {
    const targets = [...root.querySelectorAll('[data-math]')];

    if (targets.some((el) => el.dataset.mathRendered !== '1')) {
        const renderMathInElement = await loadKatex();

        targets.forEach((el) => {
            // Kiểm lại sau await: lượt gọi khác có thể đã render trong lúc chờ tải KaTeX.
            if (el.dataset.mathRendered === '1') return;

            renderMathInElement(el, {
                delimiters: [
                    { left: '$$', right: '$$', display: true },
                    { left: '\\[', right: '\\]', display: true },
                    { left: '$', right: '$', display: false },
                    { left: '\\(', right: '\\)', display: false },
                ],
                throwOnError: false,
            });

            el.dataset.mathRendered = '1';
        });
    }

    // Sau công thức: thay token [[hinh:...]] bằng hình 3D chỉ-xem (Three.js chỉ tải khi trang có hình).
    renderGeometry(root);
}

window.renderMath = renderMath;

document.addEventListener('DOMContentLoaded', () => {
    renderMath();
    initAiTutor();
    initActivityTracker();
    initOnboarding();

    registerServiceWorker();

    // Tự ẩn thông báo sau 5 giây.
    document.querySelectorAll('[data-auto-dismiss]').forEach((el) => {
        setTimeout(() => bootstrap.Alert.getOrCreateInstance(el).close(), 5000);
    });
});

/**
 * PWA: service worker chỉ chạy trên HTTPS (hoặc localhost). Đăng xuất → xoá bài học đã lưu offline
 * để người sau trên máy dùng chung không đọc được trang có tên học sinh.
 */
function registerServiceWorker() {
    if (!('serviceWorker' in navigator)) return;

    navigator.serviceWorker.register('/sw.js').catch(() => { /* trình duyệt chặn — web vẫn chạy bình thường */ });

    document.querySelectorAll('form[action$="/dang-xuat"]').forEach((form) => {
        form.addEventListener('submit', () => {
            navigator.serviceWorker.controller?.postMessage('clear-user-cache');
        });
    });
}
