/**
 * Bật/tắt thông báo đẩy. Chỉ chạy khi trang có phần tử #push-toggle
 * (trang Cài đặt → Thông báo) — CỐ Ý không hỏi quyền lúc vừa vào trang:
 * trình duyệt phạt kiểu xin quyền vô cớ, và người dùng bấm "Chặn" là mất luôn,
 * không xin lại được.
 */
const toggle = document.querySelector('#push-toggle');

if (toggle) {
    const status = document.querySelector('#push-status');
    const supported = 'serviceWorker' in navigator && 'PushManager' in window;

    const say = (text) => { if (status) status.textContent = text; };

    const urlBase64ToUint8Array = (base64) => {
        const padded = (base64 + '='.repeat((4 - (base64.length % 4)) % 4)).replace(/-/g, '+').replace(/_/g, '/');
        return Uint8Array.from(atob(padded), (c) => c.charCodeAt(0));
    };

    const post = (url, body, method = 'POST') => fetch(url, {
        method,
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
        },
        body: JSON.stringify(body),
    });

    const refresh = async () => {
        if (!supported) {
            toggle.disabled = true;
            say('Trình duyệt này không hỗ trợ thông báo đẩy.');
            return null;
        }

        if (Notification.permission === 'denied') {
            toggle.disabled = true;
            toggle.checked = false;
            say('Bạn đã chặn thông báo cho trang này — mở lại trong cài đặt trình duyệt.');
            return null;
        }

        const registration = await navigator.serviceWorker.ready;
        const existing = await registration.pushManager.getSubscription();
        toggle.checked = !!existing;
        say(existing ? 'Đang bật trên thiết bị này.' : 'Đang tắt trên thiết bị này.');

        return registration;
    };

    toggle.addEventListener('change', async () => {
        // Đăng ký đẩy phải bắt tay với dịch vụ của trình duyệt qua mạng, có khi mất vài giây —
        // khoá công tắc trong lúc chờ để người dùng không bấm liên tục.
        toggle.disabled = true;
        say('Đang xử lý…');

        try {
            // await này phải nằm TRONG try: hỏng ở đây mà để ngoài là im lặng hoàn toàn.
            const registration = await navigator.serviceWorker.ready;

            if (toggle.checked) {
                // Đã cho phép từ trước thì đừng hỏi lại: thừa một nhịp chờ,
                // và có trình duyệt không bao giờ trả lời lần hỏi thứ hai.
                const permission = Notification.permission === 'granted'
                    ? 'granted'
                    : await Notification.requestPermission();

                if (permission !== 'granted') {
                    toggle.checked = false;
                    say('Bạn chưa cho phép hiện thông báo.');
                    return;
                }

                const subscription = await registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: urlBase64ToUint8Array(toggle.dataset.key),
                });

                await post('/thong-bao/day', subscription.toJSON());
                say('Đã bật. Thiết bị này sẽ nhận nhắc hạn gói và nhắc bài giao.');
            } else {
                const subscription = await registration.pushManager.getSubscription();

                if (subscription) {
                    await post('/thong-bao/day', { endpoint: subscription.endpoint }, 'DELETE');
                    await subscription.unsubscribe();
                }

                say('Đã tắt trên thiết bị này.');
            }
        } catch (e) {
            toggle.checked = !toggle.checked;
            say('Không đổi được lúc này, thử lại sau nhé.');
        } finally {
            toggle.disabled = false;
        }
    });

    refresh();
}
