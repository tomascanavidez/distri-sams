<?php use App\Services\StockService; use App\Support\Html; ?>
<div class="encabezado"><h1>Kardex<?= $producto ? ' — ' . e($producto['descripcion']) : '' ?></h1><div class="acciones"><?php if (puede('stock.exportar')): ?><?= Html::exportar('/stock/movimientos') ?><?php endif; ?></div></div>
<form class="filtros row g-2 align-items-end" method="get" @elegido="$el.submit()">
    <div class="col-12 col-lg-4"><label class="form-label">Producto</label>
        <div class="autocompletar" x-data="autocompletar({ url: '/api/productos', nombre: 'producto_id', valor: '<?= (int) ($producto['id'] ?? 0) ?: '' ?>', texto: <?= e(json_encode($producto['descripcion'] ?? '')) ?>, enviar: true })">
            <input type="hidden" name="producto_id" :value="valor"><input type="search" class="form-control" x-model="q" @input="buscar()" @keydown="tecla($event)" placeholder="Todos los productos" autocomplete="off" @search="if (!q) { valor = ''; $el.form.submit(); }">
            <div class="autocompletar-lista" x-show="abierto && resultados.length" @click.outside="abierto = false" x-cloak><template x-for="(r, i) in resultados" :key="r.id"><button type="button" :class="{ activo: i === indice }" @click="elegir(r)" x-text="r.texto"></button></template></div>
        </div></div>
    <div class="col-6 col-lg-2"><label class="form-label" for="deposito_id">Depósito</label><select class="form-select" id="deposito_id" name="deposito_id" data-autoenviar><?= opciones($depositos, $_GET['deposito_id'] ?? '', 'Todos') ?></select></div>
    <div class="col-6 col-lg-2"><label class="form-label" for="tipo">Tipo</label><select class="form-select" id="tipo" name="tipo" data-autoenviar><?= opciones(StockService::TIPOS, $_GET['tipo'] ?? '', 'Todos') ?></select></div>
    <div class="col-6 col-lg-2"><label class="form-label" for="desde">Desde</label><input type="date" class="form-control" id="desde" name="desde" value="<?= e($_GET['desde'] ?? '') ?>"></div>
    <div class="col-6 col-lg-2"><label class="form-label" for="hasta">Hasta</label><input type="date" class="form-control" id="hasta" name="hasta" value="<?= e($_GET['hasta'] ?? '') ?>"></div>
</form>
<div class="card shadow-sm"><div class="card-body p-0 p-md-2">
<table class="table table-sm align-middle tabla-cards mb-0">
    <thead><tr><th>Fecha</th><th>Producto</th><th>Depósito</th><th>Movimiento</th><th>Detalle</th><th class="num">Cantidad</th><th class="num">Saldo</th><th>Usuario</th></tr></thead>
    <tbody><?php foreach ($pag->items as $m): ?>
        <tr><td data-label="Fecha"><?= fecha_hora($m['fecha']) ?></td><td class="td-titulo"><?= e($m['descripcion']) ?></td><td data-label="Depósito"><?= e($m['deposito']) ?></td>
            <td data-label="Movimiento"><?= e(StockService::TIPOS[$m['tipo']] ?? $m['tipo']) ?></td><td data-label="Detalle"><small><?= e($m['motivo']) ?><?= $m['lote'] ? ' · Lote ' . e($m['lote']) : '' ?></small></td>
            <td class="num fw-bold <?= $m['cantidad'] < 0 ? 'text-danger' : 'text-success' ?>" data-label="Cantidad"><?= ($m['cantidad'] > 0 ? '+' : '') . cant($m['cantidad']) ?></td>
            <td class="num" data-label="Saldo"><?= cant($m['saldo']) ?></td><td data-label="Usuario"><small><?= e($m['usuario']) ?></small></td></tr>
    <?php endforeach; ?>
    <?php if (!$pag->items): ?><tr><td colspan="8" class="text-center text-body-secondary py-4">Sin movimientos.</td></tr><?php endif; ?></tbody>
</table></div></div>
<?= $pag->enlaces() ?>
