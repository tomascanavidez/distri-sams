<?php
$letra = in_array($c['letra'], ['A', 'B', 'C'], true) ? $c['letra'] : 'X';
$discrimina = $letra === 'A';
$logo = ajuste('empresa_logo') ? url('/uploads/empresa/' . ajuste('empresa_logo')) : url('/assets/img/logo.png');
?>
<div class="hoja">
    <div class="comp-cabecera">
        <div>
            <img src="<?= e($logo) ?>" alt="" class="comp-logo mb-1"><br>
            <strong class="fs-5"><?= e(ajuste('empresa_nombre')) ?></strong><br>
            <small><?= e(ajuste('empresa_razon_social')) ?><br><?= e(ajuste('empresa_domicilio')) ?><br>Tel. <?= e(ajuste('empresa_telefono')) ?><br><?= e(condicion_iva(ajuste('empresa_condicion_iva'))) ?></small>
        </div>
        <div class="comp-letra"><div class="letra"><?= $letra ?></div><small><?= $letra === 'X' ? 'No válido como factura' : 'Cód. ' . ['A' => '01', 'B' => '06', 'C' => '11'][$letra] ?></small></div>
        <div>
            <strong class="fs-5"><?= e(mb_strtoupper(tipo_comprobante($c['tipo']))) ?></strong><br>
            <strong>N° <?= sprintf('%04d-%08d', $c['pv_numero'], $c['numero']) ?></strong><br>
            <small>Fecha: <?= fecha($c['fecha']) ?><br>CUIT: <?= e(ajuste('empresa_cuit')) ?><br>IIBB: <?= e(ajuste('empresa_iibb')) ?><br>Inicio de actividades: <?= fecha(ajuste('empresa_inicio_actividades')) ?></small>
        </div>
    </div>
    <div class="comp-cliente small">
        <div class="d-flex justify-content-between"><span><strong>Cliente:</strong> <?= e($c['cliente_nombre']) ?> (<?= e($c['cliente_codigo']) ?>)</span><span><?= e($c['cliente_documento']) ?></span></div>
        <div class="d-flex justify-content-between"><span><strong>Domicilio:</strong> <?= e($c['cliente_direccion']) ?></span><span><?= e(condicion_iva($c['cliente_condicion_iva'])) ?></span></div>
        <div><strong>Condición de venta:</strong> <?= $c['condicion_venta'] === 'cta_cte' ? 'Cuenta corriente — vence ' . fecha($c['vencimiento']) : 'Contado' ?></div>
    </div>
    <table class="comp-items">
        <thead><tr><th>Código</th><th>Descripción</th><th class="n">Cant.</th><th>Presentación</th><th class="n"><?= $discrimina ? 'P. unit. neto' : 'P. unitario' ?></th><th class="n">Desc.</th><?php if ($discrimina): ?><th class="n">IVA</th><?php endif; ?><th class="n">Importe</th></tr></thead>
        <tbody><?php foreach ($c['items'] as $it):
            $precio = (float) $it['precio_unitario'];
            if ($discrimina) { $precio = round(($precio - (float) $it['internos'] / max(0.001, (float) $it['cantidad'])) / (1 + (float) $it['iva_alicuota'] / 100), 2); } ?>
            <tr><td><?= e($it['codigo']) ?></td><td><?= e($it['descripcion']) ?><?= (float) $it['bonificacion_base'] > 0 ? ' <strong>(+' . cant($it['bonificacion_base']) . ' bonif.)</strong>' : '' ?></td>
                <td class="n"><?= cant($it['cantidad']) ?></td><td><?= e($it['presentacion_nombre']) ?><?= (float) $it['factor'] != 1 ? ' x' . cant($it['factor']) : '' ?></td>
                <td class="n"><?= money($precio, false) ?></td><td class="n"><?= (float) $it['descuento_porc'] > 0 ? num($it['descuento_porc'], 1) . '%' : '' ?></td>
                <?php if ($discrimina): ?><td class="n"><?= num($it['iva_alicuota'], 1) ?>%</td><?php endif; ?>
                <td class="n"><?= money($discrimina ? $it['neto'] : $it['total'], false) ?></td></tr>
        <?php endforeach; ?></tbody>
    </table>
    <table class="comp-totales">
        <?php if ((float) $c['descuento_importe'] > 0): ?><tr><td>Descuento general (<?= num($c['descuento_porc'], 1) ?>%)</td><td class="text-end">- <?= money($c['descuento_importe']) ?></td></tr><?php endif; ?>
        <?php if ($discrimina): ?>
            <?php if ((float) $c['neto_21']): ?><tr><td>Neto gravado 21%</td><td class="text-end"><?= money($c['neto_21']) ?></td></tr><?php endif; ?>
            <?php if ((float) $c['neto_105']): ?><tr><td>Neto gravado 10,5%</td><td class="text-end"><?= money($c['neto_105']) ?></td></tr><?php endif; ?>
            <?php if ((float) $c['neto_exento']): ?><tr><td>Exento</td><td class="text-end"><?= money($c['neto_exento']) ?></td></tr><?php endif; ?>
            <?php if ((float) $c['iva_21']): ?><tr><td>IVA 21%</td><td class="text-end"><?= money($c['iva_21']) ?></td></tr><?php endif; ?>
            <?php if ((float) $c['iva_105']): ?><tr><td>IVA 10,5%</td><td class="text-end"><?= money($c['iva_105']) ?></td></tr><?php endif; ?>
            <?php if ((float) $c['impuestos_internos']): ?><tr><td>Impuestos internos</td><td class="text-end"><?= money($c['impuestos_internos']) ?></td></tr><?php endif; ?>
        <?php endif; ?>
        <tr class="total"><td>TOTAL</td><td class="text-end"><?= money($c['total']) ?></td></tr>
    </table>
    <?php if ($letra === 'B'): ?><p class="small mt-2 mb-0">Régimen de Transparencia Fiscal al Consumidor (Ley 27.743) — IVA contenido: <?= money((float) $c['iva_21'] + (float) $c['iva_105']) ?><?= (float) $c['impuestos_internos'] > 0 ? ' — Otros impuestos nacionales indirectos: ' . money($c['impuestos_internos']) : '' ?></p><?php endif; ?>
    <?php if ($pagos): ?><p class="small mt-2 mb-0"><strong>Pagos:</strong> <?php foreach ($pagos as $p) echo e(forma_pago($p['forma_pago'])) . ' ' . money($p['importe']) . '  '; ?></p><?php endif; ?>
    <?php if ($c['observaciones']): ?><p class="small mt-1"><strong>Observaciones:</strong> <?= e($c['observaciones']) ?></p><?php endif; ?>
    <div class="comp-pie d-flex justify-content-between">
        <span><?= $c['cae'] ? 'CAE N° ' . e($c['cae']) . ' — Vto. ' . fecha($c['cae_vencimiento']) : ($letra === 'X' ? 'Documento no válido como factura.' : 'Comprobante interno (sin CAE).') ?></span>
        <span><?= e(ajuste('empresa_leyenda')) ?></span>
    </div>
    <?php if ($c['estado'] === 'anulado'): ?><p class="text-danger fw-bold fs-3 text-center mt-3">ANULADO</p><?php endif; ?>
</div>
