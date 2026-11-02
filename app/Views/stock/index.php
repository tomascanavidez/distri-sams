<?php use App\Support\Html; ?>
<div class="encabezado"><h1>Existencias</h1>
    <div class="acciones"><?php if (puede('stock.exportar')): ?><?= Html::exportar('/stock/exportar') ?><?php endif; ?>
        <?php if (puede('stock.crear')): ?><a href="<?= url('/stock/ajustes/nuevo') ?>" class="btn btn-outline-primary btn-touch"><i class="bi bi-sliders"></i> Ajuste</a><a href="<?= url('/stock/transferencias/nueva') ?>" class="btn btn-outline-primary btn-touch"><i class="bi bi-arrow-left-right"></i> Transferencia</a><?php endif; ?></div></div>
<form class="filtros row g-2 align-items-end" method="get">
    <div class="col-12 col-lg-4"><label class="form-label" for="q">Producto</label><input type="search" class="form-control" id="q" name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="Nombre, código o código de barras"></div>
    <div class="col-6 col-lg-2"><label class="form-label" for="deposito_id">Depósito</label><select class="form-select" id="deposito_id" name="deposito_id" data-autoenviar><?= opciones($depositos, $_GET['deposito_id'] ?? '', 'Todos los depósitos') ?></select></div>
    <div class="col-6 col-lg-2"><label class="form-label" for="rubro_id">Rubro</label><select class="form-select" id="rubro_id" name="rubro_id" data-autoenviar><?= opciones($rubros, $_GET['rubro_id'] ?? '', 'Todos') ?></select></div>
    <div class="col-6 col-lg-2"><label class="form-label" for="situacion">Situación</label><select class="form-select" id="situacion" name="situacion" data-autoenviar><?= opciones(['bajo' => 'Bajo el mínimo', 'sin' => 'Sin stock disponible', 'exceso' => 'Sobre el máximo'], $_GET['situacion'] ?? '', 'Todos') ?></select></div>
    <div class="col-6 col-lg-2"><button class="btn btn-outline-primary w-100 btn-touch"><i class="bi bi-search"></i> Buscar</button></div>
</form>
<div class="alert alert-light border d-flex justify-content-between flex-wrap"><span><i class="bi bi-cash-stack"></i> Inventario valorizado a costo <?= ajuste('costo_metodo') === 'promedio' ? 'promedio' : 'último' ?>:</span><strong class="fs-5"><?= money($valor) ?></strong></div>
<div class="card shadow-sm"><div class="card-body p-0 p-md-2">
<table class="table table-hover align-middle tabla-cards mb-0">
    <thead><tr><th>Código</th><?= $pag->th('descripcion', 'Producto') ?><?= $pag->th('ubicacion', 'Ubic.') ?><?= $pag->th('fisico', 'Físico', 'num') ?><th class="num">Reservado</th><th class="num">Disponible</th><th class="num">Mín. / Máx.</th><?= $pag->th('valor', 'Valor', 'num') ?><th></th></tr></thead>
    <tbody><?php foreach ($pag->items as $s): $disp = (float) $s['fisico'] - (float) $s['reservado']; $bajo = $disp < (float) $s['stock_minimo']; ?>
        <tr><td data-label="Código"><code><?= e($s['codigo']) ?></code></td><td class="td-titulo"><a href="<?= url('/productos/' . $s['id']) ?>"><?= e($s['descripcion']) ?></a><div class="small fw-normal text-body-secondary"><?= e($s['rubro']) ?></div></td>
            <td data-label="Ubicación"><?= e($s['ubicacion']) ?></td><td class="num" data-label="Físico"><?= cant($s['fisico']) ?></td><td class="num" data-label="Reservado"><?= cant($s['reservado']) ?></td>
            <td class="num fw-bold <?= $bajo ? 'text-danger' : 'text-success' ?>" data-label="Disponible"><?= cant($disp) ?> <small class="fw-normal text-body-secondary"><?= e($s['unidad_base']) ?></small></td>
            <td class="num" data-label="Mín./Máx."><?= cant($s['stock_minimo']) ?> / <?= cant($s['stock_maximo']) ?></td><td class="num" data-label="Valor"><?= money((float) $s['fisico'] * (float) $s['costo']) ?></td>
            <td class="td-acciones text-end"><a class="btn btn-sm btn-outline-secondary btn-touch" href="<?= url('/stock/movimientos?producto_id=' . $s['id']) ?>" title="Kardex"><i class="bi bi-list-columns"></i> Kardex</a></td></tr>
    <?php endforeach; ?>
    <?php if (!$pag->items): ?><tr><td colspan="9" class="text-center text-body-secondary py-4">Sin resultados.</td></tr><?php endif; ?></tbody>
</table></div></div>
<?= $pag->enlaces() ?>
