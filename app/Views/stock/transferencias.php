<div class="encabezado"><h1>Transferencias entre depósitos</h1><div class="acciones"><?php if (puede('stock.crear')): ?><a href="<?= url('/stock/transferencias/nueva') ?>" class="btn btn-primary btn-touch"><i class="bi bi-plus-lg"></i> Nueva transferencia</a><?php endif; ?></div></div>
<div class="card shadow-sm"><div class="card-body p-0 p-md-2">
<table class="table table-hover align-middle tabla-cards mb-0">
    <thead><tr><th>N°</th><th>Fecha</th><th>Origen → destino</th><th>Detalle</th><th class="num">Unidades</th><th>Estado</th><th></th></tr></thead>
    <tbody><?php foreach ($pag->items as $t): ?>
        <tr><td data-label="N°"><a href="<?= url('/stock/transferencias/' . $t['id']) ?>"><?= (int) $t['id'] ?></a></td><td data-label="Fecha"><?= fecha_hora($t['fecha']) ?></td>
            <td class="td-titulo"><?= e($t['origen']) ?> <i class="bi bi-arrow-right"></i> <?= e($t['destino']) ?></td><td data-label="Detalle"><small><?= e($t['observaciones']) ?><?= $t['hr_numero'] ? ' (HR ' . (int) $t['hr_numero'] . ')' : '' ?></small></td>
            <td class="num" data-label="Unidades"><?= cant($t['unidades']) ?></td><td data-label="Estado"><?= estado_badge($t['estado']) ?></td>
            <td class="td-acciones text-end"><a class="btn btn-sm btn-outline-primary btn-touch" href="<?= url('/stock/transferencias/' . $t['id']) ?>"><i class="bi bi-eye"></i></a></td></tr>
    <?php endforeach; ?></tbody>
</table></div></div>
<?= $pag->enlaces() ?>
