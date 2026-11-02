<div class="hoja">
    <div class="d-flex justify-content-between border-bottom pb-2 mb-2">
        <div><strong class="fs-5"><?= e(ajuste('empresa_nombre')) ?></strong><br><small><?= e(ajuste('empresa_razon_social')) ?> · CUIT <?= e(ajuste('empresa_cuit')) ?><br><?= e(ajuste('empresa_domicilio')) ?> · <?= e(ajuste('empresa_telefono')) ?></small></div>
        <div class="text-end"><strong class="fs-5">ORDEN DE COMPRA</strong><br>N° <?= (int) $oc['numero'] ?><br>Fecha: <?= fecha($oc['fecha']) ?></div>
    </div>
    <p class="small mb-2"><strong>Proveedor:</strong> <?= e($oc['razon_social']) ?> · CUIT <?= e($oc['cuit']) ?> · <?= e($oc['direccion']) ?> · <?= e($oc['telefono']) ?><br>
        <strong>Entregar en:</strong> <?= e($oc['deposito']) ?> — <?= e(ajuste('empresa_domicilio')) ?><?= $oc['fecha_entrega'] ? ' · <strong>Fecha:</strong> ' . fecha($oc['fecha_entrega']) : '' ?></p>
    <table class="comp-items"><thead><tr><th>Código</th><th>Producto</th><th class="n">Cantidad</th><th>Presentación</th><th class="n">Costo unit.</th><th class="n">Subtotal</th></tr></thead><tbody>
    <?php foreach ($items as $it): ?><tr><td><?= e($it['codigo']) ?></td><td><?= e($it['descripcion']) ?></td><td class="n"><?= cant($it['cantidad']) ?></td><td><?= e($it['presentacion_nombre']) ?> x<?= cant($it['factor']) ?></td><td class="n"><?= money($it['costo_unitario']) ?></td><td class="n"><?= money((float) $it['costo_unitario'] * (float) $it['cantidad']) ?></td></tr><?php endforeach; ?>
    </tbody></table>
    <table class="comp-totales"><tr class="total"><td>Total neto</td><td class="text-end"><?= money($oc['total']) ?></td></tr></table>
    <?php if ($oc['observaciones']): ?><p class="small mt-2"><strong>Observaciones:</strong> <?= e($oc['observaciones']) ?></p><?php endif; ?>
</div>
