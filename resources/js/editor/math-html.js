/**
 * Chuyển đổi giữa định dạng LƯU trong DB và định dạng của trình soạn thảo.
 *
 * DB (và trang học sinh) giữ HTML thường với công thức dạng văn bản: $...$, $$...$$, \(...\), \[...\]
 * — KaTeX auto-render hiển thị lúc xem. Trình soạn thảo cần công thức là một "node" riêng
 * (bấm vào sửa được, không bị gõ nhầm vào giữa), nên:
 *   - lúc mở:  văn bản $...$  → <span data-math-inline data-latex="...">
 *   - lúc lưu: node công thức → văn bản $...$ / đoạn $$...$$
 * Giữ nguyên định dạng DB để bài cũ, AI soạn bài, nhập Excel… không phải đổi gì.
 */

const MATH_RE = /\$\$([\s\S]+?)\$\$|\\\[([\s\S]+?)\\\]|\\\(([\s\S]+?)\\\)|\$([^$\n]+?)\$/g;

function parse(html) {
    return new DOMParser().parseFromString(`<!doctype html><body>${html || ''}</body>`, 'text/html');
}

export function toEditorHtml(html) {
    const doc = parse(html);
    const walker = doc.createTreeWalker(doc.body, NodeFilter.SHOW_TEXT);
    const textNodes = [];
    while (walker.nextNode()) textNodes.push(walker.currentNode);

    for (const node of textNodes) {
        // Trong khối code, "$" là ký tự thường.
        if (node.parentElement?.closest('code, pre')) continue;

        const text = node.data;
        MATH_RE.lastIndex = 0;
        if (!MATH_RE.test(text)) continue;
        MATH_RE.lastIndex = 0;

        const frag = doc.createDocumentFragment();
        let last = 0;
        let m;

        while ((m = MATH_RE.exec(text)) !== null) {
            if (m.index > last) frag.append(text.slice(last, m.index));

            const isBlock = m[1] !== undefined || m[2] !== undefined;
            const latex = (m[1] ?? m[2] ?? m[3] ?? m[4]).trim();
            const el = doc.createElement(isBlock ? 'div' : 'span');
            el.setAttribute(isBlock ? 'data-math-block' : 'data-math-inline', '');
            el.setAttribute('data-latex', latex);
            frag.append(el);

            last = m.index + m[0].length;
        }

        if (last < text.length) frag.append(text.slice(last));
        node.replaceWith(frag);
    }

    return doc.body.innerHTML;
}

export function fromEditorHtml(html) {
    const doc = parse(html);

    doc.body.querySelectorAll('[data-math-inline]').forEach((el) => {
        el.replaceWith(doc.createTextNode(`$${el.getAttribute('data-latex') || ''}$`));
    });

    doc.body.querySelectorAll('[data-math-block]').forEach((el) => {
        const p = doc.createElement('p');
        p.textContent = `$$${el.getAttribute('data-latex') || ''}$$`;
        el.replaceWith(p);
    });

    const out = doc.body.innerHTML.trim();

    // Trình soạn thảo rỗng vẫn trả "<p></p>" — coi như chưa nhập để server báo "bắt buộc".
    return out === '<p></p>' ? '' : out;
}
