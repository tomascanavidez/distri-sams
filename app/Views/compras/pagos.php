<div class="encabezado"><h1>Pagos a proveedores</h1><div class="acciones"><?php if (puede('compras.crear')): ?><a href="<?= url('/compras/pagos/nuevo') ?>" class="btn btn-primary btn-touch"><i class="bi bi-plus-lg"></i> Nuevo pago</a><?php endif; ?></div></div>
<div class="card shadow-sm"><div class="card-body p-0 p-md-2">
<table class="table align-middle tabla-cards mb-0">
    <thead><tr><th>N°</th><th>Fecha</th><th>Proveedor</th><th>Formas</th><th class="num">Total</th><th>Estado</th><th></th></tr></thead>
    <tbody><?php foreach ($pag->items as $p): ?>
        <tr><td data-label="N°"><a href="<?= url('/compras/pagos/' . $p['id']) ?>"><?= (int) $p['numero'] ?></a></td><td data-label="Fecha"><?= fecha_hora($p['fecha']) ?></td><td class="td-titulo"><?= e($p['razon_social']) ?></td>
            <td data-label="Formas"><?= e(str_replace(['cheque_tercero', 'cheque_propio', 'transferencia', 'efectivo'], ['Cheque de tercero', 'Cheque propio', 'Transferencia', 'Efectivo'], (string) $p['formas'])) ?></td>
            <td class="num fw-semibold" data-label="Total"><?= money($p['total']) ?></td><td data-label="Estado"><?= estado_badge($p['estado']) ?></td>
            <td class="td-acciones text-end"><a class="btn btn-sm btn-outline-primary btn-touch" href="<?= url('/compras/pagos/' . $p['id']) ?>"><i class="bi bi-eye"></i></a></td></tr>
    <?php endforeach; ?>
    <?php if (!$pag->items): ?><tr><td colspan="7" class="text-center text-body-secondary py-4">No hay pagos.</td></tr><?php endif; ?></tbody>
</table></div></div>
<?= $pag->enlaces() ?>
