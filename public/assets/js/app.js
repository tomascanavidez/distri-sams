/* =====================================================================
   Distribuidora Toro — JavaScript común (sin dependencias de build)
   ===================================================================== */
(function () {
    'use strict';

    var BASE = (document.querySelector('meta[name="base-url"]') || {}).content || '';
    function csrf() { return (document.querySelector('meta[name="csrf-token"]') || {}).content || ''; }

    var fmtMoneda = new Intl.NumberFormat('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    var fmtCant = new Intl.NumberFormat('es-AR', { maximumFractionDigits: 3 });

    var DT = window.DT = {
        base: BASE,
        url: function (ruta) { return BASE + ruta; },
        csrf: csrf,
        /** $ 1.234,56 */
        dinero: function (n) { return '$ ' + fmtMoneda.format(Math.round((Number(n) || 0) * 100) / 100); },
        numero: function (n) { return fmtMoneda.format(Number(n) || 0); },
        cant: function (n) { return fmtCant.format(Number(n) || 0); },
        /** Interpreta "1.234,56", "1234,56" o "1234.56" */
        parse: function (v) {
            if (typeof v === 'number') { return v; }
            v = String(v || '').replace(/[$\s]/g, '');
            if (v.indexOf(',') >= 0) { v = v.replace(/\./g, '').replace(',', '.'); }
            var n = parseFloat(v);
            return isNaN(n) ? 0 : n;
        },
        redondear: function (n, d) { var f = Math.pow(10, d === undefined ? 2 : d); return Math.round((Number(n) || 0) * f) / f; },
        escapar: function (s) {
            return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
            });
        },

        /** fetch con CSRF y JSON. Rechaza con {mensaje, datos, estado}. */
        api: function (ruta, opciones) {
            opciones = opciones || {};
            var init = {
                method: opciones.method || (opciones.body ? 'POST' : 'GET'),
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': csrf() },
                credentials: 'same-origin'
            };
            if (opciones.body !== undefined) {
                init.headers['Content-Type'] = 'application/json';
                init.body = JSON.stringify(opciones.body);
            }
            var url = /^https?:/.test(ruta) ? ruta : BASE + ruta;
            return fetch(url, init).then(function (r) {
                return r.text().then(function (txt) {
                    var datos = null;
                    try { datos = txt ? JSON.parse(txt) : {}; } catch (e) { datos = { error: 'Respuesta inválida del servidor.' }; }
                    if (!r.ok || datos.ok === false) {
                        if (r.status === 401 && !opciones.sinRedireccion) { window.location.href = BASE + '/login'; }
                        var err = new Error(datos.error || 'Error ' + r.status);
                        err.datos = datos; err.estado = r.status;
                        throw err;
                    }
                    return datos;
                });
            });
        },

        /** Aviso flotante (toast) */
        aviso: function (mensaje, tipo) {
            var cont = document.getElementById('avisos');
            if (!cont) {
                cont = document.createElement('div');
                cont.id = 'avisos';
                cont.className = 'toast-container position-fixed top-0 start-50 translate-middle-x p-3';
                cont.style.zIndex = 2000;
                document.body.appendChild(cont);
            }
            var colores = { exito: 'success', error: 'danger', aviso: 'warning', info: 'primary' };
            var el = document.createElement('div');
            el.className = 'toast align-items-center text-bg-' + (colores[tipo] || 'dark') + ' border-0 show';
            el.setAttribute('role', 'alert');
            el.innerHTML = '<div class="d-flex"><div class="toast-body fs-6">' + DT.escapar(mensaje) +
                '</div><button type="button" class="btn-close btn-close-white me-2 m-auto" aria-label="Cerrar"></button></div>';
            el.querySelector('button').onclick = function () { el.remove(); };
            cont.appendChild(el);
            setTimeout(function () { el.remove(); }, tipo === 'error' ? 7000 : 3500);
        },

        /** Confirmación con modal Bootstrap. opciones: {titulo, mensaje, motivo, textoBoton} */
        confirmar: function (opciones) {
            return new Promise(function (resolver) {
                var modalEl = document.getElementById('modalConfirmar');
                if (!modalEl || !window.bootstrap) { resolver(window.confirm(opciones.mensaje) ? { motivo: '' } : null); return; }
                modalEl.querySelector('[data-titulo]').textContent = opciones.titulo || 'Confirmar';
                modalEl.querySelector('[data-mensaje]').textContent = opciones.mensaje || '¿Confirmás la operación?';
                var grupo = modalEl.querySelector('[data-motivo-grupo]');
                var txt = modalEl.querySelector('#confirmarMotivo');
                txt.value = ''; txt.classList.remove('is-invalid');
                grupo.classList.toggle('d-none', !opciones.motivo);
                var btn = modalEl.querySelector('[data-aceptar]');
                btn.textContent = opciones.textoBoton || 'Confirmar';
                var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
                var resuelto = false;
                btn.onclick = function () {
                    if (opciones.motivo && txt.value.trim().length < 3) { txt.classList.add('is-invalid'); txt.focus(); return; }
                    resuelto = true;
                    modal.hide();
                    resolver({ motivo: txt.value.trim() });
                };
                modalEl.addEventListener('hidden.bs.modal', function h() {
                    modalEl.removeEventListener('hidden.bs.modal', h);
                    if (!resuelto) { resolver(null); }
                });
                modal.show();
                if (opciones.motivo) { setTimeout(function () { txt.focus(); }, 400); }
            });
        },

        alternarTema: function () {
            var actual = document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-bs-theme', actual);
            try { localStorage.setItem('dt-tema', actual); } catch (e) {}
        }
    };

    // ---- Delegación de eventos -------------------------------------------
    document.addEventListener('click', function (ev) {
        var t = ev.target.closest('[data-tema-alternar]');
        if (t) { ev.preventDefault(); DT.alternarTema(); return; }

        var imp = ev.target.closest('[data-imprimir]');
        if (imp) { ev.preventDefault(); window.print(); }
    });

    // Formularios con data-confirmar="mensaje" (y data-motivo si se exige motivo)
    document.addEventListener('submit', function (ev) {
        var form = ev.target;
        if (!form.matches('form[data-confirmar]') || form.dataset.confirmado === '1') {
            if (form.matches('form') && !form.dataset.sinBloqueo) {
                // Evita doble envío
                var btn = form.querySelector('[type="submit"]');
                if (btn && form.method.toLowerCase() === 'post') {
                    setTimeout(function () { btn.disabled = true; }, 0);
                    setTimeout(function () { btn.disabled = false; }, 8000);
                }
            }
            return;
        }
        ev.preventDefault();
        DT.confirmar({
            titulo: form.dataset.titulo,
            mensaje: form.dataset.confirmar,
            motivo: form.hasAttribute('data-motivo'),
            textoBoton: form.dataset.boton
        }).then(function (r) {
            if (!r) { return; }
            if (form.hasAttribute('data-motivo')) {
                var inp = form.querySelector('input[name="motivo"]');
                if (!inp) { inp = document.createElement('input'); inp.type = 'hidden'; inp.name = 'motivo'; form.appendChild(inp); }
                inp.value = r.motivo;
            }
            form.dataset.confirmado = '1';
            if (form.requestSubmit) { form.requestSubmit(); } else { form.submit(); }
        });
    });

    // Selects de filtros que envían el formulario al cambiar
    document.addEventListener('change', function (ev) {
        if (ev.target.matches('[data-autoenviar]')) { ev.target.form.submit(); }
    });

    // ---- Componentes Alpine ------------------------------------------------
    document.addEventListener('alpine:init', function () {
        /**
         * Autocompletado genérico.
         * x-data="autocompletar({url:'/api/clientes', nombre:'cliente_id', valor:'', texto:''})"
         */
        Alpine.data('autocompletar', function (cfg) {
            return {
                q: cfg.texto || '',
                valor: cfg.valor || '',
                nombre: cfg.nombre || '',
                resultados: [],
                abierto: false,
                indice: -1,
                cargando: false,
                temporizador: null,
                buscar: function () {
                    var self = this;
                    clearTimeout(this.temporizador);
                    if (this.q.trim().length < (cfg.minimo || 1)) { this.resultados = []; this.abierto = false; return; }
                    this.temporizador = setTimeout(function () {
                        self.cargando = true;
                        var sep = cfg.url.indexOf('?') >= 0 ? '&' : '?';
                        DT.api(cfg.url + sep + 'q=' + encodeURIComponent(self.q.trim())).then(function (r) {
                            self.resultados = r.items || [];
                            self.abierto = true;
                            self.indice = self.resultados.length ? 0 : -1;
                        }).catch(function () { self.resultados = []; }).finally(function () { self.cargando = false; });
                    }, 180);
                },
                elegir: function (item) {
                    this.valor = item.id;
                    this.q = item.texto;
                    this.abierto = false;
                    this.$dispatch('elegido', { nombre: this.nombre, item: item });
                    if (cfg.alElegir && typeof window[cfg.alElegir] === 'function') { window[cfg.alElegir](item); }
                    if (cfg.enviar) { var self = this; this.$nextTick(function () { self.$el.closest('form').submit(); }); }
                },
                limpiar: function () { this.valor = ''; this.q = ''; this.resultados = []; this.$dispatch('elegido', { nombre: this.nombre, item: null }); },
                tecla: function (ev) {
                    if (!this.abierto || !this.resultados.length) { return; }
                    if (ev.key === 'ArrowDown') { ev.preventDefault(); this.indice = Math.min(this.indice + 1, this.resultados.length - 1); }
                    else if (ev.key === 'ArrowUp') { ev.preventDefault(); this.indice = Math.max(this.indice - 1, 0); }
                    else if (ev.key === 'Enter') { ev.preventDefault(); if (this.indice >= 0) { this.elegir(this.resultados[this.indice]); } }
                    else if (ev.key === 'Escape') { this.abierto = false; }
                }
            };
        });
    });

    // ---- Service Worker (PWA y funcionamiento sin conexión) ----------------
    if ('serviceWorker' in navigator && window.isSecureContext) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register(BASE + '/sw.js', { scope: BASE + '/' }).catch(function () {});
            // Las apps de calle se guardan para poder abrirlas sin conexión
            if (/\/(movil|chofer)$/.test(location.pathname)) {
                navigator.serviceWorker.ready.then(function (reg) {
                    var urls = [location.pathname].concat([].slice.call(document.querySelectorAll('script[src], link[rel=stylesheet]')).map(function (el) { return el.src || el.href; }));
                    if (reg.active) { reg.active.postMessage({ tipo: 'cachear', urls: urls }); }
                });
            }
        });
    }
})();
