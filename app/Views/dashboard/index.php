<?php
use App\Core\View;

$variacion = $kpi['ventas_mes_anterior'] > 0 ? ($kpi['ventas_mes'] / $kpi['ventas_mes_anterior'] - 1) * 100 : null;
$tarjetas = [
    ['Ventas de hoy', money($kpi['ventas_hoy']), 'cash-stack', 'primary', '/ventas?desde=' . hoy(), 'ventas.ver'],
    ['Ventas de la semana', money($kpi['ventas_semana']), 'calendar-week', 'primary', '/reportes/ventas', 'reportes.ver'],
    ['Ventas del mes', money($kpi['ventas_mes']), 'graph-up-arrow', 'success', '/reportes/ventas', 'reportes.ver'],
    ['Cobranzas de hoy', money($kpi['cobranzas_hoy']), 'cash-coin', 'success', '/cobranzas', 'cobranzas.ver'],
    ['Pedidos pendientes', (string) $kpi['pedidos_pendientes'], 'cart3', 'warning', '/pedidos?estado=pendientes', 'pedidos.ver'],
    ['Entregas en curso', $kpi['entregas_pendientes'] . ' <small class="fs-6 fw-normal">en ' . $kpi['hojas_en_reparto'] . ' HR</small>', 'truck', 'info', '/reparto?estado=en_reparto', 'reparto.ver'],
    ['Saldo en caja', money($kpi['saldo_caja']), 'safe', 'secondary', '/caja', 'caja.ver'],
    ['Deuda vencida clientes', money($kpi['deuda_vencida']), 'exclamation-octagon', 'danger', '/clientes/saldos', 'clientes.ver'],
];
?>
<div class="encabezado">
    <h1>Panel principal</h1>
    <div class="acciones">
        <?php if (puede('ventas.crear')): ?><a href="<?= url('/ventas/pos') ?>" class="btn btn-primary btn-touch"><i class="bi bi-upc-scan"></i> Mostrador</a><?php endif; ?>
        <?php if (puede('pedidos.crear')): ?><a href="<?= url('/pedidos/nuevo') ?>" class="btn btn-outline-primary btn-touch"><i class="bi bi-cart-plus"></i> Nuevo pedido</a><?php endif; ?>
    </div>
</div>

<?php if ($kpi['pedidos_revision'] > 0 && puede('pedidos.ver')): ?>
    <a href="<?= url('/pedidos?revision=1') ?>" class="alert alert-warning d-flex align-items-center gap-2 text-decoration-none">
        <i class="bi bi-phone fs-4"></i>
        <div><strong><?= $kpi['pedidos_revision'] ?> pedido(s) de la app</strong> quedaron para revisión (stock, precio o crédito). Tocá para revisarlos.</div>
    </a>
<?php endif; ?>

