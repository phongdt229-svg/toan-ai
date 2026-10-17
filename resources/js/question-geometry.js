/**
 * Nút "Vẽ hình" cho ô nhập câu hỏi / lời giải (textarea thuần, không phải trình soạn Tiptap).
 * Hình là token `[[hinh:BASE64]]` trong chính nội dung — chèn tại con trỏ; mỗi hình đã có hiện thành
 * một dòng "Sửa / Xoá" bên dưới ô. Xem resources/js/geometry-embed.js.
 */
import { TOKEN_RE, decodeConfig, describeConfig, encodeConfig } from './geometry-embed';

const openDialog = async (options) => (await import('./editor/geometry-dialog')).openGeometryDialog(options);

function mount(textarea) {
    const bar = document.createElement('div');
    bar.className = 'mt-1';
    const addBtn = document.createElement('button');
    addBtn.type = 'button';
    addBtn.className = 'btn btn-sm btn-outline-primary';
    addBtn.innerHTML = '<i class="bi bi-box me-1"></i>Vẽ hình (2D / 3D)';
    const list = document.createElement('div');
    list.className = 'small mt-1';
    bar.append(addBtn, list);
    textarea.after(bar);

    const render = () => {
        list.replaceChildren();
        TOKEN_RE.lastIndex = 0;
        const found = [...textarea.value.matchAll(TOKEN_RE)];
        found.forEach((m, i) => {
            const cfg = decodeConfig(m[1]);
            const row = document.createElement('div');
            row.className = 'd-flex align-items-center gap-2 py-1';
            const label = document.createElement('span');
            label.className = 'flex-grow-1';
            label.textContent = `Hình ${i + 1}: ${describeConfig(cfg)}`;
            const edit = document.createElement('button');
            edit.type = 'button';
            edit.className = 'btn btn-sm btn-link p-0';
            edit.textContent = 'Sửa';
            const del = document.createElement('button');
            del.type = 'button';
            del.className = 'btn btn-sm btn-link text-danger p-0';
            del.textContent = 'Xoá';
            row.append(label, edit, del);
            list.append(row);

            edit.addEventListener('click', async () => {
                const result = await openDialog({ config: cfg, editing: true });
                if (!result) return;
                replace(m[0], `[[hinh:${encodeConfig(result)}]]`);
            });
            del.addEventListener('click', async () => {
                if (await window.confirmDialog('Xoá hình này khỏi nội dung?', { ok: 'Xoá', variant: 'danger' })) replace(m[0], '');
            });
        });
    };

    const replace = (from, to) => {
        textarea.value = textarea.value.replace(from, to);
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
    };

    addBtn.addEventListener('click', async () => {
        const result = await openDialog({});
        if (!result) return;
        const token = `\n[[hinh:${encodeConfig(result)}]]\n`;
        const pos = textarea.selectionEnd ?? textarea.value.length;
        textarea.value = textarea.value.slice(0, pos) + token + textarea.value.slice(pos);
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
    });

    textarea.addEventListener('input', render);
    render();
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('textarea[data-geometry-field]').forEach(mount);
});
