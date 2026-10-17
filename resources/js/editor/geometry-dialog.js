/**
 * Hộp thoại "Vẽ hình": mở công cụ 3D toàn màn hình trong modal, trả về cấu hình hình khi bấm "Chèn".
 */
import { mountGeometry } from '../geometry-embed';

export function openGeometryDialog({ config = null, editing = false } = {}) {
    return new Promise((resolve) => {
        const el = document.createElement('div');
        el.className = 'modal fade';
        el.tabIndex = -1;
        el.setAttribute('aria-labelledby', 'geometry-dialog-title');
        el.innerHTML = `
            <div class="modal-dialog modal-xl modal-dialog-scrollable modal-fullscreen-lg-down">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 class="modal-title h5" id="geometry-dialog-title">Vẽ hình</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
                    </div>
                    <div class="modal-body">
                        <div class="btn-group mb-2" role="group" aria-label="Loại hình" data-geometry-types>
                            <button type="button" class="btn btn-sm btn-outline-primary" data-type="3d">Hình không gian (3D)</button>
                            <button type="button" class="btn btn-sm btn-outline-primary" data-type="2d">Hình phẳng (2D)</button>
                        </div>
                        <p class="small text-secondary">
                            Vẽ hình theo ý muốn. Học sinh thấy đúng hình này nhưng không sửa được và không thấy các số đo / kết quả.
                        </p>
                        <div data-geometry-host><div class="text-secondary small">Đang tải công cụ…</div></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Huỷ</button>
                        <button type="button" class="btn btn-primary" data-geometry-save>${editing ? 'Cập nhật hình' : 'Chèn vào bài'}</button>
                    </div>
                </div>
            </div>`;
        document.body.appendChild(el);

        const modal = new window.bootstrap.Modal(el);
        let state = config;
        let saved = false;

        const host = el.querySelector('[data-geometry-host]');
        const typeBtns = el.querySelectorAll('[data-type]');
        let type = config?.type === '2d' ? '2d' : '3d';

        // Đang sửa hình có sẵn thì không đổi loại (đổi = vẽ lại từ đầu, dễ mất công nhầm).
        if (editing) el.querySelector('[data-geometry-types]').style.display = 'none';

        const show = (nextType, initial) => {
            type = nextType;
            typeBtns.forEach((b) => b.classList.toggle('active', b.dataset.type === type));
            state = null;
            host.innerHTML = '<div class="text-secondary small">Đang tải công cụ…</div>';
            mountGeometry(host, type, initial, {
                onChange: (s) => { state = { ...s, type }; },
            }).catch(() => { host.textContent = 'Không tải được công cụ vẽ hình.'; });
        };

        typeBtns.forEach((b) => b.addEventListener('click', () => { if (b.dataset.type !== type) show(b.dataset.type, null); }));
        el.addEventListener('shown.bs.modal', () => show(type, config), { once: true });

        el.querySelector('[data-geometry-save]').addEventListener('click', () => {
            saved = true;
            modal.hide();
        });

        el.addEventListener('hidden.bs.modal', () => {
            modal.dispose();
            el.remove();
            resolve(saved && state ? state : null);
        });

        modal.show();
    });
}
