<div class="encabezado"><h1>Visitas de preventistas</h1></div>
<form class="filtros row g-2 align-items-end" method="get">
    <div class="col-6 col-md-3"><label class="form-label" for="desde">Desde</label><input type="date" class="form-control" id="desde" name="desde" value="<?= e($desde) ?>"></div>
    <div class="col-6 col-md-3"><label class="form-label" for="hasta">Hasta</label><input type="date" class="form-control" id="hasta" name="hasta" value="<?= e($hasta) ?>"></div>
    <div class="col-6 col-md-3"><label class="form-label" for="preventista_id">Preventista</label><select class="form-select" id="preventista_id" name="preventista_id"><?= opciones($preventistas, $_GET['preventista_id'] ?? '', 'Todos') ?></select></div>
    <div class="col-6 col-md-2"><label class="form-label" for="resultado">Resultado</label><select class="form-select" id="resultado" name="resultado"><?= opciones(['pedido' => 'Con pedido', 'sin_pedido' => 'Sin pedido'], $_GET['resultado'] ?? '', 'Todos') ?></select></div>
    <div class="col-12 col-md-1"><button class="btn btn-outline-primary w-100 btn-touch" aria-label="Filtrar"><i class="bi bi-search"></i></button></div>
</form>
<div class="row g-3 mb-3"><?php foreach ($resumen as $r): ?><div class="col-md-4"><div class="card kpi shadow-sm"><div class="card-body"><div class="kpi-etiqueta"><?= e($r['nombre'] ?? 'Sin preventista') ?></div><div class="kpi-valor"><?= (int) $r['visitas'] ?> visitas</div><small><?= (int) $r['con_pedido'] ?> con pedido (<?= $r['visitas'] ? round($r['con_pedido'] * 100 / $r['visitas']) : 0 ?> %)</small></div></div></div><?php endforeach; ?></div>
<div class="card shadow-sm"><div class="card-body p-0 p-md-2">
<table class="table align-middle tabla-cards mb-0">
    <thead><tr><th>Fecha</th><th>Cliente</th><th>Zona</th><th>Preventista</th><th>Resultado</th><th>Ubicación</th></tr></thead>
    <tbody><?php foreach ($pag->items as $v): ?>
        <tr><td data-label="Fecha"><?= fecha_hora($v['fecha']) ?></td><td class="td-titulo"><a href="<?= url('/clientes/' . $v['cliente_id']) ?>"><?= e($v['nombre_comercio']) ?></a></td><td data-label="Zona"><?= e($v['zona']) ?></td><td data-label="Preventista"><?= e($v['preventista']) ?></td>
            <td data-label="Resultado"><?= $v['resultado'] === 'pedido' ? '<a href="' . url('/pedidos/' . $v['pedido_id']) . '" class="badge text-bg-success text-decoration-none">Pedido N° ' . (int) $v['pedido_numero'] . ' · ' . money($v['pedido_total']) . '</a>' : '<span class="badge text-bg-secondary">Sin pedido: ' . e(str_replace('_', ' ', (string) $v['motivo'])) . '</span>' ?><?= $v['observaciones'] ? '<br><small>' . e($v['observaciones']) . '</small>' : '' ?></td>
            <td data-label="Ubicación"><?= $v['latitud'] ? '<a target="_blank" rel="noopener" href="' . e(link_maps(null, null, $v['latitud'], $v['longitud'])) . '"><i class="bi bi-geo-alt"></i> Ver</a>' : '—' ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$pag->items): ?><tr><td colspan="6" class="text-center text-body-secondary py-4">Sin visitas en el período.</td></tr><?php endif; ?></tbody>
</table></div></div>
<?= $pag->enlaces() ?>
