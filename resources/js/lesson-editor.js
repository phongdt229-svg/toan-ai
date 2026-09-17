/**
 * Trình soạn thảo trực quan cho nội dung bài học (thay textarea HTML thô).
 *
 * Dùng: <textarea name="content" data-rich-editor>…</textarea> + @vite('resources/js/lesson-editor.js').
 * Textarea vẫn là thứ được submit — trình soạn thảo chỉ ghi ngược HTML vào nó, nên server, Form Request,
 * HtmlSanitizer không phải đổi. Script khác (AI viết lại) đổi textarea.value rồi bắn sự kiện `rich:refresh`.
 */
import { Editor, InputRule, Node, mergeAttributes } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import { TableKit } from '@tiptap/extension-table';
import Placeholder from '@tiptap/extension-placeholder';
import katex from 'katex';
import { fromEditorHtml, toEditorHtml } from './editor/math-html';

// MathLive nặng (~800KB) → chỉ tải khi giáo viên mở hộp thoại công thức lần đầu.
const openMathDialog = async (options) => (await import('./editor/math-dialog')).openMathDialog(options);

// --- Node công thức ----------------------------------------------------------------------

/**
 * Gõ $...$ / $$...$$ → thay CẢ đoạn khớp (kể cả dấu $) bằng node công thức.
 * (nodeInputRule có sẵn của TipTap giữ lại ký tự bao quanh nên không dùng được.)
 */
function mathInputRule(find, getType, block) {
    return new InputRule({
        find,
        handler: ({ state, range, match }) => {
            const node = getType().create({ latex: match[1].trim() });
            const tr = state.tr.delete(range.from, range.to);
            if (block) tr.replaceSelectionWith(node);
            else tr.insert(range.from, node);
        },
    });
}

function mathNodeView(block) {
    return ({ node, getPos, editor }) => {
        let current = node;
        const dom = document.createElement(block ? 'div' : 'span');
        dom.className = block ? 'math-node math-node--block' : 'math-node';
        dom.contentEditable = 'false';
        dom.title = 'Bấm để sửa công thức';

        const render = () => {
            dom.innerHTML = katex.renderToString(current.attrs.latex || '\\square', {
                displayMode: block,
                throwOnError: false,
            });
        };
        render();

        dom.addEventListener('click', async (event) => {
            event.preventDefault();
            if (!editor.isEditable) return;

            const result = await openMathDialog({ latex: current.attrs.latex, block, editing: true });
            const pos = getPos();
            if (!result || typeof pos !== 'number') return;

            const range = { from: pos, to: pos + current.nodeSize };

            if (result.remove) {
                editor.chain().focus().deleteRange(range).run();
            } else {
                editor.chain().focus().insertContentAt(range, {
                    type: result.block ? 'mathBlock' : 'mathInline',
                    attrs: { latex: result.latex },
                }).run();
            }
        });

        return {
            dom,
            update(updated) {
                if (updated.type !== current.type) return false;
                current = updated;
                render();
                return true;
            },
            ignoreMutation: () => true,
        };
    };
}

const latexAttribute = {
    latex: {
        default: '',
        parseHTML: (el) => el.getAttribute('data-latex') || '',
        renderHTML: (attrs) => ({ 'data-latex': attrs.latex }),
    },
};

const MathInline = Node.create({
    name: 'mathInline',
    group: 'inline',
    inline: true,
    atom: true,
    selectable: true,

    addAttributes: () => latexAttribute,
    parseHTML: () => [{ tag: 'span[data-math-inline]' }],
    renderHTML: ({ HTMLAttributes }) => ['span', mergeAttributes({ 'data-math-inline': '' }, HTMLAttributes)],
    addNodeView: () => mathNodeView(false),

    // Gõ $x^2$ → tự thành công thức (không bắt $$ để dành cho công thức riêng dòng).
    addInputRules() {
        return [mathInputRule(/(?<!\$)\$([^$\s](?:[^$]*[^$\s])?)\$$/, () => this.type, false)];
    },
});

const MathBlock = Node.create({
    name: 'mathBlock',
    group: 'block',
    atom: true,
    selectable: true,

    addAttributes: () => latexAttribute,
    parseHTML: () => [{ tag: 'div[data-math-block]' }],
    renderHTML: ({ HTMLAttributes }) => ['div', mergeAttributes({ 'data-math-block': '' }, HTMLAttributes)],
    addNodeView: () => mathNodeView(true),

    addInputRules() {
        return [mathInputRule(/^\$\$([^$]+)\$\$$/, () => this.type, true)];
    },
});

// --- Thanh công cụ -----------------------------------------------------------------------

