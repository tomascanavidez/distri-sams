<?php use App\Support\Html;
$difTotal = 0; foreach ($items as $it) { if ($it['cantidad_contada'] !== null) { $difTotal += ((float) $it['cantidad_contada'] - (float) $it['stock_sistema']) * (float) $it['costo']; } } ?>
<div class="encabezado"><h1>Inventario N° <?= (int) $inv['id'] ?> <?= estado_badge($inv['estado']) ?></h1>
    <div class="acciones">
        <a class="btn btn-outline-secondary btn-touch" target="_blank" href="<?= url('/stock/inventarios/' . $inv['id'] . '/planilla') ?>"><i class="bi bi-printer"></i> Planilla para contar</a>
        <?php if ($inv['estado'] === 'abierto' && puede('stock.crear')): ?><?= Html::botonPost('/stock/inventarios/' . $inv['id'] . '/aplicar', '<i class="bi bi-check2-all"></i> Aplicar diferencias', 'btn-primary', '¿Aplicar el inventario? Las diferencias se registran como ajuste de stock (sólo productos contados).') ?><?php endif; ?>
        <?php if ($inv['estado'] === 'abierto' && puede('stock.anular')): ?><?= Html::botonPost('/stock/inventarios/' . $inv['id'] . '/anular', 'Descartar', 'btn-outline-danger', '¿Descartar este inventario sin aplicar?', true) ?><?php endif; ?>
        <?php if ($inv['ajuste_id']): ?><a class="btn btn-outline-primary btn-touch" href="<?= url('/stock/ajustes/' . $inv['ajuste_id']) ?>">Ver ajuste</a><?php endif; ?>
    </div></div>
<p class="text-body-secondary"><?= e($inv['deposito']) ?> · <?= e($inv['rubro'] ?? 'Todos los rubros') ?> · <?= fecha_hora($inv['fecha']) ?> · Diferencia valorizada: <strong class="<?= $difTotal < 0 ? 'text-danger' : '' ?>"><?= money($difTotal) ?></strong></p>
<form method="post" action="<?= url('/stock/inventarios/' . $inv['id'] . '/conteo') ?>">
    <?= csrf_campo() ?>
    <div class="card shadow-sm"><div class="card-body p-0 p-md-2">
    <table class="table table-sm align-middle tabla-cards mb-0">
        <thead><tr><th>Ubic.</th><th>Producto</th><th class="num">Sistema</th><th class="num">Contado</th><th class="num">Diferencia</th><th class="num">Valor dif.</th></tr></thead>
        <tbody><?php foreach ($items as $it): $dif = $it['cantidad_contada'] === null ? null : (float) $it['cantidad_contada'] - (float) $it['stock_sistema']; ?>
            <tr><td data-label="Ubicación"><?= e($it['ubicacion']) ?></td><td class="td-titulo"><?= e($it['descripcion']) ?> <small class="fw-normal text-body-secondary"><?= e($it['codigo']) ?></small></td>
                <td class="num" data-label="Sistema"><?= cant($it['stock_sistema']) ?></td>
                <td class="num" data-label="Contado"><?php if ($inv['estado'] === 'abierto'): ?><input class="form-control form-control-sm text-end ms-auto" style="max-width:110px" name="contado[<?= (int) $it['id'] ?>]" value="<?= $it['cantidad_contada'] !== null ? e(cant($it['cantidad_contada'])) : '' ?>" inputmode="decimal" aria-label="Contado"><?php else: ?><?= $it['cantidad_contada'] !== null ? cant($it['cantidad_contada']) : '—' ?><?php endif; ?></td>
                <td class="num fw-bold <?= $dif < 0 ? 'text-danger' : ($dif > 0 ? 'text-success' : '') ?>" data-label="Diferencia"><?= $dif === null ? '' : ($dif > 0 ? '+' : '') . cant($dif) ?></td>
                <td class="num" data-label="Valor"><?= $dif === null ? '' : money($dif * (float) $it['costo']) ?></td></tr>
        <?php endforeach; ?></tbody>
    </table></div></div>
    <?php if ($inv['estado'] === 'abierto'): ?><div class="barra-acciones-fija"><button class="btn btn-primary btn-touch"><i class="bi bi-save"></i> Guardar conteo</button></div><?php endif; ?>
</form>
