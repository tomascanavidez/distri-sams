<?php
use App\Core\View;
use App\Support\Exportador;
use App\Support\Html;

[$tit, $grupo, $desc, $filtros] = $def;
$num = fn ($t) => in_array($t, ['money', 'num', 'cant', 'entero', 'porc'], true);
?>
<div class="encabezado"><h1><?= e($tit) ?></h1>
    <div class="acciones"><a href="<?= url('/reportes') ?>" class="btn btn-outline-secondary btn-touch"><i class="bi bi-arrow-left"></i> Reportes</a><?php if (puede('reportes.exportar')): ?><?= Html::exportar('/reportes/' . $clave) ?><?php endif; ?></div></div>
<p class="text-body-secondary"><?= e($desc) ?></p>
<?php if ($filtros): ?>
<form class="filtros row g-2 align-items-end" method="get">
    <?php if (in_array('fechas', $filtros, true)): ?>
        <div class="col-6 col-md-2"><label class="form-label" for="desde">Desde</label><input type="date" class="form-control" id="desde" name="desde" value="<?= e($_GET['desde'] ?? date('Y-m-01')) ?>"></div>
        <div class="col-6 col-md-2"><label class="form-label" for="hasta">Hasta</label><input type="date" class="form-control" id="hasta" name="hasta" value="<?= e($_GET['hasta'] ?? hoy()) ?>"></div>
    <?php endif; ?>
    <?php $agr = ['agrupar_ventas' => ['dia' => 'Día', 'mes' => 'Mes', 'cliente' => 'Cliente', 'producto' => 'Producto', 'rubro' => 'Rubro', 'marca' => 'Marca', 'zona' => 'Zona', 'preventista' => 'Preventista', 'tipo' => 'Tipo de comprobante'],
        'agrupar_cobranzas' => ['forma' => 'Forma de pago', 'dia' => 'Día', 'origen' => 'Origen'],
        'agrupar_rentabilidad' => ['producto' => 'Producto', 'rubro' => 'Rubro', 'cliente' => 'Cliente', 'mes' => 'Mes'],
        'agrupar_compras' => ['proveedor' => 'Proveedor', 'mes' => 'Mes'], 'agrupar_stock' => ['producto' => 'Producto', 'rubro' => 'Rubro']];
    foreach ($agr as $k => $ops): if (in_array($k, $filtros, true)): ?>
        <div class="col-6 col-md-3"><label class="form-label" for="agrupar">Agrupar por</label><select class="form-select" id="agrupar" name="agrupar" data-autoenviar><?= opciones($ops, $_GET['agrupar'] ?? array_key_first($ops)) ?></select></div>
    <?php endif; endforeach; ?>
    <?php if (in_array('dias', $filtros, true)): ?><div class="col-6 col-md-2"><label class="form-label" for="dias">Días sin venta</label><input class="form-control" id="dias" name="dias" inputmode="numeric" value="<?= (int) ($_GET['dias'] ?? 30) ?>"></div><?php endif; ?>
    <?php if (in_array('meses', $filtros, true)): ?><div class="col-6 col-md-2"><label class="form-label" for="meses">Meses</label><input class="form-control" id="meses" name="meses" inputmode="numeric" value="<?= (int) ($_GET['meses'] ?? 6) ?>"></div><?php endif; ?>
    <?php if (in_array('producto', $filtros, true)): ?>
        <div class="col-12 col-md-4"><label class="form-label">Producto</label>
            <div class="autocompletar" x-data="autocompletar({ url: '/api/productos', nombre: 'producto_id', valor: '<?= (int) ($producto['id'] ?? 0) ?: '' ?>', texto: <?= e(json_encode($producto['descripcion'] ?? '')) ?> })">
                <input type="hidden" name="producto_id" :value="valor"><input type="search" class="form-control" x-model="q" @input="buscar()" @keydown="tecla($event)" autocomplete="off" placeholder="Buscar producto">
                <div class="autocompletar-lista" x-show="abierto && resultados.length" @click.outside="abierto = false" x-cloak><template x-for="(r, i) in resultados" :key="r.id"><button type="button" :class="{ activo: i === indice }" @click="elegir(r)" x-text="r.texto"></button></template></div>
            </div></div>
    <?php endif; ?>
    <div class="col-6 col-md-2"><button class="btn btn-primary w-100 btn-touch"><i class="bi bi-funnel"></i> Ver</button></div>
