<div class="encabezado"><h1>Bancos y billeteras</h1>
    <div class="acciones"><?php if (puede('finanzas.crear')): ?><a href="<?= url('/catalogo/cuentas_bancarias') ?>" class="btn btn-outline-secondary btn-touch"><i class="bi bi-gear"></i> Administrar cuentas</a><?php endif; ?></div></div>
<div class="row g-3 mb-3">
<?php foreach ($cuentas as $c): ?>
    <div class="col-md-4"><a href="<?= url('/finanzas/bancos/' . $c['id']) ?>" class="card kpi shadow-sm text-decoration-none text-reset"><div class="card-body">
        <div class="kpi-etiqueta"><?= e(['banco' => 'Banco', 'billetera' => 'Billetera', 'tarjeta' => 'Tarjetas'][$c['tipo']] ?? '') ?></div>
        <div class="fw-bold"><?= e($c['nombre']) ?></div><div class="kpi-valor <?= $c['saldo'] < 0 ? 'text-danger' : '' ?>"><?= money($c['saldo']) ?></div>
        <small class="text-body-secondary"><?= e($c['cbu_alias'] ?? '') ?></small></div></a></div>
<?php endforeach; ?>
</div>
<?php if (puede('finanzas.crear')): ?>
<div class="card shadow-sm" style="max-width:820px" x-data="{ tipo: 'ingreso' }"><div class="card-body">
    <div class="card-titulo">Registrar movimiento manual</div>
    <form method="post" action="<?= url('/finanzas/bancos/movimiento') ?>" class="row g-2">
        <?= csrf_campo() ?>
        <div class="col-md-3"><select class="form-select" name="tipo" x-model="tipo"><option value="ingreso">Ingreso</option><option value="egreso">Egreso</option><option value="transferencia">Transferencia entre cuentas</option></select></div>
        <div class="col-md-3"><select class="form-select" name="cuenta_id"><?= opciones(array_column($cuentas, 'nombre', 'id'), '') ?></select></div>
        <div class="col-md-3" x-show="tipo === 'transferencia'"><select class="form-select" name="cuenta_destino_id"><?= opciones(array_column($cuentas, 'nombre', 'id'), '', 'Cuenta destino') ?></select></div>
        <div class="col-md-3"><input class="form-control" name="importe" inputmode="decimal" placeholder="Importe" required></div>
        <div class="col-md-6"><input class="form-control" name="concepto" placeholder="Concepto (ej.: comisión bancaria, acreditación de tarjetas)" required maxlength="150"></div>
        <div class="col-md-3"><input class="form-control" name="referencia" placeholder="Referencia"></div>
        <div class="col-md-3"><button class="btn btn-primary btn-touch w-100"><i class="bi bi-check-lg"></i> Registrar</button></div>
    </form>
</div></div>
<?php endif; ?>
