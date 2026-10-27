/**
 * Hướng dẫn người mới (OB-02, OB-04): tour chỉ tay bằng driver.js (đóng gói qua Vite — CSP không cho script CDN).
 *
 * Nội dung lấy từ <script id="onboarding-tour"> do layout in ra (config/onboarding.php theo route hiện tại).
 * - Lần đầu: chỉ HIỆN LỜI MỜI ở góc dưới, không tự làm mờ màn hình (OB-D2).
 * - Bước nào không tìm thấy phần tử đang hiển thị (gói Free không có nút đó, menu mobile đang đóng…) thì bỏ qua.
 * - Kết thúc: xem hết → completed; đóng giữa chừng → dismissed + bước đang đứng (để biết bước nào khó hiểu).
 */
// driver.js chỉ tải khi người dùng bấm xem tour — đa số lượt xem trang không cần, khỏi nằm trong bundle chung.
const loadDriver = () => Promise.all([import('driver.js'), import('driver.js/dist/driver.css')]).then(([mod]) => mod.driver);

const token = () => document.querySelector('meta[name="csrf-token"]')?.content;

function post(endpoint, body) {
    return fetch(endpoint, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token() },
        body: JSON.stringify(body),
    }).catch(() => {});
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/** Cùng một data-tour có thể có ở sidebar desktop và menu mobile — lấy cái đang thấy được. */
function visibleTarget(name) {
    return [...document.querySelectorAll(`[data-tour="${CSS.escape(name)}"]`)]
        .find((el) => el.getClientRects().length > 0 && getComputedStyle(el).visibility !== 'hidden') ?? null;
}

function buildSteps(config) {
    return config.steps
        .map((step) => ({ step, element: visibleTarget(step.target) }))
        .filter(({ element }) => element)
        .map(({ step, element }) => ({
            element,
            popover: { title: escapeHtml(step.title), description: escapeHtml(step.text) },
        }));
}

async function runTour(config) {
    const steps = buildSteps(config);
    if (!steps.length) return;

    const driver = await loadDriver();
    const tour = driver({
        steps,
        showProgress: true,
        progressText: '{{current}}/{{total}}',
        nextBtnText: 'Tiếp',
        prevBtnText: 'Quay lại',
        doneBtnText: 'Xong',
        allowClose: true,
        smoothScroll: true,
        onDestroyStarted: () => {
            // Đóng ở bước cuối (bấm "Xong") = xem hết; đóng sớm = bỏ qua ở bước hiện tại.
            const finished = !tour.hasNextStep();
            post(config.endpoint, {
                key: config.key,
                status: finished ? 'completed' : 'dismissed',
                step: finished ? null : tour.getActiveIndex(),
            });
            tour.destroy();
        },
    });

    tour.drive();
}

function showInvite(config) {
    if (!buildSteps(config).length) return;

    const box = document.createElement('div');
    box.className = 'tour-invite card shadow border-primary';
    box.setAttribute('role', 'dialog');
    box.setAttribute('aria-live', 'polite');
    box.innerHTML = `
        <div class="card-body py-2 px-3 d-flex align-items-center gap-2 flex-wrap">
            <i class="bi bi-signpost-2 text-primary fs-5"></i>
            <span class="small flex-grow-1">Lần đầu vào trang này? Xem hướng dẫn nhanh 1 phút.</span>
            <button type="button" class="btn btn-sm btn-primary" data-act="start">Bắt đầu</button>
            <button type="button" class="btn btn-sm btn-link text-secondary" data-act="later">Để sau</button>
        </div>`;

    box.addEventListener('click', (e) => {
        const act = e.target.closest('[data-act]')?.dataset.act;
        if (!act) return;
        box.remove();
        if (act === 'start') {
            runTour(config);
        } else {
            // "Để sau" = không mời lại; vẫn xem được từ menu người dùng → "Hướng dẫn trang này".
            post(config.endpoint, { key: config.key, status: 'dismissed', step: 0 });
        }
    });

    document.body.appendChild(box);
}

export function initOnboarding() {
    // Ẩn danh sách "Bắt đầu" — thẻ có mặt cả ở trang không có tour nên endpoint gắn ngay trên nút.
    document.querySelectorAll('[data-onboarding-hide]').forEach((btn) => btn.addEventListener('click', () => {
        post(btn.dataset.endpoint, { key: 'checklist:hidden', status: 'hidden' });
        document.getElementById('onboarding-checklist')?.remove();
    }));

    const raw = document.getElementById('onboarding-tour')?.textContent;
    if (!raw) return;

    let config;
    try {
        config = JSON.parse(raw);
    } catch {
        return;
    }

    document.querySelectorAll('[data-tour-start]').forEach((btn) => {
        btn.classList.remove('d-none');
        btn.addEventListener('click', () => runTour(config));
    });

    if (config.invite) {
        // Người mới gặp cả dải cookie lẫn lời mời cùng lúc → dải cookie (z-index cao hơn) đè mất nút "Bắt đầu".
        // Mời sau khi dải cookie đóng; đợi thêm một chút cho trang ổn định (ảnh, KaTeX) để khung khoanh không lệch.
        whenCookieBannerGone(() => setTimeout(() => showInvite(config), 800));
    }
}

function whenCookieBannerGone(callback) {
    const banner = () => document.querySelector('.cookie-consent');

    if (!banner()) {
        callback();

        return;
    }

    const observer = new MutationObserver(() => {
        if (!banner()) {
            observer.disconnect();
            callback();
        }
    });
    observer.observe(document.body, { childList: true, subtree: true });
}