const BUTTONS = [
    { icon: 'bi-arrow-counterclockwise', title: 'Hoàn tác (Ctrl+Z)', run: (e) => e.chain().focus().undo().run() },
    { icon: 'bi-arrow-clockwise', title: 'Làm lại (Ctrl+Y)', run: (e) => e.chain().focus().redo().run() },
    'sep',
    { label: 'Đoạn', title: 'Đoạn văn thường', run: (e) => e.chain().focus().setParagraph().run(), active: (e) => e.isActive('paragraph') },
    { label: 'H2', title: 'Tiêu đề lớn', run: (e) => e.chain().focus().toggleHeading({ level: 2 }).run(), active: (e) => e.isActive('heading', { level: 2 }) },
    { label: 'H3', title: 'Tiêu đề nhỏ', run: (e) => e.chain().focus().toggleHeading({ level: 3 }).run(), active: (e) => e.isActive('heading', { level: 3 }) },
    'sep',
    { icon: 'bi-type-bold', title: 'Đậm (Ctrl+B)', run: (e) => e.chain().focus().toggleBold().run(), active: (e) => e.isActive('bold') },
    { icon: 'bi-type-italic', title: 'Nghiêng (Ctrl+I)', run: (e) => e.chain().focus().toggleItalic().run(), active: (e) => e.isActive('italic') },
    { icon: 'bi-type-underline', title: 'Gạch chân (Ctrl+U)', run: (e) => e.chain().focus().toggleUnderline().run(), active: (e) => e.isActive('underline') },
    { icon: 'bi-type-strikethrough', title: 'Gạch ngang', run: (e) => e.chain().focus().toggleStrike().run(), active: (e) => e.isActive('strike') },
    'sep',
    { icon: 'bi-list-ul', title: 'Danh sách chấm', run: (e) => e.chain().focus().toggleBulletList().run(), active: (e) => e.isActive('bulletList') },
    { icon: 'bi-list-ol', title: 'Danh sách số', run: (e) => e.chain().focus().toggleOrderedList().run(), active: (e) => e.isActive('orderedList') },
    { icon: 'bi-quote', title: 'Khung ghi chú / trích dẫn', run: (e) => e.chain().focus().toggleBlockquote().run(), active: (e) => e.isActive('blockquote') },
    { icon: 'bi-hr', title: 'Đường kẻ ngang', run: (e) => e.chain().focus().setHorizontalRule().run() },
    { icon: 'bi-link-45deg', title: 'Chèn liên kết', run: promptLink, active: (e) => e.isActive('link') },
    { icon: 'bi-table', title: 'Chèn bảng 3×3', run: (e) => e.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run() },
    'sep',
    { label: '∑ Công thức', title: 'Chèn công thức trong dòng (hoặc gõ $...$)', primary: true, run: (e) => insertMath(e, false) },
    { label: '∑ Riêng dòng', title: 'Chèn công thức riêng một dòng, căn giữa (hoặc gõ $$...$$)', primary: true, run: (e) => insertMath(e, true) },
    'sep',
    { icon: 'bi-eraser', title: 'Xoá định dạng', run: (e) => e.chain().focus().unsetAllMarks().clearNodes().run() },
    { icon: 'bi-code-slash', title: 'Xem / sửa mã HTML', toggleSource: true },
];

// Chỉ hiện khi con trỏ đang trong bảng.
const TABLE_BUTTONS = [
    { label: '+ Hàng', title: 'Thêm hàng bên dưới', run: (e) => e.chain().focus().addRowAfter().run() },
    { label: '+ Cột', title: 'Thêm cột bên phải', run: (e) => e.chain().focus().addColumnAfter().run() },
    { label: '− Hàng', title: 'Xoá hàng', run: (e) => e.chain().focus().deleteRow().run() },
    { label: '− Cột', title: 'Xoá cột', run: (e) => e.chain().focus().deleteColumn().run() },
    { label: 'Xoá bảng', title: 'Xoá cả bảng', run: (e) => e.chain().focus().deleteTable().run() },
];

async function insertMath(editor, block) {
    // Đang bôi đen chữ (vd "x^2 + 1") → mở sẵn làm công thức.
    const { from, to } = editor.state.selection;
    const selected = editor.state.doc.textBetween(from, to, ' ');
    const result = await openMathDialog({ latex: selected, block });
    if (!result || result.remove) return;

    editor.chain().focus().insertContentAt({ from, to }, {
        type: result.block ? 'mathBlock' : 'mathInline',
        attrs: { latex: result.latex },
    }).run();
}

function promptLink(editor) {
    const previous = editor.getAttributes('link').href || 'https://';
    const url = window.prompt('Địa chỉ liên kết (để trống để bỏ liên kết):', previous);
    if (url === null) return;

    if (url.trim() === '' || url === 'https://') {
        editor.chain().focus().extendMarkRange('link').unsetLink().run();
    } else {
        editor.chain().focus().extendMarkRange('link').setLink({ href: url.trim() }).run();
    }
}

