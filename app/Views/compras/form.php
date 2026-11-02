<?php use App\Core\View; ?>
<div x-data="editorDocumento({ modo: 'compra', endpoint: '/compras/guardar', items: <?= e(json_encode($items, JSON_UNESCAPED_UNICODE)) ?>, depositoId: <?= (int) $deposito ?>, cabecera: <?= e(json_encode($cab, JSON_UNESCAPED_UNICODE)) ?> })">
    <div class="encabezado"><h1><?= e($titulo) ?></h1><div class="acciones"><a href="<?= url($oc ? '/compras/' . $oc['id'] : '/compras') ?>" class="btn btn-outline-secondary btn-touch"><i class="bi bi-arrow-left"></i> Volver</a></div></div>
    <div class="row g-3">
        <div class="col-lg-8"><div class="card shadow-sm"><div class="card-body">
            <?= View::parcial('partials/buscador_productos') ?>
            <?= View::parcial('partials/lineas_documento', ['etiquetaPrecio' => 'Costo neto', 'precioEditable' => true]) ?>
        </div></div></div>
        <div class="col-lg-4"><div class="card shadow-sm"><div class="card-body row g-2">
            <div class="col-12"><label class="form-label">Proveedor <span class="text-danger">*</span></label><select class="form-select" x-model="cab.proveedor_id"><?= opciones($proveedores, $cab['proveedor_id'], 'Elegí el proveedor') ?></select></div>
            <div class="col-6"><label class="form-label">Depósito</label><select class="form-select" x-model="depositoId"><?= opciones($depositos, $deposito) ?></select></div>
            <div class="col-6"><label class="form-label">Entrega estimada</label><input type="date" class="form-control" x-model="cab.fecha_entrega"></div>
            <div class="col-12"><label class="form-label">Observaciones</label><textarea class="form-control" rows="2" x-model="cab.observaciones"></textarea></div>
            <div class="col-12 totales-doc d-flex justify-content-between mt-2"><span>Total neto estimado</span><strong class="total" x-text="DT.dinero(subtotal())"></strong></div>
        </div></div></div>
    </div>
    <div class="alert alert-danger mt-3" x-show="error" x-text="error" x-cloak></div>
    <div class="barra-acciones-fija d-flex gap-2">
        <button type="button" class="btn btn-outline-primary btn-touch" :disabled="enviando || !cab.proveedor_id" @click="guardar({ enviar: false })"><i class="bi bi-save"></i> Guardar</button>
        <?php if (!$oc): ?><button type="button" class="btn btn-primary btn-touch" :disabled="enviando || !cab.proveedor_id" @click="guardar({ enviar: true })"><i class="bi bi-send"></i> Guardar y marcar enviada</button><?php endif; ?>
    </div>
    <?= View::parcial('partials/escaner') ?>
</div>
<?php View::inicio('scripts'); ?><script src="<?= asset('assets/js/documentos.js') ?>"></script><?php View::fin(); ?>
