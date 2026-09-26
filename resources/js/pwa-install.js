/**
 * Nút "Cài đặt ứng dụng" ở trang chủ (entry riêng: chỉ trang chủ cần, không nạp ở trang khác).
 *
 * Trình duyệt chỉ bắn `beforeinstallprompt` khi đủ điều kiện PWA thật (manifest hợp lệ,
 * service worker đã đăng ký, chưa cài) — nút ẩn mặc định, chỉ hiện khi trình duyệt thực sự
 * cho cài. Safari (desktop lẫn iOS) không có API này nên không bao giờ hiện nút chết —
 * iOS thay bằng một dòng hướng dẫn (chỉ cài được qua menu Chia sẻ).
 */
let deferredPrompt = null;

function isStandalone() {
    return window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
}

document.addEventListener('DOMContentLoaded', () => {
    const btn = document.querySelector('#pwa-install-btn');
    const iosHint = document.querySelector('#pwa-ios-hint');

    if (!btn || isStandalone()) return; // đã mở từ bản đã cài — không cần mời cài nữa

    const isIos = /iphone|ipad|ipod/i.test(navigator.userAgent) && !window.MSStream;

    if (isIos) {
        iosHint?.classList.remove('d-none');
        return;
    }

    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault(); // tự hiện nút của mình thay vì banner mini mặc định của Chrome
        deferredPrompt = event;
        btn.classList.remove('d-none');
    });

    btn.addEventListener('click', async () => {
        if (!deferredPrompt) return;

        btn.disabled = true;
        deferredPrompt.prompt();
        await deferredPrompt.userChoice; // Chấp nhận hay Từ chối đều dọn nút — không mời lại
        deferredPrompt = null;
        btn.classList.add('d-none');
        btn.disabled = false;
    });

    // Cài qua đường khác (menu trình duyệt) trong lúc nút vẫn hiện — cũng phải ẩn đi.
    window.addEventListener('appinstalled', () => btn.classList.add('d-none'));
});
