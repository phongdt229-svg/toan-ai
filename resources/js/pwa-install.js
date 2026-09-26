/**
 * Nút "Cài đặt ứng dụng" ở trang chủ (entry riêng: chỉ trang chủ cần, không nạp ở trang khác).
 *
 * Nút hiện ngay trên mọi trình duyệt desktop/Android (trừ đã cài rồi hoặc iOS) — không chờ
 * `beforeinstallprompt` mới hiện, vì sự kiện này có thể tới trễ vài giây hoặc không tới (Chrome
 * yêu cầu một ít tương tác với trang trước, hoặc người dùng đã từ chối lần trước). Bấm mà
 * trình duyệt đã có sẵn lời mời cài thì dùng lời mời thật; chưa có thì hướng dẫn thủ công
 * thay vì im lặng không phản hồi. iOS Safari không có API cài đặt nên thay bằng dòng hướng dẫn
 * riêng (chỉ cài được qua menu Chia sẻ).
 */
let deferredPrompt = null;

function isStandalone() {
    return window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
}

document.addEventListener('DOMContentLoaded', () => {
    const btn = document.querySelector('#pwa-install-btn');
    const iosHint = document.querySelector('#pwa-ios-hint');

    if (!btn || isStandalone()) return; // đã mở từ bản đã cài — không cần mời cài nữa

    const ua = navigator.userAgent;
    const isIos = /iphone|ipad|ipod/i.test(ua) && !window.MSStream;

    if (isIos) {
        iosHint?.classList.remove('d-none');
        return;
    }

    btn.classList.remove('d-none');

    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault(); // tự hỏi qua nút của mình thay vì banner mini mặc định của Chrome
        deferredPrompt = event;
    });

    btn.addEventListener('click', async () => {
        if (deferredPrompt) {
            btn.disabled = true;
            deferredPrompt.prompt();
            await deferredPrompt.userChoice; // Chấp nhận hay Từ chối đều dọn nút — không mời lại
            deferredPrompt = null;
            btn.classList.add('d-none');
            btn.disabled = false;
            return;
        }

        // Chưa có lời mời cài sẵn (trình duyệt chưa bắn sự kiện, hoặc không hỗ trợ) — hướng dẫn
        // thủ công. Dùng hộp thoại của app (window.alertDialog), không dùng alert() mặc định.
        const isFirefox = /firefox/i.test(ua);
        const message = isFirefox
            ? 'Firefox trên máy tính chưa hỗ trợ cài ứng dụng kiểu này. Hãy mở trang bằng Chrome hoặc Edge, hoặc mở trên điện thoại.'
            : 'Mở menu trình duyệt (dấu ⋮ ở góc trên bên phải), hoặc bấm biểu tượng cài đặt trên thanh địa chỉ, rồi chọn "Cài đặt TOÁN AI".';

        if (window.alertDialog) {
            await window.alertDialog(message, { title: 'Cài đặt ứng dụng' });
        }
    });

    window.addEventListener('appinstalled', () => btn.classList.add('d-none'));
});
