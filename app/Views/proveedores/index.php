<div class="encabezado"><h1>Proveedores</h1><div class="acciones"><?php if (puede('proveedores.crear')): ?><a href="<?= url('/proveedores/nuevo') ?>" class="btn btn-primary btn-touch"><i class="bi bi-plus-lg"></i> Nuevo proveedor</a><?php endif; ?></div></div>
<form class="filtros row g-2 align-items-end" method="get">
    <div class="col-12 col-md-6"><label class="form-label" for="q">Buscar</label><input type="search" class="form-control" id="q" name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="Razón social, fantasía o CUIT"></div>
    <div class="col-6 col-md-3"><div class="form-check"><input class="form-check-input" type="checkbox" id="inactivos" name="inactivos" value="1" <?= !empty($_GET['inactivos']) ? 'checked' : '' ?> data-autoenviar><label class="form-check-label" for="inactivos">Ver inactivos</label></div></div>
    <div class="col-6 col-md-3"><button class="btn btn-outline-primary w-100 btn-touch">Buscar</button></div>
</form>
<div class="card shadow-sm"><div class="card-body p-0 p-md-2">
<table class="table table-hover align-middle tabla-cards mb-0">
    <thead><tr><?= $pag->th('razon', 'Razón social') ?><th>CUIT</th><th>Contacto</th><th>Teléfono</th><th class="num">Plazo</th><?= $pag->th('saldo', 'Saldo', 'num') ?><th class="num">Vencido</th><th></th></tr></thead>
    <tbody><?php foreach ($pag->items as $p): ?>
        <tr><td class="td-titulo"><a href="<?= url('/proveedores/' . $p['id']) ?>"><?= e($p['razon_social']) ?></a><div class="small text-body-secondary fw-normal"><?= e($p['nombre_fantasia']) ?></div></td>
            <td data-label="CUIT"><?= e($p['cuit']) ?></td><td data-label="Contacto"><?= e($p['contacto']) ?></td><td data-label="Teléfono"><?= e($p['telefono']) ?></td>
            <td class="num" data-label="Plazo"><?= (int) $p['plazo_pago_dias'] ?> días</td>
            <td class="num fw-semibold <?= $p['saldo'] > 0 ? 'saldo-deuda' : '' ?>" data-label="Saldo"><?= money($p['saldo']) ?></td>
            <td class="num <?= $p['vencido'] > 0 ? 'text-danger fw-bold' : '' ?>" data-label="Vencido"><?= money($p['vencido']) ?></td>
            <td class="td-acciones text-end text-nowrap">
                <?php if (puede('compras.crear')): ?><a class="btn btn-sm btn-outline-success btn-touch" href="<?= url('/compras/pagos/nuevo?proveedor_id=' . $p['id']) ?>"><i class="bi bi-cash"></i> Pagar</a><?php endif; ?>
                <a class="btn btn-sm btn-outline-primary btn-touch" href="<?= url('/proveedores/' . $p['id']) ?>"><i class="bi bi-eye"></i></a></td></tr>
    <?php endforeach; ?>
    <?php if (!$pag->items): ?><tr><td colspan="8" class="text-center text-body-secondary py-4">No hay proveedores.</td></tr><?php endif; ?></tbody>
</table>
</div></div>
<?= $pag->enlaces() ?>
