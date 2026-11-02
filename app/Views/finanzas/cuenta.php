<div class="encabezado"><h1><?= e($c['nombre']) ?> <small class="fs-5 text-body-secondary">Saldo <?= money($saldo) ?></small></h1>
    <div class="acciones"><a href="<?= url('/finanzas/bancos') ?>" class="btn btn-outline-secondary btn-touch"><i class="bi bi-arrow-left"></i> Cuentas</a></div></div>
<form class="filtros row g-2 align-items-end" method="get">
    <div class="col-6 col-md-3"><label class="form-label" for="desde">Desde</label><input type="date" class="form-control" id="desde" name="desde" value="<?= e($_GET['desde'] ?? '') ?>"></div>
    <div class="col-6 col-md-3"><label class="form-label" for="hasta">Hasta</label><input type="date" class="form-control" id="hasta" name="hasta" value="<?= e($_GET['hasta'] ?? '') ?>"></div>
    <div class="col-12 col-md-3"><button class="btn btn-outline-primary w-100 btn-touch">Filtrar</button></div>
</form>
<div class="card shadow-sm"><div class="card-body p-0 p-md-2">
<table class="table table-sm align-middle tabla-cards mb-0">
    <thead><tr><th>Fecha</th><th>Concepto</th><th>Referencia</th><th class="num">Ingreso</th><th class="num">Egreso</th><th>Usuario</th></tr></thead>
    <tbody><?php foreach ($pag->items as $m): ?>
        <tr><td data-label="Fecha"><?= fecha_hora($m['fecha']) ?></td><td class="td-titulo"><?= e($m['concepto']) ?></td><td data-label="Referencia"><?= e($m['referencia']) ?></td>
            <td class="num text-success" data-label="Ingreso"><?= $m['tipo'] === 'ingreso' ? money($m['importe']) : '' ?></td><td class="num text-danger" data-label="Egreso"><?= $m['tipo'] === 'egreso' ? money($m['importe']) : '' ?></td><td data-label="Usuario"><small><?= e($m['usuario']) ?></small></td></tr>
    <?php endforeach; ?>
    <?php if (!$pag->items): ?><tr><td colspan="6" class="text-center text-body-secondary py-4">Sin movimientos.</td></tr><?php endif; ?></tbody>
</table>
</div></div>
<?= $pag->enlaces() ?>
