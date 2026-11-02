<?php use App\Support\Html; ?>
<div class="encabezado"><h1>Recepción N° <?= (int) $r['numero'] ?> <?= estado_badge($r['estado']) ?></h1>
    <div class="acciones">
        <?php if (!$r['factura_proveedor_id'] && $r['estado'] === 'aplicada' && puede('compras.crear')): ?><a class="btn btn-outline-primary btn-touch" href="<?= url('/compras/facturas/nueva?proveedor_id=' . $r['proveedor_id']) ?>"><i class="bi bi-receipt"></i> Cargar factura</a><?php endif; ?>
        <?php if ($r['estado'] === 'aplicada' && puede('compras.anular')): ?><?= Html::botonPost('/compras/recepciones/' . $r['id'] . '/anular', '<i class="bi bi-x-circle"></i> Anular', 'btn-outline-danger', '¿Anular la recepción? Se descuenta del stock lo ingresado (los costos no se revierten).', true) ?><?php endif; ?>
        <button class="btn btn-outline-secondary btn-touch" data-imprimir><i class="bi bi-printer"></i></button></div></div>
<div class="row g-3">
    <div class="col-lg-4"><div class="card shadow-sm"><div class="card-body ficha-dato row g-2">
        <div class="col-12"><small>Proveedor</small><?= e($r['razon_social']) ?></div>
        <div class="col-6"><small>Fecha</small><?= fecha_hora($r['fecha']) ?></div><div class="col-6"><small>Depósito</small><?= e($r['deposito']) ?></div>
        <div class="col-6"><small>Orden de compra</small><?= $r['orden_id'] ? '<a href="' . url('/compras/' . $r['orden_id']) . '">N° ' . (int) $r['oc_numero'] . '</a>' : '—' ?></div><div class="col-6"><small>Remito</small><?= e($r['remito_proveedor'] ?: '—') ?></div>
        <div class="col-6"><small>Factura</small><?= $r['f_numero'] ? e($r['f_tipo'] . ' ' . sprintf('%05d-%08d', $r['f_pv'], $r['f_numero'])) : 'Pendiente' ?></div><div class="col-6"><small>Usuario</small><?= e($r['usuario']) ?></div>
        <?php if ($r['motivo_anulacion']): ?><div class="col-12"><small>Motivo de anulación</small><?= e($r['motivo_anulacion']) ?></div><?php endif; ?>
    </div></div></div>
    <div class="col-lg-8"><div class="card shadow-sm"><div class="card-body">
        <table class="table align-middle tabla-cards mb-0">
            <thead><tr><th>Producto</th><th class="num">Cantidad</th><th class="num">Unidades</th><th class="num">Costo unit.</th><th>Lote / vencimiento</th><th class="num">Subtotal</th></tr></thead>
            <tbody><?php foreach ($items as $it): ?>
                <tr><td class="td-titulo"><?= e($it['descripcion']) ?></td><td class="num" data-label="Cantidad"><?= cant($it['cantidad']) ?> <?= e($it['presentacion_nombre']) ?></td><td class="num" data-label="Unidades"><?= cant($it['cantidad_base']) ?></td>
                    <td class="num" data-label="Costo"><?= money($it['costo_unitario_base']) ?></td><td data-label="Lote"><?= e($it['lote']) ?> <?= fecha($it['vencimiento']) ?></td><td class="num" data-label="Subtotal"><?= money((float) $it['cantidad_base'] * (float) $it['costo_unitario_base']) ?></td></tr>
            <?php endforeach; ?></tbody>
            <tfoot><tr class="fw-bold"><td colspan="5" class="text-end">Total neto</td><td class="num"><?= money($r['total']) ?></td></tr></tfoot>
        </table>
    </div></div></div>
</div>
