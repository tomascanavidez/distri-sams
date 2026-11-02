<div class="encabezado"><h1>Pago a proveedor</h1></div>
<form method="get" class="filtros row g-2 align-items-end"><div class="col-md-6"><label class="form-label" for="proveedor_id">Proveedor</label><select class="form-select" id="proveedor_id" name="proveedor_id" data-autoenviar><?= opciones($proveedores, $prov, 'Elegí el proveedor') ?></select></div>
    <?php if ($prov): ?><div class="col-md-6"><span class="fs-5">Saldo a pagar: <strong class="saldo-deuda"><?= money($saldo) ?></strong></span></div><?php endif; ?></form>
<?php if ($prov): ?>
<div class="row g-3" x-data="{ pendientes: <?= e(json_encode($pendientes)) ?>, detalle: [{ forma: 'transferencia', importe: '', cuenta_bancaria_id: '', cheque_id: '', referencia: '' }], cheques: <?= e(json_encode($cheques)) ?>, caja_id: '<?= (int) $cajaUsuario ?>', obs: '', enviando: false, error: '',
        importeDe(d) { if (d.forma === 'cheque_tercero') { const c = this.cheques.find(x => String(x.id) === String(d.cheque_id)); return c ? Number(c.importe) : 0; } return DT.parse(d.importe); },
        total() { return DT.redondear(this.detalle.reduce((s, d) => s + this.importeDe(d), 0), 2); },
        imputado() { return DT.redondear(this.pendientes.reduce((s, i) => s + DT.parse(i.importe), 0), 2); },
        imputarTodo() { let r = this.total(); this.pendientes.forEach(i => { const v = Math.min(r, i.pendiente); i.importe = v > 0 ? DT.redondear(v, 2) : ''; r = DT.redondear(r - v, 2); }); },
        guardar() { this.enviando = true; this.error = '';
            DT.api('/compras/pagos/nuevo', { body: { proveedor_id: <?= (int) $prov ?>, caja_id: this.caja_id, observaciones: this.obs, detalle: this.detalle.map(d => Object.assign({}, d, { importe: this.importeDe(d) })), imputaciones: this.pendientes.map(i => ({ id: i.id, importe: DT.parse(i.importe) })) } })
              .then(r => location.href = r.redirigir).catch(e => this.error = e.message).finally(() => this.enviando = false); } }">
    <div class="col-lg-6"><div class="card shadow-sm"><div class="card-body">
        <div class="card-titulo d-flex justify-content-between">Comprobantes pendientes <button type="button" class="btn btn-sm btn-outline-primary" @click="imputarTodo()">Imputar automáticamente</button></div>
        <template x-for="i in pendientes" :key="i.id"><div class="row g-2 align-items-center border-bottom py-2">
            <div class="col-7"><strong x-text="i.descripcion"></strong><br><small :class="i.dias > 0 ? 'text-danger' : 'text-body-secondary'" x-text="'Vence ' + i.vencimiento + ' · pendiente ' + DT.dinero(i.pendiente)"></small></div>
            <div class="col-5"><input class="form-control text-end" inputmode="decimal" x-model="i.importe" placeholder="A pagar" @dblclick="i.importe = i.pendiente"></div></div></template>
        <template x-if="!pendientes.length"><p class="text-body-secondary mb-0">No hay comprobantes pendientes: el pago queda a cuenta.</p></template>
    </div></div></div>
    <div class="col-lg-6"><div class="card shadow-sm"><div class="card-body">
        <div class="card-titulo">Formas de pago</div>
        <template x-for="(d, k) in detalle" :key="k"><div class="border rounded p-2 mb-2 row g-2">
            <div class="col-6"><select class="form-select" x-model="d.forma"><option value="transferencia">Transferencia</option><option value="efectivo">Efectivo</option><option value="cheque_propio">Cheque propio</option><option value="cheque_tercero">Cheque de tercero (endoso)</option></select></div>
            <div class="col-5" x-show="d.forma !== 'cheque_tercero'"><input class="form-control text-end" inputmode="decimal" x-model="d.importe" placeholder="Importe"></div>
            <div class="col-11" x-show="d.forma === 'cheque_tercero'"><select class="form-select" x-model="d.cheque_id"><option value="">Elegí un cheque en cartera</option><template x-for="c in cheques" :key="c.id"><option :value="c.id" x-text="'N° ' + c.numero + ' ' + c.banco + ' - ' + DT.dinero(c.importe) + ' - pago ' + c.fecha_pago"></option></template></select></div>
            <div class="col-1"><button type="button" class="btn btn-outline-danger btn-sm" @click="detalle.splice(k, 1)" aria-label="Quitar"><i class="bi bi-x"></i></button></div>
            <div class="col-6" x-show="d.forma === 'transferencia' || d.forma === 'cheque_propio'"><select class="form-select" x-model="d.cuenta_bancaria_id"><?= opciones($cuentas, '', 'Cuenta') ?></select></div>
            <div class="col-6" x-show="d.forma === 'efectivo'"><select class="form-select" x-model="caja_id"><?= opciones($cajas, $cajaUsuario) ?></select></div>
            <div class="col-6" x-show="d.forma !== 'cheque_tercero'"><input class="form-control" x-model="d.referencia" placeholder="Referencia / N° cheque"></div>
        </div></template>
        <button type="button" class="btn btn-outline-secondary btn-touch mb-2" @click="detalle.push({ forma: 'efectivo', importe: '', cuenta_bancaria_id: '', cheque_id: '', referencia: '' })"><i class="bi bi-plus"></i> Otra forma de pago</button>
        <input class="form-control mb-2" x-model="obs" placeholder="Observaciones">
        <div class="alert alert-danger" x-show="error" x-text="error"></div>
        <div class="d-flex justify-content-between align-items-center"><span class="fs-5">Total: <strong x-text="DT.dinero(total())"></strong></span>
            <button type="button" class="btn btn-primary btn-touch" :disabled="enviando || total() <= 0" @click="guardar()"><i class="bi bi-check-lg"></i> Registrar pago</button></div>
    </div></div></div>
</div>
<?php endif; ?>
