/* =====================================================================
   Service Worker de Distribuidora Toro.
   - Recursos estáticos: caché primero (se actualizan en segundo plano).
   - Páginas (HTML): red primero; sin conexión se usa la copia guardada.
   - API: siempre red (las apps guardan sus datos en IndexedDB).
   ===================================================================== */
var VERSION = 'dt-v1.1.0';
var BASE = self.registration ? new URL(self.registration.scope).pathname.replace(/\/$/, '') : '';
var ESTATICOS = [
    '/assets/vendor/bootstrap/bootstrap.min.css',
    '/assets/vendor/bootstrap/bootstrap.bundle.min.js',
    '/assets/vendor/bootstrap-icons/bootstrap-icons.min.css',
    '/assets/vendor/bootstrap-icons/fonts/bootstrap-icons.woff2',
    '/assets/vendor/alpine/alpine.min.js',
    '/assets/css/app.css',
    '/assets/css/movil.css',
    '/assets/js/app.js',
    '/assets/js/db.js',
    '/assets/js/movil.js',
    '/assets/js/chofer.js',
    '/assets/js/documentos.js',
    '/assets/img/logo.svg',
    '/assets/img/icono-192.png',
    '/offline.html'
].map(function (r) { return BASE + r; });

self.addEventListener('install', function (ev) {
    ev.waitUntil(caches.open(VERSION).then(function (c) {
        return Promise.all(ESTATICOS.map(function (u) { return c.add(u).catch(function () {}); }));
    }).then(function () { return self.skipWaiting(); }));
});

self.addEventListener('activate', function (ev) {
    ev.waitUntil(caches.keys().then(function (claves) {
        return Promise.all(claves.filter(function (k) { return k !== VERSION; }).map(function (k) { return caches.delete(k); }));
    }).then(function () { return self.clients.claim(); }));
});

function claveSinVersion(req) {
    var u = new URL(req.url);
    u.search = '';
    return u.toString();
}

self.addEventListener('fetch', function (ev) {
    var req = ev.request;
    if (req.method !== 'GET') { return; }
    var url = new URL(req.url);
    if (url.origin !== self.location.origin) { return; }
    var ruta = url.pathname.substring(BASE.length);

    if (ruta.indexOf('/api/') === 0) { return; } // la API no se cachea

    // Estáticos y fotos de productos: caché primero, actualizando en segundo plano
    if (ruta.indexOf('/assets/') === 0 || ruta.indexOf('/uploads/productos/t_') === 0) {
        ev.respondWith(caches.open(VERSION).then(function (cache) {
            var clave = claveSinVersion(req);
            return cache.match(clave).then(function (guardado) {
                var red = fetch(req).then(function (resp) {
                    if (resp && resp.ok) { cache.put(clave, resp.clone()); }
                    return resp;
                }).catch(function () { return guardado; });
                return guardado || red;
            });
        }));
        return;
    }

    // Páginas: red primero, si no hay conexión la última copia guardada
    if (req.mode === 'navigate' || (req.headers.get('accept') || '').indexOf('text/html') >= 0) {
        ev.respondWith(fetch(req).then(function (resp) {
            if (resp && resp.ok && !resp.redirected && (ruta === '/movil' || ruta === '/chofer' || ruta === '/')) {
                var copia = resp.clone();
                caches.open(VERSION).then(function (c) { c.put(claveSinVersion(req), copia); });
            }
            return resp;
        }).catch(function () {
            return caches.match(claveSinVersion(req)).then(function (r) {
                return r || caches.match(BASE + '/offline.html');
            });
        }));
    }
});

// La app pide guardar su propia página (la primera carga no pasa por el Service Worker)
self.addEventListener('message', function (ev) {
    var d = ev.data || {};
    if (d.tipo !== 'cachear' || !Array.isArray(d.urls)) { return; }
    ev.waitUntil(caches.open(VERSION).then(function (c) {
        return Promise.all(d.urls.map(function (u) {
            return fetch(u, { credentials: 'same-origin' }).then(function (r) {
                if (r.ok && !r.redirected) { return c.put(claveSinVersion(new Request(u)), r); }
            }).catch(function () {});
        }));
    }));
});
