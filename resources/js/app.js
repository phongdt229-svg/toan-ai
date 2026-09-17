import './bootstrap';

import * as bootstrap from 'bootstrap';
import renderMathInElement from 'katex/contrib/auto-render';
import { initAiTutor } from './ai-tutor';

window.bootstrap = bootstrap;

/**
 * Render công thức Toán trong mọi phần tử [data-math].
 * Dùng cho nội dung lesson/câu hỏi (Phase 2 trở đi).
 */
function renderMath(root = document.body) {
    root.querySelectorAll('[data-math]').forEach((el) => {
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

window.renderMath = renderMath;

document.addEventListener('DOMContentLoaded', () => {
    renderMath();
    initAiTutor();

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
