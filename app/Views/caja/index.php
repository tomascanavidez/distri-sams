<?php use App\Support\Html; ?>
<div class="encabezado"><h1>Caja</h1>
    <div class="acciones"><?php if (puede('configuracion.editar|caja.crear')): ?><a href="<?= url('/catalogo/cajas') ?>" class="btn btn-outline-secondary btn-touch"><i class="bi bi-gear"></i> Cajas</a><?php endif; ?></div>
</div>
<?php if ($pendRendir > 0): ?><div class="alert alert-info"><i class="bi bi-truck"></i> Hay <strong><?= money($pendRendir) ?></strong> en efectivo cobrado por los choferes pendiente de rendición. Ingresa a la caja al hacer la <a href="<?= url('/reparto?estado=en_reparto') ?>">rendición del reparto</a>.</div><?php endif; ?>
<div class="row g-3 mb-4">
<?php foreach ($cajas as $c): $s = $c['sesion']; ?>
    <div class="col-md-6 col-xl-4"><div class="card shadow-sm h-100 <?= $s ? 'border-success' : '' ?>"><div class="card-body">
        <div class="d-flex justify-content-between align-items-start">
            <div><div class="card-titulo mb-1"><?= e($c['nombre']) ?></div><?= estado_badge($s ? 'abierta' : 'cerrada') ?></div>
            <?php if ($s): ?><div class="text-end"><small class="text-body-secondary">Saldo teórico</small><div class="kpi-valor"><?= money($s['saldo']) ?></div></div><?php endif; ?>
        </div>
        <?php if ($s): ?>
            <p class="small text-body-secondary mt-2 mb-2">Abierta el <?= fecha_hora($s['fecha_apertura']) ?> por <?= e($s['usuario']) ?> con <?= money($s['saldo_inicial']) ?></p>
            <?php if (puede('caja.crear')): ?>
            <form method="post" action="<?= url('/caja/movimiento') ?>" class="row g-2">
                <?= csrf_campo() ?><input type="hidden" name="sesion_id" value="<?= (int) $s['id'] ?>">
                <div class="col-5"><select class="form-select" name="tipo"><option value="ingreso">Ingreso</option><option value="egreso">Egreso</option></select></div>
                <div class="col-7"><input class="form-control" name="importe" inputmode="decimal" placeholder="Importe" required></div>
                <div class="col-12"><input class="form-control" name="concepto" placeholder="Concepto (ej.: cambio, retiro, depósito)" required maxlength="150"></div>
                <div class="col-12 d-flex gap-2"><button class="btn btn-outline-primary btn-touch flex-fill"><i class="bi bi-plus"></i> Movimiento</button>
                    <a href="<?= url('/caja/sesion/' . $s['id']) ?>" class="btn btn-primary btn-touch flex-fill"><i class="bi bi-calculator"></i> Arqueo y cierre</a></div>
            </form>
            <?php endif; ?>
        <?php elseif (puede('caja.crear')): ?>
            <form method="post" action="<?= url('/caja/abrir') ?>" class="row g-2 mt-2">
                <?= csrf_campo() ?><input type="hidden" name="caja_id" value="<?= (int) $c['id'] ?>">
                <div class="col-7"><input class="form-control" name="saldo_inicial" inputmode="decimal" placeholder="Saldo inicial" required></div>
                <div class="col-5"><button class="btn btn-success btn-touch w-100"><i class="bi bi-unlock"></i> Abrir</button></div>
            </form>
        <?php endif; ?>
    </div></div></div>
<?php endforeach; ?>
</div>
<h2 class="h5">Sesiones</h2>
<div class="card shadow-sm"><div class="card-body p-0 p-md-2">
<table class="table align-middle tabla-cards mb-0">
    <thead><tr><th>Caja</th><th>Apertura</th><th>Cierre</th><th class="num">Inicial</th><th class="num">Teórico</th><th class="num">Contado</th><th class="num">Diferencia</th><th>Estado</th><th></th></tr></thead>
    <tbody><?php foreach ($pag->items as $s): ?>
        <tr><td class="td-titulo"><?= e($s['caja']) ?></td><td data-label="Apertura"><?= fecha_hora($s['fecha_apertura']) ?> <small class="text-body-secondary"><?= e($s['abrio']) ?></small></td>
            <td data-label="Cierre"><?= fecha_hora($s['fecha_cierre']) ?> <small class="text-body-secondary"><?= e($s['cerro']) ?></small></td>
            <td class="num" data-label="Inicial"><?= money($s['saldo_inicial']) ?></td><td class="num" data-label="Teórico"><?= $s['saldo_teorico'] !== null ? money($s['saldo_teorico']) : '' ?></td>
            <td class="num" data-label="Contado"><?= $s['saldo_contado'] !== null ? money($s['saldo_contado']) : '' ?></td>
            <td class="num <?= (float) $s['diferencia'] < 0 ? 'text-danger fw-bold' : ((float) $s['diferencia'] > 0 ? 'text-success fw-bold' : '') ?>" data-label="Diferencia"><?= $s['diferencia'] !== null ? money($s['diferencia']) : '' ?></td>
            <td data-label="Estado"><?= estado_badge($s['estado']) ?></td>
            <td class="td-acciones text-end"><a href="<?= url('/caja/sesion/' . $s['id']) ?>" class="btn btn-sm btn-outline-primary btn-touch"><i class="bi bi-eye"></i></a></td></tr>
    <?php endforeach; ?></tbody>
</table>
</div></div>
<?= $pag->enlaces() ?>
