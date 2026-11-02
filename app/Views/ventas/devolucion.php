<?php use App\Services\VentaService; ?>
<div class="encabezado"><h1>Devolución / notas de crédito y débito</h1></div>
<div class="row g-3">
<div class="col-lg-5">
    <div class="card shadow-sm mb-3"><div class="card-body">
        <div class="card-titulo"><i class="bi bi-arrow-return-left"></i> Devolución de mercadería (NC sobre una factura)</div>
        <form method="get" x-data="autocompletar({ url: '/api/comprobantes', nombre: 'factura_id', enviar: true })" class="autocompletar">
            <input type="hidden" name="factura_id" :value="valor">
            <label class="form-label">Factura o remito</label>
            <input type="search" class="form-control" x-model="q" @input="buscar()" @keydown="tecla($event)" placeholder="Número o nombre del cliente" autocomplete="off">
            <div class="autocompletar-lista" x-show="abierto && resultados.length" @click.outside="abierto = false" x-cloak>
                <template x-for="(r, i) in resultados" :key="r.id"><button type="button" :class="{ activo: i === indice }" @click="elegir(r)"><strong x-text="r.texto"></strong><br><small x-text="r.detalle"></small></button></template>
            </div>
        </form>
    </div></div>
    <div class="card shadow-sm" x-data="{ clase: 'nd', cliente_id: null, desc: '', importe: '', iva: '21', enviando: false,
            enviar() { this.enviando = true; DT.api('/ventas/emitir', { body: { clase: this.clase, cliente_id: this.cliente_id, items: [{ producto_id: null, descripcion: this.desc, cantidad: 1, precio_unitario: DT.parse(this.importe), iva_alicuota: Number(this.iva) }] } })
                .then(r => { if (r.redirigir) location.href = r.redirigir; }).catch(e => DT.aviso(e.message, 'error')).finally(() => this.enviando = false); } }"
         @elegido="if ($event.detail.nombre === 'cliente_nd') cliente_id = $event.detail.item ? $event.detail.item.id : null"><div class="card-body">
        <div class="card-titulo"><i class="bi bi-journal-plus"></i> Nota de débito o crédito por concepto</div>
        <p class="small text-body-secondary">Para intereses, diferencias de precio, bonificaciones, gastos de cheque rechazado, etc. (no mueve stock).</p>
        <div class="row g-2">
            <div class="col-12"><select class="form-select" x-model="clase"><option value="nd">Nota de débito (aumenta la deuda)</option><option value="nc">Nota de crédito (disminuye la deuda)</option></select></div>
            <div class="col-12 autocompletar" x-data="autocompletar({ url: '/api/clientes', nombre: 'cliente_nd' })">
                <input type="search" class="form-control" x-model="q" @input="buscar()" @keydown="tecla($event)" placeholder="Cliente" autocomplete="off">
                <div class="autocompletar-lista" x-show="abierto && resultados.length" @click.outside="abierto = false" x-cloak>
                    <template x-for="(r, i) in resultados" :key="r.id"><button type="button" :class="{ activo: i === indice }" @click="elegir(r)"><strong x-text="r.texto"></strong><br><small x-text="r.detalle"></small></button></template>
                </div>
            </div>
            <div class="col-12"><input class="form-control" x-model="desc" maxlength="200" placeholder="Concepto"></div>
            <div class="col-7"><input class="form-control" x-model="importe" inputmode="decimal" placeholder="Importe final (con IVA)"></div>
            <div class="col-5"><select class="form-select" x-model="iva"><option value="21">IVA 21 %</option><option value="10.5">IVA 10,5 %</option><option value="0">Exento</option></select></div>
            <div class="col-12"><button type="button" class="btn btn-primary btn-touch w-100" :disabled="enviando || !cliente_id || !desc || !importe" @click="enviar()"><i class="bi bi-check-lg"></i> Emitir nota</button></div>
        </div>
    </div></div>
</div>
<div class="col-lg-7">
<?php if ($factura): ?>
    <div class="card shadow-sm" x-data="{ lineas: <?= e(json_encode(array_map(fn ($it) => ['producto_id' => (int) $it['producto_id'], 'descripcion' => $it['descripcion'], 'max' => (float) $it['max_base'], 'precio' => (float) $it['precio_base'], 'cantidad' => 0, 'reingresa' => true, 'unidad' => $it['unidad_base']], $items), JSON_UNESCAPED_UNICODE)) ?>,
            deposito: '1', motivo: '', enviando: false,
            total() { return this.lineas.reduce((s, l) => s + DT.parse(l.cantidad) * l.precio, 0); },
            enviar() {
                const items = this.lineas.filter(l => DT.parse(l.cantidad) > 0).map(l => ({ producto_id: l.producto_id, factor: 1, cantidad: DT.parse(l.cantidad), precio_unitario: l.precio, reingresa_stock: l.reingresa ? 1 : 0 }));
                if (!items.length) { DT.aviso('Indicá cantidades a devolver', 'aviso'); return; }
                this.enviando = true;
                DT.api('/ventas/emitir', { body: { clase: 'nc', comprobante_asociado_id: <?= (int) $factura['id'] ?>, deposito_id: this.deposito, items: items, observaciones: 'Devolución: ' + this.motivo } })
                    .then(r => { if (r.redirigir) location.href = r.redirigir; }).catch(e => DT.aviso(e.message, 'error')).finally(() => this.enviando = false);
            } }"><div class="card-body">
        <div class="card-titulo">Devolución de <?= e(VentaService::numero($factura)) ?> — <?= e($factura['cliente_nombre']) ?></div>
        <p class="small text-body-secondary">Cantidades en unidades. "Reingresa" vuelve la mercadería al stock (si está en buen estado); si no, se registra la NC sin reingreso.</p>
        <template x-for="l in lineas" :key="l.producto_id">
            <div class="row g-2 align-items-center border-bottom py-2">
                <div class="col-12 col-md-5"><strong x-text="l.descripcion"></strong><br><small class="text-body-secondary">Máx. <span x-text="DT.cant(l.max)"></span> · <span x-text="DT.dinero(l.precio)"></span> c/u</small></div>
                <div class="col-5 col-md-3"><input class="form-control text-end" inputmode="decimal" x-model="l.cantidad" :max="l.max" aria-label="Cantidad a devolver"></div>
                <div class="col-7 col-md-4"><label class="form-check-label"><input type="checkbox" class="form-check-input" x-model="l.reingresa"> Reingresa al stock</label></div>
            </div>
        </template>
        <div class="row g-2 mt-2">
            <div class="col-md-6"><label class="form-label">Depósito de reingreso</label><select class="form-select" x-model="deposito"><?= opciones($depositos, 1) ?></select></div>
            <div class="col-md-6"><label class="form-label">Motivo</label><input class="form-control" x-model="motivo" placeholder="Ej.: vencido, dañado, error de pedido"></div>
        </div>
        <div class="d-flex justify-content-between align-items-center mt-3">
            <div class="fs-5">Total NC: <strong x-text="DT.dinero(total())"></strong></div>
            <button type="button" class="btn btn-primary btn-touch" :disabled="enviando" @click="enviar()"><i class="bi bi-check-lg"></i> Emitir nota de crédito</button>
        </div>
    </div></div>
<?php else: ?>
    <div class="card shadow-sm"><div class="card-body text-center text-body-secondary py-5"><i class="bi bi-receipt fs-1 d-block"></i>Buscá la factura o remito a la que corresponde la devolución.</div></div>
<?php endif; ?>
</div>
</div>
