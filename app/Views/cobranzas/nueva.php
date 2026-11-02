<?php use App\Core\View; ?>
<div x-data="{
        cliente_id: <?= (int) ($cliente['id'] ?? 0) ?: 'null' ?>, pendientes: [], saldo: 0, credito: 0, pagos: [], observaciones: '', caja_id: '<?= (int) $cajaUsuario ?>', enviando: false, error: '',
        init() { if (this.cliente_id) this.cargar(); },
        cargar() { DT.api('/cobranzas/pendientes/' + this.cliente_id).then(r => { this.pendientes = r.items.map(i => Object.assign(i, { importe: '' })); this.saldo = r.saldo; this.credito = r.credito; }); },
        total() { return DT.redondear(this.pagos.reduce((s, p) => s + DT.parse(p.importe), 0), 2); },
        totalPagos() { return this.total(); }, saldoACuenta() { return 0; }, vuelto() { return 0; },
        imputado() { return DT.redondear(this.pendientes.reduce((s, i) => s + DT.parse(i.importe), 0), 2); },
        agregarPago(forma) { const resto = DT.redondear(this.imputado() - this.total(), 2); this.pagos.push({ forma: forma, importe: resto > 0 ? resto : '', referencia: '', cuenta_bancaria_id: '', cheque: { numero: '', banco: '', fecha_pago: '' } }); },
        imputarTodo() { let resto = this.total(); this.pendientes.forEach(i => { const v = Math.min(resto, i.pendiente); i.importe = v > 0 ? DT.redondear(v, 2) : ''; resto = DT.redondear(resto - v, 2); }); },
        guardar() {
            this.enviando = true; this.error = '';
            DT.api('/cobranzas/nueva', { body: { cliente_id: this.cliente_id, pagos: this.pagos.map(p => ({ forma: p.forma, importe: DT.parse(p.importe), referencia: p.referencia, cuenta_bancaria_id: p.cuenta_bancaria_id || null, cheque: p.cheque })),
                imputaciones: this.pendientes.filter(i => DT.parse(i.importe) > 0).map(i => ({ id: i.id, importe: DT.parse(i.importe) })), observaciones: this.observaciones, caja_id: this.caja_id } })
              .then(r => { location.href = r.redirigir; }).catch(e => { this.error = e.message; }).finally(() => this.enviando = false);
        } }"
     @elegido="if ($event.detail.nombre === 'cliente_id') { cliente_id = $event.detail.item ? $event.detail.item.id : null; pendientes = []; if (cliente_id) cargar(); }">
    <div class="encabezado"><h1>Nueva cobranza</h1></div>
    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card shadow-sm mb-3"><div class="card-body">
                <label class="form-label fw-semibold">Cliente</label>
                <div class="autocompletar" x-data="autocompletar({ url: '/api/clientes', nombre: 'cliente_id', valor: '<?= (int) ($cliente['id'] ?? 0) ?: '' ?>', texto: <?= e(json_encode($cliente['texto'] ?? '')) ?> })">
                    <input type="search" class="form-control form-control-lg" x-model="q" @input="buscar()" @keydown="tecla($event)" placeholder="Buscar cliente" autocomplete="off">
                    <div class="autocompletar-lista" x-show="abierto && resultados.length" @click.outside="abierto = false" x-cloak>
                        <template x-for="(r, i) in resultados" :key="r.id"><button type="button" :class="{ activo: i === indice }" @click="elegir(r)"><strong x-text="r.texto"></strong><br><small x-text="r.detalle"></small></button></template>
                    </div>
                </div>
                <div class="mt-2 d-flex gap-3 small" x-show="cliente_id"><span>Saldo: <strong :class="saldo > 0 ? 'saldo-deuda' : 'saldo-favor'" x-text="DT.dinero(saldo)"></strong></span><span x-show="credito > 0">Crédito a favor: <strong x-text="DT.dinero(credito)"></strong></span></div>
            </div></div>
            <div class="card shadow-sm mb-3" x-show="cliente_id"><div class="card-body">
                <div class="card-titulo d-flex justify-content-between">Comprobantes pendientes <button type="button" class="btn btn-sm btn-outline-primary" @click="imputarTodo()">Imputar el cobro (más viejos primero)</button></div>
                <template x-if="!pendientes.length"><p class="text-body-secondary mb-0">El cliente no tiene comprobantes pendientes: el cobro queda a cuenta.</p></template>
                <template x-for="i in pendientes" :key="i.id">
                    <div class="row g-2 align-items-center border-bottom py-2">
                        <div class="col-12 col-md-7"><strong x-text="i.descripcion"></strong><br><small :class="i.dias > 0 ? 'text-danger' : 'text-body-secondary'" x-text="i.fecha + ' · vence ' + i.vencimiento + (i.dias > 0 ? ' (' + i.dias + ' días vencido)' : '')"></small></div>
                        <div class="col-6 col-md-2 text-md-end"><small class="d-md-none">Pendiente </small><span class="fw-semibold" x-text="DT.dinero(i.pendiente)"></span></div>
                        <div class="col-6 col-md-3"><input class="form-control text-end" inputmode="decimal" x-model="i.importe" placeholder="A imputar" @dblclick="i.importe = i.pendiente"></div>
                    </div>
                </template>
                <div class="text-end mt-2">Imputado: <strong x-text="DT.dinero(imputado())"></strong></div>
            </div></div>
        </div>
        <div class="col-lg-5">
            <div class="card shadow-sm mb-3" x-show="cliente_id"><div class="card-body">
                <div class="card-titulo">Pagos recibidos</div>
                <?= View::parcial('partials/pagos', ['cuentas' => $cuentas]) ?>
                <div class="row g-2 mt-2">
                    <div class="col-6"><label class="form-label small">Caja (efectivo)</label><select class="form-select" x-model="caja_id"><?= opciones($cajas, $cajaUsuario) ?></select></div>
                    <div class="col-12"><label class="form-label small">Observaciones</label><input class="form-control" x-model="observaciones" maxlength="255"></div>
                </div>
                <div class="alert alert-danger mt-2" x-show="error" x-text="error"></div>
                <div class="d-flex justify-content-between align-items-center mt-3">
                    <span class="fs-5">Total: <strong x-text="DT.dinero(total())"></strong></span>
                    <button type="button" class="btn btn-primary btn-touch" :disabled="enviando || total() <= 0" @click="guardar()"><i class="bi bi-check-lg"></i> Registrar recibo</button>
                </div>
                <small class="text-body-secondary" x-show="total() > imputado() + 0.009">Lo no imputado se aplica automáticamente a la deuda más antigua.</small>
            </div></div>
        </div>
    </div>
</div>
