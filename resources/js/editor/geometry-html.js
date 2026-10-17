/**
 * Cầu nối giữa định dạng LƯU (token văn bản `[[hinh:BASE64]]`) và định dạng của trình soạn (node có thể bấm sửa),
 * đối xứng với math-html.js. Server/HtmlSanitizer không cần biết gì về node này.
 */
import { TOKEN_RE } from '../geometry-embed';

function parse(html) {
    return new DOMParser().parseFromString(`<!doctype html><body>${html || ''}</body>`, 'text/html');
}

function makeBlock(doc, b64) {
    const el = doc.createElement('div');
    el.setAttribute('data-geometry-block', '');
    el.setAttribute('data-config', b64);

    return el;
}

export function toEditorHtml(html) {
    if (!html || !html.includes('[[hinh:')) return html;

    const doc = parse(html);
    const walker = doc.createTreeWalker(doc.body, NodeFilter.SHOW_TEXT);
    const nodes = [];
    while (walker.nextNode()) nodes.push(walker.currentNode);

    for (const node of nodes) {
        // Trong khối code, token chỉ là chữ thường.
        if (node.parentElement?.closest('code, pre')) continue;

        const text = node.data;
        TOKEN_RE.lastIndex = 0;
        if (!TOKEN_RE.test(text)) continue;
        TOKEN_RE.lastIndex = 0;

        const frag = doc.createDocumentFragment();
        let last = 0;
        let m;
        while ((m = TOKEN_RE.exec(text)) !== null) {
            if (m.index > last) frag.append(text.slice(last, m.index));
            frag.append(makeBlock(doc, m[1]));
            last = m.index + m[0].length;
        }
        if (last < text.length) frag.append(text.slice(last));

        // Token đứng riêng một đoạn → thay cả <p> (node là khối, không được nằm trong <p>).
        const p = node.parentElement;
        if (p?.tagName === 'P' && p.childNodes.length === 1) p.replaceWith(frag);
        else node.replaceWith(frag);
    }

    return doc.body.innerHTML;
}

export function fromEditorHtml(html) {
    if (!html || !html.includes('data-geometry-block')) return html;

    const doc = parse(html);
    doc.body.querySelectorAll('[data-geometry-block]').forEach((el) => {
        const p = doc.createElement('p');
        p.textContent = `[[hinh:${el.getAttribute('data-config') || ''}]]`;
        el.replaceWith(p);
    });

    return doc.body.innerHTML;
}
