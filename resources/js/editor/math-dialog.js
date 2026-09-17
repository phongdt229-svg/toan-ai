/**
 * Hộp thoại nhập công thức: gõ trực quan bằng MathLive (gõ 1/2 thành phân số, có bàn phím ảo trên điện thoại),
 * bảng ký hiệu hay dùng trong chương trình phổ thông, và ô LaTeX cho ai quen gõ mã.
 */
import katex from 'katex';
import { MathfieldElement } from 'mathlive';

// Font copy sẵn ở public/vendor/mathlive/fonts; tắt âm thanh bàn phím.
MathfieldElement.fontsDirectory = '/vendor/mathlive/fonts';
MathfieldElement.soundsDirectory = null;

// #0 = ô đang chọn, #? = ô trống tiếp theo (cú pháp MathLive).
const PALETTE = [
    ['Cơ bản', [
        ['\\frac{#0}{#?}', 'Phân số'],
        ['#0^{#?}', 'Luỹ thừa'],
        ['#0_{#?}', 'Chỉ số dưới'],
        ['\\sqrt{#0}', 'Căn bậc hai'],
        ['\\sqrt[#?]{#0}', 'Căn bậc n'],
        ['\\left(#0\\right)', 'Ngoặc tròn'],
        ['\\left|#0\\right|', 'Giá trị tuyệt đối'],
        ['#0\\,\\%', 'Phần trăm'],
    ]],
    ['Phép toán', [
        ['\\times', 'Nhân'], ['\\div', 'Chia'], ['\\cdot', 'Chấm nhân'], ['\\pm', 'Cộng trừ'],
        ['\\le', 'Nhỏ hơn hoặc bằng'], ['\\ge', 'Lớn hơn hoặc bằng'], ['\\ne', 'Khác'], ['\\approx', 'Xấp xỉ'],
        ['\\Rightarrow', 'Suy ra'], ['\\Leftrightarrow', 'Tương đương'],
    ]],
    ['Hình học', [
        ['#0^{\\circ}', 'Độ'], ['\\widehat{#0}', 'Góc'], ['\\triangle #0', 'Tam giác'], ['\\overline{#0}', 'Gạch trên'],
        ['\\overrightarrow{#0}', 'Vectơ'], ['\\parallel', 'Song song'], ['\\perp', 'Vuông góc'], ['\\pi', 'Pi'],
    ]],
    ['Tập hợp', [
        ['\\in', 'Thuộc'], ['\\notin', 'Không thuộc'], ['\\subset', 'Tập con'], ['\\cup', 'Hợp'], ['\\cap', 'Giao'],
        ['\\emptyset', 'Rỗng'], ['\\mathbb{N}', 'Số tự nhiên'], ['\\mathbb{Z}', 'Số nguyên'], ['\\mathbb{R}', 'Số thực'], ['\\infty', 'Vô cực'],
    ]],
    ['Nâng cao', [
        ['\\begin{cases}#0\\\\#?\\end{cases}', 'Hệ phương trình'],
        ['\\sin #0', 'sin'], ['\\cos #0', 'cos'], ['\\tan #0', 'tan'],
        ['\\log_{#?}#0', 'Logarit'], ['\\ln #0', 'ln'],
        ['\\lim_{#?\\to #?}#0', 'Giới hạn'], ['\\sum_{#?}^{#?}#0', 'Tổng'],
        ['\\int_{#?}^{#?}#0\\,dx', 'Tích phân'], ['#0\'', 'Đạo hàm'],
    ]],
];

let modal;
let els;
let resolveCurrent = null;

