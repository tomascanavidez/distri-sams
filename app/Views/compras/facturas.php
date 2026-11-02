<?php use App\Support\Html; ?>
<div class="encabezado"><h1>Facturas de proveedores</h1><div class="acciones"><?php if (puede('compras.crear')): ?><a href="<?= url('/compras/facturas/nueva') ?>" class="btn btn-primary btn-touch"><i class="bi bi-plus-lg"></i> Cargar factura</a><?php endif; ?></div></div>
<form class="filtros row g-2 align-items-end" method="get"><div class="col-md-5"><label class="form-label" for="proveedor_id">Proveedor</label><select class="form-select" id="proveedor_id" name="proveedor_id" data-autoenviar><?= opciones($proveedores, $_GET['proveedor_id'] ?? '', 'Todos') ?></select></div></form>
<div class="card shadow-sm"><div class="card-body p-0 p-md-2">
<table class="table align-middle tabla-cards mb-0">
    <thead><tr><?= $pag->th('fecha', 'Fecha') ?><th>Comprobante</th><th>Proveedor</th><th class="num">Neto</th><th class="num">IVA</th><th class="num">Percep.</th><?= $pag->th('total', 'Total', 'num') ?><th class="num">Pendiente</th><th>Vence</th><th></th></tr></thead>
    <tbody><?php foreach ($pag->items as $f): ?>
        <tr class="<?= $f['estado'] === 'anulada' ? 'opacity-75 text-decoration-line-through' : '' ?>"><td data-label="Fecha"><?= fecha($f['fecha']) ?></td><td data-label="Comprobante"><?= e($f['tipo'] . ' ' . sprintf('%05d-%08d', $f['punto_venta'], $f['numero'])) ?></td>
            <td class="td-titulo"><?= e($f['razon_social']) ?></td><td class="num" data-label="Neto"><?= money($f['neto_gravado']) ?></td><td class="num" data-label="IVA"><?= money((float) $f['iva_21'] + (float) $f['iva_105'] + (float) $f['iva_27']) ?></td>
            <td class="num" data-label="Percepciones"><?= money((float) $f['percepcion_iva'] + (float) $f['percepcion_iibb']) ?></td><td class="num fw-semibold" data-label="Total"><?= money($f['total']) ?></td>
            <td class="num <?= $f['pendiente'] > 0 ? 'saldo-deuda' : '' ?>" data-label="Pendiente"><?= money($f['pendiente']) ?></td><td data-label="Vence"><?= fecha($f['vencimiento']) ?></td>
            <td class="td-acciones text-end"><?php if ($f['estado'] !== 'anulada' && puede('compras.anular')): ?><?= Html::botonPost('/compras/facturas/' . $f['id'] . '/anular', '<i class="bi bi-x-circle"></i>', 'btn-sm btn-outline-danger', '¿Anular la factura del proveedor? Se revierte de su cuenta corriente.', true) ?><?php endif; ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$pag->items): ?><tr><td colspan="10" class="text-center text-body-secondary py-4">No hay facturas.</td></tr><?php endif; ?></tbody>
</table></div></div>
<?= $pag->enlaces() ?>
