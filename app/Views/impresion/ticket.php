<?php $letra = in_array($c['letra'], ['A', 'B', 'C'], true) ? $c['letra'] : 'X'; ?>
<div class="hoja">
    <div class="centro"><strong class="grande"><?= e(ajuste('empresa_nombre')) ?></strong><br>
        <?= e(ajuste('empresa_razon_social')) ?><br>CUIT <?= e(ajuste('empresa_cuit')) ?><br><?= e(ajuste('empresa_domicilio')) ?><br><?= e(condicion_iva(ajuste('empresa_condicion_iva'))) ?></div>
    <hr>
    <div class="centro"><strong><?= e(mb_strtoupper(tipo_comprobante($c['tipo']))) ?> "<?= $letra ?>"</strong><br>N° <?= sprintf('%04d-%08d', $c['pv_numero'], $c['numero']) ?><br><?= fecha_hora($c['fecha']) ?></div>
    <hr>
    <div><?= e($c['cliente_nombre']) ?><br><?= e($c['cliente_documento']) ?> · <?= e(condicion_iva($c['cliente_condicion_iva'])) ?></div>
    <hr>
    <table><?php foreach ($c['items'] as $it): ?>
        <tr><td colspan="2"><?= e($it['descripcion']) ?></td></tr>
        <tr><td><?= cant($it['cantidad']) ?> <?= e($it['presentacion_nombre']) ?> x <?= money($it['precio_unitario'], false) ?><?= (float) $it['descuento_porc'] > 0 ? ' -' . num($it['descuento_porc'], 0) . '%' : '' ?></td><td class="n"><?= money($it['total'], false) ?></td></tr>
    <?php endforeach; ?></table>
    <hr>
    <table>
        <?php if ((float) $c['descuento_importe'] > 0): ?><tr><td>Descuento</td><td class="n">-<?= money($c['descuento_importe'], false) ?></td></tr><?php endif; ?>
        <?php if ($letra === 'A'): ?><tr><td>Neto gravado</td><td class="n"><?= money((float) $c['neto_21'] + (float) $c['neto_105'], false) ?></td></tr><tr><td>IVA</td><td class="n"><?= money((float) $c['iva_21'] + (float) $c['iva_105'], false) ?></td></tr><?php endif; ?>
        <tr class="grande"><td>TOTAL</td><td class="n"><?= money($c['total']) ?></td></tr>
        <?php foreach ($pagos as $p): ?><tr><td><?= e(forma_pago($p['forma_pago'])) ?></td><td class="n"><?= money($p['importe'], false) ?></td></tr><?php endforeach; ?>
    </table>
    <?php if ($letra === 'B'): ?><hr><div>IVA contenido: <?= money((float) $c['iva_21'] + (float) $c['iva_105']) ?></div><?php endif; ?>
    <hr>
    <div class="centro"><?= $c['cae'] ? 'CAE ' . e($c['cae']) . '<br>Vto. ' . fecha($c['cae_vencimiento']) : ($letra === 'X' ? 'No válido como factura' : 'Comprobante interno') ?><br><?= e(ajuste('empresa_leyenda')) ?></div>
</div>
