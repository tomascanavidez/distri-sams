<?php use App\Support\Html; ?>
<div class="encabezado"><h1>Devoluciones a proveedores</h1><div class="acciones"><?php if (puede('compras.crear')): ?><a href="<?= url('/compras/devoluciones/nueva') ?>" class="btn btn-primary btn-touch"><i class="bi bi-plus-lg"></i> Nueva devolución</a><?php endif; ?></div></div>
<div class="card shadow-sm"><div class="card-body p-0 p-md-2">
<table class="table align-middle tabla-cards mb-0">
    <thead><tr><th>N°</th><th>Fecha</th><th>Proveedor</th><th>Depósito</th><th>Motivo</th><th class="num">Total (costo)</th><th>NC</th><th>Estado</th><th></th></tr></thead>
    <tbody><?php foreach ($pag->items as $d): ?>
        <tr><td data-label="N°"><?= (int) $d['numero'] ?></td><td data-label="Fecha"><?= fecha_hora($d['fecha']) ?></td><td class="td-titulo"><?= e($d['razon_social']) ?></td><td data-label="Depósito"><?= e($d['deposito']) ?></td>
            <td data-label="Motivo"><?= e($d['motivo']) ?></td><td class="num" data-label="Total"><?= money($d['total']) ?></td><td data-label="NC"><?= $d['nc_numero'] ? 'N° ' . (int) $d['nc_numero'] : '—' ?></td><td data-label="Estado"><?= estado_badge($d['estado']) ?></td>
            <td class="td-acciones text-end"><?php if ($d['estado'] === 'aplicada' && puede('compras.anular')): ?><?= Html::botonPost('/compras/devoluciones/' . $d['id'] . '/anular', '<i class="bi bi-x-circle"></i>', 'btn-sm btn-outline-danger', '¿Anular la devolución? La mercadería vuelve al stock.', true) ?><?php endif; ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$pag->items): ?><tr><td colspan="9" class="text-center text-body-secondary py-4">No hay devoluciones.</td></tr><?php endif; ?></tbody>
</table></div></div>
<?= $pag->enlaces() ?>
