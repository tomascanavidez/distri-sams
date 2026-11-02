<?php use App\Core\View; ?>
<?php if (!$oc): ?>
<form method="get" class="filtros row g-2 align-items-end"><div class="col-md-8"><label class="form-label">Recibir contra una orden de compra</label><select class="form-select" name="orden_id" data-autoenviar><?= opciones($ordenes, '', 'Elegí una orden (o cargá sin orden abajo)') ?></select></div></form>
<?php endif; ?>
<div x-data="editorDocumento({ modo: 'compra', endpoint: '/compras/recepciones/guardar', items: <?= e(json_encode($items, JSON_UNESCAPED_UNICODE)) ?>, depositoId: <?= (int) $deposito ?>,
        cabecera: { orden_id: <?= (int) ($oc['id'] ?? 0) ?: 'null' ?>, proveedor_id: <?= (int) ($oc['proveedor_id'] ?? 0) ?: 'null' ?>, remito_proveedor: '', observaciones: '', actualizar_costos: true } })">
    <div class="encabezado"><h1><?= e($titulo) ?></h1></div>
    <div class="row g-3">
        <div class="col-lg-8"><div class="card shadow-sm"><div class="card-body">
            <?php if ($oc): ?><p class="small text-body-secondary">Se muestran las cantidades pendientes. Corregí lo que realmente llegó (podés recibir parcial).</p><?php endif; ?>
            <?= View::parcial('partials/buscador_productos') ?>
            <?= View::parcial('partials/lineas_documento', ['etiquetaPrecio' => 'Costo neto', 'precioEditable' => true, 'conLotes' => true]) ?>
        </div></div></div>
        <div class="col-lg-4"><div class="card shadow-sm"><div class="card-body row g-2">
            <?php if (!$oc): ?><div class="col-12"><label class="form-label">Proveedor <span class="text-danger">*</span></label><select class="form-select" x-model="cab.proveedor_id"><?= opciones($proveedores, '', 'Elegí el proveedor') ?></select></div>
            <?php else: ?><div class="col-12"><small class="text-body-secondary">Proveedor</small><div class="fw-bold"><?= e($oc['razon_social']) ?></div></div><?php endif; ?>
            <div class="col-6"><label class="form-label">Depósito</label><select class="form-select" x-model="depositoId"><?= opciones($depositos, $deposito) ?></select></div>
            <div class="col-6"><label class="form-label">Remito del proveedor</label><input class="form-control" x-model="cab.remito_proveedor"></div>
            <div class="col-12"><label class="form-label">Observaciones</label><textarea class="form-control" rows="2" x-model="cab.observaciones"></textarea></div>
            <div class="col-12 form-check ms-2"><input type="checkbox" class="form-check-input" id="ac" x-model="cab.actualizar_costos"><label class="form-check-label" for="ac">Actualizar costos (último y promedio) y recalcular precios</label></div>
            <div class="col-12 totales-doc d-flex justify-content-between mt-2"><span>Total neto</span><strong class="total" x-text="DT.dinero(subtotal())"></strong></div>
        </div></div></div>
    </div>
    <div class="alert alert-danger mt-3" x-show="error" x-text="error" x-cloak></div>
    <div class="barra-acciones-fija"><button type="button" class="btn btn-success btn-touch" :disabled="enviando || !cab.proveedor_id || !lineas.length" @click="guardar()"><i class="bi bi-box-arrow-in-down"></i> Confirmar recepción (ingresa stock)</button></div>
    <?= View::parcial('partials/escaner') ?>
</div>
<?php View::inicio('scripts'); ?><script src="<?= asset('assets/js/documentos.js') ?>"></script><?php View::fin(); ?>
