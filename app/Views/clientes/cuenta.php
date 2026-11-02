<?php use App\Support\Html; ?>
<div class="encabezado"><h1>Cuenta corriente — <?= e($c['nombre_comercio']) ?></h1>
    <div class="acciones"><a href="<?= url('/clientes/' . $c['id']) ?>" class="btn btn-outline-secondary btn-touch"><i class="bi bi-arrow-left"></i> Ficha</a><?= Html::exportar('/clientes/' . $c['id'] . '/cuenta') ?>
        <?php if (puede('cobranzas.crear')): ?><a href="<?= url('/cobranzas/nueva?cliente_id=' . $c['id']) ?>" class="btn btn-success btn-touch"><i class="bi bi-cash-coin"></i> Cobrar</a><?php endif; ?></div></div>
<form class="filtros row g-2 align-items-end" method="get">
    <div class="col-6 col-md-3"><label class="form-label" for="desde">Desde</label><input type="date" class="form-control" id="desde" name="desde" value="<?= e($desde) ?>"></div>
    <div class="col-6 col-md-3"><label class="form-label" for="hasta">Hasta</label><input type="date" class="form-control" id="hasta" name="hasta" value="<?= e($hasta) ?>"></div>
    <div class="col-12 col-md-2"><button class="btn btn-outline-primary w-100 btn-touch">Ver</button></div>
</form>
<div class="row g-3">
    <div class="col-lg-8"><div class="card shadow-sm"><div class="card-body p-0 p-md-2">
        <table class="table table-sm align-middle tabla-cards mb-0">
            <thead><tr><th>Fecha</th><th>Concepto</th><th>Vence</th><th class="num">Debe</th><th class="num">Haber</th><th class="num">Saldo</th></tr></thead>
            <tbody>
                <tr class="fw-semibold"><td colspan="5" class="td-titulo">Saldo anterior al <?= fecha($desde) ?></td><td class="num" data-label="Saldo"><?= money($mov['anterior']) ?></td></tr>
                <?php foreach ($mov['filas'] as $f):
                    $link = match ($f['documento_tipo']) { 'comprobante' => '/ventas/' . $f['documento_id'], 'recibo' => '/cobranzas/' . $f['documento_id'], default => null }; ?>
                    <tr class="<?= $f['tipo'] === 'anulacion' ? 'text-body-secondary' : '' ?>">
                        <td data-label="Fecha"><?= fecha($f['fecha']) ?></td>
                        <td class="td-titulo"><?= $link ? '<a href="' . url($link) . '">' . e($f['descripcion']) . '</a>' : e($f['descripcion']) ?><?= (float) $f['importe'] > 0 && (float) $f['pendiente'] > 0 ? ' <span class="badge text-bg-warning">Pend. ' . money($f['pendiente']) . '</span>' : '' ?></td>
                        <td data-label="Vence"><?= (float) $f['importe'] > 0 ? fecha($f['vencimiento']) : '' ?></td>
                        <td class="num" data-label="Debe"><?= (float) $f['importe'] > 0 ? money($f['importe']) : '' ?></td>
                        <td class="num" data-label="Haber"><?= (float) $f['importe'] < 0 ? money(-$f['importe']) : '' ?></td>
                        <td class="num fw-semibold" data-label="Saldo"><?= money($f['saldo']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot><tr class="fw-bold fs-6"><td colspan="5" class="text-end">Saldo al <?= fecha($hasta) ?></td><td class="num"><?= money($mov['saldo']) ?></td></tr></tfoot>
        </table>
    </div></div></div>
    <div class="col-lg-4">
        <div class="card shadow-sm mb-3"><div class="card-body">
            <div class="card-titulo">Antigüedad de la deuda</div>
            <?php foreach (['a_vencer' => 'A vencer', 'd30' => '1 a 30 días', 'd60' => '31 a 60 días', 'd90' => '61 a 90 días', 'mas90' => 'Más de 90 días'] as $k => $t): ?>
                <div class="d-flex justify-content-between"><span><?= $t ?></span><strong class="<?= $k !== 'a_vencer' && ($antiguedad[$k] ?? 0) > 0 ? 'text-danger' : '' ?>"><?= money($antiguedad[$k] ?? 0) ?></strong></div>
            <?php endforeach; ?>
            <?php if ($credito > 0): ?><div class="d-flex justify-content-between mt-2 saldo-favor"><span>Crédito a favor sin aplicar</span><strong><?= money($credito) ?></strong></div><?php endif; ?>
        </div></div>
        <?php if (puede('clientes.editar')): ?>
        <div class="card shadow-sm"><div class="card-body">
            <div class="card-titulo">Saldo inicial / ajuste manual</div>
            <form method="post" action="<?= url('/clientes/' . $c['id'] . '/ajuste-cuenta') ?>" class="row g-2" data-confirmar="¿Registrar el ajuste en la cuenta corriente? Queda en auditoría.">
                <?= csrf_campo() ?>
                <div class="col-6"><select class="form-select" name="tipo"><option value="ajuste">Ajuste</option><option value="saldo_inicial">Saldo inicial</option></select></div>
                <div class="col-6"><select class="form-select" name="sentido"><option value="debe">Debe (aumenta deuda)</option><option value="haber">Haber (disminuye)</option></select></div>
                <div class="col-6"><input class="form-control" name="importe" inputmode="decimal" placeholder="Importe" required></div>
                <div class="col-6"><input type="date" class="form-control" name="vencimiento" aria-label="Vencimiento"></div>
                <div class="col-12"><input class="form-control" name="descripcion" placeholder="Descripción" required maxlength="150"></div>
                <div class="col-12"><button class="btn btn-outline-primary btn-touch w-100">Registrar</button></div>
            </form>
        </div></div>
        <?php endif; ?>
    </div>
</div>
