<div class="encabezado"><h1>Importar / exportar productos</h1></div>
<div class="row g-3">
    <?php if (puede('productos.crear')): ?>
    <div class="col-lg-7"><div class="card shadow-sm h-100"><div class="card-body">
        <div class="card-titulo"><i class="bi bi-upload"></i> Importar desde CSV</div>
        <p>El archivo se procesa por <strong>código interno</strong>: si el código existe se actualiza, si no, se crea. Los rubros y marcas que no existan se crean solos.</p>
        <ul class="small text-body-secondary">
            <li>Columnas: <code><?= e(implode(';', App\Controllers\ProductosController::COLUMNAS_CSV)) ?></code></li>
            <li>Separador <code>;</code> (como guarda Excel en español). Decimales con coma.</li>
            <li>Presentaciones: <code>Display:12:EAN|Caja:240:EAN</code> (nombre:unidades:código de barras).</li>
            <li>Costo neto sin IVA por unidad base; IVA: 21, 10,5 o 0.</li>
        </ul>
        <form method="post" action="<?= url('/productos/importar') ?>" enctype="multipart/form-data" class="d-flex flex-column flex-md-row gap-2">
            <?= csrf_campo() ?>
            <input type="file" class="form-control" name="archivo" accept=".csv,text/csv" required>
            <button class="btn btn-primary btn-touch text-nowrap" type="submit"><i class="bi bi-upload"></i> Importar</button>
        </form>
        <a class="btn btn-link px-0 mt-2" href="<?= url('/productos/plantilla-csv') ?>"><i class="bi bi-file-earmark-arrow-down"></i> Descargar plantilla de ejemplo</a>
        <?php if ($resultado): ?>
            <div class="alert alert-<?= $resultado['errores'] ? 'warning' : 'success' ?> mt-3 mb-0">
                <strong><?= (int) $resultado['creados'] ?></strong> creados, <strong><?= (int) $resultado['actualizados'] ?></strong> actualizados.
                <?php if ($resultado['errores']): ?><ul class="mb-0 mt-2"><?php foreach (array_slice($resultado['errores'], 0, 30) as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul><?php endif; ?>
            </div>
        <?php endif; ?>
    </div></div></div>
    <?php endif; ?>
    <?php if (puede('productos.exportar')): ?>
    <div class="col-lg-5"><div class="card shadow-sm h-100"><div class="card-body">
        <div class="card-titulo"><i class="bi bi-download"></i> Exportar</div>
        <div class="d-grid gap-2 lista-acciones-grandes">
            <a class="btn btn-outline-primary" href="<?= url('/productos/exportar?formato=csv') ?>"><i class="bi bi-filetype-csv"></i> Productos completos (CSV, reimportable)</a>
            <a class="btn btn-outline-primary" href="<?= url('/productos/exportar?formato=xlsx') ?>"><i class="bi bi-file-earmark-excel"></i> Lista de precios (Excel)</a>
            <a class="btn btn-outline-primary" href="<?= url('/productos/exportar?formato=pdf') ?>"><i class="bi bi-file-earmark-pdf"></i> Lista de precios (PDF)</a>
        </div>
    </div></div></div>
    <?php endif; ?>
</div>
