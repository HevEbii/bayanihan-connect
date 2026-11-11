/* Bayanihan Connect service worker - offline shell for field use (FR07). */
const VERSION = 'bc-v2';
const STATIC_CACHE = `${VERSION}-static`;
const PAGE_CACHE = `${VERSION}-pages`;
const BASE = new URL(self.registration.scope);

// Only these screens are kept for offline use; other pages (e.g. beneficiary lists) are never stored on the device.
const OFFLINE_PAGES = ['dashboard', 'beneficiaries/create', 'distributions/create', 'messages', 'my-schedule'];
const STATIC_ASSETS = ['css/app.css', 'js/app.js', 'icons/icon.svg', 'manifest.webmanifest', 'offline'];

const url = (path) => new URL(path, BASE).href;
const relPath = (href) => new URL(href).pathname.replace(BASE.pathname, '').replace(/\/$/, '');
const isOfflinePage = (href) => OFFLINE_PAGES.includes(relPath(href));

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(STATIC_CACHE).then((cache) => cache.addAll(STATIC_ASSETS.map(url))).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((k) => !k.startsWith(VERSION)).map((k) => caches.delete(k))))
            .then(() => self.clients.claim())
    );
});

async function cachePage(request, response) {
    if (response.ok && !response.redirected && isOfflinePage(request.url)) {
        const cache = await caches.open(PAGE_CACHE);
        await cache.put(request.url.split('?')[0], response.clone());
    }
    return response;
}

self.addEventListener('fetch', (event) => {
    const { request } = event;
    if (request.method !== 'GET' || new URL(request.url).origin !== BASE.origin) return;

    if (request.mode === 'navigate') {
        // Network first so data is always fresh online; fall back to the saved copy offline.
        event.respondWith(
            fetch(request)
                .then((response) => cachePage(request, response))
                .catch(async () => (await caches.match(request.url.split('?')[0])) || caches.match(url('offline')))
        );
        return;
    }

    if (STATIC_ASSETS.some((p) => relPath(request.url) === p)) {
        // Static assets are versioned (?v=<mtime>): an exact match is current; otherwise fetch it, and
        // fall back to any cached version only when offline.
        event.respondWith(
            caches.open(STATIC_CACHE).then(async (cache) => {
                const exact = await cache.match(request);
                if (exact) return exact;
                try {
                    const res = await fetch(request);
                    if (res.ok) cache.put(request, res.clone());
                    return res;
                } catch {
                    return cache.match(request, { ignoreSearch: true });
                }
            })
        );
    }
});

self.addEventListener('message', (event) => {
    const { type, urls } = event.data || {};
    if (type === 'precache' && Array.isArray(urls)) {
        event.waitUntil(Promise.all(urls.map((u) =>
            fetch(u, { credentials: 'same-origin' }).then((res) => cachePage(new Request(u), res)).catch(() => null)
        )));
    }
    if (type === 'clear-pages') {
        // Called on logout so saved screens do not stay on a shared device.
        event.waitUntil(caches.delete(PAGE_CACHE));
    }
});
