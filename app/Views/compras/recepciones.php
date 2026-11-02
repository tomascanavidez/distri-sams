<div class="encabezado"><h1>Recepciones de mercadería</h1><div class="acciones"><?php if (puede('compras.crear')): ?><a href="<?= url('/compras/recepciones/nueva') ?>" class="btn btn-primary btn-touch"><i class="bi bi-box-arrow-in-down"></i> Nueva recepción</a><?php endif; ?></div></div>
<div class="card shadow-sm"><div class="card-body p-0 p-md-2">
<table class="table table-hover align-middle tabla-cards mb-0">
    <thead><tr><th>N°</th><th>Fecha</th><th>Proveedor</th><th>OC</th><th>Remito</th><th class="num">Total neto</th><th>Estado</th><th></th></tr></thead>
    <tbody><?php foreach ($pag->items as $r): ?>
        <tr><td data-label="N°"><a href="<?= url('/compras/recepciones/' . $r['id']) ?>"><?= (int) $r['numero'] ?></a></td><td data-label="Fecha"><?= fecha_hora($r['fecha']) ?></td><td class="td-titulo"><?= e($r['razon_social']) ?></td>
            <td data-label="OC"><?= $r['oc_numero'] ? 'N° ' . (int) $r['oc_numero'] : '—' ?></td><td data-label="Remito"><?= e($r['remito_proveedor']) ?></td><td class="num" data-label="Total"><?= money($r['total']) ?></td>
            <td data-label="Estado"><?= estado_badge($r['estado']) ?><?= $r['factura_proveedor_id'] ? ' <span class="badge text-bg-light">Facturada</span>' : '' ?></td>
            <td class="td-acciones text-end"><a class="btn btn-sm btn-outline-primary btn-touch" href="<?= url('/compras/recepciones/' . $r['id']) ?>"><i class="bi bi-eye"></i></a></td></tr>
    <?php endforeach; ?></tbody>
</table></div></div>
<?= $pag->enlaces() ?>
