/**
 * Entry Vite cho trang độc lập /hoc-sinh/hinh-hoc-phang — logic thật nằm ở
 * plane-geometry-widget.js (module dùng chung, xem comment đầu file đó).
 */
import { mountPlaneGeometry } from './plane-geometry-widget.js';

const root = document.querySelector('[data-plane-geometry]');
if (root) mountPlaneGeometry(root);
