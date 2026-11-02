/* =====================================================================
   Editor de líneas de documentos (pedidos, ventas, compras, ajustes,
   transferencias) y escáner de códigos de barras con la cámara.
   ===================================================================== */
(function () {
    'use strict';

    /** Escáner de códigos de barras: carga html5-qrcode sólo cuando hace falta. */
    DT.escanear = function (alLeer) {
        var modalEl = document.getElementById('modalEscaner');
        if (!modalEl) { return; }
        var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        var lector = null;
        var detener = function () {
            if (lector) { lector.stop().catch(function () {}).finally(function () { lector.clear(); lector = null; }); }
        };
        var iniciar = function () {
            var formatos = [Html5QrcodeSupportedFormats.EAN_13, Html5QrcodeSupportedFormats.EAN_8, Html5QrcodeSupportedFormats.UPC_A,
                Html5QrcodeSupportedFormats.UPC_E, Html5QrcodeSupportedFormats.CODE_128, Html5QrcodeSupportedFormats.CODE_39, Html5QrcodeSupportedFormats.QR_CODE];
            lector = new Html5Qrcode('lectorCamara', { formatsToSupport: formatos, verbose: false });
            lector.start({ facingMode: 'environment' }, { fps: 12, qrbox: function (w, h) { return { width: Math.min(w * 0.85, 320), height: Math.min(h * 0.5, 160) }; } },
                function (texto) {
                    if (navigator.vibrate) { navigator.vibrate(80); }
                    detener();
                    modal.hide();
                    alLeer(texto);
                }, function () {}).catch(function (e) {
                    document.getElementById('lectorError').textContent = 'No se pudo abrir la cámara. Revisá los permisos del navegador (se necesita HTTPS). ' + (e && e.message ? e.message : '');
                });
        };
        document.getElementById('lectorError').textContent = '';
        modalEl.addEventListener('hidden.bs.modal', detener, { once: true });
        modal.show();
        if (window.Html5Qrcode) { setTimeout(iniciar, 300); return; }
        var s = document.createElement('script');
        s.src = DT.url('/assets/vendor/html5-qrcode/html5-qrcode.min.js');
        s.onload = function () { setTimeout(iniciar, 200); };
        s.onerror = function () { document.getElementById('lectorError').textContent = 'No se pudo cargar el lector.'; };
        document.head.appendChild(s);
    };

    document.addEventListener('alpine:init', function () {
        /**
         * cfg: modo (venta|compra|stock), endpoint, listaId, depositoId, items[], cabecera{}, signo (ajustes),
         *      precioEditable, conBonificacion, conLotes
         */
        Alpine.data('editorDocumento', DT.editorDocumento);
        Alpine.data('pos', DT.pos);
    });

    DT.editorDocumento = function (cfg) {
            return {
                modo: cfg.modo || 'venta',
                lineas: (cfg.items || []).map(function (l) { return Object.assign({ descuento: 0, bonificacion: 0, lote: '', vencimiento: '' }, l); }),
                cab: Object.assign({ descuento_porc: 0, observaciones: '' }, cfg.cabecera || {}),
                listaId: cfg.listaId || null,
                depositoId: cfg.depositoId || null,
                q: '',
                resultados: [],
                indice: -1,
                buscando: false,
                enviando: false,
                error: '',
                avisos: [],
                cliente: null,
                temporizador: null,
                rubroId: '',
                multiplicador: null,
                pagos: cfg.pagos || [],

                init: function () {
                    var self = this;
                    if (cfg.clienteId) { this.cargarCliente(cfg.clienteId, false); }
                    this.$watch('depositoId', function () { self.refrescarStock(); });
                },

                // ---- Búsqueda de productos ----
                buscar: function (inmediato) {
                    var self = this;
                    clearTimeout(this.temporizador);
                    var q = this.q.trim();
                    if (q.length < 2 && !/^\d+$/.test(q)) { this.resultados = []; return; }
                    this.temporizador = setTimeout(function () { self.consultar(q, false); }, inmediato ? 0 : 200);
                },
                consultar: function (q, agregarSiExacto) {
                    var self = this;
                    self.buscando = true;
                    var url = '/api/productos?q=' + encodeURIComponent(q) + (self.listaId ? '&lista_id=' + self.listaId : '') +
                        (self.depositoId ? '&deposito_id=' + self.depositoId : '') + (self.rubroId ? '&rubro_id=' + self.rubroId : '');
                    return DT.api(url).then(function (r) {
                        self.resultados = r.items || [];
                        self.indice = self.resultados.length ? 0 : -1;
                        if (agregarSiExacto && r.exacto && self.resultados.length) {
                            self.agregar(self.resultados[0]);
                        } else if (agregarSiExacto && !self.resultados.length) {
                            DT.aviso('No se encontró el código ' + q, 'error');
                        }
                    }).catch(function (e) { DT.aviso(e.message, 'error'); }).finally(function () { self.buscando = false; });
                },
                teclaBusqueda: function (ev) {
                    if (ev.key === 'ArrowDown') { ev.preventDefault(); this.indice = Math.min(this.indice + 1, this.resultados.length - 1); }
                    else if (ev.key === 'ArrowUp') { ev.preventDefault(); this.indice = Math.max(this.indice - 1, 0); }
                    else if (ev.key === 'Escape') { this.resultados = []; }
                    else if (ev.key === 'Enter') {
                        ev.preventDefault();
                        var q = this.q.trim();
                        // "3*779..." = cantidad por código
                        var m = q.match(/^(\d+(?:[.,]\d+)?)\*(.+)$/);
                        if (m) { this.multiplicador = DT.parse(m[1]); q = m[2]; }
                        if (/^\d{6,}$/.test(q)) { this.consultar(q, true); return; }
                        if (this.indice >= 0 && this.resultados[this.indice]) { this.agregar(this.resultados[this.indice]); }
                    }
                },
                escanear: function () {
                    var self = this;
                    DT.escanear(function (codigo) { self.q = codigo; self.consultar(codigo, true); });
                },

                presentacionInicial: function (p) {
                    var lista = (p.presentaciones || []).filter(function (x) { return this.modo === 'compra' ? x.compra : x.venta; }, this);
                    if (!lista.length) { lista = p.presentaciones; }
                    if (p.presentacion_id) {
                        var elegida = lista.filter(function (x) { return x.id === p.presentacion_id; })[0];
                        if (elegida) { return elegida; }
                    }
                    var pred = lista.filter(function (x) { return this.modo === 'compra' ? x.pc : x.pv; }, this)[0];
                    return pred || lista[0];
                },

                agregar: function (p) {
                    var pres = this.presentacionInicial(p);
                    var cant = this.multiplicador || 1;
                    this.multiplicador = null;
                    var existente = this.lineas.filter(function (l) { return l.producto_id === p.id && l.factor === pres.factor; })[0];
                    if (existente && this.modo !== 'stock') {
                        existente.cantidad = DT.redondear(DT.parse(existente.cantidad) + cant, 3);
                    } else {
                        this.lineas.push({
                            producto_id: p.id, descripcion: p.descripcion, codigo: p.codigo, marca: p.marca, unidad: p.unidad,
                            presentaciones: p.presentaciones, presentacion_id: pres.id, factor: pres.factor, presentacion_nombre: pres.nombre,
                            precio_base: p.precio, costo_base: p.costo, iva: p.iva, disponible: p.disponible, stock: p.stock,
                            lotes: p.lotes, cantidad: cant, descuento: 0, bonificacion: 0, lote: '', vencimiento: '',
                            precio: this.modo === 'compra' ? DT.redondear(p.costo * pres.factor, 2) : DT.redondear(p.precio * pres.factor, 2),
                            signo: cfg.signo || 1
                        });
                    }
                    this.q = '';
                    this.resultados = [];
                    if (navigator.vibrate) { navigator.vibrate(30); }
                    var self = this;
                    this.$nextTick(function () { if (self.$refs.buscador && window.innerWidth > 768) { self.$refs.buscador.focus(); } });
                },
                cambiarPresentacion: function (l, id) {
                    var p = l.presentaciones.filter(function (x) { return String(x.id) === String(id); })[0];
                    if (!p) { return; }
                    l.presentacion_id = p.id; l.factor = p.factor; l.presentacion_nombre = p.nombre;
                    l.precio = this.modo === 'compra' ? DT.redondear(l.costo_base * p.factor, 2) : DT.redondear(l.precio_base * p.factor, 2);
                },
                sumar: function (l, n) {
                    var v = DT.redondear(DT.parse(l.cantidad) + n, 3);
                    l.cantidad = v < 0 ? 0 : v;
                },
                quitar: function (i) { this.lineas.splice(i, 1); },
                unidades: function (l) { return DT.parse(l.cantidad) * Number(l.factor || 1); },
                importe: function (l) { return DT.redondear(DT.parse(l.precio) * DT.parse(l.cantidad) * (1 - DT.parse(l.descuento) / 100), 2); },
                subtotal: function () { var s = 0; this.lineas.forEach(function (l) { s += this.importe(l); }, this); return DT.redondear(s, 2); },
                total: function () { return DT.redondear(this.subtotal() * (1 - DT.parse(this.cab.descuento_porc) / 100), 2); },
                totalUnidades: function () { var s = 0; this.lineas.forEach(function (l) { s += this.unidades(l); }, this); return s; },
                sinStock: function (l) { return this.modo !== 'compra' && l.disponible !== undefined && this.unidades(l) > Number(l.disponible) + 0.0001; },

                /** Agrega los productos del último pedido del cliente. */
                repetirUltimo: function () {
                    var self = this;
                    if (!this.cliente) { return; }
                    DT.api('/pedidos/ultimo/' + this.cliente.id).then(function (r) {
                        if (!r.items.length) { DT.aviso('El cliente no tiene pedidos anteriores.', 'aviso'); return; }
                        r.items.forEach(function (it) {
                            DT.api('/api/producto/' + it.producto_id + '?lista_id=' + (self.listaId || '') + '&deposito_id=' + (self.depositoId || '')).then(function (x) {
                                if (!x.item) { return; }
                                x.item.presentacion_id = Number(it.presentacion_id);
                                self.multiplicador = Number(it.cantidad);
                                self.agregar(x.item);
                            });
                        });
                    });
                },

                // ---- Cliente ----
                cargarCliente: function (id, reprecio) {
                    var self = this;
                    if (!id) { self.cliente = null; self.avisos = []; return; }
                    DT.api('/api/cliente/' + id).then(function (r) {
                        self.cliente = r.item;
                        self.avisos = r.item.problemas || [];
                        var nueva = r.item.lista_precio_id;
                        if (reprecio !== false && nueva && String(nueva) !== String(self.listaId)) {
                            self.listaId = nueva;
                            self.reprecio();
                        } else if (nueva) { self.listaId = nueva; }
                    });
                },
                reprecio: function () {
                    var self = this;
                    this.lineas.forEach(function (l) {
                        DT.api('/api/producto/' + l.producto_id + '?lista_id=' + self.listaId + (self.depositoId ? '&deposito_id=' + self.depositoId : '')).then(function (r) {
                            if (!r.item) { return; }
                            l.precio_base = r.item.precio;
                            if (self.modo === 'venta') { l.precio = DT.redondear(r.item.precio * l.factor, 2); }
                        });
                    });
                },
                refrescarStock: function () {
                    var self = this;
                    this.lineas.forEach(function (l) {
                        DT.api('/api/producto/' + l.producto_id + '?deposito_id=' + (self.depositoId || '') + (self.listaId ? '&lista_id=' + self.listaId : '')).then(function (r) {
                            if (r.item) { l.disponible = r.item.disponible; l.stock = r.item.stock; }
                        });
                    });
                },

                // ---- Pagos (venta contado / mixta) ----
                agregarPago: function (forma) {
                    var resto = DT.redondear(this.total() - this.totalPagos(), 2);
                    this.pagos.push({ forma: forma || 'efectivo', importe: resto > 0 ? resto : '', referencia: '', cuenta_bancaria_id: '', cheque: { numero: '', banco: '', fecha_pago: '' }, entregado: '' });
                },
                totalPagos: function () { var s = 0; this.pagos.forEach(function (p) { s += DT.parse(p.importe); }); return DT.redondear(s, 2); },
                saldoACuenta: function () { return DT.redondear(this.total() - this.totalPagos(), 2); },
                vuelto: function () { var v = 0; this.pagos.forEach(function (p) { if (p.forma === 'efectivo' && DT.parse(p.entregado) > 0) { v += DT.parse(p.entregado) - DT.parse(p.importe); } }); return DT.redondear(v, 2); },

                // ---- Envío ----
                datos: function (extra) {
                    var items = this.lineas.filter(function (l) { return DT.parse(l.cantidad) > 0; }).map(function (l) {
                        return {
                            producto_id: l.producto_id, presentacion_id: l.presentacion_id || null, factor: l.factor,
                            presentacion_nombre: l.presentacion_nombre, cantidad: DT.parse(l.cantidad) * (l.signo || 1),
                            precio_unitario: l.precio, costo_unitario: l.precio, descuento_porc: DT.parse(l.descuento),
                            bonificacion_base: DT.parse(l.bonificacion), lote: l.lote, vencimiento: l.vencimiento,
                            pedido_item_id: l.pedido_item_id || null, orden_item_id: l.orden_item_id || null,
                            reingresa_stock: l.reingresa_stock === undefined ? 1 : (l.reingresa_stock ? 1 : 0)
                        };
                    });
                    var pagos = this.pagos.filter(function (p) { return DT.parse(p.importe) > 0; }).map(function (p) { return { forma: p.forma, importe: DT.parse(p.importe), referencia: p.referencia, cuenta_bancaria_id: p.cuenta_bancaria_id || null, cheque: p.cheque }; });
                    return Object.assign({}, this.cab, { items: items, pagos: pagos, deposito_id: this.depositoId, lista_precio_id: this.listaId }, extra || {});
                },
                guardar: function (extra) {
                    var self = this;
                    if (!this.lineas.length) { DT.aviso('Agregá al menos un producto.', 'error'); return Promise.resolve(); }
                    this.enviando = true;
                    this.error = '';
                    return DT.api(cfg.endpoint, { body: this.datos(extra) }).then(function (r) {
                        if (r.mensaje) { DT.aviso(r.mensaje, 'exito'); }
                        if (r.redirigir) { window.location.href = r.redirigir; }
                        return r;
                    }).catch(function (e) {
                        self.error = e.message;
                        DT.aviso(e.message, 'error');
                        return null;
                    }).finally(function () { self.enviando = false; });
                }
            };
    };

    /** Mostrador (POS): editor de venta + cobro rápido con atajos de teclado. */
    DT.pos = function (cfg) {
        var c = DT.editorDocumento(cfg);
        var initBase = c.init;
        return Object.assign(c, {
            ultima: null,
            init: function () {
                var self = this;
                initBase.call(this);
                window.addEventListener('keydown', function (ev) {
                    if (ev.key === 'F2') { ev.preventDefault(); self.$refs.buscador.focus(); }
                    if (ev.key === 'F4') { ev.preventDefault(); self.abrirCobro(); }
                    if (ev.key === 'F8') { ev.preventDefault(); self.nuevaVenta(); }
                });
            },
            abrirCobro: function () {
                var self = this;
                if (!this.lineas.length) { DT.aviso('Agregá productos.', 'aviso'); return; }
                this.pagos = [];
                this.error = '';
                this.agregarPago('efectivo');
                bootstrap.Modal.getOrCreateInstance(this.$refs.modalCobro).show();
                setTimeout(function () { var i = self.$refs.modalCobro.querySelector('input[placeholder^=Para]'); if (i) { i.focus(); } }, 400);
            },
            nuevaVenta: function () {
                this.lineas = []; this.pagos = []; this.error = '';
                this.cab.descuento_porc = 0;
                this.cab.cliente_id = cfg.clienteDefecto.id;
                this.cliente = null; this.avisos = []; this.listaId = cfg.listaId;
                this.$dispatch('reiniciar-cliente', cfg.clienteDefecto);
                this.$refs.buscador.focus();
            },
            cobrar: function () {
                var self = this;
                this.guardar().then(function (r) {
                    if (!r) { return; }
                    bootstrap.Modal.getInstance(self.$refs.modalCobro).hide();
                    self.ultima = r;
                    self.$refs.ticket.src = r.imprimir;
                    DT.aviso('Venta ' + r.numero + ' registrada: ' + DT.dinero(r.total), 'exito');
                    self.nuevaVenta();
                });
            }
        });
    };
})();
