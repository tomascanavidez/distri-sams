<?php
use App\Services\PrecioService;
use App\Services\StockService;
use App\Support\Html;

$costoBase = PrecioService::costoBase($p);
$stockTotal = array_sum(array_map(fn ($s) => $s['tipo'] === 'deposito' ? (float) $s['cantidad'] : 0, $stock));
?>
<div class="encabezado">
    <h1><?= e($p['descripcion']) ?> <?= (int) $p['activo'] ? '' : estado_badge('inactivo') ?></h1>
    <div class="acciones">
        <a href="<?= url('/productos') ?>" class="btn btn-outline-secondary btn-touch"><i class="bi bi-arrow-left"></i> Productos</a>
        <?php if (puede('productos.editar')): ?><a href="<?= url('/productos/' . $p['id'] . '/editar') ?>" class="btn btn-primary btn-touch"><i class="bi bi-pencil"></i> Editar</a><?php endif; ?>
        <?php if (puede('productos.anular')): ?><?= Html::botonPost('/productos/' . $p['id'] . '/estado', (int) $p['activo'] ? '<i class="bi bi-toggle-off"></i> Desactivar' : '<i class="bi bi-toggle-on"></i> Activar', 'btn-outline-secondary', (int) $p['activo'] ? '¿Desactivar el producto? Deja de aparecer para vender, pero se conserva su historial.' : null) ?><?php endif; ?>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card shadow-sm mb-3"><div class="card-body">
            <?php if ($p['foto']): ?><img src="<?= url('/uploads/productos/' . $p['foto']) ?>" alt="" class="img-fluid rounded mb-3 d-block mx-auto" style="max-height:220px" loading="lazy"><?php endif; ?>
            <div class="row g-2 ficha-dato">
                <div class="col-6"><small>Código</small><code><?= e($p['codigo']) ?></code></div>
                <div class="col-6"><small>Código de barras</small><?= e($p['codigo_barras'] ?: '—') ?></div>
                <div class="col-6"><small>Rubro</small><?= e($p['rubro'] ?? '—') ?></div>
                <div class="col-6"><small>Marca</small><?= e($p['marca'] ?? '—') ?></div>
                <div class="col-12"><small>Proveedor habitual</small><?= e($p['proveedor'] ?? '—') ?></div>
                <div class="col-6"><small>Último costo (neto)</small><?= money($p['costo']) ?></div>
                <div class="col-6"><small>Costo promedio</small><?= money($p['costo_promedio']) ?></div>
                <div class="col-6"><small>IVA</small><?= num($p['iva_alicuota'], 1) ?> %</div>
                <div class="col-6"><small>Imp. internos</small><?= money($p['impuesto_interno']) ?></div>
                <div class="col-6"><small>Stock total</small><strong class="<?= $stockTotal < (float) $p['stock_minimo'] ? 'text-danger' : '' ?>"><?= cant($stockTotal) ?> <?= e($p['unidad_base']) ?></strong></div>
                <div class="col-6"><small>Mín. / Máx.</small><?= cant($p['stock_minimo']) ?> / <?= cant($p['stock_maximo']) ?></div>
                <div class="col-6"><small>Ubicación</small><?= e($p['ubicacion'] ?: '—') ?></div>
                <div class="col-6"><small>Lotes</small><?= (int) $p['maneja_lotes'] ? 'Sí' : 'No' ?></div>
            </div>
        </div></div>
        <div class="card shadow-sm mb-3"><div class="card-body">
            <div class="card-titulo">Stock por depósito</div>
            <table class="table table-sm mb-0"><thead><tr><th>Depósito</th><th class="num">Físico</th><th class="num">Reservado</th><th class="num">Disponible</th></tr></thead><tbody>
            <?php foreach ($stock as $s): ?><tr><td><?= e($s['nombre']) ?></td><td class="num"><?= cant($s['cantidad']) ?></td><td class="num"><?= cant($s['reservado']) ?></td><td class="num fw-bold"><?= cant($s['cantidad'] - $s['reservado']) ?></td></tr><?php endforeach; ?>
            <?php if (!$stock): ?><tr><td colspan="4" class="text-body-secondary">Sin stock registrado.</td></tr><?php endif; ?>
            </tbody></table>
            <?php if ($lotes): ?>
                <div class="card-titulo mt-3">Lotes</div>
                <ul class="list-group list-group-flush small"><?php foreach ($lotes as $l): ?>
                    <li class="list-group-item px-0 d-flex justify-content-between"><span>Lote <?= e($l['lote']) ?> · <?= e($l['deposito']) ?><br><span class="<?= $l['vencimiento'] && $l['vencimiento'] < hoy() ? 'text-danger' : 'text-body-secondary' ?>">Vence <?= fecha($l['vencimiento']) ?: '—' ?></span></span><strong><?= cant($l['cantidad']) ?></strong></li>
                <?php endforeach; ?></ul>
            <?php endif; ?>
        </div></div>
    </div>

    <div class="col-lg-8">
        <div class="card shadow-sm mb-3"><div class="card-body">
            <div class="card-titulo">Precios por lista <small class="text-body-secondary fw-normal">(finales, IVA incluido, por <?= e(mb_strtolower($p['unidad_base'])) ?>)</small></div>
            <form method="post" action="<?= url('/productos/' . $p['id'] . '/precios') ?>">
                <?= csrf_campo() ?>
                <table class="table align-middle tabla-cards">
                    <thead><tr><th>Lista</th><th>Modo</th><th class="num">Margen %</th><th class="num">Precio unitario</th><?php foreach ($presentaciones as $pr) if ((float) $pr['factor'] != 1) echo '<th class="num">' . e($pr['nombre']) . ' (' . cant($pr['factor']) . ')</th>'; ?></tr></thead>
                    <tbody>
                    <?php foreach ($precios as $l):
                        $modo = $l['modo'] ?? 'margen';
                        $margenReal = PrecioService::margenDesdePrecio((float) $l['precio'], $costoBase, (float) $p['iva_alicuota'], (float) $p['impuesto_interno']);
                    ?>
                        <tr x-data="{ modo: '<?= e($modo) ?>' }">
                            <td class="td-titulo"><?= e($l['nombre']) ?></td>
                            <td data-label="Modo"><?php if (puede('productos.precios')): ?>
                                <select class="form-select form-select-sm" name="modo[<?= $l['lista_id'] ?>]" x-model="modo"><option value="margen">Por margen</option><option value="fijo">Precio fijo</option></select>
                                <?php else: ?><?= $modo === 'fijo' ? 'Fijo' : 'Margen' ?><?php endif; ?></td>
                            <td class="num" data-label="Margen %">
                                <?php if (puede('productos.precios')): ?>
                                    <input x-show="modo === 'margen'" class="form-control form-control-sm text-end" name="margen[<?= $l['lista_id'] ?>]" value="<?= $l['margen_porc'] !== null ? e(num($l['margen_porc'])) : '' ?>" placeholder="<?= e(num($l['margen_lista'])) ?> (lista)" inputmode="decimal" style="max-width:130px;margin-left:auto">
                                    <span x-show="modo === 'fijo'" class="text-body-secondary"><?= num($margenReal) ?> %</span>
                                <?php else: ?><?= num($margenReal) ?> %<?php endif; ?>
                            </td>
                            <td class="num fw-bold" data-label="Precio">
                                <?php if (puede('productos.precios')): ?>
                                    <span x-show="modo === 'margen'"><?= money($l['precio']) ?></span>
                                    <input x-show="modo === 'fijo'" class="form-control form-control-sm text-end" name="precio[<?= $l['lista_id'] ?>]" value="<?= e(num($l['precio'] ?? 0)) ?>" inputmode="decimal" style="max-width:140px;margin-left:auto">
                                <?php else: ?><?= money($l['precio']) ?><?php endif; ?>
                            </td>
                            <?php foreach ($presentaciones as $pr) if ((float) $pr['factor'] != 1) echo '<td class="num" data-label="' . e($pr['nombre']) . '">' . money((float) $l['precio'] * (float) $pr['factor']) . '</td>'; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if (puede('productos.precios')): ?><button class="btn btn-primary btn-touch" type="submit"><i class="bi bi-check-lg"></i> Guardar precios</button>
                <small class="text-body-secondary ms-2">Margen vacío = se usa el margen de la lista. Costo usado: <?= money($costoBase) ?> (<?= ajuste('costo_metodo') === 'promedio' ? 'promedio' : 'último' ?>).</small><?php endif; ?>
            </form>
            <?php if ($promos): ?><div class="alert alert-info mt-3 mb-0 py-2"><i class="bi bi-gift"></i> Promoción vigente: <?php foreach ($promos as $pr) echo e(cant($pr['cantidad_compra']) . ' + ' . cant($pr['cantidad_bonificada']) . ' ' . ($pr['descripcion'] ?? '')) . ' '; ?></div><?php endif; ?>
        </div></div>

        <div class="card shadow-sm mb-3"><div class="card-body">
            <div class="card-titulo d-flex justify-content-between">Últimos movimientos de stock <a class="small fw-normal" href="<?= url('/stock/movimientos?producto_id=' . $p['id']) ?>">Ver kardex completo</a></div>
            <table class="table table-sm tabla-cards mb-0">
                <thead><tr><th>Fecha</th><th>Tipo</th><th>Depósito</th><th class="num">Cantidad</th><th class="num">Saldo</th><th>Detalle</th></tr></thead>
                <tbody><?php foreach ($movimientos as $m): ?>
                    <tr><td class="td-titulo"><?= fecha_hora($m['fecha']) ?></td><td data-label="Tipo"><?= e(StockService::TIPOS[$m['tipo']] ?? $m['tipo']) ?></td><td data-label="Depósito"><?= e($m['deposito']) ?></td>
                        <td class="num <?= $m['cantidad'] < 0 ? 'text-danger' : 'text-success' ?>" data-label="Cantidad"><?= ($m['cantidad'] > 0 ? '+' : '') . cant($m['cantidad']) ?></td><td class="num" data-label="Saldo"><?= cant($m['saldo']) ?></td><td data-label="Detalle"><small><?= e($m['motivo']) ?></small></td></tr>
                <?php endforeach; ?>
                <?php if (!$movimientos): ?><tr><td colspan="6" class="text-body-secondary">Sin movimientos.</td></tr><?php endif; ?></tbody>
            </table>
        </div></div>

        <div class="card shadow-sm"><div class="card-body">
            <div class="card-titulo">Historial de costos y precios</div>
            <table class="table table-sm tabla-cards mb-0">
                <thead><tr><th>Fecha</th><th>Lista</th><th class="num">Anterior</th><th class="num">Nuevo</th><th class="num">Var.</th><th>Motivo</th><th>Usuario</th></tr></thead>
                <tbody><?php foreach ($historial as $h): $var = (float) $h['valor_anterior'] > 0 ? ((float) $h['valor_nuevo'] / (float) $h['valor_anterior'] - 1) * 100 : null; ?>
                    <tr><td class="td-titulo"><?= fecha_hora($h['fecha']) ?></td><td data-label="Lista"><?= e($h['lista'] ?? 'Costo') ?></td><td class="num" data-label="Anterior"><?= money($h['valor_anterior']) ?></td><td class="num" data-label="Nuevo"><?= money($h['valor_nuevo']) ?></td>
                        <td class="num" data-label="Variación"><?= $var === null ? '—' : num($var, 1) . ' %' ?></td><td data-label="Motivo"><small><?= e($h['motivo']) ?></small></td><td data-label="Usuario"><small><?= e($h['usuario'] ?? '') ?></small></td></tr>
                <?php endforeach; ?>
                <?php if (!$historial): ?><tr><td colspan="7" class="text-body-secondary">Sin cambios registrados.</td></tr><?php endif; ?></tbody>
            </table>
        </div></div>
    </div>
</div>
