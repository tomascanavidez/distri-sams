<?php use App\Support\Html; ?>
<div class="encabezado"><h1>Vencimientos</h1><div class="acciones"><?= Html::exportar('/stock/vencimientos') ?></div></div>
<form class="filtros row g-2 align-items-end" method="get"><div class="col-6 col-md-3"><label class="form-label" for="dias">Vencen en los próximos (días)</label><input class="form-control" id="dias" name="dias" inputmode="numeric" value="<?= (int) $dias ?>"></div><div class="col-6 col-md-2"><button class="btn btn-outline-primary w-100 btn-touch">Ver</button></div></form>
<div class="card shadow-sm"><div class="card-body p-0 p-md-2">
<table class="table align-middle tabla-cards mb-0">
    <thead><tr><th>Vence</th><th>Producto</th><th>Lote</th><th>Depósito</th><th class="num">Cantidad</th><th class="num">Valor</th><th></th></tr></thead>
    <tbody><?php foreach ($filas as $f): ?>
        <tr><td data-label="Vence" class="<?= (int) $f['dias'] < 0 ? 'text-danger fw-bold' : ((int) $f['dias'] <= 7 ? 'text-warning-emphasis fw-bold' : '') ?>"><?= fecha($f['vencimiento']) ?> <small>(<?= (int) $f['dias'] < 0 ? 'vencido' : (int) $f['dias'] . ' días' ?>)</small></td>
            <td class="td-titulo"><?= e($f['descripcion']) ?></td><td data-label="Lote"><?= e($f['lote']) ?></td><td data-label="Depósito"><?= e($f['deposito']) ?></td>
            <td class="num" data-label="Cantidad"><?= cant($f['cantidad']) ?> <?= e($f['unidad_base']) ?></td><td class="num" data-label="Valor"><?= money((float) $f['cantidad'] * (float) $f['costo']) ?></td>
            <td class="td-acciones text-end"><?php if (puede('stock.crear') && (int) $f['dias'] < 0): ?><a class="btn btn-sm btn-outline-danger btn-touch" href="<?= url('/stock/ajustes/nuevo') ?>">Dar de baja</a><?php endif; ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$filas): ?><tr><td colspan="7" class="text-center text-body-secondary py-4">No hay productos por vencer en ese plazo.</td></tr><?php endif; ?></tbody>
</table></div></div>
