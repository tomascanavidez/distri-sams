<?php use App\Support\Html; ?>
<div class="encabezado"><h1>Comisiones de preventistas</h1></div>
<form class="filtros row g-2 align-items-end" method="get">
    <div class="col-12 col-md-4"><label class="form-label" for="preventista_id">Preventista</label><select class="form-select" id="preventista_id" name="preventista_id"><?= opciones($preventistas, $prev, 'Elegí el preventista') ?></select></div>
    <div class="col-6 col-md-3"><label class="form-label" for="desde">Desde</label><input type="date" class="form-control" id="desde" name="desde" value="<?= e($desde) ?>"></div>
    <div class="col-6 col-md-3"><label class="form-label" for="hasta">Hasta</label><input type="date" class="form-control" id="hasta" name="hasta" value="<?= e($hasta) ?>"></div>
    <div class="col-12 col-md-2"><button class="btn btn-outline-primary w-100 btn-touch">Calcular</button></div>
</form>
<?php if ($calculo): ?>
<div class="card shadow-sm mb-3"><div class="card-body">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div><div class="card-titulo mb-0"><?= e($calculo['preventista']['nombre']) ?> — <?= num($calculo['porcentaje'], 2) ?> % sobre <?= $calculo['preventista']['comision_base'] === 'cobranzas' ? 'cobranzas' : 'ventas netas (sin IVA)' ?></div>
            <small class="text-body-secondary">Documentos no liquidados del <?= fecha($desde) ?> al <?= fecha($hasta) ?>: <?= count($calculo['documentos']) ?></small></div>
        <div class="text-end"><div>Base: <strong><?= money($calculo['base']) ?></strong></div><div class="fs-4">Comisión: <strong><?= money($calculo['comision']) ?></strong></div></div>
    </div>
    <?php if ($calculo['documentos'] && puede('preventistas.crear')): ?>
        <form method="post" action="<?= url('/preventistas/comisiones/liquidar') ?>" class="d-flex gap-2 mt-3" data-confirmar="¿Generar la liquidación? Los documentos quedan marcados como liquidados.">
            <?= csrf_campo() ?><input type="hidden" name="preventista_id" value="<?= (int) $prev ?>"><input type="hidden" name="desde" value="<?= e($desde) ?>"><input type="hidden" name="hasta" value="<?= e($hasta) ?>">
            <input class="form-control" name="observaciones" placeholder="Observaciones"><button class="btn btn-primary btn-touch text-nowrap"><i class="bi bi-calculator"></i> Liquidar</button>
        </form>
    <?php endif; ?>
    <details class="mt-3"><summary>Ver documentos</summary>
        <table class="table table-sm mt-2"><tbody><?php foreach ($calculo['documentos'] as $d): ?><tr><td><?= fecha($d['fecha']) ?></td><td><?= e($d['numero']) ?></td><td><?= e($d['nombre_comercio']) ?></td><td class="text-end"><?= money($d['base']) ?></td></tr><?php endforeach; ?></tbody></table>
    </details>
</div></div>
<?php endif; ?>
<h2 class="h5">Liquidaciones</h2>
<div class="card shadow-sm"><div class="card-body p-0 p-md-2">
<table class="table align-middle tabla-cards mb-0">
    <thead><tr><th>N°</th><th>Preventista</th><th>Período</th><th class="num">Base</th><th class="num">%</th><th class="num">Comisión</th><th>Estado</th><th class="text-end">Acciones</th></tr></thead>
    <tbody><?php foreach ($liquidaciones as $l): ?>
        <tr><td data-label="N°"><a href="<?= url('/preventistas/comisiones/' . $l['id']) ?>"><?= (int) $l['id'] ?></a></td><td class="td-titulo"><?= e($l['preventista']) ?></td><td data-label="Período"><?= fecha($l['desde']) ?> al <?= fecha($l['hasta']) ?></td>
            <td class="num" data-label="Base"><?= money($l['importe_base']) ?></td><td class="num" data-label="%"><?= num($l['porcentaje']) ?></td><td class="num fw-bold" data-label="Comisión"><?= money($l['importe']) ?></td><td data-label="Estado"><?= estado_badge($l['estado']) ?></td>
            <td class="td-acciones text-end text-nowrap"><?php if ($l['estado'] === 'pendiente'): ?>
                <?php if (puede('preventistas.editar')): ?><?= Html::botonPost('/preventistas/comisiones/' . $l['id'] . '/estado', 'Marcar pagada', 'btn-sm btn-outline-success', '¿Marcar la liquidación como pagada? Registrá el pago como gasto (categoría Comisiones) si corresponde.', false, ['estado' => 'pagada']) ?><?php endif; ?>
                <?php if (puede('preventistas.anular')): ?><?= Html::botonPost('/preventistas/comisiones/' . $l['id'] . '/estado', 'Anular', 'btn-sm btn-outline-danger', '¿Anular la liquidación? Los documentos vuelven a quedar pendientes.', false, ['estado' => 'anulada']) ?><?php endif; ?>
            <?php endif; ?></td></tr>
    <?php endforeach; ?></tbody>
</table></div></div>
