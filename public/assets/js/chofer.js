/* =====================================================================
   App del chofer: entregas del día, cobros y rechazos. Funciona sin
   conexión: cada acción queda en una cola con UUID y se envía sola.
   ===================================================================== */
(function () {
    'use strict';

    function redondear(n) { return Math.round((Number(n) || 0) * 100) / 100; }

    document.addEventListener('alpine:init', function () {
        Alpine.data('appChofer', function () {
            var base = DT_appBase({ claveDatos: 'reparto', urlDatos: '/api/chofer/datos', urlSync: '/api/chofer/sincronizar' });
            return Object.assign(base, {
                vista: 'inicio',
                parada: null,
                hoja: null,
                resultado: null,
                entregados: {},
                motivo: '',
                obsEntrega: '',
                pagos: [],

                init: function () {
                    var self = this;
                    this.iniciarBase();
                    window.addEventListener('popstate', function () { if (self.hoja) { self.hoja = null; } else if (self.vista !== 'inicio') { self.vista = 'inicio'; } });
                },
                ir: function (v) { if (v !== this.vista) { history.pushState({}, ''); } this.vista = v; window.scrollTo(0, 0); },

                hojaActual: function () {
                    if (!this.datos || !this.datos.hojas.length) { return null; }
                    return this.datos.hojas.filter(function (h) { return h.estado === 'en_reparto'; })[0] || this.datos.hojas[0];
                },
                salidaPendiente: function (h) { return this.cola.some(function (o) { return o.tipo === 'salida' && o.hoja_ruta_id === h.id; }); },
                enReparto: function (h) { return h && (h.estado === 'en_reparto' || this.salidaPendiente(h)); },
                darSalida: function (h) {
                    if (!confirm('¿Confirmás la salida a reparto? Se registra la carga del camión.')) { return; }
                    this.encolar('salida', { hoja_ruta_id: h.id }, { hoja_ruta_id: h.id }).then(function () { DT.aviso('Salida registrada. ¡Buen reparto!', 'exito'); });
                },

                /** Estado de una parada considerando lo registrado en el celular y aún no enviado. */
                opDe: function (pa) { return this.cola.filter(function (o) { return o.tipo === 'entrega' && o.parada_id === pa.id; })[0]; },
                estadoParada: function (pa) { var op = this.opDe(pa); return op ? op.resultado : pa.estado; },
                hecha: function (pa) { return this.estadoParada(pa) !== 'pendiente'; },
                paradas: function () { var h = this.hojaActual(); return h ? h.paradas : []; },
                proxima: function () { var self = this; return this.paradas().filter(function (p) { return !self.hecha(p); })[0]; },
                resumen: function () {
                    var self = this, ps = this.paradas(), hechas = ps.filter(function (p) { return self.hecha(p); });
                    var cobrado = {};
                    this.cola.filter(function (o) { return o.tipo === 'entrega'; }).forEach(function (o) {
                        (o.cobros || []).forEach(function (c) { cobrado[c.forma] = (cobrado[c.forma] || 0) + Number(c.importe); });
                    });
                    return {
                        total: ps.length, hechas: hechas.length, porc: ps.length ? Math.round(hechas.length * 100 / ps.length) : 0,
                        aEntregar: ps.reduce(function (s, p) { return s + (self.hecha(p) ? 0 : p.total); }, 0), cobradoCelular: cobrado
                    };
                },
                etiqueta: function (e) {
                    return { pendiente: ['Pendiente', ''], entregado: ['Entregado', 'chip-ok'], parcial: ['Entrega parcial', 'chip-warn'], rechazado: ['Rechazado', 'chip-mal'], cerrado: ['Cliente cerrado', 'chip-mal'] }[e] || [e, ''];
                },
                abrirParada: function (pa) { this.parada = pa; this.ir('parada'); },

                // ---------------- Registro de la entrega ----------------
                iniciar: function (resultado) {
                    var self = this;
                    this.resultado = resultado;
                    this.motivo = '';
                    this.obsEntrega = '';
                    this.entregados = {};
                    this.parada.items.forEach(function (it) { self.entregados[it.id] = it.base; });
                    this.pagos = [];
                    if (resultado === 'entregado' || resultado === 'parcial') {
                        this.pagos = [{ forma: 'efectivo', importe: this.parada.cliente.cp === 'contado' ? this.aCobrar() : '', auto: this.parada.cliente.cp === 'contado' }];
                    }
                    this.hoja = resultado;
                    history.pushState({}, '');
                },
                sumarItem: function (it, n) {
                    var v = Math.max(0, Math.min(it.base, (Number(this.entregados[it.id]) || 0) + n));
                    this.entregados[it.id] = v;
                    this.entregados = Object.assign({}, this.entregados);
                    this.recalcularCobro();
                },
                /** El importe sugerido sigue a lo entregado mientras el chofer no lo modifique a mano. */
                recalcularCobro: function () {
                    var self = this;
                    this.pagos.forEach(function (p) { if (p.auto) { p.importe = self.aCobrar(); } });
                },
                /** Importe a cobrar según lo entregado (precio por unidad base, descuentos de línea y generales). */
                aCobrar: function () {
                    var self = this, pa = this.parada;
                    if (!pa) { return 0; }
                    if (this.resultado !== 'parcial') { return pa.total; }
                    var t = pa.items.reduce(function (s, it) {
                        var u = Number(self.entregados[it.id]) || 0;
                        return s + (it.precio / it.f) * u * (1 - it.desc / 100);
                    }, 0);
                    return redondear(t * (1 - pa.desc / 100));
                },
                totalPagos: function () { return redondear(this.pagos.reduce(function (s, p) { return s + DT.parse(p.importe); }, 0)); },
                agregarPago: function (forma) { var resto = redondear(this.aCobrar() - this.totalPagos()); this.pagos.push({ forma: forma, importe: resto > 0 ? resto : '' }); },
                confirmar: function () {
                    var self = this, pa = this.parada;
                    if ((this.resultado === 'rechazado') && !this.motivo) { DT.aviso('Elegí el motivo del rechazo.', 'aviso'); return; }
                    var datos = {
                        parada_id: pa.id, resultado: this.resultado, motivo: this.motivo || null, observaciones: this.obsEntrega || null,
                        items: this.resultado === 'parcial' ? this.entregados : {},
                        cobros: this.pagos.filter(function (p) { return DT.parse(p.importe) > 0; }).map(function (p) { return { forma: p.forma, importe: DT.parse(p.importe) }; })
                    };
                    if (this.resultado === 'parcial' && !Object.keys(this.entregados).some(function (k) { return Number(self.entregados[k]) > 0; })) {
                        DT.aviso('Si no entregaste nada, marcá la parada como rechazada.', 'aviso'); return;
                    }
                    this.hoja = null;
                    this.encolar('entrega', datos, datos).then(function () {
                        DT.aviso(self.etiqueta(self.resultado)[0] + ': ' + pa.cliente.n, 'exito');
                        var sig = self.proxima();
                        if (sig) { self.parada = sig; window.scrollTo(0, 0); } else { self.ir('inicio'); }
                    });
                },
                conflictos: function () { return this.cola.filter(function (o) { return o.estado === 'conflicto'; }); },
                cerrarSesion: function () {
                    if (this.pendientes() && !confirm('Hay entregas sin enviar: quedan guardadas en el celular. ¿Cerrar sesión?')) { return; }
                    var f = document.getElementById('formSalir');
                    f.querySelector('[name=_token]').value = this.csrf || DT.csrf();
                    f.submit();
                }
            });
        });
    });
})();
