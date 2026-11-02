<?php use App\Support\Html; ?>
<div class="encabezado">
    <h1>Productos</h1>
    <div class="acciones">
        <?php if (puede('productos.exportar')): ?>
            <div class="dropdown">
                <button class="btn btn-outline-secondary btn-touch dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-download"></i> Exportar</button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="<?= url('/productos/exportar?formato=csv') ?>">Productos (CSV para reimportar)</a></li>
                    <li><a class="dropdown-item" href="<?= url('/productos/exportar?formato=xlsx') ?>">Lista de precios (Excel)</a></li>
                    <li><a class="dropdown-item" href="<?= url('/productos/exportar?formato=imprimir') ?>" target="_blank">Lista de precios (PDF / imprimir)</a></li>
                </ul>
            </div>
        <?php endif; ?>
        <?php if (puede('productos.crear')): ?><a href="<?= url('/productos/nuevo') ?>" class="btn btn-primary btn-touch d-none d-md-inline-flex"><i class="bi bi-plus-lg"></i> Nuevo producto</a><?php endif; ?>
    </div>
</div>

<form class="filtros row g-2 align-items-end" method="get">
    <div class="col-12 col-lg-4"><label class="form-label" for="q">Buscar</label>
        <input type="search" class="form-control" id="q" name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="Nombre, marca, código o código de barras" autocomplete="off"></div>
    <div class="col-6 col-lg-2"><label class="form-label" for="rubro_id">Rubro</label>
        <select class="form-select" id="rubro_id" name="rubro_id" data-autoenviar><?= opciones($rubros, $_GET['rubro_id'] ?? '', 'Todos') ?></select></div>
    <div class="col-6 col-lg-2"><label class="form-label" for="marca_id">Marca</label>
        <select class="form-select" id="marca_id" name="marca_id" data-autoenviar><?= opciones($marcas, $_GET['marca_id'] ?? '', 'Todas') ?></select></div>
    <div class="col-6 col-lg-2"><label class="form-label" for="lista_id">Precio de lista</label>
        <select class="form-select" id="lista_id" name="lista_id" data-autoenviar><?= opciones($listas, $_GET['lista_id'] ?? '') ?></select></div>
    <div class="col-6 col-lg-2"><label class="form-label" for="estado">Estado</label>
        <select class="form-select" id="estado" name="estado" data-autoenviar><?= opciones(['activos' => 'Activos', 'inactivos' => 'Inactivos', 'todos' => 'Todos'], $_GET['estado'] ?? 'activos') ?></select></div>
    <div class="col-6 col-lg-3"><div class="form-check"><input class="form-check-input" type="checkbox" id="stock_bajo" name="stock_bajo" value="1" <?= !empty($_GET['stock_bajo']) ? 'checked' : '' ?> data-autoenviar><label class="form-check-label" for="stock_bajo">Sólo stock bajo el mínimo</label></div></div>
    <div class="col-6 col-lg-2"><button class="btn btn-outline-primary w-100 btn-touch"><i class="bi bi-search"></i> Buscar</button></div>
</form>

<div class="card shadow-sm"><div class="card-body p-0 p-md-2">
<table class="table table-hover align-middle tabla-cards mb-0">
    <thead><tr>
        <th></th><?= $pag->th('codigo', 'Código') ?><?= $pag->th('descripcion', 'Descripción') ?><?= $pag->th('rubro', 'Rubro') ?>
        <?= $pag->th('costo', 'Costo', 'num') ?><?= $pag->th('precio', 'Precio', 'num') ?><?= $pag->th('stock', 'Stock', 'num') ?><th class="num">Disponible</th><th></th>
    </tr></thead>
    <tbody>
    <?php foreach ($pag->items as $p): $bajo = (float) $p['stock'] < (float) $p['stock_minimo']; ?>
        <tr>
            <td class="ocultar-movil" style="width:56px"><?php if ($p['foto']): ?><img src="<?= url('/uploads/productos/t_' . $p['foto']) ?>" class="foto-producto" alt="" loading="lazy" width="48" height="48"><?php else: ?><span class="foto-producto d-inline-flex align-items-center justify-content-center"><i class="bi bi-box-seam text-body-tertiary"></i></span><?php endif; ?></td>
            <td data-label="Código"><code><?= e($p['codigo']) ?></code></td>
            <td class="td-titulo"><a href="<?= url('/productos/' . $p['id']) ?>"><?= e($p['descripcion']) ?></a><?= (int) $p['activo'] ? '' : ' ' . estado_badge('inactivo') ?><div class="small text-body-secondary fw-normal"><?= e($p['marca'] ?? '') ?></div></td>
            <td data-label="Rubro"><?= e($p['rubro'] ?? '') ?></td>
            <td class="num" data-label="Costo"><?= money($p['costo']) ?></td>
            <td class="num fw-semibold" data-label="Precio"><?= money($p['precio']) ?></td>
            <td class="num <?= $bajo ? 'text-danger fw-bold' : '' ?>" data-label="Stock"><?= cant($p['stock']) ?><?= $bajo ? ' <i class="bi bi-exclamation-triangle" title="Bajo el mínimo"></i>' : '' ?></td>
            <td class="num" data-label="Disponible"><?= cant($p['disponible']) ?></td>
            <td class="td-acciones text-end text-nowrap">
                <a class="btn btn-sm btn-outline-primary btn-touch" href="<?= url('/productos/' . $p['id']) ?>"><i class="bi bi-eye"></i> Ver</a>
                <?php if (puede('productos.editar')): ?><a class="btn btn-sm btn-outline-secondary btn-touch" href="<?= url('/productos/' . $p['id'] . '/editar') ?>"><i class="bi bi-pencil"></i></a><?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$pag->items): ?><tr><td colspan="9" class="text-center text-body-secondary py-4">No se encontraron productos.</td></tr><?php endif; ?>
    </tbody>
</table>
</div></div>
<?= $pag->enlaces() ?>
<?php if (puede('productos.crear')): ?><a href="<?= url('/productos/nuevo') ?>" class="fab d-md-none" aria-label="Nuevo producto"><i class="bi bi-plus-lg"></i></a><?php endif; ?>