</form>
<?php endif; ?>
<p class="small text-body-secondary"><?= e($res['subtitulo']) ?><?= !empty($res['nota']) ? ' · ' . e($res['nota']) : '' ?></p>
<?php if (!empty($res['grafico']) && count($res['grafico']['valores']) > 1): ?>
    <div class="card shadow-sm mb-3"><div class="card-body"><div class="grafico-contenedor"><canvas id="graficoReporte" aria-label="<?= e($res['grafico']['titulo']) ?>"></canvas></div></div></div>
<?php endif; ?>
<?php if (!empty($res['resumen'])): ?><div class="d-flex flex-wrap gap-2 mb-3"><?php foreach ($res['resumen'] as $r): ?><span class="badge text-bg-light border fs-6"><?= e($r['motivo']) ?>: <?= (int) $r['cantidad'] ?></span><?php endforeach; ?></div><?php endif; ?>
<div class="card shadow-sm"><div class="card-body p-0 p-md-2"><div class="table-responsive">
<table class="table table-sm table-hover align-middle tabla-cards mb-0">
    <thead><tr><?php foreach ($res['columnas'] as [$etq, $tipo]): ?><th class="<?= $num($tipo) ? 'num' : '' ?>"><?= e($etq) ?></th><?php endforeach; ?></tr></thead>
    <tbody>
    <?php foreach (array_slice($res['filas'], 0, 1000) as $f): $primero = true; ?>
        <tr><?php foreach ($res['columnas'] as $k => [$etq, $tipo]): ?>
            <td class="<?= $primero ? 'td-titulo' : '' ?> <?= $num($tipo) ? 'num' : '' ?> <?= $tipo === 'money' && (float) ($f[$k] ?? 0) < 0 ? 'text-danger' : '' ?>" <?= $primero ? '' : 'data-label="' . e($etq) . '"' ?>><?= e(Exportador::formatear($f[$k] ?? '', $tipo)) ?></td>
        <?php $primero = false; endforeach; ?></tr>
    <?php endforeach; ?>
    <?php if (!$res['filas']): ?><tr><td colspan="<?= count($res['columnas']) ?>" class="text-center text-body-secondary py-4">Sin datos para los filtros elegidos.</td></tr><?php endif; ?>
    </tbody>
    <?php if ($res['totales']): ?><tfoot><tr class="fw-bold"><?php foreach ($res['columnas'] as $k => [, $tipo]): ?><td class="<?= $num($tipo) ? 'num' : '' ?>"><?= e(Exportador::formatear($res['totales'][$k] ?? '', $tipo)) ?></td><?php endforeach; ?></tr></tfoot><?php endif; ?>
</table>
</div></div></div>
<?php if (count($res['filas']) > 1000): ?><p class="small text-body-secondary mt-2">Se muestran las primeras 1.000 filas. Exportá para ver todo.</p><?php endif; ?>
<?php if (!empty($res['grafico']) && count($res['grafico']['valores']) > 1): View::inicio('scripts'); ?>
<script src="<?= asset('assets/vendor/chartjs/chart.umd.min.js') ?>"></script>
<script>
(function () {
    var g = <?= json_encode($res['grafico'], JSON_UNESCAPED_UNICODE) ?>;
    var oscuro = document.documentElement.getAttribute('data-bs-theme') === 'dark';
    Chart.defaults.color = oscuro ? '#cfd3d7' : '#495057';
    var negativo = g.valores.some(function (v) { return v < 0; });
    new Chart(document.getElementById('graficoReporte'), {
        type: 'bar',
        data: { labels: g.etiquetas, datasets: [{ label: g.titulo, data: g.valores, borderRadius: 4, maxBarThickness: 34,
            backgroundColor: g.valores.map(function (v) { return v < 0 ? '#c62828' : '#b33a3a'; }) }] },
        options: { maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { callbacks: { label: function (c) { return g.titulo + ': ' + (Math.abs(c.parsed.y) >= 100 ? DT.dinero(c.parsed.y) : DT.numero(c.parsed.y)); } } } },
            scales: { x: { grid: { display: false }, ticks: { maxRotation: 45, autoSkip: true } }, y: { beginAtZero: !negativo, grid: { color: oscuro ? 'rgba(255,255,255,.08)' : 'rgba(0,0,0,.06)' },
                ticks: { callback: function (v) { return new Intl.NumberFormat('es-AR', { notation: 'compact' }).format(v); } } } } }
    });
})();
</script>
<?php View::fin(); endif; ?>
