// Không nạp ./bootstrap (axios ~35KB): không nơi nào dùng window.axios, mọi lời gọi dùng fetch.
// Chỉ nạp các thành phần Bootstrap thật sự dùng (nạp cả gói tốn ~80KB JS không chạy tới).
import Alert from 'bootstrap/js/dist/alert';
import Collapse from 'bootstrap/js/dist/collapse';
import Dropdown from 'bootstrap/js/dist/dropdown';
import Modal from 'bootstrap/js/dist/modal';
import Offcanvas from 'bootstrap/js/dist/offcanvas';

const bootstrap = { Alert, Collapse, Dropdown, Modal, Offcanvas };
import { initAiTutor } from './ai-tutor';
import { initActivityTracker } from './activity-tracker';
import './confirm-dialog';
import { renderGeometry } from './geometry-embed';

window.bootstrap = bootstrap;

/**
 * Render công thức Toán trong mọi phần tử [data-math].
 * Dùng cho nội dung lesson/câu hỏi (Phase 2 trở đi).
 */
// Có ký hiệu công thức nào trong văn bản không — không có thì khỏi tải KaTeX (~75KB gzip) cho trang đó.
const MATH_HINT = /\$|\\\(|\\\[/;
let katexLoader = null;
const loadKatex = () => (katexLoader ??= import('katex/contrib/auto-render').then((m) => m.default));

function renderMath(root = document.body) {
    root.querySelectorAll('[data-math]').forEach((el) => {
        if (el.dataset.mathRendered === '1') return;
        el.dataset.mathRendered = '1';

        // Trang công khai/đa số bài không có công thức: bỏ qua, không kéo thư viện về làm chậm lần tải đầu.
        if (!MATH_HINT.test(el.textContent)) return;

        loadKatex().then((renderMathInElement) => {
            renderMathInElement(el, {
                delimiters: [
                    { left: '$$', right: '$$', display: true },
                    { left: '\\[', right: '\\]', display: true },
                    { left: '$', right: '$', display: false },
                    { left: '\\(', right: '\\)', display: false },
                ],
                throwOnError: false,
            });
        });
    });

    // Sau công thức: thay token [[hinh:...]] bằng hình 3D chỉ-xem (Three.js chỉ tải khi trang có hình).
    renderGeometry(root);
}

window.renderMath = renderMath;

document.addEventListener('DOMContentLoaded', () => {
    renderMath();
    initAiTutor();
    initActivityTracker();
    // driver.js (~28KB) chỉ cần ở trang sau đăng nhập có tour/danh sách "Bắt đầu" — trang chủ khỏi tải.
    if (document.getElementById('onboarding-tour') || document.querySelector('[data-onboarding-hide]')) {
        import('./onboarding-tour').then((m) => m.initOnboarding());
    }

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
