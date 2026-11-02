<div class="encabezado"><h1>Hojas de ruta</h1><div class="acciones"><?php if (puede('reparto.crear')): ?><a href="<?= url('/reparto/nueva') ?>" class="btn btn-primary btn-touch"><i class="bi bi-plus-lg"></i> Nueva hoja de ruta</a><?php endif; ?></div></div>
<form class="filtros row g-2 align-items-end" method="get">
    <div class="col-6 col-md-3"><label class="form-label" for="estado">Estado</label><select class="form-select" id="estado" name="estado" data-autoenviar><?= opciones(['armado' => 'Armado', 'en_reparto' => 'En reparto', 'rendida' => 'Rendida', 'anulada' => 'Anulada'], $_GET['estado'] ?? '', 'Todos') ?></select></div>
    <div class="col-6 col-md-3"><label class="form-label" for="fecha">Fecha</label><input type="date" class="form-control" id="fecha" name="fecha" value="<?= e($_GET['fecha'] ?? '') ?>" onchange="this.form.submit()"></div>
</form>
<div class="card shadow-sm"><div class="card-body p-0 p-md-2">
<table class="table table-hover align-middle tabla-cards mb-0">
    <thead><tr><th>N°</th><th>Fecha</th><th>Chofer / vehículo</th><th>Zona</th><th class="num">Paradas</th><th class="num">Total pedidos</th><th>Estado</th><th></th></tr></thead>
    <tbody><?php foreach ($pag->items as $h): ?>
        <tr><td data-label="N°"><a class="fw-bold" href="<?= url('/reparto/' . $h['id']) ?>"><?= (int) $h['numero'] ?></a></td><td data-label="Fecha"><?= fecha($h['fecha']) ?></td>
            <td class="td-titulo"><?= e($h['chofer']) ?> <small class="fw-normal text-body-secondary"><?= e($h['patente']) ?></small></td><td data-label="Zona"><?= e($h['zona'] ?? 'Varias') ?></td>
            <td class="num" data-label="Paradas"><?= (int) $h['entregadas'] ?> / <?= (int) $h['paradas'] ?></td><td class="num" data-label="Total"><?= money($h['total']) ?></td><td data-label="Estado"><?= estado_badge($h['estado']) ?></td>
            <td class="td-acciones text-end text-nowrap"><?php if ($h['estado'] === 'en_reparto' && puede('reparto.editar')): ?><a class="btn btn-sm btn-success btn-touch" href="<?= url('/reparto/' . $h['id'] . '/rendicion') ?>"><i class="bi bi-clipboard-check"></i> Rendir</a><?php endif; ?>
                <a class="btn btn-sm btn-outline-primary btn-touch" href="<?= url('/reparto/' . $h['id']) ?>"><i class="bi bi-eye"></i></a></td></tr>
    <?php endforeach; ?>
    <?php if (!$pag->items): ?><tr><td colspan="8" class="text-center text-body-secondary py-4">No hay hojas de ruta.</td></tr><?php endif; ?></tbody>
</table></div></div>
<?= $pag->enlaces() ?>
