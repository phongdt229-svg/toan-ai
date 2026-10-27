/**
 * Sinh CSS + font Bootstrap Icons vào resources/scss/vendor/generated/ (thư mục bị .gitignore).
 *
 *   node scripts/bootstrap-icons.mjs          → bản build: chỉ giữ icon có trong code (npm run build)
 *   node scripts/bootstrap-icons.mjs --full   → đủ ~2000 icon (npm run dev — thêm icon mới khỏi phải chạy lại)
 *
 * Tại sao: font gốc 127 KB + CSS ~85 KB tải ở MỌI trang và tranh băng thông với CSS/font chữ — PageSpeed
 * mobile tính thẳng vào FCP/LCP. Site chỉ dùng ~200 icon.
 *
 * Quét tên "bi-xxx" trong view, JS, PHP, config. Quy ước bắt buộc: luôn viết ĐỦ tên class ('bi-house'),
 * không ghép chuỗi ('bi-' . $name) — ghép thì script không thấy và icon mất ở production.
 */
import { readFileSync, writeFileSync, mkdirSync, readdirSync, statSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import subsetFont from 'subset-font';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const pkg = join(root, 'node_modules/bootstrap-icons/font');
const outDir = join(root, 'resources/scss/vendor/generated');
const full = process.argv.includes('--full');

const SCAN_DIRS = ['resources/views', 'resources/js', 'resources/scss', 'app', 'config', 'database', 'routes'];
const SCAN_EXT = /\.(php|js|scss|json)$/;

mkdirSync(outDir, { recursive: true });

if (full) {
    // Dev: dùng nguyên bản, đường dẫn tương đối từ thư mục generated tới node_modules.
    writeFileSync(join(outDir, '_bootstrap-icons.scss'),
        '// SINH TỰ ĐỘNG (scripts/bootstrap-icons.mjs --full) — đừng sửa tay.\n'
        + '@import "../../../../node_modules/bootstrap-icons/font/bootstrap-icons.css";\n');
    console.log('bootstrap-icons: bản đầy đủ (dev)');
    process.exit(0);
}

const codepoints = JSON.parse(readFileSync(join(pkg, 'bootstrap-icons.json'), 'utf8'));

function* walk(dir) {
    for (const name of readdirSync(dir)) {
        const path = join(dir, name);
        if (statSync(path).isDirectory()) {
            if (name !== 'generated') yield* walk(path);
        } else if (SCAN_EXT.test(name)) {
            yield path;
        }
    }
}

const used = new Set();
for (const dir of SCAN_DIRS) {
    for (const file of walk(join(root, dir))) {
        for (const [, name] of readFileSync(file, 'utf8').matchAll(/\bbi-([a-z0-9]+(?:-[a-z0-9]+)*)/g)) {
            if (name in codepoints) used.add(name);
        }
    }
}

const names = [...used].sort();
const text = names.map((name) => String.fromCodePoint(codepoints[name])).join('');

const font = await subsetFont(readFileSync(join(pkg, 'fonts/bootstrap-icons.woff2')), text, { targetFormat: 'woff2' });
writeFileSync(join(outDir, 'bootstrap-icons.woff2'), font);

// Giữ nguyên phần đầu của CSS gốc (license + quy tắc .bi::before), chỉ đổi nguồn font và lọc danh sách icon.
const original = readFileSync(join(pkg, 'bootstrap-icons.css'), 'utf8');
const head = original
    .slice(0, original.indexOf('.bi-123::before'))
    .replace(/src: url\([^;]+;/s, 'src: url("./bootstrap-icons.woff2") format("woff2");');
const rules = names.map((name) => `.bi-${name}::before { content: "\\${codepoints[name].toString(16)}"; }`).join('\n');

writeFileSync(join(outDir, '_bootstrap-icons.scss'),
    `// SINH TỰ ĐỘNG (scripts/bootstrap-icons.mjs) — đừng sửa tay. ${names.length} icon.\n${head}${rules}\n`);

console.log(`bootstrap-icons: ${names.length} icon, font ${(font.length / 1024).toFixed(1)} KB`);
