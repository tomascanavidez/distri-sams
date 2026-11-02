<div class="hoja">
    <div class="d-flex justify-content-between border-bottom pb-2 mb-3">
        <div><strong class="fs-5"><?= e(ajuste('empresa_nombre')) ?></strong><br><small><?= e(ajuste('empresa_razon_social')) ?> · CUIT <?= e(ajuste('empresa_cuit')) ?><br><?= e(ajuste('empresa_domicilio')) ?> · <?= e(ajuste('empresa_telefono')) ?></small></div>
        <div class="text-end"><strong class="fs-5">RECIBO</strong><br>N° <?= e(substr($r['nro'], 3)) ?><br>Fecha: <?= fecha($r['fecha']) ?></div>
    </div>
    <p>Recibimos de <strong><?= e($r['nombre_comercio']) ?></strong> (<?= e($r['cliente_codigo']) ?><?= $r['documento'] ? ' · ' . e($r['tipo_documento'] . ' ' . $r['documento']) : '' ?>) la suma de <strong><?= money($r['total']) ?></strong> en concepto de pago según el siguiente detalle:</p>
    <table class="comp-items"><thead><tr><th>Forma de pago</th><th>Detalle</th><th class="n">Importe</th></tr></thead><tbody>
    <?php foreach ($r['pagos'] as $p): ?><tr><td><?= e(forma_pago($p['forma_pago'])) ?></td><td><?= $p['cheque_numero'] ? 'Cheque N° ' . e($p['cheque_numero']) . ' ' . e($p['cheque_banco']) . ' pago ' . fecha($p['cheque_fecha']) : e($p['referencia'] ?? '') ?></td><td class="n"><?= money($p['importe']) ?></td></tr><?php endforeach; ?>
    </tbody></table>
    <?php if ($r['imputaciones']): ?>
        <p class="mt-3 mb-1"><strong>Aplicado a:</strong></p>
        <table class="comp-items"><tbody><?php foreach ($r['imputaciones'] as $i): ?><tr><td><?= e($i['descripcion']) ?></td><td><?= fecha($i['fecha']) ?></td><td class="n"><?= money($i['importe']) ?></td></tr><?php endforeach; ?></tbody></table>
    <?php endif; ?>
    <?php if ($r['estado'] === 'anulado'): ?><p class="text-danger fw-bold fs-3 text-center mt-3">ANULADO</p><?php endif; ?>
    <div class="d-flex justify-content-end mt-5"><div class="firma">Firma y aclaración</div></div>
</div>
