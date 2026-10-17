/**
 * Hình học nhúng trong nội dung (bài học, câu hỏi, lời giải) — dùng chung cho trình soạn và trang xem.
 *
 * Trong DB hình là MỘT token văn bản `[[hinh:BASE64(JSON)]]` giống cách công thức là `$...$`: base64 chỉ gồm
 * A-Z a-z 0-9 + / = nên đi qua HtmlSanitizer nguyên vẹn mà không phải mở thêm thẻ/thuộc tính nào.
 * Xem docs/SPEC_HINH_HOC_SOAN_BAI.md §3.1.
 */

export const TOKEN_RE = /\[\[hinh:([A-Za-z0-9+/=]+)\]\]/g;

export function encodeConfig(config) {
    const bytes = new TextEncoder().encode(JSON.stringify(config));
    let binary = '';
    bytes.forEach((b) => { binary += String.fromCharCode(b); });

    return btoa(binary);
}

/** Trả null nếu token hỏng — nơi gọi bỏ qua hình đó chứ không làm hỏng cả bài. */
export function decodeConfig(b64) {
    try {
        const binary = atob(b64);
        const bytes = Uint8Array.from(binary, (c) => c.charCodeAt(0));
        const config = JSON.parse(new TextDecoder().decode(bytes));

        return config && typeof config === 'object' ? config : null;
    } catch {
        return null;
    }
}

const markupCache = {};

/** Khung HTML của công cụ ('3d' | '2d') — tải một lần cho cả trang rồi dùng lại. */
export function loadMarkup(type) {
    const url = type === '2d' ? '/hinh-hoc/phang/mau' : '/hinh-hoc/khong-gian/mau';
    markupCache[type] ??= fetch(url, { headers: { Accept: 'text/html' } })
        .then((r) => {
            if (!r.ok) throw new Error('markup');

            return r.text();
        })
        .catch((e) => {
            delete markupCache[type];
            throw e;
        });

    return markupCache[type];
}

export const SHAPE_NAMES = {
    box: 'Hình hộp chữ nhật', cube: 'Hình lập phương', pyramid3: 'Chóp tam giác đều', pyramid4: 'Chóp tứ giác đều',
    pyramidRect: 'Chóp S.ABCD đáy chữ nhật', prismRight: 'Lăng trụ đứng', tetraRight: 'Tứ diện vuông',
    cylinder: 'Hình trụ', cone: 'Hình nón', sphere: 'Hình cầu',
};

/** Tóm tắt một dòng để hiện trong trình soạn / form câu hỏi. */
export function describeConfig(cfg) {
    if (!cfg) return '(hình hỏng)';
    if (cfg.type === '2d') {
        return `Hình phẳng 2D · ${cfg.points?.length || 0} điểm · ${cfg.constructions?.length || 0} hình`;
    }

    return `Hình 3D: ${SHAPE_NAMES[cfg.shape] || 'Hình không gian'} · ${cfg.lines?.length || 0} đường · ${cfg.points?.length || 0} điểm`;
}

/** Dựng công cụ 2D hoặc 3D (theo `type`, mặc định 3D) vào `container`. Thư viện nặng chỉ tải khi cần. */
export async function mountGeometry(container, type, config, options = {}) {
    const is2d = type === '2d';
    const [html, mod] = await Promise.all([
        loadMarkup(is2d ? '2d' : '3d'),
        is2d ? import('./plane-geometry-widget.js') : import('./solid-geometry-widget.js'),
    ]);
    container.innerHTML = html;
    const root = container.querySelector(is2d ? '[data-plane-geometry]' : '[data-solid-geometry]');
    (is2d ? mod.mountPlaneGeometry : mod.mountSolidGeometry)(root, { initialConfig: config, ...options });

    return root;
}

/**
 * Trang xem: thay mỗi token `[[hinh:...]]` trong [data-math] bằng hình chỉ-xem, đúng vị trí trong dòng chữ.
 * Idempotent — token đã thay thì không còn trong văn bản nên gọi lại không nhân đôi.
 */
export function renderGeometry(root = document.body) {
    root.querySelectorAll('[data-math]').forEach((scope) => {
        const walker = document.createTreeWalker(scope, NodeFilter.SHOW_TEXT);
        const nodes = [];
        while (walker.nextNode()) {
            if (walker.currentNode.data.includes('[[hinh:')) nodes.push(walker.currentNode);
        }

        for (const node of nodes) {
            if (node.parentElement?.closest('code, pre')) continue;

            const text = node.data;
            TOKEN_RE.lastIndex = 0;
            const frag = document.createDocumentFragment();
            let last = 0;
            let m;

            while ((m = TOKEN_RE.exec(text)) !== null) {
                const config = decodeConfig(m[1]);
                if (!config) continue;

                if (m.index > last) frag.append(text.slice(last, m.index));
                const host = document.createElement('div');
                host.className = 'geometry-embed my-3';
                frag.append(host);
                mountGeometry(host, config.type, config, { readOnly: true }).catch(() => {
                    host.className = 'alert alert-warning small';
                    host.textContent = 'Không tải được hình minh hoạ.';
                });
                last = m.index + m[0].length;
            }

            if (last === 0) continue;
            if (last < text.length) frag.append(text.slice(last));

            // Token đứng riêng trong <p> → thay cả <p>, tránh khối <div> nằm trong <p> và đoạn rỗng thừa.
            const p = node.parentElement;
            if (p?.tagName === 'P' && p.childNodes.length === 1) p.replaceWith(frag);
            else node.replaceWith(frag);
        }
    });
}