function makeButton(def) {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = `btn btn-sm ${def.primary ? 'btn-outline-primary' : 'btn-light'} rich-editor__btn`;
    btn.title = def.title;
    btn.setAttribute('aria-label', def.title);
    btn.innerHTML = def.icon ? `<i class="bi ${def.icon}"></i>` : '';
    if (def.label) btn.append(def.label);
    return btn;
}

// --- Gắn vào textarea --------------------------------------------------------------------

function mount(textarea) {
    const wrapper = document.createElement('div');
    wrapper.className = 'rich-editor';

    const toolbar = document.createElement('div');
    toolbar.className = 'rich-editor__toolbar';
    toolbar.setAttribute('role', 'toolbar');
    toolbar.setAttribute('aria-label', 'Định dạng nội dung');

    const tableBar = document.createElement('div');
    tableBar.className = 'rich-editor__toolbar rich-editor__toolbar--table';
    tableBar.hidden = true;

    const surface = document.createElement('div');
    surface.className = 'rich-editor__surface';

    const hint = document.createElement('div');
    hint.className = 'rich-editor__hint';
    hint.innerHTML = 'Mẹo: gõ <code>$x^2$</code> để chèn công thức nhanh · bấm vào công thức để sửa.';

    wrapper.append(toolbar, tableBar, surface, hint);
    textarea.before(wrapper);

    // Textarea ẩn nhưng vẫn submit. Bỏ `required`: trình duyệt không cho submit khi trường bắt buộc bị ẩn;
    // server vẫn kiểm tra bắt buộc.
    textarea.required = false;
    textarea.classList.add('rich-editor__source', 'd-none');

    let syncing = false;
    const sync = () => {
        if (syncing) return;
        textarea.value = fromEditorHtml(editor.getHTML());
    };

    const editor = new Editor({
        element: surface,
        extensions: [
            StarterKit.configure({
                heading: { levels: [2, 3] },
                code: false,
                codeBlock: false,
                link: { openOnClick: false, autolink: true, HTMLAttributes: { rel: 'noopener noreferrer', target: null } },
            }),
            TableKit.configure({ table: { resizable: false } }),
            Placeholder.configure({ placeholder: textarea.getAttribute('placeholder') || 'Soạn nội dung bài học…' }),
            MathInline,
            MathBlock,
        ],
        content: toEditorHtml(textarea.value),
        onUpdate: sync,
    });

    const refs = [];
    const addButtons = (bar, defs) => {
        for (const def of defs) {
            if (def === 'sep') {
                const sep = document.createElement('span');
                sep.className = 'rich-editor__sep';
                bar.appendChild(sep);
                continue;
            }
            const btn = makeButton(def);
            bar.appendChild(btn);
            refs.push([btn, def]);

            btn.addEventListener('mousedown', (e) => e.preventDefault()); // giữ vùng chọn trong trình soạn thảo
            btn.addEventListener('click', () => {
                if (def.toggleSource) toggleSource(btn);
                else def.run(editor);
            });
        }
    };
    addButtons(toolbar, BUTTONS);
    addButtons(tableBar, TABLE_BUTTONS);

    const refreshToolbar = () => {
        for (const [btn, def] of refs) {
            if (def.active) btn.classList.toggle('is-active', def.active(editor));
        }
        tableBar.hidden = !editor.isActive('table');
    };
    editor.on('selectionUpdate', refreshToolbar);
    editor.on('transaction', refreshToolbar);
    refreshToolbar();

    // Chế độ mã HTML: cho người quen HTML dán nội dung sẵn có.
    let sourceMode = false;
    function toggleSource(btn) {
        sourceMode = !sourceMode;
        if (sourceMode) {
            sync();
        } else {
            syncing = true;
            editor.commands.setContent(toEditorHtml(textarea.value));
            syncing = false;
            sync();
        }
        textarea.classList.toggle('d-none', !sourceMode);
        surface.classList.toggle('d-none', sourceMode);
        hint.classList.toggle('d-none', sourceMode);
        toolbar.querySelectorAll('button').forEach((b) => { if (b !== btn) b.disabled = sourceMode; });
        btn.classList.toggle('is-active', sourceMode);
        (sourceMode ? textarea : editor).focus();
    }

    // Script ngoài (AI viết lại, hoàn tác) đổi textarea.value rồi báo cập nhật.
    textarea.addEventListener('rich:refresh', () => {
        syncing = true;
        editor.commands.setContent(toEditorHtml(textarea.value));
        syncing = false;
    });

    textarea.form?.addEventListener('submit', () => {
        if (!sourceMode) sync();
    });
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('textarea[data-rich-editor]').forEach(mount);
});
