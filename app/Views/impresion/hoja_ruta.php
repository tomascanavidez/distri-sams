<div class="hoja">
    <div class="d-flex justify-content-between border-bottom pb-2 mb-2">
        <div><strong class="fs-5"><?= e(ajuste('empresa_nombre')) ?></strong><br><small>Hoja de ruta N° <?= (int) $hr['numero'] ?> · <?= fecha($hr['fecha']) ?></small></div>
        <div class="text-end small">Chofer: <strong><?= e($hr['chofer']) ?></strong><br>Vehículo: <?= e($hr['patente']) ?> · Zona: <?= e($hr['zona'] ?? 'Varias') ?></div>
    </div>
    <table class="comp-items"><thead><tr><th>#</th><th>Cliente / dirección</th><th>Horario</th><th>Pedido</th><th class="n">A cobrar</th><th class="n">Saldo cta.</th><th>Resultado / cobro</th></tr></thead><tbody>
    <?php foreach ($paradas as $pa): ?>
        <tr><td><strong><?= (int) $pa['orden'] ?></strong></td><td><strong><?= e($pa['nombre_comercio']) ?></strong><br><?= e($pa['direccion']) ?> · Tel. <?= e($pa['telefono']) ?></td><td><?= e($pa['horario_entrega']) ?></td>
            <td>N° <?= (int) $pa['pedido_numero'] ?><br><small><?= $pa['condicion_pago'] === 'contado' ? 'CONTADO' : 'Cta. cte.' ?></small></td><td class="n"><?= money($pa['total']) ?></td><td class="n"><?= money($pa['saldo']) ?></td><td style="width:150px">&nbsp;</td></tr>
    <?php endforeach; ?></tbody></table>
    <table class="comp-totales"><tr class="total"><td>Total a entregar</td><td class="text-end"><?= money(array_sum(array_column($paradas, 'total'))) ?></td></tr></table>
    <div class="d-flex justify-content-between mt-5"><div class="firma">Firma chofer</div><div class="firma">Control depósito</div></div>
</div>
<?php foreach ($paradas as $pa): ?>
<div class="hoja">
    <div class="d-flex justify-content-between border-bottom pb-1 mb-2"><strong>Remito de entrega — Pedido N° <?= (int) $pa['pedido_numero'] ?></strong><span>HR <?= (int) $hr['numero'] ?> · Parada <?= (int) $pa['orden'] ?></span></div>
    <p class="small mb-1"><strong><?= e($pa['nombre_comercio']) ?></strong> — <?= e($pa['direccion']) ?></p>
    <table class="comp-items"><thead><tr><th>Producto</th><th class="n">Cant.</th><th>Presentación</th><th class="n">Precio</th><th class="n">Subtotal</th><th>Entregado</th></tr></thead><tbody>
    <?php foreach ($items[$pa['pedido_id']] ?? [] as $it): ?><tr><td><?= e($it['descripcion']) ?><?= (float) $it['bonificacion_base'] > 0 ? ' (+' . cant($it['bonificacion_base']) . ' bonif.)' : '' ?></td><td class="n"><?= cant($it['cantidad']) ?></td><td><?= e($it['presentacion_nombre']) ?></td><td class="n"><?= money($it['precio_unitario']) ?></td><td class="n"><?= money($it['subtotal']) ?></td><td>______</td></tr><?php endforeach; ?>
    </tbody></table>
    <table class="comp-totales"><tr class="total"><td>Total</td><td class="text-end"><?= money($pa['total']) ?></td></tr></table>
    <div class="d-flex justify-content-end mt-4"><div class="firma">Recibí conforme</div></div>
</div>
<?php endforeach; ?>
