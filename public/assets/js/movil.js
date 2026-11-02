/* =====================================================================
   App del preventista: ruta del día, clientes, carga rápida de pedidos,
   visitas sin pedido. Funciona sin conexión (IndexedDB + cola con UUID).
   ===================================================================== */
(function () {
    'use strict';

    function redondear(n) { return Math.round((Number(n) || 0) * 100) / 100; }

    document.addEventListener('alpine:init', function () {
        Alpine.data('appPreventa', function () {
            var base = DT_appBase({ claveDatos: 'preventa', urlDatos: '/api/movil/datos', urlSync: '/api/movil/sincronizar' });
            return Object.assign(base, {
                vista: 'inicio',
                verTodos: false,
                modoElegir: false,
                qCli: '',
                q: '',
                rubro: '',
                limite: 40,
                cliente: null,
                carrito: [],
                presElegida: {},
                obs: '',
                hoja: null,
                motivoVisita: 'tiene_stock',
                obsVisita: '',
                ultimoResultado: null,
                pedidosServidor: [],
                motivos: { tiene_stock: 'Tiene mercadería', cerrado: 'Comercio cerrado', sin_dinero: 'Sin dinero', no_estaba: 'No estaba el encargado', otro: 'Otro motivo' },

                init: function () {
                    var self = this;
                    this.iniciarBase().then(function () {
                        return DTDB.get('borradorPedido');
                    }).then(function (b) {
                        // Si quedó un pedido a medio cargar (se bloqueó el celular, se cerró la app) se recupera
                        if (b && b.carrito && b.carrito.length && self.datos) {
                            self.cliente = self.clientes().filter(function (c) { return c.i === b.cliente; })[0] || null;
                            if (self.cliente) { self.carrito = b.carrito; self.obs = b.obs || ''; self.vista = 'pedido'; DT.aviso('Se recuperó el pedido que estabas cargando.', 'info'); }
                        }
                    });
                    this.$watch('carrito', function () { self.guardarBorrador(); });
                    window.addEventListener('popstate', function () { if (self.hoja) { self.hoja = null; } else if (self.vista !== 'inicio') { self.vista = 'inicio'; } });
                },
                /** Índices de búsqueda (se calculan antes de mostrar los datos, también al abrir sin conexión). */
                preparar: function (d) {
                    d.productos.forEach(function (p) { p._t = DT_normalizar([p.d, p.m, p.c].concat(p.b || []).join(' ')); });
                    d.clientes.forEach(function (c) { c._t = DT_normalizar([c.n, c.t, c.dir, c.c, c.z].join(' ')); });
                },
                alDescargar: function () {
                    if (!this.datos) { return; }
                    if (this.cliente) {
                        // Un cliente dado de alta en el celular se reemplaza por el real (se busca por su UUID)
                        var id = this.cliente.i, uuid = this.cliente.uuid;
                        this.cliente = this.datos.clientes.filter(function (c) { return c.i === id || (uuid && c.u === uuid); })[0] || this.cliente;
                    }
                    if (this.online) { this.cargarPedidosServidor(); }
                },
                alConfirmar: function (op, res) {
                    if (op.tipo === 'cliente' && res.estado === 'ok' && !res.repetido) {
                        DT.aviso('Cliente ' + (op.cliente || '') + ' dado de alta.' + (res.mensajes && res.mensajes.length ? ' ' + res.mensajes.join(' ') : ''), res.mensajes && res.mensajes.length ? 'aviso' : 'exito');
                    }
                    if (op.tipo !== 'pedido') { return; }
                    if (res.estado === 'ok') { DT.aviso('Pedido de ' + (op.cliente || '') + ' enviado (N° ' + res.numero + ').', 'exito'); }
                    if (res.estado === 'revision') { DT.aviso('El pedido de ' + (op.cliente || '') + ' quedó para revisión en la oficina.', 'aviso'); }
                },
                cargarPedidosServidor: function () {
                    var self = this;
                    DT.api('/api/movil/pedidos', { sinRedireccion: true }).then(function (r) { self.pedidosServidor = r.items; }).catch(function () {});
                },
                ir: function (vista) {
                    if (vista !== this.vista) { history.pushState({}, ''); }
                    this.vista = vista;
                    this.modoElegir = false;
                    window.scrollTo(0, 0);
                },

                // ---------------- Clientes y ruta ----------------
                /** Clientes descargados más los dados de alta en el celular que todavía no se enviaron. */
                clientes: function () {
                    if (!this.datos) { return []; }
                    var self = this;
                    var nuevos = this.cola.filter(function (o) { return o.tipo === 'cliente' && o.estado !== 'revision'; }).map(function (o) { return self.clienteLocal(o.datos); })
                        .filter(function (n) { return !self.datos.clientes.some(function (c) { return c.u === n.uuid; }); });
                    return nuevos.concat(this.datos.clientes);
                },
                clienteLocal: function (d) {
                    var z = (this.datos.zonas || []).filter(function (x) { return String(x.i) === String(d.zona_id); })[0];
                    return {
                        i: d.temp_id, uuid: d.uuid, c: 'NUEVO', n: d.nombre_comercio, t: d.titular, dir: d.direccion, loc: d.localidad, lat: d.lat, lng: d.lng,
                        tel: d.telefono, wa: d.whatsapp ? String(d.whatsapp).replace(/\D/g, '') : '', z: z ? z.n : '', l: Number(d.lista_precio_id) || this.datos.lista_defecto,
                        sal: 0, ven: 0, lim: 0, est: 'activo', cp: 'contado', dv: (d.dias_visita || []).join(','), hv: d.horario_visita, iva: d.condicion_iva,
                        hab: [], ult: [], peds: [], nuevo: true, _t: DT_normalizar([d.nombre_comercio, d.titular, d.direccion].join(' '))
                    };
                },
                // ---------------- Alta de cliente (también sin conexión) ----------------
                formCliente: null,
                abrirAltaCliente: function () {
                    var zona = (this.datos.zonas || [])[0];
                    this.formCliente = {
                        nombre_comercio: '', titular: '', tipo_comercio: 'Kiosco', condicion_iva: 'CF', documento: '', direccion: '', localidad: 'Salta',
                        zona_id: zona ? zona.i : '', telefono: '', whatsapp: '', lista_precio_id: this.datos.lista_defecto, horario_visita: '',
                        dias_visita: zona && zona.dv ? zona.dv.split(',').map(Number) : [], observaciones: '', lat: null, lng: null
                    };
                    this.ir('nuevoCliente');
                },
                cambiarZonaForm: function () {
                    var f = this.formCliente, z = (this.datos.zonas || []).filter(function (x) { return String(x.i) === String(f.zona_id); })[0];
                    if (z && z.dv) { f.dias_visita = z.dv.split(',').map(Number); }
                },
                alternarDia: function (n) {
                    var d = this.formCliente.dias_visita, i = d.indexOf(n);
                    if (i >= 0) { d.splice(i, 1); } else { d.push(n); }
                },
                tomarUbicacion: function () {
                    var self = this;
                    if (!navigator.geolocation) { DT.aviso('El celular no permite obtener la ubicación.', 'error'); return; }
                    navigator.geolocation.getCurrentPosition(function (p) {
                        self.formCliente.lat = Number(p.coords.latitude.toFixed(7)); self.formCliente.lng = Number(p.coords.longitude.toFixed(7));
                        DT.aviso('Ubicación cargada.', 'exito');
                    }, function () { DT.aviso('No se pudo obtener la ubicación.', 'error'); }, { enableHighAccuracy: true, timeout: 15000 });
                },
                guardarCliente: function (conPedido) {
                    var self = this, f = this.formCliente;
                    if (!f.nombre_comercio.trim() || !f.direccion.trim()) { DT.aviso('Completá el nombre del comercio y la dirección.', 'aviso'); return; }
                    var d = JSON.parse(JSON.stringify(f));
                    d.uuid = DT_uuid();
                    d.temp_id = -Date.now();
                    // La operación usa el mismo UUID que el cliente: así el servidor no lo duplica al reintentar
                    var op = { uuid: d.uuid, tipo: 'cliente', datos: d, creado: new Date().toISOString(), estado: 'pendiente', cliente: d.nombre_comercio };
                    DTDB.encolar(op).then(function () { return self.refrescarCola(); }).then(function () {
                        var c = self.clienteLocal(d);
                        self.formCliente = null;
                        DT.aviso('Cliente guardado' + (self.online ? '.' : ' en el celular: se enviará al volver la señal.'), 'exito');
                        if (self.online) { self.sincronizar(); }
                        if (conPedido) { self.abrirPedido(c); } else { self.verCliente(c); }
                    }).catch(function (e) { DT.aviso('No se pudo guardar: ' + e.message, 'error'); });
                },

                // ---------------- Lista de precios ----------------
                qPrecios: '',
                rubroPrecios: '',
                limitePrecios: 40,
                productosPrecios: function () {
                    if (!this.datos) { return []; }
                    var self = this, q = DT_normalizar(this.qPrecios.trim()), palabras = q.split(/\s+/).filter(Boolean);
                    return this.datos.productos.filter(function (p) {
                        if (self.rubroPrecios && String(p.r) !== String(self.rubroPrecios)) { return false; }
                        if (/^\d{8,}$/.test(q)) { return (p.b || []).indexOf(q) >= 0; }
                        return palabras.every(function (w) { return p._t.indexOf(w) >= 0; });
                    });
                },
                listasOrdenadas: function () {
                    var self = this;
                    return (this.datos.orden_listas || Object.keys(this.datos.listas)).map(function (id) { return { id: id, nombre: self.datos.listas[id] }; });
                },

                enRutaHoy: function (c) { return String(c.dv || '').split(',').indexOf(String(this.datos.dia)) >= 0; },
                rutaHoy: function () {
                    var self = this;
                    return this.clientes().filter(function (c) { return self.verTodos || self.enRutaHoy(c); });
                },
                opsDe: function (c) { return this.cola.filter(function (o) { return o.datos && o.datos.cliente_id === c.i; }); },
                visitado: function (c) {
                    return (this.datos.visitas_hoy || []).indexOf(c.i) >= 0 || this.opsDe(c).length > 0;
                },
                estadoCliente: function (c) {
                    var ops = this.opsDe(c);
                    if (ops.some(function (o) { return o.tipo === 'pedido' && o.estado !== 'revision'; })) { return { t: 'Pedido sin enviar', c: 'chip-warn', i: 'cloud-upload' }; }
                    if (ops.some(function (o) { return o.estado === 'revision'; })) { return { t: 'Pedido en revisión', c: 'chip-warn', i: 'exclamation-triangle' }; }
                    if (ops.some(function (o) { return o.tipo === 'visita'; })) { return { t: 'Visitado sin pedido', c: 'chip-info', i: 'person-check' }; }
                    if ((this.datos.visitas_hoy || []).indexOf(c.i) >= 0) { return { t: 'Visitado hoy', c: 'chip-ok', i: 'check-circle' }; }
                    return { t: 'Pendiente', c: '', i: 'clock' };
                },
                resumenDia: function () {
                    var self = this, ruta = this.clientes().filter(function (c) { return self.enRutaHoy(c); });
                    var hechos = ruta.filter(function (c) { return self.visitado(c); }).length;
                    var hoy = (this.datos && this.datos.hoy) || '';
                    var peds = this.pedidosServidor.filter(function (p) { return String(p.fecha).indexOf(hoy) === 0; });
                    var enCola = this.cola.filter(function (o) { return o.tipo === 'pedido'; });
                    return {
                        ruta: ruta.length, hechos: hechos, faltan: ruta.length - hechos,
                        pedidos: peds.length + enCola.filter(function (o) { return o.estado !== 'revision'; }).length,
                        vendido: peds.reduce(function (s, p) { return s + Number(p.total); }, 0) + enCola.reduce(function (s, o) { return s + (o.total || 0); }, 0)
                    };
                },
                clientesFiltrados: function () {
                    var palabras = DT_normalizar(this.qCli).split(/\s+/).filter(Boolean);
                    return this.clientes().filter(function (c) { return palabras.every(function (w) { return c._t && c._t.indexOf(w) >= 0; }); }).slice(0, 80);
                },
                verCliente: function (c) { this.cliente = c; this.ir('cliente'); },
                elegirCliente: function (c) { if (this.modoElegir) { this.abrirPedido(c); } else { this.verCliente(c); } },
                mensajeWhatsapp: function (c) {
                    var t = 'Hola ' + (c.t || c.n) + ', te escribe ' + (this.datos.usuario || '') + ' de ' + (this.datos.empresa || 'Distribuidora Toro') + '.';
                    if (c.sal > 0) { t += ' Tu saldo es de ' + DT.dinero(c.sal) + '.'; }
                    return t;
                },
                alertasCliente: function (c) {
                    var a = [];
                    if (c.est === 'bloqueado') { a.push('Cliente BLOQUEADO: el pedido quedará para revisión.'); }
                    if (c.ven > 0) { a.push('Deuda vencida: ' + DT.dinero(c.ven)); }
                    if (c.lim > 0 && c.sal > c.lim) { a.push('Supera el límite de crédito (' + DT.dinero(c.lim) + ').'); }
                    return a;
                },

                // ---------------- Visita sin pedido ----------------
                abrirVisita: function (c) { this.cliente = c; this.motivoVisita = 'tiene_stock'; this.obsVisita = ''; this.hoja = 'visita'; history.pushState({}, ''); },
                registrarVisita: function () {
                    var self = this, c = this.cliente;
                    this.hoja = null;
                    this.ubicacion().then(function (u) {
                        return self.encolar('visita', { cliente_id: c.i, cliente_uuid: c.nuevo ? c.uuid : null, motivo: self.motivoVisita, observaciones: self.obsVisita, lat: u && u.lat, lng: u && u.lng }, { cliente: c.n });
                    }).then(function () { DT.aviso('Visita registrada: ' + c.n, 'exito'); });
                },

                // ---------------- Pedido ----------------
                abrirPedido: function (c) {
                    if (this.carrito.length && this.cliente && this.cliente.i !== c.i && !confirm('Tenés un pedido sin confirmar de ' + this.cliente.n + '. ¿Descartarlo?')) { return; }
                    if (!this.cliente || this.cliente.i !== c.i) { this.carrito = []; this.obs = ''; }
                    this.cliente = c; this.q = ''; this.rubro = c.hab && c.hab.length ? 'hab' : ''; this.limite = 40;
                    this.ir('pedido');
                },
                producto: function (id) { return this.datos.productos.filter(function (p) { return p.i === id; })[0]; },
                productosFiltrados: function () {
                    if (!this.datos) { return []; }
                    var self = this, q = DT_normalizar(this.q.trim()), lista = this.datos.productos;
                    if (/^\d{8,}$/.test(q)) {
                        return lista.filter(function (p) { return (p.b || []).indexOf(q) >= 0 || p.c === q; });
                    }
                    var palabras = q.split(/\s+/).filter(Boolean);
                    // Al escribir se busca en todo el catálogo (salvo que se haya elegido un rubro)
                    if (palabras.length && (this.rubro === 'hab' || this.rubro === 'carrito')) {
                        lista = this.datos.productos;
                    } else if (this.rubro === 'hab' && this.cliente) {
                        lista = this.cliente.hab.map(function (id) { return self.producto(id); }).filter(Boolean);
                    } else if (this.rubro === 'carrito') {
                        lista = this.carrito.map(function (l) { return self.producto(l.producto_id); }).filter(Boolean);
                    } else if (this.rubro) {
                        lista = lista.filter(function (p) { return String(p.r) === String(self.rubro); });
                    }
                    if (palabras.length) { lista = lista.filter(function (p) { return palabras.every(function (w) { return p._t.indexOf(w) >= 0; }); }); }
                    return lista;
                },
                presDe: function (p) {
                    var idx = this.presElegida[p.i];
                    if (idx === undefined) {
                        idx = 0;
                        p.pr.forEach(function (x, i) { if (x[3]) { idx = i; } });
                    }
                    return p.pr[idx] || p.pr[0];
                },
                elegirPres: function (p, i) { this.presElegida[p.i] = i; this.presElegida = Object.assign({}, this.presElegida); },
                precio: function (p, pres) {
                    var lista = this.cliente ? this.cliente.l : Object.keys(p.p)[0];
                    return redondear((p.p[lista] || 0) * (pres || this.presDe(p))[2]);
                },
                linea: function (p) {
                    var pres = this.presDe(p);
                    return this.carrito.filter(function (l) { return l.producto_id === p.i && l.presentacion_id === pres[0]; })[0];
                },
                cantidad: function (p) { var l = this.linea(p); return l ? l.cantidad : 0; },
                fijar: function (p, cant, pres) {
                    pres = pres || this.presDe(p);
                    cant = Math.max(0, Math.round(Number(cant) * 1000) / 1000 || 0);
                    var l = this.carrito.filter(function (x) { return x.producto_id === p.i && x.presentacion_id === pres[0]; })[0];
                    if (!l && cant > 0) {
                        this.carrito.push({ producto_id: p.i, descripcion: p.d, presentacion_id: pres[0], presentacion_nombre: pres[1], factor: pres[2], cantidad: cant, precio: this.precio(p, pres), disponible: p.s });
                    } else if (l && cant > 0) {
                        l.cantidad = cant;
                    } else if (l) {
                        this.carrito.splice(this.carrito.indexOf(l), 1);
                    }
                    if (navigator.vibrate) { navigator.vibrate(15); }
                },
                sumar: function (p, n) { this.fijar(p, this.cantidad(p) + n); },
                totalCarrito: function () { return redondear(this.carrito.reduce(function (s, l) { return s + l.precio * l.cantidad; }, 0)); },
                unidadesCarrito: function () { return this.carrito.reduce(function (s, l) { return s + l.cantidad * l.factor; }, 0); },
                sinStock: function (l) { return l.cantidad * l.factor > l.disponible + 0.0001; },
                repetirUltimo: function () {
                    var self = this;
                    (this.cliente.ult || []).forEach(function (u) {
                        var p = self.producto(u[0]);
                        if (!p) { return; }
                        var pres = p.pr.filter(function (x) { return x[0] === u[1]; })[0] || self.presDe(p);
                        self.fijar(p, u[2], pres);
                    });
                    this.rubro = 'carrito';
                    DT.aviso(this.carrito.length ? 'Se cargó el último pedido. Revisalo antes de confirmar.' : 'No hay un pedido anterior para repetir.', this.carrito.length ? 'exito' : 'aviso');
                },
                escanearProducto: function () {
                    var self = this;
                    DT.escanear(function (codigo) {
                        var p = self.datos.productos.filter(function (x) { return (x.b || []).indexOf(codigo) >= 0 || x.c === codigo; })[0];
                        if (!p) { DT.aviso('No se encontró el código ' + codigo, 'error'); return; }
                        self.q = p.d;
                        self.rubro = '';
                        self.sumar(p, 1);
                        DT.aviso('+1 ' + p.d, 'exito');
                    });
                },
                guardarBorrador: function () {
                    DTDB.set('borradorPedido', this.cliente && this.carrito.length ? { cliente: this.cliente.i, carrito: JSON.parse(JSON.stringify(this.carrito)), obs: this.obs } : null);
                },
                confirmarPedido: function () {
                    var self = this, c = this.cliente;
                    if (!this.carrito.length) { DT.aviso('El pedido está vacío.', 'aviso'); return; }
                    var items = this.carrito.map(function (l) { return { producto_id: l.producto_id, presentacion_id: l.presentacion_id, presentacion_nombre: l.presentacion_nombre, factor: l.factor, cantidad: l.cantidad, precio: l.precio }; });
                    var total = this.totalCarrito();
                    this.hoja = null;
                    this.ubicacion().then(function (u) {
                        return self.encolar('pedido', { cliente_id: c.i, cliente_uuid: c.nuevo ? c.uuid : null, items: items, observaciones: self.obs, lat: u && u.lat, lng: u && u.lng }, { cliente: c.n, total: total, lineas: items.length });
                    }).then(function (op) {
                        self.ultimoResultado = { cliente: c.n, total: total, online: self.online, uuid: op.uuid };
                        self.carrito = []; self.obs = ''; self.presElegida = {};
                        DTDB.set('borradorPedido', null);
                        self.hoja = 'resultado';
                    });
                },
                opsPedidos: function () { return this.cola.filter(function (o) { return o.tipo === 'pedido'; }).slice().reverse(); },
                fotoUrl: function (p) { return p.f && this.datos.fotos ? this.datos.fotos + p.f : null; },
                cerrarSesion: function () {
                    if (this.pendientes() && !confirm('Hay operaciones sin enviar. Se van a conservar en el celular y se envían al volver a ingresar. ¿Cerrar sesión?')) { return; }
                    var f = document.getElementById('formSalir');
                    f.querySelector('[name=_token]').value = this.csrf || DT.csrf();
                    f.submit();
                }
            });
        });
    });
})();
