<?php use App\Core\View; ?>
<div x-data="editorDocumento({ modo: 'stock', endpoint: '/stock/transferencias/nueva', depositoId: <?= (int) array_key_first($depositos) ?>, cabecera: { deposito_destino_id: '', observaciones: '' } })">
    <div class="encabezado"><h1>Nueva transferencia</h1></div>
    <div class="row g-3">
        <div class="col-lg-8"><div class="card shadow-sm"><div class="card-body">
            <?= View::parcial('partials/buscador_productos') ?>
            <?= View::parcial('partials/lineas_documento', ['conPrecio' => false]) ?>
        </div></div></div>
        <div class="col-lg-4"><div class="card shadow-sm"><div class="card-body row g-2">
            <div class="col-12"><label class="form-label">Desde</label><select class="form-select" x-model="depositoId"><?= opciones($depositos, array_key_first($depositos)) ?></select></div>
            <div class="col-12"><label class="form-label">Hacia</label><select class="form-select" x-model="cab.deposito_destino_id"><?= opciones($depositos, '', 'Elegí el destino') ?></select></div>
            <div class="col-12"><label class="form-label">Observaciones</label><textarea class="form-control" rows="2" x-model="cab.observaciones"></textarea></div>
        </div></div></div>
    </div>
    <div class="alert alert-danger mt-3" x-show="error" x-text="error" x-cloak></div>
    <div class="barra-acciones-fija"><button type="button" class="btn btn-primary btn-touch" :disabled="enviando || !lineas.length || !cab.deposito_destino_id" @click="guardar()"><i class="bi bi-arrow-left-right"></i> Transferir</button></div>
    <?= View::parcial('partials/escaner') ?>
</div>
<?php View::inicio('scripts'); ?><script src="<?= asset('assets/js/documentos.js') ?>"></script><?php View::fin(); ?>
