/*
 * Service worker TOÁN AI (PWA, Phase 10).
 *
 * - Asset build (/build/assets/*, tên có hash) → cache-first: không bao giờ đổi nội dung.
 * - Trang bài học học sinh đã mở (/hoc-sinh/bai-hoc/…) → network-first, lưu bản sao để đọc lại lý thuyết khi mất mạng.
 * - Trang khác → luôn lấy mạng; mất mạng thì hiện /offline.html.
 * - KHÔNG cache: request không phải GET, API, làm bài kiểm tra, thanh toán — dữ liệu phải luôn mới và đi qua server.
 *
 * Đổi VERSION khi sửa file này để xoá cache cũ.
 */
const VERSION = 'v1';
const STATIC_CACHE = `toanai-static-${VERSION}`;
const LESSON_CACHE = 'toanai-lessons';
const OFFLINE_URL = '/offline.html';
const MAX_LESSONS = 30;

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(STATIC_CACHE)
            .then((cache) => cache.addAll([OFFLINE_URL, '/icons/icon-192.png', '/manifest.webmanifest']))
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(
                keys.filter((k) => k.startsWith('toanai-static-') && k !== STATIC_CACHE).map((k) => caches.delete(k)),
            ))
            .then(() => self.clients.claim()),
    );
});

// Đăng xuất trên máy dùng chung → xoá bài học đã lưu (trang có tên học sinh).
self.addEventListener('message', (event) => {
    if (event.data === 'clear-user-cache') {
        event.waitUntil(caches.delete(LESSON_CACHE));
    }
});

self.addEventListener('fetch', (event) => {
    const { request } = event;
    if (request.method !== 'GET') return;

    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;

    if (url.pathname.startsWith('/build/assets/')) {
        event.respondWith(cacheFirst(request));
        return;
    }

    if (request.mode !== 'navigate') return;

    if (/^\/hoc-sinh\/bai-hoc\/[^/]+\/?$/.test(url.pathname)) {
        event.respondWith(lessonNetworkFirst(request));
        return;
    }

    event.respondWith(fetch(request).catch(() => caches.match(OFFLINE_URL)));
});

async function cacheFirst(request) {
    const cached = await caches.match(request);
    if (cached) return cached;

    const response = await fetch(request);
    if (response.ok) {
        const cache = await caches.open(STATIC_CACHE);
        cache.put(request, response.clone());
    }
    return response;
}

async function lessonNetworkFirst(request) {
    const cache = await caches.open(LESSON_CACHE);

    try {
        const response = await fetch(request);
        // Không lưu trang bị chuyển hướng (hết phiên đăng nhập) hay trang lỗi.
        if (response.ok && !response.redirected) {
            await cache.put(request, response.clone());
            trimCache(cache);
        }
        return response;
    } catch (e) {
        return (await cache.match(request)) || (await caches.match(OFFLINE_URL));
    }
}

async function trimCache(cache) {
    const keys = await cache.keys();
    for (let i = 0; i < keys.length - MAX_LESSONS; i++) {
        await cache.delete(keys[i]);
    }
}
