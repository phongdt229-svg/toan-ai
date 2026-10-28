import purgecss from '@fullhuman/postcss-purgecss';

// Bootstrap Icons có ~2000 lớp `.bi-*` (~130KB CSS) mà site dùng vài chục. Chỉ cắt lớp icon — mọi selector khác
// (Bootstrap, JS tạo class lúc chạy, class ghép từ biến…) được giữ nguyên để không vỡ giao diện.
// Icon ghép tên động (`bi-{{ $x }}`) vẫn an toàn vì ta quét cả config/ và database/data/ lấy chuỗi `bi-xxx` nguyên văn.
export default {
    plugins: [
        purgecss({
            content: [
                './resources/**/*.php',
                './resources/**/*.js',
                './app/**/*.php',
                './config/**/*.php',
                './database/data/**/*.php',
                './public/*.html',
            ],
            // Nhận cả "bi-bell" lẫn "bi bi-bell-fill" và chuỗi trong PHP/JS.
            defaultExtractor: (content) => content.match(/[A-Za-z0-9_:/-]+/g) || [],
            // Giữ MỌI selector không phải icon; chỉ `.bi-<tên>` mới bị xét.
            safelist: { standard: [/^(?!bi-)/] },
        }),
    ],
};