<div class="row g-3 mb-3">
    <?php foreach ($tarjetas as [$etiqueta, $valor, $icono, $color, $enlace, $perm]): $tag = puede($perm) ? 'a' : 'div'; ?>
        <div class="col-6 col-xl-3">
            <<?= $tag ?> <?= $tag === 'a' ? 'href="' . url($enlace) . '"' : '' ?> class="card kpi shadow-sm text-decoration-none text-reset">
                <div class="card-body d-flex justify-content-between align-items-start gap-2">
                    <div class="min-w-0">
                        <div class="kpi-etiqueta"><?= e($etiqueta) ?></div>
                        <div class="kpi-valor text-truncate"><?= $valor ?></div>
                        <?php if ($etiqueta === 'Ventas del mes' && $variacion !== null): ?>
                            <small class="<?= $variacion >= 0 ? 'text-success' : 'text-danger' ?>"><i class="bi bi-arrow-<?= $variacion >= 0 ? 'up' : 'down' ?>"></i> <?= num(abs($variacion), 1) ?> % vs. mes anterior</small>
                        <?php endif; ?>
                    </div>
                    <i class="bi bi-<?= $icono ?> kpi-icono text-<?= $color ?>"></i>
                </div>
            </<?= $tag ?>>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-8">
        <div class="card shadow-sm h-100"><div class="card-body">
            <div class="card-titulo">Ventas de los últimos 30 días</div>
            <div class="grafico-contenedor"><canvas id="graficoDias" aria-label="Ventas por día"></canvas></div>
        </div></div>
    </div>
    <div class="col-lg-4">
        <div class="card shadow-sm h-100"><div class="card-body">
            <div class="card-titulo">Ventas del mes por zona</div>
            <div class="grafico-contenedor"><canvas id="graficoZonas" aria-label="Ventas por zona"></canvas></div>
        </div></div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card shadow-sm h-100"><div class="card-body">
            <div class="card-titulo d-flex justify-content-between"><span><i class="bi bi-trophy text-warning"></i> Productos más vendidos (mes)</span></div>
            <?php if (!$topProductos): ?><p class="text-body-secondary mb-0">Sin ventas este mes.</p><?php else: ?>
            <table class="table table-sm tabla-cards mb-0">
                <thead><tr><th>Producto</th><th class="num">Unidades</th><th class="num">Total</th></tr></thead>
                <tbody><?php foreach ($topProductos as $p): ?>
                    <tr><td class="td-titulo"><?= enlace_si('productos.ver', '/productos/' . $p['id'], $p['descripcion']) ?></td>
                        <td class="num" data-label="Unidades"><?= cant($p['unidades']) ?></td>
                        <td class="num" data-label="Total"><?= money($p['total']) ?></td></tr>
                <?php endforeach; ?></tbody>
            </table><?php endif; ?>
        </div></div>
    </div>
    <div class="col-lg-6">
        <div class="card shadow-sm h-100"><div class="card-body">
            <div class="card-titulo"><i class="bi bi-star text-warning"></i> Mejores clientes (mes)</div>
            <?php if (!$topClientes): ?><p class="text-body-secondary mb-0">Sin ventas este mes.</p><?php else: ?>
            <table class="table table-sm tabla-cards mb-0">
                <thead><tr><th>Cliente</th><th class="num">Compras</th><th class="num">Total</th></tr></thead>
                <tbody><?php foreach ($topClientes as $c): ?>
                    <tr><td class="td-titulo"><?= enlace_si('clientes.ver', '/clientes/' . $c['id'], $c['nombre_comercio']) ?></td>
                        <td class="num" data-label="Compras"><?= (int) $c['comprobantes'] ?></td>
                        <td class="num" data-label="Total"><?= money($c['total']) ?></td></tr>
                <?php endforeach; ?></tbody>
            </table><?php endif; ?>
        </div></div>
    </div>
    <div class="col-lg-4">
        <div class="card shadow-sm h-100"><div class="card-body">
            <div class="card-titulo"><i class="bi bi-box-seam text-danger"></i> Stock bajo el mínimo</div>
            <?php if (!$stockBajo): ?><p class="text-body-secondary mb-0">Todo en orden.</p><?php else: ?>
            <ul class="list-group list-group-flush">
                <?php foreach ($stockBajo as $p): ?>
                    <li class="list-group-item px-0 d-flex justify-content-between gap-2">
                        <?= enlace_si('productos.ver', '/productos/' . $p['id'], $p['descripcion'], 'text-truncate') ?>
                        <span class="text-nowrap"><strong class="text-danger"><?= cant($p['stock']) ?></strong> / <?= cant($p['stock_minimo']) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php if (puede('compras.crear')): ?><a href="<?= url('/compras/sugerencia') ?>" class="btn btn-sm btn-outline-primary mt-2">Ver sugerencia de compra</a><?php endif; ?>
            <?php endif; ?>
        </div></div>
    </div>
    <div class="col-lg-4">
        <div class="card shadow-sm h-100"><div class="card-body">
            <div class="card-titulo"><i class="bi bi-hourglass-split text-warning"></i> Próximos a vencer</div>
            <?php if (!$vencimientos): ?><p class="text-body-secondary mb-0">No hay vencimientos próximos.</p><?php else: ?>
            <ul class="list-group list-group-flush">
                <?php foreach ($vencimientos as $v): ?>
                    <li class="list-group-item px-0">
                        <div class="d-flex justify-content-between gap-2"><span class="text-truncate"><?= e($v['descripcion']) ?></span><strong><?= cant($v['cantidad']) ?></strong></div>
                        <small class="<?= (int) $v['dias'] < 0 ? 'text-danger fw-bold' : 'text-body-secondary' ?>">
                            Vence <?= fecha($v['vencimiento']) ?> (<?= (int) $v['dias'] < 0 ? 'vencido' : 'en ' . (int) $v['dias'] . ' días' ?>) · Lote <?= e($v['lote']) ?>
                        </small>
                    </li>
                <?php endforeach; ?>
            </ul><?php endif; ?>
        </div></div>
    </div>
    <div class="col-lg-4">
        <div class="card shadow-sm h-100"><div class="card-body">
            <div class="card-titulo"><i class="bi bi-person-exclamation text-danger"></i> Clientes con deuda vencida</div>
            <?php if (!$deudores): ?><p class="text-body-secondary mb-0">Ningún cliente con deuda vencida.</p><?php else: ?>
            <ul class="list-group list-group-flush">
                <?php foreach ($deudores as $d): ?>
                    <li class="list-group-item px-0 d-flex justify-content-between gap-2">
                        <span class="text-truncate"><?= enlace_si('clientes.ver', '/clientes/' . $d['id'] . '/cuenta', $d['nombre_comercio']) ?><br><small class="text-body-secondary"><?= e($d['zona'] ?? '') ?></small></span>
                        <span class="saldo-deuda text-nowrap"><?= money($d['vencido']) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul><?php endif; ?>
        </div></div>
    </div>
