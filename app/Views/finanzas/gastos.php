<?php use App\Support\Html; ?>
<div class="encabezado"><h1>Gastos</h1></div>
<div class="row g-3">
<div class="col-lg-8">
    <form class="filtros row g-2 align-items-end" method="get">
        <div class="col-6 col-md-3"><label class="form-label" for="desde">Desde</label><input type="date" class="form-control" id="desde" name="desde" value="<?= e($desde) ?>"></div>
        <div class="col-6 col-md-3"><label class="form-label" for="hasta">Hasta</label><input type="date" class="form-control" id="hasta" name="hasta" value="<?= e($hasta) ?>"></div>
        <div class="col-6 col-md-4"><label class="form-label" for="categoria_id">Categoría</label><select class="form-select" id="categoria_id" name="categoria_id" data-autoenviar><?= opciones($categorias, $_GET['categoria_id'] ?? '', 'Todas') ?></select></div>
        <div class="col-6 col-md-2"><button class="btn btn-outline-primary w-100 btn-touch">Filtrar</button></div>
    </form>
    <div class="card shadow-sm"><div class="card-body p-0 p-md-2">
    <table class="table align-middle tabla-cards mb-0">
        <thead><tr><?= $pag->th('fecha', 'Fecha') ?><th>Categoría</th><th>Descripción</th><th>Pago</th><?= $pag->th('importe', 'Importe', 'num') ?><th>Estado</th><th></th></tr></thead>
        <tbody><?php foreach ($pag->items as $g): ?>
            <tr class="<?= $g['estado'] === 'anulado' ? 'opacity-75 text-decoration-line-through' : '' ?>"><td data-label="Fecha"><?= fecha($g['fecha']) ?></td><td data-label="Categoría"><?= e($g['categoria']) ?></td>
                <td class="td-titulo"><?= e($g['descripcion']) ?><?= $g['patente'] ? ' <small class="text-body-secondary">(' . e($g['patente']) . ')</small>' : '' ?><?= $g['comprobante'] ? '<br><small class="text-body-secondary fw-normal">Comp. ' . e($g['comprobante']) . '</small>' : '' ?></td>
                <td data-label="Pago"><?= $g['forma_pago'] === 'efectivo' ? 'Efectivo' : e($g['cuenta']) ?></td><td class="num fw-semibold" data-label="Importe"><?= money($g['importe']) ?></td>
                <td data-label="Estado"><?= estado_badge($g['estado']) ?></td>
                <td class="td-acciones text-end"><?php if ($g['estado'] === 'registrado' && puede('finanzas.anular')): ?><?= Html::botonPost('/finanzas/gastos/' . $g['id'] . '/anular', '<i class="bi bi-x-circle"></i>', 'btn-sm btn-outline-danger', '¿Anular el gasto? Se revierte el egreso de caja/banco.', true) ?><?php endif; ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$pag->items): ?><tr><td colspan="7" class="text-center text-body-secondary py-4">Sin gastos en el período.</td></tr><?php endif; ?></tbody>
    </table>
    </div></div>
    <?= $pag->enlaces() ?>
</div>
<div class="col-lg-4">
    <?php if (puede('finanzas.crear')): ?>
    <div class="card shadow-sm mb-3" x-data="{ forma: 'efectivo' }"><div class="card-body">
        <div class="card-titulo"><i class="bi bi-plus-circle"></i> Nuevo gasto</div>
        <form method="post" action="<?= url('/finanzas/gastos/nuevo') ?>" class="row g-2">
            <?= csrf_campo() ?>
            <div class="col-6"><label class="form-label small">Fecha</label><input type="date" class="form-control" name="fecha" value="<?= hoy() ?>"></div>
            <div class="col-6"><label class="form-label small">Importe</label><input class="form-control" name="importe" inputmode="decimal" required></div>
            <div class="col-12"><select class="form-select" name="categoria_id" required><?= opciones($categorias, '', 'Categoría') ?></select></div>
            <div class="col-12"><input class="form-control" name="descripcion" placeholder="Descripción" required maxlength="200"></div>
            <div class="col-6"><select class="form-select" name="forma_pago" x-model="forma"><option value="efectivo">Efectivo (caja)</option><option value="banco">Banco / billetera</option></select></div>
            <div class="col-6" x-show="forma === 'efectivo'"><select class="form-select" name="caja_id"><?= opciones($cajas, $cajaUsuario) ?></select></div>
            <div class="col-6" x-show="forma === 'banco'"><select class="form-select" name="cuenta_bancaria_id"><?= opciones($cuentas, '') ?></select></div>
            <div class="col-6"><input class="form-control" name="comprobante" placeholder="N° comprobante"></div>
            <div class="col-6"><select class="form-select" name="vehiculo_id"><?= opciones($vehiculos, '', 'Vehículo (opc.)') ?></select></div>
            <div class="col-12"><button class="btn btn-primary btn-touch w-100"><i class="bi bi-check-lg"></i> Registrar gasto</button></div>
        </form>
    </div></div>
    <?php endif; ?>
    <div class="card shadow-sm"><div class="card-body">
        <div class="card-titulo">Por categoría (<?= fecha($desde) ?> al <?= fecha($hasta) ?>)</div>
        <?php foreach ($porCategoria as $c): ?><div class="d-flex justify-content-between border-bottom py-1"><span><?= e($c['nombre']) ?></span><strong><?= money($c['total']) ?></strong></div><?php endforeach; ?>
        <div class="d-flex justify-content-between pt-2 fw-bold"><span>Total</span><span><?= money(array_sum(array_column($porCategoria, 'total'))) ?></span></div>
    </div></div>
</div>
</div>
