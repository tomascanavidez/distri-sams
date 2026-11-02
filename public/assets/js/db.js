/* =====================================================================
   Almacenamiento local (IndexedDB) y sincronización para las apps móviles.
   Stores: "kv" (datos descargados, sesión) y "cola" (operaciones pendientes, por uuid).
   ===================================================================== */
(function () {
    'use strict';
    var NOMBRE = 'distri-toro', VERSION = 1, dbPromesa = null;

    function abrir() {
        if (dbPromesa) { return dbPromesa; }
        dbPromesa = new Promise(function (resolver, rechazar) {
            var req = indexedDB.open(NOMBRE, VERSION);
            req.onupgradeneeded = function () {
                var db = req.result;
                if (!db.objectStoreNames.contains('kv')) { db.createObjectStore('kv'); }
                if (!db.objectStoreNames.contains('cola')) { db.createObjectStore('cola', { keyPath: 'uuid' }); }
            };
            req.onsuccess = function () { resolver(req.result); };
            req.onerror = function () { rechazar(req.error); };
        });
        return dbPromesa;
    }

    function tx(store, modo, fn) {
        return abrir().then(function (db) {
            return new Promise(function (resolver, rechazar) {
                var t = db.transaction(store, modo);
                var s = t.objectStore(store);
                var resultado = fn(s);
                t.oncomplete = function () { resolver(resultado && 'result' in resultado ? resultado.result : resultado); };
                t.onerror = function () { rechazar(t.error); };
                t.onabort = function () { rechazar(t.error); };
            });
        });
    }

    window.DTDB = {
        get: function (clave) { return tx('kv', 'readonly', function (s) { return s.get(clave); }); },
        set: function (clave, valor) { return tx('kv', 'readwrite', function (s) { s.put(valor, clave); }); },
        cola: function () { return tx('cola', 'readonly', function (s) { return s.getAll(); }).then(function (l) { return (l || []).sort(function (a, b) { return a.creado < b.creado ? -1 : 1; }); }); },
        encolar: function (op) { return tx('cola', 'readwrite', function (s) { s.put(op); }); },
        quitar: function (uuid) { return tx('cola', 'readwrite', function (s) { s.delete(uuid); }); },
        actualizar: function (op) { return tx('cola', 'readwrite', function (s) { s.put(op); }); }
    };

    /** UUID v4 generado en el dispositivo (identifica cada operación y evita duplicados). */
    window.DT_uuid = function () {
        if (window.crypto && crypto.randomUUID) { return crypto.randomUUID(); }
        var b = new Uint8Array(16);
        (window.crypto || window.msCrypto).getRandomValues(b);
        b[6] = (b[6] & 0x0f) | 0x40; b[8] = (b[8] & 0x3f) | 0x80;
        var h = Array.prototype.map.call(b, function (x) { return ('0' + x.toString(16)).slice(-2); }).join('');
        return h.slice(0, 8) + '-' + h.slice(8, 12) + '-' + h.slice(12, 16) + '-' + h.slice(16, 20) + '-' + h.slice(20);
    };

    /** Texto sin acentos y en minúscula (búsqueda tolerante). */
    window.DT_normalizar = function (s) {
        return String(s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
    };

    /**
     * Base común de las apps (preventista y chofer): estado de conexión, cola y sincronización.
     * opciones: { claveDatos, urlDatos, urlSync }
     */
    window.DT_appBase = function (opciones) {
        return {
            online: navigator.onLine,
            sincronizando: false,
            ultimaSync: null,
            cola: [],
            datos: null,
            sesionVencida: false,
            mensaje: '',
            csrf: '',
            ultimaDescarga: 0,

            iniciarBase: function () {
                var self = this;
                window.addEventListener('online', function () { self.online = true; self.sincronizar(); });
                window.addEventListener('offline', function () { self.online = false; });
                setInterval(function () { if (navigator.onLine && self.pendientes() && !self.sincronizando) { self.sincronizar(); } }, 45000);
                return Promise.all([DTDB.get(opciones.claveDatos), DTDB.get('ultimaSync' + opciones.claveDatos), this.refrescarCola()]).then(function (r) {
                    if (r[0]) { if (self.preparar) { self.preparar(r[0]); } self.datos = r[0]; }
                    self.ultimaSync = r[1] || null;
                    if (self.online) { return self.descargar().then(function () { return self.sincronizar(); }); }
                });
            },
            refrescarCola: function () { var self = this; return DTDB.cola().then(function (c) { self.cola = c; }); },
            pendientes: function () { return this.cola.filter(function (o) { return o.estado !== 'revision' && o.estado !== 'conflicto'; }).length; },

            /** Descarga los datos completos para trabajar sin conexión. */
            descargar: function () {
                var self = this;
                return DT.api(opciones.urlDatos, { sinRedireccion: true }).then(function (d) {
                    if (self.preparar) { self.preparar(d); }
                    self.datos = d;
                    self.ultimaDescarga = Date.now();
                    self.sesionVencida = false;
                    return DTDB.set(opciones.claveDatos, d).then(function () { if (self.alDescargar) { self.alDescargar(); } });
                }).catch(function (e) { self.manejarError(e); });
            },
            manejarError: function (e) {
                if (e && e.estado === 401) { this.sesionVencida = true; return; }
                if (!e || !e.estado) { this.online = navigator.onLine && false; return; }
                if (e.estado >= 500) { DT.aviso('El servidor no pudo enviar los datos (error ' + e.estado + '). Avisá a la oficina; mientras tanto se usan los datos guardados.', 'error'); }
            },
            /** Envía la cola al servidor. Las respuestas por operación deciden si se borra o se reintenta. */
            sincronizar: function () {
                var self = this;
                if (this.sincronizando) { return Promise.resolve(); }
                this.sincronizando = true;
                return this.refrescarCola().then(function () {
                    var enviar = self.cola.filter(function (o) { return o.estado === 'pendiente' || o.estado === 'error'; });
                    return DT.api('/api/movil/sesion', { sinRedireccion: true }).then(function (s) {
                        self.csrf = s.csrf;
                        var meta = document.querySelector('meta[name="csrf-token"]');
                        if (meta) { meta.content = s.csrf; }
                        self.sesionVencida = false;
                        if (!enviar.length) { return null; }
                        return DT.api(opciones.urlSync, { body: { items: enviar }, sinRedireccion: true });
                    }).then(function (r) {
                        self.online = true;
                        self.huboEnvio = !!r;
                        if (!r) { return; }
                        return Promise.all(r.resultados.map(function (res) {
                            var op = self.cola.filter(function (o) { return o.uuid === res.uuid; })[0];
                            if (!op) { return null; }
                            if (res.estado === 'ok' || res.estado === 'descartado') {
                                if (self.alConfirmar) { self.alConfirmar(op, res); }
                                return DTDB.quitar(op.uuid);
                            }
                            op.estado = res.estado === 'revision' ? 'revision' : (res.estado === 'conflicto' ? 'conflicto' : 'error');
                            op.respuesta = res;
                            if (self.alConfirmar && res.estado === 'revision') { self.alConfirmar(op, res); }
                            // Copia plana: el objeto de la cola es reactivo (Alpine) y IndexedDB no lo puede guardar
                            return DTDB.actualizar(JSON.parse(JSON.stringify(op)));
                        }));
                    }).then(function () {
                        var ahora = new Date().toISOString();
                        self.ultimaSync = ahora;
                        DTDB.set('ultimaSync' + opciones.claveDatos, ahora);
                        // Se vuelve a descargar si se envió algo o si los datos tienen más de 10 minutos
                        return self.refrescarCola().then(function () { if (self.huboEnvio || Date.now() - self.ultimaDescarga > 600000) { return self.descargar(); } });
                    });
                }).catch(function (e) { self.manejarError(e); }).finally(function () { self.sincronizando = false; });
            },
            encolar: function (tipo, datos, extra) {
                // Copia plana: los objetos reactivos de Alpine no se pueden guardar en IndexedDB
                var plano = function (o) { return JSON.parse(JSON.stringify(o || {})); };
                var op = Object.assign({ uuid: DT_uuid(), tipo: tipo, datos: plano(datos), creado: new Date().toISOString(), estado: 'pendiente' }, plano(extra));
                var self = this;
                return DTDB.encolar(op).catch(function (e) {
                    DT.aviso('No se pudo guardar en el celular: ' + (e && e.message ? e.message : e) + '. Reintentá.', 'error');
                    throw e;
                }).then(function () {
                    return self.refrescarCola();
                }).then(function () {
                    if (self.online) { self.sincronizar(); }
                    return op;
                });
            },
            descartarOp: function (op) { var self = this; return DTDB.quitar(op.uuid).then(function () { return self.refrescarCola(); }); },
            horaSync: function () {
                if (!this.ultimaSync) { return 'nunca'; }
                var d = new Date(this.ultimaSync);
                return ('0' + d.getHours()).slice(-2) + ':' + ('0' + d.getMinutes()).slice(-2);
            },
            /** Texto y color de la barra de estado. */
            estadoConexion: function () {
                var n = this.pendientes();
                if (this.sesionVencida) { return { clase: 'estado-error', texto: 'Tu sesión venció: ingresá de nuevo para enviar' + (n ? ' (' + n + ' pendientes)' : '') }; }
                if (!this.online) { return { clase: 'estado-offline', texto: 'Sin conexión' + (n ? ' · ' + n + (n === 1 ? ' operación pendiente' : ' operaciones pendientes') + ' de enviar' : ' · trabajando con datos guardados') }; }
                if (this.sincronizando) { return { clase: 'estado-sync', texto: 'Sincronizando…' }; }
                if (n) { return { clase: 'estado-pend', texto: n + ' pendiente(s) de enviar · tocá para reintentar' }; }
                return { clase: 'estado-ok', texto: 'Sincronizado ' + this.horaSync() };
            },
            mapa: function (c) {
                if (c.lat && c.lng) { return 'https://www.google.com/maps/dir/?api=1&destination=' + c.lat + ',' + c.lng; }
                return 'https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent((c.dir || '') + ', ' + (c.loc || 'Salta') + ', Argentina');
            },
            whatsapp: function (c, texto) { return c.wa ? 'https://wa.me/' + c.wa + (texto ? '?text=' + encodeURIComponent(texto) : '') : null; },
            telefono: function (c) { return c.tel ? 'tel:' + String(c.tel).replace(/[^\d+]/g, '') : null; },
            /** Ubicación opcional: nunca demora más de 2,5 s (si no hay permiso o señal GPS se sigue sin ella). */
            ubicacion: function () {
                return new Promise(function (resolver) {
                    var listo = false;
                    var fin = function (v) { if (!listo) { listo = true; resolver(v); } };
                    setTimeout(function () { fin(null); }, 2500);
                    if (!navigator.geolocation) { fin(null); return; }
                    try {
                        navigator.geolocation.getCurrentPosition(function (p) { fin({ lat: p.coords.latitude, lng: p.coords.longitude }); }, function () { fin(null); }, { timeout: 2400, maximumAge: 300000 });
                    } catch (e) { fin(null); }
                });
            }
        };
    };
})();
