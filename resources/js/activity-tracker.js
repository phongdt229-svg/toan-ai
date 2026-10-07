/**
 * Đo thời gian học thật (đặc tả "Logic" — active learning time).
 *
 * Chỉ chạy khi layout gắn <meta name="activity-endpoint"> (portal học sinh). Mỗi 30 giây, nếu tab đang hiển thị,
 * gửi heartbeat kèm cờ "có tương tác trong 60 giây qua". Server tự tính số giây được cộng — script này
 * chỉ báo hiệu, không gửi con số thời gian nào.
 */
const HEARTBEAT_MS = 30_000;
const ACTIVE_WINDOW_MS = 60_000;

export function initActivityTracker() {
    const endpoint = document.querySelector('meta[name="activity-endpoint"]')?.content;
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    if (!endpoint || !token) return;

    let lastInteraction = Date.now();
    const queue = [];
    const path = location.pathname;

    const push = (type, meta = null) => {
        if (queue.length < 20) queue.push({ type, path, meta });
    };

    const send = (keepalive = false) => {
        const events = queue.splice(0);
        fetch(endpoint, {
            method: 'POST',
            keepalive,
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token },
            body: JSON.stringify({ active: Date.now() - lastInteraction < ACTIVE_WINDOW_MS, events }),
        }).catch(() => {});
    };

    ['pointerdown', 'keydown', 'scroll', 'input', 'touchstart'].forEach((name) =>
        window.addEventListener(name, () => { lastInteraction = Date.now(); }, { passive: true, capture: true }),
    );

    if (/^\/hoc-sinh\/bai-hoc\//.test(path)) push('lesson_open');

    // Phần bài học lọt vào màn hình ≥ 3 giây mới tính là "đã xem" — lướt qua không tính.
    const sections = document.querySelectorAll('[data-section-id]');
    if (sections.length && 'IntersectionObserver' in window) {
        const timers = new Map();
        const seen = new Set();
        const io = new IntersectionObserver((entries) => entries.forEach((entry) => {
            const id = entry.target.dataset.sectionId;
            if (seen.has(id)) return;
            if (entry.isIntersecting) {
                timers.set(id, setTimeout(() => { seen.add(id); push('section_view', { section_id: Number(id) }); }, 3000));
            } else {
                clearTimeout(timers.get(id));
            }
        }), { threshold: 0.5 });
        sections.forEach((el) => io.observe(el));
    }

    // Lần đầu chạm vào ô trả lời trên trang = bắt đầu làm bài.
    let exerciseStarted = false;
    document.addEventListener('input', (e) => {
        if (exerciseStarted || !e.target.closest?.('[data-question-id]')) return;
        exerciseStarted = true;
        push('exercise_start');
    }, true);

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'hidden') {
            push('tab_inactive');
            send(true); // keepalive: tab đóng vẫn gửi được
        } else {
            lastInteraction = Date.now();
            push('tab_active');
        }
    });

    setInterval(() => {
        if (document.visibilityState === 'visible') send();
    }, HEARTBEAT_MS);
}
