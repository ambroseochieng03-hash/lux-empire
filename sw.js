/*
=========================================
LUX EMPIRE — SERVICE WORKER
=========================================
Three layers:
  1. App shell (CSS/JS) — network-first-with-cache-fallback, same
     helper as everything else below. No longer a separate
     cache-first special case — see note on the old APP_SHELL
     array below.
  2. House images AND videos (same directory) — cache-first for
     images; videos are explicitly excluded from cache.put() below
     because <video> Range requests return 206 Partial Content,
     which the Cache API cannot store (throws if you try).
  3. House data endpoints (filter_houses.php, fetch_houses.php,
     filter_meta.php) — network-first.
  4. Page navigations (actual HTML documents) and everything else
     same-origin — network-first, so a page visited once online is
     available again offline on reload.
=========================================
*/

const SHELL_CACHE = 'lux-empire-shell-v5';
const RUNTIME_CACHE = 'lux-empire-runtime-v5';

// Precached on install so the very first offline visit (before any
// online browsing) still has basic chrome to show. Runtime caching
// below (networkFirst) picks up everything else as pages are
// actually visited — this list doesn't need to be exhaustive.
const APP_SHELL = [
    '/luxempire/manifest.json',
    '/luxempire/offline.html',
    '/luxempire/maintenance.html',
    '/luxempire/assets/css/style.css',
    '/luxempire/assets/js/offline-db.js',
    '/luxempire/assets/js/offline-drafts.js',
    '/luxempire/assets/js/offline-required-modal.js',
    '/luxempire/assets/images/logo.svg',
    '/luxempire/assets/images/favicon-32.png'
];

self.addEventListener('install', (event) => {

    event.waitUntil(
        caches.open(SHELL_CACHE)
            .then((cache) => cache.addAll(APP_SHELL))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {

    event.waitUntil(
        caches.keys().then((keys) => Promise.all(
            keys
                .filter((key) => key !== SHELL_CACHE && key !== RUNTIME_CACHE)
                .map((key) => caches.delete(key))
        )).then(() => self.clients.claim())
    );
});

function isHouseImage(url) {
    return url.pathname.indexOf('/assets/uploads/house_images/') !== -1
        && !/\.mp4$/i.test(url.pathname);
}

function isHouseVideo(url) {
    return url.pathname.indexOf('/assets/uploads/house_images/') !== -1
        && /\.mp4$/i.test(url.pathname);
}

function isHouseDataEndpoint(url) {
    return url.pathname.indexOf('/api/houses/filter_houses.php') !== -1
        || url.pathname.indexOf('/api/houses/fetch_houses.php') !== -1
        || url.pathname.indexOf('/api/houses/filter_meta.php') !== -1;
}

self.addEventListener('fetch', (event) => {

    const request = event.request;

    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    if (url.origin !== self.location.origin) {
        return;
    }

    // Videos: pass straight through, no caching at all. A cache
    // layer for Range-requested video would need to reassemble and
    // store the full file separately from the Range response the
    // browser actually asked for — real, but meaningfully more work
    // than this pass covers. Videos simply won't play offline for
    // now; images and everything else still will.
    if (isHouseVideo(url)) {
        return;
    }

    if (isHouseImage(url)) {
        event.respondWith(cacheFirst(request, RUNTIME_CACHE));
        return;
    }

    if (isHouseDataEndpoint(url)) {
        event.respondWith(networkFirst(request, RUNTIME_CACHE));
        return;
    }

    // Full-page navigations get the proper branded offline page when
    // both the network AND this URL's own cache miss — rather than
    // the plain-text fallback used below for CSS/JS/other GETs.
    if (request.mode === 'navigate') {
        event.respondWith(networkFirstNavigation(request, RUNTIME_CACHE));
        return;
    }

    // Everything else same-origin (CSS, JS, other GET requests):
    // network-first, cached on success, served from cache on
    // failure.
    event.respondWith(networkFirst(request, RUNTIME_CACHE));
});

function safePut(cacheName, request, response) {

    // 206 Partial Content (and anything not a plain 200) cannot be
    // stored via cache.put() — attempting it throws. Silently skip
    // rather than let that reject the whole response chain.
    if (!response || response.status !== 200) {
        return;
    }

    const clone = response.clone();
    caches.open(cacheName).then((cache) => cache.put(request, clone));
}

function cacheFirst(request, cacheName) {

    return caches.match(request).then((cached) => {

        if (cached) {
            return cached;
        }

        return fetch(request).then((response) => {
            safePut(cacheName, request, response);
            return response;
        });
    });
}

function networkFirst(request, cacheName) {

    return fetch(request)
        .then((response) => {
            safePut(cacheName, request, response);
            return response;
        })
        .catch(() => caches.match(request).then((cached) => {

            if (cached) {
                return cached;
            }

            return new Response(
                '<h1>You are offline</h1><p>This has not been loaded before, so it is not available offline yet.</p>',
                { status: 503, headers: { 'Content-Type': 'text/html' } }
            );
        }));
}


/*
 * Same network-first strategy, but for a full PAGE NAVIGATION whose
 * fallback should be the branded, precached offline.html page rather
 * than a bare text response — this is what replaces the browser's
 * default "can't reach this page" error with LUX EMPIRE's own offline
 * screen.
 */
function networkFirstNavigation(request, cacheName) {

    return fetch(request)
        .then((response) => {

            /*
             * Reachable, but the server itself is erroring (PHP-FPM
             * crashed, upstream down, etc.) — genuinely different from
             * "no network at all", so it gets the maintenance page, not
             * offline.html. 4xx is deliberately left untouched: the
             * app's own legitimate 404s/redirect targets must keep
             * rendering normally, not get silently swapped out.
             */
            if (response.status >= 500) {
                return caches.match('/luxempire/maintenance.html').then((maintenancePage) => {
                    return maintenancePage || response;
                });
            }

            safePut(cacheName, request, response);
            return response;
        })
        .catch(() => caches.match(request).then((cached) => {

            if (cached) {
                return cached;
            }

            return caches.match('/luxempire/offline.html').then((offlinePage) => {
                return offlinePage || new Response(
                    '<h1>You are offline</h1><p>This has not been loaded before, so it is not available offline yet.</p>',
                    { status: 503, headers: { 'Content-Type': 'text/html' } }
                );
            });
        }));
}