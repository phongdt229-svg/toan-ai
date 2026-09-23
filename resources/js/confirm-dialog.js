/**
 * Hộp thoại xác nhận dùng chung, thay cho `confirm()` mặc định của trình duyệt.
 *
 * Vì sao bỏ `confirm()`: hộp mặc định không theo giao diện trang, không đổi được chữ trên nút
 * ("OK/Cancel" tiếng Anh giữa trang tiếng Việt), và trên một số trình duyệt di động nó bị chặn
 * hoặc hiện kèm ô "không hiện lại nữa" — người dùng tick vào là mọi xác nhận sau đó im lặng trôi qua.
 *
 * Cách dùng trong Blade — không cần viết JS:
 *   <form data-confirm="Xoá mục này?" data-confirm-ok="Xoá" data-confirm-variant="danger">
 *   <a href="..." data-confirm="Rời khỏi trang?">
 *
 * Gọi từ JS khi cần chờ kết quả:
 *   if (await window.confirmDialog('Nộp bài?')) { ... }
 *   await window.alertDialog('Hết giờ rồi.');
 */
import * as bootstrap from 'bootstrap';

const MARKUP = `
<div class="modal fade" id="app-confirm" tabindex="-1" aria-hidden="true" aria-labelledby="app-confirm-title">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h2 class="modal-title h6 fw-bold" id="app-confirm-title"></h2>
            </div>
            <div class="modal-body" id="app-confirm-body"></div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal"
                        id="app-confirm-cancel">Huỷ</button>
                <button type="button" class="btn btn-sm btn-primary" id="app-confirm-ok">Đồng ý</button>
            </div>
        </div>
    </div>
</div>`;

let modal = null;
let els = null;

function ensureModal() {
    if (modal) return modal;

    document.body.insertAdjacentHTML('beforeend', MARKUP);

    els = {
        root: document.querySelector('#app-confirm'),
        title: document.querySelector('#app-confirm-title'),
        body: document.querySelector('#app-confirm-body'),
        ok: document.querySelector('#app-confirm-ok'),
        cancel: document.querySelector('#app-confirm-cancel'),
    };

    modal = new bootstrap.Modal(els.root);

    return modal;
}

/**
 * @param {object} options message, title, ok, variant ('danger' cho việc không hoàn tác được), alert
 * @returns {Promise<boolean>}
 */
function open({ message, title = 'Xác nhận', ok = 'Đồng ý', variant = 'primary', alert = false }) {
    ensureModal();

    els.title.textContent = title;
    els.body.textContent = message;
    els.ok.textContent = alert ? 'Đã hiểu' : ok;
    els.ok.className = `btn btn-sm btn-${variant}`;
    els.cancel.hidden = alert;

    return new Promise((resolve) => {
        let answer = false;

        const onOk = () => { answer = true; modal.hide(); };

        els.ok.addEventListener('click', onOk, { once: true });
        els.root.addEventListener('hidden.bs.modal', () => {
            els.ok.removeEventListener('click', onOk);
            resolve(answer);
        }, { once: true });

        modal.show();
        // Nút đồng ý nhận focus: bấm Enter là xong, bấm Esc là huỷ.
        els.root.addEventListener('shown.bs.modal', () => els.ok.focus(), { once: true });
    });
}

window.confirmDialog = (message, options = {}) => open({ message, ...options });
window.alertDialog = (message, options = {}) => open({ message, alert: true, ...options });

/** Đọc tuỳ chọn từ thuộc tính data-* của phần tử kích hoạt. */
function optionsFrom(el, trigger) {
    // Nút màu đỏ thì hộp thoại cũng đỏ theo — không bắt người viết Blade khai lại.
    const looksDangerous = (trigger || el).className?.includes('danger');

    return {
        message: el.dataset.confirm,
        title: el.dataset.confirmTitle || 'Xác nhận',
        ok: el.dataset.confirmOk || 'Đồng ý',
        variant: el.dataset.confirmVariant || (looksDangerous ? 'danger' : 'primary'),
    };
}

// --- Gắn vào form và link có data-confirm ------------------------------------------------

document.addEventListener('submit', async (event) => {
    const form = event.target;

    if (!form.dataset?.confirm || form.dataset.confirmed === '1') return;

    event.preventDefault();

    if (!(await open(optionsFrom(form, event.submitter)))) return;

    // Gửi lại đúng nút đã bấm để form nhiều nút submit không mất name/value.
    form.dataset.confirmed = '1';

    if (event.submitter && form.requestSubmit) {
        form.requestSubmit(event.submitter);
    } else {
        form.submit();
    }
}, true);

document.addEventListener('click', async (event) => {
    const link = event.target.closest('a[data-confirm]');

    if (!link) return;

    event.preventDefault();

    if (await open(optionsFrom(link))) {
        window.location.href = link.href;
    }
});
