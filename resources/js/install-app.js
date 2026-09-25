/**
 * Nút "Cài ứng dụng" (PWA).
 *
 * Trình duyệt bắn `beforeinstallprompt` khi trang đủ điều kiện cài (có manifest, có service
 * worker, chạy HTTPS). Ta giữ sự kiện đó lại rồi mới mở hộp cài lúc người dùng bấm nút —
 * gọi thẳng lúc trang vừa tải thì trình duyệt chặn, vì người dùng chưa hề bày tỏ ý muốn.
 *
 * Nút ẩn mặc định và CHỈ hiện khi trình duyệt nói là cài được. Safari trên iPhone không
 * bắn sự kiện này (phải "Thêm vào MH chính" bằng tay) nên nút sẽ không hiện ở đó —
 * bài hướng dẫn "Cài như ứng dụng" lo phần đó, hiện nút chết chỉ làm người dùng bực.
 */
const buttons = document.querySelectorAll('[data-install-app]');

if (buttons.length) {
    let deferred = null;

    const show = (visible) => buttons.forEach((b) => { b.hidden = !visible; });

    // Đã cài rồi thì mở ở chế độ standalone — không mời cài nữa.
    const installed = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone;

    window.addEventListener('beforeinstallprompt', (event) => {
        // Chặn thanh mời cài mặc định của trình duyệt để dùng nút của mình.
        event.preventDefault();
        deferred = event;

        if (!installed) show(true);
    });

    buttons.forEach((button) => {
        button.addEventListener('click', async () => {
            if (!deferred) return;

            button.disabled = true;
            deferred.prompt();
            await deferred.userChoice;

            // Sự kiện chỉ dùng được một lần; từ chối thì trình duyệt sẽ bắn lại vào lần sau.
            deferred = null;
            show(false);
        });
    });

    window.addEventListener('appinstalled', () => {
        deferred = null;
        show(false);
    });
}
