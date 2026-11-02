<?php use App\Services\FinanzasService; ?>
<div class="encabezado"><h1>Cheques de terceros</h1><div class="acciones"><span class="badge text-bg-primary fs-6">En cartera: <?= money($totalCartera) ?></span></div></div>
<form class="filtros row g-2 align-items-end" method="get">
    <div class="col-6 col-md-3"><label class="form-label" for="estado">Estado</label><select class="form-select" id="estado" name="estado" data-autoenviar><?= opciones(FinanzasService::ESTADOS_CHEQUE + ['todos' => 'Todos'], $_GET['estado'] ?? 'cartera') ?></select></div>
    <div class="col-6 col-md-5"><label class="form-label" for="q">Buscar</label><input type="search" class="form-control" id="q" name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="Número, librador o cliente"></div>
    <div class="col-12 col-md-2"><button class="btn btn-outline-primary w-100 btn-touch">Buscar</button></div>
</form>
<div class="card shadow-sm"><div class="card-body p-0 p-md-2">
<table class="table align-middle tabla-cards mb-0">
    <thead><tr><th>Número</th><th>Banco</th><th>Librador / cliente</th><?= $pag->th('fecha_pago', 'Fecha de pago') ?><?= $pag->th('importe', 'Importe', 'num') ?><th>Estado</th><th>Destino</th><th class="text-end">Acciones</th></tr></thead>
    <tbody><?php foreach ($pag->items as $ch): $vencido = $ch['estado'] === 'cartera' && $ch['fecha_pago'] <= hoy(); ?>
        <tr><td class="td-titulo">N° <?= e($ch['numero']) ?></td><td data-label="Banco"><?= e($ch['banco']) ?></td><td data-label="Librador"><?= e($ch['librador']) ?><?= $ch['cliente'] ? '<br><small class="text-body-secondary">' . e($ch['cliente']) . '</small>' : '' ?></td>
            <td data-label="Pago" class="<?= $vencido ? 'text-success fw-bold' : '' ?>"><?= fecha($ch['fecha_pago']) ?><?= $vencido ? ' <small>(cobrable)</small>' : '' ?></td>
            <td class="num fw-semibold" data-label="Importe"><?= money($ch['importe']) ?></td><td data-label="Estado"><?= estado_badge($ch['estado']) ?></td>
            <td data-label="Destino"><small><?= e($ch['cuenta'] ?? $ch['proveedor'] ?? '') ?></small></td>
            <td class="td-acciones text-end">
                <?php if ($ch['estado'] === 'cartera' && puede('finanzas.crear')): ?>
                    <form method="post" action="<?= url('/finanzas/cheques/' . $ch['id'] . '/depositar') ?>" class="d-inline-flex gap-1" data-confirmar="¿Registrar el depósito del cheque?">
                        <?= csrf_campo() ?><select class="form-select form-select-sm" name="cuenta_id" aria-label="Cuenta"><?= opciones($cuentas, '') ?></select>
                        <button class="btn btn-sm btn-outline-success btn-touch">Depositar</button></form>
                <?php endif; ?>
                <?php if (in_array($ch['estado'], ['cartera', 'depositado', 'endosado'], true) && puede('finanzas.anular')): ?>
                    <form method="post" action="<?= url('/finanzas/cheques/' . $ch['id'] . '/rechazar') ?>" class="d-inline" data-confirmar="¿Marcar el cheque como RECHAZADO? Se debita del banco (si estaba depositado) y se carga la deuda al cliente." data-motivo>
                        <?= csrf_campo() ?><input type="hidden" name="gastos" value="0"><button class="btn btn-sm btn-outline-danger btn-touch">Rechazado</button></form>
                <?php endif; ?>
            </td></tr>
    <?php endforeach; ?>
    <?php if (!$pag->items): ?><tr><td colspan="8" class="text-center text-body-secondary py-4">No hay cheques.</td></tr><?php endif; ?></tbody>
</table>
</div></div>
<?= $pag->enlaces() ?>
<p class="small text-body-secondary mt-2">Los cheques se reciben en Cobranzas y se pueden entregar a proveedores (endoso) desde Compras › Pagos.</p>
