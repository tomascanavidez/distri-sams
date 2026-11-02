<div class="hoja">
    <div class="d-flex justify-content-between border-bottom pb-2 mb-2">
        <div><h1 class="h5 mb-0"><?= e(ajuste('empresa_nombre')) ?></h1><small><?= e(ajuste('empresa_domicilio')) ?> · <?= e(ajuste('empresa_telefono')) ?></small></div>
        <div class="text-end"><h2 class="h5 mb-0">Nota de pedido N° <?= (int) $p['numero'] ?></h2><small>Documento no válido como factura</small></div>
    </div>
    <div class="row small mb-2">
        <div class="col-7"><strong><?= e($p['nombre_comercio']) ?></strong> (<?= e($p['cliente_codigo']) ?>)<br><?= e($p['direccion']) ?>, <?= e($p['localidad']) ?> · Tel. <?= e($p['telefono']) ?></div>
        <div class="col-5 text-end">Fecha: <?= fecha_hora($p['fecha']) ?><br>Entrega: <?= fecha($p['fecha_entrega']) ?> · Zona: <?= e($p['zona']) ?><br>Preventista: <?= e($p['preventista']) ?></div>
    </div>
    <table class="comp-items">
        <thead><tr><th>Ubic.</th><th>Código</th><th>Producto</th><th class="n">Cant.</th><th>Presentación</th><th class="n">Unid.</th><th class="n">Precio</th><th class="n">Subtotal</th></tr></thead>
        <tbody><?php foreach ($items as $it): ?>
            <tr><td><?= e($it['ubicacion']) ?></td><td><?= e($it['codigo']) ?></td><td><?= e($it['descripcion']) ?><?= (float) $it['bonificacion_base'] > 0 ? ' <strong>+' . cant($it['bonificacion_base']) . ' bonif.</strong>' : '' ?></td>
                <td class="n"><?= cant($it['cantidad']) ?></td><td><?= e($it['presentacion_nombre']) ?></td><td class="n"><?= cant($it['cantidad_base']) ?></td><td class="n"><?= money($it['precio_unitario']) ?></td><td class="n"><?= money($it['subtotal']) ?></td></tr>
        <?php endforeach; ?></tbody>
    </table>
    <table class="comp-totales"><?php if ((float) $p['descuento_importe'] > 0): ?><tr><td>Descuento</td><td class="text-end">- <?= money($p['descuento_importe']) ?></td></tr><?php endif; ?><tr class="total"><td>Total</td><td class="text-end"><?= money($p['total']) ?></td></tr></table>
    <?php if ($p['observaciones']): ?><p class="mt-2 small"><strong>Observaciones:</strong> <?= e($p['observaciones']) ?></p><?php endif; ?>
    <div class="d-flex justify-content-between mt-5"><div class="firma">Preparó</div><div class="firma">Recibí conforme</div></div>
</div>
