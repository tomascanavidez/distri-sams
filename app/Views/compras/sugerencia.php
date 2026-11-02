<div class="encabezado"><h1>Sugerencia de compra</h1></div>
<form class="filtros row g-2 align-items-end" method="get">
    <div class="col-12 col-md-4"><label class="form-label" for="proveedor_id">Proveedor</label><select class="form-select" id="proveedor_id" name="proveedor_id"><?= opciones($proveedores, $prov, 'Todos') ?></select></div>
    <div class="col-6 col-md-3"><label class="form-label" for="dias_venta">Ventas de los últimos (días)</label><input class="form-control" id="dias_venta" name="dias_venta" inputmode="numeric" value="<?= (int) $diasVenta ?>"></div>
    <div class="col-6 col-md-3"><label class="form-label" for="dias_cobertura">Cubrir (días)</label><input class="form-control" id="dias_cobertura" name="dias_cobertura" inputmode="numeric" value="<?= (int) $diasCob ?>"></div>
    <div class="col-12 col-md-2"><button class="btn btn-outline-primary w-100 btn-touch">Calcular</button></div>
</form>
<p class="small text-body-secondary">Se sugiere comprar para llegar al mayor entre el stock mínimo y la venta promedio × días de cobertura (sin superar el máximo), descontando el stock actual y lo pendiente de recibir. Las cantidades se redondean a bultos de compra.</p>
<?php if (!$porProveedor): ?><div class="alert alert-success">No hay productos para reponer con estos parámetros.</div><?php endif; ?>
<?php foreach ($porProveedor as $provId => $filas): ?>
<form method="get" action="<?= url('/compras/nueva') ?>" class="card shadow-sm mb-3"><div class="card-body">
    <input type="hidden" name="proveedor_id" value="<?= (int) $provId ?>"><input type="hidden" name="sugerencia" value="1"><input type="hidden" name="dias_venta" value="<?= (int) $diasVenta ?>">
    <div class="d-flex justify-content-between align-items-center mb-2"><div class="card-titulo mb-0"><?= e($proveedores[$provId] ?? 'Sin proveedor') ?></div>
        <?php if (isset($proveedores[$provId])): ?><button class="btn btn-primary btn-touch"><i class="bi bi-bag-plus"></i> Crear orden con lo marcado</button><?php endif; ?></div>
    <table class="table table-sm align-middle tabla-cards mb-0">
        <thead><tr><th></th><th>Producto</th><th class="num">Stock</th><th class="num">Mín.</th><th class="num">Venta/día</th><th class="num">Pendiente</th><th class="num">Sugerido</th><th class="num">Costo</th></tr></thead>
        <tbody><?php foreach ($filas as $f): ?>
            <tr><td data-label="Incluir"><input type="checkbox" class="form-check-input" name="productos[]" value="<?= (int) $f['id'] ?>" checked aria-label="Incluir"></td>
                <td class="td-titulo"><?= e($f['descripcion']) ?></td><td class="num <?= $f['stock'] < $f['stock_minimo'] ? 'text-danger fw-bold' : '' ?>" data-label="Stock"><?= cant($f['stock']) ?></td>
                <td class="num" data-label="Mínimo"><?= cant($f['stock_minimo']) ?></td><td class="num" data-label="Venta/día"><?= num($f['diario'], 1) ?></td><td class="num" data-label="Pendiente"><?= cant($f['pendiente']) ?></td>
                <td class="num fw-bold" data-label="Sugerido"><?= (int) $f['sugerido'] ?> <?= e($f['presentacion_nombre']) ?> <small class="text-body-secondary">(<?= cant($f['sugerido'] * $f['factor']) ?> u.)</small></td>
                <td class="num" data-label="Costo"><?= money($f['sugerido'] * $f['costo_presentacion']) ?></td></tr>
        <?php endforeach; ?></tbody>
    </table>
</div></form>
<?php endforeach; ?>
