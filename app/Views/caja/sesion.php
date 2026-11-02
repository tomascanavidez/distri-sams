<div class="encabezado"><h1><?= e($s['caja']) ?> — sesión del <?= fecha($s['fecha_apertura']) ?> <?= estado_badge($s['estado']) ?></h1>
    <div class="acciones"><a href="<?= url('/caja') ?>" class="btn btn-outline-secondary btn-touch"><i class="bi bi-arrow-left"></i> Caja</a><button class="btn btn-outline-secondary btn-touch" data-imprimir><i class="bi bi-printer"></i> Imprimir</button></div>
</div>
<div class="row g-3">
    <div class="col-lg-8">
        <div class="card shadow-sm"><div class="card-body p-0 p-md-2">
        <table class="table table-sm align-middle tabla-cards mb-0">
            <thead><tr><th>Hora</th><th>Concepto</th><th>Usuario</th><th class="num">Ingreso</th><th class="num">Egreso</th><th class="num">Saldo</th></tr></thead>
            <tbody>
                <tr><td data-label="Hora"><?= date('H:i', strtotime($s['fecha_apertura'])) ?></td><td class="td-titulo">Saldo inicial</td><td></td><td class="num" data-label="Ingreso"><?= money($s['saldo_inicial']) ?></td><td></td><td class="num" data-label="Saldo"><?= money($s['saldo_inicial']) ?></td></tr>
            <?php foreach ($movs as $m): ?>
                <tr><td data-label="Hora"><?= date('d/m H:i', strtotime($m['fecha'])) ?></td><td class="td-titulo"><?= e($m['concepto']) ?></td><td data-label="Usuario"><small><?= e($m['usuario']) ?></small></td>
                    <td class="num text-success" data-label="Ingreso"><?= $m['tipo'] === 'ingreso' ? money($m['importe']) : '' ?></td><td class="num text-danger" data-label="Egreso"><?= $m['tipo'] === 'egreso' ? money($m['importe']) : '' ?></td><td class="num" data-label="Saldo"><?= money($m['saldo']) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot><tr class="fw-bold"><td colspan="5" class="text-end">Saldo teórico</td><td class="num"><?= money($saldo) ?></td></tr></tfoot>
        </table>
        </div></div>
    </div>
    <div class="col-lg-4">
        <?php if ($s['estado'] === 'abierta' && puede('caja.crear')): ?>
        <div class="card shadow-sm" x-data="{ b: {}, monedas: '', total() { let t = DT.parse(this.monedas); <?= json_encode($billetes) ?>.forEach(v => t += (parseInt(this.b[v]) || 0) * v); return t; } }"><div class="card-body">
            <div class="card-titulo"><i class="bi bi-calculator"></i> Arqueo y cierre</div>
            <form method="post" action="<?= url('/caja/sesion/' . $s['id'] . '/cerrar') ?>" data-confirmar="¿Cerrar la caja con el arqueo indicado?">
                <?= csrf_campo() ?>
                <div class="row g-2">
                    <?php foreach ($billetes as $b): ?>
                        <div class="col-6"><div class="input-group"><span class="input-group-text" style="min-width:84px">$ <?= number_format($b, 0, ',', '.') ?></span><input class="form-control text-end" name="billete[<?= $b ?>]" inputmode="numeric" x-model="b[<?= $b ?>]" placeholder="0" aria-label="Cantidad de billetes de <?= $b ?>"></div></div>
                    <?php endforeach; ?>
                    <div class="col-12"><div class="input-group"><span class="input-group-text">Monedas $</span><input class="form-control text-end" name="monedas" inputmode="decimal" x-model="monedas" placeholder="0"></div></div>
                    <div class="col-12"><input class="form-control" name="observaciones" placeholder="Observaciones del cierre" maxlength="200"></div>
                </div>
                <div class="d-flex justify-content-between mt-3"><span>Contado</span><strong x-text="DT.dinero(total())"></strong></div>
                <div class="d-flex justify-content-between"><span>Teórico</span><strong><?= money($saldo) ?></strong></div>
                <div class="d-flex justify-content-between fs-5" :class="Math.abs(total() - <?= $saldo ?>) < 0.01 ? 'text-success' : 'text-danger'"><span>Diferencia</span><strong x-text="DT.dinero(total() - <?= $saldo ?>)"></strong></div>
                <button class="btn btn-primary btn-touch w-100 mt-3"><i class="bi bi-lock"></i> Cerrar caja</button>
            </form>
        </div></div>
        <?php else: ?>
        <div class="card shadow-sm"><div class="card-body ficha-dato row g-2">
            <div class="col-6"><small>Abrió</small><?= e($s['abrio']) ?></div><div class="col-6"><small>Cerró</small><?= e($s['cerro']) ?> <?= fecha_hora($s['fecha_cierre']) ?></div>
            <div class="col-6"><small>Teórico</small><?= money($s['saldo_teorico']) ?></div><div class="col-6"><small>Contado</small><?= money($s['saldo_contado']) ?></div>
            <div class="col-12"><small>Diferencia</small><strong class="<?= (float) $s['diferencia'] < 0 ? 'text-danger' : '' ?>"><?= money($s['diferencia']) ?></strong></div>
            <?php if ($s['arqueo']): ?><div class="col-12"><small>Arqueo</small><?php foreach ((array) json_decode($s['arqueo'], true) as $k => $v) echo e($k === 'monedas' ? 'Monedas: ' . money($v) : $v . ' x $' . number_format((int) $k, 0, ',', '.')) . '<br>'; ?></div><?php endif; ?>
            <?php if ($s['observaciones']): ?><div class="col-12"><small>Observaciones</small><?= e($s['observaciones']) ?></div><?php endif; ?>
        </div></div>
        <?php endif; ?>
    </div>
</div>
