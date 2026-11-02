<div class="encabezado"><h1>Historial de precios y costos</h1></div>
<form class="filtros row g-2 align-items-end" method="get">
    <div class="col-12 col-md-6"><label class="form-label" for="q">Producto</label><input type="search" class="form-control" id="q" name="q" value="<?= e($_GET['q'] ?? '') ?>"></div>
    <div class="col-6 col-md-3"><label class="form-label" for="desde">Desde</label><input type="date" class="form-control" id="desde" name="desde" value="<?= e($_GET['desde'] ?? '') ?>"></div>
    <div class="col-6 col-md-3"><button class="btn btn-outline-primary w-100 btn-touch"><i class="bi bi-search"></i> Filtrar</button></div>
</form>
<div class="card shadow-sm"><div class="card-body p-0 p-md-2">
<table class="table table-sm align-middle tabla-cards mb-0">
    <thead><tr><th>Fecha</th><th>Producto</th><th>Lista / costo</th><th class="num">Anterior</th><th class="num">Nuevo</th><th class="num">Var.</th><th>Motivo</th><th>Usuario</th></tr></thead>
    <tbody>
    <?php foreach ($pag->items as $h): $var = (float) $h['valor_anterior'] > 0 ? ((float) $h['valor_nuevo'] / (float) $h['valor_anterior'] - 1) * 100 : null; ?>
        <tr><td data-label="Fecha"><?= fecha_hora($h['fecha']) ?></td><td class="td-titulo"><a href="<?= url('/productos/' . $h['producto_id']) ?>"><?= e($h['descripcion']) ?></a></td><td data-label="Lista"><?= e($h['lista']) ?></td>
            <td class="num" data-label="Anterior"><?= money($h['valor_anterior']) ?></td><td class="num" data-label="Nuevo"><?= money($h['valor_nuevo']) ?></td>
            <td class="num <?= $var > 0 ? 'text-danger' : 'text-success' ?>" data-label="Variación"><?= $var === null ? '—' : ($var > 0 ? '+' : '') . num($var, 1) . ' %' ?></td>
            <td data-label="Motivo"><small><?= e($h['motivo']) ?></small></td><td data-label="Usuario"><small><?= e($h['usuario'] ?? '') ?></small></td></tr>
    <?php endforeach; ?>
    <?php if (!$pag->items): ?><tr><td colspan="8" class="text-center text-body-secondary py-4">Sin registros.</td></tr><?php endif; ?>
    </tbody>
</table>
</div></div>
<?= $pag->enlaces() ?>
