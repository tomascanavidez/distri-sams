<?php use App\Core\View; use App\Services\StockService; ?>
<div x-data="editorDocumento({ modo: 'stock', endpoint: '/stock/ajustes/nuevo', depositoId: <?= (int) $deposito ?>, cabecera: { motivo: 'rotura', observaciones: '' } })">
    <div class="encabezado"><h1>Nuevo ajuste de stock</h1></div>
    <div class="row g-3">
        <div class="col-lg-8"><div class="card shadow-sm"><div class="card-body">
            <p class="small text-body-secondary" x-show="['rotura','vencimiento','faltante','consumo_interno'].includes(cab.motivo)">Las cantidades se <strong>descuentan</strong> del stock.</p>
            <p class="small text-body-secondary" x-show="cab.motivo === 'sobrante'">Las cantidades se <strong>suman</strong> al stock.</p>
            <p class="small text-body-secondary" x-show="cab.motivo === 'otro'">Indicá cantidades positivas para sumar y negativas (−) para restar.</p>
            <?= View::parcial('partials/buscador_productos') ?>
            <?= View::parcial('partials/lineas_documento', ['conPrecio' => false, 'conLotes' => true]) ?>
        </div></div></div>
        <div class="col-lg-4"><div class="card shadow-sm"><div class="card-body row g-2">
            <div class="col-12"><label class="form-label">Motivo</label><select class="form-select" x-model="cab.motivo"><?= opciones(array_diff_key(StockService::MOTIVOS_AJUSTE, ['inventario' => 1, 'rendicion' => 1]), 'rotura') ?></select></div>
            <div class="col-12"><label class="form-label">Depósito</label><select class="form-select" x-model="depositoId"><?= opciones($depositos, $deposito) ?></select></div>
            <div class="col-12"><label class="form-label">Observaciones</label><textarea class="form-control" rows="2" x-model="cab.observaciones" maxlength="255"></textarea></div>
        </div></div></div>
    </div>
    <div class="alert alert-danger mt-3" x-show="error" x-text="error" x-cloak></div>
    <div class="barra-acciones-fija"><button type="button" class="btn btn-primary btn-touch" :disabled="enviando || !lineas.length" @click="guardar()"><i class="bi bi-check-lg"></i> Aplicar ajuste</button></div>
    <?= View::parcial('partials/escaner') ?>
</div>
<?php View::inicio('scripts'); ?><script src="<?= asset('assets/js/documentos.js') ?>"></script><?php View::fin(); ?>
