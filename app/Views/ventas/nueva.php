<?php use App\Core\View;
$titulos = ['factura' => 'Nueva factura', 'remito' => 'Nuevo remito', 'presupuesto' => 'Nuevo presupuesto', 'nd' => 'Nueva nota de débito']; ?>
<div x-data="editorDocumento({ modo: 'venta', endpoint: '/ventas/emitir', depositoId: <?= (int) $deposito ?>, cabecera: { clase: '<?= e($clase) ?>', cliente_id: null, descuento_porc: 0, observaciones: '' } })"
     @elegido="if ($event.detail.nombre === 'cliente_id') { cab.cliente_id = $event.detail.item ? $event.detail.item.id : null; cargarCliente(cab.cliente_id); }">
    <div class="encabezado"><h1><?= e($titulos[$clase]) ?></h1>
        <div class="acciones">
            <select class="form-select" x-model="cab.clase" aria-label="Tipo de comprobante" style="min-width: 180px">
                <option value="factura">Factura</option><option value="remito">Remito</option><option value="presupuesto">Presupuesto</option>
            </select>
        </div>
    </div>
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card shadow-sm mb-3"><div class="card-body">
                <label class="form-label fw-semibold">Cliente <span class="text-danger">*</span></label>
                <div class="autocompletar" x-data="autocompletar({ url: '/api/clientes', nombre: 'cliente_id' })">
                    <input type="search" class="form-control form-control-lg" x-model="q" @input="buscar()" @keydown="tecla($event)" placeholder="Buscar cliente" autocomplete="off">
                    <div class="autocompletar-lista" x-show="abierto && resultados.length" @click.outside="abierto = false" x-cloak>
                        <template x-for="(r, i) in resultados" :key="r.id"><button type="button" :class="{ activo: i === indice }" @click="elegir(r)"><strong x-text="r.texto"></strong><br><small x-text="r.detalle"></small></button></template>
                    </div>
                </div>
                <template x-if="cliente"><div class="mt-2 small d-flex flex-wrap gap-3">
                    <span><span x-text="cliente.condicion_iva === 'RI' ? 'Factura A (Resp. Inscripto)' : 'Factura B'"></span></span>
                    <span>Lista: <strong x-text="cliente.lista || '—'"></strong></span>
                    <span>Saldo: <strong :class="cliente.resumen.saldo > 0 ? 'saldo-deuda' : ''" x-text="DT.dinero(cliente.resumen.saldo)"></strong></span>
                </div></template>
                <template x-for="a in avisos"><div class="alert alert-warning py-2 mt-2 mb-0 small"><i class="bi bi-exclamation-triangle"></i> <span x-text="a"></span></div></template>
            </div></div>
            <div class="card shadow-sm mb-3"><div class="card-body">
                <?= View::parcial('partials/buscador_productos') ?>
                <?= View::parcial('partials/lineas_documento', ['conDescuento' => true, 'conBonificacion' => true, 'precioEditable' => puede('productos.precios')]) ?>
            </div></div>
            <div class="card shadow-sm mb-3" x-show="cab.clase !== 'presupuesto'"><div class="card-body">
                <div class="card-titulo">Cobro <small class="text-body-secondary fw-normal">(lo no pagado va a la cuenta corriente)</small></div>
                <?= View::parcial('partials/pagos', ['cuentas' => $cuentas]) ?>
            </div></div>
        </div>
        <div class="col-lg-4">
            <div class="card shadow-sm mb-3"><div class="card-body row g-2">
                <div class="col-12"><label class="form-label" for="dep">Depósito</label><select class="form-select" id="dep" x-model="depositoId"><?= opciones($depositos, $deposito) ?></select></div>
                <div class="col-12"><label class="form-label" for="desc">Descuento general %</label><input class="form-control" id="desc" inputmode="decimal" x-model="cab.descuento_porc"></div>
                <div class="col-12"><label class="form-label" for="obs">Observaciones</label><textarea class="form-control" id="obs" rows="2" maxlength="255" x-model="cab.observaciones"></textarea></div>
                <?php if (puede('clientes.editar')): ?><div class="col-12 form-check ms-2"><input type="checkbox" class="form-check-input" id="forzar" x-model="cab.forzar_credito"><label class="form-check-label" for="forzar">Autorizar aunque supere el crédito</label></div><?php endif; ?>
            </div></div>
            <div class="card shadow-sm totales-doc"><div class="card-body">
                <div class="d-flex justify-content-between"><span>Subtotal</span><span x-text="DT.dinero(subtotal())"></span></div>
                <div class="d-flex justify-content-between" x-show="cab.descuento_porc > 0"><span>Descuento</span><span x-text="'- ' + DT.dinero(subtotal() - total())"></span></div>
                <div class="d-flex justify-content-between total"><span>Total</span><span x-text="DT.dinero(total())"></span></div>
                <small class="text-body-secondary">El IVA se discrimina en el comprobante según la condición del cliente.</small>
            </div></div>
        </div>
    </div>
    <div class="alert alert-danger mt-3" x-show="error" x-cloak><span x-text="error"></span></div>
    <div class="barra-acciones-fija d-flex gap-2 align-items-center">
        <div class="fw-bold fs-5 me-auto" x-text="DT.dinero(total())"></div>
        <button type="button" class="btn btn-primary btn-touch" :disabled="enviando || !cab.cliente_id || !lineas.length" @click="guardar()"><i class="bi bi-receipt"></i> Emitir</button>
    </div>
    <?= View::parcial('partials/escaner') ?>
</div>
<?php View::inicio('scripts'); ?><script src="<?= asset('assets/js/documentos.js') ?>"></script><?php View::fin(); ?>
