<?php use App\Support\Html; ?>
<div class="encabezado"><h1>Saldos y deudas de clientes</h1><div class="acciones"><?= Html::exportar('/clientes/saldos') ?></div></div>
<form class="filtros row g-2 align-items-end" method="get">
    <div class="col-6 col-md-3"><label class="form-label" for="zona_id">Zona</label><select class="form-select" id="zona_id" name="zona_id" data-autoenviar><?= opciones($zonas, $_GET['zona_id'] ?? '', 'Todas') ?></select></div>
</form>
<div class="row g-3 mb-3">
    <?php foreach (['saldo' => 'Saldo total', 'a_vencer' => 'A vencer', 'd30' => 'Vencido 1-30', 'd60' => 'Vencido 31-60', 'mas60' => 'Vencido +60'] as $k => $t): ?>
        <div class="col-6 col-md"><div class="card kpi shadow-sm"><div class="card-body"><div class="kpi-etiqueta"><?= $t ?></div><div class="kpi-valor fs-5 <?= in_array($k, ['d30', 'd60', 'mas60'], true) && $tot[$k] > 0 ? 'text-danger' : '' ?>"><?= money($tot[$k]) ?></div></div></div></div>
    <?php endforeach; ?>
</div>
<div class="card shadow-sm"><div class="card-body p-0 p-md-2">
<table class="table align-middle tabla-cards mb-0">
    <thead><tr><?= $pag->th('nombre', 'Cliente') ?><th>Zona</th><?= $pag->th('saldo', 'Saldo', 'num') ?><th class="num">A vencer</th><th class="num">1-30</th><th class="num">31-60</th><th class="num">+60</th><th></th></tr></thead>
    <tbody><?php foreach ($pag->items as $f): ?>
        <tr><td class="td-titulo"><a href="<?= url('/clientes/' . $f['id'] . '/cuenta') ?>"><?= e($f['nombre_comercio']) ?></a> <?= $f['estado'] === 'bloqueado' ? estado_badge('bloqueado') : '' ?></td><td data-label="Zona"><?= e($f['zona']) ?></td>
            <td class="num fw-bold <?= $f['saldo'] > 0 ? 'saldo-deuda' : 'saldo-favor' ?>" data-label="Saldo"><?= money($f['saldo']) ?></td>
            <td class="num" data-label="A vencer"><?= money($f['a_vencer']) ?></td><td class="num <?= $f['d30'] > 0 ? 'text-danger' : '' ?>" data-label="1-30"><?= money($f['d30']) ?></td>
            <td class="num <?= $f['d60'] > 0 ? 'text-danger' : '' ?>" data-label="31-60"><?= money($f['d60']) ?></td><td class="num <?= $f['mas60'] > 0 ? 'text-danger fw-bold' : '' ?>" data-label="+60"><?= money($f['mas60']) ?></td>
            <td class="td-acciones text-end"><?php if (puede('cobranzas.crear')): ?><a class="btn btn-sm btn-outline-success btn-touch" href="<?= url('/cobranzas/nueva?cliente_id=' . $f['id']) ?>"><i class="bi bi-cash-coin"></i> Cobrar</a><?php endif; ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$pag->items): ?><tr><td colspan="8" class="text-center text-body-secondary py-4">No hay saldos pendientes.</td></tr><?php endif; ?></tbody>
</table>
</div></div>
<?= $pag->enlaces() ?>
