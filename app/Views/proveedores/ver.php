<?php use App\Support\Html; ?>
<div class="encabezado"><h1><?= e($pr['razon_social']) ?> <?= (int) $pr['activo'] ? '' : estado_badge('inactivo') ?></h1>
    <div class="acciones">
        <?php if (puede('compras.crear')): ?>
            <a href="<?= url('/compras/nueva?proveedor_id=' . $pr['id']) ?>" class="btn btn-outline-primary btn-touch"><i class="bi bi-bag-plus"></i> Orden de compra</a>
            <a href="<?= url('/compras/facturas/nueva?proveedor_id=' . $pr['id']) ?>" class="btn btn-outline-primary btn-touch"><i class="bi bi-receipt"></i> Cargar factura</a>
            <a href="<?= url('/compras/pagos/nuevo?proveedor_id=' . $pr['id']) ?>" class="btn btn-success btn-touch"><i class="bi bi-cash"></i> Pagar</a>
        <?php endif; ?>
        <?php if (puede('proveedores.editar')): ?><a href="<?= url('/proveedores/' . $pr['id'] . '/editar') ?>" class="btn btn-outline-secondary btn-touch"><i class="bi bi-pencil"></i></a><?php endif; ?>
        <?php if (puede('proveedores.anular')): ?><?= Html::botonPost('/proveedores/' . $pr['id'] . '/estado', (int) $pr['activo'] ? 'Desactivar' : 'Activar', 'btn-outline-secondary', (int) $pr['activo'] ? '¿Desactivar el proveedor?' : null) ?><?php endif; ?>
    </div></div>
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card shadow-sm mb-3"><div class="card-body text-center"><div class="kpi-etiqueta">Saldo a pagar</div><div class="kpi-valor <?= $saldo > 0 ? 'saldo-deuda' : '' ?>"><?= money($saldo) ?></div>
            <?php foreach (['a_vencer' => 'A vencer', 'd30' => 'Vencido 1-30', 'd60' => 'Vencido 31-60', 'd90' => 'Vencido 61-90', 'mas90' => 'Vencido +90'] as $k => $t): if (($antiguedad[$k] ?? 0) <= 0) continue; ?>
                <div class="small d-flex justify-content-between"><span><?= $t ?></span><strong><?= money($antiguedad[$k]) ?></strong></div><?php endforeach; ?></div></div>
        <div class="card shadow-sm mb-3"><div class="card-body ficha-dato row g-2">
            <div class="col-12"><small>Nombre de fantasía</small><?= e($pr['nombre_fantasia']) ?></div>
            <div class="col-6"><small>CUIT</small><?= e($pr['cuit']) ?></div><div class="col-6"><small>IVA</small><?= e(condicion_iva($pr['condicion_iva'])) ?></div>
            <div class="col-12"><small>Dirección</small><?= e($pr['direccion']) ?>, <?= e($pr['localidad']) ?></div>
            <div class="col-6"><small>Contacto</small><?= e($pr['contacto']) ?></div><div class="col-6"><small>Teléfono</small><?= e($pr['telefono']) ?></div>
            <div class="col-12"><small>Correo</small><?= e($pr['email']) ?></div>
            <div class="col-6"><small>Plazo de pago</small><?= (int) $pr['plazo_pago_dias'] ?> días</div><div class="col-6"><small>Productos</small><a href="<?= url('/productos?proveedor_id=' . $pr['id']) ?>"><?= $productos ?></a></div>
        </div></div>
        <div class="card shadow-sm"><div class="card-body"><div class="card-titulo">Últimas órdenes de compra</div>
            <?php foreach ($ordenes as $o): ?><div class="d-flex justify-content-between border-bottom py-1"><a href="<?= url('/compras/' . $o['id']) ?>">OC N° <?= (int) $o['numero'] ?> · <?= fecha($o['fecha']) ?></a><span><?= money($o['total']) ?> <?= estado_badge($o['estado']) ?></span></div><?php endforeach; ?>
            <?php if (!$ordenes): ?><p class="text-body-secondary mb-0">Sin órdenes.</p><?php endif; ?></div></div>
    </div>
    <div class="col-lg-8"><div class="card shadow-sm"><div class="card-body">
        <div class="card-titulo">Cuenta corriente</div>
        <form class="row g-2 mb-2" method="get"><div class="col-5"><input type="date" class="form-control" name="desde" value="<?= e($desde) ?>" aria-label="Desde"></div><div class="col-5"><input type="date" class="form-control" name="hasta" value="<?= e($hasta) ?>" aria-label="Hasta"></div><div class="col-2"><button class="btn btn-outline-primary w-100" aria-label="Ver"><i class="bi bi-search"></i></button></div></form>
        <table class="table table-sm tabla-cards mb-0">
            <thead><tr><th>Fecha</th><th>Concepto</th><th>Vence</th><th class="num">Debe</th><th class="num">Haber</th><th class="num">Saldo</th></tr></thead>
            <tbody><tr class="fw-semibold"><td colspan="5" class="td-titulo">Saldo anterior</td><td class="num" data-label="Saldo"><?= money($mov['anterior']) ?></td></tr>
            <?php foreach ($mov['filas'] as $f): ?>
                <tr><td data-label="Fecha"><?= fecha($f['fecha']) ?></td><td class="td-titulo"><?= e($f['descripcion']) ?><?= (float) $f['importe'] > 0 && (float) $f['pendiente'] > 0 ? ' <span class="badge text-bg-warning">Pend. ' . money($f['pendiente']) . '</span>' : '' ?></td>
                    <td data-label="Vence"><?= (float) $f['importe'] > 0 ? fecha($f['vencimiento']) : '' ?></td><td class="num" data-label="Debe"><?= (float) $f['importe'] > 0 ? money($f['importe']) : '' ?></td>
                    <td class="num" data-label="Haber"><?= (float) $f['importe'] < 0 ? money(-$f['importe']) : '' ?></td><td class="num fw-semibold" data-label="Saldo"><?= money($f['saldo']) ?></td></tr>
            <?php endforeach; ?></tbody>
        </table>
    </div></div></div>
</div>
