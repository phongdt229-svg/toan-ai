/**
 * Entry Vite cho trang độc lập /hoc-sinh/hinh-hoc-khong-gian — logic thật nằm ở
 * solid-geometry-widget.js (module dùng chung, xem comment đầu file đó).
 */
import { mountSolidGeometry } from './solid-geometry-widget.js';

const root = document.querySelector('[data-solid-geometry]');
if (root) mountSolidGeometry(root);
