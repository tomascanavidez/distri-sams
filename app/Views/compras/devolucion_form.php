<?php use App\Core\View; ?>
<div x-data="editorDocumento({ modo: 'compra', endpoint: '/compras/devoluciones/guardar', depositoId: <?= (int) $deposito ?>, cabecera: { proveedor_id: null, motivo: '', nc: { punto_venta: '', numero: '', fecha: '<?= hoy() ?>' } } })">
    <div class="encabezado"><h1>Devolución a proveedor</h1></div>
    <div class="row g-3">
        <div class="col-lg-8"><div class="card shadow-sm"><div class="card-body">
            <?= View::parcial('partials/buscador_productos') ?>
            <?= View::parcial('partials/lineas_documento', ['etiquetaPrecio' => 'Costo neto', 'precioEditable' => true]) ?>
        </div></div></div>
        <div class="col-lg-4"><div class="card shadow-sm"><div class="card-body row g-2">
            <div class="col-12"><label class="form-label">Proveedor</label><select class="form-select" x-model="cab.proveedor_id"><?= opciones($proveedores, '', 'Elegí el proveedor') ?></select></div>
            <div class="col-12"><label class="form-label">Depósito</label><select class="form-select" x-model="depositoId"><?= opciones($depositos, $deposito) ?></select></div>
            <div class="col-12"><label class="form-label">Motivo</label><input class="form-control" x-model="cab.motivo" placeholder="Vencido, dañado, error de entrega..."></div>
            <div class="col-12 small fw-semibold mt-2">Nota de crédito del proveedor (opcional)</div>
            <div class="col-4"><input class="form-control" x-model="cab.nc.punto_venta" inputmode="numeric" placeholder="P. venta"></div>
            <div class="col-8"><input class="form-control" x-model="cab.nc.numero" inputmode="numeric" placeholder="Número de NC"></div>
            <div class="col-12 totales-doc d-flex justify-content-between"><span>Total a costo</span><strong x-text="DT.dinero(subtotal())"></strong></div>
        </div></div></div>
    </div>
    <div class="alert alert-danger mt-3" x-show="error" x-text="error" x-cloak></div>
    <div class="barra-acciones-fija"><button type="button" class="btn btn-primary btn-touch" :disabled="enviando || !cab.proveedor_id || !lineas.length" @click="guardar()"><i class="bi bi-check-lg"></i> Registrar devolución</button></div>
    <?= View::parcial('partials/escaner') ?>
</div>
<?php View::inicio('scripts'); ?><script src="<?= asset('assets/js/documentos.js') ?>"></script><?php View::fin(); ?>