</div>

<?php View::inicio('scripts'); ?>
<script src="<?= asset('assets/vendor/chartjs/chart.umd.min.js') ?>"></script>
<script>
(function () {
    var oscuro = document.documentElement.getAttribute('data-bs-theme') === 'dark';
    var texto = oscuro ? '#cfd3d7' : '#495057';
    var grilla = oscuro ? 'rgba(255,255,255,.08)' : 'rgba(0,0,0,.06)';
    Chart.defaults.color = texto;
    Chart.defaults.font.family = 'system-ui, -apple-system, "Segoe UI", Roboto, sans-serif';
    var dias = <?= json_encode($serieDias, JSON_UNESCAPED_UNICODE) ?>;
    new Chart(document.getElementById('graficoDias'), {
        type: 'bar',
        data: { labels: dias.map(function (d) { return d.dia; }),
            datasets: [{ label: 'Ventas', data: dias.map(function (d) { return d.total; }), backgroundColor: '#b33a3a', borderRadius: 4, maxBarThickness: 22 }] },
        options: { maintainAspectRatio: false, plugins: { legend: { display: false },
                tooltip: { callbacks: { label: function (c) { return DT.dinero(c.parsed.y); } } } },
            scales: { x: { grid: { display: false }, ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 10 } },
                y: { grid: { color: grilla }, ticks: { callback: function (v) { return '$ ' + new Intl.NumberFormat('es-AR', { notation: 'compact' }).format(v); } } } } }
    });
    var zonas = <?= json_encode($porZona, JSON_UNESCAPED_UNICODE) ?>;
    new Chart(document.getElementById('graficoZonas'), {
        type: 'bar',
        data: { labels: zonas.map(function (z) { return z.zona; }),
            datasets: [{ label: 'Ventas', data: zonas.map(function (z) { return Number(z.total); }), backgroundColor: '#5c6f82', borderRadius: 4, maxBarThickness: 26 }] },
        options: { indexAxis: 'y', maintainAspectRatio: false, plugins: { legend: { display: false },
                tooltip: { callbacks: { label: function (c) { return DT.dinero(c.parsed.x); } } } },
            scales: { x: { grid: { color: grilla }, ticks: { callback: function (v) { return new Intl.NumberFormat('es-AR', { notation: 'compact' }).format(v); } } }, y: { grid: { display: false } } } }
    });
})();
</script>
<?php View::fin(); ?>