function preview(template) {
    const latex = template.replace(/#0|#\?/g, '\\square');
    try {
        return katex.renderToString(latex, { throwOnError: false });
    } catch {
        return template;
    }
}

function build() {
    const root = document.createElement('div');
    root.className = 'modal fade';
    root.tabIndex = -1;
    root.setAttribute('aria-labelledby', 'math-dialog-title');
    root.innerHTML = `
        <div class="modal-dialog modal-lg modal-dialog-scrollable modal-fullscreen-sm-down">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title h5" id="math-dialog-title">Công thức toán</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-secondary mb-2">
                        Gõ trực tiếp như viết tay: <kbd>1/2</kbd> thành phân số, <kbd>x^2</kbd> thành luỹ thừa,
                        <kbd>sqrt</kbd> thành căn. Hoặc bấm ký hiệu bên dưới.
                    </p>
                    <math-field class="math-dialog__field" math-virtual-keyboard-policy="auto"></math-field>

                    <div class="math-dialog__palette mt-3"></div>

                    <div class="form-check mt-3">
                        <input class="form-check-input" type="checkbox" id="math-dialog-block">
                        <label class="form-check-label" for="math-dialog-block">Hiển thị riêng một dòng, căn giữa</label>
                    </div>

                    <details class="mt-2">
                        <summary class="small text-secondary">Sửa bằng mã LaTeX</summary>
                        <textarea class="form-control font-monospace mt-2" rows="2" spellcheck="false" aria-label="Mã LaTeX"></textarea>
                    </details>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-danger me-auto" data-math-remove hidden>
                        <i class="bi bi-trash me-1"></i>Xoá công thức
                    </button>
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Huỷ</button>
                    <button type="button" class="btn btn-primary" data-math-save>Chèn</button>
                </div>
            </div>
        </div>`;
    document.body.appendChild(root);

    els = {
        field: root.querySelector('math-field'),
        palette: root.querySelector('.math-dialog__palette'),
        block: root.querySelector('#math-dialog-block'),
        source: root.querySelector('textarea'),
        save: root.querySelector('[data-math-save]'),
        remove: root.querySelector('[data-math-remove]'),
    };

    for (const [group, items] of PALETTE) {
        const title = document.createElement('div');
        title.className = 'small fw-semibold text-secondary mt-2 mb-1';
        title.textContent = group;
        const row = document.createElement('div');
        row.className = 'd-flex flex-wrap gap-1';

        for (const [template, label] of items) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn btn-light border math-dialog__key';
            btn.title = label;
            btn.setAttribute('aria-label', label);
            btn.innerHTML = preview(template);
            // Giữ focus trong ô công thức: MathLive bỏ qua lệnh chèn khi ô vừa mất focus vì bấm nút.
            btn.addEventListener('mousedown', (e) => e.preventDefault());
            btn.addEventListener('click', () => {
                els.field.executeCommand(['insert', template, { selectionMode: 'placeholder' }]);
                els.field.focus();
                els.source.value = els.field.value;
            });
            row.appendChild(btn);
        }

        els.palette.append(title, row);
    }

    els.field.addEventListener('input', () => { els.source.value = els.field.value; });
    els.source.addEventListener('input', () => { els.field.value = els.source.value; });

    els.save.addEventListener('click', () => finish({ latex: els.field.value.trim(), block: els.block.checked }));
    els.remove.addEventListener('click', () => finish({ remove: true }));

    // focus: false — không để Bootstrap giữ focus trong modal, nếu không bàn phím ảo MathLive (nằm ngoài modal) không gõ được.
    modal = new window.bootstrap.Modal(root, { focus: false });
    root.addEventListener('shown.bs.modal', () => els.field.focus());
    root.addEventListener('hidden.bs.modal', () => {
        window.mathVirtualKeyboard?.hide();
        finish(null);
    });
}

function finish(result) {
    if (!resolveCurrent) return;
    const resolve = resolveCurrent;
    resolveCurrent = null;
    resolve(result && !result.remove && !result.latex ? null : result);
    modal.hide();
}

/**
 * @returns {Promise<null | {latex: string, block: boolean} | {remove: true}>}
 */
export function openMathDialog({ latex = '', block = false, editing = false } = {}) {
    if (!modal) build();

    els.field.value = latex;
    els.source.value = latex;
    els.block.checked = block;
    els.remove.hidden = !editing;
    els.save.textContent = editing ? 'Cập nhật' : 'Chèn';

    return new Promise((resolve) => {
        resolveCurrent = resolve;
        modal.show();
    });
}
