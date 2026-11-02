<?php use App\Services\CobranzaService; use App\Support\Html; ?>
<div class="encabezado">
    <h1>Cobranzas</h1>
    <div class="acciones">
        <?php if (puede('cobranzas.exportar')): ?><?= Html::exportar('/cobranzas/exportar') ?><?php endif; ?>
        <?php if (puede('cobranzas.crear')): ?><a href="<?= url('/cobranzas/nueva') ?>" class="btn btn-primary btn-touch"><i class="bi bi-plus-lg"></i> Nueva cobranza</a><?php endif; ?>
    </div>
</div>
<form class="filtros row g-2 align-items-end" method="get">
    <div class="col-12 col-lg-4"><label class="form-label" for="q">Cliente o N°</label><input type="search" class="form-control" id="q" name="q" value="<?= e($_GET['q'] ?? '') ?>"></div>
    <div class="col-6 col-lg-2"><label class="form-label" for="origen">Origen</label><select class="form-select" id="origen" name="origen" data-autoenviar><?= opciones(['cobranza' => 'Oficina', 'mostrador' => 'Mostrador', 'reparto' => 'Reparto'], $_GET['origen'] ?? '', 'Todos') ?></select></div>
    <div class="col-6 col-lg-2"><label class="form-label" for="desde">Desde</label><input type="date" class="form-control" id="desde" name="desde" value="<?= e($_GET['desde'] ?? '') ?>"></div>
    <div class="col-6 col-lg-2"><label class="form-label" for="hasta">Hasta</label><input type="date" class="form-control" id="hasta" name="hasta" value="<?= e($_GET['hasta'] ?? '') ?>"></div>
    <div class="col-6 col-lg-2"><button class="btn btn-outline-primary w-100 btn-touch"><i class="bi bi-search"></i> Filtrar</button></div>
</form>
<div class="card shadow-sm"><div class="card-body p-0 p-md-2">
<table class="table table-hover align-middle tabla-cards mb-0">
    <thead><tr><?= $pag->th('fecha', 'Fecha') ?><th>Recibo</th><?= $pag->th('cliente', 'Cliente') ?><th>Origen</th><th>Formas de pago</th><?= $pag->th('total', 'Total', 'num') ?><th>Estado</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($pag->items as $r): ?>
        <tr class="<?= $r['estado'] === 'anulado' ? 'opacity-75 text-decoration-line-through' : '' ?>">
            <td data-label="Fecha"><?= fecha_hora($r['fecha']) ?></td>
            <td data-label="Recibo"><a href="<?= url('/cobranzas/' . $r['id']) ?>"><?= e(CobranzaService::numero($r)) ?></a></td>
            <td class="td-titulo"><?= e($r['nombre_comercio']) ?></td>
            <td data-label="Origen"><?= e(['cobranza' => 'Oficina', 'mostrador' => 'Mostrador', 'reparto' => 'Reparto'][$r['origen']] ?? $r['origen']) ?></td>
            <td data-label="Formas"><?= e(implode(', ', array_map('forma_pago', explode(',', (string) $r['formas'])))) ?></td>
            <td class="num fw-semibold" data-label="Total"><?= money($r['total']) ?></td>
            <td data-label="Estado"><?= estado_badge($r['estado']) ?></td>
            <td class="td-acciones text-end"><a class="btn btn-sm btn-outline-primary btn-touch" href="<?= url('/cobranzas/' . $r['id']) ?>"><i class="bi bi-eye"></i></a></td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$pag->items): ?><tr><td colspan="8" class="text-center text-body-secondary py-4">No hay cobranzas.</td></tr><?php endif; ?>
    </tbody>
    <tfoot><tr class="fw-bold"><td colspan="5" class="text-end">Total cobrado</td><td class="num"><?= money($total) ?></td><td colspan="2"></td></tr></tfoot>
</table>
</div></div>
<?= $pag->enlaces() ?>
